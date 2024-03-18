<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class RefPropOccupancyFactor extends PropParamModel #Model
{
    use HasFactory;
    private $_redis;

    public function __construct()
    {
        $this->_redis = Redis::connection();
    }
    /**
     * | Get Occupancy Factors
     */
    public function getOccupancyFactors()
    {
        $occupancyFactors = json_decode(Redis::get('occupancyFactors'));
        if (!$occupancyFactors) {
            $occupancyFactors = self::where('status', 1)
                ->get();
            Redis::set('occupancyFactors', json_encode($occupancyFactors));
        }
        return $occupancyFactors;
    }
}
