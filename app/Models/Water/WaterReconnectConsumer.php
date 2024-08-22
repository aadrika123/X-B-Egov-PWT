<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WaterReconnectConsumer extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    #get consumer 
    public function getConsumerDetails($consumerId)
    {
        return self::select(
            'water_reconnect_consumers.id'
        )
            ->where('water_reconnect_consumers.status', 1)
            ->where('water_reconnect_consumers.consumer_id', $consumerId);
    }

    /**
     * | Save request details 
     */
    public function saveRequestDetails($req, $consumerDetails, $refRequest, $applicationNo)
    {
        $mWaterReconnectConsumer  = new WaterReconnectConsumer();
        $mWaterReconnectConsumer->consumer_id               = $consumerDetails->id;
        $mWaterReconnectConsumer->apply_date                = Carbon::now();
        $mWaterReconnectConsumer->user_id                   = authUser($req)->id ?? null;
        $mWaterReconnectConsumer->created_at                = Carbon::now();
        // $mWaterReconnectConsumer->user_type                 = $refRequest['empId'] ?? null;
        $mWaterReconnectConsumer->current_role              = $refRequest['initiatorRoleId'];
        $mWaterReconnectConsumer->initiator                 = $refRequest['initiatorRoleId'];
        $mWaterReconnectConsumer->workflow_id               = $refRequest['ulbWorkflowId'];
        $mWaterReconnectConsumer->finisher                  = $refRequest['finisherRoleId'];
        $mWaterReconnectConsumer->user_type                 = authUser($req)->user_type;;
        $mWaterReconnectConsumer->application_no            = $applicationNo;
        $mWaterReconnectConsumer->charge_category_id        = $refRequest['chargeCategoryId'];
        $mWaterReconnectConsumer->save();
        return [
            "id" => $mWaterReconnectConsumer->id
        ];
    }

    /**
     * | Get the Application according to user details 
     */
    public function getApplicationByUser($userId)
    {
        return WaterReconnectConsumer::select(
            'water_reconnect_consumers.id',
            'water_reconnect_consumers.application_no',
            // DB::raw('REPLACE(water_consumer_charges.charge_category, \'_\', \' \') as charge_category'),
            "water_second_consumers.consumer_no",
            "water_reconnect_consumers.apply_date",
            "water_reconnect_consumers.payment_status",
            "ulb_ward_masters.ward_name",
            "water_consumer_charge_categories.charge_category",
            "wf_roles.role_name as current_role_name",
            'water_reconnect_consumers.verify_status',
        )
            ->leftjoin('water_consumer_charges', 'water_consumer_charges.related_id', 'water_reconnect_consumers.id')
            ->join('water_consumer_charge_categories', 'water_consumer_charge_categories.id', 'water_reconnect_consumers.charge_category_id')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_reconnect_consumers.consumer_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->join('wf_roles', 'wf_roles.id', 'water_reconnect_consumers.current_role')
            ->where('water_reconnect_consumers.user_id', $userId)
            ->where('water_reconnect_consumers.status', 1)
            ->where('water_second_consumers.status', 1)
            ->orderByDesc('water_reconnect_consumers.id');
    }

    public function getDetailsByApplicationNo($req, $applicationNo)
    {
        return WaterReconnectConsumer::select(
            'water_second_consumers.id',
            'water_reconnect_consumers.id as applicationId',
            'water_reconnect_consumers.application_no',
            'water_approval_application_details.ward_id',
            'water_approval_application_details.address',
            'water_approval_application_details.saf_no',
            'water_approval_application_details.payment_status',
            'water_approval_application_details.property_no as holding_no',
            'ulb_ward_masters.ward_name',
            DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardianName")
        )
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_reconnect_consumers.consumer_id')
            ->leftjoin('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_reconnect_consumers.consumer_id')
            ->leftjoin('water_approval_application_details', 'water_approval_application_details.id', '=', 'water_second_consumers.apply_connection_id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
            // ->where('water_approval_application_details.status', true)
            ->where('water_reconnect_consumers.application_no', 'LIKE', '%' . $applicationNo . '%')
            ->where('water_second_consumers.ulb_id', 2)
            ->whereIn('water_second_consumers.status', [1, 4])
            ->orderby('water_reconnect_consumers.id', 'DESC')
            ->groupBy(
                'water_second_consumers.id',
                'water_reconnect_consumers.id',
                'water_reconnect_consumers.application_no',
                'water_approval_application_details.ward_id',
                'water_approval_application_details.address',
                'water_approval_application_details.saf_no',
                'water_approval_application_details.payment_status',
                'water_approval_application_details.property_no',
                'ulb_ward_masters.ward_name'
            );
    }
    /**
     * | Update the payment Status ini case of pending
     * | in case of application is under verification 
     * | @param applicationId
     */
    public function updatePendingStatus($applicationId)
    {
        $activeSaf = WaterReconnectConsumer::find($applicationId);
        $activeSaf->payment_status = 2;
        $activeSaf->save();
    }

    /**
     * | Save The payment Status 
     * | @param ApplicationId
     */
    public function updateOnlyPaymentstatus($applicationId)
    {
        $activeSaf = WaterReconnectConsumer::where('consumer_id', $applicationId)->first();
        $activeSaf->payment_status = 1;
        $activeSaf->save();
    }

    public function fullWaterDetails($request)
    {
        return  WaterReconnectConsumer::select(
            'water_reconnect_consumers.id',
            'water_reconnect_consumers.id as applicationId',
            'water_reconnect_consumers.consumer_id as consumer_id',
            'water_consumer_owners.mobile_no',
            'water_second_consumers.tab_size',
            'water_second_consumers.property_no',
            'water_second_consumers.status',
            'water_reconnect_consumers.payment_status',
            'water_reconnect_consumers.user_type',
            'water_reconnect_consumers.apply_date',
            'water_second_consumers.landmark',
            'water_second_consumers.address',
            'water_second_consumers.category',
            'water_second_consumers.consumer_no',
            'water_reconnect_consumers.application_no',
            // 'water_reconnect_consumers.ward_no',
            // 'water_second_consumers.pin',
            'water_reconnect_consumers.current_role',
            'water_reconnect_consumers.workflow_id',
            'water_reconnect_consumers.last_role_id',
            'water_reconnect_consumers.doc_upload_status',
            'water_approval_application_details.meter_no',
            // 'water_second_consumers.initial_reading',
            'water_approval_application_details.email',
            'water_property_type_mstrs.property_type',
            'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            'wf_roles.role_name AS current_role_name',
            // 'water_connection_type_mstrs.connection_type',
            'water_consumer_charges.amount',
            "water_consumer_charges.charge_category",
            "ulb_ward_masters.ward_name as ward_no",
            "water_road_cutter_charges.road_type",
            "water_approval_application_details.per_meter",
            "water_approval_application_details.trade_license as license_no",
            "water_approval_application_details.user_type",
            'water_second_consumers.holding_no'
        )
            ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_reconnect_consumers.current_role')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_reconnect_consumers.consumer_id')
            ->leftjoin('water_approval_application_details', 'water_approval_application_details.id', 'water_second_consumers.apply_connection_id')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_second_consumers.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->join('water_param_pipeline_types', 'water_param_pipeline_types.id', 'water_second_consumers.pipeline_type_id')
            ->join('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->join('water_consumer_charges', 'water_consumer_charges.consumer_id', 'water_reconnect_consumers.consumer_id')
            ->leftjoin('water_road_cutter_charges', 'water_road_cutter_charges.id', 'water_approval_application_details.road_type_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_reconnect_consumers.consumer_id')
            ->where('water_reconnect_consumers.id', $request->applicationId)
            ->where('water_reconnect_consumers.status', true);
    }

    /**
     * | Get active request by request id 
     */
    public function getActiveReqById($id)
    {
        return self::where('id', $id)
            ->where('status', 1);
    }
    #update doc status
    public function updateDocStatus($applicationId)
    {
        $application = WaterReconnectConsumer::where('id', $applicationId)->first();
        $application->doc_upload_status = true;
        $application->save();
    }
    /**
     * | Update the application Doc Verify status
     * | @param applicationId
     */
    public function updateAppliVerifyStatus($applicationId)
    {
        WaterReconnectConsumer::where('id', $applicationId)
            ->update([
                'doc_status' => true
            ]);
    }
    /**
     * | Get active request by request id 
     */
    public function getApproveApplication($id)
    {
        return self::where('id', $id)
            ->where('status', 1)
            ->where('verify_status', 1);
    }

    # Reject consumer
    public function updateVerifyRequest($request, $userId)
    {
        $application = WaterReconnectConsumer::where('id', $request->applicationId)->first();
        $application->verify_status = 1;
        $application->emp_detail_id = $userId;
        $application->save();
    }
    # Reject consumer
    public function updateVerifyRejectRequest($request, $userId)
    {
        $application = WaterReconnectConsumer::where('id', $request->applicationId)->first();
        $application->verify_status = 2;
        $application->emp_detail_id = $userId;
        $application->save();
    }

    /**
     * | Get consumer by consumer Id
     */
    public function getConsumerDetailsById($consumerId)
    {
        return WaterReconnectConsumer::select(
            'water_reconnect_consumers.*',
        )
            ->where('verify_status', 1)
            ->where('demand_generate', false)
            ->where('consumer_id', $consumerId);
    }
}
