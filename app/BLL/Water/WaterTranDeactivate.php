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

class WaterTranDeactivate
{
    public $waterDemands;
    private $_WaterDemandsModel;
    private $_mWaterTrans;
    private $_mWaterTranDtl;
    private $_mConsumerCollection;
    protected $_gatewayType = null;
    public $_tranId;
    private $_WaterAdvance;
    private $_WaterAdjustment;
    private $_mTempTransaction   ;
    protected $_DB_NAME;
    protected $_DB;
    protected $_DB_MASTER;
    protected $_error;
    private $_transaction;
    private $_tranDtls;

    public function __construct($tranId)
    {
        $this->_tranId = $tranId;
        $this->_DB_NAME             = "pgsql_water";
        $this->_DB                  = DB::connection($this->_DB_NAME);
        $this->_DB_MASTER           = DB::connection("pgsql_master");
        $this->_WaterAdvance = new WaterAdvance();
        $this->_WaterAdjustment = new WaterAdjustment();
        $this->_WaterDemandsModel = new WaterConsumerDemand();
        $this->_mWaterTrans = new WaterTran();
        $this->_mWaterTranDtl = new WaterTranDetail();
        $this->_mConsumerCollection = new WaterConsumerCollection();
        $this->_mTempTransaction   = new TempTransaction();
    }

    /**
     * | Deactivate transaction of consumer or app(1)
     */
    public function deactivate()
    {
        $this->_transaction = $this->_mWaterTrans::find($this->_tranId);

        if (collect($this->_transaction)->isEmpty())
            throw new Exception("Transaction not found");
        $this->deactivateConsumerTrans();                       // 1.1

        $this->deactivateAppTrans();                        // (1.2)  🔴🔴 Yet to complete
        
        $this->_transaction->status = 0;                    // Deactivation
        $this->_transaction->save();

        $this->deactivateTempTrans();                       // (1.3) 
        $this->_WaterAdvance->deactivateAdvanceByTrId($this->_transaction->id);
        $this->_WaterAdjustment->deactivateAdjustmentAmtByTrId($this->_transaction->id);        
    }

    private function deactivateConsumerTrans()
    {
        if(strtoupper($this->_transaction->tran_type)==strtoupper("Demand Collection"))
        {
            $this->_tranDtls = $this->_mWaterTranDtl->getDetailByTranId($this->_transaction->id);
            $this->_mConsumerCollection->where("transaction_id",$this->_transaction->id)->update(["status"=>0]);
            foreach($this->_tranDtls as $key=>$tranDtl)
            {
                $demand = $this->_WaterDemandsModel->find($tranDtl->demand_id);
                if (collect($demand)->isEmpty()) {
                    throw new Exception("Demand Not Available for demand ID $tranDtl->demand_id");
                }
                $this->adjustPropDemand($demand, $tranDtl);
                $oldD = $this->_WaterDemandsModel->find($tranDtl->demand_id);
                $demand->save();
                
                # Tran Dtl Deactivation
                $tranDtl = $this->_mWaterTranDtl->find($tranDtl->id);
                $tranDtl->status = 0;               # Deactivation of tran Details
                $tranDtl->save();
            }
        }
    }

    private function adjustPropDemand(WaterConsumerDemand $tblDemand, $paidTaxes)
    {
        $tblDemand->due_balance_amount  = $tblDemand->due_balance_amount + ($paidTaxes->paid_amount ? $paidTaxes->paid_amount : 0) ;
        $tblDemand->due_arrear_demand   = $tblDemand->due_arrear_demand + ($paidTaxes->arrear_settled ? $paidTaxes->arrear_settled : 0);
        $tblDemand->due_current_demand  = $tblDemand->due_current_demand +(($paidTaxes->paid_amount ? $paidTaxes->paid_amount : 0 ) - ($paidTaxes->arrear_settled ? $paidTaxes->arrear_settled : 0)) ;        
        $tblDemand->paid_total_tax      = $tblDemand->paid_total_tax - ($paidTaxes->paid_amount ? $paidTaxes->paid_amount : 0);
        if( $tblDemand->paid_status == 1)
        {
            $tblDemand->is_full_paid = false;
        }

        if( $tblDemand->paid_total_tax == 0)
        {
            $tblDemand->paid_status = 0;
        }
    }

    private function deactivateAppTrans()
    {
        
    }

    public function deactivateTempTrans()
    {
        $tempTrans = $this->_mTempTransaction->getTempTranByTranId($this->_tranId, 1);                // 1 is the module id for property
        if ($tempTrans)
            $tempTrans->update(['status' => 0]);
    }
}