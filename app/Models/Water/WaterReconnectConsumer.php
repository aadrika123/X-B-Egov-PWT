<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
