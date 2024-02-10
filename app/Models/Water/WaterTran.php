<?php

namespace App\Models\Water;

use App\MicroServices\IdGeneration;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class WaterTran extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $connection = 'pgsql_water';

    /**
     * |--------------- Get Transaction Data -----------|
     */
    public function getTransactionDetailsById($req)
    {
        return WaterTran::where('related_id', $req)
            ->get();
    }

    /**
     * |---------------- Get transaction by the transaction details ---------------|
     */
    public function getTransNo($applicationId, $applicationFor)
    {
        return WaterTran::where('related_id', $applicationId)
            ->where('tran_type', "<>", "Demand Collection")
            ->where('status', 1);
    }
    public function ConsumerTransaction($applicationId)
    {
        return WaterTran::where('related_id', $applicationId)
            ->where('tran_type', "=", "Demand Collection")
            ->where('status', 1)
            ->orderByDesc('id');
    }
    public function ConsumerTransactionV2($transactionId)
    {
        return WaterTran::join('water_tran_details', 'water_tran_details.tran_id', 'water_trans.id')
            ->where('water_trans.id', $transactionId)
            ->where('water_trans.status', 1)
            ->where('water_tran_details.status', 1);
    }
    public function siteInspectionTransaction($applicationId)
    {
        return WaterTran::where('related_id', $applicationId)
            ->where('tran_type', "Site Inspection")
            ->where('status', 1)
            ->orderByDesc('id');
    }
    public function getTransByCitizenId($citizenId)
    {
        return WaterTran::where('citizen_id', $citizenId)
            ->where('status', 1)
            ->orderByDesc('id');
    }
    public function getTransNoForConsumer($applicationId, $applicationFor)
    {
        return WaterTran::where('related_id', $applicationId)
            ->where('tran_type', $applicationFor)
            ->where('status', 1);
    }

    /**
     * | Get Transaction Details According to TransactionId
     * | @param 
     */
    public function getTransactionByTransactionNo($transactionNo)
    {
        return WaterTran::select(
            'water_trans.*',
            'water_tran_details.demand_id'
        )
            ->leftjoin('water_tran_details', 'water_tran_details.tran_id', '=', 'water_trans.id')
            ->where('tran_no', $transactionNo)
            ->where('water_trans.status', 1);
        // ->where('water_tran_details.status', 1);
    }


    public function getTransactionByTransactionNoV2($transactionNo, $reftranId)
    {
        $query = WaterTran::select(
            'water_trans.*',
            'water_tran_details.demand_id',
            'users.name as tcName',
            'users.mobile',
            'water_second_consumers.zone_mstr_id',
            'zone_masters.zone_name' // Add this line to retrieve zone_name
            // 'consumer_bills.paid_amt',
            // 'consumer_bills.payment_date'
        )
            ->leftJoin('water_tran_details', 'water_tran_details.tran_id', 'water_trans.id')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_trans.related_id')
            ->leftjoin('users', 'users.id', 'water_trans.emp_dtl_id')
            ->leftJoin('zone_masters', 'zone_masters.id', '=', 'water_second_consumers.zone_mstr_id') // Join zone_masters
            // ->leftjoin('consumer_bills','consumer_bills.consumer_id','water_trans.related_id') 
            // ->orderby('consumer_bills.id','Desc')
            ->where('water_trans.status', 1);


        if ($transactionNo !== null) {
            $query->where('water_trans.tran_no', $transactionNo);
        } elseif ($reftranId !== null) {
            $query->where('water_trans.id', $reftranId);
        }

        return $query;
    }


    /**
     * | Enter the default details of the transacton which have 0 Connection charges
     * | @param totalConnectionCharges
     * | @param ulbId
     * | @param req
     * | @param applicationId
     * | @param connectionId
     * | @param connectionType
        | Check for the user Id for wether to save the user id in emp_details_id or in citizen_id
     */
    public function saveZeroConnectionCharg($totalConnectionCharges, $ulbId, $req, $applicationId, $connectionId, $connectionType)
    {
        $user               = auth()->user();
        $refIdGeneration    = new IdGeneration();
        $transactionNo      = $refIdGeneration->generateTransactionNo($ulbId);
        if ($user->user_type == 'Citizen') {
            $isJsk = false;
            $paymentMode = "Online";                                                // Static
            $citizenId = $user->id;
        } else {                                                                    // ($user->user_type != 'Citizen')
            $empId = $user->id;
            $paymentMode = "Cash";                                                  // Static
        }

        $watertransaction = new WaterTran;
        $watertransaction->related_id       = $applicationId;
        $watertransaction->ward_id          = $req->wardId;
        $watertransaction->tran_type        = $connectionType;
        $watertransaction->tran_date        = Carbon::now();
        $watertransaction->payment_mode     = $paymentMode;
        $watertransaction->amount           = $totalConnectionCharges;
        $watertransaction->emp_dtl_id       = $empId ?? null;
        $watertransaction->citizen_id       = $citizenId ?? null;
        $watertransaction->user_type        = $user->user_type;
        $watertransaction->is_jsk           = $isJsk ?? true;
        $watertransaction->created_at       = Carbon::now();
        $watertransaction->ip_address       = getClientIpAddress();
        $watertransaction->ulb_id           = $ulbId;
        $watertransaction->tran_no          = $transactionNo;
        $watertransaction->save();
        $transactionId = $watertransaction->id;

        $mWaterTranDetail = new WaterTranDetail();
        $mWaterTranDetail->saveDefaultTrans($totalConnectionCharges, $applicationId, $transactionId, $connectionId, null);
    }

    public function chequeTranDtl($ulbId)
    {
        return WaterTran::select(
            'water_trans.*',
            DB::raw("TO_CHAR(water_trans.tran_date, 'DD-MM-YYYY') as tran_date"),
            'water_cheque_dtls.*',
            DB::raw("TO_CHAR(water_cheque_dtls.cheque_date, 'DD-MM-YYYY') as cheque_date"),
            DB::raw("TO_CHAR(water_cheque_dtls.clear_bounce_date, 'DD-MM-YYYY') as clear_bounce_date"),
            "users.name as user_name",
            DB::raw("2 as module_id"),
        )
            ->leftjoin('water_cheque_dtls', 'water_cheque_dtls.transaction_id', 'water_trans.id')
            ->join('users', 'users.id', 'water_cheque_dtls.user_id')
            ->whereIn('payment_mode', ['Cheque', 'DD'])
            // ->where('water_trans.status', 1)
            ->where('water_trans.ulb_id', $ulbId);
    }

    /**
     * | Post Water Transaction
        | Make the column for pg_response_id and pg_id
     */
    public function waterTransaction($req, $consumer)
    {
        $waterTrans = new WaterTran();
        $nowTime = Carbon::now()->format('h:i:s A');
        $waterTrans->related_id         = $req['id'];
        $waterTrans->amount             = $req['amount'];
        $waterTrans->tran_type          = $req['chargeCategory'];
        $waterTrans->tran_date          = $req['todayDate'];
        $waterTrans->tran_no            = $req['tranNo'];
        $waterTrans->payment_mode       = $req['paymentMode'];
        $waterTrans->emp_dtl_id         = $req['userId'] ?? null;
        $waterTrans->citizen_id         = $req['citizenId'] ?? null;
        $waterTrans->is_jsk             = $req['isJsk'] ?? false;
        $waterTrans->user_type          = $req['userType'];
        $waterTrans->ulb_id             = $req['ulbId'];
        $waterTrans->ward_id            = $consumer['ward_mstr_id'];
        $waterTrans->due_amount         = $req['leftDemandAmount'] ?? 0;
        $waterTrans->adjustment_amount  = $req['adjustedAmount'] ?? 0;
        $waterTrans->pg_response_id     = $req['pgResponseId'] ?? null;
        $waterTrans->pg_id              = $req['pgId'] ?? null;
        $waterTrans->tran_time          = $nowTime;
        $waterTrans->payment_type       = $req['partPayment'];
        $waterTrans->device_type        = $req['deviceType'] ?? null;
        if ($req->penaltyIds) {
            $waterTrans->penalty_ids    = $req->penaltyIds;
            $waterTrans->is_penalty     = $req->isPenalty;
        }
        $waterTrans->save();

        return [
            'id' => $waterTrans->id
        ];
    }

    /**
     * | Water Transaction Details by date
        | Not Used
     */
    public function tranDetail($date, $ulbId)
    {
        return WaterTran::select(
            'users.id',
            'users.user_name',
            DB::raw("sum(amount) as amount"),
        )
            ->join('users', 'users.id', 'water_trans.emp_dtl_id')
            ->where('verified_date', $date)
            ->where('water_trans.status', 1)
            ->where('payment_mode', '!=', 'ONLINE')
            ->where('verify_status', 1)
            ->where('water_trans.ulb_id', $ulbId)
            ->groupBy(["users.id", "users.user_name"]);
    }

    /**
     * | Get Transaction Details for current Date
     * | And for current login user
     */
    public function tranDetailByDate($currentDate, $userType, $rfTransMode)
    {
        return WaterTran::where('tran_date', $currentDate)
            ->where('user_type', $userType)
            ->whereIn('status', [1, 2])
            ->where('payment_mode', '!=', $rfTransMode)
            ->get();
    }

    /**
     * | Save the verify status in case of pending verification
     * | @param watertransId
     */
    public function saveVerifyStatus($watertransId)
    {
        WaterTran::where('id', $watertransId)
            ->update([
                'verify_status' => 2
            ]);
    }

    /**
     * | Get details of online transactions
     * | According to Fyear
     * | @param fromDate
     * | @param toDate
     */
    public function getOnlineTrans($fromDate, $toDate)
    {
        return WaterTran::select('id', 'amount')
            ->where('payment_mode', 'Online')
            ->where('status', 1)
            ->whereBetween('tran_date', [$fromDate, $toDate])
            ->orderByDesc('id');
    }

    /**
     * | Save if the payment is penalty or not 
     * | and the peanlty ids of it 
     */
    public function saveIsPenalty($transactionId, $penaltyIds)
    {
        WaterTran::where('id', $transactionId)
            ->where('status', 1)
            ->update([
                'is_penalty' => 1,
                'penalty_ids' => $penaltyIds
            ]);
    }
    /**
     * | Get details of Cash transactions
     * | According to Fyear
     * | @param fromDate
     * | @param toDate
     */
    public function getWaterReport($fromDate, $toDate, $wardId)
    {
        return WaterTran::select('amount', 'payment_mode', 'user_type')
            ->where('status', 1)
            // ->where('ward_id',$wardId)
            ->whereBetween('tran_date', [$fromDate, $toDate])
            ->orderByDesc('id');
    }
    /**
     * get details of tc visit
     */
    public function getDetailsOfTc($key, $refNo)
    {
        return WaterTran::select(
            'water_trans.*',
            'water_second_consumers.*',
            'water_consumer_demands.*',
            'users.user_name as TcName'
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_trans.related_id')
            ->join('water_second_consumers', 'water_trans.related_id', '=', 'water_second_consumers.id')
            ->join('water_consumer_demands', 'water_trans.related_id', '=', 'water_consumer_demands.consumer_id')
            ->leftjoin('water')
            ->leftjoin('users', 'water_trans.emp_dtl_id', '=', 'users.id')
            ->where('water_trans.' . $key, 'LIKE', '%' . $refNo . '%')
            ->where('water_trans.user_type', 'TC');
    }
    /**
     * tc name wise
     */
    public function getTcDetails($key, $refNo)
    {
        return WaterTran::select(
            'water_trans.*',
            'water_second_consumers.consumer_no',
            'water_consumer_demands.amount',
            'users.user_name'
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_trans.related_id')
            ->join('water_second_consumers', 'water_trans.related_id', '=', 'water_second_consumers.id')
            ->join('water_consumer_demands', 'water_trans.related_id', '=', 'water_consumer_demands.consumer_id')
            ->leftjoin('users', 'water_trans.emp_dtl_id', 'users.id')
            ->where('users.' . $key, 'LIKE', '%' . $refNo . '%')
            ->where('water_trans.user_type', 'TC');
    }
    /**
     * deactivate transaction 
     */
    public function updateTransatcion($transactionId, $updateData)
    {
        WaterTran::where('id', $transactionId)
            ->where('status', 1)
            ->update($updateData);
    }
    /**
     * | 
     */
    public function tranDtl($userId, $fromDate, $toDate)
    {
        return WaterTran::select(
            'water_trans.*',
            'water_consumer_owners.applicant_name as owner_name',
            'water_consumer_owners.guardian_name',
            'users.user_name',
            'users.name'
        )
            ->join('water_consumer_demands', 'water_consumer_demands.consumer_id', 'water_trans.related_id')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_trans.related_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_trans.related_id')
            ->join('users', 'users.id', 'water_trans.emp_dtl_id')
            ->where('water_trans.emp_dtl_id', $userId)
            ->where('water_trans.status', 1)
            ->whereBetween('tran_date', [$fromDate, $toDate])
            ->get();
    }
    /**
     * | Get water Transaction by Transaction No
     */
    public function getTransByTranNo($tranNo)
    {
        return WaterTran::select(
            'water_trans.id as transaction_id',
            'water_trans.tran_no as transaction_no',
            'water_trans.amount',
            'water_trans.payment_mode',
            'water_trans.tran_date',
            'water_trans.tran_type',
            DB::raw('2 as moduleId'),
                'water_trans.status'
            )
            ->where('water_trans.tran_no', $tranNo)
            ->where('status', 1)
            ->get();
    }

    public function getTranById($tranId)
    {
        return WaterTran::select(
            'water_trans.*',
            'water_cheque_dtls.bank_name',
            'water_cheque_dtls.branch_name',
            'water_cheque_dtls.cheque_no',
            'water_cheque_dtls.cheque_date',
            'u.name as tc_name',
            'u.mobile as tc_mobile',
            "water_cheque_dtls.status as cheque_status"
        )
            ->where('water_trans.id', $tranId)
            ->leftJoin("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")
            ->leftJoin("users as u", "u.id", "water_trans.emp_dtl_id")
            ->where('water_trans.status', 1)
            ->first();
    }
    public function getTranByTranNo($tranNo)
    {
        return WaterTran::select(
            'water_trans.*',
            'water_cheque_dtls.bank_name',
            'water_cheque_dtls.branch_name',
            'water_cheque_dtls.cheque_no',
            'water_cheque_dtls.cheque_date',
            'u.name as tc_name',
            'u.mobile as tc_mobile',
            "water_cheque_dtls.status as cheque_status"
        )
            ->where('water_trans.tran_no', $tranNo)
            ->leftJoin("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")
            ->leftJoin("users as u", "u.id", "water_trans.emp_dtl_id")
            ->where('water_trans.status', 1)
            ->first();
    }
}
