<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerCollection extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];

    /**
     * | Save consumer demand details for the transactions
     * | @param 
     */
    public function saveConsumerCollection($charges, $waterTrans, $refUserId, $refPaidAmount)
    {
        $mWaterConsumerCollection = new WaterConsumerCollection();
        $mWaterConsumerCollection->consumer_id          = $charges->consumer_id;
        $mWaterConsumerCollection->ward_mstr_id         = $charges->ward_id;
        $mWaterConsumerCollection->transaction_id       = $waterTrans['id'];
        $mWaterConsumerCollection->amount               = $charges->amount ?? $charges->balance_amount;
        $mWaterConsumerCollection->emp_details_id       = $refUserId;
        $mWaterConsumerCollection->demand_id            = $charges->id;
        $mWaterConsumerCollection->demand_from          = $charges->demand_from;
        $mWaterConsumerCollection->demand_upto          = $charges->demand_upto;
        $mWaterConsumerCollection->penalty              = $charges->penalty;
        $mWaterConsumerCollection->payment_from         = null;
        $mWaterConsumerCollection->demand_payment_from  = null;
        $mWaterConsumerCollection->connection_type      = $charges->connection_type;
        $mWaterConsumerCollection->paid_amount          = $refPaidAmount ?? $charges->due_balance_amount;
        $mWaterConsumerCollection->save();
    }
    /**
     * get consumer_collection
     */
    public function getConsumerCollection($demandId)
    {
        return WaterConsumerCollection::where('demand_id', $demandId)
            ->where('status', 1);
    }

    /**
     * deactivate consumer collections by tran id
     */
    public function updateConsumerCollection($tranId, $updateDetils)
    {
        return WaterConsumerCollection::where('transaction_id', $tranId)
            ->update($updateDetils);
    }
}
