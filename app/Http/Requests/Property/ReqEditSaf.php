<?php

namespace App\Http\Requests\Property;

use App\Models\Property\PropActiveSaf;

class ReqEditSaf extends reqApplySaf
{
    
    public function rules()
    {
        $tableName = (new PropActiveSaf)->gettable();
        $rules = parent::rules();
        $rules["id"]="required|digits_between:1,9223372036854775807|exists:prop_active_safs,id";
        $rules["previousHoldingId"]="nullable";
        $rules["owner.*.propOwnerDetailId"]       = "nullable|digits_between:1,9223372036854775807";
        if($this->propertyType != 4)
        {
            $rules["floor.*.propFloorDetailId"] =   "nullable|digits_between:1,9223372036854775807";
        }
        return $rules;
    }
}
