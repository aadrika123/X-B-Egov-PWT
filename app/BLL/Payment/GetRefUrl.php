<?php

namespace App\BLL\Payment;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * | Created On-02-09-2023 
 * | Author-Anshu Kumar
 * | Status - Open
 * | Final Url-https://eazypayuat.icicibank.com/EazyPGVerify?ezpaytranid=2309111661222&amount=&paymentmode=&merchantid=136082&trandate=&pgreferenceno=16945076411108222585  // tranid is ref no
 */
class GetRefUrl
{
    // private static $icid = 600587;
    // private static $icid = 136082;                                                       // Merchant Id uat
    private static $icid = 378278;                                                          // live
    // private static $aesKey = 6000010105805020;
    // private static $aesKey = 1300011160805020;                                           // Uat
    private static $aesKey = 3705200682705002;                                              // Live
    private static $subMerchantId = 45;
    private static $paymentMode = 9;
    private static $baseUrl = "https://eazypay.icicibank.com";                       // https://eazypayuat.icicibank.com
    private static $returnUrl = "https://egov.modernulb.com/api/payment/v1/collect-callback-data"; //"http://203.129.217.62:82/api/payment/v1/collect-callback-data";                   // http://203.129.217.62:82/api/payment/v1/collect-callback-data   https://modernulb.com/property/payment-success/87878787  https://modernulb.com/property/paymentReceipt/550980/holding
    private static $ciphering = "aes-128-ecb";                                                                  // Store the cipher method for encryption
    private static $cipheringV2 = 'AES-128-ECB';
    public $_tranAmt;
    public $_refNo;
    public $_refUrl;

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
        // $mandatoryField     = "$refNo|" . self::$subMerchantId . "|$tranAmt|" . $todayDate . "|0123456789|xy|xy";               // 10 is transactional amount
        $mandatoryField     = "$refNo|" . self::$subMerchantId . "|$tranAmt|" . $req->moduleId;                                              // 10 is transactional amount
        $eMandatoryField    = $this->encryptAes($mandatoryField);
        // $optionalField      = $this->encryptAes("X|X|X");
        $optionalField      = $this->encryptAes("");
        $returnUrl          = $this->encryptAes(self::$returnUrl);
        $eRefNo             = $this->encryptAes($refNo);
        $subMerchantId      = $this->encryptAes(self::$subMerchantId);
        // $eTranAmt           = $this->encryptAes($tranAmt);
        $eTranAmt           = $this->encryptAes($tranAmt);
        $paymentMode        = $this->encryptAes(self::$paymentMode);

        $plainUrl = self::$baseUrl . '/EazyPG?merchantid=' . self::$icid . '&mandatory fields=' . $mandatoryField . "&optional fields=''" . '&returnurl=' . self::$returnUrl . '&Reference No=' . $refNo
            . '&submerchantid=' . self::$subMerchantId . '&transaction amount=' . "$tranAmt" . '&paymode=' . self::$paymentMode;

        $encryptUrl = self::$baseUrl . '/EazyPG?merchantid=' . self::$icid . '&mandatory fields=' . $eMandatoryField . "&optional fields=''" . '&returnurl=' . $returnUrl . '&Reference No=' . $eRefNo
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
        $cipher = self::$ciphering;
        $key = self::$aesKey;
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
            $decryptedData = openssl_decrypt(base64_decode($encodedData), self::$cipheringV2, self::$aesKey, OPENSSL_RAW_DATA);
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
