<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * | ✅✅Created by- Sandeep Bara
 * | Created for-Calculation of the tax By Created Property
 * | Status-Closed
 */
class CalculatePropNewTaxByPropId extends TaxCalculator
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
        $this->setCalculationDateFrom();
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
            "previousHoldingId" => $this->_propDtls->id ?? 0,
            "applyDate" => $this->_propDtls->application_date ?? null,
            "ward" => $this->_propDtls->ward_mstr_id ?? null,
            "zone" => $this->_propDtls->zone_mstr_id ?? null,
            // "assessmentType" => (flipConstants(Config::get("PropertyConstaint.ASSESSMENT-TYPE"))[$this->_propDtls->assessment_type] ?? ''),
            "nakshaAreaOfPlot" => $this->_propDtls->naksha_area_of_plot,
            "isAllowDoubleTax" => $this->_propDtls->is_allow_double_tax,
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
                    "dateFrom" =>  $floor->date_from,
                    "dateUpto" =>  $floor->date_upto
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
    public function setCalculationDateFrom()
    {
        $this->_lastDemand = ($this->_propDtls->PropLastDemands());
        list($fromYear, $lastYear) = explode("-", $this->_lastDemand->fyear ?? getFY());        
        $this->_calculationDateFrom = ($this->_lastDemand ? $lastYear : $fromYear). "-04-01";
    }

    public function readCalculatorParams()
    {
        parent::readCalculatorParams();
        $this->setCalculationDateFrom();
    }

    public function calculateTax()
    {
        $this->readCalculatorParams();      // 1

        $this->generateFloorWiseTax();      // 2

        $this->generateVacantWiseTax();     // 3

        $this->generateFyearWiseTaxes();    // 4

        $this->generatePayableAmount();     // 5
        $this->storeDemand();     // 6
    }

    public function storeDemand()
    {
        $tax = new SafApprovalBll();   
        $tax->_activeSaf =  $this->_propDtls;    
        $tax->_replicatedPropId = $this->_propDtls->id;
        $tax->_calculateTaxByUlb = (object)(["_GRID"=>$this->_GRID]);
        $tax->generatTaxAccUlTc();
    }

}
