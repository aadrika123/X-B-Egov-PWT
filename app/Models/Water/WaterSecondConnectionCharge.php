<?php

namespace App\Models\water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterSecondConnectionCharge extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    
    public function saveCharges($refrequest){
    $watercharges= new WaterSecondConnectionCharge();
    $watercharges->consumer_id     =$refrequest['consumerId'];
    $watercharges->amount          =$refrequest['amount'];
    $watercharges->charge_category = $refrequest['chargeCategory'];
    $watercharges->paid_status     = 1;
    $watercharges->save();
    return $watercharges;

}
 /**
     * | Get Consumer charges by application id
     */
    public function getConsumerChargesById($applicationId)
    {
        return WaterSecondConnectionCharge::where('consumer_id', $applicationId)
            ->where('status', 1);
    }   
   }



