<?php

use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterAdjustment;
use App\Models\Water\WaterAdvance;
use App\Models\Water\WaterChequeDtl;
use App\Models\Water\WaterConsumerCollection;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterTranDetail;
use Illuminate\Support\Facades\Config;

class WaterConsumerPaymentRecite
{
    private $_mUlbMasters;
    private $_COMMON_FUNCTION;
    private $_WaterAdvance;
    private $_WaterAdjustment;
    private $_WaterDemandsModel;
    private $_mWaterTrans;
    private $_mWaterTranDtl;
    private $_mConsumerCollection;
    private $_ulbWardMaster;
    private $_mWaterChequeDtl;

    public array $_GRID;
    private $_advanceAmt = 0;
    private $_adjustAmt = 0;
    public $_REQ;
    public $waterDemands;
    public $_tranNo;
    public $_trans;
    public $_tranType;
    protected $_gatewayType = null;
    public $_tranId;
    protected $_DB_NAME;
    protected $_DB;
    protected $_DB_MASTER;
    protected $_error;
    protected $_mTowards ; 
    protected $_mTranType;
    protected $_mDepartmentSection;

    public function __construct()
    {
        $this->_mUlbMasters = new UlbMaster();
        $this->_COMMON_FUNCTION = new \App\Repository\Common\CommonFunction();
        $this->_WaterAdvance = new WaterAdvance();
        $this->_WaterAdjustment = new WaterAdjustment();
        $this->_WaterDemandsModel = new WaterConsumerDemand();
        $this->_mWaterTrans = new WaterTran();
        $this->_mWaterTranDtl = new WaterTranDetail();
        $this->_mConsumerCollection = new WaterConsumerCollection();
        $this->_ulbWardMaster = new UlbWardMaster();
        $this->_mWaterChequeDtl     = new WaterChequeDtl();
    }

    /**
     * | Generate Payment Receipt
     */
    public function generateReceipt($tranNo, $tranId = null)
    {
        $this->_tranNo = $tranNo;
        $this->_tranId = $tranId;
        $this->readParams();
        $this->addConsumerDtls();
    }

    /**
     * | Read parameters
     */
    public function readParams()
    {
        $this->_mTowards = Config::get('waterConstaint.TOWARDS_DEMAND');
        $this->_mTranType          = Config::get("waterConstaint.PAYMENT_FOR");
        $this->_mDepartmentSection = Config::get('waterConstaint.DEPARTMENT_SECTION');

        if (isset($this->_tranId))
            $trans = $this->_mWaterTrans->getTranById($this->_tranId);
        else
            $trans = $this->_mWaterTrans->getTranByTranNo($this->_tranNo);

        $this->_trans = $trans;
        if (collect($trans)->isEmpty())
            throw new Exception("Transaction Not Available for this Transaction No");

        $this->_GRID['transactionNo'] = $trans->tran_no;
        $this->_tranType = $trans->tran_type;                // Property or SAF       
        $this->_advanceAmt = $this->_WaterAdvance->getAdvanceAmtByTrId($this->_trans->id)->sum("amount");
        $this->_adjustAmt = $this->_WaterAdjustment->getAdjustmentAmtByTrId($this->_trans->id)->sum("amount");
        
        $tranDtls = $this->_mPropTranDtl->getTranDemandsByTranId($trans->id);
        // $this->_propertyDtls = $this->_mPropProperty->getBasicDetails($trans->property_id);             // Get details from property table
        $this->_propertyDtls = $this->_mPropProperty->getBasicDetailsV2($trans->property_id); 

        if ($this->_tranType == 'Property') {                                   // Get Property Demands by demand ids
            if (collect($this->_propertyDtls)->isEmpty())
                throw new Exception("Property Details not available");
        }

        if ($this->_tranType == 'Saf') {                                   // Get Saf Demands by demand ids
            $this->_propertyDtls = $this->_mPropSaf->getBasicDetails($trans->saf_id);                       // Get Details from saf table
            if (collect($this->_propertyDtls)->isEmpty())
                throw new Exception("Saf Details not available");
        }

        $this->_ulbDetails = $this->_mUlbMasters->getUlbDetails($this->_propertyDtls->ulb_id);

        $this->_GRID['penaltyRebates'] = [];
        if (collect($tranDtls)->isNotEmpty()) {
            $this->_isArrearReceipt = false;
            if ($this->_tranType == 'Property') {                                   // Get Property Demands by demand ids
                $demandIds = collect($tranDtls)->pluck('prop_demand_id')->toArray();
                $demandsList = $this->_mPropDemands->getDemandsListByIds($demandIds);
                foreach ($demandsList as $list) {
                    $paidTranDemands =  collect($tranDtls)->where("prop_demand_id",$list->id)->first();
                    $list->general_tax = $paidTranDemands->paid_general_tax;
                    $list->road_tax = $paidTranDemands->paid_road_tax;
                    $list->firefighting_tax = $paidTranDemands->paid_firefighting_tax;
                    $list->education_tax = $paidTranDemands->paid_education_tax;
                    $list->water_tax = $paidTranDemands->paid_water_tax;
                    $list->cleanliness_tax = $paidTranDemands->paid_cleanliness_tax;
                    $list->sewarage_tax = $paidTranDemands->paid_sewarage_tax;
                    $list->tree_tax = $paidTranDemands->paid_tree_tax;
                    $list->professional_tax = $paidTranDemands->paid_professional_tax;
                    $list->total_tax = $paidTranDemands->paid_total_tax;
                    $list->balance = $paidTranDemands->paid_balance;
                    $list->tax1 = $paidTranDemands->paid_tax1;
                    $list->tax2 = $paidTranDemands->paid_tax2;
                    $list->tax3 = $paidTranDemands->paid_tax3;
                    $list->sp_education_tax = $paidTranDemands->paid_sp_education_tax;
                    $list->water_benefit = $paidTranDemands->paid_water_benefit;
                    $list->water_bill = $paidTranDemands->paid_water_bill;
                    $list->sp_water_cess = $paidTranDemands->paid_sp_water_cess;
                    $list->drain_cess = $paidTranDemands->paid_drain_cess;
                    $list->light_cess = $paidTranDemands->paid_light_cess;
                    $list->major_building = $paidTranDemands->paid_major_building;
                    $list->open_ploat_tax = $paidTranDemands->paid_open_ploat_tax;                    
                }
                $this->_GRID['penaltyRebates'] = $this->_mPropPenaltyRebates->getPenaltyRebatesHeads($trans->id, "Property");
            }

            if ($this->_tranType == 'Saf') {                                   // Get Saf Demands by demand ids
                $demandIds = collect($tranDtls)->pluck('saf_demand_id')->toArray();
                $demandsList = $this->_mPropDemands->getDemandsListByIds($demandIds);
                $this->_GRID['penaltyRebates'] = $this->_mPropPenaltyRebates->getPenaltyRebatesHeads($trans->id, "Saf");
            }

            $this->_GRID['arrearPenalty'] = collect($this->_GRID['penaltyRebates'])->where('head_name', 'Monthly Penalty')->first()->amount ?? 0;
            $currentDemand = $demandsList->where('fyear', $currentFyear);
            $this->_currentDemand = $this->aggregateDemand($currentDemand, true);       // Current Demand true
            $this->_currentDemand['arrearPenalty'] = 0;

            $overdueDemand = $demandsList->where('fyear', '<>', $currentFyear);
            $this->_overDueDemand = $this->aggregateDemand($overdueDemand);

            $this->_overDueDemand["advancePaidAmount"]    =  0;
            $this->_overDueDemand["advancePaidAmount"]    = 0;
            $this->_overDueDemand["netAdvance"] = 0;

            $this->_currentDemand["FinalTax"] =   $this->_currentDemand["FinalTax"] + (($this->_advanceAmt??0) - ($this->_adjustAmt??0) );    
            $this->_currentDemand["advancePaidAmount"]    =  ($this->_adjustAmt??0) ;
            $this->_currentDemand["advancePaidAmount"]    =  ($this->_advanceAmt??0) ;
            $this->_currentDemand["netAdvance"] =  (($this->_advanceAmt??0) - ($this->_adjustAmt??0)) ;

            $this->_GRID['overdueDemand'] = $this->_overDueDemand;
            $this->_GRID['currentDemand'] = $this->_currentDemand;

            $aggregateDemandList = new Collection([$this->_currentDemand, $this->_overDueDemand]);
            $aggregateDemand = $this->aggregateDemand($aggregateDemandList);
            $aggregateDemand["FinalTax"] =   round($aggregateDemand["FinalTax"] + (($this->_advanceAmt??0) - ($this->_adjustAmt??0) ));    
            $aggregateDemand["advancePaidAmount"]    =  ($this->_adjustAmt??0) ;
            $aggregateDemand["advancePaidAmount"]    =  ($this->_advanceAmt??0) ;
            $aggregateDemand["netAdvance"] =  (($this->_advanceAmt??0) - ($this->_adjustAmt??0)) ;
            $this->_GRID['aggregateDemand'] = $aggregateDemand;   
        }
    }
}