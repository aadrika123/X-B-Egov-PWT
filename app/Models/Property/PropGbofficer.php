<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropGbofficer extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Officer by officer Id
     * | function used in replicate saf function
     */
    public function getPropOfficerByOfficerId($officerId)
    {
        return PropOwner::find($officerId);
    }


    /**
     * | Edit Owner
     */
    public function editOfficer($safOfficer)
    {
        $owner = PropGbofficer::find($safOfficer->id);
        $req = $this->reqOfficer($safOfficer);
        $owner->update($req);
    }

    /**
     * | Post New Owner
     */
    public function postOfficer($safOfficer)
    {
        $owner = new PropGbofficer();
        $req = $this->reqOfficer($safOfficer);
        $owner->create($req);
    }

    /**
     * | Request for Post Owner Details or Edit
     */
    public function reqOfficer($req)
    {
        return [
            'property_id' => $req->property_id,
            'saf_id' => $req->saf_id,
            'officer_name' => $req->officer_name,
            'designation' => $req->designation,
            'mobile_no' => $req->mobile_no,
            'email' => $req->email,
            'address' => $req->address,
            'ulb_id' => $req->ulb_id,
            'user_id' => $req->user_id,
        ];
    }

    /**
     * | Get Officer by SAF Id
     */
    public function getOfficerBySafId($safId)
    {
        return PropActiveGbOfficer::select(
            'officer_name',
            'designation',
            'mobile_no'
        )
            ->where('saf_id', $safId)
            ->first();
    }
}
