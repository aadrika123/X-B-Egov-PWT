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
            "active_trade_temp_licences.application_no",
            "ulb_ward_masters.ward_name as ward_no",
            "zone_masters.zone_name as zone",
            "active_trade_temp_licences.application_date",
            "active_trade_temp_licences.firm_name",
            "active_trade_temp_licences.premises_owner_name",
            //     DB::raw("CASE 
            //     WHEN approve = 0 THEN 'Pending'
            //     WHEN approve = 1 THEN 'Approved'
            //     WHEN approve = 2 THEN 'Rejected'
            //     ELSE 'Unknown Status'
            //   END AS approval_status"),
            DB::raw("CASE 
                WHEN active_trade_temp_licences.current_role = 6 THEN 'AT LIPIK'
                WHEN active_trade_temp_licences.current_role = 11 THEN 'AT Back Office'
                WHEN active_trade_temp_licences.current_role = 13 THEN 'AT Section Head',
                WHEN active_trade_temp_licences.current_role = 10 THEN 'AT TAX SUPERITENDENT',
                ELSE 'Unknown Role'
            END AS application_at"),
            DB::raw("string_agg(active_temp_trade_owners.owner_name,',') as applicant_name"),
            DB::raw("string_agg(active_temp_trade_owners.mobile_no::VARCHAR,',') as mobile_no"),
            DB::raw("string_agg(active_temp_trade_owners.guardian_name,',') as guardian_name"),
            DB::raw("string_agg(active_temp_trade_owners.owner_name_marathi,',') as owner_name_marathi"),
            DB::raw("string_agg(active_temp_trade_owners.guardian_name_marathi,',') as guardian_name_marathi"),
            "active_trade_temp_licences.payment_status"
        )
            ->join('active_temp_trade_owners', 'active_temp_trade_owners.temp_id', '=', 'active_trade_temp_licences.id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'active_trade_temp_licences.ward_id')
            ->join('zone_masters', 'zone_masters.id', '=', 'active_trade_temp_licences.zone_id')
            ->where('active_trade_temp_licences.status', 1)
            ->orderBy('active_trade_temp_licences.id', 'desc')
            ->groupby(
                'active_trade_temp_licences.id',
                'active_trade_temp_licences.application_no',
                'ulb_ward_masters.ward_name',
                'zone_masters.zone_name',
                'active_trade_temp_licences.application_date',
                'active_trade_temp_licences.firm_name',
                'active_trade_temp_licences.premises_owner_name',
                'active_trade_temp_licences.current_role',
                'active_trade_temp_licences.payment_status'
            );
    }
}
