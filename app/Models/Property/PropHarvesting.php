<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropHarvesting extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Harvesting By Harvesting No
     */
    public function getDtlsByHarvestingNo($harvestingNo)
    {
        return DB::table('prop_harvestings as h')
            ->select(
                'h.id',
                DB::raw("'approved' as status"),
                'h.application_no',
                'holding_no',
                'p.new_holding_no',
                'pt_no',
                'pt.property_type',
                'p.id as property_id',
                'p.ward_mstr_id',
                'p.new_ward_mstr_id',
                'u.ward_name as ward_no',
                'u1.ward_name as new_ward_no',
                'h.date',
                'role_name as currentRole'
            )
            ->leftjoin('wf_roles', 'wf_roles.id', 's.current_role')
            ->join('prop_properties as p', 'p.id', '=', 'h.property_id')
            ->leftjoin('ref_prop_types as pt', 'pt.id', '=', 'p.prop_type_mstr_id')
            ->join('ulb_ward_masters as u', 'p.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 'p.new_ward_mstr_id', '=', 'u1.id')
            ->where('application_no', strtoupper($harvestingNo))
            ->first();
    }

    /**
     * | Search Harvesting Applications
     */
    public function searchHarvesting()
    {
        return PropHarvesting::select(
            'prop_harvestings.id',
            DB::raw("'approved' as status"),
            'prop_harvestings.application_no',
            'prop_harvestings.current_role',
            'role_name as currentRole',
            'ward_name',
            'prop_address',
            // DB::raw("string_agg(prop_owners.mobile_no::VARCHAR,',') as mobile_no"),
            // DB::raw("string_agg(prop_owners.owner_name,',') as owner_name"),
        )

            ->join('wf_roles', 'wf_roles.id', 'prop_harvestings.current_role')
            ->join('prop_properties as pp', 'pp.id', 'prop_harvestings.property_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'pp.ward_mstr_id')
            ->join('prop_owners', 'prop_owners.property_id', 'pp.id');
    }
}
