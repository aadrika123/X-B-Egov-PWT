<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterIciciRequest extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    /**
     * | Save the details for online payment detials 
     */
    public function savePaymentReq($paymentDetails, $request, $refDetails, $paymentFor)
    {
        $WaterIciciRequest = new WaterIciciRequest;
        $WaterIciciRequest->related_id          = $request->consumerId;
        $WaterIciciRequest->payment_from        = $request;
        $WaterIciciRequest->demand_from_upto    = $refDetails->refDemandFrom . "--" . $refDetails->refDemandUpto ?? null;
        $WaterIciciRequest->amount              = round($request->amount);
        $WaterIciciRequest->due_amount          = $refDetails->leftDemandAmount;
        $WaterIciciRequest->remarks             = $request->remarks;
        $WaterIciciRequest->ip_address          = $request->ip();
        $WaterIciciRequest->is_rebate           = $refDetails->isRebate ?? null;
        $WaterIciciRequest->unique_ref_number   = $paymentDetails['req_ref_no'];
        $WaterIciciRequest->transaction_date    = Carbon::now();
        $WaterIciciRequest->sub_merchant_id     = null;
        $WaterIciciRequest->reference_no        = $request;
        $WaterIciciRequest->reference_url       = $request;
        $WaterIciciRequest->save();

        // related_id
        // payment_from
        // demand_from_upto
        // amount
        // due_amount
        // remarks
        // ip_address
        // is_rebate
        // consumer_charge_id
        // unique_ref_number
        // transaction_date
        // payment_mode
        // sub_merchant_id
        // reference_no
        // reference_url
    }
}
