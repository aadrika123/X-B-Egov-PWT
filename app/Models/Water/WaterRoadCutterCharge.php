<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterRoadCutterCharge extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get the charges 
     */
    public function getRoadCharges($raodtypeId)
    {
        return WaterRoadCutterCharge::where('id', $raodtypeId)->where('status', true)->first();
    }
}
