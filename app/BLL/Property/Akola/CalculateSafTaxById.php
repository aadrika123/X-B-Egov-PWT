<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\PropSafsOwner;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropSafVerificationDtl;
use App\Traits\Property\SAF;
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
    use SAF;
    private $_safDtls;
    private $_REQUEST;
    private $_mPropActiveSafFloors;
    private $_mPropSafFloors;
    private $_mPropActiveSafOwners;
    private $_mPropSafOwner;
    private $_mLastVerification;
    private $_mLastFloorVerification;

    public function __construct($safDtls)
    {
        $this->_mPropActiveSafFloors = new PropActiveSafsFloor();
        $this->_mPropSafFloors = new PropSafsFloor();
        $this->_mPropActiveSafOwners = new PropActiveSafsOwner();
        $this->_mPropSafOwner = new PropSafsOwner();
        $this->_safDtls = $safDtls;
        $this->_mLastVerification = PropSafVerification::where("saf_id", $this->_safDtls->id)->where("status", 1)->orderBy("id", "DESC")->first();
        $this->_mLastFloorVerification = PropSafVerificationDtl::where("verification_id", $this->_mLastVerification->id ?? 0)->where("status", 1)->orderBy("id", "DESC")->get();
        $this->_safDtls = $this->addjustVerifySafDtls($this->_safDtls, $this->_mLastVerification);
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
            "previousHoldingId" => $this->_safDtls->previous_holding_id ?? 0,
            "applyDate" => $this->_safDtls->application_date ?? null,
            "approvedDate" => $this->_safDtls->saf_approved_date ?? null,
            "ward" => $this->_safDtls->ward_mstr_id ?? null,
            "zone" => $this->_safDtls->zone_mstr_id ?? null,
            "assessmentType" => (flipConstants(Config::get("PropertyConstaint.ASSESSMENT-TYPE"))[$this->_safDtls->assessment_type] ?? ''),
            "nakshaAreaOfPlot" => $this->_safDtls->naksha_area_of_plot,
            "isAllowDoubleTax" => $this->_safDtls->is_allow_double_tax,
            "buildingPlanApprovalDate"=>$this->_safDtls->building_plan_approval_date,
            "buildingPlanCompletionDate"=> $this->_safDtls->building_plan_completion_date,
            "floor" => [],
            "owner" => []
        ];

        // Get Floors
        if ($this->_safDtls->prop_type_mstr_id != 4) {
            $propFloors = $this->_mPropActiveSafFloors->getSafFloorsBySafId($this->_safDtls->id);
            if (collect($propFloors)->isEmpty())
                $propFloors = $this->_mPropSafFloors->getSafFloorsBySafId($this->_safDtls->id);

            if (collect($propFloors)->isEmpty() && collect($this->_mLastFloorVerification)->isEmpty())
                throw new Exception("Floors not available for this property");

            $notInApplication = collect($this->_mLastFloorVerification)->whereNotIn("saf_floor_id", collect($propFloors)->pluck("id"));
            foreach ($propFloors as $floor) {
                $verifyFloor = collect($this->_mLastFloorVerification->values())->whereIn("saf_floor_id", $floor->id)->first();
                $floor  = $this->addjustVerifyFloorDtls($floor, $verifyFloor);
                $floor  = $this->addjustVerifyFloorDtlVal($floor);
                $floorReq =  [
                    "floorNo" => $floor->floor_mstr_id,
                    "floorName" => $floor->floor_name, #name value
                    "constructionType" =>  $floor->const_type_mstr_id,
                    "occupancyType" =>  $floor->occupancy_type_mstr_id ?? "",
                    "usageType" => $floor->usage_type_mstr_id,
                    "usageTypeName" => $floor->usage_type, #name value
                    "buildupArea" =>  $floor->builtup_area,
                    "dateFrom" =>  $floor->date_from,
                    "dateUpto" =>  $floor->date_upto,
                    "rentAmount" =>  $floor->rent_amount,
                    "rentAgreementDate" =>  $floor->rent_agreement_date,
                    "propFloorDetailId" =>$floor->prop_floor_details_id,
                ];
                array_push($calculationReq['floor'], $floorReq);
            }
            foreach ($notInApplication as  $newfloor) {
                $newfloorObj = new PropActiveSafsFloor();

                $floor  = $this->addjustVerifyFloorDtls($newfloorObj, $newfloor);
                $floor  = $this->addjustVerifyFloorDtlVal($floor);
                $floorReq =  [
                    "floorNo" => $floor->floor_mstr_id,
                    "floorName" => $floor->floor_name,
                    "constructionType" =>  $floor->const_type_mstr_id,
                    "occupancyType" =>  $floor->occupancy_type_mstr_id ?? "",
                    "usageType" => $floor->usage_type_mstr_id,
                    "usageTypeName" => $floor->usage_type, #name value
                    "buildupArea" =>  $floor->builtup_area,
                    "dateFrom" =>  $floor->date_from,
                    "dateUpto" =>  $floor->date_upto,
                    "rentAmount" =>  $floor->rent_amount??null,
                    "rentAgreementDate" =>  $floor->rent_agreement_date??null,
                    "propFloorDetailId" =>$floor->prop_floor_details_id,
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
