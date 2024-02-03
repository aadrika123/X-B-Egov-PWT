<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

if(!function_exists("checkPaymentStatus")){
    function checkPaymentStatus($reqRefNo){
        $response["status"] = true;
        try{
            $merchantid = Config::get("payment-constants.ICICI_ID");
            $baseUrl = config::get("payment-constants.ICICI_BASE_URL");
            $url = "$baseUrl/EazyPGVerify?ezpaytranid=&amount=&paymentmode=&merchantid=$merchantid&trandate=&pgreferenceno=$reqRefNo";        
            $data = (file_get_contents($url));
            $response["response"]["response"] = $data;
            $data = (explode("&",$data));            
            foreach($data as $val){
                list($key,$values) = explode("=",$val);
                $response["response"][$key] = $values;
                if($key=="status")
                {
                    $response["response"]["reason"] = paymentStatusDescription($values);
                    $response["response"]["ResponseCode"] = paymentResponseCode($values);
                }
                
            }
            
        }
        catch(Exception $e)
        {
            $response["status"] = true;
            $response["errors"] = $e->getMessage();
            $response["response"] = [];
        }
        return($response);
    }
}
if(!function_exists("paymentStatusDescription")){
    function paymentStatusDescription($paymentStatus){
        switch($paymentStatus){
            case "RIP"      : $paymentStatus = "Reconciliation in Progress between the respective bank and eazypay is initiated.";
                            break;
            case "SIP"      : $paymentStatus = "Successful reconciliation between the respective bank and eazypay (Settlement in Progress).";
                            break;
            case "Success"  : $paymentStatus = "Successful settlement to the Merchant Account.";
                            break;
            case "FAILED"   : $paymentStatus = "The payer has initiated the transaction and logged into respective payment
                                                modes on their bank site for Net-Banking or Debit Card/Credit Card and the
                                                transaction fails due to various reasons like, No Funds, Wrong Authentication,
                                                Session Expired, Canceled by User etc, for such transactions the status will be
                                                Failed.";
                            break;
        }
        return $paymentStatus;
    }
}

if(!function_exists("paymentResponseCode")){
    function paymentResponseCode($paymentStatus){
        switch($paymentStatus){
            case "RIP"      : $paymentStatus = "E0033";
                            break;
            case "SIP"      : $paymentStatus = "E0033";
                            break;
            case "Success"  : $paymentStatus = "E000";
                            break;
            case "FAILED"   : $paymentStatus = "E007";
                            break;
        }
        return $paymentStatus;
    }
}