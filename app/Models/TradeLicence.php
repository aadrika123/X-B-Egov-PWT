<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TradeLicence extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_trade';
    public function getLicenceByUserId($user_id)
    {
        $licenceList = array();
        $licenceList = DB::table('trade_licences as t1')
            ->select('t1.id', 't1.license_no','t1.holding_no','t1.valid_from','t2.user_name as applicant_name')
            ->leftJoin('users as t2','t1.user_id','=',"t2.id")
            ->where('t1.user_id', $user_id)
            ->get();
        return $licenceList;
    }

    public function getLicenceByHoldingNo($holding_no)
    {
        $licenceList = array();
        $licenceList = DB::table('trade_licences as t1')
            ->select('t1.id', 't1.license_no','t1.holding_no','t1.valid_from','t2.user_name as applicant_name')
            ->leftJoin('users as t2','t1.user_id','=',"t2.id")
            ->where('t1.holding_no', $holding_no)
            ->get();
        return $licenceList;
    }

    public function getDetailsByLicenceNo($license_no)
    {
        $details = array();
        $details = DB::table('trade_licences as t1')
            ->select('t1.application_no','t1.application_date','t1.license_no','t1.valid_from','t1.valid_upto','t1.licence_for_years','t1.firm_name','t1.premises_owner_name','t1.landmark','t1.pin_code','t1.holding_no','t1.ward_id','t2.user_name as applicant_name','t2.mobile','t3.ward_name as entity_ward_no','t1.firm_name as entity_name','t1.address as entity_address')
            ->leftJoin('users as t2','t1.user_id','=','t2.id')
            ->leftJoin('ulb_ward_masters as t3','t1.ward_id','=','t3.id')
            ->where('t1.license_no', $license_no)
            ->first();
        return $details;
    }

    public function getDetailsByLicenceNov2($license_no)
    {
        //$details = array();
        $details = DB::connection($this->connection)->table('trade_licences as t1')
            ->select('t1.*','t2.user_name as applicant_name','t2.mobile','t3.ward_name as entity_ward_no','t1.firm_name as entity_name','t1.address as entity_address')
            ->leftJoin('users as t2','t1.user_id','=','t2.id')
            ->leftJoin('ulb_ward_masters as t3','t1.ward_id','=','t3.id')
            ->where('t1.license_no', $license_no)
            ->first();
        return $details;
    }
}
