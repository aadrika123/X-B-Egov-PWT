<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Exception;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class WaterConsumerDemand extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];


    /**
     * | Get Payed Consumer Demand
     * | @param ConsumerId
     */
    public function getDemandBydemandId($demandId)
    {
        return WaterConsumerDemand::where('id', $demandId)
            ->where('paid_status', 1);
    }
    /**
     * | Get  Consumer Demand
     * | @param ConsumerId
     */
    public function getDemandBydemandIds($consumerId)
    {
        return WaterConsumerDemand::select(
            'water_consumer_demands.id AS ref_demand_id',
            'water_consumer_demands.*',
            'water_second_consumers.*',
            'water_consumer_owners.applicant_name',
            'water_consumer_initial_meters.initial_reading',
            'users.user_name',
            DB::raw("ROUND(water_consumer_demands.due_balance_amount, 2) as due_balance_amount"), 
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_consumer_demands.consumer_id')
            ->leftjoin('water_consumer_initial_meters', 'water_consumer_initial_meters.consumer_id', 'water_consumer_demands.consumer_id')
            ->join('water_second_consumers', 'water_second_consumers.id', '=', 'water_consumer_demands.consumer_id')
            ->leftjoin('users','users.id','=','water_consumer_demands.emp_details_id') 
           
            // ->where('water_consumer_demands.paid_status', 0)
            ->where('water_consumer_demands.is_full_paid',false)
            ->where('water_consumer_demands.consumer_id', $consumerId)
            ->orderByDesc('water_consumer_demands.generation_date')
            ->first();
    }


    /**
     * | Get Demand According to consumerId and payment status false 
        | Here Changes
     */
    public function getConsumerDemand($consumerId)
    {
        // $this->impos_penalty($consumerId);
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('paid_status', 0)
            ->where('status', true)
            ->orderByDesc('id')
            ->get();
    }


    /**
     * | Get Demand According to consumerId and payment status false 
        | Here Changes
     */
    public function getConsumerDemandV3($consumerId)
    {
        // $this->impos_penalty($consumerId);
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('is_full_paid', false)
            ->where('status', true)
            ->orderByDesc('id')
            ->get();
    }


    /**
     * | Get Demand According to consumerId and payment status false versin 2
        | Here Changes
     */
    public function getConsumerDemandV2($consumerId)
    {
        // $this->impos_penalty($consumerId);
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('is_full_paid', false)
            ->where('status', true)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * | 
     */
    public function getRefConsumerDemand($consumerId)
    {
        $this->impos_penalty($consumerId);
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('status', true)
            ->orderByDesc('id');
    }



    public function consumerDemandByConsumerId($consumerId)
    {
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('status', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * | Deactivate the consumer Demand
     * | Demand Ids will be in array
     * | @param DemandIds
     */
    public function updateDemand($updateList, $demandIds)
    {
        WaterConsumerDemand::where('id', $demandIds)
            ->update($updateList);
    }


    /**
     * | Save the consumer demand while Demand generation
     * | @param demands
     * | @param meterDetails
        | Create the demand no through id generation
     */
    public function saveConsumerDemand($demands, $consumerDetails, $request, $taxId, $userDetails)
    {
        $mWaterConsumerDemand = new WaterConsumerDemand();
        $mWaterConsumerDemand->consumer_id              =  $request->consumerId;
        $mWaterConsumerDemand->ward_id                  =  $consumerDetails->ward_mstr_id;
        $mWaterConsumerDemand->ulb_id                   =  $consumerDetails->ulb_id;
        $mWaterConsumerDemand->generation_date          =  $demands['generation_date'];
        $mWaterConsumerDemand->amount                   =  $demands['amount'];
        $mWaterConsumerDemand->paid_status              =  0;                                   // Static
        $mWaterConsumerDemand->consumer_tax_id          =  $taxId;
        $mWaterConsumerDemand->emp_details_id           =  $userDetails['emp_id'] ?? null;
        $mWaterConsumerDemand->citizen_id               =  $userDetails['citizen_id'] ?? null;
        $mWaterConsumerDemand->demand_from              =  $demands['demand_from'];
        $mWaterConsumerDemand->demand_upto              =  $demands['demand_upto'];
        $mWaterConsumerDemand->penalty                  =  $demands['penalty'] ?? 0;            // Static
        $mWaterConsumerDemand->current_meter_reading    =  $request->finalRading;
        $mWaterConsumerDemand->unit_amount              =  $demands['unit_amount'];
        $mWaterConsumerDemand->connection_type          =  $demands['connection_type'];
        $mWaterConsumerDemand->demand_no                =  "WCD" . random_int(100000, 999999) . "/" . random_int(1, 10);
        $mWaterConsumerDemand->balance_amount           =  $demands['penalty'] ?? 0 + $demands['amount'];
        $mWaterConsumerDemand->created_at               =  Carbon::now();
        $mWaterConsumerDemand->due_balance_amount       =  $demands['penalty'] ?? 0 + $demands['amount'];
        $mWaterConsumerDemand->save();

        return $mWaterConsumerDemand->id;
    }


    /**
     * | Get Demand According to consumerId and payment status false 
     */
    public function getFirstConsumerDemand($consumerId)
    {
        // $this->impos_penalty($consumerId);
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('paid_status', 0)
            ->where('status', true)
            ->orderByDesc('id');
    }

    /**
     * | Get Demand According to consumerId and payment status false 
     */
    public function getFirstConsumerDemandV2($consumerId)
    {
        // $this->impos_penalty($consumerId);
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('is_full_paid', false)
            ->where('status', true)
            ->orderByDesc('id');
    }


    /**
     * | impose penalty
     * 
     */
    public function impos_penalty($consumerId)
    {
        try {
            $fine_months = 0;
            $penalty = 0.00;
            $penalty_amt = 0.00;
            $demand = array();
            $currend_date = Carbon::now()->format("Y-m-d");
            $meter_demand_sql = "SELECT * FROM  water_consumer_demands 
                                    where consumer_id=$consumerId 
                                    and paid_status= 0
                                    and status=true 
                                    and connection_type in ('Metered', 'Meter')";

            $meter_demand = DB::select($meter_demand_sql);
            DB::beginTransaction();
            #meter Demand
            foreach ($meter_demand as $val) {
                $val = collect($val)->all();
                if ($val["panelty_updated_on"] == $currend_date) {
                    continue;
                }
                if ($val["demand_upto"] >= '2021-01-01') {
                    $fine_months_sql = "SELECT ((DATE_PART('year', '$currend_date'::date) - DATE_PART('year', '" . ($val["demand_upto"]) . "'::date)) * 12 +
                                            (DATE_PART('month', '$currend_date'::date) - DATE_PART('month', '" . ($val["demand_upto"]) . "'::date))) :: integer as months
                                            ";
                    $fine_months = ((DB::select($fine_months_sql))[0]->months) ?? 0;
                }
                if ($fine_months >= 3) {
                    $penalty = ($val["amount"] / 100) * 1.5;
                    $penalty_amt = ($penalty * ($fine_months - 2));
                    $upate_sql = "update water_consumer_demands  set penalty=" . ($penalty_amt) . ", 
                                    balance_amount=(" . ($val["amount"] + $penalty_amt) . "), 
                                    panelty_updated_on='" . $currend_date . "' 
                                    where id=" . $val["id"] . " ";
                    $id = DB::select($upate_sql);
                } else {
                    $upate_sql = "update water_consumer_demands  set penalty=" . $penalty . ", 
                                    balance_amount=(" . ($val["amount"] + $penalty_amt) . "), 
                                    panelty_updated_on='" . $currend_date . "' 
                                    where id=" . $val["id"] . "";
                    $id = DB::select($upate_sql);
                }
            }

            #fixed Demand
            $fixed_demand_sql = "SELECT * FROM water_consumer_demands  
                                    where consumer_id=$consumerId 
                                    and paid_status=0 
                                    and status=true 
                                    and connection_type='Fixed'";
            $fixed_demand = DB::select($fixed_demand_sql);
            foreach ($fixed_demand as $val) {
                $val = collect($val)->all();
                if ($val["panelty_updated_on"] == $currend_date) {
                    continue;
                }
                if ($val["demand_upto"] >= '2015-07-01') {
                    $fine_months_sql = "SELECT ((DATE_PART('year', '$currend_date'::date) - DATE_PART('year', '" . ($val["demand_upto"]) . "'::date)) * 12 +
                                            (DATE_PART('month', '$currend_date'::date) - DATE_PART('month', '" . ($val["demand_upto"]) . "'::date))) :: integer
                                            ";
                    $fine_months = ((DB::select($fine_months_sql))[0]->months) ?? 0;
                }
                if ($fine_months >= 1) {
                    $penalty = ($val["amount"] / 100) * 10;
                    $penalty_amt = $penalty;
                    $upate_sql = "update water_consumer_demands  set penalty=" . $penalty_amt . ", 
                                    balance_amount=(" . ($val["amount"] + $penalty_amt) . "), 
                                    panelty_updated_on='" . $currend_date . "' 
                                    where id=" . $val["id"] . " ";
                    DB::select($upate_sql);
                } else {
                    $upate_sql = "update water_consumer_demands  set penalty=" . $penalty . ", 
                                    balance_amount=(" . ($val["amount"] + $penalty_amt) . "), 
                                    panelty_updated_on='" . $currend_date . "' 
                                    where id=" . $val["id"] . "";
                    DB::select($upate_sql);
                }
            }
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * | Get collictively consumer demand by demand Ids
     * | @param ids
     */
    public function getDemandCollectively($ids)
    {
        return WaterConsumerDemand::whereIn('id', $ids)
            ->where('status', true)
            ->where('paid_status',  '!=', 0);
    }

    /**
     * | get the meter listing
     */
    public function getConsumerTax($demandIds)
    {
        return WaterConsumerDemand::select(
            'water_consumer_taxes.initial_reading',
            "water_consumer_taxes.final_reading",
            'water_consumer_demands.*'
        )
            ->leftjoin('water_consumer_taxes', function ($join) {
                $join->on('water_consumer_taxes.id', 'water_consumer_demands.consumer_tax_id')
                    ->where('water_consumer_taxes.status', 1)
                    ->where('water_consumer_taxes.charge_type', 'Meter');
            })
            ->whereIn('water_consumer_demands.id', $demandIds)
            ->where('water_consumer_demands.status', 1)
            ->orderByDesc('water_consumer_demands.id')
            ->get();
    }


    /**
     * | Get Demand According to consumerId and payment status false 
        | Caution 
        | Use only to check consumer demand in case of online payment 
        | Dont use any where else 
     */
    public function checkConsumerDemand($consumerId)
    {
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('paid_status', 0)
            ->where('status', true)
            ->orderByDesc('id');
    }

    /**
     * | Akola get demand
     */
    public function akolaCheckConsumerDemand($consumerId)
    {
        return WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('status', true)
            ->orderByDesc('id');
    }

    /**
     * get all data of consumer demands
     */
    public function getALLDemand($fromDate, $uptoDate, $wardId, $zoneId)
    {
        return WaterConsumerDemand::select(
            'water_consumer_demands.amount',
            'water_consumer_demands.paid_status'
        )
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
            ->where('water_consumer_demands.demand_from', '>=', $fromDate)
            ->where('water_consumer_demands.demand_upto', '<=', $uptoDate)
            ->where('water_second_consumers.ward_mstr_id', $wardId)
            ->where('water_second_consumers.zone_mstr_id', $zoneId)
            ->where('water_consumer_demands.status', true);
    }
    #previous year financial 

    public function previousDemand($fromDate, $uptoDate, $wardId, $zoneId)
    {
        return WaterConsumerDemand::select(
            'water_consumer_demands.amount',
            'water_consumer_demands.paid_status'
        )
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
            ->where('water_consumer_demands.demand_from', '>=', $fromDate)
            ->where('water_consumer_demands.demand_upto', '<=', $uptoDate)
            ->where('water_second_consumers.ward_mstr_id', $wardId)
            ->where('water_second_consumers.zone_mstr_id', $zoneId)
            ->where('water_consumer_demands.status', true);
    }
    /**
     * get details of tc visit
     */
    public function getDetailsOfTc($key, $refNo)
    {
        return WaterConsumerDemand::select(
            'water_consumer_demands.amount',
            'water_consumer_demands.generation_date',
            'users.user_name',
            'users.user_type',
            'water_second_consumers.consumer_no'
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_consumer_demands.consumer_id')
            ->join('water_second_consumers', 'water_second_consumers.id', '=', 'water_consumer_demands.consumer_id')
            ->leftjoin('users', 'users.id', 'water_consumer_demands.emp_details_id')
            ->where('water_consumer_demands.' . $key, 'LIKE', '%' . $refNo . '%')
            ->orderByDesc('water_consumer_demands.id');
        // ->where('users.user_type', 'TC');
    }

    /**
     * | Get Consumer Demand 
     *   and demand date 
     * | @param ConsumerId
     */
    public function getConsumerDetailById($consumerId)
    {
        // Execute the query and select the columns
        return  WaterConsumerDemand::where('consumer_id', $consumerId)
            ->where('paid_status', 1)
            ->orderbydesc('id');
    }
    /**
     * get actual amount
     */
    public function getActualamount($demandId)
    {
        return WaterConsumerDemand::where('id', $demandId)
            ->where('status', true);
    }
    /**
     * ward wise demand report
     */
    public function wardWiseConsumer($fromDate, $uptoDate, $wardId, $ulbId, $perPage)
    {
        return WaterConsumerDemand::select(
            'water_consumer_demands.*',
            'water_second_consumers.consumer_no',
            'water_consumer_owners.guardian_name',
            'water_second_consumers.mobile_no',
            'water_second_consumers.address',
            'water_consumer_demands.balance_amount',
            'water_second_consumers.ward_mstr_id',
            'ulb_ward_masters.ward_name as ward_no'
        )
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_demands.consumer_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_second_consumers.id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->where('water_consumer_demands.paid_status', 0)
            ->where('water_consumer_demands.demand_from', '>=', $fromDate)
            ->where('water_consumer_demands.demand_upto', '<=', $uptoDate)
            ->where('water_second_consumers.ulb_id', $ulbId)
            ->where('water_second_consumers.ward_mstr_id', $wardId)
            ->where('water_consumer_demands.status', true)
            ->groupby(
                'water_consumer_demands.consumer_id',
                'water_consumer_demands.balance_amount',
                'water_consumer_demands.id',
                'water_second_consumers.consumer_no',
                'water_consumer_owners.guardian_name',
                'water_second_consumers.mobile_no',
                'water_second_consumers.address',
                'water_second_consumers.ward_mstr_id',
                'ulb_ward_masters.ward_name'
            );
        // ->get();
    }
    /**
     * | Get Payed Consumer Demand
     * | @param ConsumerId
     */
    public function consumerDemandId($demandId)
    {
        return WaterConsumerDemand::where('id', $demandId)
            ->where('is_full_paid', 0)
            ->where('status', '<>', 0);
    }
    /**
     * get tc detail 
     */
    public function tcReport($date, $userId)
    {
        return WaterConsumerDemand::select(
            'water_consumer_demands.*',
            'users.user_name as tcName',
            'users.name as empName',
            'water_consumer_owners.applicant_name as ownerName'

        )
            ->join('users', 'users.id', 'water_consumer_demands.emp_details_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_consumer_demands.consumer_id')
            ->join('water_second_consumers','water_second_consumers.id','water_consumer_demands.consumer_id')
            ->where('water_consumer_demands.generation_date', $date)
            ->where('water_consumer_demands.emp_details_id', $userId)
            ->orderBy('water_consumer_demands.emp_details_id','DESC')
            ->count('water_consumer_demands.id')
            ->get();
    }
}
