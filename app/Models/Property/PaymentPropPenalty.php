<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPropPenalty extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Check Penalty By demand ID and safid
     */
    public function getPenaltyByDemandSafId($demandId, $safId)
    {
        return PaymentPropPenalty::where('saf_demand_id', $demandId)
            ->where('saf_id', $safId)
            ->first();
    }

    /**
     * | Edit Penalties
     */
    public function editPenalties($penaltyId, $reqs)
    {
        $penalty = PaymentPropPenalty::find($penaltyId);
        $penalty->update($reqs);
    }

    /**
     * | Post New Penalty
     */
    public function postPenalties($reqs)
    {
        PaymentPropPenalty::create($reqs);
    }
}
