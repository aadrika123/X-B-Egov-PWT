<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropIciciPaymentsResponse extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        $data = PropIciciPaymentsResponse::create($req);
        return $data;
    }
}
