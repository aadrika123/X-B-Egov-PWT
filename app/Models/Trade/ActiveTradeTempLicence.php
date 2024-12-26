<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ActiveTradeTempLicence extends TradeParamModel
{
    use HasFactory;
    protected $connection;
    public function __construct($DB = null)
    {
        parent::__construct($DB);
    }

    /**
     * | Get application details by Id
     */
    public function getApplicationDtls($appId)
    {
        return self::select('*')
            ->where('id', $appId)
            ->first();
    }
    public function getByItsDetailsV2()
    {
        return self::select(
            "active_trade_temp_licences.id",
        //     DB::raw("CASE 
        //     WHEN approve = 0 THEN 'Pending'
        //     WHEN approve = 1 THEN 'Approved'
        //     WHEN approve = 2 THEN 'Rejected'
        //     ELSE 'Unknown Status'
        //   END AS approval_status"),
        //     DB::raw("CASE 
        //     WHEN active_trade_temp_licences.current_role = 6 THEN 'AT LIPIK'
        //     WHEN active_trade_temp_licences.current_role = 13 THEN 'AT SECTION INCHARGE'
        //     WHEN active_trade_temp_licences.current_role = 10 THEN 'AT TAX  SUPRERINTENDENT'
        //     ELSE 'Unknown Role'
        // END AS application_at"),
            "active_trade_temp_licences.payment_status"
        )
            // ->join('wf_roles', 'wf_roles.id', '=', 'active_trade_temp_licences.current_role')
            // ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'active_trade_temp_licences.ward_id')
            // ->join('ulb_masters', 'ulb_masters.id', '=', 'active_trade_temp_licences.ulb_id')
            // ->where('active_trade_temp_licences.status', 1)
            ->orderBy('active_trade_temp_licences.id', 'desc');
    }
}
