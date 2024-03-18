<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPropPenaltyrebate extends  PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Get Property Penalty Rebates by safid or Prop id
     */
    public function getRebatePanelties($key, $id, $headName)
    {
        return PaymentPropPenaltyrebate::where($key, $id)
            ->where('head_name', $headName)
            ->where('status', 1)
            ->first();
    }

    /**
     * | Edit Rebate panalties
     */
    public function editRebatePenalty($safId, $req)
    {
        $rebatePanalty = PaymentPropPenaltyrebate::find($safId);
        $rebatePanalty->update($req);
    }

    /**
     * | Post Rebate Penalties
     */
    public function postRebatePenalty($reqs)
    {
        PaymentPropPenaltyrebate::create($reqs);
    }

    /**
     * | Get Penal Rebates by Saf Id
     */
    public function getPenalRebatesBySafId($safId)
    {
        return PaymentPropPenaltyrebate::where('saf_id', $safId)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Get Penal Rebates by Property ID
     */
    public function getPenalRebatesByPropId($propId)
    {
        return PaymentPropPenaltyrebate::where('prop_id', $propId)
            ->get();
    }
}
