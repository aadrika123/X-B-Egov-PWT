<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterReconnectConsumer extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    #get consumer 
    public function getConsumerDetails($consumerId)
    {
        return self::select(
            'water_reconnect_consumers.id'
        )
            ->where('water_reconnect_consumers.status', 1)
            ->where('water_reconnect_consumers.consumer_id', $consumerId);
    }
}
