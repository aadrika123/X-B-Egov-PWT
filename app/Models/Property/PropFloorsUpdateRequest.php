<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropFloorsUpdateRequest extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store(array $req)
    {
        $reqs = [
            "request_id"=>$req["requestId"],
            "floor_id"=>$req["floorId"],
            "property_id"=>$req["propId"],
            "logs"=>$req["logs"],
            "saf_id"=>$req["safId"]??null,
            "floor_mstr_id"=>$req['floorNo'],
            "usage_type_mstr_id"=>$req['usageType'],
            "const_type_mstr_id"=>$req['constructionType'],
            "occupancy_type_mstr_id"=>$req['occupancyType'],
            "builtup_area"=>$req['buildupArea']??0,
            "date_from"=>$req['dateFrom']??null,
            "date_upto"=>$req['dateUpto']??null,
            "carpet_area"=>$req['carpetArea']??0,
            "prop_floor_details_id"=>$req['propFlooDetailsId']??null,
            "old_floor_id"=>$req['oldFloorId']??null,
            "saf_floor_id"=>$req['safFloorId']??null,
            "no_of_rooms"=>$req['noOfRooms']??null,
            "no_of_toilets"=>$req['noOfToilet']??null,
            "user_id"=>$req["userId"]??null,
        ];
        return PropFloorsUpdateRequest::create($reqs)->id;   
    }
}
