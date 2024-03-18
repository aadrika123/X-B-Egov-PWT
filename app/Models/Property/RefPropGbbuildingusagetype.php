<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefPropGbbuildingusagetype extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get GB building usage types
     */
    public function getGbbuildingusagetypes()
    {
        return RefPropGbbuildingusagetype::select(
            'id',
            DB::raw('INITCAP(building_type) as building_type'),
            'status'
        )
            ->get();
    }
}
