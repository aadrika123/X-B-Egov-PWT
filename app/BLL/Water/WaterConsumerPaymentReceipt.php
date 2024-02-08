<?php
namespace App\BLL\Water;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterAdjustment;
use App\Models\Water\WaterAdvance;
use App\Models\Water\WaterChequeDtl;
use App\Models\Water\WaterConsumerCollection;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterSecondConsumer;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterTranDetail;
use App\Models\Water\WaterTranFineRebate;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class WaterConsumerPaymentReceipt
{
    private $_mUlbMasters;
    private $_COMMON_FUNCTION;
    private $_WaterConsumer;
    private $_WaterAdvance;
    private $_WaterAdjustment;
    private $_WaterDemandsModel;
    private $_mWaterTrans;
    private $_mWaterTranDtl;
    private $_mConsumerCollection;
    private $_ulbWardMaster;
    private $_mWaterChequeDtl;
    private $_WaterPenaltyRebate;

    public array $_GRID;
    private $_advanceAmt = 0;
    private $_adjustAmt = 0;
    private $_REQ;
    private $waterDemands;
    private $_tranNo;
    private $_trans;
    private $_tranType;
    private $_tranDtls;
    private $_consumerDtls;
    private $_ulbDetails;
    private $_gatewayType = null;
    private $_tranId;
    private $_DB_NAME;
    private $_DB;
    private $_DB_MASTER;
    private $_error;
    private $_mTowards ; 
    private $_mTranType;
    private $_currentDemand;
    private $_overDueDemand;
    private $_demandsList;
    private $_mDepartmentSection;
    private $_accDescription;

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
        $this->_WaterConsumer = new WaterSecondConsumer();
        $this->_WaterPenaltyRebate = new WaterTranFineRebate();
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
        $this->_accDescription = Config::get('waterConstaint.ACCOUNT_DESCRIPTION');
        $this->_mDepartmentSection = Config::get('waterConstaint.DEPARTMENT_SECTION');

        if (isset($this->_tranId))
            $trans = $this->_mWaterTrans->getTranById($this->_tranId);
        else
            $trans = $this->_mWaterTrans->getTranByTranNo($this->_tranNo);
        
        $startingDateOfTranDate = Carbon::parse($trans->tran_date)->startOfMonth()->format('Y-m-d');
        $this->_trans = $trans;
        if (collect($trans)->isEmpty())
            throw new Exception("Transaction Not Available for this Transaction No");

        $this->_GRID['transactionNo'] = $trans->tran_no;
        $this->_tranType = $trans->tran_type;                // Property or SAF       
        $this->_advanceAmt = $this->_WaterAdvance->getAdvanceAmtByTrId($this->_trans->id)->sum("amount");
        $this->_adjustAmt = $this->_WaterAdjustment->getAdjustmentAmtByTrId($this->_trans->id)->sum("amount");
        
        $this->_tranDtls = $this->_mWaterTranDtl->getTransDemandByIds($trans->id)->get();

        $this->_consumerDtls = $this->_WaterConsumer->fullWaterDetailsV2($trans->related_id); 

        if ($this->_tranType == 'Demand Collection') {                                   // Get Property Demands by demand ids
            if (collect($this->_consumerDtls)->isEmpty())
                throw new Exception("Consumer Details not available");
        }

        $this->_ulbDetails = $this->_mUlbMasters->getUlbDetails($this->_consumerDtls->ulb_id);
        $this->_GRID['penaltyRebates'] = [];
        if (collect($this->_tranDtls)->isNotEmpty()) {
            if ($this->_tranType == 'Demand Collection') {                                   // Get Property Demands by demand ids
                $demandIds = collect($this->_tranDtls)->pluck('demand_id')->toArray();
                $demandsList = $this->_WaterDemandsModel->getDemandsListByIds($demandIds);
                $this->_demandsList = $demandsList ;
                foreach ($demandsList as $list) {
                    $paidTranDemands =  collect($this->_tranDtls)->where("demand_id",$list->id)->first();
                    $list->total_demand = $paidTranDemands->total_demand;
                    $list->paid_amount  = $paidTranDemands->paid_amount ;
                    $list->current_amount = ($paidTranDemands->sum("paid_amount") - $paidTranDemands->sum("arrear_settled"));
                    $list->arrear_amount = $paidTranDemands->arrear_settled;                                       
                }
                $this->_GRID['penaltyRebates'] = $this->_WaterPenaltyRebate->getPenaltyRebatesHeads($trans->id);
            }
            $currentDemand = $demandsList->where('demand_from',">=", $startingDateOfTranDate);
            $this->_currentDemand = $this->aggregateDemand($currentDemand, true);       // Current Demand true
            $this->_currentDemand['arrearPenalty'] = 0;

            $overdueDemand = $demandsList->where('fyear', '<', $startingDateOfTranDate);
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

      /**
     * | Aggregate Demand
     */
    public function aggregateDemand($demandList, $isCurrent = false)
    {
        $aggregate = $demandList->pipe(function ($item) use ($isCurrent) {
            $totalTax = roundFigure($item->sum('total_demand'));

            $paidAmount = roundFigure($item->sum('paid_amount'));
            $currentAmount = roundFigure($item->sum('current_amount'));
            $arrearAmount = roundFigure($item->sum('arrear_amount'));

            if ($isCurrent == 0)
                $arrearPenalty = roundFigure($this->_GRID['arrearPenalty']??0);              // 🔴🔴🔴 Condition Handled in case of other payments Receipt Purpose
            else
                $arrearPenalty = roundFigure(0);

            $totalPayableAmt = roundFigure($paidAmount);

            return [
                "currentAmount" => $currentAmount,
                "arrearAmount" => $arrearAmount,
                "arrearPenalty" => $arrearPenalty,
                "total_tax" => $totalTax,
                "FinalTax" => $totalPayableAmt
            ];
        }); 
        return collect($aggregate);
    }

    /**
     * | Property Details
     */
    public function addConsumerDtls()
    {
        $fromDate =  collect($this->_demandsList)->min("demand_from");
        $uptoDate =collect($this->_demandsList)->max("demand_upto"); 
        $duration = (Carbon::parse($fromDate)->format("d-M-Y")) ." to ".(Carbon::parse($uptoDate)->format("d-M-Y"));        
        $mobileDuration = (Carbon::parse($fromDate)->format("Y-m-d"))." to ".(Carbon::parse($uptoDate)->format("Y-m-d"));
        
        $receiptDtls = [
            "departmentSection" => $this->_mDepartmentSection,
            "accountDescription" =>  $this->_accDescription,
            "transactionDate" => Carbon::parse($this->_trans->tran_date)->format('d-m-Y'),
            "transactionNo" => $this->_trans->tran_no,
            "transactionTime" => Carbon::parse($this->_trans->created_at)->format('g:i A'),
            "chequeStatus" => $this->_trans->cheque_status??1, 
            "verifyStatus" => $this->_trans->verify_status,                     # (0-Not Verified,1-Verified,2-Under Verification,3-Bounce)
            "applicationNo" => $this->_consumerDtls->application_no??"",
            "customerName" => $this->_consumerDtls->applicant_marathi ?? "", 
            "ownerName" => $this->_consumerDtls->owner_name_marathi ?? "", 
            "guardianName" => trim($this->_consumerDtls->guardian_name??"") ? $this->_consumerDtls->guardian_name : $this->_consumerDtls->guardian_name_marathi??"",
            "mobileNo" => $this->_consumerDtls->mobile_no??"",
            "address" => $this->_consumerDtls->prop_address??"",
            "zone_name" => $this->_consumerDtls->zone_name??"",
            "paidFrom" => $this->_trans->from_fyear,
            "paidUpto" => $this->_trans->to_fyear,
            "paymentMode" => $this->_trans->payment_mode,
            "bankName" => $this->_trans->bank_name,
            "branchName" => $this->_trans->branch_name,
            "chequeNo" => $this->_trans->cheque_no,
            "chequeDate" => ymdToDmyDate($this->_trans->cheque_date),
            "demandAmount" => $this->_trans->demand_amt,
            "arrearSettled" => $this->_trans->arrear_settled_amt,
            "ulbId" => $this->_consumerDtls->ulb_id??"",
            "wardNo" => $this->_consumerDtls->ward_no??"",
            "propertyNo" => $this->_consumerDtls->property_no ?? "",
            "towards" => $this->_mTowards,
            "description" => [
                "keyString" => $this->_mTowards
            ],
            "totalPaidAmount" => $this->_trans->amount,
            "advancePaidAmount" => $this->_advanceAmt,
            "adjustAmount" => $this->_adjustAmt,
            "netAdvance"=>$this->_advanceAmt - $this->_adjustAmt, 
            "paidAmtInWords" => getIndianCurrency($this->_trans->amount),
            "tcName" => $this->_trans->tc_name,
            "tcMobile" => $this->_trans->tc_mobile,
            "ulbDetails" => $this->_ulbDetails,            
            "bookNo" => $this->_trans->book_no ?? "",
            "plot_no"=>$this->_consumerDtls->plot_no??"",
            "area_of_plot"=>$this->_consumerDtls->area_of_plot??"",
            "receiptNo" => isset($this->_trans->book_no) ? (explode('-', $this->_trans->book_no)[1]??"0") : "",                  
           'duration' => ($duration) ,
           'mobileDuration' => ($mobileDuration) ,
        ];

        $this->_GRID['receiptDtls'] = $receiptDtls;
    }
}