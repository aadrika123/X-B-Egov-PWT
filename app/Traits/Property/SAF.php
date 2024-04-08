<?php

namespace App\Traits\Property;

use App\Http\Controllers\Property\ActiveSafController;
use App\Models\CustomDetail;
use App\Models\Property\ActiveSaf;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\PropSafsOwner;
use App\Models\UlbWardMaster;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Property\Concrete\PropertyBifurcation;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * | Created On-17-10-2022 
 * | Created By - Anshu Kumar
 * | Created for - Code Reausable for SAF Repository
 */

trait SAF
{
    /**
     * | Apply SAF Trait
     */
    public function tApplySaf($saf, $req, $safNo, $roadWidthType)
    {
        $saf->has_previous_holding_no = $req->hasPreviousHoldingNo;
        $saf->previous_holding_id = $req->previousHoldingId;
        $saf->previous_ward_mstr_id = $req->previousWard;
        $saf->is_owner_changed = $req->isOwnerChanged;
        $saf->transfer_mode_mstr_id = $req->transferModeId;
        $saf->saf_no = $safNo;
        $saf->ward_mstr_id = $req->ward;
        $saf->ownership_type_mstr_id = $req->ownershipType;
        $saf->prop_type_mstr_id = $req->propertyType;
        $saf->appartment_name = $req->apartmentName;
        $saf->flat_registry_date = $req->flatRegistryDate;
        $saf->zone_mstr_id = $req->zone;
        $saf->no_electric_connection = $req->electricityConnection;
        $saf->elect_consumer_no = $req->electricityCustNo;
        $saf->elect_acc_no = $req->electricityAccNo;
        $saf->elect_bind_book_no = $req->electricityBindBookNo;
        $saf->elect_cons_category = $req->electricityConsCategory;
        $saf->building_plan_approval_no = $req->buildingPlanApprovalNo;
        $saf->building_plan_approval_date = $req->buildingPlanApprovalDate;
        $saf->water_conn_no = $req->waterConnNo;
        $saf->water_conn_date = $req->waterConnDate;
        $saf->khata_no = $req->khataNo;
        $saf->plot_no = $req->plotNo;
        $saf->village_mauja_name = $req->villageMaujaName;
        $saf->road_type_mstr_id = $roadWidthType;
        $saf->area_of_plot = $req->areaOfPlot;
        $saf->prop_address = $req->propAddress;
        $saf->prop_city = $req->propCity;
        $saf->prop_dist = $req->propDist;
        $saf->prop_pin_code = $req->propPinCode;
        $saf->is_corr_add_differ = $req->isCorrAddDiffer;
        $saf->corr_address = $req->corrAddress;
        $saf->corr_city = $req->corrCity;
        $saf->corr_dist = $req->corrDist;
        $saf->corr_pin_code = $req->corrPinCode;

        $saf->is_mobile_tower = $req->isMobileTower;
        if ($req->isMobileTower == 1) {
            $saf->tower_area = $req->mobileTower['area'];
            $saf->tower_installation_date = $req->mobileTower['dateFrom'];
        }

        $saf->is_hoarding_board = $req->isHoardingBoard;
        if ($req->isHoardingBoard == 1) {
            $saf->hoarding_area = $req->hoardingBoard['area'];
            $saf->hoarding_installation_date = $req->hoardingBoard['dateFrom'];
        }

        $saf->is_petrol_pump = $req->isPetrolPump;
        if ($req->isPetrolPump == 1) {
            $saf->under_ground_area = $req->petrolPump['area'];
            $saf->petrol_pump_completion_date = $req->petrolPump['dateFrom'];
        }

        $saf->is_water_harvesting = $req->isWaterHarvesting;
        $saf->land_occupation_date = $req->landOccupationDate;
        $saf->doc_verify_cancel_remarks = $req->docVerifyCancelRemark;

        $saf->application_date =  Carbon::now()->format('Y-m-d');
        $saf->assessment_type = $req->assessmentType;
        $saf->saf_distributed_dtl_id = $req->safDistributedDtl;
        $saf->prop_dtl_id = $req->propDtl;
        $saf->prop_state = $req->propState;
        $saf->corr_state = $req->corrState;
        $saf->holding_type = $req->holdingType;
        $saf->ip_address = $req->ipAddress;
        // $saf->property_assessment_id = $req->assessmentType;
        $saf->new_ward_mstr_id = $req->newWard;
        $saf->percentage_of_property_transfer = $req->percOfPropertyTransfer;
        $saf->apartment_details_id = $req->apartmentDetail;
        $saf->applicant_name = collect($req->owner)->first()['ownerName'];
        $saf->road_width = $req->roadType;
    }

    // Trait SAF Owner
    public function tApplySafOwner($owner, $safId, $owner_details)
    {
        $owner->saf_id = $safId;
        $owner->owner_name = $owner_details['ownerName'] ?? null;
        $owner->guardian_name = $owner_details['guardianName'] ?? null;
        $owner->relation_type = $owner_details['relation'] ?? null;
        $owner->mobile_no = $owner_details['mobileNo'] ?? null;
        $owner->email = $owner_details['email'] ?? null;
        $owner->pan_no = $owner_details['pan'] ?? null;
        $owner->aadhar_no = $owner_details['aadhar'] ?? null;
        $owner->gender = $owner_details['gender'] ?? null;
        $owner->dob = $owner_details['dob'] ?? null;
        $owner->is_armed_force = $owner_details['isArmedForce'] ?? null;
        $owner->is_specially_abled = $owner_details['isSpeciallyAbled'] ?? null;
    }

    // Trait SAF Floors
    public function tApplySafFloor($floor, $safId, $floor_details)
    {
        // Calculate Carpet Area
        $readFloorUsageType = $floor_details['useType'];
        $paramCarpetAreaPerc = ($readFloorUsageType == 1) ? 70 : 80;
        $carpetArea = ($floor_details['buildupArea'] * $paramCarpetAreaPerc) / 100;

        $floor->saf_id = $safId;
        $floor->floor_mstr_id = $floor_details['floorNo'] ?? null;
        $floor->usage_type_mstr_id = $floor_details['useType'] ?? null;
        $floor->const_type_mstr_id = $floor_details['constructionType'] ?? null;
        $floor->occupancy_type_mstr_id = $floor_details['occupancyType'] ?? null;
        $floor->builtup_area = $floor_details['buildupArea'] ?? null;
        $floor->carpet_area = $carpetArea;
        $floor->date_from = $floor_details['dateFrom'] ?? null;
        $floor->date_upto = $floor_details['dateUpto'] ?? null;
        $floor->prop_floor_details_id = $floor_details['propFloorDetailId'] ?? null;
    }

    // SAF Inbox 
    public function getSaf($workflowIds)
    {
        $data = DB::table('prop_active_safs')
            ->join('prop_active_safs_owners as o', 'o.saf_id', '=', 'prop_active_safs.id')
            ->join('ref_prop_types as p', 'p.id', '=', 'prop_active_safs.prop_type_mstr_id')
            ->join('ulb_ward_masters as ward', 'ward.id', '=', 'prop_active_safs.ward_mstr_id')
            ->select(
                'prop_active_safs.saf_no',
                'prop_active_safs.id',
                'prop_active_safs.ward_mstr_id',
                'ward.ward_name as ward_no',
                'prop_active_safs.prop_type_mstr_id',
                'prop_active_safs.appartment_name',
                DB::raw("string_agg(o.id::VARCHAR,',') as owner_id"),
                DB::raw("string_agg(o.owner_name,',') as owner_name"),
                'p.property_type',
                'prop_active_safs.assessment_type as assessment',
                'prop_active_safs.application_date as apply_date',
                'prop_active_safs.parked'
            )
            ->whereIn('workflow_id', $workflowIds);
        return $data;
    }

    /**
     * | Generate SAF No
     */
    /**
     * desc This function return the safNo of the application
     * format: SAF/application_type/ward_no/count active application on the basise of ward_id
     *         3 |       02       |   03   |            05    ;
     * request : ward_id,assessment_type,ulb_id;
     * #==========================================
     * --------Tables------------
     * activ_saf_details  -> for counting;
     * ward_matrs   -> for ward_no;
     * ===========================================
     * #count <- count(activ_saf_details.*)
     * #ward_no <- ward_matrs.ward_no
     * #safNo <- "SAF/".str_pad($assessment_type,2,'0',STR_PAD_LEFT)."/".str_pad($word_no,3,'0',STR_PAD_LEFT)."/".str_pad($count,5,'0',STR_PAD_LEFT)
     * Status-Closed
     */
    public function safNo($ward_id, $assessment_type, $ulb_id)
    {
        $count = PropActiveSaf::where('ward_mstr_id', $ward_id)
            ->where('ulb_id', $ulb_id)
            ->count() + 1;
        $ward_no = UlbWardMaster::select("ward_name")->where('id', $ward_id)->first()->ward_name;
        return $safNo = "SAF/" . str_pad($assessment_type, 2, '0', STR_PAD_LEFT) . "/" . str_pad($ward_no, 3, '0', STR_PAD_LEFT) . "/" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * | Get SAF Request Details for SAF Calculation by SAF ID
     */

    public function generateSafRequest($req)
    {
        $array = array();

        $array['ward'] = $req['ward_mstr_id'];
        $array['propertyType'] = $req['prop_type_mstr_id'];
        $array['dateOfPurchase'] = $req['land_occupation_date'];
        $array['landOccupationDate'] = $req['land_occupation_date'];
        $array['ownershipType'] = $req['ownership_type_mstr_id'];
        $array['roadType'] = isset($req['road_width']) ? $req['road_width'] : 0;
        $array['areaOfPlot'] = $req['area_of_plot'];
        $array['isMobileTower'] = $req['is_mobile_tower'];
        $array['mobileTower']['area'] = $req['tower_area'];
        $array['mobileTower']['dateFrom'] = $req['tower_installation_date'];
        $array['isHoardingBoard'] = $req['is_hoarding_board'];
        $array['hoardingBoard']['area'] = $req['hoarding_area'];
        $array['hoardingBoard']['dateFrom'] = $req['hoarding_installation_date'];
        $array['isPetrolPump'] = $req['is_petrol_pump'];
        $array['petrolPump']['area'] = $req['under_ground_area'];
        $array['petrolPump']['dateFrom'] = $req['petrol_pump_completion_date'];
        $array['isWaterHarvesting'] = $req['is_water_harvesting'];
        $array['zone'] = $req['zone_mstr_id'];
        $array['roadTypeMstrId'] = $req['road_type_mstr_id'];
        $array['isGBSaf'] = $req['is_gb_saf'];
        $array['ulbId'] = $req['ulb_id'];
        $array['rwhDateFrom'] = $req['rwh_date_from'] ?? null;
        $array['isTrust'] = $req['is_trust'];
        $array['trustType'] = $req['trust_type'];
        $refFloors = $req['floors'];

        foreach ($refFloors as $key => $refFloor) {
            if (is_object($refFloor))
                $refFloor = json_decode(json_encode($refFloor), true);
            $array['floor'][$key]['floorNo'] = $refFloor['floor_mstr_id'];
            $array['floor'][$key]['useType'] = $refFloor['usage_type_mstr_id'];
            $array['floor'][$key]['usageType'] = $refFloor['usage_type_mstr_id'];
            $array['floor'][$key]['constructionType'] = $refFloor['const_type_mstr_id'];
            $array['floor'][$key]['occupancyType'] = $refFloor['occupancy_type_mstr_id'];
            $array['floor'][$key]['buildupArea'] = $refFloor['builtup_area'];
            $array['floor'][$key]['dateFrom'] = $refFloor['date_from'];
            $array['floor'][$key]['dateUpto'] = $refFloor['date_upto'];
        }

        $refFloors = $req['owners'];

        foreach ($refFloors as $key => $refFloor) {
            if (is_object($refFloor))
                $refFloor = json_decode(json_encode($refFloor), true);
            $array['owner'][$key]['ownerName'] = $refFloor['owner_name'];
            $array['owner'][$key]['gender'] = $refFloor['gender'];
            $array['owner'][$key]['guardianName'] = $refFloor['guardian_name'];
            $array['owner'][$key]['relation'] = $refFloor['relation_type'];
            $array['owner'][$key]['mobileNo'] = $refFloor['mobile_no'];
            $array['owner'][$key]['email'] = $refFloor['email'];
            $array['owner'][$key]['aadhar'] = $refFloor['aadhar_no'];
            $array['owner'][$key]['isArmedForce'] = $refFloor['is_armed_force'];
            $array['owner'][$key]['isSpeciallyAbled'] = $refFloor['is_specially_abled'];
            $array['owner'][$key]['dob'] = $refFloor['dob'];
        }

        return $array;
    }

    /**
     * | Read Assessment Types
     */
    public function readAssessmentType($assessmentType)
    {
        if ($assessmentType == 1)                                                     // New Assessment 
            $assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.1');

        if ($assessmentType == 2)                                                   // Reassessment
            $assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.2');


        if ($assessmentType == 3)                                                     // Mutation
            $assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.3');


        if ($assessmentType == 4)                                                    // Bifurcation
            $assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.4');


        if ($assessmentType == 5)                                                    // Amalgamation
            $assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.5');

        return $assessmentType;
    }

    /**
     * | Generated SAF Demand to push the value in propSafsDemand Table 
     * | @param collection
     */
    public function generateSafDemand($collection)
    {
        $filtered = collect($collection)->map(function ($value) {
            return collect($value)->only([
                'qtr', 'holdingTax', 'waterTax', 'educationTax',
                'healthTax', 'latrineTax', 'quarterYear', 'dueDate', 'totalTax', 'arv', 'rwhPenalty', 'onePercPenalty', 'onePercPenaltyTax', 'ruleSet'
            ]);
        });

        $groupBy = $filtered->groupBy(['quarterYear', 'qtr']);

        $taxes = $groupBy->map(function ($values) {
            return $values->map(function ($collection) {
                $totalAmt = roundFigure($collection->sum('totalTax'));
                return collect([
                    'quarterYear' => $collection->first()['quarterYear'],
                    'qtr' => $collection->first()['qtr'],
                    'dueDate' => $collection->first()['dueDate'],
                    'rwhPenalty' => roundFigure($collection->sum('rwhPenalty')),
                    'onePercPenalty' => roundFigure($collection->sum('onePercPenalty')),
                    'onePercPenaltyTax' => roundFigure($collection->sum('onePercPenaltyTax')),
                    'arv' => roundFigure($collection->sum('arv')),
                    'holdingTax' => roundFigure($collection->sum('holdingTax')),
                    'waterTax' => roundFigure($collection->sum('waterTax')),
                    'educationTax' => roundFigure($collection->sum('educationTax')),
                    'healthCess' => roundFigure($collection->sum('healthTax')),
                    'latrineTax' => roundFigure($collection->sum('latrineTax')),
                    'additionTax' => roundFigure($collection->sum('additionalTax')),
                    'totalTax' => $totalAmt,
                    'ruleSet' => $collection->first()['ruleSet'],
                    'adjustAmount' => 0,
                    'balance' => $totalAmt
                ]);
            });
        });

        return $taxes->values()->collapse();
    }

    /**
     * | Save SAF Demand
     */
    public function tSaveSafDemand($propSafDemand, $safDemandDetail, $safId)
    {
        $propSafDemand->saf_id = $safId;
        $propSafDemand->arv = $safDemandDetail['arv'];
        $propSafDemand->water_tax = $safDemandDetail['waterTax'];
        $propSafDemand->education_cess = $safDemandDetail['educationTax'];
        $propSafDemand->health_cess = $safDemandDetail['healthCess'];
        $propSafDemand->latrine_tax = $safDemandDetail['latrineTax'];
        $propSafDemand->additional_tax = $safDemandDetail['rwhPenalty'];
        $propSafDemand->holding_tax = $safDemandDetail['holdingTax'];
        $propSafDemand->amount = $safDemandDetail['totalTax'];
        $propSafDemand->fyear = $safDemandDetail['quarterYear'];
        $propSafDemand->qtr = $safDemandDetail['qtr'];
        $propSafDemand->due_date = $safDemandDetail['dueDate'];
        $propSafDemand->status = 1;
        $propSafDemand->paid_status = 0;
        $propSafDemand->user_id = auth()->user()->id;
    }

    /**
     * | Get Active Saf Details
     */
    public function tActiveSafDetails()
    {
        return DB::table('prop_active_safs')
            ->select(
                'prop_active_safs.*',
                'prop_active_safs.assessment_type as assessment',
                'w.ward_name as old_ward_no',
                'nw.ward_name as new_ward_no',
                'o.ownership_type',
                'p.property_type',
                'r.road_type as road_type_master',
                'wr.role_name as current_role_name'
            )
            ->join('ulb_ward_masters as w', 'w.id', '=', 'prop_active_safs.ward_mstr_id')
            ->join('wf_roles as wr', 'wr.id', '=', 'prop_active_safs.current_role')
            ->leftJoin('ulb_ward_masters as nw', 'nw.id', '=', 'prop_active_safs.new_ward_mstr_id')
            ->leftJoin('ref_prop_ownership_types as o', 'o.id', '=', 'prop_active_safs.ownership_type_mstr_id')
            ->leftJoin('ref_prop_types as p', 'p.id', '=', 'prop_active_safs.prop_type_mstr_id')
            ->leftJoin('ref_prop_road_types as r', 'r.id', '=', 'prop_active_safs.road_type_mstr_id');
    }

    /**
     * | Trait to Save On Payment Property Rebates
     */
    public function tSavePropRebate($paymentPropRebate, $req, $rebate)
    {
        $paymentPropRebate->saf_id = $req->id;
        $paymentPropRebate->rebate_type_id = $rebate['rebateTypeId'];
        $paymentPropRebate->amount = $rebate['rebateAmount'];
        $paymentPropRebate->description = $rebate['rebateType'];
    }

    /**
     * | Trait to Save On Payment Property Penalties
     */
    public function tSavePropPenalties($paymentPropPenalty, $penaltyTypeId, $demandId, $amount)
    {
        $paymentPropPenalty->saf_demand_id = $demandId;
        $paymentPropPenalty->penalty_type_id = $penaltyTypeId;
        $paymentPropPenalty->amount = $amount;
        $paymentPropPenalty->penalty_date = Carbon::now()->format('Y-m-d');
    }

    /**
     * | to Get Property Details
     */
    public function tPropertyDetails()
    {
        return DB::table('prop_properties')
            ->select('s.*', 's.assessment_type as assessment', 'w.ward_name as old_ward_no', 'o.ownership_type', 'p.property_type', 'r.road_type')
            ->join('prop_safs as s', 's.id', '=', 'prop_properties.saf_id')
            ->join('ulb_ward_masters as w', 'w.id', '=', 's.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as nw', 'nw.id', '=', 's.new_ward_mstr_id')
            ->join('ref_prop_ownership_types as o', 'o.id', '=', 's.ownership_type_mstr_id')
            ->leftJoin('ref_prop_types as p', 'p.id', '=', 's.property_assessment_id')
            ->join('ref_prop_road_types as r', 'r.id', '=', 'prop_properties.road_type_mstr_id')
            ->where('prop_properties.status', 1);
    }

    /**
     * | Read Road Width Types
     */
    public function readRoadWidthType($roadWidth)
    {
        if ($roadWidth <= 0)
            $roadWidthType = 4;
        elseif ($roadWidth > 0 && $roadWidth < 20)
            $roadWidthType = 3;
        elseif ($roadWidth >= 20 && $roadWidth <= 39)
            $roadWidthType = 2;
        elseif ($roadWidth >= 40)
            $roadWidthType = 1;

        return $roadWidthType;
    }

    /**
     * | Get Saf Details
     */
    public function details($req)
    {
        $mPropActiveSaf = new PropActiveSaf();
        $mPropActiveSafOwner = new PropActiveSafsOwner();
        $mActiveSafsFloors = new PropActiveSafsFloor();

        $mPropSaf = new PropSaf();
        $mPropSafOwners = new PropSafsOwner();
        $mPropSafFloors = new PropSafsFloor();
        // Saf Details
        $data = [];
        if ($req->id) {                                       //<------- Search By SAF ID
            $data = $mPropActiveSaf->getActiveSafDtls()      // <------- Model function Active SAF Details
                ->where('prop_active_safs.id', $req->id)
                ->first();
            if (!$data) {
                $data = $mPropSaf->getSafDtls()
                    ->where('prop_safs.id', $req->id)
                    ->first();
            }
        }

        if ($req->safNo) {                                  // <-------- Search By SAF No
            $data = $mPropActiveSaf->getActiveSafDtls()    // <------- Model Function Active SAF Details
                ->where('prop_active_safs.saf_no', $req->safNo)
                ->first();
            if (!$data) {
                $data = $mPropSaf->getSafDtls()
                    ->where('prop_safs.saf_no', $req->safNo)
                    ->first();
            }
        }

        $data = json_decode(json_encode($data), true);
        if (collect($data)->isEmpty())
            throw new Exception("Saf Data Not Available");
        $ownerDetails = $mPropActiveSafOwner->getOwnersBySafId($data['id']);    // Model function to get Owner Details
        if ($ownerDetails->isEmpty())
            $ownerDetails = $mPropSafOwners->getOwnersBySafId($data['id']);
        $data['owners'] = $ownerDetails;

        $floorDetails = $mActiveSafsFloors->getFloorsBySafId($data['id']);      // Model Function to Get Floor Details
        if ($floorDetails->isEmpty())
            $floorDetails = $mPropSafFloors->getFloorsBySafId($data['id']);
        $data['floors'] = $floorDetails;

        return $data;
    }

    /**
     * | Read if the Floor is Trust or Not
     * | @param floors
     */
    public function isPropTrust($floors)
    {
        $trustId = 42;
        $floors = collect($floors);
        $floorUsageTypeIds = $floors->pluck('useType');
        $isTrust = $floorUsageTypeIds->contains($trustId) ? true : false;
        return $isTrust;
    }

    /**
     * | read holding type
     * | @param req is the floors
     */
    public function holdingType($req)
    {
        $useType =  collect($req)->pluck('useType');
        // Check Pure Residential
        $pureResidential = collect($useType)->every(function ($value) {
            return in_array($value ,Config::get('akola-property-constant.RESIDENTIAL_USAGE_TYPE'));
        });
        // check Pure Commercial
        $pureCommercial = collect($useType)->every(function ($value) {
            return !in_array($value,Config::get('akola-property-constant.RESIDENTIAL_USAGE_TYPE'));
        });

        if ($pureResidential == true)
            return "PURE_RESIDENTIAL";
        elseif ($pureCommercial == true)
            return "PURE_COMMERCIAL";
        else
            return "MIX_COMMERCIAL";
    }

    /**
     * | read holding type
     * | @param req is the floors
     */
    public function propHoldingType($req)
    {
        $useType =  collect($req)->pluck('usage_type_mstr_id');
        // Check Pure Residential
        $pureResidential = collect($useType)->every(function ($value) {
            return in_array($value ,Config::get('akola-property-constant.RESIDENTIAL_USAGE_TYPE'));
        });
        // check Pure Commercial
        $pureCommercial = collect($useType)->every(function ($value) {
            return !in_array($value ,Config::get('akola-property-constant.RESIDENTIAL_USAGE_TYPE'));
            // return $value != Config::get('akola-property-constant.RESIDENTIAL_USAGE_TYPE');
        });

        if ($pureResidential == true)
            return "PURE_RESIDENTIAL";
        elseif ($pureCommercial == true)
            return "PURE_COMMERCIAL";
        else
            return "MIX_COMMERCIAL";
    }


    /**
     * | Read Assessment Type and Ulb Workflow Id(2.1)
     */
    public function readAssessUlbWfId($assessmentType, $ulb_id)
    {
        if ($assessmentType == 1)                                                    // New Assessment 
            $workflow_id = Config::get('workflow-constants.SAF_WORKFLOW_ID');


        if ($assessmentType == 2)                                                     // Reassessment
            $workflow_id = Config::get('workflow-constants.SAF_REASSESSMENT_ID');


        if ($assessmentType == 3)                                                    // Mutation
            $workflow_id = Config::get('workflow-constants.SAF_MUTATION_ID');


        if ($assessmentType == 4)                                                     // Bifurcation
            $workflow_id = Config::get('workflow-constants.SAF_BIFURCATION_ID');


        if ($assessmentType == 5)                                                     // Amalgamation
            $workflow_id = Config::get('workflow-constants.SAF_AMALGAMATION_ID');

        return WfWorkflow::where('wf_master_id', $workflow_id)
            ->where('ulb_id', $ulb_id)
            ->first();
    }

    /**
     * =========== Mutasion ProccessFee ========
     *          Created By : Sandeep bara
     *          Date       : 30-11-2023
     *          
     */
    public function readProccessFee($assessmentType, $saleVal = 0, $propertyType = 0,  $transferType = 0, $isLocateOnGovLan = false)
    {
        $propertyTypeCon = flipConstants(Config::get("PropertyConstaint.PROPERTY-TYPE"));
        $transferTypeCon = flipConstants(Config::get("PropertyConstaint.TRANSFER_MODES"));
        if (is_numeric($assessmentType)) {
            $assessmentType = Config::get("PropertyConstaint.ASSESSMENT-TYPE." . $assessmentType);
        }
        $proccessFee = 15000;
        if ($propertyType != $propertyTypeCon["VACANT LAND"] && $transferType == $transferTypeCon["Imla"]) {
            $proccessFee = 2000;
        } elseif ($propertyType != $propertyTypeCon["VACANT LAND"] && !in_array($transferType, [$transferTypeCon["Gift"], $transferTypeCon["Will"], $transferTypeCon["Succession"], $transferTypeCon["Heridity/Will/Gift"]]) && $saleVal <= 1500000 && !$isLocateOnGovLan) {
            $proccessFee = $saleVal * 0.01;
        } elseif ($propertyType != $propertyTypeCon["VACANT LAND"] && !in_array($transferType, [$transferTypeCon["Gift"], $transferTypeCon["Will"], $transferTypeCon["Succession"], $transferTypeCon["Heridity/Will/Gift"]]) && $saleVal > 1500000 && !$isLocateOnGovLan) {
            $proccessFee = 15000;
        } elseif ($propertyType != $propertyTypeCon["VACANT LAND"] && in_array($transferType, [$transferTypeCon["Gift"], $transferTypeCon["Will"], $transferTypeCon["Succession"], $transferTypeCon["Heridity/Will/Gift"]]) && !$isLocateOnGovLan) {
            $proccessFee = 500;
        } elseif ($propertyType != $propertyTypeCon["VACANT LAND"] && !$isLocateOnGovLan) {
            $proccessFee = 2000;
        } elseif ($propertyType == $propertyTypeCon["VACANT LAND"]) {
            $proccessFee = 2000;
        }
        if ($assessmentType != ("Mutation" || "Bifurcation")) {
            $proccessFee = 0;
        }
        return $proccessFee;
    }

    public function addjustVerifySafDtls($safData, $verificationData)
    {
        $safData->prop_type_mstr_id         = $verificationData ? ($verificationData->prop_type_id ?? $safData->prop_type_mstr_id) : $safData->prop_type_mstr_id;
        $safData->ward_mstr_id              = $verificationData ? ($verificationData->ward_id ?? $safData->ward_mstr_id) : $safData->ward_mstr_id;
        $safData->zone_mstr_id              = $verificationData ? ($verificationData->zone_mstr_id ? $verificationData->zone_mstr_id :  $safData->zone_mstr_id) : $safData->zone_mstr_id;
        $safData->area_of_plot              = $verificationData ? ($verificationData->area_of_plot ?? $safData->area_of_plot) : $safData->area_of_plot;
        $safData->is_mobile_tower           = $verificationData ? ($verificationData->has_mobile_tower ?? $safData->is_mobile_tower) : $safData->is_mobile_tower;
        $safData->tower_area                = $verificationData ? ($verificationData->tower_area ?? $safData->tower_area) : $safData->tower_area;
        $safData->tower_installation_date   = $verificationData ? ($verificationData->tower_installation_date ?? $safData->tower_installation_date) : $safData->tower_installation_date;
        $safData->is_hoarding_board         = $verificationData ? ($verificationData->has_hoarding ?? $safData->is_hoarding_board) : $safData->is_hoarding_board;
        $safData->hoarding_area             = $verificationData ? ($verificationData->hoarding_area ?? $safData->hoarding_area) : $safData->hoarding_area;
        $safData->hoarding_installation_date = $verificationData ? ($verificationData->hoarding_installation_date ?? $safData->hoarding_installation_date) : $safData->hoarding_installation_date;

        $safData->is_petrol_pump            = $verificationData ? ($verificationData->is_petrol_pump ?? $safData->is_petrol_pump) : $safData->is_petrol_pump;
        $safData->under_ground_area         = $verificationData ? ($verificationData->underground_area ?? $safData->under_ground_area) : $safData->under_ground_area;
        $safData->petrol_pump_completion_date = $verificationData ? ($verificationData->petrol_pump_completion_date ?? $safData->petrol_pump_completion_date) : $safData->petrol_pump_completion_date;
        $safData->is_water_harvesting       = $verificationData ? ($verificationData->has_water_harvesting ?? $safData->is_water_harvesting) : $safData->is_water_harvesting;
        $safData->rwh_date_from               = $verificationData ? ($verificationData->rwh_date_from ?? $safData->rwh_date_from) : $safData->rwh_date_from;
        $safData->category_id               = $verificationData ? ($verificationData->category_id ?? $safData->category_id) : $safData->category_id;
        return $safData;
    }

    public function addjustVerifySafDtlVal($safData)
    {
        $controller = App::makeWith(ActiveSafController::class, ["iSafRepository" => app(\App\Repository\Property\Interfaces\iSafRepository::class)]);
        $response = $controller->masterSaf(new Request);
        if (!$response->original["status"]) {
            throw new Exception("Master Data Not Found");
        }
        $data = $response->original["data"];
        $property_type = $data["property_type"];
        $zone_master = $data["zone"];
        $ward_master = $data["ward_master"];
        $categories = $data["categories"];
        $safData->property_type     = (collect($property_type)->where("id", $safData->prop_type_mstr_id)->first())->property_type ?? $safData->property_type;
        $safData->old_ward_no       = (collect($ward_master)->where("id", $safData->ward_mstr_id)->first())->ward_name ?? $safData->old_ward_no;
        $safData->zone              = (collect($zone_master)->where("id", $safData->zone_mstr_id)->first())->zone_name ?? $safData->zone;

        $safData->category = (collect($categories)->where("id", $safData->category_id)->first())->category ?? $safData->category;
        return $safData;
    }

    public function addjustVerifyFloorDtls($floor, $verifyFloor)
    {
        $floor->floor_mstr_id               = $verifyFloor ? ($verifyFloor->floor_mstr_id ?? $floor->floor_mstr_id) : $floor->floor_mstr_id;
        $floor->usage_type_mstr_id          = $verifyFloor ? ($verifyFloor->usage_type_id ?? $floor->usage_type_mstr_id) : $floor->usage_type_mstr_id;
        $floor->const_type_mstr_id          = $verifyFloor ? ($verifyFloor->construction_type_id ?? $floor->const_type_mstr_id) : $floor->const_type_mstr_id;
        $floor->occupancy_type_mstr_id      = $verifyFloor ? ($verifyFloor->occupancy_type_id ?? $floor->occupancy_type_mstr_id) : $floor->occupancy_type_mstr_id;
        $floor->builtup_area                = $verifyFloor ? ($verifyFloor->builtup_area ?? $floor->builtup_area) : $floor->builtup_area;
        $floor->date_from                   = $verifyFloor ? ($verifyFloor->date_from ?? $floor->date_from) : $floor->date_from;
        $floor->date_upto                   = $verifyFloor ? ($verifyFloor->date_to ?? null) : $floor->date_upto;
        $floor->carpet_area                 = $verifyFloor ? ($verifyFloor->carpet_area ?? $floor->carpet_area) : $floor->carpet_area;
        return $floor;
    }

    public function addjustVerifyFloorDtlVal($floor)
    {
        $controller = App::makeWith(ActiveSafController::class, ["iSafRepository" => app(\App\Repository\Property\Interfaces\iSafRepository::class)]);
        $response = $controller->masterSaf(new Request);
        if (!$response->original["status"]) {
            throw new Exception("Master Data Not Found");
        }
        $data = $response->original["data"];
        $floor_type = $data["floor_type"];
        $occupancy_type = $data["occupancy_type"];
        $usage_type = $data["usage_type"];
        $construction_type = $data["construction_type"];

        $floor->floor_name          = (collect($floor_type)->where("id", $floor->floor_mstr_id)->first())->floor_name ?? $floor->floor_name;
        $floor->usage_type          = (collect($usage_type)->where("id", $floor->usage_type_mstr_id)->first())->usage_type ?? $floor->usage_type;
        $floor->construction_type   = (collect($construction_type)->where("id", $floor->const_type_mstr_id)->first())->construction_type ?? $floor->construction_type;
        $floor->occupancy_type      = (collect($occupancy_type)->where("id", $floor->occupancy_type_mstr_id)->first())->occupancy_type ?? $floor->occupancy_type;

        return $floor;
    }
}
