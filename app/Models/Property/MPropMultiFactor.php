<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MPropMultiFactor extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Multi Factors by usage type
     */
    public function getMultiFactorsByUsageType($usageTypeId)
    {
        return MPropMultiFactor::where('usage_type_id', $usageTypeId)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Get All Multi Factors
     */
    public function multiFactorsLists()
    {
        return MPropMultiFactor::where('status', 1)
            ->get();
    }
}
