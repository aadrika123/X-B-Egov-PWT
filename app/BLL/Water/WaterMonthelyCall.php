<?php

namespace App\BLL\Water;

use App\Models\Water\WaterApplication;
use App\Models\Water\WaterConsumer;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterConsumerInitialMeter;
use App\Models\Water\WaterConsumerMeter;
use App\Models\Water\WaterParamDemandCharge;
use App\Models\Water\WaterParamFreeUnit;
use App\Models\Water\WaterSecondConsumer;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | Created On :- 29-08-2023 
 * | Author     :- Sam kerketta
 * | Status     :- Semi Closed
 * | Calculation of the consumer demand according to AMC  
 */
class WaterMonthelyCall
{
    private $_consumerId;
    private $_mWaterConsumer;
    private $_mWaterParamDemandCharge;
    private $_mWaterParamFreeUnit;
    private $_consumerCharges;
    private $_consumerFeeUnits;
    private $_consuemrDetails;
    private $_mWaterConsumerDemand;
    private $_consumerLastDemand;
    private $_toDate;
    private $_now;
    private $_consumerLastUnpaidDemand;
    private $_mWaterConsumerInitialMeter;
    private $_unitConsumed;
    private $_tax;
    private $_consumerLastMeterReding;
    private $_catagoryType;
    private $_meterStatus;
    private $_mWaterConsumerMeter;
    private $_ConsumerMeters;
    private $_consuemrMeterDetails;
    private $lastRearding;
    private $_testMeterFixeRateCondition ;
    private $_meterFixeRate ;
    private $_meterFixeRateF;
    private $_testMeterFixeRateConditionF;
    private $_fromDate;
    private $_uptoDate;
    private $_monthsArray;
    private $_dateDifference;
    private $_dalyUnitConsumed;
    # Class cons
    public function __construct(int $consumerId, $toDate, $unitConsumed)
    {
        $this->_unitConsumed            = $unitConsumed ?? 0;
        $this->_consumerId              = $consumerId;
        $this->_toDate                  = Carbon::parse($toDate);
        $this->_now                     = Carbon::now();
        $this->_catagoryType            = Config::get('waterConstaint.AKOLA_CATEGORY');
        $this->_mWaterConsumer          = new WaterSecondConsumer();
        $this->_mWaterParamDemandCharge = new WaterParamDemandCharge();
        $this->_mWaterParamFreeUnit     = new WaterParamFreeUnit();
        $this->_mWaterConsumerDemand    = new WaterConsumerDemand();
        $this->_mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
        $this->_mWaterConsumerMeter     = new WaterConsumerMeter();
    }


    /**
     * | Parent function 
     * | Distribution of the calculation process into function 
     */
    public function parentFunction()
    {
        $this->readParamsForCall();                     // 1
        $this->monthelyDemandCall();                    // 2
        // $this->generateDemand();                        // 3
        $this->generateDemandV2();
        // dd($this->_tax,$this->_dalyUnitConsumed,($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading));
        return $this->_tax;
    }

    /**
     * | params assigning for calculation 
     * | Get all the params and data from database
        | Collect the consumer meter details       
     */
    public function readParamsForCall()
    {
        $catagory = collect($this->_catagoryType)->flip();
        # Check the existence of consumer 
        $this->_consuemrDetails = $this->_mWaterConsumer->getConsumerDetailsById($this->_consumerId)
            ->where('status', 1)                                                                            // Static
            ->first();
        $this->_ConsumerMeters = $this->_mWaterConsumerMeter->getConsumerMeterDetails($this->_consumerId)
            ->where('status', 1)
            ->orderBy("id","DESC")                                                                            // Static
            ->first();;
        if ($this->_consuemrDetails->category == $catagory['1']) {
            $catagoryId = $this->_catagoryType['Slum'];
        } else {
            $catagoryId = $this->_catagoryType['General'];
        }

        # Get the charges for call 
        $chargesParams = new Request([
            "propertyType"      => $this->_consuemrDetails->property_type_id,
            "areaCatagory"      => $catagoryId,
            "connectionSize"    => $this->_consuemrDetails->tab_size,
            "meterState"        => $this->_ConsumerMeters->connection_type,
        ]);

        # Assigning the global var 
        $this->_consumerCharges         = $this->_mWaterParamDemandCharge->getConsumerCharges($chargesParams);
        $this->_consumerFeeUnits        = $this->_mWaterParamFreeUnit->getFeeUnits($chargesParams);
        // $this->_consumerLastDemand      = $this->_mWaterConsumerDemand->akolaCheckConsumerDemand($this->_consumerId)->first();
        $this->_consumerLastDemand      = ($this->_mWaterConsumerDemand->akolaCheckConsumerDemand($this->_consumerId)->get())->sortByDesc("demand_upto")->first();
        $this->_consumerLastMeterReding = $this->_mWaterConsumerInitialMeter->getmeterReadingAndDetails($this->_consumerId)->orderByDesc('id')->first();
        $this->_consuemrMeterDetails    = $this->_mWaterConsumerMeter->getMeterDetailsByConsumerId($this->_consumerId)->first();
        $this->_testMeterFixeRateCondition = ($this->_consumerFeeUnits->condition_unit??0)/30;
        $this->_testMeterFixeRateConditionF = ($this->_consumerFeeUnits->condition_unit??0);
        $this->_meterFixeRate = ($this->_consumerCharges->amount??0)/30;
        $this->_meterFixeRateF = ($this->_consumerCharges->amount??0);
        $this->_meterStatus = "Fixed";
        if ($this->_consuemrMeterDetails->connection_type == 1) {
            $this->_meterStatus = "Meter"; 
        }

        $useStartDate       = $this->_consumerLastDemand &&  $this->_consumerLastDemand->demand_upto ? $this->_consumerLastDemand->demand_upto : ($this->_consuemrDetails && $this->_consuemrDetails->connection_date ? $this->_consuemrDetails->connection_date : $this->_consuemrMeterDetails->connection_date);
        $startDate          = (Carbon::parse($useStartDate))->addDay();

        $this->_fromDate = $startDate->copy()->format("Y-m-d");         
        $uptoDate = $this->_toDate->copy()->format("Y-m-d");        
        if($this->_meterStatus=="Fixed" && $uptoDate!=$this->_toDate->copy()->endOfMonth())
        {
            $uptoDate = Carbon::parse($uptoDate)->subMonth()->endOfMonth()->format("Y-m-d");
        }
        $this->_uptoDate = $uptoDate;
        $this->_toDate = Carbon::parse($this->_uptoDate);
    }

    /**
     * | Consumer calculation 
     * | Checking the params before calculation
        | Check the meter status for meter and non meter  
     */
    public function monthelyDemandCall()
    {
        switch ($this->_consumerId) {
            case (!$this->_consuemrDetails):
                throw new Exception("Consumer details not found!");
                break;

            case (!$this->_consumerCharges):
                throw new Exception("Consumer charges not found!");
                break;

            case (!$this->_consumerFeeUnits):
                throw new Exception("Consumer free units not found!");
                break;
        }

        # Charges for month 
        $lastDemandDate = $this->_consumerLastDemand->demand_upto ?? ($this->_consuemrDetails->connection_date?$this->_consuemrDetails->connection_date:$this->_consuemrMeterDetails->connection_date);
        if (!$lastDemandDate) {
            throw new Exception("Demand date not Found!");
        }
        $lastDemandMonth    = Carbon::parse($lastDemandDate)->format('Y-m');
        $currentMonth       = Carbon::parse($this->_now->copy())->format('Y-m');

        # Check if the last demand is generated for the month
        if (!$lastDemandDate) {
            // if ($lastDemandMonth >= $currentMonth) {
            //     throw new Exception("demand is generated till $lastDemandDate!");
            // }
        }
        if($this->_consumerLastDemand && $this->_meterStatus!="Meter" && $lastDemandMonth >= $currentMonth)
        {
            throw new Exception("demand is generated till this month $lastDemandDate");
        }
        # ❗❗ Check the connection type for the consumer ❗❗
        if (!$this->_consuemrMeterDetails) {
            throw new Exception("update Connection detials!");
        }
        if ($this->_consuemrMeterDetails->connection_type == 1 && $this->_unitConsumed < ($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading))
        {   
                throw new Exception("Final Reading should be greater than previous reading!");
        }
        if($this->_fromDate > $this->_uptoDate)
        {
            throw new Exception("From date can't be smaller than upto date!(from date = $this->_fromDate, upto date = $this->_uptoDate )");
        }

    }

    /**
     * | Generta demand 
     * | Actual calculation
        | Check the comment 
        | check the demand generation process according to days
     */
    public function generateDemand()
    {
        # Switch between meter connection and non meter connection 
        switch ($this->_meterStatus) {
            case ("Meter"):                                                                          // Static
                # If the consumer demand exist the following process will continue with respective of last demand
                if ($this->_consumerLastDemand) {
                    $monthsArray        = [];
                    $startDate          = Carbon::parse($this->_consumerLastDemand->demand_upto);
                    $endDate            = Carbon::parse($this->_now);
                    $refEndDate         = $endDate;
                    $refStartDate       = $startDate;
                    $startDateCount     = 0;
                }
                # If the demand is generated for the first time
                else {
                    $endDate            = Carbon::parse($this->_now);
                    $startDate          = Carbon::parse($this->_consuemrDetails->connection_date);
                    $startDateCount     = 0;
                    $refEndDate         = $endDate;
                    $refStartDate       = $startDate;
                }

                # Check if the date diff in starting month
                if ($startDate->copy()->format('d') != "01") {                                                  // Static
                    $startDateCount = $startDate->diffInDays($startDate->copy()->endOfMonth());
                    $refStartDate   = ($startDate->copy())->firstOfMonth()->addMonth();
                }
                $monthsDifference   = $refStartDate->diffInMonths($endDate);
                $dateDifference     = $startDate->diffInDays($endDate);
                $daysInEndMonth     = $endDate->copy()->endOfMonth()->day;

                # If the end date is the last day of the month, don't count it
                if ($endDate->day == $daysInEndMonth) {
                    $monthsDifference--;
                    $dateCount = 0;
                } else {
                    $dateCount = $endDate->day;
                    $refEndDate = ($endDate->copy()->subMonth())->lastOfMonth();
                }

                # get all the month between dates
                if ($monthsDifference != 0) {
                    $currentDate = $refStartDate->copy();
                    while ($currentDate->lt($refEndDate)) {
                        $monthsArray[] = $currentDate->format('Y-m-01');
                        $currentDate->addMonth();
                    }
                }
                $testcurruntDate =(collect($monthsArray)->filter(function($val){
                    return(Carbon::parse($val)->between((Carbon::parse(Carbon::now()->format('Y-m-01'))),(Carbon::parse(Carbon::now()->lastOfMonth())))) ;
                    
                }));
                if($testcurruntDate->isEmpty())
                {
                    $monthsArray[] = Carbon::now()->format("Y-m-01");
                }
                # daly consumed unit
                $dalyUnitConsumed = ($this->_unitConsumed - ($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading)) / $dateDifference;
                $this->lastRearding = ($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading);
                # If the statrt day diff exist
                if ($startDateCount > 0) {
                    $amount = $this->_consumerFeeUnits->unit_fee * ($startDateCount * $dalyUnitConsumed);
                    if ($amount < 0) {
                        $amount = 0;
                    }
                    $startDateWiseCall[] = [
                        "generation_date"       => $this->_now,
                        "amount"                => $amount,
                        "current_meter_reading" => $this->_unitConsumed,
                        "unit_amount"           => 1,                                               // Static
                        "demand_from"           => $startDate->format('Y-m-d'),                                     // Static
                        "demand_upto"           => $startDate->copy()->endOfMonth()->format('Y-m-d'),
                        "connection_type"       => $this->_meterStatus,
                    ];
                    $refReturnData = collect($startDateWiseCall);
                }

                # Monthely demand generation 
                
                $returnData = collect($monthsArray)->map(function ($values, $key)
                use ($dalyUnitConsumed) {
                    $lastDateOfMonth = Carbon::parse($values)->endOfMonth();
                    $totalDayOfmonth = $noOfDays = $lastDateOfMonth->day;
                    if((Carbon::parse($values)->between((Carbon::parse(Carbon::now()->format('Y-m-01'))),(Carbon::parse(Carbon::now()->lastOfMonth())))))
                    {
                        $lastDateOfMonth = Carbon::now();
                        $noOfDays = $lastDateOfMonth->day;
                    }
                    // $refCallMonthAmount = ($this->_consumerFeeUnits->unit_fee * (($noOfDays * $dalyUnitConsumed) - 10));                         // Static the free amount per month
                    $refCallMonthAmount = ((($noOfDays * $dalyUnitConsumed) ));  
                    $amount = round($this->_consumerFeeUnits->unit_fee * $refCallMonthAmount,2);
                    if (($refCallMonthAmount)  <=  10 && $totalDayOfmonth == $noOfDays) {
                        // $refCallMonthAmount = 0;
                        $amount = round($this->_consumerCharges->amount,2);
                    }
                    if((Carbon::parse($values)->between((Carbon::parse(Carbon::now()->format('Y-m-01'))),(Carbon::parse(Carbon::now()->lastOfMonth())))) 
                        && $totalDayOfmonth != $noOfDays
                        && ($totalDayOfmonth * $dalyUnitConsumed <=  10)
                    )
                    {
                        $amount = round(($this->_consumerCharges->amount/$totalDayOfmonth) * $noOfDays,2);
                    }
                    // $amount = $refCallMonthAmount + $this->_consumerCharges->amount;
                    if ($amount < 0) { 
                        $amount = 0;
                    }
                    $this->lastRearding = $this->lastRearding +$refCallMonthAmount;
                    return [
                        "generation_date"       => $this->_now,
                        "amount"                => $amount,
                        "current_meter_reading" => $this->_unitConsumed,
                        "current_month_counsumen"=> $refCallMonthAmount,
                        "current_reading"   =>$this->lastRearding,
                        "unit_amount"           => 1,                                           // Statisc
                        "demand_from"           => $values,                                     // Static
                        "demand_upto"           => $lastDateOfMonth->format('Y-m-d'),
                        "connection_type"       => $this->_meterStatus,
                    ];
                });

                # If the statrt day diff exist
                if ($startDateCount > 0) {
                    $returnData = $refReturnData->merge($returnData);
                }

                # If the day diff exist
                // if ($dateCount > 0) {
                //     $demandFrom = $endDate->copy()->startOfMonth()->format('Y-m-d');
                //     $amount = $this->_consumerFeeUnits->unit_fee * ($dateCount * $dalyUnitConsumed);
                //     if ($amount < 0) {
                //         $amount = 0;
                //     }
                //     $dateWiseCall[] = [
                //         "generation_date"       => $this->_now,
                //         "amount"                => $amount,
                //         "current_meter_reading" => $this->_unitConsumed,
                //         "unit_amount"           => 1,                                               // Static
                //         "demand_from"           => $demandFrom,                                     // Static
                //         "demand_upto"           => $endDate->format('Y-m-d'),
                //         "connection_type"       => $this->_meterStatus,
                //     ];
                //     $returnData = $returnData->merge(collect($dateWiseCall));
                // }
            
                # show taxes
                $this->_tax = [
                    "status" => true,
                    "consumer_tax" => [
                        [
                            "fee_unit"          => $this->_consumerFeeUnits->unit_fee,                              // the fee unit 
                            "charge_type"       => $this->_meterStatus,
                            "rate_id"           => $this->_consumerCharges->id,
                            "effective_from"    => $startDate->format('Y-m-d'),
                            "initial_reading"   => $this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading,
                            "final_reading"     => $this->_unitConsumed,
                            "amount"            => $returnData->sum('amount'),
                            "consumer_demand"   => $returnData->toArray(),
                        ]
                    ]
                ];
                break;

                # For fixed connection calculation 
            case ("Fixed"):                                                                         // Static
                $monthsArray        = [];
                $useStartDate       = $this->_consumerLastDemand->demand_upto ?? $this->_consuemrDetails->connection_date;
                $endDate            = Carbon::parse($this->_now->copy())->endOfMonth();
                $startDate          = ((Carbon::parse($useStartDate))->firstOfMonth())->addMonth();

                # get all the month between dates
                $currentDate = $startDate->copy();
                while ($currentDate->lt($endDate)) {
                    $monthsArray[] = $currentDate->format('Y-m-01');
                    $currentDate->addMonth();
                }

                # demand generation
                $returnData = collect($monthsArray)->map(function ($values, $key) {
                    $lastDateOfMonth = Carbon::parse($values)->endOfMonth();
                    $amount = $this->_consumerCharges->amount;                                  // look over here
                    return [
                        "generation_date"       => $this->_now,
                        "amount"                => $amount,
                        "current_meter_reading" => $this->_unitConsumed,
                        "unit_amount"           => 1,                                           // Statisc
                        "demand_from"           => $values,                                     // Static
                        "demand_upto"           => $lastDateOfMonth->format('Y-m-d'),
                        "connection_type"       => $this->_meterStatus,
                    ];
                });

                $this->_tax = [
                    "status" => true,
                    "consumer_tax" => [
                        [
                            "charge_type"       => $this->_meterStatus,
                            "rate_id"           => $this->_consumerCharges->id,
                            "effective_from"    => $startDate->format('Y-m-d'),
                            "initial_reading"   => $this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading,
                            "final_reading"     => $this->_unitConsumed,
                            "amount"            => $returnData->sum('amount'),
                            "consumer_demand"   => $returnData->toArray(),
                        ]
                    ]
                ];
                break;
            default:
                throw new Exception("Demand generation process works only for meter and non meter connection!");
                break;
        }
    }

    private function generateDemandV2()
    {
        $this->setMonteList();
        $this->generateMeterDemand();
        $this->generateFixedDemand(); 

    }

    private function setMonteList()
    {
        $monthsArray        = [];
        $useStartDate       = $this->_consumerLastDemand ? $this->_consumerLastDemand->demand_upto : $this->_consuemrDetails->connection_date;
        $endDate            = Carbon::parse($this->_now->copy()->subMonth())->endOfMonth();
        $startDate          = (Carbon::parse($useStartDate))->addDay();
        // $currentDate = $startDate->copy();
        $currentDate = Carbon::parse($this->_fromDate);        
        while ($currentDate->lt($this->_toDate)) {
            $monthsArray[] = ($startDate->copy())->format('Y-m-d') !=($currentDate->copy())->format('Y-m-d') ? ($currentDate->copy())->format('Y-m-01'):($startDate->copy())->format('Y-m-d');            
            if(Carbon::parse($currentDate->copy())->day == Carbon::parse($currentDate->copy())->endOfMonth()->day)
            {
                $currentDate->addDay();
            }
            else
            {
                $currentDate->addMonth();
            }
        }
        
        $testcurruntDate =(collect($monthsArray)->filter(function($val){
            // return(Carbon::parse($val)->between((Carbon::parse(Carbon::now()->format('Y-m-01'))),(Carbon::parse(Carbon::now()->lastOfMonth())))) ;
            return(Carbon::parse($val)->between((Carbon::parse($this->_toDate->format('Y-m-01'))),(Carbon::parse($this->_toDate->lastOfMonth())))) ;
            
        }));
        
        if($this->_meterStatus=="Meter" && $testcurruntDate->isEmpty())
        {
            $monthsArray[] = ($this->_toDate->copy())->format("Y-m-01");
        }      
        $fromDate = Carbon::parse($this->_fromDate);
        $uptoDate = Carbon::parse($this->_uptoDate);;        
        $dateDifference     = $fromDate->diffInDays($uptoDate)+1;
        $this->_monthsArray = $monthsArray;
        $this->_dateDifference = $dateDifference; 
        $this->_dalyUnitConsumed = ($this->_unitConsumed - ($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading)) / $this->_dateDifference;      
    }

    private function generateMeterDemand()
    {
        
        if($this->_meterStatus=="Meter")
        {  
            # daly consumed unit
            $dalyUnitConsumed = $this->_dalyUnitConsumed;
            $this->lastRearding = ($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading);
            // dd($this->_dateDifference,$dalyUnitConsumed,$this->_dateDifference*$dalyUnitConsumed,($this->_unitConsumed - ($this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading)));
            # Monthely demand generation             
            $returnData = collect($this->_monthsArray)->map(function ($values, $key)
            use ($dalyUnitConsumed) {
                $lastDateOfMonth = Carbon::parse($values)->endOfMonth();
                $totalDayOfmonth =  $lastDateOfMonth->day;
                $fromDate = Carbon::parse($values);
                $uptoDate = Carbon::parse($values)->endOfMonth();
                $noOfDays = $fromDate->diffInDays($uptoDate)+1;
                if((Carbon::parse($values)->between((Carbon::parse(Carbon::parse($this->_uptoDate)->format('Y-m-01'))),(Carbon::parse(Carbon::parse($this->_uptoDate)->lastOfMonth())))))
                {
                    $lastDateOfMonth = Carbon::parse($this->_uptoDate);
                    $noOfDays = $fromDate->diffInDays($lastDateOfMonth)+1;
                }

                $refCallMonthAmount = ((($noOfDays * $dalyUnitConsumed) ));  
                $amount = round($this->_consumerFeeUnits->unit_fee * $refCallMonthAmount,2);    
                $unitAmount =  $this->_consumerFeeUnits->unit_fee;
                /* 
                if (($dalyUnitConsumed)  <=  $this->_testMeterFixeRateCondition)                
                {
                    $amount = $totalDayOfmonth < 30 ? $this->_consumerCharges->amount : $totalDayOfmonth * $this->_meterFixeRate;                    
                    $unitAmount =  $this->_meterFixeRate;
                }
                if($totalDayOfmonth != $noOfDays && ($dalyUnitConsumed  <=  $this->_testMeterFixeRateCondition)
                )
                {
                    $amount = round(($this->_consumerCharges->amount/$totalDayOfmonth) * $noOfDays,2);
                    $unitAmount =  $this->_consumerCharges->amount/$totalDayOfmonth;
                }
                */
                $this->_testMeterFixeRateCondition = $this->_testMeterFixeRateConditionF/$totalDayOfmonth;
                $this->_meterFixeRate = $this->_meterFixeRateF/$totalDayOfmonth;
                if (($dalyUnitConsumed)  <=  $this->_testMeterFixeRateCondition)
                {
                    $amount = $totalDayOfmonth < 30 ? $this->_consumerCharges->amount : $totalDayOfmonth * $this->_meterFixeRate;                    
                    $unitAmount =  $this->_meterFixeRate;
                }
                if($totalDayOfmonth != $noOfDays && ($dalyUnitConsumed  <=  $this->_testMeterFixeRateCondition)
                )
                {
                    $amount = round(($this->_consumerCharges->amount/$totalDayOfmonth) * $noOfDays,2);
                    $unitAmount =  $this->_consumerCharges->amount/$totalDayOfmonth;                    
                }
                         
                if ($amount < 0) { 
                    $amount = 0;
                }                
                $this->lastRearding = $this->lastRearding + $refCallMonthAmount;
                return [
                    "generation_date"       => $this->_now,
                    "amount"                => $amount,
                    "current_meter_reading" => $this->_unitConsumed,
                    "current_month_counsumen"=> $refCallMonthAmount,
                    "current_reading"       =>$this->lastRearding,
                    "no_of_day"           => $noOfDays,
                    "total_of_day"         =>$totalDayOfmonth,
                    "unit_amount"           => $unitAmount,                                           // Statisc
                    "demand_from"           => $values,                                     // Static
                    "demand_upto"           => $lastDateOfMonth->format('Y-m-d'),
                    "connection_type"       => $this->_meterStatus,
                ];
            });

            
            # show taxes
            $this->_tax = [
                "status" => true,
                "consumer_tax" => [
                    [
                        "fee_unit"          => $this->_consumerFeeUnits->unit_fee,                              // the fee unit 
                        "charge_type"       => $this->_meterStatus,
                        "rate_id"           => $this->_consumerCharges->id,
                        "effective_from"    => $this->_fromDate,
                        "initial_reading"   => $this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading,
                        "final_reading"     => $this->_unitConsumed,
                        "amount"            => $returnData->sum('amount'),
                        "consumer_demand"   => $returnData->toArray(),
                    ]
                ]
            ];           
        }
    }

    private function generateFixedDemand()
    {
        if($this->_meterStatus=="Fixed")
        {    
            # demand generation
            $returnData = collect($this->_monthsArray)->map(function ($values, $key) {
                $lastDateOfMonth = Carbon::parse($values)->endOfMonth();
                $amount = $this->_consumerCharges->amount;
                $unitAmount =  $this->_consumerCharges->amount;                                  // look over here
                return [
                    "generation_date"       => $this->_now,
                    "amount"                => $amount,
                    "current_meter_reading" => $this->_unitConsumed,
                    "unit_amount"           => $unitAmount,                                           // Statisc
                    "demand_from"           => $values,                                     // Static
                    "demand_upto"           => $lastDateOfMonth->format('Y-m-d'),
                    "connection_type"       => $this->_meterStatus,
                ];
            });
    
            $this->_tax = [
                "status" => true,
                "consumer_tax" => [
                    [
                        "charge_type"       => $this->_meterStatus,
                        "rate_id"           => $this->_consumerCharges->id,
                        "effective_from"    => $this->_fromDate,
                        "initial_reading"   => $this->_consumerLastMeterReding->initial_reading ?? $this->_consuemrMeterDetails->initial_reading,
                        "final_reading"     => $this->_unitConsumed,
                        "amount"            => $returnData->sum('amount'),
                        "consumer_demand"   => $returnData->toArray(),
                    ]
                ]
            ]; 
        }
    }

    public function checkDemandGenerationCondition()
    {
        $this->readParamsForCall();
        $today                  = ($this->_now->copy())->format("Y-m-d");
        $lastDemand = $this->_consumerLastDemand;
        $lastDemandUpto = Carbon::parse($this->_fromDate)->subDay()->format('Y-m-d');
        $lastDemandQuater = calculateQtr($lastDemandUpto);
        $lastDemandYear = Carbon::parse($lastDemandUpto)->format("Y");
        $lastDemandGenerationQuater = calculateQtr($lastDemand->generation_date ? $lastDemand->generation_date : $lastDemandUpto);
        $lastDemandGenerationYear = Carbon::parse($lastDemand->generation_date ? $lastDemand->generation_date : $lastDemandUpto)->format("Y");
        $currentDateQuater = calculateQtr($today);
        $currentDateYear =  Carbon::parse($today)->format("Y");
        $totalDayDiff = Carbon::parse($lastDemand->generation_date ? $lastDemand->generation_date : $lastDemandUpto)->diffInDays($this->_now->copy());
        if($lastDemandUpto >= $today)
        {
            throw new Exception("The demand is generated till ".$this->_fromDate);
        }
        // if($this->_meterStatus=="Meter" && $currentDateYear == $lastDemandYear && $lastDemandQuater == $currentDateQuater)
        // {
        //     throw new Exception("the demand is generated Of this Quater ");
        // }
        if($this->_meterStatus!="Meter" && $currentDateYear == $lastDemandGenerationYear && $lastDemandGenerationQuater == $currentDateQuater)
        {
            throw new Exception("The demand is generated of this Quater ");
        }
        if($this->_meterStatus=="Meter" && $totalDayDiff<45)
        {
            throw new Exception("Total difference between previous demand and current demand should be minimum 45 days.");
        } 
        $this->monthelyDemandCall();       
    }
    
}

