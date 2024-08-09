<?php

namespace App\MicroServices\IdGenerator;

use App\Models\Masters\IdGenerationParam;
use App\Models\Property\PropProperty;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;

/**
 * | Created On-22/03/2023 
 * | Created By-Anshu Kumar
 * | Created for- Id Generation Service 
 */

class PrefixIdGenerator implements iIdGenerator
{
    protected $prefix;
    protected $paramId;
    protected $ulbId;
    protected $incrementStatus;
    protected $_mUlbWardMstr;
    protected $property;

    public function __construct(int $paramId, int $ulbId)
    {
        $this->paramId = $paramId;
        $this->ulbId = $ulbId;
        $this->incrementStatus = true;
        $this->_mUlbWardMstr = new UlbWardMaster();
        $this->property = new PropProperty();
    }

    /**
     * | Id Generation Business Logic 
     */
    public function generate(): string
    {
        $paramId = $this->paramId;
        $mIdGenerationParams = new IdGenerationParam();
        $mUlbMaster = new UlbMaster();
        $ulbDtls = $mUlbMaster::findOrFail($this->ulbId);

        $ulbDistrictCode = $ulbDtls->district_code;
        $ulbCategory = $ulbDtls->category;
        $code = $ulbDtls->code;

        $params = $mIdGenerationParams->getParams($paramId);
        $prefixString = $params->string_val;
        $stringVal = $ulbDistrictCode . $ulbCategory . $code;

        $stringSplit = collect(str_split($stringVal));
        $flag = ($stringSplit->sum()) % 9;
        $intVal = $params->int_val;
        // Case for the Increamental
        if ($this->incrementStatus == true) {
            $id = $stringVal . str_pad($intVal, 7, "0", STR_PAD_LEFT);
            $intVal += 1;
            $params->int_val = $intVal;
            $params->save();
        }

        // Case for not Increamental
        if ($this->incrementStatus == false) {
            $id = $stringVal  . str_pad($intVal, 7, "0", STR_PAD_LEFT);
        }

        return $prefixString . '/' . $id . $flag;
    }

    // public function generatev1($prop): string
    // {
    //     $paramId = $this->paramId;
    //     $mIdGenerationParams = new IdGenerationParam();
    //     $params = $mIdGenerationParams->getParams($paramId);
    //     $prefixString = $params->string_val; 
    //     $wardDtls = $this->_mUlbWardMstr->getWardById($prop->ward_mstr_id);
    //     // Params Generation
    //     $wardNo = str_pad($wardDtls->ward_name, 3, "0", STR_PAD_LEFT);
    //     $intVal = $params->int_val;

    //     if ($this->incrementStatus == true) {
    //         $id = str_pad($intVal, 7, "0", STR_PAD_LEFT);
    //         $intVal += 1;
    //         $params->int_val = $intVal;
    //         $params->save();
    //     } else {
    //         $id = str_pad($intVal, 7, "0", STR_PAD_LEFT);
    //     }

    //     // Calculate flag
    //     $stringVal = $prefixString . $id;
    //     $flag = array_sum(str_split($stringVal)) % 9;

    //     return $prefixString . '/' .$wardNo.$id ;
    // }

    public function generatev1($prop): string
    {
        $paramId = $this->paramId;
        $mIdGenerationParams = new IdGenerationParam();
        $params = $mIdGenerationParams->getParams($paramId);
        $prefixString = $params->string_val;
        $wardDtls = $this->_mUlbWardMstr->getWardById($prop->ward_mstr_id);

        // Params Generation
        $wardNo = str_pad($wardDtls->ward_name, 3, "0", STR_PAD_LEFT);
        $intVal = $params->int_val;

        // Increment the value if required
        if ($this->incrementStatus) {
            $id = str_pad($intVal, 3, "0", STR_PAD_LEFT); // Use 3 digits for id
            $intVal += 1;
            $params->int_val = $intVal;
            $params->save();
        } else {
            $id = str_pad($intVal, 3, "0", STR_PAD_LEFT); // Use 3 digits for id
        }

        return $prefixString . '/' . $wardNo . '/' . $id;
    }
}
