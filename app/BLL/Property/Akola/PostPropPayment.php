<?php

namespace App\BLL\Property\Akola;

use App\MicroServices\DocumentUpload;
use App\MicroServices\IdGeneration;
use App\Models\Payment\TempTransaction;
use App\Models\Property\PropChequeDtl;
use App\Models\Property\PropDemand;
use App\Models\Property\PropPenaltyrebate;
use App\Models\Property\PropProperty;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\UlbWardMaster;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | Created On-18-09-2023 
 * | Created By-Anshu Kumar
 * | Created for handling the Payment for Cheque,Cash,DD, NEFT of Property
 * | Code - Closed
 */
class PostPropPayment
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

    /**
     * | Required @param Requests(propertyId as id)
     */
    public function __construct($req)
    {
        $this->_COMMON_FUNCTION = new \App\Repository\Common\CommonFunction();
        $this->_REQ = $req;
        $this->readGenParams();
    }

    /**
     * | Read Gen Params
     */
    public function readGenParams()
    {
        $this->_offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
        $this->_todayDate = Carbon::now();
        $this->_userId = auth()->user()->id;
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
            throw new Exception("No Dues Available for this Property");

        // Property Transactions
        $tranBy = auth()->user()->user_type;
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

        if (collect($demands)->isEmpty() && $arrear > 0) {
            $arrearDate = Carbon::now()->addYear(-1)->format('Y-m-d');
            $arrearFyear = getFY($arrearDate);
            $this->_fromFyear = $arrearFyear;
            $this->_uptoFyear = $arrearFyear;
            $this->_REQ['isArrearSettled'] = true;
        }

        $this->_demands = $demands;
    }

    /**
     * | Beginning Transactions
     */
    #this function is removed 
    public function postPaymentOld()
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
                $tblDemand->save();
            }

            if (!isset($demand->id) && isset($demand->is_arrear) && $demand->is_arrear == true) {             // Only for arrear payment
                //🔴🔴 New Entry            
                $demandReq = [
                    "property_id" => $this->_REQ['id'],
                    "general_tax" => $demand->general_tax,
                    "road_tax" => $demand->road_tax,
                    "firefighting_tax" => $demand->firefighting_tax,
                    "education_tax" => $demand->education_tax,
                    "water_tax" => $demand->water_tax,
                    "cleanliness_tax" => $demand->cleanliness_tax,
                    "sewarage_tax" => $demand->sewarage_tax,
                    "tree_tax" => $demand->tree_tax,
                    "professional_tax" => $demand->professional_tax,
                    "sp_education_tax" => $demand->state_education_tax,
                    "total_tax" => $demand->total_tax,
                    "balance" => $demand->balance,
                    "paid_status" => 1,
                    "fyear" => $demand->fyear,
                    "adjust_amt" => $demand->adjustAmt ?? 0,
                    "user_id" => $this->_userId,
                    "ulb_id" => $this->_propDetails->ulb_id,
                    "is_arrear" => true
                ];
                $demand = $this->_mPropDemand->create($demandReq);
            }

            // ✅✅✅✅✅ Tran details insertion
            $tranDtlReq = [
                "tran_id" => $propTrans['id'],
                "prop_demand_id" => $demand->id,
                "total_demand" => $demand->balance,
                "ulb_id" => $this->_REQ['ulbId'],
            ];
            $this->_mPropTranDtl->create($tranDtlReq);
            return $tranDtlReq;
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
        $mUserType = $user->user_type;
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
}
