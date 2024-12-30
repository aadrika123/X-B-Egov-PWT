<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class WaterConsumerMeter extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];

    /**
     * | Get Meter reading using the ConsumerId
     * | @param consumerId
        | Recheck 
     */
    public function getMeterDetailsByConsumerId($consumerId)
    {
        return WaterConsumerMeter::select(
            DB::raw("concat(relative_path,'/',meter_doc) as doc_path"),
            'water_consumer_meters.*',
        )
            ->where('water_consumer_meters.consumer_id', $consumerId)
            ->where('water_consumer_meters.status', 1)
            ->orderByDesc('water_consumer_meters.id');
    }

    /**
     * | Get Meter reading using the ConsumerId
     * | @param consumerId
     */
    public function getMeterDetailsByConsumerIdV2($consumerId)
    {
        return WaterConsumerMeter::select(
            'subquery.initial_reading as ref_initial_reading',
            'subquery.created_at as ref_created_at', // Include created_at from subquery
            DB::raw("concat(relative_path, '/', meter_doc) as doc_path"),
            'water_consumer_meters.*'
        )
            ->leftJoinSub(
                DB::connection('pgsql_water')
                    ->table('water_consumer_initial_meters')
                    ->select('consumer_id', 'initial_reading', DB::raw('DATE(created_at) as created_at')) // Format created_at in the subquery
                    ->where('consumer_id', '=', $consumerId)
                    ->orderBy('id', 'desc')
                    ->skip(1) // Skip the most recent record
                    ->take(1), // Take the second latest record
                'subquery',
                function ($join) {
                    $join->on('subquery.consumer_id', '=', 'water_consumer_meters.consumer_id');
                }
            )
            ->where('water_consumer_meters.consumer_id', $consumerId)
            ->where('water_consumer_meters.status', 1)
            ->orderByDesc('water_consumer_meters.id');
    }

    /**
     * | Update the final Meter reading while Generation of Demand
     * | @param
     */
    public function saveMeterReading($req)
    {
        $mWaterConsumerMeter = WaterConsumerMeter::where('consumer_id', $req->consumerId)
            ->where('status', true)
            ->orderByDesc('id')
            ->first();

        $mWaterConsumerMeter->final_meter_reading = $req->finalRading;
        $mWaterConsumerMeter->save();
        return
            [
                "meterId" => $mWaterConsumerMeter->id,
                "meterNo" => $mWaterConsumerMeter->meter_no
            ];
    }

    /**
     * | Save Meter Details While intallation of the new meter 
     * | @param 
        | Get the fixed rate
     */
    public function saveMeterDetails($req, $documentPath, $fixedRate)
    {
        $meterStatus = null;
        $refConnectionType = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');
        if ($req->connectionType == $refConnectionType['Meter/Fixed']) {
            $req->connectionType = 1;
            $meterStatus = 0;
        }
        if ($req->connectionType == $refConnectionType['Meter']) {
            $installationDate = Carbon::parse($req->connectionDate); #Carbon::now();
        }
        if ($req->connectionType == $refConnectionType['Fixed']) {
            $meterStatus = 0;
        }

        $mWaterConsumerMeter = new WaterConsumerMeter();
        $mWaterConsumerMeter->consumer_id               = $req->consumerId;
        $mWaterConsumerMeter->connection_date           = $req->connectionDate;
        $mWaterConsumerMeter->emp_details_id            = Auth()->user() ? auth()->user()->id : null;
        $mWaterConsumerMeter->connection_type           = $req->connectionType;
        $mWaterConsumerMeter->meter_no                  = $req->meterNo ?? null;
        $mWaterConsumerMeter->meter_intallation_date    = $installationDate ?? null;
        $mWaterConsumerMeter->initial_reading           = $req->newMeterInitialReading ?? 0;
        $mWaterConsumerMeter->final_meter_reading       = $req->oldMeterFinalReading ?? 0;
        $mWaterConsumerMeter->meter_status              = $meterStatus ?? 1;                        // Static for meter connection
        $mWaterConsumerMeter->rate_per_month            = $fixedRate ?? 0;                          // For fixed connection
        $mWaterConsumerMeter->relative_path             = $documentPath['relaivePath'] ?? null;
        $mWaterConsumerMeter->meter_doc                 = $documentPath['document'] ?? null;
        $mWaterConsumerMeter->save();
        return $mWaterConsumerMeter->id;
    }
    /**
     * save meter details for akola 
     */

    public function saveInitialMeter($refrequest, $meta)
    {
        $mWaterConsumerMeter = new WaterConsumerMeter();
        $mWaterConsumerMeter->consumer_id          = $refrequest['consumerId'];
        $mWaterConsumerMeter->final_meter_reading  = $refrequest['InitialMeter'];
        $mWaterConsumerMeter->initial_reading      = $refrequest['InitialMeter'];
        $mWaterConsumerMeter->connection_type      = $refrequest['ConnectionType'];
        $mWaterConsumerMeter->meter_no             = $meta['meterNo'];


        $mWaterConsumerMeter->save();
        return $mWaterConsumerMeter;
    }
    /**
     * | Get consumer by consumer Id
     */
    public function getConsumerMeterDetails($consumerId)
    {
        return WaterConsumerMeter::where('consumer_id', $consumerId);
    }


    /**
     * | Update Consumer Meter details
     * | Send the updation details in array format
     */
    public function updateMeterDetails($consumerId, $updateDetails)
    {
        WaterConsumerMeter::where('id', $consumerId)
            ->orderByDesc('id')
            ->first()
            ->update($updateDetails);
    }

    /**
     * |update consumer meter
     */
    public function updateMeterNo($consumerOwnedetails, $checkExist)
    {
        WaterConsumerMeter::where('id', $consumerOwnedetails->consumer_id)
            ->orderByDesc('id')
            ->first()
            ->update([
                'meter_no' => $checkExist->meter_number,                                                 // Static
            ]);
    }
    /**
     * | Get consumer by consumer Id
     */
    public function checkMeterNo($request)
    {
        return WaterConsumerMeter::where('meter_no', $request->meterNo);
    }

    /**
     * |update consumer meter
     */
    public function updatePreviouReading($consumerId, $meterReading)
    {
       return  WaterConsumerMeter::where('consumer_id', $consumerId)
            ->orderByDesc('id')
            ->first()
            ->update([
                'final_meter_reading' => $meterReading,                                                 // Static
            ]);
    }
}
