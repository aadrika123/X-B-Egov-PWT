<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterTempDisconnection extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    public function getActiveReqById($id)
    {
        return WaterTempDisconnection::where('consumer_id', $id)
            ->where('status', 1);
    }

    public function fullDetails($req)
    {
        return WaterTempDisconnection::where('consumer_id', $req->applicationId)
            ->where('status', 1);
    }

}
