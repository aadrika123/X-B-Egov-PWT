<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerInitialMeter extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];

    /**
     * | Get the Meter Reading and the meter details by consumer no
     */
    public function getmeterReadingAndDetails($consumerId)
    {
        return WaterConsumerInitialMeter::where('status', 1)
            ->where('consumer_id', $consumerId);
    }


    /**
     * | Save the consumer meter details when the monthely demand is generated
     * | @param request
     */
    public function saveConsumerReading($request, $meterDetails, $userDetails)
    {
        $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
        $mWaterConsumerInitialMeter->consumer_id        = $request->consumerId;
        $mWaterConsumerInitialMeter->initial_reading    = $request->finalRading;
        $mWaterConsumerInitialMeter->emp_details_id     = $userDetails['emp_id'] ?? null;
        $mWaterConsumerInitialMeter->citizen_id         = $userDetails['citizen_id'] ?? null;
        $mWaterConsumerInitialMeter->consumer_meter_id  = $meterDetails['meterId'];
        $mWaterConsumerInitialMeter->save();
    }

    /**
     * | Get second last meter details 
     */
    public function getSecondLastReading($consumerId, $id)
    {
        return WaterConsumerInitialMeter::where("consumer_id", $consumerId)
            ->where("status", 1)
            ->where("id", "<", $id)
            ->orderBy("id", "DESC")
            ->first();
    }

    /**
     * |
     */

    public function saveConsumerReadings($refrequest)
    {
        $mWaterConsumerInitialMeter  = new WaterConsumerInitialMeter();
        $mWaterConsumerInitialMeter->consumer_id      = $refrequest['consumerId'];
        $mWaterConsumerInitialMeter->initial_reading  = $refrequest['InitialMeter'];
        $mWaterConsumerInitialMeter->save();
        return $mWaterConsumerInitialMeter;
    }
    /**
     * | Get the Meter Reading and the meter details by consumer no
     */
    public function calculateUnitsConsumed($consumerId)
    {
        return WaterConsumerInitialMeter::select('*')
            ->where('consumer_id', $consumerId)
            ->where('status',1)
            ->orderByDesc('id')
            ->limit(2)
            ->get();
    }

    /**
     * | Update consumer initial meter details 
     */
    public function updateInitialMeter($consumerId, $updateDetails)
    {
        WaterConsumerInitialMeter::where('consumer_id', $consumerId)
            ->orderByDesc('id')
            ->first()
            ->update($updateDetails);
    }
}
