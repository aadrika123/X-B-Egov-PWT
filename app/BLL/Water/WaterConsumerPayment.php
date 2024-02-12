<?php

namespace App\BLL\Water;

use App\MicroServices\IdGeneration;
use App\Models\Payment\TempTransaction;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterAdjustment;
use App\Models\Water\WaterAdvance;
use App\Models\Water\WaterChequeDtl;
use App\Models\Water\WaterConsumer;
use App\Models\Water\WaterConsumerCollection;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterSecondConsumer;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterTranDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class WaterConsumerPayment
{
    public $_REQ;
    public $waterDemands;
    private $_WaterDemandsModel;
    public $_tranNo;
    private $_offlinePaymentModes;
    private $_todayDate;
    private $_userId;
    private $_mWaterTrans;
    private $_mWaterTranDtl;
    private $_mConsumerCollection;
    private $_consumerId;
    private $_verifyPaymentModes;
    private $_WaterConsumer;
    private $_demands;
    protected $_gatewayType = null;
    public $_tranId;
    private $_COMMON_FUNCTION;
    private $_WaterAdvance;
    private $_WaterAdjustment;
    private $_PropPendingArrear;
    private $_idGeneration;
    private $_ulbWardMaster;
    private $_mTempTransaction;
    private $_mWaterChequeDtl;
    private $_moduleId;
    private $_adjustmentFor;
    protected $_DB_NAME;
    protected $_DB;
    protected $_DB_MASTER;
    protected $_error;
    public function __construct($req)
    {
        $this->_DB_NAME             = "pgsql_water";
        $this->_DB                  = DB::connection($this->_DB_NAME);
        $this->_DB_MASTER           = DB::connection("pgsql_master");
        $this->_COMMON_FUNCTION = new \App\Repository\Common\CommonFunction();
        $this->_WaterAdvance = new WaterAdvance();
        $this->_WaterAdjustment = new WaterAdjustment();
        $this->_WaterDemandsModel = new WaterConsumerDemand();
        $this->_idGeneration = new IdGeneration;
        $this->_mWaterTrans = new WaterTran();
        $this->_mWaterTranDtl = new WaterTranDetail();
        $this->_mConsumerCollection = new WaterConsumerCollection();
        $this->_ulbWardMaster = new UlbWardMaster();
        $this->_mTempTransaction   = new TempTransaction();
        $this->_mWaterChequeDtl     = new WaterChequeDtl();
        $this->_REQ = $req;
        $this->readGenParams();
    }
    /**
     * | Database transaction
     */
    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB_MASTER->getDatabaseName();
        DB::beginTransaction();
        if ($db1 != $db2)
            $this->_DB->beginTransaction();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB_MASTER->beginTransaction();
    }
    /**
     * | Database transaction
     */
    public function rollback()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB_MASTER->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB_MASTER->rollBack();
    }
    /**
     * | Database transaction
     */
    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB_MASTER->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB_MASTER->commit();
    }
    public function readGenParams()
    {
        $this->_offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
        $this->_todayDate = Carbon::now();
        $this->_userId = auth()->user()->id ?? $this->_REQ['userId'];
        $this->_consumerId = $this->_REQ['consumerId'];
        $this->_verifyPaymentModes = Config::get('payment-constants.VERIFICATION_PAYMENT_MODES');
        $this->_moduleId           = Config::get('module-constants.WATER_MODULE_ID');
        $this->_adjustmentFor      = Config::get("waterConstaint.ADVANCE_FOR.1");

        $this->_WaterConsumer = WaterSecondConsumer::find($this->_consumerId);
        if (collect($this->_WaterConsumer)->isEmpty())
            throw new Exception("Consumer Details Not Available for this id");

        if ($this->_REQ['transactionNo'])
            $this->_tranNo = $this->_REQ['transactionNo'];          // Transaction No comes in case of online payment
        else
            $this->_tranNo = $this->_idGeneration->generateTransactionNo($this->_WaterConsumer->ulb_id);
    }

    public function readPaymentParams()
    {
        if (!$this->waterDemands->original["status"]) {
            throw new Exception($this->waterDemands->original["message"]);
        }
        $demands = $this->waterDemands->original['data']['consumerDemands'];
        $demands = collect($demands)->sortBy("demand_upto");
        $this->_demands = $demands;

        $payableAmount = $this->waterDemands->original['data']['totalSumDemand'];

        if ($payableAmount <= 0)
            throw new Exception("Payment Amount should be greater than 0");
        if (collect($demands)->isEmpty())
            throw new Exception("No Dues For this Consumer");
        // Property Transactions
        $tranBy = auth()->user()->user_type ??  $this->_REQ['userType'];
        $wardDTls = $this->_ulbWardMaster::find($this->_WaterConsumer->ward_mstr_id);

        $this->_REQ->merge([
            'userId' => $this->_userId,
            'todayDate' => $this->_todayDate->format('Y-m-d'),
            'tranNo' => $this->_tranNo,
            'payableAmount' => $payableAmount,                                                                         // Payable Amount with Arrear
            'demandAmt' => $payableAmount,                         // Demandable Amount
            'userType' => $tranBy,
            'verifyStatus' => 1,
            'id'           => $this->_WaterConsumer->id,
            'chargeCategory' => "Demand Collection",
            "wardMstrId" => $this->_WaterConsumer->ward_mstr_id,
            'workflowId'    => 0,
            'wardNo'       => $wardDTls->ward_name ?? null,
            'moduleId'     => $this->_moduleId,
            "ulbId"        => $this->_WaterConsumer->ulb_id,
            'isJsk'        => true
        ]);

        if (in_array($this->_REQ['paymentMode'], $this->_verifyPaymentModes)) {
            $this->_REQ->merge([
                'verifyStatus' => 2
            ]);
        }
    }


    public function postPayment()
    {
        $this->readPaymentParams();
        if (!$this->_REQ->amount) {
            $this->_REQ->merge(['amount' => $this->_REQ['payableAmount']]);
        }
        $addvanceAmt = $this->waterDemands->original['data']["remainAdvance"] ?? 0;
        $adjustAmt = 0;
        $payableAmount = $this->_REQ["amount"];
        if (strtoupper($this->_REQ["paymentMode"]) != "ONLINE") {
            $adjustAmt = round($this->_REQ['payableAmount'] - $addvanceAmt);
            $adjustAmt = $adjustAmt >= 0 ? $addvanceAmt : $this->_REQ->amount;
            switch ($this->_REQ->paymentType) {
                case "isPartPayment":
                    $payableAmount = $payableAmount + $addvanceAmt;
                    break;
                case "isFullPayment":
                    $payableAmount;
                    $this->_REQ->merge(
                        [
                            'amount' => ($this->_REQ->amount - $addvanceAmt)
                        ]
                    );
                    break;
            }
        }
        $paidPenalty = 0;
        $paidDemands = [];
        $leftDemand = ($this->_REQ['payableAmount'] - $payableAmount) > 0 ? $this->_REQ['payableAmount'] - $payableAmount : 0;
        $this->_REQ->merge(["leftDemandAmount" => $leftDemand]);
        foreach ($this->_demands as $key => $val) {
            if ($payableAmount <= 0) {
                continue;
            }
            $paymentDtl = ($this->demandAdjust($payableAmount, $val["id"]));
            $payableAmount = $paymentDtl["balence"];
            $paidPenalty += $paymentDtl["payableAmountOfPenalty"];
            $paidDemands[] = $paymentDtl;
        }
        if (!$paidDemands) {
            throw new Exception("Something went wrong");
        }
        $d1 = [];
        $trDtl = [];
        // dd(["paid amount"=>$this->_REQ->amount,"oldAdvanceAmt"=>$addvanceAmt,"newAdvanceAmt"=>$payableAmount,"adjustAmt"=>$adjustAmt,]);
        # Save the Details of the transaction
        $waterTrans = $this->_mWaterTrans->waterTransaction($this->_REQ, $this->_WaterConsumer);
        $this->_tranId = $waterTrans['id'];
        // Updation of payment status in demand table
        foreach ($paidDemands as $dtls) {
            $demand = collect($dtls["currentTax"]);
            $demand = (object)$demand->toArray();
            $tblDemand = $this->_WaterDemandsModel->findOrFail($demand->id);
            $d1[] = $demand;
            $tblDemand->is_full_paid = $dtls["remaining"] > 0 ? false : true;
            $paidTaxes = (object)($dtls["paidCurrentTaxesBifurcation"]);

            // Update Paid Taxes

            /**
             * | due taxes = paid_taxes-due Taxes
             */
            $tblDemand->paid_status         = 1;           // Paid Status Updation
            $tblDemand->due_balance_amount  = round($tblDemand->due_balance_amount - $paidTaxes->paidTotalTax)      > 0  ? $tblDemand->due_balance_amount - $paidTaxes->paidTotalTax            : 0;
            $tblDemand->due_arrear_demand   = $tblDemand->due_arrear_demand - $paidTaxes->paidArrearDemand          > 0  ?  ($tblDemand->due_balance_amount == 0 ? 0  : $tblDemand->due_arrear_demand - $paidTaxes->paidArrearDemand)  : 0;
            $tblDemand->due_current_demand  = $tblDemand->due_current_demand - $paidTaxes->paidCurrentDemand       > 0  ?  ($tblDemand->due_balance_amount == 0 ? 0  : $tblDemand->due_current_demand - $paidTaxes->paidCurrentDemand)  : 0;

            $tblDemand->paid_total_tax      = $paidTaxes->paidTotalTax + $tblDemand->paid_total_tax                 > 0  ?  ($paidTaxes->paidTotalTax + $tblDemand->paid_total_tax) : 0;
            #it is testing purps only
            if (strtoupper($this->_REQ['paymentMode']) != "ONLINE") {
                foreach ($tblDemand->toArray() as $keys => $testVal) {
                    if (is_numeric($testVal) && $testVal < 0) {
                        throw new Exception($keys . " Go On Nagative of fyear " . $tblDemand->generation_date . " amount =>" . $testVal);
                    }
                }
            }
            if ($tblDemand->due_balance_amount <= 0) {
                $tblDemand->is_full_paid = true;
            }
            # end hear
            $tblDemand->save();

            // ✅✅✅✅✅ Tran details insertion
            $collection = [
                "transaction_id" => $waterTrans['id'],
                "consumer_id" => $demand->consumer_id,
                "ward_mstr_id" => $this->_REQ["wardMstrId"],
                "demand_id" => $demand->id,
                "amount" => $demand->due_balance_amount,
                "emp_details_id" => $this->_REQ['userId'],
                "demand_from" => $paidTaxes->demand_from,
                "demand_upto" => $paidTaxes->demand_upto,
                "connection_type" => $demand->connection_type,
                "paid_amount" => $paidTaxes->paidTotalTax,
            ];
            $tranDtlReq = [
                "tran_id" => $waterTrans['id'],
                "application_id" => $demand->consumer_id,
                "demand_id" => $demand->id,
                "total_demand" => $demand->due_balance_amount,
                "paid_amount" => $paidTaxes->paidTotalTax,
                "arrear_settled" => $paidTaxes->paidArrearDemand,
            ];

            $collDtlId  = $this->_mConsumerCollection->create($collection)->id;
            $DtlId      = $this->_mWaterTranDtl->create($tranDtlReq)->id;
            // $tranDtlReq["collDtlId"] =$collDtlId;
            // $tranDtlReq["DtlId"]    =$DtlId;
            $trDtl[] = $tranDtlReq;
        }
        # Save the Details for the Cheque,DD,neft
        if (in_array(strtoupper($this->_REQ['paymentMode']), $this->_offlinePaymentModes)) {
            $this->_REQ->merge([
                'tranId'        => $waterTrans['id'],
                'applicationNo' => $this->_WaterConsumer->consumer_no,
            ]);
            $this->postOtherPaymentModes($this->_REQ);
        }
        #insert Advance Amount
        if (round($payableAmount) > 0) {
            $advArr = [
                "related_id" => $this->_consumerId,
                "reason"    => "Advance payment",
                "tran_id" => $waterTrans['id'],
                "amount" => round($payableAmount),
                "user_id" => $this->_REQ['userId'] ?? (auth()->user() ? auth()->user()->id : null),
                "advance_for" => $this->_adjustmentFor,
                "remarks" => "Advance Payment",
            ];
            $this->_WaterAdvance->store($advArr);
        }

        #insert Adjusted Amount
        if (round($adjustAmt) > 0) {
            $adjArr = [
                "related_id" => $this->_consumerId,
                "tran_id" => $waterTrans['id'],
                "adjustment_for" => $this->_adjustmentFor,
                "amount" => round($adjustAmt),
                "user_id" => $this->_REQ['userId'] ?? (auth()->user() ? auth()->user()->id : null),
                // "ulb_id" => $this->_REQ['ulbId'] ?? (auth()->user() ? auth()->user()->ulbd_id : null),
            ];
            $this->_WaterAdjustment->store($adjArr);
        }
    }
    public function postOtherPaymentModes($req)
    {
        $cash               = Config::get('payment-constants.PAYMENT_MODE.3');
        $moduleId           = Config::get('module-constants.WATER_MODULE_ID');

        if (strtoupper($req['paymentMode']) != $cash) {
            if ($req->chargeCategory == "Demand Collection") {
                $chequeReqs = [
                    'user_id'           => $req['userId'],
                    'consumer_id'       => $req['id'],
                    'transaction_id'    => $req['tranId'],
                    'cheque_date'       => $req['chequeDate'],
                    'bank_name'         => $req['bankName'],
                    'branch_name'       => $req['branchName'],
                    'cheque_no'         => $req['chequeNo']
                ];
            } else {
                $chequeReqs = [
                    'user_id'           => $req['userId'],
                    'application_id'    => $req['id'],
                    'transaction_id'    => $req['tranId'],
                    'cheque_date'       => $req['chequeDate'],
                    'bank_name'         => $req['bankName'],
                    'branch_name'       => $req['branchName'],
                    'cheque_no'         => $req['chequeNo']
                ];
            }
            $this->_mWaterChequeDtl->postChequeDtl($chequeReqs);
        }

        $tranReqs = [
            'transaction_id'    => $req['tranId'],
            'application_id'    => $req['id'],
            'module_id'         => $moduleId,
            'workflow_id'       => $req['workflowId'],
            'transaction_no'    => $req['tranNo'],
            'application_no'    => $req['applicationNo'],
            'amount'            => $req['amount'],
            'payment_mode'      => strtoupper($req['paymentMode']),
            'cheque_dd_no'      => $req['chequeNo'],
            'bank_name'         => $req['bankName'],
            'tran_date'         => $req['todayDate'],
            'user_id'           => $req['userId'],
            'ulb_id'            => $req['ulbId'],
            'ward_no'           => $req['ward_no']
        ];
        $this->_mTempTransaction->tempTransaction($tranReqs);
    }
    public function readPaidTaxes($demand)
    {
        $demand = (object)collect($demand)->toArray();
        return [
            'paidTotalTax' => $demand->total_tax ?? 0,
            'paidArrearDemand' => $demand->arrear_demand ?? 0,
            'paidCurrentDemand' => $demand->current_demand ?? 0,
        ];
    }

    public function demandAdjust($currentPayableAmount, $demanId)
    {
        $currentTax = collect($this->waterDemands->original['data']['consumerDemands'])->where("id", $demanId);
        $totaTax = $currentTax->sum("due_balance_amount");
        $penalty = $currentTax->sum("due_penalty");
        $demandPayableAmount = $totaTax + $penalty;

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
            "fromDate" => ($currentTax->first())["demand_from"],
            "uptoDate" => ($currentTax->first())["demand_upto"],
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
        $paidArrearDemand = (roundFigure($payableAmountOfTax) > $currentTax->sum('due_arrear_demand'))  ? $currentTax->sum('due_arrear_demand') : (roundFigure($payableAmountOfTax));
        if ($paidArrearDemand > $currentTax->sum('due_arrear_demand') && roundFigure($payableAmountOfTax) == $totaTax) {
            $paidArrearDemand = $currentTax->sum('due_arrear_demand');
        }
        $paidCurrentDemand = $payableAmountOfTax > $currentTax->sum('due_arrear_demand') ? ($payableAmountOfTax - ($paidArrearDemand)) : 0;
        if ($paidCurrentDemand > $currentTax->sum('due_current_demand') && roundFigure($payableAmountOfTax) == $totaTax) {
            $paidCurrentDemand = $currentTax->sum('due_current_demand');
        }

        $paidDemandBifurcation = [
            'total_tax' => roundFigure(($payableAmountOfTax)),
            "arrear_demand" => $paidArrearDemand,
            "current_demand" => $paidCurrentDemand,
        ];
        $data["paid_total_tax"] =  $paidDemandBifurcation["total_tax"] ?? 0;
        $data["paidCurrentTaxesBifurcation"] = $this->readPaidTaxes($paidDemandBifurcation);
        $data["paidCurrentTaxesBifurcation"]['demand_from'] = $currentTax->min('demand_from');
        $data["paidCurrentTaxesBifurcation"]['demand_upto'] = $currentTax->max('demand_upto');
        return $data;
    }
}
