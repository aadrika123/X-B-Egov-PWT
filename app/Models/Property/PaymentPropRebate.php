<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPropRebate extends PropParamModel #Model
{
    use HasFactory;

    // Get Payment Property Rebate by SAF Or Property ID and RebateId
    /**
     * | @param key can be safid or property id
     */
    public function getPaymentRebate($key, $id, $rebateId)
    {
        return  PaymentPropRebate::where("$key", $id)
            ->where('rebate_type_id', $rebateId)
            ->first();
    }

    /**
     * | Get PaymentRebates by saf_id
     */
    public function getRebatesBySafId($safId)
    {
        return PaymentPropRebate::where('saf_id', $safId)
            ->get();
    }
}
