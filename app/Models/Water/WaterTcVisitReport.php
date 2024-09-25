<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterTcVisitReport extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    /**
     * apply for akola 
     */
    public function addTcVisitRecord($req)
    {
        $waterTcVisit = new WaterTcVisitReport();
        $waterTcVisit->ulb_id                    = $req->ulbId ?? 2;
        $waterTcVisit->consumer_id               = $req->consumerId;
        $waterTcVisit->property_no               = $req->propertyNo;
        $waterTcVisit->consumer_no               = $req->ConsumerNo;
        $waterTcVisit->holding_no                = $req->holdingNo;
        $waterTcVisit->mobile_no                 = $req->moblieNo;
        $waterTcVisit->address                   = $req->address;
        $waterTcVisit->meter_no                  = $req->meterNo;
        $waterTcVisit->meter_digit               = $req->meterDigit;
        $waterTcVisit->ward_mstr_id              = $req->wardId;
        $waterTcVisit->zone_mstr_id              = $req->zoneId;
        $waterTcVisit->category                  = $req->category;
        $waterTcVisit->remarks                   = $req->remarks;
        $waterTcVisit->citizen_comments          = $req->citizenComment;
        $waterTcVisit->last_meter_reading        = $req->meterReading;
        $waterTcVisit->citizen_comments          = $req->citizenComment;
        $waterTcVisit->longitude                 = $req->longitude;
        $waterTcVisit->latitude                  = $req->latitude;
        $waterTcVisit->connection_type           = $req->connection_type;
        $waterTcVisit->demand_amount             = $req->demandAmount;
        $waterTcVisit->save();
        return $waterTcVisit;
    }
}
