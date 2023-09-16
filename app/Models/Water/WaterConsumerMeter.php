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
            DB::raw("concat(relative_path,'/',meter_doc) as doc_path"),
            'water_consumer_meters.*',
        )
            ->leftjoinSub(
                DB::connection('pgsql_water')
                    ->table('water_consumer_initial_meters')
                    ->select('*')
                    ->where('consumer_id', '=', $consumerId)
                    ->orderBy('id', 'desc')
                    ->skip(1)
                    ->take(1),
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
            $installationDate = Carbon::now();
        }
        if ($req->connectionType == $refConnectionType['Fixed']) {
            $meterStatus = 0;
        }

        $mWaterConsumerMeter = new WaterConsumerMeter();
        $mWaterConsumerMeter->consumer_id               = $req->consumerId;
        $mWaterConsumerMeter->connection_date           = $req->connectionDate;
        $mWaterConsumerMeter->emp_details_id            = auth()->user()->id;
        $mWaterConsumerMeter->connection_type           = $req->connectionType;
        $mWaterConsumerMeter->meter_no                  = $req->meterNo ?? null;
        $mWaterConsumerMeter->meter_intallation_date    = $installationDate ?? null;
        $mWaterConsumerMeter->initial_reading           = $req->newMeterInitialReading ?? null;
        $mWaterConsumerMeter->meter_status              = $meterStatus ?? 1;                        // Static for meter connection
        $mWaterConsumerMeter->rate_per_month            = $fixedRate ?? 0;                          // For fixed connection
        $mWaterConsumerMeter->relative_path             = $documentPath['relaivePath'];
        $mWaterConsumerMeter->meter_doc                 = $documentPath['document'];
        $mWaterConsumerMeter->save();
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
        $mWaterConsumerMeter->connection_type      = $refrequest['connectionType'];
        $mWaterConsumerMeter->meter_no             = $meta['meterNo'];


        $mWaterConsumerMeter->save();
        return $mWaterConsumerMeter;
    }
}
