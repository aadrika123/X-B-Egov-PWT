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
        $WaterIciciRequest->payment_from        = $paymentFor;
        $WaterIciciRequest->demand_from_upto    = $refDetails['refDemandFrom'] . "--" . $refDetails['refDemandUpto'] ?? null;
        $WaterIciciRequest->amount              = round($request->amount);
        $WaterIciciRequest->due_amount          = $refDetails['leftDemandAmount'];
        $WaterIciciRequest->remarks             = $request->remarks;
        $WaterIciciRequest->ip_address          = $request->ip();
        $WaterIciciRequest->is_rebate           = $refDetails['isRebate'] ?? null;
        $WaterIciciRequest->unique_ref_number   = $request->uniqueNo;
        $WaterIciciRequest->transaction_date    = Carbon::now();
        $WaterIciciRequest->reference_no        = $paymentDetails["req_ref_no"];
        $WaterIciciRequest->reference_url       = $paymentDetails["encryptUrl"];
        $WaterIciciRequest->is_part_payment     = $refDetails["partPaymentFlag"];
        $WaterIciciRequest->save();
    }


    /**
     * | Get request data according to referal no 
     */
    public function getReqDataByRefNo($refNo)
    {
        return WaterIciciRequest::where('reference_no', $refNo)
            ->orderByDesc('id');
    }
}
