<?php

namespace App\BLL\Payment;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class PayNimo
{

    protected $client;

    private $MERCHANT_KEY = "";
    private $SALT = "";
    private $ENV = "";
    private $easebuzzLib = null;

    private $_EasebuzzPaymentReq;
    private $_EasebuzzPaymentResponse;

    function __construct()
    {
        $this->MERCHANT_KEY = Config::get("paynimo.EASEBUZZ_MERCHANT_KEY");
        $this->SALT = Config::get("paynimo.EASEBUZZ_SALT");
        $this->ENV = Config::get("paynimo.EASEBUZZ_ENV");
        global $EASEBUZZ_PATH;
        $this->easebuzzLib = new EasebuzzLibEasebuzz($this->MERCHANT_KEY, $this->SALT, $this->ENV);
        $this->_EasebuzzPaymentReq = new EasebuzzPaymentReq();
        $this->_EasebuzzPaymentResponse = new EasebuzzPaymentResponse();
    }

    public function initPayment(array $param)
    {
        if ($test = $this->testParam($param)) {
            return $test;
        }
        $taxId = $this->getOderId($param["moduleId"] ?? 0);
        $param["txnid"] = $taxId;
        $param["surl"] = config('app.url') . "/api/advert/payment/easebuzz/collect-callback-data";
        $param["furl"] = config('app.url') . "/api/advert/payment/easebuzz/collect-callback-data";
        $param["productinfo"] = "AKOLA ULB TAX";
        $result =  json_decode(json_encode($this->initiatePaymentAPI($param)), true);
        if ($result["status"]) {
            $this->_EasebuzzPaymentReq->module_id = $param["moduleId"] ?? 0;
            $this->_EasebuzzPaymentReq->workflow_id = $param["workflowId"] ?? 0;
            $this->_EasebuzzPaymentReq->req_ref_no = $param["txnid"];
            $this->_EasebuzzPaymentReq->amount = $param["amount"] ?? 0;
            $this->_EasebuzzPaymentReq->application_id = $param["applicationId"] ?? 0;
            $this->_EasebuzzPaymentReq->phone = $param["phone"] ?? null;
            $this->_EasebuzzPaymentReq->email = $param["email"] ?? null;
            $this->_EasebuzzPaymentReq->user_id = $param["userId"] ?? (Auth()->user()->id ?? null);
            $this->_EasebuzzPaymentReq->ulb_id = $param["ulbId"] ?? (Auth()->user()->ulb_id ?? null);
            $this->_EasebuzzPaymentReq->referal_url = $result["data"];
            $this->_EasebuzzPaymentReq->success_back_url = $param["surl"];
            $this->_EasebuzzPaymentReq->fail_back_url = $param["furl"];
            $this->_EasebuzzPaymentReq->fail_back_url = $param["furl"];
            $this->_EasebuzzPaymentReq->front_success_url = $param["frontSuccessUrl"];
            $this->_EasebuzzPaymentReq->front_fail_url = $param["frontFailUrl"];
            $this->_EasebuzzPaymentReq->payload_json = json_encode($param, JSON_UNESCAPED_UNICODE);
            $this->_EasebuzzPaymentReq->save();
        }
        $result["txnid"] = $param["txnid"];
        $result["surl"] = $param["surl"];
        $result["furl"] = $param["furl"];
        return $result;
    }

    private function testParam($param)
    {
        if (!isset($param["frontSuccessUrl"])  || empty($param["frontSuccessUrl"])) {
            return array(
                'status' => 0,
                'data' => "Invalid value for frontSuccessUrl."
            );
        }
        if (!isset($param["frontFailUrl"])  || empty($param["frontFailUrl"])) {
            return array(
                'status' => 0,
                'data' => "Invalid value for frontFailUrl."
            );
        }
        if (!isset($param["email"]) || empty($param["email"])) {
            return array(
                'status' => 0,
                'data' => "Invalid value for email."
            );
        }
        if (!isset($param["amount"]) || empty($param["amount"]) || $param["amount"] <= 0) {
            return array(
                'status' => 0,
                'data' => "Invalid value for amount."
            );
        }
        if (!isset($param["phone"])  || empty($param["phone"])) {
            return array(
                'status' => 0,
                'data' => "Invalid value for phone."
            );
        }
        if (!isset($param["firstname"])  || empty($param["firstname"])) {
            return array(
                'status' => 0,
                'data' => "Invalid value for firstname."
            );
        }
    }

    function getOderId(int $modeuleId = 0)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 10; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
        $orderId = (("Order_" . $modeuleId . date('dmyhism') . $randomString));
        return $orderId = explode("=", chunk_split($orderId, 30, "="))[0];
    }
}
