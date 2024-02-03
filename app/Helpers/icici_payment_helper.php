<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

if(!function_exists("checkPaymentStatus")){
    function checkPaymentStatus($reqRefNo){
        $merchantid = Config::get("payment-constants.ICICI_ID");
        $url = "https://eazypay.icicibank.com/EazyPGVerify?ezpaytranid=&amount=&paymentmode=&merchantid=$merchantid&trandate=&pgreferenceno=$reqRefNo";
        
        // $respons = Http::get($url);
        // $responseBody = json_decode($respons->getBody(), true);
        // dd($respons->getBody(),$respons,$responseBody );
        $post = curl_init();
        curl_setopt($post, CURLOPT_URL, $url);
        $result = curl_exec($post); //result from mobile seva server
        curl_close($post);
        // dd($result,explode("&",$result));
    }
}