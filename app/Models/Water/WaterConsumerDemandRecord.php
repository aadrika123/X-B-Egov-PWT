<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerDemandRecord extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $connection = 'pgsql_water';

    public function getProperty()
    {
        return $this->hasOne(WaterPropertyTypeMstr::class,"id","property_type_id")->first();
    }
    
    public function getOwners()
    {
        return $this->hasMany(WaterConsumerOwner::class,"consumer_id","consumer_id")->get();
    }

    /**
     * | Set details 
     */
}
