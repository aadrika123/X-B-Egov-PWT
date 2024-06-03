<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\TradeLicence;
use App\Models\UlbWardMaster;
use App\Repository\Common\CommonFunction;
use App\Repository\Trade\IReport;
use App\Traits\Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use LDAP\Result;

class ReportController extends Controller
{
    use Auth;

    private $Repository;
    private $_common;

    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;
    public function __construct(IReport $TradeRepository)
    {
        DB::enableQueryLog();
        $this->Repository = $TradeRepository;
        $this->_common = new CommonFunction();

        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];
    }

    public function CollectionReports(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "key"    => "nullable|regex:/^[^<>{};:.,~!?@#$%^=&*\"]*$/i",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr1.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->CollectionReports($request);
    }

    public function teamSummary(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr2.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->teamSummary($request);
    }

    public function valideAndExpired(Request $request)
    {
        $request->validate(
            [
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "licenseNo" => "nullable|regex:/^[^<>{};:.,~!?@#$%^=&*\"]*$/i",
                "licenseStatus" => "nullable|in:EXPIRED,VALID,TO BE EXPIRED",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr3.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->valideAndExpired($request);
    }
    public function CollectionSummary(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr5.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->CollectionSummary($request);
    }
    public function tradeDaseboard(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr6.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->tradeDaseboard($request);
    }

    public function applicationTypeCollection(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr6.2", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->applicationTypeCollection($request);
    }

    public function userAppliedApplication(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr6.3", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->userAppliedApplication($request);
    }

    public function collectionPerfomance(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr6.4", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->collectionPerfomance($request);
    }

    public function ApplicantionTrackStatus(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr7.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->ApplicantionTrackStatus($request);
    }
    public function applicationAgentNotice(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr8.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->applicationAgentNotice($request);
    }
    public function noticeSummary(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                // "page" => "nullable|digits_between:1,9223372036854775807",
                // "perPage"=>"nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr9.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->noticeSummary($request);
    }

    public function levelwisependingform(Request $request)
    {
        $request->request->add(["metaData" => ["tr10.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->levelwisependingform($request);
    }

    public function levelUserPending(Request $request)
    {
        $request->validate(
            [
                "roleId" => "required|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr10.2", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->levelUserPending($request);
    }
    public function userWiseWardWiseLevelPending(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "required|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr10.2.1.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->userWiseWardWiseLevelPending($request);
    }

    public function levelformdetail(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "roleId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr10.2.2.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->levelformdetail($request);
    }
    public function userWiseLevelPending(Request $request)
    {
        $request->validate(
            [
                "userId" => "required|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr10.2.2.2", 1.1, null, $request->getMethod(), null,]]);

        $refUser        = Auth()->user();
        $refUserId      = $refUser->id;
        $ulbId          = $refUser->ulb_id;
        if ($request->ulbId) {
            $ulbId = $request->ulbId;
        }

        $respons =  $this->levelformdetail($request);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;

        $roles = ($this->_common->getUserRoll($request->userId, $ulbId, $this->_WF_MASTER_Id));
        $respons = json_decode(json_encode($respons), true);
        if ($respons["original"]["status"]) {
            $respons["original"]["data"]["data"] = collect($respons["original"]["data"]["data"])->map(function ($val) use ($roles) {
                $val["role_name"] = $roles->role_name ?? "";
                $val["role_id"] = $roles->role_id ?? 0;
                return $val;
            });
        }
        return responseMsgs($respons["original"]["status"], $respons["original"]["message"], $respons["original"]["data"], $apiId, $version, $queryRunTime, $action, $deviceId);
    }

    public function bulkPaymentRecipt(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->request->add(["metaData" => ["tr11.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->bulkPaymentRecipt($request);
    }

    public function applicationStatus(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "areaInsqrFt" => "nullable|numeric",
                "status" => "nullable|int|in:1,2,3,4,5,6",
                "key"   => "nullable|string"
            ]
        );
        $request->request->add(["metaData" => ["tr12.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->applicationStatus($request);
    }

    public function todaysApplication(Request $request)
    {
        try {
            $todayDate = Carbon::now();
            $mTradeLicence =  new TradeLicence();
            $data = $mTradeLicence
                ->select('trade_licences.id', 'application_type', 'paid_amount')
                ->join('trade_param_application_types as a', 'a.id', 'trade_licences.application_type_id')
                ->join('trade_transactions', 'trade_transactions.temp_id', 'trade_licences.id')
                ->where('application_date', $todayDate)
                ->where('trade_licences.is_active', true)
                ->where('trade_transactions.status', 1)
                ->get();

            $result = collect($data)->groupBy('application_type')->map(function ($group) {
                return count($group);
            });
            $paidAmount = collect($data)->sum('paid_amount');
            $response['new_licence'] = $result['NEW LICENSE'] ?? 0;
            $response['renewal']     = $result['RENEWAL'] ?? 0;
            $response['amendment']   = $result['AMENDMENT'] ?? 0;
            $response['surrender']   = $result['SURRENDER'] ?? 0;
            $response['paid_amount'] = $paidAmount;
            $response['date']        = ($todayDate)->format('d-m-Y');

            return responseMsgs(true, "Data Retreived", $response, "", "", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function WardList(Request $request)
    {
        $request->request->add(["metaData" => ["tr13.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            if ($request->ulbId) {
                $ulbId  =   $request->ulbId;
            }
            $wardList = UlbWardMaster::select(DB::raw("min(id) as id ,ward_name as ward_no"))
                ->WHERE("ulb_id", $ulbId)
                ->GROUPBY("ward_name")
                ->ORDERBY("ward_name")
                ->GET();

            return responseMsgs(true, "", $wardList, $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }

    public function TcList(Request $request)
    {
        $request->request->add(["metaData" => ["tr14.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $ulbId          = $refUser->ulb_id;
            if ($request->ulbId) {
                $ulbId  =   $request->ulbId;
            }
            $rolse = $this->_common->getAllRoles($refUserId, $ulbId, $this->_WF_MASTER_Id, 0, true);
            $rolseIds = collect($rolse)->implode("id", ",");
            if (!$rolseIds) {
                throw new Exception("No Anny Role Found In This Ulb");
            }
            $tcList = DB::table("users")
                ->select(DB::raw("users.id,users.user_name, users.name, wf_roles.role_name"))
                ->JOIN("wf_roleusermaps", "wf_roleusermaps.user_id", "=", "users.id")
                ->JOIN("wf_roles", "wf_roles.id", "wf_roleusermaps.wf_role_id")
                ->WHERE("wf_roleusermaps.is_suspended", FALSE)
                ->WHERE("wf_roles.is_suspended", FALSE)
                ->WHEREIN("wf_roles.id", explode(",", $rolseIds))
                ->GET();
            return responseMsgs(true, "", remove_null($tcList), $apiId, $version, $queryRunTime, $action, $deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $apiId, $version, $queryRunTime, $action, $deviceId);
        }
    }
}
