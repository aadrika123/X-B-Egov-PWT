<?php

namespace App\Http\Controllers\Property;

use App\BLL\Payment\PineLabPayment;
use App\BLL\Property\Akola\GetHoldingDuesV2;
use App\BLL\Property\Akola\PostPropPaymentV2;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\IciciPaymentController;
use App\Http\Requests\Property\ReqPayment;
use App\Models\Property\PropIciciPaymentsRequest;
use App\Models\Property\PropIciciPaymentsResponse;
use App\Models\Property\PropPinelabPaymentsRequest;
use App\Models\Property\PropPinelabPaymentsResponse;
use App\Repository\Common\CommonFunction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CitizenHoldingController extends Controller
{
    private $_HoldingTaxController;
    private $_IciciPaymentController;
    private $_callbackUrl;
    private $_PropIciciPaymentsRequest;
    private $_PropIciciPaymentsRespone;
    protected $_safRepo;
    protected $_PropPinelabPaymentsRequest ;
    protected $_PropPinelabPaymentsResponse ;
    protected $_PineLabPayment ;
    protected $_COMONFUNCTION;

    public function __construct(iSafRepository $safRepo)
    {
        $this->_safRepo = $safRepo;
        $this->_HoldingTaxController = App::makeWith(HoldingTaxController::class, ["iSafRepository" => app(iSafRepository::class)]);
        $this->_IciciPaymentController = App::makeWith(IciciPaymentController::class);
        $this->_callbackUrl = Config::get("payment-constants.PROPERTY_FRONT_URL");
        $this->_PropIciciPaymentsRequest = new PropIciciPaymentsRequest();
        $this->_PropIciciPaymentsRespone = new PropIciciPaymentsResponse();
        $this->_PropPinelabPaymentsRequest = new PropPinelabPaymentsRequest();
        $this->_PropPinelabPaymentsResponse = new PropPinelabPaymentsResponse();
        $this->_PineLabPayment =  new PineLabPayment;
        $this->_COMONFUNCTION = new CommonFunction();
    }

    public function getHoldingDues(Request $request)
    {
        return $this->_HoldingTaxController->getHoldingDues($request);
    }

    public function ICICPaymentRequest(Request $request)
    {
        $validater = Validator::make(
            $request->all(),
            [
                "propId" => "required|digits_between:1,9223372036854775807",
                'paymentType' => 'required|In:isFullPayment,isArrearPayment,isPartPayment',
                'paidAmount' => 'nullable|required_if:paymentType,==,isPartPayment|numeric',
            ]
        );
        if ($validater->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validater->errors()
            ]);
        }
        try {
            $reqData  = $this->_PropIciciPaymentsRequest->where('prop_id', $request['propId'])
            ->where('status', 1)
            ->orderby("created_at","DESC")
            ->first();
            $diffInMin = Carbon::parse(Carbon::parse())->diffInMinutes($reqData->created_at??null);
            if($reqData && $diffInMin < 5 && !Config::get("sms-constants.sms_test"))
            {
                // throw new Exception("Please Wait ".(5-$diffInMin)." Minutes");
            }
            $user = Auth()->user()??null;
            $isCitizenUserType = $user ? $this->_COMONFUNCTION->checkUsersWithtocken("active_citizens") : true;
            $demandsResponse = $this->_HoldingTaxController->getHoldingDues($request);
            if (!$demandsResponse->original["status"]) {
                return $demandsResponse;
            }
            $demand = (object)$demandsResponse->original["data"];
            
            if ($demand["payableAmt"]<=0) {
                throw new Exception("Deamnd Amount is 0 ");
            }
            if (!$demand["isOldTranClear"]) {
                throw new Exception("Please waite for previous transaction clearance ");
            }
            $payableAmt = $demand["payableAmt"];
            $arrear = $demand["arrear"]+($demand["arrearMonthlyPenalty"]??0);
            if ($request->paymentType != "isPartPayment") {
                $request->merge(["paidAmount" => $request->paymentType == "isFullPayment" ? $payableAmt : $arrear]);
            }
            if(round($request->paidAmount) > round($payableAmt))
            {
                throw new Exception("Can not pay advance amount throw online");
            }

            $newReqs = new ReqPayment($request->all());
            $newReqs->merge([
                "id"         => $request->propId,
                "paymentMode" => "ONLINE",
            ]);
            $postPropPayment = new PostPropPaymentV2($newReqs);
            $postPropPayment->_propCalculation = $demandsResponse;
            $postPropPayment->chakPayentAmount();

            $previousInterest = $demand["previousInterest"] ?? 0;
            $arrearDemand = collect($demand["demandList"])->where("fyear", "<", getFY());
            $arrearTotaTax = $arrearDemand->sum("total_tax");
            $penalty = $arrearDemand->sum("monthlyPenalty");
            $totalaAreaDemand = $previousInterest + $arrearTotaTax + $penalty;
            $rebats = collect($demandData["rebates"]??[]);
            $rebatsAmt =  $rebats->sum("rebates_amt");
            $isApplicable = $request->paymentType=="isFullPayment" || ( $request->paymentType!="isFullPayment" && round($request->paidAmount) > $demand["payableAmt"]) ? true : false;
            $rebatsAmt = 0;
            $rebats =  $postPropPayment->testSpecialRebates($demand,$request->paidAmount);
            if($isApplicable)
            {
                $rebatsAmt = roundFigure(collect($rebats)->where("is_applicable",true)->sum("rebates_amt"));
            }
            $paidTotalExemptedGeneralTax = $postPropPayment->testArmforceRebat();
            $firstQuaterRebats = collect($demand["QuarterlyRebates"]??[]);
            $firstQuaterRebatsAmt =  $request->paymentType=="isFullPayment" ? $firstQuaterRebats->sum("rebates_amt"):0;

            $request->merge([
                "rebate" =>$rebatsAmt,   
                "amount" => round($request->paidAmount - $rebatsAmt - $paidTotalExemptedGeneralTax -$firstQuaterRebatsAmt),
                "id"    => $request->propId,
                "ulbId" => $demand["basicDetails"]["ulb_id"],
                "demandList" => $demand["demandList"],
                "fromFyear" => collect($demand['demandList'])->first()['fyear'],
                "toFyear" => collect($demand['demandList'])->last()['fyear'],
                "demandAmt" => $demand['grandTaxes']['balance'],
                "arrearSettled" => $demand['arrear'],
                "workflowId" => $demand["basicDetails"]["workflowId"],
                "moduleId" => $demand["basicDetails"]["moduleId"],
                "moduleId" => $demand["basicDetails"]["moduleId"],
                "callbackUrl" => $request->callbackUrl ? $request->callbackUrl : $this->_callbackUrl,
                // "userId" => $user->id,
                // "userType"  => $user->user_type,
                // "auth"  => $user
            ]); #$request->propId!=9
            if( $request->propId!=9 && !Config::get("sms-constants.sms_test"))
            {
                throw new Exception("Payment Gateway temporary disabled due to maintainable");
            }
            if(!$isCitizenUserType)
            {
                $request->merge(["userId"=>$user->id]);
            }
            if($isCitizenUserType && $user)
            {
                $request->merge(["CitizenId"=>$user->id]);
            }
            $respons = $this->_IciciPaymentController->getReferalUrl($request);
            if (!$respons->original["status"]) {
                throw new Exception("Payment Not Initiate");
            }
            $plainUrl = $respons->original["message"]["plainUrl"];
            $respons = $respons->original["data"];

            $request->merge([
                "encryptUrl" => $respons["encryptUrl"],
                "reqRefNo"  => $respons["req_ref_no"]
            ]);
            $respons["propId"] = $request->propId;
            $respons["plainUrl"] = $plainUrl;
            $respons["paidAmount"] = $request->paidAmount;
            $respons["amount"] = $request->amount;

            DB::beginTransaction();
            $respons["requestId"] = $this->_PropIciciPaymentsRequest->store($request);
            DB::commit();

            $respons = collect($respons)->only(
                [
                    "encryptUrl",
                    // "plainUrl",
                    "propId",
                    "paidAmount",
                    "amount",
                    "requestId",
                ]
            );
            return responseMsgs(true, "request Initiated", $respons, "phc1.1", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), $request->all(), "phc1.1", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    public function ICICPaymentResponse(Request $req)
    {
        $mHoldingTaxController = new HoldingTaxController($this->_safRepo);
        $jsonData = json_encode($req->all());
        $a = Storage::disk('public')->put($req['reqRefNo'] . '.json', $jsonData);

        $reqData  = $this->_PropIciciPaymentsRequest->where('req_ref_no', $req['reqRefNo'])
            ->where('payment_status', 0)
            ->first();
              
        if (collect($reqData)->isEmpty())
            throw new Exception("No Transaction Found");

        $reqData->update(['payment_status' => 1,"is_manual_update"=>$req->has("isManualUpdate")?$req->isManualUpdate:false]);

        $newReqs = new ReqPayment([
            "paymentType" => $reqData->tran_type,
            "paidAmount" => $reqData->payable_amount ,
            "id"         => $reqData->prop_id,
            "paymentMode" => "ONLINE",
        ]);
        if(isset($req->TrnDate))
        {
            $newReqs->merge([
                "TrnDate"=>$req->TrnDate,
            ]);
        }
        $reqDataRequest = json_decode($reqData->request,true);
        if(isset($reqDataRequest["userId"]))
        {
            $newReqs->merge([
                "userId"=>$reqDataRequest["userId"],
                "deviceType"=>$reqDataRequest["deviceType"]??null,
            ]);
        }
        if(isset($reqDataRequest["CitizenId"]))
        {
            $newReqs->merge([
                "CitizenId"=>$reqDataRequest["CitizenId"],
                "isCitizen"=>true,
            ]);
        }
        if(isset($reqDataRequest["auth"]))
        {
            $newReqs->merge([
                "userType"=>$reqDataRequest["auth"]["user_type"]??"",
            ]);
        }
        
        $data = $mHoldingTaxController->offlinePaymentHoldingV2($newReqs);

        $mReqs = [
            "tran_id"       => $data->original['data']['transactionId'] ?? null,
            "tran_no"       => $data->original['data']['TransactionNo'] ?? null,
            "payment_mode"  => $req['PayMode'],
            "tran_date"     => $req['TrnDate'],
            "status"        => $req['Status'],
            "response_code" => $req['ResponseCode'],
            "initiate_date" => $req['InitiateDT'],
            "tran_amount"   => $req['TranAmt'],
            "base_amount"   => $req['BaseAmt'],
            "proc_fee"      => $req['ProcFees'],
            "s_tax"         => $req['STax'],
            "m_sgst"        => $req['M_SGST'],
            "m_cgst"        => $req['M_CGST'],
            "m_utgst"       => $req['M_UTGST'],
            "m_stcess"      => $req['M_STCESS'],
            "m_ctcess"      => $req['M_CTCESS'],
            "m_igst"        => $req['M_IGST'],
            "gst_state"     => $req['GSTState'],
            "billing_state" => $req['BillingState'],
            "remarks"       => $req['Remarks'],
            "hash_value"    => $req['HashVal'],
            "req_ref_no"    => $req['reqRefNo'],
            "response_json" => $jsonData,
        ];
        $this->_PropIciciPaymentsRespone->store($mReqs);

        return $data;
    }

    public function ICICPaymentFailSuccess(Request $request)
    {
        $reqData  = $this->_PropIciciPaymentsRequest->where('req_ref_no', $request['reqRefNo'])
            ->where('payment_status', 0)
            ->first();
              
        if (collect($reqData)->isEmpty()){
            throw new Exception("No Transaction Found");
        }
        if ($request->Status == 'SUCCESS' || $request->ResponseCode == 'E000'){
            $responsData =  $this->ICICPaymentResponse($request);  
            $respons =  $responsData->original["data"];
            $respons["bankResponse"]=$request->Status;
            if(!$responsData->original["status"])
            {
                throw new Exception($responsData->original["message"]) ;
            }            
            return responseMsgs(true, "", $respons, "1", "1.0", "", "", $request->deviceId ?? "");         
        }
        try{
            DB::beginTransaction();
            $reqData->update(['payment_status' => 2,"is_manual_update"=>$request->isManualUpdate]);
            DB::commit();
            $respons = ["bankResponse"=>$request->Status,"TransactionNo"=>"","transactionId"=>""];
            return responseMsgs(true, "", $respons, "1", "1.0", "", "", $request->deviceId ?? "");
        }
        catch(Exception $e)
        {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }

    public function testIcic(Request $req)
    {
        try{         
            $respons=  $this->ICICPaymentResponse($req);
            return($respons);
        }
        catch (Exception $e) {
            return responseMsgs(false,$e->getMessage(), "", "011604", "2.0", "", "POST", $req->deviceId ?? "");
        }
    }

    public function pinLabInitPement(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'propId' => 'required|integer',
                'paymentType' => 'required|In:isFullPayment,isArrearPayment,isPartPayment',
                'paidAmount' => 'nullable|required_if:paymentType,==,isPartPayment|integer',
                'paymentMode' => 'required|string'
            ]
        );

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try{
            $user = Auth()->user();
            $billRefNo = null;

            if ($request->paymentType == 'isFullPayment')
            {
                $request->merge(['isArrear' => false]);
            }
            elseif ($request->paymentType == 'isArrearPayment')
            {
                $request->merge(['isArrear' => true]);
            }
            else{
                $request->merge(['isArrear' => false]);
            }
            $demandsResponse = $this->getHoldingDues($request);
            if (!$demandsResponse->original["status"]) {
                return $demandsResponse;
            }
            $demand = (object)$demandsResponse->original["data"];
            
            if ($demand["payableAmt"]<=0) {
                throw new Exception("Deamnd Amount is 0 ");
            }

            $payableAmt = $demand["payableAmt"];
            $arrear = round($demand["arrear"]+($demand["arrearMonthlyPenalty"]??0));
            if ($request->paymentType != "isPartPayment") {
                $request->merge(["paidAmount" => $request->paymentType == "isFullPayment" ? $payableAmt : $arrear]);
            }

            $newReqs = new ReqPayment($request->all());
            $newReqs->merge([
                "id"         => $request->propId,
            ]);
            $postPropPayment = new PostPropPaymentV2($newReqs);
            $postPropPayment->_propCalculation = $demandsResponse;
            $postPropPayment->chakPayentAmount();
            $OneMinLate = Carbon::now()->addMinutes("-1")->format("Y-m-d H:i:s");            
            $chack = $this->_PropPinelabPaymentsRequest::where("prop_id",$request->propId)
                    ->where("created_at",">=",$OneMinLate)
                    ->OrderBy("id","DESC")
                    ->first();           
            if($chack)
            {
                throw new Exception("Please Try After ".(60- Carbon::now()->diffInSeconds(Carbon::parse($chack->created_at)))." Seconds");
            }
            $pineLabParams = (object)[
                "workflowId"    => $demand["basicDetails"]["workflowId"],
                "amount"        => $request->paidAmount,
                "moduleId"      => $demand["basicDetails"]["moduleId"],
                "applicationId" =>$request->propId,
                "paymentType" => "Property"
            ];
            $billRefNo = $this->_PineLabPayment->initiatePayment($pineLabParams);
            $request->merge([
                "resRefNo" => $billRefNo
            ]);
            $request->merge([
                "amount" => $request->paidAmount,
                "id"    => $request->propId,
                "ulbId" => $demand["basicDetails"]["ulb_id"],
                "demandList" => $demand["demandList"],
                "fromFyear" => collect($demand['demandList'])->first()['fyear'],
                "toFyear" => collect($demand['demandList'])->last()['fyear'],
                "demandAmt" => $demand['grandTaxes']['balance'],
                "arrearSettled" => $demand['arrear'],
                "workflowId" => $demand["basicDetails"]["workflowId"],
                "moduleId" => $demand["basicDetails"]["moduleId"],
                "userId" => $user->id,
                "tranType"=>"Property",
            ]);
            
            $respons["propId"] = $request->propId;
            $respons["paidAmount"] = $request->paidAmount;
            $respons["resRefNo"] = $request->resRefNo;

            DB::beginTransaction();
            $respons["requestId"] = $this->_PropPinelabPaymentsRequest->store($request);            
            DB::commit();

            $respons = collect($respons)->only(
                [
                    "resRefNo",
                    "propId",
                    "paidAmount",
                    "requestId",
                ]
            );
            return responseMsgs(true, "request Initiated", $respons, "phc1.1", "1.0", "", "POST", $request->deviceId ?? "");
        }
        catch(Exception $e)
        {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }

    public function pinLabResponse(Request $request)
    {
        $mHoldingTaxController = new HoldingTaxController($this->_safRepo);
        $validated = Validator::make(
            $request->all(),
            [       
                "paidAmount" =>"required|numeric" ,       
                'Detail.BillingRefNo' => 'required|string',
                'Response.ResponseMsg' => 'required|string',
                'Response.ResponseCode' => 'required',
            ]
        );

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try{
            $responStatusArr = $request->Response;
            $reqRefNo = $request->Detail["BillingRefNo"];            
            $reqData  = $this->_PropPinelabPaymentsRequest->where('bill_ref_no', $reqRefNo)
                        ->where('payment_status', 0)
                        ->orderBy("id","DESC")
                        ->first();              
            if (collect($reqData)->isEmpty())
            {
                throw new Exception("No Transaction Found");
            }
            
            if($reqData->payable_amount != $request->paidAmount)
            {
                throw new Exception("payble Amount Missmatch");
            }
            $newReqs = new ReqPayment([
                "paymentType" => $reqData->payment_type,
                "paidAmount" => $reqData->payable_amount,
                "id"         => $reqData->prop_id,
                "paymentMode" => $reqData->payment_mode,
            ]);
            $responsData = [                
                "requestId"  => $reqData->id,
                "reqRefNo"     => $reqRefNo,
                "safId"        => $reqData->saf_id ,
                "propId"        => $reqData->prop_id ,
                "paymentMode"   => $reqData->payment_mode,
                "responseCode" => $responStatusArr["ResponseCode"],
                "responseSms"   => $responStatusArr["ResponseMsg"],
                "paidAmount"   => $request['paidAmount'],
                "requst"      => $request->all(),
                "userId"       => Auth()->user()?Auth()->user()->id:"",
                "ipAddress"        => $request->ipAddress,
            ];
            if(in_array($responStatusArr["ResponseCode"],[0]))
            { 
                $data = $mHoldingTaxController->offlinePaymentHoldingV2($newReqs);
                if($data->original['status'])
                {
                    $reqData->update(['payment_status' => 1]);
                    $responsData["tranId"]=$data->original['data']['transactionId'] ?? null;
                    $responsData = (object) $responsData;
                    $this->_PropPinelabPaymentsResponse->store($responsData);
                }
                return $data;
            }
            throw new Exception("Payment Is Failed");

            
        }
        catch(Exception $e)
        {
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }

    /**
     * | Update Icici Payment Manually
     */

    public function getIciciPendingPaymentList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [       
                "fromDate" =>"nullable|date" ,       
                'uptoDate' => 'nullable|date',
                'reqRefNo' => 'nullable|string',
                'applicationNo' => 'nullable|string',
                'appType' => 'nullable|in:Saf,Property',
                'PaymentStatus' => 'nullable|in:Success,Failed,Payment initiated but not done',
            ]
        );

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try{
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = $zoneId = $appType =  $reqRefNo = $applicationNo = null;
            $PaymentStatus = 0;
            if($request->fromDate)
            {
                $fromDate = $request->fromDate;
            }
            if($request->uptoDate)
            {
                $uptoDate = $request->uptoDate;
            }
            if($request->appType)
            {
                $appType = $request->appType;
            }
            if($request->wardId)
            {
                $wardId = $request->wardId;
            }
            if($request->zoneId)
            {
                $zoneId = $request->zoneId;
            }
            if($request->PaymentStatus)
            {
                $PaymentStatus = $this->getIntPaymentStatus($request->PaymentStatus);
            }
            if($request->reqRefNo)
            {
                $reqRefNo = $request->reqRefNo;
            }
            if($request->applicationNo)
            {
                $applicationNo = $request->applicationNo;
            }
            $data = PropIciciPaymentsRequest::select(
                DB::raw("
                prop_icici_payments_requests.id,prop_icici_payments_requests.req_ref_no, prop_icici_payments_requests.saf_id,prop_icici_payments_requests.prop_id,
                prop_icici_payments_requests.tran_type,prop_icici_payments_requests.demand_amt,prop_icici_payments_requests.payable_amount,
                prop_icici_payments_requests.payment_status,prop_icici_payments_requests.created_at,
                prop_icici_payments_requests.from_fyear,prop_icici_payments_requests.to_fyear,
                case when prop_icici_payments_requests.saf_id is not null then 'saf' else 'property' end as app_type,
                case when prop_icici_payments_requests.saf_id is not null then prop_icici_payments_requests.saf_id else prop_icici_payments_requests.prop_id end as app_id,
                case when prop_icici_payments_requests.saf_id is not null then saf.saf_no else prop.holding_no end as app_no,
                case when prop_icici_payments_requests.saf_id is not null then saf.ward_name else prop.ward_name end as ward_name,
                case when prop_icici_payments_requests.saf_id is not null then saf.zone_name else prop.zone_name end as zone_name
                    ")
            )
            ->leftJoin(DB::raw("
                    (
                        select prop_icici_payments_requests.id,prop_properties.holding_no,prop_properties.property_no,
                            zone_masters.zone_name,ulb_ward_masters.ward_name,
                            prop_properties.zone_mstr_id,prop_properties.ward_mstr_id
                        from prop_icici_payments_requests
                        join prop_properties on prop_properties.id = prop_icici_payments_requests.prop_id
                        left join zone_masters on zone_masters.id = prop_properties.zone_mstr_id
                        left join ulb_ward_masters on ulb_ward_masters.id = prop_properties.ward_mstr_id                        
                    )prop
            "),"prop.id","prop_icici_payments_requests.id")
            ->leftJoin(DB::raw("
                    (
                        select prop_icici_payments_requests.id,saf.holding_no,saf.saf_no,
                            zone_masters.zone_name,ulb_ward_masters.ward_name,
                            saf.zone_mstr_id,saf.ward_mstr_id
                        from prop_icici_payments_requests
                        join (
                            (
                                select id,zone_mstr_id,ward_mstr_id,saf_no,holding_no
                                from prop_active_safs
                            )
                            union(
                                select id,zone_mstr_id,ward_mstr_id,saf_no,holding_no
                                from prop_rejected_safs
                            )
                            union(
                                select id,zone_mstr_id,ward_mstr_id,saf_no,holding_no
                                from prop_safs
                            )
                        )saf on saf.id = prop_icici_payments_requests.saf_id
                        left join zone_masters on zone_masters.id = saf.zone_mstr_id
                        left join ulb_ward_masters on ulb_ward_masters.id = saf.ward_mstr_id	 
                        
                )saf
            "),"saf.id","prop_icici_payments_requests.id")            
            ->where("prop_icici_payments_requests.status",1)
            ->where("prop_icici_payments_requests.payment_status",$PaymentStatus);

            switch($appType)
            {
                case 'Saf' : $data->whereNotNull("prop_icici_payments_requests.saf_id");
                            break;
                case 'Property' : $data->whereNotNull("prop_icici_payments_requests.prop_id");
                            break;
            }
            if($reqRefNo)
            {
                $data->where("prop_icici_payments_requests.req_ref_no",$reqRefNo);
            }
            if($wardId)
            {
                $data->where(function($query) use($wardId){
                    $query->OrWhere("saf.ward_mstr_id",$wardId)
                    ->OrWhere("prop.ward_mstr_id",$wardId);
                });
            }
            if($zoneId)
            {
                $data->where(function($query) use($zoneId){
                    $query->OrWhere("saf.zone_mstr_id",$zoneId)
                    ->OrWhere("prop.zone_mstr_id",$zoneId);
                });
            }
            if($applicationNo)
            {
                $data->where(function($query)use($applicationNo){
                    $query->where("saf.saf_no",$applicationNo)
                          ->OrWhere("prop.holding_no",$applicationNo);
                });
            }
            else{
                $data->whereBetween(DB::raw("CAST(prop_icici_payments_requests.created_at AS DATE)"),[$fromDate,$uptoDate]);
            }
            $data->OrderBy("prop_icici_payments_requests.id","ASC")
                ->OrderBy("prop_icici_payments_requests.prop_id","ASC");
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => collect($paginator->items())->map(function ($val) {
                    // $val->demand_list =  $val->demand_list ? json_decode($val->demand_list):$val->demand_list;
                    // $val->request =  $val->request ? json_decode($val->request):$val->request;
                    return $val;
                }),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        }
        catch(Exception $e)
        {
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }

    private function getIntPaymentStatus($PaymentStatus)
    {   
        $status = 0;
        switch($PaymentStatus)
        {
            case "Success" : $status = 1;
                            break;
            case "Failed" : $status = 2;
                            break;
        }
        return $status;
    }

    public function updateIciciPendingPayment(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [       
                "id" =>"required",
            ]
        );

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try{
            $users = Auth()->user();
            $requestId = $request->id;
            $reqData = PropIciciPaymentsRequest::find($requestId);
            if(!$reqData)
            {
                throw New Exception("Request Data Not Found");
            }
            if($reqData->payment_status==1)
            {
                throw New Exception("Payment Already cleared");
            }
            $testPayment = checkPaymentStatus($reqData->req_ref_no);
            if(!$testPayment["status"]){
                throw new Exception($testPayment["errors"]);
            }
            $respons = (object)$testPayment["response"];
            $PaymentStatus = $respons->status??null;             
            $mReqs = [
                "RT"            => $respons->RT            ?? null,
                "IcId"          => $respons->IcId          ?? null,
                "TrnId"         => $respons->TrnId         ?? null,
                "PayMode"       => $respons->PaymentMode   ?? null,
                "TrnDate"       => $respons->trandate      ?? null,
                "SettleDT"      => $respons->sdt            ?? null,
                "Status"        => strtoupper($respons->status??null) ?? null,
                "InitiateDT"    => $respons->InitiateDT    ?? null,
                "TranAmt"       => $respons->amount       ?? null,
                "BaseAmt"       => $respons->amount       ?? null,
                "ProcFees"      => $respons->ProcFees      ?? null,
                "STax"          => $respons->STax          ?? null,
                "M_SGST"        => $respons->M_SGST        ?? null,
                "M_CGST"        => $respons->M_CGST        ?? null,
                "M_UTGST"       => $respons->M_UTGST       ?? null,
                "M_STCESS"      => $respons->M_STCESS      ?? null,
                "M_CTCESS"      => $respons->M_CTCESS      ?? null,
                "M_IGST"        => $respons->M_IGST        ?? null,
                "GSTState"      => $respons->GSTState      ?? null,
                "BillingState"  => $respons->BillingState  ?? null,
                "Remarks"       => $respons->Remarks       ?? null,
                "HashVal"       => $respons->HashVal       ?? null,
                "reqRefNo"      => $reqData->req_ref_no    ?? null,
                "isManualUpdate"=> true,
                "reason"        => $respons->reason??"",
            ];
            $newRequest = new Request($mReqs);
            return $this->ICICPaymentFailSuccess($newRequest);
        }
        catch(Exception $e)
        {
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }
}
