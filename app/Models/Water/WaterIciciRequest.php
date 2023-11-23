<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterIciciRequest extends Model
{
    use HasFactory;

    /**
     * | Save the details for online payment detials 
     */
    public function savePaymentReq($paymentDetails, $request, $refDetails,)
    {
        $WaterIciciRequest = new WaterIciciRequest;
        // $WaterIciciRequest->related_id        = $request->consumerId ?? $request->applicationId;
        // $WaterIciciRequest->payment_from      = $paymentFor;
        // $WaterIciciRequest->amount            = $request->amount ?? $refDetails['totalAmount'];
        // $WaterIciciRequest->demand_from_upto  = $request->demandFrom ? ($request->demandFrom . "--" . $request->demandUpto) : null;
        // $WaterIciciRequest->ip_address        = $request->ip();
        // $WaterIciciRequest->order_id          = $temp["orderId"];
        // $WaterIciciRequest->department_id     = $temp["departmentId"];
        // $WaterIciciRequest->adjusted_amount   = $refDetails["adjustedAmount"] ?? null;
        // $WaterIciciRequest->due_amount        = $refDetails["leftDemandAmount"] ?? null; # dont save 
        // $WaterIciciRequest->penalty_amount    = $refDetails["penaltyAmount"] ?? null;
        // $WaterIciciRequest->remarks           = $request->remarks;
        // $WaterIciciRequest->conumer_charge_id = $refDetails['chargeCatagoryId'] ?? null;
        // $WaterIciciRequest->save();
    }
}
