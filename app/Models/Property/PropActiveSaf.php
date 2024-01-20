<?php

namespace App\Models\Property;

use App\MicroServices\IdGeneration;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class  PropActiveSaf extends Model
{
    use HasFactory;

    protected $guarded = [];
    // Store
    public function store($req)
    {
        $reqs = [
            'has_previous_holding_no' => $req->hasPreviousHoldingNo,
            'previous_holding_id' => $req->previousHoldingId,
            'previous_ward_mstr_id' => $req->previousWard,
            'is_owner_changed' => $req->isOwnerChanged,
            'transfer_mode_mstr_id' => $req->transferModeId ?? null,
            'holding_no' => $req->holdingNo,
            'ward_mstr_id' => $req->ward,
            'ownership_type_mstr_id' => $req->ownershipType,
            'prop_type_mstr_id' => $req->propertyType,
            'appartment_name' => $req->appartmentName,
            'flat_registry_date' => $req->flatRegistryDate,
            'zone_mstr_id' => $req->zone,
            'no_electric_connection' => $req->electricityConnection,
            'elect_consumer_no' => $req->electricityCustNo,
            'elect_acc_no' => $req->electricityAccNo,
            'elect_bind_book_no' => $req->electricityBindBookNo,
            'elect_cons_category' => $req->electricityConsCategory,
            'building_plan_approval_no' => $req->buildingPlanApprovalNo,
            'building_plan_approval_date' => $req->buildingPlanApprovalDate,
            'water_conn_no' => $req->waterConnNo,
            'water_conn_date' => $req->waterConnDate,
            'khata_no' => $req->khataNo,
            'plot_no' => $req->plotNo,
            'village_mauja_name' => $req->villageMaujaName,
            'road_type_mstr_id' => $req->roadWidthType,
            'area_of_plot' => isset($req->bifurcatedPlot) ? $req->bifurcatedPlot : $req->areaOfPlot,
            'prop_address' => $req->propAddress,
            'prop_city' => $req->propCity,
            'prop_dist' => $req->propDist,
            'prop_pin_code' => $req->propPinCode,
            'is_corr_add_differ' => $req->isCorrAddDiffer,
            'corr_address' => $req->corrAddress,
            'corr_city' => $req->corrCity,
            'corr_dist' => $req->corrDist,
            'corr_pin_code' => $req->corrPinCode,
            'holding_type' => $req->holdingType,
            'is_mobile_tower' => $req->isMobileTower,
            'tower_area' => $req->mobileTower['area'] ?? null,
            'tower_installation_date' => $req->mobileTower['dateFrom'] ?? null,

            'is_hoarding_board' => $req->isHoardingBoard,
            'hoarding_area' => $req->hoardingBoard['area'] ?? null,
            'hoarding_installation_date' => $req->hoardingBoard['dateFrom'] ?? null,


            'is_petrol_pump' => $req->isPetrolPump,
            'under_ground_area' => $req->petrolPump['area'] ?? null,
            'petrol_pump_completion_date' => $req->petrolPump['dateFrom'] ?? null,

            'is_water_harvesting' => $req->isWaterHarvesting,
            'rwh_date_from' => ($req->isWaterHarvesting == 1) ? $req->rwhDateFrom : null,
            'land_occupation_date' => $req->landOccupationDate ? $req->landOccupationDate : $req->dateOfPurchase,
            'doc_verify_cancel_remarks' => $req->docVerifyCancelRemark,
            'application_date' =>  Carbon::now()->format('Y-m-d'),
            'assessment_type' => $req->assessmentType,
            'saf_distributed_dtl_id' => $req->safDistributedDtl,
            'prop_dtl_id' => $req->propDtl,
            'prop_state' => $req->propState,
            'corr_state' => $req->corrState,
            'holding_type' => $req->holdingType,
            'ip_address' => getClientIpAddress(),
            'new_ward_mstr_id' => $req->newWard,
            'percentage_of_property_transfer' => $req->percOfPropertyTransfer,
            'apartment_details_id' => $req->apartmentId,
            'applicant_name' => Str::upper(collect($req->owner)->first()['ownerName']),
            'road_width' => $req->roadType,
            'user_id' => $req->userId,
            'workflow_id' => $req->workflowId,
            'ulb_id' => $req->ulbId,
            'current_role' => $req->initiatorRoleId,
            'initiator_role_id' => $req->initiatorRoleId,
            'finisher_role_id' => $req->finisherRoleId,
            'citizen_id' => $req->citizenId ?? null,

            'building_name' => $req->buildingName,
            'street_name' => $req->streetName,
            'location' => $req->location,
            'landmark' => $req->landmark,
            'is_gb_saf' => isset($req->isGBSaf) ? $req->isGBSaf : false,
            'is_trust' => $req->isTrust ?? false,
            'trust_type' => $req->trustType ?? null,
            'category_id' => $req->category,
            'sale_value' => (in_array($req->assessmentType, ['Mutation', 'Bifurcation'])) ? ($req->saleValue ?? null) : null,
            'proccess_fee' => (in_array($req->assessmentType, ['Mutation', 'Bifurcation'])) ? ($req->proccessFee ?? 0) : 0,
            'proccess_fee_paid' => (($req->proccessFee ?? 0) > 0 && (in_array($req->assessmentType, ['Mutation', 'Bifurcation']))) ? 0 : 1,
            "property_no" => ($req->propertyNo ?? null),
            "bifurcated_from_plot_area" => isset($req->bifurcatedPlot) ? $req->areaOfPlot : null,
        ];
        $propActiveSafs = PropActiveSaf::create($reqs);                 // SAF No is Created Using Observer
        return response()->json([
            'safId' => $propActiveSafs->id,
            'safNo' => $propActiveSafs->saf_no,
            'workflow_id' => $propActiveSafs->workflow_id,
            'current_role' => $propActiveSafs->current_role,
            'ulb_id' => $propActiveSafs->ulb_id
        ]);
    }

    /**
     * | Store GB Saf
     */
    public function storeGBSaf($req)
    {
        $propActiveSafs = PropActiveSaf::create($req);
        return response()->json([
            'safId' => $propActiveSafs->id,
            'safNo' => $propActiveSafs->saf_no,
            'workflow_id' => $propActiveSafs->workflow_id,
            'current_role' => $propActiveSafs->current_role,
            'ulb_id' => $propActiveSafs->ulb_id,
        ]);
    }

    // Update
    public function edit($req)
    {
        $saf = PropActiveSaf::findOrFail($req->id);

        $reqs = [
            'previous_ward_mstr_id' => $req->previousWard,
            'no_electric_connection' => $req->electricityConnection,
            'elect_consumer_no' => $req->electricityCustNo,
            'elect_acc_no' => $req->electricityAccNo,
            'elect_bind_book_no' => $req->electricityBindBookNo,
            'elect_cons_category' => $req->electricityConsCategory,
            'building_plan_approval_no' => $req->buildingPlanApprovalNo,
            'building_plan_approval_date' => $req->buildingPlanApprovalDate,
            'water_conn_no' => $req->waterConnNo,
            'water_conn_date' => $req->waterConnDate,
            'khata_no' => $req->khataNo,
            'plot_no' => $req->plotNo,
            'village_mauja_name' => $req->villageMaujaName,
            'prop_address' => $req->propAddress,
            'prop_city' => $req->propCity,
            'prop_dist' => $req->propDist,
            'prop_pin_code' => $req->propPinCode,
            'is_corr_add_differ' => $req->isCorrAddDiffer,
            'corr_address' => $req->corrAddress,
            'corr_city' => $req->corrCity,
            'corr_dist' => $req->corrDist,
            'corr_pin_code' => $req->corrPinCode,

            'prop_state' => $req->propState,
            'corr_state' => $req->corrState,
            'new_ward_mstr_id' => $req->newWard,
            'building_name' => $req->buildingName,
            'street_name' => $req->streetName,
            'location' => $req->location,
            'landmark' => $req->landmark
        ];

        return $saf->update($reqs);
    }

    // Get Active SAF Details
    public function getActiveSafDtls()
    {
        return DB::table('prop_active_safs')
            ->select(
                'prop_active_safs.*',
                DB::raw(
                    "case when prop_active_safs.workflow_id=202 then 'Direct Mutation'
                          else assessment_type end
                          as assessment_type"
                ),
                DB::raw("REPLACE(prop_active_safs.holding_type, '_', ' ') AS holding_type"),
                'w.ward_name as old_ward_no',
                'nw.ward_name as new_ward_no',
                'o.ownership_type',
                'p.property_type',
                'r.road_type as road_type_master',
                'wr.role_name as current_role_name',
                't.transfer_mode',
                'a.apt_code as apartment_code',
                'a.apartment_address',
                'a.no_of_block',
                'a.apartment_name',
                'building_type',
                'prop_usage_type',
                'zone_masters.zone_name as zone',
                'cat.category',
                'cat.description as category_description',
                'bifurcated_from_plot_area',
            )
            ->leftJoin('ulb_ward_masters as w', 'w.id', '=', 'prop_active_safs.ward_mstr_id')
            ->leftJoin('wf_roles as wr', 'wr.id', '=', 'prop_active_safs.current_role')
            ->leftJoin('ulb_ward_masters as nw', 'nw.id', '=', 'prop_active_safs.new_ward_mstr_id')
            ->leftJoin('ref_prop_ownership_types as o', 'o.id', '=', 'prop_active_safs.ownership_type_mstr_id')
            ->leftJoin('ref_prop_types as p', 'p.id', '=', 'prop_active_safs.prop_type_mstr_id')
            ->leftJoin('ref_prop_road_types as r', 'r.id', '=', 'prop_active_safs.road_type_mstr_id')
            ->leftJoin('ref_prop_transfer_modes as t', 't.id', '=', 'prop_active_safs.transfer_mode_mstr_id')
            ->leftJoin('prop_apartment_dtls as a', 'a.id', '=', 'prop_active_safs.apartment_details_id')
            ->leftJoin('zone_masters', 'zone_masters.id', 'prop_active_safs.zone_mstr_id')
            ->leftJoin('ref_prop_gbbuildingusagetypes as gbu', 'gbu.id', 'prop_active_safs.gb_usage_types')
            ->leftJoin('ref_prop_gbpropusagetypes as gbp', 'gbp.id', 'prop_active_safs.gb_prop_usage_types')
            ->join('ref_prop_categories as cat', 'cat.id', '=', 'prop_active_safs.category_id');
    }

    /**
     * |-------------------------- safs list whose Holding are not craeted -----------------------------------------------|
     * | @var safDetails
     */
    public function allNonHoldingSaf()
    {
        try {
            $allSafList = PropActiveSaf::select(
                'id AS SafId'
            )
                ->get();
            return responseMsg(true, "Saf List!", $allSafList);
        } catch (Exception $error) {
            return responseMsg(false, "ERROR!", $error->getMessage());
        }
    }


    /**
     * |-------------------------- Details of the Mutation accordind to ID -----------------------------------------------|
     * | @param request
     * | @var mutation
     */
    public function allMutation($request)
    {
        $mutation = PropActiveSaf::where('id', $request->id)
            ->where('property_assessment_id', 3)
            ->get();
        return $mutation;
    }


    /**
     * |-------------------------- Details of the ReAssisments according to ID  -----------------------------------------------|
     * | @param request
     * | @var reAssisment
     */
    public function allReAssisment($request)
    {
        $reAssisment = PropActiveSaf::where('id', $request->id)
            ->where('property_assessment_id', 2)
            ->get();
        return $reAssisment;
    }


    /**
     * |-------------------------- Details of the NewAssisment according to ID  -----------------------------------------------|
     * | @var safDetails
     */
    public function allNewAssisment($request)
    {
        $newAssisment = PropActiveSaf::where('id', $request->id)
            ->where('property_assessment_id', 1)
            ->get();
        return $newAssisment;
    }


    /**
     * |-------------------------- safId According to saf no -----------------------------------------------|
     */
    public function getSafId($safNo)
    {
        return PropActiveSaf::where('saf_no', $safNo)
            ->select('id', 'saf_no')
            ->first();
    }

    /**
     * | Get Saf Details by Saf No
     * | @param SafNo
     */
    public function getSafDtlsBySafNo($safNo)
    {
        return DB::table('prop_active_safs as s')
            ->where('s.saf_no', strtoupper($safNo))
            ->select(
                's.id',
                DB::raw("'active' as status"),
                's.saf_no',
                's.ward_mstr_id',
                's.new_ward_mstr_id',
                's.elect_consumer_no',
                's.elect_acc_no',
                's.elect_bind_book_no',
                's.elect_cons_category',
                's.prop_address',
                's.corr_address',
                's.prop_pin_code',
                's.corr_pin_code',
                's.assessment_type',
                's.applicant_name',
                DB::raw("TO_CHAR(s.application_date, 'DD-MM-YYYY') as application_date"),
                's.area_of_plot as total_area_in_decimal',
                's.prop_type_mstr_id',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
                'p.property_type',
                'doc_upload_status',
                'payment_status',
                DB::raw(
                    "case when payment_status!=1 then 'Payment Not Done'
                          else role_name end
                          as current_role
                    "
                ),
                's.user_id',
                's.citizen_id',
                DB::raw(
                    "case when s.user_id is not null then 'TC/TL/JSK' when 
                    s.citizen_id is not null then 'Citizen' end as appliedBy
                "
                ),
            )
            ->leftjoin('wf_roles', 'wf_roles.id', 's.current_role')
            ->join('ulb_ward_masters as u', 's.ward_mstr_id', '=', 'u.id')
            ->join('ref_prop_types as p', 'p.id', '=', 's.prop_type_mstr_id')
            ->leftJoin('ulb_ward_masters as u1', 's.new_ward_mstr_id', '=', 'u1.id')
            ->first();
    }

    /**
     * 
     */

    // Get SAF No
    public function getSafNo($safId)
    {
        return PropActiveSaf::select('*')
            ->where('id', $safId)
            ->first();
    }

    /**
     * | Get late Assessment by SAF id
     */
    public function getLateAssessBySafId($safId)
    {
        return PropActiveSaf::select('late_assess_penalty')
            ->where('id', $safId)
            ->first();
    }

    /**
     * | Enable Field Verification Status
     */
    public function verifyFieldStatus($safId)
    {
        $activeSaf = PropActiveSaf::find($safId);
        if (!$activeSaf)
            throw new Exception("Application Not Found");
        $activeSaf->is_field_verified = true;
        $activeSaf->save();
    }

    /**
     * | Enable Agency Field Verification Status
     */
    public function verifyAgencyFieldStatus($safId)
    {
        $activeSaf = PropActiveSaf::find($safId);
        if (!$activeSaf)
            throw new Exception("Application Not Found");
        $activeSaf->is_agency_verified = true;
        $activeSaf->save();
    }

    /**
     * | Get Saf Details by Saf No
     * | @param SafNo
     */
    public function getSafDtlBySafUlbNo($safNo, $ulbId)
    {
        return DB::table('prop_active_safs as s')
            ->where('s.saf_no', $safNo)
            ->where('s.ulb_id', $ulbId)
            ->select(
                's.id',
                's.saf_no',
                's.ward_mstr_id',
                's.new_ward_mstr_id',
                's.elect_consumer_no',
                's.elect_acc_no',
                's.elect_bind_book_no',
                's.elect_cons_category',
                's.prop_address',
                's.corr_address',
                's.prop_pin_code',
                's.corr_pin_code',
                's.area_of_plot as total_area_in_desimal',
                's.apartment_details_id',
                's.prop_type_mstr_id',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
            )
            ->join('ulb_ward_masters as u', 's.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 's.new_ward_mstr_id', '=', 'u1.id')
            ->where('s.status', 1)
            ->first();
    }

    /**
     * | Get Saf details by user Id and ulbId
     */
    public function getSafByIdUlb($request)
    {
        PropActiveSaf::select(
            'saf_no',
        )
            ->where('ulb_id', $request->ulbId)
            ->where('user_id', auth()->user()->id)
            ->get();
    }

    /**
     * | Serch Saf 
     */
    public function searchSafDtlsBySafNo($ulbId)
    {
        return DB::table('prop_active_safs as s')
            ->select(
                's.id',
                's.saf_no',
                's.ward_mstr_id as wardId',
                's.new_ward_mstr_id',
                's.prop_address as address',
                's.corr_address',
                's.prop_pin_code',
                's.corr_pin_code',
                'prop_active_safs_owners.owner_name as ownerName',
                'prop_active_safs_owners.mobile_no as mobileNo',
                'prop_active_safs_owners.email',
                'ref_prop_types.property_type as propertyType'
            )
            ->join('prop_active_safs_owners', 'prop_active_safs_owners.saf_id', '=', 's.id')
            ->join('ref_prop_types', 'ref_prop_types.id', '=', 's.prop_type_mstr_id')
            ->where('ulb_id', $ulbId);
    }

    /**
     * | Saerch collective saf
     */
    public function searchCollectiveSaf($safList)
    {
        return PropActiveSaf::whereIn('saf_no', $safList)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Search Saf Details By Cluster Id
     */
    public function getSafByCluster($clusterId)
    {
        return  PropActiveSaf::join()
            ->join('ref_prop_types', 'ref_prop_types.id', '=', 'prop_active_safs.prop_type_mstr_id')
            ->select(
                'prop_active_safs.id',
                'prop_active_safs.saf_no',
                'prop_active_safs.ward_mstr_id as wardId',
                'prop_active_safs.',
                'prop_active_safs.',
                'prop_active_safs.',
                'prop_active_safs_owners.owner_name as ownerName',
                'prop_active_safs_owners.mobile_no as mobileNo',
                'prop_active_safs_owners.email',
                'ref_prop_types.property_type as propertyType'
            )
            ->where('prop_active_safs.cluster_id', $clusterId)
            ->where('prop_active_safs.status', 1)
            ->where('ref_prop_types.status', 1);
    }

    /**
     * | Get Saf Details
     */
    public function safByCluster($clusterId)
    {
        return  DB::table('prop_active_safs')
            ->leftJoin('prop_active_safs_owners as o', 'o.saf_id', '=', 'prop_active_safs.id')
            ->join('ref_prop_types', 'ref_prop_types.id', '=', 'prop_active_safs.prop_type_mstr_id')
            ->select(
                'prop_active_safs.saf_no',
                'prop_active_safs.id',
                'prop_active_safs.ward_mstr_id as ward_id',
                DB::raw("string_agg(o.mobile_no::VARCHAR,',') as mobileNo"),
                DB::raw("string_agg(o.owner_name,',') as ownerName"),
                'ref_prop_types.property_type as propertyType',
                'prop_active_safs.cluster_id',
                'prop_active_safs.prop_address as address',
                'prop_active_safs.ulb_id',
                'prop_active_safs.new_ward_mstr_id as new_ward_id'
            )
            ->where('prop_active_safs.cluster_id', $clusterId)
            ->where('ref_prop_types.status', 1)
            ->where('prop_active_safs.status', 1)
            ->where('o.status', 1)
            ->groupBy('prop_active_safs.id', 'ref_prop_types.property_type')
            ->get();
    }

    /**
     * | get Safs By Cluster Id
     */
    public function getSafsByClusterId($clusterId)
    {
        return PropActiveSaf::where('cluster_id', $clusterId)
            ->get();
    }

    /**
     * | Edit citizen safs
     */
    public function safEdit($req, $mPropActiveSaf, $citizenId)
    {
        $reqs = [
            'previous_ward_mstr_id' => $req->previousWard,
            'transfer_mode_mstr_id' => $req->transferModeId ?? null,
            'ward_mstr_id' => $req->ward,
            'ownership_type_mstr_id' => $req->ownershipType,
            'prop_type_mstr_id' => $req->propertyType,
            'appartment_name' => $req->apartmentName,
            'flat_registry_date' => $req->flatRegistryDate,
            'zone_mstr_id' => $req->zone,
            'no_electric_connection' => $req->electricityConnection,
            'elect_consumer_no' => $req->electricityCustNo,
            'elect_acc_no' => $req->electricityAccNo,
            'elect_bind_book_no' => $req->electricityBindBookNo,
            'elect_cons_category' => $req->electricityConsCategory,
            'building_plan_approval_no' => $req->buildingPlanApprovalNo,
            'building_plan_approval_date' => $req->buildingPlanApprovalDate,
            'water_conn_no' => $req->waterConnNo,
            'water_conn_date' => $req->waterConnDate,
            'khata_no' => $req->khataNo,
            'plot_no' => $req->plotNo,
            'village_mauja_name' => $req->villageMaujaName,
            'road_type_mstr_id' => $req->roadWidthType,
            'area_of_plot' => $req->areaOfPlot,
            'prop_address' => $req->propAddress,
            'prop_city' => $req->propCity,
            'prop_dist' => $req->propDist,
            'prop_pin_code' => $req->propPinCode,
            'is_corr_add_differ' => $req->isCorrAddDiffer,
            'corr_address' => $req->corrAddress,
            'corr_city' => $req->corrCity,
            'corr_dist' => $req->corrDist,
            'corr_pin_code' => $req->corrPinCode,
            'is_mobile_tower' => $req->isMobileTower,
            'tower_area' => $req->mobileTower['area'],
            'tower_installation_date' => $req->mobileTower['dateFrom'],

            'is_hoarding_board' => $req->isHoardingBoard,
            'hoarding_area' => $req->hoardingBoard['area'],
            'hoarding_installation_date' => $req->hoardingBoard['dateFrom'],


            'is_petrol_pump' => $req->isPetrolPump,
            'under_ground_area' => $req->petrolPump['area'],
            'petrol_pump_completion_date' => $req->petrolPump['dateFrom'],

            'is_water_harvesting' => $req->isWaterHarvesting,
            'land_occupation_date' => $req->dateOfPurchase,
            'doc_verify_cancel_remarks' => $req->docVerifyCancelRemark,
            'application_date' =>  Carbon::now()->format('Y-m-d'),
            'saf_distributed_dtl_id' => $req->safDistributedDtl,
            'prop_state' => $req->propState,
            'corr_state' => $req->corrState,
            'holding_type' => $req->holdingType,
            'ip_address' => getClientIpAddress(),
            'new_ward_mstr_id' => $req->newWard,
            'percentage_of_property_transfer' => $req->percOfPropertyTransfer,
            'apartment_details_id' => $req->apartmentId,
            'applicant_name' => collect($req->owner)->first()['ownerName'],
            'road_width' => $req->roadType,
            'user_id' => $req->userId,
            'citizen_id' => $citizenId,
        ];
        return $mPropActiveSaf->update($reqs);
    }

    /**
     * | Recent Applications
     */
    public function recentApplication($userId)
    {
        $data = PropActiveSaf::select(
            'prop_active_safs.id',
            'saf_no as applicationNo',
            'application_date as applyDate',
            'assessment_type as assessmentType',
            DB::raw("string_agg(owner_name,',') as applicantName"),
        )
            ->join('prop_active_safs_owners', 'prop_active_safs_owners.saf_id', 'prop_active_safs.id')
            ->where('prop_active_safs.user_id', $userId)
            ->orderBydesc('prop_active_safs.id')
            ->groupBy('saf_no', 'application_date', 'assessment_type', 'prop_active_safs.id')
            ->take(10)
            ->get();

        $application = collect($data)->map(function ($value) {
            $value['applyDate'] = (Carbon::parse($value['applyDate']))->format('d-m-Y');
            return $value;
        });
        return $application;
    }


    public function todayAppliedApplications($userId)
    {
        $date = Carbon::now();
        return PropActiveSaf::select('id')
            ->where('prop_active_safs.user_id', $userId)
            ->where('application_date', $date);
        // ->get();
    }

    /**
     * | Today Received Appklication
     */
    public function todayReceivedApplication($currentRole, $ulbId)
    {
        $date = Carbon::now()->format('Y-m-d');
        // $date =  '2023-01-16';
        return PropActiveSaf::select(
            'saf_no as applicationNo',
            'application_date as applyDate',
            'assessment_type as assessmentType',
            DB::raw("string_agg(owner_name,',') as applicantName"),
        )

            ->join('prop_active_safs_owners', 'prop_active_safs_owners.saf_id', 'prop_active_safs.id')
            ->join('workflow_tracks', 'workflow_tracks.ref_table_id_value', 'prop_active_safs.id')
            ->where('workflow_tracks.receiver_role_id', $currentRole)
            ->where('workflow_tracks.ulb_id', $ulbId)
            ->where('ref_table_dot_id', 'prop_active_safs.id')
            // ->where('track_date' . '::' . 'date', $date)
            ->whereRaw("date(track_date) = '$date'")
            ->orderBydesc('prop_active_safs.id')
            ->groupBy('saf_no', 'application_date', 'assessment_type', 'prop_active_safs.id');
    }

    /**
     * | GB SAF Details
     */
    public function getGbSaf($workflowIds)
    {
        $data = DB::table('prop_active_safs')
            ->join('ref_prop_gbpropusagetypes as p', 'p.id', '=', 'prop_active_safs.gb_usage_types')
            ->join('ref_prop_gbbuildingusagetypes as q', 'q.id', '=', 'prop_active_safs.gb_prop_usage_types')
            ->leftjoin('prop_active_safgbofficers as gbo', 'gbo.saf_id', 'prop_active_safs.id')
            ->join('ulb_ward_masters as ward', 'ward.id', '=', 'prop_active_safs.ward_mstr_id')
            ->join('ref_prop_road_types as r', 'r.id', 'prop_active_safs.road_type_mstr_id')
            ->select(
                'prop_active_safs.id',
                'prop_active_safs.workflow_id',
                'prop_active_safs.payment_status',
                'prop_active_safs.saf_no',
                'prop_active_safs.ward_mstr_id',
                'ward.ward_name as ward_no',
                'prop_active_safs.assessment_type as assessment',
                DB::raw("TO_CHAR(prop_active_safs.application_date, 'DD-MM-YYYY') as apply_date"),
                'prop_active_safs.parked',
                'prop_active_safs.prop_address',
                'gb_office_name',
                'gb_usage_types',
                'gb_prop_usage_types',
                'prop_usage_type',
                'building_type',
                'road_type_mstr_id',
                'road_type',
                'area_of_plot',
                'officer_name',
                'designation',
                'mobile_no'
            )
            ->whereIn('workflow_id', $workflowIds)
            ->where('is_gb_saf', true);
        return $data;
    }


    /**
     * | 
     */
    public function getpropLatLongDetails($wardId)
    {
        return PropActiveSaf::select(
            'prop_active_safs.id as saf_id',
            'prop_saf_geotag_uploads.id as geo_id',
            'prop_active_safs.holding_no',
            'prop_active_safs.prop_address',
            'prop_saf_geotag_uploads.latitude',
            'prop_saf_geotag_uploads.longitude',
            'prop_saf_geotag_uploads.created_at',
            DB::raw("concat(relative_path,'/',image_path) as doc_path"),
        )
            ->leftjoin('prop_saf_geotag_uploads', 'prop_saf_geotag_uploads.saf_id', '=', 'prop_active_safs.id')
            ->where('prop_active_safs.ward_mstr_id', $wardId)
            ->where('prop_active_safs.holding_no', '!=', null)
            ->orderByDesc('prop_active_safs.id')
            ->skip(0)
            ->take(200)
            ->get();
    }

    /**
     * | Get citizen safs
     */
    public function getCitizenSafs($citizenId, $ulbId)
    {
        return PropActiveSaf::select('id', 'saf_no', 'citizen_id')
            ->where('citizen_id', $citizenId)
            ->where('ulb_id', $ulbId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * | Get GB SAf details by saf No
     */
    public function getGbSafDtlsBySafNo($safNo)
    {
        return DB::table('prop_active_safs as s')
            ->where('s.saf_no', strtoupper($safNo))
            ->select(
                's.id',
                DB::raw("'active' as status"),
                's.saf_no',
                's.ward_mstr_id',
                's.new_ward_mstr_id',
                's.prop_address',
                's.prop_pin_code',
                's.assessment_type',
                's.applicant_name',
                // 's.application_date',
                DB::raw("TO_CHAR(s.application_date, 'DD-MM-YYYY') as application_date"),
                's.area_of_plot as total_area_in_decimal',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
                'doc_upload_status',
                'payment_status',
                DB::raw(
                    "case when payment_status!=1 then 'Payment Not Done'
                          else role_name end
                          as current_role
                    "
                ),
                's.user_id',
                's.citizen_id',
                'gb_office_name',
                'building_type',
                DB::raw(
                    "case when s.user_id is not null then 'TC/TL/JSK' when 
                    s.citizen_id is not null then 'Citizen' end as appliedBy
                "
                ),
            )
            ->join('wf_roles', 'wf_roles.id', 's.current_role')
            ->leftjoin('ref_prop_gbpropusagetypes as p', 'p.id', '=', 's.gb_usage_types')
            ->leftjoin('ref_prop_gbbuildingusagetypes as q', 'q.id', '=', 's.gb_prop_usage_types')
            ->join('ulb_ward_masters as u', 's.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 's.new_ward_mstr_id', '=', 'u1.id')
            ->first();
    }

    /**
     * | Save Cluster in saf
     */
    public function saveClusterInSaf($safNoList, $clusterId)
    {
        PropActiveSaf::whereIn('saf_no', $safNoList)
            ->update([
                'cluster_id' => $clusterId
            ]);
    }

    /**
     * | Search safs
     */
    public function searchSafs()
    {
        return PropActiveSaf::select(
            'prop_active_safs.id',
            DB::raw("'active' as status"),
            'prop_active_safs.saf_no',
            'prop_active_safs.assessment_type',
            DB::raw(
                "case when prop_active_safs.payment_status = 0 then 'Payment Not Done'
                      when prop_active_safs.payment_status = 2 then 'Cheque Payment Verification Pending'
                    else role_name end
                as current_role
                "
            ),
            'role_name as currentRole',
            'u.ward_name as old_ward_no',
            'uu.ward_name as new_ward_no',
            'prop_address',
            DB::raw(
                "case when prop_active_safs.user_id is not null then 'TC/TL/JSK' when 
                prop_active_safs.citizen_id is not null then 'Citizen' end as appliedBy"
            ),
            DB::raw("string_agg(so.mobile_no::VARCHAR,',') as mobile_no"),
            DB::raw("string_agg(so.owner_name,',') as owner_name"),
        )
            ->leftjoin('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
            ->join('ulb_ward_masters as u', 'u.id', 'prop_active_safs.ward_mstr_id')
            ->leftjoin('ulb_ward_masters as uu', 'uu.id', 'prop_active_safs.new_ward_mstr_id')
            ->join('prop_active_safs_owners as so', 'so.saf_id', 'prop_active_safs.id');
    }

    /**
     * | Search Gb Saf
     */
    public function searchGbSafs()
    {
        return PropActiveSaf::select(
            'prop_active_safs.id',
            DB::raw("'active' as status"),
            'prop_active_safs.saf_no',
            'prop_active_safs.assessment_type',
            DB::raw(
                "case when prop_active_safs.payment_status!=1 then 'Payment Not Done'
                      else role_name end
                      as current_role
                "
            ),
            'role_name as currentRole',
            'ward_name',
            'prop_address',
            'gbo.officer_name',
            'gbo.mobile_no'
        )
            ->leftjoin('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_active_safs.ward_mstr_id')
            ->join('prop_active_safgbofficers as gbo', 'gbo.saf_id', 'prop_active_safs.id');
    }

    /**
     * | saf Basic Edit the water connection
     */
    public function updateWaterConnection($safIds, $consumerNo)
    {
        $nPropActiveSaf = PropActiveSaf::whereIn('id', $safIds);
        $reqs = [
            "water_conn_no" => $consumerNo,
            "water_conn_date" => Carbon::now(),
        ];
        $nPropActiveSaf->update($reqs);
    }

    /**
     * | 
     */
    public function getSafByApartmentId($apartmentId)
    {
        return PropActiveSaf::select(
            'prop_active_safs.*',
            'ulb_ward_masters.ward_name AS old_ward_no',
            'u.ward_name AS new_ward_no'
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'prop_active_safs.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'prop_active_safs.new_ward_mstr_id')
            ->where('prop_active_safs.apartment_details_id', $apartmentId)
            ->where('prop_active_safs.status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get Appartment Details 
     * | @param 
     */
    public function getActiveSafByApartmentId($apartmentId)
    {
        return PropActiveSaf::where('prop_active_safs.apartment_details_id', $apartmentId)
            ->where('prop_active_safs.status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Count Previous Holdings
     */
    public function countPreviousHoldings($previousHoldingId)
    {
        return PropActiveSaf::where('previous_holding_id', $previousHoldingId)
            ->count();
    }


    /**
     * | Get Query Active Saf by id
     */
    public function getQuerySafById($applicationId)
    {
        return PropActiveSaf::query()
            ->where('id', $applicationId)
            ->first();
    }

    /**
     * | 
     */
    public function toBePropertyBySafId($safId)
    {
        return PropActiveSaf::where('id', $safId)
            ->select(
                'saf_no',
                'ulb_id',
                'cluster_id',
                'holding_no',
                'applicant_name',
                'ward_mstr_id',
                'ownership_type_mstr_id',
                'prop_type_mstr_id',
                'appartment_name',
                'no_electric_connection',
                'elect_consumer_no',
                'elect_acc_no',
                'elect_bind_book_no',
                'elect_cons_category',
                'building_plan_approval_no',
                'building_plan_approval_date',
                'water_conn_no',
                'water_conn_date',
                'khata_no',
                'plot_no',
                'village_mauja_name',
                'road_type_mstr_id',
                'road_width',
                'area_of_plot',
                'prop_address',
                'prop_city',
                'prop_dist',
                'prop_pin_code',
                'prop_state',
                'corr_address',
                'corr_city',
                'corr_dist',
                'corr_pin_code',
                'corr_state',
                'is_mobile_tower',
                'tower_area',
                'tower_installation_date',
                'is_hoarding_board',
                'hoarding_area',
                'hoarding_installation_date',
                'is_petrol_pump',
                'under_ground_area',
                'petrol_pump_completion_date',
                'is_water_harvesting',
                'land_occupation_date',
                'new_ward_mstr_id',
                'zone_mstr_id',
                'flat_registry_date',
                'assessment_type',
                'holding_type',
                'apartment_details_id',
                'ip_address',
                'status',
                'user_id',
                'citizen_id',
                'pt_no',
                'building_name',
                'street_name',
                'location',
                'landmark',
                'is_gb_saf',
                'gb_office_name',
                'gb_usage_types',
                'gb_prop_usage_types',
                'is_trust',
                'trust_type',
                'is_trust_verified',
                'rwh_date_from',
                'category_id'
            )->first();
    }


    // Get the active saf details 
    public function getSafDetailsByCitizenId($citizenId)
    {
        return PropActiveSaf::where('citizen_id', $citizenId)
            ->where('status', 1);
    }
}
