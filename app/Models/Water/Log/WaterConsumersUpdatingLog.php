<?php

namespace App\Models\Water\Log;

use App\Models\water\waterParamPropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumersUpdatingLog extends Model
{
    use HasFactory;    
    public $timestamps = false;
    protected $connection = 'pgsql_water';

    public function getOwners()
    {
        return $this->hasMany(WaterConsumerOwnerUpdatingLog::class,"consumers_updating_log_id","id")->get();
    }

    public function getProperty()
    {
        return $this->hasOne(waterParamPropertyType::class,"id","property_type_id")->first();
    }

}
