<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefPropFloor extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get All Property Types
     */
    public function getPropTypes()
    {
        return RefPropFloor::select(
            'id',
            DB::raw('INITCAP(floor_name) as floor_name')
        )
            ->where('status', 1)
            ->orderBy("id","ASC")
            ->get();
    }
}
