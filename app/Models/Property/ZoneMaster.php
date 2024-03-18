<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneMaster extends PropParamModel #Model
{
    use HasFactory;

    public function getZone()
    {
        return ZoneMaster::select('id', 'zone_name')
            ->where('status', 1)
            ->get();
    }
    public function createZoneName($zoneId)
    {
        $name="";
        switch($zoneId)
        {
            case(1) : $name="East";
                      break;
            case(2): $name="West";
                      break;
            case(3): $name="North";
                      break;
            case(4): $name="South";
                      break;
        }
        return $name;
        
        
    }
}
