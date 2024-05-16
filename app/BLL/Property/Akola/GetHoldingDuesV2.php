<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropAdjustment;
use App\Models\Property\PropAdvance;
use App\Models\Property\PropDemand;
use App\Models\Property\PropOwner;
use App\Models\Property\PropPendingArrear;
use App\Models\Property\PropProperty;
use App\Models\Property\RefPropSpecialRebateType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | Created On-11-10-2023 
 * | Created By-Anshu Kumar 
 * | Created for Calculate the Holding Dues
 * | Version - 2
 * | Code - Closed
 */
class GetHoldingDuesV2
{
    private $_SpecialOffers;
    private $_TotalDisccountPer;
    private $_TotalDisccontAmt;
    public $_isSingleManArmedForce =false;
    public $_QuaveryRebates;
    public $_QuaveryRebatesDtls;
    public $_IsOldTranClear =true;

    public function setParams($from = null,$upto = null)
    {
        $this->_SpecialOffers = (new RefPropSpecialRebateType())->specialRebate($from,$upto);
        $this->_QuaveryRebates = collect(Config::get('akola-property-constant.FIRST_QUIETER_REBATE'))->where("effective_from","<=",Carbon::parse($from)->format("Y-m-d"))->where("upto_date",">=",Carbon::parse($from)->format("Y-m-d"))->where("from_date","<=",Carbon::parse($from)->format("Y-m-d"));
        $this->_QuaveryRebates =  new Collection($this->_QuaveryRebates);
        if($this->_QuaveryRebates)
        {
            $this->_QuaveryRebatesDtls = collect(Config::get('akola-property-constant.FIRST_QUIETER_REBATE'))->where("effective_from","<=",Carbon::parse($from)->format("Y-m-d"));
        }
        
        
    }

    public function testOldTranClear()
    {
        
    }
    public function getDues($req)
    {
        $this->setParams($req->TrnDate,$req->TrnDate);
        $mPropDemand = new PropDemand();
        $mPropProperty = new PropProperty();
        $mPropOwners = new PropOwner();
        $PropAdvance = new PropAdvance();
        $PropAdjustment = new PropAdjustment();
        $mUsers = new User();
        $demand = array();
        $mPropPendingArrear = new PropPendingArrear();
        // $revCalculateByAmt = new RevCalculateByAmt;              
        $demandList = collect();
        $calculate2PercPenalty = new Calculate2PercPenalty;
        $fy = getFY();
        $userId = auth()->user()->id ?? 0;
        $userDtls = $mUsers::find($userId);

        // Get Property Details
        $propBasicDtls = $mPropProperty->getPropBasicDtls($req->propId);
        $owners = $mPropOwners->getOwnersByPropId($req->propId);
        $armedForceOwners = collect($owners)->where("is_armed_force",true);
        if($armedForceOwners->isNotEmpty() && collect($owners)->count()==1)
        {
            $this->_isSingleManArmedForce =true;
        }
        DB::enableQueryLog();
        $totalAdvanceAmt = $PropAdvance->getAdvanceAmt($req->propId);
        $totalAdjustmentAmt = $PropAdjustment->getAdjustmentAmt($req->propId);
        $remainAdvance = $totalAdvanceAmt->sum("amount") - $totalAdjustmentAmt->sum("amount");

        // $arrear = $propBasicDtls->new_arrear;                           // 🔴🔴 Replaced with balance to new arrear

        // if ($arrear > 0) {
        //     $pendingArrearDtls = $mPropPendingArrear->getInterestByPropId($req->propId);            // 🔴🔴 Adjust Interest from Arrear
        //     $totalInterest = collect($pendingArrearDtls)->sum('total_interest');
        //     $interest = $totalInterest ?? 0;
        //     $arrear = $arrear - $interest;
        // }
        $demandList = $mPropDemand->getDueDemandByPropIdV2($req->propId)->map(function($val){
            $val->exempted_general_tax = $this->_isSingleManArmedForce ? $val->general_tax : 0;
            $val->due_exempted_general_tax = $this->_isSingleManArmedForce ? $val->due_general_tax : 0;
            return $val;
        });
        foreach ($demandList as $list) {
            if ($list->is_full_paid == false) {                                // In Case of Part Payment Get the Dues Payable amount
                $list->general_tax = $list->due_general_tax;
                $list->road_tax = $list->due_road_tax;
                $list->firefighting_tax = $list->due_firefighting_tax;
                $list->education_tax = $list->due_education_tax;
                $list->water_tax = $list->due_water_tax;
                $list->cleanliness_tax = $list->due_cleanliness_tax;
                $list->sewarage_tax = $list->due_sewarage_tax;
                $list->tree_tax = $list->due_tree_tax;
                $list->professional_tax = $list->due_professional_tax;
                $list->total_tax = $list->due_total_tax;
                $list->balance = $list->due_balance;
                $list->tax1 = $list->due_tax1;
                $list->tax2 = $list->due_tax2;
                $list->tax3 = $list->due_tax3;
                $list->state_education_tax = $list->due_state_education_tax;
                $list->water_benefit = $list->due_water_benefit;
                $list->water_bill = $list->due_water_bill;
                $list->sp_water_cess = $list->due_sp_water_cess;
                $list->drain_cess = $list->due_drain_cess;
                $list->light_cess = $list->due_light_cess;
                $list->major_building = $list->due_major_building;
                $list->open_ploat_tax = $list->due_open_ploat_tax;
                $list->exempted_general_tax = $list->due_exempted_general_tax;
            }
        }

        $demandList = collect($demandList);

        // if (isset($req->isArrear) && $req->isArrear)                            // If Citizen wants to pay only arrear from Payment function
        //     $demandList = $demandList->where('fyear', '<', $fy)->values();

        foreach ($demandList as $list) {
            $calculate2PercPenalty->calculatePenalty($list, $propBasicDtls->prop_type_mstr_id);
        }

        $demandList = collect($demandList)->sortBy('fyear')->values();

        if ($demandList->isEmpty())                              // Check the Payment Status
            $paymentStatus = 1;
        else
            $paymentStatus = 0;

        $grandTaxes = $this->sumTaxHelper($demandList);

        if ($grandTaxes['balance'] <= 0)
            $paymentStatus = 1;

        $demand['isSingleManArmedForce'] = $this->_isSingleManArmedForce;
        $demand['fromFyear'] = collect($demandList)->first()['fyear'] ?? "";
        $demand['uptoFyear'] = collect($demandList)->last()['fyear'] ?? "";
        $demand['demandList'] = $demandList;
        $currentDemandList = collect($demandList)->where('fyear', $fy)->values();
        $demand['currentDemandList'] = $this->sumTaxHelper($currentDemandList);
        $overDueDemandList = collect($demandList)->where('fyear', '!=', $fy)->values();
        $demand['overdueDemandList'] =  $this->sumTaxHelper($overDueDemandList);
        $demand['grandTaxes'] = $grandTaxes;
        $demand['currentDemand'] = roundFigure($demandList->where('fyear', $fy)->first()['balance'] ?? 0);

        $demand['arrear'] = roundFigure($demandList->where('fyear', '<', $fy)->sum('balance'));
        $twentyTwoDemandPaidStatus = $demandList->where('fyear', '=', '2022-2023')->first()->paid_status ?? 1;   // If We have the unpaid in 2022-2023 the add the interest

        // if ($twentyTwoDemandPaidStatus == 0)
        //     $previousInterest = $mPropPendingArrear->getInterestByPropId($req->propId)->total_interest ?? 0;
        // else
        //     $previousInterest = 0;

        $oldIntrest = $mPropPendingArrear->getInterestByPropId($req->propId);
        $previousInterest = $oldIntrest->due_total_interest ?? 0;

        // Monthly Interest Penalty Calculation
        $demand['previousInterest'] = $previousInterest;
        $demand['previousInterestId'] = $oldIntrest->id ?? 0;
        $demand['arrearInterest'] = roundFigure($demandList->where('fyear', '<', $fy)->sum('monthlyPenalty'));

        $demand['arrearMonthlyPenalty'] = roundFigure($demand['previousInterest'] + $demand['arrearInterest']);                   // Penalty On Arrear
        $demand['monthlyPenalty'] = roundFigure($demandList->where('fyear', $fy)->sum('monthlyPenalty'));                         // Monthly Penalty
        $demand['totalInterestPenalty'] = roundFigure($demand['arrearMonthlyPenalty'] + $demand['monthlyPenalty']);              // Total Interest Penalty


        // Read Rebate ❗❗❗ Rebate is pending
        $firstOwner = $mPropOwners->firstOwner($req->propId);
        

        // if($firstOwner->is_armed_force)
        //     // $rebate=
        $demand['remainAdvance'] = roundFigure($remainAdvance ?? 0);
        $demand['arrearPayableAmt'] = roundFigure($demand['arrear'] + $demand['arrearMonthlyPenalty']);
        $demand['payableAmt'] = roundFigure($grandTaxes['balance'] + $demand['totalInterestPenalty']);
        $demand['total_exempted_general_tax'] = roundFigure($grandTaxes['exempted_general_tax']);
        $demand['current_exempted_general_tax'] = roundFigure($demand['currentDemandList']['exempted_general_tax']??0);
        $demand['arrear_exempted_general_tax'] = roundFigure($demand['overdueDemandList']['exempted_general_tax']??0);
        
        $demand["rebates"] = $this->_SpecialOffers->map(function($val)use($demand){
            $rebateAmt = 0;
            $rebate = 0;
            if($val->apply_on_total_tax){
                $rebate = $val->rebates_in_perc ? ($demand['payableAmt']/100) * $val->rebates : $val->rebates;
                $rebateAmt += $rebate;
            }
            if($val->apply_on_arear_tax){
                $rebate = $val->rebates_in_perc ? ($demand['arrear']/100) * $val->rebates : $val->rebates;
                $rebateAmt += $rebate;
            }
            if($val->apply_on_total_intrest){
                $rebate = $val->rebates_in_perc ? ($demand['arrearMonthlyPenalty']/100) * $val->rebates : $val->rebates;
                $rebateAmt += $rebate;
            }
            if($val->apply_on_arear_intrest){
                $rebate = $val->rebates_in_perc ? ($demand['arrearInterest']/100) * $val->rebates : $val->rebates;
                $rebateAmt += $rebate;
            }
            if($val->apply_on_priv_intrest){
                $rebate = $val->rebates_in_perc ? ($demand['previousInterest']/100) * $val->rebates : $val->rebates;
                $rebateAmt += $rebate;
            }
            $val->rebates_amt = roundFigure($rebateAmt);
            return $val;
        });
        $demand["QuarterlyRebates"] = $this->_QuaveryRebates->map(function($val)use($demand){
            $rebateAmt = 0;
            $rebate = 0;
            if($val["apply_on_current_tax"]??false){
                $rebate = $val["rebates_in_perc"] ? ($demand['currentDemand']/100) * $val["rebates"] : $val["rebates"];
                $rebateAmt += $rebate;
            }
            $val["rebates_amt"] = roundFigure($rebateAmt);
            return $val;
        });
        $demand["QuarterlyRebates"] = $demand["QuarterlyRebates"]->values();
        $demand["QuarterlyRebatesDtls"] = collect($this->_QuaveryRebatesDtls)->map(function($val)use($demand){
            $rebateAmt = 0;
            $rebate = 0;
            if($val["apply_on_current_tax"]??false){
                $rebate = $val["rebates_in_perc"] ? ($demand['currentDemand']/100) * $val["rebates"] : $val["rebates"];
                $rebateAmt += $rebate;                
            }
            $val["rebates_amt"] = roundFigure($rebateAmt);
            $val["payableAmt"]=roundFigure($demand['payableAmt'] - $rebateAmt); 
            return $val;
        });
        $demand['currentDemandList']["shasti_abhay_yojan"] = 0;
        $demand['overdueDemandList']["shasti_abhay_yojan"] = roundFigure(collect($demand["rebates"])->where("rebate_type","Shasti Abhay Yojana")->sum("rebates_amt"));
        $demand['grandTaxes']["shasti_abhay_yojan"] = $demand['currentDemandList']["shasti_abhay_yojan"] + $demand['overdueDemandList']["shasti_abhay_yojan"];

        $demand['currentDemandList']["first_quarter_rebates"] = roundFigure(collect($demand["QuarterlyRebates"])->where("rebate_type","First Quieter Rebate")->sum("rebates_amt"));
        $demand['overdueDemandList']["first_quarter_rebates"] = 0;
        $demand['grandTaxes']["first_quarter_rebates"] = $demand['currentDemandList']["first_quarter_rebates"] + $demand['overdueDemandList']["first_quarter_rebates"];

        $demand['currentDemandList']["totalPenalty"] = $demand['monthlyPenalty'];
        $demand['overdueDemandList']["totalPenalty"] = $demand['arrearMonthlyPenalty'];
        $demand['grandTaxes']["totalPenalty"] = $demand['totalInterestPenalty'];
        if ($demand['payableAmt'] > 0)
            $paymentStatus = 0;

        $demand['paymentStatus'] = $paymentStatus;

        // 🔴🔴 Property Payment and demand adjustments with arrear is pending yet 🔴🔴
        $holdingType = $propBasicDtls->holding_type;
        $ownershipType = $propBasicDtls->ownership_type;
        $basicDtls = collect($propBasicDtls)->only([
            'id',
            'holding_no',
            'new_holding_no',
            'ward_no',
            'property_type',
            'zone_name',
            'is_mobile_tower',
            'is_hoarding_board',
            'is_petrol_pump',
            'is_water_harvesting',
            'ulb_id',
            'prop_address',
            'land_occupation_date',
            'citizen_id',
            'user_id',
            'applicant_name',
            'property_no',
            "plot_no",
            "area_of_plot",
            "building_name",
        ]);
        $basicDtls['moduleId'] = 1;
        $basicDtls['workflowId'] = 0;
        $basicDtls["holding_type"] = $holdingType;
        $basicDtls["ownership_type"] = $ownershipType;
        $basicDtls["demand_receipt_date"] = Carbon::now()->format('d-m-Y');
        $basicDtls["tc_name"] = $userDtls->name ?? null;
        $basicDtls["owner_name"] = $owners->implode("owner_name", ",") ?? ($firstOwner->owner_name ?? null);
        $basicDtls["owner_name_marathi"] = $owners->implode("owner_name_marathi", ",") ?? $firstOwner->owner_name_marathi ?? null;

        $demand['basicDetails'] = $basicDtls;

        return $demand;
    }


    /**
     * | Sum Tax Helper(Branch function of get holding dues 2.1)
     */
    public function sumTaxHelper($demandList)
    {
        return $demandList->pipe(function ($item) {
            return [
                "general_tax" => roundFigure($item->sum('general_tax')),
                "exempted_general_tax" => roundFigure($item->sum('exempted_general_tax')),
                "road_tax" => roundFigure($item->sum('road_tax')),
                "firefighting_tax" => roundFigure($item->sum('firefighting_tax')),
                "education_tax" => roundFigure($item->sum('education_tax')),
                "water_tax" => roundFigure($item->sum('water_tax')),
                "cleanliness_tax" => roundFigure($item->sum('cleanliness_tax')),
                "sewarage_tax" => roundFigure($item->sum('sewarage_tax')),
                "tree_tax" => roundFigure($item->sum('tree_tax')),
                "professional_tax" => roundFigure($item->sum('professional_tax')),
                "tax1" => roundFigure($item->sum('tax1')),
                "tax2" => roundFigure($item->sum('tax2')),
                "tax3" => roundFigure($item->sum('tax3')),
                "state_education_tax" => roundFigure($item->sum('state_education_tax')),
                "water_benefit" => roundFigure($item->sum('water_benefit')),
                "water_bill" => roundFigure($item->sum('water_bill')),
                "sp_water_cess" => roundFigure($item->sum('sp_water_cess')),
                "drain_cess" => roundFigure($item->sum('drain_cess')),
                "light_cess" => roundFigure($item->sum('light_cess')),
                "major_building" => roundFigure($item->sum('major_building')),
                "total_tax" => roundFigure($item->sum('total_tax')),
                "balance" => roundFigure($item->sum('balance')),
                "open_ploat_tax" => roundFigure($item->sum('open_ploat_tax')),
                "monthlyPenalty" => roundFigure($item->sum('monthlyPenalty')),
            ];
        });
    }
}
