<?php

namespace App\Http\Requests\Water;

use App\Http\Requests\AllRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

class ReqPayment extends AllRequest
{
    public function __construct()
    {
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = array();
        $offlinePaymentModes = Config::get('payment-constants.PAYMENT_OFFLINE_MODE_WATER');
        $refPaymentMode = Config::get('payment-constants.PAYMENT_OFFLINE_MODE');
        $refDate = Carbon::now()->format('Y-m-d');

        if (isset($this['paymentMode']) &&  in_array($this['paymentMode'], $offlinePaymentModes) && $this['paymentMode'] != $refPaymentMode['1']) {
            $rules['chequeDate']    = "required|date|date_format:Y-m-d";
            $rules['bankName']      = "required";
            $rules['branchName']    = "required";
            $rules['chequeNo']      = "required";
            if (isset($this['chequeDate']) && $this['chequeDate'] > $refDate) {
                # throw error
            }
        }
        if (isset($this['paymentMode']) &&  in_array($this['paymentMode'], $offlinePaymentModes) && $this['paymentMode'] != $refPaymentMode['5']) {
            $rules['remarks'] = 'required|';
        }

        // # For Part payament 
        // if (isset($this['paymentType']) && $this['paymentType'] == "isPartPayment") {
        //     $rules['deviceId']  = "nullable";
        //     // $rules['document']  = "nullable|mimes:pdf,jpg,jpeg,png|max:2048";
        // }
        $rules['paymentType'] = 'required|In:isFullPayment,isPartPayment';
        $rules['amount'] = 'nullable|required_if:paymentType,==,isPartPayment|numeric';
        $rules['consumerId']    = 'required';
        $rules['paymentMode']   = 'required';

        return $rules;
    }
}
