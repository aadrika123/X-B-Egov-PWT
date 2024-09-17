<?php

namespace App\BLL\Property\Akola;

use App\MicroServices\IdGenerator\HoldingNoGenerator;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\MicroServices\IdGenerator\PropIdGenerator;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropAdvance;
use App\Models\Property\PropAssessmentHistory;
use App\Models\Property\PropDemand;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropPendingArrear;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSafMemoDtl;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropSafVerificationDtl;
use App\Models\Property\PropTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | Created On-28-08-2023
 * | Created by-Anshu Kumar
 * | Created for the Saf Approval
 */

/**
 * =========== Target ===================
 * 1) Property Generation and Replication
 * 2) Approved Safs and floors Replication
 * 3) Fam Generation
 * --------------------------------------
 * @return holdingNo
 * @return ptNo
 * @return famNo
 */
class SafApprovalBll
{
    private $_safId;
    private $_mPropActiveSaf;
    private $_mPropActiveSafOwner;
    private $_mPropActiveSafFloor;
    public $_activeSaf;
    private $_ownerDetails;
    private $_floorDetails;
    private $_toBeProperties;
    public $_replicatedPropId;
    private $_mPropSafVerifications;
    private $_mPropSafVerificationDtls;
    private $_verifiedPropDetails;
    private $_verifiedFloors;
    private $_mPropFloors;
    public $_calculateTaxByUlb;
    public $_holdingNo;
    public $_ptNo;
    public $_famNo;
    public $_famId;
    public $_SkipFiledWorkWfMstrId = [];
    public $_paidTotalCurrentYearTax = 0;
    public $_assessmentHistoryId = null;

    //prity pandey 16-09-24
    private $_propDtls;
    private $_REQUEST;
    private $_propFloors;
    private $_mPropOwners;
    // Initializations
    public function __construct()
    {
        $this->_mPropActiveSaf = new PropActiveSaf();
        $this->_mPropActiveSafFloor = new PropActiveSafsFloor();
        $this->_mPropActiveSafOwner = new PropActiveSafsOwner();
        $this->_mPropSafVerifications = new PropSafVerification();
        $this->_mPropSafVerificationDtls = new PropSafVerificationDtl();
        $this->_mPropFloors = new PropFloor();
        $wfContent = Config::get('workflow-constants');
        $this->_SkipFiledWorkWfMstrId = [
            $wfContent["SAF_MUTATION_ID"],
            $wfContent["SAF_BIFURCATION_ID"],
        ];
        //prity pandey 16-09-24
        $this->_mPropOwners = new PropOwner();
    }

    /**
     * | Process of approval
     * | @param safId
     */
    public function approvalProcess($safId)
    {
        $this->_safId = $safId;

        $this->readParams();                    // ()

        $this->generateHoldingNo();

        $this->replicateProp();                 // ()

        $this->famGeneration();                 // ()

        $this->replicateSaf();                  // ()

        $this->transerMutationDemands();

        $this->generatTaxAccUlTc();

        $this->transferPropertyBifucation();
        $this->deactivateAmalgamateProp();
    }


    /**
     * | Read Parameters                            // ()
     */
    public function readParams()
    {
        $this->_activeSaf = $this->_mPropActiveSaf->getQuerySafById($this->_safId);
        $this->_ownerDetails = $this->_mPropActiveSafOwner->getQueSafOwnersBySafId($this->_safId);
        $this->_floorDetails = $this->_mPropActiveSafFloor->getQSafFloorsBySafId($this->_safId);
        $this->_verifiedPropDetails = $this->_mPropSafVerifications->getVerifications($this->_safId);
        $this->_toBeProperties = $this->_mPropActiveSaf->toBePropertyBySafId($this->_safId);
        if (collect($this->_verifiedPropDetails)->isEmpty()) {
            $this->_verifiedPropDetails = $this->_mPropSafVerifications->getVerifications2($this->_safId);
        }
        if (collect($this->_verifiedPropDetails)->isEmpty() && (!in_array($this->_activeSaf->workflow_id, $this->_SkipFiledWorkWfMstrId)))
            throw new Exception("Ulb Verification Details not Found");
        if (collect($this->_verifiedPropDetails)->isEmpty()) {
            $this->_verifiedPropDetails[] = (object)[
                "id" => 0,
                "saf_id" => $this->_activeSaf->id,
                "agency_verification" => $this->_activeSaf->is_agency_verified,
                "ulb_verification" => $this->_activeSaf->is_field_verified,
                "prop_type_id" => $this->_activeSaf->prop_type_mstr_id,
                "area_of_plot" => $this->_activeSaf->area_of_plot,
                "verified_by" => null,
                "ward_id" => $this->_activeSaf->ward_mstr_id,
                "zone_mstr_id" => $this->_activeSaf->zone_mstr_id,
                "has_mobile_tower" => $this->_activeSaf->is_mobile_tower,
                "tower_area" => $this->_activeSaf->tower_area,
                "tower_installation_date" => $this->_activeSaf->tower_installation_date,
                "has_hoarding" => $this->_activeSaf->is_hoarding_board,
                "hoarding_area" => $this->_activeSaf->hoarding_area,
                "hoarding_installation_date" => $this->_activeSaf->hoarding_installation_date,
                "is_petrol_pump" => $this->_activeSaf->is_petrol_pump,
                "underground_area" => $this->_activeSaf->under_ground_area,
                "petrol_pump_completion_date" => $this->_activeSaf->petrol_pump_completion_date,
                "has_water_harvesting" => $this->_activeSaf->is_water_harvesting,
                "created_at" => $this->_activeSaf->created_at,
                "updated_at" => $this->_activeSaf->updated_at,
                "status" => $this->_activeSaf->status,
                "user_id" => 0,
                "ulb_id" => $this->_activeSaf->ulb_id,
                "category_id" => $this->_activeSaf->category_id,
            ];
            $this->_verifiedFloors = $this->_mPropActiveSafFloor->getSafFloorsAsFieldVrfDtl($this->_safId);
        } else {
            $this->_verifiedFloors = $this->_mPropSafVerificationDtls->getVerificationDetails($this->_verifiedPropDetails[0]->id);
        }
    }

    /**
     * | Holding No Generation
     */
    public function generateHoldingNo()
    {
        $holdingNoGenerator = new HoldingNoGenerator;
        $ptParamId = Config::get('PropertyConstaint.PT_PARAM_ID');
        $idGeneration = new PrefixIdGenerator($ptParamId, $this->_activeSaf->ulb_id);
        // Holding No Generation
        $holdingNo = $holdingNoGenerator->generateHoldingNo($this->_activeSaf);
        $this->_holdingNo = $holdingNo;
        $ptNo = $idGeneration->generate();
        $this->_ptNo = $ptNo;
        $this->_activeSaf->pt_no = $ptNo;                        // Generate New Property Tax No for All Conditions
        $this->_activeSaf->holding_no = $holdingNo;
        $this->_activeSaf->save();
    }

    /**
     * | Replication of property()
     */
    public function replicateProp()
    {
        if (!in_array($this->_activeSaf->assessment_type, ['New Assessment', 'Mutation', 'Bifurcation'])) #update Old Property According to New Data
        {
            return $this->updateOldHolding();
        }
        // Self Assessed Saf Prop Properties and Floors
        $propProperties = $this->_toBeProperties->replicate();
        $propProperties->setTable('prop_properties');
        $propProperties->saf_id = $this->_activeSaf->id;
        $propProperties->holding_no = $this->_activeSaf->holding_no;
        $propProperties->new_holding_no = $this->_activeSaf->holding_no;
        $propProperties->property_no = $this->_activeSaf->property_no;
        $propProperties->save();

        $this->_replicatedPropId = $propProperties->id;
        // ✅Replication of Verified Saf Details by Ulb TC
        $propProperties->prop_type_mstr_id = $this->_verifiedPropDetails[0]->prop_type_id;
        $propProperties->area_of_plot = $this->_verifiedPropDetails[0]->area_of_plot;
        // if ($this->_activeSaf->assessment_type == 'Bifurcation')
        //     $propProperties->area_of_plot = $this->_activeSaf->bifurcated_plot_area;

        $propProperties->ward_mstr_id = $this->_verifiedPropDetails[0]->ward_id;
        $propProperties->zone_mstr_id = $this->_verifiedPropDetails[0]->zone_mstr_id ? $this->_verifiedPropDetails[0]->zone_mstr_id : $propProperties->zone_mstr_id;
        $propProperties->is_mobile_tower = $this->_verifiedPropDetails[0]->has_mobile_tower;
        $propProperties->tower_area = $this->_verifiedPropDetails[0]->tower_area;
        $propProperties->tower_installation_date = $this->_verifiedPropDetails[0]->tower_installation_date;
        $propProperties->is_hoarding_board = $this->_verifiedPropDetails[0]->has_hoarding;
        $propProperties->hoarding_area = $this->_verifiedPropDetails[0]->hoarding_area;
        $propProperties->hoarding_installation_date = $this->_verifiedPropDetails[0]->hoarding_installation_date;
        $propProperties->is_petrol_pump = $this->_verifiedPropDetails[0]->is_petrol_pump;
        $propProperties->under_ground_area = $this->_verifiedPropDetails[0]->underground_area;
        $propProperties->petrol_pump_completion_date = $this->_verifiedPropDetails[0]->petrol_pump_completion_date;
        $propProperties->is_water_harvesting = $this->_verifiedPropDetails[0]->has_water_harvesting;
        $propProperties->save();

        // ✅✅Verified Floors replication
        foreach ($this->_verifiedFloors as $floorDetail) {
            $floorReq = [
                "property_id" => $this->_replicatedPropId,
                "saf_id" => $this->_safId,
                "floor_mstr_id" => $floorDetail->floor_mstr_id,
                "usage_type_mstr_id" => $floorDetail->usage_type_id,
                "const_type_mstr_id" => $floorDetail->construction_type_id,
                "occupancy_type_mstr_id" => $floorDetail->occupancy_type_id,
                "builtup_area" => $floorDetail->builtup_area,
                "date_from" => $floorDetail->date_from,
                "date_upto" => $floorDetail->date_to,
                "carpet_area" => $floorDetail->carpet_area,
                "user_id" => $floorDetail->user_id,
                "saf_floor_id" => $floorDetail->saf_floor_id,
            ];
            $this->_mPropFloors->create($floorReq);
        }

        // Prop Owners replication
        foreach ($this->_ownerDetails as $ownerDetail) {
            $approvedOwners = $ownerDetail->replicate();
            $approvedOwners->setTable('prop_owners');
            $approvedOwners->property_id = $propProperties->id;
            $approvedOwners->save();
        }
    }

    /**
     * | Update Old Property Apply On Reassessment
     */
    public function updateOldHolding()
    {

        $propProperties = PropProperty::find($this->_activeSaf->previous_holding_id);
        if (!$propProperties) {
            throw new Exception("Old Property Not Found");
        }
        $oldFloor = PropFloor::where("property_id", $propProperties->id)->get();
        $oldOwners = PropOwner::where("property_id", $propProperties->id)->get();
        $oldDemand = PropDemand::where("property_id", $propProperties->id)->get();

        $currentYearDemand = collect($oldDemand)->where("status", 1);
        $currentYearDemandId = ($currentYearDemand->implode("id", ","));
        $currentYearDemandId = $currentYearDemandId ? (int)$currentYearDemandId  : 0;

        $oldTransection = $propProperties->getAllTransection()->get();
        $oldTranDtl = new Collection();
        $oldTransection->map(function ($val) use ($oldTranDtl, $currentYearDemandId) {
            $trn = $val->getAllTranDtls()->where("prop_tran_dtls.prop_demand_id", $currentYearDemandId)->first();
            if ($trn) {
                $oldTranDtl->push($trn);
            }
        });
        $this->_paidTotalCurrentYearTax = $oldTranDtl->sum("paid_total_tax");

        $history = new PropAssessmentHistory();
        $history->property_id = $propProperties->id;
        $history->assessment_type = $this->_activeSaf->assessment_type;
        $history->saf_id = $this->_activeSaf->id;
        $history->prop_log = json_encode($propProperties->toArray(), JSON_UNESCAPED_UNICODE);
        $history->owner_log = json_encode($oldOwners->toArray(), JSON_UNESCAPED_UNICODE);
        $history->floar_log = json_encode($oldFloor->toArray(), JSON_UNESCAPED_UNICODE);
        $history->demand_log = json_encode($oldDemand->toArray(), JSON_UNESCAPED_UNICODE);
        $history->transection_log = json_encode($oldTransection->toArray(), JSON_UNESCAPED_UNICODE);
        $history->current_year_paid_demand_log = json_encode($oldTranDtl->toArray(), JSON_UNESCAPED_UNICODE);

        $history->user_id = Auth()->user() ? Auth()->user()->id : 0;
        $history->save();
        $this->_assessmentHistoryId = $history->id;
        $propProperties->update($this->_toBeProperties->toArray());
        $propProperties->saf_id = $this->_activeSaf->id;
        // $propProperties->holding_no = $this->_activeSaf->holding_no;
        $propProperties->new_holding_no = $this->_activeSaf->holding_no;
        $propProperties->update();

        $this->_replicatedPropId = $propProperties->id;
        // ✅Replication of Verified Saf Details by Ulb TC
        $propProperties->prop_type_mstr_id = $this->_verifiedPropDetails[0]->prop_type_id;
        $propProperties->area_of_plot = $this->_verifiedPropDetails[0]->area_of_plot;
        $propProperties->ward_mstr_id = $this->_verifiedPropDetails[0]->ward_id;
        $propProperties->zone_mstr_id = $this->_verifiedPropDetails[0]->zone_mstr_id ? $this->_verifiedPropDetails[0]->zone_mstr_id : $propProperties->zone_mstr_id;
        $propProperties->is_mobile_tower = $this->_verifiedPropDetails[0]->has_mobile_tower;
        $propProperties->tower_area = $this->_verifiedPropDetails[0]->tower_area;
        $propProperties->tower_installation_date = $this->_verifiedPropDetails[0]->tower_installation_date;
        $propProperties->is_hoarding_board = $this->_verifiedPropDetails[0]->has_hoarding;
        $propProperties->hoarding_area = $this->_verifiedPropDetails[0]->hoarding_area;
        $propProperties->hoarding_installation_date = $this->_verifiedPropDetails[0]->hoarding_installation_date;
        $propProperties->is_petrol_pump = $this->_verifiedPropDetails[0]->is_petrol_pump;
        $propProperties->under_ground_area = $this->_verifiedPropDetails[0]->underground_area;
        $propProperties->petrol_pump_completion_date = $this->_verifiedPropDetails[0]->petrol_pump_completion_date;
        $propProperties->is_water_harvesting = $this->_verifiedPropDetails[0]->has_water_harvesting;
        $propProperties->update();
        foreach ($oldFloor as $f) {
            $f->update(["status" => 0]);
        }

        if ($this->_verifiedFloors) {
            foreach ($this->_verifiedFloors as $floorDetail) {
                $floorReq = [
                    "property_id" => $this->_replicatedPropId,
                    "saf_id" => $this->_safId,
                    "floor_mstr_id" => $floorDetail->floor_mstr_id,
                    "usage_type_mstr_id" => $floorDetail->usage_type_id,
                    "const_type_mstr_id" => $floorDetail->construction_type_id,
                    "occupancy_type_mstr_id" => $floorDetail->occupancy_type_id,
                    "builtup_area" => $floorDetail->builtup_area,
                    "date_from" => $floorDetail->date_from,
                    "date_upto" => $floorDetail->date_to,
                    "carpet_area" => $floorDetail->carpet_area,
                    "user_id" => $floorDetail->user_id,
                    "saf_floor_id" => $floorDetail->saf_floor_id
                ];
                $safFloor = PropActiveSafsFloor::find($floorDetail->saf_floor_id);
                $oldPFloorUpdate = PropFloor::find($safFloor ? $safFloor->prop_floor_details_id : 0);
                if ($oldPFloorUpdate) {
                    $floorReq["status"] = 1;
                    $oldPFloorUpdate->update($floorReq);
                } else {
                    $this->_mPropFloors->create($floorReq);
                }
            }
        }

        // Prop Owners replication
        foreach ($oldOwners as $w) {
            $w->update(["status" => 0]);
        }
        foreach ($this->_ownerDetails as $ownerDetail) {
            $approvedOwners = $ownerDetail->replicate();
            $oldPOwnersUpdate = PropOwner::find($ownerDetail->prop_owner_id ? $ownerDetail->prop_owner_id : 0);
            if ($oldPOwnersUpdate) {
                $oldPOwnersUpdate->update($approvedOwners->toArray());
                $approvedOwners = $oldPOwnersUpdate;
            } else {
                $approvedOwners->setTable('prop_owners');
            }
            $approvedOwners->property_id = $this->_replicatedPropId;
            $approvedOwners->save();
        }
    }

    /**
     * | Generation of FAM(04)
     */
    public function famGeneration()
    {
        // Tax Calculation
        $this->_calculateTaxByUlb = $this->_verifiedPropDetails[0]->id ? new CalculateTaxByUlb($this->_verifiedPropDetails[0]->id) : new CalculateSafTaxById($this->_activeSaf);
        $propIdGenerator = new PropIdGenerator;
        $calculatedTaxes = $this->_calculateTaxByUlb->_GRID;
        $firstDemand = $calculatedTaxes['fyearWiseTaxes']->first();
        // Fam No Generation
        $famFyear = $firstDemand['fyear'] ?? getFY();
        $famNo = $propIdGenerator->generateMemoNo("FAM", $this->_activeSaf->ward_mstr_id, $famFyear);
        $this->_famNo = $famNo;
        $memoReq = [
            "saf_id" => $this->_activeSaf->id,
            "from_fyear" => $famFyear,
            "alv" => $firstDemand['alv'] ?? ($calculatedTaxes[0]["floorsTaxes"]["alv"] ?? 0),
            "annual_tax" => $firstDemand['totalTax'] ?? ($calculatedTaxes["grandTaxes"]["totalTax"] ?? 0),
            "user_id" => auth()->user()->id,
            "memo_no" => $famNo,
            "memo_type" => "FAM",
            "holding_no" => $this->_activeSaf->holding_no,
            "prop_id" => $this->_replicatedPropId,
            "ward_mstr_id" => $this->_activeSaf->ward_mstr_id,
            "pt_no" => $this->_activeSaf->pt_no,
        ];

        $createdFam = PropSafMemoDtl::create($memoReq);
        $this->_famId = $createdFam->id;
    }

    /**
     * | Replication of Saf ()
     */
    public function replicateSaf()
    {
        $approvedSaf = $this->_activeSaf->replicate();
        $approvedSaf->setTable('prop_safs');
        $approvedSaf->id = $this->_activeSaf->id;
        $approvedSaf->saf_approved_date = Carbon::now()->format("Y-m-d");
        $approvedSaf->property_id = $this->_replicatedPropId;
        $approvedSaf->property_no = $this->_activeSaf->property_no;
        $approvedSaf->save();
        $this->_activeSaf->delete();

        // Saf Owners Replication
        foreach ($this->_ownerDetails as $ownerDetail) {
            $approvedOwner = $ownerDetail->replicate();
            $approvedOwner->setTable('prop_safs_owners');
            $approvedOwner->id = $ownerDetail->id;
            $approvedOwner->save();
            $ownerDetail->delete();
        }

        if ($this->_activeSaf->prop_type_mstr_id != 4) {               // Applicable Not for Vacant Land
            // Saf Floors Replication
            foreach ($this->_floorDetails as $floorDetail) {
                $approvedFloor = $floorDetail->replicate();
                $approvedFloor->setTable('prop_safs_floors');
                $approvedFloor->id = $floorDetail->id;
                $approvedFloor->save();
                $floorDetail->delete();
            }
        }
    }

    public function generatTaxAccUlTc()
    {
        if (in_array($this->_activeSaf->assessment_type, ['Bifurcation'])) {
            return;
            // list($fromFyear, $uptoFyear) = explode("-", getFY());
            // $privThreeYear = ($fromFyear - 2) . "-" . ($uptoFyear - 1);
            // $fyDemand = collect($this->_calculateTaxByUlb->_GRID['fyearWiseTaxes'])->where("fyear", ">=", $privThreeYear)->sortBy("fyear");
        }
        $fyDemand = collect($this->_calculateTaxByUlb->_GRID['fyearWiseTaxes'])->sortBy("fyear");
        // if (in_array($this->_activeSaf->assessment_type, ['Reassessment'])) {
        //     list($fromFyear, $uptoFyear) = explode("-", getFY());
        //     $privTwoYear = ($fromFyear - 1) . "-" . ($uptoFyear - 1);
        //     $fyDemand = collect($this->_calculateTaxByUlb->_GRID['fyearWiseTaxes'])->where("fyear", ">", $privTwoYear)->sortBy("fyear");
        // }


        $this->generateAdvance($fyDemand);
        $user = Auth()->user();
        $ulbId = $this->_activeSaf->ulb_id;
        $demand = new PropDemand();
        foreach ($fyDemand as $key => $val) {
            $arr = [
                "property_id"   => $this->_replicatedPropId,
                "alv"           => $val["alv"],
                "maintanance_amt" => $val["maintananceTax"] ?? 0,
                "aging_amt"     => $val["agingAmt"] ?? 0,
                "general_tax"   => $val["generalTax"] ?? 0,
                "road_tax"      => $val["roadTax"] ?? 0,
                "firefighting_tax" => $val["firefightingTax"] ?? 0,
                "education_tax" => $val["educationTax"] ?? 0,
                "water_tax"     => $val["waterTax"] ?? 0,
                "cleanliness_tax" => $val["cleanlinessTax"] ?? 0,
                "sewarage_tax"  => $val["sewerageTax"] ?? 0,
                "tree_tax"      => $val["treeTax"] ?? 0,
                "professional_tax" => $val["professionalTax"] ?? 0,
                "tax1"      => $val["tax1"] ?? 0,
                "tax2"      => $val["tax2"] ?? 0,
                "tax3"      => $val["tax3"] ?? 0,
                "sp_education_tax" => $val["stateEducationTax"] ?? 0,
                "water_benefit" => $val["waterBenefitTax"] ?? 0,
                "water_bill"    => $val["waterBillTax"] ?? 0,
                "sp_water_cess" => $val["spWaterCessTax"] ?? 0,
                "drain_cess"    => $val["drainCessTax"] ?? 0,
                "light_cess"    => $val["lightCessTax"] ?? 0,
                "major_building" => $val["majorBuildingTax"] ?? 0,
                "total_tax"     => $val["totalTax"],
                "open_ploat_tax" => $val["openPloatTax"] ?? 0,

                "is_arrear"     => $val["fyear"] < getFY() ? true : false,
                "fyear"         => $val["fyear"],
                "user_id"       => $user->id ?? null,
                "ulb_id"        => $ulbId ?? $user->ulb_id,

                "balance" => $val["totalTax"],
                "due_total_tax" => $val["totalTax"],
                "due_balance" => $val["totalTax"],
                "due_alv" => $val["alv"],
                "due_maintanance_amt" => $val["maintananceTax"] ?? 0,
                "due_aging_amt"     => $val["agingAmt"] ?? 0,
                "due_general_tax"   => $val["generalTax"] ?? 0,
                "due_road_tax"      => $val["roadTax"] ?? 0,
                "due_firefighting_tax" => $val["firefightingTax"] ?? 0,
                "due_education_tax" => $val["educationTax"] ?? 0,
                "due_water_tax"     => $val["waterTax"] ?? 0,
                "due_cleanliness_tax" => $val["cleanlinessTax"] ?? 0,
                "due_sewarage_tax"  => $val["sewerageTax"] ?? 0,
                "due_tree_tax"      => $val["treeTax"] ?? 0,
                "due_professional_tax" => $val["professionalTax"] ?? 0,
                "due_tax1"      => $val["tax1"] ?? 0,
                "due_tax2"      => $val["tax2"] ?? 0,
                "due_tax3"      => $val["tax3"] ?? 0,
                "due_sp_education_tax" => $val["stateEducationTax"] ?? 0,
                "due_water_benefit" => $val["waterBenefitTax"] ?? 0,
                "due_water_bill"    => $val["waterBillTax"] ?? 0,
                "due_sp_water_cess" => $val["spWaterCessTax"] ?? 0,
                "due_drain_cess"    => $val["drainCessTax"] ?? 0,
                "due_light_cess"    => $val["lightCessTax"] ?? 0,
                "due_major_building" => $val["majorBuildingTax"] ?? 0,
                "due_open_ploat_tax" => $val["openPloatTax"] ?? 0,
            ];
            if ($oldDemand = $demand->where("fyear", $arr["fyear"])->where("property_id", $arr["property_id"])->where("status", 1)->first()) {
                $arr["adjustAmount"] = $val["adjustAmount"];
                $arr["dueAmount"] = $val["dueAmount"];
                $oldDemand = $this->updateOldDemandsV1($oldDemand, $arr);
                $oldDemand->update();
                continue;
            }
            $demand->store($arr);
        }
    }

    public function generateAdvance($newDemand)
    {
        $oldPaidTax = $this->_paidTotalCurrentYearTax;
        if (round($oldPaidTax) > 0 && !in_array($this->_activeSaf->assessment_type, ['New Assessment'])) {
            $new_demand_log = json_encode($newDemand, JSON_UNESCAPED_UNICODE);
            $newAdvance = new PropAdvance();
            $advArr = [
                "prop_id" => $this->_replicatedPropId,
                "tran_id" => null,
                "amount" => round($oldPaidTax),
                "user_id" => (auth()->user() ? auth()->user()->id : null),
                "ulb_id" => (auth()->user() ? auth()->user()->ulb_id : null),
                "remarks" => "Old Demand payment",
            ];
            $newAdvance->where("prop_id", $advArr["prop_id"])->where("remarks", $advArr["remarks"])->update(["status" => 0]);
            $advanceId = $newAdvance->store($advArr);
            $history = new PropAssessmentHistory();
            $history->where("id", $this->_assessmentHistoryId)->update(["advance_id" => $advanceId, "total_paid_demand_amount" => $oldPaidTax, "new_demand_log" => $new_demand_log]);
        }
    }

    public function updateOldDemands($oldDemand, $newDemand)
    {
        $oldDemand->maintanance_amt = $oldDemand->maintanance_amt + $newDemand["maintanance_amt"];
        $oldDemand->aging_amt       = $oldDemand->aging_amt + $newDemand["aging_amt"];
        $oldDemand->general_tax     = $oldDemand->general_tax + $newDemand["general_tax"];
        $oldDemand->road_tax        = $oldDemand->road_tax + $newDemand["road_tax"];
        $oldDemand->firefighting_tax = $oldDemand->firefighting_tax + $newDemand["firefighting_tax"];
        $oldDemand->education_tax   = $oldDemand->education_tax + $newDemand["education_tax"];
        $oldDemand->water_tax       = $oldDemand->water_tax + $newDemand["water_tax"];
        $oldDemand->cleanliness_tax = $oldDemand->cleanliness_tax + $newDemand["cleanliness_tax"];
        $oldDemand->sewarage_tax    = $oldDemand->sewarage_tax + $newDemand["sewarage_tax"];
        $oldDemand->tree_tax        = $oldDemand->tree_tax + $newDemand["tree_tax"];
        $oldDemand->professional_tax = $oldDemand->professional_tax + $newDemand["professional_tax"];
        $oldDemand->total_tax       = $oldDemand->total_tax + $newDemand["total_tax"];
        $oldDemand->balance         = $oldDemand->balance + $newDemand["total_tax"];
        $oldDemand->tax1            = $oldDemand->tax1 + $newDemand["tax1"];
        $oldDemand->tax2            = $oldDemand->tax2 + $newDemand["tax2"];
        $oldDemand->tax3            = $oldDemand->tax3 + $newDemand["tax3"];
        $oldDemand->sp_education_tax = $oldDemand->sp_education_tax + $newDemand["sp_education_tax"];
        $oldDemand->water_benefit   = $oldDemand->water_benefit + $newDemand["water_benefit"];
        $oldDemand->water_bill      = $oldDemand->water_bill + $newDemand["water_bill"];
        $oldDemand->sp_water_cess   = $oldDemand->sp_water_cess + $newDemand["sp_water_cess"];
        $oldDemand->drain_cess      = $oldDemand->drain_cess + $newDemand["drain_cess"];
        $oldDemand->light_cess      = $oldDemand->light_cess + $newDemand["light_cess"];
        $oldDemand->major_building  = $oldDemand->major_building + $newDemand["major_building"];
        $oldDemand->due_maintanance_amt  = $oldDemand->due_maintanance_amt + $newDemand["due_maintanance_amt"];
        $oldDemand->due_aging_amt  = $oldDemand->due_aging_amt + $newDemand["due_aging_amt"];
        $oldDemand->due_general_tax  = $oldDemand->due_general_tax + $newDemand["due_general_tax"];
        $oldDemand->due_road_tax  = $oldDemand->due_road_tax + $newDemand["due_road_tax"];
        $oldDemand->due_firefighting_tax  = $oldDemand->due_firefighting_tax + $newDemand["due_firefighting_tax"];
        $oldDemand->due_education_tax  = $oldDemand->due_education_tax + $newDemand["due_education_tax"];
        $oldDemand->due_water_tax  = $oldDemand->due_water_tax + $newDemand["due_water_tax"];
        $oldDemand->due_cleanliness_tax  = $oldDemand->due_cleanliness_tax + $newDemand["due_cleanliness_tax"];
        $oldDemand->due_sewarage_tax  = $oldDemand->due_sewarage_tax + $newDemand["due_sewarage_tax"];
        $oldDemand->due_tree_tax  = $oldDemand->due_tree_tax + $newDemand["due_tree_tax"];
        $oldDemand->due_professional_tax  = $oldDemand->due_professional_tax + $newDemand["due_professional_tax"];
        $oldDemand->due_total_tax  = $oldDemand->due_total_tax + $newDemand["due_total_tax"];
        $oldDemand->due_balance  = $oldDemand->due_balance + $newDemand["due_balance"];
        $oldDemand->due_tax1  = $oldDemand->due_tax1 + $newDemand["due_tax1"];
        $oldDemand->due_tax2  = $oldDemand->due_tax2 + $newDemand["due_tax2"];
        $oldDemand->due_tax3  = $oldDemand->due_tax3 + $newDemand["due_tax3"];
        $oldDemand->due_sp_education_tax  = $oldDemand->due_sp_education_tax + $newDemand["due_sp_education_tax"];
        $oldDemand->due_water_benefit  = $oldDemand->due_water_benefit + $newDemand["due_water_benefit"];
        $oldDemand->due_water_bill  = $oldDemand->due_water_bill + $newDemand["due_water_bill"];
        $oldDemand->due_sp_water_cess  = $oldDemand->due_sp_water_cess + $newDemand["due_sp_water_cess"];
        $oldDemand->due_drain_cess  = $oldDemand->due_drain_cess + $newDemand["due_drain_cess"];
        $oldDemand->due_light_cess  = $oldDemand->due_light_cess + $newDemand["due_light_cess"];
        $oldDemand->due_major_building  = $oldDemand->due_major_building + $newDemand["due_major_building"];
        $oldDemand->open_ploat_tax  = $oldDemand->open_ploat_tax + $newDemand["open_ploat_tax"];
        $oldDemand->due_open_ploat_tax  = $oldDemand->due_open_ploat_tax + $newDemand["due_open_ploat_tax"];
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 1) {
            $oldDemand->is_full_paid = false;
        }
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 0) {
            $oldDemand->is_full_paid = true;
        }
        return $oldDemand;
    }

    public function updateOldDemandsV1($oldDemand, $newDemand)
    {
        $oldDemand->maintanance_amt =  $newDemand["maintanance_amt"];
        $oldDemand->aging_amt       = $newDemand["aging_amt"];
        $oldDemand->general_tax     = $newDemand["general_tax"];
        $oldDemand->road_tax        = $newDemand["road_tax"];
        $oldDemand->firefighting_tax = $newDemand["firefighting_tax"];
        $oldDemand->education_tax   = $newDemand["education_tax"];
        $oldDemand->water_tax       = $newDemand["water_tax"];
        $oldDemand->cleanliness_tax = $newDemand["cleanliness_tax"];
        $oldDemand->sewarage_tax    = $newDemand["sewarage_tax"];
        $oldDemand->tree_tax        = $newDemand["tree_tax"];
        $oldDemand->professional_tax = $newDemand["professional_tax"];
        $oldDemand->total_tax       = $newDemand["total_tax"];
        $oldDemand->balance         = $newDemand["total_tax"];
        $oldDemand->tax1            = $newDemand["tax1"];
        $oldDemand->tax2            = $newDemand["tax2"];
        $oldDemand->tax3            = $newDemand["tax3"];
        $oldDemand->sp_education_tax = $newDemand["sp_education_tax"];
        $oldDemand->water_benefit   = $newDemand["water_benefit"];
        $oldDemand->water_bill      = $newDemand["water_bill"];
        $oldDemand->sp_water_cess   = $newDemand["sp_water_cess"];
        $oldDemand->drain_cess      = $newDemand["drain_cess"];
        $oldDemand->light_cess      = $newDemand["light_cess"];
        $oldDemand->major_building  = $newDemand["major_building"];
        $oldDemand->due_maintanance_amt  = $newDemand["due_maintanance_amt"];
        $oldDemand->due_aging_amt  = $newDemand["due_aging_amt"];
        $oldDemand->due_general_tax  = $newDemand["due_general_tax"];
        $oldDemand->due_road_tax  = $newDemand["due_road_tax"];
        $oldDemand->due_firefighting_tax  = $newDemand["due_firefighting_tax"];
        $oldDemand->due_education_tax  = $newDemand["due_education_tax"];
        $oldDemand->due_water_tax  = $newDemand["due_water_tax"];
        $oldDemand->due_cleanliness_tax  = $newDemand["due_cleanliness_tax"];
        $oldDemand->due_sewarage_tax  = $newDemand["due_sewarage_tax"];
        $oldDemand->due_tree_tax  = $newDemand["due_tree_tax"];
        $oldDemand->due_professional_tax  = $newDemand["due_professional_tax"];
        $oldDemand->due_total_tax  = $newDemand["due_total_tax"];
        $oldDemand->due_balance  = $newDemand["due_balance"];
        $oldDemand->due_tax1  = $newDemand["due_tax1"];
        $oldDemand->due_tax2  = $newDemand["due_tax2"];
        $oldDemand->due_tax3  = $newDemand["due_tax3"];
        $oldDemand->due_sp_education_tax  = $newDemand["due_sp_education_tax"];
        $oldDemand->due_water_benefit  = $newDemand["due_water_benefit"];
        $oldDemand->due_water_bill  = $newDemand["due_water_bill"];
        $oldDemand->due_sp_water_cess  = $newDemand["due_sp_water_cess"];
        $oldDemand->due_drain_cess  = $newDemand["due_drain_cess"];
        $oldDemand->due_light_cess  = $newDemand["due_light_cess"];
        $oldDemand->due_major_building  = $newDemand["due_major_building"];
        $oldDemand->open_ploat_tax  = $newDemand["open_ploat_tax"];
        $oldDemand->due_open_ploat_tax  = $newDemand["due_open_ploat_tax"];
        $oldDemand->paid_total_tax  = $newDemand["paid_total_tax"] ?? 0;
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 1) {
            $oldDemand->is_full_paid = false;
        }
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 0) {
            $oldDemand->is_full_paid = true;
        }
        return $oldDemand;
    }

    public function updateOldDemandsV2($oldDemand, $newDemand)
    {
        $adjustDemand = $this->demandAdjust($newDemand);
        $newDemand = array_merge($newDemand, $adjustDemand);

        $oldDemand->maintanance_amt =  $newDemand["maintanance_amt"];
        $oldDemand->aging_amt       = $newDemand["aging_amt"];
        $oldDemand->general_tax     = $newDemand["general_tax"];
        $oldDemand->road_tax        = $newDemand["road_tax"];
        $oldDemand->firefighting_tax = $newDemand["firefighting_tax"];
        $oldDemand->education_tax   = $newDemand["education_tax"];
        $oldDemand->water_tax       = $newDemand["water_tax"];
        $oldDemand->cleanliness_tax = $newDemand["cleanliness_tax"];
        $oldDemand->sewarage_tax    = $newDemand["sewarage_tax"];
        $oldDemand->tree_tax        = $newDemand["tree_tax"];
        $oldDemand->professional_tax = $newDemand["professional_tax"];
        $oldDemand->total_tax       = $newDemand["total_tax"];
        $oldDemand->balance         = $newDemand["total_tax"];
        $oldDemand->tax1            = $newDemand["tax1"];
        $oldDemand->tax2            = $newDemand["tax2"];
        $oldDemand->tax3            = $newDemand["tax3"];
        $oldDemand->sp_education_tax = $newDemand["sp_education_tax"];
        $oldDemand->water_benefit   = $newDemand["water_benefit"];
        $oldDemand->water_bill      = $newDemand["water_bill"];
        $oldDemand->sp_water_cess   = $newDemand["sp_water_cess"];
        $oldDemand->drain_cess      = $newDemand["drain_cess"];
        $oldDemand->light_cess      = $newDemand["light_cess"];
        $oldDemand->major_building  = $newDemand["major_building"];
        $oldDemand->due_maintanance_amt  = $newDemand["due_maintanance_amt"];
        $oldDemand->due_aging_amt  = $newDemand["due_aging_amt"];
        $oldDemand->due_general_tax  = $newDemand["due_general_tax"];
        $oldDemand->due_road_tax  = $newDemand["due_road_tax"];
        $oldDemand->due_firefighting_tax  = $newDemand["due_firefighting_tax"];
        $oldDemand->due_education_tax  = $newDemand["due_education_tax"];
        $oldDemand->due_water_tax  = $newDemand["due_water_tax"];
        $oldDemand->due_cleanliness_tax  = $newDemand["due_cleanliness_tax"];
        $oldDemand->due_sewarage_tax  = $newDemand["due_sewarage_tax"];
        $oldDemand->due_tree_tax  = $newDemand["due_tree_tax"];
        $oldDemand->due_professional_tax  = $newDemand["due_professional_tax"];
        $oldDemand->due_total_tax  = $newDemand["due_total_tax"];
        $oldDemand->due_balance  = $newDemand["due_balance"];
        $oldDemand->due_tax1  = $newDemand["due_tax1"];
        $oldDemand->due_tax2  = $newDemand["due_tax2"];
        $oldDemand->due_tax3  = $newDemand["due_tax3"];
        $oldDemand->due_sp_education_tax  = $newDemand["due_sp_education_tax"];
        $oldDemand->due_water_benefit  = $newDemand["due_water_benefit"];
        $oldDemand->due_water_bill  = $newDemand["due_water_bill"];
        $oldDemand->due_sp_water_cess  = $newDemand["due_sp_water_cess"];
        $oldDemand->due_drain_cess  = $newDemand["due_drain_cess"];
        $oldDemand->due_light_cess  = $newDemand["due_light_cess"];
        $oldDemand->due_major_building  = $newDemand["due_major_building"];
        $oldDemand->open_ploat_tax  = $newDemand["open_ploat_tax"];
        $oldDemand->due_open_ploat_tax  = $newDemand["due_open_ploat_tax"];
        $oldDemand->paid_total_tax  = 0;
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 1) {
            $oldDemand->is_full_paid = false;
        }
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 0) {
            $oldDemand->is_full_paid = true;
        }
        return $oldDemand;
    }

    public function demandAdjust($arr)
    {
        $currentTax = collect([$arr]);

        $totaTax = $currentTax->sum("total_tax");
        $defmandDueAmount = $currentTax->sum("dueAmount");
        $defmandDueAmount = $defmandDueAmount > 0 ? $defmandDueAmount : 0;

        $generalTaxPerc = ($currentTax->sum('general_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $roadTaxPerc = ($currentTax->sum('road_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $firefightingTaxPerc = ($currentTax->sum('firefighting_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $educationTaxPerc = ($currentTax->sum('education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterTaxPerc = ($currentTax->sum('water_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $cleanlinessTaxPerc = ($currentTax->sum('cleanliness_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $sewarageTaxPerc = ($currentTax->sum('sewarage_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $treeTaxPerc = ($currentTax->sum('tree_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $professionalTaxPerc = ($currentTax->sum('professional_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax1Perc = ($currentTax->sum('tax1') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax2Perc = ($currentTax->sum('tax2') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax3Perc = ($currentTax->sum('tax3') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $stateEducationTaxPerc = ($currentTax->sum('sp_education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBenefitPerc = ($currentTax->sum('water_benefit') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBillPerc = ($currentTax->sum('water_bill') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $spWaterCessPerc = ($currentTax->sum('sp_water_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $drainCessPerc = ($currentTax->sum('drain_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $lightCessPerc = ($currentTax->sum('light_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $majorBuildingPerc = ($currentTax->sum('major_building') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $openPloatTaxPerc = ($currentTax->sum('open_ploat_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;

        $totalPerc = $generalTaxPerc + $roadTaxPerc + $firefightingTaxPerc + $educationTaxPerc +
            $waterTaxPerc + $cleanlinessTaxPerc + $sewarageTaxPerc + $treeTaxPerc
            + $professionalTaxPerc + $tax1Perc + $tax2Perc + $tax3Perc
            + $stateEducationTaxPerc + $waterBenefitPerc + $waterBillPerc +
            $spWaterCessPerc + $drainCessPerc + $lightCessPerc + $majorBuildingPerc
            + $openPloatTaxPerc;



        /**
         * | 100 % = Payable Amount
         * | 1 % = Payable Amount/100  (Hence We Have devided the taxes by hundred)
         */

        $dues = [
            'due_general_tax' => roundFigure(($defmandDueAmount * $generalTaxPerc) / 100),
            'due_road_tax' => roundFigure(($defmandDueAmount * $roadTaxPerc) / 100),
            'due_firefighting_tax' => roundFigure(($defmandDueAmount * $firefightingTaxPerc) / 100),
            'due_education_tax' => roundFigure(($defmandDueAmount * $educationTaxPerc) / 100),
            'due_water_tax' => roundFigure(($defmandDueAmount * $waterTaxPerc) / 100),
            'due_cleanliness_tax' => roundFigure(($defmandDueAmount * $cleanlinessTaxPerc) / 100),
            'due_sewarage_tax' => roundFigure(($defmandDueAmount * $sewarageTaxPerc) / 100),
            'due_tree_tax' => roundFigure(($defmandDueAmount * $treeTaxPerc) / 100),
            'due_professional_tax' => roundFigure(($defmandDueAmount * $professionalTaxPerc) / 100),
            'due_tax1' => roundFigure(($defmandDueAmount * $tax1Perc) / 100),
            'due_tax2' => roundFigure(($defmandDueAmount * $tax2Perc) / 100),
            'due_tax3' => roundFigure(($defmandDueAmount * $tax3Perc) / 100),
            'due_sp_education_tax' => roundFigure(($defmandDueAmount * $stateEducationTaxPerc) / 100),
            'due_water_benefit' => roundFigure(($defmandDueAmount * $waterBenefitPerc) / 100),
            'due_water_bill' => roundFigure(($defmandDueAmount * $waterBillPerc) / 100),
            'due_sp_water_cess' => roundFigure(($defmandDueAmount * $spWaterCessPerc) / 100),
            'due_drain_cess' => roundFigure(($defmandDueAmount * $drainCessPerc) / 100),
            'due_light_cess' => roundFigure(($defmandDueAmount * $lightCessPerc) / 100),
            'due_major_building' => roundFigure(($defmandDueAmount * $majorBuildingPerc) / 100),
            'due_open_ploat_tax' => roundFigure(($defmandDueAmount * $openPloatTaxPerc) / 100),
            'due_total_tax' => roundFigure(($defmandDueAmount * $totalPerc) / 100),
            'due_balance' => roundFigure(($defmandDueAmount * $totalPerc) / 100),
            'balance' => roundFigure(($defmandDueAmount * $totalPerc) / 100),
        ];
        return $dues;
    }

    public function transerMutationDemands()
    {
        if (in_array($this->_activeSaf->assessment_type, ['Mutation'])) #update Old Property According to New Data
        {
            $propProperties = PropProperty::find($this->_activeSaf->previous_holding_id);
            if (!$propProperties) {
                throw new Exception("Old Property Not Found");
            }
            $propProperties->update(["status" => 3]);
            $newPropProperties = PropProperty::find($this->_replicatedPropId);
            $newPropProperties->update(["status" => 0]);
            $dueDemands = PropDemand::where("property_id", $propProperties->id)
                ->where("status", 1)
                ->where("due_total_tax", ">", 0)
                ->OrderBy("fyear", "ASC")
                ->get();
            foreach ($dueDemands as $val) {
                $lagaciDimand = new PropDemand();
                $this->MutationDemands($val, $lagaciDimand);
                $lagaciDimand->property_id = $this->_replicatedPropId;
                $lagaciDimand->save();
            }
        }
    }

    public function MutationDemands(PropDemand $demand, PropDemand $newDemand)
    {
        // $newDemand->
        $newDemand->alv             = $demand->due_alv;
        $newDemand->maintanance_amt = $demand->due_maintanance_amt;
        $newDemand->aging_amt       = $demand->due_aging_amt;
        $newDemand->general_tax     = $demand->due_general_tax;
        $newDemand->road_tax        = $demand->due_road_tax;
        $newDemand->firefighting_tax = $demand->due_firefighting_tax;
        $newDemand->education_tax   = $demand->due_education_tax;
        $newDemand->water_tax       = $demand->due_water_tax;
        $newDemand->cleanliness_tax = $demand->due_cleanliness_tax;
        $newDemand->sewarage_tax    = $demand->due_sewarage_tax;
        $newDemand->tree_tax        = $demand->due_tree_tax;
        $newDemand->professional_tax = $demand->due_professional_tax;
        $newDemand->total_tax       = $demand->due_total_tax;
        $newDemand->balance         = $demand->due_balance;
        $newDemand->fyear           = $demand->fyear;
        $newDemand->adjust_type     = $demand->adjust_type;
        $newDemand->adjust_amt      = $demand->adjust_amt;
        $newDemand->user_id         = Auth()->user()->id ?? $demand->user_id;
        $newDemand->ulb_id          = $demand->ulb_id;
        $newDemand->tax1            = $demand->due_tax1;
        $newDemand->tax2            = $demand->due_tax2;
        $newDemand->tax3            = $demand->due_tax3;
        $newDemand->sp_education_tax = $demand->due_sp_education_tax;
        $newDemand->water_benefit   = $demand->due_water_benefit;
        $newDemand->water_bill      = $demand->due_water_bill;
        $newDemand->sp_water_cess   = $demand->due_sp_water_cess;
        $newDemand->drain_cess      = $demand->due_drain_cess;
        $newDemand->light_cess      = $demand->due_light_cess;
        $newDemand->major_building  = $demand->due_major_building;

        $newDemand->due_alv             = $demand->due_alv;
        $newDemand->due_maintanance_amt = $demand->due_maintanance_amt;
        $newDemand->due_aging_amt       = $demand->due_aging_amt;
        $newDemand->due_general_tax     = $demand->due_general_tax;
        $newDemand->due_road_tax        = $demand->due_road_tax;
        $newDemand->due_firefighting_tax = $demand->due_firefighting_tax;
        $newDemand->due_education_tax   = $demand->due_education_tax;
        $newDemand->due_water_tax       = $demand->due_water_tax;
        $newDemand->due_cleanliness_tax = $demand->due_cleanliness_tax;
        $newDemand->due_sewarage_tax    = $demand->due_sewarage_tax;
        $newDemand->due_tree_tax        = $demand->due_tree_tax;
        $newDemand->due_professional_tax = $demand->due_professional_tax;
        $newDemand->due_total_tax       = $demand->due_total_tax;
        $newDemand->due_balance         = $demand->due_balance;
        $newDemand->due_adjust_amt      = $demand->due_adjust_amt;
        $newDemand->due_tax1            = $demand->due_tax1;
        $newDemand->due_tax2            = $demand->due_tax2;
        $newDemand->due_tax3            = $demand->due_tax3;
        $newDemand->due_sp_education_tax = $demand->due_sp_education_tax;
        $newDemand->due_water_benefit   = $demand->due_water_benefit;
        $newDemand->due_water_bill      = $demand->due_water_bill;
        $newDemand->due_sp_water_cess   = $demand->due_sp_water_cess;
        $newDemand->due_drain_cess      = $demand->due_drain_cess;
        $newDemand->due_light_cess      = $demand->due_light_cess;
        $newDemand->due_major_building  = $demand->due_major_building;
        $newDemand->open_ploat_tax      = $demand->open_ploat_tax;
        $newDemand->due_open_ploat_tax  = $demand->due_open_ploat_tax;
    }

    // public function transferPropertyBifucation()
    // {
    //     if (in_array($this->_activeSaf->assessment_type, ['Bifurcation'])) {
    //         $propProperties = PropProperty::find($this->_activeSaf->previous_holding_id);
    //         if (!$propProperties) {
    //             throw new Exception("Old Property Not Found");
    //         }
    //         $newPropProperties = PropProperty::find($this->_replicatedPropId);
    //         $newPropProperties->update(["status" => 0]);
    //         $this->transferPropertyBifurcationDemand();

    //         #_Save in Assessment History Table
    //         $oldFloor = PropFloor::where("property_id", $propProperties->id)->get();
    //         $oldOwners = PropOwner::where("property_id", $propProperties->id)->get();
    //         $oldDemand = PropDemand::where("property_id", $propProperties->id)->get();
    //         $history = new PropAssessmentHistory();
    //         $history->property_id = $propProperties->id;
    //         $history->assessment_type = $this->_activeSaf->assessment_type;
    //         $history->saf_id = $this->_activeSaf->id;
    //         $history->prop_log = json_encode($propProperties->toArray(), JSON_UNESCAPED_UNICODE);
    //         $history->owner_log = json_encode($oldOwners->toArray(), JSON_UNESCAPED_UNICODE);
    //         $history->floar_log = json_encode($oldFloor->toArray(), JSON_UNESCAPED_UNICODE);
    //         $history->demand_log = json_encode($oldDemand->toArray(), JSON_UNESCAPED_UNICODE);

    //         $history->user_id = Auth()->user() ? Auth()->user()->id : 0;
    //         $history->save();

    //         $propProperties->update(["area_of_plot" => $propProperties->area_of_plot - $this->_verifiedPropDetails[0]->area_of_plot]);

    //         if ($this->_activeSaf->prop_type_mstr_id != 4) {              // Applicable Not for Vacant Land
    //             $mPropFloors = new PropFloor();

    //             $propFloors = $mPropFloors
    //                 ->where("property_id", $propProperties->id)
    //                 ->where('status', 1)
    //                 ->orderby('id')
    //                 ->get();

    //             foreach ($this->_verifiedFloors as $floorDetail) {
    //                 $activeSafFloorDtl = $this->_floorDetails->where('id', $floorDetail->saf_floor_id);
    //                 $activeSafFloorDtl = collect($activeSafFloorDtl)->first();

    //                 // $propFloor =  collect($propFloors)->where('id', $activeSafFloorDtl->prop_floor_details_id);
    //                 $propFloor =  $activeSafFloorDtl ? collect($propFloors)->where('id', $activeSafFloorDtl->prop_floor_details_id) : [];
    //                 $propFloor =  collect($propFloor)->first();
    //                 if ($propFloor) {
    //                     $propFloor->builtup_area = $propFloor->builtup_area - $floorDetail->builtup_area;
    //                     $propFloor->carpet_area = $propFloor->builtup_area;
    //                     if ($propFloor->builtup_area == 0)
    //                         $propFloor->status = 0;
    //                     $propFloor->save();
    //                 }
    //             }
    //             $isNewFloorExist = collect($this->_floorDetails)->whereNull('prop_floor_details_id');
    //             if ($isNewFloorExist->isNotEmpty())
    //                 $this->generateBiNewFloorDemand();

    //             $newPropFloors = $mPropFloors->getFloorsByPropId($propProperties->id);
    //             if (collect($newPropFloors)->isEmpty())
    //                 $propProperties->update(["prop_type_mstr_id" => 4]);
    //         }
    //     }
    // }

    public function transferPropertyBifucation()
    {
        $user = Auth()->user();
        $userId = $user ? $user->id : 0;
        $ulbId = $this->_activeSaf->ulb_id;
        if (in_array($this->_activeSaf->assessment_type, ['Bifurcation'])) {
            $propProperties = PropProperty::find($this->_activeSaf->previous_holding_id);
            if (!$propProperties) {
                throw new Exception("Old Property Not Found");
            }
            $newPropProperties = PropProperty::find($this->_replicatedPropId);
            $newPropProperties->update(["status" => 0]);
            $this->transferPropertyBifurcationDemand();

            #_Save in Assessment History Table
            $oldFloor = PropFloor::where("property_id", $propProperties->id)->get();
            $oldOwners = PropOwner::where("property_id", $propProperties->id)->get();
            $oldDemand = PropDemand::where("property_id", $propProperties->id)->get();


            $currentYearDemand = collect($oldDemand)->where("status", 1);
            $currentYearDemandId = ($currentYearDemand->implode("id", ","));
            $currentYearDemandId = $currentYearDemandId ? (int)$currentYearDemandId  : 0;

            $oldTransection = $propProperties->getAllTransection()->get();
            $oldTranDtl = new Collection();
            $oldTransection->map(function ($val) use ($oldTranDtl, $currentYearDemandId) {
                $trn = $val->getAllTranDtls()->where("prop_tran_dtls.prop_demand_id", $currentYearDemandId)->first();
                if ($trn) {
                    $oldTranDtl->push($trn);
                }
            });
            $this->_paidTotalCurrentYearTax = $oldTranDtl->sum("paid_total_tax");
            $history = new PropAssessmentHistory();
            $history->property_id = $propProperties->id;
            $history->assessment_type = $this->_activeSaf->assessment_type;
            $history->saf_id = $this->_activeSaf->id;
            $history->prop_log = json_encode($propProperties->toArray(), JSON_UNESCAPED_UNICODE);
            $history->owner_log = json_encode($oldOwners->toArray(), JSON_UNESCAPED_UNICODE);
            $history->floar_log = json_encode($oldFloor->toArray(), JSON_UNESCAPED_UNICODE);
            $history->demand_log = json_encode($oldDemand->toArray(), JSON_UNESCAPED_UNICODE);
            $history->transection_log = json_encode($oldTransection->toArray(), JSON_UNESCAPED_UNICODE);
            $history->current_year_paid_demand_log = json_encode($oldTranDtl->toArray(), JSON_UNESCAPED_UNICODE);

            $history->user_id = $userId;
            $history->save();

            $propProperties->update(["area_of_plot" => $propProperties->area_of_plot - $this->_verifiedPropDetails[0]->area_of_plot]);

            if ($this->_activeSaf->prop_type_mstr_id != 4) {              // Applicable Not for Vacant Land
                $mPropFloors = new PropFloor();

                $propFloors = $mPropFloors
                    ->where("property_id", $propProperties->id)
                    ->where('status', 1)
                    ->orderby('id')
                    ->get();

                foreach ($this->_verifiedFloors as $floorDetail) {
                    $activeSafFloorDtl = $this->_floorDetails->where('id', $floorDetail->saf_floor_id);
                    $activeSafFloorDtl = collect($activeSafFloorDtl)->first();

                    // $propFloor =  collect($propFloors)->where('id', $activeSafFloorDtl->prop_floor_details_id);
                    $propFloor =  $activeSafFloorDtl ? collect($propFloors)->where('id', $activeSafFloorDtl->prop_floor_details_id) : [];
                    $propFloor =  collect($propFloor)->first();
                    if ($propFloor) {
                        $propFloor->builtup_area = $propFloor->builtup_area - $floorDetail->builtup_area;
                        $propFloor->carpet_area = $propFloor->builtup_area;
                        if ($propFloor->builtup_area == 0)
                            $propFloor->status = 0;
                        $propFloor->save();
                    }
                }
                $isNewFloorExist = collect($this->_floorDetails)->whereNull('prop_floor_details_id');
                if ($isNewFloorExist->isNotEmpty())
                    $this->generateBiNewFloorDemand();

                $newPropFloors = $mPropFloors->getFloorsByPropId($propProperties->id);
                if (collect($newPropFloors)->isEmpty())
                    $propProperties->update(["prop_type_mstr_id" => 4]);
            }
            $oldPropDemand = $this->generateAfterBifurcationPropertyRequest();
            $oldPropDemand = $oldPropDemand->where("fyear", getFY())->values();
            $newTotalTax = collect($oldPropDemand)->sum("totalTax");
            $paidAmount = $this->_paidTotalCurrentYearTax;
            if ($newTotalTax < $this->_paidTotalCurrentYearTax) {
                $this->_paidTotalCurrentYearTax = $this->_paidTotalCurrentYearTax - $newTotalTax;
                $paidAmount = $newTotalTax;
                $this->generateAdvance($oldPropDemand);
            }
            foreach ($oldPropDemand  as $newDemand) {
                $demand = new PropDemand();
                $arr = [
                    "property_id"   => $this->_activeSaf->previous_holding_id,
                    "alv"           => $newDemand["alv"],
                    "maintanance_amt" => $newDemand["maintananceTax"] ?? 0,
                    "aging_amt"     => $newDemand["agingAmt"] ?? 0,
                    "general_tax"   => $newDemand["generalTax"] ?? 0,
                    "road_tax"      => $newDemand["roadTax"] ?? 0,
                    "firefighting_tax" => $newDemand["firefightingTax"] ?? 0,
                    "education_tax" => $newDemand["educationTax"] ?? 0,
                    "water_tax"     => $newDemand["waterTax"] ?? 0,
                    "cleanliness_tax" => $newDemand["cleanlinessTax"] ?? 0,
                    "sewarage_tax"  => $newDemand["sewerageTax"] ?? 0,
                    "tree_tax"      => $newDemand["treeTax"] ?? 0,
                    "professional_tax" => $newDemand["professionalTax"] ?? 0,
                    "tax1"      => $newDemand["tax1"] ?? 0,
                    "tax2"      => $newDemand["tax2"] ?? 0,
                    "tax3"      => $newDemand["tax3"] ?? 0,
                    "sp_education_tax" => $newDemand["stateEducationTax"] ?? 0,
                    "water_benefit" => $newDemand["waterBenefitTax"] ?? 0,
                    "water_bill"    => $newDemand["waterBillTax"] ?? 0,
                    "sp_water_cess" => $newDemand["spWaterCessTax"] ?? 0,
                    "drain_cess"    => $newDemand["drainCessTax"] ?? 0,
                    "light_cess"    => $newDemand["lightCessTax"] ?? 0,
                    "major_building" => $newDemand["majorBuildingTax"] ?? 0,
                    "total_tax"     => $newDemand["totalTax"],
                    "open_ploat_tax" => $newDemand["openPloatTax"] ?? 0,

                    "is_arrear"     => $newDemand["fyear"] < getFY() ? true : false,
                    "fyear"         => $newDemand["fyear"],
                    "user_id"       => $userId ?? null,
                    "ulb_id"        => $ulbId ?? $user->ulb_id,

                    "balance" => $newDemand["totalTax"],
                    "due_total_tax" => $newDemand["totalTax"],
                    "due_balance" => $newDemand["totalTax"],
                    "due_alv" => $newDemand["alv"],
                    "due_maintanance_amt" => $newDemand["maintananceTax"] ?? 0,
                    "due_aging_amt"     => $newDemand["agingAmt"] ?? 0,
                    "due_general_tax"   => $newDemand["generalTax"] ?? 0,
                    "due_road_tax"      => $newDemand["roadTax"] ?? 0,
                    "due_firefighting_tax" => $newDemand["firefightingTax"] ?? 0,
                    "due_education_tax" => $newDemand["educationTax"] ?? 0,
                    "due_water_tax"     => $newDemand["waterTax"] ?? 0,
                    "due_cleanliness_tax" => $newDemand["cleanlinessTax"] ?? 0,
                    "due_sewarage_tax"  => $newDemand["sewerageTax"] ?? 0,
                    "due_tree_tax"      => $newDemand["treeTax"] ?? 0,
                    "due_professional_tax" => $newDemand["professionalTax"] ?? 0,
                    "due_tax1"      => $newDemand["tax1"] ?? 0,
                    "due_tax2"      => $newDemand["tax2"] ?? 0,
                    "due_tax3"      => $newDemand["tax3"] ?? 0,
                    "due_sp_education_tax" => $newDemand["stateEducationTax"] ?? 0,
                    "due_water_benefit" => $newDemand["waterBenefitTax"] ?? 0,
                    "due_water_bill"    => $newDemand["waterBillTax"] ?? 0,
                    "due_sp_water_cess" => $newDemand["spWaterCessTax"] ?? 0,
                    "due_drain_cess"    => $newDemand["drainCessTax"] ?? 0,
                    "due_light_cess"    => $newDemand["lightCessTax"] ?? 0,
                    "due_major_building" => $newDemand["majorBuildingTax"] ?? 0,
                    "due_open_ploat_tax" => $newDemand["openPloatTax"] ?? 0,
                ];
                if ($oldDemand = $demand->where("fyear", $arr["fyear"])->where("property_id", $arr["property_id"])->where("status", 1)->first()) {
                    $arr = $this->adjustPaidAmount($paidAmount, $arr);
                    $oldDemand = $this->updateOldDemandsV1($oldDemand, $arr);
                    $oldDemand->update();
                } else {
                    $demand->store($arr);
                }
            }
        }
    }

    // public function transferPropertyBifurcationDemand()
    // {
    //     if (in_array($this->_activeSaf->assessment_type, ['Bifurcation'])) {
    //         $test = PropProperty::find($this->_replicatedPropId);
    //         $user = Auth()->user();
    //         $ulbId = $this->_activeSaf->ulb_id;
    //         $demand = new PropDemand();
    //         $mPropPendingArrear = new PropPendingArrear();
    //         $propProperties = PropProperty::find($this->_activeSaf->previous_holding_id);
    //         $oldIntrest = $mPropPendingArrear->getInterestByPropId($propProperties->id);
    //         $previousInterest = $oldIntrest->due_total_interest ?? 0;
    //         //$arr=[];
    //         $totalArea = $propProperties->area_of_plot;
    //         $bifurcatedArea = $this->_verifiedPropDetails[0]->area_of_plot;
    //         if($this->_activeSaf->prop_type_mstr_id!=4){
    //             $propFloor = PropFloor::where("property_id", $propProperties->id)
    //                 ->where('status', 1)
    //                 ->orderby('id')
    //                 ->get();
    //             $totalArea = collect($propFloor)->sum("builtup_area");
    //             $bifurcatedArea = collect($this->_floorDetails)->sum("builtup_area");
    //            // $bifurcatedArea = collect($this->_verifiedFloors)->isNotEmpty() ? collect($this->_verifiedFloors)->sum("builtup_area") : $bifurcatedArea ;
    //         }

    //         $onePercOfArea = $totalArea / 100;
    //         $percOfBifurcatedArea = round(($bifurcatedArea / $onePercOfArea), 2);
    //         $unPaidDemand = $propProperties->PropDueDemands()->get();
    //         $previousInterest = ($previousInterest / 100) * $percOfBifurcatedArea;
    //         foreach ($unPaidDemand as $val) {
    //             //if ($val["fyear"] == '2024-2025') {
    //                 $arr = [
    //                     "property_id"   => $this->_replicatedPropId,
    //                     "alv"           => ($val["alv"] / 100) * $percOfBifurcatedArea,
    //                     "maintanance_amt" => ($val["due_maintanance_amt"] / 100) * $percOfBifurcatedArea,
    //                     "aging_amt"     => ($val["due_aging_amt"] / 100) * $percOfBifurcatedArea,
    //                     "general_tax"   => ($val["due_general_tax"] / 100) * $percOfBifurcatedArea,
    //                     "road_tax"      => ($val["due_road_tax"] / 100) * $percOfBifurcatedArea,
    //                     "firefighting_tax" => ($val["due_firefighting_tax"] / 100) * $percOfBifurcatedArea,
    //                     "education_tax" => ($val["due_education_tax"] / 100) * $percOfBifurcatedArea,
    //                     "water_tax"     => ($val["due_water_tax"] / 100) * $percOfBifurcatedArea,
    //                     "cleanliness_tax" => ($val["due_cleanliness_tax"] / 100) * $percOfBifurcatedArea,
    //                     "sewarage_tax"  => ($val["due_sewarage_tax"] / 100) * $percOfBifurcatedArea,
    //                     "tree_tax"      => ($val["due_tree_tax"] / 100) * $percOfBifurcatedArea,
    //                     "professional_tax" => ($val["due_professional_tax"] / 100) * $percOfBifurcatedArea,
    //                     "tax1"      => ($val["due_tax1"] / 100) * $percOfBifurcatedArea,
    //                     "tax2"      => ($val["due_tax2"] / 100) * $percOfBifurcatedArea,
    //                     "tax3"      => ($val["due_tax3"] / 100) * $percOfBifurcatedArea,
    //                     "sp_education_tax" => ($val["due_sp_education_tax"] / 100) * $percOfBifurcatedArea,
    //                     "water_benefit" => ($val["due_water_benefit"] / 100) * $percOfBifurcatedArea,
    //                     "water_bill"    => ($val["due_water_bill"] / 100) * $percOfBifurcatedArea,
    //                     "sp_water_cess" => ($val["due_sp_water_cess"] / 100) * $percOfBifurcatedArea,
    //                     "drain_cess"    => ($val["due_drain_cess"] / 100) * $percOfBifurcatedArea,
    //                     "light_cess"    => ($val["due_light_cess"] / 100) * $percOfBifurcatedArea,
    //                     "major_building" => ($val["due_major_building"] / 100) * $percOfBifurcatedArea,
    //                     "total_tax"     => ($val["due_total_tax"] / 100) * $percOfBifurcatedArea,
    //                     "open_ploat_tax" => ($val["due_open_ploat_tax"] / 100) * $percOfBifurcatedArea,

    //                     "is_arrear"     => $val["is_arrear"],
    //                     "fyear"         => $val["fyear"],
    //                     "user_id"       => $user->id ?? null,
    //                     "ulb_id"        => $ulbId ?? $user->ulb_id,

    //                     "balance" => ($val["due_total_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_total_tax" => ($val["due_total_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_balance" => ($val["due_balance"] / 100) * $percOfBifurcatedArea,
    //                     "due_alv" => ($val["alv"] / 100) * $percOfBifurcatedArea,
    //                     "due_maintanance_amt" => ($val["due_maintanance_amt"] / 100) * $percOfBifurcatedArea,
    //                     "due_aging_amt"     => ($val["due_aging_amt"] / 100) * $percOfBifurcatedArea,
    //                     "due_general_tax"   => ($val["due_general_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_road_tax"      => ($val["due_road_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_firefighting_tax" => ($val["due_firefighting_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_education_tax" => ($val["due_education_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_water_tax"     => ($val["due_water_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_cleanliness_tax" => ($val["due_cleanliness_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_sewarage_tax"  => ($val["due_sewarage_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_tree_tax"      => ($val["due_tree_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_professional_tax" => ($val["due_professional_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_tax1"      => ($val["due_tax1"] / 100) * $percOfBifurcatedArea,
    //                     "due_tax2"      => ($val["due_tax2"] / 100) * $percOfBifurcatedArea,
    //                     "due_tax3"      => ($val["due_tax3"] / 100) * $percOfBifurcatedArea,
    //                     "due_sp_education_tax" => ($val["due_sp_education_tax"] / 100) * $percOfBifurcatedArea,
    //                     "due_water_benefit" => ($val["due_water_benefit"] / 100) * $percOfBifurcatedArea,
    //                     "due_water_bill"    => ($val["due_water_bill"] / 100) * $percOfBifurcatedArea,
    //                     "due_sp_water_cess" => ($val["due_sp_water_cess"] / 100) * $percOfBifurcatedArea,
    //                     "due_drain_cess"    => ($val["due_drain_cess"] / 100) * $percOfBifurcatedArea,
    //                     "due_light_cess"    => ($val["due_light_cess"] / 100) * $percOfBifurcatedArea,
    //                     "due_major_building" => ($val["due_major_building"] / 100) * $percOfBifurcatedArea,
    //                     "due_open_ploat_tax" => ($val["due_open_ploat_tax"] / 100) * $percOfBifurcatedArea,
    //                 ];
    //                 if ($oldDemand = $demand->where("fyear", $arr["fyear"])->where("property_id", $arr["property_id"])->where("status", 1)->first()) {
    //                     $oldDemand = $this->updateOldDemands($oldDemand, $arr);
    //                     $oldDemand->update();
    //                     continue;
    //                 }
    //             //}
    //             $this->testDemand($arr);
    //             $demand->store($arr);
    //             $this->adjustOldDemand($val, $arr);
    //             $val->update();
    //         }
    //         // $areaPercentage = $bifurcatedArea / $totalArea;

    //         // foreach ($unPaidDemand as $demand) {
    //         //     if ($demand->fyear == '2022-2023') {
    //         //         $arearAmount2022 = $propProperties->PropDueDemands()
    //         //             ->where('fyear', '2022-2023')
    //         //             ->sum('due_total_tax');

    //         //         $dueAmount = $arearAmount2022 * $areaPercentage;
    //         //         $demand->arrear_amt = $dueAmount;
    //         //         $demand->save();
    //         //     }

    //         //     if ($demand->fyear == '2023-2024') {
    //         //         $arearAmount2023 = $propProperties->PropDueDemands()
    //         //             ->where('fyear', '2023-2024')
    //         //             ->sum('due_total_tax');

    //         //         $dueAmount2 = $arearAmount2023 * $areaPercentage;
    //         //         $demand->arrear_amt1 = $dueAmount2;
    //         //         $demand->save();
    //         //     }
    //         // }
    //     }
    // }
    //written by prity pandey 16-09-24
    public function transferPropertyBifurcationDemand()
    {
        if (in_array($this->_activeSaf->assessment_type, ['Bifurcation'])) {
            $test = PropProperty::find($this->_replicatedPropId);
            $fyDemand = collect($this->_calculateTaxByUlb->_GRID['fyearWiseTaxes'])->sortBy("fyear");

            $user = Auth()->user();
            $ulbId = $this->_activeSaf->ulb_id;
            $demand = new PropDemand();
            $test = collect();
            $test2 = collect();
            foreach ($fyDemand as $key => $val) {
                $arr = [
                    "property_id"   => $this->_replicatedPropId,
                    "alv"           => $val["alv"],
                    "maintanance_amt" => $val["maintananceTax"] ?? 0,
                    "aging_amt"     => $val["agingAmt"] ?? 0,
                    "general_tax"   => $val["generalTax"] ?? 0,
                    "road_tax"      => $val["roadTax"] ?? 0,
                    "firefighting_tax" => $val["firefightingTax"] ?? 0,
                    "education_tax" => $val["educationTax"] ?? 0,
                    "water_tax"     => $val["waterTax"] ?? 0,
                    "cleanliness_tax" => $val["cleanlinessTax"] ?? 0,
                    "sewarage_tax"  => $val["sewerageTax"] ?? 0,
                    "tree_tax"      => $val["treeTax"] ?? 0,
                    "professional_tax" => $val["professionalTax"] ?? 0,
                    "tax1"      => $val["tax1"] ?? 0,
                    "tax2"      => $val["tax2"] ?? 0,
                    "tax3"      => $val["tax3"] ?? 0,
                    "sp_education_tax" => $val["stateEducationTax"] ?? 0,
                    "water_benefit" => $val["waterBenefitTax"] ?? 0,
                    "water_bill"    => $val["waterBillTax"] ?? 0,
                    "sp_water_cess" => $val["spWaterCessTax"] ?? 0,
                    "drain_cess"    => $val["drainCessTax"] ?? 0,
                    "light_cess"    => $val["lightCessTax"] ?? 0,
                    "major_building" => $val["majorBuildingTax"] ?? 0,
                    "total_tax"     => $val["totalTax"],
                    "open_ploat_tax" => $val["openPloatTax"] ?? 0,

                    "is_arrear"     => $val["fyear"] < getFY() ? true : false,
                    "fyear"         => $val["fyear"],
                    "user_id"       => $user->id ?? null,
                    "ulb_id"        => $ulbId ?? $user->ulb_id,

                    "balance" => $val["totalTax"],
                    "due_total_tax" => $val["totalTax"],
                    "due_balance" => $val["totalTax"],
                    "due_alv" => $val["alv"],
                    "due_maintanance_amt" => $val["maintananceTax"] ?? 0,
                    "due_aging_amt"     => $val["agingAmt"] ?? 0,
                    "due_general_tax"   => $val["generalTax"] ?? 0,
                    "due_road_tax"      => $val["roadTax"] ?? 0,
                    "due_firefighting_tax" => $val["firefightingTax"] ?? 0,
                    "due_education_tax" => $val["educationTax"] ?? 0,
                    "due_water_tax"     => $val["waterTax"] ?? 0,
                    "due_cleanliness_tax" => $val["cleanlinessTax"] ?? 0,
                    "due_sewarage_tax"  => $val["sewerageTax"] ?? 0,
                    "due_tree_tax"      => $val["treeTax"] ?? 0,
                    "due_professional_tax" => $val["professionalTax"] ?? 0,
                    "due_tax1"      => $val["tax1"] ?? 0,
                    "due_tax2"      => $val["tax2"] ?? 0,
                    "due_tax3"      => $val["tax3"] ?? 0,
                    "due_sp_education_tax" => $val["stateEducationTax"] ?? 0,
                    "due_water_benefit" => $val["waterBenefitTax"] ?? 0,
                    "due_water_bill"    => $val["waterBillTax"] ?? 0,
                    "due_sp_water_cess" => $val["spWaterCessTax"] ?? 0,
                    "due_drain_cess"    => $val["drainCessTax"] ?? 0,
                    "due_light_cess"    => $val["lightCessTax"] ?? 0,
                    "due_major_building" => $val["majorBuildingTax"] ?? 0,
                    "due_open_ploat_tax" => $val["openPloatTax"] ?? 0,
                ];
                if ($oldDemand = $demand->where("fyear", $arr["fyear"])->where("property_id", $arr["property_id"])->where("status", 1)->first()) {
                    $oldDemand = $this->updateOldDemandsV1($oldDemand, $arr);
                    $oldDemand->update();
                    continue;
                }
                $this->testDemand($arr);
                $demand->store($arr);
                $privOld = $demand->where("property_id", $this->_activeSaf->previous_holding_id)->where("fyear", $val["fyear"])->where("status", 1)->first();
                $val["fyear"] != getFY() ? $this->adjustOldDemand($privOld, $arr) : "";
                $privOld->update();
                $test->push($privOld);
                $test2->push($arr);
            }
        }
    }

    //written by prity pandey 16-09-24
    public function generateAfterBifurcationPropertyRequest()
    {
        $property = PropProperty::find($this->_activeSaf->previous_holding_id);
        $calculationReq = [
            "propertyType" => $property->prop_type_mstr_id,
            "areaOfPlot" => $property->area_of_plot,
            "category" => $property->category_id,
            "dateOfPurchase" => $property->land_occupation_date,
            "previousHoldingId" => $property->id ?? 0,
            "applyDate" => $property->application_date ?? null,
            "ward" => $property->ward_mstr_id ?? null,
            "zone" => $property->zone_mstr_id ?? null,
            "assessmentType" => 1,
            "nakshaAreaOfPlot" => $property->naksha_area_of_plot,
            "isAllowDoubleTax" => $property->is_allow_double_tax,
            "floor" => [],
            "owner" => []
        ];

        // Get Floors
        if ($property->prop_type_mstr_id != 4) {
            $propFloors = $this->_mPropFloors->getFloorsByPropId($property->id);

            if (collect($propFloors)->isEmpty())
                throw new Exception("Floors not available for this property");

            foreach ($propFloors as $floor) {
                $floorReq =  [
                    "floorNo" => $floor->floor_mstr_id,
                    "constructionType" =>  $floor->const_type_mstr_id,
                    "occupancyType" =>  $floor->occupancy_type_mstr_id ?? "",
                    "usageType" => $floor->usage_type_mstr_id,
                    "buildupArea" =>  $floor->builtup_area,
                    "dateFrom" =>  Carbon::now()->format("Y-m-d"),
                    "dateUpto" =>  $floor->date_upto
                ];
                array_push($calculationReq['floor'], $floorReq);
            }
        }

        // Get Owners
        $propFirstOwners = $this->_mPropOwners->firstOwner($property->id);
        if (collect($propFirstOwners)->isEmpty())
            throw new Exception("Owner Details not Available");

        $ownerReq = [
            "isArmedForce" => $propFirstOwners->is_armed_force
        ];
        array_push($calculationReq['owner'], $ownerReq);
        $propRequest = new Request($calculationReq);

        $taxCalculator = new TaxCalculator($propRequest);
        $taxCalculator->calculateTax();
        return $taxCalculator->_GRID['fyearWiseTaxes'];
    }

    public function adjustOldDemand($oldDemand, $newDemand)
    {
        $oldDemand->balance         = $oldDemand->balance - $newDemand["total_tax"];

        $oldDemand->due_maintanance_amt  = $oldDemand->due_maintanance_amt - $newDemand["due_maintanance_amt"];
        $oldDemand->due_aging_amt  = $oldDemand->due_aging_amt - $newDemand["due_aging_amt"];
        $oldDemand->due_general_tax  = $oldDemand->due_general_tax - $newDemand["due_general_tax"];
        $oldDemand->due_road_tax  = $oldDemand->due_road_tax - $newDemand["due_road_tax"];
        $oldDemand->due_firefighting_tax  = $oldDemand->due_firefighting_tax - $newDemand["due_firefighting_tax"];
        $oldDemand->due_education_tax  = $oldDemand->due_education_tax - $newDemand["due_education_tax"];
        $oldDemand->due_water_tax  = $oldDemand->due_water_tax - $newDemand["due_water_tax"];
        $oldDemand->due_cleanliness_tax  = $oldDemand->due_cleanliness_tax - $newDemand["due_cleanliness_tax"];
        $oldDemand->due_sewarage_tax  = $oldDemand->due_sewarage_tax - $newDemand["due_sewarage_tax"];
        $oldDemand->due_tree_tax  = $oldDemand->due_tree_tax - $newDemand["due_tree_tax"];
        $oldDemand->due_professional_tax  = $oldDemand->due_professional_tax - $newDemand["due_professional_tax"];
        $oldDemand->due_total_tax  = $oldDemand->due_total_tax - $newDemand["due_total_tax"];
        $oldDemand->due_balance  = $oldDemand->due_balance - $newDemand["due_balance"];
        $oldDemand->due_tax1  = $oldDemand->due_tax1 - $newDemand["due_tax1"];
        $oldDemand->due_tax2  = $oldDemand->due_tax2 - $newDemand["due_tax2"];
        $oldDemand->due_tax3  = $oldDemand->due_tax3 - $newDemand["due_tax3"];
        $oldDemand->due_sp_education_tax  = $oldDemand->due_sp_education_tax - $newDemand["due_sp_education_tax"];
        $oldDemand->due_water_benefit  = $oldDemand->due_water_benefit - $newDemand["due_water_benefit"];
        $oldDemand->due_water_bill  = $oldDemand->due_water_bill - $newDemand["due_water_bill"];
        $oldDemand->due_sp_water_cess  = $oldDemand->due_sp_water_cess - $newDemand["due_sp_water_cess"];
        $oldDemand->due_drain_cess  = $oldDemand->due_drain_cess - $newDemand["due_drain_cess"];
        $oldDemand->due_light_cess  = $oldDemand->due_light_cess - $newDemand["due_light_cess"];
        $oldDemand->due_major_building  = $oldDemand->due_major_building - $newDemand["due_major_building"];
        $oldDemand->open_ploat_tax  = $oldDemand->open_ploat_tax - $newDemand["open_ploat_tax"];
        $oldDemand->due_open_ploat_tax  = $oldDemand->due_open_ploat_tax - $newDemand["due_open_ploat_tax"];
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 1) {
            $oldDemand->is_full_paid = false;
        }
        if ($oldDemand->due_total_tax > 0 && $oldDemand->paid_status == 0) {
            $oldDemand->is_full_paid = true;
        }
        return $oldDemand;
    }

    //prity pandey 16-09-24
    public function adjustPaidAmount($paidAmount, $demand)
    {
        $currentTax = collect();
        $currentTax->push($demand);
        $totaTax = $currentTax->sum("due_balance");


        $perPecOfTax =  $totaTax / 100;
        $payableAmountOfTax = $paidAmount / 100;

        $generalTaxPerc = ($currentTax->sum('general_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $roadTaxPerc = ($currentTax->sum('road_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $firefightingTaxPerc = ($currentTax->sum('firefighting_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $educationTaxPerc = ($currentTax->sum('education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterTaxPerc = ($currentTax->sum('water_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $cleanlinessTaxPerc = ($currentTax->sum('cleanliness_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $sewarageTaxPerc = ($currentTax->sum('sewarage_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $treeTaxPerc = ($currentTax->sum('tree_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $professionalTaxPerc = ($currentTax->sum('professional_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax1Perc = ($currentTax->sum('tax1') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax2Perc = ($currentTax->sum('tax2') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax3Perc = ($currentTax->sum('tax3') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $stateEducationTaxPerc = ($currentTax->sum('sp_education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBenefitPerc = ($currentTax->sum('water_benefit') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBillPerc = ($currentTax->sum('water_bill') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $spWaterCessPerc = ($currentTax->sum('sp_water_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $drainCessPerc = ($currentTax->sum('drain_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $lightCessPerc = ($currentTax->sum('light_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $majorBuildingPerc = ($currentTax->sum('major_building') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $openPloatTaxPerc = ($currentTax->sum('open_ploat_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;

        $totalPerc = $generalTaxPerc + $roadTaxPerc + $firefightingTaxPerc + $educationTaxPerc +
            $waterTaxPerc + $cleanlinessTaxPerc + $sewarageTaxPerc + $treeTaxPerc
            + $professionalTaxPerc + $tax1Perc + $tax2Perc + $tax3Perc
            + $stateEducationTaxPerc + $waterBenefitPerc + $waterBillPerc +
            $spWaterCessPerc + $drainCessPerc + $lightCessPerc + $majorBuildingPerc
            + $openPloatTaxPerc;



        /**
         * | 100 % = Payable Amount
         * | 1 % = Payable Amount/100  (Hence We Have devided the taxes by hundred)
         */


        $paidDemandBifurcation = [
            'due_general_tax' => roundFigure(($payableAmountOfTax * $generalTaxPerc)),
            'due_road_tax' => roundFigure(($payableAmountOfTax * $roadTaxPerc)),
            'due_firefighting_tax' => roundFigure(($payableAmountOfTax * $firefightingTaxPerc)),
            'due_education_tax' => roundFigure(($payableAmountOfTax * $educationTaxPerc)),
            'due_water_tax' => roundFigure(($payableAmountOfTax * $waterTaxPerc)),
            'due_cleanliness_tax' => roundFigure(($payableAmountOfTax * $cleanlinessTaxPerc)),
            'due_sewarage_tax' => roundFigure(($payableAmountOfTax * $sewarageTaxPerc)),
            'due_tree_tax' => roundFigure(($payableAmountOfTax * $treeTaxPerc)),
            'due_professional_tax' => roundFigure(($payableAmountOfTax * $professionalTaxPerc)),
            'due_tax1' => roundFigure(($payableAmountOfTax * $tax1Perc)),
            'due_tax2' => roundFigure(($payableAmountOfTax * $tax2Perc)),
            'due_tax3' => roundFigure(($payableAmountOfTax * $tax3Perc)),
            'due_sp_education_tax' => roundFigure(($payableAmountOfTax * $stateEducationTaxPerc)),
            'due_water_benefit' => roundFigure(($payableAmountOfTax * $waterBenefitPerc)),
            'due_water_bill' => roundFigure(($payableAmountOfTax * $waterBillPerc)),
            'due_sp_water_cess' => roundFigure(($payableAmountOfTax * $spWaterCessPerc)),
            'due_drain_cess' => roundFigure(($payableAmountOfTax * $drainCessPerc)),
            'due_light_cess' => roundFigure(($payableAmountOfTax * $lightCessPerc)),
            'due_major_building' => roundFigure(($payableAmountOfTax * $majorBuildingPerc)),
            'due_open_ploat_tax' => roundFigure(($payableAmountOfTax * $openPloatTaxPerc)),
            'due_total_tax' => roundFigure(($payableAmountOfTax * $totalPerc)),
            "due_balance" => roundFigure(($payableAmountOfTax * $totalPerc)),
        ];
        $demand["paid_total_tax"] =  $paidDemandBifurcation["due_total_tax"] ?? 0;
        foreach ($paidDemandBifurcation as $key => $val) {
            $demand[$key] = roundFigure($demand[$key] - $val);
        }
        return $demand;
    }

    public function testDemand($newDemand)
    {
        $newDemand = collect($newDemand);
        $newDemand1 = collect($newDemand)->only([
            "due_maintanance_amt",
            "due_general_tax",
            "due_road_tax",
            "due_firefighting_tax",
            "due_education_tax",
            "due_water_tax",
            "due_cleanliness_tax",
            "due_sewarage_tax",
            "due_tree_tax",
            "due_professional_tax",
            "due_tax1",
            "due_tax2",
            "due_tax3",
            "due_sp_education_tax",
            "due_water_benefit",
            "due_water_bill",
            "due_sp_water_cess",
            "due_drain_cess",
            "due_light_cess",
            "due_major_building",
            "due_open_ploat_tax"
        ]);
        // $diff=round($newDemand["total_tax"])-round($newDemand1->sum());
        // if(round($newDemand["total_tax"])!=round($newDemand1->sum())&& !is_between(round($diff), -1, 1.1))
        // {
        //     throw new Exception("Demand not adjusted properly");
        // }
        if (round($newDemand["total_tax"]) != round($newDemand1->sum())) {
            throw new Exception("Demand not adjusted properly");
        }
    }

    public function generateBiNewFloorDemand()
    {
        $floorReqs = [];
        $ownerReqs = [];
        $safDtls   = $this->_activeSaf;
        $floorDtls = collect($this->_floorDetails)->whereNull('prop_floor_details_id');

        foreach ($floorDtls as $floor) {

            $floor = $this->_verifiedFloors->where('saf_floor_id', $floor->id);
            $floor = $floor->first();

            $floorReq =  [
                "floorNo" => $floor['floor_mstr_id'],
                "constructionType" =>  $floor['construction_type_id'],
                "occupancyType" =>  $floor['occupancy_type_id'] ?? "",
                "usageType" => $floor['usage_type_id'],
                "buildupArea" =>  $floor['builtup_area'],
                "dateFrom" =>  $floor['date_from'],
                "dateUpto" =>  $floor['date_upto']
            ];
            array_push($floorReqs, $floorReq);
        }

        $ownerDtls = ($this->_ownerDetails->sortBy('id'));
        foreach ($ownerDtls as $ownerDtl) {

            $ownerReq = ["isArmedForce" => $ownerDtl->is_armed_force];
            array_push($ownerReqs, $ownerReq);
        }

        $request   = new Request([
            "propertyType" => $this->_activeSaf->property_type,
            "assessmentType" => $this->_activeSaf->assessment_type,
            "dateOfPurchase" => $this->_activeSaf->land_occupation_date,
            "previousHoldingId" => $this->_activeSaf->previous_holding_id,
            "areaOfPlot" => $this->_activeSaf->area_of_plot,
            "category" => $this->_activeSaf->category_id,
            "owner" => $ownerReqs,
            "floor" => $floorReqs,
        ]);
        $taxCalculator = new TaxCalculator($request);
        $taxCalculator->calculateTax();
        $fyDemand = collect($taxCalculator->_GRID['fyearWiseTaxes'])->sortBy("fyear");
        $user = Auth()->user();
        $ulbId = $this->_activeSaf->ulb_id;
        $demand = new PropDemand();

        foreach ($fyDemand as $key => $val) {
            $arr = [
                "property_id"   => $this->_replicatedPropId,
                "alv"           => $val["alv"],
                "maintanance_amt" => $val["maintananceTax"] ?? 0,
                "aging_amt"     => $val["agingAmt"] ?? 0,
                "general_tax"   => $val["generalTax"] ?? 0,
                "road_tax"      => $val["roadTax"] ?? 0,
                "firefighting_tax" => $val["firefightingTax"] ?? 0,
                "education_tax" => $val["educationTax"] ?? 0,
                "water_tax"     => $val["waterTax"] ?? 0,
                "cleanliness_tax" => $val["cleanlinessTax"] ?? 0,
                "sewarage_tax"  => $val["sewerageTax"] ?? 0,
                "tree_tax"      => $val["treeTax"] ?? 0,
                "professional_tax" => $val["professionalTax"] ?? 0,
                "tax1"      => $val["tax1"] ?? 0,
                "tax2"      => $val["tax2"] ?? 0,
                "tax3"      => $val["tax3"] ?? 0,
                "sp_education_tax" => $val["stateEducationTax"] ?? 0,
                "water_benefit" => $val["waterBenefitTax"] ?? 0,
                "water_bill"    => $val["waterBillTax"] ?? 0,
                "sp_water_cess" => $val["spWaterCessTax"] ?? 0,
                "drain_cess"    => $val["drainCessTax"] ?? 0,
                "light_cess"    => $val["lightCessTax"] ?? 0,
                "major_building" => $val["majorBuildingTax"] ?? 0,
                "total_tax"     => $val["totalTax"],
                "open_ploat_tax" => $val["openPloatTax"] ?? 0,

                "is_arrear"     => $val["fyear"] < getFY() ? true : false,
                "fyear"         => $val["fyear"],
                "user_id"       => $user->id ?? null,
                "ulb_id"        => $ulbId ?? $user->ulb_id,

                "balance" => $val["totalTax"],
                "due_total_tax" => $val["totalTax"],
                "due_balance" => $val["totalTax"],
                "due_alv" => $val["alv"],
                "due_maintanance_amt" => $val["maintananceTax"] ?? 0,
                "due_aging_amt"     => $val["agingAmt"] ?? 0,
                "due_general_tax"   => $val["generalTax"] ?? 0,
                "due_road_tax"      => $val["roadTax"] ?? 0,
                "due_firefighting_tax" => $val["firefightingTax"] ?? 0,
                "due_education_tax" => $val["educationTax"] ?? 0,
                "due_water_tax"     => $val["waterTax"] ?? 0,
                "due_cleanliness_tax" => $val["cleanlinessTax"] ?? 0,
                "due_sewarage_tax"  => $val["sewerageTax"] ?? 0,
                "due_tree_tax"      => $val["treeTax"] ?? 0,
                "due_professional_tax" => $val["professionalTax"] ?? 0,
                "due_tax1"      => $val["tax1"] ?? 0,
                "due_tax2"      => $val["tax2"] ?? 0,
                "due_tax3"      => $val["tax3"] ?? 0,
                "due_sp_education_tax" => $val["stateEducationTax"] ?? 0,
                "due_water_benefit" => $val["waterBenefitTax"] ?? 0,
                "due_water_bill"    => $val["waterBillTax"] ?? 0,
                "due_sp_water_cess" => $val["spWaterCessTax"] ?? 0,
                "due_drain_cess"    => $val["drainCessTax"] ?? 0,
                "due_light_cess"    => $val["lightCessTax"] ?? 0,
                "due_major_building" => $val["majorBuildingTax"] ?? 0,
                "due_open_ploat_tax" => $val["openPloatTax"] ?? 0,
            ];
            if ($oldDemand = $demand->where("fyear", $arr["fyear"])->where("property_id", $arr["property_id"])->where("status", 1)->first()) {
                $oldDemand = $this->updateOldDemands($oldDemand, $arr);
                $oldDemand->update();
                continue;
            }
            $demand->store($arr);
        }
    }

    public function deactivateAmalgamateProp()
    {
        if ($this->_activeSaf->assessment_type == "Amalgamation") {
            $amalgamateProps = $this->_activeSaf->getAmalgamateLogs()->where("is_master", false);
            foreach ($amalgamateProps as $aProp) {
                $aProp->status = 4;
                $aProp->update();
            }
        }
    }
}
