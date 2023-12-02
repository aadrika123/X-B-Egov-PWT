<?php

namespace App\Models\water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerDemandRecord extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $connection = 'pgsql_water';
    
}
