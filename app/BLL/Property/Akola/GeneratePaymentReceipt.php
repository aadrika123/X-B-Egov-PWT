<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropDemand;
use App\Models\Property\PropPenaltyrebate;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSaf;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * | Author -Anshu Kumar
 * | Created On-09-09-2023 
 * | Created for - Payment Receipt for SAF Payment and Property Payment
 * | Status-Closed
 */

class GeneratePaymentReceipt
{
    private $_mPropProperty;
    private $_mPropTransaction;
    private $_mPropTranDtl;
    private $_mPropDemands;
    private $_tranNo;
    private $_tranId;
    private $_tranType;
    private $_currentDemand;
    private $_overDueDemand;
    private $_mPropPenaltyRebates;
    public array $_GRID;
    private $_trans;
    private $_mTowards;
    private $_mAccDescription;
    private $_mDepartmentSection;
    private $_propertyDtls;
    private $_ulbDetails;
    private $_mUlbMasters;
    private $_mPropSaf;
    private $_isArrearReceipt = true;

    /**
     * | Initializations of Variables
     */
    public function __construct()
    {
        $this->_mUlbMasters = new UlbMaster();
        $this->_mPropProperty = new PropProperty();
        $this->_mPropTransaction = new PropTransaction();
        $this->_mPropTranDtl = new PropTranDtl();
        $this->_mPropDemands = new PropDemand();
        $this->_mPropPenaltyRebates = new PropPenaltyrebate();
        $this->_mPropSaf = new PropSaf();
    }

    /**
     * | Generate Payment Receipt
     */
    public function generateReceipt($tranNo, $tranId = null)
    {
        $this->_tranNo = $tranNo;
        $this->_tranId = $tranId;
        $this->readParams();
        $this->addPropDtls();
    }

    /**
     * | Read parameters
     */
    public function readParams()
    {
        $this->_mTowards = Config::get('PropertyConstaint.SAF_TOWARDS');
        $this->_mAccDescription = Config::get('PropertyConstaint.ACCOUNT_DESCRIPTION');
        $this->_mDepartmentSection = Config::get('PropertyConstaint.DEPARTMENT_SECTION');

        $currentFyear = getFY();

        if (isset($this->_tranId))
            $trans = $this->_mPropTransaction->getPropByTranId($this->_tranId);
        else
            $trans = $this->_mPropTransaction->getPropByTranPropId($this->_tranNo);

        $this->_trans = $trans;
        if (collect($trans)->isEmpty())
            throw new Exception("Transaction Not Available for this Transaction No");

        $this->_GRID['transactionNo'] = $trans->tran_no;
        $this->_tranType = $trans->tran_type;                // Property or SAF 

        $tranDtls = $this->_mPropTranDtl->getTranDemandsByTranId($trans->id);
        $this->_propertyDtls = $this->_mPropProperty->getBasicDetails($trans->property_id);             // Get details from property table

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

            $this->_GRID['overdueDemand'] = $this->_overDueDemand;
            $this->_GRID['currentDemand'] = $this->_currentDemand;

            $aggregateDemandList = new Collection([$this->_currentDemand, $this->_overDueDemand]);
            $this->_GRID['aggregateDemand'] = $this->aggregateDemand($aggregateDemandList);
        }
    }

    /**
     * | Aggregate Demand
     */
    public function aggregateDemand($demandList, $isCurrent = false)
    {
        $aggregate = $demandList->pipe(function ($item) use ($isCurrent) {
            $totalTax = roundFigure($item->sum('total_tax'));

            $generalTax = roundFigure($item->sum('general_tax'));
            $roadTax = roundFigure($item->sum('road_tax'));
            $firefightingTax = roundFigure($item->sum('firefighting_tax'));
            $educationTax = roundFigure($item->sum('education_tax'));
            $waterTax = roundFigure($item->sum('water_tax'));
            $cleanlinessTax = roundFigure($item->sum('cleanliness_tax'));
            $sewarageTax = roundFigure($item->sum('sewarage_tax'));
            $treeTax = roundFigure($item->sum('tree_tax'));
            $professionalTax = roundFigure($item->sum('professional_tax'));
            $adjustAmt = roundFigure($item->sum('adjust_amt'));
            $tax1 = roundFigure($item->sum('tax1'));
            $tax2 = roundFigure($item->sum('tax2'));
            $tax3 = roundFigure($item->sum('tax3'));
            $spEducation = roundFigure($item->sum('sp_education_tax'));
            $waterBenefit = roundFigure($item->sum('water_benefit'));
            $waterBill = roundFigure($item->sum('water_bill'));
            $spWaterCess = roundFigure($item->sum('sp_water_cess'));
            $drainCess = roundFigure($item->sum('drain_cess'));
            $lightCess = roundFigure($item->sum('light_cess'));
            $majorBuilding = roundFigure($item->sum('major_building'));

            if ($isCurrent == 0)
                $arrearPenalty = roundFigure($this->_GRID['arrearPenalty']);              // 🔴🔴🔴 Condition Handled in case of other payments Receipt Purpose
            else
                $arrearPenalty = roundFigure(0);

            $totalPayableAmt = $generalTax +
                $roadTax +
                $firefightingTax +
                $educationTax +
                $waterTax +
                $cleanlinessTax +
                $sewarageTax +
                $treeTax +
                $professionalTax +
                $adjustAmt +
                $tax1 +
                $tax2 +
                $tax3 +
                $spEducation +
                $waterBenefit +
                $waterBill +
                $spWaterCess +
                $drainCess +
                $lightCess +
                $majorBuilding + $arrearPenalty;

            $totalPayableAmt = roundFigure($totalPayableAmt);

            return [
                "general_tax" => $generalTax,
                "road_tax" => $roadTax,
                "firefighting_tax" => $firefightingTax,
                "education_tax" => $educationTax,
                "water_tax" => $waterTax,
                "cleanliness_tax" => $cleanlinessTax,
                "sewarage_tax" => $sewarageTax,
                "tree_tax" => $treeTax,
                "professional_tax" => $professionalTax,
                "adjust_amt" => $adjustAmt,
                "tax1" => $tax1,
                "tax2" => $tax2,
                "tax3" => $tax3,
                "sp_education_tax" => $spEducation,
                "water_benefit" => $waterBenefit,
                "water_bill" => $waterBill,
                "sp_water_cess" => $spWaterCess,
                "drain_cess" => $drainCess,
                "light_cess" => $lightCess,
                "major_building" => $majorBuilding,
                "arrearPenalty" => $arrearPenalty,
                "totalPayableAmt" => $totalPayableAmt,
                "total_tax" => $totalTax,
                "exceptionUnderSAY" => roundFigure(0),
                "generalTaxException" => roundFigure(0),
                "payableAfterDeduction" => $totalPayableAmt,
                "advanceAmt" => roundFigure(0),
                "totalPayableAfterAdvance" => $totalPayableAmt,
                "noticeFee" => roundFigure(0),
                "FinalTax" => $totalPayableAmt
            ];
        });

        return collect($aggregate);
    }

    /**
     * | Property Details
     */
    public function addPropDtls()
    {
        $receiptDtls = [
            "departmentSection" => $this->_mDepartmentSection,
            "accountDescription" => $this->_mAccDescription,
            "transactionDate" => Carbon::parse($this->_trans->tran_date)->format('d-m-Y'),
            "transactionNo" => $this->_trans->tran_no,
            "transactionTime" => $this->_trans->created_at->format('g:i A'),
            "verifyStatus" => $this->_trans->verify_status,                     // (0-Not Verified,1-Verified,2-Under Verification,3-Bounce)
            "applicationNo" => $this->_propertyDtls->application_no,
            "customerName" => $this->_propertyDtls->applicant_marathi,          //trim($this->_propertyDtls->applicant_name) ? $this->_propertyDtls->applicant_name : $this->_propertyDtls->applicant_marathi,
            "ownerName" => $this->_propertyDtls->owner_name_marathi,            //trim($this->_propertyDtls->owner_name) ? $this->_propertyDtls->owner_name : $this->_propertyDtls->owner_name_marathi,
            "guardianName" => trim($this->_propertyDtls->guardian_name) ? $this->_propertyDtls->guardian_name : $this->_propertyDtls->guardian_name_marathi,
            "mobileNo" => $this->_propertyDtls->mobile_no,
            "address" => $this->_propertyDtls->prop_address,
            "zone_name" => $this->_propertyDtls->zone_name,
            "paidFrom" => $this->_trans->from_fyear,
            "paidUpto" => $this->_trans->to_fyear,
            "paymentMode" => $this->_trans->payment_mode,
            "bankName" => $this->_trans->bank_name,
            "branchName" => $this->_trans->branch_name,
            "chequeNo" => $this->_trans->cheque_no,
            "chequeDate" => ymdToDmyDate($this->_trans->cheque_date),
            "demandAmount" => $this->_trans->demand_amt,
            "arrearSettled" => $this->_trans->arrear_settled_amt,
            "ulbId" => $this->_propertyDtls->ulb_id,
            "wardNo" => $this->_propertyDtls->ward_no,
            "propertyNo" => $this->_propertyDtls->property_no ?? "",
            "towards" => $this->_mTowards,
            "description" => [
                "keyString" => "Holding Tax"
            ],
            "totalPaidAmount" => $this->_trans->amount,
            "paidAmtInWords" => getIndianCurrency($this->_trans->amount),
            "tcName" => $this->_trans->tc_name,
            "tcMobile" => $this->_trans->tc_mobile,
            "ulbDetails" => $this->_ulbDetails,
            "isArrearReceipt" => $this->_isArrearReceipt,
            "bookNo" => $this->_trans->book_no ?? "",
            "receiptNo" => isset($this->_trans->book_no) ? explode('-', $this->_trans->book_no)[1] : ""
        ];

        $this->_GRID['receiptDtls'] = $receiptDtls;
    }
}
