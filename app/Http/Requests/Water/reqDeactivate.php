<?php

namespace App\Http\Requests\Water;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class reqDeactivate extends FormRequest
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
            'consumerId'             => 'required|digits_between:1,9223372036854775807',
            'ulbId'                  => 'nullable|integer',
            'reason'                 => 'nullable|string',
            'remarks'                => 'nullable|string',
            'requestType'            => 'required|integer',
            'documents'              => 'nullable|array',
            'documents.*.image'      => 'nullable|mimes:png,jpeg,pdf,jpg',
            'documents.*.docCode'    => 'nullable|string',
            'documents.*.ownerDtlId' => 'nullable|integer',
        ];

        if ($this->request->get('requestType') == 6) {
            $rules['newName'] = 'required|string';
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
                200
            )
        );
    }
}
