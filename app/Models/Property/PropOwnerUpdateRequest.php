<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PropOwnerUpdateRequest extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];
    public function store(array $req)
    {
        $reqs = [
            "request_id"=>$req["requestId"],
            "owner_id"=>$req["ownerId"],
            "property_id"=>$req["propId"],
            "logs"=>$req["logs"],
            "saf_id"=>$req["safId"],
            "owner_name"=>Str::upper($req['ownerName']??null),
            "owner_name_marathi"=>$req['ownerNameMarathi'??null],
            "guardian_name_marathi"=>$req['guardianNameMarathi']??null,
            "guardian_name"=>Str::upper($req['guardianName']??null),
            "relation_type"=>$req['relation']??null,
            "mobile_no"=>$req['mobileNo']??null,
            "email"=>$req['email']??null,
            "pan_no"=>$req['pan']??null,
            "aadhar_no"=>$req['aadhar']??null,
            "gender"=>$req['gender']??null,
            "dob"=>$req['dob']??null,
            "is_armed_force"=>$req['isArmedForce']??null,
            "is_specially_abled"=>$req['isSpeciallyAbled']??null,
            "user_id"=>$req["userId"],
        ];
        return PropOwnerUpdateRequest::create($reqs)->id;   
    }

}
