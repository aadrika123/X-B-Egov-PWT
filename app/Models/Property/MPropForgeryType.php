<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MPropForgeryType extends PropParamModel #Model
{
    use HasFactory;

    public function forgeryType()
    {
        return MPropForgeryType::select('id', 'type')
            ->where('status', true)
            ->orderby('id')
            ->get();
    }
}
