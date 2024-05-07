<?php

namespace App\Repository\Property\Concrete;

use App\EloquentModels\Common\ModelWard;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropProperty;
use App\Models\Property\PropTransaction;
use App\Models\Property\ZoneMaster;
use App\Models\UlbWardMaster;
use App\Models\User;
use App\Models\Workflows\WfRole;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\IReport;
use App\Repository\Water\Concrete\Report as ConcreteReport;
use App\Traits\Property\SAF;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery\CountValidator\Exact;
use Illuminate\Support\Str;

class Report implements IReport
{
    use SAF;
    use Workflow;

    protected $_common;
    protected $_modelWard;
    protected $_Saf;
    protected $_DB;
    protected $_DB_READ;
    protected $_DB_NAME;

    public function __construct()
    {
        $this->_DB_NAME = (new PropProperty())->getConnectionName();
        $this->_DB = DB::connection( $this->_DB_NAME );
        $this->_DB_READ = DB::connection( $this->_DB_NAME."::read" );
        $this->_common = new CommonFunction();
        $this->_modelWard = new ModelWard();
        $this->_Saf = new SafRepository();
    }
    /**
     * | Property Collection
     */
    public function collectionReport(Request $request)
    {
        $metaData = collect($request->metaData)->all();

        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $ulbId          = $refUser->ulb_id;
            $userJoin = "LEFTJOIN";
            $wardId = $zoneId = $userId =  $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");

            if ($request->userJoin) {
                $userJoin = $request->userJoin;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            if ($request->userId)
                $userId = $request->userId;
            // else
            //     $userId = auth()->user()->id;                   // In Case of any logged in TC User

            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            DB::enableQueryLog();
            $data = PropTransaction::readConnection()->SELECT(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            prop_properties.id,
                            prop_properties.prop_address,
                            zone_masters.zone_name,
                            'property' as type,
                            prop_properties.saf_no,
                            prop_properties.assessment_type,
                            new_holding_no,
                            prop_transactions.id AS tran_id,
                            CONCAT('', prop_properties.holding_no, '') AS holding_no,
                            prop_properties.property_no,
                            prop_owner_detail.owner_name,
                            prop_owner_detail.mobile_no,
                            CONCAT(
                                prop_transactions.from_fyear, '/',prop_transactions.to_fyear
                            ) AS from_upto_fy_qtr,
                            prop_transactions.tran_date,
                            prop_transactions.payment_mode AS transaction_mode,
                            prop_transactions.amount,users.name as emp_name,users.id as user_id,
                            users.mobile as tc_mobile,
                            prop_transactions.tran_no,prop_cheque_dtls.cheque_no,
                            prop_cheque_dtls.bank_name,prop_cheque_dtls.branch_name
                "),
            )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->LEFTJOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(owner_name, ', ') AS owner_name, STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, prop_owners.property_id 
                            FROM prop_properties 
                        JOIN prop_transactions on prop_transactions.property_id = prop_properties.id
                        JOIN prop_owners on prop_owners.property_id = prop_properties.id
                        WHERE prop_transactions.property_id IS NOT NULL AND prop_transactions.status in (1, 2) 
                        AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND prop_transactions.user_id = $userId " : "")
                        . ($paymentMode ? " AND upper(prop_transactions.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY prop_owners.property_id
                        ) AS prop_owner_detail
                        "),
                    function ($join) {
                        $join->on("prop_owner_detail.property_id", "=", "prop_transactions.property_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_properties.ward_mstr_id")
                ->LEFTJOIN("zone_masters", "zone_masters.id", "prop_properties.zone_mstr_id")
                ->$userJoin("users", "users.id", "prop_transactions.user_id")
                ->LEFTJOIN("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("zone_masters.id", $zoneId);
            }
            if ($userId) {
                $data = $data->where("prop_transactions.user_id", $userId);
            }
            if ($paymentMode) {
                $data = $data->where(DB::raw("upper(prop_transactions.payment_mode)"), $paymentMode);
            }
            if ($ulbId) {
                $data = $data->where("prop_transactions.ulb_id", $ulbId);
            }
            $paginator = collect();

            $data2 = $data;
            $totalHolding = $data2->count("prop_properties.id");
            $totalAmount = $data2->sum("prop_transactions.amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            if ($request->all) {
                $data = $data->get();
                $mode = collect($data)->unique("transaction_mode")->pluck("transaction_mode");
                $totalFAmount = collect($data)->unique("tran_id")->sum("amount");
                $totalFCount = collect($data)->unique("tran_id")->count("tran_id");
                $footer = $mode->map(function ($val) use ($data) {
                    $count = $data->where("transaction_mode", $val)->unique("tran_id")->count("tran_id");
                    $amount = $data->where("transaction_mode", $val)->unique("tran_id")->sum("amount");
                    return ['mode' => $val, "count" => $count, "amount" => $amount];
                });
                $list = [
                    "data" => $data,
                    "total" => $totalFCount,
                    "totalAmount"=> $totalFAmount,

                ];
                $tcName = collect($data)->first()->emp_name ?? "";
                $tcMobile = collect($data)->first()->tc_mobile ?? "";
                if ($request->footer) {
                    $list["tcName"] = $tcName;
                    $list["tcMobile"] = $tcMobile;
                    $list["footer"] = $footer;
                    $list["totalCount"] = $totalFCount;
                    $list["totalAmount"] = $totalFAmount;
                }
                return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
            }

            $paginator = $data->paginate($perPage);
            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalHolding" => $totalHolding,
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                // "numberOfPages" => $numberOfPages
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | Saf collection
     */
    public function safCollection(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $userJoin = "LEFTJOIN";
            $wardId = $zoneId = $userId = $paymentMode = null;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            if ($request->userJoin) {
                $userJoin = $request->userJoin;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
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
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->is_gbsaf) {
                $isGbsaf = $request->is_gbsaf;
            }

            DB::enableQueryLog();
            $activSaf = PropTransaction::readConnection()->select(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            prop_active_safs.id,
                            prop_active_safs.prop_address,
                            zone_masters.zone_name,
                            'saf' as type,
                            assessment_type,
                            prop_transactions.id AS tran_id,
                            CONCAT('', prop_active_safs.holding_no, '') AS holding_no,
                            (
                                CASE WHEN prop_active_safs.saf_no='' OR prop_active_safs.saf_no IS NULL THEN 'N/A' 
                                ELSE prop_active_safs.saf_no END
                            ) AS saf_no,
                            owner_detail.owner_name,
                            owner_detail.mobile_no,
                            CONCAT(
                                prop_transactions.from_fyear, '(', prop_transactions.from_qtr, ')', ' / ', 
                                prop_transactions.to_fyear, '(', prop_transactions.to_qtr, ')'
                            ) AS from_upto_fy_qtr,
                            prop_transactions.tran_date,
                            prop_transactions.payment_mode AS transaction_mode,
                            prop_transactions.amount,users.name as emp_name,users.id as user_id,
                            prop_transactions.tran_no,prop_cheque_dtls.cheque_no,
                            prop_cheque_dtls.bank_name,prop_cheque_dtls.branch_name
                "),
            )
                ->JOIN("prop_active_safs", "prop_active_safs.id", "prop_transactions.saf_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(owner_name, ', ') AS owner_name, 
                            STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                            prop_active_safs_owners.saf_id 
                        FROM prop_active_safs_owners 
                        JOIN prop_transactions on prop_transactions.saf_id = prop_active_safs_owners.saf_id 
                        WHERE prop_transactions.saf_id IS NOT NULL AND prop_transactions.status in (1, 2) 
                        AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND prop_transactions.user_id = $userId " : "")
                        . ($paymentMode ? " AND upper(prop_transactions.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY prop_active_safs_owners.saf_id 
                        ) AS owner_detail
                        "),
                    function ($join) {
                        $join->on("owner_detail.saf_id", "=", "prop_transactions.saf_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_active_safs.ward_mstr_id")
                ->LEFTJOIN("zone_masters", "zone_masters.id", "prop_active_safs.zone_mstr_id")
                ->$userJoin("users", "users.id", "prop_transactions.user_id")
                ->LEFTJOIN("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
                ->WHERENOTNULL("prop_transactions.saf_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $rejectedSaf = PropTransaction::readConnection()->select(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            prop_rejected_safs.id,
                            prop_rejected_safs.prop_address,
                            zone_masters.zone_name,
                            'saf' as type,
                            assessment_type,
                            prop_transactions.id AS tran_id,
                            CONCAT('', prop_rejected_safs.holding_no, '') AS holding_no,
                            (
                                CASE WHEN prop_rejected_safs.saf_no='' OR prop_rejected_safs.saf_no IS NULL THEN 'N/A' 
                                ELSE prop_rejected_safs.saf_no END
                            ) AS saf_no,
                            owner_detail.owner_name,
                            owner_detail.mobile_no,
                            CONCAT(
                                prop_transactions.from_fyear, '(', prop_transactions.from_qtr, ')', ' / ', 
                                prop_transactions.to_fyear, '(', prop_transactions.to_qtr, ')'
                            ) AS from_upto_fy_qtr,
                            prop_transactions.tran_date,
                            prop_transactions.payment_mode AS transaction_mode,
                            prop_transactions.amount,users.name as emp_name,users.id as user_id,
                            prop_transactions.tran_no,prop_cheque_dtls.cheque_no,
                            prop_cheque_dtls.bank_name,prop_cheque_dtls.branch_name
                "),
            )
                ->JOIN("prop_rejected_safs", "prop_rejected_safs.id", "prop_transactions.saf_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(owner_name, ', ') AS owner_name, 
                            STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                            prop_rejected_safs_owners.saf_id 
                        FROM prop_rejected_safs_owners 
                        JOIN prop_transactions on prop_transactions.saf_id = prop_rejected_safs_owners.saf_id 
                        WHERE prop_transactions.saf_id IS NOT NULL AND prop_transactions.status in (1, 2) 
                        AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND prop_transactions.user_id = $userId " : "")
                        . ($paymentMode ? " AND upper(prop_transactions.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY prop_rejected_safs_owners.saf_id 
                        ) AS owner_detail
                        "),
                    function ($join) {
                        $join->on("owner_detail.saf_id", "=", "prop_transactions.saf_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_rejected_safs.ward_mstr_id")
                ->LEFTJOIN("zone_masters", "zone_masters.id", "prop_rejected_safs.zone_mstr_id")
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->LEFTJOIN("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
                ->WHERENOTNULL("prop_transactions.saf_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $saf = PropTransaction::readConnection()->select(
                DB::raw("
                            ulb_ward_masters.ward_name AS ward_no,
                            prop_safs.id,
                            prop_safs.prop_address,
                            zone_masters.zone_name,
                            'saf' as type,
                            assessment_type,
                            prop_transactions.id AS tran_id,
                            CONCAT('', prop_safs.holding_no, '') AS holding_no,
                            (
                                CASE WHEN prop_safs.saf_no='' OR prop_safs.saf_no IS NULL THEN 'N/A' 
                                ELSE prop_safs.saf_no END
                            ) AS saf_no,
                            owner_detail.owner_name,
                            owner_detail.mobile_no,
                            CONCAT(
                                prop_transactions.from_fyear, '(', prop_transactions.from_qtr, ')', ' / ', 
                                prop_transactions.to_fyear, '(', prop_transactions.to_qtr, ')'
                            ) AS from_upto_fy_qtr,
                            prop_transactions.tran_date,
                            prop_transactions.payment_mode AS transaction_mode,
                            prop_transactions.amount,users.name as emp_name,users.id as user_id,
                            prop_transactions.tran_no,prop_cheque_dtls.cheque_no,
                            prop_cheque_dtls.bank_name,prop_cheque_dtls.branch_name
                "),
            )
                ->JOIN("prop_safs", "prop_safs.id", "prop_transactions.saf_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT STRING_AGG(owner_name, ', ') AS owner_name, 
                            STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                            prop_safs_owners.saf_id 
                        FROM prop_safs_owners 
                        JOIN prop_transactions on prop_transactions.saf_id = prop_safs_owners.saf_id 
                        WHERE prop_transactions.saf_id IS NOT NULL AND prop_transactions.status in (1, 2) 
                        AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " .
                        ($userId ? " AND prop_transactions.user_id = $userId " : "")
                        . ($paymentMode ? " AND upper(prop_transactions.payment_mode) = upper('$paymentMode') " : "")
                        . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId" : "")
                        . "
                        GROUP BY prop_safs_owners.saf_id 
                        ) AS owner_detail
                        "),
                    function ($join) {
                        $join->on("owner_detail.saf_id", "=", "prop_transactions.saf_id");
                    }
                )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_safs.ward_mstr_id")
                ->LEFTJOIN("zone_masters", "zone_masters.id", "prop_safs.zone_mstr_id")
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->LEFTJOIN("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
                ->WHERENOTNULL("prop_transactions.saf_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            if ($wardId) {
                $activSaf = $activSaf->where("ulb_ward_masters.id", $wardId);
                $rejectedSaf = $rejectedSaf->where("ulb_ward_masters.id", $wardId);
                $saf = $saf->where("ulb_ward_masters.id", $wardId);
            }
            if ($zoneId) {
                $activSaf = $activSaf->where("zone_masters.id", $zoneId);
                $rejectedSaf = $rejectedSaf->where("zone_masters.id", $zoneId);
                $saf = $saf->where("zone_masters.id", $zoneId);
            }
            if ($userId) {
                $activSaf = $activSaf->where("prop_transactions.user_id", $userId);
                $rejectedSaf = $rejectedSaf->where("prop_transactions.user_id", $userId);
                $saf = $saf->where("prop_transactions.user_id", $userId);
            }
            if ($paymentMode) {
                $activSaf = $activSaf->where(DB::raw("prop_transactions.payment_mode"), $paymentMode);
                $rejectedSaf = $rejectedSaf->where(DB::raw("prop_transactions.payment_mode"), $paymentMode);
                $saf = $saf->where(DB::raw("prop_transactions.payment_mode"), $paymentMode);
            }
            if ($ulbId) {
                $activSaf = $activSaf->where("prop_transactions.ulb_id", $ulbId);
                $rejectedSaf = $rejectedSaf->where("prop_transactions.ulb_id", $ulbId);
                $saf = $saf->where("prop_transactions.ulb_id", $ulbId);
            }

            $data = $activSaf->union($rejectedSaf)->union($saf);
            $data2 = $data;
            $totalAmount = $data2->sum("amount");
            $perPage = $request->perPage ? $request->perPage : 5;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            if ($request->all) {
                $data = $data->get();
                $mode = collect($data)->unique("transaction_mode")->pluck("transaction_mode");
                $totalFAmount = collect($data)->unique("tran_id")->sum("amount");
                $totalFCount = collect($data)->unique("tran_id")->count("tran_id");
                $footer = $mode->map(function ($val) use ($data) {
                    $count = $data->where("transaction_mode", $val)->unique("tran_id")->count("tran_id");
                    $amount = $data->where("transaction_mode", $val)->unique("tran_id")->sum("amount");
                    return ['mode' => $val, "count" => $count, "amount" => $amount];
                });
                $list = [
                    "data" => $data,
                    "total" => $totalFCount,
                    "totalAmount"=> $totalFAmount,

                ];
                $tcName = collect($data)->first()->emp_name ?? "";
                $tcMobile = collect($data)->first()->tc_mobile ?? "";
                if ($request->footer) {
                    $list["tcName"] = $tcName;
                    $list["tcMobile"] = $tcMobile;
                    $list["footer"] = $footer;
                    $list["totalCount"] = $totalFCount;
                    $list["totalAmount"] = $totalFAmount;
                };
                return responseMsgs(true, "", remove_null($list), $apiId, $version, $queryRunTime, $action, $deviceId);
            }
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "totalAmount" => $totalAmount,
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | saf prop Individual demand collection
     */
    public function safPropIndividualDemandAndCollection(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $key = null;
            $fiYear = getFY();
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            if ($request->key) {
                $key = $request->key;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $data = PropProperty::readConnection()->select(
                DB::raw("ulb_ward_masters.ward_name as ward_no,
                        prop_properties.holding_no,
                        (
                            CASE WHEN prop_properties.new_holding_no IS NULL OR prop_properties.new_holding_no='' THEN 'N/A' 
                            ELSE prop_properties.new_holding_no END
                        ) AS new_holding_no,
                        (
                            CASE WHEN prop_safs.saf_no IS NULL  THEN 'N/A' 
                            ELSE prop_safs.saf_no END
                        ) AS saf_no,
                        owner_detail.owner_name,
                        owner_detail.mobile_no,
                        prop_properties.prop_address,
                        (
                            CASE WHEN prop_safs.assessment_type IS NULL  THEN 'N/A' 
                            ELSE prop_safs.assessment_type END
                        ) AS assessment_type,
                        (
                            CASE WHEN floor_details.usage_type IS NULL THEN 'N/A' 
                            ELSE floor_details.usage_type END
                        ) AS usage_type,
                        (
                            CASE WHEN floor_details.construction_type IS NULL 
                            THEN 'N/A' ELSE floor_details.construction_type END
                        ) AS construction_type,
                        (
                            CASE WHEN demands.arrear_demand IS NULL THEN '0.00'
                            ELSE demands.arrear_demand END
                        ) AS arrear_demand,
                        (
                            CASE WHEN demands.current_demand IS NULL THEN '0.00' 
                            ELSE demands.current_demand END
                        ) AS current_demand,
                        COALESCE(demands.arrear_demand, 0)
                            +COALESCE(demands.current_demand, 0)
                            AS total_demand,                        
                        (
                            CASE WHEN collection.arrear_collection IS NULL THEN '0.00' 
                            ELSE collection.arrear_collection END
                        ) AS arrear_collection,
                        (
                            CASE WHEN collection.current_collection IS NULL THEN '0.00' 
                            ELSE collection.current_collection END
                        ) AS current_collection,
                        COALESCE(collection.arrear_collection, 0)
                            +COALESCE(collection.current_collection, 0) 
                            AS total_collection,
                        (
                            CASE WHEN tbl_penalty.penalty IS NULL THEN '0.00'
                            ELSE tbl_penalty.penalty END
                        ) AS penalty,
                        (
                            CASE WHEN tbl_rebate.rebate IS NULL THEN '0.00' 
                            ELSE tbl_rebate.rebate END
                        ) AS rebate,
                        '0.00' AS advance,
                        '0.00' AS adjust,
                        (
                            COALESCE(demands.arrear_demand, 0)+COALESCE(demands.current_demand, 0)
                        )
                        -
                        (
                           COALESCE(collection.arrear_collection, 0)+COALESCE(collection.current_collection, 0)
                        ) AS total_due
                "),
            )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_properties.ward_mstr_id")
                ->LEFTJOIN("prop_safs", "prop_safs.id", "prop_properties.saf_id")
                ->JOIN(
                    DB::RAW("(
                        SELECT 
                            prop_owners.property_id, 
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name, 
                            STRING_AGG(prop_owners.mobile_no::TEXT, ',') AS mobile_no 
                        FROM prop_owners
                        WHERE prop_owners.status=1
                        GROUP BY prop_owners.property_id
                        ) AS owner_detail
                        "),
                    function ($join) {
                        $join->on("owner_detail.property_id", "=", "prop_properties.id");
                    }
                )
                ->LEFTJOIN(
                    DB::RAW("(
                        SELECT 
                            prop_floors.property_id, 
                            STRING_AGG(ref_prop_usage_types.usage_type, ',') AS usage_type, 
                            STRING_AGG(ref_prop_construction_types.construction_type, ',') AS construction_type 
                        FROM prop_floors
                        INNER JOIN ref_prop_usage_types ON ref_prop_usage_types.id=prop_floors.usage_type_mstr_id
                        INNER JOIN ref_prop_construction_types ON ref_prop_construction_types.id=prop_floors.const_type_mstr_id
                        WHERE prop_floors.status=1 
                        GROUP BY prop_floors.property_id
                        )AS floor_details
                        "),
                    function ($join) {
                        $join->on("floor_details.property_id", "=", "prop_properties.id");
                    }
                )
                ->LEFTJOIN(
                    DB::RAW("(
                    SELECT 
                        property_id, 
                        SUM(CASE WHEN fyear < '$fiYear' THEN amount ELSE 0 END) AS arrear_demand,
                        SUM(CASE WHEN fyear = '$fiYear' THEN amount ELSE 0 END) AS current_demand
                    FROM prop_demands 
                    WHERE status=1 AND paid_status IN (0,1)  
                    GROUP BY property_id
                    )AS demands
                    "),
                    function ($join) {
                        $join->on("demands.property_id", "=", "prop_properties.id");
                    }
                )
                ->LEFTJOIN(
                    DB::RAW("(
                    SELECT 
                        property_id, 
                        SUM(CASE WHEN fyear < '$fiYear' THEN amount ELSE 0 END) AS arrear_collection,
                        SUM(CASE WHEN fyear = '$fiYear' THEN amount ELSE 0 END) AS current_collection
                    FROM prop_demands 
                    WHERE status=1 AND paid_status=1 
                    GROUP BY property_id
                    )AS collection
                    "),
                    function ($join) {
                        $join->on("collection.property_id", "=", "prop_properties.id");
                    }
                )
                ->LEFTJOIN(
                    DB::RAW("(
                    SELECT
                        prop_transactions.property_id AS property_id,
                        SUM(prop_penaltyrebates.amount) AS penalty
                    FROM prop_penaltyrebates
                    INNER JOIN prop_transactions ON prop_transactions.id=prop_penaltyrebates.tran_id
                    WHERE prop_transactions.property_id is not null 
                            AND prop_penaltyrebates.status=1 
                            AND prop_penaltyrebates.is_rebate = FALSE
                    GROUP BY prop_transactions.property_id
                    )AS tbl_penalty
                    "),
                    function ($join) {
                        $join->on("tbl_penalty.property_id", "=", "prop_properties.id");
                    }
                )
                ->LEFTJOIN(
                    DB::RAW("(
                    SELECT
                        prop_transactions.property_id AS property_id,
                        SUM(prop_penaltyrebates.amount) AS rebate
                    FROM prop_penaltyrebates
                    INNER JOIN prop_transactions ON prop_transactions.id=prop_penaltyrebates.tran_id
                    WHERE prop_transactions.property_id is not null 
                            AND prop_penaltyrebates.status=1 
                            AND prop_penaltyrebates.is_rebate=true
                    GROUP BY prop_transactions.property_id
                    )AS tbl_rebate
                    "),
                    function ($join) {
                        $join->on("tbl_rebate.property_id", "=", "prop_properties.id");
                    }
                )
                ->WHERE("prop_properties.status", 1)
                ->WHERE(function ($where) use ($key) {
                    $where->ORWHERE('prop_properties.holding_no', 'ILIKE', '%' . $key . '%')
                        ->ORWHERE('prop_properties.new_holding_no', 'ILIKE', '%' . $key . '%')
                        ->ORWHERE('prop_safs.saf_no', 'ILIKE', '%' . $key . '%')
                        ->ORWHERE('owner_detail.owner_name', 'ILIKE', '%' . $key . '%')
                        ->ORWHERE('owner_detail.mobile_no', 'ILIKE', '%' . $key . '%')
                        ->ORWHERE('prop_properties.prop_address', 'ILIKE', '%' . $key . '%');
                });
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($ulbId) {
                $data = $data->where("prop_properties.ulb_id", $ulbId);
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
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | level wise pending form
     */
    public function levelwisependingform(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $data = WfRole::SELECT(
                "wf_roles.id",
                "wf_roles.role_name",
                DB::RAW("COUNT(prop_active_safs.id) AS total")
            )
                ->JOIN(DB::RAW("(
                                        SELECT distinct(wf_role_id) as wf_role_id
                                        FROM wf_workflowrolemaps 
                                        WHERE  wf_workflowrolemaps.is_suspended = false AND (forward_role_id IS NOT NULL OR backward_role_id IS NOT NULL)
                                            AND workflow_id IN(3,4,5) 
                                        GROUP BY wf_role_id 
                                ) AS roles
                    "), function ($join) {
                    $join->on("wf_roles.id", "roles.wf_role_id");
                })
                ->LEFTJOIN("prop_active_safs", function ($join) use ($ulbId) {
                    $join->ON("prop_active_safs.current_role", "roles.wf_role_id")
                        ->WHERE("prop_active_safs.ulb_id", $ulbId)
                        ->WHERE(function ($where) {
                            $where->ORWHERE("prop_active_safs.payment_status", "<>", 0)
                                ->ORWHERENOTNULL("prop_active_safs.payment_status");
                        });
                })
                ->GROUPBY(["wf_roles.id", "wf_roles.role_name"])
                // ->UNION(
                //     PropActiveSaf::SELECT(
                //         DB::RAW("8 AS id, 'JSK' AS role_name,
                //                 COUNT(prop_active_safs.id)")
                //     )
                //         ->WHERE("prop_active_safs.ulb_id", $ulbId)
                //         ->WHERENOTNULL("prop_active_safs.user_id")
                //         ->WHERE(function ($where) {
                //             $where->WHERE("prop_active_safs.payment_status", "=", 0)
                //                 ->ORWHERENULL("prop_active_safs.payment_status");
                //         })
                // )
                ->GET();
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | level form details
     */
    public function levelformdetail(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $roleId = $roleId2 = $userId = null;
            $mWardPermission = collect([]);
            $perPage = $request->perPage ? $request->perPage : 5;

            $safWorkFlow = Config::get('workflow-constants.SAF_WORKFLOW_ID');
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->roleId) {
                $roleId = $request->roleId;
            }
            if ($request->userId) {
                $userId = $request->userId;
                $roleId2 = ($this->_common->getUserRoll($userId, $ulbId, $safWorkFlow))->role_id ?? 0;
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
            $mWardIds = explode(',', ($mWardIds ? $mWardIds : "0"));

            $data = PropActiveSaf::readConnection()->SELECT(
                DB::RAW("wf_roles.id AS role_id, wf_roles.role_name,
                    prop_active_safs.id, prop_active_safs.saf_no, prop_active_safs.prop_address,prop_type_mstr_id,property_type,
                    ward_name as ward_no, 
                    owner.owner_name, owner.mobile_no")
            )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_active_safs.ward_mstr_id")
                ->JOIN("ref_prop_types", "ref_prop_types.id", "prop_active_safs.prop_type_mstr_id")
                ->LEFTJOIN(DB::RAW("( 
                                SELECT DISTINCT(prop_active_safs_owners.saf_id) AS saf_id,STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no
                                FROM prop_active_safs_owners
                                JOIN prop_active_safs ON prop_active_safs.id = prop_active_safs_owners.saf_id
                                WHERE prop_active_safs_owners.status = 1 
                                    AND prop_active_safs.ulb_id = $ulbId
                                GROUP BY prop_active_safs_owners.saf_id
                                ) AS owner
                                "), function ($join) {
                    $join->on("owner.saf_id", "=", "prop_active_safs.id");
                });
            if ($roleId == 8) {
                $data = $data->LEFTJOIN("wf_roles", "wf_roles.id", "prop_active_safs.current_role")
                    ->WHERENOTNULL("prop_active_safs.user_id")
                    ->WHERE(function ($where) {
                        $where->WHERE("prop_active_safs.payment_status", "=", 0)
                            ->ORWHERENULL("prop_active_safs.payment_status");
                    });
            } else {
                $data = $data->JOIN("wf_roles", "wf_roles.id", "prop_active_safs.current_role")
                    ->WHERE("wf_roles.id", $roleId);
            }
            $data = $data->WHERE("prop_active_safs.ulb_id", $ulbId);
            if (!in_array($roleId, [11, 8]) && $userId) {
                $data = $data->WHEREIN("prop_active_safs.ward_mstr_id", $mWardIds);
            }

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

    public function levelUserPending(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;

        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $roleId = $roleId2 = $userId = null;
            $joins = "join";

            $safWorkFlow = Config::get('workflow-constants.SAF_WORKFLOW_ID');
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->roleId) {
                $roleId = $request->roleId;
            }
            if (($request->roleId && $request->userId) && ($roleId != $roleId2)) {
                throw new Exception("Invalid RoleId Pass");
            }
            if (in_array($roleId, [11, 8])) {
                $joins = "leftjoin";
            }


            $data = PropActiveSaf::readConnection()->SELECT(
                DB::RAW(
                    "count(prop_active_safs.id),
                    users_role.user_id ,
                    users_role.name as user_name,
                    users_role.wf_role_id as role_id,
                    users_role.role_name"
                )
            )
                ->$joins(
                    DB::RAW("(
                        select wf_role_id,user_id,user_name,name,role_name,concat('{',ward_ids,'}') as ward_ids
                        from (
                            select wf_roleusermaps.wf_role_id,wf_roleusermaps.user_id,
                            users.user_name,users.name, wf_roles.role_name,
                            string_agg(wf_ward_users.ward_id::text,',') as ward_ids
                            from wf_roleusermaps 
                            join wf_roles on wf_roles.id = wf_roleusermaps.wf_role_id
                                AND wf_roles.is_suspended = false
                            join users on users.id = wf_roleusermaps.user_id
                            left join wf_ward_users on wf_ward_users.user_id = wf_roleusermaps.user_id and wf_ward_users.is_suspended = false
                            where wf_roleusermaps.wf_role_id =$roleId
                                AND wf_roleusermaps.is_suspended = false
                            and users.id <> 76
                            and users.id <> 72
                            and users.id <> 175
                            and users.suspended = false
                            group by wf_roleusermaps.wf_role_id,wf_roleusermaps.user_id,users.user_name,users.name,wf_roles.role_name
                        )role_user_ward
                    ) users_role
                    "),
                    function ($join) use ($joins) {
                        if ($joins == "join") {
                            $join->on("users_role.wf_role_id", "=", "prop_active_safs.current_role")
                                ->where("prop_active_safs.ward_mstr_id", DB::raw("ANY (ward_ids::int[])"));
                        } else {
                            $join->on(DB::raw("1"), DB::raw("1"));
                        }
                    }
                )
                ->WHERE("prop_active_safs.ulb_id", $ulbId)
                ->groupBy(["users_role.user_id", "users_role.user_name","users_role.name", "users_role.wf_role_id", "users_role.role_name"]);

            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            // dd(DB::getQueryLog());
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    public function userWiseWardWireLevelPending(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $roleId = $roleId2 = $userId = null;
            $mWardPermission = collect([]);

            $safWorkFlow = Config::get('workflow-constants.SAF_WORKFLOW_ID');
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->roleId) {
                $roleId = $request->roleId;
            }
            if ($request->userId) {
                $userId = $request->userId;
                $roleId2 = ($this->_common->getUserRoll($userId, $ulbId, $safWorkFlow))->role_id ?? 0;
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
            $mWardIds = explode(',', ($mWardIds ? $mWardIds : "0"));

            $data = $this->_DB_READ->table("ulb_ward_masters")->SELECT(
                DB::RAW(" DISTINCT(ward_name) as ward_no, COUNT(prop_active_safs.id) AS total")
            )
                ->LEFTJOIN("prop_active_safs", "prop_active_safs.ward_mstr_id", "ulb_ward_masters.id");
            if ($roleId == 8) {
                $data = $data->LEFTJOIN("wf_roles", "wf_roles.id", "prop_active_safs.current_role")
                    ->WHERENOTNULL("prop_active_safs.user_id")
                    ->WHERE(function ($where) {
                        $where->WHERE("prop_active_safs.payment_status", "=", 0)
                            ->ORWHERENULL("prop_active_safs.payment_status");
                    });
            } else {
                $data = $data->JOIN("wf_roles", "wf_roles.id", "prop_active_safs.current_role")
                    ->WHERE("wf_roles.id", $roleId);
            }
            if (!in_array($roleId, [11, 8]) && $userId) {
                $data = $data->WHEREIN("prop_active_safs.ward_mstr_id", $mWardIds);
            }
            $data = $data->WHERE("prop_active_safs.ulb_id", $ulbId);
            $data = $data->groupBy(["ward_name"]);

            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            
            $data2 = $data;
            $total = $data2->get();
            $paginator = $data->paginate($perPage);
            $list = [

                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                "totalSaf" => collect($total)->sum("total"),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }


    public function safSamFamGeotagging(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            $where = " WHERE status = 1 AND  ulb_id = $ulbId
                        AND created_at::date BETWEEN '$fromDate' AND '$uptoDate'";
            if ($wardId) {
                $where .= " AND ward_mstr_id = $wardId ";
            }
            $sql = "
                WITH saf AS (
                    SELECT 
                    distinct saf.* 
                    FROM(
                            (
                                select prop_active_safs.id as id, 
                                    prop_active_safs.ward_mstr_id,
                                    prop_active_safs.parked
                                from prop_active_safs                                     
                                $where
                            )
                            UNION (    
                                select prop_safs.id as id, 
                                    prop_safs.ward_mstr_id,
                                    prop_safs.parked
                                from prop_safs
                                $where
                
                            )
                            UNION (    
                                select prop_rejected_safs.id as id, 
                                    prop_rejected_safs.ward_mstr_id,
                                    prop_rejected_safs.parked
                                from prop_rejected_safs
                                $where
                            )
                    ) saf
                    join prop_transactions on prop_transactions.saf_id = saf.id 
                    and prop_transactions.status in(1,2)
                    GROUP BY saf.id,ward_mstr_id,parked
                ),
                memos AS (
                        select prop_saf_memo_dtls.saf_id,
                            prop_saf_memo_dtls.memo_type,
                            prop_saf_memo_dtls.created_at::date as created_at
                        FROM prop_saf_memo_dtls
                        JOIN saf ON saf.id = prop_saf_memo_dtls.saf_id
                        
                ),
                geotaging as (
                    select prop_saf_geotag_uploads.saf_id
                    from prop_saf_geotag_uploads
                    join saf on saf.id = prop_saf_geotag_uploads.saf_id
                    where prop_saf_geotag_uploads.status = 1
                    group by prop_saf_geotag_uploads.saf_id
                )
                
                select 
                    count(distinct(saf.id)) total_saf,
                    count(distinct( case when memos.memo_type = 'SAM' then memos.saf_id else null end)) as total_sam,
                    count( distinct(case when memos.memo_type = 'FAM' then memos.saf_id else null end)) as total_fam,
                    count( distinct(case when saf.parked = true then memos.saf_id else null end)) as total_btc,
                    count(distinct(geotaging.saf_id)) total_geotaging,
                    COALESCE(count(distinct(saf.id)) -  count(distinct( case when memos.memo_type = 'SAM' then memos.saf_id else null end))) as pending_sam,
                    COALESCE(count(distinct(saf.id)) -  count(distinct( case when memos.memo_type = 'FAM' then memos.saf_id else null end))) as pending_fam,
                    ward_name as ward_no
                    
                from saf
                join ulb_ward_masters on ulb_ward_masters.id = saf.ward_mstr_id
                LEFT JOIN memos ON memos.saf_id = saf.id
                LEFT JOIN geotaging ON geotaging.saf_id = saf.id
                group by ward_name
                ORDER BY  ward_name
            ";
            $data = $this->_DB_READ->select($sql);
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));

            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function PropPaymentModeWiseSummery(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId =   $zoneId = $userId = null;
            $paymentMode = null;
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            $collection = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.property_id)) AS holding_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN(DB::raw("(
                    select prop_transactions.*,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_transactions
                    join prop_properties on prop_properties.id = prop_transactions.property_id
                    where prop_transactions.tran_date between '$fromDate' and '$uptoDate'
                    )prop_transactions
                    "), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId, $wardId, $zoneId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode) "))
                        ->WHERENOTNULL("prop_transactions.property_id")
                        // ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                    if ($wardId) {
                        $sub = $sub->WHERE("prop_transactions.ward_mstr_id", $wardId);
                    }
                    if ($zoneId) {
                        $sub = $sub->WHERE("prop_transactions.zone_mstr_id", $zoneId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $collection = $collection->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $refund = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.property_id)) AS holding_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN(DB::raw("(
                    select prop_transactions.*,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_transactions
                    join prop_properties on prop_properties.id = prop_transactions.property_id
                    where prop_transactions.tran_date between '$fromDate' and '$uptoDate'
                    )prop_transactions
                    "), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId, $wardId, $zoneId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode) "))
                        ->WHERENOTNULL("prop_transactions.property_id")
                        ->WHERENOTIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                    if ($wardId) {
                        $sub = $sub->WHERE("prop_transactions.ward_mstr_id", $wardId);
                    }
                    if ($zoneId) {
                        $sub = $sub->WHERE("prop_transactions.zone_mstr_id", $zoneId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $refund = $refund->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $doorToDoor = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                    WHERE UPPER(prop_transactions.payment_mode) <> UPPER('ONLINE')
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.property_id)) AS holding_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN(DB::RAW("(
                                     SELECT prop_transactions.* ,collecter.*,
                                        prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                                     FROM prop_transactions
                                     JOIN (
                                        
                                            SELECT DISTINCT wf_roleusermaps.user_id as role_user_id
                                            FROM wf_roles
                                            JOIN wf_roleusermaps ON wf_roleusermaps.wf_role_id = wf_roles.id 
                                                AND wf_roleusermaps.is_suspended = FALSE
                                            JOIN wf_workflowrolemaps ON wf_workflowrolemaps.wf_role_id = wf_roleusermaps.wf_role_id
                                                AND wf_workflowrolemaps.is_suspended = FALSE
                                            JOIN wf_workflows ON wf_workflows.id = wf_workflowrolemaps.workflow_id AND wf_workflows.is_suspended = FALSE 
                                            JOIN ulb_masters ON ulb_masters.id = wf_workflows.ulb_id
                                            WHERE wf_roles.is_suspended = FALSE 
                                                AND wf_workflows.ulb_id = 2
                                                AND wf_roles.id not in (8,108)
                                                --AND wf_workflows.id in (3,4,5)
                                            GROUP BY wf_roleusermaps.user_id
                                            ORDER BY wf_roleusermaps.user_id
                                     ) collecter on prop_transactions.user_id  = collecter.role_user_id
                                     join prop_properties on prop_properties.id = prop_transactions.property_id
                                ) prop_transactions"), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId, $wardId, $zoneId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode)"))
                        ->WHERENOTNULL("prop_transactions.property_id")
                        ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                    if ($wardId) {
                        $sub = $sub->WHERE("prop_transactions.ward_mstr_id", $wardId);
                    }
                    if ($zoneId) {
                        $sub = $sub->WHERE("prop_transactions.zone_mstr_id", $zoneId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $doorToDoor = $doorToDoor->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $jsk = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                    WHERE UPPER(prop_transactions.payment_mode) <> UPPER('ONLINE')
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.property_id)) AS holding_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN(DB::RAW("(
                                        SELECT prop_transactions.* ,collecter.*,
                                        prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                                        FROM prop_transactions
                                        JOIN (
                                            
                                                SELECT DISTINCT wf_roleusermaps.user_id as role_user_id
                                                FROM wf_roles
                                                JOIN wf_roleusermaps ON wf_roleusermaps.wf_role_id = wf_roles.id 
                                                    AND wf_roleusermaps.is_suspended = FALSE
                                                JOIN wf_workflowrolemaps ON wf_workflowrolemaps.wf_role_id = wf_roleusermaps.wf_role_id
                                                    AND wf_workflowrolemaps.is_suspended = FALSE
                                                JOIN wf_workflows ON wf_workflows.id = wf_workflowrolemaps.workflow_id AND wf_workflows.is_suspended = FALSE 
                                                JOIN ulb_masters ON ulb_masters.id = wf_workflows.ulb_id
                                                WHERE wf_roles.is_suspended = FALSE 
                                                    AND wf_workflows.ulb_id = 2
                                                    AND wf_roles.id in (8,108)
                                                   -- AND wf_workflows.id in (3,4,5)
                                                GROUP BY wf_roleusermaps.user_id
                                                ORDER BY wf_roleusermaps.user_id
                                        ) collecter on prop_transactions.user_id  = collecter.role_user_id
                                        join prop_properties on prop_properties.id = prop_transactions.property_id
                                    ) prop_transactions"), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId, $wardId, $zoneId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode)"))
                        ->WHERENOTNULL("prop_transactions.property_id")
                        ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                    if ($wardId) {
                        $sub = $sub->WHERE("prop_transactions.ward_mstr_id", $wardId);
                    }
                    if ($zoneId) {
                        $sub = $sub->WHERE("prop_transactions.zone_mstr_id", $zoneId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $jsk = $jsk->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }

            $collection = $collection->get();
            $refund     = $refund->get();
            $doorToDoor = $doorToDoor->get();
            $jsk        = $jsk->get();

            $totalCollection = $collection->sum("amount");
            $totalHolding = $collection->sum("holding_count");
            $totalTran = $collection->sum("tran_count");

            $totalCollectionRefund = $refund->sum("amount");
            $totalHoldingRefund = $refund->sum("holding_count");
            $totalTranRefund = $refund->sum("tran_count");

            $totalCollectionDoor = $doorToDoor->sum("amount");
            $totalHoldingDoor = $doorToDoor->sum("holding_count");
            $totalTranDoor = $doorToDoor->sum("tran_count");

            $totalCollectionJsk = $jsk->sum("amount");
            $totalHoldingJsk = $jsk->sum("holding_count");
            $totalTranJsk = $jsk->sum("tran_count");

            $collection[] = [
                "transaction_mode" => "Total Collection",
                "holding_count"    => $totalHolding,
                "tran_count"       => $totalTran,
                "amount"           => $totalCollection
            ];
            $funal["collection"] = $collection;
            $refund[] = [
                "transaction_mode" => "Total Refund",
                "holding_count"    => $totalHoldingRefund,
                "tran_count"       => $totalTranRefund,
                "amount"           => $totalCollectionRefund
            ];
            $funal["refund"] = $refund;
            $funal["netCollection"][] = [
                "transaction_mode" => "Net Collection",
                "holding_count"    => $totalHolding - $totalHoldingRefund,
                "tran_count"       => $totalTran - $totalTranRefund,
                "amount"           => $totalCollection - $totalCollectionRefund
            ];

            $doorToDoor[] = [
                "transaction_mode" => "Total Door To Door",
                "holding_count"    => $totalHoldingDoor,
                "tran_count"       => $totalTranDoor,
                "amount"           => $totalCollectionDoor,
            ];
            $funal["doorToDoor"] = $doorToDoor;

            $jsk[] = [
                "transaction_mode" => "Total JSK",
                "holding_count"    => $totalHoldingJsk,
                "tran_count"       => $totalTranJsk,
                "amount"           => $totalCollectionJsk,
            ];
            $funal["jsk"] = $jsk;
            // $funal["query"] = DB::getQueryLog();
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $funal, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function SafPaymentModeWiseSummery(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = null;
            $userId = null;
            $paymentMode = null;
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            $collection = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.saf_id)) AS saf_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN("prop_transactions", function ($join) use ($fromDate, $uptoDate, $userId, $ulbId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode) "))
                        ->WHERENOTNULL("prop_transactions.saf_id")
                        ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $collection = $collection->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $refund = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.saf_id)) AS saf_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN("prop_transactions", function ($join) use ($fromDate, $uptoDate, $userId, $ulbId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode) "))
                        ->WHERENOTNULL("prop_transactions.saf_id")
                        ->WHERENOTIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $refund = $refund->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $doorToDoor = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                    WHERE UPPER(prop_transactions.payment_mode) <> UPPER('ONLINE')
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.saf_id)) AS saf_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN(DB::RAW("(
                                     SELECT * 
                                     FROM prop_transactions
                                     JOIN (
                                        
                                            SELECT wf_roleusermaps.user_id as role_user_id
                                            FROM wf_roles
                                            JOIN wf_roleusermaps ON wf_roleusermaps.wf_role_id = wf_roles.id 
                                                AND wf_roleusermaps.is_suspended = FALSE
                                            JOIN wf_workflowrolemaps ON wf_workflowrolemaps.wf_role_id = wf_roleusermaps.wf_role_id
                                                AND wf_workflowrolemaps.is_suspended = FALSE
                                            JOIN wf_workflows ON wf_workflows.id = wf_workflowrolemaps.workflow_id AND wf_workflows.is_suspended = FALSE 
                                            JOIN ulb_masters ON ulb_masters.id = wf_workflows.ulb_id
                                            WHERE wf_roles.is_suspended = FALSE 
                                                AND wf_workflows.ulb_id = 2
                                                AND wf_roles.id not in (8,108)
                                                --AND wf_workflows.id in (3,4,5)
                                            GROUP BY wf_roleusermaps.user_id
                                            ORDER BY wf_roleusermaps.user_id
                                     ) collecter on prop_transactions.user_id  = collecter.role_user_id
                                ) prop_transactions"), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode)"))
                        ->WHERENOTNULL("prop_transactions.saf_id")
                        ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $doorToDoor = $doorToDoor->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $jsk = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(prop_transactions.payment_mode)) AS mode 
                                    FROM prop_transactions
                                    WHERE UPPER(prop_transactions.payment_mode) <> UPPER('ONLINE')
                                ) payment_modes")
            )
                ->select(
                    DB::raw("payment_modes.mode AS transaction_mode,
                        COUNT(DISTINCT(prop_transactions.saf_id)) AS saf_count,
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM(COALESCE(prop_transactions.amount,0)) AS amount
                        ")
                )
                ->LEFTJOIN(DB::RAW("(
                                        SELECT * 
                                        FROM prop_transactions
                                        JOIN (
                                            
                                                SELECT wf_roleusermaps.user_id as role_user_id
                                                FROM wf_roles
                                                JOIN wf_roleusermaps ON wf_roleusermaps.wf_role_id = wf_roles.id 
                                                    AND wf_roleusermaps.is_suspended = FALSE
                                                JOIN wf_workflowrolemaps ON wf_workflowrolemaps.wf_role_id = wf_roleusermaps.wf_role_id
                                                    AND wf_workflowrolemaps.is_suspended = FALSE
                                                JOIN wf_workflows ON wf_workflows.id = wf_workflowrolemaps.workflow_id AND wf_workflows.is_suspended = FALSE 
                                                JOIN ulb_masters ON ulb_masters.id = wf_workflows.ulb_id
                                                WHERE wf_roles.is_suspended = FALSE 
                                                    AND wf_workflows.ulb_id = 2
                                                    AND wf_roles.id in (8,108)
                                                    --AND wf_workflows.id in (3,4,5)
                                                GROUP BY wf_roleusermaps.user_id
                                                ORDER BY wf_roleusermaps.user_id
                                        ) collecter on prop_transactions.user_id  = collecter.role_user_id
                                    ) prop_transactions"), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER(payment_modes.mode)"))
                        ->WHERENOTNULL("prop_transactions.saf_id")
                        ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("payment_modes.mode");
            if ($paymentMode) {
                $jsk = $jsk->where(DB::raw("upper(payment_modes.mode)"), $paymentMode);
            }
            $assestmentType = $this->_DB_READ->table(
                DB::raw("(SELECT DISTINCT(UPPER(assessment_type)) AS mode 
                                    FROM (
                                            (
                                                select
                                                    distinct(
                                                        CASE WHEN assessment_type ILIKE '%MUTATION%' THEN 'MUTATION' 
                                                        ELSE UPPER(assessment_type)   
                                                        END 
                                                    ) AS assessment_type
                                                from prop_active_safs
                                            )
                                            union(
                                                select
                                                    distinct(
                                                        CASE WHEN assessment_type ILIKE '%MUTATION%' THEN 'MUTATION' 
                                                        ELSE UPPER(assessment_type)   
                                                        END 
                                                    ) AS assessment_type
                                                from prop_rejected_safs
                                            )
                                            union(
                                                    select
                                                        distinct(
                                                            CASE WHEN assessment_type ILIKE '%MUTATION%' THEN 'MUTATION' 
                                                            ELSE UPPER(assessment_type)   
                                                            END 
                                                        ) AS assessment_type
                                                    from prop_safs
                                            )
                                    )assesment_type
                                ) assesment_type")
            )
                ->select(
                    DB::raw("
                        CASE WHEN assesment_type.mode ILIKE '%MUTATION%' THEN 'MUTATION' 
                            ELSE assesment_type.mode 
                            END AS transaction_mode,
                        CASE WHEN assesment_type.mode ILIKE '%MUTATION%' THEN COUNT(DISTINCT(prop_transactions.saf_id)) 
                            ELSE COUNT(DISTINCT(prop_transactions.saf_id))
                            END AS saf_count,
                        CASE WHEN assesment_type.mode ILIKE '%MUTATION%' THEN COUNT(prop_transactions.id) 
                            ELSE COUNT(prop_transactions.id)
                            END AS tran_count, 
                        CASE WHEN assesment_type.mode ILIKE '%MUTATION%' THEN SUM(COALESCE(prop_transactions.amount,0)) 
                            ELSE SUM(COALESCE(prop_transactions.amount,0))
                            END AS amount
                        ")
                )
                ->LEFTJOIN(DB::RAW("(
                                        SELECT * 
                                        FROM(
                                            (
                                                SELECT prop_transactions.*, 
                                                    CASE WHEN prop_active_safs.assessment_type ILIKE '%MUTATION%' THEN 'MUTATION'
                                                        ELSE UPPER(prop_active_safs.assessment_type)
                                                        END AS assessment_type
                                                FROM prop_transactions
                                                JOIN prop_active_safs ON  prop_active_safs.id = prop_transactions.saf_id 
                                                WHERE prop_transactions.status in (1,2)
                                                    AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                                    " . ($userId ? " AND prop_transactions.user_id = $userId " : "") . "
                                                    " . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId " : "") . "
                                            )
                                            UNION(
                                                SELECT prop_transactions.*,
                                                    CASE WHEN prop_rejected_safs.assessment_type ILIKE '%MUTATION%' THEN 'MUTATION'
                                                        ELSE UPPER(prop_rejected_safs.assessment_type)
                                                        END AS assessment_type 
                                                FROM prop_transactions
                                                JOIN prop_rejected_safs ON prop_transactions.id = prop_transactions.saf_id 
                                                WHERE prop_transactions.status in (1,2)
                                                    AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                                    " . ($userId ? " AND prop_transactions.user_id = $userId " : "") . "
                                                    " . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId " : "") . "
                                            )
                                            UNION(
                                                SELECT prop_transactions.*,
                                                    CASE WHEN prop_safs.assessment_type ILIKE '%MUTATION%' THEN 'MUTATION'
                                                        ELSE UPPER(prop_safs.assessment_type)
                                                        END AS assessment_type  
                                                FROM prop_transactions
                                                JOIN prop_safs ON prop_safs.id = prop_transactions.saf_id 
                                                WHERE prop_transactions.status in (1,2)
                                                    AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                                    " . ($userId ? " AND prop_transactions.user_id = $userId " : "") . "
                                                    " . ($ulbId ? " AND prop_transactions.ulb_id = $ulbId " : "") . "
                                            )

                                        )prop_transactions
                                    ) prop_transactions"), function ($join) use ($fromDate, $uptoDate, $userId, $ulbId) {
                    $sub = $join->on(DB::RAW("UPPER(prop_transactions.assessment_type)"), "=", DB::RAW("UPPER(assesment_type.mode)"))
                        ->WHERENOTNULL("prop_transactions.saf_id")
                        ->WHEREIN("prop_transactions.status", [1, 2])
                        ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);
                    if ($userId) {
                        $sub = $sub->WHERE("prop_transactions.user_id", $userId);
                    }
                    if ($ulbId) {
                        $sub = $sub->WHERE("prop_transactions.ulb_id", $ulbId);
                    }
                })
                ->LEFTJOIN("users", "users.id", "prop_transactions.user_id")
                ->GROUPBY("assesment_type.mode");
            if ($paymentMode) {
                $assestmentType = $assestmentType->where(DB::raw("upper(prop_transactions.payment_mode)"), $paymentMode);
            }

            $collection = $collection->get();
            $refund     = $refund->get();
            $doorToDoor = $doorToDoor->get();
            $jsk        = $jsk->get();
            $assestmentType = $assestmentType->get();

            $totalCollection = $collection->sum("amount");
            $totalSaf = $collection->sum("saf_count");
            $totalTran = $collection->sum("tran_count");

            $totalCollectionRefund = $refund->sum("amount");
            $totalSafRefund = $refund->sum("saf_count");
            $totalTranRefund = $refund->sum("tran_count");

            $totalCollectionDoor = $doorToDoor->sum("amount");
            $totalSafDoor = $doorToDoor->sum("saf_count");
            $totalTranDoor = $doorToDoor->sum("tran_count");

            $totalCollectionJsk = $jsk->sum("amount");
            $totalSafJsk = $jsk->sum("saf_count");
            $totalTranJsk = $jsk->sum("tran_count");



            $collection[] = [
                "transaction_mode" => "Total Collection",
                "saf_count"    => $totalSaf,
                "tran_count"       => $totalTran,
                "amount"           => $totalCollection
            ];
            $funal["collection"] = $collection;
            $refund[] = [
                "transaction_mode" => "Total Refund",
                "saf_count"    => $totalSafRefund,
                "tran_count"       => $totalTranRefund,
                "amount"           => $totalCollectionRefund
            ];
            $funal["refund"] = $refund;
            $funal["netCollection"][] = [
                "transaction_mode" => "Net Collection",
                "saf_count"    => $totalSaf - $totalSafRefund,
                "tran_count"       => $totalTran - $totalTranRefund,
                "amount"           => $totalCollection - $totalCollectionRefund
            ];

            $doorToDoor[] = [
                "transaction_mode" => "Total Door To Door",
                "saf_count"    => $totalCollectionDoor,
                "tran_count"       => $totalSafDoor,
                "amount"           => $totalTranDoor
            ];
            $funal["doorToDoor"] = $doorToDoor;

            $jsk[] = [
                "transaction_mode" => "Total JSK",
                "saf_count"    => $totalCollectionJsk,
                "tran_count"       => $totalSafJsk,
                "amount"           => $totalTranJsk
            ];
            $funal["jsk"] = $jsk;
            $funal["assestment_type"] = $assestmentType;
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $funal, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function PropDCB(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
            $fiYear = getFY();
            $isGbsaf = null;
            if ($request->fiYear) {
                $fiYear = $request->fiYear;
            }
            if ($request->isGbsaf) {
                $isGbsaf = $request->isGbsaf;
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
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? ($request->page * $perPage) : 0;


            $from = "
                FROM (
                    SELECT *
                    FROM prop_properties
                    WHERE prop_properties.ulb_id = $ulbId
                    ORDER BY id
                    limit $limit offset $offset
                  )prop_properties
                LEFT JOIN (
                    SELECT STRING_AGG(owner_name, ', ') AS owner_name,
                        STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                        prop_properties.id AS property_id
                    FROM prop_owners 
                    JOIN (
                        SELECT * 
                        FROM prop_properties
                        WHERE prop_properties.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )prop_properties ON prop_properties.id = prop_owners.property_id
                        AND prop_properties.ulb_id = $ulbId
                    WHERE prop_owners.status =1
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                    GROUP BY prop_properties.id
                )prop_owner_detail ON prop_owner_detail.property_id = prop_properties.id
                LEFT JOIN (
                    SELECT prop_demands.property_id,
                        SUM(
                                CASE WHEN prop_demands.fyear  = '$fiYear' then prop_demands.total_tax
                                    ELSE 0
                                    END
                        ) AS current_demand,
                        SUM(
                            CASE WHEN prop_demands.fyear  < '$fiYear' then prop_demands.total_tax
                                ELSE 0
                                END
                            ) AS arrear_demand,
                    SUM(prop_demands.total_tax) AS total_demand
                    FROM prop_demands
                    JOIN (
                        SELECT * 
                        FROM prop_properties
                        WHERE prop_properties.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )prop_properties ON prop_properties.id = prop_demands.property_id
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_demands.fyear  <= '$fiYear'
                    GROUP BY prop_demands.property_id    
                )demands ON demands.property_id = prop_properties.id
                LEFT JOIN (
                    SELECT prop_demands.property_id,
                        SUM(
                                CASE WHEN prop_demands.fyear  = '$fiYear' then prop_demands.total_tax
                                    ELSE 0
                                    END
                        ) AS current_collection,
                        SUM(
                            cASe when prop_demands.fyear  < '$fiYear' then prop_demands.total_tax
                                ELSE 0
                                END
                            ) AS arrear_collection,
                    SUM(prop_demands.total_tax) AS total_collection
                    FROM prop_demands
                    JOIN (
                        SELECT * 
                        FROM prop_properties
                        WHERE prop_properties.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_transactions.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND prop_demands.fyear  <= '$fiYear'
                    GROUP BY prop_demands.property_id
                )collection ON collection.property_id = prop_properties.id
                LEFT JOIN ( 
                    SELECT prop_demands.property_id,
                    SUM(prop_demands.total_tax) AS total_prev_collection
                    FROM prop_demands
                    JOIN (
                        SELECT * 
                        FROM prop_properties
                        WHERE prop_properties.ulb_id = $ulbId
                        ORDER BY id
                        limit $limit offset $offset
                      )prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_transactions.tran_date<'$fromDate'
                    GROUP BY prop_demands.property_id
                )prev_collection ON prev_collection.property_id = prop_properties.id 
                JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                WHERE  prop_properties.ulb_id = $ulbId  
                    " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "           
            ";
            $footerfrom = "
                FROM prop_properties
                LEFT JOIN (
                    SELECT STRING_AGG(owner_name, ', ') AS owner_name,
                        STRING_AGG(mobile_no::TEXT, ', ') AS mobile_no, 
                        prop_properties.id AS property_id
                    FROM prop_owners 
                    JOIN prop_properties ON prop_properties.id = prop_owners.property_id
                        AND prop_properties.ulb_id = $ulbId
                    WHERE prop_owners.status =1
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                    GROUP BY prop_properties.id
                )prop_owner_detail ON prop_owner_detail.property_id = prop_properties.id
                LEFT JOIN (
                    SELECT prop_demands.property_id,
                        SUM(
                                CASE WHEN prop_demands.fyear  = '$fiYear' then prop_demands.total_tax
                                    ELSE 0
                                    END
                        ) AS current_demand,
                        SUM(
                            CASE WHEN prop_demands.fyear  < '$fiYear' then prop_demands.total_tax
                                ELSE 0
                                END
                            ) AS arrear_demand,
                    SUM(prop_demands.total_tax) AS total_demand
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_demands.fyear  <= '$fiYear'
                    GROUP BY prop_demands.property_id    
                )demands ON demands.property_id = prop_properties.id
                LEFT JOIN (
                    SELECT prop_demands.property_id,
                        SUM(
                                CASE WHEN prop_demands.fyear  = '$fiYear' then prop_demands.total_tax
                                    ELSE 0
                                    END
                        ) AS current_collection,
                        SUM(
                            cASe when prop_demands.fyear  < '$fiYear' then prop_demands.total_tax
                                ELSE 0
                                END
                            ) AS arrear_collection,
                    SUM(prop_demands.total_tax) AS total_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_transactions.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND prop_demands.fyear  <= '$fiYear'
                    GROUP BY prop_demands.property_id
                )collection ON collection.property_id = prop_properties.id
                LEFT JOIN ( 
                    SELECT prop_demands.property_id,
                    SUM(prop_demands.total_tax) AS total_prev_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_transactions.tran_date<'$fromDate'
                    GROUP BY prop_demands.property_id
                )prev_collection ON prev_collection.property_id = prop_properties.id 
                JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                WHERE  prop_properties.ulb_id = $ulbId  
                    " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "           
            ";
            $select = "SELECT  prop_properties.id,
                            ulb_ward_masters.ward_name AS ward_no,
                            CONCAT('', prop_properties.holding_no, '') AS holding_no,
                            (
                                CASE WHEN prop_properties.new_holding_no='' OR prop_properties.new_holding_no IS NULL THEN 'N/A' 
                                ELSE prop_properties.new_holding_no END
                            ) AS new_holding_no,
                            prop_owner_detail.owner_name,
                            prop_owner_detail.mobile_no,
                    
                            COALESCE(
                                COALESCE(demands.arrear_demand, 0::numeric) 
                                - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                            ) AS arrear_demand,
                            COALESCE(demands.current_demand, 0::numeric) AS current_demand,   
                            COALESCE(prev_collection.total_prev_collection, 0::numeric) AS previous_collection,
                            
                            COALESCE(collection.arrear_collection, 0::numeric) AS arrear_collection,
                            COALESCE(collection.current_collection, 0::numeric) AS current_collection,
                    
                            (COALESCE(
                                    COALESCE(demands.arrear_demand, 0::numeric) 
                                    - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                                ) 
                                - COALESCE(collection.arrear_collection, 0::numeric) )AS old_due,
                    
                            (COALESCE(demands.current_demand, 0::numeric) - COALESCE(collection.current_collection, 0::numeric)) AS current_due,
                    
                            (
                                COALESCE(
                                    COALESCE(demands.current_demand, 0::numeric) 
                                    + (
                                        COALESCE(demands.arrear_demand, 0::numeric) 
                                        - COALESCE(prev_collection.total_prev_collection, 0::numeric)
                                    ), 0::numeric
                                ) 
                                - COALESCE(
                                    COALESCE(collection.current_collection, 0::numeric) 
                                    + COALESCE(collection.arrear_collection, 0::numeric), 0::numeric
                                )
                            ) AS outstanding                                 
            ";
            $footerselect = "SELECT
                        COUNT(prop_properties.id)AS total_prop,
                        COUNT(DISTINCT(ulb_ward_masters.ward_name)) AS total_ward,
                        SUM(
                            COALESCE(
                                COALESCE(demands.arrear_demand, 0::numeric) 
                                - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                            )
                        ) AS outstanding_at_begin,
                        SUM(COALESCE(demands.currend_demand, 0::numeric)) AS current_demand,
                
                        SUM(COALESCE(prev_collection.total_prev_collection, 0::numeric)) AS prev_coll,
                        SUM(COALESCE(collection.arrear_collection, 0::numeric)) AS arrear_ollection,
                        SUM(COALESCE(collection.current_collection, 0::numeric)) AS current_collection,
                
                        SUM(
                            (
                                COALESCE(
                                    COALESCE(demands.arrear_demand, 0::numeric) 
                                    - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                                ) 
                                - COALESCE(collection.arrear_collection, 0::numeric) 
                            )
                        )AS old_due,
                
                        SUM((COALESCE(demands.current_demand, 0::numeric) - COALESCE(collection.current_collection, 0::numeric))) AS current_due,
                
                        SUM(
                            (
                                COALESCE(
                                    COALESCE(demands.current_demand, 0::numeric) 
                                    + (
                                        COALESCE(demands.arrear_demand, 0::numeric) 
                                        - COALESCE(prev_collection.total_prev_collection, 0::numeric)
                                    ), 0::numeric
                                ) 
                                - COALESCE(
                                    COALESCE(collection.currend_collection, 0::numeric) 
                                    + COALESCE(collection.arrear_collection, 0::numeric), 0::numeric
                                )
                            )
                        ) AS outstanding               
            ";
            $data = $this->_DB_READ->TABLE(DB::RAW("($select $from)AS prop"))->get();
            // $footer = DB::TABLE(DB::RAW("($footerselect $footerfrom)AS prop"))->get();
            $items = $data;
            $total = (collect($this->_DB_READ->SELECT("SELECT COUNT(*) AS total $footerfrom"))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                // "perPage" => $perPage,
                // "page" => $page,
                // "items" => $items,
                // "footer" => $footer,
                // "total" => $total,
                // "numberOfPages" => $numberOfPages,
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage - 1
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function PropWardWiseDCB(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $zoneId=$wardId = null;
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
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zone) {
                $zoneId = $request->zone;
            }
            $from = "
                FROM ulb_ward_masters 
                LEFT JOIN(
                    SELECT prop_properties.ward_mstr_id,
                    COUNT
                        (DISTINCT (
                            CASE WHEN prop_demands.fyear  = '$fiYear'  then prop_demands.property_id
                            END)
                        ) as current_demand_hh,
                        SUM(
                                CASE WHEN prop_demands.fyear = '$fiYear' then prop_demands.total_tax
                                    ELSE 0
                                    END
                        ) AS current_demand,
                        COUNT
                            (DISTINCT (
                                CASE WHEN prop_demands.fyear<'$fiYear' then prop_demands.property_id
                                END)
                            ) as arrear_demand_hh,
                        SUM(
                            CASE WHEN prop_demands.fyear<'$fiYear' then prop_demands.total_tax
                                ELSE 0
                                END
                            ) AS arrear_demand,
                    SUM(total_tax) AS total_demand
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_demands.fyear<='$fiYear'
                    GROUP BY prop_properties.ward_mstr_id
                )demands ON demands.ward_mstr_id = ulb_ward_masters.id
                LEFT JOIN (
                    SELECT prop_properties.ward_mstr_id,
                        COUNT(
                            DISTINCT (
                                CASE WHEN prop_demands.fyear  = '$fiYear'  then prop_demands.property_id
                                END
                            )
                        ) as current_collection_hh,

                        COUNT(DISTINCT(prop_properties.id)) AS collection_from_no_of_hh,
                        SUM(
                                CASE WHEN prop_demands.fyear  = '$fiYear' then prop_tran_dtls.paid_total_tax
                                ELSE 0
                                END
                        ) AS current_collection,

                        COUNT(
                            DISTINCT (
                                CASE WHEN prop_demands.fyear< '$fiYear' then prop_demands.property_id
                                END
                            )
                        ) as arrear_collection_hh,

                        SUM(
                            CASE when prop_demands.fyear <'$fiYear' then prop_tran_dtls.paid_total_tax
                                ELSE 0
                                END
                        ) AS arrear_collection,
                        SUM(prop_tran_dtls.paid_total_tax) AS total_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_transactions.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND prop_demands.fyear<='$fiYear'
                    GROUP BY prop_properties.ward_mstr_id
                )collection ON collection.ward_mstr_id = ulb_ward_masters.id
                LEFT JOIN ( 
                    SELECT prop_properties.ward_mstr_id,
                    SUM(prop_tran_dtls.paid_total_tax) AS total_prev_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        AND prop_transactions.tran_date<'$fromDate'
                    GROUP BY prop_properties.ward_mstr_id
                )prev_collection ON prev_collection.ward_mstr_id = ulb_ward_masters.id                 
                WHERE  ulb_ward_masters.ulb_id = $ulbId  
                    " . ($wardId ? " AND ulb_ward_masters.id = $wardId" : "") . "
                    " . ($zoneId ? " AND ulb_ward_masters.zone = $zoneId" : "") . "
                GROUP BY ulb_ward_masters.ward_name           
            ";
            $select = "SELECT ulb_ward_masters.ward_name AS ward_no, 
                            SUM(COALESCE(demands.current_demand_hh, 0::numeric)) AS current_demand_hh,   
                            SUM(COALESCE(demands.arrear_demand_hh, 0::numeric)) AS arrear_demand_hh,
                            SUM(COALESCE(collection.current_collection_hh, 0::numeric)) AS current_collection_hh,   
                            SUM(COALESCE(collection.arrear_collection_hh, 0::numeric)) AS arrear_collection_hh,
                            SUM(COALESCE(collection.collection_from_no_of_hh, 0::numeric)) AS collection_from_hh,
                            
                            round(SUM(((collection.arrear_collection_hh ::numeric) / (case when demands.arrear_demand_hh > 0 then demands.arrear_demand_hh else 1 end))*100)) AS arrear_hh_eff,
                            round(SUM(((collection.current_collection_hh ::numeric) / (case when demands.current_demand_hh > 0 then demands.current_demand_hh else 1 end))*100)) AS current_hh_eff,

                            round(SUM(COALESCE(
                                COALESCE(demands.current_demand_hh, 0::numeric) 
                                - COALESCE(collection.collection_from_no_of_hh, 0::numeric), 0::numeric
                            ))) AS balance_hh,                       
                            round(SUM(COALESCE(
                                COALESCE(demands.arrear_demand, 0::numeric) 
                                - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                            ))) AS arrear_demand,
                    
                            round(SUM(COALESCE(prev_collection.total_prev_collection, 0::numeric))) AS previous_collection,
                            round(SUM(COALESCE(demands.current_demand, 0::numeric))) AS current_demand,
                            round(SUM(COALESCE(collection.arrear_collection, 0::numeric))) AS arrear_collection,
                            round(SUM(COALESCE(collection.current_collection, 0::numeric))) AS current_collection,
                    
                            round(SUM((COALESCE(
                                    COALESCE(demands.arrear_demand, 0::numeric) 
                                    - COALESCE(prev_collection.total_prev_collection, 0::numeric), 0::numeric
                                ) 
                                - COALESCE(collection.arrear_collection, 0::numeric) 
                                )))AS old_due,
                    
                            round(SUM((COALESCE(demands.current_demand, 0::numeric) - COALESCE(collection.current_collection, 0::numeric)))) AS current_due,

                            round(SUM((COALESCE(demands.current_demand_hh, 0::numeric) - COALESCE(collection.current_collection_hh, 0::numeric)))) AS current_balance_hh,
                            round(SUM((COALESCE(demands.arrear_demand_hh, 0::numeric) - COALESCE(collection.arrear_collection_hh, 0::numeric)))) AS arrear_balance_hh,

                            round(SUM(((collection.arrear_collection ::numeric) / (case when demands.arrear_demand > 0 then demands.arrear_demand else 1 end))*100)) AS arrear_eff,
                            round(SUM(((collection.current_collection ::numeric) / (case when demands.current_demand > 0 then demands.current_demand else 1 end))*100)) AS current_eff,

                            round(SUM((
                                COALESCE(
                                    COALESCE(demands.current_demand, 0::numeric) 
                                    + (
                                        COALESCE(demands.arrear_demand, 0::numeric) 
                                        - COALESCE(prev_collection.total_prev_collection, 0::numeric)
                                    ), 0::numeric
                                ) 
                                - COALESCE(
                                    COALESCE(collection.current_collection, 0::numeric) 
                                    + COALESCE(collection.arrear_collection, 0::numeric), 0::numeric
                                )
                            ))) AS outstanding                                 
            ";
            $dcb = $this->_DB_READ->select($select . $from);

            $data['total_arrear_demand'] = round(collect($dcb)->sum('arrear_demand'), 0);
            $data['total_current_demand'] = round(collect($dcb)->sum('current_demand'), 0);
            $data['total_arrear_collection'] = round(collect($dcb)->sum('arrear_collection'), 0);
            $data['total_current_collection'] = round(collect($dcb)->sum('current_collection'), 0);
            $data['total_old_due'] = round(collect($dcb)->sum('old_due'), 0);
            $data['total_current_due'] = round(collect($dcb)->sum('current_due'), 0);
            $data['total_arrear_demand_hh'] = round(collect($dcb)->sum('arrear_demand_hh'), 0);
            $data['total_current_demand_hh'] = round(collect($dcb)->sum('current_demand_hh'), 0);
            $data['total_arrear_collection_hh'] = round(collect($dcb)->sum('arrear_collection_hh'), 0);
            $data['total_current_collection_hh'] = round(collect($dcb)->sum('current_collection_hh'), 0);
            $data['total_arrear_balance_hh'] = round(collect($dcb)->sum('arrear_balance_hh'));
            $data['total_current_balance_hh'] = round(collect($dcb)->sum('current_balance_hh'));
            // $data['total_current_eff'] = round(($data['total_current_collection_hh'] / $data['total_current_demand']) * 100);
            // $data['total_arrear_hh_eff'] = round(($data['total_arrear_collection_hh'] /  $data['total_arrear_demand_hh']) * 100);
            // $data['total_current_hh_eff'] = round(($data['total_current_collection_hh']) / ($data['total_current_demand_hh']) * 100);
            // $data['total_arrear_eff'] = round(($data['total_arrear_collection']) / ($data['total_arrear_demand']) * 100);
            $data['total_eff'] = round((($data['total_arrear_collection'] + $data['total_current_collection']) / ($data['total_arrear_demand'] + $data['total_current_demand'])) * 100);
            $data['dcb'] = $dcb;

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    public function oldPropWardWiseDCB(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $zoneId = $wardId = null;
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
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId || $request->zone) {
                $zoneId = $request->zoneId ?? $request->zone;
            }
            $from = "
                FROM ulb_ward_masters 
                LEFT JOIN(
                    SELECT prop_properties.ward_mstr_id,            
                        COUNT(DISTINCT (CASE WHEN prop_demands.fyear  = '$fiYear'  then prop_demands.property_id               
                                        END)                        
                            ) as current_demand_hh,    
                        SUM(              
                            CASE WHEN prop_demands.fyear = '$fiYear' then prop_demands.total_tax           
                            ELSE 0                                   
                            END                        
                        ) AS current_demand,       
                        COUNT(DISTINCT ( CASE WHEN prop_demands.fyear < '$fiYear' then prop_demands.property_id 
                                        END)                            
                            ) as arrear_demand_hh,                       
                        SUM(CASE WHEN prop_demands.fyear < '$fiYear' then (prop_demands.total_tax) ELSE 0 END ) AS arrear_demand,     
                        SUM(total_tax) AS total_demand,
                        COUNT(DISTINCT (CASE WHEN prop_demands.fyear  = '$fiYear' and prop_demands.paid_status =1 then prop_demands.property_id               
                                            END)                        
                                ) as current_collection_hh,  
                        SUM(              
                                CASE WHEN prop_demands.fyear = '$fiYear' and prop_demands.paid_status =1 then prop_demands.paid_total_tax           
                                ELSE 0                                   
                                END                        
                            ) AS current_collection,
                        COUNT(DISTINCT ( CASE WHEN prop_demands.fyear < '$fiYear' and prop_demands.paid_status =1 then prop_demands.property_id 
                                            END)                            
                                ) as arrear_collection_hh, 
                        SUM(CASE WHEN prop_demands.fyear < '$fiYear' and prop_demands.paid_status =1  then prop_demands.paid_total_tax ELSE 0 END ) AS arrear_collection,
                        SUM(CASE WHEN prop_demands.paid_status =1  then prop_demands.paid_total_tax ELSE 0 END) AS total_collection,
                        COUNT(DISTINCT(CASE WHEN prop_demands.paid_status =1  then prop_properties.id end)) AS collection_from_no_of_hh 
                    FROM prop_demands                    
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    WHERE prop_demands.status =1 
                        AND prop_demands.ulb_id =$ulbId
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        " . ($zoneId ? " AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                        AND prop_demands.fyear<='$fiYear'
                    GROUP BY prop_properties.ward_mstr_id
                )demands ON demands.ward_mstr_id = ulb_ward_masters.id   
                left join(
                    SELECT prop_properties.ward_mstr_id, SUM(0)AS balance
                    FROM prop_properties
                    where prop_properties.status = 1 
                        AND prop_properties.ulb_id =$ulbId
                    " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? " AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                    GROUP BY prop_properties.ward_mstr_id
                ) AS arrear  on arrear.ward_mstr_id = ulb_ward_masters.id                            
                WHERE  ulb_ward_masters.ulb_id = $ulbId  
                    " . ($wardId ? " AND ulb_ward_masters.id = $wardId" : "") . "
                    " . ($zoneId ? " AND ulb_ward_masters.zone = $zoneId" : "") . "
                GROUP BY ulb_ward_masters.ward_name          
            ";
            $select = "SELECT ulb_ward_masters.ward_name AS ward_no,ulb_ward_masters.ward_name,
                            SUM(COALESCE(demands.current_demand_hh, 0::numeric)) AS current_demand_hh,   
                            SUM(COALESCE(demands.arrear_demand_hh, 0::numeric)) AS arrear_demand_hh,      
                            SUM(COALESCE(demands.current_collection_hh, 0::numeric)) AS current_collection_hh,  
                            SUM(COALESCE(demands.arrear_collection_hh, 0::numeric)) AS arrear_collection_hh,      
                            SUM(COALESCE(demands.collection_from_no_of_hh, 0::numeric)) AS collection_from_hh,   
                            round(
                                SUM(
                                    (
                                            COALESCE(demands.arrear_collection_hh, 0::numeric) 
                                            / (case when COALESCE(demands.arrear_demand_hh, 0::numeric) > 0  then demands.arrear_demand_hh else 1 end)
                                    )
                                    *100
                                )
                            ) AS arrear_hh_eff,                            
                            round(
                                SUM(
                                    (
                                        COALESCE(demands.current_collection_hh, 0::numeric) 
                                        / (case when COALESCE(demands.current_demand_hh, 0::numeric) > 0 then demands.current_demand_hh else 1 end)
                                    )
                                    *100
                                )
                            ) AS current_hh_eff,                            
                            round(
                                SUM(
                                    COALESCE(                                
                                        COALESCE(demands.current_demand_hh, 0::numeric)  
                                        +COALESCE(demands.arrear_demand_hh, 0::numeric)
                                        - COALESCE(demands.collection_from_no_of_hh, 0::numeric), 0::numeric     
                                    )
                                )
                            ) AS balance_hh,                                                   
                            round(
                                SUM(
                                    COALESCE(                                
                                        COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)   
                                    )
                                )
                            ) AS arrear_demand,    
                            round(SUM(COALESCE(demands.current_demand, 0::numeric))) AS current_demand,   
                            round(SUM(COALESCE(demands.arrear_collection, 0::numeric))) AS arrear_collection,   
                            round(SUM(COALESCE(demands.current_collection, 0::numeric))) AS current_collection, 
                            round(
                                SUM(
                                    (                                    
                                        COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)                              
                                        - COALESCE(demands.arrear_collection, 0::numeric)           
                                    )
                                )
                            )AS old_due,                          
                            round(SUM((COALESCE(demands.current_demand, 0::numeric) - COALESCE(demands.current_collection, 0::numeric)))) AS current_due,    
                            round(SUM((COALESCE(demands.current_demand_hh, 0::numeric) - COALESCE(demands.current_collection_hh, 0::numeric)))) AS current_balance_hh, 
                            round(SUM((COALESCE(demands.arrear_demand_hh, 0::numeric) - COALESCE(demands.arrear_collection_hh, 0::numeric)))) AS arrear_balance_hh,   
                            round(
                                SUM(
                                    (
                                        COALESCE(demands.arrear_collection ::numeric , 0::numeric)
                                        / (case when (COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)) > 0 then demands.arrear_demand else 1 end)
                                        
                                    )
                                    *100
                                )
                            ) AS arrear_eff,                            
                            round(
                                SUM(
                                    (
                                        COALESCE(demands.current_collection, 0::numeric)
                                            / (case when COALESCE(demands.current_demand, 0::numeric) > 0  then demands.current_demand else 1 end)
                                        
                                    )
                                    *100
                                )
                            ) AS current_eff,
                            round(
                                SUM(
                                    (                                
                                        COALESCE(                                   
                                            COALESCE(demands.current_demand, 0::numeric)         
                                            + (                                       
                                                COALESCE(demands.arrear_demand, 0::numeric) + COALESCE(arrear.balance, 0::numeric)  
                                            ), 0::numeric                                
                                        )                                 
                                        - COALESCE(                              
                                            COALESCE(demands.current_collection, 0::numeric)   
                                            + COALESCE(demands.arrear_collection, 0::numeric), 0::numeric     
                                        )                            
                                    )
                                )
                            ) AS outstanding                                  
            ";
            $dcb = $this->_DB_READ->select($select . $from);

            $data['total_arrear_demand'] = round(collect($dcb)->sum('arrear_demand'), 0);
            $data['total_current_demand'] = round(collect($dcb)->sum('current_demand'), 0);
            $data['total_arrear_collection'] = round(collect($dcb)->sum('arrear_collection'), 0);
            $data['total_current_collection'] = round(collect($dcb)->sum('current_collection'), 0);
            $data['total_old_due'] = round(collect($dcb)->sum('old_due'), 0);
            $data['total_current_due'] = round(collect($dcb)->sum('current_due'), 0);
            $data['total_arrear_demand_hh'] = round(collect($dcb)->sum('arrear_demand_hh'), 0);
            $data['total_current_demand_hh'] = round(collect($dcb)->sum('current_demand_hh'), 0);
            $data['total_arrear_collection_hh'] = round(collect($dcb)->sum('arrear_collection_hh'), 0);
            $data['total_current_collection_hh'] = round(collect($dcb)->sum('current_collection_hh'), 0);
            $data['total_arrear_balance_hh'] = round(collect($dcb)->sum('arrear_balance_hh'));
            $data['total_current_balance_hh'] = round(collect($dcb)->sum('current_balance_hh'));
            // $data['total_current_eff'] = round(($data['total_current_collection_hh'] / $data['total_current_demand']) * 100);
            // $data['total_arrear_hh_eff'] = round(($data['total_arrear_collection_hh'] /  $data['total_arrear_demand_hh']) * 100);
            // $data['total_current_hh_eff'] = round(($data['total_current_collection_hh']) / ($data['total_current_demand_hh']) * 100);
            // $data['total_arrear_eff'] = round(($data['total_arrear_collection']) / ($data['total_arrear_demand']) * 100);
            $data['total_eff'] = round((($data['total_arrear_collection'] + $data['total_current_collection']) / ($data['total_arrear_demand'] + $data['total_current_demand'])) * 100);
            $data['dcb'] = collect($dcb)->sortBy(function ($item) {
                // Extract the numeric part from the "ward_name"
                preg_match('/\d+/', $item->ward_name, $matches);
                return (int) ($matches[0] ?? "");
            })->values();

            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $data, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function PropFineRebate(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $wardId = null;
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
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            $data = PropProperty::readConnection()->SELECT(
                DB::RAW("
                        ulb_ward_masters.ward_name as ward_no,
                        CASE WHEN prop_properties.new_holding_no!='' 
                            THEN prop_properties.new_holding_no 
                            ELSE prop_properties.holding_no 
                            END AS holding_no,
                        prop_properties.id,
                        (COALESCE(demands.total_demand, 0)) AS total_demand,
                        (
                            COALESCE(prop_transactions.paid_amt,0)
                        )AS paid_amt,
                       (COALESCE(prop_transactions.demand_amt, 0))AS actual_demand_amt,
                       (COALESCE(fine_rebate.total_rebate, 0)) AS total_rebate,
                        (COALESCE(fine_rebate.first_qtr_rebate, 0)) AS first_qtr_rebate,
                        (COALESCE(fine_rebate.online_rebate, 0)) AS online_rebate,
                        (COALESCE(fine_rebate.jsk_rebate, 0)) AS jsk_rebate,        
                        (COALESCE(fine_rebate.special_rebate, 0)) AS special_rebate,
                        
                        (COALESCE(fine_rebate.total_fine, 0)) AS total_fine,
                        (COALESCE(fine_rebate.one_percent_penalty, 0)) AS one_percent_penalty,
                        (COALESCE(fine_rebate.late_assessment_penalty, 0)) AS late_assessment_penalty
                        ")
            )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_properties.ward_mstr_id")
                ->JOIN(
                    DB::RAW("
                                (
                                    SELECT 
                                        prop_demands.property_id, 
                                        SUM(
                                            COALESCE(
                                               prop_tran_dtls.total_demand 
                                                , 0
                                            )
                                        ) AS total_demand
                                    FROM prop_demands
                                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                                        AND  prop_tran_dtls.prop_demand_id NOTNULL
                                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                                        AND prop_transactions.property_id = prop_demands.property_id                                        
                                    WHERE prop_demands.status=1
                                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                                        AND prop_transactions.tran_date BETWEEN '$fromDate'AND '$uptoDate'
                                        " . ($ulbId ? " AND prop_transactions.ulb_id=$ulbId" : "") . "
                                    GROUP BY prop_demands.property_id 
                                )demands
                            "),
                    function ($join) {
                        $join->on("demands.property_id", "prop_properties.id");
                    }
                )
                ->JOIN(
                    DB::RAW("
                                    (
                                        SELECT
                                            prop_transactions.property_id AS property_id,
                                            SUM(COALESCE(prop_transactions.amount, 0)) AS paid_amt,
                                            SUM(COALESCE(prop_transactions.demand_amt, 0)) AS demand_amt        
                                        FROM prop_transactions
                                        WHERE 
                                            prop_transactions.property_id NOTNULL
                                            AND prop_transactions.status IN(1,2)
                                            AND prop_transactions.tran_date BETWEEN '$fromDate'AND '$uptoDate'
                                            " . ($ulbId ? " AND prop_transactions.ulb_id=$ulbId" : "") . "
                                        GROUP BY prop_transactions.property_id
                                    )prop_transactions
                                "),
                    function ($join) {
                        $join->on("prop_transactions.property_id", "prop_properties.id");
                    }
                )
                ->LEFTJOIN(
                    DB::RAW("
                            (
                                SELECT
                                    prop_transactions.property_id, 
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=TRUE 
                                                THEN prop_penaltyrebates.amount ELSE 0 END), 0)
                                    ) AS total_rebate,
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=TRUE 
                                                            AND prop_penaltyrebates.head_name ILIKE'%First Qtr%' 
                                                        THEN prop_penaltyrebates.amount 
                                                        ELSE 0 END), 0)
                                    ) AS first_qtr_rebate,
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=TRUE 
                                                            AND prop_penaltyrebates.head_name ILIKE'%JSK%' AND UPPER(prop_transactions.payment_mode)='ONLINE'
                                                        THEN prop_penaltyrebates.amount 
                                                        ELSE 0 END), 0)
                                    ) AS online_rebate,
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=TRUE 
                                                            AND (prop_penaltyrebates.head_name ILIKE'%JSK%' OR prop_penaltyrebates.head_name ILIKE'%ONLINE%') AND UPPER(prop_transactions.payment_mode)!='ONLINE'
                                                        THEN prop_penaltyrebates.amount 
                                                        ELSE 0 END), 0)
                                    ) AS jsk_rebate,
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=TRUE 
                                                            AND prop_penaltyrebates.head_name ILIKE'%Special Rebate%' 
                                                        THEN prop_penaltyrebates.amount 
                                                        ELSE 0 END), 0)
                                    ) AS special_rebate,

                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=FALSE THEN prop_penaltyrebates.amount 
                                                ELSE 0 END), 0)
                                    ) AS total_fine,
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=FALSE 
                                                            AND prop_penaltyrebates.head_name ILIKE'%Monthly Penalty%' 
                                                        THEN prop_penaltyrebates.amount 
                                                        ELSE 0 END), 0)
                                    ) AS one_percent_penalty,
                                    SUM(COALESCE((CASE WHEN prop_penaltyrebates.is_rebate=FALSE 
                                                            AND prop_penaltyrebates.head_name ILIKE'%Late Assessment%' 
                                                        THEN prop_penaltyrebates.amount 
                                                        ELSE 0 END), 0)
                                    ) AS late_assessment_penalty
                                FROM prop_penaltyrebates
                                JOIN prop_transactions ON prop_penaltyrebates.tran_id = prop_transactions.id       
                                WHERE 
                                    prop_transactions.property_id NOTNULL 
                                    AND prop_transactions.status IN(1,2)
                                    AND prop_transactions.tran_date BETWEEN '$fromDate'AND '$uptoDate'
                                    " . ($ulbId ? " AND prop_transactions.ulb_id=$ulbId" : "") . "
                                GROUP BY prop_transactions.property_id
                            )fine_rebate
                            "),
                    function ($join) {
                        $join->on("fine_rebate.property_id", "prop_properties.id");
                    }
                );
            if ($wardId) {
                $data = $data->WHERE("ulb_ward_masters.id", $wardId);
            }
            if ($ulbId) {

                $data = $data->WHERE("prop_properties.ulb_id", $ulbId);
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                "per_page" => $perPage,
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function PropDeactedList(Request $request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            $fromDate = $uptoDate = null;
            $wardId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }

            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }

            $data = PropProperty::readConnection()->select(
                DB::RAW("
                            prop_properties.id,
                            prop_properties.pt_no,
                            ulb_ward_masters.ward_name AS ward_no,
                            CASE WHEN prop_properties.new_holding_no!='' 
                                THEN prop_properties.new_holding_no 
                                ELSE prop_properties.holding_no 
                                END AS holding_no ,
                            owners_details.owner_name,  
                            owners_details.mobile_no,
                            CONCAT(prop_properties.prop_address, 
                                    ', City - ', prop_properties.prop_city, 
                                    ', Pin Code - ', prop_properties.prop_pin_code
                                ) AS address,
                            prop_deactivation_requests.remarks,
                            prop_deactivation_requests.documents
                            ")
            )
                ->JOIN("ulb_ward_masters", "ulb_ward_masters.id", "prop_properties.ward_mstr_id")
                ->JOIN("prop_deactivation_requests", "prop_deactivation_requests.property_id", "prop_properties.id")
                ->LEFTJOIN(DB::raw("(
                        SELECT
                            property_id,
                            STRING_AGG(owner_name,',')as owner_name,
                            STRING_AGG(guardian_name,',')as guardian_name,
                            STRING_AGG(mobile_no::text,',')as mobile_no
                        FROM view_owners_details
                        GROUP BY property_id
                        )owners_details"), function ($join) {
                    $join->on("owners_details.property_id", "prop_properties.id");
                })
                ->WHERE("prop_properties.status", 0);
            if ($fromDate && $uptoDate) {

                $data = $data->WHEREBETWEEN("prop_deactivation_requests.approve_date", [$fromDate, $uptoDate]);
            }

            if ($wardId) {
                $data = $data->WHERE("ulb_ward_masters.id", $wardId);
            }
            if ($ulbId) {

                $data = $data->WHERE("prop_properties.ulb_id", $ulbId);
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
                "per_page" => $perPage,
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | Property Individual Demand Collection
     */
    public function propIndividualDemandCollection($request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        $perPage = $request->perPage ? $request->perPage : 10;
        $page = $request->page && $request->page > 0 ? $request->page : 1;
        $limit = $perPage;
        $offset =  $request->page && $request->page > 0 ? ($request->page - 1 * $perPage) : 0;
        $wardMstrId = NULL;
        $ulbId = authUser($request)->ulb_id;

        if ($request->wardMstrId) {
            $wardMstrId = $request->wardMstrId;
        }

        try {
            $sql = "SELECT p.id,p.ward_mstr_id,ward_name,p.holding_no,p.new_holding_no,p.prop_address,pt_no,
                        owner_name,
                        mobile_no,
                    prop_demands.total_demand,prop_demands.collection_amount,prop_demands.balance_amount
        
                FROM prop_properties AS p
                left JOIN (
                    SELECT property_id,
                        STRING_AGG(owner_name,',')as owner_name,
                        STRING_AGG(mobile_no::text,',')as mobile_no
                    FROM prop_owners
                    WHERE status =1
                    GROUP BY property_id
                ) prop_owners ON prop_owners.property_id = p.id
                JOIN ulb_ward_masters AS w ON w.id = p.ward_mstr_id
                left JOIN (
                    SELECT property_id,
                        SUM (amount) AS total_demand,
                        SUM(CASE WHEN paid_status =1 THEN amount ELSE 0 END )AS collection_amount,
                        SUM(CASE WHEN paid_status =0 THEN amount ELSE 0 END )AS balance_amount
                    FROM prop_demands 
                    WHERE status =1 
                    GROUP BY property_id
                    limit $limit offset $offset
                )prop_demands ON prop_demands.property_id = p.id
                WHERE p.ulb_id = $ulbId
                " . ($wardMstrId ? " AND p.ward_mstr_id = $wardMstrId" : "") . "
            limit $limit offset $offset";

            $sql2 = "SELECT count(*) as total
                    FROM prop_properties AS p
                left JOIN (
                    SELECT property_id,
                        STRING_AGG(owner_name,',')as owner_name,
                        STRING_AGG(mobile_no::text,',')as mobile_no
                    FROM prop_owners
                    WHERE status =1
                    GROUP BY property_id
                ) prop_owners ON prop_owners.property_id = p.id
                JOIN ulb_ward_masters AS w ON w.id = p.ward_mstr_id
                left JOIN (
                    SELECT property_id,
                        SUM (amount) AS total_demand,
                        SUM(CASE WHEN paid_status =1 THEN amount ELSE 0 END )AS collection_amount,
                        SUM(CASE WHEN paid_status =0 THEN amount ELSE 0 END )AS balance_amount
                    FROM prop_demands 
                    WHERE status =1 
                    GROUP BY property_id                    
                )prop_demands ON prop_demands.property_id = p.id
                WHERE  p.ulb_id = $ulbId
                " . ($wardMstrId ? " AND p.ward_mstr_id = $wardMstrId" : "") . "
               ";

            $data = $this->_DB_READ->TABLE(DB::RAW("($sql )AS prop"))->get();

            $total = (collect($this->_DB_READ->SELECT($sql2))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];

            $queryRunTime = (collect(DB::getQueryLog($sql, $sql2))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | GBSAF Individual Demand Collection
     */
    public function gbsafIndividualDemandCollection($request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        $perPage = $request->perPage ? $request->perPage : 10;
        $page = $request->page && $request->page > 0 ? $request->page : 1;
        $limit = $perPage;
        $offset =  $request->page && $request->page > 0 ? ($request->page - 1 * $perPage) : 0;
        $wardMstrId = NULL;
        $ulbId = authUser($request)->ulb_id;

        if ($request->wardMstrId) {
            $wardMstrId = $request->wardMstrId;
        }
        try {
            $sql = "SELECT p.id,p.ward_mstr_id,ward_name,saf_no,p.prop_address,gb_office_name,
                    total_demand,collection_amount,balance_amount
                    
                        FROM prop_active_safs AS p
                        JOIN prop_active_safgbofficers AS gbo ON gbo.saf_id = p.id
                        JOIN ulb_ward_masters AS w ON w.id = p.ward_mstr_id
                        left JOIN (
                            SELECT saf_id,
                                SUM (amount) AS total_demand,
                                SUM(CASE WHEN paid_status =1 THEN amount ELSE 0 END )AS collection_amount,
                                SUM(CASE WHEN paid_status =0 THEN amount ELSE 0 END )AS balance_amount
                            FROM prop_safs_demands 
                            WHERE status =1 
                            GROUP BY saf_id
                    )prop_safs_demands ON prop_safs_demands.saf_id = p.id
                        where is_gb_saf =true
                        AND p.ulb_id = $ulbId
                    " . ($wardMstrId ? " AND p.ward_mstr_id = $wardMstrId" : "") . "
                    limit $limit offset $offset";

            $sql2 = "SELECT count(*) as total
            
                    FROM prop_active_safs AS p
                    JOIN prop_active_safgbofficers AS gbo ON gbo.saf_id = p.id
                    JOIN ulb_ward_masters AS w ON w.id = p.ward_mstr_id
                    left JOIN (
                        SELECT saf_id,
                            SUM (amount) AS total_demand,
                            SUM(CASE WHEN paid_status =1 THEN amount ELSE 0 END )AS collection_amount,
                            SUM(CASE WHEN paid_status =0 THEN amount ELSE 0 END )AS balance_amount
                        FROM prop_safs_demands 
                        WHERE status =1 
                        GROUP BY saf_id
                )prop_safs_demands ON prop_safs_demands.saf_id = p.id
                    where is_gb_saf =true
                    AND p.ulb_id = $ulbId
                " . ($wardMstrId ? " AND p.ward_mstr_id = $wardMstrId" : "") . "
                ";

            $data = $this->_DB_READ->TABLE(DB::RAW("($sql )AS prop"))->get();

            $total = (collect($this->_DB_READ->SELECT($sql2))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];

            $queryRunTime = (collect(DB::getQueryLog($sql, $sql2))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | Not paid from 2019-2017
     */
    public function notPaidFrom2016($request)
    {
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        $perPage = $request->perPage ? $request->perPage : 10;
        $page = $request->page && $request->page > 0 ? $request->page : 1;
        $limit = $perPage;
        $offset =  $request->page && $request->page > 0 ? ($request->page - 1 * $perPage) : 0;
        $wardMstrId = NULL;
        $ulbId = authUser($request)->ulb_id;

        if ($request->wardMstrId) {
            $wardMstrId = $request->wardMstrId;
        }
        try {
            $sql = "SELECT prop_demands.property_id,holding_no,new_holding_no,
                        owner_name,mobile_no,prop_address,
                        SUM (total_tax) AS total_demand,
                        SUM(CASE WHEN paid_status =0 THEN total_tax ELSE 0 END )AS balance_amount
                    FROM prop_demands 
                    JOIN (
                        SELECT property_id,
                        STRING_AGG(owner_name,',')as owner_name,
                        STRING_AGG(mobile_no::text,',')as mobile_no
                            FROM prop_owners
                            WHERE status =1
                            GROUP BY property_id 
                        ) AS o ON o.property_id = prop_demands.property_id
            
                    JOIN prop_properties on prop_properties.id = prop_demands.property_id
                    -- JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                    WHERE prop_demands.status =1 
                    -- " . ($wardMstrId ? " AND prop_demands.ward_mstr_id = $wardMstrId" : "") . "
                    AND fyear > '2016-2017'
                    AND paid_status = 0
                    AND prop_demands.ulb_id = $ulbId
                    GROUP BY prop_demands.property_id,holding_no,new_holding_no,owner_name,mobile_no,
                             prop_address
                    order by prop_demands.property_id desc
            limit $limit offset $offset";

            $sql2 = "SELECT count(DISTINCT prop_demands.property_id) as total
                        FROM prop_demands 
                        JOIN (
                            SELECT property_id,
                            STRING_AGG(owner_name,',')as owner_name,
                            STRING_AGG(mobile_no::text,',')as mobile_no
                                FROM prop_owners
                                WHERE status =1
                                GROUP BY property_id 
                            ) AS o ON o.property_id = prop_demands.property_id
                        
                            JOIN prop_properties on prop_properties.id = prop_demands.property_id
                        WHERE prop_demands.status =1 
                        " . ($wardMstrId ? " AND prop_demands.ward_mstr_id = $wardMstrId" : "") . "
                        AND fyear > '2016-2017'
                        AND paid_status = 0
            ";

            $data = $this->_DB_READ->TABLE(DB::RAW("($sql )AS prop"))->get();

            $total = (collect($this->_DB_READ->SELECT($sql2))->first())->total ?? 0;
            $lastPage = round($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];

            $queryRunTime = (collect(DB::getQueryLog($sql, $sql2))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | Paid Previous Year but not Current Year
     */
    public function previousYearPaidButnotCurrentYear($request)
    {
        try {
            $metaData = collect($request->metaData)->all();
            list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? ($request->page - 1 * $perPage) : 0;
            $wardMstrId = NULL;
            $ulbId = authUser($request)->ulb_id;

            $currentYear = Carbon::now()->year;
            $financialYearStart = $currentYear;
            if (Carbon::now()->month < 4) {
                $financialYearStart--;
            }
            $currentFinancialYear = $financialYearStart . '-' . ($financialYearStart + 1);
            $previousFinancialYear =  ($financialYearStart - 1) . '-' . ($financialYearStart);

            if ($request->wardMstrId) {
                $wardMstrId = $request->wardMstrId;
            }

            $sql = "SELECT prop_demands.property_id,new_holding_no,new_holding_no,pt_no,prop_address,owner_name,mobile_no,
                        SUM (total_tax) AS total_demand,
                        SUM(CASE WHEN paid_status =0 THEN total_tax ELSE 0 END )AS balance_amount,
                        SUM(CASE WHEN paid_status =1 THEN total_tax ELSE 0 END )AS paid_amount
                    FROM prop_demands
                    JOIN (
                        SELECT property_id,
                        STRING_AGG(owner_name,',')as owner_name,
                        STRING_AGG(mobile_no::text,',')as mobile_no
                            FROM prop_owners
                            WHERE status =1
                            GROUP BY property_id 
                        ) AS ow ON ow.property_id = prop_demands.property_id
        
                    left join(
                            select property_id
                                from prop_demands
                                WHERE prop_demands.status =1
                                AND fyear = '$previousFinancialYear'
                                AND paid_status = 1
                            GROUP BY property_id 
                            ) AS o ON o.property_id = prop_demands.property_id 
                            join prop_properties on prop_properties.id=prop_demands.property_id 
                            -- join ulb_ward_masters on ulb_ward_masters.id = prop_demands.ward_mstr_id
                        WHERE prop_demands.status =1 
                        -- " . ($wardMstrId ? " AND prop_demands.ward_mstr_id = $wardMstrId" : "") . "
                        AND fyear = '$currentFinancialYear' 
                        AND paid_status = 0
                        GROUP BY prop_demands.property_id,new_holding_no,new_holding_no,pt_no,prop_address,owner_name,mobile_no
                    limit $limit offset $offset";

            $sql2 = "SELECT count(distinct prop_demands.property_id) as total
                        FROM prop_demands
                        JOIN (
                            SELECT property_id,
                            STRING_AGG(owner_name,',')as owner_name,
                            STRING_AGG(mobile_no::text,',')as mobile_no
                                FROM prop_owners
                                WHERE status =1
                                GROUP BY property_id 
                            ) AS ow ON ow.property_id = prop_demands.property_id
            
                    left join(
                            select property_id
                                from prop_demands
                                WHERE prop_demands.status =1
                                AND fyear = '$previousFinancialYear'
                                AND paid_status = 1
                            GROUP BY property_id 
                            ) AS o ON o.property_id = prop_demands.property_id 
                            join prop_properties on prop_properties.id=prop_demands.property_id 
                            -- join ulb_ward_masters on ulb_ward_masters.id = prop_demands.ward_mstr_id
                        WHERE prop_demands.status =1 
                        -- " . ($wardMstrId ? " AND prop_demands.ward_mstr_id = $wardMstrId" : "") . "
                        AND fyear = '$currentFinancialYear' 
                        AND paid_status = 0";

            $data = $this->_DB_READ->TABLE(DB::RAW("($sql )AS prop"))->get();

            $total = (collect($this->_DB_READ->SELECT($sql2))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];

            $queryRunTime = (collect(DB::getQueryLog($sql, $sql2))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function notPayedFromOld(Request $request)
    {
        try {
            $metaData = collect($request->metaData)->all();
            list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $wardMstrId = NULL;
            $ulbId = authUser($request)->ulb_id;
            $fiYear = $request->fiYear;
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            if ($request->wardMstrId) {
                $wardMstrId = $request->wardMstrId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $sql = "SELECT prop.*
                    FROM(
                        SELECT prop_properties.id AS property_id ,new_holding_no,
                            CASE WHEN new_holding_no IS NULL THEN holding_no ELSE new_holding_no END AS holding_no,
                            ulb_ward_masters.ward_name,
                            pt_no,prop_address,owner_name,mobile_no,
                            SUM (total_tax) AS total_demand,
                            SUM(CASE WHEN paid_status =0 THEN total_tax ELSE 0 END )AS balance_amount,
                            SUM(CASE WHEN paid_status =1 THEN total_tax ELSE 0 END )AS paid_amount
                        FROM prop_properties
                        JOIN prop_demands ON prop_demands.property_id= prop_properties.id 
                        LEFT JOIN(
                            SELECT property_id
                            FROM prop_demands
                            WHERE prop_demands.status =1
                                AND fyear >= '$fiYear'
                                AND paid_status = 1
                            GROUP BY property_id 
                        ) AS o ON o.property_id = prop_demands.property_id 
                        JOIN (
                            SELECT property_id,
                            STRING_AGG(owner_name,',')as owner_name,
                            STRING_AGG(mobile_no::text,',')as mobile_no
                                FROM prop_owners
                                WHERE status =1
                                GROUP BY property_id 
                        ) AS ow ON ow.property_id = prop_demands.property_id                            
                        JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                        WHERE  o.property_id IS NULL 
                            AND prop_demands.status =1 
                            " . ($wardMstrId ? " AND ulb_ward_masters.id = $wardMstrId" : "") . "  
                            " . ($ulbId ? " AND prop_properties.ulb_id = $ulbId" : "") . "                      
                            AND paid_status = 0
                        GROUP BY prop_properties.id,owner_name,mobile_no,ulb_ward_masters.ward_name
                    )prop
                    JOIN prop_properties ON prop_properties.id=prop.property_id
                    limit $limit offset $offset";

            $sql2 = "SELECT count(distinct prop_properties.id) as total
                    FROM prop_properties
                    JOIN prop_demands ON prop_demands.property_id= prop_properties.id 
                    LEFT JOIN(
                        SELECT property_id
                        FROM prop_demands
                        WHERE prop_demands.status =1
                            AND fyear >= '$fiYear'
                            AND paid_status = 1
                        GROUP BY property_id 
                    ) AS o ON o.property_id = prop_demands.property_id 
                    JOIN (
                        SELECT property_id,
                        STRING_AGG(owner_name,',')as owner_name,
                        STRING_AGG(mobile_no::text,',')as mobile_no
                            FROM prop_owners
                            WHERE status =1
                            GROUP BY property_id 
                    ) AS ow ON ow.property_id = prop_demands.property_id                            
                    JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                    WHERE  o.property_id IS NULL 
                        AND prop_demands.status =1 
                        " . ($wardMstrId ? " AND ulb_ward_masters.id = $wardMstrId" : "") . " 
                        " . ($ulbId ? " AND prop_properties.ulb_id = $ulbId" : "") . "                        
                        AND paid_status = 0
                    ";

            $data = $this->_DB_READ->select($sql);

            $total = (collect($this->_DB_READ->SELECT($sql2))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];
            $queryRunTime = (collect(DB::getQueryLog($sql, $sql2))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function notPayedFrom(Request $request)
    {
        try {
            $metaData = collect($request->metaData)->all();
            list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $zoneId = $wardMstrId = NULL;
            $ulbId = authUser($request)->ulb_id;
            $fiYear = $request->fiYear;
            list($fromYear, $toYear) = explode("-", $fiYear);
            if ($toYear - $fromYear != 1) {
                throw new Exception("Enter Valide Financial Year");
            }
            if ($request->wardMstrId) {
                $wardMstrId = $request->wardMstrId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }

            $sql = "SELECT prop.*, (COALESCE(balance_amount,0) 
            -- + COALESCE(monthly_penalty,0) 
            + COALESCE(arrear_interest,0)) AS total_demand
                    FROM(
                        SELECT prop_properties.id AS property_id ,holding_no,
                                property_no, prop_address,
                                ulb_ward_masters.ward_name,zone_masters.zone_name,
                                owner_name,mobile_no,
                                SUM (prop_demands.total_tax) AS total_tax,
                                SUM(prop_demands.balance)AS balance_amount,
                                SUM(prop_demands.total_tax - prop_demands.balance)AS paid_amount,
                                -- SUM(CASE WHEN prop_demands.fyear ='" . getFy() . "' THEN 0 
                                --         WHEN prop_demands.fyear !='" . getFy() . "' AND prop_demands.is_old IS true THEN prop_demands.balance *3*0.02 
                                --         WHEN prop_demands.fyear !='" . getFy() . "' AND prop_demands.is_old IS false THEN prop_demands.balance *12*0.02 
                                --     end )as monthly_penalty,
                                SUM(prive_penalty.arrear_interest) AS arrear_interest ,
                                MIN(fyear) as from_year,
                                MAX(fyear) as upto_year
                        FROM prop_properties
                        JOIN prop_demands ON prop_demands.property_id= prop_properties.id 
                        LEFT JOIN(
                            SELECT property_id
                            FROM prop_demands
                            WHERE prop_demands.status =1
                                AND fyear >= '$fiYear'
                                AND paid_status = 1
                            GROUP BY property_id 
                        ) AS o ON o.property_id = prop_demands.property_id 
                        JOIN (
                            SELECT property_id,
                                STRING_AGG(owner_name,', ')as owner_name,
                                STRING_AGG(mobile_no::text,', ')as mobile_no
                            FROM prop_owners
                            WHERE status =1
                            GROUP BY property_id 
                        ) AS ow ON ow.property_id = prop_demands.property_id    
                        LEFT JOIN(
                            SELECT DISTINCT prop_id,
                                SUM(total_interest) AS arrear_interest
                            FROM prop_pending_arrears
                            WHERE status = 1 AND paid_status =0
                            GROUP BY prop_id
                        )prive_penalty ON prive_penalty.prop_id = prop_properties.id                        
                        JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                        JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
                        WHERE  o.property_id IS NULL 
                            AND prop_demands.status =1 
                            " . ($wardMstrId ? " AND ulb_ward_masters.id = $wardMstrId" : "") . "  
                            " . ($zoneId ? " AND zone_masters.id = $zoneId" : "") . "
                            " . ($ulbId ? " AND prop_properties.ulb_id = $ulbId" : "") . "                      
                            AND paid_status = 0
                        GROUP BY prop_properties.id,owner_name,mobile_no,ulb_ward_masters.ward_name,zone_masters.zone_name
                    )prop
                    JOIN prop_properties ON prop_properties.id=prop.property_id
                    limit $limit offset $offset";

            $sql2 = "SELECT count(distinct prop_properties.id) as total
                    FROM prop_properties
                    JOIN prop_demands ON prop_demands.property_id= prop_properties.id 
                    LEFT JOIN(
                        SELECT property_id
                        FROM prop_demands
                        WHERE prop_demands.status =1
                            AND fyear >= '$fiYear'
                            AND paid_status = 1
                        GROUP BY property_id 
                    ) AS o ON o.property_id = prop_demands.property_id 
                    JOIN (
                        SELECT property_id,
                        STRING_AGG(owner_name,',')as owner_name,
                        STRING_AGG(mobile_no::text,',')as mobile_no
                            FROM prop_owners
                            WHERE status =1
                            GROUP BY property_id 
                    ) AS ow ON ow.property_id = prop_demands.property_id   
                    LEFT JOIN(
                            SELECT DISTINCT prop_id,
                                SUM(total_interest) AS arrear_interest
                            FROM prop_pending_arrears
                            WHERE status = 1 AND paid_status =0
                            GROUP BY prop_id
                    )prive_penalty ON prive_penalty.prop_id = prop_properties.id                           
                    JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                    JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
                    WHERE  o.property_id IS NULL 
                        AND prop_demands.status =1 
                        " . ($wardMstrId ? " AND ulb_ward_masters.id = $wardMstrId" : "") . " 
                        " . ($zoneId ? " AND zone_masters.id = $zoneId" : "") . "
                        " . ($ulbId ? " AND prop_properties.ulb_id = $ulbId" : "") . "                        
                        AND paid_status = 0
                    ";

            $data = $this->_DB_READ->select($sql);

            $total = (collect($this->_DB_READ->SELECT($sql2))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];
            $queryRunTime = (collect(DB::getQueryLog($sql, $sql2))->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
    
    public function dueDemandPropList(Request $request)
    {      
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;  
        try{
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $zoneId = $wardMstrId = NULL;
            $ulbId = authUser($request)->ulb_id;
            $fiYear = getFY();
            list($fromYear ,$uptoyear) = explode("-",$fiYear) ;
            $fromDate = $fromYear."-04-01";
            $uptoDate = $uptoyear."-03-31";
            if ($request->wardMstrId) {
                $wardMstrId = $request->wardMstrId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->ulbId) {
                $ulbId = $request->ulbId;
            }
            $with = "
                    with demands as(
                        select prop_demands.property_id, prop_demands.total_tax, prop_demands.balance, prop_demands.fyear, prop_demands.due_total_tax,
                            prop_demands.is_old, prop_demands.created_at,
                            SPLIT_PART(prop_demands.fyear,'-',2) as upto_year,
                            (
                                case when prop_demands.paid_status = 1 then prop_demands.due_total_tax 			
                                else total_tax end
                            ) as actual_demand,
                            (
                                case when prop_demands.is_full_paid = false then prop_demands.due_total_tax else total_tax end
                            ) /12 as monthly_tax,
                            case when prop_properties.prop_type_mstr_id = 4 and  prop_demands.is_old != true and prop_demands.created_at is not null 
                                    and (prop_demands.created_at::date between '$fromDate' and '$uptoDate')
                                    then (0)
                                when prop_demands.fyear < '$fiYear' and prop_demands.is_old != true
                                    then (
                                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-04-01') :: DATE)) * 12
                                        + (DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-04-01') :: DATE))
                                    )+1
                                when prop_demands.fyear < '$fiYear' and prop_demands.is_old = true 
                                    then (
                                            (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-09-01') :: DATE)) * 12
                                            + (DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-09-01') :: DATE))
                                        )
                                
                                else 0 end AS month_diff,
                            prop_properties.prop_type_mstr_id
                        from prop_demands
                        join prop_properties on prop_properties.id = prop_demands.property_id
                        where prop_demands.status =1 
                        order by prop_demands.property_id,prop_demands.fyear
                    ),
                    demand_with_penalty as (
                        select property_id, sum(actual_demand) as actual_demand,min(fyear) as from_year,max(fyear)as upto_year,
                            sum(case when fyear < '$fiYear' then actual_demand else 0 end)as arrear_demand,
                            sum(case when fyear = '$fiYear' then actual_demand else 0 end)as current_demand,
                            sum((actual_demand * month_diff *0.02)) as two_per_penalty 
                        from demands 
                        where actual_demand > 0
                        group by property_id
                    ),
                    arrea_pending_penalty as (
                        select prop_pending_arrears.prop_id,sum(prop_pending_arrears.due_total_interest) as priv_total_interest
                        from prop_pending_arrears
                        join demand_with_penalty on demand_with_penalty.property_id = prop_pending_arrears.prop_id
                        where prop_pending_arrears.paid_status =0 and prop_pending_arrears.status =1
                        group by prop_pending_arrears.prop_id
                    ),
                    owners as (
                        select prop_owners.property_id,
                            string_agg((case when trim(prop_owners.owner_name_marathi)='' then prop_owners.owner_name else owner_name_marathi end),', ') as owner_name,
                            string_agg(prop_owners.mobile_no,', ') as mobile_no
                        from prop_owners
                        join demand_with_penalty on demand_with_penalty.property_id = prop_owners.property_id
                        where prop_owners.status =1
                        group by prop_owners.property_id
                    )
            ";
            $select=" select prop_properties.id,zone_masters.zone_name,ulb_ward_masters.ward_name,prop_properties.holding_no,prop_properties.property_no,
                        prop_properties.prop_address ,
                        owners.owner_name, owners.mobile_no, 
                        demand_with_penalty.from_year, demand_with_penalty.upto_year,                        
                        demand_with_penalty.actual_demand,
                        demand_with_penalty.arrear_demand,demand_with_penalty.current_demand,
                        demand_with_penalty.two_per_penalty,arrea_pending_penalty.priv_total_interest,
                        ( 
                            coalesce(demand_with_penalty.two_per_penalty,0)
                             + coalesce(arrea_pending_penalty.priv_total_interest,0)
                        ) as total_intrest,
                        (coalesce(demand_with_penalty.actual_demand,0)
                        + coalesce(demand_with_penalty.two_per_penalty,0)
                        + coalesce(arrea_pending_penalty.priv_total_interest,0)
                        ) as total_demand
            ";
            $from=" from demand_with_penalty
                    join prop_properties on prop_properties.id = demand_with_penalty.property_id
                    left join owners on owners.property_id = demand_with_penalty.property_id
                    left join arrea_pending_penalty on arrea_pending_penalty.prop_id = demand_with_penalty.property_id
                    left join zone_masters on zone_masters.id = prop_properties.zone_mstr_id
                    left join ulb_ward_masters on ulb_ward_masters.id = prop_properties.ward_mstr_id
            ";
            $where=" WHERE prop_properties.status IN(1,3)
                    " . ($wardMstrId ? " AND ulb_ward_masters.id = $wardMstrId" : "") . "  
                    " . ($zoneId ? " AND zone_masters.id = $zoneId" : "") . "
                    " . ($ulbId ? " AND prop_properties.ulb_id = $ulbId" : "") . " 
            ";
            $orderBy = " ORDER BY prop_properties.ward_mstr_id,prop_properties.id";
            $offsetLimit=" limit $limit offset $offset ";
            $dataSql = $with.$select.$from.$where.$orderBy.$offsetLimit;
            $countSql = $with." select count(*) AS total ".$from.$where;

            $data = $this->_DB_READ->SELECT($dataSql);

            $total = (collect($this->_DB_READ->SELECT($countSql))->first())->total ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    /**
     * | DCB Pie Chart
     */
    public function dcbPieChart($request)
    {
        $ulbId = $request->ulbId ?? authUser($request)->ulb_id;
        $currentDate = Carbon::now()->format('Y-m-d');
        $currentYear = Carbon::now()->year;
        $currentFyear = getFinancialYear($currentDate);
        $startOfCurrentYear = Carbon::createFromDate($currentYear, 4, 1); // Start date of current financial year
        $startOfPreviousYear = $startOfCurrentYear->copy()->subYear(); // Start date of previous financial year
        $previousFinancialYear = getFinancialYear($startOfPreviousYear);
        $startOfprePreviousYear = $startOfCurrentYear->copy()->subYear()->subYear();
        $prePreviousFinancialYear = getFinancialYear($startOfprePreviousYear);

        $sql1 = "SELECT     
                    '$currentFyear' as fyear,    
                    SUM (total_tax) AS totalDemand,
                    SUM(CASE WHEN paid_status =1 THEN total_tax ELSE 0 END )AS totalCollection,
                    sum (total_tax - CASE WHEN paid_status =1 THEN total_tax ELSE 0 END) as totalBalance
                FROM prop_demands 
                WHERE prop_demands.status =1 
                AND  fyear = '$currentFyear'
                AND  ulb_id = '$ulbId'";

        $sql2 = "SELECT     
                    '$previousFinancialYear' as fyear,    
                    SUM (total_tax) AS totalDemand,
                    SUM(CASE WHEN paid_status =1 THEN total_tax ELSE 0 END )AS totalCollection,
                    sum (total_tax - CASE WHEN paid_status =1 THEN total_tax ELSE 0 END) as totalBalance
                FROM prop_demands 
                WHERE prop_demands.status =1 
                AND fyear = '$previousFinancialYear'
                AND ulb_id = '$ulbId'";

        $sql3 = "SELECT     
                    '$prePreviousFinancialYear' as fyear,    
                    SUM (total_tax) AS totalDemand,
                    SUM(CASE WHEN paid_status =1 THEN total_tax ELSE 0 END )AS totalCollection,
                    sum (total_tax - CASE WHEN paid_status =1 THEN total_tax ELSE 0 END) as totalBalance
                FROM prop_demands 
                WHERE prop_demands.status =1 
                AND  fyear = '$prePreviousFinancialYear'
                AND  ulb_id = '$ulbId'";

        $currentYearDcb =  $this->_DB_READ->select($sql1);
        $previousYearDcb = $this->_DB_READ->select($sql2);
        $prePreviousYearDcb = $this->_DB_READ->select($sql3);

        $data = [
            collect($currentYearDcb)->first(),
            collect($previousYearDcb)->first(),
            collect($prePreviousYearDcb)->first()
        ];

        return responseMsgs(true, "", remove_null($data), "010203", "", "", 'POST', "");
    }

    /**
     * | Rebate and Penalty
     */
    public function rebateNpenalty($request)
    {
        $uptoDate = $request->uptoDate;
        $fromDate = $request->fromDate;
        $propCollection = null;
        $safCollection = null;
        $gbsafCollection = null;
        $wardId = null;
        $propertyType = null;
        $paymentMode = null;
        $propCount = 0;
        $safCount = 0;
        $gbsafCount = 0;
        $proptotal = 0;
        $saftotal = 0;
        $propPaidAmt = 0;
        $safPaidAmt = 0;
        $gbsafPaidAmt = 0;
        $propDemandAmt = 0;
        $safDemandAmt = 0;
        $gbsafDemandAmt = 0;
        $propPenaltyAmt = 0;
        $safPenaltyAmt = 0;
        $gbsafPenaltyAmt = 0;
        $propOnlineRebateAmt = 0;
        $safOnlineRebateAmt = 0;
        $gbsafOnlineRebateAmt = 0;
        $propSpecialRebateAmt = 0;
        $safSpecialRebateAmt = 0;
        $gbsafSpecialRebateAmt = 0;
        $propFirstQtrRebateAmt = 0;
        $safFirstQtrRebateAmt = 0;
        $gbsafFirstQtrRebateAmt = 0;
        $propJskRebateAmt = 0;
        $safJskRebateAmt = 0;
        $gbsafJskRebateAmt = 0;
        $propTotalRebate = 0;
        $safTotalRebate = 0;
        $gbsafTotalRebate = 0;
        $reportTypes = $request->reportType;
        $paymentMode = $request->paymentMode;
        $propertyType = $request->propertyType;
        $perPage = $request->perPage ?? 5;
        $page = $request->page && $request->page > 0 ? $request->page : 1;
        $limit = $perPage;
        $currentPage = $request->page ?? 1;
        $offset =  $request->page && $request->page > 0 ? ($request->page - 1 * $perPage) : 0;
        $rebatePenalList = collect(Config::get('PropertyConstaint.REBATE_PENAL_MASTERS'));

        $onePercPenalty  =  $rebatePenalList->where('key', 'onePercPenalty')->first()['value'];
        $firstQtrRebate  =  $rebatePenalList->where('key', 'firstQtrRebate')->first()['value'];
        $jskonlineRebate =  $rebatePenalList->where('key', 'onlineRebate')->first()['value'];
        $specialRebate   =  $rebatePenalList->where('key', 'specialRebate')->first()['value'];
        $onlineRebate    =  $rebatePenalList->where('key', 'onlineRebate5%')->first()['value'];
        $jskRebate       =  $rebatePenalList->where('key', 'jskRebate2.5%')->first()['value'];

        if ($request->wardId) {
            $wardId = $request->wardId;
        }

        if ($request->propertyType) {
            $propertyType = $request->propertyType;
        }

        if ($request->paymentMode) {
            $paymentMode = $request->paymentMode;
        }

        foreach ($reportTypes as $reportType) {
            if ($reportType == 'property') {

                $sql1 = "select t.id,payment_mode,ward_name as ward_no,
                                'property' as type,
                                prop_properties.saf_no,holding_no,new_holding_no,
                                round(t.demand_amt) as demand_amt,
                                round(t.amount) as paid_amount,
                                round(penalty_amt)as penalty_amt,
                                online_rebate_amt,
                                special_rebate_amt,
                                first_qtr_rebate,
                                jsk_rebate_amt,
                                CASE WHEN  t.property_id is not null THEN t.property_id END AS property_id
                        from prop_transactions as t
                            join (select  tran_id,
                                        CASE WHEN  head_name = '$onePercPenalty' THEN sum(prop_penaltyrebates.amount) END AS penalty_amt,
                                        CASE WHEN  head_name = '$onlineRebate' THEN sum(prop_penaltyrebates.amount) 
                                             WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'ONLINE' then sum(prop_penaltyrebates.amount) END AS online_rebate_amt,
                                        CASE WHEN  head_name = '$firstQtrRebate' THEN sum(prop_penaltyrebates.amount) END AS first_qtr_rebate,
                                        CASE WHEN  head_name = '$specialRebate' THEN sum(prop_penaltyrebates.amount) END AS special_rebate_amt,
                                        CASE WHEN  head_name = '$jskRebate' THEN sum(prop_penaltyrebates.amount) 
                                             WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'CASH' then  sum(prop_penaltyrebates.amount) END AS jsk_rebate_amt 
                                    from prop_penaltyrebates 
                                    join prop_transactions on prop_penaltyrebates.tran_id=prop_transactions.id
                                    where prop_penaltyrebates.status = 1
                                " .  ($paymentMode ? " AND prop_transactions.payment_mode = '$paymentMode' " : "") . "
                                    group by tran_id,head_name,payment_mode
                                ) as pr on pr.tran_id = t.id 
                            join prop_properties on prop_properties.id = t.property_id
                            join ulb_ward_masters on ulb_ward_masters.id = prop_properties.ward_mstr_id
                            where t.tran_date between '$fromDate' and '$uptoDate'
                            and t.status = 1
                            " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                            " . ($propertyType ? " AND prop_properties.prop_type_mstr_id = $propertyType" : "") . "
                            limit $limit offset $offset";

                $sql = "select count(*) as total,
                                t.id,t.amount as paid_amount, t.demand_amt,penalty_amt,
                                online_rebate_amt,
                                special_rebate_amt,
                                first_qtr_rebate,
                                jsk_rebate_amt
                            from prop_transactions as t
                            join (select  tran_id,
                                    CASE WHEN  head_name = '$onePercPenalty' THEN sum(prop_penaltyrebates.amount) END AS penalty_amt,
                                    CASE WHEN  head_name = '$onlineRebate' THEN sum(prop_penaltyrebates.amount) 
                                         WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'ONLINE' then sum(prop_penaltyrebates.amount) END AS online_rebate_amt,
                                    CASE WHEN  head_name = '$firstQtrRebate' THEN sum(prop_penaltyrebates.amount) END AS first_qtr_rebate,
                                    CASE WHEN  head_name = '$specialRebate' THEN sum(prop_penaltyrebates.amount) END AS special_rebate_amt,
                                    CASE WHEN  head_name = '$jskRebate' THEN sum(prop_penaltyrebates.amount) 
                                         WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'CASH' then  sum(prop_penaltyrebates.amount) END AS jsk_rebate_amt 
                                from prop_penaltyrebates 
                                join prop_transactions on prop_penaltyrebates.tran_id=prop_transactions.id
                                where prop_penaltyrebates.status = 1
                                group by tran_id,head_name,payment_mode
                            ) as pr on pr.tran_id = t.id 
                        join prop_properties on prop_properties.id = t.property_id
                        where t.tran_date between '$fromDate' and '$uptoDate'
                        and t.status = 1
                        group by t.id,
                                t.demand_amt,penalty_amt,
                                online_rebate_amt,
                                special_rebate_amt,
                                first_qtr_rebate,
                                jsk_rebate_amt";

                $propData =  $this->_DB_READ->select($sql1);
                $proptotalData = collect($this->_DB_READ->select($sql));
                $propCount = collect($proptotalData)->sum('total');
                $propPaidAmt = collect($proptotalData)->sum('paid_amount');
                $propDemandAmt = collect($proptotalData)->sum('demand_amt');
                $propPenaltyAmt = collect($proptotalData)->sum('penalty_amt');
                $propOnlineRebateAmt = collect($proptotalData)->sum('online_rebate_amt');
                $propSpecialRebateAmt = collect($proptotalData)->sum('special_rebate_amt');
                $propFirstQtrRebateAmt = collect($proptotalData)->sum('first_qtr_amt');
                $propJskRebateAmt = collect($proptotalData)->sum('jsk_rebate_amt');
                $propTotalRebate = $propOnlineRebateAmt + $propSpecialRebateAmt + $propFirstQtrRebateAmt + $propJskRebateAmt;
                $propCollection = $propData;
            }

            if ($reportType == 'saf') {

                $sql2 = "select
                                t.id,payment_mode,saf_no,ward_name as ward_no,
                                'saf' as type,
                                CASE WHEN  t.saf_id is not null THEN t.saf_id END AS saf_id,
                                round(t.amount) as paid_amount,
                                round(pr.demand_amt)as demand_amt,
                                round(sum(penalty_amt)) as penalty_amt,
                                round(sum(online_rebate_amt)) as online_rebate_amt,
                                round(sum(first_qtr_rebate)) as first_qtr_rebate,
                                round(sum(jsk_rebate_amt)) as jsk_rebate_amt,
                                round(sum(special_rebate_amt)) as special_rebate_amt
                                from prop_transactions as t
                                join (select  tran_id,demand_amt,
                                            CASE WHEN  head_name = '$onePercPenalty' THEN sum(prop_penaltyrebates.amount) END AS penalty_amt,
                                            CASE WHEN  head_name = '$onlineRebate' THEN sum(prop_penaltyrebates.amount) 
                                                WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'ONLINE' then sum(prop_penaltyrebates.amount) END AS online_rebate_amt,
                                            CASE WHEN  head_name = '$firstQtrRebate' THEN sum(prop_penaltyrebates.amount) END AS first_qtr_rebate,
                                            CASE WHEN  head_name = '$specialRebate' THEN sum(prop_penaltyrebates.amount) END AS special_rebate_amt,
                                            CASE WHEN  head_name = '$jskRebate' THEN sum(prop_penaltyrebates.amount) 
                                                WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'CASH' then  sum(prop_penaltyrebates.amount) END AS jsk_rebate_amt 
                                        from prop_penaltyrebates 
                                        join prop_transactions on prop_penaltyrebates.tran_id = prop_transactions.id
                                        where prop_penaltyrebates.status = 1
                                        " .  ($paymentMode ? " AND prop_transactions.payment_mode = '$paymentMode' " : "") . "
                                        group by tran_id,head_name,payment_mode,demand_amt
                                        ) as pr on pr.tran_id = t.id
                        join prop_active_safs on prop_active_safs.id = t.saf_id
                        join ulb_ward_masters on ulb_ward_masters.id = prop_active_safs.ward_mstr_id
                        where t.tran_date between '$fromDate' and '$uptoDate'
                        and t.status = 1
                        " . ($wardId ? " AND prop_active_safs.ward_mstr_id = $wardId" : "") . "
                        " . ($propertyType ? " AND prop_active_safs.prop_type_mstr_id = " . $propertyType . "" : "") . "
                        group by t.id,payment_mode,pr.demand_amt,saf_no,ward_name,t.saf_id
                        limit $limit offset $offset";

                $sql = "select
                                count(*) as total,
                                t.id,t.amount as paid_amount, t.demand_amt,penalty_amt,
                                online_rebate_amt,
                                special_rebate_amt,
                                first_qtr_rebate,
                                jsk_rebate_amt
                            from prop_transactions as t
                            join 
                        (select  tran_id,demand_amt,
                            CASE WHEN  head_name = '$onePercPenalty' THEN sum(prop_penaltyrebates.amount) END AS penalty_amt,
                            CASE WHEN  head_name = '$onlineRebate' THEN sum(prop_penaltyrebates.amount) 
                                 WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'ONLINE' then sum(prop_penaltyrebates.amount) END AS online_rebate_amt,
                            CASE WHEN  head_name = '$firstQtrRebate' THEN sum(prop_penaltyrebates.amount) END AS first_qtr_rebate,
                            CASE WHEN  head_name = '$specialRebate' THEN sum(prop_penaltyrebates.amount) END AS special_rebate_amt,
                            CASE WHEN  head_name = '$jskRebate' THEN sum(prop_penaltyrebates.amount) 
                                 WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'CASH' then  sum(prop_penaltyrebates.amount) END AS jsk_rebate_amt 
                        from prop_penaltyrebates 
                        join prop_transactions on prop_penaltyrebates.tran_id=prop_transactions.id
                        where prop_penaltyrebates.status = 1
                        group by tran_id,head_name,payment_mode,demand_amt
                        ) as pr on pr.tran_id = t.id
                    join prop_active_safs on prop_active_safs.id = t.saf_id
                    where t.tran_date between '$fromDate' and '$uptoDate'
                    and t.status = 1
                    group by t.id,
                             t.demand_amt,penalty_amt,
                             online_rebate_amt,
                             special_rebate_amt,
                             first_qtr_rebate,
                             jsk_rebate_amt";

                $safData =  $this->_DB_READ->select($sql2);
                $saftotalData = collect($this->_DB_READ->select($sql));
                $safCount = collect($saftotalData)->sum('total');
                $safPaidAmt = collect($saftotalData)->sum('paid_amount');
                $safDemandAmt = collect($saftotalData)->sum('demand_amt');
                $safPenaltyAmt = collect($saftotalData)->sum('penalty_amt');
                $safOnlineRebateAmt = collect($saftotalData)->sum('online_rebate_amt');
                $safSpecialRebateAmt = collect($saftotalData)->sum('special_rebate_amt');
                $safFirstQtrRebateAmt = collect($saftotalData)->sum('first_qtr_amt');
                $safJskRebateAmt = collect($saftotalData)->sum('jsk_rebate_amt');
                $safTotalRebate = $safOnlineRebateAmt + $safSpecialRebateAmt + $safFirstQtrRebateAmt + $safJskRebateAmt;
                $safCollection = $safData;
            }

            if ($reportType == 'gbsaf') {

                $sql3 = "select
                            payment_mode,
                            'gbsaf' as type,
                            tran_id,saf_no,ward_name as ward_no,
                            t.amount as paid_amount,
                            pr.demand_amt,
                            sum(penalty_amt) as penalty_amt,
                            sum(online_rebate_amt) as online_rebate_amt,
                            sum(first_qtr_rebate) as first_qtr_rebate,
                            sum(jsk_rebate_amt) as jsk_rebate_amt,
                            sum(special_rebate_amt) as special_rebate_amt
                            from prop_transactions as t
                            join (
                                select  tran_id,demand_amt,
                                    CASE WHEN  head_name = '$onePercPenalty' THEN sum(prop_penaltyrebates.amount) END AS penalty_amt,
                                    CASE WHEN  head_name = '$onlineRebate' THEN sum(prop_penaltyrebates.amount) 
                                         WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'ONLINE' then sum(prop_penaltyrebates.amount) END AS online_rebate_amt,
                                    CASE WHEN  head_name = '$firstQtrRebate' THEN sum(prop_penaltyrebates.amount) END AS first_qtr_rebate,
                                    CASE WHEN  head_name = '$specialRebate' THEN sum(prop_penaltyrebates.amount) END AS special_rebate_amt,
                                    CASE WHEN  head_name = '$jskRebate' THEN sum(prop_penaltyrebates.amount) 
                                         WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'CASH' then  sum(prop_penaltyrebates.amount) END AS jsk_rebate_amt 
                                from prop_penaltyrebates 
                                join prop_transactions on prop_penaltyrebates.tran_id=prop_transactions.id
                                where prop_penaltyrebates.status = 1
                                " .  ($paymentMode ? " AND prop_transactions.payment_mode = '$paymentMode' " : "") . "
                                group by tran_id,head_name,payment_mode,demand_amt
                            ) as pr on pr.tran_id = t.id
                        join prop_active_safs on prop_active_safs.id = t.saf_id
                        join ulb_ward_masters on ulb_ward_masters.id = prop_active_safs.ward_mstr_id
                        where t.tran_date between '$fromDate' and '$uptoDate'
                        and is_gb_saf = true
                        and t.status = 1
                        " . ($wardId ? " AND prop_active_safs.ward_mstr_id = $wardId" : "") . "
                        " . ($propertyType ? " AND prop_active_safs.prop_type_mstr_id = $propertyType" : "") . "
                        group by tran_id,payment_mode,pr.demand_amt,saf_no,ward_name
                        limit $limit offset $offset";

                $sql = "select
                                count(*) as total,
                                t.id,t.amount as paid_amount, t.demand_amt,penalty_amt,
                                online_rebate_amt,
                                special_rebate_amt,
                                first_qtr_rebate,
                                jsk_rebate_amt
                            from prop_transactions as t
                            join (
                                select  tran_id,demand_amt,
                                    CASE WHEN  head_name = '$onePercPenalty' THEN sum(prop_penaltyrebates.amount) END AS penalty_amt,
                                    CASE WHEN  head_name = '$onlineRebate' THEN sum(prop_penaltyrebates.amount) 
                                         WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'ONLINE' then sum(prop_penaltyrebates.amount) END AS online_rebate_amt,
                                    CASE WHEN  head_name = '$firstQtrRebate' THEN sum(prop_penaltyrebates.amount) END AS first_qtr_rebate,
                                    CASE WHEN  head_name = '$specialRebate' THEN sum(prop_penaltyrebates.amount) END AS special_rebate_amt,
                                    CASE WHEN  head_name = '$jskRebate' THEN sum(prop_penaltyrebates.amount) 
                                         WHEN  head_name = '$jskonlineRebate' AND prop_transactions.payment_mode = 'CASH' then  sum(prop_penaltyrebates.amount) END AS jsk_rebate_amt 
                                from prop_penaltyrebates 
                                join prop_transactions on prop_penaltyrebates.tran_id = prop_transactions.id
                                where prop_penaltyrebates.status = 1
                                group by tran_id,head_name,payment_mode,demand_amt
                            ) as pr on pr.tran_id = t.id
                            join prop_active_safs on prop_active_safs.id = t.saf_id
                            where t.tran_date between '$fromDate' and '$uptoDate'
                            and is_gb_saf = true
                            and t.status = 1
                            group by t.id,
                                     t.demand_amt,penalty_amt,
                                     online_rebate_amt,
                                     special_rebate_amt,
                                     first_qtr_rebate,
                                     jsk_rebate_amt";

                $gbsafData =  $this->_DB_READ->select($sql3);
                $gbsaftotalData = collect($this->_DB_READ->select($sql));
                $gbsafCount = collect($gbsaftotalData)->sum('total');
                $gbsafPaidAmt = collect($gbsaftotalData)->sum('paid_amount');
                $gbsafDemandAmt = collect($gbsaftotalData)->sum('demand_amt');
                $gbsafPenaltyAmt = collect($gbsaftotalData)->sum('penalty_amt');
                $gbsafOnlineRebateAmt = collect($gbsaftotalData)->sum('online_rebate_amt');
                $gbsafSpecialRebateAmt = collect($gbsaftotalData)->sum('special_rebate_amt');
                $gbsafFirstQtrRebateAmt = collect($gbsaftotalData)->sum('first_qtr_amt');
                $gbsafJskRebateAmt = collect($gbsaftotalData)->sum('jsk_rebate_amt');
                $gbsafTotalRebate = $gbsafOnlineRebateAmt + $gbsafSpecialRebateAmt + $gbsafFirstQtrRebateAmt + $gbsafJskRebateAmt;
                $gbsafCollection = $gbsafData;
            }
        }

        $details = collect($propCollection)->merge($safCollection)->merge($gbsafCollection);

        $a = round($propCount / $perPage);
        $b = round($safCount / $perPage);
        $c = round($gbsafCount / $perPage);
        $data['current_page'] = $currentPage;
        $data['total'] = $propCount + $safCount + $gbsafCount;
        $data['total_holding_no'] = $propCount;
        $data['total_saf_no'] = $safCount + $gbsafCount;
        $data['totalAmt'] = round($proptotal + $saftotal);
        $data['last_page'] = max($a, $b, $c);
        $data['total_paid_amount'] = round($propPaidAmt + $safPaidAmt + $gbsafPaidAmt);
        $data['total_demand_amt'] = round($propDemandAmt + $safDemandAmt + $gbsafDemandAmt);
        $data['total_penalty_amt'] = round($propPenaltyAmt + $safPenaltyAmt + $gbsafPenaltyAmt);
        $data['total_online_rebate_amt'] = round($propOnlineRebateAmt + $safOnlineRebateAmt + $gbsafOnlineRebateAmt);
        $data['total_special_rebate_amt'] = round($propSpecialRebateAmt + $safSpecialRebateAmt + $gbsafSpecialRebateAmt);
        $data['total_first_qtr_rebate'] = round($propFirstQtrRebateAmt + $safFirstQtrRebateAmt + $gbsafFirstQtrRebateAmt);
        $data['total_jsk_rebate_amt'] = round($propJskRebateAmt + $safJskRebateAmt + $gbsafJskRebateAmt);
        $data['total_total_rebate'] = round($propTotalRebate + $safTotalRebate + $gbsafTotalRebate);
        $data['reportTypes'] = $reportTypes;
        $data['data'] = $details;

        return responseMsgs(true, "", $data, "", "", "", "post", $request->deviceId);
    }

    /**
     * | Admin Dashboard Report for akola
     */
    public function adminDashReport(Request $request)
    {
        try {
            $currentFyear = getFY();
            $fromDate = $toDate = Carbon::now()->format("Y-m-d");
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $toDate = $request->uptoDate;
            }
            $query = " SELECT *,
                                (SELECT COUNT(id) FROM prop_properties) AS total_properties,
                                (SELECT ROUND(((zone1_collection/case when zone1_demand=0 then 1 else zone1_demand end)*100),2) as zone1_recovery),
                                (SELECT ROUND(((zone2_collection/case when zone2_demand=0 then 1 else zone2_demand end)*100),2) as zone2_recovery),
                                (SELECT ROUND(((zone3_collection/case when zone3_demand=0 then 1 else zone3_demand end)*100),2) as zone3_recovery),
                                (SELECT ROUND(((zone4_collection/case when zone4_demand=0 then 1 else zone4_demand end)*100),2) as zone4_recovery))
                                
                                FROM
                                -- Transaction Queries
                            (
                                SELECT 
                                        COALESCE(SUM(amount),0) AS today_collections,
                                        (
                                            SELECT COALESCE(SUM(amount),0) FROM prop_transactions as t
                                            JOIN prop_properties as p ON p.id=t.property_id
                                            WHERE p.zone_mstr_id=1 AND tran_date=CURRENT_DATE AND t.status=1
                                        ) as zone1_today_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'NEFT' THEN amount ELSE 0 END),0) AS neft_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'QR' THEN amount ELSE 0 END),0) AS qr_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'CASH' THEN amount ELSE 0 END),0) AS cash_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'DD' THEN amount ELSE 0 END),0) AS dd_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'ONLINE' THEN amount ELSE 0 END),0) AS online_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'CARD' THEN amount ELSE 0 END),0) AS card_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'CHEQUE' THEN amount ELSE 0 END),0) AS chque_collection,
                                        COALESCE(SUM(CASE WHEN payment_mode = 'RTGS' THEN amount ELSE 0 END),0) AS rtgs_collection
                                        FROM prop_transactions
                                WHERE tran_date=CURRENT_DATE and status=1
                            ) AS tc,
                            -- Property Demands Queries
                            (
                                SELECT cd.*,
                                        ar.*
                                    FROM
                                    (
                                        SELECT SUM(balance) AS current_demand
                                        FROM prop_demands
                                        WHERE fyear='$currentFyear' and paid_status=0 and status=1
                                    ) as cd,
                                    (
                                        SELECT  COALESCE(SUM(COALESCE(balance, 0)),0) AS arrear_demand
                                        FROM prop_demands
                                        WHERE fyear<'$currentFyear' and paid_status=0 and status=1
                                    ) as ar
                            
                            ) as current_arrear_demands,
                            (
                                SELECT 
                                SUM(CASE WHEN p.zone_mstr_id = 1 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone1_demand,
                                SUM(CASE WHEN p.zone_mstr_id = 2 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone2_demand,
                                SUM(CASE WHEN p.zone_mstr_id = 3 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone3_demand,
                                SUM(CASE WHEN p.zone_mstr_id = 4 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone4_demand
                            
                                FROM prop_demands as d
                                join prop_properties as p on p.id=d.property_id
                                where fyear='$currentFyear' and d.status=1 and p.status=1
                            ) as zone_wise_demands,
                            (
                                SELECT 
                                SUM(CASE WHEN p.zone_mstr_id = 1 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone1_collection,
                                SUM(CASE WHEN p.zone_mstr_id = 2 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone2_collection,
                                SUM(CASE WHEN p.zone_mstr_id = 3 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone3_collection,
                                SUM(CASE WHEN p.zone_mstr_id = 4 THEN COALESCE((total_tax-adjust_amt),0) ELSE 0 END) AS zone4_collection
                            
                                FROM prop_demands as d
                                join prop_properties as p on p.id=d.property_id
                                where fyear='$currentFyear' and d.paid_status=1 and p.status=1 and d.status=1
                            ) as zone_wise_collection,
                            (SELECT 
                                SUM(CASE WHEN p.zone_mstr_id = 1 THEN COALESCE(d.balance,0) ELSE 0 END) AS zone1_balance,
                                SUM(CASE WHEN p.zone_mstr_id = 2 THEN COALESCE(d.balance,0) ELSE 0 END) AS zone2_balance,
                                SUM(CASE WHEN p.zone_mstr_id = 3 THEN COALESCE(d.balance,0) ELSE 0 END) AS zone3_balance,
                                SUM(CASE WHEN p.zone_mstr_id = 4 THEN COALESCE(d.balance,0) ELSE 0 END) AS zone4_balance
                            
                                FROM prop_demands as d
                                join prop_properties as p on p.id=d.property_id
                                where fyear='$currentFyear' and paid_status=0 and p.status=1 and d.status=1
                            ) as zone_wise_balance,
                            (
                                SELECT 
                                COALESCE(SUM(CASE WHEN p.zone_mstr_id = 1 THEN COALESCE(t.amount,0) ELSE 0 END),0) as zone1_today_collection,
                                COALESCE(SUM(CASE WHEN p.zone_mstr_id = 2 THEN COALESCE(t.amount,0) ELSE 0 END),0) as zone2_today_collection,
                                COALESCE(SUM(CASE WHEN p.zone_mstr_id = 3 THEN COALESCE(t.amount,0) ELSE 0 END),0) as zone3_today_collection,
                                COALESCE(SUM(CASE WHEN p.zone_mstr_id = 4 THEN COALESCE(t.amount,0) ELSE 0 END),0) as zone4_today_collection
                                FROM prop_transactions AS t
                                JOIN (
                                    SELECT id, zone_mstr_id
                                    FROM prop_properties
                                        UNION ALL
                                    SELECT id, zone_mstr_id
                                    FROM prop_safs
                                ) AS p
                                ON (
                                    (t.tran_type = 'Property' AND t.property_id = p.id) OR
                                    (t.tran_type <> 'Property' AND t.saf_id = p.id)
                                )
                                WHERE t.tran_date = CURRENT_DATE AND t.status = 1
                            ) as zonewise_today_collection        
                        ";

            $report = $this->_DB_READ->select($query);
            $report[0]->zoneWiseReport = [
                [
                    'zone' => 1,
                    'demand' => $report[0]->zone1_demand,
                    'collection' => $report[0]->zone1_collection,
                    'todayCollection' => $report[0]->zone1_today_collection,
                    'recovery' => $report[0]->zone1_recovery,
                    'balance' => $report[0]->zone1_balance,
                ],
                [
                    'zone' => 2,
                    'demand' => $report[0]->zone2_demand,
                    'collection' => $report[0]->zone2_collection,
                    'todayCollection' => $report[0]->zone2_today_collection,
                    'recovery' => $report[0]->zone2_recovery,
                    'balance' => $report[0]->zone2_balance,
                ],
                [
                    'zone' => 3,
                    'demand' => $report[0]->zone3_demand,
                    'collection' => $report[0]->zone3_collection,
                    'todayCollection' => $report[0]->zone3_today_collection,
                    'recovery' => $report[0]->zone3_recovery,
                    'balance' => $report[0]->zone3_balance,
                ],
                [
                    'zone' => 4,
                    'demand' => $report[0]->zone4_demand,
                    'collection' => $report[0]->zone4_collection,
                    'todayCollection' => $report[0]->zone4_today_collection,
                    'recovery' => $report[0]->zone4_recovery,
                    'balance' => $report[0]->zone4_balance,
                ]
            ];

            $zoneWiseCollectionQuery = "SELECT z.id as zone_mstr_id,
                                           COUNT(p.id) AS total_properties,
                                           round(COALESCE(details.today_collections,0),2) as today_collections,
                                           round(COALESCE(details.neft_collection,0),2) as neft_collection,
                                           round(COALESCE(details.qr_collection,0),2) as qr_collection,
                                           round(COALESCE(details.cash_collection,0),2) as cash_collection,
                                           round(COALESCE(details.dd_collection,0),2) as dd_collection,
                                           round(COALESCE(details.online_collection,0),2) as online_collection,
                                           round(COALESCE(details.card_collection,0),2) as card_collection,
                                           round(COALESCE(details.chque_collection,0),2) as chque_collection,
                                           round(COALESCE(details.rtgs_collection,0),2) as rtgs_collection
                                       FROM 
                                       zone_masters as z 
                                       JOIN prop_properties AS p ON p.zone_mstr_id=z.id
                                       LEFT JOIN (
                                       SELECT  
                                               p.zone_mstr_id,
                                               COALESCE(SUM(amount),0) AS today_collections,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'NEFT' THEN amount ELSE 0 END),0) AS neft_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'QR' THEN amount ELSE 0 END),0) AS qr_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'CASH' THEN amount ELSE 0 END),0) AS cash_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'DD' THEN amount ELSE 0 END),0) AS dd_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'ONLINE' THEN amount ELSE 0 END),0) AS online_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'CARD' THEN amount ELSE 0 END),0) AS card_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'CHEQUE' THEN amount ELSE 0 END),0) AS chque_collection,
                                               COALESCE(SUM(CASE WHEN payment_mode = 'RTGS' THEN amount ELSE 0 END),0) AS rtgs_collection
                                   
                                           
                                           FROM prop_transactions AS t
                                           
                                           LEFT JOIN (
                                                (   
                                                    SELECT id,NULL AS saf_id, zone_mstr_id
                                                    FROM prop_properties
                                               )
                                               UNION ALL(
                                                    SELECT NULL AS id, id AS saf_id,zone_mstr_id
                                                    FROM prop_safs
                                               )
                                           ) AS p
                                           ON (
                                               (t.tran_type = 'Property' AND t.property_id = p.id) OR
                                               (t.tran_type <> 'Property' AND t.saf_id = p.saf_id)
                                           )
                                           LEFT JOIN zone_masters AS z on z.id=p.zone_mstr_id
                                           WHERE t.tran_date BETWEEN '$fromDate' AND '$toDate' AND t.status = 1
                                           GROUP BY zone_mstr_id 
                                           ) AS details on details.zone_mstr_id=z.id
                                           
                                           GROUP BY 
                                                        z.id,details.today_collections,
                                           details.today_collections,
                                           details.neft_collection,
                                           details.qr_collection,
                                           details.cash_collection,
                                           details.dd_collection,
                                           details.online_collection,
                                           details.card_collection,
                                           details.chque_collection,
                                           details.rtgs_collection";
            $zoneWiseReport = $this->_DB_READ->select($zoneWiseCollectionQuery);#print_var($query);print_var($zoneWiseCollectionQuery);die;
            $report[0]->zoneWiseReport = collect($zoneWiseReport);
            $report[0]->totalReport = [
                "total_properties" => collect($zoneWiseReport)->sum("total_properties"),
                "today_collections" => collect($zoneWiseReport)->sum("today_collections"),
                "neft_collection" => collect($zoneWiseReport)->sum("neft_collection"),
                "qr_collection" => collect($zoneWiseReport)->sum("qr_collection"),
                "cash_collection" => collect($zoneWiseReport)->sum("cash_collection"),
                "dd_collection" => collect($zoneWiseReport)->sum("dd_collection"),
                "online_collection" => collect($zoneWiseReport)->sum("online_collection"),
                "card_collection" => collect($zoneWiseReport)->sum("card_collection"),
                "chque_collection" => collect($zoneWiseReport)->sum("chque_collection"),
                "rtgs_collection" => collect($zoneWiseReport)->sum("rtgs_collection"),
            ];

            return responseMsgs(true, "Admin Dashboard Reports", collect($report)->first());
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    /**
     * | TC collection Report
     */
    public function tcCollectionReport()
    {
        try {
            $userId = auth()->user()->id;
            $query = " SELECT 
                            COALESCE(count(id),0) AS total_counter,
                            COALESCE(SUM(amount),0) AS total_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'NEFT' THEN amount ELSE 0 END),0) AS neft_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'QR' THEN amount ELSE 0 END),0) AS qr_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'CASH' THEN amount ELSE 0 END),0) AS cash_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'DD' THEN amount ELSE 0 END),0) AS dd_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'ONLINE' THEN amount ELSE 0 END),0) AS online_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'CARD' THEN amount ELSE 0 END),0) AS card_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'CHEQUE' THEN amount ELSE 0 END),0) AS chque_collection,
                            COALESCE(SUM(CASE WHEN payment_mode = 'RTGS' THEN amount ELSE 0 END),0) AS rtgs_collection
                            FROM prop_transactions
                    WHERE tran_date=CURRENT_DATE AND status=1 AND user_id=$userId";
            $response = $this->_DB_READ->select($query);
            return responseMsgs(true, "", remove_null($response[0]), "", "", "", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "", "", "POST", "");
        }
    }

    public function paymentModedealyCollectionRptV2(Request $request)
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
                if (!is_array($request->paymentMode))
                    $paymentMode = Str::upper($request->paymentMode);
                else {

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
            $fromFyear = getFy($fromDate);
            $uptoFyear = getFy($toDate);
            $query = "
            select 

                '$paymentMode' AS payment_mode,
                count(distinct(prop_transactions.id)) as total_tran,	
                sum(COALESCE(total_demand,0)::numeric) as total_demand,
                sum(COALESCE(total_tax,0)::numeric) as total_tax,
                sum(COALESCE(maintanance_amt,0)::numeric) as maintanance_amt,
                sum(COALESCE(aging_amt,0)::numeric) as aging_amt,
                sum(COALESCE(general_tax,0)::numeric) as general_tax,
                sum(COALESCE(road_tax,0)::numeric) as road_tax,
                sum(COALESCE(firefighting_tax,0)::numeric) as firefighting_tax,
                sum(COALESCE(education_tax,0)::numeric) as education_tax,
                sum(COALESCE(water_tax,0)::numeric) as water_tax,
                sum(COALESCE(cleanliness_tax,0)::numeric) as cleanliness_tax,
                sum(COALESCE(sewarage_tax,0)::numeric) as sewarage_tax,
                sum(COALESCE(tree_tax,0)::numeric) as tree_tax,
                sum(COALESCE(professional_tax,0)::numeric) as professional_tax,
                sum(COALESCE(adjust_amt,0)::numeric) as adjust_amt,
                sum(COALESCE(tax1,0)::numeric) as tax1,
                sum(COALESCE(tax2,0)::numeric) as tax2,
                sum(COALESCE(tax3,0)::numeric) as tax3,
                sum(COALESCE(sp_education_tax,0)::numeric) as sp_education_tax,
                sum(COALESCE(water_benefit,0)::numeric) as water_benefit,
                sum(COALESCE(water_bill,0)::numeric) as water_bill,
                sum(COALESCE(sp_water_cess,0)::numeric) as sp_water_cess,
                sum(COALESCE(drain_cess,0)::numeric) as drain_cess,
                sum(COALESCE(light_cess,0)::numeric) as light_cess,
                sum(COALESCE(major_building,0)::numeric) as major_building,
            
                sum(COALESCE(c1urrent_total_demand,0)::numeric) as c1urrent_total_demand,
	            sum(COALESCE(c1urrent_total_tax,0)::numeric) as c1urrent_total_tax,
                sum(COALESCE(current_maintanance_amt,0)::numeric ) as current_maintanance_amt,
                sum(COALESCE(current_aging_amt,0)::numeric ) as current_aging_amt,
                sum(COALESCE(current_general_tax,0)::numeric ) as current_general_tax,
                sum(COALESCE(current_road_tax,0)::numeric ) as current_road_tax,
                sum(COALESCE(current_firefighting_tax,0)::numeric ) as current_firefighting_tax,
                sum(COALESCE(current_education_tax,0)::numeric ) as current_education_tax,
                sum(COALESCE(current_water_tax,0)::numeric ) as current_water_tax,
                sum(COALESCE(current_cleanliness_tax,0)::numeric ) as current_cleanliness_tax,
                sum(COALESCE(current_sewarage_tax,0)::numeric ) as current_sewarage_tax,
                sum(COALESCE(current_tree_tax,0)::numeric ) as current_tree_tax,
                sum(COALESCE(current_professional_tax,0)::numeric ) as current_professional_tax,
                sum(COALESCE(current_adjust_amt,0)::numeric ) as current_adjust_amt,
                sum(COALESCE(current_tax1,0)::numeric ) as current_tax1,
                sum(COALESCE(current_tax2,0)::numeric ) as current_tax2,
                sum(COALESCE(current_tax3,0)::numeric ) as current_tax3,
                sum(COALESCE(current_sp_education_tax,0)::numeric ) as current_sp_education_tax,
                sum(COALESCE(current_water_benefit,0)::numeric ) as current_water_benefit,
                sum(COALESCE(current_water_bill,0)::numeric ) as current_water_bill,
                sum(COALESCE(current_sp_water_cess,0)::numeric ) as current_sp_water_cess,
                sum(COALESCE(current_drain_cess,0)::numeric ) as current_drain_cess,
                sum(COALESCE(current_light_cess,0)::numeric ) as current_light_cess,
                sum(COALESCE(current_major_building,0)::numeric ) as current_major_building,
            
                sum(COALESCE(a1rear_total_demand,0)::numeric) as a1rear_total_demand,
	            sum(COALESCE(a1rear_total_tax,0)::numeric) as a1rear_total_tax,
                sum(COALESCE(arear_maintanance_amt,0)::numeric ) as arear_maintanance_amt,
                sum(COALESCE(arear_aging_amt,0)::numeric ) as arear_aging_amt,
                sum(COALESCE(arear_general_tax,0)::numeric ) as arear_general_tax,
                sum(COALESCE(arear_road_tax,0)::numeric ) as arear_road_tax,
                sum(COALESCE(arear_firefighting_tax,0)::numeric ) as arear_firefighting_tax,
                sum(COALESCE(arear_education_tax,0)::numeric ) as arear_education_tax,
                sum(COALESCE(arear_water_tax,0)::numeric ) as arear_water_tax,
                sum(COALESCE(arear_cleanliness_tax,0)::numeric ) as arear_cleanliness_tax,
                sum(COALESCE(arear_sewarage_tax,0)::numeric ) as arear_sewarage_tax,
                sum(COALESCE(arear_tree_tax,0)::numeric ) as arear_tree_tax,
                sum(COALESCE(arear_professional_tax,0)::numeric ) as arear_professional_tax,
                sum(COALESCE(arear_adjust_amt,0)::numeric ) as arear_adjust_amt,
                sum(COALESCE(arear_tax1,0)::numeric ) as arear_tax1,
                sum(COALESCE(arear_tax2,0)::numeric ) as arear_tax2,
                sum(COALESCE(arear_tax3,0)::numeric ) as arear_tax3,
                sum(COALESCE(arear_sp_education_tax,0)::numeric ) as arear_sp_education_tax,
                sum(COALESCE(arear_water_benefit,0)::numeric ) as arear_water_benefit,
                sum(COALESCE(arear_water_bill,0)::numeric ) as arear_water_bill,
                sum(COALESCE(arear_sp_water_cess,0)::numeric ) as arear_sp_water_cess,
                sum(COALESCE(arear_drain_cess,0)::numeric ) as arear_drain_cess,
                sum(COALESCE(arear_light_cess,0)::numeric ) as arear_light_cess,
                sum(COALESCE(arear_major_building,0)::numeric ) as arear_major_building,
                sum(COALESCE(rebadet,0)::numeric) as rebadet,
                sum(COALESCE(penalty,0)::numeric) as penalty
            from prop_transactions
            join (
                select distinct(prop_transactions.id)as tran_id ,
                    sum(COALESCE(total_demand,0)::numeric) as total_demand,
                    sum(COALESCE(total_tax,0)::numeric) as total_tax,
                    sum(COALESCE(maintanance_amt,0)::numeric) as maintanance_amt,
                    sum(COALESCE(aging_amt,0)::numeric) as aging_amt,
                    sum(COALESCE(general_tax,0)::numeric) as general_tax,
                    sum(COALESCE(road_tax,0)::numeric) as road_tax,
                    sum(COALESCE(firefighting_tax,0)::numeric) as firefighting_tax,
                    sum(COALESCE(education_tax,0)::numeric) as education_tax,
                    sum(COALESCE(water_tax,0)::numeric) as water_tax,
                    sum(COALESCE(cleanliness_tax,0)::numeric) as cleanliness_tax,
                    sum(COALESCE(sewarage_tax,0)::numeric) as sewarage_tax,
                    sum(COALESCE(tree_tax,0)::numeric) as tree_tax,
                    sum(COALESCE(professional_tax,0)::numeric) as professional_tax,
                    sum(COALESCE(adjust_amt,0)::numeric) as adjust_amt,
                    sum(COALESCE(tax1,0)::numeric) as tax1,
                    sum(COALESCE(tax2,0)::numeric) as tax2,
                    sum(COALESCE(tax3,0)::numeric) as tax3,
                    sum(COALESCE(sp_education_tax,0)::numeric) as sp_education_tax,
                    sum(COALESCE(water_benefit,0)::numeric) as water_benefit,
                    sum(COALESCE(water_bill,0)::numeric) as water_bill,
                    sum(COALESCE(sp_water_cess,0)::numeric) as sp_water_cess,
                    sum(COALESCE(drain_cess,0)::numeric) as drain_cess,
                    sum(COALESCE(light_cess,0)::numeric) as light_cess,
                    sum(COALESCE(major_building,0)::numeric) as major_building,
                
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(total_demand,0)::numeric else 0 end) as c1urrent_total_demand,
		            sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(total_tax,0)::numeric else 0 end) as c1urrent_total_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(maintanance_amt,0)::numeric else 0 end) as current_maintanance_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(aging_amt,0)::numeric else 0 end) as current_aging_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(general_tax,0)::numeric else 0 end) as current_general_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(road_tax,0)::numeric else 0 end) as current_road_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(firefighting_tax,0)::numeric else 0 end) as current_firefighting_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(education_tax,0)::numeric else 0 end) as current_education_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(water_tax,0)::numeric else 0 end) as current_water_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(cleanliness_tax,0)::numeric else 0 end) as current_cleanliness_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(sewarage_tax,0)::numeric else 0 end) as current_sewarage_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(tree_tax,0)::numeric else 0 end) as current_tree_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(professional_tax,0)::numeric else 0 end) as current_professional_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(adjust_amt,0)::numeric else 0 end) as current_adjust_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(tax1,0)::numeric else 0 end) as current_tax1,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(tax2,0)::numeric else 0 end) as current_tax2,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(tax3,0)::numeric else 0 end) as current_tax3,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(sp_education_tax,0)::numeric else 0 end) as current_sp_education_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(water_benefit,0)::numeric else 0 end) as current_water_benefit,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(water_bill,0)::numeric else 0 end) as current_water_bill,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(sp_water_cess,0)::numeric else 0 end) as current_sp_water_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(drain_cess,0)::numeric else 0 end) as current_drain_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(light_cess,0)::numeric else 0 end) as current_light_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(major_building,0)::numeric else 0 end) as current_major_building,
                
                    sum(case when fyear < '$fromFyear' then COALESCE(total_demand,0)::numeric else 0 end) as a1rear_total_demand,
		            sum(case when fyear < '$fromFyear' then COALESCE(total_tax,0)::numeric else 0 end) as a1rear_total_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(maintanance_amt,0)::numeric else 0 end) as arear_maintanance_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(aging_amt,0)::numeric else 0 end) as arear_aging_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(general_tax,0)::numeric else 0 end) as arear_general_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(road_tax,0)::numeric else 0 end) as arear_road_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(firefighting_tax,0)::numeric else 0 end) as arear_firefighting_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(education_tax,0)::numeric else 0 end) as arear_education_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(water_tax,0)::numeric else 0 end) as arear_water_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(cleanliness_tax,0)::numeric else 0 end) as arear_cleanliness_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(sewarage_tax,0)::numeric else 0 end) as arear_sewarage_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(tree_tax,0)::numeric else 0 end) as arear_tree_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(professional_tax,0)::numeric else 0 end) as arear_professional_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(adjust_amt,0)::numeric else 0 end) as arear_adjust_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(tax1,0)::numeric else 0 end) as arear_tax1,
                    sum(case when fyear < '$fromFyear' then COALESCE(tax2,0)::numeric else 0 end) as arear_tax2,
                    sum(case when fyear < '$fromFyear' then COALESCE(tax3,0)::numeric else 0 end) as arear_tax3,
                    sum(case when fyear < '$fromFyear' then COALESCE(sp_education_tax,0)::numeric else 0 end) as arear_sp_education_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(water_benefit,0)::numeric else 0 end) as arear_water_benefit,
                    sum(case when fyear < '$fromFyear' then COALESCE(water_bill,0)::numeric else 0 end) as arear_water_bill,
                    sum(case when fyear < '$fromFyear' then COALESCE(sp_water_cess,0)::numeric else 0 end) as arear_sp_water_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(drain_cess,0)::numeric else 0 end) as arear_drain_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(light_cess,0)::numeric else 0 end) as arear_light_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(major_building,0)::numeric else 0 end) as arear_major_building
                
                from prop_tran_dtls                
                join prop_transactions on prop_transactions.id = prop_tran_dtls.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                join prop_demands on prop_demands.id = prop_tran_dtls.prop_demand_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_demands.status =1 
                    and prop_tran_dtls.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
                    
            )prop_tran_dtls on prop_tran_dtls.tran_id = prop_transactions.id
            left join(
                select distinct(prop_transactions.id)as tran_id ,
                    sum(case when prop_penaltyrebates.is_rebate =true then COALESCE(prop_penaltyrebates.amount,0) else 0 end) as rebadet,
                    sum(case when prop_penaltyrebates.is_rebate !=true then COALESCE(prop_penaltyrebates.amount,0) else 0 end) as penalty
                from prop_penaltyrebates
                join prop_transactions on prop_transactions.id = prop_penaltyrebates.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_penaltyrebates.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
            )fine_rebet on fine_rebet.tran_id = prop_transactions.id
            where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                and prop_transactions.status in(1,2)
                " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
            ";

            $report = $this->_DB_READ->select($query);
            $report = collect($report)->first();
            $data["report"] = collect($report)->map(function ($val, $key) {
                if ($key == "payment_mode") {
                    return $val;
                }
                return !is_null($val) ? $val : 0;
            });
            $arear = 0;
            $current = 0;
            $penalty = $report->penalty;
            $rebate  = $report->rebadet;
            $currentPattern = "/current_/i";
            $arrearPattern = "/arear_/i";
            foreach ($report as $key => $val) {
                if (preg_match($currentPattern, $key)) {
                    $current += ($val ? $val : 0);
                }
                if (preg_match($arrearPattern, $key)) {
                    $arear += ($val ? $val : 0);
                }
            };
            $arear = $arear + $penalty;
            $current = $current - $rebate;
            $data["total"] = [
                "arear" => roundFigure($arear),
                "current" => roundFigure($current),
                "total" => roundFigure(($arear + $current)),
            ];
            $data["headers"] = [
                "fromDate" => Carbon::parse($fromDate)->format('d-m-Y'),
                "uptoDate" => Carbon::parse($toDate)->format('d-m-Y'),
                "fromFyear" => $fromFyear,
                "uptoFyear" => $uptoFyear,
                "tcName" => $userId ? User::find($userId)->name ?? "" : "All",
                "WardName" => $wardId ? ulbWardMaster::find($wardId)->ward_name ?? "" : "All",
                "zoneName" => $zoneId ? (new ZoneMaster)->createZoneName($zoneId) ?? "" : "East/West/North/South",
                "paymentMode" => $paymentMode ? str::replace(",", "/", $paymentMode) : "All",
                "printDate" => Carbon::now()->format('d-m-Y H:i:s A'),
                "printedBy" => $user->name ?? "",
            ];
            return responseMsgs(true, "Admin Dashboard Reports", remove_null($data));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function paymentModedealyCollectionRptV1(Request $request)
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
            $fromFyear = getFy($fromDate);
            $uptoFyear = getFy($toDate);
            $query = "
            select 

                '$paymentMode' AS payment_mode,
                count(distinct(prop_transactions.id)) as total_tran,	
                sum(COALESCE(total_demand,0)::numeric) as total_demand,
                sum(COALESCE(total_tax,0)::numeric) as total_tax,
                sum(COALESCE(prop_transactions.amount,0)::numeric) as amount,
                sum(0) as arear_process_fee,
                sum(CASE WHEN prop_transactions.saf_id IS NOT NULL AND prop_transactions.tran_type ='Saf Proccess Fee' then prop_transactions.amount else 0 end) as current_process_fee,
				sum(
                    +(COALESCE(maintanance_amt,0)::numeric) 
                    +(COALESCE(aging_amt,0)::numeric) 
                    +(COALESCE(general_tax,0)::numeric) 
                    +(COALESCE(road_tax,0)::numeric) 
                    +(COALESCE(firefighting_tax,0)::numeric)
                    +(COALESCE(education_tax,0)::numeric)
                    +(COALESCE(water_tax,0)::numeric)
                    +(COALESCE(cleanliness_tax,0)::numeric)
                    +(COALESCE(sewarage_tax,0)::numeric)
                    +(COALESCE(tree_tax,0)::numeric)
                    +(COALESCE(professional_tax,0)::numeric)
                    +(COALESCE(adjust_amt,0)::numeric)
                    +(COALESCE(tax1,0)::numeric)
                    +(COALESCE(tax2,0)::numeric) 
                    +(COALESCE(tax3,0)::numeric)
                    +(COALESCE(sp_education_tax,0)::numeric) 
                    +(COALESCE(water_benefit,0)::numeric)
                    +(COALESCE(water_bill,0)::numeric)
                    +(COALESCE(sp_water_cess,0)::numeric)
                    +(COALESCE(drain_cess,0)::numeric)
                    +(COALESCE(light_cess,0)::numeric) 
                    +(COALESCE(major_building,0)::numeric) 
                    +(COALESCE(open_ploat_tax,0)::numeric)
                    -(COALESCE(exempted_general_tax,0)::numeric)
				)as total,
                sum(COALESCE(maintanance_amt,0)::numeric) as maintanance_amt,
                sum(COALESCE(aging_amt,0)::numeric) as aging_amt,
                sum(COALESCE(general_tax,0)::numeric) as general_tax,
                sum(COALESCE(exempted_general_tax,0)::numeric) as exempted_general_tax,
                sum(COALESCE(road_tax,0)::numeric) as road_tax,
                sum(COALESCE(firefighting_tax,0)::numeric) as firefighting_tax,
                sum(COALESCE(education_tax,0)::numeric) as education_tax,
                sum(COALESCE(water_tax,0)::numeric) as water_tax,
                sum(COALESCE(cleanliness_tax,0)::numeric) as cleanliness_tax,
                sum(COALESCE(sewarage_tax,0)::numeric) as sewarage_tax,
                sum(COALESCE(tree_tax,0)::numeric) as tree_tax,
                sum(COALESCE(professional_tax,0)::numeric) as professional_tax,
                sum(COALESCE(adjust_amt,0)::numeric) as adjust_amt,
                sum(COALESCE(tax1,0)::numeric) as tax1,
                sum(COALESCE(tax2,0)::numeric) as tax2,
                sum(COALESCE(tax3,0)::numeric) as tax3,
                sum(COALESCE(sp_education_tax,0)::numeric) as sp_education_tax,
                sum(COALESCE(water_benefit,0)::numeric) as water_benefit,
                sum(COALESCE(water_bill,0)::numeric) as water_bill,
                sum(COALESCE(sp_water_cess,0)::numeric) as sp_water_cess,
                sum(COALESCE(drain_cess,0)::numeric) as drain_cess,
                sum(COALESCE(light_cess,0)::numeric) as light_cess,
                sum(COALESCE(major_building,0)::numeric) as major_building,
                sum(COALESCE(open_ploat_tax,0)::numeric) as open_ploat_tax,
            
                sum(COALESCE(c1urrent_total_demand,0)::numeric) as c1urrent_total_demand,
	            sum(COALESCE(c1urrent_total_tax,0)::numeric) as c1urrent_total_tax,
                sum(
                    +(COALESCE(current_maintanance_amt,0)::numeric) 
                    +(COALESCE(current_aging_amt,0)::numeric) 
                    +(COALESCE(current_general_tax,0)::numeric) 
                    +(COALESCE(current_road_tax,0)::numeric) 
                    +(COALESCE(current_firefighting_tax,0)::numeric)
                    +(COALESCE(current_education_tax,0)::numeric)
                    +(COALESCE(current_water_tax,0)::numeric)
                    +(COALESCE(current_cleanliness_tax,0)::numeric)
                    +(COALESCE(current_sewarage_tax,0)::numeric)
                    +(COALESCE(current_tree_tax,0)::numeric)
                    +(COALESCE(current_professional_tax,0)::numeric)
                    +(COALESCE(current_adjust_amt,0)::numeric)
                    +(COALESCE(current_tax1,0)::numeric)
                    +(COALESCE(current_tax2,0)::numeric) 
                    +(COALESCE(current_tax3,0)::numeric)
                    +(COALESCE(current_sp_education_tax,0)::numeric) 
                    +(COALESCE(current_water_benefit,0)::numeric)
                    +(COALESCE(current_water_bill,0)::numeric)
                    +(COALESCE(current_sp_water_cess,0)::numeric)
                    +(COALESCE(current_drain_cess,0)::numeric)
                    +(COALESCE(current_light_cess,0)::numeric) 
                    +(COALESCE(current_major_building,0)::numeric) 
                    +(COALESCE(current_open_ploat_tax,0)::numeric)
                    -(COALESCE(current_exempted_general_tax,0)::numeric)
                )as c1urrent_total,
                sum(COALESCE(current_maintanance_amt,0)::numeric ) as current_maintanance_amt,
                sum(COALESCE(current_aging_amt,0)::numeric ) as current_aging_amt,
                sum(COALESCE(current_general_tax,0)::numeric ) as current_general_tax,
                sum(COALESCE(current_exempted_general_tax,0)::numeric ) as current_exempted_general_tax,
                sum(COALESCE(current_road_tax,0)::numeric ) as current_road_tax,
                sum(COALESCE(current_firefighting_tax,0)::numeric ) as current_firefighting_tax,
                sum(COALESCE(current_education_tax,0)::numeric ) as current_education_tax,
                sum(COALESCE(current_water_tax,0)::numeric ) as current_water_tax,
                sum(COALESCE(current_cleanliness_tax,0)::numeric ) as current_cleanliness_tax,
                sum(COALESCE(current_sewarage_tax,0)::numeric ) as current_sewarage_tax,
                sum(COALESCE(current_tree_tax,0)::numeric ) as current_tree_tax,
                sum(COALESCE(current_professional_tax,0)::numeric ) as current_professional_tax,
                sum(COALESCE(current_adjust_amt,0)::numeric ) as current_adjust_amt,
                sum(COALESCE(current_tax1,0)::numeric ) as current_tax1,
                sum(COALESCE(current_tax2,0)::numeric ) as current_tax2,
                sum(COALESCE(current_tax3,0)::numeric ) as current_tax3,
                sum(COALESCE(current_sp_education_tax,0)::numeric ) as current_sp_education_tax,
                sum(COALESCE(current_water_benefit,0)::numeric ) as current_water_benefit,
                sum(COALESCE(current_water_bill,0)::numeric ) as current_water_bill,
                sum(COALESCE(current_sp_water_cess,0)::numeric ) as current_sp_water_cess,
                sum(COALESCE(current_drain_cess,0)::numeric ) as current_drain_cess,
                sum(COALESCE(current_light_cess,0)::numeric ) as current_light_cess,
                sum(COALESCE(current_major_building,0)::numeric ) as current_major_building,
                sum(COALESCE(current_open_ploat_tax,0)::numeric ) as current_open_ploat_tax,
            
                sum(COALESCE(a1rear_total_demand,0)::numeric) as a1rear_total_demand,
	            sum(COALESCE(a1rear_total_tax,0)::numeric) as a1rear_total_tax,
                sum(
                    +(COALESCE(arear_maintanance_amt,0)::numeric) 
                    +(COALESCE(arear_aging_amt,0)::numeric) 
                    +(COALESCE(arear_general_tax,0)::numeric) 
                    +(COALESCE(arear_road_tax,0)::numeric) 
                    +(COALESCE(arear_firefighting_tax,0)::numeric)
                    +(COALESCE(arear_education_tax,0)::numeric)
                    +(COALESCE(arear_water_tax,0)::numeric)
                    +(COALESCE(arear_cleanliness_tax,0)::numeric)
                    +(COALESCE(arear_sewarage_tax,0)::numeric)
                    +(COALESCE(arear_tree_tax,0)::numeric)
                    +(COALESCE(arear_professional_tax,0)::numeric)
                    +(COALESCE(arear_adjust_amt,0)::numeric)
                    +(COALESCE(arear_tax1,0)::numeric)
                    +(COALESCE(arear_tax2,0)::numeric) 
                    +(COALESCE(arear_tax3,0)::numeric)
                    +(COALESCE(arear_sp_education_tax,0)::numeric) 
                    +(COALESCE(arear_water_benefit,0)::numeric)
                    +(COALESCE(arear_water_bill,0)::numeric)
                    +(COALESCE(arear_sp_water_cess,0)::numeric)
                    +(COALESCE(arear_drain_cess,0)::numeric)
                    +(COALESCE(arear_light_cess,0)::numeric) 
                    +(COALESCE(arear_major_building,0)::numeric) 
                    +(COALESCE(arear_open_ploat_tax,0)::numeric)
                    -(COALESCE(arear_exempted_general_tax,0)::numeric)
                )as a1rear_total,
                sum(COALESCE(arear_maintanance_amt,0)::numeric ) as arear_maintanance_amt,
                sum(COALESCE(arear_aging_amt,0)::numeric ) as arear_aging_amt,
                sum(COALESCE(arear_general_tax,0)::numeric ) as arear_general_tax,
                sum(COALESCE(arear_exempted_general_tax,0)::numeric ) as arear_exempted_general_tax,
                sum(COALESCE(arear_road_tax,0)::numeric ) as arear_road_tax,
                sum(COALESCE(arear_firefighting_tax,0)::numeric ) as arear_firefighting_tax,
                sum(COALESCE(arear_education_tax,0)::numeric ) as arear_education_tax,
                sum(COALESCE(arear_water_tax,0)::numeric ) as arear_water_tax,
                sum(COALESCE(arear_cleanliness_tax,0)::numeric ) as arear_cleanliness_tax,
                sum(COALESCE(arear_sewarage_tax,0)::numeric ) as arear_sewarage_tax,
                sum(COALESCE(arear_tree_tax,0)::numeric ) as arear_tree_tax,
                sum(COALESCE(arear_professional_tax,0)::numeric ) as arear_professional_tax,
                sum(COALESCE(arear_adjust_amt,0)::numeric ) as arear_adjust_amt,
                sum(COALESCE(arear_tax1,0)::numeric ) as arear_tax1,
                sum(COALESCE(arear_tax2,0)::numeric ) as arear_tax2,
                sum(COALESCE(arear_tax3,0)::numeric ) as arear_tax3,
                sum(COALESCE(arear_sp_education_tax,0)::numeric ) as arear_sp_education_tax,
                sum(COALESCE(arear_water_benefit,0)::numeric ) as arear_water_benefit,
                sum(COALESCE(arear_water_bill,0)::numeric ) as arear_water_bill,
                sum(COALESCE(arear_sp_water_cess,0)::numeric ) as arear_sp_water_cess,
                sum(COALESCE(arear_drain_cess,0)::numeric ) as arear_drain_cess,
                sum(COALESCE(arear_light_cess,0)::numeric ) as arear_light_cess,
                sum(COALESCE(arear_major_building,0)::numeric ) as arear_major_building,
                sum(COALESCE(arear_open_ploat_tax,0)::numeric ) as arear_open_ploat_tax,
                sum(COALESCE(rebadet,0)::numeric) as rebadet,
                sum(COALESCE(shasti_rebadet,0)::numeric) as shasti_rebadet,
				sum(COALESCE(total_rebadet,0)::numeric) as total_rebadet,
                sum(COALESCE(penalty,0)::numeric) as penalty,
				sum(COALESCE(advance_amount,0)::numeric) as advance_amount,
                sum(COALESCE(adjusted_amount,0)::numeric) as adjusted_amount
            from prop_transactions
            left join (
                select distinct(prop_transactions.id)as tran_id ,
                    sum(COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric) as total_demand,					
                    sum(COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric) as total_tax,
                    sum(COALESCE(prop_tran_dtls.paid_maintanance_amt,0)::numeric) as maintanance_amt,
                    sum(COALESCE(prop_tran_dtls.paid_aging_amt,0)::numeric) as aging_amt,
                    sum(COALESCE(prop_tran_dtls.paid_general_tax,0)::numeric) as general_tax,
                    sum(COALESCE(prop_tran_dtls.paid_exempted_general_tax,0)::numeric) as exempted_general_tax,
                    sum(COALESCE(prop_tran_dtls.paid_road_tax,0)::numeric) as road_tax,
                    sum(COALESCE(prop_tran_dtls.paid_firefighting_tax,0)::numeric) as firefighting_tax,
                    sum(COALESCE(prop_tran_dtls.paid_education_tax,0)::numeric) as education_tax,
                    sum(COALESCE(prop_tran_dtls.paid_water_tax,0)::numeric) as water_tax,
                    sum(COALESCE(prop_tran_dtls.paid_cleanliness_tax,0)::numeric) as cleanliness_tax,
                    sum(COALESCE(prop_tran_dtls.paid_sewarage_tax,0)::numeric) as sewarage_tax,
                    sum(COALESCE(prop_tran_dtls.paid_tree_tax,0)::numeric) as tree_tax,
                    sum(COALESCE(prop_tran_dtls.paid_professional_tax,0)::numeric) as professional_tax,
                    sum(COALESCE(prop_tran_dtls.paid_adjust_amt,0)::numeric) as adjust_amt,
                    sum(COALESCE(prop_tran_dtls.paid_tax1,0)::numeric) as tax1,
                    sum(COALESCE(prop_tran_dtls.paid_tax2,0)::numeric) as tax2,
                    sum(COALESCE(prop_tran_dtls.paid_tax3,0)::numeric) as tax3,
                    sum(COALESCE(prop_tran_dtls.paid_sp_education_tax,0)::numeric) as sp_education_tax,
                    sum(COALESCE(prop_tran_dtls.paid_water_benefit,0)::numeric) as water_benefit,
                    sum(COALESCE(prop_tran_dtls.paid_water_bill,0)::numeric) as water_bill,
                    sum(COALESCE(prop_tran_dtls.paid_sp_water_cess,0)::numeric) as sp_water_cess,
                    sum(COALESCE(prop_tran_dtls.paid_drain_cess,0)::numeric) as drain_cess,
                    sum(COALESCE(prop_tran_dtls.paid_light_cess,0)::numeric) as light_cess,
                    sum(COALESCE(prop_tran_dtls.paid_major_building,0)::numeric) as major_building,
                    sum(COALESCE(prop_tran_dtls.paid_open_ploat_tax,0)::numeric) as open_ploat_tax,
                
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as c1urrent_total_demand,
		            sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as c1urrent_total_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_maintanance_amt,0)::numeric else 0 end) as current_maintanance_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_aging_amt,0)::numeric else 0 end) as current_aging_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_general_tax,0)::numeric else 0 end) as current_general_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_exempted_general_tax,0)::numeric else 0 end) as current_exempted_general_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_road_tax,0)::numeric else 0 end) as current_road_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_firefighting_tax,0)::numeric else 0 end) as current_firefighting_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_education_tax,0)::numeric else 0 end) as current_education_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_water_tax,0)::numeric else 0 end) as current_water_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_cleanliness_tax,0)::numeric else 0 end) as current_cleanliness_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_sewarage_tax,0)::numeric else 0 end) as current_sewarage_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tree_tax,0)::numeric else 0 end) as current_tree_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_professional_tax,0)::numeric else 0 end) as current_professional_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_adjust_amt,0)::numeric else 0 end) as current_adjust_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tax1,0)::numeric else 0 end) as current_tax1,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tax2,0)::numeric else 0 end) as current_tax2,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tax3,0)::numeric else 0 end) as current_tax3,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_sp_education_tax,0)::numeric else 0 end) as current_sp_education_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_water_benefit,0)::numeric else 0 end) as current_water_benefit,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_water_bill,0)::numeric else 0 end) as current_water_bill,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_sp_water_cess,0)::numeric else 0 end) as current_sp_water_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_drain_cess,0)::numeric else 0 end) as current_drain_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_light_cess,0)::numeric else 0 end) as current_light_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_major_building,0)::numeric else 0 end) as current_major_building,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_open_ploat_tax,0)::numeric else 0 end) as current_open_ploat_tax,
                
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as a1rear_total_demand,
		            sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as a1rear_total_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_maintanance_amt,0)::numeric else 0 end) as arear_maintanance_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_aging_amt,0)::numeric else 0 end) as arear_aging_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_general_tax,0)::numeric else 0 end) as arear_general_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_exempted_general_tax,0)::numeric else 0 end) as arear_exempted_general_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_road_tax,0)::numeric else 0 end) as arear_road_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_firefighting_tax,0)::numeric else 0 end) as arear_firefighting_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_education_tax,0)::numeric else 0 end) as arear_education_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_water_tax,0)::numeric else 0 end) as arear_water_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_cleanliness_tax,0)::numeric else 0 end) as arear_cleanliness_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_sewarage_tax,0)::numeric else 0 end) as arear_sewarage_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tree_tax,0)::numeric else 0 end) as arear_tree_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_professional_tax,0)::numeric else 0 end) as arear_professional_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_adjust_amt,0)::numeric else 0 end) as arear_adjust_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tax1,0)::numeric else 0 end) as arear_tax1,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tax2,0)::numeric else 0 end) as arear_tax2,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tax3,0)::numeric else 0 end) as arear_tax3,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_sp_education_tax,0)::numeric else 0 end) as arear_sp_education_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_water_benefit,0)::numeric else 0 end) as arear_water_benefit,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_water_bill,0)::numeric else 0 end) as arear_water_bill,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_sp_water_cess,0)::numeric else 0 end) as arear_sp_water_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_drain_cess,0)::numeric else 0 end) as arear_drain_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_light_cess,0)::numeric else 0 end) as arear_light_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_major_building,0)::numeric else 0 end) as arear_major_building,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_open_ploat_tax,0)::numeric else 0 end) as arear_open_ploat_tax
                
                from prop_tran_dtls                
                join prop_transactions on prop_transactions.id = prop_tran_dtls.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                join prop_demands on prop_demands.id = prop_tran_dtls.prop_demand_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_demands.status =1 
                    and prop_tran_dtls.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
                    
            )prop_tran_dtls on prop_tran_dtls.tran_id = prop_transactions.id
            left join(
                select distinct(prop_transactions.id)as tran_id ,
                    sum(case when prop_penaltyrebates.is_rebate =true AND prop_penaltyrebates.head_name ='Shasti Abhay Yojana'  then COALESCE(round(prop_penaltyrebates.amount),0) else 0 end) as shasti_rebadet,
                    sum(case when prop_penaltyrebates.is_rebate =true AND prop_penaltyrebates.head_name !='Shasti Abhay Yojana' then COALESCE(round(prop_penaltyrebates.amount),0) else 0 end) as rebadet,
                    sum(case when prop_penaltyrebates.is_rebate =true then COALESCE(round(prop_penaltyrebates.amount),0) else 0 end) as total_rebadet,
                    sum(case when prop_penaltyrebates.is_rebate !=true then COALESCE(round(prop_penaltyrebates.amount),0) else 0 end) as penalty
                from prop_penaltyrebates
                join prop_transactions on prop_transactions.id = prop_penaltyrebates.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_penaltyrebates.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
            )fine_rebet on fine_rebet.tran_id = prop_transactions.id
            left join(
                select distinct(prop_transactions.id)as tran_id ,
                    sum(prop_advances.amount) as advance_amount
				from prop_advances
                join prop_transactions on prop_transactions.id = prop_advances.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate'  
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_advances.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "   
                group by prop_transactions.id
            )advance on advance.tran_id = prop_transactions.id			
			left join(
                select distinct(prop_transactions.id)as tran_id ,
                    sum(prop_adjustments.amount) as adjusted_amount
				from prop_adjustments
                join prop_transactions on prop_transactions.id = prop_adjustments.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_adjustments.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . " 
                group by prop_transactions.id
            )adjusted on adjusted.tran_id = prop_transactions.id
            where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                and prop_transactions.status in(1,2)
                " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
            ";
            // print_var($query);die;
            $report = $this->_DB_READ->select($query);
            $report = collect($report)->first();
            $report->maintanance_amt = round($report->current_maintanance_amt)     +  round($report->arear_maintanance_amt);
            $report->aging_amt       = round($report->current_aging_amt)           +  round($report->arear_aging_amt);
            $report->general_tax     = round($report->current_general_tax)         +  round($report->arear_general_tax);
            $report->exempted_general_tax     = round($report->current_exempted_general_tax)         +  round($report->arear_exempted_general_tax);
            $report->road_tax        = round($report->current_road_tax)            +  round($report->arear_road_tax);
            $report->firefighting_tax = round($report->current_firefighting_tax)    +  round($report->arear_firefighting_tax);
            $report->education_tax   = round($report->current_education_tax)       +  round($report->arear_education_tax);
            $report->water_tax       = round($report->current_water_tax)           +  round($report->arear_water_tax);
            $report->cleanliness_tax = round($report->current_cleanliness_tax)     +  round($report->arear_cleanliness_tax);
            $report->sewarage_tax    = round($report->current_sewarage_tax)        +  round($report->arear_sewarage_tax);
            $report->tree_tax        = round($report->current_tree_tax)            +  round($report->arear_tree_tax);
            $report->professional_tax = round($report->current_professional_tax)    +  round($report->arear_professional_tax);
            $report->tax1            = round($report->current_tax1)                +  round($report->arear_tax1);
            $report->tax2            = round($report->current_tax2)                +  round($report->arear_tax2);
            $report->tax3            = round($report->current_tax3)                +  round($report->arear_tax3);
            $report->sp_education_tax = round($report->current_sp_education_tax)    +  round($report->arear_sp_education_tax);
            $report->water_benefit   = round($report->current_water_benefit)       +  round($report->arear_water_benefit);
            $report->water_bill      = round($report->current_water_bill)          +  round($report->arear_water_bill);
            $report->sp_water_cess   = round($report->current_sp_water_cess)       +  round($report->arear_sp_water_cess);
            $report->drain_cess      = round($report->current_drain_cess)          +  round($report->arear_drain_cess);
            $report->light_cess      = round($report->current_light_cess)          +  round($report->arear_light_cess);
            $report->major_building  = round($report->current_major_building)      +  round($report->arear_major_building);
            $report->open_ploat_tax  = round($report->current_open_ploat_tax)      +  round($report->arear_open_ploat_tax);
            $report->net_advance     = round($report->advance_amount)      -  round($report->adjusted_amount);
            $report->process_fee     = round($report->arear_process_fee)  + round($report->current_process_fee)   ;

            $data["report"] = collect($report)->map(function ($val, $key) {
                if ($key == "payment_mode") {
                    return $val;
                }
                return !is_null($val) ? round($val) : 0;
            });
            // $penalty = $report->penalty;
            // $rebate  = $report->rebadet;
            // $arear = $report->a1rear_total_tax + $penalty;
            // $current = $report->c1urrent_total_tax - $rebate;
            $arear = 0;
            $current = 0;
            $advance = 0;
            $adjusted = 0;
            $penalty = $data["report"]["penalty"];
            $shasti_rebadet = $data["report"]["shasti_rebadet"];
            $rebate  = $data["report"]["rebadet"];
            $advance = $data["report"]["advance_amount"];
            $adjusted = $data["report"]["adjusted_amount"];
            // $arear = $data["report"]["a1rear_total"] + $penalty;
            // $current = $data["report"]["c1urrent_total"] - $rebate;
            $currentPattern = "/current_/i";
            $arrearPattern = "/arear_/i";
            $arrearExcemptedPattern = "/arear_exempted_/i";
            $currentExcemptedPattern = "/current_exempted_/i";
            foreach ($data["report"] as $key => $val) {
                if (preg_match($currentPattern, $key)) {
                    $current += ($val ? $val : 0);
                }
                if (preg_match($arrearPattern, $key)) {
                    $arear += ($val ? $val : 0);
                }
                if (preg_match($currentExcemptedPattern, $key)) {
                    $current -= ($val ? $val : 0);
                }
                if (preg_match($arrearExcemptedPattern, $key)) {
                    $arear -= ($val ? $val : 0);
                }
            };
            $arear = $arear + $penalty - $shasti_rebadet;
            $current = $current - $rebate + $advance - $adjusted;
            $data["total"] = [
                "arear" => round($arear),
                "current" => round($current),
                "total" => round(($arear + $current)),
            ];
            $data["headers"] = [
                "fromDate" => Carbon::parse($fromDate)->format('d-m-Y'),
                "uptoDate" => Carbon::parse($toDate)->format('d-m-Y'),
                "fromFyear" => $fromFyear,
                "uptoFyear" => $uptoFyear,
                "tcName" => $userId ? User::find($userId)->name ?? "" : "All",
                "WardName" => $wardId ? ulbWardMaster::find($wardId)->ward_name ?? "" : "All",
                "zoneName" => $zoneId ? (new ZoneMaster)->createZoneName($zoneId) ?? "" : "East/West/North/South",
                "paymentMode" => $paymentMode ? str::replace(",", "/", $paymentMode) : "All",
                "printDate" => Carbon::now()->format('d-m-Y H:i:s A'),
                "printedBy" => $user->name ?? "",
            ];
            return responseMsgs(true, "Admin Dashboard Reports", remove_null($data));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function individualDedealyCollectionRptV1(Request $request)
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
            $fromFyear = getFy($fromDate);
            $uptoFyear = getFy($toDate);
            $query = "
            select 
                users.name,
                prop_transactions.from_fyear,
                prop_transactions.to_fyear,
                prop_properties.property_no,
                prop_transactions.id as tran_id,
                prop_transactions.property_id,
                prop_transactions.payment_mode,
                prop_transactions.tran_no,	
                prop_transactions.tran_date,
                prop_transactions.book_no,
                CASE WHEN prop_cheque_dtls.id IS NULL THEN 1 ELSE prop_cheque_dtls.status END AS cheque_status,
                prop_cheque_dtls.cheque_no,
                prop_cheque_dtls.cheque_date,
                prop_cheque_dtls.bank_name,
                prop_cheque_dtls.branch_name,
                prop_cheque_dtls.clear_bounce_date,
                prop_properties.holding_no,
                case when trim(prop_properties.applicant_marathi) is null then prop_properties.applicant_name else prop_properties.applicant_marathi end as applicant_name ,
                ulb_ward_masters.ward_name,
                zone_masters.zone_name,
                owners.owner_name,
                owners.guardian_name,
                owners.mobile_no,
                
                COALESCE(total_demand,0::numeric) as total_demand,
                COALESCE(total_tax,0::numeric) as total_tax,
                COALESCE(prop_transactions.amount,0::numeric) as amount,
                (
                    +(COALESCE(maintanance_amt,0)::numeric) 
                    +(COALESCE(aging_amt,0)::numeric) 
                    +(COALESCE(general_tax,0)::numeric) 
                    +(COALESCE(road_tax,0)::numeric) 
                    +(COALESCE(firefighting_tax,0)::numeric)
                    +(COALESCE(education_tax,0)::numeric)
                    +(COALESCE(water_tax,0)::numeric)
                    +(COALESCE(cleanliness_tax,0)::numeric)
                    +(COALESCE(sewarage_tax,0)::numeric)
                    +(COALESCE(tree_tax,0)::numeric)
                    +(COALESCE(professional_tax,0)::numeric)
                    +(COALESCE(adjust_amt,0)::numeric)
                    +(COALESCE(tax1,0)::numeric)
                    +(COALESCE(tax2,0)::numeric) 
                    +(COALESCE(tax3,0)::numeric)
                    +(COALESCE(sp_education_tax,0)::numeric) 
                    +(COALESCE(water_benefit,0)::numeric)
                    +(COALESCE(water_bill,0)::numeric)
                    +(COALESCE(sp_water_cess,0)::numeric)
                    +(COALESCE(drain_cess,0)::numeric)
                    +(COALESCE(light_cess,0)::numeric) 
                    +(COALESCE(major_building,0)::numeric) 
                    +(COALESCE(open_ploat_tax,0)::numeric)
                )as total,
                (COALESCE(maintanance_amt,0)::numeric) as maintanance_amt,
                (COALESCE(aging_amt,0)::numeric) as aging_amt,
                (COALESCE(general_tax,0)::numeric) as general_tax,
                (COALESCE(road_tax,0)::numeric) as road_tax,
                (COALESCE(firefighting_tax,0)::numeric) as firefighting_tax,
                (COALESCE(education_tax,0)::numeric) as education_tax,
                (COALESCE(water_tax,0)::numeric) as water_tax,
                (COALESCE(cleanliness_tax,0)::numeric) as cleanliness_tax,
                (COALESCE(sewarage_tax,0)::numeric) as sewarage_tax,
                (COALESCE(tree_tax,0)::numeric) as tree_tax,
                (COALESCE(professional_tax,0)::numeric) as professional_tax,
                (COALESCE(adjust_amt,0)::numeric) as adjust_amt,
                (COALESCE(tax1,0)::numeric) as tax1,
                (COALESCE(tax2,0)::numeric) as tax2,
                (COALESCE(tax3,0)::numeric) as tax3,
                (COALESCE(sp_education_tax,0)::numeric) as sp_education_tax,
                (COALESCE(water_benefit,0)::numeric) as water_benefit,
                (COALESCE(water_bill,0)::numeric) as water_bill,
                (COALESCE(sp_water_cess,0)::numeric) as sp_water_cess,
                (COALESCE(drain_cess,0)::numeric) as drain_cess,
                (COALESCE(light_cess,0)::numeric) as light_cess,
                (COALESCE(major_building,0)::numeric) as major_building,
                (COALESCE(open_ploat_tax,0)::numeric) as open_ploat_tax,
            
                (COALESCE(c1urrent_total_demand,0)::numeric) as c1urrent_total_demand,
                (COALESCE(c1urrent_total_tax,0)::numeric) as c1urrent_total_tax,
                (
                    +(COALESCE(current_maintanance_amt,0)::numeric) 
                    +(COALESCE(current_aging_amt,0)::numeric) 
                    +(COALESCE(current_general_tax,0)::numeric) 
                    +(COALESCE(current_road_tax,0)::numeric) 
                    +(COALESCE(current_firefighting_tax,0)::numeric)
                    +(COALESCE(current_education_tax,0)::numeric)
                    +(COALESCE(current_water_tax,0)::numeric)
                    +(COALESCE(current_cleanliness_tax,0)::numeric)
                    +(COALESCE(current_sewarage_tax,0)::numeric)
                    +(COALESCE(current_tree_tax,0)::numeric)
                    +(COALESCE(current_professional_tax,0)::numeric)
                    +(COALESCE(current_adjust_amt,0)::numeric)
                    +(COALESCE(current_tax1,0)::numeric)
                    +(COALESCE(current_tax2,0)::numeric) 
                    +(COALESCE(current_tax3,0)::numeric)
                    +(COALESCE(current_sp_education_tax,0)::numeric) 
                    +(COALESCE(current_water_benefit,0)::numeric)
                    +(COALESCE(current_water_bill,0)::numeric)
                    +(COALESCE(current_sp_water_cess,0)::numeric)
                    +(COALESCE(current_drain_cess,0)::numeric)
                    +(COALESCE(current_light_cess,0)::numeric) 
                    +(COALESCE(current_major_building,0)::numeric) 
                    +(COALESCE(current_open_ploat_tax,0)::numeric)
                )as c1urrent_total,
                (COALESCE(current_maintanance_amt,0)::numeric ) as current_maintanance_amt,
                (COALESCE(current_aging_amt,0)::numeric ) as current_aging_amt,
                (COALESCE(current_general_tax,0)::numeric ) as current_general_tax,
                (COALESCE(current_road_tax,0)::numeric ) as current_road_tax,
                (COALESCE(current_firefighting_tax,0)::numeric ) as current_firefighting_tax,
                (COALESCE(current_education_tax,0)::numeric ) as current_education_tax,
                (COALESCE(current_water_tax,0)::numeric ) as current_water_tax,
                (COALESCE(current_cleanliness_tax,0)::numeric ) as current_cleanliness_tax,
                (COALESCE(current_sewarage_tax,0)::numeric ) as current_sewarage_tax,
                (COALESCE(current_tree_tax,0)::numeric ) as current_tree_tax,
                (COALESCE(current_professional_tax,0)::numeric ) as current_professional_tax,
                (COALESCE(current_adjust_amt,0)::numeric ) as current_adjust_amt,
                (COALESCE(current_tax1,0)::numeric ) as current_tax1,
                (COALESCE(current_tax2,0)::numeric ) as current_tax2,
                (COALESCE(current_tax3,0)::numeric ) as current_tax3,
                (COALESCE(current_sp_education_tax,0)::numeric ) as current_sp_education_tax,
                (COALESCE(current_water_benefit,0)::numeric ) as current_water_benefit,
                (COALESCE(current_water_bill,0)::numeric ) as current_water_bill,
                (COALESCE(current_sp_water_cess,0)::numeric ) as current_sp_water_cess,
                (COALESCE(current_drain_cess,0)::numeric ) as current_drain_cess,
                (COALESCE(current_light_cess,0)::numeric ) as current_light_cess,
                (COALESCE(current_major_building,0)::numeric ) as current_major_building,
                (COALESCE(current_open_ploat_tax,0)::numeric ) as current_open_ploat_tax,
            
                (COALESCE(a1rear_total_demand,0)::numeric) as a1rear_total_demand,
                (COALESCE(a1rear_total_tax,0)::numeric) as a1rear_total_tax,
                (
                    +(COALESCE(arear_maintanance_amt,0)::numeric) 
                    +(COALESCE(arear_aging_amt,0)::numeric) 
                    +(COALESCE(arear_general_tax,0)::numeric) 
                    +(COALESCE(arear_road_tax,0)::numeric) 
                    +(COALESCE(arear_firefighting_tax,0)::numeric)
                    +(COALESCE(arear_education_tax,0)::numeric)
                    +(COALESCE(arear_water_tax,0)::numeric)
                    +(COALESCE(arear_cleanliness_tax,0)::numeric)
                    +(COALESCE(arear_sewarage_tax,0)::numeric)
                    +(COALESCE(arear_tree_tax,0)::numeric)
                    +(COALESCE(arear_professional_tax,0)::numeric)
                    +(COALESCE(arear_adjust_amt,0)::numeric)
                    +(COALESCE(arear_tax1,0)::numeric)
                    +(COALESCE(arear_tax2,0)::numeric) 
                    +(COALESCE(arear_tax3,0)::numeric)
                    +(COALESCE(arear_sp_education_tax,0)::numeric) 
                    +(COALESCE(arear_water_benefit,0)::numeric)
                    +(COALESCE(arear_water_bill,0)::numeric)
                    +(COALESCE(arear_sp_water_cess,0)::numeric)
                    +(COALESCE(arear_drain_cess,0)::numeric)
                    +(COALESCE(arear_light_cess,0)::numeric) 
                    +(COALESCE(arear_major_building,0)::numeric) 
                    +(COALESCE(arear_open_ploat_tax,0)::numeric)
                )as a1rear_total,
                (COALESCE(arear_maintanance_amt,0)::numeric ) as arear_maintanance_amt,
                (COALESCE(arear_aging_amt,0)::numeric ) as arear_aging_amt,
                (COALESCE(arear_general_tax,0)::numeric ) as arear_general_tax,
                (COALESCE(arear_road_tax,0)::numeric ) as arear_road_tax,
                (COALESCE(arear_firefighting_tax,0)::numeric ) as arear_firefighting_tax,
                (COALESCE(arear_education_tax,0)::numeric ) as arear_education_tax,
                (COALESCE(arear_water_tax,0)::numeric ) as arear_water_tax,
                (COALESCE(arear_cleanliness_tax,0)::numeric ) as arear_cleanliness_tax,
                (COALESCE(arear_sewarage_tax,0)::numeric ) as arear_sewarage_tax,
                (COALESCE(arear_tree_tax,0)::numeric ) as arear_tree_tax,
                (COALESCE(arear_professional_tax,0)::numeric ) as arear_professional_tax,
                (COALESCE(arear_adjust_amt,0)::numeric ) as arear_adjust_amt,
                (COALESCE(arear_tax1,0)::numeric ) as arear_tax1,
                (COALESCE(arear_tax2,0)::numeric ) as arear_tax2,
                (COALESCE(arear_tax3,0)::numeric ) as arear_tax3,
                (COALESCE(arear_sp_education_tax,0)::numeric ) as arear_sp_education_tax,
                (COALESCE(arear_water_benefit,0)::numeric ) as arear_water_benefit,
                (COALESCE(arear_water_bill,0)::numeric ) as arear_water_bill,
                (COALESCE(arear_sp_water_cess,0)::numeric ) as arear_sp_water_cess,
                (COALESCE(arear_drain_cess,0)::numeric ) as arear_drain_cess,
                (COALESCE(arear_light_cess,0)::numeric ) as arear_light_cess,
                (COALESCE(arear_major_building,0)::numeric ) as arear_major_building,
                (COALESCE(arear_open_ploat_tax,0)::numeric ) as arear_open_ploat_tax,
                (COALESCE(rebate,0)::numeric) as rebate,
                (COALESCE(penalty,0)::numeric) as penalty,
				(COALESCE(advance_amount,0)::numeric) as advance_amount,
                (COALESCE(adjusted_amount,0)::numeric) as adjusted_amount
            from prop_transactions
            join prop_properties on prop_properties.id = prop_transactions.property_id 
            join (
                select distinct(prop_transactions.id)as tran_id ,
                    sum(COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric) as total_demand,					
                    sum(COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric) as total_tax,
                    sum(COALESCE(prop_tran_dtls.paid_maintanance_amt,0)::numeric) as maintanance_amt,
                    sum(COALESCE(prop_tran_dtls.paid_aging_amt,0)::numeric) as aging_amt,
                    sum(COALESCE(prop_tran_dtls.paid_general_tax,0)::numeric) as general_tax,
                    sum(COALESCE(prop_tran_dtls.paid_road_tax,0)::numeric) as road_tax,
                    sum(COALESCE(prop_tran_dtls.paid_firefighting_tax,0)::numeric) as firefighting_tax,
                    sum(COALESCE(prop_tran_dtls.paid_education_tax,0)::numeric) as education_tax,
                    sum(COALESCE(prop_tran_dtls.paid_water_tax,0)::numeric) as water_tax,
                    sum(COALESCE(prop_tran_dtls.paid_cleanliness_tax,0)::numeric) as cleanliness_tax,
                    sum(COALESCE(prop_tran_dtls.paid_sewarage_tax,0)::numeric) as sewarage_tax,
                    sum(COALESCE(prop_tran_dtls.paid_tree_tax,0)::numeric) as tree_tax,
                    sum(COALESCE(prop_tran_dtls.paid_professional_tax,0)::numeric) as professional_tax,
                    sum(COALESCE(prop_tran_dtls.paid_adjust_amt,0)::numeric) as adjust_amt,
                    sum(COALESCE(prop_tran_dtls.paid_tax1,0)::numeric) as tax1,
                    sum(COALESCE(prop_tran_dtls.paid_tax2,0)::numeric) as tax2,
                    sum(COALESCE(prop_tran_dtls.paid_tax3,0)::numeric) as tax3,
                    sum(COALESCE(prop_tran_dtls.paid_sp_education_tax,0)::numeric) as sp_education_tax,
                    sum(COALESCE(prop_tran_dtls.paid_water_benefit,0)::numeric) as water_benefit,
                    sum(COALESCE(prop_tran_dtls.paid_water_bill,0)::numeric) as water_bill,
                    sum(COALESCE(prop_tran_dtls.paid_sp_water_cess,0)::numeric) as sp_water_cess,
                    sum(COALESCE(prop_tran_dtls.paid_drain_cess,0)::numeric) as drain_cess,
                    sum(COALESCE(prop_tran_dtls.paid_light_cess,0)::numeric) as light_cess,
                    sum(COALESCE(prop_tran_dtls.paid_major_building,0)::numeric) as major_building,
                    sum(COALESCE(prop_tran_dtls.paid_open_ploat_tax,0)::numeric) as open_ploat_tax,
                
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as c1urrent_total_demand,
		            sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as c1urrent_total_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_maintanance_amt,0)::numeric else 0 end) as current_maintanance_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_aging_amt,0)::numeric else 0 end) as current_aging_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_general_tax,0)::numeric else 0 end) as current_general_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_road_tax,0)::numeric else 0 end) as current_road_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_firefighting_tax,0)::numeric else 0 end) as current_firefighting_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_education_tax,0)::numeric else 0 end) as current_education_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_water_tax,0)::numeric else 0 end) as current_water_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_cleanliness_tax,0)::numeric else 0 end) as current_cleanliness_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_sewarage_tax,0)::numeric else 0 end) as current_sewarage_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tree_tax,0)::numeric else 0 end) as current_tree_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_professional_tax,0)::numeric else 0 end) as current_professional_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_adjust_amt,0)::numeric else 0 end) as current_adjust_amt,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tax1,0)::numeric else 0 end) as current_tax1,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tax2,0)::numeric else 0 end) as current_tax2,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_tax3,0)::numeric else 0 end) as current_tax3,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_sp_education_tax,0)::numeric else 0 end) as current_sp_education_tax,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_water_benefit,0)::numeric else 0 end) as current_water_benefit,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_water_bill,0)::numeric else 0 end) as current_water_bill,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_sp_water_cess,0)::numeric else 0 end) as current_sp_water_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_drain_cess,0)::numeric else 0 end) as current_drain_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_light_cess,0)::numeric else 0 end) as current_light_cess,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_major_building,0)::numeric else 0 end) as current_major_building,
                    sum(case when fyear between '$fromFyear' and '$uptoFyear' then COALESCE(prop_tran_dtls.paid_open_ploat_tax,0)::numeric else 0 end) as current_open_ploat_tax,
                
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as a1rear_total_demand,
		            sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_total_tax,0)::numeric else 0 end) as a1rear_total_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_maintanance_amt,0)::numeric else 0 end) as arear_maintanance_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_aging_amt,0)::numeric else 0 end) as arear_aging_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_general_tax,0)::numeric else 0 end) as arear_general_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_road_tax,0)::numeric else 0 end) as arear_road_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_firefighting_tax,0)::numeric else 0 end) as arear_firefighting_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_education_tax,0)::numeric else 0 end) as arear_education_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_water_tax,0)::numeric else 0 end) as arear_water_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_cleanliness_tax,0)::numeric else 0 end) as arear_cleanliness_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_sewarage_tax,0)::numeric else 0 end) as arear_sewarage_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tree_tax,0)::numeric else 0 end) as arear_tree_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_professional_tax,0)::numeric else 0 end) as arear_professional_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_adjust_amt,0)::numeric else 0 end) as arear_adjust_amt,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tax1,0)::numeric else 0 end) as arear_tax1,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tax2,0)::numeric else 0 end) as arear_tax2,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_tax3,0)::numeric else 0 end) as arear_tax3,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_sp_education_tax,0)::numeric else 0 end) as arear_sp_education_tax,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_water_benefit,0)::numeric else 0 end) as arear_water_benefit,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_water_bill,0)::numeric else 0 end) as arear_water_bill,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_sp_water_cess,0)::numeric else 0 end) as arear_sp_water_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_drain_cess,0)::numeric else 0 end) as arear_drain_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_light_cess,0)::numeric else 0 end) as arear_light_cess,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_major_building,0)::numeric else 0 end) as arear_major_building,
                    sum(case when fyear < '$fromFyear' then COALESCE(prop_tran_dtls.paid_open_ploat_tax,0)::numeric else 0 end) as arear_open_ploat_tax
                
                from prop_tran_dtls                
                join prop_transactions on prop_transactions.id = prop_tran_dtls.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                join prop_demands on prop_demands.id = prop_tran_dtls.prop_demand_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_demands.status =1 
                    and prop_tran_dtls.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
                    
            )prop_tran_dtls on prop_tran_dtls.tran_id = prop_transactions.id
            left join(
                select distinct(prop_transactions.id)as tran_id ,
                sum(case when prop_penaltyrebates.is_rebate =true then COALESCE(round(prop_penaltyrebates.amount),0) else 0 end) as rebate,
                    sum(case when prop_penaltyrebates.is_rebate !=true then COALESCE(round(prop_penaltyrebates.amount),0) else 0 end) as penalty
                from prop_penaltyrebates
                join prop_transactions on prop_transactions.id = prop_penaltyrebates.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_penaltyrebates.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
            )fine_rebet on fine_rebet.tran_id = prop_transactions.id
            left join(
                select distinct(prop_transactions.id)as tran_id ,
                    sum(prop_advances.amount) as advance_amount
				from prop_advances
                join prop_transactions on prop_transactions.id = prop_advances.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate'  
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_advances.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "   
                group by prop_transactions.id
            )advance on advance.tran_id = prop_transactions.id			
			left join(
                select distinct(prop_transactions.id)as tran_id ,
                    sum(prop_adjustments.amount) as adjusted_amount
				from prop_adjustments
                join prop_transactions on prop_transactions.id = prop_adjustments.tran_id
                join (
                    select prop_properties.id as pid,prop_properties.ward_mstr_id,prop_properties.zone_mstr_id
                    from prop_properties
                    join prop_transactions on prop_transactions.property_id = prop_properties.id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                    group BY prop_properties.id
                )props on props.pid = prop_transactions.property_id
                where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_adjustments.status =1 
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                    " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                    " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . " 
                group by prop_transactions.id
            )adjusted on adjusted.tran_id = prop_transactions.id
            left join prop_cheque_dtls on prop_cheque_dtls.transaction_id = prop_transactions.id
            left join(
                select string_agg(case when trim(owner_name_marathi) is null then owner_name else owner_name_marathi end,',')owner_name,
                    string_agg(case when trim(guardian_name_marathi) is null then guardian_name else guardian_name_marathi end,',')guardian_name,
                    string_agg(mobile_no	,',')mobile_no,
                    string_agg(owner_name_marathi,',')owner_name_marathi,
                    string_agg(guardian_name_marathi,',')guardian_name_marathi,
                    prop_transactions.id
                from prop_owners
                join prop_transactions on prop_transactions.property_id = prop_owners.property_id
                where  prop_transactions.tran_date between '$fromDate' and '$toDate' 
                    and prop_transactions.status in(1,2)
                    and prop_owners.status =1
                    " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "                    
                    " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                group by prop_transactions.id
            
            )owners on owners.id = prop_transactions.id
            left join users on users.id = prop_transactions.user_id
            left join ulb_ward_masters on ulb_ward_masters.id = prop_properties.ward_mstr_id	
            left join zone_masters on zone_masters.id = prop_properties.zone_mstr_id
            where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                and prop_transactions.status in(1,2)
                " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
            ";

            $report = $this->_DB_READ->select($query);
            $report = collect($report);

            $data["data"] = $report->map(function ($val) {
                $val->generalTaxException = 0;
                $val->payableAfterDeduction = $val->c1urrent_total_tax;
                $val->advanceAmt = $val->advance_amount;
                $val->adjustAmt = $val->adjusted_amount;
                $val->noticeFee = 0;
                $val->FinalTax = $val->c1urrent_total_tax;
                $val->receiptNo = isset($val->book_no) ? explode('-', $val->book_no)[1] : "";
                $val->receiptNo = isset($val->book_no) ? explode('-', $val->book_no)[1] : "";
                $json[] = [
                    "fyear"               => $val->from_fyear,
                    "total"               => ($val->a1rear_total_tax + $val->penalty - $val->adjusted_amount),
                    "generalTaxException" =>  $val->generalTaxException,
                    "noticeFee"           => $val->noticeFee,
                    "maintanance_amt"     =>  $val->arear_maintanance_amt,
                    "aging_amt"           =>  $val->arear_aging_amt,
                    "general_tax"         =>  $val->arear_general_tax,
                    "road_tax"            =>  $val->arear_road_tax,
                    "firefighting_tax"    =>  $val->arear_firefighting_tax,
                    "education_tax"       =>  $val->arear_education_tax,
                    "water_tax"           =>  $val->arear_water_tax,
                    "cleanliness_tax"     =>  $val->arear_cleanliness_tax,
                    "sewarage_tax"        =>  $val->arear_sewarage_tax,
                    "tree_tax"            =>  $val->arear_tree_tax,
                    "professional_tax"    =>  $val->arear_professional_tax,
                    "adjust_amt"          =>  $val->arear_adjust_amt,
                    "tax1"                =>  $val->arear_tax1,
                    "tax2"                =>  $val->arear_tax2,
                    "tax3"                =>  $val->arear_tax3,
                    "penalty"             =>  $val->penalty,
                    "rebate"              =>  0,
                    "advance"             =>  0,
                    "adjustment"          => $val->adjusted_amount,
                ];
                $json[] = [
                    "fyear"               => $val->to_fyear,
                    "total"               => ($val->c1urrent_total - $val->rebate + $val->advance_amount), //$val->c1urrent_total,
                    "generalTaxException" =>  $val->generalTaxException,
                    "noticeFee"           => $val->noticeFee,
                    "maintanance_amt"     => $val->current_maintanance_amt,
                    "aging_amt"           => $val->current_aging_amt,
                    "general_tax"         => $val->current_general_tax,
                    "road_tax"            => $val->current_road_tax,
                    "firefighting_tax"    => $val->current_firefighting_tax,
                    "education_tax"       => $val->current_education_tax,
                    "water_tax"           => $val->current_water_tax,
                    "cleanliness_tax"     => $val->current_cleanliness_tax,
                    "sewarage_tax"        => $val->current_sewarage_tax,
                    "tree_tax"            => $val->current_tree_tax,
                    "professional_tax"    => $val->current_professional_tax,
                    "adjust_amt"          => $val->current_adjust_amt,
                    "tax1"                => $val->current_tax1,
                    "tax2"                => $val->current_tax2,
                    "tax3"                => $val->current_tax3,
                    "penalty"             => 0,
                    "rebate"              => $val->rebate,
                    "advance"             => $val->advance_amount,
                    "adjustment"          => 0,
                ];
                $val->json = ($json);
                return $val;
            });
            $data["headers"] = [
                "fromDate" => Carbon::parse($fromDate)->format('d-m-Y'),
                "uptoDate" => Carbon::parse($toDate)->format('d-m-Y'),
                "fromFyear" => $fromFyear,
                "uptoFyear" => $uptoFyear,
                "tcName" => $userId ? User::find($userId)->name ?? "" : "All",
                "WardName" => $wardId ? ulbWardMaster::find($wardId)->ward_name ?? "" : "All",
                "zoneName" => $zoneId ? (new ZoneMaster)->createZoneName($zoneId) ?? "" : "East/West/North/South",
                "paymentMode" => $paymentMode ? str::replace(",", "/", $paymentMode) : "All",
                "printDate" => Carbon::now()->format('d-m-Y H:i:s A'),
                "printedBy" => $user->name ?? "",
            ];
            return responseMsgs(true, "Admin Dashboard Reports", remove_null($data));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function tranDeactivatedList(Request $request)
    {
        try {
            if($request->tranType=="Water" || $request->selectedModuleOption=="Water")
            {
                return ((new ConcreteReport())->tranDeactivatedList($request));
            }

            if($request->tranType=="Trade" || $request->selectedModuleOption=="Trade")
            {
                
            }
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
            $data = PropTransaction::readConnection()->select(DB::raw("
                        prop_transactions.id,prop_transactions.tran_no,
                        prop_transactions.tran_type,
                        prop_transactions.tran_date,
                        prop_transactions.payment_mode,
                        app.app_no,
                        ulb_ward_masters.ward_name,
                        zone_masters.zone_name,
                        users.name as tran_by_user_name,
                        prop_transactions.amount,
                        prop_cheque_dtls.cheque_date,
                        prop_cheque_dtls.bank_name,
                        prop_cheque_dtls.branch_name,
                        prop_cheque_dtls.cheque_no,
                        prop_transaction_deactivate_dtls.reason,
                        prop_transaction_deactivate_dtls.file_path,
                        prop_transaction_deactivate_dtls.deactive_date,
                        users2.name as tran_deactivated_by
                    "))
                ->join(DB::raw("
                        (
                            (
                                select saf.id as app_id ,saf.saf_no as app_no,prop_transactions.id as tran_id,
                                    ward_mstr_id,zone_mstr_id
                                from prop_active_safs saf
                                join prop_transactions on prop_transactions.saf_id = saf.id
                                where prop_transactions.tran_date between '$fromDate' and '$toDate'
                            )
                            union ALL(
                                select saf.id as app_id ,saf.saf_no as app_no,prop_transactions.id as tran_id,
                                    ward_mstr_id,zone_mstr_id
                                from prop_safs saf
                                join prop_transactions on prop_transactions.saf_id = saf.id
                                where prop_transactions.tran_date between  '$fromDate' and '$toDate'
                            )
                            union ALL(
                                select saf.id as app_id ,saf.saf_no as app_no,prop_transactions.id as tran_id,
                                    ward_mstr_id,zone_mstr_id
                                from prop_rejected_safs saf
                                join prop_transactions on prop_transactions.saf_id = saf.id
                                where prop_transactions.tran_date between '$fromDate' and '$toDate'
                            )
                            union ALL(
                                select saf.id as app_id ,saf.holding_no as app_no,prop_transactions.id as tran_id,
                                    ward_mstr_id,zone_mstr_id
                                from prop_properties saf
                                join prop_transactions on prop_transactions.property_id = saf.id
                                where prop_transactions.tran_date between '$fromDate' and '$toDate'
                            )
                        ) app
                    "), "app.tran_id", "prop_transactions.id")
                ->leftjoin("prop_cheque_dtls", "prop_cheque_dtls.transaction_id", "prop_transactions.id")
                ->leftjoin("prop_transaction_deactivate_dtls", "prop_transaction_deactivate_dtls.tran_id", "prop_transactions.id")
                ->leftjoin("users", "users.id", "prop_transactions.user_id")
                ->leftjoin("users AS users2", "users2.id", "prop_transaction_deactivate_dtls.deactivated_by")
                ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "app.ward_mstr_id")
                ->leftjoin("zone_masters", "zone_masters.id", "app.zone_mstr_id")
                ->where("prop_transactions.status", 0)
                ->whereBetween("prop_transactions.tran_date", [$fromDate, $toDate]);
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("zone_masters.id", $zoneId);
            }
            if ($userId) {
                $data = $data->where("prop_transactions.user_id", $userId);
            }
            if ($paymentMode) {

                $data = $data->whereIN(DB::raw("UPPER(prop_transactions.payment_mode)"), explode(",", $paymentMode));
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
