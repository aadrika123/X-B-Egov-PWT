<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Exception;

class RefPropObjectionType extends PropParamModel #Model
{
    use HasFactory;

    //objection type master data
    public function objectionType()
    {
        $objectionType = RefPropObjectionType::where('status', 1)
            ->select('id', 'type')
            ->get();
        return $objectionType;
    }
}
