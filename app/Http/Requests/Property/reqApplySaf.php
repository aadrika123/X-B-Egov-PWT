<?php

namespace App\Http\Requests\Property;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;

class reqApplySaf extends FormRequest
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
        $mNowDate     = Carbon::now()->format("Y-m-d");
        $mNowDateYm   = Carbon::now()->format("Y-m");

        if (isset($this->edit) && $this->edit == true)
            $rules['assessmentType'] = "nullable";
        else
            $rules['assessmentType'] = "required|int|in:1,2,3,4,5";

        $rules['category'] = "required|int";
        if (isset($this->assessmentType) && $this->assessmentType == 3) {
            $rules['transferModeId'] = "required";
            $rules['dateOfPurchase'] = "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
            $rules["isOwnerChanged"] = "required|bool";
            $rules["saleValue"]      = "required|digits_between:1,9223372036854775807";
        }
        if (isset($this->assessmentType) && $this->assessmentType == 4) {
            $rules['bifurcatedPlot'] = "required|numeric|max:" . $this->areaOfPlot;
            $rules['biDateOfPurchase'] = "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
            $rules['transferModeId'] = "required";
            $rules["saleValue"]      = "required|digits_between:1,9223372036854775807";
        }
        if (isset($this->assessmentType) && $this->assessmentType == 5) {
            $rules['holdingNoLists'] = "required|array";
            $rules['holdingNoLists.*'] = "required";
        }
        if (isset($this->isGBSaf))
            $rules['isGBSaf'] = "required|bool";

        if ($this->propertyType == 3)
            $rules['apartmentId'] = "required|integer";

        $rules['ward']          = "required|digits_between:1,9223372036854775807";
        $rules['propertyType']  = "required|int";
        $rules['ownershipType'] = "required|int";
        $rules['areaOfPlot']    = "required|numeric|not_in:0";
        $rules['owner'] = "required|array";
        if (isset($this->owner) && $this->owner) {
            $rules["owner.*.ownerName"] = "required";
            $rules["owner.*.gender"] = "required";
            $rules["owner.*.dob"] = "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
            $rules["owner.*.isArmedForce"] = "required|in:true,false,0,1";
            $rules["owner.*.isSpeciallyAbled"] = "required|in:true,false,0,1";
        }
        $rules['isMobileTower'] = "required|bool";
        if (isset($this->isMobileTower) && $this->isMobileTower) {
            $rules['mobileTower.area'] = "required|numeric";
            $rules['mobileTower.dateFrom'] = "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
        }
        $rules['isHoardingBoard'] = "required|bool";
        if (isset($this->isHoardingBoard) && $this->isHoardingBoard) {
            $rules['hoardingBoard.area'] = "required|numeric";
            $rules['hoardingBoard.dateFrom'] = "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
        }
        $rules['isPetrolPump'] = "required|bool";
        if (isset($this->isPetrolPump) && $this->isPetrolPump) {
            $rules['petrolPump.area'] = "required|numeric";
            $rules['petrolPump.dateFrom'] = "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
        }

        if ($this->propertyType == 2)                                           // Land Occupation Date for Independent Building
            $rules['landOccupationDate'] = "nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate";

        if (isset($this->propertyType) && $this->propertyType == 4) {
            $rules['landOccupationDate'] = "nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate";
        } else {
            $rules['floor']        = "required|array";
            if (isset($this->floor) && $this->floor) {
                $rules["floor.*.propFloorDetailId"] =   "nullable|numeric";
                $rules["floor.*.floorNo"]           =   "required|int";
                $rules["floor.*.usageType"]         =   "required|int";
                $rules["floor.*.constructionType"]  =   "required";
                $rules["floor.*.occupancyType"]     =   "required|int";
                $rules["floor.*.buildupArea"]       =   "required|numeric|not_in:0";
                $rules["floor.*.dateFrom"]          =   "required|date|date_format:Y-m-d|before_or_equal:$mNowDate" . ((in_array($this->assessmentType, [3,4,5])) ? "" :  "|after_or_equal:$this->dateOfPurchase");
                $rules["floor.*.dateUpto"]          =   "nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate|before:$this->dateFrom";
                $rules["floor.*.rentAmount"]        =    "required_if:floor.*.occupancyType,2|not_in:0";   
                $rules["floor.*.rentAgreementDate"] =    "required_if:floor.*.occupancyType,2";             
                // $rules["floor.*.dateFrom"]          =   "required|date|date_format:Y-m-d|before_or_equal:$mNowDate|after_or_equal:$this->dateOfPurchase";

                // if ($this->assessmentType == 4)
                //     $rules["floor.*.biBuildupArea"] =   "required|numeric";
            }
        }

        $rules['isWaterHarvesting'] = "required|bool";

        // if ($this->isWaterHarvesting == 1)
        //     $rules['rwhDateFrom'] = 'nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate';

        if (isset($this->assessmentType) && $this->assessmentType != 1) {           // Holding No Required for Reassess,Mutation,Bifurcation,Amalgamation
            $rules['previousHoldingId'] = "required|numeric";
        }
        $rules['zone']           = "required|int";
        if (isset($this->assessmentType) && $this->assessmentType == 1 || ($this->assessmentType == 3 && $this->isOwnerChanged)) {
            if ($this->formType != 'taxCalculator')
                $rules['owner']        = "required|array";
            else
                $rules['owner'] = "nullable|array";

            if (isset($this->owner) && $this->owner) {
                $rules["owner.*.ownerName"]           =   "required|regex:/^[A-Za-z.\s]+$/";
                $rules["owner.*.gender"]              =   "required|string";
                $rules["owner.*.dob"]                 =   "required|date|date_format:Y-m-d|before_or_equal:$mNowDate";
                $rules["owner.*.guardianName"]        =   "nullable|regex:/^[A-Za-z.\s]+$/";
                $rules["owner.*.relation"]            =   "nullable|string|in:S/O,W/O,D/O,C/O";
                $rules["owner.*.mobileNo"]            =   "required|digits:10|regex:/[0-9]{10}/";
                $rules["owner.*.email"]               =   "email|nullable";
                $rules["owner.*.pan"]                 =   "string|nullable";
                $rules["owner.*.aadhar"]              =   "digits:12|regex:/[0-9]{12}/|nullable";
                $rules["owner.*.isArmedForce"]        =   "required|bool";
                $rules["owner.*.isSpeciallyAbled"]    =   "required|bool";
                $rules["owner.*.ownerNameMarathi"]    =   "required|string";
                $rules["owner.*.guardianNameMarathi"] =   "nullable|string";
            }
        }
        return $rules;
    }

    // Validation Error Message
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'status' => false,
                    'message' => $validator->errors(),
                    'errors' => $validator->errors()
                ],
                200
            )
        );
    }
}
