<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;
use Illuminate\Support\Facades\DB;

class RefPropConstructionType extends PropParamModel #Model
{
    use HasFactory;

    public function propConstructionType()
    {
        return RefPropConstructionType::select(
            'id',
            DB::raw('INITCAP(construction_type) as construction_type'),
            'category1_rate',
            'category2_rate',
            'category3_rate',
        )
            ->where('status', 1)
            ->get();
    }
}
