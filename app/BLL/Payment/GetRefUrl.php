<?php

namespace App\BLL\Payment;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * | Created On-02-09-2023 
 * | Author-Anshu Kumar
 * | Status - Open
 * | Final Url-https://eazypayuat.icicibank.com/EazyPGVerify?ezpaytranid=2309111661222&amount=&paymentmode=&merchantid=136082&trandate=&pgreferenceno=16945076411108222585  // tranid is ref no
 */
class GetRefUrl
{
    private  $icid ;
    private  $aesKey ;                                              
    private  $subMerchantId ;
    private  $paymentMode ;
    private  $baseUrl ;                       
    private  $returnUrl; 
    private  $ciphering ;                                                                  
    private  $cipheringV2 ;
    

    
    public $_tranAmt;
    public $_refNo;
    public $_refUrl;

    public function __construct()
    {
        $this->icid             = Config::get("payment-constants.ICICI_ID");
        $this->aesKey           = Config::get("payment-constants.ICICI_AESKEY");                                              
        $this->subMerchantId    = Config::get("payment-constants.ICICI_MERCHANT_ID"); ;
        $this->paymentMode      = 9;
        $this->baseUrl          = Config::get("payment-constants.ICICI_BASE_URL");                       
        $this->returnUrl        = Config::get("payment-constants.ICICI_RETURN_URL");  
        $this->ciphering        = Config::get("payment-constants.ICICI_CIPHERING");                                                                   
        $this->cipheringV2      = Config::get("payment-constants.ICICI_CIPHERING_V2");
    }

    /**
     * | Generate Referal Url
     */
    public function generateRefUrl($req)
    {
        $todayDate          = Carbon::now()->format('d/M/Y');
        $refNo              = time() . rand();
        $this->_refNo       = $refNo;
        // $tranAmt            = 1;
        $tranAmt            =  $req->amount;                                                                            // Remove the static amount
        // $mandatoryField     = "$refNo|" . $this->subMerchantId . "|$tranAmt|" . $todayDate . "|0123456789|xy|xy";               // 10 is transactional amount
        $mandatoryField     = "$refNo|" . $this->subMerchantId . "|$tranAmt|" . $req->moduleId;                                              // 10 is transactional amount
        $eMandatoryField    = $this->encryptAes($mandatoryField);
        // $optionalField      = $this->encryptAes("X|X|X");
        $optionalField      = $this->encryptAes("");
        $returnUrl          = $this->encryptAes($this->returnUrl);
        $eRefNo             = $this->encryptAes($refNo);
        $subMerchantId      = $this->encryptAes($this->subMerchantId);
        // $eTranAmt           = $this->encryptAes($tranAmt);
        $eTranAmt           = $this->encryptAes($tranAmt);
        $paymentMode        = $this->encryptAes($this->paymentMode);

        $plainUrl = $this->baseUrl . '/EazyPG?merchantid=' . $this->icid . '&mandatory fields=' . $mandatoryField . "&optional fields=''" . '&returnurl=' . $this->returnUrl . '&Reference No=' . $refNo
            . '&submerchantid=' . $this->subMerchantId . '&transaction amount=' . "$tranAmt" . '&paymode=' . $this->paymentMode;

        $encryptUrl = $this->baseUrl . '/EazyPG?merchantid=' . $this->icid . '&mandatory fields=' . $eMandatoryField . "&optional fields=''" . '&returnurl=' . $returnUrl . '&Reference No=' . $eRefNo
            . '&submerchantid=' . $subMerchantId . '&transaction amount=' . $eTranAmt . '&paymode=' . $paymentMode;
        $this->_refUrl = $encryptUrl;
        return [
            'plainUrl'      => $plainUrl,
            'encryptUrl'    => $encryptUrl   
        ];
    }

    /**
     * | Encrypt AES
     */
    public function encryptAes($string)
    {
        // Encrption AES
        $cipher = $this->ciphering;
        $key = $this->aesKey;
        in_array($cipher, openssl_get_cipher_methods(true));
        $ivlen = openssl_cipher_iv_length($cipher);
        //echo "ivlen [". $ivlen . "]";
        $iv = openssl_random_pseudo_bytes(1);
        // echo "iv [". $iv . "]";
        $ciphertext = openssl_encrypt($string, $cipher, $key, $options = 0, "");
        return $ciphertext;
    }


    /**
     * | Get the Payment Status and data 
     */
    public function decryptWebhookData($encodedData)
    {
        try {
            $decryptedData = openssl_decrypt(base64_decode($encodedData), $this->cipheringV2, $this->aesKey, OPENSSL_RAW_DATA);
            if ($decryptedData === false) {
                throw new \Exception('Decryption failed.');
            }
            $finalWebhookData = json_decode(json_encode(simplexml_load_string($decryptedData)));
            return $finalWebhookData;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
