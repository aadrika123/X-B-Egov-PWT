<?php

namespace App\Models\Water\Log;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterConsumerOwnerUpdatingLog extends Model
{
    use HasFactory;    
    public $timestamps = false;
    protected $connection = 'pgsql_water';
}
