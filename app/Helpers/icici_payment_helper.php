<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

if(!function_exists("checkPaymentStatus")){
    function checkPaymentStatus($reqRefNo){
        $merchantid = Config::get("payment-constants.ICICI_ID");
        $url = "https://eazypay.icicibank.com/EazyPGVerify?ezpaytranid=&amount=&paymentmode=&merchantid=$merchantid&trandate=&pgreferenceno=$reqRefNo";
        $respons = Http::post($url);
        $responseBody = json_decode($respons->getBody(), true);
        dd($responseBody);
    }
}