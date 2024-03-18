<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MPropBuildingRentalrate extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Rental Rate by 
     */
    public function getRentalRates()
    {
        return MPropBuildingRentalrate::select('id', 'prop_road_type_id', 'construction_types_id', 'rate', 'effective_date', 'status')
            ->where('status', 1)
            ->get();
    }
}
