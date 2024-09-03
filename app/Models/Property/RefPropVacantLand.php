<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefPropVacantLand extends PropParamModel
{
    use HasFactory;
    public function propPropertyVacantLandType()
    {
        return RefPropVacantLand::select(
            'id',
            'vacant_land_type'
        )
            ->where('status', 1)
            ->get();
    }
}
