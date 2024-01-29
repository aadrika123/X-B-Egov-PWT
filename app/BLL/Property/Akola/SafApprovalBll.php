<?php

namespace App\BLL\Property\Akola;

use App\MicroServices\IdGenerator\HoldingNoGenerator;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\MicroServices\IdGenerator\PropIdGenerator;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropAssessmentHistory;
use App\Models\Property\PropDemand;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSafMemoDtl;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropSafVerificationDtl;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

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
    private $_activeSaf;
    private $_ownerDetails;
    private $_floorDetails;
    private $_toBeProperties;
    public $_replicatedPropId;
    private $_mPropSafVerifications;
    private $_mPropSafVerificationDtls;
    private $_verifiedPropDetails;
    private $_verifiedFloors;
    private $_mPropFloors;
    private $_calculateTaxByUlb;
    public $_holdingNo;
    public $_ptNo;
    public $_famNo;
    public $_famId;
    protected $_SkipFiledWorkWfMstrId = [];
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
        $history = new PropAssessmentHistory();
        $history->property_id = $propProperties->id;
        $history->assessment_type = $this->_activeSaf->assessment_type;
        $history->saf_id = $this->_activeSaf->id;
        $history->prop_log = json_encode($propProperties->toArray(), JSON_UNESCAPED_UNICODE);
        $history->owner_log = json_encode($oldOwners->toArray(), JSON_UNESCAPED_UNICODE);
        $history->floar_log = json_encode($oldFloor->toArray(), JSON_UNESCAPED_UNICODE);
        $history->demand_log = json_encode($oldDemand->toArray(), JSON_UNESCAPED_UNICODE);

        $history->user_id = Auth()->user() ? Auth()->user()->id : 0;
        $history->save();

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
                $oldPFloorUpdate = PropFloor::find($safFloor->prop_floor_details_id ? $safFloor->prop_floor_details_id : 0);
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
        $approvedSaf->property_id = $this->_replicatedPropId;
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
        $fyDemand = collect($this->_calculateTaxByUlb->_GRID['fyearWiseTaxes'])->sortBy("fyear");
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

    public function transferPropertyBifucation()
    {
        if (in_array($this->_activeSaf->assessment_type, ['Bifurcation'])) {
            $propProperties = PropProperty::find($this->_activeSaf->previous_holding_id);
            if (!$propProperties) {
                throw new Exception("Old Property Not Found");
            }
            $propProperties->update(["area_of_plot" => $propProperties->area_of_plot - $this->_verifiedPropDetails[0]->area_of_plot]);

            if ($this->_activeSaf->prop_type_mstr_id != 4) {               // Applicable Not for Vacant Land

                $propFloors = PropFloor::where("property_id", $propProperties->id)
                    ->orderby('id')
                    ->get();

                foreach ($this->_verifiedFloors as $floorDetail) {
                    $activeSafFloorDtl = $this->_floorDetails->where('id', $floorDetail->saf_floor_id);
                    $activeSafFloorDtl = collect($activeSafFloorDtl)->first();

                    $propFloor =  collect($propFloors)->where('id', $activeSafFloorDtl->prop_floor_details_id);
                    $propFloor =  collect($propFloor)->first();
                    $propFloor->builtup_area = $propFloor->builtup_area - $activeSafFloorDtl->builtup_area;
                    $propFloor->carpet_area = $propFloor->builtup_area - $activeSafFloorDtl->builtup_area;
                    $propFloor->save();
                }
            }
        }
    }
}
