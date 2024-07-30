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
        $waterConsumerOwner->applicant_name      = $req->applicantName;
        $waterConsumerOwner->guardian_name       = $req->guardianName;
        $waterConsumerOwner->mobile_no           = $req->mobileNo;
        $waterConsumerOwner->email               = $req->email;
        $waterConsumerOwner->status              = true;
        $waterConsumerOwner->save();
        return $waterConsumerOwner;
    }
    public function editConsumerOwnerDtls($request, $userId)
    {
        $waterConsumerOwner = WaterConsumerOwner::findorfail($request->consumerId);
        $waterConsumerOwner->applicant_name       =  $request->applicant_name      ?? $waterConsumerOwner->applicant_name;
        $waterConsumerOwner->guardian_name        =  $request->guardian_name       ?? $waterConsumerOwner->guardian_name;
        $waterConsumerOwner->email                =  $request->email               ?? $waterConsumerOwner->email;
        $waterConsumerOwner->mobile_no            =  $request->mobile_no           ?? $waterConsumerOwner->mobile_no;
        $waterConsumerOwner->user_id              =  $userId;
        $waterConsumerOwner->save();
    }
    /**
     * |get Consumer Owner
     */
    public function getOwnerDtlById($consumerId)
    {
        return WaterConsumerOwner::where('consumer_id', $consumerId)
            ->first();
    }

    /**
     * save owner details for akola 
     */

    public function createOwner($consumerOwnedetails,$checkExist)
    {
        $waterConsumerOwner   = new WaterConsumerOwner();
        $waterConsumerOwner->consumer_id         = $consumerOwnedetails->consumer_id;
        $waterConsumerOwner->applicant_name      = $checkExist->new_name;
        $waterConsumerOwner->guardian_name       = $consumerOwnedetails->guardian_name;
        $waterConsumerOwner->mobile_no           = $consumerOwnedetails->mobile_no;
        $waterConsumerOwner->email               = $consumerOwnedetails->email;
        $waterConsumerOwner->status              = true;
        $waterConsumerOwner->save();
        return $waterConsumerOwner;
    }

     /**
     * |get consumer details 
     */
    public function getOwnerList($consumerId)
    {
        return WaterConsumerOwner::select(
            'id',
            'applicant_name',
            'guardian_name',
            'mobile_no',
            'email'
        )
            ->where('consumer_id', $consumerId)
            ->where('status', true);
    }
}
