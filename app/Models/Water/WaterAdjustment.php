<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterAdjustment extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];

    public function store($req)
    {
        WaterAdjustment::create($req);
    }

    /**
     * | Get the Adjusted amount for reslative id
     * | @param
     * | @return 
        | Serial No : 01
     */
    public function getAdjustedDetails($consumerId)
    {
        return WaterAdjustment::where('related_id', $consumerId)
            ->where("status", 1);
    }

    /**
     * | Save the adjustment amount 
     * | @param
     * | @param
        | Serial No : 02
     */
    public function saveAdjustment($waterTrans, $request, $adjustmentFor)
    {
        $mWaterAdjustment = new WaterAdjustment();
        $mWaterAdjustment->related_id       = $request->consumerId;
        $mWaterAdjustment->adjustment_for   = $adjustmentFor;
        $mWaterAdjustment->tran_id          = $waterTrans['id'];
        $mWaterAdjustment->amount           = $request->amount;
        $mWaterAdjustment->user_id          = $request->userId;
        $mWaterAdjustment->remarks          = $request->remarks;
        $mWaterAdjustment->save();
    }
}
