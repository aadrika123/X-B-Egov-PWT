<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefPropGbpropusagetype extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get GB prop usage types
     */
    public function getGbpropusagetypes()
    {
        return RefPropGbpropusagetype::select(
            'id',
            DB::raw('INITCAP(prop_usage_type) as prop_usage_type'),
        )
            ->get();
    }
}
