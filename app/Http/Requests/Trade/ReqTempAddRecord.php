<?php

namespace App\Http\Requests\Trade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ReqTempAddRecord extends FormRequest
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
        return [
            'applyWith'                         => 'required|integer',
            'firmType'                          => 'required|integer',
            'ownershipType'                     => 'required|integer',
            'applyFrom'                         => 'required|date',
            'applyUpto'                         => 'required|date|after_or_equal:applyFrom',
            'propertyType'                      => 'required|integer',
            'wardNo'                            => 'required|integer',
            'areaSqft'                          => 'required|numeric',
            'firmName'                          => 'required|string',
            'firmNameMarathi'                   => 'nullable|string',
            'businessAddress'                   => 'required|string',
            'landmark'                          => 'nullable|string',
            'pincode'                           => 'required|digits:6',
            'premisesOwner'                     => 'nullable|string',
            'businessDescription'               => 'nullable|string',
            'tocStatus'                         => 'required|integer',
            'zoneId'                            => 'required|integer',
            'applicationType'                   => 'required|string|in:TEMPORARY,PERMANANT',
            'owners'                            => 'required|array',
            'owners.*.businessOwnerName'        => 'required|string',
            'owners.*.ownerNameMarathi'         => 'nullable|string',
            'owners.*.guardianName'             => 'nullable|string',
            'owners.*.guardianNameMarathi'      => 'nullable|string',
            'owners.*.mobileNo'                 => 'required|digits:10',
            'documents'                         => 'required|array',
            'documents.*.image'                 => 'required|file',
            'documents.*.docCode'               => 'required|string',
            'documents.*.ownerDtlId'            => 'nullable|string'
        ];
    }
    /**
     * | Error Message
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   => 'Validation errors',
            'data'      => $validator->errors()
        ], 422),);
    }
}
