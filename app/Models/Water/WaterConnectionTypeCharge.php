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
    public function getChargesByIds($id)
    {
        return WaterConnectionTypeCharge::where('id', $id)
            ->where('status', 1)
            ->first();
    }
}

