<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropObjection extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Objection by Objection No
     */
    public function getObjByObjNo($objectionNo)
    {
        return DB::table('prop_objections as o')
            ->select(
                'o.id',
                DB::raw("'approved' as status"),
                'o.objection_no as application_no',
                'p.new_holding_no',
                'p.id as property_id',
                'p.ward_mstr_id',
                'p.new_ward_mstr_id',
                'role_name as currentRole',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no'
            )
            ->leftjoin('wf_roles', 'wf_roles.id', 'o.current_role')
            ->join('prop_properties as p', 'p.id', '=', 'o.property_id')
            ->join('ulb_ward_masters as u', 'p.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 'p.new_ward_mstr_id', '=', 'u1.id')
            ->where('o.objection_no', strtoupper($objectionNo))
            ->first();
    }

    /**
     * | Search Objection
     */
    public function searchObjections()
    {
        return PropObjection::select(
            'prop_objections.id',
            DB::raw("'approved' as status"),
            'prop_objections.objection_no as application_no',
            'prop_objections.current_role',
            'role_name as currentRole',
            'u.ward_name as old_ward_no',
            'uu.ward_name as new_ward_no',
            'prop_address',
            // DB::raw("string_agg(prop_owners.mobile_no::VARCHAR,',') as mobile_no"),
            // DB::raw("string_agg(prop_owners.owner_name,',') as owner_name"),
        )

            ->join('wf_roles', 'wf_roles.id', 'prop_objections.current_role')
            ->join('prop_properties as pp', 'pp.id', 'prop_objections.property_id')
            ->join('ulb_ward_masters as u', 'u.id', 'pp.ward_mstr_id')
            ->join('ulb_ward_masters as uu', 'uu.id', 'pp.new_ward_mstr_id')
            ->join('prop_owners', 'prop_owners.property_id', 'pp.id');
    }
}
