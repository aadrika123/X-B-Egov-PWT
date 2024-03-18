<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waiver extends PropParamModel #Model
{
    use HasFactory;
    
    public function addwaiver($request)
    {
        $data = new Waiver();
        $data->is_bill_waiver = $request->isBillWaiver;
        $data->is_rwh_penalty = $request->isRwhPenalty;
        $data->is_lateassessment_waiver = $request->isLateassessmentWaiver;
        $data->billed_amount = $request->billedAmount;
        $data->bill_waiver_amount = $request->billWaiverAmount;
        $data->penalty_amount = $request->penaltyAmount;
        $data->penalty_waiver_amount = $request->penaltyWaiverAmount;
        $data->rwh_amount = $request->rwhAmount;
        $data->rwh_waiver_amount = $request->rwhWaiverAmount;
        $data->waiver_startdate = $request->waiverStartdate;
        $data->waiver_enddate = $request->waiverEnddate;
        $data->bill_id = $request->billId;
        $data->property_id = $request->propertyId;
        $data->saf_id = $request->safId;
        $data->waiver_document = $request->waiverDocument;

        $data->save();
    }
    
}
