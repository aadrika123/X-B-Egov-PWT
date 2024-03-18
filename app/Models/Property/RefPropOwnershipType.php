<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefPropOwnershipType extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Property Ownership Types
     */
    public function getPropOwnerTypes()
    {
        return RefPropOwnershipType::select(
            'id',
            DB::raw('INITCAP(ownership_type) as ownership_type')
        )
            ->where('status', 1)
            ->get();
    }
}
