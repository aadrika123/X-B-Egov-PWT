<?php

namespace App\BLL\Property\Akola;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * | Author - Anshu Kumar
 * | Created On-21-09-2023 
 * | @desc Calculation for 2 perc penalty
 */
class Calculate2PercPenalty
{
    public $_Today;
    public $_assesmentType;
    public $_propSaf;
    /**
     * | @param demand 
     */
    public function calculatePenalty($demand,$prop_type_mstr_id=null)
    {
        $currentFy = getFY($this->_Today);
        // $currentMonth = Carbon::now()->format('m');
        $currentMonth = Carbon::parse($this->_Today)->format('m');        
        $now = Carbon::parse($this->_Today)->firstOfMonth()->format("Y-m-d");
        $currentFyMonths = $currentMonth - 4;                   // Start of the month april
        $monthlyBalance = 0;
        $noOfPenalMonths = 0;

        $demand = (object)$demand;
        if ($demand->fyear == $currentFy) {
            $noOfPenalMonths = 0;                               // Start of the month april
            $monthlyBalance = $demand->balance / 12;
        }
        // && $demand->has_partwise_paid == false
        if ($demand->fyear < $currentFy) {               // if the citizen has paid the tax part wise then avert the one percent penalty Calculation
            $noOfPenalMonths = 1;                                  // Start of the month april(if the fyear is past)
            if($demand->is_old)
            {
                list($fromYear,$uptoYear) = explode("-",$demand->fyear);
                $uptoDate = new Carbon($uptoYear."-09-01");
                $noOfPenalMonths = $uptoDate->diffInMonths($now);                
            }
            if(!$demand->is_old)
            {
                list($fromYear,$uptoYear) = explode("-",$demand->fyear);
                $uptoDate = new Carbon($uptoYear."-04-01");
                // $now = Carbon::now()->firstOfMonth()->format("Y-m-d");
                $noOfPenalMonths = $uptoDate->diffInMonths($now)+1;  
            } 
            
            if(!$demand->is_old &&  $prop_type_mstr_id==4 && $demand->created_at)
            {
                $noOfPenalMonths = (getFY($demand->created_at)==getFY())?0:$noOfPenalMonths;
            }  
            
                    
            $monthlyBalance = $demand->balance;
        }

        if ($demand->fyear > $currentFy) {
            $noOfPenalMonths = 0;
            $monthlyBalance = $demand->balance / 12;
        }

        $amount = $monthlyBalance * $noOfPenalMonths;
        $penalAmt = $amount * 0.02;
        // if($demand->fyear=='2021-2022'){
        //     dd($demand,$noOfPenalMonths,$amount,$penalAmt, Carbon::now()->firstOfMonth()->format("Y-m-d"),$monthlyBalance);
        // }
        // if($demand->fyear != $currentFy){
            // }
        if($this->_assesmentType==Config::get("PropertyConstaint.ASSESSMENT-TYPE.1") && $this->_propSaf && $demand->fyear <= getFY($this->_propSaf->saf_approved_date) && getFY($demand->created_at)<getFY()) {
            $penalAmt =0;
        }
            $demand->monthlyPenalty = roundFigure($penalAmt);
        
    }

    /**
     * | Calculate Arrear Penalty
     */
    public function calculateArrearPenalty($arrear)
    {
        $currentMonth = Carbon::now()->format('m');
        $currentFyMonths = $currentMonth - 4;
        $arrearPenalty = $arrear * $currentFyMonths * 0.02;
        return roundFigure($arrearPenalty);
    }
}
