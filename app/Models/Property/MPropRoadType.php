<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MPropRoadType extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Road Type by road Width
     */
    public function getRoadTypeByRoadWidth($roadWidth)
    {
        $query = "SELECT * FROM m_prop_road_types
                WHERE range_from_sqft<=ROUND($roadWidth)
                ORDER BY range_from_sqft DESC LIMIT 1";
        return DB::select($query);
    }
}
