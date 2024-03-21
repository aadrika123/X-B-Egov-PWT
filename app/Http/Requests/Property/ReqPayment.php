<?php

namespace App\Http\Requests\Property;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;

class ReqPayment extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = array();
        $offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
        $cash = Config::get('payment-constants.PAYMENT_MODE.3');
        $neft = Config::get('payment-constants.PAYMENT_MODE.6');
        if (isset($this['paymentMode']) &&  in_array($this['paymentMode'], $offlinePaymentModes) && $this['paymentMode'] != $cash && $this['paymentMode'] != $neft) {
            $rules['chequeDate'] = "required|date|date_format:Y-m-d";
            $rules['bankName'] = "required";
            $rules['branchName'] = "required";
            $rules['chequeNo'] = "required";
        }
        if(isset($this['paymentMode']) && $this['paymentMode']=='ONLINE_R')
        {
            $rules['TrnDate'] = "nullable|required_if:paymentMode,==,ONLINE_R|date|before_or_equal:".Carbon::now()->format('Y-m-d');
            $rules['bankName'] = "nullable";
            $rules['branchName'] = "nullable";
        }
        $rules['paymentMode'] = "required|In:CASH,CHEQUE,DD,NEFT,ONLINE,ONLINE_R,NETBANKING";
        $rules['id'] = "required";

        return $rules;
    }


    // Validation Error Message
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'status' => false,
                    'message' => 'The given data was invalid',
                    'errors' => $validator->errors()
                ],
                200
            )
        );
    }
}
