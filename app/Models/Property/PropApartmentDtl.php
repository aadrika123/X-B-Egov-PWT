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

    /**
     * | Added Apartment
     */
    public function  store($req)
    {
      $mPropApartmentDtl = new PropApartmentDtl();
      $mPropApartmentDtl->apt_code                  = $req->apartmentCode;
      $mPropApartmentDtl->apartment_name            = $req->apartmentName;
      $mPropApartmentDtl->apartment_address         = $req->apartmentAddress;
      $mPropApartmentDtl->water_harvesting_status   = $req->waterHarvestingStatus;
      $mPropApartmentDtl->wtr_hrvs_image_file_name  = $req->waterHarvestingImage;
      $mPropApartmentDtl->apt_image_file_name       = $req->aptImage;
      $mPropApartmentDtl->ward_mstr_id              = $req->ward;
      $mPropApartmentDtl->is_blocks                 = $req->isBlocks;
      $mPropApartmentDtl->no_of_block               = $req->blocks;
    //   $mPropApartmentDtl->road_type_mstr_id         = $req->road_type_mstr_id;
      $mPropApartmentDtl->created_at                = now();
      $mPropApartmentDtl->user_id                   = $req->userId;
      $mPropApartmentDtl->ulb_id                    = $req->ulbId;
      $mPropApartmentDtl->save();

    }
}
