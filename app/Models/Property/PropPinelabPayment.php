<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropPinelabPayment extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | @param refNo
     */
    public function getPaymentByBillRefNo($refNo)
    {
        return self::where('bill_ref_no', $refNo)
            ->where('status', 1)
            ->first();
    }
}
