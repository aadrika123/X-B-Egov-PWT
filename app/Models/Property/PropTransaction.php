<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use stdClass;

class PropTransaction extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /***
     * | @param id 
     * | @param key saf id or property id
     */
    public function getPropTransactions($id, $key)
    {
        return PropTransaction::where("$key", $id)
            ->where('status', 1)
            ->get();
    }

    public function getPropTransactionsHistory($id, $key)
    {
        return PropTransaction::where("$key", $id)
            ->where('status', 1)
            ->where('tran_type', '!=', 'isNakkalPayment')
            ->get();
    }

    public function getPropTransactionsNakal($id, $key)
    {
        return PropTransaction::where("$key", $id)
            ->where('status', 1)
            ->where('tran_type', '=', 'isNakkalPayment')
            ->get();
    }
    public function getTranByTranNo($tranNo)
    {
        return PropTransaction::where('tran_no', $tranNo)
            ->where('status', 1)
            ->first();
    }

    /**
     * | Get PropTran By tranno property id
     */
    public function getPropByTranPropId($tranNo)
    {
        return PropTransaction::select(
            'prop_transactions.*',
            'prop_cheque_dtls.bank_name',
            'prop_cheque_dtls.branch_name',
            'prop_cheque_dtls.cheque_no',
            'prop_cheque_dtls.cheque_date',
            'u.name as tc_name',
            'u.mobile as tc_mobile',
            "prop_cheque_dtls.status as cheque_status"
        )
            ->where('tran_no', $tranNo)
            ->leftJoin("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
            ->leftJoin("users as u", "u.id", "prop_transactions.user_id")
            ->where('prop_transactions.status', 1)
            ->first();
    }


    /**
     * | Get PropTran By tranno property id
     */
    public function getPropByTranId($tranId)
    {
        return PropTransaction::select(
            'prop_transactions.*',
            'prop_cheque_dtls.bank_name',
            'prop_cheque_dtls.branch_name',
            'prop_cheque_dtls.cheque_no',
            'prop_cheque_dtls.cheque_date',
            'u.name as tc_name',
            'u.mobile as tc_mobile',
            "prop_cheque_dtls.status as cheque_status"
        )
            ->where('prop_transactions.id', $tranId)
            ->leftJoin("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
            ->leftJoin("users as u", "u.id", "prop_transactions.user_id")
            ->where('prop_transactions.status', 1)
            ->first();
    }

    // getPropTrans as trait function on current object
    public function getPropTransTrait()
    {
        return DB::table('prop_transactions')
            ->select(
                'prop_transactions.*',
                DB::raw("TO_CHAR(prop_transactions.tran_date,'dd-mm-YYYY') as tran_date"),
                'a.saf_no',
                'p.holding_no',
                DB::raw("CASE
                            WHEN (prop_transactions.saf_id IS NULL) THEN 'PROPERTY'
                            WHEN (prop_transactions.property_id IS NULL) THEN 'SAF'
                        END
                        AS application_type
                        ")
            )
            ->leftJoin('prop_active_safs as a', 'a.id', '=', 'prop_transactions.saf_id')
            ->leftJoin('prop_properties as p', 'p.id', '=', 'prop_transactions.property_id')
            ->where('prop_transactions.status', 1);
    }

    // Get Property Transaction by citizen id
    public function getPropTransByCitizenId($citizenId)
    {
        return $this->getPropTransTrait()
            ->where('prop_transactions.citizen_id', $citizenId)
            ->orderByDesc('prop_transactions.id')
            ->first();
    }

    // Get Property Transaction by User Id
    public function getPropTransByUserId($userId)
    {
        return $this->getPropTransTrait()
            ->where('prop_transactions.user_id', $userId)
            ->orderByDesc('prop_transactions.id')
            ->get();
    }

    // Get Property Transaction by SAF Id
    public function getPropTransBySafId($safId)
    {
        return $this->getPropTransTrait()
            ->where('prop_transactions.saf_id', $safId)
            ->orderByDesc('prop_transactions.id')
            ->first();
    }

    // Get Property Transaction by Property ID
    public function getPropTransByPropId($propId)
    {
        return $this->getPropTransTrait()
            ->where('prop_transactions.property_id', $propId)
            ->orderByDesc('prop_transactions.id')
            ->first();
    }

    // Get Property Transaction by SAF Ids
    public function getPropTransBySafIdV2($safIds)
    {
        return $this->getPropTransTrait()
            ->whereIn('prop_transactions.saf_id', $safIds)
            ->orderByDesc('prop_transactions.id');
    }

    // Get Property Transaction by Property ID
    public function getPropTransByPropIdV2($propIds)
    {
        return $this->getPropTransTrait()
            ->whereIn('prop_transactions.property_id', $propIds)
            ->orderByDesc('prop_transactions.id');
    }

    // Save Property Transactions
    public function store($req)
    {
        $tranDate = Carbon::now()->format('Y-m-d');
        $metaReqs = [
            'saf_id' => $req->id,
            'amount' => $req->amount,
            'tran_date' => $tranDate,
            'tran_no' => $req->transactionNo,
            'payment_mode' => $req->paymentMode,
            'user_id' => $req->userId,
        ];
        return PropTransaction::insertGetId($metaReqs);
    }

    public function getAllData()
    {
        return PropTransaction::select('*')
            ->where('payment_mode', '!=', 'ONLINE')
            ->get();
    }

    /**
     * | Property Transaction
     * | @param 
     */
    public function postPropTransactions($req, $demands, $fromFyear = null, $uptoFyear = null)
    {
        $fromDemand = collect($demands)->first();
        if ($fromDemand instanceof stdClass)
            $fromDemand = (array)$fromDemand;

        $toDemand = collect($demands)->last();
        if ($toDemand instanceof stdClass)
            $toDemand = (array)$toDemand;

        $propTrans = new PropTransaction();
        $propTrans->property_id = $req['id'];
        $propTrans->amount = $req['amount'];
        $propTrans->tran_type = 'Property';
        $propTrans->tran_date = $req['todayDate'];
        $propTrans->tran_no = $req['tranNo'];
        $propTrans->payment_mode = $req['paymentMode'];
        $propTrans->user_id = $req['userId'] ?? (auth()->user() ? auth()->user()->id : null);
        $propTrans->ulb_id = $req['ulbId'];
        $propTrans->from_fyear = $fromDemand['fyear'] ?? $fromFyear;         // 🔴 In Case of only arrear payment fromFyear 
        $propTrans->to_fyear = $toDemand['fyear'] ?? $uptoFyear;            // 🔴 In Case of only arrear payment uptoFyear 
        $propTrans->demand_amt = $req['demandAmt'];
        $propTrans->tran_by_type = $req['tranBy'];
        $propTrans->verify_status = $req['verifyStatus'];
        $propTrans->arrear_settled_amt = $req['arrearSettledAmt'] ?? 0;
        $propTrans->is_arrear_settled = $req['isArrearSettled'] ?? false;
        $propTrans->book_no = $req['bookNo'] ?? null;
        $propTrans->device_type = $req['deviceType'] ?? null;
        $propTrans->payment_type = $req['paymentType'] ?? null;
        if ($req['isCitizen']) {
            $propTrans->user_id     = null;
            $propTrans->citizen_id  = $req['CitizenId'] ?? (auth()->user() ? auth()->user()->id : null);
            $propTrans->is_citizen  = $req['isCitizen'];
        }
        $propTrans->save();

        return [
            'id' => $propTrans->id
        ];
    }

    /**
     * | Property Cluster Demand
     */
    public function postClusterTransactions($req, $demands, $tranType = 'Property')
    {
        $propTrans = new PropTransaction();
        $propTrans->cluster_id = $req['id'];
        $propTrans->cluster_type = $req['clusterType'];
        $propTrans->amount = $req['amount'];
        $propTrans->tran_type = $tranType;
        $propTrans->tran_date = $req['todayDate'];
        $propTrans->tran_no = $req['tranNo'];
        $propTrans->payment_mode = $req['paymentMode'];
        $propTrans->user_id = $req['userId'];
        $propTrans->ulb_id = $req['ulbId'];
        $propTrans->from_fyear = collect($demands)->last()['fyear'];
        $propTrans->to_fyear = collect($demands)->first()['fyear'];
        $propTrans->from_qtr = collect($demands)->last()['qtr'];
        $propTrans->to_qtr = collect($demands)->first()['qtr'];
        $propTrans->demand_amt = collect($demands)->sum('balance');
        $propTrans->tran_by_type = $req['tranBy'];
        $propTrans->save();

        return [
            'id' => $propTrans->id
        ];
    }

    /**
     * | Post Saf Transaction
     */
    public function postSafTransaction($req, $demands)
    {
        $propTrans = new PropTransaction();
        $propTrans->saf_id = $req['id'];
        $propTrans->amount = $req['amount'];
        $propTrans->tran_type = 'Saf';
        $propTrans->tran_date = $req['todayDate'];
        $propTrans->tran_no = $req['tranNo'];
        $propTrans->payment_mode = $req['paymentMode'];
        $propTrans->user_id = $req['userId'];
        $propTrans->ulb_id = $req['ulbId'];
        $propTrans->from_fyear = collect($demands)->first()['fyear'];
        $propTrans->to_fyear = collect($demands)->last()['fyear'];
        $propTrans->demand_amt = $req['demandAmt'];
        $propTrans->tran_by_type = $req['tranBy'];
        $propTrans->verify_status = $req['verifyStatus'];
        $propTrans->save();

        return [
            'id' => $propTrans->id
        ];
    }

    /**
     * | public function Get Transaction Full Details by TranNo
     */
    public function getPropTransFullDtlsByTranNo($tranNo)
    {
        return DB::table('prop_transactions as t')
            ->select(
                't.*',
                'd.prop_demand_id',
                'd.total_demand',
                'pd.arv',
                'pd.qtr',
                'pd.holding_tax',
                'pd.water_tax',
                'pd.education_cess',
                'pd.health_cess',
                'pd.latrine_tax',
                'pd.additional_tax',
                'pd.amount',
                'pd.balance',
                'pd.fyear',
                'pd.due_date'
            )
            ->join('prop_tran_dtls as d', 'd.tran_id', '=', 't.id')
            ->join('prop_demands as pd', 'pd.id', '=', 'd.prop_demand_id')
            ->where('t.tran_no', $tranNo)
            ->where('pd.status', 1)
            ->orderBy('pd.due_date')
            ->get();
    }

    /**
     * | Cheque Dtl And Transaction Dtl
     */
    public function chequeTranDtl($ulbId)
    {
        return PropTransaction::select(
            'prop_cheque_dtls.*',
            DB::raw("1 as module_id"),
            DB::raw(
                "case when prop_transactions.property_id is not null then 'Property' when 
                prop_transactions.saf_id is not null then 'Saf' end as tran_type"
            ),
            DB::raw("TO_CHAR(tran_date, 'DD-MM-YYYY') as tran_date"),
            'tran_no',
            'payment_mode',
            'amount',
            DB::raw("TO_CHAR(cheque_date, 'DD-MM-YYYY') as cheque_date"),
            "bank_name",
            "branch_name",
            "bounce_status",
            "cheque_no",
            DB::raw("TO_CHAR(clear_bounce_date, 'DD-MM-YYYY') as clear_bounce_date"),
            "users.name as user_name"
        )
            ->join('prop_cheque_dtls', 'prop_cheque_dtls.transaction_id', 'prop_transactions.id')
            ->join('users', 'users.id', 'prop_cheque_dtls.user_id')
            ->whereIn('payment_mode', ['CHEQUE', 'DD'])
            // ->where('prop_transactions.status', 1)
            ->where('prop_transactions.ulb_id', $ulbId);
    }

    /**
     * | Prop Transaction Details by date
     */
    public function tranDetail($date, $ulbId)
    {
        return PropTransaction::select(
            'users.id',
            "users.name as user_name",
            DB::raw("sum(amount) as amount"),
        )
            ->join('users', 'users.id', 'prop_transactions.user_id')
            ->where('verify_date', $date)
            ->where('prop_transactions.status', 1)
            ->where('payment_mode', '!=', 'ONLINE')
            ->where('verify_status', 1)
            ->where('prop_transactions.ulb_id', $ulbId)
            ->groupBy(['users.id', "user_name"]);
    }

    /**
     * | 
     */
    public function recentPayment($userId)
    {
        return PropTransaction::select(
            'property_id',
            'saf_id',
            'tran_no as transactionNo',
            'tran_date as transactionDate',
            'payment_mode as paymentMode',
            'amount',
            DB::raw(
                "case when prop_transactions.property_id is not null then 'Property' when 
                prop_transactions.saf_id is not null then 'Saf' end as tran_type
            "
            ),
        )
            ->where('user_id', $userId)
            ->where('prop_transactions.status', 1)
            ->orderBydesc('id')
            ->take(10)
            ->get();
    }

    /**
     * | 
     */
    public function tranDtl($userId, $fromDate, $toDate)
    {
        return PropTransaction::where('user_id', $userId)
            ->whereBetween('tran_date', [$fromDate, $toDate]);
    }

    /**
     * | Get Last Tranid by Prop or Saf Id
     */
    public function getLastTranByKeyId($key, $appId)
    {
        return PropTransaction::where("$key", $appId)
            ->orderByDesc('id')
            ->where('status', 1)
            ->first();
    }

    /**
     * | Store Transaction
     */
    public function storeTrans(array $req)
    {
        $stored = PropTransaction::create($req);
        return [
            'id' => $stored->id
        ];
    }

    /**
     * | Update Cheque Document Information
     */
    public function updateChequeDocInfo($tranId, array $cheDocDtls)
    {
        self::find($tranId)->update([
            'cheque_doc_refno' => $cheDocDtls['ReferenceNo'],
            'cheque_doc_uniqueid' => $cheDocDtls['uniqueId']
        ]);
    }

    /**
     * | Get Property Transaction by Transaction No
     */
    public function getTransByTranNo($tranNo)
    {
        return DB::table('prop_transactions as t')
            ->select(
                't.id as transaction_id',
                't.tran_no as transaction_no',
                't.amount',
                't.payment_mode',
                't.tran_date',
                't.tran_type as module_name',
                DB::raw("
                CASE
                    WHEN t.property_id is not null THEN t.property_id
                    ELSE t.saf_id
                END as application_id
            "),
                DB::raw('1 as moduleId'),
                't.status'
            )
            ->where('t.tran_no', $tranNo)
            ->where('status', 1)
            ->get();
    }

    public function getSafTranList($safId)
    {
        return $data = self::select(
            "prop_transactions.*",
            DB::raw(
                "users.name,
                                users.mobile,
                                prop_cheque_dtls.cheque_date,
                                prop_cheque_dtls.bank_name,                                
                                prop_cheque_dtls.branch_name,
                                prop_cheque_dtls.cheque_no,
                                CASE WHEN UPPER(prop_transactions.payment_mode) NOT IN ('CASH','ONLINE') AND prop_cheque_dtls.status is not null then prop_cheque_dtls.status else prop_transactions.verify_status END AS cheque_status
                                "
            )
        )
            ->leftJoin("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
            ->leftJoin("users", "users.id", "prop_transactions.user_id")
            ->where("prop_transactions.saf_id", $safId)
            ->whereIn("prop_transactions.status", [1, 2])
            ->get();
    }

    public function getAllTranDtls()
    {
        return $this->hasMany(PropTranDtl::class, "tran_id", "id")->where("status", 1);
    }

    public function getChequeDtl()
    {
        return $this->hasOne(PropChequeDtl::class, "transaction_id", "id")->where("status", "<>", 0)->orderBy("id")->first();
    }

    public function getTransactionsNakal($tranId)
    {
        $transactions = PropTransaction::select(
            'prop_transactions.property_id',
            'prop_transactions.saf_id',
            'prop_transactions.tran_date',
            'prop_transactions.tran_no',
            'prop_transactions.amount',
            'prop_transactions.tran_type',
            DB::raw("string_agg(DISTINCT prop_owners.owner_name, ', ') as owner_name"),
            DB::raw("string_agg(DISTINCT prop_owners.owner_name_marathi, ', ') as owner_names_marathi")
        )
            ->join('prop_properties as p', 'p.id', '=', 'prop_transactions.property_id')
            ->join('prop_owners', 'prop_owners.property_id', '=', 'p.id')
            ->whereIn('prop_transactions.status', [1, 2])
            ->where('tran_type', '=', 'isNakkalPayment')
            ->where('prop_transactions.id', $tranId)
            ->groupBy(
                'prop_transactions.property_id',
                'prop_transactions.saf_id',
                'prop_transactions.tran_date',
                'prop_transactions.tran_no',
                'prop_transactions.amount',
                'prop_transactions.tran_type'
            )
            ->get();

        // Add the 'paidAmtInWords' field
        $transactions->each(function ($transaction) {
            $transaction->paidAmtInWords = getIndianCurrency($transaction->amount);
        });

        return $transactions;
    }
}
