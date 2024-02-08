<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterTranFineRebate extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Save fine and rebate in table
     * | @param 
     * | 
     */
    public function saveRebateDetails($metaRequest, $waterTrans)
    {
        $mWaterTranFineRebate = new WaterTranFineRebate();
        $mWaterTranFineRebate->transaction_id       = $waterTrans;
        $mWaterTranFineRebate->head_name            = $metaRequest->headName;
        $mWaterTranFineRebate->amount               = $metaRequest->amount;
        $mWaterTranFineRebate->value_add_minus      = $metaRequest->valueAddMinus;
        $mWaterTranFineRebate->apply_connection_id  = $metaRequest->applicationId ?? null;
        $mWaterTranFineRebate->save();
    }


    /**
     * | Get fine and rebate according to application no 
     * | 
     */
    public function getFineRebate($applicationId, $searchFor, $transactionId)
    {
        return WaterTranFineRebate::where("transaction_id", $transactionId)
            ->where("apply_connection_id", $applicationId)
            ->where("head_name", $searchFor);
    }

    public function getPenaltyRebatesHeads($transactionId)
    {
        return WaterTranFineRebate::where("transaction_id", $transactionId)
                ->where("status",true)
                ->get();
    }
}
