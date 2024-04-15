<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use Exception;
use Illuminate\Http\Request;

/**
 * | ✅✅Created by-Anshu Kumar
 * | Created for-Calculation of the tax By Created Property
 * | Status-Closed
 */
class CalculatePropTaxByPropId extends TaxCalculator
{
    private $_propDtls;
    private $_REQUEST;
    private $_propFloors;
    private $_mPropOwners;

    public function __construct($propId)
    {
        $this->_propFloors = new PropFloor();
        $this->_mPropOwners = new PropOwner();
        $this->_propDtls = PropProperty::find($propId);

        if (collect($this->_propDtls)->isEmpty())
            throw new Exception("Property Details not available");

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
            "propertyType" => $this->_propDtls->prop_type_mstr_id,
            "areaOfPlot" => $this->_propDtls->area_of_plot,
            "category" => $this->_propDtls->category_id,
            "dateOfPurchase" => $this->_propDtls->land_occupation_date,
            "floor" => [],
            "owner" => []
        ];

        // Get Floors
        if ($this->_propDtls->prop_type_mstr_id != 4) {
            $propFloors = $this->_propFloors->getFloorsByPropId($this->_propDtls->id);

            if (collect($propFloors)->isEmpty())
                throw new Exception("Floors not available for this property");

            foreach ($propFloors as $floor) {
                $floorReq =  [
                    "floorNo" => $floor->floor_mstr_id,
                    "constructionType" =>  $floor->const_type_mstr_id,
                    "occupancyType" =>  $floor->occupancy_type_mstr_id??"",
                    "usageType" => $floor->usage_type_mstr_id,
                    "buildupArea" =>  $floor->builtup_area,
                    "dateFrom" =>  $floor->date_from ,
                    "dateUpto" =>  $floor->date_upto,
                    "propFloorDetailId" =>$floor->prop_floor_details_id,
                ];
                array_push($calculationReq['floor'], $floorReq);
            }
        }

        // Get Owners
        $propFirstOwners = $this->_mPropOwners->firstOwner($this->_propDtls->id);
        if (collect($propFirstOwners)->isEmpty())
            throw new Exception("Owner Details not Available");

        $ownerReq = [
            "isArmedForce" => $propFirstOwners->is_armed_force
        ];
        array_push($calculationReq['owner'], $ownerReq);
        $this->_REQUEST = new Request($calculationReq);
    }
}
