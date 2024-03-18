<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropTransactionDeactivateDtl extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];
    public $timestamps = false;
}
