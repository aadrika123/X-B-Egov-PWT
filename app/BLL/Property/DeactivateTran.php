<?php

namespace App\BLL\Property;

use App\Models\Payment\TempTransaction;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropAdjustment;
use App\Models\Property\PropAdvance;
use App\Models\Property\PropDemand;
use App\Models\Property\PropPendingArrear;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSaf;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * | Created On - 28/09/2023 
 * | Author - Anshu Kumar
 * | Email-9939anshu@gmail.com
 * | Objective-Transaction Deactivation of Property and Saf
 * | Required @param TransactionID to Deactivate
 * | Code - Open
 */

class DeactivateTran
{
    public $_tranId;
    private $_mPropTranDtl;
    private $_transaction;
    private $_tranDtls;
    private $_mTempTrans;
    private $_PropAdvance ;
    private $_PropAdjustment;
    private $_PropPendingArrear;

    public function __construct($tranId)
    {
        $this->_tranId = $tranId;
        $this->_mPropTranDtl = new PropTranDtl();
        $this->_mTempTrans = new TempTransaction();
        $this->_PropAdvance = new PropAdvance();
        $this->_PropAdjustment = new PropAdjustment();
        $this->_PropPendingArrear= new PropPendingArrear();
    }


    /**
     * | Deactivate transation of property or saf(1)
     */
    public function deactivate()
    {
        $this->_transaction = PropTransaction::find($this->_tranId);

        if (collect($this->_transaction)->isEmpty())
            throw new Exception("Transaction not found");

        if ($this->_transaction->is_arrear_settled == false)
            $this->_tranDtls = $this->_mPropTranDtl->getTranDemandsByTranId($this->_transaction->id);


        $this->deactivatePropTrans();                       // 1.1

        $this->deactivateSafTrans();                        // (1.2)  🔴🔴 Yet to complete
        $this->deactivateSafProccessTrans();
        $this->_transaction->status = 0;                    // Deactivation
        $this->_transaction->save();

        $this->deactivateTempTrans();                       // (1.3) 
        $this->_PropAdvance->deactivateAdvanceByTrId($this->_transaction->id);
        $this->_PropAdjustment->deactivateAdjustmentAmtByTrId($this->_transaction->id);
        $this->AdjustArreaInterast();
       
    }


    /**
     * | Deactive Property Transactions (1.1)
     */
    public function deactivatePropTrans()
    {
        if ($this->_transaction->tran_type == 'Property') {
            $propId = $this->_transaction->property_id;
            foreach ($this->_tranDtls as $tranDtl) {
                $demand = PropDemand::find($tranDtl->prop_demand_id);
                if (collect($demand)->isEmpty())
                    throw new Exception("Demand Not Available for demand ID $tranDtl->prop_demand_id");
                $demand->paid_status = 0;
                $demand->balance = $demand->total_tax - $demand->adjust_amt;
                $demand->save();

                // Tran Dtl Deactivation
                $tranDtl = PropTranDtl::find($tranDtl->id);
                $tranDtl->status = 0;               // Deactivation of tran Details
                $tranDtl->save();

                /**
                 * | Condition for Reverting in case of Part wise Payment
                 */
                if ($demand->is_full_paid == false) {
                    $demand->due_alv = $demand->due_alv + $tranDtl->paid_alv;
                    $demand->due_maintanance_amt = $demand->due_maintanance_amt + $tranDtl->paid_maintanance_amt;
                    $demand->due_aging_amt = $demand->due_aging_amt + $tranDtl->paid_aging_amt;
                    $demand->due_general_tax = $demand->due_general_tax + $tranDtl->paid_general_tax;
                    $demand->due_road_tax = $demand->due_road_tax + $tranDtl->paid_road_tax;
                    $demand->due_firefighting_tax = $demand->due_firefighting_tax + $tranDtl->paid_firefighting_tax;
                    $demand->due_education_tax = $demand->due_education_tax + $tranDtl->paid_education_tax;
                    $demand->due_water_tax = $demand->due_water_tax + $tranDtl->paid_water_tax;
                    $demand->due_cleanliness_tax = $demand->due_cleanliness_tax + $tranDtl->paid_cleanliness_tax;
                    $demand->due_sewarage_tax = $demand->due_sewarage_tax + $tranDtl->paid_sewarage_tax;
                    $demand->due_tree_tax = $demand->due_tree_tax + $tranDtl->paid_tree_tax;
                    $demand->due_professional_tax = $demand->due_professional_tax + $tranDtl->paid_professional_tax;
                    $demand->due_total_tax = $demand->due_total_tax + $tranDtl->paid_total_tax;
                    $demand->due_balance = $demand->due_balance + $tranDtl->paid_balance;
                    $demand->due_adjust_amt = $demand->due_adjust_amt + $tranDtl->paid_adjust_amt;
                    $demand->due_tax1 = $demand->due_tax1 + $tranDtl->paid_tax1;
                    $demand->due_tax2 = $demand->due_tax2 + $tranDtl->paid_tax2;
                    $demand->due_tax3 = $demand->due_tax3 + $tranDtl->paid_tax3;
                    $demand->due_sp_education_tax = $demand->due_sp_education_tax + $tranDtl->paid_sp_education_tax;
                    $demand->due_water_benefit = $demand->due_water_benefit + $tranDtl->paid_water_benefit;
                    $demand->due_water_bill = $demand->due_water_bill + $tranDtl->paid_water_bill;
                    $demand->due_sp_water_cess = $demand->due_sp_water_cess + $tranDtl->paid_sp_water_cess;
                    $demand->due_drain_cess = $demand->due_drain_cess + $tranDtl->paid_drain_cess;
                    $demand->due_light_cess = $demand->due_light_cess + $tranDtl->paid_light_cess;
                    $demand->due_major_building = $demand->due_major_building + $tranDtl->paid_major_building;
                    $demand->paid_total_tax = $demand->paid_total_tax - $tranDtl->paid_total_tax;
                    $demand->paid_status = 1;

                    // Check the condition of first part payment We have to make it revertable as previous
                    if ($demand->paid_total_tax == 0) {
                        $demand->paid_status = 0;
                        $demand->is_full_paid = true;
                        $demand->has_partwise_paid = false;
                    }

                    $demand->save();
                }
            }
        }

        $property = PropProperty::find($propId);
        if (collect($property)->isEmpty())
            throw new Exception("Property Not Available for this Property ID $propId");

        $property->balance = $this->_transaction->arrear_settled_amt;
        $property->save();
    }

    /**
     * | Deactive saf transactions (1.2)
     */
    public function deactivateSafTrans()
    {
        if ($this->_transaction->tran_type == 'Saf') {
        }
    }

    public function deactivateSafProccessTrans()
    {
        if ($this->_transaction->tran_type == 'Saf Proccess Fee') {
            $saf = PropActiveSaf::find($this->_transaction->saf_id);
            if(!$saf){
                $saf = PropSaf::find($this->_transaction->saf_id);
            }
            if(!$saf)
            {
                throw new Exception("Saf Not Available for this Saf ID ".$this->_transaction->saf_id);
            }
            $saf->proccess_fee_paid = $saf->proccess_fee > 0 ? 0 :1;
            $saf->save();
        }
    }

    /**
     * | Deactivate Temp Transactions
     */
    public function deactivateTempTrans()
    {
        $tempTrans = $this->_mTempTrans->getTempTranByTranId($this->_tranId, 1);                // 1 is the module id for property
        if ($tempTrans)
            $tempTrans->update(['status' => 0]);
    }

    #Adjust Arrea Interast

    public function AdjustArreaInterast()
    { 
        if ($this->_transaction->tran_type == 'Property') 
        {
            $OldInterest = $this->_PropPendingArrear->where("prop_id",$this->_transaction->property_id)->where("status",1)->get();
            foreach($OldInterest as $interast)
            {
                
                $paymentHistory = collect(json_decode($interast->due_total_interest_pay_json))->where("tran_id",$this->_transaction->id);
                $paiedAmount = $paymentHistory->sum("amount");
                $newPaymentHistory = (collect(json_decode($interast->due_total_interest_pay_json))->where("tran_id","<>",$this->_transaction->id)->values());
                $interast->due_total_interest = ($interast->due_total_interest + $paiedAmount);
                $interast->due_total_interest_pay_json = json_encode($newPaymentHistory);
                $interast->paid_status = $interast->due_total_interest > 0 ? 0 : 1 ;
                $interast->save();
            }
        }
    }
}
