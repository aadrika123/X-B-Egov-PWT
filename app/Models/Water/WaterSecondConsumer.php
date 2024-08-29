<?php

namespace App\Models\Water;

use App\Models\water\waterParamPropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WaterSecondConsumer extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $fillable = [
        'notice_no_1',
        'notice_no_2',
        'notice_no_3',
        'notice',
        'notice_2',
        'notice_3',
        'generated',
        'notice_1_generated_at',
        'notice_2_generated_at',
        'notice_3_generated_at',
    ];

    /**
     * | Get consumer by consumer Id
     */
    public function getConsumerDetailsById($consumerId)
    {
        return WaterSecondConsumer::where('id', $consumerId);
    }

    /**
     * apply for akola 
     */
    public function saveConsumer($req, $meta, $applicationNo)
    {
        $waterSecondConsumer = new WaterSecondConsumer();
        $waterSecondConsumer->ulb_id                    = $req->ulbId;
        $waterSecondConsumer->zone                      = $req->zoneId;
        $waterSecondConsumer->cycle                     = $req->Cycle;
        $waterSecondConsumer->property_no               = $req->propertyNo;
        $waterSecondConsumer->consumer_no               = $applicationNo;
        $waterSecondConsumer->mobile_no                 = $req->moblieNo;
        $waterSecondConsumer->address                   = $req->address;
        $waterSecondConsumer->landmark                  = $req->poleLandmark;
        $waterSecondConsumer->dtc_code                  = $req->mtcCode;
        $waterSecondConsumer->meter_make                = $req->meterMake;
        $waterSecondConsumer->meter_no                  = $req->meterNo;
        $waterSecondConsumer->meter_digit               = $req->meterDigit;
        $waterSecondConsumer->tab_size                  = $req->tapsize;
        $waterSecondConsumer->meter_state               = $req->meterState;
        $waterSecondConsumer->reading_date              = $req->ReadingDate;
        $waterSecondConsumer->connection_date           = $req->ConectionDate ? $req->ConectionDate : $req->connectionDate;
        $waterSecondConsumer->disconnection_date        = $req->DisconnectionDate;
        $waterSecondConsumer->disconned_reading         = $req->DisconnedDate;
        $waterSecondConsumer->book_no                   = $req->BookNo;
        $waterSecondConsumer->folio_no                  = $req->propertyNo;
        $waterSecondConsumer->no_of_connection          = $req->NoOfConnection;
        $waterSecondConsumer->is_meter_rented           = $req->IsMeterRented;
        $waterSecondConsumer->rent_amount               = $req->RentAmount;
        $waterSecondConsumer->total_installment         = $req->TotalInstallment;
        $waterSecondConsumer->nearest_consumer_no       = $req->NearestConsumerNo;
        $waterSecondConsumer->status                    = $meta['status'];
        $waterSecondConsumer->ward_mstr_id              = $meta['wardmstrId'];
        $waterSecondConsumer->category                  = $req->category;
        $waterSecondConsumer->property_type_id          = $req->propertyType;
        $waterSecondConsumer->meter_reading             = $req->MeterReading;
        $waterSecondConsumer->is_meter_working          = $req->IsMeterWorking;
        $waterSecondConsumer->connection_type_id        = $meta['connectionType'];
        $waterSecondConsumer->zone_mstr_id              = $req->zoneId;
        $waterSecondConsumer->ward_mstr_id              = $req->wardId;


        $waterSecondConsumer->save();
        return $waterSecondConsumer;
    }

    /**
     * get all details 
     */

    public function getallDetails($applicationId)
    {
        return  WaterSecondConsumer::select(
            'water_second_consumers.*'

        )
            ->where('water_second_consumers.id', $applicationId)
            ->get();
    }

    /**
     * | Get active request by request id 
     */
    public function getActiveReqById($id)
    {
        return WaterSecondConsumer::where('id', $id)
            ->where('status', 4);
    }

    /**
     * | get the water consumer detaials by consumr No
     * | @param consumerNo
     * | @var 
     * | @return 
     */
    public function getConsumerByItsDetails($req, $key, $refNo, $wardId, $zoneId, $zone)
    {
        return WaterSecondConsumer::select([
            'water_consumer_demands.id AS demand_id',
            DB::raw("
                CASE
                    WHEN water_consumer_demands.is_full_paid = true THEN 'Paid'
                    WHEN water_consumer_demands.is_full_paid = false THEN 'Unpaid'
                    ELSE 'unknown'
                END AS payment_status
            "),
            'water_consumer_demands.paid_status',
            'water_consumer_demands.consumer_id',
            'water_second_consumers.id AS id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.folio_no as property_no',
            DB::raw("ROUND(water_consumer_demands.due_balance_amount, 2) as balance_amount"),
            'water_second_consumers.address',
            'water_second_consumers.folio_no',
            'zone_masters.zone_name',
            DB::raw("string_agg(wco.applicant_name, ',') as owner_name"),
            DB::raw("string_agg(wco.mobile_no, ',') as mobile_no"),
            DB::raw("string_agg(wco.email, ',') as owner_email"),
            DB::raw("ulb_ward_masters.ward_name AS ward_mstr_id"),
        ])
            ->leftJoin(
                DB::raw("(SELECT DISTINCT ON (consumer_id) id,balance_amount,amount,consumer_id,paid_status,due_balance_amount,is_full_paid
                            FROM water_consumer_demands AS wcd
                            WHERE status = true
                            ORDER BY consumer_id,id DESC) AS water_consumer_demands
                "),
                function ($join) {
                    $join->on("water_consumer_demands.consumer_id", "=", "water_second_consumers.id");
                }
            )
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->join('water_consumer_owners as wco', 'water_second_consumers.id', '=', 'wco.consumer_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->where('water_second_consumers.status', 1)
            ->where('wco.status', true)
            ->where('water_second_consumers.' . $key, 'LIKE', '%' . $refNo . '%')
            ->when($wardId, function ($query) use ($wardId) {
                return $query->where('water_second_consumers.ward_mstr_id', $wardId);
            })
            ->when($zoneId, function ($query) use ($zoneId) {
                return $query->where('water_second_consumers.zone_mstr_id', $zoneId);
            })
            ->when($zone, function ($query) use ($zone) {
                return $query->where('water_second_consumers.zone', $zone);
            })
            ->groupBy(
                'water_consumer_demands.id',
                'water_consumer_demands.paid_status',
                'water_consumer_demands.balance_amount',
                'water_consumer_demands.amount',
                'water_consumer_demands.consumer_id',
                'water_second_consumers.id',
                'ulb_ward_masters.ward_name',
                'zone_masters.zone_name',
                'water_consumer_demands.due_balance_amount',
                'water_consumer_demands.is_full_paid'
            );
    }

    public function getConsumerByItsDetailsV2($req, $key, $refNo, $wardId, $zoneId, $zone)
    {
        return WaterSecondConsumer::select([
            DB::raw("
                water_consumer_demands.id AS demand_id,
                    water_consumer_demands.consumer_id,
                    water_consumer_demands.due_balance_amount,
                CASE
                    WHEN water_consumer_demands.due_balance_amount <=0  THEN true
                    else false
                    END AS paid_status,
                CASE
                    WHEN water_consumer_demands.due_balance_amount >0  THEN 'Unpaid'
                    else 'Paid'
                    END AS payment_status,
                water_consumer_demands.consumer_id,
                ROUND(water_consumer_demands.due_balance_amount, 2) as balance_amount,
                water_second_consumers.id AS id,
                water_second_consumers.consumer_no,
                water_second_consumers.folio_no as property_no,
                water_second_consumers.address,
                water_second_consumers.folio_no,
                zone_masters.zone_name,
                ulb_ward_masters.ward_name,
                wco.applicant_name as owner_name,
                wco.mobile_no as mobile_no               
                "),

        ])
            ->leftJoin(
                DB::raw("(
                            SELECT consumer_id,
                                string_agg(wcd.id::text,', ') as id,
                                sum(balance_amount) as balance_amount ,
                                sum(amount) amount,	
                                sum(due_balance_amount) as due_balance_amount
                            FROM water_consumer_demands AS wcd
                            WHERE status = true and  balance_amount >0 
                            group by consumer_id
                            ORDER BY consumer_id
                        ) AS water_consumer_demands
                "),
                function ($join) {
                    $join->on("water_consumer_demands.consumer_id", "=", "water_second_consumers.id");
                }
            )
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->join(DB::raw("(
                    select string_agg(wco.applicant_name, ',') as applicant_name,
                        string_agg(wco.mobile_no, ',') as mobile_no,
                        string_agg(wco.email, ',') as email,
                        consumer_id
                    from water_consumer_owners wco
                    where status = true
                    group by consumer_id
                ) as wco"), 'water_second_consumers.id', '=', 'wco.consumer_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->where('water_second_consumers.status', 1)
            ->where('water_second_consumers.' . $key, 'LIKE', '%' . $refNo . '%')
            ->when($wardId, function ($query) use ($wardId) {
                return $query->where('water_second_consumers.ward_mstr_id', $wardId);
            })
            ->when($zoneId, function ($query) use ($zoneId) {
                return $query->where('water_second_consumers.zone_mstr_id', $zoneId);
            })
            ->when($zone, function ($query) use ($zone) {
                return $query->where('water_second_consumers.zone', $zone);
            });
    }
    public function getConsumerByItsDetailsV4($req, $key, $refNo, $wardId, $zoneId, $zone)
    {
        return WaterSecondConsumer::select([
            'water_consumer_demands.id AS demand_id',
            'water_second_consumers.id as consumer_id', // Always return this as consumer_id
            'water_consumer_demands.due_balance_amount',
            DB::raw("CASE
                    WHEN water_consumer_demands.due_balance_amount <= 0 THEN true
                    ELSE false
                 END AS paid_status"),
            DB::raw("CASE
                    WHEN water_consumer_demands.due_balance_amount > 0 THEN 'Unpaid'
                    ELSE 'Paid'
                 END AS payment_status"),
            DB::raw("ROUND(water_consumer_demands.due_balance_amount, 2) as balance_amount"),
            'water_second_consumers.consumer_no',
            'water_second_consumers.folio_no as property_no',
            'water_second_consumers.address',
            'zone_masters.zone_name',
            'ulb_ward_masters.ward_name',
            'wco.applicant_name as owner_name',
            'wco.mobile_no as mobile_no',
            'water_approval_application_details.application_no'
        ])
            ->leftJoin(
                DB::raw("(
            SELECT consumer_id,
                string_agg(wcd.id::text, ', ') as id,
                sum(balance_amount) as balance_amount,
                sum(amount) as amount,
                sum(due_balance_amount) as due_balance_amount
            FROM water_consumer_demands AS wcd
            WHERE status = true AND balance_amount > 0 
            GROUP BY consumer_id
            ORDER BY consumer_id
        ) AS water_consumer_demands"),
                function ($join) {
                    $join->on("water_consumer_demands.consumer_id", "=", "water_second_consumers.id");
                }
            )
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->join('water_approval_application_details', 'water_approval_application_details.id', '=', 'water_second_consumers.apply_connection_id')
            ->join(DB::raw("(
        SELECT string_agg(wco.applicant_name, ',') as applicant_name,
            string_agg(wco.mobile_no, ',') as mobile_no,
            string_agg(wco.email, ',') as email,
            consumer_id
        FROM water_consumer_owners wco
        WHERE status = true
        GROUP BY consumer_id
    ) as wco"), 'water_second_consumers.id', '=', 'wco.consumer_id')
            ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')
            ->where('water_second_consumers.status', 1)
            ->where('water_approval_application_details.' . $key, 'LIKE', '%' . $refNo . '%')
            ->when($wardId, function ($query) use ($wardId) {
                return $query->where('water_second_consumers.ward_mstr_id', $wardId);
            })
            ->when($zoneId, function ($query) use ($zoneId) {
                return $query->where('water_second_consumers.zone_mstr_id', $zoneId);
            })
            ->when($zone, function ($query) use ($zone) {
                return $query->where('water_second_consumers.zone', $zone);
            });
    }

    /**
     * |consumer search by consumer name
     */
    public function getConsumerByItsDetailsV3($req, $key, $refNo, $wardId, $zoneId, $zone)
    {
        return WaterSecondConsumer::select([
            DB::raw("
                water_consumer_demands.id AS demand_id,
                    water_consumer_demands.consumer_id,
                    water_consumer_demands.due_balance_amount,
                CASE
                    WHEN water_consumer_demands.due_balance_amount <=0  THEN true
                    else false
                    END AS paid_status,
                CASE
                    WHEN water_consumer_demands.due_balance_amount >0  THEN 'Unpaid'
                    else 'Paid'
                    END AS payment_status,
                water_consumer_demands.consumer_id,
                ROUND(water_consumer_demands.due_balance_amount, 2) as balance_amount,
                water_second_consumers.id AS id,
                water_second_consumers.consumer_no,
                water_second_consumers.folio_no as property_no,
                water_second_consumers.address,
                water_second_consumers.folio_no,
                zone_masters.zone_name,
                ulb_ward_masters.ward_name,
                wco.applicant_name as owner_name,
                wco.mobile_no as mobile_no               
                "),

        ])
            ->leftJoin(
                DB::raw("(
                            SELECT consumer_id,
                                string_agg(wcd.id::text,', ') as id,
                                sum(balance_amount) as balance_amount ,
                                sum(amount) amount,	
                                sum(due_balance_amount) as due_balance_amount
                            FROM water_consumer_demands AS wcd
                            WHERE status = true and  balance_amount >0 
                            group by consumer_id
                            ORDER BY consumer_id
                        ) AS water_consumer_demands
                "),
                function ($join) {
                    $join->on("water_consumer_demands.consumer_id", "=", "water_second_consumers.id");
                }
            )
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->join(DB::raw("(
                    select string_agg(wco.applicant_name, ',') as applicant_name,
                        string_agg(wco.mobile_no, ',') as mobile_no,
                        string_agg(wco.email, ',') as email,
                        consumer_id
                    from water_consumer_owners wco
                    where status = true
                    group by consumer_id
                ) as wco"), 'water_second_consumers.id', '=', 'wco.consumer_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->where('water_second_consumers.status', 1)
            ->where('wco.' . $key, 'LIKE', '%' . $refNo . '%')
            ->when($wardId, function ($query) use ($wardId) {
                return $query->where('water_second_consumers.ward_mstr_id', $wardId);
            })
            ->when($zoneId, function ($query) use ($zoneId) {
                return $query->where('water_second_consumers.zone_mstr_id', $zoneId);
            })
            ->when($zone, function ($query) use ($zone) {
                return $query->where('water_second_consumers.zone', $zone);
            });
    }

    /**
     * | get the water consumer detaials by Owner details
     * | @param consumerNo
     * | @var 
     * | @return 
     */
    public function getDetailByOwnerDetails($key, $refVal)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.ward_mstr_id',
            'water_second_consumers.address',
            'water_second_consumers.holding_no',
            'water_second_consumers.saf_no',
            'water_consumer_owners.applicant_name as owner_name',
            'water_consumer_owners.mobile_no as mobile_no',
            'water_consumer_owners.guardian_name as guardian_name',
            "water_consumer_demands.balance_amount",
            "water_consumer_demands.amount",
            DB::raw("
                    CASE
                        WHEN water_consumer_demands.paid_status = 1  THEN 'paid'
                        WHEN water_consumer_demands.paid_status = 0 THEN 'unpaid'
                        WHEN water_consumer_demands.paid_status = 2 THEN 'pending'
                        ELSE 'unknown'
                    END AS payment_status,
                    zone_masters.zone_name,
                    ulb_ward_masters.ward_name
                ")
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
            ->where('water_consumer_owners.' . $key, 'ILIKE', "%$refVal%")
            ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', 'water_second_consumers.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->where('water_second_consumers.status', 1);
    }
    /**
     * get meter details of consumer
     */
    public function getDetailByMeterNo($key, $refNo)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.ward_mstr_id',
            'water_second_consumers.address',
            'water_second_consumers.ulb_id',
            "water_consumer_owners.applicant_name as owner_name",
            "water_consumer_owners.mobile_no",
            "water_consumer_owners.guardian_name",
            "water_consumer_meters.meter_no"

        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
            ->join('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            ->where('water_consumer_meters.' . $key, 'LIKE', '%' . $refNo . '%')
            ->where('water_second_consumers.status', 1);
    }
    /**
     * | Update the payment status and the current role for payment 
     * | After the payment is done the data are update in active table
     */
    public function updateDataForPayment($applicationId, $req)
    {
        WaterSecondConsumer::where('id', $applicationId)
            ->where('status', 4)
            ->update($req);
    }

    /**
     * |----------------------- Get Water Consumer detals With all Relation ------------------|
     * | @param request
     * | @return 
     */
    public function fullWaterDetails($applicationId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
            'water_second_consumers.consumer_no',
            'water_consumer_meters.meter_no',
            'water_consumer_meters.connection_type',
            'water_consumer_meters.initial_reading',
            'water_consumer_meters.final_meter_reading',
            'water_consumer_initial_meters.initial_reading as finalReading',
            'ulb_masters.ulb_name',
            'water_second_consumers.property_no',
            'water_property_type_mstrs.property_type',
            'zone_masters.zone_name',
            'water_consumer_demands.demand_from',
            'water_consumer_demands.demand_upto',
            'water_consumer_demands.balance_amount',
            DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
            DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobile_no"),
            DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardian_name"),
            DB::raw("string_agg(water_consumer_owners.email,',') as email"),
            "ulb_masters.association_with",
            "ulb_masters.current_website",
            "ulb_masters.logo",
            DB::raw('ulb_ward_masters.ward_name as ward_number'),
            DB::raw("
                CASE
                    WHEN water_consumer_demands.paid_status = 1 THEN 'Paid'
                    WHEN water_consumer_demands.paid_status = 0 THEN 'Unpaid'
                    ELSE 'unknown'
                END AS payment_status
            ")
        )
            ->join('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->leftjoin('water_consumer_initial_meters', 'water_consumer_initial_meters.consumer_id', 'water_second_consumers.id')
            ->join("water_consumer_owners", 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('ulb_masters', 'ulb_masters.id', 'water_second_consumers.ulb_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_second_connection_charges', 'water_second_connection_charges.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_consumer_demands', 'water_consumer_demands.consumer_id', '=', 'water_second_consumers.id')
            ->where('water_second_consumers.id', $applicationId)
            ->where('water_second_consumers.status', 1)
            ->orderBy('water_consumer_initial_meters.id', 'DESC')
            ->groupBy(
                'water_second_consumers.id',
                'water_second_consumers.consumer_no',
                'water_consumer_meters.meter_no',
                'water_consumer_meters.connection_type',
                'water_consumer_meters.initial_reading',
                'water_consumer_meters.final_meter_reading',
                'ulb_masters.ulb_name',
                'ulb_masters.association_with',
                "ulb_masters.logo",
                "ulb_masters.current_website",
                'ulb_ward_masters.ward_name',
                'water_consumer_initial_meters.initial_reading',
                'water_property_type_mstrs.property_type',
                'zone_masters.zone_name',
                'water_consumer_initial_meters.id',
                'water_consumer_demands.paid_status',
                'water_consumer_demands.demand_from',
                'water_consumer_demands.demand_upto',
                'water_consumer_demands.balance_amount'
            );
    }

    public function fullWaterDetailsV2($applicationId)
    {
        return WaterSecondConsumer::select(
            DB::raw("
            water_second_consumers.*,
            water_second_consumers.consumer_no,
            water_consumer_meters.meter_no,
            water_consumer_meters.connection_type,
            water_consumer_meters.initial_reading,
            water_consumer_meters.final_meter_reading,
            water_consumer_initial_meters.initial_reading as finalReading,
            ulb_masters.ulb_name,
            water_second_consumers.property_no,
            water_property_type_mstrs.property_type,
            zone_masters.zone_name,
            water_consumer_demands.demand_from,
            water_consumer_demands.demand_upto,
            water_consumer_demands.balance_amount,
            water_consumer_owners.applicant_name,
            water_consumer_owners.mobile_no,
            water_consumer_owners.guardian_name,
            water_consumer_owners.email,
            ulb_masters.association_with,
            ulb_masters.current_website,
            ulb_masters.logo,
            ulb_ward_masters.ward_name as ward_number,           
                CASE
                    WHEN round(water_consumer_demands.amount) >= round(water_consumer_demands.paid_total_tax) THEN 'Paid'
                    WHEN round(water_consumer_demands.amount) < round(water_consumer_demands.paid_total_tax) THEN 'Unpaid'
                    ELSE 'unknown'
                END AS payment_status
            
            ")
        )
            ->leftjoin(DB::raw("(
                select string_agg(mobile_no::text,',') as mobile_no,
                        string_agg(applicant_name,',') as applicant_name,
                        string_agg(guardian_name,',') as guardian_name,   
                        string_agg(email,',') as email,
                        consumer_id
                from water_consumer_owners
                where status  =true AND consumer_id = $applicationId
                group by consumer_id
            )as water_consumer_owners"), 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->leftjoin('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->leftjoin('water_consumer_initial_meters', 'water_consumer_initial_meters.consumer_id', 'water_second_consumers.id')
            ->leftjoin('ulb_masters', 'ulb_masters.id', 'water_second_consumers.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_second_connection_charges', 'water_second_connection_charges.consumer_id', 'water_second_consumers.id')
            ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', '=', 'water_second_consumers.id')
            ->where('water_second_consumers.id', $applicationId)
            ->where('water_second_consumers.status', 1)
            ->orderBy('water_consumer_initial_meters.id', 'DESC')
            ->first();
    }


    public function fullWaterDetailsv3($request)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.notice_no_1',
            'water_second_consumers.notice_no_2',
            'water_second_consumers.notice_no_3',
            'water_second_consumers.notice_1_generated_at',
            'water_second_consumers.notice_2_generated_at',
            'water_second_consumers.notice_3_generated_at',
            'water_second_consumers.consumer_no',
            'water_second_consumers.address',
            'water_second_consumers.category',
            'water_second_connection_charges.charge_category',
            'water_temp_disconnections.current_role',
            'water_temp_disconnections.workflow_id',
            'water_temp_disconnections.last_role_id',
            'water_consumer_meters.meter_no',
            'water_consumer_meters.connection_type',
            'water_connection_type_mstrs.connection_type',
            // 'water_consumer_meters.initial_reading',
            // 'water_consumer_meters.final_meter_reading',
            'water_consumer_initial_meters.initial_reading as finalReading',
            'ulb_masters.ulb_name',
            'water_second_consumers.property_no',
            'water_property_type_mstrs.property_type',
            'zone_masters.zone_name',
            'water_consumer_demands.demand_from',
            'water_consumer_demands.demand_upto',
            'water_consumer_demands.balance_amount',
            "water_approval_application_details.per_meter",
            "water_road_cutter_charges.road_type",
            "water_approval_application_details.trade_license as license_no",
            "water_approval_application_details.mobile_no as basicmobile",
            "water_approval_application_details.initial_reading",
            "water_approval_application_details.landmark",
            DB::raw("string_agg(water_consumer_owners.applicant_name, ',') as applicant_name"),
            DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR, ',') as mobile_no"),
            DB::raw("string_agg(water_consumer_owners.guardian_name, ',') as guardian_name"),
            DB::raw("string_agg(water_consumer_owners.email, ',') as email"),
            'ulb_masters.association_with',
            'ulb_masters.current_website',
            'ulb_masters.logo',
            'ulb_ward_masters.ward_name',
            DB::raw("
            CASE
                WHEN water_consumer_demands.paid_status = 1 THEN 'Paid'
                WHEN water_consumer_demands.paid_status = 0 THEN 'Unpaid'
                ELSE 'unknown'
            END AS payment_status
        ")
        )
            ->leftJoin('water_approval_application_details', function ($join) {
                $join->on('water_approval_application_details.id', '=', 'water_second_consumers.apply_connection_id')
                    ->where('water_approval_application_details.status', 1);
            })
            ->leftJoin('water_road_cutter_charges', function ($join) {
                $join->on('water_road_cutter_charges.id', '=', 'water_approval_application_details.road_type_id')
                    ->where('water_road_cutter_charges.status', 1);
            })
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->join('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id')
            ->leftJoin('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_second_consumers.property_type_id')
            ->leftJoin('water_consumer_initial_meters', 'water_consumer_initial_meters.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('ulb_masters', 'ulb_masters.id', '=', 'water_second_consumers.ulb_id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->leftJoin('water_consumer_meters', 'water_consumer_meters.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('water_second_connection_charges', 'water_second_connection_charges.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('water_consumer_demands', 'water_consumer_demands.consumer_id', '=', 'water_second_consumers.id')
            ->where('water_second_consumers.id', $request->applicationId)
            ->where('water_second_consumers.status', 1)
            ->orderBy('water_consumer_initial_meters.id', 'DESC')
            ->groupBy(
                'water_second_consumers.id',
                'water_second_consumers.notice_no_3',
                'water_second_consumers.notice_3_generated_at',
                'water_second_consumers.consumer_no',
                'water_second_connection_charges.charge_category',
                'water_temp_disconnections.current_role',
                'water_temp_disconnections.workflow_id',
                'water_temp_disconnections.last_role_id',
                'water_consumer_meters.meter_no',
                'water_consumer_meters.connection_type',
                'water_consumer_meters.initial_reading',
                'water_consumer_meters.final_meter_reading',
                'water_consumer_initial_meters.initial_reading',
                'ulb_masters.ulb_name',
                'water_connection_type_mstrs.connection_type',
                'water_second_consumers.property_no',
                'water_property_type_mstrs.property_type',
                'zone_masters.zone_name',
                'water_consumer_demands.demand_from',
                'water_consumer_demands.demand_upto',
                'water_consumer_demands.balance_amount',
                'ulb_masters.association_with',
                'ulb_masters.current_website',
                'ulb_masters.logo',
                'ulb_ward_masters.ward_name',
                'water_consumer_demands.paid_status',
                'water_consumer_initial_meters.id',
                'water_approval_application_details.per_meter',
                "water_approval_application_details.trade_license",
                "water_approval_application_details.mobile_no",
                "water_approval_application_details.initial_reading",
                "water_approval_application_details.landmark",
                "water_approval_application_details.tab_size",
                'water_road_cutter_charges.road_type'
            );
    }

    /**
     * | Get consumer 
     */
    public function getConsumerDetails($applicationId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
            'water_temp_disconnections.status as deactivate_status'
        )
            ->leftjoin('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
            ->where('water_second_consumers.id', $applicationId)
            ->whereIn('water_second_consumers.status', [1, 4]);
    }
    /**
     * | Dectivate the water Consumer 
     * | @param req
     */
    public function dissconnetConsumer($consumerId)
    {
        WaterSecondConsumer::where('id', $consumerId)
            ->update([
                'status' => 3
            ]);
    }

    /**
     * 
     */
    public function fullWaterDetail($applicationId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
            'water_second_consumers.consumer_no',
            'water_second_connection_charges.amount',
            'water_second_connection_charges.charge_category',
            'water_consumer_meters.meter_no',
            'water_consumer_meters.connection_type',
            'water_consumer_meters.initial_reading',
            'water_consumer_meters.final_meter_reading',
            'ulb_masters.ulb_name',
            "water_consumer_owners.applicant_name",
            "water_consumer_owners.guardian_name",
            "water_consumer_owners.email",
            DB::raw('ulb_ward_masters.ward_name as ward_number')   // Alias the column as "ward_number"
        )
            ->join("water_consumer_owners", 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->join('ulb_masters', 'ulb_masters.id', 'water_second_consumers.ulb_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->join('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            ->join('water_second_connection_charges', 'water_second_connection_charges.consumer_id', 'water_second_consumers.id')
            ->where('water_second_consumers.id', $applicationId)
            ->where('water_second_consumers.status', 4);
    }

    /**
     * | Get consumer Details By ConsumerId
     * | @param conasumerId
     */
    public function getConsumerDetailById($consumerId)
    {
        return WaterSecondConsumer::where('id', $consumerId)
            ->whereIn('status', [1, 4])
            ->firstOrFail();
    }
    /**
     * | Get water consumer according to apply connection id 
     */
    public function getConsumerByAppId($applicationId)
    {
        return WaterSecondConsumer::where('apply_connection_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first();
    }
    /**
     * | Get water consumer according to apply connection id 
     */
    public function getConsumerById($consumerId)
    {
        return WaterSecondConsumer::where('id', $consumerId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first();
    }
    /**
     * | Save the approved application to water Consumer
     * | @param consumerDetails
     * | @return
     */
    public function saveWaterConsumer($consumerDetails, $consumerNo, $siteDetails)
    {
        $mWaterConsumer = new WaterSecondConsumer();
        $mWaterConsumer->apply_connection_id         = $consumerDetails['id'];
        $mWaterConsumer->connection_type_id          = $consumerDetails['connection_type_id'];
        $mWaterConsumer->connection_through_id       = $consumerDetails['connection_through'];
        $mWaterConsumer->pipeline_type_id            = $consumerDetails['pipeline_type_id'];
        $mWaterConsumer->property_type_id            = $siteDetails['property_type_id'];
        $mWaterConsumer->prop_dtl_id                 = $consumerDetails['prop_id'];
        $mWaterConsumer->holding_no                  = $consumerDetails['property_no'];
        $mWaterConsumer->saf_dtl_id                  = $consumerDetails['saf_id'];
        $mWaterConsumer->saf_no                      = $consumerDetails['saf_no'];
        $mWaterConsumer->category                    = $siteDetails['category'];
        $mWaterConsumer->ward_mstr_id                = $consumerDetails['ward_id'];
        $mWaterConsumer->zone_mstr_id                = $consumerDetails['zone_mstr_id'];
        $mWaterConsumer->consumer_no                 = $consumerNo;
        $mWaterConsumer->address                     = $consumerDetails['address'];
        $mWaterConsumer->apply_from                  = $consumerDetails['apply_from'];
        $mWaterConsumer->k_no                        = $consumerDetails['elec_k_no'];
        $mWaterConsumer->bind_book_no                = $consumerDetails['elec_bind_book_no'];
        $mWaterConsumer->account_no                  = $consumerDetails['elec_account_no'];
        $mWaterConsumer->electric_category_type      = $consumerDetails['elec_category'];
        $mWaterConsumer->ulb_id                      = $consumerDetails['ulb_id'];
        $mWaterConsumer->area_sqft                   = $consumerDetails['area_sqft'];
        $mWaterConsumer->owner_type_id               = $consumerDetails['owner_type'];
        $mWaterConsumer->application_apply_date      = $consumerDetails['apply_date'];
        $mWaterConsumer->user_id                     = $consumerDetails['user_id'];
        $mWaterConsumer->pin                         = $consumerDetails['pin'];
        $mWaterConsumer->user_type                   = $consumerDetails['user_type'];
        $mWaterConsumer->area_sqmt                   = $consumerDetails['area_sqft'];
        $mWaterConsumer->rent_amount                 = $consumerDetails['rent_amount'] ?? null;
        $mWaterConsumer->tab_size                    = $siteDetails['ferrule_type'];
        $mWaterConsumer->approve_date                = Carbon::now();
        $mWaterConsumer->connection_date             = Carbon::now();
        $mWaterConsumer->status                      = 4;
        $mWaterConsumer->save();
        return $mWaterConsumer->id;
    }
    #zone or ward wise consumers
    public function totalConsumerType($wardId, $zoneId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.id as consumerId',
            'water_consumer_demands.id as demandId'
        )
            ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', 'water_second_consumers.id')
            // ->where('ward_mstr_id',$wardId)
            // ->where('zone',$zoneId);
        ;
    }
    #all consumer details
    public function consumerDetails($consumerId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
            'water_consumer_owners.id as waterConsumerOwner',
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->where('water_second_consumers.id', $consumerId);
    }
    /**
     * | Save the consumer dtl 
     */
    public function editConsumerdtls($request, $userId)
    {
        $mWaterSecondConsumer = WaterSecondConsumer::findorfail($request->consumerId);
        $mWaterSecondConsumer->ward_mstr_id         =  $request->wardId;
        $mWaterSecondConsumer->zone_mstr_id         =  $request->zoneId;
        $mWaterSecondConsumer->mobile_no            =  $request->mobileNo;
        $mWaterSecondConsumer->old_consumer_no      =  $request->oldConsumerNo;
        $mWaterSecondConsumer->property_no          =  $request->propertyNo;
        $mWaterSecondConsumer->dtc_code             =  $request->dtcCode;
        $mWaterSecondConsumer->user_id              =  $userId;
        $mWaterSecondConsumer->save();
    }
    /**
     * | get the water consumer detaials by application no
     * | @param refVal as ApplicationNo
     */
    public function getDetailByApplicationNo($refVal)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.ward_mstr_id',
            'water_second_consumers.address',
            'water_second_consumers.holding_no',
            'water_second_consumers.saf_no',
            'ulb_ward_masters.ward_name',
            'water_consumer_owners.applicant_name as applicant_name',
            'water_consumer_owners.mobile_no as mobile_no',
            'water_consumer_owners.guardian_name as guardian_name',
            DB::raw("
                zone_masters.zone_name,
                ulb_ward_masters.ward_name,
            ")
        )
            ->join('water_approval_application_details', 'water_approval_application_details.id', 'water_second_consumers.apply_connection_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->where('water_approval_application_details.application_no', 'LIKE', '%' . $refVal . '%')
            ->where('water_second_consumers.status', 1);
        // ->where('ulb_ward_masters.status', true);
    }
    /**
     * get consumer by consumer number
     */
    public function getconsuerByConsumerNo($consumerNo)
    {
        return WaterSecondConsumer::where('consumer_no', $consumerNo)
            ->where('status', 1);
    }

    public function getLastConnectionDtl()
    {
        return $this->hasOne(WaterConsumerMeter::class, "consumer_id", "id")->where("status", 1)->orderBy("id", "DESC")->first();
    }

    public function getLastDemand()
    {
        return $this->hasOne(WaterConsumerDemand::class, "consumer_id", "id")->where("status", 1)->orderBy("demand_upto", "DESC")->first();
    }

    public function getLastPaidDemand()
    {
        return $this->hasOne(WaterConsumerDemand::class, "consumer_id", "id")->where("status", 1)->where("paid_status", 1)->orderBy("demand_upto", "DESC")->first();
    }

    public function getAllUnpaidDemand()
    {
        return $this->hasMany(WaterConsumerDemand::class, "consumer_id", "id")->where("status", 1)->where("paid_status", 0)->orderBy("demand_upto", "ASC")->get();
    }

    public function getLastReading()
    {
        return $this->hasOne(WaterConsumerInitialMeter::class, "consumer_id", "id")->where("status", 1)->orderBy("id", "DESC")->first();
    }

    public function getProperty()
    {
        return $this->hasOne(WaterPropertyTypeMstr::class, "id", "property_type_id")->first();
    }

    /**
     * | Fing data according to consumer No 
     * | @param consumerNo
     */
    public function getConsumerByNo($consumerNo)
    {
        return self::where('consumer_no', $consumerNo)->where('status', 1)->first();
    }

    public function getApplicationById($applicationId)
    {
        return  WaterSecondConsumer::select(
            'water_second_consumers.*'
        )
            ->where('water_second_consumers.id', $applicationId)
            ->join('water_approval_application_details', 'water_approval_application_details.id', 'water_second_consumers.apply_connection_id')
            ->whereIn('water_second_consumers.status', [1, 4]);
    }
    public function getApplicationByIdv1($applicationId)
    {
        return  WaterSecondConsumer::select(
            'water_second_consumers.*'
        )
            ->where('water_second_consumers.id', $applicationId)
            ->whereIn('water_second_consumers.status', [1, 4]);
    }

    public function updateConsumer($consumerId)
    {
        return self::where('id', $consumerId)
            ->update([
                'status' => 1,
                'payment_status' => 1
            ]);
    }

    /**
     * | get the water consumer detaials by consumr No / accurate search
     * | @param consumerNo
     * | @var 
     * | @return 
     */
    public function getConsumerByConsumerNo($key, $parameter)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
            'water_second_consumers.id as consumer_id',
            'ulb_ward_masters.ward_name',
            'water_second_consumers.connection_through_id',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            'water_property_type_mstrs.property_type',
            'water_connection_through_mstrs.connection_through',
        )
            ->leftjoin('water_connection_through_mstrs', 'water_connection_through_mstrs.id', '=', 'water_second_consumers.connection_through_id')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_second_consumers.ulb_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_second_consumers.property_type_id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
            ->where('water_second_consumers.' . $key, $parameter)
            ->where('water_second_consumers.status', 1)
            ->firstOrFail();
    }

    public function getDetailByConsumerNoforProperty($consumerNo)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
            DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
            'water_property_type_mstrs.property_type as building_type'
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
            ->leftjoin('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_second_consumers.property_type_id')
            ->where('water_second_consumers.status', 1)
            ->where('water_second_consumers.consumer_no', $consumerNo)
            ->groupBy(
                'water_second_consumers.consumer_no',
                'water_second_consumers.id',
                'water_property_type_mstrs.property_type'
            )->first();
    }

    /**
     * | Get consumer 
     */
    public function getConsumerDtlsByID($consumerId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.id as consumerId',
            'water_second_consumers.consumer_no',
            'water_second_consumers.category',
            'water_second_consumers.tab_size',
            'water_property_type_mstrs.property_type'
        )
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->where('water_second_consumers.id', $consumerId)
            ->where('water_second_consumers.status', '!=', 3);
    }
    /**
     * |update
     */
    public function updateConnectionType($consumerOwnedetails, $checkExist)
    {
        $propertyTypeId = $checkExist->property_type == 'Residential' ? 1 : 2;

        return self::where('id', $consumerOwnedetails->consumer_id)
            ->where('status', true)
            ->update([
                'category' => $checkExist->category,
                'property_type_id' => $propertyTypeId
            ]);
    }
    # Update tab size
    public function updateTabSize($consumerOwnedetails, $checkExist)
    {
        return self::where('id', $consumerOwnedetails->consumer_id)
            ->where('status', true)
            ->update([
                'tab_size' => $checkExist->tab_size
            ]);
    }
    /** 
     * | Get consumer by consumer id
     */
    public function getConsumerByIds($consumerIds)
    {
        return self::select(
            'water_second_consumers.id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.ward_mstr_id',
            'water_second_consumers.address',
            'water_second_consumers.holding_no',
            'water_second_consumers.saf_no',
            'water_second_consumers.ulb_id',
            'water_second_consumers.status',
            'ulb_ward_masters.ward_name',
            "water_temp_disconnections.status as deactivated_status",
            "water_second_consumers.notice_no_1",
            "water_second_consumers.notice_no_2",
            "water_second_consumers.notice_no_3",
            "water_second_consumers.notice",
            "water_second_consumers.notice_2",
            "water_second_consumers.notice_3",
            "water_second_consumers.notice_1_generated_at",
            "water_second_consumers.notice_2_generated_at",
            "water_second_consumers.notice_3_generated_at",
            DB::raw("subquery.applicant_name"),
            DB::raw("subquery.mobile_no"),
            DB::raw("subquery.guardian_name"),
            DB::raw('sum(water_consumer_demands.due_balance_amount)as due_amount')
        )
        ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', 'water_second_consumers.id')
        ->leftJoin('water_temp_disconnections', 'water_temp_disconnections.consumer_id', '=', 'water_second_consumers.id')
        ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_second_consumers.ward_mstr_id')
        ->leftJoin(DB::raw("(SELECT 
                                water_consumer_owners.consumer_id,
                                string_agg(DISTINCT water_consumer_owners.applicant_name, ',') as applicant_name,
                                string_agg(DISTINCT water_consumer_owners.mobile_no::VARCHAR, ',') as mobile_no,
                                string_agg(DISTINCT water_consumer_owners.guardian_name, ',') as guardian_name
                            FROM water_consumer_owners
                            GROUP BY water_consumer_owners.consumer_id
                        ) AS subquery"), 'subquery.consumer_id', '=', 'water_second_consumers.id')
        ->whereIn("water_second_consumers.id", $consumerIds)
        ->groupBy(
            'water_second_consumers.saf_no',
            'water_second_consumers.holding_no',
            'water_second_consumers.address',
            'water_second_consumers.id',
            'water_second_consumers.ulb_id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.ward_mstr_id',
            'ulb_ward_masters.ward_name',
            'water_temp_disconnections.status',
            'water_second_consumers.notice_no_1',
            'water_second_consumers.notice_no_2',
            'water_second_consumers.notice_no_3',
            'water_second_consumers.notice',
            'water_second_consumers.notice_2',
            'water_second_consumers.notice_3',
            'water_second_consumers.notice_1_generated_at',
            'water_second_consumers.notice_2_generated_at',
            'water_second_consumers.notice_3_generated_at',
            'subquery.applicant_name',
            'subquery.mobile_no',
            'subquery.guardian_name'
        );
    }
    

    
    /**
     * |get details of applications which is partiallly make consumer
     * | before it payments
     */
    public function fullWaterDetailsV1($request)
    {
        return  self::select(
            'water_second_consumers.id',
            'water_approval_application_details.id as applicationId',
            'water_reconnect_consumers.id as  reconnectId',
            'water_consumer_owners.mobile_no',
            'water_second_consumers.tab_size',
            'water_second_consumers.property_no',
            'water_approval_application_details.status',
            'water_second_consumers.payment_status',
            'water_approval_application_details.user_type',
            'water_second_consumers.application_apply_date as apply_date',
            'water_approval_application_details.landmark',
            'water_approval_application_details.address',
            'water_second_consumers.category',
            'water_approval_application_details.application_no',
            'water_approval_application_details.ward_no',
            'water_approval_application_details.pin',
            'water_approval_application_details.doc_upload_status',
            'water_approval_application_details.property_no',
            'water_property_type_mstrs.property_type',
            'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            // 'wf_roles.role_name AS current_role_name',
            'water_connection_charges.amount',
            "water_connection_charges.charge_category",
            "water_consumer_owners.applicant_name as owner_name",
            "water_consumer_owners.guardian_name",
            "water_consumer_meters.connection_type",
            "water_approval_application_details.meter_no",
            "water_second_consumers.consumer_no",
            "ulb_ward_masters.ward_name as ward_no",
            "water_approval_application_details.initial_reading",
            "water_approval_application_details.per_meter",
            "water_road_cutter_charges.road_type",
            "water_approval_application_details.email",
            "water_approval_application_details.trade_license"
        )
            // ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_approval_application_details.current_role')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_second_consumers.ulb_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->leftjoin('water_approval_application_details', 'water_approval_application_details.id', 'water_second_consumers.apply_connection_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->leftjoin('water_param_pipeline_types', 'water_param_pipeline_types.id', 'water_second_consumers.pipeline_type_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->leftjoin('water_connection_charges', 'water_connection_charges.application_id', 'water_approval_application_details.id')
            ->leftjoin('water_road_cutter_charges', 'water_road_cutter_charges.id', 'water_approval_application_details.road_type_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            ->leftJoin('water_reconnect_consumers', function ($join) {
                $join->on('water_reconnect_consumers.consumer_id', '=', 'water_second_consumers.id')
                    ->where('water_reconnect_consumers.status', 1);
            })
            ->where('water_second_consumers.id', $request->applicationId);
        // ->whereIn('water_second_consumers.status', [1, 2,4]);
        // ->where('water_approval_application_details.status', true);
    }

    #update
    public function waterConsumerActivate($consumerId)
    {
        $consumer = WaterSecondConsumer::where('id', $consumerId)->first();
        $consumer->status = 1;
        $consumer->save();
    }

    #check consumer 
    public function checkConsumer($consumerId)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.*',
        )
            ->where('id', $consumerId)
            ->first();
    }

    /**
     * |get details of applications which is partiallly make consumer
     * | before it payments
     */
    public function fullWaterDetailsv5($request)
    {
        return  WaterSecondConsumer::select(
            'water_second_consumers.id',
            'water_second_consumers.mobile_no',
            'water_second_consumers.tab_size',
            'water_second_consumers.property_no',
            'water_second_consumers.status',
            'water_reconnect_consumers.payment_status',
            'water_reconnect_consumers.user_type',
            'water_reconnect_consumers.apply_date',
            'water_second_consumers.landmark',
            'water_second_consumers.address',
            'water_second_consumers.category',
            'water_reconnect_consumers.application_no',
            'water_property_type_mstrs.property_type',
            'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            // 'wf_roles.role_name AS current_role_name',
            'water_connection_type_mstrs.connection_type',
            "water_consumer_owners.applicant_name as owner_name",
            "water_consumer_owners.guardian_name",
            "water_consumer_meters.connection_type",
            "water_consumer_meters.meter_no",
            "ulb_ward_masters.ward_name as ward_no",
            "water_consumer_charges.amount as reconnectCharges",
            "water_consumer_charges.charge_category as reconnechargeCategory"
        )
            // ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_approval_application_details.current_role')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_second_consumers.ulb_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->leftjoin('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_param_pipeline_types', 'water_param_pipeline_types.id', 'water_second_consumers.pipeline_type_id')
            ->join('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->join('water_reconnect_consumers', 'water_reconnect_consumers.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('water_consumer_charges', function ($join) {
                $join->on('water_consumer_charges.consumer_id', '=', 'water_reconnect_consumers.consumer_id')
                    ->where('water_consumer_charges.status', 1)
                    ->where('water_consumer_charges.charge_category', 'WATER RECONNECTION');
            })
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            // ->leftjoin('water_road_cutter_charges')
            ->where('water_second_consumers.id', $request->applicationId)
            ->whereIn('water_second_consumers.status', [1, 2, 4]);
    }
    /**
     * |get details of applications which is partiallly make consumer
     * | before it payments
     */
    public function fullWaterDetailsv6($id)
    {
        return  WaterSecondConsumer::select(
            'water_second_consumers.id',
            'water_second_consumers.mobile_no',
            'water_second_consumers.tab_size',
            'water_second_consumers.consumer_no',
            'water_second_consumers.property_no',
            'water_second_consumers.status',
            'water_reconnect_consumers.payment_status',
            'water_reconnect_consumers.user_type',
            'water_reconnect_consumers.apply_date',
            'water_second_consumers.landmark',
            'water_second_consumers.address',
            'water_second_consumers.category',
            'water_reconnect_consumers.application_no',
            'water_property_type_mstrs.property_type',
            'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            // 'wf_roles.role_name AS current_role_name',
            'water_connection_type_mstrs.connection_type',
            "water_consumer_owners.applicant_name as owner_name",
            "water_consumer_owners.guardian_name",
            "water_consumer_meters.connection_type",
            "water_consumer_meters.meter_no",
            "ulb_ward_masters.ward_name as ward_no",
            "water_consumer_charges.amount as reconnectCharges",
            "water_consumer_charges.charge_category as reconnechargeCategory",
            DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardianName"),
        )
            // ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_approval_application_details.current_role')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_second_consumers.ulb_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->leftjoin('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_param_pipeline_types', 'water_param_pipeline_types.id', 'water_second_consumers.pipeline_type_id')
            ->join('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->join('water_reconnect_consumers', 'water_reconnect_consumers.consumer_id', '=', 'water_second_consumers.id')
            ->leftJoin('water_consumer_charges', function ($join) {
                $join->on('water_consumer_charges.consumer_id', '=', 'water_reconnect_consumers.consumer_id')
                    ->where('water_consumer_charges.status', 1)
                    ->where('water_consumer_charges.charge_category', 'WATER RECONNECTION');
            })
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            // ->leftjoin('water_road_cutter_charges')
            ->where('water_second_consumers.id', $id)
            ->whereIn('water_second_consumers.status', [1, 2, 4])
            ->groupBy(
                'water_second_consumers.id',
                'water_second_consumers.mobile_no',
                'water_second_consumers.tab_size',
                'water_second_consumers.consumer_no',
                'water_second_consumers.property_no',
                'water_second_consumers.status',
                'water_reconnect_consumers.payment_status',
                'water_reconnect_consumers.user_type',
                'water_reconnect_consumers.apply_date',
                'water_second_consumers.landmark',
                'water_second_consumers.address',
                'water_second_consumers.category',
                'water_reconnect_consumers.application_no',
                'water_property_type_mstrs.property_type',
                'water_param_pipeline_types.pipeline_type',
                'zone_masters.zone_name',
                'ulb_masters.ulb_name',
                'water_connection_type_mstrs.connection_type',
                'water_consumer_owners.applicant_name',
                'water_consumer_owners.guardian_name',
                'water_consumer_meters.connection_type',
                'water_consumer_meters.meter_no',
                'ulb_ward_masters.ward_name',
                'water_consumer_charges.amount',
                'water_consumer_charges.charge_category'
            );
    }
}
