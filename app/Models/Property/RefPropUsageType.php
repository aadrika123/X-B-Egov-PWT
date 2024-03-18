<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Illuminate\Support\Facades\DB;

class RefPropUsageType extends PropParamModel #Model
{
    use HasFactory;

    public function propUsageType()
    {
        return RefPropUsageType::select(
            'id',
            DB::raw('INITCAP(usage_type) as usage_type'),
            'usage_code',
            'usage_type_hn',
            'usage_type_mr'
        )
            ->where('status', 1)
            ->get();
    }


    public function propAllUsageType()
    {
        return RefPropUsageType::select(
            'id',
            DB::raw('INITCAP(usage_type) as usage_type'),
            'usage_code'
        )
            ->get();
    }
}
