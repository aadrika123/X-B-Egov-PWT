<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropRejectedDeactivationRequest extends PropParamModel #Model
{
    use HasFactory;
    public $timestamps=false;
}
