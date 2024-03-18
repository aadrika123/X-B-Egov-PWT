<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropApartmentDtl extends PropParamModel #Model
{
    use HasFactory;

    /**
     * |
     */
    public function apartmentList($req)
    {
        return PropApartmentDtl::select('id', 'apt_code', 'apartment_name')
            ->where('ward_mstr_id', $req->wardMstrId)
            ->where('ulb_id', $req->ulbId)
            ->get();
    }

    /**
     * | Get Apartment Road Type by ApartmentId
     */
    public function getAptRoadTypeById($id, $ulbId)
    {
        return PropApartmentDtl::where('id', $id)
            ->where('ulb_id', $ulbId)
            ->select('road_type_mstr_id')
            ->firstOrFail();
    }

    /**
     * | Get apartment details by id
     * | @param
     */
    public function getApartmentById($apartmentId)
    {
        return PropApartmentDtl::where('id', $apartmentId)
            ->where('status', 1)
            ->first();
    }
}
