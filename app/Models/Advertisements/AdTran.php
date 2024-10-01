<?php

namespace App\Models\Advertisements;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdTran extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = 'pgsql_advertisements';
    public static function chequeTranDtl($ulbId)
    {
        return  AdTran::select(
            'ad_cheque_dtls.*',
            DB::raw("TO_CHAR(tran_date, 'DD-MM-YYYY') as tran_date"),
            'tran_type',
            DB::raw("14 as module_id"),
            'tran_no',
            'payment_mode',
            'amount',
            DB::raw("TO_CHAR(cheque_date, 'DD-MM-YYYY') as cheque_date"),
            "bank_name",
            "branch_name",
            "ad_cheque_dtls.status",
            "cheque_no",
            DB::raw("TO_CHAR(clear_bounce_date, 'DD-MM-YYYY') as clear_bounce_date"),
            "users.name as user_name"
        )
            ->leftjoin('ad_cheque_dtls', 'ad_cheque_dtls.transaction_id', 'ad_trans.id')
            ->join('users', 'users.id', 'ad_cheque_dtls.user_id')
            ->whereIn('payment_mode', ['CHEQUE', 'DD'])
            ->where('ad_trans.status', 1)
            ->where('ad_trans.ulb_id', $ulbId);
    }
}
