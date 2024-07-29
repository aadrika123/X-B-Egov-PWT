<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConnectionTypeCharge extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get the Connection charges By array of ids
     * | @param id
     */
    public function getChargesByIds($tabSize)
    {
        return WaterConnectionTypeCharge::where('tab_size', $tabSize)->where('status', true)->first();
    }
}
