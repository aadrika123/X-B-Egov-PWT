<?php

namespace App\Models\Water;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WaterApprovalApplicationDetail extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * |------------------------- Get the Approved Applecaton Details ---------------------------|
     * | @param request
     */
    public function getApprovedApplications()
    {
        $approvedWater = WaterApprovalApplicationDetail::orderByDesc('id');
        return $approvedWater;
    }


    /**
     * |
     */
    public function getApplicationRelatedDetails()
    {
        return WaterApprovalApplicationDetail::join('ulb_masters', 'ulb_masters.id', '=', 'water_approval_application_details.ulb_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
            ->orderByDesc('id');
    }


    /**
     * | Get
     */
    public function getApprovedApplicationById($applicationId)
    {
        return WaterApprovalApplicationDetail::select(
            'water_approval_application_details.id',
            'water_approval_application_details.application_no',
            'water_approval_application_details.ward_id',
            'water_approval_application_details.address',
            'water_approval_application_details.holding_no',
            'water_approval_application_details.saf_no',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            DB::raw("string_agg(water_approval_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_approval_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_approval_applicants.guardian_name,',') as guardianName"),
        )
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_approval_application_details.ulb_id')
            ->join('water_approval_applicants', 'water_approval_applicants.application_id', '=', 'water_approval_application_details.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
            ->where('water_approval_application_details.status', true)
            ->where('water_approval_application_details.id', $applicationId)
            ->groupBy(
                'water_approval_application_details.saf_no',
                'water_approval_application_details.holding_no',
                'water_approval_application_details.address',
                'water_approval_application_details.id',
                'water_approval_applicants.application_id',
                'water_approval_application_details.application_no',
                'water_approval_application_details.ward_id',
                'water_approval_application_details.ulb_id',
                'ulb_ward_masters.ward_name',
                'ulb_masters.id',
                'ulb_masters.ulb_name'
            );
    }

    /**
     * | Get approved appliaction using the id 
     */
    public function getApproveApplication($applicationId)
    {
        return WaterApprovalApplicationDetail::where('id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first();
    }

    public function getDetailsByApplicationNo($req, $connectionTypes, $applicationNo)
    {
        return WaterApprovalApplicationDetail::select(
            'water_second_consumers.id',
            'water_approval_application_details.id as applicationId',
            'water_approval_application_details.application_no',
            'water_approval_application_details.ward_id',
            'water_approval_application_details.address',
            'water_approval_application_details.saf_no',
            'water_approval_application_details.payment_status',
            'water_approval_application_details.property_no as holding_no',
            'ulb_ward_masters.ward_name',
            DB::raw("string_agg(water_approval_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_approval_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_approval_applicants.guardian_name,',') as guardianName"),
        )
            ->join('water_approval_applicants', 'water_approval_applicants.application_id', '=', 'water_approval_application_details.id')
            ->join('water_second_consumers', 'water_second_consumers.apply_connection_id', 'water_approval_application_details.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
            ->where('water_approval_application_details.status', true)
            ->where('water_approval_application_details.connection_type_id', $connectionTypes)
            ->where('water_approval_application_details.application_no', 'LIKE', '%' . $applicationNo . '%')
            ->where('water_approval_application_details.ulb_id', authUser($req)->ulb_id)
            ->whereIn('water_second_consumers.status', [1, 4])
            ->orderby('water_second_consumers.id', 'DESC')
            ->groupBy(
                'water_second_consumers.id',
                'water_approval_application_details.saf_no',
                'water_approval_application_details.holding_no',
                'water_approval_application_details.address',
                'water_approval_application_details.id',
                'water_approval_applicants.application_id',
                'water_approval_application_details.application_no',
                'water_approval_application_details.ward_id',
                'ulb_ward_masters.ward_name'
            );
    }
    /**
     * |get details of applications which is partiallly make consumer
     * | before it payments
     */
    public function fullWaterDetails($request)
    {
        return  WaterApprovalApplicationDetail::select(
            'water_second_consumers.id',
            'water_approval_application_details.id as applicationId',
            'water_approval_application_details.mobile_no',
            'water_approval_application_details.tab_size',
            'water_approval_application_details.property_no',
            'water_approval_application_details.status',
            'water_approval_application_details.payment_status',
            'water_approval_application_details.user_type',
            'water_approval_application_details.apply_date',
            'water_approval_application_details.landmark',
            'water_approval_application_details.address',
            'water_approval_application_details.category',
            'water_approval_application_details.application_no',
            'water_approval_application_details.ward_no',
            'water_approval_application_details.pin',
            'water_approval_application_details.doc_upload_status',
            'water_property_type_mstrs.property_type',
            'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            'wf_roles.role_name AS current_role_name',
            'water_connection_type_mstrs.connection_type',
            'water_connection_charges.amount',
            "water_connection_charges.charge_category",
            "water_consumer_owners.applicant_name as owner_name",
            "water_consumer_owners.guardian_name",
            "water_consumer_meters.connection_type",
            "water_consumer_meters.meter_no",
            "ulb_ward_masters.ward_name as ward_no",
            // "water_road_cutter_charges.road_type",
            "water_approval_application_details.per_meter",
            "water_approval_application_details.trade_license as license_no",
            "water_approval_application_details.initial_reading",
            "water_consumer_charges.amount as reconnectCharges",
            "water_consumer_charges.charge_category as reconnechargeCategory"
        )
            ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_approval_application_details.current_role')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_approval_application_details.ulb_id')
            ->join('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_approval_application_details.connection_type_id')
            ->rightjoin('water_second_consumers', 'water_second_consumers.apply_connection_id', 'water_approval_application_details.id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_approval_application_details.property_type_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_approval_application_details.ward_id')
            ->join('water_param_pipeline_types', 'water_param_pipeline_types.id', 'water_approval_application_details.pipeline_type_id')
            ->join('zone_masters', 'zone_masters.id', 'water_approval_application_details.zone_mstr_id')
            ->join('water_connection_charges', 'water_connection_charges.application_id', 'water_approval_application_details.id')
            ->leftJoin('water_consumer_charges', function ($join) {
                $join->on('water_consumer_charges.consumer_id', '=', 'water_second_consumers.id')
                    ->where('water_consumer_charges.status', 1)
                    ->where('water_consumer_charges.charge_category', 'WATER RECONNECTION');
            })
            ->join('water_consumer_owners', 'water_consumer_owners.application_id', 'water_approval_application_details.id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            // ->leftjoin('water_road_cutter_charges')
            ->where('water_second_consumers.id', $request->applicationId)
            ->whereIn('water_second_consumers.status', [1, 2, 4])
            ->where('water_approval_application_details.status', true);
    }

    /**
     * |------------------- Get the Application details by applicationNo -------------------|
     * | @param applicationNo
     * | @param connectionTypes 
     * | @return 
     */
    public function getDetailsByApplicationId($applicationId)
    {
        return WaterApprovalApplicationDetail::select(
            'water_approval_application_details.id',
            'water_approval_application_details.application_no',
            'water_approval_application_details.ward_id',
            'water_approval_application_details.address',
            'water_approval_application_details.holding_no',
            'water_approval_application_details.property_no',
            'water_approval_application_details.meter_no',
            'water_approval_application_details.saf_no',
            "water_second_consumers.category",
            'water_approval_application_details.mobile_no',
            'water_second_consumers.consumer_no',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            'ulb_masters.logo',
            'ulb_masters.association_with',
            'water_road_cutter_charges.road_type',
            'water_road_cutter_charges.per_meter_amount',
            'water_param_property_types.property_type',
            // 'water_site_inspections.road_width ',
            'water_second_consumers.tab_size',
            'water_Second_consumers.connection_type',
            'water_connection_type_charges.amount as connecton_amount',
            DB::raw("(SELECT string_agg(water_approval_applicants.applicant_name, ',') FROM water_approval_applicants WHERE water_approval_applicants.application_id = water_approval_application_details.id) as applicantName"),
            DB::raw("(SELECT string_agg(water_approval_applicants.mobile_no::VARCHAR, ',') FROM water_approval_applicants WHERE water_approval_applicants.application_id = water_approval_application_details.id) as mobileNo"),
            DB::raw("(SELECT string_agg(water_approval_applicants.guardian_name, ',') FROM water_approval_applicants WHERE water_approval_applicants.application_id = water_approval_application_details.id) as guardianName")
        )
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_approval_application_details.ulb_id')
            ->join('water_approval_applicants', 'water_approval_applicants.application_id', '=', 'water_approval_application_details.id')
            ->join('water_second_consumers', 'water_second_consumers.apply_connection_id', 'water_approval_application_details.id')
            ->leftjoin('water_road_cutter_charges', 'water_road_cutter_charges.id', 'water_approval_application_details.road_type_id')
            ->join('water_connection_type_charges', 'water_connection_type_charges.id', 'water_approval_application_details.connection_type_id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
            ->Join('water_param_property_types', 'water_param_property_types.id', '=', 'water_second_consumers.property_type_id')
            ->where('water_approval_application_details.status', true)
            ->where('water_second_consumers.id', $applicationId);
    }

    public function fullWaterDetail($applicationId)
    {
        return WaterApprovalApplicationDetail::select(
            'water_second_consumers.id',
            'water_approval_application_details.id as applicationId',
            'water_approval_application_details.application_no',
            'water_approval_application_details.category',
            'water_approval_application_details.address',
            'water_approval_application_details.landmark',
            'water_approval_application_details.payment_status',
            'water_approval_application_details.property_no',
            'water_approval_application_details.mobile_no',
            'water_connection_charges.amount',
            "water_connection_charges.charge_category"

        )
            ->join('water_connection_charges', 'water_connection_charges.application_id', 'water_approval_application_details.id')
            ->join('water_second_consumers', 'water_second_consumers.apply_connection_id', 'water_approval_application_details.id')
            ->where('water_second_consumers.id', $applicationId);
    }

    public function getApplicationById($applicationId)
    {
        return  WaterApprovalApplicationDetail::where('id', $applicationId)
            ->where('status', 1);
    }
    /**
     * | Save The payment Status 
     * | @param ApplicationId
     */
    public function updateOnlyPaymentstatus($applicationId)
    {
        $activeSaf = WaterApprovalApplicationDetail::find($applicationId);
        $activeSaf->payment_status = 1;
        $activeSaf->save();
    }

    /**
     * | Update the payment Status ini case of pending
     * | in case of application is under verification 
     * | @param applicationId
     */
    public function updatePendingStatus($applicationId)
    {
        $activeSaf = WaterApprovalApplicationDetail::find($applicationId);
        $activeSaf->payment_status = 2;
        $activeSaf->save();
    }

    /**
     * | Save the application current role as the bo when payament is done offline
     * | @param 
     */
    public function sendApplicationToRole($applicationId, $refRoleId)
    {
        WaterApprovalApplicationDetail::where('id', $applicationId)
            ->where('status', 1)
            ->update([
                "current_role" => $refRoleId
            ]);
    }
    public function updateConsumerId($applicationId, $consumerId)
    {
        WaterApprovalApplicationDetail::where('id', $applicationId)
            ->where('status', 1)
            ->update([
                "consumer_id" => $consumerId
            ]);
    }

    /**
     * |----------------------------- Get Water Approve  Application List ---------------------------|
     * | Rating : 
     * | Opertation : List Approve Application For EO
        | Working
     */
    public function getWaterApproveList($ulbId)
    {
        return WaterApprovalApplicationDetail::select(
            'water_approval_application_details.id',
            'water_approval_application_details.application_no',
            'water_approval_application_details.id as owner_id',
            'water_approval_application_details.applicant_name as owner_name',
            // 'water_applications.ward_id',
            'water_connection_through_mstrs.connection_through',
            'water_connection_type_mstrs.connection_type',
            // 'u.ward_name as ward_no',
            'water_approval_application_details.workflow_id',
            'water_approval_application_details.current_role as role_id',
            'water_approval_application_details.apply_date',
            'water_approval_application_details.parked',
            'ulb_ward_masters.ward_name'
        )
            // ->join('ulb_ward_masters as u', 'u.id', '=', 'water_applications.ward_id')
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_approval_application_details.id')
            ->leftjoin('water_connection_through_mstrs', 'water_connection_through_mstrs.id', '=', 'water_approval_application_details.connection_through')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_applications.ward_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_approval_application_details.connection_type_id')
            ->where('water_approval_application_details.status', 1)
            ->where('water_approval_application_details.ulb_id', $ulbId)
            ->orderByDesc('water_applicants.id');
    }
}
