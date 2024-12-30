<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterDemandDeactivateLog extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];
    /**
     * | store deactivate demands 
     */
    public function saveDeativateDemands($request, $deactivatedDemandIds, $demandFromDate, $demandUptoDate, $documentPath,$lastMeterreading,$demandAmount)
    {
        $mWaterDemandDeactivate = new WaterDemandDeactivateLog();
        $mWaterDemandDeactivate->consumer_id               = $request->consumerId;
        $mWaterDemandDeactivate->demand_id                 = implode(',', $deactivatedDemandIds);
        $mWaterDemandDeactivate->generation_date           = $request->generationDate;
        $mWaterDemandDeactivate->meter_reading             = $lastMeterreading->initial_reading ?? null;
        $mWaterDemandDeactivate->demand_from               = $demandFromDate ?? null;
        $mWaterDemandDeactivate->demand_upto               = $demandUptoDate ?? 0;
        $mWaterDemandDeactivate->amount                    = $demandAmount ?? 0;
        $mWaterDemandDeactivate->status_before             = 1;
        $mWaterDemandDeactivate->status_after              = 0;                        // Static for meter connection
        $mWaterDemandDeactivate->document_path             = $documentPath['relaivePath'];                          // For fixed connection
        $mWaterDemandDeactivate->document                  = $documentPath['document'];                          // For fixed connection
        $mWaterDemandDeactivate->logged_at                 = Carbon::now()->format('Y-m-d');
        $mWaterDemandDeactivate->user_id                   = Auth()->user() ? auth()->user()->id : null;
        $mWaterDemandDeactivate->user_type                 = Auth()->user() ? auth()->user()->user_type : null;
        $mWaterDemandDeactivate->save();
        return $mWaterDemandDeactivate->id;
    }
}
