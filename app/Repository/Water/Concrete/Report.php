<?php

namespace App\Repository\Water\Concrete;

use App\Models\Advertisements\AdTran;
use App\Models\Water\WaterTran;
use App\Repository\Water\Interfaces\IReport;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Report implements IReport
{
    private $_docUrl;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct()
    {
        $this->_DB_NAME = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
        // $this->_DB->enableQueryLog();
        $this->_docUrl = Config::get("waterConstaint.DOC_URL");
    }

    public function tranDeactivatedList(Request $request)
    {
        try {
            $user = Auth()->user();
            $paymentMode = "";
            $fromDate = $toDate = Carbon::now()->format("Y-m-d");
            $wardId = $zoneId = $userId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $toDate = $request->uptoDate;
            }
            if ($request->paymentMode) {
                if (!is_array($request->paymentMode)) {
                    $paymentMode = Str::upper($request->paymentMode);
                } elseif (is_array($request->paymentMode[0])) {
                    foreach ($request->paymentMode as $val) {
                        $paymentMode .= Str::upper($val["value"]) . ",";
                    }
                    $paymentMode =  trim($paymentMode, ",");
                } else {

                    foreach ($request->paymentMode as $val) {
                        $paymentMode .= Str::upper($val) . ",";
                    }
                    $paymentMode =  trim($paymentMode, ",");
                }
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $data = WaterTran::select(DB::raw("
                        water_trans.id,water_trans.tran_no,
                        water_trans.tran_type,
                        water_trans.tran_date,
                        water_trans.payment_mode,
                        app.app_no,
                        ulb_ward_masters.ward_name,
                        zone_masters.zone_name,
                        users.name as tran_by_user_name,
                        water_trans.amount,
                        water_cheque_dtls.cheque_date,
                        water_cheque_dtls.bank_name,
                        water_cheque_dtls.branch_name,
                        water_cheque_dtls.cheque_no,
                        water_transaction_deactivate_dtls.reason,
                        water_transaction_deactivate_dtls.file_path,
                        water_transaction_deactivate_dtls.deactive_date,
                        users2.name as tran_deactivated_by
                    "))
                ->join(DB::raw("
                        (
                            (
                                select application.id as app_id ,application.application_no as app_no,water_trans.id as tran_id,
                                    application.ward_id AS ward_mstr_id, null::int as zone_mstr_id
                                from water_applications application
                                join water_trans on water_trans.related_id = application.id and upper(water_trans.tran_type) != upper('Demand Collection')
                                where water_trans.tran_date between '$fromDate' and '$toDate'
                            )
                            union ALL(
                                select application.id as app_id ,application.application_no as app_no,water_trans.id as tran_id,
                                    application.ward_id AS ward_mstr_id, null::int as zone_mstr_id
                                from water_rejection_application_details application
                                join water_trans on water_trans.related_id = application.id and upper(water_trans.tran_type) != upper('Demand Collection')
                                where water_trans.tran_date between '$fromDate' and '$toDate'
                            )
                            union ALL(
                                select application.id as app_id ,application.consumer_no as app_no,water_trans.id as tran_id,
                                    application.ward_mstr_id,zone_mstr_id
                                from water_second_consumers application
                                join water_trans on water_trans.related_id = application.id and upper(water_trans.tran_type) = upper('Demand Collection')
                                where water_trans.tran_date between '$fromDate' and '$toDate'
                            )
                        ) app
                    "), "app.tran_id", "water_trans.id")
                ->leftjoin("water_cheque_dtls", "water_cheque_dtls.transaction_id", "water_trans.id")
                ->leftjoin("water_transaction_deactivate_dtls", "water_transaction_deactivate_dtls.tran_id", "water_trans.id")
                ->leftjoin("users", "users.id", "water_trans.emp_dtl_id")
                ->leftjoin("users AS users2", "users2.id", "water_transaction_deactivate_dtls.deactivated_by")
                ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "app.ward_mstr_id")
                ->leftjoin("zone_masters", "zone_masters.id", "app.zone_mstr_id")
                ->where("water_trans.status", 0)
                ->whereBetween("water_trans.tran_date", [$fromDate, $toDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("zone_masters.id", $zoneId);
            }
            if ($userId) {
                $data = $data->where("water_trans.user_id", $userId);
            }
            if ($paymentMode) {

                $data = $data->whereIN(DB::raw("UPPER(water_trans.payment_mode)"), explode(",", $paymentMode));
            }

            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $data2 = $data;
            $totalAmount = $data2->sum("amount");
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalAmount" => $totalAmount,
                "data" => collect($paginator->items())->map(function ($val) {
                    $val->file =  trim($val->file_path) ? (Config::get('module-constants.DOC_URL') . "/" . $val->file_path) : "";
                    return $val;
                }),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }
    public function tranDeactivatedListAdvertisement(Request $request)
    {
        try {
            $user = Auth()->user();
            $paymentMode = "";
            $fromDate = $toDate = Carbon::now()->format("Y-m-d");
            $wardId = $zoneId = $userId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $toDate = $request->uptoDate;
            }
            if ($request->paymentMode) {
                if (!is_array($request->paymentMode)) {
                    $paymentMode = Str::upper($request->paymentMode);
                } elseif (is_array($request->paymentMode[0])) {
                    foreach ($request->paymentMode as $val) {
                        $paymentMode .= Str::upper($val["value"]) . ",";
                    }
                    $paymentMode =  trim($paymentMode, ",");
                } else {

                    foreach ($request->paymentMode as $val) {
                        $paymentMode .= Str::upper($val) . ",";
                    }
                    $paymentMode =  trim($paymentMode, ",");
                }
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $data = AdTran::select(DB::raw("
                        ad_trans.id,ad_trans.tran_no,
                        ad_trans.tran_type,
                        ad_trans.tran_date,
                        ad_trans.payment_mode,
                        app.app_no,
                        ulb_ward_masters.ward_name,
                        m_circle.circle_name as zone_name,
                        users.name as tran_by_user_name,
                        ad_trans.amount,
                        ad_cheque_dtls.cheque_date,
                        ad_cheque_dtls.bank_name,
                        ad_cheque_dtls.branch_name,
                        ad_cheque_dtls.cheque_no,
                        ad_transaction_deactivate_dtls.reason,
                        ad_transaction_deactivate_dtls.file_path,
                        ad_transaction_deactivate_dtls.deactive_date,
                        users2.name as tran_deactivated_by
                    "))
                    ->join(DB::raw("
                    (
                        (
                            select application.id as app_id ,application.application_no as app_no,ad_trans.id as tran_id,
                                application.ward_mstr_id AS ward_mstr_id, null::int as zone_mstr_id
                            from agency_hoardings application
                            join ad_trans on ad_trans.related_id = application.id 
                            where ad_trans.tran_date between '$fromDate' and '$toDate'
                        )
                        union ALL(
                            select application.id as app_id ,application.application_no as app_no,ad_trans.id as tran_id,
                                application.ward_mstr_id AS ward_mstr_id, null::int as zone_mstr_id
                            from agency_hoarding_rejected_applications application
                            join ad_trans on ad_trans.related_id = application.id 
                            where ad_trans.tran_date between '$fromDate' and '$toDate'
                        )
                        union ALL(
                            select application.id as app_id ,application.application_no as app_no,ad_trans.id as tran_id,
                                application.ward_mstr_id,zone_mstr_id
                            from agency_hoarding_approve_applications application
                            join ad_trans on ad_trans.related_id = application.id 
                            where ad_trans.tran_date between '$fromDate' and '$toDate'
                        )
                    ) app
                "), "app.tran_id", "ad_trans.id")
                ->leftjoin("ad_cheque_dtls", "ad_cheque_dtls.transaction_id", "ad_trans.id")
                ->leftjoin("ad_transaction_deactivate_dtls", "ad_transaction_deactivate_dtls.tran_id", "ad_trans.id")
                ->leftjoin("users", "users.id", "ad_trans.emp_dtl_id")
                ->leftjoin("users AS users2", "users2.id", "ad_transaction_deactivate_dtls.deactivated_by")
                ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "app.ward_mstr_id")
                ->leftjoin("m_circle", "m_circle.id", "app.zone_mstr_id")
                ->where("ad_trans.status", 0)
                ->whereBetween("ad_trans.tran_date", [$fromDate, $toDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("zone_masters.id", $zoneId);
            }
            if ($userId) {
                $data = $data->where("ad_trans.emp_dtl_id", $userId);
            }
            if ($paymentMode) {

                $data = $data->whereIN(DB::raw("UPPER(ad_trans.payment_mode)"), explode(",", $paymentMode));
            }

            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $data2 = $data;
            $totalAmount = $data2->sum("amount");
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalAmount" => $totalAmount,
                "data" => collect($paginator->items())->map(function ($val) {
                    $val->file =  trim($val->file_path) ? (Config::get('module-constants.DOC_URL') . "/" . $val->file_path) : "";
                    return $val;
                }),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }
}
