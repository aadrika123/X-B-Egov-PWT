<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Location extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $guarded = [];

    # get tc details 
    public function getTcDetails($tcId)
    {
        return Location::select(
            'locations.latitude',
            'locations.longitude',
            'locations.altitude',
            DB::raw('DATE(locations.created_at) as created_date'),
            'locations.created_at',
            'users.user_name',
            'users.name',

        )
            ->leftjoin('users', 'users.id', '=', 'locations.tc_id')
            ->where('locations.tc_id', $tcId);
    }
}
