<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropSafsFloor extends PropParamModel #Model
{
    use HasFactory;


    /**
     * | Get Safs Floors By Saf Id
     */
    public function getSafFloorsBySafId($safId)
    {
        return PropSafsFloor::where('saf_id', $safId)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Get Saf Floors
     */
    public function getFloorsBySafId($safId)
    {
        return DB::table('prop_safs_floors')
            ->select(
                'prop_safs_floors.*',
                'f.floor_name',
                'u.usage_type',
                'o.occupancy_type',
                'c.construction_type'
            )
            ->join('ref_prop_floors as f', 'f.id', '=', 'prop_safs_floors.floor_mstr_id')
            ->join('ref_prop_usage_types as u', 'u.id', '=', 'prop_safs_floors.usage_type_mstr_id')
            ->join('ref_prop_occupancy_types as o', 'o.id', '=', 'prop_safs_floors.occupancy_type_mstr_id')
            ->join('ref_prop_construction_types as c', 'c.id', '=', 'prop_safs_floors.const_type_mstr_id')
            ->where('saf_id', $safId)
            ->where('prop_safs_floors.status', 1)
            ->get();
    }
}
