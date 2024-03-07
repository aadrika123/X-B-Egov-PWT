<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WaterSecondConsumer extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

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
        $waterSecondConsumer->connection_date           = $req->ConectionDate?$req->ConectionDate:$req->connectionDate;
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
                    ulb_ward_masters.ward_name,
                ")
            )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_second_consumers.id')
            ->where('water_consumer_owners.' . $key, 'ILIKE', '%' . $refVal . '%')
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
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->leftjoin('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->leftjoin('water_consumer_initial_meters', 'water_consumer_initial_meters.consumer_id', 'water_second_consumers.id')
            ->leftjoin("water_consumer_owners", 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('ulb_masters', 'ulb_masters.id', 'water_second_consumers.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_second_consumers.id')
            ->leftjoin('water_second_connection_charges', 'water_second_connection_charges.consumer_id', 'water_second_consumers.id')
            ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', '=', 'water_second_consumers.id')
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
    /**
     * | Get consumer 
     */
    public function getConsumerDetails($applicationId)
    {
        return WaterSecondConsumer::where('id', $applicationId)
            ->where('status', 1);
    }
    /**
     * | Dectivate the water Consumer 
     * | @param req
     */
    public function dissconnetConsumer($consumerId, $status)
    {
        WaterSecondConsumer::where('id', $consumerId)
            ->update([
                'status' => $status
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
            ->where('status', 1)
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
     * | Save the approved application to water Consumer
     * | @param consumerDetails
     * | @return
     */
    public function saveWaterConsumer($consumerDetails, $consumerNo)
    {
        $mWaterConsumer = new WaterSecondConsumer();
        $mWaterConsumer->apply_connection_id         = $consumerDetails['id'];
        $mWaterConsumer->connection_type_id          = $consumerDetails['connection_type_id'];
        $mWaterConsumer->connection_through_id       = $consumerDetails['connection_through'];
        $mWaterConsumer->pipeline_type_id            = $consumerDetails['pipeline_type_id'];
        $mWaterConsumer->property_type_id            = $consumerDetails['property_type_id'];
        $mWaterConsumer->prop_dtl_id                 = $consumerDetails['prop_id'];
        $mWaterConsumer->holding_no                  = $consumerDetails['holding_no'];
        $mWaterConsumer->saf_dtl_id                  = $consumerDetails['saf_id'];
        $mWaterConsumer->saf_no                      = $consumerDetails['saf_no'];
        $mWaterConsumer->category                    = $consumerDetails['category'];
        $mWaterConsumer->ward_mstr_id                = $consumerDetails['ward_id'];
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
        $mWaterConsumer->tab_size                   = $consumerDetails['tab_size'];
        $mWaterConsumer->approve_date                = Carbon::now();
        $mWaterConsumer->connection_date             = Carbon::now();
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
            'water_second_consumers.id as waterConsumerId',
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
}
