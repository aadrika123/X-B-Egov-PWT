<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerComplain extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    public function AddConsumerComplain($request, $deactivatedDetails)
    {
        $waterConsumerComplain = new WaterConsumerComplain();
        $waterConsumerComplain->consumer_id                              = $request->consumerId;
        $waterConsumerComplain->application_id                           = $deactivatedDetails['id'];
        $waterConsumerComplain->respodent_name                           = $request->name;
        $waterConsumerComplain->mobile_no                                = $request->mobileNo;
        $waterConsumerComplain->city                                     = $request->city ?? "Akola";
        $waterConsumerComplain->district                                 = $request->district ?? "Akola";
        $waterConsumerComplain->state                                    = $request->state ?? "MAHARASTRA";
        $waterConsumerComplain->address                                  = $request->address;
        $waterConsumerComplain->zone_id                                  = $request->zoneId;
        $waterConsumerComplain->ward_no                                  = $request->wardNo;
        $waterConsumerComplain->comsumer_no                              = $request->consumerNo;
        $waterConsumerComplain->save();
        return $waterConsumerComplain;
    }
}
