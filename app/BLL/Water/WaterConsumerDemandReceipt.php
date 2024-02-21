<?php

namespace App\BLL\Water;

use App\Models\Property\ZoneMaster;
use App\Models\UlbMaster;
use App\Models\User;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterConsumerInitialMeter;
use App\Models\Water\WaterConsumerMeter;
use App\Models\Water\WaterConsumerOwner;
use App\Models\Water\WaterConsumerTax;
use App\Models\Water\WaterMeterReadingDoc;
use App\Models\Water\WaterSecondConsumer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class WaterConsumerDemandReceipt
{
    public $_GRID;
    public $_mTowards;

    private $_userDtl;
    private $_consumerId;
    private $_dueDemandsList;
    private $_consumerDtls;
    private $_wardName;
    private $_zoneName;
    private $_ownersDtls;
    private $_connectionDtls;
    private $_meterReadings;
    private $_demandFrom;
    private $_demandUpto;
    private $_demandNo;
    private $_currentDemand;
    private $_currentDemandAmount;
    private $_arrearDemand;
    private $_arrearDemandAmount;
    private $_currentReadingDate;
    private $_prevuesReadingDate;
    private $_currentDemandFrom;
    private $_currentDemandUpto;
    private $_connectionType;
    private $_categoryType;
    private $_currentBillDays;
    private $_meterStatus;
    private $_lastTowReading;
    private $_lastFiveTax;
    private $_lastTax;
    private $_fromUnit;
    private $_uptoUnit;
    private $_consumptionUnit;
    private $_meterReadingDocuments;
    private $_docUrl;
    private $_meterImg;
    private $_now;
    private $_billDate;
    private $_billDueDate;
    private $_totalDemand;
    private $_totalPenalty;

    private $_mWaterSecondaryConsumers;
    private $_mWaterConsumerOwners;
    private $_mWaterConsumerDemands;
    private $_mUlbWards;
    private $_mZones;
    private $_mWaterConsumerTax;
    private $_mWaterConsumerMeter;
    private $_mWaterConsumerInitialMeter;
    private $_mWaterMeterReadingDoc ;
    private $_mUsers;

    public function __construct($consumerId)
    {
        $this->_consumerId = $consumerId;
        $this->_now        = Carbon::now();
        $this->_docUrl     = Config::get("waterConstaint.DOC_URL");
        $this->_mTowards = Config::get('waterConstaint.TOWARDS_DEMAND');
        $this->_mWaterSecondaryConsumers = new WaterSecondConsumer();
        $this->_mWaterConsumerOwners     = new WaterConsumerOwner();
        $this->_mWaterConsumerDemands    = new WaterConsumerDemand();
        $this->_mWaterConsumerTax        = new WaterConsumerTax();
        $this->_mWaterConsumerMeter      = new WaterConsumerMeter();
        $this->_mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
        $this->_mWaterMeterReadingDoc    = new WaterMeterReadingDoc();
        $this->_mUlbWards                = new UlbMaster();
        $this->_mZones                   = new ZoneMaster();
        $this->_mUsers                   = new User();
    }

    private function setConsumerDtl()
    {
        $this->_connectionDtls  = $this->_mWaterSecondaryConsumers->find($this->_consumerId);
        if(!$this->_connectionDtls)
        {
            throw new Exception("Invalid Consumer Id");
        }
        $this->_connectionDtls->zone_name = $this->_mZones->find($this->_connectionDtls->zone_mstr_id)->zone_name??"";
        $this->_connectionDtls->ward_name = $this->_mUlbWards->find($this->_connectionDtls->ward_mstr_id)->ward_name??"";
    }
    private function setOwnersDtl()
    {
        $this->_ownersDtls      = $this->_mWaterConsumerOwners->where("consumer_id",$this->_consumerId)->where("status",true)->orderBy("id","ASC")->get();
    }

    private function setDueDemandList()
    {
        $this->_dueDemandsList  = $this->_mWaterConsumerDemands->getConsumerDemandV3($this->_consumerId);
        $this->_demandFrom = $this->_dueDemandsList->min("demand_from");
        
        $this->_demandUpto = $this->_dueDemandsList->max("demand_upto");        
        $lastDemands  = collect($this->_dueDemandsList)->where("demand_upto",$this->_demandUpto)->sortBy("generation_date")->first();

        $this->_userDtl = $this->_mUsers->find($lastDemands->emp_details_id);

        $lastTaxId = $lastDemands->consumer_tax_id??null;
        $prevuesReadingDemand = collect();        
        $this->_demandNo = $lastDemands->demand_no;
        $prevuesReadingDemand = $this->_mWaterConsumerDemands->where("consumer_id",$this->_consumerId)->where(function($where) use($lastTaxId){
            $where->OrWhere("consumer_tax_id","<>",$lastTaxId)
            ->orWhereNull("consumer_tax_id");
        })
        ->orderBy("consumer_tax_id","DESC")
        ->first();        
        if($lastTaxId)
        {            
            $this->_demandFrom = collect($this->_dueDemandsList)->where("consumer_tax_id",$lastTaxId)->min("demand_from");
            $this->_demandUpto = collect($this->_dueDemandsList)->where("consumer_tax_id",$lastTaxId)->max("demand_upto");
        }
        $this->_currentDemand = collect(collect($this->_dueDemandsList)->where("consumer_tax_id",$lastTaxId)->values());

        $currenBillDemadFrom = collect($this->_currentDemand)->min("demand_from");
        $currenBillDemadUpto = collect($this->_currentDemand)->max("demand_upto");

        $this->_arrearDemand = collect(collect($this->_dueDemandsList)->where("consumer_tax_id","<>",$lastTaxId)->values());
        $this->_currentDemandAmount = round($this->_currentDemand->sum("due_balance_amount"),2);
        $this->_arrearDemandAmount = round($this->_arrearDemand->sum("due_balance_amount"),2);

        $this->_totalDemand         = collect($this->_dueDemandsList)->sum('due_balance_amount');
        $this->_totalDemand         = round($this->_totalDemand,2);

        $this->_currentReadingDate = collect($this->_currentDemand)->max("generation_date");
        $this->_prevuesReadingDate = $prevuesReadingDemand->generation_date??null;
        $this->_currentReadingDate = $this->_currentReadingDate ? Carbon::parse($this->_currentReadingDate)->format("d-m-Y") : "";
        $this->_prevuesReadingDate = $this->_prevuesReadingDate ? Carbon::parse($this->_prevuesReadingDate)->format("d-m-Y") : "";

        $this->_demandFrom = Carbon::parse($this->_demandFrom)->format("d-m-Y");
        $this->_demandUpto = Carbon::parse($this->_demandUpto)->format("d-m-Y");
        $this->_currentBillDays = $currenBillDemadFrom && $currenBillDemadUpto ? (Carbon::parse($currenBillDemadFrom)->diffInDays(Carbon::parse($currenBillDemadUpto))+1):null;
        
    }

    private function setLastFiveTax()
    {
        $this->_lastFiveTax      = $this->_mWaterConsumerTax->select("id","charge_type","initial_reading","final_reading")
                                    ->where("consumer_id",$this->_consumerId)
                                    ->where("status",1)
                                    ->orderBy("id","DESC")
                                    ->limit(5)
                                    ->get();
        $this->_lastFiveTax = collect($this->_lastFiveTax)->map(function($val){
                $demandFromUpto = $this->_mWaterConsumerDemands->select(DB::raw("min(demand_from)AS demand_from,max(demand_upto)As demand_upto"))
                                    ->where("consumer_tax_id",$val->id)
                                    ->where("status",true)
                                    ->first();
                $val->demand_from = $demandFromUpto->demand_from ? Carbon::parse($demandFromUpto->demand_from)->format("d-m-Y"):"";
                $val->demand_upto = $demandFromUpto->demand_upto ? Carbon::parse($demandFromUpto->demand_upto)->format("d-m-Y"):"";
                $val->unit_consume = $val->final_reading - $val->initial_reading;
                return $val;

        });
    }

    private function setMeterReadingImage()
    {
        $this->_meterReadingDocuments   = $this->_mWaterMeterReadingDoc->getDocByDemandId($this->_dueDemandsList->max("id"));
        $this->_meterImg = ($this->_meterReadingDocuments ? ($this->_docUrl . "/" . $this->_meterReadingDocuments->relative_path . "/" . $this->_meterReadingDocuments->file_name) : "");
    }

    private function setLastUnitConsume()
    {
        $this->_lastTax = $this->_lastFiveTax->first();
        $this->_meterStatus = ($this->_lastTax->charge_type??"Fixed");
        if($this->_consumptionUnit != ($this->_lastTax->final_reading -$this->_lastTax->initial_reading))
        {
            $this->_lastFiveTax[0]["initial_reading"] = $this->_fromUnit; 
            $this->_lastFiveTax[0]["final_reading"]   = $this->_uptoUnit;
            $this->_lastFiveTax[0]["unit_consume"]    = $this->_consumptionUnit;
        }
    }

    private function setParams()
    {        
        $this->setConsumerDtl();
        $this->setOwnersDtl();
        $this->setDueDemandList();
        $this->setLastFiveTax();
        $this->setMeterReadingImage();
        
        $this->_billDate            = Carbon::parse($this->_now->copy())->format("d-m-Y");
        $this->_billDueDate         = Carbon::parse($this->_now->copy())->addDays(15)->format('d-m-Y');
        

        $this->_lastTowReading  = $this->_mWaterConsumerInitialMeter->calculateUnitsConsumed($this->_consumerId);
        $this->_fromUnit            = $this->_lastTowReading->min("initial_reading");
        $this->_uptoUnit            = $this->_lastTowReading->max("initial_reading");
        $this->_consumptionUnit     = $this->_uptoUnit - $this->_fromUnit;

        $this->setLastUnitConsume();
    }

    public function setReceipts()
    {
        $this->setParams();     
        $this->_GRID = [
            "demandType"            => $this->_mTowards,
            "consumerNo"            => $this->_connectionDtls->consumer_no,
            "fromDate"              => $this->_demandFrom,
            "uptoDate"              => $this->_demandUpto,
            "demandNo"              => $this->_demandNo,
            "taxNo"                 => $this->_connectionDtls->property_no ,
            "billDate"              => $this->_billDate ,
            "billDueDate"           => $this->_billDueDate ,
            "billDueDate"           => $this->_billDueDate ,
            "currentDemand"         => $this->_currentDemand,
            "arrearDemand"         => $this->_arrearDemand,
            "userDtl"              => $this->_userDtl,
            "userName"             => $this->_userDtl->name??"",
            "consumerDtl"          => $this->_consumerDtls,
            "ownersDtl"            => $this->_ownersDtls,
            "consumerName"         => collect($this->_ownersDtls)->implode("applicant_name",", "),
            "mobileNo"             => collect($this->_ownersDtls)->implode("mobile_no",", "),  
            "consumerAddress"      => $this->_connectionDtls->address, 
            "zoneName"          => $this->_connectionDtls->zone_name,  
            "WardName"          => $this->_connectionDtls->Ward_name,  
            "connectionDate"    => $this->_connectionDtls->connection_date ? Carbon::parse($this->_connectionDtls->connection_date)->format("d-m-Y"):"",  
            "connectionType"    => $this->_connectionDtls->category ? $this->_connectionDtls->category:"General",    
            "tabSize"           => $this->_connectionDtls->tab_size ,    
            "oldPropertyNo"     => $this->_connectionDtls->property_no , 
            "newPropertyNo"     => $this->_connectionDtls->holding_no , 
            "meterNo"           => $this->_meterReadingDocuments->meter_no??"",
            "currentReadingDate"    => $this->_currentReadingDate,
            "currentReading"        => $this->_uptoUnit,
            "previousReadingDate"   => $this->_prevuesReadingDate,
            "previousReading"       => $this->_fromUnit,
            "totalUnitUsed"         => $this->_consumptionUnit,
            "meterStatus"           => $this->_meterStatus,
            "meterImage"            => $this->_meterStatus=="Meter" ? $this->_meterImg : "",
            "previousReadingDtls"   => $this->_lastFiveTax,
            "billPeriodInDay"       => $this->_currentBillDays,
            "billOutstandingDetails"=> [
                                            "currentBillAmount" => $this->_currentDemandAmount,
                                            "arrearBillAmount"  => $this->_arrearDemandAmount,
                                            "adjustmentBill"    => 0, 
                                            "totalOutstanding"  => $this->_totalDemand,
                                            "beforeDueDate"     => $this->_totalDemand,
                                        ],
        ];
    }

    public function generateDemandReceipts()
    {
        $this->setReceipts();
    }


    
}