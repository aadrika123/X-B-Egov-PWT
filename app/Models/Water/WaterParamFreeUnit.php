<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterParamFreeUnit extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get free units for the consuemr demand
     */
    public function getFeeUnits($callParams)
    {
        return WaterParamFreeUnit::where('property_type_id', $callParams->propertyType)
            ->where('catagory', $callParams->areaCatagory)
            ->where('connection_size',$callParams->connectionSize)
            ->where('status', 1)
            ->first();
    }
}
