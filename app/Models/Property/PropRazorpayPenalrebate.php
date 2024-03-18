<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropRazorpayPenalrebate extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Store New Penalty Rebates
     */
    public function store(array $req)
    {
        PropRazorpayPenalrebate::create($req);
    }

    /**
     * | Get Penalty Rebates by Request Id
     */
    public function getPenalRebatesByReqId($razorPayReqId)
    {
        return PropRazorpayPenalrebate::where('razorpay_request_id', $razorPayReqId)
            ->get();
    }
}
