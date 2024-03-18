<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropActiveWaiver extends PropParamModel #Model
{
    use HasFactory;

    public function addWaiver($request)
    {
        $data = new PropActiveWaiver();
        $data->is_bill_waiver = $request->isBillWaiver;
        $data->is_one_percent_penalty = $request->isOnePercentPenalty;
        $data->is_rwh_penalty = $request->isRwhPenalty;
        $data->is_lateassessment_penalty = $request->isLateassessmentPenalty;
        $data->bill_amount = $request->billAmount;
        $data->bill_waiver_amount = $request->billWaiverAmount;
        $data->one_percent_penalty_amount = $request->onePercentPenaltyAmount;
        $data->one_percent_penalty_waiver_amount = $request->onePercentPenaltyWaiverAmount;
        $data->rwh_amount = $request->rwhAmount;
        $data->rwh_waiver_amount = $request->rwhWaiverAmount;
        $data->lateassessment_penalty_amount = $request->lateAssessmentPenaltyAmount;
        $data->lateassessment_penalty_waiver_amount = $request->lateAssessmentPenaltyWaiverAmount;
        $data->waiver_startdate = $request->waiverStartdate;
        $data->waiver_enddate = $request->waiverEnddate;
        $data->bill_id = $request->billId;
        $data->property_id = $request->propertyId;
        $data->saf_id = $request->safId;
        // $data->waiver_document = $request->waiverDocument;
        $data->description = $request->description;
        $data->user_id = $request->userId;
        $data->workflow_id = $request->workflowId;
        $data->ulb_id = $request->ulbId;
        $data->current_role = $request->currentRole;
        $data->application_no = $request->applicationNo;
        $data->save();
        return $data;
    }

    public function waiverList()
    {
        return PropActiveWaiver::select(
            'prop_active_waivers.*',
            's.area_of_plot',
            // DB::raw("case when is_bill_waiver = true then 'Bill Waiver',
            //          case when is_one_percent_penalty = true then '1 % Penalty',
            //          case when is_rwh_penalty = true then 'RWH Penalty',
            //          case when is_lateassessment_penalty = true then 'Late Assessment Penalty',
            //  end as applied_for"),
            DB::raw("case when property_id is not null then 'Property' else 'Saf' end as application_type"),
            DB::raw("TO_CHAR(prop_active_waivers.created_at, 'DD-MM-YYYY') as application_date"),
            'u.ward_name as old_ward_no',
            'u1.ward_name as new_ward_no',
            'p.property_type',
            'o.ownership_type',
            'r.road_type as road_type_master'
        )
            ->leftJoin('prop_properties as s', 's.id', '=', 'prop_active_waivers.property_id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 's.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as u1', 'u.id', '=', 's.new_ward_mstr_id')
            ->leftJoin('ref_prop_ownership_types as o', 'o.id', '=', 's.ownership_type_mstr_id')
            ->leftJoin('ref_prop_types as p', 'p.id', '=', 's.prop_type_mstr_id')
            ->leftJoin('ref_prop_road_types as r', 'r.id', '=', 's.road_type_mstr_id');
    }
}
