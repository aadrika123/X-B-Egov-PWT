<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropActiveObjectionOwner extends PropParamModel #Model
{
    use HasFactory;

    public function getOwnerDetail($objId)
    {
        return PropActiveObjectionOwner::select('*')
            ->where('objection_id', $objId)
            ->get();
    }

    public function getOwnerEditDetail($objId)
    {
        return PropActiveObjectionOwner::select('*')
            ->where('objection_id', $objId)
            ->first();
    }
}
