<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiMaster extends Model
{
    use HasFactory;
    protected $connection = "pgsql_master";

    /**
     * | get Api with Advertisment 
     * | ADV = 76
     */
    public function getAdvApi()
    {
        $adv = 76;
        return ApiMaster::select(
            'end_point'
        )
            ->where('id', $adv)
            ->first();
    }

    /**
     * | Get pet module api
     */
    public function getPetApi()
    {
        $adv = 461;
        return ApiMaster::select(
            'end_point'
        )
            ->where('id', $adv)
            ->first();
    }

    /**
     * | get for water tanker
     */
    public function getApiEndpoint($id)
    {
        return ApiMaster::select(
            'end_point'
        )
            ->where('id', $id)
            ->first();
    }
}
