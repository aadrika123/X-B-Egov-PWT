<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerCharge extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get consumer charges using consumer Id
     */
    public function getConsumerCharges($consumerId)
    {
        return WaterConsumerCharge::where('consumer_id', $consumerId)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Save Consumer chsrges for the penalty
     */
    public function saveConsumerCharges($consumerCharges, $consumerId, $refChrgesCatagory)
    {
        $mWaterConsumerCharge = new WaterConsumerCharge();
        $mWaterConsumerCharge->consumer_id          = $consumerId;
        $mWaterConsumerCharge->charge_category      = $refChrgesCatagory;
        $mWaterConsumerCharge->charge_amount        = $consumerCharges['chargeAmount'];
        $mWaterConsumerCharge->penalty              = $consumerCharges['penalty'] ?? 0;
        $mWaterConsumerCharge->amount               = $consumerCharges['amount'];
        $mWaterConsumerCharge->rule_set             = $consumerCharges['ruleSet'];
        $mWaterConsumerCharge->charge_category_id   = $consumerCharges['chargeCategoryId'];
        $mWaterConsumerCharge->related_id           = $consumerCharges['relatedId'];
        $mWaterConsumerCharge->save();
        return [
            "id" => $mWaterConsumerCharge->id
        ];
    }

    /**
     * | Get Consumer charges by application id
     */
    public function getConsumerChargesById($applicationId)
    {
        return WaterConsumerCharge::where('related_id', $applicationId)
            ->where('status', 1);
    }



    /**
        | Serial No :
        | Remove   
     */

    public function saveConsumerChargesDiactivation($consumerId, $meteReq, $var)
    {
        $mWaterConsumerCharge = new WaterConsumerCharge();
        $mWaterConsumerCharge->consumer_id = $consumerId;
        $mWaterConsumerCharge->charge_category = $meteReq["chargeCategory"]; // Access the correct key from $meteReq array
        $mWaterConsumerCharge->charge_amount = $meteReq['chargeAmount'];
        $mWaterConsumerCharge->penalty = $meteReq['penalty'];
        $mWaterConsumerCharge->amount = $meteReq['amount'];
        $mWaterConsumerCharge->rule_set = $meteReq['ruleSet'];
        $mWaterConsumerCharge->charge_category_id = $meteReq['chargeCategoryID'];
        $mWaterConsumerCharge->related_id    = $var['relatedId'];

        $mWaterConsumerCharge->save();
        return $mWaterConsumerCharge;
    }

    /**
     * |----------------------------------- Get Water Charges By ApplicationId ------------------------------|
     * | @param request
     */
    public function getWaterchargesById($consumerId)
    {
        return WaterConsumerCharge::select(
            'id',
            'amount',
            'charge_category',
            'penalty',
            'rule_set',
            'paid_status'
        )
            ->where('consumer_id', $consumerId)
            ->where('status', 1);
    }
}
