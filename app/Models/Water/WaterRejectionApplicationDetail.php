<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterRejectionApplicationDetail extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    public function fullWaterDetails($request)
    {
        return  WaterRejectionApplicationDetail::select(
            'water_rejection_application_details.id',
            'water_rejection_application_details.id as applicationId',
            'water_rejection_application_details.mobile_no',
            'water_rejection_application_details.tab_size',
            'water_rejection_application_details.property_no',
            'water_rejection_application_details.status',
            'water_rejection_application_details.payment_status',
            'water_rejection_application_details.user_type',
            'water_rejection_application_details.apply_date',
            'water_rejection_application_details.landmark',
            'water_rejection_application_details.address',
            'water_rejection_application_details.category',
            'water_rejection_application_details.application_no',
            // 'water_rejection_application_details.ward_no',
            'water_rejection_application_details.pin',
            'water_rejection_application_details.current_role',
            'water_rejection_application_details.workflow_id',
            'water_rejection_application_details.last_role_id',
            'water_rejection_application_details.doc_upload_status',
            'water_rejection_application_details.meter_no',
            'water_rejection_application_details.initial_reading',
            'water_rejection_application_details.email',
            'water_property_type_mstrs.property_type',
            'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            'wf_roles.role_name AS current_role_name',
            'water_connection_type_mstrs.connection_type',
            'water_connection_charges.amount',
            "water_connection_charges.charge_category",
            "ulb_ward_masters.ward_name as ward_no",
            "water_road_cutter_charges.road_type",
            "water_rejection_application_details.per_meter",
            "water_rejection_application_details.trade_license as license_no",
            "workflow_tracks.message as reason"
        )

            ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_rejection_application_details.current_role')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_rejection_application_details.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_rejection_application_details.ward_id')
            ->join('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_rejection_application_details.connection_type_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_rejection_application_details.property_type_id')
            ->join('water_param_pipeline_types', 'water_param_pipeline_types.id', 'water_rejection_application_details.pipeline_type_id')
            ->join('zone_masters', 'zone_masters.id', 'water_rejection_application_details.zone_mstr_id')
            ->join('water_connection_charges', 'water_connection_charges.application_id', 'water_rejection_application_details.id')
            ->leftjoin('water_road_cutter_charges', 'water_road_cutter_charges.id', 'water_rejection_application_details.road_type_id')
            ->leftJoin('workflow_tracks', function ($join) use ($request) {
                $join->on('workflow_tracks.ref_table_id_value', 'water_rejection_application_details.id')
                    ->where('workflow_tracks.verification_status', 0)
                    ->where('workflow_tracks.module_id', 2)
                    ->where('workflow_tracks.status', true)
                    ->where('workflow_tracks.message', '<>', null)
                    ->where('workflow_tracks.ref_table_id_value', $request->applicationId);;
            })
            ->where('water_rejection_application_details.id', $request->applicationId)
            ->where('water_rejection_application_details.status', true);
    }

    /**
     * |------------------- Get Water Application By Id -------------------|
     * | @param applicationId
     */
    public function getApplicationById($applicationId)
    {
        return  self::where('id', $applicationId)
            ->where('status', 1);
    }
}
