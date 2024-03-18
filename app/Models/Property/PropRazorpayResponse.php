<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropRazorpayResponse extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    // Save
    public function store(array $req)
    {
        PropRazorpayResponse::create($req);
    }
}
