<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerOwner extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get Consumer Details According to ConsumerId
     * | @param ConsumerId
     * | @return list / List of owners
     */
    public function getConsumerOwner($consumerId)
    {
        return WaterConsumerOwner::where('status', true)
            ->where('consumer_id', $consumerId);
    }

    /**
     * save owner details for akola 
     */

    public function saveConsumerOwner($req, $refRequest)
    {
        $waterConsumerOwner   = new WaterConsumerOwner();
        $waterConsumerOwner->consumer_id         = $refRequest['consumerId'];
        $waterConsumerOwner->applicant_name      = $req->OwnerName;
        $waterConsumerOwner->guardian_name       = $req->GuardianName;
        $waterConsumerOwner->mobile_no           = $req->MobileNo;
        $waterConsumerOwner->email               = $req->Email;
        $waterConsumerOwner->save();
        return $waterConsumerOwner;
    }
    public function editConsumerOwnerDtls($request)
    {
        $waterConsumerOwner = WaterConsumerOwner::findorfail($request->consumerId);
        $waterConsumerOwner->applicant_name      =  $request->applicantName      ?? $waterConsumerOwner->applicant_name;
        $waterConsumerOwner->guardian_name      =  $request->guardianName      ?? $waterConsumerOwner->guardian_name;
        $waterConsumerOwner->save();
    }
}
