<?php

namespace App\Http\Requests\Water;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Contracts\Service\Attribute\Required;

class siteAdjustment extends FormRequest
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
        $rules['areaSqft']          = 'required|';
        $rules['propertyTypeId']    = 'required|int:1,2,3,4,5,6,7,8';
        $rules['connectionTypeId']  = 'required|int|in:1,2';
        $rules['latitude']          = 'required|';
        $rules['longitude']         = 'required|';
        $rules['pipelineTypeId']    = 'required|int|in:1,2';
        $rules['pipelineSize']      = 'required|int';
        $rules['pipelineSizeType']  = 'required|in:CI,DI';
        $rules['diameter']          = 'required|int|in:15,20,25';
        $rules['pipeQuality']       = 'required|in:GI,HDPE,PVC 80';
        // $rules['feruleSize']        = 'required|int|in:6,10,12,16';
        $rules['feruleSize']        = 'required|';
        $rules['roadType']          = 'required|in:RMC,PWD';
        // $rules['category']          = 'required|in:APL,BPL';
        $rules['category']          = 'required|';
        $rules['tsMap']             = 'required|int|in:0,1';
        $rules['applicationId']     = 'required|';

        if (isset($this->owners) && $this->owners) {
            $rules["owners.*.ownerName"]    = "nullable";
            $rules["owners.*.mobileNo"]     = "nullable|digits:10|regex:/[0-9]{10}/";
            $rules['guardianName']          = 'nullable';
            $rules["owners.*.email"]        = "nullable|email";
        }
        return $rules;
    }

    // Validation Error Message
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
