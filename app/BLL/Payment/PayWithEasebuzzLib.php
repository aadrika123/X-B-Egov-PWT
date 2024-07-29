<?php
namespace App\BLL\Payment;

use App\Lib\EasebuzzLib\Easebuzz as EasebuzzLibEasebuzz;
use App\Models\Payment\EasebuzzPaymentReq;
use App\Models\Payment\EasebuzzPaymentResponse;
use Illuminate\Support\Facades\Config;

class PayWithEasebuzzLib
{
    private $MERCHANT_KEY = "";
    private $SALT = "";
    private $ENV = "";
    private $easebuzzLib = null;

    private $_EasebuzzPaymentReq;
    private $_EasebuzzPaymentResponse;

    /*
    * initialised private variable for setup easebuzz payment gateway.
    *
    * @param  string $key - holds the merchant key.
    * @param  string $salt - holds the merchant salt key.
    * @param  string $env - holds the env(enviroment). 
    *
    */
    function __construct(){
        $this->MERCHANT_KEY = Config::get("payment-constants.EASEBUZZ_MERCHANT_KEY");
        $this->SALT = Config::get("payment-constants.EASEBUZZ_SALT");
        $this->ENV = Config::get("payment-constants.EASEBUZZ_ENV");
        global $EASEBUZZ_PATH;
        $this->easebuzzLib = new EasebuzzLibEasebuzz($this->MERCHANT_KEY,$this->SALT, $this->ENV);
        $this->_EasebuzzPaymentReq = new EasebuzzPaymentReq();
        $this->_EasebuzzPaymentResponse = new EasebuzzPaymentResponse();
    }

    function getOderId(int $modeuleId=0)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';        
        for ($i = 0; $i < 10; $i++) 
        {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
        $orderId = (("Order_".$modeuleId.date('dmyhism').$randomString));
        return $orderId = explode("=",chunk_split($orderId,30,"="))[0]; 
    }
    public function initPayment(array $param){
        if($test = $this->testParam($param)){
            return $test;
        }
        $taxId = $this->getOderId($param["moduleId"]??0);
        $param["txnid"] = $taxId;
        $param["surl"]=config('app.url')."/api/payment/easebuzz/collect-callback-data";
        $param["furl"]=config('app.url')."/api/payment/easebuzz/collect-callback-data";
        $param["productinfo"] = "AKOLA ULB TAX";        
        $result =  json_decode(json_encode($this->initiatePaymentAPI($param)),true);
        if($result["status"]){
            $this->_EasebuzzPaymentReq->module_id = $param["moduleId"]??0;
            $this->_EasebuzzPaymentReq->workflow_id = $param["workflowId"]??0;
            $this->_EasebuzzPaymentReq->req_ref_no = $param["txnid"];
            $this->_EasebuzzPaymentReq->amount = $param["amount"]??0;
            $this->_EasebuzzPaymentReq->application_id = $param["applicationId"]??0;
            $this->_EasebuzzPaymentReq->phone = $param["phone"]??null;
            $this->_EasebuzzPaymentReq->email = $param["email"]??null;
            $this->_EasebuzzPaymentReq->user_id = $param["userId"]??(Auth()->user()->id??null);
            $this->_EasebuzzPaymentReq->ulb_id = $param["ulbId"]??(Auth()->user()->ulb_id??null);
            $this->_EasebuzzPaymentReq->referal_url = $result["data"];
            $this->_EasebuzzPaymentReq->success_back_url = $param["surl"];
            $this->_EasebuzzPaymentReq->fail_back_url = $param["furl"];
            $this->_EasebuzzPaymentReq->fail_back_url = $param["furl"];
            $this->_EasebuzzPaymentReq->front_success_url = $param["frontSuccessUrl"];
            $this->_EasebuzzPaymentReq->front_fail_url = $param["frontFailUrl"];
            $this->_EasebuzzPaymentReq->payload_json = json_encode($param,JSON_UNESCAPED_UNICODE);
            $this->_EasebuzzPaymentReq->save();

        }
        $result["txnid"] = $param["txnid"];
        $result["surl"]=$param["surl"];
        $result["furl"]=$param["furl"];
        return $result;

    }

    private function testParam($param){
        if(!isset($param["frontSuccessUrl"])  ||empty($param["frontSuccessUrl"])){
            return array(
                'status' => 0,
                'data' =>"Invalid value for frontSuccessUrl."
            );
        }
        if(!isset($param["frontFailUrl"])  ||empty($param["frontFailUrl"])){
            return array(
                'status' => 0,
                'data' =>"Invalid value for frontFailUrl."
            );
        }
        if(!isset($param["email"]) ||empty($param["email"])){
            return array(
                'status' => 0,
                'data' =>"Invalid value for email."
            );
        }
        if(!isset($param["amount"]) || empty($param["amount"])||$param["amount"]<=0){
            return array(
                'status' => 0,
                'data' =>"Invalid value for amount."
            );
        }
        if(!isset($param["phone"])  ||empty($param["phone"])){
            return array(
                'status' => 0,
                'data' =>"Invalid value for phone."
            );
        }
        if(!isset($param["firstname"])  ||empty($param["firstname"])){
            return array(
                'status' => 0,
                'data' =>"Invalid value for firstname."
            );
        }
    }


    /*
        * initiatePaymentAPI function to integrate easebuzz for payment.
        *
        * http method used - POST
        *
        * param string $txnid - holds the transaction id (which is auto generate using hash)
        * param array $params - holds the $_POST data which is pass from the html form.
        *
        * ##Return values
        *
        * - return array ApiResponse['status']== 1 means successful.
        * 
        * - return array ApiResponse['status']== 0 means error.
        *
        * @param array $params - holds the $_POST data which is pass from the html form.
        *
        * @return array ApiResponse['status']== 1 successful.
        * @return array ApiResponse['status']== 0 error.
        *
        * ##Helper methods for initiate payment(payment.php)
        *
        * - initiate_payment(arg1, arg2, arg3, arg4) :- call all method initiate payment and dispaly payment page.
        * 
        * - _payment(arg1, arg2, arg3, arg4) :- use for initiate payment.
        *
        * - _paymentResponse(arg1) :- use for show api response (like error, payment page etc.).
        *
        * - _checkArgumentValidation(arg1, arg2, arg3, arg4) :- check no. of argument validation.
        *
        * - _removeSpaceAndPreparePostArray(arg1) :- remove space, anonymous tag from the $_POST and prepare array.
        *
        * - _typeValidation(arg1, arg2, arg3) :- check type validation (like amount shoud be float etc).
        *
        * - _emptyValidation(arg1, arg2) :- check empty validation for Mandatory Parameters.
        *
        * - _email_validation(arg1) :- check email format validation.
        *
        * - _getURL(arg1) :- get URL based on set enviroment.
        *
        * - _pay(arg1, arg2, arg3) :- initiate payment.
        *
        * ## below method call from _pay() method.
        *
        * -- _getHashKey(arg1, arg2) :- generate hash key based on hash sequence.
        *
        * -- _curlCall(arg1, arg2) :- initiate pay link.
        *
        * ## below method call from _curlCall() method.
        *
        * Note :- Before call below method, install cURL. if cURL is already installed the go ahead.
        *
        * --- curl_init() :- Initializes a new session and return a cURL.
        *
        * --- curl_setopt_array(arg1, arg2) :- Set multiple options for a cURL transfer.
        *
        * --- curl_exec(arg1) :- Perform a cURL session.
        *
        * --- curl_errno(arg1) :- check there is any error or not in curl execution.
        *
        */

    public function initiatePaymentAPI($params, $redirect=True){
        // include file

        // generate transaction ID and push into $params array
        // $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        // $params['txnid'] = $txnid;
        return $this->easebuzzLib->initiatePaymentAPI($params, $redirect);
    }

    /*
        * transactionAPI function to query for single transaction
        *
        * http method used - POST
        *
        * param array $params - holds the $_POST data which is pass from the html form.
        *
        * ##Return values
        *
        * - return array ApiResponse['status']== 1 means successful.
        * 
        * - return array ApiResponse['status']== 0 means error.
        *
        * @param array $params - holds the $_POST data which is pass from the html form.
        *
        * @return array ApiResponse['status']== 1 successful.
        * @return array ApiResponse['status']== 0 error.
        *
        * ##Helper methods for initiate transaction(transaction.php)
        *
        * - get_transaction_details(arg1, arg2, arg3, arg4) :- call all method initiate transaction.
        *
        * - _transaction(arg1, arg2, arg3, arg4) :- use for initiate transaction.
        *
        * - _transactionResponse(arg1, arg2) :- use for verify api response is acceptable or not.
        *
        * - _checkArgumentValidation(arg1, arg2, arg3, arg4) :- check no. of argument validation.
        *
        * - _removeSpaceAndPreparePostArray(arg1) :- remove space, anonymous tag from the $_POST and prepare array.
        *
        * - _typeValidation(arg1, arg2, arg3) :- check type validation (like amount shoud be float etc).
        *
        * - _emptyValidation(arg1, arg2) :- check empty validation for Mandatory Parameters.
        *
        * - _email_validation(arg1) :- check email format validation.
        *
        * - _getURL(arg1) :- get URL based on set enviroment.
        * 
        * - _getTransaction(arg1, arg2, arg3) :- initiate transaction.
        *
        * ## below method call from _getTransaction() method.
        *
        * -- _getHashKey(arg1, arg2) :- generate hash key based on hash sequence.
        *
        * -- _curlCall(arg1, arg2) :- initiate pay link.
        *
        * ## below method call from _curlCall() method.
        *
        * Note :- Before call below method, install cURL. if cURL is already installed the go ahead.
        *
        * --- curl_init() :- Initializes a new session and return a cURL.
        *
        * --- curl_setopt_array(arg1, arg2) :- Set multiple options for a cURL transfer.
        *
        * --- curl_exec(arg1) :- Perform a cURL session.
        *
        * --- curl_errno(arg1) :- check there is any error or not in curl execution.
        *
        * ## below method call from _transactionResponse() method.
        *
        * -- _getReverseHashKey(arg1, arg2) :- generate reverse hash key for response verification.
        *
        */
    public function transactionAPI($params){
        $result = $this->easebuzzLib->transactionAPI($params);
        return $result;
    }


    /*
        * transactionDateAPI function to transaction based on date.
        *
        * http method used - POST
        *
        * param array $params - holds the $_POST data which is pass from the html form.
        *
        * ##Return values
        *
        * - return array ApiResponse['status']== 1 means successful.
        * 
        * - return array ApiResponse['status']== 0 means error.
        *
        * @param array $params - holds the $_POST data which is pass from the html form.
        *
        * @return array ApiResponse['status']== 1 successful.
        * @return array ApiResponse['status']== 0 error.
        *
        * ##Helper methods for initiate date transaction(transaction_date.php)
        *
        * - get_transactions_by_date(arg1, arg2, arg3, arg4) :- call all method initiate date transaction.
        *
        * - _date_transaction(arg1, arg2, arg3, arg4) :- use for initiate date transaction.
        *
        * - _checkArgumentValidation(arg1, arg2, arg3, arg4) :- check no. of argument validation.
        *
        * - _removeSpaceAndPreparePostArray(arg1) :- remove space, anonymous tag from the $_POST and prepare array.
        *
        * - _typeValidation(arg1, arg2, arg3) :- check type validation (like amount shoud be float etc).
        *
        * - _emptyValidation(arg1, arg2) :- check empty validation for Mandatory Parameters.
        *
        * - _email_validation(arg1) :- check email format validation.
        *
        * - _getURL(arg1) :- get URL based on set enviroment.
        * 
        * - _getDateTransaction(arg1, arg2, arg3) :- initiate date transaction.
        *
        * ## below method call from _getDateTransaction() method.
        *
        * -- _getHashKey(arg1, arg2) :- generate hash key based on hash sequence.
        *
        * -- _curlCall(arg1, arg2) :- initiate pay link.
        *
        * ## below method call from _curlCall() method.
        *
        * Note :- Before call below method, install cURL. if cURL is already installed the go ahead.
        *
        * --- curl_init() :- Initializes a new session and return a cURL.
        *
        * --- curl_setopt_array(arg1, arg2) :- Set multiple options for a cURL transfer.
        *
        * --- curl_exec(arg1) :- Perform a cURL session.
        *
        * --- curl_errno(arg1) :- check there is any error or not in curl execution.
        *
        */
    public function transactionDateAPI($params){
        $result = $this->easebuzzLib->transactionDateAPI($params);
        return $result;
    }

    /*
        * refundAPI function to refund for the transaction
        *
        * http method used - POST
        *
        * param array $params - holds the $_POST data which is pass from the html form.
        *
        * ##Return values
        *
        * - return array ApiResponse['status']== 1 means successful.
        * 
        * - return array ApiResponse['status']== 0 means error.
        *
        * @param array $params - holds the $_POST data which is pass from the html form.
        *
        * @return array ApiResponse['status']== 1 successful.
        * @return array ApiResponse['status']== 0 error.
        *
        * ##Helper methods for initiate refund (refund.php)
        *
        * - initiate_refund(arg1, arg2, arg3, arg4) :- call all method initiate refund.
        *
        * - _refund(arg1, arg2, arg3, arg4) :- use for initiate refund.
        *
        * - _checkArgumentValidation(arg1, arg2, arg3, arg4) :- check no. of argument validation.
        *
        * - _removeSpaceAndPreparePostArray(arg1) :- remove space, anonymous tag from the $_POST and prepare array.
        *
        * - _typeValidation(arg1, arg2, arg3) :- check type validation (like amount shoud be float etc).
        *
        * - _emptyValidation(arg1, arg2) :- check empty validation for Mandatory Parameters.
        *
        * - _email_validation(arg1) :- check email format validation.
        *
        * - _getURL(arg1) :- get URL based on set enviroment.
        * 
        * - _refundPayment(arg1, arg2, arg3) :- initiate refund.
        *
        * ## below method call from _refundPayment() method.
        *
        * -- _getHashKey(arg1, arg2) :- generate hash key based on hash sequence.
        *
        * -- _curlCall(arg1, arg2) :- initiate pay link.
        *
        * ## below method call from _curlCall() method.
        *
        * Note :- Before call below method, install cURL. if cURL is already installed the go ahead.
        *
        * --- curl_init() :- Initializes a new session and return a cURL.
        *
        * --- curl_setopt_array(arg1, arg2) :- Set multiple options for a cURL transfer.
        *
        * --- curl_exec(arg1) :- Perform a cURL session.
        *
        * --- curl_errno(arg1) :- check there is any error or not in curl execution.
        *
        */

    public function refundAPI($params){
        $result = $this->easebuzzLib->refundAPI($params);
        return $result;
    }
    public function refundAPIV2($params){
        $result = $this->easebuzzLib->refundAPIV2($params);
        return $result;
    }
    public function refundStatus($params){
        $result = $this->easebuzzLib->refundStatus($params);
        return $result;
    }

    /*
        * payoutAPI function to payout for particular date.
        *
        * http method used - POST
        *
        * param array $params - holds the $_POST data which is pass from the html form.
        *
        * ##Return values
        *
        * - return array ApiResponse['status']== 1 means successful.
        * 
        * - return array ApiResponse['status']== 0 means error.
        *
        * @param array $params - holds the $_POST data which is pass from the html form.
        *
        * @return array ApiResponse['status']== 1 successful.
        * @return array ApiResponse['status']== 0 error.
        *
        * ##Helper methods for initiate payout (payout.php)
        *
        * - get_payout_details_by_date(arg1, arg2, arg3, arg4) :- call all method initiate payout.
        *
        * - _payout(arg1, arg2, arg3, arg4) :- use for initiate payout.
        *
        * - _checkArgumentValidation(arg1, arg2, arg3, arg4) :- check no. of argument validation.
        *
        * - _removeSpaceAndPreparePostArray(arg1) :- remove space, anonymous tag from the $_POST and prepare array.
        *
        * - _typeValidation(arg1, arg2, arg3) :- check type validation (like amount shoud be float etc).
        *
        * - _emptyValidation(arg1, arg2) :- check empty validation for Mandatory Parameters.
        *
        * - _email_validation(arg1) :- check email format validation.
        *
        * - _getURL(arg1) :- get URL based on set enviroment.
        * 
        * - _payoutPayment(arg1, arg2, arg3) :- initiate payout payment.
        *
        * ## below method call from _payoutPayment() method.
        *
        * -- _getHashKey(arg1, arg2) :- generate hash key based on hash sequence.
        *
        * -- _curlCall(arg1, arg2) :- initiate pay link.
        *
        * ## below method call from _curlCall() method.
        *
        * Note :- Before call below method, install cURL. if cURL is already installed the go ahead.
        *
        * --- curl_init() :- Initializes a new session and return a cURL.
        *
        * --- curl_setopt_array(arg1, arg2) :- Set multiple options for a cURL transfer.
        *
        * --- curl_exec(arg1) :- Perform a cURL session.
        *
        * --- curl_errno(arg1) :- check there is any error or not in curl execution.
        *
        */
    public function payoutAPI($params){
        $result = $this->easebuzzLib->payoutAPI($params);
        return $result;
    }

    public function easebuzzResponse($params){
        $result = $this->easebuzzLib->easebuzzResponse($params);
        return $result;
    }

}
