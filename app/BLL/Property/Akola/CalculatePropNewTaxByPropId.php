<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\RefPropCategory;
use App\Models\Property\RefPropConstructionType;
use App\Models\Property\RefPropFloor;
use App\Models\Property\RefPropOccupancyType;
use App\Models\Property\RefPropOwnershipType;
use App\Models\Property\RefPropType;
use App\Models\Property\RefPropUsageType;
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

    public $_ref_prop_floors;
    public $_ref_prop_construction_types;
    public $_ref_prop_occupancy_types;
    public $_ref_prop_usage_types;

    public $_ref_prop_ownership_types;
    public $_ref_prop_types;
    public $_ref_prop_categories;
    public $_ERROR = [];

    public function __construct($propId)
    {
        $this->_propFloors = new PropFloor();
        $this->_mPropOwners = new PropOwner();
        $this->_propDtls = PropProperty::find($propId);
        
    }

    public function starts()
    {
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
        // $this->storeDemand();     // 6
    }

    public function storeDemand()
    {
        $tax = new SafApprovalBll();   
        $tax->_activeSaf =  $this->_propDtls;    
        $tax->_replicatedPropId = $this->_propDtls->id;
        $tax->_calculateTaxByUlb = (object)(["_GRID"=>$this->_GRID]);
        $tax->generatTaxAccUlTc();
    }


    #====================test property==============
    public function readParamTbl()
    {
        $this->_ref_prop_floors = RefPropFloor::where("status",1)->get();
        $this->_ref_prop_construction_types = RefPropConstructionType::where("status",1)->get();
        $this->_ref_prop_occupancy_types = RefPropOccupancyType::where("status",1)->get();
        $this->_ref_prop_usage_types = RefPropUsageType::where("status",1)->get();

        $this->_ref_prop_ownership_types = RefPropOwnershipType::where("status",1)->get();
        $this->_ref_prop_types = RefPropType::where("status",1)->get();
        $this->_ref_prop_categories = RefPropCategory::where("status",1)->get();

    }

    public function testPropMetaData()
    {
        if(!$this->_propDtls->category_id)
        {
            $this->_ERROR[]= "Category not available";
        }
        elseif(!in_array($this->_propDtls->category_id,$this->_ref_prop_categories->pluck("id")->toArray()))
        {
            $this->_ERROR[]= "invalid Category id";
        }

        if(!$this->_propDtls->ownership_type_mstr_id)
        {
            $this->_ERROR[]= "Ownership not available";
        }
        elseif(!in_array($this->_propDtls->ownership_type_mstr_id,$this->_ref_prop_ownership_types->pluck("id")->toArray()))
        {
            $this->_ERROR[]= "invalid Ownership id";
        }

        if(!$this->_propDtls->prop_type_mstr_id)
        {
            $this->_ERROR[]= "Property Type not available";
        }
        elseif(!in_array($this->_propDtls->prop_type_mstr_id,$this->_ref_prop_types->pluck("id")->toArray()))
        {
            $this->_ERROR[]= "invalid Property Type id";
        }

        if(!$this->_propDtls->area_of_plot || !is_numeric($this->_propDtls->area_of_plot))
        {
            $this->_ERROR[]= "Plot area not available";
        }
    }

    public function insertRefValues($floor)
    {
        $floor->floor_name = ($this->_ref_prop_floors->where("id",$floor->floor_mstr_id)->first())->floor_name??"N/A";
        $floor->usage_type = ($this->_ref_prop_usage_types->where("id",$floor->usage_type_mstr_id)->first())->usage_type??"N/A";
        $floor->construction_type = ($this->_ref_prop_construction_types->where("id",$floor->const_type_mstr_id)->first())->construction_type??"N/A";
        $floor->construction_type = ($this->_ref_prop_construction_types->where("id",$floor->const_type_mstr_id)->first())->construction_type??"N/A";
        return $floor;
    }

    public function testIndividualFloor($floor,$key)
    {
        if(!$floor)
        {
            return;
        }
        $floor = $this->insertRefValues($floor);
        $floor = is_array($floor) ? $floor : collect($floor)->toArray();
        $erFloorDtls = "($key)Floor No -".$floor["floor_name"]." Usage Type -".$floor["usage_type"]." Construction Type -".$floor["construction_type"]." Of ";
        if(!$floor["floor_mstr_id"]??true)
        {
           $erFloorDtls.="floor mstr id is not available ";
        }
        elseif(!in_array($floor["floor_mstr_id"],$this->_ref_prop_floors->pluck("id")->toArray()))
        {
            $erFloorDtls.="floor mstr id is invalid ";
        }

        if(!$floor["usage_type_mstr_id"]??true)
        {
           $erFloorDtls.="floor usage type mstr id is not available ";
        }
        elseif(!in_array($floor["usage_type_mstr_id"],$this->_ref_prop_usage_types->pluck("id")->toArray()))
        {
            $erFloorDtls.="floor usage type mstr id is invalid ";
        }

        if(!$floor["const_type_mstr_id"]??true)
        {
           $erFloorDtls.="floor const type mstr id is not available ";
        }
        elseif(!in_array($floor["const_type_mstr_id"],$this->_ref_prop_construction_types->pluck("id")->toArray()))
        {
            $erFloorDtls.="floor const type mstr id is invalid ";
        }

        if(!$floor["occupancy_type_mstr_id"]??true)
        {
           $erFloorDtls.="floor occupancy type mstr id is not available ";
        }
        elseif(!in_array($floor["occupancy_type_mstr_id"],$this->_ref_prop_occupancy_types->pluck("id")->toArray()))
        {
            $erFloorDtls.="floor occupancy type mstr id is invalid ";
        }

        if(!$floor["builtup_area"]??true)
        {
           $erFloorDtls.="floor builtup area is not available ";
        }
        $this->_ERROR[]= $erFloorDtls;
    }

    public function testFloors()
    {
        if($this->_propDtls->prop_type_mstr_id==4)
        {
            return;
        }
        $propFloors = $this->_propFloors->getFloorsByPropId($this->_propDtls->id);
        if(collect($propFloors)->count()==0)
        { 
            $this->_ERROR[]= "floor not available but property is not a vacant land";
        }
        foreach($propFloors as $key=>$val)
        {
            $this->testIndividualFloor($val,$key);
        }
    }

    public function testData()
    {        
        $this->readParamTbl();
        $this->testPropMetaData();
        $this->testFloors();        
    }
}
