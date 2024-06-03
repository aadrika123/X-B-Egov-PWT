<?php

namespace App\Repository\Trade;

use App\EloquentModels\Common\ModelWard;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\ActiveTradeNoticeConsumerDtl;
use App\Models\Trade\RejectedTradeLicence;
use App\Models\Trade\RejectedTradeNoticeConsumerDtl;
use App\Models\Trade\TradeLicence;
use App\Models\Trade\TradeNoticeConsumerDtl;
use App\Models\Trade\TradeRenewal;
use App\Models\Trade\TradeTransaction;
use App\Models\UlbWardMaster;
use App\Models\Workflows\WfRole;
use App\Models\Workflows\WfTrack;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Common\CommonFunction;
use App\Traits\Auth;
use Carbon\Carbon;
use App\Traits\Workflow\Workflow;
use App\Traits\Trade\TradeTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class Report implements IReport
{
    use Auth;
    use Workflow;
    use TradeTrait;

    protected $_DB;
    protected $_DB_NAME;
    protected $_NOTICE_DB;
    protected $_NOTICE_DB_NAME;
    protected $_DB_READ;

    protected $_MODEL_WARD;
    protected $_COMMON_FUNCTION;
    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;
    public function __construct()
    {
        $this->_DB_NAME = "pgsql_trade";
        $this->_NOTICE_DB = "pgsql_notice";
        $this->_DB_READ = DB::connection($this->_DB_NAME . "::read");
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_NOTICE_DB = DB::connection($this->_NOTICE_DB);
        DB::enableQueryLog();
        // $this->_DB->enableQueryLog();
        // $this->_NOTICE_DB->enableQueryLog();

        $this->_COMMON_FUNCTION = new CommonFunction();
        $this->_MODEL_WARD = new ModelWard();

        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];
    }

    public function CollectionReports(Request $request)
    {

        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id ?? 0;
            $ulbId          = $refUser->ulb_id ?? 2;
            $wardId = null;
            $userId = null;
            $zoneId = null;
            $paymentMode = $key = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            $where = "WHERE trade_transactions.status IN(1,2) AND trade_transactions.tran_date between '$fromDate' AND '$uptoDate' ";
            if ($request->wardId) {
                $wardId = $request->wardId;
                $where .= " AND trade_transactions.ward_id =  $wardId ";
            }
            if ($request->userId) {
                $userId = $request->userId;
                $where .= " AND trade_transactions.emp_dtl_id =  $userId ";
            }
            if ($request->paymentMode) {
                $paymentMode = strtoupper($request->paymentMode);
                $where .= " AND upper(trade_transactions.payment_mode) =  upper('$paymentMode') ";
            }
            if (trim($request->key)) {
                $key = strtoupper($request->key);
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $where .= " AND trade_transactions.ulb_id =  $ulbId ";
            $select = [
                DB::raw("   
                            trade_transactions.id as tran_id,
                            trade_transactions.temp_id as license_id,
                            ulb_ward_masters.ward_name AS ward_no,
                            licences.application_no AS application_no,
                            licences.zone_id AS zone_id,
                            application_type,
                            zone_masters.zone_name AS zone,
                            (
                                CASE WHEN licences.license_no='' OR licences.license_no IS NULL THEN 'N/A' 
                                ELSE licences.license_no END
                            ) AS license_no,
                            owner_detail.owner_name,
                            owner_detail.mobile_no,
                            licences.firm_name AS firm_name,
                            TO_CHAR(trade_transactions.tran_date, 'DD-MM-YYYY') as tran_date ,
                            trade_transactions.payment_mode AS transaction_mode,
                            trade_transactions.paid_amount,
                            (
                                CASE WHEN upper(trade_transactions.payment_mode) NOT IN('ONLINE','ONL') THEN users.name 
                                ELSE 'N/A' END
                            ) AS emp_name,
                            trade_transactions.tran_no,
                            (
                                CASE WHEN trade_cheque_dtls.cheque_no IS NULL THEN 'N/A' 
                                ELSE trade_cheque_dtls.cheque_no END
                            ) AS cheque_no,
                            (
                                CASE WHEN trade_cheque_dtls.bank_name IS NULL THEN 'N/A' 
                                ELSE trade_cheque_dtls.bank_name END
                            ) AS bank_name,
                            (
                                CASE WHEN trade_cheque_dtls.branch_name IS NULL THEN 'N/A' 
                                ELSE trade_cheque_dtls.branch_name END
                            ) AS branch_name
                "),
            ];
            $active = TradeTransaction::readConnection()->select($select)
                ->JOIN("active_trade_licences AS licences", "licences.id", "trade_transactions.temp_id")
                ->JOIN('trade_param_application_types as a', 'a.id', 'licences.application_type_id')
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->JOIN("zone_masters", "zone_masters.id", "licences.zone_id")
                ->LEFTJOIN("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->LEFTJOIN("users", "users.id", "trade_transactions.emp_dtl_id")
                ->LEFTJOIN(DB::RAW("(
                        SELECT DISTINCT(active_trade_owners.temp_id) AS temp_id,
                            STRING_AGG(owner_name,',') AS  owner_name,
                            STRING_AGG(mobile_no::TEXT,',') AS  mobile_no
                        FROM  active_trade_owners
                        JOIN trade_transactions ON trade_transactions.temp_id = active_trade_owners.temp_id
                        $where
                        GROUP BY active_trade_owners.temp_id
                        ) AS owner_detail"), function ($join) {
                    $join->on("owner_detail.temp_id", "trade_transactions.temp_id");
                })
                ->WHEREIN("trade_transactions.status", [1, 2])
                ->WHEREBETWEEN("trade_transactions.tran_date", [$fromDate, $uptoDate]);

            $approved = TradeTransaction::readConnection()->select($select)
                ->JOIN("trade_licences AS licences", "licences.id", "trade_transactions.temp_id")
                ->JOIN('trade_param_application_types as a', 'a.id', 'licences.application_type_id')
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->JOIN("zone_masters", "zone_masters.id", "licences.zone_id")

                ->LEFTJOIN("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->LEFTJOIN("users", "users.id", "trade_transactions.emp_dtl_id")
                ->LEFTJOIN(DB::RAW("(
                    SELECT DISTINCT(trade_owners.temp_id) AS temp_id,
                        STRING_AGG(owner_name,',') AS  owner_name,
                        STRING_AGG(mobile_no::TEXT,',') AS  mobile_no
                    FROM  trade_owners
                    JOIN trade_transactions ON trade_transactions.temp_id = trade_owners.temp_id
                    $where
                    GROUP BY trade_owners.temp_id
                    ) AS owner_detail"), function ($join) {
                    $join->on("owner_detail.temp_id", "trade_transactions.temp_id");
                })
                ->WHEREIN("trade_transactions.status", [1, 2])
                ->WHEREBETWEEN("trade_transactions.tran_date", [$fromDate, $uptoDate]);

            $rejected = TradeTransaction::readConnection()->select($select)
                ->JOIN("rejected_trade_licences AS licences", "licences.id", "trade_transactions.temp_id")
                ->JOIN('trade_param_application_types as a', 'a.id', 'licences.application_type_id')
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->JOIN("zone_masters", "zone_masters.id", "licences.zone_id")

                ->LEFTJOIN("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->LEFTJOIN("users", "users.id", "trade_transactions.emp_dtl_id")
                ->LEFTJOIN(DB::RAW("(
                    SELECT DISTINCT(rejected_trade_owners.temp_id) AS temp_id,
                        STRING_AGG(owner_name,',') AS  owner_name,
                        STRING_AGG(mobile_no::TEXT,',') AS  mobile_no
                    FROM  rejected_trade_owners
                    JOIN trade_transactions ON trade_transactions.temp_id = rejected_trade_owners.temp_id
                    $where
                    GROUP BY rejected_trade_owners.temp_id
                    ) AS owner_detail"), function ($join) {
                    $join->on("owner_detail.temp_id", "trade_transactions.temp_id");
                })
                ->WHEREIN("trade_transactions.status", [1, 2])
                ->WHEREBETWEEN("trade_transactions.tran_date", [$fromDate, $uptoDate]);
            $old = TradeTransaction::readConnection()->select($select)
                ->JOIN("trade_renewals AS licences", "licences.id", "trade_transactions.temp_id")
                ->JOIN('trade_param_application_types as a', 'a.id', 'licences.application_type_id')
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->JOIN("zone_masters", "zone_masters.id", "licences.zone_id")

                ->LEFTJOIN("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->LEFTJOIN("users", "users.id", "trade_transactions.emp_dtl_id")
                ->LEFTJOIN(DB::RAW("(
                    SELECT DISTINCT(trade_owners.temp_id) AS temp_id,
                        STRING_AGG(owner_name,',') AS  owner_name,
                        STRING_AGG(mobile_no::TEXT,',') AS  mobile_no
                    FROM  trade_owners
                    JOIN trade_transactions ON trade_transactions.temp_id = trade_owners.temp_id
                    $where
                    GROUP BY trade_owners.temp_id
                    ) AS owner_detail"), function ($join) {
                    $join->on("owner_detail.temp_id", "trade_transactions.temp_id");
                })
                ->WHEREIN("trade_transactions.status", [1, 2])
                ->WHEREBETWEEN("trade_transactions.tran_date", [$fromDate, $uptoDate]);;

            if ($wardId) {
                $active = $active->where("ulb_ward_masters.id", $wardId);
                $approved = $approved->where("ulb_ward_masters.id", $wardId);
                $rejected = $rejected->where("ulb_ward_masters.id", $wardId);
                $old = $old->where("ulb_ward_masters.id", $wardId);
            }
            if ($userId) {
                $active = $active->where("trade_transactions.emp_dtl_id", $userId);
                $approved = $approved->where("trade_transactions.emp_dtl_id", $userId);
                $rejected = $rejected->where("trade_transactions.emp_dtl_id", $userId);
                $old = $old->where("trade_transactions.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $active = $active->where(DB::raw("upper(trade_transactions.payment_mode)"), $paymentMode);
                $approved = $approved->where(DB::raw("upper(trade_transactions.payment_mode)"), $paymentMode);
                $rejected = $rejected->where(DB::raw("upper(trade_transactions.payment_mode)"), $paymentMode);
                $old = $old->where(DB::raw("upper(trade_transactions.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $active = $active->where("trade_transactions.ulb_id", $ulbId);
                $approved = $approved->where("trade_transactions.ulb_id", $ulbId);
                $rejected = $rejected->where("trade_transactions.ulb_id", $ulbId);
                $old = $old->where("trade_transactions.ulb_id", $ulbId);
            }
            if (trim($key)) {
                $active = $active->where(function ($query) use ($key) {
                    $query->orwhere('trade_transactions.tran_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.firm_name", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner_detail.mobile_no', 'ILIKE', '%' . $key . '%');
                });

                $approved = $approved->where(function ($query) use ($key) {
                    $query->orwhere('trade_transactions.tran_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.firm_name", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner_detail.mobile_no', 'ILIKE', '%' . $key . '%');
                });

                $rejected = $rejected->where(function ($query) use ($key) {
                    $query->orwhere('trade_transactions.tran_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.firm_name", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner_detail.mobile_no', 'ILIKE', '%' . $key . '%');
                });

                $old = $old->where(function ($query) use ($key) {
                    $query->orwhere('trade_transactions.tran_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.firm_name", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner_detail.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            $data = $active->union($approved)
                ->union($rejected)
                ->union($old);
            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function teamSummary(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if ($request->paymentMode) {
                $paymentMode = strtoupper($request->paymentMode);
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $data = TradeTransaction::readConnection()
                ->select(
                    DB::raw("sum(trade_transactions.paid_amount) as amount, 
                            count(trade_transactions.id) total_no,
                            users.id as user_id, 
                            case when users.id  is null then 'Online' else users.name 
                            end as user_name ")
                )
                ->LEFTJOIN("users", function ($join) {
                    $join->on("users.id", "=", "trade_transactions.emp_dtl_id")
                        ->whereNotIn(DB::raw("upper(trade_transactions.payment_mode)"), ["ONLINE", "ONL"]);
                })
                ->whereIN("trade_transactions.status", [1, 2])
                ->WHEREBETWEEN("trade_transactions.tran_date", [$fromDate, $uptoDate]);
            if ($wardId) {
                $data = $data->where("trade_transactions.ward_id", $wardId);
            }
            if ($userId) {
                $data = $data->where("trade_transactions.emp_dtl_id", $userId);
            }
            if ($paymentMode) {
                $data = $data->where(DB::raw("upper(trade_transactions.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $data = $data->where("trade_transactions.ulb_id", $ulbId);
            }
            $data = $data->groupBy(["users.id", "users.name"]);

            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function valideAndExpired(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $uptoDate = Carbon::now()->format("Y-m-d");
            $licenseNo = null;
            $oprater = null;

            if ($request->licenseNo) {
                $licenseNo = $request->licenseNo;
            }
            if (strtoupper($request->licenseStatus) == "EXPIRED") {
                $oprater = "<";
            }
            if (strtoupper($request->licenseStatus) == "VALID") {
                $oprater = ">=";
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $select = [
                "licences.id",
                "licences.ward_id",
                "licences.application_type_id",
                "application_type",
                "licences.ulb_id",
                "licences.application_no",
                "licences.holding_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.firm_name",
                'zone_masters.zone_name AS zone',
                "trade_param_firm_types.firm_type",
                "licences.firm_type_id",
                "owner.owner_name",
                "owner.mobile_no",
                "owner.address",
                
                DB::raw("ulb_ward_masters.ward_name as ward_no,
                ulb_masters.ulb_name,
                TO_CHAR( CAST(licences.valid_from AS DATE), 'DD-MM-YYYY') as valid_from ,
                TO_CHAR( CAST(licences.valid_upto AS DATE), 'DD-MM-YYYY') as valid_upto,
                TO_CHAR( CAST(licences.application_date AS DATE), 'DD-MM-YYYY') as application_date,
                TO_CHAR( CAST(licences.license_date AS DATE), 'DD-MM-YYYY') as license_date
            ")
            ];
            $select1 = $select;
            $select1[] = DB::raw("'approve' as type ");
            $data = $this->_DB_READ->TABLE("trade_licences AS licences")
                ->select($select1)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftJOIN("zone_masters", "zone_masters.id", "licences.zone_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "licences.firm_type_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                trade_owners.temp_id,
                                STRING_AGG(address,',') AS address
                                FROM trade_owners 
                                WHERE trade_owners.is_active = true
                                GROUP BY trade_owners.temp_id
                                ) owner"), "owner.temp_id", "=", "licences.id");

            if ($oprater) {
                $data = $data->where("licences.valid_upto", $oprater, $uptoDate);
            }
            if (strtoupper($request->licenseStatus) == "TO BE EXPIRED") {
                $oprater = true;
                $data = $data->whereBetween("licences.valid_upto", [$uptoDate, (new Carbon($uptoDate))->addDays(30)->format("Y-m-d")])
                    ->where("licences.is_active", true);
            }
            if ($wardId) {
                $data = $data->where("licences.ward_id", $wardId);
            }
            if ($ulbId) {
                $data = $data->where("licences.ulb_id", $ulbId);
            }
            if ($licenseNo) {
                if ($ulbId) {
                    $data = $data->where('licences.license_no', 'ILIKE', "%" . $licenseNo . "%");
                } else {
                    $data = $data->where('licences.license_no', 'ILIKE', "" . $licenseNo . "");
                }
            }
            if ((!$oprater) && $licenseNo) {
                $select2 = $select;
                $select2[] = DB::raw("'old' as type ");
                $old = $this->_DB_READ->TABLE("trade_renewals AS licences")
                    ->select($select2)
                    ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                    ->leftJOIN("zone_masters", "zone_masters.id", "licences.zone_id")
                    ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                    ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                    ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "licences.firm_type_id")
                    ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                trade_owners.temp_id,STRING_AGG(address,',') AS address
                                FROM trade_owners 
                                WHERE trade_owners.is_active = true
                                GROUP BY trade_owners.temp_id
                                ) owner"), "owner.temp_id", "=", "licences.id");
                if ($ulbId) {
                    $old = $old->where('licences.license_no', 'ILIKE', "%" . $licenseNo . "%");
                } else {
                    $old = $old->where('licences.license_no', 'ILIKE', "" . $licenseNo . "");
                }
                if ($wardId) {
                    $old = $old->where("licences.ward_id", $wardId);
                }
                if ($ulbId) {
                    $old = $old->where("licences.ulb_id", $ulbId);
                }
                $data = $data->union($old)->orderBy("application_date");
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }


    public function valideAndExpired1(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $uptoDate = Carbon::now()->format("Y-m-d");
            $licenseNo = null;
            $oprater = null;

            if ($request->licenseNo) {
                $licenseNo = $request->licenseNo;
            }
            if (strtoupper($request->licenseStatus) == "EXPIRED") {
                $oprater = "<";
            }
            if (strtoupper($request->licenseStatus) == "VALID") {
                $oprater = ">=";
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $select = [
                "licences.id",
                "licences.ward_id",
                "licences.application_type_id",
                "application_type",
                "licences.ulb_id",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.firm_name",
                'zone_masters.zone_name AS zone',
                "trade_param_firm_types.firm_type",
                "licences.firm_type_id",
                DB::raw("ulb_ward_masters.ward_name as ward_no,
                ulb_masters.ulb_name,
                TO_CHAR( CAST(licences.valid_from AS DATE), 'DD-MM-YYYY') as valid_from ,
                TO_CHAR( CAST(licences.valid_upto AS DATE), 'DD-MM-YYYY') as valid_upto,
                TO_CHAR( CAST(licences.application_date AS DATE), 'DD-MM-YYYY') as application_date,
                TO_CHAR( CAST(licences.license_date AS DATE), 'DD-MM-YYYY') as license_date,
                STRING_AGG(trade_owners.owner_name, ', ') AS owner_name,
                STRING_AGG(trade_owners.mobile_no::TEXT, ', ') AS mobile_no,
                STRING_AGG(trade_owners.address, ', ') AS address")
            ];

            $data = $this->_DB_READ->TABLE("trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftJOIN("zone_masters", "zone_masters.id", "licences.zone_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "licences.firm_type_id")
                ->leftjoin("trade_owners", "trade_owners.temp_id", "licences.id")
                ->where("trade_owners.is_active", true)
                ->groupBy([
                    "licences.id",
                    "zone_masters.zone_name",
                    "trade_param_firm_types.firm_type",
                    "ulb_ward_masters.ward_name",
                    "ulb_masters.ulb_name"
                ]);

            if ($oprater) {
                $data = $data->where("licences.valid_upto", $oprater, $uptoDate);
            }
            if (strtoupper($request->licenseStatus) == "TO BE EXPIRED") {
                $oprater = true;
                $data = $data->whereBetween("licences.valid_upto", [$uptoDate, (new Carbon($uptoDate))->addDays(30)->format("Y-m-d")])
                    ->where("licences.is_active", true);
            }
            if ($wardId) {
                $data = $data->where("licences.ward_id", $wardId);
            }
            if ($ulbId) {
                $data = $data->where("licences.ulb_id", $ulbId);
            }
            if ($licenseNo) {
                if ($ulbId) {
                    $data = $data->where('licences.license_no', 'ILIKE', "%" . $licenseNo . "%");
                } else {
                    $data = $data->where('licences.license_no', 'ILIKE', "" . $licenseNo . "");
                }
            }
            if ((!$oprater) && $licenseNo) {
                $select2 = $select;
                $select2[] = DB::raw("'old' as type ");
                $old = $this->_DB_READ->TABLE("trade_renewals AS licences")
                    ->select($select2)
                    ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                    ->leftJOIN("zone_masters", "zone_masters.id", "licences.zone_id")
                    ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                    ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                    ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "licences.firm_type_id")
                    ->leftjoin("rejected_trade_owners", "rejected_trade_owners.temp_id", "licences.id")
                    ->where("trade_owners.is_active", true)
                    ->groupBy([
                        "licences.id",
                        "zone_masters.zone_name",
                        "trade_param_firm_types.firm_type",
                        "ulb_ward_masters.ward_name",
                        "ulb_masters.ulb_name"
                    ]);

                if ($ulbId) {
                    $old = $old->where('licences.license_no', 'ILIKE', "%" . $licenseNo . "%");
                } else {
                    $old = $old->where('licences.license_no', 'ILIKE', "" . $licenseNo . "");
                }
                if ($wardId) {
                    $old = $old->where("licences.ward_id", $wardId);
                }
                if ($ulbId) {
                    $old = $old->where("licences.ulb_id", $ulbId);
                }
                $data = $data->union($old)->orderBy("application_date");
            }

            $perPage = $request->perPage ? $request->perPage :  10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }


    public function CollectionSummary(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            $where = " AND trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate' ";
            if ($request->wardId) {
                $wardId = $request->wardId;
                $where .= " AND trade_transactions.ward_id = $wardId ";
            }
            if ($request->userId) {
                $userId = $request->userId;
                $where .= " AND trade_transactions.emp_dtl_id = $userId ";
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $where .= " AND trade_transactions.ulb_id = $ulbId ";
            $total_transacton = $this->_DB_READ->select("
                            SELECT
                                sum(COALESCE(trade_transactions.paid_amount,0)) as amount, 
                                count(trade_transactions.id) as total_transaction,
                                count(trade_transactions.temp_id) as total_consumer,
                                payment_modes.mode as payment_mode
                            FROM ( 
                                    SELECT distinct(upper(payment_mode)) as mode
                                    FROM trade_transactions 
                                ) payment_modes
                            LEFT JOIN trade_transactions ON upper(trade_transactions.payment_mode) = upper(payment_modes.mode)
                                $where
                            GROUP BY  payment_modes.mode
                            ");
            $deactivate_transacton = $this->_DB_READ->select("
                            SELECT
                                sum(COALESCE(trade_transactions.paid_amount,0)) as amount, 
                                count(trade_transactions.id) as total_transaction,
                                count(trade_transactions.temp_id) as total_consumer,
                                payment_modes.mode as payment_mode
                            FROM ( 
                                    SELECT distinct(upper(payment_mode)) as mode
                                    FROM trade_transactions 
                                ) payment_modes
                            LEFT JOIN trade_transactions ON upper(trade_transactions.payment_mode) = upper(payment_modes.mode)
                                $where AND trade_transactions.status IN(0,3)
                            GROUP BY  payment_modes.mode
                            ");
            $actual_transacton = $this->_DB_READ->select("
                            SELECT
                                sum(COALESCE(trade_transactions.paid_amount,0)) as amount, 
                                count(trade_transactions.id) as total_transaction,
                                count(trade_transactions.temp_id) as total_consumer,
                                payment_modes.mode as payment_mode
                            FROM ( 
                                    SELECT distinct(upper(payment_mode)) as mode
                                    FROM trade_transactions 
                                ) payment_modes
                            LEFT JOIN trade_transactions ON upper(trade_transactions.payment_mode) = upper(payment_modes.mode)
                                $where AND trade_transactions.status IN(1,2)
                            GROUP BY  payment_modes.mode
                            ");
            $application_type_transacton = $this->_DB_READ->select("
                            SELECT
                                sum(COALESCE(trade_transactions.paid_amount,0)) as amount, 
                                count(trade_transactions.id) as total_transaction,
                                count(trade_transactions.temp_id) as total_consumer,
                                initcap(trade_param_application_types.application_type) as application_type ,
                                trade_param_application_types.id 
                            FROM trade_param_application_types
                            LEFT JOIN trade_transactions ON trade_transactions.tran_type =  trade_param_application_types.application_type 
                                $where AND trade_transactions.status IN(1,2)
                            GROUP BY  trade_param_application_types.application_type ,trade_param_application_types.id
                            ORDER BY trade_param_application_types.id
                            ");
            $total_transacton = collect($total_transacton);
            $deactivate_transacton = collect($deactivate_transacton);
            $actual_transacton = collect($actual_transacton);
            $application_type_transacton = collect($application_type_transacton);

            $total_collection_amount = $total_transacton->sum("amount");
            $total_collection_consumer = $total_transacton->sum("total_consumer");
            $total_collection_transection = $total_transacton->sum("total_transaction");

            $deactivate_collection_amount = $deactivate_transacton->sum("amount");
            $deactivate_collection_consumer = $deactivate_transacton->sum("total_consumer");
            $deactivate_collection_transection = $deactivate_transacton->sum("total_transaction");

            $actual_collection_amount = $actual_transacton->sum("amount");
            $actual_collection_consumer = $actual_transacton->sum("total_consumer");
            $actual_collection_transection = $actual_transacton->sum("total_transaction");

            $application_type_collection_amount = $application_type_transacton->sum("amount");
            $application_type_collection_consumer = $application_type_transacton->sum("total_consumer");
            $application_type_collection_transection = $application_type_transacton->sum("total_transaction");

            $data["total_collection"]["mode_wise"] = $total_transacton->all();
            $data["total_collection"]["total"] = [
                "payment_mode" => "Total",
                "amount" => $total_collection_amount,
                "total_transaction" => $total_collection_transection,
                "total_consumer" => $total_collection_consumer,
            ];
            $data["deactivate_collection"]["mode_wise"] = $deactivate_transacton->all();
            $data["deactivate_collection"]["total"] = [
                "payment_mode" => "Total",
                "amount" => $deactivate_collection_amount,
                "total_transaction" => $deactivate_collection_transection,
                "total_consumer" => $deactivate_collection_consumer,
            ];
            $data["actual_collection"]["mode_wise"] = $actual_transacton->all();
            $data["actual_collection"]["total"] = [
                "payment_mode" => "Total",
                "amount" => $actual_collection_amount,
                "total_transaction" => $actual_collection_transection,
                "total_consumer" => $actual_collection_consumer,
            ];
            $data["application_collection"]["mode_wise"] = $application_type_transacton->all();
            $data["application_collection"]["total"] = [
                "payment_mode" => "Total",
                "amount" => $application_type_collection_amount,
                "total_transaction" => $application_type_collection_transection,
                "total_consumer" => $application_type_collection_consumer,
            ];

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function tradeDaseboard(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fiYear = getFY();
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            $fromDate = $fromYear . "-04-01";
            $uptoDate = $toYear . "-03-31";

            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $data = $this->_DB_READ->select("
                                select count(license.id) , application_type 
                                from trade_param_application_types
                                left join (
                                        (
                                            select id,application_date,application_type_id
                                            from active_trade_licences
                                            where active_trade_licences.application_date between '$fromDate' and '$uptoDate'
                                                AND ulb_id = $ulbId
                                        )
                                        union(
                                            select id,application_date,application_type_id
                                            from rejected_trade_licences
                                            where rejected_trade_licences.application_date between '$fromDate' and '$uptoDate'
                                                AND ulb_id = $ulbId
                                        )
                                        union(
                                            select id,application_date,application_type_id
                                            from trade_licences
                                            where trade_licences.application_date between '$fromDate' and '$uptoDate'
                                                AND ulb_id = $ulbId
                                        )
                                        union(
                                            select id,application_date,application_type_id
                                            from trade_renewals
                                            where trade_renewals.application_date between '$fromDate' and '$uptoDate'
                                                AND ulb_id = $ulbId
                                        )
                                    ) license  on trade_param_application_types.id = license.application_type_id                                
                                    
                                    group by application_type,trade_param_application_types.id
                                    ORDER BY trade_param_application_types.id
                            ");


            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function applicationTypeCollection(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $userId = null;

            $fiYear = getFY();
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valid Financial Year");
            }
            $fromDate = $fromYear . "-04-01";
            $uptoDate = $toYear . "-03-31";

            $where = " AND trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate' ";

            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $where .= " AND trade_transactions.ulb_id = $ulbId ";

            $application_type_transacton = $this->_DB_READ->select("
                                                        SELECT
                                                            sum(COALESCE(trade_transactions.paid_amount,0)) as amount, 
                                                            count(trade_transactions.id) as total_transaction,
                                                            count(trade_transactions.temp_id) as total_consumer,
                                                            initcap(trade_param_application_types.application_type) as application_type ,
                                                            trade_param_application_types.id 
                                                        FROM trade_param_application_types
                                                        LEFT JOIN trade_transactions ON trade_transactions.tran_type =  trade_param_application_types.application_type 
                                                            $where AND trade_transactions.status IN(1,2)
                                                        GROUP BY  trade_param_application_types.application_type ,trade_param_application_types.id
                                                        ORDER BY trade_param_application_types.id
                                                        ");
            $application_type_transacton = collect($application_type_transacton);
            $data["data"] = $application_type_transacton;
            $data["fiYear"] = $fiYear;
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($data), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function userAppliedApplication(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $select = [
                "licences.id",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.document_upload_status",
                "licences.payment_status",
                "licences.pending_status",
                "licences.firm_name",
                "licences.apply_from",
                "licences.application_type_id",
                "licences.ulb_id",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                "ulb_masters.ulb_name",
                DB::RAW("TO_CHAR( CAST(licences.license_date AS DATE), 'DD-MM-YYYY') as license_date,
                        TO_CHAR( CAST(licences.valid_from AS DATE), 'DD-MM-YYYY') as valid_from,
                        TO_CHAR( CAST(licences.valid_upto AS DATE), 'DD-MM-YYYY') as valid_upto,
                        TO_CHAR( CAST(licences.application_date AS DATE), 'DD-MM-YYYY') as application_date
                "),
            ];
            $ActiveSelect = $select;
            $RejectedSelect = $select;
            $ApprovedSelect = $select;

            $ActiveSelect[] = DB::raw("'active' as license_type");
            $ActiveLicence = $this->_DB_READ->table("active_trade_licences AS licences")
                ->select($ActiveSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                    STRING_AGG(email_id,',') AS email_id,
                                    active_trade_owners.temp_id
                                    FROM active_trade_owners 
                                    JOIN active_trade_licences on active_trade_licences.user_id = $refUserId 
                                        AND active_trade_licences.id = active_trade_owners.temp_id 
                                    WHERE active_trade_owners.is_active = true
                                    GROUP BY active_trade_owners.temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.user_id", $refUserId);
            // ->get();

            $RejectedSelect[] = DB::raw("'rejected' as license_type");
            $RejectedLicence = $this->_DB_READ->table("rejected_trade_licences AS licences")
                ->select($RejectedSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                    STRING_AGG(email_id,',') AS email_id,
                                    rejected_trade_owners.temp_id
                                    FROM rejected_trade_owners
                                    JOIN rejected_trade_licences on rejected_trade_licences.user_id = $refUserId 
                                        AND rejected_trade_licences.id = rejected_trade_owners.temp_id 
                                    WHERE rejected_trade_owners.is_active = true
                                    GROUP BY rejected_trade_owners.temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.user_id", $refUserId);
            // ->get();

            $ApprovedSelect[] = DB::raw("'approved' as license_type");
            $ApprovedLicence = $this->_DB_READ->table("trade_licences AS licences")
                ->select($ApprovedSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        trade_owners.temp_id
                                        FROM trade_owners
                                        JOIN trade_licences on trade_licences.user_id = $refUserId 
                                        AND trade_licences.id = trade_owners.temp_id 
                                        WHERE trade_owners.is_active = true
                                        GROUP BY trade_owners.temp_id
                                        )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.user_id", $refUserId);

            $ActiveLicence = $ActiveLicence->WHEREBETWEEN("application_date", [$fromDate, $uptoDate]);
            $RejectedLicence = $RejectedLicence->WHEREBETWEEN("application_date", [$fromDate, $uptoDate]);
            $ApprovedLicence = $ApprovedLicence->WHEREBETWEEN("application_date", [$fromDate, $uptoDate]);

            $ActiveLicence = $ActiveLicence->WHERE("ulb_id", $ulbId);
            $RejectedLicence = $RejectedLicence->WHERE("ulb_id", $ulbId);
            $ApprovedLicence = $ApprovedLicence->WHERE("ulb_id", $ulbId);

            if ($wardId) {
                $ActiveLicence = $ActiveLicence->WHERE("ward_id", $wardId);
                $RejectedLicence = $RejectedLicence->WHERE("ward_id", $wardId);
                $ApprovedLicence = $ApprovedLicence->WHERE("ward_id", $wardId);
            }

            $final = $ActiveLicence->union($RejectedLicence)
                ->union($ApprovedLicence)
                ->get();
            // dd(DB::getQueryLog());

            $final->map(function ($val) {
                $option = [];
                $nextMonth = Carbon::now()->addMonths(1)->format('Y-m-d');
                $validUpto = "";
                if ($val->valid_upto) {
                    $validUpto = Carbon::createFromFormat("d-m-Y", $val->valid_upto)->format('Y-m-d');
                }
                if (trim($val->license_type) == "approved" && $val->pending_status == 5 && $validUpto < $nextMonth) {
                    $option[] = "RENEWAL";
                }
                if (trim($val->license_type) == "approved" && $val->pending_status == 5 && $validUpto >= Carbon::now()->format('Y-m-d')) {
                    $option[] = "AMENDMENT";
                    $option[] = "SURRENDER";
                }
                $val->option = $option;
                return $val;
            });
            return responseMsg(true, "", remove_null($final));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function collectionPerfomance(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $data = (array) null;
            $wardId = null;

            $fiYear = getFY();
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            $yFromDate = $fromYear . "-04-01";
            $yUptoDate = $toYear . "-03-31";

            $now = Carbon::now();
            $toDay = $now->format('Y-m-d');
            $wFromDate = $now->startOfWeek()->format('Y-m-d');
            $wUptoDate = $now->endOfWeek()->format('Y-m-d');
            $mFromDate = $now->startOfMonth()->format('Y-m-d');
            $mUptoDate = $now->endOfMonth()->format('Y-m-d');

            $yearlly = TradeTransaction::readConnection()->select(DB::RAW("
                                CASE WHEN SUM(COALESCE(trade_transactions.paid_amount,0)) IS NOT NULL THEN SUM(COALESCE(trade_transactions.paid_amount,0)) ELSE 0 END  as amount,
                                COUNT(DISTINCT(trade_transactions.temp_id)) AS total_application,
                                COUNT(DISTINCT(trade_transactions.id)) AS total_transection
                                "))
                ->WHERE("trade_transactions.ulb_id", $ulbId)
                ->WHEREIN("trade_transactions.status", [1, 2]);

            $monthlly = TradeTransaction::readConnection()->select(DB::RAW("
                            CASE WHEN SUM(COALESCE(trade_transactions.paid_amount,0)) IS NOT NULL THEN SUM(COALESCE(trade_transactions.paid_amount,0)) ELSE 0 END  as amount,
                            COUNT(DISTINCT(trade_transactions.temp_id)) AS total_application,
                            COUNT(DISTINCT(trade_transactions.id)) AS total_transection
                            "))
                ->WHERE("trade_transactions.ulb_id", $ulbId)
                ->WHEREIN("trade_transactions.status", [1, 2]);

            $weeklly = TradeTransaction::readConnection()->select(DB::RAW("
                            CASE WHEN SUM(COALESCE(trade_transactions.paid_amount,0)) IS NOT NULL THEN SUM(COALESCE(trade_transactions.paid_amount,0)) ELSE 0 END  as amount,
                            COUNT(DISTINCT(trade_transactions.temp_id)) AS total_application,
                            COUNT(DISTINCT(trade_transactions.id)) AS total_transection
                            "))
                ->WHERE("trade_transactions.ulb_id", $ulbId)
                ->WHEREIN("trade_transactions.status", [1, 2]);

            $daylly = TradeTransaction::readConnection()->select(DB::RAW("
                            CASE WHEN SUM(COALESCE(trade_transactions.paid_amount,0)) IS NOT NULL THEN SUM(COALESCE(trade_transactions.paid_amount,0)) ELSE 0 END  as amount,
                            COUNT(DISTINCT(trade_transactions.temp_id)) AS total_application,
                            COUNT(DISTINCT(trade_transactions.id)) AS total_transection
                            "))
                ->WHERE("trade_transactions.ulb_id", $ulbId)
                ->WHEREIN("trade_transactions.status", [1, 2]);

            $yearlly = $yearlly->WHEREBETWEEN('trade_transactions.tran_date', [$yFromDate, $yUptoDate]);
            $monthlly = $monthlly->WHEREBETWEEN('trade_transactions.tran_date', [$mFromDate, $mUptoDate]);
            $weeklly = $weeklly->WHEREBETWEEN('trade_transactions.tran_date', [$wFromDate, $wUptoDate]);
            $daylly = $daylly->WHEREBETWEEN('trade_transactions.tran_date', [$toDay, $toDay]);

            if ($wardId) {
                $yearlly = $yearlly->WHERE('trade_transactions.ward_id', $wardId);
                $monthlly = $monthlly->WHERE('trade_transactions.ward_id', $wardId);
                $weeklly = $weeklly->WHERE('trade_transactions.ward_id', $wardId);
                $daylly = $daylly->WHERE('trade_transactions.ward_id', $wardId);
            }

            if (in_array(strtoupper($mUserType), ["JSK", "TC"])) {
                $yearlly = $yearlly->WHERE('trade_transactions.emp_dtl_id', $refUserId)
                    ->WHERENOTIN(DB::RAW('UPPER(trade_transactions.payment_mode)'), ['ONLINE', 'ONL']);
                $monthlly = $monthlly->WHERE('trade_transactions.emp_dtl_id', $refUserId)
                    ->WHERENOTIN(DB::RAW('UPPER(trade_transactions.payment_mode)'), ['ONLINE', 'ONL']);
                $weeklly = $weeklly->WHERE('trade_transactions.emp_dtl_id', $refUserId)
                    ->WHERENOTIN(DB::RAW('UPPER(trade_transactions.payment_mode)'), ['ONLINE', 'ONL']);
                $daylly = $daylly->WHERE('trade_transactions.emp_dtl_id', $refUserId)
                    ->WHERENOTIN(DB::RAW('UPPER(trade_transactions.payment_mode)'), ['ONLINE', 'ONL']);
            }

            $yearlly = $yearlly->get();
            $monthlly = $monthlly->get();
            $weeklly = $weeklly->get();
            $daylly = $daylly->get();



            $data["yearlly"]    = $yearlly;
            $data["monthlly"]   = $monthlly;
            $data["weeklly"]    = $weeklly;
            $data["daylly"]     = $daylly;

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function ApplicantionTrackStatus(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $select = [
                "licences.id",
                "licences.ward_id",
                "licences.document_upload_status",
                "licences.payment_status",
                "licences.pending_status",
                "licences.is_parked",
                "licences.workflow_id",
                "owners.owner_name", "owners.mobile_no",
                DB::raw("ulb_ward_masters.ward_name as ward_no,
                    TO_CHAR(CAST(licences.application_date AS DATE) , 'DD-MM-YYYY') as application_date
                ")
            ];
            $active = $this->_DB_READ->TABLE("active_trade_licences AS licences")
                ->select($select)
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->leftjoin(DB::raw("(
                        select string_agg(owner_name,',') as owner_name,
                            string_agg(mobile_no::text,',') as mobile_no,
                            active_trade_owners.temp_id
                        from active_trade_owners 
                        join active_trade_licences on active_trade_licences.id = active_trade_owners.temp_id
                        where active_trade_owners.is_active = true
                            and active_trade_licences.application_date between '$fromDate' and '$uptoDate'
                            and active_trade_licences.ulb_id = $ulbId" .
                    ($wardId ? " And active_trade_licences.ward_id = $wardId " : " ")
                    .
                    "
                        group by active_trade_owners.temp_id
                        )owners"), function ($join) {
                    $join->on("owners.temp_id", "licences.id");
                })
                ->whereBetween("licences.application_date", [$fromDate, $uptoDate])
                ->where("licences.ulb_id", $ulbId);

            $rejected = $this->_DB_READ->TABLE("rejected_trade_licences AS licences")
                ->select($select)
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->leftjoin(DB::raw("(
                        select string_agg(owner_name,',') as owner_name,
                            string_agg(mobile_no::text,',') as mobile_no,
                            rejected_trade_owners.temp_id
                        from rejected_trade_owners 
                        join rejected_trade_licences on rejected_trade_licences.id = rejected_trade_owners.temp_id
                        where rejected_trade_owners.is_active = true
                            and rejected_trade_licences.application_date between '$fromDate' and '$uptoDate'
                            and rejected_trade_licences.ulb_id = $ulbId" .
                    ($wardId ? " And rejected_trade_licences.ward_id = $wardId " : " ")
                    .
                    "
                        group by rejected_trade_owners.temp_id
                        )owners"), function ($join) {
                    $join->on("owners.temp_id", "licences.id");
                })
                ->whereBetween("licences.application_date", [$fromDate, $uptoDate])
                ->where("licences.ulb_id", $ulbId);

            $approved = $this->_DB_READ->TABLE("trade_licences AS licences")
                ->select($select)
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->leftjoin(DB::raw("(
                        select string_agg(owner_name,',') as owner_name,
                            string_agg(mobile_no::text,',') as mobile_no,
                            trade_owners.temp_id
                        from trade_owners 
                        join trade_licences on trade_licences.id = trade_owners.temp_id
                        where trade_licences.is_active = true
                            and trade_licences.application_date between '$fromDate' and '$uptoDate'
                            and trade_licences.ulb_id = $ulbId" .
                    ($wardId ? " And trade_licences.ward_id = $wardId " : " ")
                    .
                    "
                        group by trade_owners.temp_id
                        )owners"), function ($join) {
                    $join->on("owners.temp_id", "licences.id");
                })
                ->whereBetween("licences.application_date", [$fromDate, $uptoDate])
                ->where("licences.ulb_id", $ulbId);


            $old = $this->_DB_READ->TABLE("trade_renewals AS licences")
                ->select($select)
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
                ->leftjoin(DB::raw("(
                        select string_agg(owner_name,',') as owner_name,
                            string_agg(mobile_no::text,',') as mobile_no,
                            trade_owners.temp_id
                        from trade_owners 
                        join trade_renewals on trade_renewals.id = trade_owners.temp_id
                        where trade_renewals.is_active = true
                            and trade_renewals.application_date between '$fromDate' and '$uptoDate'
                            and trade_renewals.ulb_id = $ulbId" .
                    ($wardId ? " And trade_renewals.ward_id = $wardId " : " ")
                    .
                    "
                        group by trade_owners.temp_id
                        )owners"), function ($join) {
                    $join->on("owners.temp_id", "licences.id");
                })
                ->whereBetween("licences.application_date", [$fromDate, $uptoDate])
                ->where("licences.ulb_id", $ulbId);
            if ($wardId) {
                $active = $active->where("licences.ward_id", $wardId);
                $rejected = $rejected->where("licences.ward_id", $wardId);
                $approved = $approved->where("licences.ward_id", $wardId);
                $old = $old->where("licences.ward_id", $wardId);
            }
            $data = $active
                ->union($rejected)
                ->union($approved)
                ->union($old);
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $data->paginate($perPage);
            $items = $paginator->items();
            $total = $paginator->total();
            $numberOfPages = ceil($total / $perPage);
            // $item2 =[];
            $items2 = collect($items);
            $items2 = $items2->map(function ($val) {

                $level = DB::SELECT("
                select array_to_json(array_agg(row_to_json(levle))) as level
                from (
                        select 
                        TO_CHAR(CAST(workflow_tracks.track_date AS DATE) , 'DD-MM-YYYY') as track_date,
                        TO_CHAR(CAST(workflow_tracks.forward_date AS DATE) , 'DD-MM-YYYY') as forward_date,
                        verification_status, 
                        ids.role_name
                        from workflow_tracks 
                        join(
                            select max(workflow_tracks.id)as id,wf_workflowrolemaps.serial_no,wf_roles.role_name
                            from workflow_tracks
                            inner join wf_roles on wf_roles.id = workflow_tracks.receiver_role_id 
                            inner join wf_workflowrolemaps on wf_workflowrolemaps.wf_role_id = wf_roles.id 
                                and wf_workflowrolemaps.workflow_id = " . $val->workflow_id . "
                            where 
                                workflow_tracks.ref_table_id_value = " . $val->id . "
                                and workflow_tracks.ref_table_dot_id = 'active_trade_licences' and workflow_tracks.status = true 
                                and workflow_tracks.workflow_id = " . $val->workflow_id . "
                                and workflow_tracks.citizen_id is null 
                                and workflow_tracks.deleted_at is null 
                            group by wf_roles.id,wf_workflowrolemaps.serial_no,wf_roles.role_name
                            )ids on ids.id = workflow_tracks.id
                    )levle
                ");
                $val->level  = (collect($level)->first())->level ? (json_decode((collect($level)->first())->level, true)) : [];
                return $val;
            });
            $list = [
                "current_page" => $page,
                "last_page" => $numberOfPages,
                "perPage" => $perPage,
                "data" => $items2,
                "total" => $total,
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function applicationAgentNotice(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        return responseMsgs(false, "Please check the function", [], $apiId, $version, $queryRunTime, $action, $deviceId);

        // try {
        //     $refUser        = Auth()->user();
        //     $refUserId      = $refUser->id;
        //     $ulbId          = $refUser->ulb_id;
        //     $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
        //     $wardId = null;

        //     if ($request->fromDate) {
        //         $fromDate = $request->fromDate;
        //     }
        //     if ($request->uptoDate) {
        //         $uptoDate = $request->uptoDate;
        //     }
        //     if ($request->wardId) {
        //         $wardId = $request->wardId;
        //     }
        //     if ($request->ulbId) {
        //         $ulbId = $request->ulbId;
        //     }

        //     $select = [
        //         DB::RAW("
        //             trade_notice_consumer_dtls.notice_no,
        //             TO_CHAR(cast(trade_notice_consumer_dtls.notice_date as date),'DD-MM-YYYY') as notice_date,
        //             TO_CHAR(cast(trade_notice_consumer_dtls.created_at as date),'DD-MM-YYYY') as notice_apply_date,
        //             TO_CHAR(cast(licences.application_date as date),'DD-MM-YYYY') as application_date,
        //             licences.application_no,
        //             licences.firm_name,
        //             licences.address,
        //             owners.owner_name,owners.guardian_name,owners.mobile_no,
        //             ulb_ward_masters.ward_name as ward_no
        //         ")
        //     ];
        //     $active = $this->_DB_READ->TABLE("active_trade_licences AS licences")
        //         ->select($select)
        //         ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
        //         ->JOIN("trade_notice_consumer_dtls", "trade_notice_consumer_dtls.id", "licences.denial_id")
        //         ->LEFTJOIN(DB::RAW("(
        //                 SELECT active_trade_owners.temp_id,
        //                     string_agg(owner_name,',')as owner_name,
        //                     string_agg(guardian_name,',')as guardian_name,
        //                     string_agg(mobile_no,',')as mobile_no
        //                 FROM  active_trade_owners
        //                 JOIN active_trade_licences ON active_trade_licences.id = active_trade_owners.temp_id
        //                 WHERE active_trade_owners.is_active = TRUE
        //                     AND active_trade_licences.ulb_id = $ulbId
        //                     AND active_trade_licences.application_date BETWEEN '$fromDate' AND '$uptoDate'
        //                     " . ($wardId ? " AND active_trade_licences.ward_id" : "") . "
        //                 GROUP BY active_trade_owners.temp_id
        //             )owners"), function ($join) {
        //             $join->on("owners.temp_id", "licences.id");
        //         })
        //         ->WHERE("licences.ulb_id", $ulbId)
        //         ->WHEREBETWEEN("licences.application_date", [$fromDate, $uptoDate]);

        //     $rejected = $this->_DB_READ->TABLE("rejected_trade_licences AS licences")
        //         ->select($select)
        //         ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
        //         ->JOIN("trade_notice_consumer_dtls", "trade_notice_consumer_dtls.id", "licences.denial_id")
        //         ->LEFTJOIN(DB::RAW("(
        //                 SELECT rejected_trade_owners.temp_id,
        //                     string_agg(owner_name,',')as owner_name,
        //                     string_agg(guardian_name,',')as guardian_name,
        //                     string_agg(mobile_no,',')as mobile_no
        //                 FROM rejected_trade_owners
        //                 JOIN rejected_trade_licences ON rejected_trade_licences.id = rejected_trade_owners.temp_id
        //                 WHERE rejected_trade_owners.is_active = true
        //                     AND rejected_trade_licences.ulb_id = $ulbId
        //                     AND rejected_trade_licences.application_date BETWEEN '$fromDate' AND '$uptoDate'
        //                     " . ($wardId ? " AND rejected_trade_licences.ward_id" : "") . "
        //                 GROUP BY rejected_trade_owners.temp_id
        //             )owners"), function ($join) {
        //             $join->on("owners.temp_id", "licences.id");
        //         })
        //         ->WHERE("licences.ulb_id", $ulbId)
        //         ->WHEREBETWEEN("licences.application_date", [$fromDate, $uptoDate]);

        //     $approved = $this->_DB_READ->TABLE("trade_licences AS licences")
        //         ->select($select)
        //         ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
        //         ->JOIN("trade_notice_consumer_dtls", "trade_notice_consumer_dtls.id", "licences.denial_id")
        //         ->LEFTJOIN(DB::RAW("(
        //                 SELECT trade_owners.temp_id,
        //                     string_agg(owner_name,',')as owner_name,
        //                     string_agg(guardian_name,',')as guardian_name,
        //                     string_agg(mobile_no,',')as mobile_no
        //                 FROM trade_owners
        //                 JOIN trade_licences ON trade_licences.id = trade_owners.temp_id
        //                 WHERE trade_owners.is_active = true
        //                     AND trade_licences.ulb_id = $ulbId
        //                     AND trade_licences.application_date BETWEEN '$fromDate' AND '$uptoDate'
        //                     " . ($wardId ? " AND trade_licences.ward_id" : "") . "
        //                 GROUP BY trade_owners.temp_id
        //             )owners"), function ($join) {
        //             $join->on("owners.temp_id", "licences.id");
        //         })
        //         ->WHERE("licences.ulb_id", $ulbId)
        //         ->WHEREBETWEEN("licences.application_date", [$fromDate, $uptoDate]);

        //     $old = $this->_DB_READ->TABLE("trade_renewals AS licences")
        //         ->select($select)
        //         ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "licences.ward_id")
        //         ->JOIN("trade_notice_consumer_dtls", "trade_notice_consumer_dtls.id", "licences.denial_id")
        //         ->LEFTJOIN(DB::RAW("(
        //                 SELECT trade_owners.temp_id,
        //                     string_agg(owner_name,',')as owner_name,
        //                     string_agg(guardian_name,',')as guardian_name,
        //                     string_agg(mobile_no,',')as mobile_no
        //                 FROM trade_owners
        //                 JOIN trade_renewals ON trade_renewals.id = trade_owners.temp_id
        //                 WHERE trade_owners.is_active = true
        //                     AND trade_renewals.ulb_id = $ulbId
        //                     AND trade_renewals.application_date BETWEEN '$fromDate' AND '$uptoDate'
        //                     " . ($wardId ? " AND trade_renewals.ward_id" : "") . "
        //                 GROUP BY trade_owners.temp_id
        //             )owners"), function ($join) {
        //             $join->on("owners.temp_id", "licences.id");
        //         })
        //         ->WHERE("licences.ulb_id", $ulbId)
        //         ->WHEREBETWEEN("licences.application_date", [$fromDate, $uptoDate]);
        //     $data = $active
        //         ->union($rejected)
        //         ->union($approved)
        //         ->union($old);

        //     $perPage = $request->perPage ? $request->perPage : 10;
        //     $paginator = $data->paginate($perPage);
        //     $list = [
        //         "current_page" => $paginator->currentPage(),
        //         "last_page" => $paginator->lastPage(),
        //         "data" => $paginator->items(),
        //         "total" => $paginator->total(),
        //     ];
        //     $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
        //     return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        // } catch (Exception $e) {
        //     return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        // }
    }

    public function noticeSummary(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;

            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $data["approved"] = TradeNoticeConsumerDtl::readconnection()->WHEREBETWEEN(DB::RAW("cast(created_at as date)"), [$fromDate, $uptoDate])
                ->WHERE("ulb_id", $ulbId)
                ->count("id");
            $data["pending"] = ActiveTradeNoticeConsumerDtl::readconnection()->WHEREBETWEEN(DB::RAW("cast(created_at as date)"), [$fromDate, $uptoDate])
                ->WHERE("ulb_id", $ulbId)
                ->count("id");
            $data["rejected"] = RejectedTradeNoticeConsumerDtl::readconnection()->WHEREBETWEEN(DB::RAW("cast(created_at as date)"), [$fromDate, $uptoDate])
                ->WHERE("ulb_id", $ulbId)
                ->count("id");
            $data["total"] = $data["approved"] + $data["pending"] + $data["rejected"];

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function levelwisependingform(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $refWfWorkflow     = $this->_DB_READ->table("wf_workflows")
                ->where('wf_master_id', $this->_WF_MASTER_Id)
                ->where('ulb_id', $ulbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $workflow_id = $refWfWorkflow->id;
            $data = $this->_DB_READ->table("wf_roles")->SELECT(
                "wf_roles.id",
                "wf_roles.role_name",
                DB::RAW("COUNT(active_trade_licences.id) AS total")
            )
                ->JOIN(DB::RAW("(
                                    SELECT distinct(wf_role_id) as wf_role_id
                                    FROM wf_workflowrolemaps 
                                    WHERE  wf_workflowrolemaps.is_suspended = false AND (forward_role_id IS NOT NULL OR backward_role_id IS NOT NULL)
                                        AND workflow_id IN(" . $workflow_id . ") 
                                    GROUP BY wf_role_id 
                                ) AS roles
                    "), function ($join) {
                    $join->on("wf_roles.id", "roles.wf_role_id");
                })
                ->LEFTJOIN("active_trade_licences", function ($join) use ($ulbId) {
                    $join->ON("active_trade_licences.current_role", "roles.wf_role_id")
                        ->WHERE("active_trade_licences.ulb_id", $ulbId)
                        ->WHERE("active_trade_licences.is_parked", FALSE)
                        ->WHERE("active_trade_licences.is_active", TRUE);
                    // ->WHEREIN("active_trade_licences.payment_status", [1, 2]);
                })
                ->GROUPBY(["wf_roles.id", "wf_roles.role_name"])
                // ->UNION(
                //     ActiveTradeLicence::SELECT(
                //         DB::RAW("8 AS id, 'JSK' AS role_name,
                //                 COUNT(active_trade_licences.id)")
                //     )
                //         ->WHERE("active_trade_licences.ulb_id", $ulbId)
                //         ->WHERE("active_trade_licences.is_parked", FALSE)
                //         ->WHERE("active_trade_licences.is_active", TRUE)
                //         ->WHERE(function ($where) {
                //             $where->WHERE("active_trade_licences.payment_status", "=", 0)
                //                 ->ORWHERENULL("active_trade_licences.payment_status");
                //         })
                // )
                ->GET();
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, ["header" => "LEVEL PENDING REPORT"], $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function levelUserPending(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $roleId = $roleId2 = $userId = null;
            $header = $userName = $roleName = null;
            $allRolse = $this->_COMMON_FUNCTION->getAllRoles($refUserId, $ulbId, $this->_WF_MASTER_Id, 0, TRUE);
            $joins = "join";
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->userId) {
                $userId = $request->userId;
                $userName = $this->_DB->TABLE('users')->find($userId)->name ?? "";
                $roleId2 = ($this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id))->role_id ?? 0;
            }
            if ($request->roleId) {
                $roleId = $request->roleId;
                $currentRole = (array_values(collect($allRolse)->where("id", $roleId)->toArray()))[0] ?? [];

                $roleName = $currentRole["role_name"] ?? "";
            }
            if (($request->roleId && $request->userId) && ($roleId != $roleId2)) {
                throw new Exception("Invalid RoleId Pass");
            }
            if ($roleName) {
                $header = ["header" => "PENDING AT $roleName"];
            }
            if ($userName) {
                $header = ["header" => "PENDING AT $userName"];
            }
            if (in_array($roleId, [8])) {
                $joins = "leftjoin";
            }

            // DB::enableQueryLog();
            $data = ActiveTradeLicence::readConnection()->SELECT(
                DB::RAW(
                    "count(active_trade_licences.id),
                        users_role.user_id ,
                        users_role.user_name,
                        users_role.wf_role_id as role_id,
                        users_role.role_name"
                )
            )
                ->$joins(
                    DB::RAW("(
                        select wf_role_id,user_id,user_name,role_name,concat('{',ward_ids,'}') as ward_ids
                        from (
                            select wf_roleusermaps.wf_role_id,wf_roleusermaps.user_id,
                                users.name as user_name, wf_roles.role_name,
                                string_agg(wf_ward_users.ward_id::text,',') as ward_ids
                            from wf_roleusermaps 
                            join wf_roles on wf_roles.id = wf_roleusermaps.wf_role_id
                                AND wf_roles.is_suspended =false
                            join users on users.id = wf_roleusermaps.user_id
                            left join wf_ward_users on wf_ward_users.user_id = wf_roleusermaps.user_id and wf_ward_users.is_suspended = false
                            where wf_roleusermaps.wf_role_id =$roleId
                                AND wf_roleusermaps.is_suspended = false
                            group by wf_roleusermaps.wf_role_id,wf_roleusermaps.user_id,
                                users.name,wf_roles.role_name
                        )role_user_ward
                    ) users_role
                    "),
                    function ($join) use ($roleId) {
                        if (!in_array($roleId, [11, 8])) {
                            $join->on("users_role.wf_role_id", "=", "active_trade_licences.current_role")
                                ->where("active_trade_licences.ward_id", DB::raw("ANY (ward_ids::int[])"));
                        }
                        if (in_array($roleId, [11])) {
                            $join->on("users_role.wf_role_id", "=", "active_trade_licences.current_role");
                        }
                        if (in_array($roleId, [8])) {
                            $join->on(DB::raw("1"), DB::raw("1"));
                        }
                    }
                )
                ->WHERE("active_trade_licences.is_parked", FALSE)
                ->WHERE("active_trade_licences.is_active", TRUE)
                ->WHERE("active_trade_licences.ulb_id", $ulbId)
                ->groupBy(["users_role.user_id", "users_role.user_name", "users_role.wf_role_id", "users_role.role_name"]);
            if (!in_array($roleId, [8])) {
                // $data  = $data->WHEREIN("active_trade_licences.payment_status", [1, 2]);
            }
            if (in_array($roleId, [8])) {
                $data  = $data->WHERE(function ($where) {
                    $where->WHERE("active_trade_licences.payment_status", "=", 0)
                        ->ORWHERENULL("active_trade_licences.payment_status");
                });
            }

            $perPage = $request->perPage ? $request->perPage : 1000;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, $header, remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function userWiseWardWiseLevelPending(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $roleId = $roleId2 = $userId = null;
            $header = $userName = $roleName = null;
            $allRolse = $this->_COMMON_FUNCTION->getAllRoles($refUserId, $ulbId, $this->_WF_MASTER_Id, 0, TRUE);
            $mWardPermission = collect([]);
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->roleId) {
                $roleId = $request->roleId;
                $currentRole = (array_values(collect($allRolse)->where("id", $roleId)->toArray()))[0] ?? [];

                $roleName = $currentRole["role_name"] ?? "";
            }
            if ($request->userId) {
                $userId = $request->userId;
                $userName = $this->_DB->TABLE('users')->find($userId)->name ?? "";
                $roleId2 = ($this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id))->role_id ?? 0;
            }
            if (($request->roleId && $request->userId) && ($roleId != $roleId2)) {
                throw new Exception("Invalid RoleId Pass");
            }
            $roleId = $roleId2 ? $roleId2 : $roleId;
            if (!in_array($roleId, [11, 8])) {
                $mWfWardUser = new WfWardUser();
                $mWardPermission = $mWfWardUser->getWardsByUserId($userId);
            }
            if ($roleName) {
                $header = ["header" => "PENDING AT $roleName"];
            }
            if ($userName) {
                $header = ["header" => "PENDING AT $userName"];
            }

            $mWardIds = $mWardPermission->implode("ward_id", ",");
            $mWardIds = explode(',', ($mWardIds ? $mWardIds : "0"));
            $data = $this->_DB->TABLE("ulb_ward_masters")->SELECT(
                DB::RAW(" DISTINCT(ward_name) as ward_no,ulb_ward_masters.id AS ward_id, COUNT(active_trade_licences.id) AS total")
            )
                ->LEFTJOIN("active_trade_licences", "ulb_ward_masters.id", "active_trade_licences.ward_id");
            if ($roleId == 8) {
                $data = $data->LEFTJOIN("wf_roles", "wf_roles.id", "active_trade_licences.current_role")
                    ->WHERENOTNULL("active_trade_licences.user_id")
                    ->WHERE(function ($where) {
                        $where->WHERE("active_trade_licences.payment_status", "=", 0)
                            ->ORWHERENULL("active_trade_licences.payment_status");
                    });
            } else {
                $data = $data->JOIN("wf_roles", "wf_roles.id", "active_trade_licences.current_role")
                    ->WHERE("wf_roles.id", $roleId);
            }

            if (!in_array($roleId, [11, 8]) && $userId) {
                $data = $data->WHEREIN("active_trade_licences.ward_id", $mWardIds);
            }
            $data = $data->WHERE("active_trade_licences.ulb_id", $ulbId)
                ->WHERE("active_trade_licences.is_parked", FALSE)
                ->WHERE("active_trade_licences.is_active", TRUE);
            $data = $data->groupBy(["ward_name", "ulb_ward_masters.id"]);

            $perPage = $request->perPage ? $request->perPage : 1000;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, $header, $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function levelformdetail(Request $request)
    {
        # $roleId =8 jsk , $roleId =11 back office
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $roleId = $roleId2 = $userId = null;
            $mWardPermission = collect([]);

            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->roleId) {
                $roleId = $request->roleId;
            }
            if ($request->userId) {
                $userId = $request->userId;
                $roleId2 = ($this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id))->role_id ?? 0;
            }
            if (($request->roleId && $request->userId) && ($roleId != $roleId2)) {
                throw new Exception("Invalid RoleId Pass");
            }
            $roleId = $roleId2 ? $roleId2 : $roleId;
            if (!in_array($roleId, [11, 8])) {
                $mWfWardUser = new WfWardUser();
                $mWardPermission = $mWfWardUser->getWardsByUserId($userId);
            }
            $mWardIds = $mWardPermission->implode("ward_id", ",");
            if ($request->wardId) {
                $mWardIds = $request->wardId;
            }
            $mWardIds = explode(',', ($mWardIds ? $mWardIds : "0"));

            // DB::enableQueryLog();
            $data = ActiveTradeLicence::readConnection()->SELECT(
                DB::RAW("wf_roles.id AS role_id, wf_roles.role_name,
                        active_trade_licences.id, active_trade_licences.application_no, active_trade_licences.address,
                        ward_name as ward_no, 
                        owner.owner_name, owner.mobile_no")
            )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "active_trade_licences.ward_id")
                ->LEFTJOIN(DB::RAW("( 
                                SELECT DISTINCT(active_trade_owners.temp_id) AS temp_id,STRING_AGG(owner_name,',') AS owner_name, 
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no
                                FROM active_trade_owners
                                JOIN active_trade_licences ON active_trade_licences.id = active_trade_owners.temp_id
                                WHERE active_trade_owners.is_active = true 
                                    AND active_trade_licences.ulb_id = $ulbId
                                GROUP BY active_trade_owners.temp_id
                                ) AS owner
                                "), function ($join) {
                    $join->on("owner.temp_id", "=", "active_trade_licences.id");
                });
            if ($roleId == 8) {
                $data = $data->LEFTJOIN("wf_roles", "wf_roles.id", "active_trade_licences.current_role")
                    ->WHERENOTNULL("active_trade_licences.user_id")
                    ->WHERE(function ($where) {
                        $where->WHERE("active_trade_licences.payment_status", "=", 0)
                            ->ORWHERENULL("active_trade_licences.payment_status");
                    });
            } else {
                $data = $data->JOIN("wf_roles", "wf_roles.id", "active_trade_licences.current_role")
                    ->WHERE("wf_roles.id", $roleId);
            }
            $data = $data->WHERE("active_trade_licences.ulb_id", $ulbId)
                ->WHERE("active_trade_licences.is_parked", FALSE)
                ->WHERE("active_trade_licences.is_active", TRUE);
            if (!in_array($roleId, [11, 8]) && $userId) {
                $data = $data->WHEREIN("active_trade_licences.ward_id", $mWardIds);
            }
            if ($request->wardId) {
                $data = $data->WHEREIN("active_trade_licences.ward_id", $mWardIds);
            }
            $perPage = $request->perPage ? $request->perPage : 1000;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function bulkPaymentRecipt(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;
            $userId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $select = [
                "licences.id",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.trade_id",
                "licences.licence_for_years",
                "licences.application_type_id",
                "licences.license_no",
                "licences.firm_name",
                "licences.holding_no",
                "licences.address",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile",
                "trade_transactions.id as tran_id",
                "trade_transactions.tran_no",
                "trade_transactions.tran_type",
                "trade_transactions.tran_date",
                "trade_transactions.payment_mode",
                "trade_transactions.paid_amount",
                "trade_transactions.penalty",
                "fine_rebate.delay_fee",
                "fine_rebate.denial_fee",
                DB::raw("(trade_transactions.paid_amount-fine_rebate.penalty)as rate,
                        ulb_ward_masters.ward_name AS ward_no, 
                        ulb_masters.id as ulb_id, ulb_masters.ulb_name,ulb_masters.ulb_type,
                        ulb_masters.logo,
                        ulb_masters.short_name,
                        ulb_masters.hindi_name ,
                        ulb_masters.current_website ,
                        ulb_masters.parent_website ,
                        ulb_masters.toll_free_no ,
                        ulb_masters.mobile_no AS ulb_mobile_no,
                        TO_CHAR(cast(licences.application_date as date), 'DD-MM-YYYY') AS application_date,
                        TO_CHAR(cast(licences.valid_from as date), 'DD-MM-YYYY') AS valid_from,
                        TO_CHAR(cast(licences.valid_upto as date), 'DD-MM-YYYY') AS valid_upto
                        ")
            ];
            $active = $this->_DB_READ->table("active_trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->join("trade_transactions", "trade_transactions.temp_id", "licences.id")
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->leftjoin(DB::raw("(SELECT STRING_AGG(active_trade_owners.owner_name,',') as owner_name,
                                                STRING_AGG(active_trade_owners.guardian_name,',') as guardian_name,
                                                STRING_AGG(active_trade_owners.mobile_no::text,',') as mobile,
                                                active_trade_owners.temp_id
                                            FROM active_trade_owners 
                                            JOIN trade_transactions ON trade_transactions.temp_id = active_trade_owners.temp_id 
                                            WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                                AND trade_transactions.status in(1,2)
                                                AND active_trade_owners.is_active  = TRUE
                                            GROUP BY active_trade_owners.temp_id
                                            ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->leftjoin(DB::RAW("(SELECT trade_transactions.id AS tran_id,
                                        SUM(CASE WHEN trade_fine_rebetes.type = 'Delay Apply License' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS delay_fee,
                                        SUM(CASE WHEN trade_fine_rebetes.type = 'Denial Apply' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS denial_fee,
                                        SUM( trade_fine_rebetes.amount) AS penalty
                                    FROM trade_fine_rebetes
                                    JOIN trade_transactions ON trade_transactions.id = trade_fine_rebetes.tran_id 
                                    WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                        AND trade_transactions.status in(1,2)
                                        AND trade_fine_rebetes.status  = 1
                                    GROUP BY trade_transactions.id
                        ) fine_rebate"), function ($join) {
                    $join->on("fine_rebate.tran_id", "trade_transactions.id");
                })
                ->WHEREBETWEEN('trade_transactions.tran_date', [$fromDate, $uptoDate])
                ->WHEREIN('trade_transactions.status', [1, 2]);

            $approved = $this->_DB_READ->table("trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->join("trade_transactions", "trade_transactions.temp_id", "licences.id")
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->leftjoin(DB::raw("(SELECT STRING_AGG(trade_owners.owner_name,',') as owner_name,
                                            STRING_AGG(trade_owners.guardian_name,',') as guardian_name,
                                            STRING_AGG(trade_owners.mobile_no,',') as mobile,
                                            trade_owners.temp_id
                                        FROM trade_owners 
                                        JOIN trade_transactions ON trade_transactions.temp_id = trade_owners.temp_id 
                                        WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                            AND trade_transactions.status in(1,2)
                                            AND trade_owners.is_active  = TRUE
                                        GROUP BY trade_owners.temp_id
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->leftjoin(DB::RAW("(SELECT trade_transactions.id AS tran_id,
                                        SUM(CASE WHEN trade_fine_rebetes.type = 'Delay Apply License' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS delay_fee,
                                        SUM(CASE WHEN trade_fine_rebetes.type = 'Denial Apply' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS denial_fee,
                                        SUM( trade_fine_rebetes.amount) AS penalty
                                    FROM trade_fine_rebetes
                                    JOIN trade_transactions ON trade_transactions.id = trade_fine_rebetes.tran_id 
                                    WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                        AND trade_transactions.status in(1,2)
                                        AND trade_fine_rebetes.status  = 1
                                    GROUP BY trade_transactions.id
                        ) fine_rebate"), function ($join) {
                    $join->on("fine_rebate.tran_id", "trade_transactions.id");
                })
                ->WHEREBETWEEN('trade_transactions.tran_date', [$fromDate, $uptoDate])
                ->WHEREIN('trade_transactions.status', [1, 2]);

            $rejected = $this->_DB_READ->table("rejected_trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->join("trade_transactions", "trade_transactions.temp_id", "licences.id")
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->leftjoin(DB::raw("(SELECT STRING_AGG(rejected_trade_owners.owner_name,',') as owner_name,
                                            STRING_AGG(rejected_trade_owners.guardian_name,',') as guardian_name,
                                            STRING_AGG(rejected_trade_owners.mobile_no,',') as mobile,
                                            rejected_trade_owners.temp_id
                                        FROM rejected_trade_owners 
                                        JOIN trade_transactions ON trade_transactions.temp_id = rejected_trade_owners.temp_id 
                                        WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                            AND trade_transactions.status in(1,2)
                                            AND rejected_trade_owners.is_active  = TRUE
                                        GROUP BY rejected_trade_owners.temp_id
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->leftjoin(DB::RAW("(SELECT trade_transactions.id AS tran_id,
                                    SUM(CASE WHEN trade_fine_rebetes.type = 'Delay Apply License' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS delay_fee,
                                    SUM(CASE WHEN trade_fine_rebetes.type = 'Denial Apply' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS denial_fee,
                                    SUM( trade_fine_rebetes.amount) AS penalty
                                FROM trade_fine_rebetes
                                JOIN trade_transactions ON trade_transactions.id = trade_fine_rebetes.tran_id 
                                WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                    AND trade_transactions.status in(1,2)
                                    AND trade_fine_rebetes.status  = 1
                                GROUP BY trade_transactions.id
                    ) fine_rebate"), function ($join) {
                    $join->on("fine_rebate.tran_id", "trade_transactions.id");
                })
                ->WHEREBETWEEN('trade_transactions.tran_date', [$fromDate, $uptoDate])
                ->WHEREIN('trade_transactions.status', [1, 2]);

            $renewal = $this->_DB_READ->table("trade_renewals AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->join("trade_transactions", "trade_transactions.temp_id", "licences.id")
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->leftjoin(DB::raw("(SELECT STRING_AGG(trade_owners.owner_name,',') as owner_name,
                                                STRING_AGG(trade_owners.guardian_name,',') as guardian_name,
                                                STRING_AGG(trade_owners.mobile_no,',') as mobile,
                                                trade_owners.temp_id
                                            FROM trade_owners 
                                            JOIN trade_transactions ON trade_transactions.temp_id = trade_owners.temp_id 
                                            WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                                AND trade_transactions.status in(1,2)
                                                AND trade_owners.is_active  = TRUE
                                            GROUP BY trade_owners.temp_id
                                            ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->leftjoin(DB::RAW("(SELECT trade_transactions.id AS tran_id,
                                        SUM(CASE WHEN trade_fine_rebetes.type = 'Delay Apply License' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS delay_fee,
                                        SUM(CASE WHEN trade_fine_rebetes.type = 'Denial Apply' THEN  trade_fine_rebetes.amount ELSE 0 END ) AS denial_fee,
                                        SUM( trade_fine_rebetes.amount) AS penalty
                                    FROM trade_fine_rebetes
                                    JOIN trade_transactions ON trade_transactions.id = trade_fine_rebetes.tran_id 
                                    WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                        AND trade_transactions.status in(1,2)
                                        AND trade_fine_rebetes.status  = 1
                                    GROUP BY trade_transactions.id
                        ) fine_rebate"), function ($join) {
                    $join->on("fine_rebate.tran_id", "trade_transactions.id");
                })
                ->WHEREBETWEEN('trade_transactions.tran_date', [$fromDate, $uptoDate])
                ->WHEREIN('trade_transactions.status', [1, 2]);
            if ($wardId) {
                $active = $active->WHERE('ulb_ward_masters.id', $wardId);
                $approved = $approved->WHERE('ulb_ward_masters.id', $wardId);
                $rejected = $rejected->WHERE('ulb_ward_masters.id', $wardId);
                $renewal = $renewal->WHERE('ulb_ward_masters.id', $wardId);
            }
            if ($userId) {
                $active = $active->WHERE('trade_transactions.emp_dtl_id', $userId);
                $approved = $approved->WHERE('trade_transactions.emp_dtl_id', $userId);
                $rejected = $rejected->WHERE('trade_transactions.emp_dtl_id', $userId);
                $renewal = $renewal->WHERE('trade_transactions.emp_dtl_id', $userId);
            }
            if ($ulbId) {
                $active = $active->WHERE('licences.ulb_id', $ulbId);
                $approved = $approved->WHERE('licences.ulb_id', $ulbId);
                $rejected = $rejected->WHERE('licences.ulb_id', $ulbId);
                $renewal = $renewal->WHERE('licences.ulb_id', $ulbId);
            }
            $data = $active->union($approved)
                ->union($rejected)
                ->union($renewal)
                ->get();
            $data2 = $data->map(function ($val) {
                $val->paid_amount_in_words = getIndianCurrency($val->paid_amount);
                if (!$val->licence_for_years || !$val->valid_from || !$val->valid_upto) {
                    $this->temCalValidity($val);
                }
                return $val;
            });
            $data = remove_null($data2);
            return responseMsg(true, "", $data);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function applicationStatus(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;
            $userId = null;
            $status = $key = "";
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if (trim($request->key)) {
                $key = trim($request->key);
            }

            $select = [
                "licences.id as application_id",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.firm_name",
                "licences.holding_no",
                "licences.address",
                "licences.application_type_id",
                "application_type",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, 
                        ulb_masters.id as ulb_id, 
                        ulb_masters.ulb_name,
                        ulb_masters.ulb_type,
                        TO_CHAR(CAST(licences.application_date AS DATE), 'DD-MM-YYYY') as application_date,
                        TO_CHAR(CAST(licences.valid_from AS DATE), 'DD-MM-YYYY') as valid_from,
                        TO_CHAR(CAST(licences.valid_upto AS DATE), 'DD-MM-YYYY') as valid_upto
                        ")
            ];
            $active = $this->_DB_READ->TABLE("active_trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(active_trade_owners.owner_name,',') as owner_name,
                                                STRING_AGG(active_trade_owners.guardian_name,',') as guardian_name,
                                                STRING_AGG(active_trade_owners.mobile_no::text,',') as mobile,
                                                active_trade_owners.temp_id
                                            FROM active_trade_owners
                                            JOIN active_trade_licences ON active_trade_licences.id = active_trade_owners.temp_id
                                            WHERE active_trade_licences.application_date BETWEEN '$fromDate' AND '$uptoDate'
                                                AND active_trade_licences.is_active = TRUE
                                                AND active_trade_owners.is_active  = TRUE
                                            GROUP BY active_trade_owners.temp_id
                                            ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->WHEREBETWEEN('licences.application_date', [$fromDate, $uptoDate])
                ->WHERE('licences.is_active', TRUE);


            $approved = $this->_DB_READ->TABLE("trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(trade_owners.owner_name,',') as owner_name,
                                            STRING_AGG(trade_owners.guardian_name,',') as guardian_name,
                                            STRING_AGG(trade_owners.mobile_no,',') as mobile,
                                            trade_owners.temp_id
                                        FROM trade_owners 
                                        JOIN trade_licences ON trade_licences.id = trade_owners.temp_id 
                                        WHERE trade_licences.application_date BETWEEN '$fromDate' AND '$uptoDate'
                                            AND trade_licences.is_active  = TRUE
                                            AND trade_owners.is_active  = TRUE
                                        GROUP BY trade_owners.temp_id
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->WHEREBETWEEN('licences.application_date', [$fromDate, $uptoDate])
                ->WHERE('licences.is_active', TRUE);

            $rejected = $this->_DB_READ->TABLE("rejected_trade_licences AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(rejected_trade_owners.owner_name,',') as owner_name,
                                            STRING_AGG(rejected_trade_owners.guardian_name,',') as guardian_name,
                                            STRING_AGG(rejected_trade_owners.mobile_no,',') as mobile,
                                            rejected_trade_owners.temp_id
                                        FROM rejected_trade_owners 
                                        JOIN rejected_trade_licences ON rejected_trade_licences.id = rejected_trade_owners.temp_id 
                                        WHERE rejected_trade_licences.application_date BETWEEN '$fromDate' AND '$uptoDate'
                                            AND rejected_trade_licences.is_active  = TRUE
                                            AND rejected_trade_owners.is_active  = TRUE
                                        GROUP BY rejected_trade_owners.temp_id
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->WHEREBETWEEN('licences.application_date', [$fromDate, $uptoDate])
                ->WHERE('licences.is_active', TRUE);
            /*
            $renewal = $this->_DB_READ->TABLE("trade_renewals AS licences")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(trade_owners.owner_name,',') as owner_name,
                                            STRING_AGG(trade_owners.guardian_name,',') as guardian_name,
                                            STRING_AGG(trade_owners.mobile_no,',') as mobile,
                                            trade_owners.temp_id
                                        FROM trade_owners 
                                        JOIN trade_transactions ON trade_transactions.temp_id = trade_owners.temp_id 
                                        WHERE trade_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                            AND trade_transactions.status in(1,2)
                                            AND trade_owners.is_active  = TRUE
                                        GROUP BY trade_owners.temp_id
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->WHEREBETWEEN('licences.application_date',[$fromDate,$uptoDate])
                ->WHERE('licences.is_active',TRUE);
            */
            if ($wardId) {
                $active = $active->WHERE('ulb_ward_masters.id', $wardId);
                $approved = $approved->WHERE('ulb_ward_masters.id', $wardId);
                $rejected = $rejected->WHERE('ulb_ward_masters.id', $wardId);
                // $renewal = $renewal->WHERE('ulb_ward_masters.id',$wardId);
            }
            if ($userId) {
                $active = $active->WHERE('licences.user_id', $userId);
                $approved = $approved->WHERE('licences.user_id', $userId);
                $rejected = $rejected->WHERE('licences.user_id', $userId);
                // $renewal = $renewal->WHERE('licences.user_id',$userId);
            }
            if ($ulbId) {
                $active = $active->WHERE('licences.ulb_id', $ulbId);
                $approved = $approved->WHERE('licences.ulb_id', $ulbId);
                $rejected = $rejected->WHERE('licences.ulb_id', $ulbId);
                // $renewal = $renewal->WHERE('licences.ulb_id',$ulbId);
            }
            if ($key) {
                $active = $active->where(function ($query) use ($key) {
                    $query->orwhere('licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile', 'ILIKE', '%' . $key . '%');
                });
                $approved = $approved->where(function ($query) use ($key) {
                    $query->orwhere('licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile', 'ILIKE', '%' . $key . '%');
                });
                $rejected = $rejected->where(function ($query) use ($key) {
                    $query->orwhere('licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile', 'ILIKE', '%' . $key . '%');
                });
                // $renewal = $renewal->where(function ($query) use ($key) {
                //     $query->orwhere('licences.holding_no', 'ILIKE', '%' . $key . '%')
                //         ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                //         ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                //         ->orwhere("licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                //         ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                //         ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                //         ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                // });
            }
            $data = collect([]);

            switch ($request->status) {
                    #approved
                case 1:
                    $data = $approved;
                    break;
                    #PENDING
                case 2:
                    $data = $active;
                    break;
                    #rejected
                case 3:
                    $data = $rejected;
                    break;
                    #PAYMENT DONE BUT DOCUMENT NOT UPLOADED
                case 4:
                    $data = $active->WHEREIN("payment_status", [1, 2])->WHERE("document_upload_status", "<>", 1);
                    break;
                    #DOCUMENT UPLOADED BUT PAYMENT NOT DONE
                case 5:
                    $data = $active->WHERENOTIN("payment_status", [1, 2])->WHERE("document_upload_status", 1);
                    break;
                    #PAYMENT AND DOCUMENT UPLOAD PENDING
                case 6:
                    $data = $active->WHERENOTIN("payment_status", [1, 2])->WHERE("document_upload_status", "<>", 1);
                    break;
                default:
                    $data = $active->union($approved)->union($rejected);
            }

            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
}
