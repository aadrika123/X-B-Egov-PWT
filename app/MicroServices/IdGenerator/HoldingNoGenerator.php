<?php

namespace App\MicroServices\IdGenerator;

use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSaf;
use App\Models\Property\RefPropUsageType;
use App\Models\UlbWardMaster;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

// ╔═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╗
// ║                                                ✅  Author-Anshu Kumar ✅                                                         ║ 
// |                                                    Created On-13-07-2023                                                          |          
// |                                                     Created On-13-07-2023                                                         |            
// |                                               Created for the Id generation of Holding No                                         |
// |                                                         Status-Open                                                               |
// ╚═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╝ 

class HoldingNoGenerator
{
    private $_mUlbWardMstr;
    private $_cAssessmentTypes;
    private $_mPropActiveSafs;
    private $_mPropSafs;
    private $_activeSafs;
    private $_refPropUsageType;
    private $_redisConn;
    private $_mPropActiveSafFloors;
    private $_readFloors;
    public function __construct()
    {
        $this->_mUlbWardMstr = new UlbWardMaster();
        $this->_cAssessmentTypes = Config::get('PropertyConstaint.ASSESSMENT-TYPE');
        $this->_mPropActiveSafs = new PropActiveSaf();
        $this->_mPropSafs = new PropSaf();
        $this->_refPropUsageType = new RefPropUsageType();
        $this->_redisConn = Redis::connection();
        $this->_mPropActiveSafFloors = new PropActiveSafsFloor();
    }

    /**
     * | Holding No Generation
     */
    public function generateHoldingNo($activeSaf)
    {
        $this->_activeSafs = $activeSaf;
        if(in_array($activeSaf->assessment_type, [$this->_cAssessmentTypes[2],$this->_cAssessmentTypes[3],$this->_cAssessmentTypes[5]]) &&  $activeSaf->holding_no)
        {
            return $activeSaf->holding_no;
        }
        $c=0;
        while(true)
        {
            if($this->_activeSafs)
            $wardDtls = $this->_mUlbWardMstr->getWardById($activeSaf->ward_mstr_id);
            if (collect($wardDtls)->isEmpty())
                throw new Exception("Ward Details Not Available");

            // Params Generation
            $wardNo = str_pad($wardDtls->ward_name, 3, "0", STR_PAD_LEFT);
            $roadType = str_pad($activeSaf->road_type_mstr_id, 3, "0", STR_PAD_LEFT);
            $counter = str_pad($wardDtls->holding_counter, 4, "0", STR_PAD_LEFT);
            $subHoldingCounter = 0;                     // For New Assessment

            if (in_array($activeSaf->assessment_type, [$this->_cAssessmentTypes[3], $this->_cAssessmentTypes[4], $this->_cAssessmentTypes[5]]))
                $subHoldingCounter = $this->countSubHoldings();

            $subHoldingNo = str_pad($subHoldingCounter, 3, "0", STR_PAD_LEFT);
            $read14Digit = $this->read14Digit();
            $read15Digit = $this->read15Digit();
            $holdingNo = $wardNo . $roadType . $counter . $subHoldingNo . $read14Digit . $read15Digit;
            $wardDtls->holding_counter += 1;  
            $wardDtls->save();
            if(!PropProperty::where("holding_no",$holdingNo)->first() || $c>50)
            {
                break;
            }
            $c+=1;
        }
        return $holdingNo;
    }

    /**
     * | Count SubHolding for Mutation Bifurcation etc.
     */
    public function countSubHoldings()
    {
        $previousHoldingId = $this->_activeSafs->previousHoldingId;
        $counterActiveHoldings = $this->_mPropActiveSafs->countPreviousHoldings($previousHoldingId);
        $counterHoldings = $this->_mPropSafs->countPreviousHoldings($previousHoldingId);
        return $counterActiveHoldings + $counterHoldings;
    }

    /**
     * | Read the forteen digit of holding no
     */
    public function read14Digit()
    {
        if ($this->_activeSafs->prop_type_mstr_id != 4) {
            $this->_readFloors = $this->_mPropActiveSafFloors->getSafFloorsBySafId($this->_activeSafs->id);
            $pluckedUsageTypeIds = $this->_readFloors->pluck('usage_type_mstr_id');
            $usageType = json_decode(Redis::get('property-all-usage-types'));
            // Property Usage Types
            if (collect($usageType)->isEmpty()) {                                                      // For Buildings
                $usageType = $this->_refPropUsageType->propAllUsageType();
                $this->_redisConn->set('property-all-usage-types', json_encode($usageType));
            }
            $usageTypeCodes = collect($usageType)->whereIn('id', $pluckedUsageTypeIds)
                ->pluck('usage_code');

            if ($usageTypeCodes->isEmpty())
                throw new Exception("Deactivated Usage Types given");

            $isUsageCodeSame = isElementsSame($usageTypeCodes);
            $digit14 = $isUsageCodeSame ? ($usageTypeCodes->first()) : "X";
        }

        if ($this->_activeSafs->prop_type_mstr_id == 4)
            $digit14 = "Z";                                                       // For Vacant Land

        return $digit14;
    }


    /**
     * | Read 15 th digit of the holding
     */
    public function read15Digit()
    {
        $digit15 = -1;
        if ($this->_activeSafs->prop_type_mstr_id == 4)                     // Vacant Land
            $digit15 = 0;
        else
            $pluckedConsType = $this->_readFloors->pluck('const_type_mstr_id');

        if (in_array($this->_activeSafs->prop_type_mstr_id, [2, 3])) {          // Multistored Building
            $isConstTypeSame = isElementsSame($pluckedConsType);
            $digit15 = $isConstTypeSame ? ($pluckedConsType->first()) : 4;
        }

        if (in_array($this->_activeSafs->prop_type_mstr_id, [1, 5])) {          // Super Structure/Occupied Building
            $isConstTypeSame = isElementsSame($pluckedConsType);
            $digit15 = $isConstTypeSame ? ($pluckedConsType->first() + 4) : 8;
        }

        if ($digit15 == -1)
            throw new Exception("15th Digit Not Found");

        return $digit15;
    }
}
