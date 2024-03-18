<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropHarvestingGeotagUpload extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [''];

    /**
     * |
     */
    public function add($req)
    {
        PropHarvestingGeotagUpload::create($req);
    }

    public function getLatLong($applicationId)
    {
        return PropHarvestingGeotagUpload::where('application_id', $applicationId)
            ->orderbydesc('id')
            ->first();
    }
}
