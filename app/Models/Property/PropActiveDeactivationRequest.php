<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropActiveDeactivationRequest extends PropParamModel #Model
{
    use HasFactory;
    public $timestamps = false;

    /**
     * | Get details of deactivation list by holding no
     */
    public function getDeactivationApplication()
    {
        return PropActiveDeactivationRequest::select(
            'prop_active_deactivation_requests.id',
            DB::raw("'active' as status"),
            'application_no',
            'prop_properties.new_holding_no',
            'prop_properties.holding_no',
            'prop_active_deactivation_requests.property_id',
            'prop_properties.ward_mstr_id',
            'prop_properties.new_ward_mstr_id',
            'u.ward_name as old_ward_no',
            'u1.ward_name as new_ward_no'
        )
            ->join('prop_properties', 'prop_properties.id', '=', 'prop_active_deactivation_requests.property_id')
            ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_active_deactivation_requests.property_id')
            ->join('ulb_ward_masters as u', 'prop_properties.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 'prop_properties.new_ward_mstr_id', '=', 'u1.id');
    }

    /**
     * | REcent Applications
     */
    public function recentApplication($userId)
    {
        $data = PropActiveDeactivationRequest::select(
            'prop_active_deactivation_requests.id',
            'holding_no as holdingNo',
            'apply_date as applydate',
            // DB::raw("TO_CHAR(apply_date, 'DD-MM-YYYY') as applyDate"),
            DB::raw(" 'Deactivation' as assessmentType"),
        )
            ->join('prop_properties', 'prop_properties.id', 'prop_active_deactivation_requests.property_id')
            ->where('prop_active_deactivation_requests.emp_detail_id', $userId)
            ->orderBydesc('prop_active_deactivation_requests.id')
            ->take(10)
            ->get();

        $application = collect($data)->map(function ($value) {
            $value['applyDate'] = (Carbon::parse($value['applydate']))->format('d-m-Y');
            return $value;
        });
        return $application;
    }

    /**
     * | 
     */
    public function todayAppliedApplications($userId)
    {
        $date = Carbon::now();
        return PropActiveDeactivationRequest::select('id')
            ->where('prop_active_deactivation_requests.emp_detail_id', $userId)
            ->where('apply_date', $date);
    }

    /**
     * | Today Received Appklication
     */
    public function todayReceivedApplication($currentRole, $ulbId)
    {
        $date = Carbon::now()->format('Y-m-d');
        return PropActiveDeactivationRequest::select(
            'application_no as applicationNo',
            'apply_date as applyDate',
        )

            ->join('workflow_tracks', 'workflow_tracks.ref_table_id_value', 'prop_active_deactivation_requests.id')
            ->where('workflow_tracks.receiver_role_id', $currentRole)
            ->where('workflow_tracks.ulb_id', $ulbId)
            ->where('ref_table_dot_id', 'prop_active_deactivation_requests')
            ->whereRaw("date(track_date) = '$date'")
            ->orderBydesc('prop_active_deactivation_requests.id');
    }
}
