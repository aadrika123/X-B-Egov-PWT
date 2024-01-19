<?php

namespace App\BLL\Property\Akola;

use App\MicroServices\DocumentUpload;
use App\MicroServices\IdGeneration;
use App\Models\Payment\TempTransaction;
use App\Models\Property\Logs\PropSmsLog;
use App\Models\Property\PropAdjustment;
use App\Models\Property\PropAdvance;
use App\Models\Property\PropChequeDtl;
use App\Models\Property\PropDemand;
use App\Models\Property\PropOwner;
use App\Models\Property\PropPenaltyrebate;
use App\Models\Property\PropPendingArrear;
use App\Models\Property\PropProperty;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\UlbWardMaster;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * | Created On-07-10-2023 
 * | Created By-Anshu Kumar 
 * | Created for handling the Payment for Cheque,Cash,DD, NEFT of Property and Part Payment Integration
 * | Code - Closed
 */
class PostPropPaymentV2
{
    public $_REQ;
    public $_propCalculation;
    public $_tranNo;
    private $_offlinePaymentModes;
    private $_todayDate;
    private $_userId;
    private $_mPropDemand;
    private $_mPropTrans;
    private $_propTransaction;
    private $_propId;
    private $_verifyPaymentModes;
    private $_mPropTranDtl;
    private $_propDetails;
    private $_demands;
    private array $_penaltyRebates;
    private $_mPropPenaltyrebates;
    private $_fromFyear = null;
    private $_uptoFyear = null;
    protected $_gatewayType = null;
    public $_tranId;
    private $_COMMON_FUNCTION;
    private $_currentFullPaid = true;
    private $_arrearFullPaid = true;
    private $_paidCurrentTaxesBifurcation;
    private $_paidArrearTaxesBifurcation;
    private $_fy;
    private $_PropAdvance ;
    private $_PropAdjustment;
    private $_PropPendingArrear;
    /**
     * | Required @param Requests(propertyId as id)
     */
    public function __construct($req)
    {
        $this->_COMMON_FUNCTION = new \App\Repository\Common\CommonFunction();
        $this->_PropAdvance = new PropAdvance();
        $this->_PropAdjustment = new PropAdjustment();
        $this->_PropPendingArrear= new PropPendingArrear();
        $this->_REQ = $req;
        $this->readGenParams();
    }

    /**
     * | Read Gen Params
     */
    public function readGenParams()
    {
        $this->_fy = getFY();
        $this->_offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
        $this->_todayDate = Carbon::now();
        $this->_userId = auth()->user()->id ?? $this->_REQ['userId'];
        $this->_mPropDemand = new PropDemand();
        $idGeneration = new IdGeneration;
        $this->_mPropTrans = new PropTransaction();
        $this->_propId = $this->_REQ['id'];
        $this->_verifyPaymentModes = Config::get('payment-constants.VERIFICATION_PAYMENT_MODES');
        $this->_mPropTranDtl = new PropTranDtl();

        $this->_propDetails = PropProperty::find($this->_propId);
        if (collect($this->_propDetails)->isEmpty())
            throw new Exception("Property Details Not Available for this id");

        if ($this->_REQ['transactionNo'])
            $this->_tranNo = $this->_REQ['transactionNo'];          // Transaction No comes in case of online payment
        else
            $this->_tranNo = $idGeneration->generateTransactionNo($this->_propDetails->ulb_id);

        $this->_mPropPenaltyrebates = new PropPenaltyrebate();
    }

    /**
     * | Read Parameters for Payment
     */
    public function readPaymentParams()
    {
        $demands = $this->_propCalculation->original['data']['demandList'];
        // 🔺🔺 Arrears and Settlements is on under process
        $arrear = $this->_propCalculation->original['data']['arrear'];

        if (isset($arrear) && $arrear > 0)
            $arrear = $arrear;
        else
            $arrear = 0;

        $this->_penaltyRebates['monthlyPenalty'] = [                                    // Monthly Penalty
            'type' => 'Monthly Penalty',
            'isRebate' => false,
            'amount' => $this->_propCalculation->original['data']['totalInterestPenalty']
        ];

        $payableAmount = $this->_propCalculation->original['data']['payableAmt'];

        if ($payableAmount <= 0)
            throw new Exception("Payment Amount should be greater than 0");

        // Property Transactions
        $tranBy = auth()->user()->user_type ??  $this->_REQ['userType'];

        $this->_REQ->merge([
            'userId' => $this->_userId,
            'todayDate' => $this->_todayDate->format('Y-m-d'),
            'tranNo' => $this->_tranNo,
            'amount' => $payableAmount,                                                                         // Payable Amount with Arrear
            'demandAmt' => $this->_propCalculation->original['data']['grandTaxes']['balance'],                         // Demandable Amount
            'tranBy' => $tranBy,
            'arrearSettledAmt' => $arrear,
            'isArrearSettled' => false,
            'verifyStatus' => 1
        ]);

        if (in_array($this->_REQ['paymentMode'], $this->_verifyPaymentModes)) {
            $this->_REQ->merge([
                'verifyStatus' => 2
            ]);
        }

        if (collect($demands)->isEmpty() && $arrear <= 0)
            throw new Exception("No Dues For this Property");

        if (collect($demands)->isEmpty() && $arrear > 0) {                                          // This option is not in used right now from 14-10-2023
            $arrearDate = Carbon::now()->addYear(-1)->format('Y-m-d');
            $arrearFyear = getFY($arrearDate);
            $this->_fromFyear = $arrearFyear;
            $this->_uptoFyear = $arrearFyear;
            $this->_REQ['isArrearSettled'] = true;
        }

        $currentDemand = $this->_propCalculation->original['data']['currentDemandList'];
        $arrearDemand = $this->_propCalculation->original['data']['overdueDemandList'];

        $this->_paidCurrentTaxesBifurcation = $this->readPaidTaxes($currentDemand);
        $this->_paidArrearTaxesBifurcation = $this->readPaidTaxes($arrearDemand);

        $this->_demands = $demands;
    }


    /**
     * | Read the paid Taxes
     */
    public function readPaidTaxes($demand)
    {
        $demand = (object)collect($demand)->toArray();
        return [
            'paidGeneralTax' => $demand->general_tax ?? 0,
            'paidRoadTax' => $demand->road_tax ?? 0,
            'paidFirefightingTax' => $demand->firefighting_tax ?? 0,
            'paidEducationTax' => $demand->education_tax ?? 0,
            'paidWaterTax' => $demand->water_tax ?? 0,
            'paidCleanlinessTax' => $demand->cleanliness_tax ?? 0,
            'paidSewarageTax' => $demand->sewarage_tax ?? 0,
            'paidTreeTax' => $demand->tree_tax ?? 0,
            'paidProfessionalTax' => $demand->professional_tax ?? 0,
            'paidTax1' => $demand->tax1 ?? 0,
            'paidTax2' => $demand->tax2 ?? 0,
            'paidTax3' => $demand->tax3 ?? 0,
            'paidStateEducationTax' => $demand->state_education_tax ?? 0,
            'paidWaterBenefit' => $demand->water_benefit ?? 0,
            'paidWaterBill' => $demand->water_bill ?? 0,
            'paidSpWaterCess' => $demand->sp_water_cess ?? 0,
            'paidDrainCess' => $demand->drain_cess ?? 0,
            'paidLightCess' => $demand->light_cess ?? 0,
            'paidMajorBuilding' => $demand->major_building ?? 0,
            'paidOpenPloatTax' => $demand->open_ploat_tax ?? 0,
            'paidTotalTax' => $demand->total_tax ?? 0,
        ];
    }

    /**
     * | Beginning Transactions
     */
    public function postPaymentOld()
    {
        return $this->postPayment();

        $this->readPaymentParams();

        // 🔴🔴🔴🔴Begining Transactions 🔴🔴🔴
        DB::beginTransaction();
        $this->_propDetails->balance = 0;                  // Update Arrear
        $this->_propDetails->save();

        $this->_REQ['ulbId'] = $this->_propDetails->ulb_id;
        $paymentReceiptNo = $this->generatePaymentReceiptNoV2();
        $this->_REQ['bookNo'] = $paymentReceiptNo["bookNo"];
        $this->_REQ['receiptNo'] = $paymentReceiptNo["receiptNo"];
        $isPartWisePaid = null;
        // Part Payment
        if ($this->_REQ->paymentType == 'isPartPayment' && $this->_REQ->paidAmount < $this->_propCalculation->original['data']['payableAmt']) {                    // Adjust Demand on Part Payment
            $isPartWisePaid = true;                                                                                 // Flag has been kept for showing the partial payment receipt
            $this->_REQ->merge(['amount' => $this->_REQ->paidAmount]);
            if ($this->_REQ->paidAmount > $this->_propCalculation->original['data']['arrearPayableAmt'])           // We have to adjust current demand
                $this->currentDemandAdjust();
            elseif ($this->_REQ->paidAmount < $this->_propCalculation->original['data']['arrearPayableAmt'] && $this->_REQ->paidAmount > $this->_propCalculation->original['data']['totalInterestPenalty'])           // We have to adjust Arrear demand
                $this->arrearDemandAdjust();
            else
                throw new Exception("Part Payment in Monthly Interest Not Available");
        }

        if ($this->_REQ->paymentType == 'isPartPayment' && $this->_REQ->paidAmount > $this->_propCalculation->original['data']['payableAmt'])                     // Adjust Demand on Part Payment
            throw new Exception("Amount should be less then the payable amount");

        // return (["Full Payment"]);

        $propTrans = $this->_mPropTrans->postPropTransactions($this->_REQ, $this->_demands, $this->_fromFyear, $this->_uptoFyear);
        $this->_tranId = $propTrans['id'];
        $this->_propTransaction = $propTrans;

        // Updation of payment status in demand table
        foreach ($this->_demands as $demand) {
            $demand = collect($demand);
            $demand = (object)$demand->toArray();
            if (isset($demand->id)) {                     // if id exist on demand
                // 🔴🔴🔴🔴🔴🔴🔴 If isArrear is true then disable this condition (Pending)
                $tblDemand = $this->_mPropDemand->findOrFail($demand->id);
                $tblDemand->paid_status = 1;           // Paid Status Updation
                $tblDemand->balance = 0;

                $paidTaxes = ($tblDemand->fyear == $this->_fy) ? $this->_paidCurrentTaxesBifurcation : $this->_paidArrearTaxesBifurcation;
                $fullPaidStatus = ($tblDemand->fyear == $this->_fy) ? $this->_currentFullPaid : $this->_arrearFullPaid;
                $tblDemand->is_full_paid = $fullPaidStatus;
                // Update Paid Taxes

                /**
                 * | due taxes = paid_taxes-due Taxes
                 */
                $paidTaxes = (object)$paidTaxes;
                $tblDemand->due_general_tax = $tblDemand->due_general_tax - $paidTaxes->paidGeneralTax;
                $tblDemand->due_road_tax = $tblDemand->due_road_tax - $paidTaxes->paidRoadTax;
                $tblDemand->due_firefighting_tax = $tblDemand->due_firefighting_tax - $paidTaxes->paidFirefightingTax;
                $tblDemand->due_education_tax = $tblDemand->due_education_tax - $paidTaxes->paidEducationTax;
                $tblDemand->due_water_tax = $tblDemand->due_water_tax - $paidTaxes->paidWaterTax;
                $tblDemand->due_cleanliness_tax = $tblDemand->due_cleanliness_tax - $paidTaxes->paidCleanlinessTax;
                $tblDemand->due_sewarage_tax = $tblDemand->due_sewarage_tax - $paidTaxes->paidSewarageTax;
                $tblDemand->due_tree_tax = $tblDemand->due_tree_tax - $paidTaxes->paidTreeTax;
                $tblDemand->due_professional_tax = $tblDemand->due_professional_tax - $paidTaxes->paidProfessionalTax;
                $tblDemand->due_total_tax = $tblDemand->due_total_tax - $paidTaxes->paidTotalTax;
                $tblDemand->due_balance = $tblDemand->due_total_tax;
                $tblDemand->due_tax1 = $tblDemand->due_tax1 - $paidTaxes->paidTax1;
                $tblDemand->due_tax2 = $tblDemand->due_tax2 - $paidTaxes->paidTax2;
                $tblDemand->due_tax3 = $tblDemand->due_tax3 - $paidTaxes->paidTax3;
                $tblDemand->due_sp_education_tax = $tblDemand->due_sp_education_tax - $paidTaxes->paidStateEducationTax;
                $tblDemand->due_water_benefit = $tblDemand->due_water_benefit - $paidTaxes->paidWaterBenefit;
                $tblDemand->due_water_bill = $tblDemand->due_water_bill - $paidTaxes->paidWaterBill;
                $tblDemand->due_sp_water_cess = $tblDemand->due_sp_water_cess - $paidTaxes->paidSpWaterCess;
                $tblDemand->due_drain_cess = $tblDemand->due_drain_cess - $paidTaxes->paidDrainCess;
                $tblDemand->due_light_cess = $tblDemand->due_light_cess - $paidTaxes->paidLightCess;
                $tblDemand->due_major_building = $tblDemand->due_major_building - $paidTaxes->paidMajorBuilding;
                $tblDemand->due_open_ploat_tax = $tblDemand->due_open_ploat_tax - $paidTaxes->paidOpenPloatTax ?? 0;
                $tblDemand->paid_total_tax = $paidTaxes->paidTotalTax + $tblDemand->paid_total_tax;

                if (isset($isPartWisePaid))
                    $tblDemand->has_partwise_paid = $isPartWisePaid;

                $tblDemand->save();
            }

            // if (!isset($demand->id) && isset($demand->is_arrear) && $demand->is_arrear == true) {             // Only for arrear payment
            //     //🔴🔴 New Entry            
            //     $demandReq = [
            //         "property_id" => $this->_REQ['id'],
            //         "general_tax" => $demand->general_tax,
            //         "road_tax" => $demand->road_tax,
            //         "firefighting_tax" => $demand->firefighting_tax,
            //         "education_tax" => $demand->education_tax,
            //         "water_tax" => $demand->water_tax,
            //         "cleanliness_tax" => $demand->cleanliness_tax,
            //         "sewarage_tax" => $demand->sewarage_tax,
            //         "tree_tax" => $demand->tree_tax,
            //         "professional_tax" => $demand->professional_tax,
            //         "sp_education_tax" => $demand->state_education_tax,
            //         "total_tax" => $demand->total_tax,
            //         "balance" => $demand->balance,
            //         "paid_status" => 1,
            //         "fyear" => $demand->fyear,
            //         "adjust_amt" => $demand->adjustAmt ?? 0,
            //         "user_id" => $this->_userId,
            //         "ulb_id" => $this->_propDetails->ulb_id,
            //         "is_arrear" => true
            //     ];
            //     $demand = $this->_mPropDemand->create($demandReq);
            // }

            // ✅✅✅✅✅ Tran details insertion
            $tranDtlReq = [
                "tran_id" => $propTrans['id'],
                "prop_demand_id" => $demand->id,
                "total_demand" => $demand->balance,
                "ulb_id" => $this->_REQ['ulbId'],
                "paid_general_tax" => $paidTaxes->paidGeneralTax,
                "paid_road_tax" => $paidTaxes->paidRoadTax,
                "paid_firefighting_tax" => $paidTaxes->paidFirefightingTax,
                "paid_education_tax" => $paidTaxes->paidEducationTax,
                "paid_water_tax" => $paidTaxes->paidWaterTax,
                "paid_cleanliness_tax" => $paidTaxes->paidCleanlinessTax,
                "paid_sewarage_tax" => $paidTaxes->paidSewarageTax,
                "paid_tree_tax" => $paidTaxes->paidTreeTax,
                "paid_professional_tax" => $paidTaxes->paidProfessionalTax,
                "paid_total_tax" => $paidTaxes->paidTotalTax,
                "paid_balance" => $paidTaxes->paidTotalTax,
                "paid_tax1" => $paidTaxes->paidTax1,
                "paid_tax2" => $paidTaxes->paidTax2,
                "paid_tax3" => $paidTaxes->paidTax3,
                "paid_sp_education_tax" => $paidTaxes->paidStateEducationTax,
                "paid_water_benefit" => $paidTaxes->paidWaterBenefit,
                "paid_water_bill" => $paidTaxes->paidWaterBill,
                "paid_sp_water_cess" => $paidTaxes->paidSpWaterCess,
                "paid_drain_cess" => $paidTaxes->paidDrainCess,
                "paid_light_cess" => $paidTaxes->paidLightCess,
                "paid_major_building" => $paidTaxes->paidMajorBuilding,
                "paid_open_ploat_tax" => $paidTaxes->paidOpenPloatTax ?? 0,
            ];
            $this->_mPropTranDtl->create($tranDtlReq);
        }

        // Rebate Penalty Transactions 🔴🔴 Rebate implementation is pending
        foreach ($this->_penaltyRebates as $penalRebates) {
            $reqPenalRebate = [
                'tran_id' => $propTrans['id'],
                'head_name' => $penalRebates['type'],
                'amount' => $penalRebates['amount'],
                'is_rebate' => $penalRebates['isRebate'],
                'tran_date' => Carbon::now(),
                'prop_id' => $this->_propId,
                'app_type' => 'Property'
            ];
            $this->_mPropPenaltyrebates->create($reqPenalRebate);
        }

        // Cheque Entry
        if (in_array($this->_REQ['paymentMode'], $this->_offlinePaymentModes)) {
            $this->_REQ->merge([
                'chequeDate' => $this->_REQ['chequeDate'],
                'tranId' => $propTrans['id']
            ]);
            $this->postOtherPaymentModes($this->_REQ);
        }
    }

    /**
     * | Post Other Payment Modes for Cheque,DD,Neft
     */
    public function postOtherPaymentModes()
    {
        $cash = Config::get('payment-constants.PAYMENT_MODE.3');
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $mTempTransaction = new TempTransaction();
        if ($this->_REQ['paymentMode'] != $cash) {

            $this->chequeDocUpload();                   // Cheque document upload

            $mPropChequeDtl = new PropChequeDtl();
            $chequeReqs = [
                'user_id' => $this->_REQ['userId'],
                'prop_id' => $this->_REQ['id'],
                'transaction_id' => $this->_REQ['tranId'],
                'cheque_date' => $this->_REQ['chequeDate'],
                'bank_name' => $this->_REQ['bankName'],
                'branch_name' => $this->_REQ['branchName'],
                'cheque_no' => $this->_REQ['chequeNo']
            ];
            $mPropChequeDtl->postChequeDtl($chequeReqs);
        }

        $tranReqs = [
            'transaction_id' => $this->_REQ['tranId'],
            'application_id' => $this->_REQ['id'],
            'module_id' => $moduleId,
            'workflow_id' => 0,
            'transaction_no' => $this->_REQ['tranNo'],
            'application_no' => $this->_REQ->applicationNo,
            'amount' => $this->_REQ['amount'],
            'payment_mode' => $this->_REQ['paymentMode'],
            'cheque_dd_no' => $this->_REQ['chequeNo'],
            'bank_name' => $this->_REQ['bankName'],
            'tran_date' => $this->_REQ['todayDate'],
            'user_id' => $this->_REQ['userId'],
            'ulb_id' => $this->_REQ['ulbId'],
            "ward_no" => $this->_REQ["wardNo"],
        ];
        $mTempTransaction->tempTransaction($tranReqs);
    }

    /**
     * | Document Upload in case of cheque
     */
    public function chequeDocUpload()
    {
        if ($this->_REQ['paymentMode'] == 'CHEQUE') {
            $documentUpload = new DocumentUpload;
            if (isset($this->_REQ['document'])) {
                $uploadResponse = $documentUpload->uploadV2($this->_REQ);                                    // Upload Document
                if (json_decode($uploadResponse)->status == false)
                    throw new Exception(json_decode($uploadResponse)->message);
                $this->_mPropTrans->updateChequeDocInfo($this->_REQ['tranId'], $uploadResponse['data']);     // Cheque dd document upload refernece no update
            }
        }
    }

    /**
     * | Work On Process 🔴🔴🔴
     */

    public function generatePaymentReceiptNo(): array
    {
        $wardDetails = UlbWardMaster::find($this->_propDetails->ward_mstr_id);
        if (collect($wardDetails)->isEmpty())
            throw new Exception("Ward Details Not Available");

        $fyear = $this->_uptoFyear;
        $wardNo = $wardDetails->ward_name;
        return [
            'bookNo' => '23TA1',
            'receiptNo' => '01'
        ];
    }

    /**
     * | Author - Sandeep Bara
     */
    public function generatePaymentReceiptNoV2(): array
    {
        $wardDetails = UlbWardMaster::find($this->_propDetails->ward_mstr_id);
        if (collect($wardDetails)->isEmpty())
            throw new Exception("Ward Details Not Available");

        $fyear = $this->_uptoFyear;
        if (!$fyear) {
            foreach ($this->_demands as $val) {
                if ($fyear < $val['fyear'])
                    $fyear = $val['fyear'];
            }
        }
        $wardNo = $wardDetails->ward_name;
        $this->_REQ["wardNo"] = $wardNo;
        $counter = (new UlbWardMaster)->getTranCounter($wardDetails->id)->counter ?? null;
        $user = Auth()->user();
        $mUserType = $user->user_type ?? $this->_REQ['userType'];
        $type = "O";
        if ($mUserType == "TC") {
            $type = "T";
        } elseif ($this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
            $type = "C";
        }
        if (!$counter) {
            throw new Exception("Unable To Find Counter");
        }
        return [
            'bookNo' => substr($fyear, 7, 2) . $type . $wardNo . "-" . $counter,
            'receiptNo' => $counter,
        ];
    }

    /**
     * | Adjust On Penalty Amount
     */
    public function penaltyAdjust()
    {
    }


    /**
     * | Adjust On Arrear Amount
     */
    public function arrearAdjust()
    {
    }

    /**
     * | Adjust on Current Demand
     */
    public function currentDemandAdjust()
    {
        $currentPayableAmount = $this->_REQ->paidAmount - $this->_propCalculation->original['data']['arrearPayableAmt'];

        if ($currentPayableAmount > 0)
            $this->_currentFullPaid = false;

        $currentTax = collect($this->_propCalculation->original['data']["demandList"])->where("fyear", getFY());
        $totaTax = $currentTax->sum("total_tax");

        $perPecOfTax =  $totaTax / 100;

        $generalTaxPerc = ($currentTax->sum('general_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $roadTaxPerc = ($currentTax->sum('road_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $firefightingTaxPerc = ($currentTax->sum('firefighting_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $educationTaxPerc = ($currentTax->sum('education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterTaxPerc = ($currentTax->sum('water_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $cleanlinessTaxPerc = ($currentTax->sum('cleanliness_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $sewarageTaxPerc = ($currentTax->sum('sewarage_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $treeTaxPerc = ($currentTax->sum('tree_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $professionalTaxPerc = ($currentTax->sum('professional_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax1Perc = ($currentTax->sum('tax1') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax2Perc = ($currentTax->sum('tax2') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax3Perc = ($currentTax->sum('tax3') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $stateEducationTaxPerc = ($currentTax->sum('state_education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBenefitPerc = ($currentTax->sum('water_benefit') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBillPerc = ($currentTax->sum('water_bill') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $spWaterCessPerc = ($currentTax->sum('sp_water_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $drainCessPerc = ($currentTax->sum('drain_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $lightCessPerc = ($currentTax->sum('light_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $majorBuildingPerc = ($currentTax->sum('major_building') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $openPloatTaxPerc = ($currentTax->sum('open_ploat_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;

        $totalPerc = $generalTaxPerc + $roadTaxPerc + $firefightingTaxPerc + $educationTaxPerc +
            $waterTaxPerc + $cleanlinessTaxPerc + $sewarageTaxPerc + $treeTaxPerc
            + $professionalTaxPerc + $tax1Perc + $tax2Perc + $tax3Perc
            + $stateEducationTaxPerc + $waterBenefitPerc + $waterBillPerc +
            $spWaterCessPerc + $drainCessPerc + $lightCessPerc + $majorBuildingPerc
            + $openPloatTaxPerc;

        

        /**
         * | 100 % = Payable Amount
         * | 1 % = Payable Amount/100  (Hence We Have devided the taxes by hundred)
         */

        $paidDemandBifurcation = [
            'general_tax' => roundFigure(($currentPayableAmount * $generalTaxPerc) / 100),
            'road_tax' => roundFigure(($currentPayableAmount * $roadTaxPerc) / 100),
            'firefighting_tax' => roundFigure(($currentPayableAmount * $firefightingTaxPerc) / 100),
            'education_tax' => roundFigure(($currentPayableAmount * $educationTaxPerc) / 100),
            'water_tax' => roundFigure(($currentPayableAmount * $waterTaxPerc) / 100),
            'cleanliness_tax' => roundFigure(($currentPayableAmount * $cleanlinessTaxPerc) / 100),
            'sewarage_tax' => roundFigure(($currentPayableAmount * $sewarageTaxPerc) / 100),
            'tree_tax' => roundFigure(($currentPayableAmount * $treeTaxPerc) / 100),
            'professional_tax' => roundFigure(($currentPayableAmount * $professionalTaxPerc) / 100),
            'tax1' => roundFigure(($currentPayableAmount * $tax1Perc) / 100),
            'tax2' => roundFigure(($currentPayableAmount * $tax2Perc) / 100),
            'tax3' => roundFigure(($currentPayableAmount * $tax3Perc) / 100),
            'state_education_tax' => roundFigure(($currentPayableAmount * $stateEducationTaxPerc) / 100),
            'water_benefit' => roundFigure(($currentPayableAmount * $waterBenefitPerc) / 100),
            'water_bill' => roundFigure(($currentPayableAmount * $waterBillPerc) / 100),
            'sp_water_cess' => roundFigure(($currentPayableAmount * $spWaterCessPerc) / 100),
            'drain_cess' => roundFigure(($currentPayableAmount * $drainCessPerc) / 100),
            'light_cess' => roundFigure(($currentPayableAmount * $lightCessPerc) / 100),
            'major_building' => roundFigure(($currentPayableAmount * $majorBuildingPerc) / 100),
            'open_ploat_tax' => roundFigure(($currentPayableAmount * $openPloatTaxPerc) / 100),
            'total_tax' => roundFigure(($currentPayableAmount * $totalPerc) / 100)
        ];

        return $this->_paidCurrentTaxesBifurcation = $this->readPaidTaxes($paidDemandBifurcation);
    }


    /**
     * | Part Payment On Arrear Demand
     */
    public function arrearDemandAdjust()
    {
        $arrearPayableAmount = $this->_REQ->paidAmount - $this->_propCalculation->original['data']['totalInterestPenalty'];

        if ($arrearPayableAmount > 0)
            $this->_arrearFullPaid = false;

        $this->_propCalculation->original['data']["demandList"] = collect($this->_propCalculation->original['data']["demandList"])->where("fyear", "<>", $this->_fy);    // In Case of part payment is less then the arrear payment and the user select is arrear false then this will handle this case
        $this->_demands = $this->_propCalculation->original['data']["demandList"];
        $arrearTax = $this->_propCalculation->original['data']["demandList"];
        $totaTax = collect($arrearTax)->sum("total_tax");

        $this->_REQ->merge([
            'demandAmt' => $totaTax,                                            // Demandable Amount
            'arrearSettledAmt' => $totaTax,
        ]);

        $perPecOfTax =  $totaTax / 100;

        $generalTaxPerc = ($arrearTax->sum('general_tax') / $totaTax) * 100;
        $roadTaxPerc = ($arrearTax->sum('road_tax') / $totaTax) * 100;
        $firefightingTaxPerc = ($arrearTax->sum('firefighting_tax') / $totaTax) * 100;
        $educationTaxPerc = ($arrearTax->sum('education_tax') / $totaTax) * 100;
        $waterTaxPerc = ($arrearTax->sum('water_tax') / $totaTax) * 100;
        $cleanlinessTaxPerc = ($arrearTax->sum('cleanliness_tax') / $totaTax) * 100;
        $sewarageTaxPerc = ($arrearTax->sum('sewarage_tax') / $totaTax) * 100;
        $treeTaxPerc = ($arrearTax->sum('tree_tax') / $totaTax) * 100;
        $professionalTaxPerc = ($arrearTax->sum('professional_tax') / $totaTax) * 100;
        $tax1Perc = ($arrearTax->sum('tax1') / $totaTax) * 100;
        $tax2Perc = ($arrearTax->sum('tax2') / $totaTax) * 100;
        $tax3Perc = ($arrearTax->sum('tax3') / $totaTax) * 100;
        $stateEducationTaxPerc = ($arrearTax->sum('state_education_tax') / $totaTax) * 100;
        $waterBenefitPerc = ($arrearTax->sum('water_benefit') / $totaTax) * 100;
        $waterBillPerc = ($arrearTax->sum('water_bill') / $totaTax) * 100;
        $spWaterCessPerc = ($arrearTax->sum('sp_water_cess') / $totaTax) * 100;
        $drainCessPerc = ($arrearTax->sum('drain_cess') / $totaTax) * 100;
        $lightCessPerc = ($arrearTax->sum('light_cess') / $totaTax) * 100;
        $majorBuildingPerc = ($arrearTax->sum('major_building') / $totaTax) * 100;
        $openPloatTaxPerc = ($arrearTax->sum('open_ploat_tax') / $totaTax) * 100;

        $totalPerc = $generalTaxPerc + $roadTaxPerc + $firefightingTaxPerc + $educationTaxPerc +
            $waterTaxPerc + $cleanlinessTaxPerc + $sewarageTaxPerc + $treeTaxPerc
            + $professionalTaxPerc + $tax1Perc + $tax2Perc + $tax3Perc
            + $stateEducationTaxPerc + $waterBenefitPerc + $waterBillPerc +
            $spWaterCessPerc + $drainCessPerc + $lightCessPerc + $majorBuildingPerc
            + $openPloatTaxPerc;

        

        /**
         * | 100 % = Payable Amount
         * | 1 % = Payable Amount/100  (Hence We Have devided the taxes by hundred)
         */

        $paidDemandBifurcation = [
            'general_tax' => roundFigure(($arrearPayableAmount * $generalTaxPerc) / 100),
            'road_tax' => roundFigure(($arrearPayableAmount * $roadTaxPerc) / 100),
            'firefighting_tax' => roundFigure(($arrearPayableAmount * $firefightingTaxPerc) / 100),
            'education_tax' => roundFigure(($arrearPayableAmount * $educationTaxPerc) / 100),
            'water_tax' => roundFigure(($arrearPayableAmount * $waterTaxPerc) / 100),
            'cleanliness_tax' => roundFigure(($arrearPayableAmount * $cleanlinessTaxPerc) / 100),
            'sewarage_tax' => roundFigure(($arrearPayableAmount * $sewarageTaxPerc) / 100),
            'tree_tax' => roundFigure(($arrearPayableAmount * $treeTaxPerc) / 100),
            'professional_tax' => roundFigure(($arrearPayableAmount * $professionalTaxPerc) / 100),
            'tax1' => roundFigure(($arrearPayableAmount * $tax1Perc) / 100),
            'tax2' => roundFigure(($arrearPayableAmount * $tax2Perc) / 100),
            'tax3' => roundFigure(($arrearPayableAmount * $tax3Perc) / 100),
            'state_education_tax' => roundFigure(($arrearPayableAmount * $stateEducationTaxPerc) / 100),
            'water_benefit' => roundFigure(($arrearPayableAmount * $waterBenefitPerc) / 100),
            'water_bill' => roundFigure(($arrearPayableAmount * $waterBillPerc) / 100),
            'sp_water_cess' => roundFigure(($arrearPayableAmount * $spWaterCessPerc) / 100),
            'drain_cess' => roundFigure(($arrearPayableAmount * $drainCessPerc) / 100),
            'light_cess' => roundFigure(($arrearPayableAmount * $lightCessPerc) / 100),
            'major_building' => roundFigure(($arrearPayableAmount * $majorBuildingPerc) / 100),
            'open_ploat_tax' => roundFigure(($arrearPayableAmount * $openPloatTaxPerc) / 100),
            'total_tax' => roundFigure(($arrearPayableAmount * $totalPerc) / 100)
        ];

        return $this->_paidArrearTaxesBifurcation = $this->readPaidTaxes($paidDemandBifurcation);
    }
    public function postPaymentV2()
    {
        return $this->postPayment();
    }
    public function postPayment()
    {
        $this->readPaymentParams();

        // 🔴🔴🔴🔴Begining Transactions 🔴🔴🔴
        DB::beginTransaction();
        $this->_propDetails->balance = 0;                  // Update Arrear
        $this->_propDetails->save();

        $this->_REQ['ulbId'] = $this->_propDetails->ulb_id;
        $paymentReceiptNo = $this->generatePaymentReceiptNoV2();
        $this->_REQ['bookNo'] = $paymentReceiptNo["bookNo"];
        $this->_REQ['receiptNo'] = $paymentReceiptNo["receiptNo"];
        $isPartWisePaid = null;
        // Part Payment
        // Adjust Demand on Part Payment
        if ($this->_REQ->paymentType == 'isPartPayment' && $this->_REQ->paidAmount < $this->_propCalculation->original['data']['payableAmt']) 
        {                    
            $isPartWisePaid = true;                                                                                 // Flag has been kept for showing the partial payment receipt
            $this->_REQ->merge(['amount' => $this->_REQ->paidAmount]);
            // if ($this->_REQ->paidAmount > $this->_propCalculation->original['data']['arrearPayableAmt'])           // We have to adjust current demand
            //     $this->currentDemandAdjust();
            // elseif ($this->_REQ->paidAmount < $this->_propCalculation->original['data']['arrearPayableAmt'] && $this->_REQ->paidAmount > $this->_propCalculation->original['data']['totalInterestPenalty'])           // We have to adjust Arrear demand
            //     $this->arrearDemandAdjust();
            // else
            //     throw new Exception("Part Payment in Monthly Interest Not Available");            
            if($this->_REQ->paidAmount < $this->_propCalculation->original['data']['totalInterestPenalty'])
            {
                // throw new Exception("Part Payment in Monthly Interest Not Available");
            }
        }

        // Adjust Demand on Part Payment
        if ($this->_REQ->paymentType == 'isPartPayment' && $this->_REQ->paidAmount > $this->_propCalculation->original['data']['payableAmt']) {
            // throw new Exception("Amount should be less then the payable amount");

        }  
        
        if(!$this->_REQ->paidAmount){
            $this->_REQ->merge(['paidAmount' => $this->_REQ['amount']]);
        }
        

        $addvanceAmt = $this->_propCalculation->original['data']["remainAdvance"]??0; 
        
        $previousInterest = $this->_propCalculation->original['data']["previousInterest"]??0;
        $previousInterestId = $this->_propCalculation->original['data']["previousInterestId"]??0;

        $allDemands = $this->_propCalculation->original['data']["demandList"];
        $arrearDemand = collect($allDemands)->where("fyear","<",getFY());
        $arrearTotaTax = $arrearDemand->sum("total_tax");
        $penalty = $arrearDemand->sum("monthlyPenalty");
        $totalaAreaDemand = $previousInterest + $arrearTotaTax + $penalty;
        $thertyPerOfpreviousInterest = ($this->_REQ->paidAmount /100 )* 30;
        if(round($this->_REQ->paidAmount) < round($totalaAreaDemand) )
        {
            $previousInterest = $previousInterest > 0 ? ($thertyPerOfpreviousInterest<=$previousInterest ? $thertyPerOfpreviousInterest : $previousInterest ) : 0 ;
        } 
        $adjustAmt = 0;
        $payableAmount = $this->_REQ->paidAmount - $previousInterest ;
        if($this->_REQ["paymentMode"]!="ONLINE"){
            $adjustAmt = round($this->_REQ->paidAmount - $addvanceAmt) ;
            $adjustAmt = $adjustAmt > 0 ? $addvanceAmt : $this->_REQ->paidAmount;   
            switch($this->_REQ->paymentType){
                case "isPartPayment" : $payableAmount = $payableAmount + $addvanceAmt;
                                    $this->_REQ->merge(
                                        [
                                            'amount' => $this->_REQ->paidAmount 
                                        ]
                                    );
                                    break;
                case "isFullPayment" : $payableAmount ;
                                    $this->_REQ->merge(
                                        [
                                            'paidAmount' => ($this->_REQ->paidAmount - $addvanceAmt),
                                            'amount' => ($this->_REQ->paidAmount - $addvanceAmt)
                                        ]
                                    );
                                    break;
                case "isArrearPayment" : $payableAmount ;
                                    $this->_REQ->merge(
                                        [
                                            'paidAmount' => ($this->_REQ->paidAmount - $addvanceAmt),
                                            'amount' => ($this->_REQ->paidAmount - $addvanceAmt)
                                        ]
                                    );
                                    break;
            }
        }
        
        $demands = collect($this->_propCalculation->original['data']["demandList"])->sortBy(["fyear", "id"]);
        $paidPenalty = $previousInterest;#$this->_propCalculation->original['data']["previousInterest"];
        $demandAmt = $this->_propCalculation->original['data']["payableAmt"];
        $paidDemands = [];
        foreach ($demands as $key => $val) {
            if ($payableAmount <= 0) {
                continue;
            }
            $paymentDtl = ($this->demandAdjust($payableAmount, $val["id"]));
            $payableAmount = $paymentDtl["balence"];
            $paidPenalty += $paymentDtl["payableAmountOfPenalty"];
            $paidDemands[] = $paymentDtl;
        }
        
        $arrearSetalAmount = collect($paidDemands)->where("fyear","<",getFY())->sum("paid_total_tax");
        $this->_REQ->merge([
            'demandAmt' => $arrearSetalAmount,                                            // Demandable Amount
            'arrearSettledAmt' => $arrearSetalAmount > 0 ? true : false,
        ]);
        
        $this->_fromFyear = ((collect($paidDemands)->sortBy("fyear"))->first())["fyear"] ?? $this->_fromFyear;
        $this->_uptoFyear = ((collect($paidDemands)->sortBy("fyear"))->last())["fyear"] ?? $this->_uptoFyear;

        $propTrans = $this->_mPropTrans->postPropTransactions($this->_REQ, $paidDemands, $this->_fromFyear, $this->_uptoFyear);
        $this->_tranId = $propTrans['id'];
        $this->_propTransaction = $propTrans;
        $this->_penaltyRebates["monthlyPenalty"]["amount"] = roundFigure($paidPenalty);
        $d1 = [];
        $trDtl = [];

        #update Pending Arrear Penalty
        if($previousInterest>0 && $OldInterest = $this->_PropPendingArrear->find($previousInterestId))
        {
            $OldInterest->due_total_interest = $OldInterest->due_total_interest - $previousInterest > 0 ?  $OldInterest->due_total_interest - $previousInterest : 0 ;
            $OldInterest->paid_status       = $OldInterest->due_total_interest ==0 ? 1 : $OldInterest->paid_status;
            $OldInterest->save();                      
        }        
        // Updation of payment status in demand table
        foreach ($paidDemands as $dtls) {
            $demand = collect($dtls["currentTax"]);
            $demand = (object)$demand->toArray();
            $tblDemand = $this->_mPropDemand->findOrFail($demand->id);
            $d1[] = $demand;
            $tblDemand->is_full_paid = $dtls["remaining"] > 0 ? false : true;
            $paidTaxes = (object)($dtls["paidCurrentTaxesBifurcation"]);

            // Update Paid Taxes

            /**
             * | due taxes = paid_taxes-due Taxes
             */
            $tblDemand->paid_status         = 1;           // Paid Status Updation
            $tblDemand->balance             = $tblDemand->balance - $paidTaxes->paidTotalTax > 0 ? $tblDemand->balance - $paidTaxes->paidTotalTax : 0;
            $tblDemand->due_general_tax     = $tblDemand->due_general_tax - $paidTaxes->paidGeneralTax;
            $tblDemand->due_road_tax        = $tblDemand->due_road_tax - $paidTaxes->paidRoadTax;
            $tblDemand->due_firefighting_tax = $tblDemand->due_firefighting_tax - $paidTaxes->paidFirefightingTax;
            $tblDemand->due_education_tax   = $tblDemand->due_education_tax - $paidTaxes->paidEducationTax;
            $tblDemand->due_water_tax       = $tblDemand->due_water_tax - $paidTaxes->paidWaterTax;
            $tblDemand->due_cleanliness_tax = $tblDemand->due_cleanliness_tax - $paidTaxes->paidCleanlinessTax;
            $tblDemand->due_sewarage_tax    = $tblDemand->due_sewarage_tax - $paidTaxes->paidSewarageTax;
            $tblDemand->due_tree_tax        = $tblDemand->due_tree_tax - $paidTaxes->paidTreeTax;
            $tblDemand->due_professional_tax = $tblDemand->due_professional_tax - $paidTaxes->paidProfessionalTax;
            $tblDemand->due_total_tax       = $tblDemand->due_total_tax - $paidTaxes->paidTotalTax;
            $tblDemand->due_balance         = $tblDemand->due_total_tax;
            $tblDemand->due_tax1            = $tblDemand->due_tax1 - $paidTaxes->paidTax1;
            $tblDemand->due_tax2            = $tblDemand->due_tax2 - $paidTaxes->paidTax2;
            $tblDemand->due_tax3            = $tblDemand->due_tax3 - $paidTaxes->paidTax3;
            $tblDemand->due_sp_education_tax = $tblDemand->due_sp_education_tax - $paidTaxes->paidStateEducationTax;
            $tblDemand->due_water_benefit   = $tblDemand->due_water_benefit - $paidTaxes->paidWaterBenefit;
            $tblDemand->due_water_bill      = $tblDemand->due_water_bill - $paidTaxes->paidWaterBill;
            $tblDemand->due_sp_water_cess   = $tblDemand->due_sp_water_cess - $paidTaxes->paidSpWaterCess;
            $tblDemand->due_drain_cess      = $tblDemand->due_drain_cess - $paidTaxes->paidDrainCess;
            $tblDemand->due_light_cess      = $tblDemand->due_light_cess - $paidTaxes->paidLightCess;
            $tblDemand->due_major_building  = $tblDemand->due_major_building - $paidTaxes->paidMajorBuilding;
            $tblDemand->due_open_ploat_tax  = $tblDemand->due_open_ploat_tax - $paidTaxes->paidOpenPloatTax ?? 0;
            $tblDemand->paid_total_tax      = $paidTaxes->paidTotalTax + $tblDemand->paid_total_tax;
            #it is testing purps only
            if(strtoupper($this->_REQ['paymentMode'])!="ONLINE"){
                foreach($tblDemand->toArray() as $keys=>$testVal)
                {
                    if(is_numeric($testVal) && $testVal<0)
                    {
                        throw new Exception($keys." Go On Nagative of fyear ".$tblDemand->fyear." amount =>".$testVal);
                    }
                }
            }
            # end hear
            if (isset($isPartWisePaid))
                $tblDemand->has_partwise_paid = $isPartWisePaid;

            $tblDemand->save();

            // ✅✅✅✅✅ Tran details insertion
            $tranDtlReq = [
                "tran_id" => $propTrans['id'],
                "prop_demand_id" => $demand->id,
                "total_demand" => $demand->balance,
                "ulb_id" => $this->_REQ['ulbId'],
                "paid_general_tax" => $paidTaxes->paidGeneralTax,
                "paid_road_tax" => $paidTaxes->paidRoadTax,
                "paid_firefighting_tax" => $paidTaxes->paidFirefightingTax,
                "paid_education_tax" => $paidTaxes->paidEducationTax,
                "paid_water_tax" => $paidTaxes->paidWaterTax,
                "paid_cleanliness_tax" => $paidTaxes->paidCleanlinessTax,
                "paid_sewarage_tax" => $paidTaxes->paidSewarageTax,
                "paid_tree_tax" => $paidTaxes->paidTreeTax,
                "paid_professional_tax" => $paidTaxes->paidProfessionalTax,
                "paid_total_tax" => $paidTaxes->paidTotalTax,
                "paid_balance" => $paidTaxes->paidTotalTax,
                "paid_tax1" => $paidTaxes->paidTax1,
                "paid_tax2" => $paidTaxes->paidTax2,
                "paid_tax3" => $paidTaxes->paidTax3,
                "paid_sp_education_tax" => $paidTaxes->paidStateEducationTax,
                "paid_water_benefit" => $paidTaxes->paidWaterBenefit,
                "paid_water_bill" => $paidTaxes->paidWaterBill,
                "paid_sp_water_cess" => $paidTaxes->paidSpWaterCess,
                "paid_drain_cess" => $paidTaxes->paidDrainCess,
                "paid_light_cess" => $paidTaxes->paidLightCess,
                "paid_major_building" => $paidTaxes->paidMajorBuilding,
                "paid_open_ploat_tax" => $paidTaxes->paidOpenPloatTax ?? 0,
            ];
            $trDtl[] = $tranDtlReq;
            $this->_mPropTranDtl->create($tranDtlReq);
        }

        // Rebate Penalty Transactions 🔴🔴 Rebate implementation is pending
        foreach ($this->_penaltyRebates as $penalRebates) {
            $reqPenalRebate = [
                'tran_id' => $propTrans['id'],
                'head_name' => $penalRebates['type'],
                'amount' => $penalRebates['amount'],
                'is_rebate' => $penalRebates['isRebate'],
                'tran_date' => Carbon::now(),
                'prop_id' => $this->_propId,
                'app_type' => 'Property'
            ];
            $finP[] = $reqPenalRebate;
            $this->_mPropPenaltyrebates->create($reqPenalRebate);
        }

        // Cheque Entry
        if (in_array($this->_REQ['paymentMode'], $this->_offlinePaymentModes)) {
            $this->_REQ->merge([
                'chequeDate' => $this->_REQ['chequeDate'],
                'tranId' => $propTrans['id']
            ]);
            $this->postOtherPaymentModes($this->_REQ);
        }

        #insert Advance Amount
        if(round($payableAmount)>0)
        {
            $advArr = [
                "prop_id"=>$this->_propId,
                "tran_id"=>$propTrans['id'],
                "amount" =>round($payableAmount),
                "user_id"=>$this->_REQ['userId'] ?? (auth()->user() ? auth()->user()->id : null),
                "ulb_id"=>$this->_REQ['ulbId'] ?? (auth()->user() ? auth()->user()->ulbd_id : null),
                "remarks"=>"Advance Payment",
            ];
            $this->_PropAdvance->store($advArr);
        }

        #insert Adjusted Amount
        if(round($adjustAmt)>0)
        {
            $adjArr = [
                "prop_id"=>$this->_propId,
                "tran_id"=>$propTrans['id'],
                "amount" =>round($adjustAmt),
                "user_id"=>$this->_REQ['userId'] ?? (auth()->user() ? auth()->user()->id : null),
                "ulb_id"=>$this->_REQ['ulbId'] ?? (auth()->user() ? auth()->user()->ulbd_id : null),                
            ];
            $this->_PropAdjustment->store($adjArr);
        }
        
        $generatePaymentReceipt = new GeneratePaymentReceiptV2;                     // Version 2 Receipt
        $generatePaymentReceipt->generateReceipt("", $propTrans['id']);
        $receipt = $generatePaymentReceipt->_GRID;
        // dd($receipt,$this->_REQ["paidAmount"],$this->_REQ["amount"]);

        // sendsms
        // $propertyNo     = $this->_propDetails->property_no;
        // $payableAmount  = $this->_REQ->paidAmount;

        // $mPropOwner = new PropOwner();
        // $mPropSmsLog = new PropSmsLog();
        // $propOwners = $mPropOwner->getOwnerByPropId($this->_propDetails->id);
        // $firstOwner = collect($propOwners)->first();
        // if ($firstOwner) {
        //     $ownerMobile = $firstOwner->mobileNo;
        //     $ownerName = Str::limit(trim($firstOwner->ownerName), 30);;
        //     if (strlen($ownerMobile) == 10) {
        //         $sms      = "Thank you " . $ownerName . " for making payment of Rs. " . $payableAmount . " against Property No. " . $propertyNo . ". For more details visit www.akolamc.org/call us at:18008907909 SWATI INDUSTRIES";
        //         $response = send_sms($ownerMobile, $sms, 1707169564199604962);

        //         $smsReqs = [
        //             "emp_id" => $user_id,
        //             "ref_id" => $this->_propDetails->id,
        //             "ref_type" => 'Property',
        //             "mobile_no" => $ownerMobile,
        //             "purpose" => 'Holding Payment',
        //             "template_id" => 1707169564199604962,
        //             "message" => $sms,
        //             "response" => $response['status'],
        //             "smgid" => $response['msg'],
        //             "stampdate" => Carbon::now(),
        //         ];
        //         $mPropSmsLog->create($smsReqs);
        //     }
        // }
    }
    /**
     * | demand Adjust
     */
    public function demandAdjust($currentPayableAmount, $demanId)
    {
        $currentTax = collect($this->_propCalculation->original['data']["demandList"])->where("id", $demanId);

        $totaTax = $currentTax->sum("total_tax");
        $penalty = $currentTax->sum("monthlyPenalty");
        $demandPayableAmount = $totaTax + $penalty;

        // if ($demandPayableAmount == 0)
        // throw new Exception("Demand Of Current Year is 0 Please Pay Arrear Only");

        $balence = $currentPayableAmount - $demandPayableAmount;
        $totalTaxOfDemand = ($totaTax / ($demandPayableAmount == 0 ? 1 : $demandPayableAmount)) * 100;
        $penaltyOfDemand = ($penalty / ($demandPayableAmount == 0 ? 1 : $demandPayableAmount)) * 100;
        $onePerOfCurrentPaybleAmount = $currentPayableAmount / 100;
        if ($currentPayableAmount > $demandPayableAmount) {
            $onePerOfCurrentPaybleAmount = $demandPayableAmount / 100;
        }

        $payableAmountOfTax = $onePerOfCurrentPaybleAmount * $totalTaxOfDemand;
        $payableAmountOfPenalty = $onePerOfCurrentPaybleAmount * $penaltyOfDemand;
        $data = [
            "currentTax" => $currentTax->first(),
            "demandId" => $demanId,
            "fyear" => ($currentTax->first())["fyear"],
            "totalTax" => $totaTax,
            "totalpenalty" => $penalty,
            "demandPayableAmount" => $demandPayableAmount,
            "currentPayableAmount" => $currentPayableAmount,
            "totalTaxOfDemand" => $totalTaxOfDemand,
            "penaltyOfDemand" => $penaltyOfDemand,
            "onePerOfCurrentPaybleAmount" => $onePerOfCurrentPaybleAmount,
            "payableAmountOfTax" => $payableAmountOfTax,
            "payableAmountOfPenalty" => $payableAmountOfPenalty,
            "balence" => round($balence) > 0 ? $balence : 0,
            "remaining" => $totaTax - $payableAmountOfTax > 0 ? $totaTax - $payableAmountOfTax : 0,
        ];
        $perPecOfTax =  $totaTax / 100;

        $generalTaxPerc = ($currentTax->sum('general_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $roadTaxPerc = ($currentTax->sum('road_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $firefightingTaxPerc = ($currentTax->sum('firefighting_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $educationTaxPerc = ($currentTax->sum('education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterTaxPerc = ($currentTax->sum('water_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $cleanlinessTaxPerc = ($currentTax->sum('cleanliness_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $sewarageTaxPerc = ($currentTax->sum('sewarage_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $treeTaxPerc = ($currentTax->sum('tree_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $professionalTaxPerc = ($currentTax->sum('professional_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax1Perc = ($currentTax->sum('tax1') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax2Perc = ($currentTax->sum('tax2') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $tax3Perc = ($currentTax->sum('tax3') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $stateEducationTaxPerc = ($currentTax->sum('state_education_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBenefitPerc = ($currentTax->sum('water_benefit') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $waterBillPerc = ($currentTax->sum('water_bill') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $spWaterCessPerc = ($currentTax->sum('sp_water_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $drainCessPerc = ($currentTax->sum('drain_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $lightCessPerc = ($currentTax->sum('light_cess') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $majorBuildingPerc = ($currentTax->sum('major_building') / ($totaTax == 0 ? 1 : $totaTax)) * 100;
        $openPloatTaxPerc = ($currentTax->sum('open_ploat_tax') / ($totaTax == 0 ? 1 : $totaTax)) * 100;

        $totalPerc = $generalTaxPerc + $roadTaxPerc + $firefightingTaxPerc + $educationTaxPerc +
            $waterTaxPerc + $cleanlinessTaxPerc + $sewarageTaxPerc + $treeTaxPerc
            + $professionalTaxPerc + $tax1Perc + $tax2Perc + $tax3Perc
            + $stateEducationTaxPerc + $waterBenefitPerc + $waterBillPerc +
            $spWaterCessPerc + $drainCessPerc + $lightCessPerc + $majorBuildingPerc
            + $openPloatTaxPerc;



        /**
         * | 100 % = Payable Amount
         * | 1 % = Payable Amount/100  (Hence We Have devided the taxes by hundred)
         */

        $paidDemandBifurcation = [
            'general_tax' => roundFigure(($payableAmountOfTax * $generalTaxPerc) / 100),
            'road_tax' => roundFigure(($payableAmountOfTax * $roadTaxPerc) / 100),
            'firefighting_tax' => roundFigure(($payableAmountOfTax * $firefightingTaxPerc) / 100),
            'education_tax' => roundFigure(($payableAmountOfTax * $educationTaxPerc) / 100),
            'water_tax' => roundFigure(($payableAmountOfTax * $waterTaxPerc) / 100),
            'cleanliness_tax' => roundFigure(($payableAmountOfTax * $cleanlinessTaxPerc) / 100),
            'sewarage_tax' => roundFigure(($payableAmountOfTax * $sewarageTaxPerc) / 100),
            'tree_tax' => roundFigure(($payableAmountOfTax * $treeTaxPerc) / 100),
            'professional_tax' => roundFigure(($payableAmountOfTax * $professionalTaxPerc) / 100),
            'tax1' => roundFigure(($payableAmountOfTax * $tax1Perc) / 100),
            'tax2' => roundFigure(($payableAmountOfTax * $tax2Perc) / 100),
            'tax3' => roundFigure(($payableAmountOfTax * $tax3Perc) / 100),
            'state_education_tax' => roundFigure(($payableAmountOfTax * $stateEducationTaxPerc) / 100),
            'water_benefit' => roundFigure(($payableAmountOfTax * $waterBenefitPerc) / 100),
            'water_bill' => roundFigure(($payableAmountOfTax * $waterBillPerc) / 100),
            'sp_water_cess' => roundFigure(($payableAmountOfTax * $spWaterCessPerc) / 100),
            'drain_cess' => roundFigure(($payableAmountOfTax * $drainCessPerc) / 100),
            'light_cess' => roundFigure(($payableAmountOfTax * $lightCessPerc) / 100),
            'major_building' => roundFigure(($payableAmountOfTax * $majorBuildingPerc) / 100),
            'open_ploat_tax' => roundFigure(($payableAmountOfTax * $openPloatTaxPerc) / 100),
            'total_tax' => roundFigure(($payableAmountOfTax * $totalPerc) / 100),
        ];
        $data["paid_total_tax"] =  $paidDemandBifurcation["total_tax"]??0;
        $data["paidCurrentTaxesBifurcation"] = $this->readPaidTaxes($paidDemandBifurcation);
        return $data;
    }


    public function chakPayentAmount()
    {
        if ($this->_REQ->paymentType == 'isPartPayment' && $this->_REQ->paidAmount < $this->_propCalculation->original['data']['payableAmt']) {                    // Adjust Demand on Part Payment
            $isPartWisePaid = true;                                                                                 // Flag has been kept for showing the partial payment receipt
            $this->_REQ->merge(['amount' => $this->_REQ->paidAmount]);
            if ($this->_REQ->paidAmount > $this->_propCalculation->original['data']['arrearPayableAmt'])           // We have to adjust current demand
                $this->currentDemandAdjust();
            elseif ($this->_REQ->paidAmount < $this->_propCalculation->original['data']['arrearPayableAmt'] && $this->_REQ->paidAmount > $this->_propCalculation->original['data']['totalInterestPenalty'])           // We have to adjust Arrear demand
                $this->arrearDemandAdjust();
            else
                throw new Exception("Part Payment in Monthly Interest Not Available");
        }
    }
}
