<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\PropSafsOwner;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * | Created by-Anshu Kumar
 * | Created For - Calculate Saf Taxes By Saf ID
 * | Status-Closed
 */
class CalculateSafTaxById extends TaxCalculator
{
    private $_safDtls;
    private $_REQUEST;
    private $_mPropActiveSafFloors;
    private $_mPropSafFloors;
    private $_mPropActiveSafOwners;
    private $_mPropSafOwner;

    public function __construct($safDtls)
    {
        $this->_mPropActiveSafFloors = new PropActiveSafsFloor();
        $this->_mPropSafFloors = new PropSafsFloor();
        $this->_mPropActiveSafOwners = new PropActiveSafsOwner();
        $this->_mPropSafOwner = new PropSafsOwner();
        $this->_safDtls = $safDtls;
        $this->generateRequests();                                      // making request
        parent::__construct($this->_REQUEST);                           // making parent constructor for tax calculator BLL
        $this->calculateTax();                                          // Calculate Tax with Tax Calculator
    }

    /**
     * | Generate Request for Calculation
     */
    public function generateRequests(): void
    {
        $calculationReq = [
            "propertyType" => $this->_safDtls->prop_type_mstr_id,
            "areaOfPlot" => $this->_safDtls->area_of_plot,
            "category" => $this->_safDtls->category_id,
            "dateOfPurchase" => $this->_safDtls->land_occupation_date,
            "previousHoldingId" => $this->_safDtls->previous_holding_id??0,
            "applyDate" => $this->_safDtls->application_date??null,
            "ward" => $this->_safDtls->ward_mstr_id??null,
            "zone" => $this->_safDtls->zone_mstr_id??null,
            "assessmentType" =>(flipConstants(Config::get("PropertyConstaint.ASSESSMENT-TYPE"))[$this->_safDtls->assessment_type]??''),
            "nakshaAreaOfPlot" => $this->_safDtls->naksha_area_of_plot,
            "isAllowDoubleTax" => $this->_safDtls->is_allow_double_tax,
            "floor" => [],
            "owner" => []
        ];

        // Get Floors
        if ($this->_safDtls->prop_type_mstr_id != 4) {
            $propFloors = $this->_mPropActiveSafFloors->getSafFloorsBySafId($this->_safDtls->id);

            if (collect($propFloors)->isEmpty())
                $propFloors = $this->_mPropSafFloors->getSafFloorsBySafId($this->_safDtls->id);

            if (collect($propFloors)->isEmpty())
                throw new Exception("Floors not available for this property");

            foreach ($propFloors as $floor) {
                $floorReq =  [
                    "floorNo" => $floor->floor_mstr_id,
                    "constructionType" =>  $floor->const_type_mstr_id,
                    "occupancyType" =>  $floor->occupancy_type_mstr_id??"",
                    "usageType" => $floor->usage_type_mstr_id,
                    "buildupArea" =>  $floor->builtup_area,
                    "dateFrom" =>  $floor->date_from
                ];
                array_push($calculationReq['floor'], $floorReq);
            }
        }

        // Get Owners
        $propFirstOwners = $this->_mPropActiveSafOwners->getOwnerDtlsBySafId1($this->_safDtls->id);
        if (collect($propFirstOwners)->isEmpty())
            $propFirstOwners = $this->_mPropSafOwner->getOwnerDtlsBySafId1($this->_safDtls->id);

        $ownerReq = [
            "isArmedForce" => $propFirstOwners->is_armed_force
        ];
        array_push($calculationReq['owner'], $ownerReq);
        $this->_REQUEST = new Request($calculationReq);
    }
}
