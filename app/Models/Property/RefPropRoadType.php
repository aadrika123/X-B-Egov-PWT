<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Illuminate\Support\Facades\DB;

class RefPropRoadType extends PropParamModel #Model
{
    use HasFactory;

    public function propRoadType()
    {

        return RefPropRoadType::select(
            'id',
            DB::raw('INITCAP(road_type) as road_type')
        )
            ->where('status', '1')
            ->get();
    }
}
