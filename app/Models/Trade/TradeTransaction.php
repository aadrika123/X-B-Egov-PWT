<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TradeTransaction extends TradeParamModel    #Model
{
    use HasFactory;
    protected $connection;
    public $timestamps=false;

    public function __construct($DB=null)
    {
        parent::__construct($DB);
    }

    public static function listByLicId($licenseId)
    {
        return self::select("*")
            ->where("temp_id", $licenseId)
            ->whereIn("status", [1, 2])
            ->get();
    }

    public static function chequeTranDtl($ulbId)
    {
        return  TradeTransaction::select(
            'trade_cheque_dtls.*',
            DB::raw("TO_CHAR(tran_date, 'DD-MM-YYYY') as tran_date"),
            'tran_type',
            DB::raw("3 as module_id"),
            'tran_no',
            'payment_mode',
            'paid_amount as amount',
            DB::raw("TO_CHAR(cheque_date, 'DD-MM-YYYY') as cheque_date"),
            "bank_name",
            "branch_name",
            "trade_cheque_dtls.status",
            "cheque_no",
            DB::raw("TO_CHAR(clear_bounce_date, 'DD-MM-YYYY') as clear_bounce_date"),
            "users.name as user_name"
        )
            ->leftjoin('trade_cheque_dtls', 'trade_cheque_dtls.tran_id', 'trade_transactions.id')
            ->join('users', 'users.id', 'trade_cheque_dtls.emp_dtl_id')
            ->whereIn('payment_mode', ['CHEQUE', 'DD'])
            ->where('trade_transactions.status', 1)
            ->where('trade_transactions.ulb_id', $ulbId);
    }

    /**
     * | Trade Transaction Details by date
     */
    public function tranDetail($date, $ulbId)
    {
        return TradeTransaction::select(
            'users.id',
            'users.user_name',
            DB::raw("sum(paid_amount) as amount"),
        )
            ->join('users', 'users.id', 'trade_transactions.emp_dtl_id')
            ->where('verify_date', $date)
            ->where('trade_transactions.status', 1)
            ->where('payment_mode', '!=', 'ONLINE')
            ->where('is_verified', true)
            ->where('trade_transactions.ulb_id', $ulbId)
            ->groupBy(["users.id", "users.user_name"]);
    }

    public function chequeDtl()
    {
        return $this->belongsTo(TradeChequeDtl::class, "id", 'tran_id')->whereNotIn("status", [0, 3]);
    }
}
