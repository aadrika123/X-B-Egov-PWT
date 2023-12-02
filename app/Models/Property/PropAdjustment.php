<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropAdjustment extends Model
{
    use HasFactory;
    protected $guarded = [];


    /**
     * | Store new Adjustment
     */
    public function store($req)
    {
        PropAdjustment::create($req);
    }

    public function getAdjustmentAmt($propId)
    {
        return self::where("prop_id",$propId)
               ->where("status",1)
               ->get();
    }
    public function getAdjustmentAmtByTrId($tranId)
    {
        return self::where("tran_id",$tranId)
               ->where("status",1) 
               ->get();
    }

    public function deactivateAdjustmentAmtByTrId($tranId)
    {
        return self::where("tran_id",$tranId)->update([
            "status"=>0
        ]);
    }
}
