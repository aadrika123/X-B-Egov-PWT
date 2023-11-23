<?php

namespace App\Http\Controllers\Payment;

use App\BLL\Payment\GetRefUrl;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\ActiveSafController;
use App\Http\Controllers\Property\HoldingTaxController;
use App\Http\Requests\Property\ReqPayment;
use App\MicroServices\IdGeneration;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\ApiMaster;
use App\Models\Payment\IciciPaymentReq;
use App\Models\Payment\IciciPaymentResponse;
use App\Models\Payment\PinelabPaymentReq;
use App\Models\Payment\PinelabPaymentResponse;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Repository\Trade\TradeCitizen;
use App\Repository\Water\Concrete\WaterNewConnection;
use Carbon\Carbon;
use Exception;
use Hamcrest\Core\HasToString;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


/**
 * | Created On - 30-09-2023
 * | Author - Sam kerketta
 * | Status-Open
 */

class IciciPaymentController extends Controller
{

    /**
     * | Generation of Referal url for payment for Testing for ICICI payent gateway
        | Serila No :
        | Under Con
     */
    public function getReferalUrl(Request $req)
    {

        $req->request->add([
            "callbackUrl" => "https://modernulb.com/citizen/property/payment-status",
            "moduleId"    => 1,
        ]);

        $validated = Validator::make(
            $req->all(),
            [
                "workflowId"    => "nullable|int",
                "amount"        => "required|min:1",
                "id"            => "required",
                "callbackUrl"   => "required",
                "moduleId"      => "required"
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $getRefUrl          = new GetRefUrl();
            $mIciciPaymentReq   = new IciciPaymentReq();
            $url                = $getRefUrl->generateRefUrl($req);
            $paymentReq = [
                "user_id"           => $req->auth->id ?? $req->userId,
                "workflow_id"       => $req->workflowId ?? 0,
                "req_ref_no"        => $getRefUrl->_refNo,
                "amount"            => $req->amount,
                "application_id"    => $req->id,
                "module_id"         => $req->moduleId,
                "ulb_id"            => $req->ulbId ?? 2,
                "referal_url"       => $url['encryptUrl'],
                "call_back_url"     => $req->callbackUrl
            ];
            $mIciciPaymentReq->create($paymentReq);
            $returnDetails = [
                "encryptUrl" => $url['encryptUrl'],
                "req_ref_no" => $getRefUrl->_refNo
            ];
            return responseMsgs(true,  ["plainUrl" => $url['plainUrl'], "req_ref_no" => $getRefUrl->_refNo], $returnDetails);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }


    /**
     * | Get Webhook data for icici payament 
        | Serial No :
        | Under Con
     */
    public function getWebhookData(Request $req)
    {
        $mIciciPaymentReq = new IciciPaymentReq();
        $mIciciPaymentRes = new IciciPaymentResponse();
        $mApiMaster = new ApiMaster();
        $getRefUrl = new GetRefUrl();

        try {
            # Save the data in file 
            $webhoohEncripted   = $req->getContent();
            $webhookData        = $getRefUrl->decryptWebhookData($webhoohEncripted);
            $webhookDataInArray = json_decode(json_encode($webhookData), true);
            Storage::disk('public')->put('icici/webhook/' . ("encripted" . $webhookData->TrnId) . '.json', $webhoohEncripted);
            Storage::disk('public')->put('icici/webhook/' . $webhookData->TrnId . '.json', json_encode($webhookData));
            $refNo = $webhookData->Remarks;
            $refNo = explode('~', $refNo, 2);
            $refNo = $refNo['0'];
            $webhookDataInArray['reqRefNo'] = $refNo;

            # Get the payamen request
            $paymentReqsData = $mIciciPaymentReq->findByReqRefNoV3($refNo)->first();
            if (!$paymentReqsData) {
                throw new Exception("Payment request dont exist for $refNo");
            }

            if ($webhookData->Status == 'SUCCESS' || $webhookData->ResponseCode == 'E000') {
                // $updReqs = [
                //     'res_ref_no'        => $refNo,
                //     'payment_status'    => 1
                // ];
                // $paymentReqsData->update($updReqs);                 // Table Updation
                // $resPayReqs = [
                //     "payment_req_id"    => $paymentReqsData->id,
                //     "req_ref_id"        => $reqRefNo,
                //     "res_ref_id"        => $resRefNo,
                //     "icici_signature"   => $req->signature,
                //     "payment_status"    => 1
                // ];
                // $mIciciPaymentRes->create($resPayReqs);             // Response Data 


                // ❗❗ Pending for Module Specific Table Updation / Dont user to transfer data to module ❗❗
                switch ($paymentReqsData->module_id) {
                    case ('5'):
                        # For advertisment
                        $id = 4;                                                                            // Static
                        $endPoint = $mApiMaster->getApiEndpoint($id);
                        $reqResponse = Http::withHeaders([
                            "api-key" => "eff41ef6-d430-4887-aa55-9fcf46c72c99"
                        ])->post($endPoint->end_point, $webhookDataInArray);
                        $reqResponse;
                        break;
                    case ('1'):
                        # For Property
                        $id = 5;                                                                            // Static
                        $endPoint = $mApiMaster->getApiEndpoint($id);
                        $reqResponse = Http::withHeaders([
                            "api-key" => "eff41ef6-d430-4887-aa55-9fcf46c72c99"
                        ])->post($endPoint->end_point, $webhookDataInArray);
                        $reqResponse;
                        break;
                }
            }
            return responseMsgs(true, "Data Received Successfully", []);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }


    /**
     * | Collect callback url details for payment
        | Working  
        | Use in case of webhook not used
     */
    public function getCallbackDetial(Request $req)
    {
        try {
            # Save the callback data
            $mIciciPaymentReq = new IciciPaymentReq();
            Storage::disk('public')->put('icici/callback/' . $req->Unique_Ref_Number . '.json', json_encode($req->all()));

            # Check if the payament is success 
            if ($req->Response_Code == "E000") {
                # Check the transaction initials
                $paymentReqsData = $mIciciPaymentReq->findByReqRefNoV2($req->ReferenceNo);
                if (!$paymentReqsData) {
                    # Redirect to the error page
                    $erroData = [
                        "redirectUrl" => "https://modernulb.com/citizen"
                    ];
                    return view('icic_payment_erro', $erroData);
                }
                # Redirect to the desired url 
                $refData = [
                    "callBack" => $paymentReqsData->call_back_url
                ];
                return view('icici_payment_call_back', $refData);
            } else {
                $paymentReqsData = $mIciciPaymentReq->findByReqRefNoV2($req->ReferenceNo);
                if (!$paymentReqsData) {
                    # Redirect to the error page
                    $erroData = [
                        "redirectUrl" => "https://modernulb.com/citizen"
                    ];
                    return view('icic_payment_erro', $erroData);
                }
                # Redirect to the desired url 
                $erroData = [
                    "redirectUrl" => $paymentReqsData->call_back_url
                ];
                return view('icic_payment_erro', $erroData);
            }
        } catch (Exception $e) {
            $erroData = [
                "redirectUrl" => "https://modernulb.com/citizen"
            ];
            return view('icic_payment_erro', $erroData);
        }
    }
}

            # Call back data 
            // $reqBody = [
            //     "Response_Code"         : "E000",               // Payment status
            //     "Unique_Ref_Number"     : "2310131666814",      // Tran no
            //     "Service_Tax_Amount"    : "0.0",
            //     "Processing_Fee_Amount" : "0.00",
            //     "Total_Amount"          : "100",
            //     "Transaction_Amount"    : "100",
            //     "Transaction_Date"      : "13-10-2023 12:34:35",
            //     "Interchange_Value"     : null,
            //     "TDR"                   : null,
            //     "Payment_Mode"          : "NET_BANKING",
            //     "SubMerchantId"         : "45",
            //     "ReferenceNo"           : "1697180633788010986", // Refno
            //     "ID"                    : "136082",
            //     "RS"                    : "73b4de05181599bf5809e4bc37edc9c32612e0bbedff71f09f8db68d5a0f9e29bc44be8d8fc7d9d5a5446c9b07674bdf6093a90b18a75b1758dc1ee77d044a6d",
            //     "TPS"                   : "Y",
            //     "mandatory_fields"      : "1697180633788010986|45|100|13/Oct/2023|0123456789|xy|xy",
            //     "optional_fields"       : "X|X|X",
            //     "RSV"                   : "8c988a820acc67ee8b0ebd2c525e3e4c88575cc2fef7f9c7dc1f2dbbad9002c86471ed446500deb4b6f1e25b11b091f7575469c0d603bccf1ba361c30f83f1a7",
            // ];


            # Webhook Data 
            // $webhook = [
            //     "IcId"          => "378278",
            //     "TrnId"         => "231107168205627",
            //     "PayMode"       => "UPI_ICICI",
            //     "TrnDate"       => "2023-11-07 16:04:28.0",
            //     "SettleDT"      => "2023-11-07 19:33:03.0",
            //     "Status"        => "Success",
            //     "InitiateDT"    => "08-Nov-23",
            //     "TranAmt"       => "1",
            //     "BaseAmt"       => "1",
            //     "ProcFees"      => "0",
            //     "STax"          => "0",
            //     "M_SGST"        => "0",
            //     "M_CGST"        => "0",
            //     "M_UTGST"       => "0",
            //     "M_STCESS"      => "0",
            //     "M_CTCESS"      => "0",
            //     "M_IGST"        => "0",
            //     "GSTState"      => "MH",
            //     "BillingState"  => "MH",
            //     "Remarks"       => "1699511340737516641~Subject to realization",
            //     "HashVal"       => "2a39772feae0571f53a113c82d8fd6d6a35ce5eff0795b529dc9dd97503ef30700ad372506e82de9a31bf62f016169a86e9b2572dce9256f3363f858d28dbf02"
            // ];

            // [
            //     ic_id
            //     trn_id
            //     pay_mode
            //     trn_date
            //     settle_dt
            //     icici_status
            //     initiate_dt
            //     tran_amt
            //     base_amt
            //     proc_fees
            //     s_tax
            //     m_sgst 
            //     m_cgst  
            //     m_utgst  
            //     m_stcess  
            //     m_ctcess 
            //     m_igst  
            //     gst_state
            //     billing_state
            //     remarks
            //     hash_val
            // ]