<?php

namespace App\Models\Water\Log;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerOwnerUpdatingLog extends Model
{
    use HasFactory;    
    public $timestamps = false;
    protected $connection = 'pgsql_water';

    public function getConsumer()
    {
        return $this->belongsTo(WaterConsumersUpdatingLog::class,"id","consumers_updating_log_id")->first();
    }
    
}
