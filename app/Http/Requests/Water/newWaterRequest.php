<?php

namespace App\Http\Requests\water;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class newWaterRequest extends FormRequest
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
        $rules['Zone']                  = 'nullable';
        $rules['ward']                  =  'nullable';
        $rule['ulbId']                  = 'required|numeric';
        $rules['PropertyNo']            = 'required';
        $rules['mobileNo']               = 'nullable|numeric';
        $rules['address']                = 'nullable';
        $rules['poleLandmark']           = "nullable";
        $rules['dtcCode']                = 'nullable';
        $rules['meterMake']              = 'nullable';
        $rules['meterNo']                = "required";
        $rules['meterDigit']             = 'nullable';
        $rules['meterCategory']          = "nullable";
        $rule['tabSize']                 = "required|numeric";
        $rule['meterState']              = "nullable";
        $rule['meterReadig']             = 'nullable|numeric';
        $rule['readingDate']             = 'nullable';
        $rules['amount']                  = 'required';
        $rules = [
            'connectionDate' => 'nullable|date_format:Y-m-d',
        ];

        $rule['disconnectionDate']        = 'nullable|date_format:Y-m-d';
        $rule['disconnedReading']         = "nullable";
        $rule['bookNo']                   = 'nullable';
        $rule['folioNo']                  = "nullable";
        $rule['buildingNo']               = 'nullable';
        $rule['noOfConnection']           = "nullable";
        $rule['isMeterRented']            = "nullable";
        $rule['rentAmount']               = "nullable";
        $rule['totalAmount']              = 'nullable';
        $rule['nearestConsumerNo']        = 'nullable';
        $rules['initialMeter']           = 'nullable';
        $rules['ownerName']               = 'nullable';
        $rules['guardianName']            = 'nullable';
        $rules['email']                   = 'nullable';
        $rules['category']                = 'required';
        $rules['propertyType']            = 'required';
        $rules['isMeterWorking']          = 'nullbale';
        $rules['connectionId']            = "nullable";
        $rules['propertyNoType']          = "nullable";
        $rules['connectionType']          = "required";

        return $rules;
    }



    //     if (isset($this->owners) && $this->owners) {
    //         $rules["owners.*.ownerName"]    = "required";
    //         $rules["owners.*.mobileNo"]     = "required|digits:10|regex:/[0-9]{10}/";
    //         $rules["owners.*.email"]        = "nullable|email";
    //     }
    //     if (isset($this->connection_through) && $this->connection_through == 1) {
    //         $rules['holdingNo'] = 'required|';
    //     }
    //     if (isset($this->connection_through) && $this->connection_through == 2) {
    //         $rules['safNo'] = 'required|';
    //     }
    //     return $rules;
    // }

    //    Validation Error Message
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'status'   => false,
                    'message'  => 'The given data was invalid',
                    'errors'   => $validator->errors()
                ],
                422
            )
        );
    }
}
