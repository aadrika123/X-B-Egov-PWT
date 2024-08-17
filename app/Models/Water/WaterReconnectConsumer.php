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
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_reconnect_consumers.consumer_id')
            ->join('water_approval_application_details', 'water_approval_application_details.id', '=', 'water_second_consumers.apply_connection_id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
            ->where('water_approval_application_details.status', true)
            ->where('water_reconnect_consumers.application_no', 'LIKE', '%' . $applicationNo . '%')
            ->where('water_second_consumers.ulb_id', authUser($req)->ulb_id)
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
}
