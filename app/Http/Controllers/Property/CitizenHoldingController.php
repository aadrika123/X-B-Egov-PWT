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
            $payableAmt = $demand["payableAmt"];
            $arrear = $demand["arrear"]+($demand["arrearMonthlyPenalty"]??0);
            if ($request->paymentType != "isPartPayment") {
                $request->merge(["paidAmount" => $request->paymentType == "isFullPayment" ? $payableAmt : $arrear]);
            }

            $newReqs = new ReqPayment($request->all());
            $newReqs->merge([
                "id"         => $request->propId,
                "paymentMode" => "ONLINE",
            ]);
            $postPropPayment = new PostPropPaymentV2($newReqs);
            $postPropPayment->_propCalculation = $demandsResponse;
            $postPropPayment->chakPayentAmount();

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
                "moduleId" => $demand["basicDetails"]["moduleId"],
                "callbackUrl" => $request->callbackUrl ? $request->callbackUrl : $this->_callbackUrl,
                // "userId" => $user->id,
                // "userType"  => $user->user_type,
                // "auth"  => $user
            ]);
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
            $respons = $respons->original["data"];
            $request->merge([
                "encryptUrl" => $respons["encryptUrl"],
                "reqRefNo"  => $respons["req_ref_no"]
            ]);
            $respons["propId"] = $request->propId;
            $respons["paidAmount"] = $request->paidAmount;

            DB::beginTransaction();
            $respons["requestId"] = $this->_PropIciciPaymentsRequest->store($request);
            DB::commit();

            $respons = collect($respons)->only(
                [
                    "encryptUrl",
                    "propId",
                    "paidAmount",
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

        $reqData->update(['payment_status' => 1]);

        $newReqs = new ReqPayment([
            "paymentType" => $reqData->tran_type,
            "paidAmount" => $reqData->payable_amount,
            "id"         => $reqData->prop_id,
            "paymentMode" => "ONLINE",
        ]);
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

    public function testIcic(Request $req)
    {
        try{         
            $respons=  $this->ICICPaymentResponse($req);
            return($respons);
        }
        catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(),$e->getLine(),$e->getFile()], "", "011604", "2.0", "", "POST", $req->deviceId ?? "");
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

    public function getPendingPaymentList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [       
                "fromDate" =>"nullable|date" ,       
                'uptoDate' => 'nullable|date',
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

        }
        catch(Exception $e)
        {
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }
}
