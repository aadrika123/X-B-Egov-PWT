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
        $waterTcVisit->owner_name                = $req->applicantName;
        $waterTcVisit->property_type             = $req->propertyType;
        $waterTcVisit->save();
        return $waterTcVisit;
    }
    public function getDetailsRecords($request)
    {
        return self::select(
            'water_tc_visit_reports.consumer_no',
            'water_tc_visit_reports.remarks',
            'water_tc_visit_reports.citizen_comments',
            'water_tc_visit_reports.relative_path',
            'water_tc_visit_reports.document',
            'water_tc_visit_reports.category',
            'water_tc_visit_reports.holding_no',
            'water_tc_visit_reports.address',
            'water_tc_visit_reports.user_type',
            'water_tc_visit_reports.property_no',
            'water_tc_visit_reports.connection_type',
            'water_tc_visit_reports.last_meter_reading',
            'water_tc_visit_reports.longitude',
            'water_tc_visit_reports.latitude',
            'water_tc_visit_reports.demand_amount',
            'water_tc_visit_reports.owner_name',
            'water_tc_visit_reports.property_type',

        )
            ->join('zone_masters', 'zone_masters.id', 'water_tc_visit_reports.zone_mstr_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'water_tc_visit_reports.ward_mstr_id')
            ->where('water_tc_visit_reports.id', $request->applicationId);
    }
}
