<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropDeactivationRequest extends PropParamModel #Model
{
    use HasFactory;
    public $timestamps = false;


    public function getDeactivationApplication()
    {
        return PropDeactivationRequest::select(
            'prop_deactivation_requests.id',
            DB::raw("'active' as status"),
            'application_no',
            'prop_properties.new_holding_no',
            'prop_properties.holding_no',
            'prop_deactivation_requests.property_id',
            'prop_properties.ward_mstr_id',
            'prop_properties.new_ward_mstr_id',
            'u.ward_name as old_ward_no',
            'u1.ward_name as new_ward_no'
        )
            ->join('prop_properties', 'prop_properties.id', '=', 'prop_deactivation_requests.property_id')
            ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_deactivation_requests.property_id')
            ->join('ulb_ward_masters as u', 'prop_properties.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 'prop_properties.new_ward_mstr_id', '=', 'u1.id');
    }
}
