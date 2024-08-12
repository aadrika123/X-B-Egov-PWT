<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterParamConnFee extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $connection = 'pgsql_water';

    /**
     * | get Calcullation charges 
     */
    public function getCallParameter($propertyId,)
    {
        if ($propertyId == 1 ||  $propertyId == 7) {
            return WaterParamConnFee::select(
                'property_type_id',
                'area_from_sqft',
                'area_upto_sqft',
                'conn_fee',
                'effective_date',
                'calculation_type'
            )
                ->where('property_type_id', $propertyId);
            // ->where('area_from_sqft', '<=', $refAreaInSqFt)
            // ->where('area_upto_sqft', '>=', $refAreaInSqFt);
        }
        return WaterParamConnFee::select(
            'property_type_id',
            'area_from_sqft',
            'area_upto_sqft',
            'conn_fee',
            'effective_date',
            'calculation_type'
        )
            ->where('property_type_id', $propertyId);
    }
}
