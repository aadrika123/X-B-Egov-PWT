<?php

namespace App\Http\Controllers\Property;

use App\BLL\Property\Akola\GetHoldingDuesV2;
use App\BLL\Property\Akola\PostPropPaymentV2;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payment\IciciPaymentController;
use App\Http\Requests\Property\ReqPayment;
use App\Models\Property\PropIciciPaymentsRequest;
use App\Models\Property\PropIciciPaymentsResponse;
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

    public function __construct(iSafRepository $safRepo)
    {
        $this->_safRepo = $safRepo;
        $this->_HoldingTaxController = App::makeWith(HoldingTaxController::class, ["iSafRepository" => app(iSafRepository::class)]);
        $this->_IciciPaymentController = App::makeWith(IciciPaymentController::class);
        $this->_callbackUrl = Config::get("payment-constants.PROPERTY_FRONT_URL");
        $this->_PropIciciPaymentsRequest = new PropIciciPaymentsRequest();
        $this->_PropIciciPaymentsRespone = new PropIciciPaymentsResponse();
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
            // $user = authUser($request);
            $demandsResponse = $this->_HoldingTaxController->getHoldingDues($request);
            if (!$demandsResponse->original["status"]) {
                return $demandsResponse;
            }
            $demand = (object)$demandsResponse->original["data"];
            
            if ($demand["payableAmt"]<=0) {
                throw new Exception("Deamnd Amount is 0 ");
            }
            $payableAmt = $demand["payableAmt"];
            $arrear = $demand["arrear"];
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
                "callbackUrl" => $this->_callbackUrl,
                // "userId" => $user->id,
                // "userType"  => $user->user_type,
                // "auth"  => $user
            ]);
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
}
