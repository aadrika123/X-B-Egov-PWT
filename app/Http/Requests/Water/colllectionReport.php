<?php

namespace App\Http\Requests\Water;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class colllectionReport extends FormRequest
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
        $rules = [
            "fromDate" => "nullable|date|date_format:Y-m-d",
            "uptoDate" => "nullable|date|date_format:Y-m-d",
            "wardId" => "nullable|digits_between:1,9223372036854775807",
            "zoneId" => "nullable|digits_between:1,9223372036854775807",
            "userId" => "nullable|digits_between:1,9223372036854775807",
            "paymentMode" => "nullable",
            "page" => "nullable|digits_between:1,9223372036854775807",
            "perPage" => "nullable|digits_between:1,9223372036854775807",
            "all" => "nullable|in:1,0",
            "footer" => "nullable|in:1,0",
            "metertype"=> "nullable|in:1,2",
            "propertyType"=> 'nullable|in:1,2,3'
        ];
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
