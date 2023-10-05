<?php

namespace App\Http\Controllers\Payment;

use App\BLL\Payment\GetRefUrl;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\ActiveSafController;
use App\Http\Controllers\Property\HoldingTaxController;
use App\Http\Requests\Property\ReqPayment;
use App\MicroServices\IdGeneration;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\Payment\IciciPaymentReq;
use App\Models\Payment\IciciPaymentResponse;
use App\Models\Payment\PinelabPaymentReq;
use App\Models\Payment\PinelabPaymentResponse;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Repository\Trade\TradeCitizen;
use App\Repository\Water\Concrete\WaterNewConnection;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On-27-09-2023 
 * | Author - Anshu Kumar
 * | Status-Closed
 */

class PaymentController extends Controller
{
    private $_paymentStatus;
    protected $_safRepo;

    public function __construct(iSafRepository $safRepo)
    {
        $this->_paymentStatus = Config::get('payment-constants.PAYMENT_STATUS');
        $this->_safRepo = $safRepo;
    }

    // Generation of Referal url for payment for Testing
    public function getReferalUrl(Request $req)
    {
        $getRefUrl = new GetRefUrl;
        $mIciciPaymentReq = new IciciPaymentReq();
        try {
            $url = $getRefUrl->generateRefUrl();
            $paymentReq = [
                "user_id" => $req->userId,
                "workflow_id" => $req->workflowId,
                "req_ref_no" => $getRefUrl->_refNo,
                "amount" => $req->amount,
                "application_id" => $req->applicationId,
                "module_id" => $req->moduleId,
                "ulb_id" => $req->ulbId,
                "referal_url" => $url['encryptUrl']
            ];
            $mIciciPaymentReq->create($paymentReq);
            return responseMsgs(true,  ["plainUrl" => $url['plainUrl'], "req_ref_no" => $getRefUrl->_refNo], $url['encryptUrl']);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    // Module Referal Urls

    /**
     * | Get Webhook data
     */
    public function getWebhookData(Request $req)
    {
        $mIciciPaymentReq = new IciciPaymentReq();
        $mIciciPaymentRes = new IciciPaymentResponse();

        try {
            $data = $req->all();
            $reqRefNo = $req->reqRefNo;
            if ($req->Status == 'Success') {
                $resRefNo = $req->resRefNo;
                $paymentReqsData = $mIciciPaymentReq->findByReqRefNo($reqRefNo);
                $updReqs = [
                    'res_ref_no' => $resRefNo,
                    'payment_status' => 1
                ];
                DB::connection('pgsql_master')->beginTransaction();
                $paymentReqsData->update($updReqs);                 // Table Updation
                $resPayReqs = [
                    "payment_req_id" => $paymentReqsData->id,
                    "req_ref_id" => $reqRefNo,
                    "res_ref_id" => $resRefNo,
                    "icici_signature" => $req->signature,
                    "payment_status" => 1
                ];
                $mIciciPaymentRes->create($resPayReqs);             // Response Data 
            }
            // ❗❗ Pending for Module Specific Table Updation ❗❗

            $filename = time() . "webhook.json";
            Storage::disk('local')->put($filename, json_encode($data));
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Data Received Successfully", []);
        } catch (Exception $e) {
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    /**
     * | Get data by reference no 
     */
    public function getPaymentDataByRefNo(Request $req)
    {
        $getPayemntDetails  = new GetRefUrl;
        $mIciciPaymentReq   = new IciciPaymentReq();
        $mIciciPaymentRes   = new IciciPaymentResponse();
        try {
            $user               = authUser($req);
            $resRefNo           = $req->referencNo;
            $confPaymentStatus  = $this->_paymentStatus;
            $paymentReqData     = $mIciciPaymentReq->findByReqRefNo($resRefNo);
            if (!$paymentReqData) {
                throw new Exception("Payment request of $resRefNo not found!");
            }

            $paymentJsonData = $this->filterReqReqData($req);
            # Get the payment req for refNo
            switch ($paymentReqData->payment_status) {
                case ($confPaymentStatus['PENDING']):
                    $PaymentHistory = $getPayemntDetails->getPaymentStatusByUrl($resRefNo);
                    break;
                default:

                    break;
            }


            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Payment Received Successfully", []);
        } catch (Exception $e) {
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    /**
     * | filter the string data into json
     */
    public function filterReqReqData($req)
    {
        $string         = "status=NotInitiated&ezpaytranid=NA&amount=NA&trandate=NA&pgreferenceno=null&sdt=&BA=null&PF=null&TAX=null&PaymentMode=null";
        $keyValuePairs  = explode('&', $string);
        $data           = [];

        foreach ($keyValuePairs as $pair) {
            list($key, $value) = explode('=', $pair);
            $value = ($value === 'null') ? null : $value;
            $data[$key] = $value;
        }

        $jsonData = json_encode($data);
        if ($jsonData === false) {
            throw new Exception("JSON encoding failed!");
        } else {
            return $jsonData;
        }
    }

    /**
     * | Generate Order Id
     */
    protected function getOrderId(int $modeuleId)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 10; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
        $orderId = (("Order_" . $modeuleId . date('dmyhism') . $randomString));
        $orderId = explode("=", chunk_split($orderId, 30, "="))[0];
        return $orderId;
    }

    /**
     * | Save Pine lab Request
     */
    public function initiatePayment(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "workflowId"    => "nullable|int",
            "amount"        => "required|numeric",
            "moduleId"      => "nullable|int",
            "applicationId" => "required|int",
        ]);

        if ($validator->fails())
            return validationError($validator);

        try {
            $mPinelabPaymentReq =  new PinelabPaymentReq();
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $moduleId = $req->moduleId;

            if ($req->paymentType == 'Property' || 'Saf')
                $moduleId = $propertyModuleId;

            $user = authUser($req);
            $mReqs = [
                "ref_no"          => $this->getOrderId($moduleId),
                "user_id"         => $user->id,
                "workflow_id"     => $req->workflowId ?? 0,
                "amount"          => $req->amount,
                "module_id"       => $moduleId,
                "ulb_id"          => $user->ulb_id ?? $req->ulbId,
                "application_id"  => $req->applicationId,
                "payment_type"    => $req->paymentType

            ];
            $data = $mPinelabPaymentReq->store($mReqs);

            return responseMsgs(true, "Bill id is", ['billRefNo' => $data->ref_no], "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Save Pine lab Response
     * | Code by Mrinal Kumar
     * | Edited By-Anshu Kumar(27-09-2023)
     */
    public function savePinelabResponse(Request $req)
    {
        $idGeneration = new IdGeneration;
        try {
            Storage::disk('public')->put($req->billRefNo . '.json', json_encode($req->all()));
            $mPinelabPaymentReq      =  new PinelabPaymentReq();
            $mPinelabPaymentResponse = new PinelabPaymentResponse();
            $responseCode            = Config::get('payment-constants.PINELAB_RESPONSE_CODE');
            $propertyModuleId        = Config::get('module-constants.PROPERTY_MODULE_ID');
            $user                    = authUser($req);
            $pinelabData             = $req->pinelabResponseBody;
            $detail                  = (object)($req->pinelabResponseBody['Detail'] ?? []);


            $actualTransactionNo = $idGeneration->generateTransactionNo($user->ulb_id);

            if (in_array($req->paymentType, ['Property', 'Saf']))
                $moduleId = $propertyModuleId;

            $paymentData = $mPinelabPaymentReq->getPaymentRecord($req);

            if (collect($paymentData)->isEmpty())
                throw new Exception("Payment Data not available");
            if ($paymentData) {
                $mReqs = [
                    "payment_req_id"       => $paymentData->id,
                    "req_ref_no"           => $req->billRefNo,
                    "res_ref_no"           => $actualTransactionNo,                         // flag
                    "response_msg"         => $pinelabData['Response']['ResponseMsg'],
                    "response_code"        => $pinelabData['Response']['ResponseCode'],
                    "description"          => $req->description,
                ];

                $data = $mPinelabPaymentResponse->store($mReqs);
            }

            # data transfer to the respective module's database 
            $moduleData = [
                'id'                => $req->applicationId,
                'billRefNo'         => $req->billRefNo,
                'amount'            => $req->amount,
                'workflowId'        => $req->workflowId,
                'userId'            => $user->id,
                'ulbId'             => $user->ulb_id,
                'departmentId'      => $moduleId,         #_Module Id
                'gatewayType'       => "Pinelab",         #_Pinelab Id
                'transactionNo'     => $actualTransactionNo,
                'TransactionDate'   => $detail->TransactionDate ?? null,
                'HostResponse'      => $detail->HostResponse ?? null,
                'CardEntryMode'     => $detail->CardEntryMode ?? null,
                'ExpiryDate'        => $detail->ExpiryDate ?? null,
                'InvoiceNumber'     => $detail->InvoiceNumber ?? null,
                'MerchantAddress'   => $detail->MerchantAddress ?? null,
                'TransactionTime'   => $detail->TransactionTime ?? null,
                'TerminalId'        => $detail->TerminalId ?? null,
                'TransactionType'   => $detail->TransactionType ?? null,
                'CardNumber'        => $detail->CardNumber ?? null,
                'MerchantId'        => $detail->MerchantId ?? null,
                'PlutusVersion'     => $detail->PlutusVersion ?? null,
                'PosEntryMode'      => $detail->PosEntryMode ?? null,
                'RetrievalReferenceNumber' => $detail->RetrievalReferenceNumber ?? null,
                'BillingRefNo'             => $detail->BillingRefNo ?? null,
                'BatchNumber'              => $detail->BatchNumber ?? null,
                'Remark'                   => $detail->Remark ?? null,
                'AcquiringBankCode'        => $detail->AcquiringBankCode ?? null,
                'MerchantName'             => $detail->MerchantName ?? null,
                'MerchantCity'             => $detail->MerchantCity ?? null,
                'ApprovalCode'             => $detail->ApprovalCode ?? null,
                'CardType'                 => $detail->CardType ?? null,
                'PrintCardholderName'      => $detail->PrintCardholderName ?? null,
                'AcquirerName'             => $detail->AcquirerName ?? null,
                'LoyaltyPointsAwarded'     => $detail->LoyaltyPointsAwarded ?? null,
                'CardholderName'           => $detail->CardholderName ?? null,
                'AuthAmoutPaise'           => $detail->AuthAmoutPaise ?? null,
                'PlutusTransactionLogID'   => $detail->PlutusTransactionLogID ?? null
            ];


            if ($pinelabData['Response']['ResponseCode'] == 00) {                           // Success Response code(00)
                $paymentData->payment_status = 1;
                $paymentData->save();

                # calling function for the modules
                switch ($paymentData->module_id) {
                    case ('1'):
                        $workflowId = $paymentData->workflow_id;
                        if ($workflowId == 0) {
                            $objHoldingTaxController = new HoldingTaxController($this->_safRepo);
                            $moduleData = new Request($moduleData);
                            $objHoldingTaxController->paymentHolding($moduleData);
                        } else {                                            //<------------------ (SAF PAYMENT)
                            $obj = new ActiveSafController($this->_safRepo);
                            $moduleData = new ReqPayment($moduleData);
                            $obj->paymentSaf($moduleData);
                        }
                        break;
                        // case ('2'):                                             //<------------------ (Water)
                        //     $objWater = new WaterNewConnection();
                        //     $objWater->razorPayResponse($moduleData);
                        //     break;
                    case ('3'):                                             //<------------------ (TRADE)
                        $objTrade = new TradeCitizen();
                        $objTrade->pinelabResponse($moduleData);
                        break;
                }
            } else
                throw new Exception("Payment Cancelled");
            return responseMsgs(true, "Data Saved", $data, "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
