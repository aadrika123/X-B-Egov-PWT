<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Location extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        $mlocations = new Location();
        $nowTime    = Carbon::now()->format('h:i:s A');
        $mlocations->tc_id     = $req->tcId;
        $mlocations->latitude  = $req->latitude;
        $mlocations->longitude = $req->longitude;
        $mlocations->altitude  = $req->altitude;
        $mlocations->time      = $nowTime;
        $mlocations->save();
        return $mlocations->id;
    }
    public function getTcDetails($tcId)
    {
        return Location::select(
            'locations.latitude',
            'locations.longitude',
            'locations.altitude',
            DB::raw('DATE(locations.created_at) as created_date'),  
            'locations.created_at',
            'locations.time',
            'locations.created_at',
            'users.user_name',
            'users.name'
        )
        ->leftJoin('users', 'users.id', '=', 'locations.tc_id')
        ->where('locations.tc_id', $tcId);
    }

    public function getTcVisitingListORM()
    {
        return self::select(
            'locations.latitude',
            'locations.longitude',
            'locations.altitude',
            'locations.action_type',
            'locations.ref_id',
            'locations.module_id',
            "module_masters.module_name",
            DB::raw('DATE(locations.created_at) as created_date'),  
            'locations.created_at',
            'locations.time',
            'locations.created_at',
            'users.user_name',
            'users.name'
        )
        ->leftJoin('users', 'users.id', '=', 'locations.tc_id')
        ->leftJoin('module_masters', 'module_masters.id', '=', 'locations.module_id')
        ->orderBy("locations.id", "DESC")
        ->orderBy("users.id");
    }
}
