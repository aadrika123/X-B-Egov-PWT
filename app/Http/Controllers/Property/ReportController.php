<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\Reports\CollectionReport;
use App\Http\Requests\Property\Reports\Levelformdetail;
use App\Http\Requests\Property\Reports\LevelUserPending;
use App\Http\Requests\Property\Reports\SafPropIndividualDemandAndCollection;
use App\Http\Requests\Property\Reports\UserWiseLevelPending;
use App\Http\Requests\Property\Reports\UserWiseWardWireLevelPending;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\MplYearlyReport;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropDemand;
use App\Models\Property\PropProperty;
use App\Models\Property\PropPropertyUpdateRequest;
use App\Models\Property\PropSaf;
use App\Models\Property\PropTransaction;
use App\Models\Property\ZoneMaster;
use App\Models\Trade\TradeTransaction;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Models\User;
use App\Models\Water\WaterTran;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\IReport;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Traits\Auth;
use App\Traits\Property\Report;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Expr\FuncCall;
use Illuminate\Support\Str;

#------------date 13/03/2023 -------------------------------------------------------------------------
#   Code By Sandeep Bara
#   Payment Mode Wise Collection Report

class ReportController extends Controller
{
    use Auth;
    use Report;

    private $Repository;
    private $_common;
    protected $_DB;
    protected $_DB_READ;
    protected $_DB_NAME;
    public function __construct(IReport $TradeRepository)
    {
        $this->_DB_NAME = (new PropProperty())->getConnectionName();
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_DB_READ = DB::connection($this->_DB_NAME . "::read");
        DB::enableQueryLog();
        $this->Repository = $TradeRepository;
        $this->_common = new CommonFunction();
    }

    public function collectionReport(CollectionReport $request)
    {
        $request->merge(["metaData" => ["pr1.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->collectionReport($request);
    }

    public function safCollection(CollectionReport $request)
    {
        $request->merge(["metaData" => ["pr2.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->safCollection($request);
    }

    public function safPropIndividualDemandAndCollection(SafPropIndividualDemandAndCollection $request)
    {
        $request->merge(["metaData" => ["pr3.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->safPropIndividualDemandAndCollection($request);
    }

    public function levelwisependingform(Request $request)
    {
        $request->merge(["metaData" => ["pr4.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->levelwisependingform($request);
    }

    public function levelformdetail(Levelformdetail $request)
    {
        $request->merge(["metaData" => ["pr4.2", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->levelformdetail($request);
    }

    public function levelUserPending(LevelUserPending $request)
    {

        $request->merge(["metaData" => ["pr4.2.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->levelUserPending($request);
    }

    public function userWiseLevelPending(UserWiseLevelPending $request)
    {
        $request->merge(["metaData" => ["pr4.2.2", 1.1, null, $request->getMethod(), null,]]);

        $refUser        = authUser($request);
        $refUserId      = $refUser->id;
        $ulbId          = $refUser->ulb_id;
        $safWorkFlow = Config::get('workflow-constants.SAF_WORKFLOW_ID');
        if ($request->ulbId) {
            $ulbId = $request->ulbId;
        }

        $respons =  $this->levelformdetail($request);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;

        $roles = ($this->_common->getUserRoll($request->userId, $ulbId, $safWorkFlow));
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

    public function userWiseWardWireLevelPending(UserWiseWardWireLevelPending $request)
    {
        $request->merge(["metaData" => ["pr4.2.1.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->userWiseWardWireLevelPending($request);
    }

    public function safSamFamGeotagging(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr5.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->safSamFamGeotagging($request);
    }

    public function PropPaymentModeWiseSummery(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "userId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr1.2", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->PropPaymentModeWiseSummery($request);
    }

    public function SafPaymentModeWiseSummery(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "userId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr2.2", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->SafPaymentModeWiseSummery($request);
    }

    public function PropDCB(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr7.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->PropDCB($request);
    }

    public function PropWardWiseDCB(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                // "page" => "nullable|digits_between:1,9223372036854775807",
                // "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr8.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->PropWardWiseDCB($request);
    }

    public function PropFineRebate(Request $request)
    {
        $request->validate(
            [
                "fiYear" => "nullable|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr9.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->PropFineRebate($request);
    }

    public function PropDeactedList(Request $request)
    {
        $request->validate(
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr10.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->PropDeactedList($request);
    }


    //========================================================================================================
    // Modification By : Mrinal Kumar
    // Date : 11-03-2023

    /**
     * | Ward wise holding report
     */
    public function wardWiseHoldingReport(Request $request)
    {
        $mPropDemand = new PropDemand();
        $wardMstrId = $request->wardMstrId;
        $ulbId = authUser($request)->ulb_id;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $start = Carbon::createFromDate($request->year, 4, 1);

        $fromDate = $start->format('Y-m-d');
        if ($currentMonth > 3) {
            $end = Carbon::createFromDate($currentYear + 1, 3, 31);
            $toDate = $end->format('Y-m-d');
        } else
            $toDate = ($currentYear . '-03-31');

        $mreq = new Request([
            "fromDate" => $fromDate,
            "toDate" => $toDate,
            "ulbId" => $ulbId,
            "wardMstrId" => $wardMstrId,
            "perPage" => $request->perPage
        ]);

        $data = $mPropDemand->readConnection()->wardWiseHolding($mreq);

        $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
        return responseMsgs(true, "Ward Wise Holding Data!", $data, 'pr6.1', '1.1', $queryRunTime, 'Post', '');
    }

    /**
     * | List of financial year
     */
    public function listFY(Request $request)
    {
        $currentDate = Carbon::now();
        $currentYear = $currentDate->year;
        $financialYears = array();
        $currentYear = date('Y');
        if ($currentDate->month <= 3)
            $currentYear = $currentYear - 1;

        for ($year = 2015; $year <= $currentYear; $year++) {
            $startOfYear = $year . '-04-01'; // Financial year starts on April 1st
            $endOfYear = ($year + 1) . '-03-31'; // Financial year ends on March 31st
            $financialYear = getFinancialYear($startOfYear, 2015); // Calculate financial year and add a label
            $financialYears[] = $financialYear;
        }
        return responseMsgs(true, "Financial Year List", array_reverse($financialYears), 'pr11.1', '01', '382ms-547ms', 'Post', '');
    }

    /**
     * | Printing of bulk receipt
     */
    public function bulkReceipt(Request $req, iSafRepository $safRepo)
    {
        $req->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date',
            'tranType' => 'required|In:Property,Saf',
            'userId' => 'required|numeric',
        ]);
        try {
            $fromDate = $req->fromDate;
            $toDate = $req->toDate;
            $userId = $req->userId;
            $tranType = $req->tranType;
            $mpropTransaction = new PropTransaction();
            $holdingCotroller = new HoldingTaxController($safRepo);
            $activeSafController = new ActiveSafController($safRepo);
            $propReceipts = collect();
            $receipts = collect();

            $transaction = $mpropTransaction->readConnection()->tranDtl($userId, $fromDate, $toDate);

            if ($tranType == 'Property')
                $data = $transaction->whereNotNull('property_id')->get();

            if ($tranType == 'Saf')
                $data = $transaction->whereNotNull('saf_id')->get();

            // if ($data->isEmpty())
            //     throw new Exception('No Data Found');

            $tranNos = collect($data)->pluck('tran_no');

            foreach ($tranNos as $tranNo) {
                $mreq = new Request(
                    ["tranNo" => $tranNo]
                );
                if ($tranType == 'Property')
                    $data = $holdingCotroller->propPaymentReceipt($mreq);

                if ($tranType == 'Saf')
                    $data = $activeSafController->generatePaymentReceipt($mreq);

                $propReceipts->push($data);
            }

            foreach ($propReceipts as $propReceipt) {
                $receipt = $propReceipt->original['data'];
                $receipts->push($receipt);
            }

            $queryRunTime = (collect(DB::getQueryLog($data))->sum("time"));

            return responseMsgs(true, 'Bulk Receipt', remove_null($receipts), '010801', '01', $queryRunTime, 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | GbSafCollection
     */
    public function gbSafCollection(Request $req)
    {
        $req->validate(
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d",
            ]
        );
        try {
            $fromDate = $req->fromDate;
            $uptoDate = $req->uptoDate;
            $perPage = $req->perPage ?? 5;
            $tbl1 = 'prop_active_safs';
            $officerTbl1 = 'prop_active_safgbofficers';
            $tbl2 = 'prop_safs';
            $officerTbl2 = 'prop_gbofficers';

            $first_query =  $this->gbSafCollectionQuery($tbl1, $fromDate, $uptoDate, $officerTbl1);
            $gbsafCollection = $this->gbSafCollectionQuery($tbl2, $fromDate, $uptoDate, $officerTbl2)
                ->union($first_query);

            if ($req->wardId)
                $gbsafCollection = $gbsafCollection->where('ward_mstr_id', $req->wardId);

            if ($req->paymentMode)
                $gbsafCollection = $gbsafCollection->where('payment_mode', $req->paymentMode);

            $list = $gbsafCollection->paginate($perPage);
            return $list;
            // $page = $req->page && $req->page > 0 ? $req->page : 1;
            // $paginator = $gbsafCollection->paginate($perPage);
            // $items = $paginator->items();
            // $total = $paginator->total();
            // $numberOfPages = ceil($total / $perPage);
            // $list = [
            //     "perPage" => $perPage,
            //     "page" => $page,
            //     "items" => $items,
            //     "total" => $total,
            //     "numberOfPages" => $numberOfPages
            // ];

            return responseMsgs(true, "GB Saf Collection!", $list, 'pr12.1', '01', '623ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Ward wise Individual Property Demand
     */
    public function propIndividualDemandCollection(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardMstrId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr13.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->propIndividualDemandCollection($request);
    }


    /**
     * | GBSAF Ward wise Individual Demand
     */
    public function gbsafIndividualDemandCollection(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardMstrId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr14.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->gbsafIndividualDemandCollection($request);
    }

    /**
     * | Not paid from 2019-2017
     */
    public function notPaidFrom2016(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardMstrId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr15.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->notPaidFrom2016($request);
    }

    /**
     * | Not paid from 2019-2017
     */
    public function previousYearPaidButnotCurrentYear(Request $request)
    {
        $request->validate(
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardMstrId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        $request->merge(["metaData" => ["pr16.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->previousYearPaidButnotCurrentYear($request);
    }

    public function notPayedFrom(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fiYear" => "required|regex:/^\d{4}-\d{4}$/",
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardMstrId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId"    => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        $request->merge(["metaData" => ["pr17.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->notPayedFrom($request);
    }

    public function dueDemandPropList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardMstrId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId"    => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        $request->merge(["metaData" => ["pr18.1", 1.1, null, $request->getMethod(), null,]]);
        return $this->Repository->dueDemandPropList($request);
    }

    /**
     * | Dcb Pie Chart
     */
    public function dcbPieChart(Request $request)
    {
        return $this->Repository->dcbPieChart($request);
    }

    public function propSafCollectionTc(Request $request)
    {
        $request->merge(["userJoin" => "JOIN"]);
        return $this->propSafCollection($request);
    }

    /**
     * | 
     */
    public function propSafCollection(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'collectionType' => 'required|array',
                // 'collectionType.*' => 'required|in:property,saf,gbsaf'
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }

        $request->merge(["metaData" => ["pr17.1", 1.1, null, $request->getMethod(), null,]]);
        $propCollection = null;
        $safCollection = null;
        $gbsafCollection = null;
        $proptotalData = 0;
        $proptotal = 0;
        $saftotal = 0;
        $gbsaftotal = 0;
        $saftotalData = 0;
        $gbsaftotalData = 0;
        $collectionTypes = $request->collectionType;
        $perPage = $request->perPage ?? 5;

        if ($request->user == 'tc') {
            $userId = authUser($request)->id;
            $request->merge(["userId" => $userId, "userJoin" => "JOIN"]);
        }

        foreach ($collectionTypes as $collectionType) {
            if ($collectionType == 'property') {
                $propCollection =   $this->Repository->collectionReport($request); #$this->collectionReport($request);
                $proptotal = $propCollection->original['data']['totalAmount'];
                $proptotalData = $propCollection->original['data']['total'];
                $propCollection = $propCollection->original['data']['data'];
            }

            if ($collectionType == 'saf') {
                $safCollection = $this->Repository->safCollection($request); #$this->safCollection($request);
                $saftotal = $safCollection->original['data']['totalAmount'];
                $saftotalData = $safCollection->original['data']['total'];
                $safCollection = $safCollection->original['data']['data'];
            }

            if ($collectionType == 'gbsaf') {
                $gbsafCollection = $this->gbSafCollection($request);
                $gbsaftotalData = $gbsafCollection->toarray()['total'];
                $gbsafCollection = $gbsafCollection->toarray()['data'];
                $gbsaftotal = collect($gbsafCollection)->sum('amount');
            }
        }
        $currentPage = $request->page ?? 1;
        $details = collect($propCollection)->merge($safCollection)->merge($gbsafCollection);

        $a = round($proptotalData / $perPage);
        $b = round($saftotalData / $perPage);
        $c = round($gbsaftotalData / $perPage);
        $data['current_page'] = $currentPage;
        $data['total'] = $proptotalData + $saftotalData + $gbsaftotalData;
        $data['totalAmt'] = round($proptotal + $saftotal + $gbsaftotal);
        $data['last_page'] = max($a, $b, $c);
        $data['data'] = $details;

        return responseMsgs(true, "", $data, "", "", "", "post", $request->deviceId);
    }

    public function propSafCollectionUserWise(Request $request)
    {
        $request->merge(["user" => "tc"]);
        return $this->propSafCollection($request);
    }

    /**
     * | Holding Wise Rebate & Penalty
     */
    public function rebateNpenalty(Request $request)
    {
        return $this->Repository->rebateNpenalty($request);
    }


    /**
     * | Admin Dashboard Report
     */
    public function adminDashReport(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        return $this->Repository->adminDashReport($request);
    }

    /**
     * | Tc Collection Report
     */
    public function tcCollectionReport()
    {
        return $this->Repository->tcCollectionReport();
    }

    public function paymentModedealyCollectionRptV1(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId"  => "nullable|digits_between:1,9223",
                "userId" => "nullable|digits_between:1,9223",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        return $this->Repository->paymentModedealyCollectionRptV1($request);
    }

    public function individualDedealyCollectionRptV1(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId"  => "nullable|digits_between:1,9223",
                "userId" => "nullable|digits_between:1,9223",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        return $this->Repository->individualDedealyCollectionRptV1($request);
    }

    public function mplReport2(Request $request)
    {
        try {
            $ulbId = $request->ulbId ?? 2;
            $fyear = getFY();
            $fyArr = explode("-", $fyear);
            $privYear = ($fyArr[0] - 1) . "-" . ($fyArr[1] - 1);
            $prevYearData =  DB::connection('pgsql_reports')
                ->table('mpl_yearly_reports')
                ->where('ulb_id', $ulbId)
                ->where("fyear", $privYear)
                ->first();
            $currentYearData =  DB::connection('pgsql_reports')
                ->table('mpl_yearly_reports')
                ->where('ulb_id', $ulbId)
                ->where("fyear", $fyear)
                ->first();
            // dd($prevYearData->assessed_property_target_for_this_year??0);

            #_Assessed Properties ??
            $data['Assessed Properties']['target_for_last_year']    = $prevYearData->assessed_property_target_for_this_year ?? 0;
            $data['Assessed Properties']['last_year_achievement']   = $prevYearData->assessed_property_this_year_achievement ?? 0;
            $data['Assessed Properties']['target_for_this_year']    = $currentYearData->assessed_property_target_for_this_year ?? 0;
            $data['Assessed Properties']['this_year_achievement']   = $currentYearData->assessed_property_this_year_achievement ?? 0;

            #_Saf Achievement
            $data['Saf Achievement']['previous_year_target']        = $prevYearData->saf_current_year_target ?? 0;
            $data['Saf Achievement']['previous_year_achievement']   = $prevYearData->saf_current_year_achievement ?? 0;
            $data['Saf Achievement']['current_year_target']         = $currentYearData->saf_current_year_target ?? 0;
            $data['Saf Achievement']['current_year_achievement']    = $currentYearData->saf_current_year_achievement ?? 0;

            #_Assessment Categories ??
            $data['Assessment Categories']['total_assessment']  = $currentYearData->total_assessment ?? 0;
            $data['Assessment Categories']['residential']       = $currentYearData->total_assessed_residential ?? 0;
            $data['Assessment Categories']['commercial']        = $currentYearData->total_assessed_commercial ?? 0;
            $data['Assessment Categories']['industrial']        = $currentYearData->total_assessed_industrial ?? 0;
            $data['Assessment Categories']['gbsaf']             = $currentYearData->total_assessed_gbsaf ?? 0;

            #_Ownership ??
            $data['Ownership']['total_ownership'] = $currentYearData->total_property ?? 0;
            $data['Ownership']['owned_property']  = $currentYearData->owned_property ?? 0;
            $data['Ownership']['rented_property'] = $currentYearData->rented_property ?? 0;
            $data['Ownership']['vacant_property'] = $currentYearData->vacant_property ?? 0;

            #_Unpaid Properties
            $data['Unpaid Properties']['count_not_paid_3yrs']  = $prevYearData->count_not_paid_3yrs ?? 0;
            $data['Unpaid Properties']['amount_not_paid_3yrs'] = round(($prevYearData->amount_not_paid_3yrs ?? 0) / 100000, 2); #_in lacs
            $data['Unpaid Properties']['count_not_paid_2yrs']  = $prevYearData->count_not_paid_2yrs ?? 0;
            $data['Unpaid Properties']['amount_not_paid_2yrs'] = round(($prevYearData->amount_not_paid_2yrs ?? 0) / 100000, 2); #_in lacs
            $data['Unpaid Properties']['count_not_paid_1yrs']  = $prevYearData->count_not_paid_1yrs ?? 0;
            $data['Unpaid Properties']['amount_not_paid_1yrs'] = round(($prevYearData->amount_not_paid_1yrs ?? 0) / 100000, 2); #_in lacs

            #_Outstanding Demand Last Year
            // 604529369.42  =>total demand
            $data['Outstanding Demand Last Year']['outstanding']        = round(($prevYearData->demand_outstanding ?? 0) / 100000, 2);          #_in lacs
            $data['Outstanding Demand Last Year']['outstanding_count']  = $prevYearData->demand_outstanding_count ?? 0;
            $data['Outstanding Demand Last Year']['outstanding_amount'] = round(($prevYearData->demand_outstanding_amount ?? 0) / 100000, 2);   #_in lacs
            $data['Outstanding Demand Last Year']['extempted']          = round(($prevYearData->demand_extempted ?? 0) / 100000, 2);            #_in lacs
            $data['Outstanding Demand Last Year']['extempted_count']    = $prevYearData->demand_extempted_count ?? 0;                           #_in lacs
            $data['Outstanding Demand Last Year']['extempted_amount']   = round(($prevYearData->demand_extempted_amount ?? 0) / 100000, 2);     #_in lacs
            $data['Outstanding Demand Last Year']['recoverable_demand'] = round(($prevYearData->demand_recoverable_demand ?? 0) / 100000, 2);   #_in lacs   #_collection amount
            $data['Outstanding Demand Last Year']['payment_done']       = round(($prevYearData->demand_payment_done ?? 0) / 100000, 2);         #_in lacs
            $data['Outstanding Demand Last Year']['payment_due']        = round(($prevYearData->demand_balance_this_year ?? 0) / 100000, 2);          #_in lacs

            #_Outstanding Demand Current Year
            $data['Outstanding Demand Current Year']['outstanding']        = round(($currentYearData->demand_outstanding ?? 0) / 100000, 2);             #_in lacs
            $data['Outstanding Demand Current Year']['outstanding_count']  = $currentYearData->demand_outstanding_count ?? 0;
            $data['Outstanding Demand Current Year']['outstanding_amount'] = round(($currentYearData->demand_outstanding_amount ?? 0) / 100000, 2);      #_in lacs
            $data['Outstanding Demand Current Year']['extempted']          = round(($currentYearData->demand_extempted ?? 0) / 100000, 2);               #_in lacs
            $data['Outstanding Demand Current Year']['extempted_count']    = $currentYearData->demand_extempted_count ?? 0;
            $data['Outstanding Demand Current Year']['extempted_amount']   = round(($currentYearData->demand_extempted_amount ?? 0) / 100000, 2);        #_in lacs
            $data['Outstanding Demand Current Year']['recoverable_demand'] = round(($currentYearData->demand_recoverable_demand ?? 0) / 100000, 2);             #_in lacs
            $data['Outstanding Demand Current Year']['payment_done']       = round(($currentYearData->demand_payment_done ?? 0) / 100000, 2);            #_in lacs
            $data['Outstanding Demand Current Year']['payment_due']        = round(($currentYearData->demand_balance_this_year ?? 0) / 100000, 2);             #_in lacs

            #_Payments
            $data['Payments']['previous_to_last_year_payment_count']  = $prevYearData->previous_to_last_year_payment_count ?? 0;
            $data['Payments']['previous_to_last_year_payment_amount'] = round(($prevYearData->previous_to_last_year_payment_amount ?? 0) / 100000, 2);   #_in lacs
            $data['Payments']['last_year_payment_count']              = $prevYearData->last_year_payment_count ?? 0;
            $data['Payments']['last_year_payment_amount']             = round(($prevYearData->last_year_payment_amount ?? 0) / 100000, 2);               #_in lacs
            $data['Payments']['this_year_payment_count']              = $currentYearData->this_year_payment_count ?? 0;
            $data['Payments']['this_year_payment_amount']             = round(($currentYearData->this_year_payment_amount ?? 0) / 100000, 2);            #_in lacs

            #_Single Payment
            $data['Single Payment']['before_previous_year_count'] = $prevYearData->single_payment_before_this_year_count ?? 0;
            $data['Single Payment']['previous_year_count']        = $currentYearData->single_payment_before_this_year_count ?? 0; // ?? one time payment in saf only

            #_Notice
            $data['Notice']['last_year_count']     = $prevYearData->notice_this_year_count ?? 0;
            $data['Notice']['last_year_amount']    = round(($prevYearData->notice_this_year_amount ?? 0) / 100000, 2);               #_in lacs
            $data['Notice']['last_year_recovery']  = round(($prevYearData->notice_this_year_recovery ?? 0) / 100000, 2);             #_in lacs
            $data['Notice']['this_year_count']     = $currentYearData->notice_this_year_count ?? 0;
            $data['Notice']['this_year_amount']    = round(($currentYearData->notice_this_year_amount ?? 0) / 100000, 2);            #_in lacs
            $data['Notice']['this_year_recovery']  = round(($currentYearData->notice_this_year_recovery ?? 0) / 100000, 2);          #_in lacs

            #_Mutation
            $data['Mutation']['last_year_count']  = $prevYearData->mutation_this_year_count ?? 0;
            $data['Mutation']['last_year_amount'] = round(($prevYearData->mutation_this_year_amount ?? 0) / 100000, 2);              #_in lacs
            $data['Mutation']['this_year_count']  = $currentYearData->mutation_this_year_count ?? 0;
            $data['Mutation']['this_year_amount'] = round(($currentYearData->mutation_this_year_amount ?? 0) / 100000, 2);           #_in lacs

            #_Top Areas Property Transactions 
            /**
             include ward no
             */
            $data['Top Areas Property Transactions']['ward1_count'] = $currentYearData->top_area_property_transaction_ward1_count ?? 0;
            $data['Top Areas Property Transactions']['ward2_count'] = $currentYearData->top_area_property_transaction_ward2_count ?? 0;
            $data['Top Areas Property Transactions']['ward3_count'] = $currentYearData->top_area_property_transaction_ward3_count ?? 0;
            $data['Top Areas Property Transactions']['ward4_count'] = $currentYearData->top_area_property_transaction_ward4_count ?? 0;
            $data['Top Areas Property Transactions']['ward5_count'] = $currentYearData->top_area_property_transaction_ward5_count ?? 0;

            #_Top Areas Saf
            /**
             include ward no
             */
            $data['Top Areas Saf']['ward1_count'] = $currentYearData->top_area_saf_ward1_count ?? 0;
            $data['Top Areas Saf']['ward2_count'] = $currentYearData->top_area_saf_ward2_count ?? 0;
            $data['Top Areas Saf']['ward3_count'] = $currentYearData->top_area_saf_ward3_count ?? 0;
            $data['Top Areas Saf']['ward4_count'] = $currentYearData->top_area_saf_ward4_count ?? 0;
            $data['Top Areas Saf']['ward5_count'] = $currentYearData->top_area_saf_ward5_count ?? 0;

            #_Payment Modes
            $data['Payment Modes']['current_year_cash_collection']   = round(($currentYearData->current_year_cash_collection ?? 0) / 100000, 2);             #_in lacs
            $data['Payment Modes']['last_year_cash_collection']      = round(($prevYearData->current_year_cash_collection ?? 0) / 100000, 2);                   #_in lacs
            $data['Payment Modes']['current_year_upi_collection']    = round(($currentYearData->current_year_upi_collection ?? 0) / 100000, 2);              #_in lacs
            $data['Payment Modes']['last_year_upi_collection']       = round(($prevYearData->current_year_upi_collection ?? 0) / 100000, 2);                    #_in lacs
            $data['Payment Modes']['current_year_card_collection']   = round(($currentYearData->current_year_card_collection ?? 0) / 100000, 2);             #_in lacs
            $data['Payment Modes']['last_year_card_collection']      = round(($prevYearData->current_year_card_collection ?? 0) / 100000, 2);                   #_in lacs
            $data['Payment Modes']['current_year_cheque_collection'] = round(($currentYearData->current_year_cheque_collection ?? 0) / 100000, 2);           #_in lacs
            $data['Payment Modes']['last_year_cheque_collection']    = round(($prevYearData->current_year_cheque_collection ?? 0) / 100000, 2);                 #_in lacs
            $data['Payment Modes']['current_year_dd_collection']     = round(($currentYearData->current_year_dd_collection ?? 0) / 100000, 2);               #_in lacs
            $data['Payment Modes']['last_year_dd_collection']        = round(($prevYearData->current_year_dd_collection ?? 0) / 100000, 2);                     #_in lacs

            #_Citizen Engagement
            $data['Citizen Engagement']['online_application_count_prev_year']  = $prevYearData->online_application_count_this_year ?? 0;
            $data['Citizen Engagement']['online_application_count_this_year']  = $currentYearData->online_application_count_this_year ?? 0;
            $data['Citizen Engagement']['online_application_amount_prev_year'] = round(($prevYearData->online_application_amount_this_year ?? 0) / 100000, 2);       #_in lacs
            $data['Citizen Engagement']['online_application_amount_this_year'] = round(($currentYearData->online_application_amount_this_year ?? 0) / 100000, 2);    #_in lacs
            $data['Citizen Engagement']['jsk_application_count_prev_year']     = $prevYearData->jsk_application_count_this_year ?? 0;
            $data['Citizen Engagement']['jsk_application_count_this_year']     = $currentYearData->jsk_application_count_this_year ?? 0;
            $data['Citizen Engagement']['jsk_application_amount_prev_year']    = round(($prevYearData->jsk_application_amount_this_year ?? 0) / 100000, 2);          #_in lacs
            $data['Citizen Engagement']['jsk_application_amount_this_year']    = round(($currentYearData->jsk_application_amount_this_year ?? 0) / 100000, 2);       #_in lacs

            #_Compliances
            $data['Compliances']['no_of_property_inspected_prev_year'] = $prevYearData->no_of_property_inspected_this_year ?? 0;
            $data['Compliances']['no_of_defaulter_prev_year']          = $prevYearData->no_of_defaulter_this_year ?? 0;
            $data['Compliances']['no_of_property_inspected_this_year'] = $currentYearData->no_of_property_inspected_this_year ?? 0;
            $data['Compliances']['no_of_defaulter_this_year']          = $currentYearData->no_of_defaulter_this_year ?? 0;

            $data['Demand']['prev_year']             = round(($prevYearData->demand_for_this_year ?? 0) / 100000, 2); #_in lacs
            $data['Demand']['current_year']          = round(($currentYearData->demand_for_this_year ?? 0) / 100000, 2); #_in lacs
            $data['Collection']['prev_year']         = round(($prevYearData->demand_coll_this_year ?? 0) / 100000, 2); #_in lacs
            $data['Collection']['current_year']      = round(($currentYearData->demand_coll_this_year ?? 0) / 100000, 2); #_in lacs
            $data['Balance']['prev_year']            = round(($prevYearData->demand_balance_this_year ?? 0)  / 100000, 2); #_in lacs
            $data['Balance']['current_year']         = round(($currentYearData->demand_balance_this_year ?? 0) / 100000, 2); #_in lacs
            $data['Total Payment From HH']['prev_year']    = $prevYearData->demand_coll_from_this_year_prop_count ?? 0;
            $data['Total Payment From HH']['current_year'] = $currentYearData->demand_coll_from_this_year_prop_count ?? 0;


            return responseMsgs(true, "Mpl Report", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    public function mplReport(Request $request)
    {
        try {
            $ulbId = $request->ulbId ?? 2;
            $fyear = getFY();
            $fyArr = explode("-", $fyear);
            $privYear = ($fyArr[0] - 1) . "-" . ($fyArr[1] - 1);
            $prevYearData =  DB::connection('pgsql_reports')
                ->table('mpl_yearly_reports')
                ->where('ulb_id', $ulbId)
                ->where("fyear", $privYear)
                ->first();
            $currentYearData =  DB::connection('pgsql_reports')
                ->table('mpl_yearly_reports')
                ->where('ulb_id', $ulbId)
                ->where("fyear", $fyear)
                ->first();

            #_Assessed Properties ??
            $data['Assessed Properties']['target_for_last_year']    = $prevYearData->assessed_property_target_for_this_year ?? 0;
            $data['Assessed Properties']['last_year_achievement']   = $prevYearData->assessed_property_this_year_achievement ?? 0;
            $data['Assessed Properties']['target_for_this_year']    = $currentYearData->assessed_property_target_for_this_year ?? 0;
            $data['Assessed Properties']['this_year_achievement']   = $currentYearData->assessed_property_this_year_achievement ?? 0;

            #_Saf Achievement
            $data['Saf Achievement']['previous_year_target']        = $prevYearData->saf_current_year_target ?? 0;
            $data['Saf Achievement']['previous_year_achievement']   = $prevYearData->saf_current_year_achievement ?? 0;
            $data['Saf Achievement']['current_year_target']         = $currentYearData->saf_current_year_target ?? 0;
            $data['Saf Achievement']['current_year_achievement']    = $currentYearData->saf_current_year_achievement ?? 0;

            #_Assessment Categories ??
            $data['Assessment Categories']['total_assessment']  = $currentYearData->total_assessment ?? 0;
            $data['Assessment Categories']['residential']       = $currentYearData->total_assessed_residential ?? 0;
            $data['Assessment Categories']['commercial']        = $currentYearData->total_assessed_commercial ?? 0;
            $data['Assessment Categories']['industrial']        = $currentYearData->total_assessed_industrial ?? 0;
            $data['Assessment Categories']['gbsaf']             = $currentYearData->total_assessed_gbsaf ?? 0;
            $data['Assessment Categories']['mix']               = $currentYearData->total_assessed_mixe ?? 0;
            $data['Assessment Categories']['vacand']            = $currentYearData->total_assessed_vacand ?? 0;

            $data['Prop Categories']['total_assessment']  = $currentYearData->total_property ?? 0;
            $data['Prop Categories']['residential']       = $currentYearData->total_prop_residential ?? 0;
            $data['Prop Categories']['commercial']        = $currentYearData->total_prop_commercial ?? 0;
            $data['Prop Categories']['industrial']        = $currentYearData->total_prop_industrial ?? 0;
            $data['Prop Categories']['gbsaf']             = $currentYearData->total_prop_gbsaf ?? 0;
            $data['Prop Categories']['mix']               = $currentYearData->total_prop_mixe ?? 0;
            $data['Prop Categories']['vacand']            = $currentYearData->total_prop_vacand ?? 0;
            $data['Prop Categories']['religious']         = $currentYearData->total_prop_religious ?? 0;

            #_payment_status
            $data['PaymentStatus']['both_paid']            = $currentYearData->current_arear_demand_clear_prop_count ?? 0;
            $data['PaymentStatus']['both_unpaid']        = $currentYearData->current_arear_demand_not_clear_prop_count ?? 0;
            $data['PaymentStatus']['curent_unpaid']            = $currentYearData->arear_demand_clear_but_not_current_prop_count ?? 0;
            // $data['PaymentStatus']['arear_demand_not_clear']            = $currentYearData->arear_demand_not_clear_prop_count ?? 0;
            // $data['PaymentStatus']['current_arear_demand_clear']            = $currentYearData->current_arear_demand_clear_prop_count ?? 0;
            // $data['PaymentStatus']['current_arear_demand_not_clear']            = $currentYearData->current_arear_demand_not_clear_prop_count ?? 0;

            #_Ownership ??
            $data['Ownership']['total_ownership'] = $currentYearData->total_property ?? 0;
            $data['Ownership']['owned_property']  = $currentYearData->owned_property ?? 0;
            $data['Ownership']['rented_property'] = $currentYearData->rented_property ?? 0;
            $data['Ownership']['mixed_property']  = $currentYearData->mixed_property ?? 0;
            $data['Ownership']['vacant_property'] = $currentYearData->vacant_property ?? 0;

            #_Unpaid Properties
            $data['Unpaid Properties']["previous"]['count_not_paid_3yrs']  = $prevYearData->count_not_paid_3yrs ?? 0;
            $data['Unpaid Properties']["previous"]['amount_not_paid_3yrs'] = round(($prevYearData->amount_not_paid_3yrs ?? 0) / 10000000, 2); #_in cr
            $data['Unpaid Properties']["previous"]['count_not_paid_2yrs']  = $prevYearData->count_not_paid_2yrs ?? 0;
            $data['Unpaid Properties']["previous"]['amount_not_paid_2yrs'] = round(($prevYearData->amount_not_paid_2yrs ?? 0) / 10000000, 2); #_in cr
            $data['Unpaid Properties']["previous"]['count_not_paid_1yrs']  = $prevYearData->count_not_paid_1yrs ?? 0;
            $data['Unpaid Properties']["previous"]['amount_not_paid_1yrs'] = round(($prevYearData->amount_not_paid_1yrs ?? 0) / 10000000, 2); #_in cr
            $data['Unpaid Properties']["previous"]['count_not_paid_this_yrs']  = $prevYearData->property_count_not_paid_this_years ?? 0;
            $data['Unpaid Properties']["previous"]['amount_not_paid_this_yrs'] = round(($prevYearData->property_amount_not_paid_this_years ?? 0) / 10000000, 2); #_in cr

            $data['Unpaid Properties']["current"]['count_not_paid_3yrs']  = $currentYearData->count_not_paid_3yrs ?? 0;
            $data['Unpaid Properties']["current"]['amount_not_paid_3yrs'] = round(($currentYearData->amount_not_paid_3yrs ?? 0) / 10000000, 2); #_in cr
            $data['Unpaid Properties']["current"]['count_not_paid_2yrs']  = $currentYearData->count_not_paid_2yrs ?? 0;
            $data['Unpaid Properties']["current"]['amount_not_paid_2yrs'] = round(($currentYearData->amount_not_paid_2yrs ?? 0) / 10000000, 2); #_in cr
            $data['Unpaid Properties']["current"]['count_not_paid_1yrs']  = $currentYearData->count_not_paid_1yrs ?? 0;
            $data['Unpaid Properties']["current"]['amount_not_paid_1yrs'] = round(($currentYearData->amount_not_paid_1yrs ?? 0) / 10000000, 2); #_in cr
            $data['Unpaid Properties']["current"]['count_not_paid_this_yrs']  = $currentYearData->property_count_not_paid_this_years ?? 0;
            $data['Unpaid Properties']["current"]['amount_not_paid_this_yrs'] = round(($currentYearData->property_amount_not_paid_this_years ?? 0) / 10000000, 2); #_in cr

            #_Outstanding Demand Last Year
            // 604529369.42  =>total demand
            $data['Outstanding Demand Last Year']['outstanding']        = round(($prevYearData->demand_outstanding_this_year ?? 0) / 10000000, 2);          #_in cr
            $data['Outstanding Demand Last Year']['outstanding_count']  = $prevYearData->demand_outstanding_from_this_year_prop_count ?? 0;
            $data['Outstanding Demand Last Year']['outstanding_amount'] = round(($prevYearData->demand_outstanding_this_year ?? 0) / 10000000, 2);   #_in cr
            $data['Outstanding Demand Last Year']['extempted']          = round(($prevYearData->demand_extempted ?? 0) / 10000000, 2);            #_in cr
            $data['Outstanding Demand Last Year']['extempted_count']    = $prevYearData->demand_extempted_count ?? 0;                           #_in cr
            $data['Outstanding Demand Last Year']['extempted_amount']   = round(($prevYearData->demand_extempted_amount ?? 0) / 10000000, 2);     #_in cr
            $data['Outstanding Demand Last Year']['recoverable_demand'] = round(($prevYearData->demand_recoverable_demand ?? 0) / 10000000, 2);   #_in cr   #_collection amount
            $data['Outstanding Demand Last Year']['payment_done']       = round(($prevYearData->demand_coll_this_year ?? 0) / 10000000, 2);         #_in cr
            $data['Outstanding Demand Last Year']['payment_due']        = round(($prevYearData->demand_balance_this_year ?? 0) / 10000000, 2);          #_in cr

            #recovery_demand
            $data['recovery']['arear']['demand']        = round(($currentYearData->demand_outstanding_this_year ?? 0) / 10000000, 2);             #_in cr
            $data['recovery']['arear']['recover']       = round(($currentYearData->demand_outstanding_coll_this_year ?? 0) / 10000000, 2);
            $data['recovery']['current']['demand']      = round(($currentYearData->demand_for_this_year ?? 0) / 10000000, 2);      #_in cr
            $data['recovery']['current']['recover']     = round(($currentYearData->demand_coll_this_year ?? 0) / 10000000, 2);               #_in cr

            #_Outstanding Demand Current Year
            $data['Outstanding Demand Current Year']['outstanding']        = round(($currentYearData->demand_outstanding_this_year ?? 0) / 10000000, 2);             #_in cr
            $data['Outstanding Demand Current Year']['outstanding_count']  = $currentYearData->demand_outstanding_from_this_year_prop_count ?? 0;
            $data['Outstanding Demand Current Year']['outstanding_amount'] = round(($currentYearData->demand_outstanding_this_year ?? 0) / 10000000, 2);      #_in cr
            $data['Outstanding Demand Current Year']['extempted']          = round(($currentYearData->demand_extempted ?? 0) / 10000000, 2);               #_in cr
            $data['Outstanding Demand Current Year']['extempted_count']    = $currentYearData->demand_extempted_count ?? 0;
            $data['Outstanding Demand Current Year']['extempted_amount']   = round(($currentYearData->demand_extempted_amount ?? 0) / 10000000, 2);        #_in cr
            $data['Outstanding Demand Current Year']['recoverable_demand'] = round(($currentYearData->demand_recoverable_demand ?? 0) / 10000000, 2);             #_in cr
            $data['Outstanding Demand Current Year']['payment_done']       = round(($currentYearData->demand_coll_this_year ?? 0) / 10000000, 2);            #_in cr
            $data['Outstanding Demand Current Year']['payment_due']        = round(($currentYearData->demand_balance_this_year ?? 0) / 10000000, 2);             #_in cr

            #_Payments
            $data['Payments']['previous_to_last_year_payment_count']  = $prevYearData->previous_to_last_year_payment_count ?? 0;
            $data['Payments']['previous_to_last_year_payment_amount'] = round(($prevYearData->previous_to_last_year_payment_amount ?? 0) / 10000000, 2);   #_in cr
            $data['Payments']['last_year_payment_count']              = $prevYearData->last_year_payment_count ?? 0;
            $data['Payments']['last_year_payment_amount']             = round(($prevYearData->last_year_payment_amount ?? 0) / 10000000, 2);               #_in cr
            $data['Payments']['this_year_payment_count']              = $currentYearData->this_year_payment_count ?? 0;
            $data['Payments']['this_year_payment_amount']             = round(($currentYearData->this_year_payment_amount ?? 0) / 10000000, 2);            #_in cr

            #_Single Payment
            $data['Single Payment']['before_previous_year_count'] = $prevYearData->single_payment_before_this_year_count ?? 0;
            $data['Single Payment']['previous_year_count']        = $currentYearData->single_payment_before_this_year_count ?? 0; // ?? one time payment in saf only

            #_Notice
            $data['Notice']['last_year_count']     = $prevYearData->notice_this_year_count ?? 0;
            $data['Notice']['last_year_amount']    = round(($prevYearData->notice_this_year_amount ?? 0) / 10000000, 2);               #_in cr
            $data['Notice']['last_year_recovery']  = round(($prevYearData->notice_this_year_recovery ?? 0) / 10000000, 2);             #_in cr
            $data['Notice']['this_year_count']     = $currentYearData->notice_this_year_count ?? 0;
            $data['Notice']['this_year_amount']    = round(($currentYearData->notice_this_year_amount ?? 0) / 10000000, 2);            #_in cr
            $data['Notice']['this_year_recovery']  = round(($currentYearData->notice_this_year_recovery ?? 0) / 10000000, 2);          #_in cr

            #_Mutation
            $data['Mutation']['last_year_count']  = $prevYearData->mutation_this_year_count ?? 0;
            $data['Mutation']['last_year_amount'] = round(($prevYearData->mutation_this_year_amount ?? 0) / 10000000, 2);              #_in cr
            $data['Mutation']['this_year_count']  = $currentYearData->mutation_this_year_count ?? 0;
            $data['Mutation']['this_year_amount'] = round(($currentYearData->mutation_this_year_amount ?? 0) / 10000000, 2);           #_in cr

            #_Top Areas Property Transactions 
            /**
             include ward no
             */
            $data['Top Areas Property Transactions']['ward1_count'] = $currentYearData->top_area_property_transaction_ward1_count ?? 0;
            $data['Top Areas Property Transactions']['ward2_count'] = $currentYearData->top_area_property_transaction_ward2_count ?? 0;
            $data['Top Areas Property Transactions']['ward3_count'] = $currentYearData->top_area_property_transaction_ward3_count ?? 0;
            $data['Top Areas Property Transactions']['ward4_count'] = $currentYearData->top_area_property_transaction_ward4_count ?? 0;
            $data['Top Areas Property Transactions']['ward5_count'] = $currentYearData->top_area_property_transaction_ward5_count ?? 0;
            $data['Top Areas Property Transactions']['ward1_name']  = $currentYearData->top_area_property_transaction_ward1_name ?? 0;
            $data['Top Areas Property Transactions']['ward2_name']  = $currentYearData->top_area_property_transaction_ward2_name ?? 0;
            $data['Top Areas Property Transactions']['ward3_name']  = $currentYearData->top_area_property_transaction_ward3_name ?? 0;
            $data['Top Areas Property Transactions']['ward4_name']  = $currentYearData->top_area_property_transaction_ward4_name ?? 0;
            $data['Top Areas Property Transactions']['ward5_name']  = $currentYearData->top_area_property_transaction_ward5_name ?? 0;

            #_Payment Modes
            $data['Payment Modes']['current_year_cash_collection']   = round(($currentYearData->current_year_cash_collection ?? 0) / 10000000, 2);             #_in cr
            $data['Payment Modes']['last_year_cash_collection']      = round(($prevYearData->last_year_cash_collection ?? 0) / 10000000, 2);                   #_in cr
            $data['Payment Modes']['current_year_upi_collection']    = round(($currentYearData->current_year_upi_collection ?? 0) / 10000000, 2);              #_in cr
            $data['Payment Modes']['last_year_upi_collection']       = round(($prevYearData->last_year_upi_collection ?? 0) / 10000000, 2);                    #_in cr
            $data['Payment Modes']['current_year_card_collection']   = round(($currentYearData->current_year_card_collection ?? 0) / 10000000, 2);             #_in cr
            $data['Payment Modes']['last_year_card_collection']      = round(($prevYearData->last_year_card_collection ?? 0) / 10000000, 2);                   #_in cr
            $data['Payment Modes']['current_year_cheque_collection'] = round(($currentYearData->current_year_cheque_collection ?? 0) / 10000000, 2);           #_in cr
            $data['Payment Modes']['last_year_cheque_collection']    = round(($prevYearData->last_year_cheque_collection ?? 0) / 10000000, 2);                 #_in cr
            $data['Payment Modes']['current_year_dd_collection']     = round(($currentYearData->current_year_dd_collection ?? 0) / 10000000, 2);               #_in cr
            $data['Payment Modes']['last_year_dd_collection']        = round(($prevYearData->last_year_dd_collection ?? 0) / 10000000, 2);                     #_in cr
            $data['Payment Modes']['current_year_neft_collection']     = round(($currentYearData->current_year_neft_collection ?? 0) / 10000000, 2);               #_in cr
            $data['Payment Modes']['last_year_neft_collection']        = round(($prevYearData->last_year_neft_collection ?? 0) / 10000000, 2);                     #_in cr
            $data['Payment Modes']['current_year_rtgs_collection']     = round(($currentYearData->current_year_rtgs_collection ?? 0) / 10000000, 2);               #_in cr
            $data['Payment Modes']['last_year_rtgs_collection']        = round(($prevYearData->last_year_rtgs_collection ?? 0) / 10000000, 2);                     #_in cr
            $data['Payment Modes']['current_year_online_collection']     = round(($currentYearData->current_year_online_collection ?? 0) / 10000000, 2);               #_in cr
            $data['Payment Modes']['last_year_online_collection']        = round(($prevYearData->online_application_amount_this_year ?? 0) / 10000000, 2);                     #_in cr

            #_Citizen Engagement
            $data['Citizen Engagement']['online_application_count_prev_year']  = $prevYearData->online_application_count_prev_year ?? 0;
            $data['Citizen Engagement']['online_application_count_this_year']  = $currentYearData->online_application_count_this_year ?? 0;
            $data['Citizen Engagement']['online_application_amount_prev_year'] = round(($prevYearData->online_application_amount_prev_year ?? 0) / 10000000, 2);       #_in cr
            $data['Citizen Engagement']['online_application_amount_this_year'] = round(($currentYearData->online_application_amount_this_year ?? 0) / 10000000, 2);    #_in cr
            $data['Citizen Engagement']['jsk_application_count_prev_year']     = $prevYearData->jsk_application_count_prev_year ?? 0;
            $data['Citizen Engagement']['jsk_application_count_this_year']     = $currentYearData->jsk_application_count_this_year ?? 0;
            $data['Citizen Engagement']['jsk_application_amount_prev_year']    = round(($prevYearData->jsk_application_amount_prev_year ?? 0) / 10000000, 2);          #_in cr
            $data['Citizen Engagement']['jsk_application_amount_this_year']    = round(($currentYearData->jsk_application_amount_this_year ?? 0) / 10000000, 2);       #_in cr

            #_Compliances
            $data['Compliances']['no_of_property_inspected_prev_year'] = $prevYearData->no_of_property_inspected_prev_year ?? 0;
            $data['Compliances']['no_of_defaulter_prev_year']          = $prevYearData->no_of_defaulter_prev_year ?? 0;
            $data['Compliances']['no_of_property_inspected_this_year'] = $currentYearData->no_of_property_inspected_this_year ?? 0;
            $data['Compliances']['no_of_defaulter_this_year']          = $currentYearData->no_of_defaulter_this_year ?? 0;

            $data['Demand']['prev_year']             = round(($prevYearData->demand_for_this_year ?? 0) / 10000000, 2); #_in cr
            $data['Demand']['current_year']          = round(($currentYearData->demand_for_this_year ?? 0) / 10000000, 2); #_in cr
            // $data['Demand']['arrear']          = round(($currentYearData->demand_outstanding_this_year ?? 0) / 10000000, 2);             #_in cr
            $data['Collection_Against_Current_Demand']      = round(($currentYearData->collection_against_current_demand ?? 0) / 10000000, 2);             #_in cr
            $data['Collection']['prev_year']         = round(($prevYearData->demand_coll_this_year ?? 0) / 10000000, 2); #_in cr
            $data['Collection']['current_year']      = round(($currentYearData->demand_coll_this_year ?? 0) / 10000000, 2); #_in cr
            // $data['Collection']['arrear']      = round(($currentYearData->demand_outstanding_coll_this_year ?? 0) / 10000000, 2);
            $data['Collection_Against_Arrear_Demand']      = round(($currentYearData->collection_againt_arrear_demand ?? 0) / 10000000, 2);
            $data['Balance']['prev_year']            = round(($prevYearData->demand_balance_this_year ?? 0)  / 10000000, 2); #_in cr
            $data['Balance']['current_year']         = round(($currentYearData->demand_balance_this_year ?? 0) / 10000000, 2); #_in cr
            $data['Total Payment From HH']['prev_year']    = $prevYearData->demand_coll_from_this_year_prop_count ?? 0;
            $data['Total Payment From HH']['current_year'] = $currentYearData->demand_coll_from_this_year_prop_count ?? 0;

            $data['Property Count']['till_prev_year'] = $prevYearData->total_property ?? 0;
            $data['Property Count']['till_current_year'] = $currentYearData->total_property ?? 0;

            $data['member_count']['tc']  = $currentYearData->tc_count ?? 0;
            $data['member_count']['da']  = $currentYearData->da_count ?? 0;
            $data['member_count']['si']  = $currentYearData->si_count ?? 0;
            $data['member_count']['eo']  = $currentYearData->eo_count ?? 0;
            $data['member_count']['jsk'] = $currentYearData->jsk_count ?? 0;
            $data['member_count']['utc'] = $currentYearData->utc_count ?? 0;
            $data['member_count']['sh_count'] = $currentYearData->sh_count ?? 0;

            $data['citizen']["Engagement"] = [
                "apr" => [
                    'month' => "apr",
                    "value" => 0,
                ],
                "may" => [
                    'month' => "may",
                    "value" => 0,
                ],
                "june" => [
                    'month' => "june",
                    "value" => 0,
                ],
                "july" => [
                    'month' => "july",
                    "value" => 0,
                ],
                "aug" => [
                    'month' => "aug",
                    "value" => 0,
                ],
                "sept" => [
                    'month' => "sept",
                    "value" => 0,
                ],
                "oct" => [
                    'month' => "oct",
                    "value" => 0,
                ],
                "nov" => [
                    'month' => "nov",
                    "value" => 0,
                ],
                "dec" => [
                    'month' => "dec",
                    "value" => 0,
                ],
                "jan" => [
                    'month' => "jan",
                    "value" => 0,
                ],
                "feb" => [
                    'month' => "feb",
                    "value" => 0,
                ],
                "mar" => [
                    'month' => "mar",
                    "value" => 0,
                ],
            ];
            #_Top Areas Saf
            /**
             include ward no
             */
            $data["Top Areas Saf"] = [
                "ward1" => [
                    "key" => "ward1",
                    "count" => $currentYearData->top_area_saf_ward1_count ?? 0,
                    "ward" => $currentYearData->top_area_saf_ward1_name ?? "",
                    "area" => $currentYearData->top_area_saf_ward1_area ?? "",
                ],
                "ward2" => [
                    "key" => "ward2",
                    "count" => $currentYearData->top_area_saf_ward2_count ?? 0,
                    "ward" => $currentYearData->top_area_saf_ward2_name ?? "",
                    "area" => $currentYearData->top_area_saf_ward2_area ?? "",
                ],
                "ward3" => [
                    "key" => "ward3",
                    "count" => $currentYearData->top_area_saf_ward3_count ?? 0,
                    "ward" => $currentYearData->top_area_saf_ward3_name ?? "",
                    "area" => $currentYearData->top_area_saf_ward3_area ?? "",
                ],
                "ward4" => [
                    "key" => "ward4",
                    "count" => $currentYearData->top_area_saf_ward4_count ?? 0,
                    "ward" => $currentYearData->top_area_saf_ward4_name ?? "",
                    "area" => $currentYearData->top_area_saf_ward4_area ?? "",
                ],
                "ward5" => [
                    "key" => "ward5",
                    "count" => $currentYearData->top_area_saf_ward5_count ?? 0,
                    "ward" => $currentYearData->top_area_saf_ward5_name ?? "",
                    "area" => $currentYearData->top_area_saf_ward5_area ?? "",
                ],
            ];

            /**
             * | Top Defaulter Ward Name
             */
            $data["Top Defaulter"] = [
                "ward1" => [
                    "key" => "ward1",
                    "count" => $currentYearData->top_defaulter_ward1_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward1_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward1_name ?? "",
                ],
                "ward2" => [
                    "key" => "ward2",
                    "count" => $currentYearData->top_defaulter_ward2_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward2_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward2_name ?? "",
                ],
                "ward3" => [
                    "key" => "ward3",
                    "count" => $currentYearData->top_defaulter_ward3_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward3_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward3_name ?? "",
                ],
                "ward4" => [
                    "key" => "ward4",
                    "count" => $currentYearData->top_defaulter_ward4_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward4_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward4_name ?? "",
                ],
                "ward5" => [
                    "key" => "ward5",
                    "count" => $currentYearData->top_defaulter_ward5_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward5_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward5_name ?? "",
                ],
                "ward6" => [
                    "key" => "ward6",
                    "count" => $currentYearData->top_defaulter_ward6_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward6_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward6_name ?? "",
                ],
                "ward7" => [
                    "key" => "ward7",
                    "count" => $currentYearData->top_defaulter_ward7_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward7_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward7_name ?? "",
                ],
                "ward8" => [
                    "key" => "ward8",
                    "count" => $currentYearData->top_defaulter_ward8_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward8_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward8_name ?? "",
                ],
                "ward9" => [
                    "key" => "ward9",
                    "count" => $currentYearData->top_defaulter_ward9_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward9_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward9_name ?? "",
                ],
                "ward10" => [
                    "key" => "ward10",
                    "count" => $currentYearData->top_defaulter_ward10_count ?? 0,
                    "amount" => round(($currentYearData->top_defaulter_ward10_amount ?? 0) / 10000000, 2), #_in cr
                    "ward" => $currentYearData->top_defaulter_ward10_name ?? "",
                ],
            ];

            #trade
            $data['Trade']['tota_trade_licenses']    = $currentYearData->total_trade_licenses;
            $data['Trade']['total_trade_licenses_underprocess']    = $currentYearData->total_trade_licenses_underprocess;
            $data['Trade']['trade_current_cash_payment']    = $currentYearData->trade_current_cash_payment;
            $data['Trade']['trade_current_cheque_payment']    = $currentYearData->trade_current_cheque_payment;
            $data['Trade']['trade_current_dd_payment']    = $currentYearData->trade_current_dd_payment;
            $data['Trade']['trade_current_card_payment']    = $currentYearData->trade_current_card_payment;
            $data['Trade']['trade_current_neft_payment']    = $currentYearData->trade_current_neft_payment;
            $data['Trade']['trade_current_rtgs_payment']    = $currentYearData->trade_current_rtgs_payment;
            $data['Trade']['trade_current_online_payment']    = $currentYearData->trade_current_online_payment;
            $data['Trade']['trade_current_online_counts']    = $currentYearData->trade_current_online_counts;
            $data['Trade']['trade_lastyear_cash_payment']    = $currentYearData->trade_lastyear_cash_payment;
            $data['Trade']['trade_lastyear_cheque_payment']    = $currentYearData->trade_lastyear_cheque_payment;
            $data['Trade']['trade_lastyear_dd_payment']    = $currentYearData->trade_lastyear_dd_payment;
            $data['Trade']['trade_lastyear_neft_payment']    = $currentYearData->trade_lastyear_neft_payment;
            $data['Trade']['tota_trade_licenses']    = $currentYearData->total_trade_licenses;
            $data['Trade']['trade_lastyear_rtgs_payment']    = $currentYearData->trade_lastyear_rtgs_payment;
            $data['Trade']['trade_lastyear_online_payment']    = $currentYearData->trade_lastyear_online_payment;
            $data['Trade']['trade_lastyear_online_counts']    = $currentYearData->trade_lastyear_online_counts;
            $data['Trade']['trade_renewal_less_then_1_year']    = $currentYearData->trade_renewal_less_then_1_year;
            $data['Trade']['trade_renewal_more_then_1_year']    = $currentYearData->trade_renewal_more_then_1_year;
            $data['Trade']['trade_renewal_more_then_1_year_and_less_then_5_years']    = $currentYearData->trade_renewal_more_then_1_year_and_less_then_5_years;
            $data['Trade']['trade_renewal_more_then_5_year']    = $currentYearData->trade_renewal_more_then_5_year;
            $data['Trade']['a_trade_zone_name']    = $currentYearData->a_trade_zone_name;
            $data['Trade']['a_trade_total_hh']    = $currentYearData->a_trade_total_hh;
            $data['Trade']['a_trade_total_amount']    = $currentYearData->a_trade_total_amount;
            $data['Trade']['b_trade_zone_name']    = $currentYearData->b_trade_zone_name;
            $data['Trade']['b_trade_total_hh']    = $currentYearData->b_trade_total_hh;
            $data['Trade']['b_trade_total_amount']    = $currentYearData->b_trade_total_amount;
            $data['Trade']['c_trade_zone_name']    = $currentYearData->c_trade_zone_name;
            $data['Trade']['c_trade_total_hh']    = $currentYearData->c_trade_total_hh;
            $data['Trade']['c_trade_total_amount']    = $currentYearData->c_trade_total_amount;
            $data['Trade']['d_trade_zone_name']    = $currentYearData->d_trade_zone_name;
            $data['Trade']['d_trade_total_hh']    = $currentYearData->d_trade_total_hh;
            $data['Trade']['d_trade_total_amount']    = $currentYearData->d_trade_total_amount;

            #water
            $data['Water']['water_connection_underprocess']    = $currentYearData->water_connection_underprocess;
            $data['Water']['water_fix_connection_type']    = $currentYearData->water_fix_connection_type;
            $data['Water']['water_meter_connection_type']    = $currentYearData->water_meter_connection_type;

            //$data['Water']['water_current_demand']    = round(($currentYearData->water_current_demand)/10000000,2);
            $data['Water']['water_current_demand']    = round(($currentYearData->water_current_demand ?? 0) / 10000000, 2); #in cr
            $data['Water']['water_arrear_demand']    = round(($currentYearData->water_arrear_demand ?? 0) / 10000000, 2); # in cr

            // $data['Water']['water_total_demand']    = $currentYearData->water_total_demand;
            $data['Water']['water_current_collection']    = round(($currentYearData->water_current_collection ?? 0) / 10000000, 2); # in cr
            $data['Water']['water_arrear_collection']    = round(($currentYearData->water_arrear_collection ?? 0) / 10000000, 2); # in cr
            $data['Water']['water_total_collection']    = round(($currentYearData->water_total_collection ?? 0) / 10000000, 2); # in cr
            $data['Water']['water_current_collection_efficiency']    = $currentYearData->water_current_collection_efficiency;
            $data['Water']['water_arrear_collection_efficiency']    = $currentYearData->water_arrear_collection_efficiency;

            $data['Water']['a_water_zone_name']    = $currentYearData->a_water_zone_name;
            $data['Water']['a_water_total_hh']    = $currentYearData->a_water_total_hh;
            $data['Water']['a_water_total_amount']    = $currentYearData->a_water_total_amount;
            $data['Water']['b_water_zone_name']    = $currentYearData->b_water_zone_name;
            $data['Water']['b_water_total_hh']    = $currentYearData->b_water_total_hh;
            $data['Water']['b_water_total_amount']    = $currentYearData->b_water_total_amount;
            $data['Water']['c_water_zone_name']    = $currentYearData->c_water_zone_name;
            $data['Water']['c_water_total_hh']    = $currentYearData->c_water_total_hh;
            $data['Water']['c_water_total_amount']    = $currentYearData->c_water_total_amount;
            $data['Water']['d_water_zone_name']    = $currentYearData->d_water_zone_name;
            $data['Water']['d_water_total_hh']    = $currentYearData->d_water_total_hh;
            $data['Water']['d_water_total_amount']    = $currentYearData->d_water_total_amount;

            $data['Market']['a_market_zone_name']    = $currentYearData->a_market_zone_name;
            $data['Market']['a_market_total_hh']    = $currentYearData->a_market_total_hh;
            $data['Market']['a_market_total_amount']    = $currentYearData->a_market_total_amount;
            $data['Market']['b_market_zone_name']    = $currentYearData->b_market_zone_name;
            $data['Market']['b_market_total_hh']    = $currentYearData->b_market_total_hh;
            $data['Market']['b_market_total_amount']    = $currentYearData->b_market_total_amount;
            $data['Market']['c_market_zone_name']    = $currentYearData->c_market_zone_name;
            $data['Market']['c_market_total_hh']    = $currentYearData->c_market_total_hh;
            $data['Market']['c_market_total_amount']    = $currentYearData->c_market_total_amount;
            $data['Market']['d_market_zone_name']    = $currentYearData->d_market_zone_name;
            $data['Market']['d_market_total_hh']    = $currentYearData->d_market_total_hh;
            $data['Market']['d_market_total_amount']    = $currentYearData->d_market_total_amount;

            #property_new
            $data['Property']['a_zone_name']    = $currentYearData->a_zone_name;
            $data['Property']['a_prop_total_hh']    = $currentYearData->a_prop_total_hh;
            $data['Property']['a_prop_total_amount']    = $currentYearData->a_prop_total_amount;
            $data['Property']['b_zone_name']    = $currentYearData->b_zone_name;
            $data['Property']['b_prop_total_hh']    = $currentYearData->b_prop_total_hh;
            $data['Property']['b_prop_total_amount']    = $currentYearData->b_prop_total_amount;
            $data['Property']['c_zone_name']    = $currentYearData->c_zone_name;
            $data['Property']['c_prop_total_hh']    = $currentYearData->c_prop_total_hh;
            $data['Property']['c_prop_total_amount']    = $currentYearData->c_prop_total_amount;
            $data['Property']['d_zone_name']    = $currentYearData->d_zone_name;
            $data['Property']['d_prop_total_hh']    = $currentYearData->d_prop_total_hh;
            $data['Property']['d_prop_total_amount']    = $currentYearData->d_prop_total_amount;
            $data['Property']['prop_current_demand']    = round(($currentYearData->prop_current_demand ?? 0) / 10000000, 2);
            $data['Property']['prop_arrear_demand']    = round(($currentYearData->prop_arrear_demand ?? 0) / 10000000, 2);
            $data['Property']['prop_total_demand']    = round(($currentYearData->prop_total_demand ?? 0) / 10000000, 2);
            //$data['Property']['prop_current_collection']    = round(($currentYearData->prop_current_collection ?? 0) / 10000000, 2);
            //$data['Property']['prop_arrear_collection']    = round(($currentYearData->prop_arrear_collection ?? 0) / 10000000, 2);
            $data['Property']['prop_total_collection']    = round(($currentYearData->prop_total_collection ?? 0) / 10000000, 2);


            return responseMsgs(true, "Mpl Report", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    public function mplReportCollection(Request $request)
    {
        try {
            $request->merge(["metaData" => ["pr111.1", 1.1, null, $request->getMethod(), null,]]);
            $ulbId = $request->ulbId ?? 2;
            $currentDate = Carbon::now()->format("Y-m-d");
            $toDayCollection = PropTransaction::readConnection()->whereIn('status', [1, 2])->where("tran_date", $currentDate)->sum("amount");
            $data["toDayCollection"] = ($toDayCollection ? $toDayCollection : 0);
            return responseMsgs(true, "Mpl Report Today Coll", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    public function userWiseCollectionSummary(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "userId" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $user = Auth()->user();
            $ulbId = $user->ulb_id ?? 2;
            $perPage = $request->perPage ? $request->perPage : 10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;
            $limit = $perPage;
            $offset =  $request->page && $request->page > 0 ? (($request->page - 1) * $perPage) : 0;
            $wardId = $zoneId = $paymentMode = $userId = null;
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
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $sql = "
                SELECT prop_transactions.*,
                    users.id as user_id,
                    users.name,
                    users.mobile,
                    users.photo,
                    users.photo_relative_path
                FROM(
                    SELECT SUM(amount) as total_amount,
                        count(prop_transactions.id) as total_tran,
                        count(distinct prop_transactions.property_id) as total_property, 
                        prop_transactions.user_id                   
                    FROM prop_transactions 
                    JOIN prop_properties on prop_properties.id = prop_transactions.property_id                   
                    WHERE prop_transactions.status IN (1,2)
                        AND prop_transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                        " . ($zoneId ? " AND prop_properties.zone_mstr_id	 = $zoneId" : "") . "
                        " . ($userId ? " AND prop_transactions.user_id = $userId" : "") . "
                        " . ($paymentMode ? " AND upper(prop_transactions.payment_mode) = upper('$paymentMode')" : "") . "
                    GROUP BY prop_transactions.user_id
                    ORDER BY prop_transactions.user_id
                )prop_transactions
                JOIN users ON users.id = prop_transactions.user_id
            ";
            $data = $this->_DB_READ->select($sql . " limit $limit offset $offset");
            $count = (collect($this->_DB_READ->SELECT("SELECT COUNT(*)AS total, SUM(total_amount) AS total_amount,sum(total_tran) as total_tran
                                          FROM ($sql) total"))->first());
            $total = ($count)->total ?? 0;
            $sum = ($count)->total_amount ?? 0;
            $totalBillCut = ($count)->total_tran ?? 0;
            $lastPage = ceil($total / $perPage);
            $list = [
                "current_page" => $page,
                "data" => $data,
                "total" => $total,
                "total_sum" => $sum,
                "totalBillCut" => $totalBillCut,
                "per_page" => $perPage,
                "last_page" => $lastPage
            ];
            return responseMsgs(true, "", $list, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    public function deviceTypeCollection(Request $request)
    {
        try {
            $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
            $deviceType = 'android';
            $paymentMode = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->deviceType) {
                $deviceType = $request->deviceType;
            }
            if ($request->paymentMode) {
                $paymentMode = $request->paymentMode;
            }
            $data = PropTransaction::readConnection()->select(DB::raw("COALESCE(sum(amount),0) as total_amount,count(id)total_tran"))
                ->whereBetween("tran_date", [$fromDate, $uptoDate])
                ->whereIn("status", [1, 2])
                ->whereNotNull("device_type")
                ->where("device_type", $deviceType);
            if ($paymentMode) {
                $data->where(DB::raw("upper(payment_mode)", strtoupper($paymentMode)));
            }
            $data = $data->first();
            return responseMsgs(true, "data fetched", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Inserting Data in report table for live dashboard
     */
    public function liveDashboardUpdate2()
    {
        $todayDate = Carbon::now();
        $currentFy = getFY();
        $currentfyStartDate = $todayDate->startOfYear()->addMonths(3)->format("Y-m-d");
        $currentfyEndDate   = $todayDate->startOfYear()->addYears(1)->addMonths(3)->addDay(-1)->format("Y-m-d");
        echo $currentFy;
        $sql = "SELECT 
                        total_assessment.*, 
                        applied_safs.*, 
                        applied_industries_safs.*, 
                        applied_gb_safs.*, 
                        total_props.*, 
                        total_vacant_land.*, 
                        total_occupancy_props.*,
                        pnding_3yrs.*,
                        pnding_2yrs.*,
                        pnding_1yrs.*,
                        outstandings_last_yr.*,
                        outstanding_current_yr.*,
                        payments.*,
                        mutations.*,
                        top_wards_collections.*,
                        top_area_safs.*,
                        area_wise_defaulter.*,
                        payment_modes.*
        
                FROM 
                    (
                  SELECT 
                    COUNT(a.*) AS total_assessed_props 
                  FROM 
                    (
                      SELECT 
                        id 
                      FROM 
                        prop_active_safs 
                      WHERE 
                        status = 1 AND ulb_id=2						-- Parameterise This
                        AND application_date BETWEEN '$currentfyStartDate' 	-- Parameterise This
                        AND '$currentfyEndDate' 							-- Parameterise this
                      UNION ALL 
                      SELECT 
                        id 
                      FROM 
                        prop_safs 
                      WHERE 
                        status = 1  AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 	-- Parameterise this
                        AND '$currentfyEndDate' 								-- Parameterise this
                      UNION ALL 
                      SELECT 
                        id 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 	-- Parameterise this
                        AND '$currentfyEndDate'								-- Parameterise this
                    ) AS a
                ) AS total_assessment, 
                (
                  SELECT 
                    SUM(a.applied_comm_safs) AS applied_comm_safs, 
                    SUM(a.applied_res_safs) AS applied_res_safs 
                  FROM 
                    (
                      SELECT 
                        SUM(
                          CASE WHEN holding_type IN (
                            'PURE_COMMERCIAL', 'MIX_COMMERCIAL'
                          ) THEN 1 ELSE 0 END
                        ) AS applied_comm_safs, 
                        SUM(
                          CASE WHEN holding_type = 'PURE_RESIDENTIAL' THEN 1 ELSE 0 END
                        ) AS applied_res_safs 
                      FROM 
                        prop_active_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 			--Parameterise this
                        AND '$currentfyEndDate' 									--Parameterise this  get fy from curreent date and get date range from current date
                      UNION ALL 
                      SELECT 
                        SUM(
                          CASE WHEN holding_type IN (
                            'PURE_COMMERCIAL', 'MIX_COMMERCIAL'
                          ) THEN 1 ELSE 0 END
                        ) AS applied_comm_safs, 
                        SUM(
                          CASE WHEN holding_type = 'PURE_RESIDENTIAL' THEN 1 ELSE 0 END
                        ) AS applied_res_safs 
                      FROM 
                        prop_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 			--Parameterise this
                        AND '$currentfyEndDate' 									--Parameterise this
                      UNION ALL 
                      SELECT 
                        SUM(
                          CASE WHEN holding_type IN (
                            'PURE_COMMERCIAL', 'MIX_COMMERCIAL'
                          ) THEN 1 ELSE 0 END
                        ) AS applied_comm_safs, 
                        SUM(
                          CASE WHEN holding_type = 'PURE_RESIDENTIAL' THEN 1 ELSE 0 END
                        ) AS applied_res_safs 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 			--Parameterise this
                        AND '$currentfyEndDate'									--Parameterise this
                    ) AS a
                ) AS applied_safs, 
                (
                  SELECT 
                    SUM(a.count) AS applied_industries_safs 
                  FROM 
                    (
                      SELECT 
                        COUNT(DISTINCT s.id) 
                      FROM 
                        prop_active_safs s 
                        JOIN prop_active_safs_floors f ON f.saf_id = s.id 
                      WHERE 
                        f.usage_type_mstr_id = 6 						--Parameterise this
                        AND f.status = 1 
                        AND s.status = 1 
                        AND s.ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(DISTINCT s.id) 
                      FROM 
                        prop_safs s 
                        JOIN prop_safs_floors f ON f.saf_id = s.id 
                      WHERE 
                        f.usage_type_mstr_id = 6 						--Parameterise this
                        AND f.status = 1 
                        AND s.status = 1 
                        AND s.ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(DISTINCT s.id) 
                      FROM 
                        prop_rejected_safs s 
                        JOIN prop_rejected_safs_floors f ON f.saf_id = s.id 
                      WHERE 
                        f.usage_type_mstr_id = 6 						-- Parameterise this
                        AND f.status = 1 
                        AND s.status = 1
                        AND s.ulb_id=2
                    ) AS a
                ) AS applied_industries_safs, 
                (
                  SELECT 
                    SUM(a.id) AS applied_gb_safs 
                  FROM 
                    (
                      SELECT 
                        COUNT(id) AS id 
                      FROM 
                        prop_active_safs 
                      WHERE 
                        is_gb_saf = TRUE 
                        AND status = 1 AND ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(id) AS id 
                      FROM 
                        prop_safs 
                      WHERE 
                        is_gb_saf = TRUE 
                        AND status = 1 AND ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(id) AS id 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        is_gb_saf = TRUE 
                        AND status = 1 AND ulb_id=2
                    ) AS a
                ) AS applied_gb_safs, 
                (
                  SELECT 
                    COUNT(id) AS total_props 
                  FROM 
                    prop_properties 
                  WHERE 
                    status = 1 AND ulb_id=2
                ) AS total_props, 
                (
                  SELECT 
                    COUNT(id) AS total_vacant_land 
                  FROM 
                    prop_properties p 
                  WHERE 
                    p.prop_type_mstr_id = 4 
                    AND status = 1 AND ulb_id=2
                ) AS total_vacant_land, 
                (
                  SELECT 
                    SUM(
                      CASE WHEN nature = 'owned' THEN 1 ELSE 0 END
                    )+ d.total_owned_props AS total_owned_props, 
                    SUM(
                      CASE WHEN nature = 'rented' THEN 1 ELSE 0 END
                    ) AS total_owned_props, 
                    SUM(
                      CASE WHEN nature = 'mixed' THEN 1 ELSE 0 END
                    ) AS total_mixed_owned_props 
                  FROM 
                    (
                      SELECT 
                        a.*, 
                        CASE WHEN a.cnt = a.owned THEN 'owned' WHEN a.cnt = a.rented THEN 'rented' ELSE 'mixed' END AS nature 
                      FROM 
                        (
                          SELECT 
                            property_id, 
                            COUNT(prop_floors.id) AS cnt, 
                            SUM(
                              CASE WHEN occupancy_type_mstr_id = 1 THEN 1 ELSE 0 END
                            ) AS owned, 
                            SUM(
                              CASE WHEN occupancy_type_mstr_id = 2 THEN 1 ELSE 0 END
                            ) AS rented 
                          FROM 
                            prop_floors 
                          JOIN prop_properties ON prop_properties.id=prop_floors.property_id
                          WHERE prop_properties.status=1 AND prop_properties.ulb_id=2
                          GROUP BY 
                            property_id
                        ) AS a
                    ) AS b, 
                    (
                      SELECT 
                        COUNT(id) AS total_owned_props 
                      FROM 
                        prop_properties 
                      WHERE 
                        prop_type_mstr_id = 4 
                        AND status = 1 AND ulb_id=2
                    ) AS d 
                  GROUP BY 
                    d.total_owned_props
                ) AS total_occupancy_props,
                (		-- Pending Count From 3 Yrs
                    SELECT COUNT(DISTINCT p.id) AS pending_cnt_3yrs,
                             SUM(d.balance) AS amt_not_paid_3yrs	
                      FROM prop_demands d
                      JOIN prop_properties p ON p.id=d.property_id
                    WHERE d.paid_status=0 AND d.status=1 AND d.fyear>='2020-2021' AND p.ulb_id=2      -- Parameterise This
                    AND p.status=1
                ) AS pnding_3yrs,
                (      -- Pending From 2 Yrs
                    SELECT COUNT(DISTINCT p.id) AS pending_cnt_2yrs,
                       SUM(d.balance) AS amt_not_paid_2yrs
                    
                      FROM prop_demands d
                      JOIN prop_properties p ON p.id=d.property_id
                      WHERE d.paid_status=0 AND d.status=1 AND d.fyear>='2021-2022' AND p.ulb_id=2      -- Parameterise This
                    AND p.status=1
                ) AS pnding_2yrs,
                (      -- Pending From 1 Yrs
                    SELECT COUNT(DISTINCT p.id) AS pending_cnt_1yrs,
                       SUM(d.balance) AS amt_not_paid_1yrs
                    
                      FROM prop_demands d
                      JOIN prop_properties p ON p.id=d.property_id
                      WHERE d.paid_status=0 AND d.status=1 AND d.fyear>='2022-2023' AND p.ulb_id=2      -- Parameterise This
                    AND p.status=1
                ) AS pnding_1yrs,
                (		-- Outstanding Demand Last Yrs
                    SELECT outstandings.*,
                           payment_done.*
                          FROM 
                              (
                              SELECT SUM(d.balance) AS outstanding_amt_lastyear,
                                 COUNT(p.id) AS outstanding_cnt_lastyear
                            
                                 FROM prop_demands d
                                   JOIN prop_properties p ON p.id=d.property_id
                              WHERE d.fyear='2022-2023'							-- Parameterise This
                              AND d.status=1 AND d.paid_status=0 AND p.status=1	AND p.ulb_id=2      	-- Parameterise This
                          ) AS outstandings,
                          (
                              SELECT SUM(balance) AS recoverable_demand_lastyear
                                     FROM prop_demands
                              WHERE status=1 AND fyear='2022-2023' AND ulb_id=2						-- Parameterise This
                          ) AS payment_done
                ) AS outstandings_last_yr,
                (
                    -- Outstanding Demand Current Yr
                    SELECT outstandings.*,
                           payment_done.*
                          FROM 
                              (
                              SELECT SUM(d.balance) AS outstanding_amt_curryear,
                                 COUNT(p.id) AS outstanding_cnt_curryear
                            
                                 FROM prop_demands d
                                   JOIN prop_properties p ON p.id=d.property_id
                              WHERE d.fyear='$currentFy'
                              AND d.status=1 AND d.paid_status=0 AND p.status=1 AND p.ulb_id=2
                          ) AS outstandings,
                          (
                              SELECT SUM(balance) AS recoverable_demand_currentyr
                                     FROM prop_demands
                              WHERE status=1 AND fyear='$currentFy' AND ulb_id=2      					-- Parameterise This
                          ) AS payment_done
                ) AS outstanding_current_yr,
                (
                   -- Payment Details
                    SELECT SUM(payments.lastyr_pmt_amt) AS lastyr_pmt_amt,
                       SUM(payments.lastyr_pmt_cnt) AS lastyr_pmt_cnt,
                       SUM(payments.currentyr_pmt_amt) AS currentyr_pmt_amt,
                       SUM(payments.currentyr_pmt_cnt) AS currentyr_pmt_cnt
                          FROM 
                              (
                                  SELECT 
                                   CASE WHEN tran_date BETWEEN '2022-04-01' AND '2023-03-31' THEN SUM(amount) END AS lastyr_pmt_amt,      -- Parameterize this for last yr fyear range date	
                                   CASE WHEN tran_date BETWEEN '2022-04-01' AND '2023-03-31' THEN COUNT(id) END AS lastyr_pmt_cnt,		-- Parameterize this for last yr fyear range date
                            
                                   CASE WHEN tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate' THEN SUM(amount) END AS currentyr_pmt_amt,	-- Parameterize this for current yr fyear range date
                                   CASE WHEN tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate' THEN COUNT(id) END AS currentyr_pmt_cnt      -- Parameterize this for current yr fyear range date
                            
                              FROM prop_transactions
                              WHERE tran_date BETWEEN '2022-04-01' AND '$currentfyEndDate' AND status=1	AND ulb_id=2			-- Parameterize this for last two yrs fyear range date
                              GROUP BY tran_date
                          ) AS payments
                ) AS payments,
                (
                    -- Mutations
                     SELECT 

                             SUM(mutations.last_yr_mutation_count) AS last_yr_mutation_count,
                             SUM(mutations.current_yr_mutation_count) AS current_yr_mutation_count

                             FROM 
                                  (
                                
                                          SELECT 
                                
                                          COALESCE(CASE WHEN application_date BETWEEN '2022-04-01' AND '2023-03-31'		-- Parameterize this for last yr fyear range date
                                                   THEN count(id) END,0)AS last_yr_mutation_count,
                                          COALESCE(CASE WHEN application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'		-- Parameterize this for current fyear range date
                                                   THEN count(id) END,0) AS current_yr_mutation_count
                                
                                          FROM prop_active_safs
                                          WHERE assessment_type='Mutation' AND status=1
                                          AND application_date BETWEEN '2022-04-01' AND '$currentfyEndDate' AND ulb_id=2
                                      GROUP BY application_date
                                
                                      UNION ALL 
                                
                                      SELECT 
                                
                                          COALESCE(CASE WHEN application_date BETWEEN '2022-04-01' AND '2023-03-31'         -- Parameterize this for last yr fyear range date
                                                  THEN count(id) END,0)AS last_yr_mutation_count,
                                          COALESCE(CASE WHEN application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'		   -- Parameterize this for current fyear range date
                                                   THEN count(id) END,0) AS current_yr_mutation_count
                                
                                          FROM prop_safs
                                          WHERE assessment_type='Mutation' AND status=1
                                          AND application_date BETWEEN '2022-04-01' AND '$currentfyEndDate' AND ulb_id=2
                                      GROUP BY application_date
                                
                                      UNION ALL
                                
                                      SELECT 
                                
                                          COALESCE(CASE WHEN application_date BETWEEN '2022-04-01' AND '2023-03-31'		-- Parameterize this for last yr fyear range date
                                                   THEN count(id) END,0)AS last_yr_mutation_count,
                                          COALESCE(CASE WHEN application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'		-- Parameterize this for current fyear range date
                                                   THEN count(id) END,0) AS current_yr_mutation_count
                                
                                          FROM prop_rejected_safs
                                          WHERE assessment_type='Mutation' AND status=1
                                          AND application_date BETWEEN '2022-04-01' AND '$currentfyEndDate' AND ulb_id=2			-- Parameterize this for last two fyear range date
                                      GROUP BY application_date
                              )  AS mutations
                ) AS mutations,
                (
                   -- Top Areas Property Transactions
                    SELECT (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[1] AS top_transaction_first_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[2] AS top_transaction_sec_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[3] AS top_transaction_third_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[4] AS top_transaction_forth_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[5] AS top_transaction_fifth_ward_no,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[1] AS top_transaction_first_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[2] AS top_transaction_sec_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[3] AS top_transaction_third_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[4] AS top_transaction_forth_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[5] AS top_transaction_fifth_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[1] AS top_transaction_first_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[2] AS top_transaction_sec_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[3] AS top_transaction_third_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[4] AS top_transaction_forth_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[5] AS top_transaction_fifth_ward_amt
                
                          FROM (
                              SELECT 
                                      p.ward_mstr_id,
                                      SUM(t.amount) AS collected_amt,
                                      COUNT(t.id) AS collection_count,
                                      u.ward_name
                        
                                  FROM prop_transactions t
                                  JOIN prop_properties p ON p.id=t.property_id
                                  JOIN ulb_ward_masters u ON u.id=p.ward_mstr_id
                                  WHERE t.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'							-- Parameterize this for current fyear range date
                                  AND p.ulb_id=2
                                  GROUP BY p.ward_mstr_id,u.ward_name
                                  ORDER BY collection_count DESC 
                              LIMIT 5
                          ) AS top_wards_collections
                ) AS top_wards_collections,
                (
                   -- Top Area Safs
                    SELECT 
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[1] AS top_saf_first_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[2] AS top_saf_sec_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[3] AS top_saf_third_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[4] AS top_saf_forth_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[5] AS top_saf_fifth_ward_no,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[1] AS top_saf_first_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[2] AS top_saf_sec_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[3] AS top_saf_third_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[4] AS top_saf_forth_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[5] AS top_saf_fifth_ward_count

                      FROM 

                              (
                                  SELECT 
                                      top_areas_safs.ward_mstr_id,
                                      SUM(top_areas_safs.application_count) AS application_count,
                                      u.ward_name 
                            
                                      FROM 

                                           (
                                               SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_active_safs
                                              WHERE application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'       -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id

                                                  UNION ALL 


                                          SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_safs
                                              WHERE application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'     -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id

                                                  UNION ALL 

                                          SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_rejected_safs
                                              WHERE application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'   -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id
                                           ) AS top_areas_safs

                                          JOIN ulb_ward_masters u ON u.id=top_areas_safs.ward_mstr_id
                                          GROUP BY top_areas_safs.ward_mstr_id,u.ward_name 
                                          ORDER BY application_count DESC 
                                          LIMIT 5
                              ) AS top_area_safs
                ) AS top_area_safs,
                (
                 -- AreaWise Defaulters
                    SELECT 
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[1] AS defaulter_first_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[2] AS defaulter_sec_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[3] AS defaulter_third_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[4] AS defaulter_forth_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[5] AS defaulter_fifth_ward_no,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[1] AS defaulter_first_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[2] AS defaulter_sec_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[3] AS defaulter_third_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[4] AS defaulter_forth_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[5] AS defaulter_fifth_ward_prop_cnt,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[1] AS defaulter_first_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[2] AS defaulter_sec_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[3] AS defaulter_third_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[4] AS defaulter_forth_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[5] AS defaulter_fifth_unpaid_amount

                      FROM 
                          (
                              SELECT 
                                  COUNT(a.property_id) AS defaulter_property_cnt,
                                  w.ward_name,
                                  SUM(a.unpaid_amt) AS unpaid_amount

                                  FROM 
                                      (
                                          SELECT
                                               property_id,
                                               COUNT(id) AS demand_cnt,
                                               SUM(CASE WHEN paid_status=1 THEN 1 ELSE 0 END) AS paid_count,
                                               SUM(CASE WHEN paid_status=0 THEN 1 ELSE 0 END) AS unpaid_count,
                                               SUM(CASE WHEN paid_status=0 THEN balance ELSE 0 END) AS unpaid_amt

                                          FROM prop_demands
                                          WHERE fyear='$currentFy'								-- Parameterize this for current fyear range date
                                          AND status=1 AND ulb_id=2
                                          GROUP BY property_id
                                          ORDER BY property_id
                                  ) a 
                                  JOIN prop_properties p ON p.id=a.property_id
                                  JOIN ulb_ward_masters w ON w.id=p.ward_mstr_id
                                    
                                  WHERE a.demand_cnt=a.unpaid_count
                                  AND p.status=1
                                    
                                  GROUP BY w.ward_name 
                                    
                                  ORDER BY defaulter_property_cnt DESC 
                                  LIMIT 5
                          ) a 
                ) AS area_wise_defaulter,
                (	
                  -- Online Payments	
                  WITH 
                     current_payments AS (
                          SELECT 

                                   SUM(CASE WHEN UPPER(payment_mode)='CASH' THEN amount ELSE 0 END) AS current_cash_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='CHEQUE' THEN amount ELSE 0 END) AS current_cheque_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='DD' THEN amount ELSE 0 END) AS current_dd_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='CARD PAYMENT' THEN amount ELSE 0 END) AS current_card_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='NEFT' THEN amount ELSE 0 END) AS current_neft_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='RTGS' THEN amount ELSE 0 END) AS current_rtgs_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN amount ELSE 0 END) AS current_online_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN 1 ELSE 0 END) AS current_online_counts

                           FROM prop_transactions
                           WHERE tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'					-- Parameterize this for current fyear range date
                           AND status=1 AND ulb_id=2
                       ),
                       lastyear_payments AS (
                           SELECT 

                                   SUM(CASE WHEN UPPER(payment_mode)='CASH' THEN amount ELSE 0 END) AS lastyear_cash_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='CHEQUE' THEN amount ELSE 0 END) AS lastyear_cheque_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='DD' THEN amount ELSE 0 END) AS lastyear_dd_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='NEFT' THEN amount ELSE 0 END) AS lastyear_neft_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN amount ELSE 0 END) AS lastyear_online_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN 1 ELSE 0 END) AS current_online_counts

                           FROM prop_transactions
                           WHERE tran_date BETWEEN '2022-04-01' AND '2023-03-31'				-- Parameterize this for Past fyear range date
                           AND status=1 AND ulb_id=2
                       ),
                    jsk_collections AS (													    -- Jsk Collections

                           SELECT 
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '2022-04-01' AND '2023-03-31') THEN t.amount ELSE 0 END),0) AS prev_year_jskcollection,    -- Parameterize this for Past fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '2022-04-01' AND '2023-03-31') THEN 1 ELSE 0 END),0) AS prev_year_jskcount,                -- Parameterize this for Past fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate') THEN t.amount ELSE 0 END),0) AS current_year_jskcollection, -- Parameterize this for current fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate') THEN 1 ELSE 0 END),0) AS current_year_jskcount			  -- Parameterize this for current fyear range date

                          FROM prop_transactions t
                          JOIN users u ON u.id=t.user_id   
                          WHERE UPPER(u.user_type)='JSK' AND u.suspended=false  AND t.status=1
                          AND t.tran_date BETWEEN '2022-04-01' AND '$currentfyEndDate'  AND t.ulb_id=2	-- Parameterize this for last two fyears
                  )

                       SELECT * FROM current_payments,lastyear_payments,jsk_collections
                ) AS payment_modes";
        $data = $this->_DB_READ->select($sql);
        return  $data = $data[0];
        $mMplYearlyReport = new MplYearlyReport();
        $currentFy = getFY();

        $updateReqs = [
            "total_assessment" => $data->total_assessed_props,
            "total_assessed_residential" => $data->applied_res_safs,
            "total_assessed_commercial" => $data->applied_comm_safs,
            "total_assessed_industrial" => $data->applied_industries_safs,
            "total_assessed_gbsaf" => $data->applied_gb_safs,
            "total_prop_vacand" => $data->total_vacant_land,
            "total_prop_residential" => $data->total_owned_props,
            "total_prop_mixe" => $data->total_mixed_owned_props,

            "total_property" => $data->total_props,
            "vacant_property" => $data->total_vacant_land,
            "owned_property" => $data->total_owned_props,
            "count_not_paid_3yrs" => $data->pending_cnt_3yrs,
            "amount_not_paid_3yrs" => $data->amt_not_paid_3yrs,
            "count_not_paid_2yrs" => $data->pending_cnt_2yrs,
            "amount_not_paid_2yrs" => $data->amt_not_paid_2yrs,
            "count_not_paid_1yrs" => $data->pending_cnt_1yrs,
            "amount_not_paid_1yrs" => $data->amt_not_paid_1yrs,
            // "assessed_property_this_year_achievement" => $data->outstanding_amt_lastyear,
            // "assessed_property_this_year_achievement" => $data->outstanding_cnt_lastyear,
            // "assessed_property_this_year_achievement" => $data->recoverable_demand_lastyear,
            "demand_outstanding_this_year" => $data->outstanding_amt_curryear,
            "demand_outstanding_from_this_year_prop_count" => $data->outstanding_cnt_curryear,
            "demand_outstanding_coll_this_year" => $data->recoverable_demand_currentyr,

            "last_year_payment_amount" => $data->lastyr_pmt_amt,
            "last_year_payment_count" => $data->lastyr_pmt_cnt,
            "this_year_payment_count" => $data->currentyr_pmt_cnt,
            "this_year_payment_amount" => $data->currentyr_pmt_amt,
            "mutation_this_year_count" => $data->current_yr_mutation_count,
            // "assessed_property_this_year_achievement" => $data->last_yr_mutation_count,

            // "assessed_property_this_year_achievement" => $data->top_transaction_first_ward_no,
            // "assessed_property_this_year_achievement" => $data->top_transaction_sec_ward_no,
            // "assessed_property_this_year_achievement" => $data->top_transaction_third_ward_no,
            // "assessed_property_this_year_achievement" => $data->top_transaction_forth_ward_no,
            // "assessed_property_this_year_achievement" => $data->top_transaction_fifth_ward_no,
            "top_area_property_transaction_ward1_count" => $data->top_transaction_first_ward_count,
            "top_area_property_transaction_ward2_count" => $data->top_transaction_sec_ward_count,
            "top_area_property_transaction_ward3_count" => $data->top_transaction_third_ward_count,
            "top_area_property_transaction_ward4_count" => $data->top_transaction_forth_ward_count,
            "top_area_property_transaction_ward5_count" => $data->top_transaction_fifth_ward_count,
            // "assessed_property_this_year_achievement" => $data->top_transaction_first_ward_amt,
            // "assessed_property_this_year_achievement" => $data->top_transaction_sec_ward_amt,
            // "assessed_property_this_year_achievement" => $data->top_transaction_third_ward_amt,
            // "assessed_property_this_year_achievement" => $data->top_transaction_forth_ward_amt,
            // "assessed_property_this_year_achievement" => $data->top_transaction_fifth_ward_amt,

            "top_area_saf_ward1_name" => $data->top_saf_first_ward_no,
            "top_area_saf_ward2_name" => $data->top_saf_sec_ward_no,
            "top_area_saf_ward3_name" => $data->top_saf_third_ward_no,
            "top_area_saf_ward4_name" => $data->top_saf_forth_ward_no,
            "top_area_saf_ward5_name" => $data->top_saf_fifth_ward_no,
            "top_area_saf_ward1_count" => $data->top_saf_first_ward_count,
            "top_area_saf_ward2_count" => $data->top_saf_sec_ward_count,
            "top_area_saf_ward3_count" => $data->top_saf_third_ward_count,
            "top_area_saf_ward4_count" => $data->top_saf_forth_ward_count,
            "top_area_saf_ward5_count" => $data->top_saf_fifth_ward_count,

            "top_defaulter_ward1_name" => $data->defaulter_first_ward_no,
            "top_defaulter_ward2_name" => $data->defaulter_sec_ward_no,
            "top_defaulter_ward3_name" => $data->defaulter_third_ward_no,
            "top_defaulter_ward4_name" => $data->defaulter_forth_ward_no,
            "top_defaulter_ward5_name" => $data->defaulter_fifth_ward_no,
            "top_defaulter_ward1_count" => $data->defaulter_first_ward_prop_cnt,
            "top_defaulter_ward2_count" => $data->defaulter_sec_ward_prop_cnt,
            "top_defaulter_ward3_count" => $data->defaulter_third_ward_prop_cnt,
            "top_defaulter_ward4_count" => $data->defaulter_forth_ward_prop_cnt,
            "top_defaulter_ward5_count" => $data->defaulter_fifth_ward_prop_cnt,
            "top_defaulter_ward1_amount" => $data->defaulter_first_unpaid_amount,
            "top_defaulter_ward2_amount" => $data->defaulter_sec_unpaid_amount,
            "top_defaulter_ward3_amount" => $data->defaulter_third_unpaid_amount,
            "top_defaulter_ward4_amount" => $data->defaulter_forth_unpaid_amount,
            "top_defaulter_ward5_amount" => $data->defaulter_fifth_unpaid_amount,

            "current_year_cash_collection" => $data->current_cash_payment,
            "current_year_cheque_collection" => $data->current_cheque_payment,
            "current_year_dd_collection"   => $data->current_dd_payment,
            "current_year_card_collection" => $data->current_card_payment,
            "current_year_neft_collection" => $data->current_neft_payment,
            "current_year_rtgs_collection" => $data->current_rtgs_payment,
            // "assessed_property_this_year_achievement" => $data->current_online_payment,
            // "assessed_property_this_year_achievement" => $data->current_online_counts,
            // "assessed_property_this_year_achievement" => $data->prev_year_jskcollection,
            // "assessed_property_this_year_achievement" => $data->prev_year_jskcount,
            // "assessed_property_this_year_achievement" => $data->current_year_jskcollection,
            // "assessed_property_this_year_achievement" => $data->current_year_jskcount,
            // "assessed_property_this_year_achievement" => $data->lastyear_cash_payment,
            // "assessed_property_this_year_achievement" => $data->lastyear_cheque_payment,
            // "assessed_property_this_year_achievement" => $data->lastyear_dd_payment,
            // "assessed_property_this_year_achievement" => $data->lastyear_neft_payment,
            // "assessed_property_this_year_achievement" => $data->lastyear_online_payment,
            // "date" => $todayDate,
            // "fyear" => "$currentFy",
            // "ulb_id" => "2",
            // "ulb_name" => "Akola Municipal Corporation",
        ];

        $mMplYearlyReport->where('fyear', $currentFy)
            ->update($updateReqs);

        // $updateReqs->push(["fyear" => "$currentFy"]);
        // $mMplYearlyReport->create($updateReqs);

        dd("ok");
    }

    public function liveDashboardUpdate()
    {
        $todayDate = Carbon::now();
        $currentFy = getFY();

        list($currentFyearFrom, $currentFyearEnd) = explode('-', getFY());
        $currentfyStartDate = $currentFyearFrom . "-04-01";
        $currentfyEndDate = $currentFyearEnd . "-03-31";
        $privfyStartDate = ($currentFyearFrom - 1) . "-04-01";
        $privfyEndDate = ($currentFyearEnd - 1) . "-03-31";

        $sql = "SELECT 
                        total_assessment.*, 
                        applied_safs.*, 
                        applied_industries_safs.*, 
                        applied_gb_safs.*, 
                        total_props.*, 
                        total_occupancy_props.*,
                        property_use_type.*,
                        payments.*,
                        top_wards_collections.*,
                        top_area_safs.*,
                        area_wise_defaulter.*,
                        payment_modes.*,
                        dcb_collection.*,
                        member_count.*
        
                FROM 
                    (
                  SELECT 
                    COUNT(a.*) AS total_assessed_props 
                  FROM 
                    (
                      SELECT 
                        id 
                      FROM 
                        prop_active_safs 
                      WHERE 
                        status = 1 AND ulb_id=2						-- Parameterise This
                        AND application_date BETWEEN '$currentfyStartDate' 	-- Parameterise This
                        AND '$currentfyEndDate' 							-- Parameterise this
                      UNION ALL 
                      SELECT 
                        id 
                      FROM 
                        prop_safs 
                      WHERE 
                        status = 1  AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 	-- Parameterise this
                        AND '$currentfyEndDate' 								-- Parameterise this
                      UNION ALL 
                      SELECT 
                        id 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 	-- Parameterise this
                        AND '$currentfyEndDate'								-- Parameterise this
                    ) AS a
                ) AS total_assessment, 
                (
                    select 
                                
                                count(case when  wf_roles.id = 8 then users.id end)as tc_count,
                                count(case when  wf_roles.id = 11 then users.id end)as da_count,
                                count(case when  wf_roles.id = 6 then users.id end)as si_count,
                                count(case when  wf_roles.id = 5 then users.id end)as eo_count,
                                count(case when  wf_roles.id = 7 then users.id end)as jsk_count,
                                count(case when  wf_roles.id = 9 then users.id end)as utc_count,
                                count(case when  wf_roles.id = 10 then users.id end)as sh_count
                                
                            from ulb_masters
                            left join users on users.ulb_id = ulb_masters.id and users.suspended = false
                            left join wf_roleusermaps on wf_roleusermaps.user_id = users.id and wf_roleusermaps.is_suspended = false
                            left join wf_roles on wf_roles.id = wf_roleusermaps.wf_role_id and wf_roles.is_suspended = false
                            where ulb_id = 2
                )as member_count,
                (
                  SELECT 
                    SUM(a.applied_comm_safs) AS applied_comm_safs, 
                    SUM(a.applied_res_safs) AS applied_res_safs 
                  FROM 
                    (
                      SELECT 
                        SUM(
                          CASE WHEN holding_type IN (
                            'PURE_COMMERCIAL', 'MIX_COMMERCIAL'
                          ) THEN 1 ELSE 0 END
                        ) AS applied_comm_safs, 
                        SUM(
                          CASE WHEN holding_type = 'PURE_RESIDENTIAL' THEN 1 ELSE 0 END
                        ) AS applied_res_safs 
                      FROM 
                        prop_active_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 			--Parameterise this
                        AND '$currentfyEndDate' 									--Parameterise this  get fy from curreent date and get date range from current date
                      UNION ALL 
                      SELECT 
                        SUM(
                          CASE WHEN holding_type IN (
                            'PURE_COMMERCIAL', 'MIX_COMMERCIAL'
                          ) THEN 1 ELSE 0 END
                        ) AS applied_comm_safs, 
                        SUM(
                          CASE WHEN holding_type = 'PURE_RESIDENTIAL' THEN 1 ELSE 0 END
                        ) AS applied_res_safs 
                      FROM 
                        prop_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 			--Parameterise this
                        AND '$currentfyEndDate' 									--Parameterise this
                      UNION ALL 
                      SELECT 
                        SUM(
                          CASE WHEN holding_type IN (
                            'PURE_COMMERCIAL', 'MIX_COMMERCIAL'
                          ) THEN 1 ELSE 0 END
                        ) AS applied_comm_safs, 
                        SUM(
                          CASE WHEN holding_type = 'PURE_RESIDENTIAL' THEN 1 ELSE 0 END
                        ) AS applied_res_safs 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '$currentfyStartDate' 			--Parameterise this
                        AND '$currentfyEndDate'									--Parameterise this
                    ) AS a
                ) AS applied_safs, 
                (
                  SELECT 
                    SUM(a.count) AS applied_industries_safs 
                  FROM 
                    (
                      SELECT 
                        COUNT(DISTINCT s.id) 
                      FROM 
                        prop_active_safs s 
                        JOIN prop_active_safs_floors f ON f.saf_id = s.id 
                      WHERE 
                        f.usage_type_mstr_id = 6 						--Parameterise this
                        AND f.status = 1 
                        AND s.status = 1 
                        AND s.ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(DISTINCT s.id) 
                      FROM 
                        prop_safs s 
                        JOIN prop_safs_floors f ON f.saf_id = s.id 
                      WHERE 
                        f.usage_type_mstr_id = 6 						--Parameterise this
                        AND f.status = 1 
                        AND s.status = 1 
                        AND s.ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(DISTINCT s.id) 
                      FROM 
                        prop_rejected_safs s 
                        JOIN prop_rejected_safs_floors f ON f.saf_id = s.id 
                      WHERE 
                        f.usage_type_mstr_id = 6 						-- Parameterise this
                        AND f.status = 1 
                        AND s.status = 1
                        AND s.ulb_id=2
                    ) AS a
                ) AS applied_industries_safs, 
                (
                  SELECT 
                    SUM(a.id) AS applied_gb_safs 
                  FROM 
                    (
                      SELECT 
                        COUNT(id) AS id 
                      FROM 
                        prop_active_safs 
                      WHERE 
                        is_gb_saf = TRUE 
                        AND status = 1 AND ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(id) AS id 
                      FROM 
                        prop_safs 
                      WHERE 
                        is_gb_saf = TRUE 
                        AND status = 1 AND ulb_id=2
                      UNION ALL 
                      SELECT 
                        COUNT(id) AS id 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        is_gb_saf = TRUE 
                        AND status = 1 AND ulb_id=2
                    ) AS a
                ) AS applied_gb_safs, 
                (
                  SELECT 
                    COUNT(id) AS total_props 
                  FROM 
                    prop_properties 
                  WHERE 
                    status = 1 AND ulb_id=2
                ) AS total_props, 

                (
                    SELECT  
                                    count(prop_transactions.id) as total_tran,
                                    count(distinct prop_transactions.property_id) as total_hh,
                                    COALESCE(sum(prop_transactions.amount),0)as total_tran_amount,
                                    COALESCE(sum(prop_advances.advance_amount),0)as advance_amount,
                                    COALESCE(sum(prop_adjustments.adjust_amount),0)as adjust_amount,
                                    sum(penalty_rebate.penalty) as penalty,
                                    sum(penalty_rebate.rebate) as rebate,
                                    (COALESCE(sum(dtls.arrear_collection),0) 
                                     + COALESCE(sum(penalty_rebate.penalty),0) 
                                    - COALESCE(sum(adjust_amount),0) ) as arrear_collection,
                                    (COALESCE(sum(dtls.arrear_hh),0) ) as arrear_hh,
                                    (COALESCE(sum(dtls.current_hh),0) ) as current_hh,
                                    ((COALESCE(sum(dtls.current_collection),0)+COALESCE(sum(prop_advances.advance_amount),0)) -  COALESCE(sum(penalty_rebate.rebate),0))as current_collection
                                FROM prop_transactions
                                LEFT JOIN (
                                    SELECT 
                                        prop_transactions.id,
                                        count(distinct prop_transactions.property_id) as total_prop,
                                        sum(CASE WHEN prop_demands.fyear < '$currentFy' THEN prop_tran_dtls.paid_total_tax ELSE 0 END) as arrear_collection,
                                        count(distinct CASE WHEN prop_demands.fyear < '$currentFy' THEN prop_demands.property_id ELSE null END) as arrear_hh,
                                        sum(CASE WHEN prop_demands.fyear = '$currentFy' THEN prop_tran_dtls.paid_total_tax ELSE 0 END) as current_collection,
                                        count(distinct CASE WHEN prop_demands.fyear = '$currentFy' THEN prop_demands.property_id ELSE null END) as current_hh,
                                        sum(prop_tran_dtls.paid_total_tax) as paid_total_tax 
                                    FROM prop_transactions
                                    JOIN prop_tran_dtls ON prop_tran_dtls.tran_id = prop_transactions.id
                                    JOIN prop_demands ON prop_demands.id = prop_tran_dtls.prop_demand_id
                                    WHERE prop_transactions.status IN (1,2) 
                                        AND prop_transactions.tran_type = 'Property'
                                        and prop_transactions.tran_date  between '$currentfyStartDate' and '$currentfyEndDate'
                                      
                
                                    GROUP BY  prop_transactions.id			
                                ) dtls ON dtls.id = prop_transactions.id
                                LEFT JOIN (
                                    SELECT 
                                        tran_id,
                                        sum(CASE WHEN is_rebate != true THEN prop_penaltyrebates.amount ELSE 0 END) as penalty,
                                        sum(CASE WHEN is_rebate = true THEN prop_penaltyrebates.amount ELSE 0 END) as rebate
                                    FROM prop_penaltyrebates
                                    JOIN prop_transactions ON prop_transactions.id = prop_penaltyrebates.tran_id
                                    WHERE prop_transactions.status IN (1,2) 
                                        AND prop_transactions.tran_type =  'Property'
                                        and prop_transactions.tran_date between '$currentfyStartDate' and '$currentfyEndDate'
                                        
                
                                    GROUP BY prop_penaltyrebates.tran_id
                                ) penalty_rebate ON penalty_rebate.tran_id = prop_transactions.id
                                left join(
                                    select tran_id, sum(prop_advances.amount)as advance_amount
                                    from prop_advances
                                    JOIN prop_transactions ON prop_transactions.id = prop_advances.tran_id
                                    WHERE prop_transactions.status IN (1,2)
                                        and prop_transactions.tran_date between '$currentfyStartDate' and '$currentfyEndDate'
                                     
                
                                    GROUP BY prop_advances.tran_id
                                )prop_advances ON prop_advances.tran_id = prop_transactions.id
                                left join(
                                    select tran_id, sum(prop_adjustments.amount)as adjust_amount
                                    from prop_adjustments
                                    JOIN prop_transactions ON prop_transactions.id = prop_adjustments.tran_id
                                    WHERE prop_transactions.status IN (1,2)
                                        and prop_transactions.tran_date  between '$currentfyStartDate' and '$currentfyEndDate'
                                        
                
                                    GROUP BY prop_adjustments.tran_id
                                )prop_adjustments ON prop_adjustments.tran_id = prop_transactions.id
                                WHERE prop_transactions.status IN (1,2)
                                    and prop_transactions.tran_date  between '$currentfyStartDate' and '$currentfyEndDate'
                ) as dcb_collection,

                (
                    SELECT 
                    SUM(
                      CASE WHEN nature = 'owned' THEN 1 ELSE 0 END
                    ) AS total_owned_props, 
                    SUM(
                      CASE WHEN nature = 'rented' THEN 1 ELSE 0 END
                    ) AS total_rented_props, 
                    SUM(
                      CASE WHEN nature = 'mixed' THEN 1 ELSE 0 END
                    ) AS total_mixed_owned_props 
                  FROM 
                    (
                      SELECT 
                        a.*, 
                        CASE WHEN a.cnt = a.owned THEN 'owned' WHEN a.cnt = a.rented THEN 'rented' ELSE 'mixed' END AS nature 
                      FROM 
                        (
                          SELECT 
                            property_id, 
                            COUNT(prop_floors.id) AS cnt, 
                            SUM(
                              CASE WHEN occupancy_type_mstr_id = 1 THEN 1 ELSE 0 END
                            ) AS owned, 
                            SUM(
                              CASE WHEN occupancy_type_mstr_id = 2 THEN 1 ELSE 0 END
                            ) AS rented 
                          FROM 
                            prop_floors 
                          JOIN prop_properties ON prop_properties.id=prop_floors.property_id
                          AND prop_properties.prop_type_mstr_id<>4 AND prop_type_mstr_id IS NOT null
                          WHERE prop_properties.status=1 AND prop_properties.ulb_id=2
                          GROUP BY 
                            property_id
                        ) AS a
                    ) AS b
                ) AS total_occupancy_props,
                (
                    SELECT 
                        SUM(
                        CASE WHEN nature = 'residential' THEN 1 ELSE 0 END
                        ) AS total_residential_props, 
                        SUM(
                        CASE WHEN nature = 'commercial' THEN 1 ELSE 0 END
                        ) AS total_commercial_props,
                        SUM(
                        CASE WHEN nature = 'govt' THEN 1 ELSE 0 END
                        ) AS total_govt_props ,
                        SUM(
                        CASE WHEN nature = 'industrial' THEN 1 ELSE 0 END
                        ) AS total_industrial_props ,
                        SUM(
                        CASE WHEN nature = 'religious' THEN 1 ELSE 0 END
                        ) AS total_religious_props ,
                        SUM(
                        CASE WHEN nature = 'mixed_commercial' THEN 1 ELSE 0 END
                        ) AS total_mixed_commercial_props 
                    FROM 
                        (
                                SELECT 
                                    a.*, 
                                    CASE WHEN a.cnt = a.residential THEN 'residential' WHEN a.cnt = a.commercial THEN 'commercial' 
                                            WHEN a.cnt = a.govt THEN 'govt' WHEN a.cnt = a.industrial THEN 'industrial' WHEN a.cnt = a.religious THEN 'religious'
                                        ELSE 'mixed_commercial' END AS nature 
                                FROM 
                                    (
                                            SELECT 
                                                property_id, 
                                                COUNT(prop_floors.id) AS cnt, 
                                                SUM(
                                                CASE WHEN usage_type_mstr_id in (45) THEN 1 ELSE 0 END
                                                ) AS residential, 
                                                SUM(
                                                CASE WHEN usage_type_mstr_id IN (3,4,5,9,12,16,19,20,34,36,37,38,39,42,43,46,47,48,49,50,51,52) THEN 1 ELSE 0 END
                                                ) AS commercial ,

                                                SUM(
                                                CASE WHEN usage_type_mstr_id in (53,54,55,56,57,40,17) THEN 1 ELSE 0 END
                                                ) AS govt ,
                                                SUM(
                                                CASE WHEN usage_type_mstr_id in (35,6) THEN 1 ELSE 0 END
                                                ) AS industrial,
                                                SUM(
                                                CASE WHEN usage_type_mstr_id = 44 THEN 1 ELSE 0 END
                                                ) AS religious
                                        
                                            FROM 
                                                prop_floors 
                                            JOIN prop_properties ON prop_properties.id=prop_floors.property_id
                                            WHERE prop_properties.status=1 AND prop_properties.ulb_id=2 and prop_type_mstr_id <> 4 AND prop_type_mstr_id is not null
                                            GROUP BY 
                                                property_id
                                    ) AS a
                        ) AS b
                ) as property_use_type,
                (
                   -- Payment Details
                    SELECT SUM(payments.lastyr_pmt_amt) AS lastyr_pmt_amt,
                       SUM(payments.lastyr_pmt_cnt) AS lastyr_pmt_cnt,
                       SUM(payments.currentyr_pmt_amt) AS currentyr_pmt_amt,
                       SUM(payments.currentyr_pmt_cnt) AS currentyr_pmt_cnt
                          FROM 
                              (
                                  SELECT 
                                   CASE WHEN tran_date BETWEEN '$privfyStartDate' AND '$privfyEndDate' THEN SUM(amount) END AS lastyr_pmt_amt,      -- Parameterize this for last yr fyear range date	
                                   CASE WHEN tran_date BETWEEN '$privfyStartDate' AND '$privfyEndDate' THEN COUNT(id) END AS lastyr_pmt_cnt,		-- Parameterize this for last yr fyear range date
                            
                                   CASE WHEN tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate' THEN SUM(amount) END AS currentyr_pmt_amt,	-- Parameterize this for current yr fyear range date
                                   CASE WHEN tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate' THEN COUNT(id) END AS currentyr_pmt_cnt      -- Parameterize this for current yr fyear range date
                            
                              FROM prop_transactions
                              WHERE tran_date BETWEEN '$privfyStartDate' AND '$currentfyEndDate' AND status=1	AND ulb_id=2			-- Parameterize this for last two yrs fyear range date
                              GROUP BY tran_date
                          ) AS payments
                ) AS payments,
                (
                   -- Top Areas Property Transactions
                    SELECT (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[1] AS top_transaction_first_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[2] AS top_transaction_sec_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[3] AS top_transaction_third_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[4] AS top_transaction_forth_ward_no,
                           (string_to_array(string_agg(top_wards_collections.ward_name::TEXT,','),','))[5] AS top_transaction_fifth_ward_no,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[1] AS top_transaction_first_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[2] AS top_transaction_sec_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[3] AS top_transaction_third_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[4] AS top_transaction_forth_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collection_count::TEXT,','),','))[5] AS top_transaction_fifth_ward_count,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[1] AS top_transaction_first_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[2] AS top_transaction_sec_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[3] AS top_transaction_third_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[4] AS top_transaction_forth_ward_amt,
                           (string_to_array(string_agg(top_wards_collections.collected_amt::TEXT,','),','))[5] AS top_transaction_fifth_ward_amt
                
                          FROM (
                              SELECT 
                                      p.ward_mstr_id,
                                      SUM(t.amount) AS collected_amt,
                                      COUNT(t.id) AS collection_count,
                                      u.ward_name
                        
                                  FROM prop_transactions t
                                  JOIN prop_properties p ON p.id=t.property_id
                                  JOIN ulb_ward_masters u ON u.id=p.ward_mstr_id
                                  WHERE t.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'							-- Parameterize this for current fyear range date
                                  AND p.ulb_id=2
                                  GROUP BY p.ward_mstr_id,u.ward_name
                                  ORDER BY collection_count DESC 
                              LIMIT 5
                          ) AS top_wards_collections
                ) AS top_wards_collections,
                (
                   -- Top Area Safs
                    SELECT 
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[1] AS top_saf_first_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[2] AS top_saf_sec_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[3] AS top_saf_third_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[4] AS top_saf_forth_ward_no,
                      (string_to_array(string_agg(top_area_safs.ward_name::TEXT,','),','))[5] AS top_saf_fifth_ward_no,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[1] AS top_saf_first_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[2] AS top_saf_sec_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[3] AS top_saf_third_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[4] AS top_saf_forth_ward_count,
                      (string_to_array(string_agg(top_area_safs.application_count::TEXT,','),','))[5] AS top_saf_fifth_ward_count

                      FROM 

                              (
                                  SELECT 
                                      top_areas_safs.ward_mstr_id,
                                      SUM(top_areas_safs.application_count) AS application_count,
                                      u.ward_name 
                            
                                      FROM 

                                           (
                                               SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_active_safs
                                              WHERE application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'       -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id

                                                  UNION ALL 


                                          SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_safs
                                              WHERE application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'     -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id

                                                  UNION ALL 

                                          SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_rejected_safs
                                              WHERE application_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'   -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id
                                           ) AS top_areas_safs

                                          JOIN ulb_ward_masters u ON u.id=top_areas_safs.ward_mstr_id
                                          GROUP BY top_areas_safs.ward_mstr_id,u.ward_name 
                                          ORDER BY application_count DESC 
                                          LIMIT 5
                              ) AS top_area_safs
                ) AS top_area_safs,
                (
                 -- AreaWise Defaulters
                    SELECT 
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[1] AS defaulter_first_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[2] AS defaulter_sec_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[3] AS defaulter_third_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[4] AS defaulter_forth_ward_no,
                      (string_to_array(string_agg(a.ward_name::TEXT,','),','))[5] AS defaulter_fifth_ward_no,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[1] AS defaulter_first_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[2] AS defaulter_sec_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[3] AS defaulter_third_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[4] AS defaulter_forth_ward_prop_cnt,
                      (string_to_array(string_agg(a.defaulter_property_cnt::TEXT,','),','))[5] AS defaulter_fifth_ward_prop_cnt,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[1] AS defaulter_first_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[2] AS defaulter_sec_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[3] AS defaulter_third_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[4] AS defaulter_forth_unpaid_amount,
                      (string_to_array(string_agg(a.unpaid_amount::TEXT,','),','))[5] AS defaulter_fifth_unpaid_amount

                      FROM 
                          (
                              SELECT 
                                  COUNT(a.property_id) AS defaulter_property_cnt,
                                  w.ward_name,
                                  SUM(a.unpaid_amt) AS unpaid_amount

                                  FROM 
                                      (
                                          SELECT
                                               property_id,
                                               COUNT(id) AS demand_cnt,
                                               SUM(CASE WHEN paid_status=1 THEN 1 ELSE 0 END) AS paid_count,
                                               SUM(CASE WHEN paid_status=0 THEN 1 ELSE 0 END) AS unpaid_count,
                                               SUM(CASE WHEN paid_status=0 THEN balance ELSE 0 END) AS unpaid_amt

                                          FROM prop_demands
                                          WHERE fyear='$currentFy'								-- Parameterize this for current fyear range date
                                          AND status=1 AND ulb_id=2
                                          GROUP BY property_id
                                          ORDER BY property_id
                                  ) a 
                                  JOIN prop_properties p ON p.id=a.property_id
                                  JOIN ulb_ward_masters w ON w.id=p.ward_mstr_id
                                    
                                  WHERE a.demand_cnt=a.unpaid_count
                                  AND p.status=1
                                    
                                  GROUP BY w.ward_name 
                                    
                                  ORDER BY defaulter_property_cnt DESC 
                                  LIMIT 5
                          ) a 
                ) AS area_wise_defaulter,
                (	
                  -- Online Payments	
                  WITH 
                     current_payments AS (
                         
                SELECT 
                        SUM(CASE WHEN UPPER(payment_mode)='CASH' THEN amount ELSE 0 END) AS current_cash_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='CHEQUE' THEN amount ELSE 0 END) AS current_cheque_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='DD' THEN amount ELSE 0 END) AS current_dd_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='CARD PAYMENT' THEN amount ELSE 0 END) AS current_card_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='NEFT' THEN amount ELSE 0 END) AS current_neft_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='RTGS' THEN amount ELSE 0 END) AS current_rtgs_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN amount ELSE 0 END) AS current_online_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='QR' THEN amount ELSE 0 END) AS current_qr_payment,
                        SUM(CASE WHEN UPPER(payment_mode)='CARD' THEN amount ELSE 0 END) AS current_card_wise_payment
                    FROM prop_transactions
                    WHERE tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate' and saf_id is null					-- Parameterize this for current fyear range date
                           AND status=1 AND ulb_id=2

                       ),
                       lastyear_payments AS (
                           SELECT 

                                   SUM(CASE WHEN UPPER(payment_mode)='CASH' THEN amount ELSE 0 END) AS lastyear_cash_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='CHEQUE' THEN amount ELSE 0 END) AS lastyear_cheque_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='DD' THEN amount ELSE 0 END) AS lastyear_dd_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='NEFT' THEN amount ELSE 0 END) AS lastyear_neft_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN amount ELSE 0 END) AS lastyear_online_payment,
                                   SUM(CASE WHEN UPPER(payment_mode)='ONLINE' THEN 1 ELSE 0 END) AS current_online_counts

                           FROM prop_transactions
                           WHERE tran_date BETWEEN '$privfyStartDate' AND '$privfyEndDate'				-- Parameterize this for Past fyear range date
                           AND status=1 AND ulb_id=2
                       ),
                    jsk_collections AS (													    -- Jsk Collections

                           SELECT 
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '$privfyStartDate' AND '$privfyEndDate') THEN t.amount ELSE 0 END),0) AS prev_year_jskcollection,    -- Parameterize this for Past fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '$privfyStartDate' AND '$privfyEndDate') THEN 1 ELSE 0 END),0) AS prev_year_jskcount,                -- Parameterize this for Past fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate') THEN t.amount ELSE 0 END),0) AS current_year_jskcollection, -- Parameterize this for current fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate') THEN 1 ELSE 0 END),0) AS current_year_jskcount			  -- Parameterize this for current fyear range date

                          FROM prop_transactions t
                          JOIN users u ON u.id=t.user_id   
                          WHERE UPPER(u.user_type)='JSK' AND u.suspended=false  AND t.status=1
                          AND t.tran_date BETWEEN '$privfyStartDate' AND '$currentfyEndDate'  AND t.ulb_id=2	-- Parameterize this for last two fyears
                  )

                       SELECT * FROM current_payments,lastyear_payments,jsk_collections
                ) AS payment_modes";
        $data = $this->_DB_READ->select($sql);
        $data = $data[0];
        $mMplYearlyReport = new MplYearlyReport();

        $tradedata = $this->tradedetails();
        $propdata = $this->propertydetails();
        $waterdata = $this->waterdetails();
        $marketdata = $this->marketdetails();

        $updateReqs = [
            "total_assessment" => $data->total_assessed_props,
            "total_prop_vacand" => $propdata->total_vacant_land + $propdata->null_prop_data + $propdata->null_floor_data,
            "total_prop_residential" => $data->total_residential_props,
            "total_prop_commercial"  => $data->total_commercial_props,
            "total_prop_industrial" => $data->total_industrial_props,
            "total_prop_gbsaf" => $data->total_govt_props,
            "total_prop_mixe" => $data->total_mixed_commercial_props,
            "total_prop_religious" => $data->total_religious_props,
            "total_property" => $data->total_props,
            "vacant_property" => $propdata->total_vacant_land + $propdata->null_prop_data + $propdata->null_floor_data,
            "owned_property" => $data->total_owned_props,
            "rented_property" => $data->total_rented_props,
            "mixed_property" => $data->total_mixed_owned_props,

            "current_year_cash_collection" => $data->current_cash_payment,
            "current_year_card_collection" => $data->current_card_payment + $data->current_card_wise_payment,
            "current_year_dd_collection"   => $data->current_dd_payment,
            "current_year_cheque_collection" => $data->current_cheque_payment,
            "current_year_neft_collection" => $data->current_neft_payment,
            "current_year_rtgs_collection" => $data->current_rtgs_payment,
            "current_year_upi_collection" => $data->current_qr_payment,
            "current_year_online_collection" => $data->current_online_payment,
            "tc_count" => $data->tc_count,
            "da_count" => $data->da_count,
            "si_count" => $data->si_count,
            "eo_count" => $data->eo_count,
            "jsk_count" => $data->jsk_count,
            "utc_count" => $data->utc_count,
            "sh_count" => $data->sh_count,

            "last_year_payment_amount" => $data->lastyr_pmt_amt,
            "last_year_payment_count" => $data->lastyr_pmt_cnt,
            "this_year_payment_count" => $data->currentyr_pmt_cnt,
            "this_year_payment_amount" => $data->currentyr_pmt_amt,

            "collection_against_current_demand" => $data->current_collection,
            "collection_againt_arrear_demand" => $data->arrear_collection,

            "top_area_property_transaction_ward1_count" => $data->top_transaction_first_ward_count,
            "top_area_property_transaction_ward2_count" => $data->top_transaction_sec_ward_count,
            "top_area_property_transaction_ward3_count" => $data->top_transaction_third_ward_count,
            "top_area_property_transaction_ward4_count" => $data->top_transaction_forth_ward_count,
            "top_area_property_transaction_ward5_count" => $data->top_transaction_fifth_ward_count,


            "top_area_saf_ward1_name" => $data->top_saf_first_ward_no,
            "top_area_saf_ward2_name" => $data->top_saf_sec_ward_no,
            "top_area_saf_ward3_name" => $data->top_saf_third_ward_no,
            "top_area_saf_ward4_name" => $data->top_saf_forth_ward_no,
            "top_area_saf_ward5_name" => $data->top_saf_fifth_ward_no,
            "top_area_saf_ward1_count" => $data->top_saf_first_ward_count,
            "top_area_saf_ward2_count" => $data->top_saf_sec_ward_count,
            "top_area_saf_ward3_count" => $data->top_saf_third_ward_count,
            "top_area_saf_ward4_count" => $data->top_saf_forth_ward_count,
            "top_area_saf_ward5_count" => $data->top_saf_fifth_ward_count,

            "top_defaulter_ward1_name" => $data->defaulter_first_ward_no,
            "top_defaulter_ward2_name" => $data->defaulter_sec_ward_no,
            "top_defaulter_ward3_name" => $data->defaulter_third_ward_no,
            "top_defaulter_ward4_name" => $data->defaulter_forth_ward_no,
            "top_defaulter_ward5_name" => $data->defaulter_fifth_ward_no,
            "top_defaulter_ward1_count" => $data->defaulter_first_ward_prop_cnt,
            "top_defaulter_ward2_count" => $data->defaulter_sec_ward_prop_cnt,
            "top_defaulter_ward3_count" => $data->defaulter_third_ward_prop_cnt,
            "top_defaulter_ward4_count" => $data->defaulter_forth_ward_prop_cnt,
            "top_defaulter_ward5_count" => $data->defaulter_fifth_ward_prop_cnt,
            "top_defaulter_ward1_amount" => $data->defaulter_first_unpaid_amount,
            "top_defaulter_ward2_amount" => $data->defaulter_sec_unpaid_amount,
            "top_defaulter_ward3_amount" => $data->defaulter_third_unpaid_amount,
            "top_defaulter_ward4_amount" => $data->defaulter_forth_unpaid_amount,
            "top_defaulter_ward5_amount" => $data->defaulter_fifth_unpaid_amount,


            #trade
            'total_trade_licenses'  => $tradedata->total_trade_licenses,
            'total_trade_licenses_underprocess' => $tradedata->total_trade_licenses_underprocess,
            'trade_current_cash_payment' => $tradedata->trade_current_cash_payment,
            'trade_current_cheque_payment' => $tradedata->trade_current_cheque_payment,
            'trade_current_dd_payment' => $tradedata->trade_current_dd_payment,
            'trade_current_card_payment' => $tradedata->trade_current_card_payment,
            'trade_current_neft_payment' => $tradedata->trade_current_neft_payment,
            'trade_current_rtgs_payment' => $tradedata->trade_current_rtgs_payment,
            'trade_current_online_payment' => $tradedata->trade_current_online_payment,
            'trade_current_online_counts' => $tradedata->trade_current_online_counts,
            'trade_lastyear_cash_payment' => $tradedata->trade_lastyear_cash_payment,
            'trade_lastyear_cheque_payment' => $tradedata->trade_lastyear_cheque_payment,
            'trade_lastyear_dd_payment' => $tradedata->trade_lastyear_dd_payment,
            'trade_lastyear_neft_payment' => $tradedata->trade_lastyear_neft_payment,
            'trade_lastyear_rtgs_payment' => $tradedata->trade_lastyear_rtgs_payment,
            'trade_lastyear_online_payment' => $tradedata->trade_lastyear_online_payment,
            'trade_lastyear_online_counts' => $tradedata->trade_lastyear_online_counts,
            'trade_renewal_less_then_1_year' => $tradedata->trade_renewal_less_then_1_year,
            'trade_renewal_more_then_1_year' => $tradedata->trade_renewal_more_then_1_year,
            'trade_renewal_more_then_1_year_and_less_then_5_years' => $tradedata->trade_renewal_more_then_1_year_and_less_then_5_years,
            'trade_renewal_more_then_5_year' => $tradedata->trade_renewal_more_then_5_year,
            'a_trade_zone_name'  => $tradedata->a_trade_zone_name,
            'a_trade_total_hh'  => $tradedata->a_trade_total_hh,
            'a_trade_total_amount'  => $tradedata->a_trade_total_amount,
            'b_trade_zone_name'  => $tradedata->b_trade_zone_name,
            'b_trade_total_hh'  => $tradedata->b_trade_total_hh,
            'b_trade_total_amount'  => $tradedata->b_trade_total_amount,
            'c_trade_zone_name'  => $tradedata->c_trade_zone_name,
            'c_trade_total_hh'  => $tradedata->c_trade_total_hh,
            'c_trade_total_amount'  => $tradedata->c_trade_total_amount,
            'd_trade_zone_name'  => $tradedata->d_trade_zone_name,
            'd_trade_total_hh'  => $tradedata->d_trade_total_hh,
            'd_trade_total_amount'  => $tradedata->d_trade_total_amount,

            // #property_new
            'a_zone_name'  => $propdata->a_zone_name,
            'a_prop_total_hh'  => $propdata->a_prop_total_hh,
            'a_prop_total_amount'  => $propdata->a_prop_total_amount,
            'b_zone_name'  => $propdata->b_zone_name,
            'b_prop_total_hh'  => $propdata->b_prop_total_hh,
            'b_prop_total_amount'  => $propdata->b_prop_total_amount,
            'c_zone_name'  => $propdata->c_zone_name,
            'c_prop_total_hh'  => $propdata->c_prop_total_hh,
            'c_prop_total_amount'  => $propdata->c_prop_total_amount,
            'd_zone_name'  => $propdata->d_zone_name,
            'd_prop_total_hh'  => $propdata->d_prop_total_hh,
            'd_prop_total_amount'  => $propdata->d_prop_total_amount,
            'prop_current_demand'  => $propdata->prop_current_demand,
            'prop_arrear_demand'  => $propdata->prop_arrear_demand,
            'prop_total_demand'  => $propdata->prop_total_demand,

            'prop_total_collection'  => $propdata->prop_total_collection,





            #water
            'water_connection_underprocess'  => $waterdata->water_connection_underprocess,
            //'water_total_consumer'  => $waterdata->water_total_consumer,
            'water_fix_connection_type'  => $waterdata->water_fix_connection_type,
            'water_meter_connection_type'  => $waterdata->water_meter_connection_type,
            'water_current_demand'  => $waterdata->water_current_demand,
            'water_arrear_demand'  => $waterdata->water_arrear_demand,
            //'water_total_demand'  => $waterdata->water_total_demand,
            'water_current_collection'  => $waterdata->water_current_collection,
            'water_arrear_collection'  => $waterdata->water_arrear_collection,
            'water_total_collection'  => $waterdata->water_total_collection,
            'water_total_prev_collection'  => $waterdata->water_total_prev_collection,
            'water_arrear_collection_efficiency'  => $waterdata->water_arrear_collection_efficiency,
            'water_current_collection_efficiency'  => $waterdata->water_current_collection_efficiency,
            'water_current_outstanding'  => $waterdata->water_current_outstanding,
            'water_arrear_outstanding'  => $waterdata->water_arrear_outstanding,

            'a_water_zone_name'  => $waterdata->a_water_zone_name,
            'a_water_total_hh'  => $waterdata->a_water_total_hh,
            'a_water_total_amount'  => $waterdata->a_water_total_amount,
            'b_water_zone_name'  => $waterdata->b_water_zone_name,
            'b_water_total_hh'  => $waterdata->b_water_total_hh,
            'b_water_total_amount'  => $waterdata->b_water_total_amount,
            'c_water_zone_name'  => $waterdata->c_water_zone_name,
            'c_water_total_hh'  => $waterdata->c_water_total_hh,
            'c_water_total_amount'  => $waterdata->c_water_total_amount,
            'd_water_zone_name'  => $waterdata->d_water_zone_name,
            'd_water_total_hh'  => $waterdata->d_water_total_hh,
            'd_water_total_amount'  => $waterdata->d_water_total_amount,


            // #market
            'a_market_zone_name'  => $marketdata->a_market_zone_name,
            'a_market_total_hh'  => $marketdata->a_market_total_hh,
            'a_market_total_amount'  => $marketdata->a_market_total_amount,
            'b_market_zone_name'  => $marketdata->b_market_zone_name,
            'b_market_total_hh'  => $marketdata->b_market_total_hh,
            'b_market_total_amount'  => $marketdata->b_market_total_amount,
            'c_market_zone_name'  => $marketdata->c_market_zone_name,
            'c_market_total_hh'  => $marketdata->c_market_total_hh,
            'c_market_total_amount'  => $marketdata->c_market_total_amount,
            'd_market_zone_name'  => $marketdata->d_market_zone_name,
            'd_market_total_hh'  => $marketdata->d_market_total_hh,
            'd_market_total_amount'  => $marketdata->d_market_total_amount
        ];
        if ($mMplYearlyReport->where('fyear', $currentFy)->count("id")) {
            $sms = "Update";
            $test = $mMplYearlyReport->where('fyear', $currentFy)
                ->update($updateReqs);
        } else {
            $sms = "Insert";
            $ulbDtl = UlbMaster::find(2);
            $updateReqs = array_merge($updateReqs, ["fyear" => $currentFy, "ulb_id" => 2, "ulb_name" => $ulbDtl->ulb_name ?? null]);
            $test = $mMplYearlyReport->create($updateReqs)->id;
        }
        // $updateReqs->push(["fyear" => "$currentFy"]);
        // $mMplYearlyReport->create($updateReqs);

        dd($sms);
    }

    /**
     * | Coded By : Prity Pandey
     * | Date : 06-01-2024
     */

    /**
     * | Trade Details
     */
    public function tradedetails()
    {
        list($currentFyearFrom, $currentFyearEnd) = explode('-', getFY());
        $currentfyStartDate = $currentFyearFrom . "-04-01";
        $currentfyEndDate = $currentFyearEnd . "-03-31";
        $privfyStartDate = ($currentFyearFrom - 1) . "-04-01";
        $privfyEndDate = ($currentFyearEnd - 1) . "-03-31";
        # total trade licences
        $sql_total_trade_license = "
                                    
                                    select count(id) as total_trade_licenses
                                    from trade_licences
                                    where is_active = true and ulb_id = 2
        ";
        #license_underprocess
        $sql_active_trade_license = "
                                    
                                    select count(id) as total_trade_licenses_underprocess
                                    FROM active_trade_licences
                                    WHERE is_active = true AND ulb_id = 2
        ";
        #Online Payments trade
        $sql_online_payment_trade = "    
                                WITH payment_mode as (
                                    select distinct (UPPER(payment_mode)) as payment_mode
                                    from trade_transactions
                                    
                                ),
                                current_payments AS (
                                    SELECT ulb_id,                                                
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='CASH' THEN  paid_amount ELSE 0 END) AS trade_current_cash_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='CHEQUE' THEN  paid_amount ELSE 0 END) AS trade_current_cheque_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='DD' THEN  paid_amount ELSE 0 END) AS trade_current_dd_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='CARD PAYMENT' THEN  paid_amount ELSE 0 END) AS trade_current_card_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='NEFT' THEN  paid_amount ELSE 0 END) AS trade_current_neft_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='RTGS' THEN  paid_amount ELSE 0 END) AS trade_current_rtgs_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='ONLINE' THEN  paid_amount ELSE 0 END) AS trade_current_online_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='ONLINE' THEN 1 ELSE 0 END) AS trade_current_online_counts,
                                                
                                                0 AS trade_lastyear_cash_payment,
                                                0 AS trade_lastyear_cheque_payment,
                                                0 AS trade_lastyear_dd_payment,
                                                0 AS trade_lastyear_neft_payment,
                                                0 AS trade_lastyear_rtgs_payment,
                                                0 AS trade_lastyear_online_payment,
                                                Null::numeric AS trade_lastyear_online_counts
                                    FROM payment_mode
                                    join trade_transactions on UPPER (trade_transactions.payment_mode) = payment_mode.payment_mode
                                    WHERE tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate'					-- Parameterize this for current fyear range date
                                        AND  status=1 
                                    group by ulb_id
                        
                                ),
                                lastyear_payments AS (
                                        SELECT ulb_id,                                        
                                                0 AS trade_current_cash_payment,
                                                0 AS trade_current_cheque_payment,
                                                0 AS trade_current_dd_payment,
                                                0  AS trade_current_card_payment,
                                                0 AS trade_current_neft_payment,
                                                0 AS trade_current_rtgs_payment,
                                                0 AS trade_current_online_payment,
                                                Null::numeric AS trade_current_online_counts,
                                                                                
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='CASH' THEN  paid_amount ELSE 0 END) AS trade_lastyear_cash_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='CHEQUE' THEN  paid_amount ELSE 0 END) AS trade_lastyear_cheque_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='DD' THEN  paid_amount ELSE 0 END) AS trade_lastyear_dd_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='NEFT' THEN  paid_amount ELSE 0 END) AS trade_lastyear_neft_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='RTGS' THEN  paid_amount ELSE 0 END) AS trade_lastyear_rtgs_payment,

                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='ONLINE' THEN  paid_amount ELSE 0 END) AS trade_lastyear_online_payment,
                                                SUM(CASE WHEN UPPER(payment_mode.payment_mode)='ONLINE' THEN 1 ELSE 0 END) AS trade_lastyear_online_counts

                                    FROM payment_mode
                                    join trade_transactions on UPPER (trade_transactions.payment_mode) = payment_mode.payment_mode
                                    WHERE tran_date BETWEEN '$privfyStartDate' AND '$privfyEndDate'					
                                        AND  status=1 
                                    group by ulb_id

                                )
                                select 
                                    ulb_id,                                   
                                    sum (trade_current_cash_payment) as trade_current_cash_payment, 
                                    sum (trade_current_cheque_payment) as trade_current_cheque_payment, 
                                    sum(trade_current_dd_payment) as trade_current_dd_payment,
                                    sum(trade_current_card_payment) as trade_current_card_payment,
                                    sum(trade_current_neft_payment) as trade_current_neft_payment,
                                    sum(trade_current_rtgs_payment) as trade_current_rtgs_payment,
                                    sum(trade_current_online_payment) as trade_current_online_payment,
                                    sum(trade_current_online_counts) as trade_current_online_counts,
                                    sum (trade_lastyear_cash_payment) as trade_lastyear_cash_payment, 
                                    sum(trade_lastyear_cheque_payment) as trade_lastyear_cheque_payment,
                                    sum(trade_lastyear_dd_payment) as trade_lastyear_dd_payment,
                                    sum(trade_lastyear_neft_payment) as trade_lastyear_neft_payment,
                                    sum(trade_lastyear_rtgs_payment) as trade_lastyear_rtgs_payment,
                                    sum(trade_lastyear_online_payment) as trade_lastyear_online_payment,
                                    sum(trade_lastyear_online_counts) as trade_lastyear_online_counts
                                    
                                from (
                                    (
                                    select * 
                                    from current_payments
                                    )
                                    union all
                                    (
                                    select * 
                                    from lastyear_payments
                                    )
                                )as payment
                                group by ulb_id
            ";

        $sql_trade_zonal = "
                            SELECT 
                                zone_masters.id AS id,
                                CASE 
                                    WHEN zone_masters.id = 1 THEN 'East Zone'
                                    WHEN zone_masters.id = 2 THEN 'West Zone'
                                    WHEN zone_masters.id = 3 THEN 'North Zone'
                                    WHEN zone_masters.id = 4 THEN 'South Zone'
                                    ELSE 'NA' 
                                END AS trade_zone_name, 
                                COUNT(DISTINCT trade_transactions.temp_id) AS trade_consumer_count,
                                COALESCE(SUM(trade_transactions.paid_amount),0) AS trade_total_amount
                            FROM 
                                zone_masters
                            LEFT JOIN (
                                (
                                    SELECT id, ward_id, zone_id, 'pending' AS app_types
                                    FROM active_trade_licences
                                )
                                UNION
                                (
                                    SELECT id, ward_id, zone_id, 'rejected' AS app_types
                                    FROM rejected_trade_licences
                                )
                                UNION
                                (
                                    SELECT id, ward_id, zone_id, 'active' AS app_types
                                    FROM trade_licences
                                )
                                UNION
                                (
                                    SELECT id, ward_id, zone_id, 'old' AS app_types
                                    FROM trade_renewals
                                )
                                ) AS trade_licences ON trade_licences.zone_id = zone_masters.id
                            LEFT JOIN trade_transactions ON trade_transactions.temp_id = trade_licences.id
                                AND trade_transactions.status IN (1, 2) 
                                AND trade_transactions.tran_date BETWEEN '$currentfyStartDate' AND '$currentfyEndDate' 
                            GROUP BY 
                                zone_masters.id
        ";

        # Renewal pending  
        $sql_renewal_pending_trade = "    
                                select 
                                count(
                                    case when ( 
                                                (DATE_PART('YEAR', current_date :: DATE) - DATE_PART('YEAR', valid_upto :: DATE)) * 12
                                                +(DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', valid_upto :: DATE))
                                                )/12 <= 1 then id else null end
                                                ) as less_then_1_year,
                                count(
                                    case when ( 
                                                (DATE_PART('YEAR', current_date :: DATE) - DATE_PART('YEAR', valid_upto :: DATE)) * 12
                                                +(DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', valid_upto :: DATE))
                                                )/12 > 1 then id else null end
                                                ) as more_then_1_year, 
                                count(
                                    case when ( 
                                                (DATE_PART('YEAR', current_date :: DATE) - DATE_PART('YEAR', valid_upto :: DATE)) * 12
                                                +(DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', valid_upto :: DATE))
                                                )/12 > 1 and 
                                                ( 
                                                (DATE_PART('YEAR', current_date :: DATE) - DATE_PART('YEAR', valid_upto :: DATE)) * 12
                                                +(DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', valid_upto :: DATE))
                                                )/12 <=5 then id else null end
                                                ) as more_then_1_year_and_less_then_5_years,
                                count(
                                    case when ( 
                                                (DATE_PART('YEAR', current_date :: DATE) - DATE_PART('YEAR', valid_upto :: DATE)) * 12
                                                +(DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', valid_upto :: DATE))
                                                )/12 >5 then id else null end
                                                ) as more_then_5_year
                            from trade_licences
                            where is_active = true and valid_upto<current_date
        ";
        $respons = [];
        $data = collect(DB::connection("pgsql_trade")->select($sql_total_trade_license))->first();
        $respons["total_trade_licenses"] = $data->total_trade_licenses ?? 0;
        $data = collect(DB::connection("pgsql_trade")->select($sql_active_trade_license))->first();
        $respons["total_trade_licenses_underprocess"] = $data->total_trade_licenses_underprocess ?? 0;
        $data = collect(DB::connection("pgsql_trade")->select($sql_online_payment_trade))->where("ulb_id", 2)->first();
        $respons["trade_current_cash_payment"] = $data->trade_current_cash_payment ?? 0;
        $respons["trade_current_cheque_payment"] = $data->trade_current_cheque_payment ?? 0;
        $respons["trade_current_dd_payment"] = $data->trade_current_dd_payment ?? 0;
        $respons["trade_current_card_payment"] = $data->trade_current_card_payment ?? 0;
        $respons["trade_current_neft_payment"] = $data->trade_current_neft_payment ?? 0;
        $respons["trade_current_rtgs_payment"] = $data->trade_current_rtgs_payment ?? 0;
        $respons["trade_current_online_payment"] = $data->trade_current_online_payment ?? 0;
        $respons["trade_current_online_counts"] = $data->trade_current_online_counts ?? 0;
        $respons["trade_lastyear_cash_payment"] = $data->trade_lastyear_cash_payment ?? 0;
        $respons["trade_lastyear_cheque_payment"] = $data->trade_lastyear_cheque_payment ?? 0;
        $respons["trade_lastyear_dd_payment"] = $data->trade_lastyear_dd_payment ?? 0;
        $respons["trade_lastyear_neft_payment"] = $data->trade_lastyear_neft_payment ?? 0;
        $respons["trade_lastyear_rtgs_payment"] = $data->trade_lastyear_rtgs_payment ?? 0;
        $respons["trade_lastyear_online_payment"] = $data->trade_lastyear_online_payment ?? 0;
        $respons["trade_lastyear_online_counts"] = $data->trade_lastyear_online_counts ?? 0;

        $data = collect(DB::connection("pgsql_trade")->select($sql_renewal_pending_trade))->first();
        $respons["trade_renewal_less_then_1_year"] = $data->less_then_1_year ?? 0;
        $respons["trade_renewal_more_then_1_year"] = $data->more_then_1_year ?? 0;
        $respons["trade_renewal_more_then_1_year_and_less_then_5_years"] = $data->more_then_1_year_and_less_then_5_years ?? 0;
        $respons["trade_renewal_more_then_5_year"] = $data->more_then_5_year ?? 0;

        $data = collect(DB::connection("pgsql_trade")->select($sql_trade_zonal))->first();
        $respons["a_trade_zone_name"] = (collect($data)->where("id", 1)->first()->trade_zone_name) ?? 0;
        $respons["a_trade_total_hh"] = (collect($data)->where("id", 1)->first()->trade_consumer_count) ?? 0;
        $respons["a_trade_total_amount"] = (collect($data)->where("id", 1)->first()->trade_total_amount) ?? 0;

        $respons["b_trade_zone_name"] = (collect($data)->where("id", 2)->first()->trade_zone_name) ?? 0;
        $respons["b_trade_total_hh"] = (collect($data)->where("id", 2)->first()->trade_consumer_count) ?? 0;
        $respons["b_trade_total_amount"] = (collect($data)->where("id", 2)->first()->trade_total_amount) ?? 0;

        $respons["c_trade_zone_name"] = (collect($data)->where("id", 3)->first()->trade_zone_name) ?? 0;
        $respons["c_trade_total_hh"] = (collect($data)->where("id", 3)->first()->trade_consumer_count) ?? 0;
        $respons["c_trade_total_amount"] = (collect($data)->where("id", 3)->first()->trade_total_amount) ?? 0;

        $respons["d_trade_zone_name"] = (collect($data)->where("id", 4)->first()->trade_zone_name) ?? 0;
        $respons["d_trade_total_hh"] = (collect($data)->where("id", 4)->first()->trade_consumer_count) ?? 0;
        $respons["d_trade_total_amount"] = (collect($data)->where("id", 4)->first()->trade_total_amount) ?? 0;
        return (object)$respons;
    }


    public function propertydetails()
    {
        $currentFy = getFY();
        list($currentFyearFrom, $currentFyearEnd) = explode('-', $currentFy);
        $currentfyStartDate = $currentFyearFrom . "-04-01";
        $currentfyEndDate = $currentFyearEnd . "-03-31";
        $privfyStartDate = ($currentFyearFrom - 1) . "-04-01";
        $privfyEndDate = ($currentFyearEnd - 1) . "-03-31";
        # total trade licences
        $sql_property_under_assesment = "
                                    
                                    select count(id) as property_under_assesment
                                    from prop_active_safs
                                    where previous_holding_id is not null
        ";
        #license_underprocess
        $sql_property_zonal = "
                                    
                SELECT 
                    zone_masters.id AS id,
                    CASE 
                        WHEN zone_masters.id = 1 THEN 'East Zone'
                        WHEN zone_masters.id = 2 THEN 'West Zone'
                        WHEN zone_masters.id = 3 THEN 'North Zone'
                        WHEN zone_masters.id = 4 THEN 'South Zone'
                        ELSE 'NA' 
                    END AS prop_zone_name, 
                    COUNT(DISTINCT prop_transactions.property_id) AS prop_total_hh,
                    SUM(prop_transactions.amount) AS prop_total_amount
                FROM 
                    zone_masters
                left join prop_properties on prop_properties.zone_mstr_id = zone_masters.id
                left JOIN prop_transactions on prop_transactions.property_id = prop_properties.id
                and prop_transactions.status IN (1, 2) 
                    AND prop_transactions.tran_date between '$currentfyStartDate' and '$currentfyEndDate' 
                GROUP BY 
                    zone_masters.id 
        ";
        # total demands
        $sql_property_demand = "
                select 
                SUM(
                    CASE WHEN prop_demands.fyear  = '$currentFy' then prop_demands.total_tax
                        ELSE 0
                        END
                ) AS prop_current_demand,
                SUM(
                    CASE WHEN prop_demands.fyear < '$currentFy' then prop_demands.total_tax
                        ELSE 0
                        END
                ) AS prop_arrear_demand,
                SUM(prop_demands.total_tax) AS prop_total_demand
            FROM prop_demands
            WHERE prop_demands.status =1 
            ";
        # total collection
        $sql_property_collection = "
             select sum(amount) AS prop_total_collection
             from prop_transactions
             where prop_transactions.tran_date between '$currentfyStartDate' and '$currentfyEndDate' 
             and saf_id is  null
             and prop_transactions.status = 1
             ";
        $sql_prop_vacant_land = "
                         SELECT 
                             (
                                 SELECT COUNT(id) 
                                 FROM prop_properties p 
                                 WHERE p.prop_type_mstr_id = 4 
                                 AND status = 1 
                                 AND ulb_id = 2
                             ) AS total_vacant_land
         ";
        $sql_prop_null_data = "
                           SELECT 
                           (
                           select count(p.id) as null_prop_data
                               FROM prop_properties p 
                               WHERE p.prop_type_mstr_id IS NULL AND p.status=1
                       ) AS null_prop_data";
        $sql_prop_null_floor_data = "
                       SELECT count(DISTINCT p.id) as null_floor_data
                       FROM prop_properties p 
                       LEFT JOIN prop_floors f ON f.property_id = p.id AND f.status = 1
                       WHERE p.status = 1 
                       AND p.prop_type_mstr_id IS NOT NULL 
                       AND p.prop_type_mstr_id <> 4 
                       AND f.id IS NULL
                   ";
        $respons = [];
        $data = collect(DB::connection("pgsql")->select($sql_property_under_assesment))->first();
        $respons["property_under_assesment"] = $data->property_under_assesment ?? 0;
        $data = collect(DB::connection("pgsql")->select($sql_property_zonal));

        $respons["a_zone_name"] = (collect($data)->where("id", 1)->first()->prop_zone_name) ?? 0;
        $respons["a_prop_total_hh"] = (collect($data)->where("id", 1)->first()->prop_total_hh) ?? 0;
        $respons["a_prop_total_amount"] = (collect($data)->where("id", 1)->first()->prop_total_amount) ?? 0;

        $respons["b_zone_name"] = (collect($data)->where("id", 2)->first()->prop_zone_name) ?? 0;
        $respons["b_prop_total_hh"] = (collect($data)->where("id", 2)->first()->prop_total_hh) ?? 0;
        $respons["b_prop_total_amount"] = (collect($data)->where("id", 2)->first()->prop_total_amount) ?? 0;

        $respons["c_zone_name"] = (collect($data)->where("id", 3)->first()->prop_zone_name) ?? 0;
        $respons["c_prop_total_hh"] = (collect($data)->where("id", 3)->first()->prop_total_hh) ?? 0;
        $respons["c_prop_total_amount"] = (collect($data)->where("id", 3)->first()->prop_total_amount) ?? 0;

        $respons["d_zone_name"] = (collect($data)->where("id", 4)->first()->prop_zone_name) ?? 0;
        $respons["d_prop_total_hh"] = (collect($data)->where("id", 4)->first()->prop_total_hh) ?? 0;
        $respons["d_prop_total_amount"] = (collect($data)->where("id", 4)->first()->prop_total_amount) ?? 0;

        $data = collect(DB::connection("pgsql")->select($sql_property_demand))->first();
        $respons["prop_current_demand"] = $data->prop_current_demand ?? 0;
        $respons["prop_arrear_demand"] = $data->prop_arrear_demand ?? 0;
        $respons["prop_total_demand"] = $data->prop_total_demand ?? 0;
        $data = collect(DB::connection("pgsql")->select($sql_property_collection))->first();
        // $respons["prop_current_collection"] = $data->prop_current_collection ?? 0;
        // $respons["prop_arrear_collection"] = $data->prop_arrear_collection ?? 0;
        $respons["prop_total_collection"] = $data->prop_total_collection ?? 0;

        $data = collect(DB::connection("pgsql")->select($sql_prop_vacant_land))->first();
        $respons["total_vacant_land"] = $data->total_vacant_land ?? 0;

        $data = collect(DB::connection("pgsql")->select($sql_prop_null_data))->first();
        $respons["null_prop_data"] = $data->null_prop_data ?? 0;
        $data = collect(DB::connection("pgsql")->select($sql_prop_null_floor_data))->first();
        $respons["null_floor_data"] = $data->null_floor_data ?? 0;
        // dd($respons);
        return (object)$respons;
    }

    public function waterdetails()
    {
        $currentFy = getFY();
        list($currentFyearFrom, $currentFyearEnd) = explode('-', $currentFy);
        $currentfyStartDate = $currentFyearFrom . "-04-01";
        $currentfyEndDate = $currentFyearEnd . "-03-31";
        $privfyStartDate = ($currentFyearFrom - 1) . "-04-01";
        $privfyEndDate = ($currentFyearEnd - 1) . "-03-31";

        # total trade licences
        $sql_water_application_underprocess = "
                                                                            
                                        select count(id) as water_connection_underprocess
                                        from water_applications
                                        where status = true
        ";
        #license_underprocess
        $sql_water_connection_type = "
                                    
                                select count(water_second_consumers.id) as water_total_consumer, 
                                    case when water_consumer_meters.connection_type in (1,2) then 'meter' else 'fix' end as water_connection_type
                                from water_second_consumers
                                left join (
                                    select *
                                    from water_consumer_meters
                                    where id in(
                                        select max(id)
                                        from water_consumer_meters
                                        where status = 1
                                        group by consumer_id
                                    )
                                )water_consumer_meters on water_consumer_meters.consumer_id = water_second_consumers.id
                                where water_second_consumers.status = 1
                                group by( case when water_consumer_meters.connection_type in (1,2) then 'meter' else 'fix' end)
                            
        ";
        #water_demand
        $sql_water_demand = "

                        with demand as (
                            select ulb_id,sum(case when demand_from >='$currentfyStartDate' and demand_upto <='$currentfyEndDate' then amount else 0 end) as current_demand,
                            sum(case when demand_upto <'$currentfyStartDate'then amount else 0 end) as arrear_demand,
                            sum(amount) as total_demand,count(distinct consumer_id) as total_consumer
                            from water_consumer_demands
                            where status = true and demand_upto<'$currentfyEndDate'
                            group by ulb_id
                            
                        ),
                        collection as (
                            
                            select water_consumer_demands.ulb_id, sum(water_tran_details.paid_amount) as total_collection,
                                sum(case when water_consumer_demands.demand_from >='$currentfyStartDate' 
                                    and water_consumer_demands.demand_upto <='$currentfyEndDate' 
                                    then water_tran_details.paid_amount else 0 
                                    end) as current_collection,
                                sum(case when water_consumer_demands.demand_upto <'$currentfyStartDate'
                                    then water_tran_details.paid_amount else 0 
                                    end) as arrear_collection,
                                count(distinct water_consumer_demands.consumer_id) as total_coll_consumer
                            from  water_tran_details
                            join water_consumer_demands on water_consumer_demands.id = 	water_tran_details.demand_id
                            join water_trans on water_trans.id = water_tran_details.tran_id
                            where water_trans.tran_date between '$currentfyStartDate' and '$currentfyEndDate' and water_trans.status in(1,2)
                                and water_trans.tran_type = 'Demand Collection'
                                and water_tran_details.status = 1
                            group by  water_consumer_demands.ulb_id
                                
                        ),
                        prev_collection as (
                            select water_trans.ulb_id,sum(water_tran_details.paid_amount) as total_prev_collection,
                                count(distinct water_trans.related_id) as total_prev_coll_consumer
                            from  water_tran_details
                            join water_trans on water_trans.id = water_tran_details.tran_id
                            where water_trans.tran_date < '$currentfyStartDate' and water_trans.status in(1,2)
                                and water_trans.tran_type = 'Demand Collection'
                            and water_tran_details.status = 1
                            group by  water_trans.ulb_id
                        )
                        select demand.ulb_id,sum(Coalesce(demand.current_demand,0) ) as water_current_demand ,
                            sum(Coalesce(collection.current_collection,0) ) as water_current_collection,
                            sum(Coalesce(prev_collection.total_prev_collection,0) ) as water_total_prev_collection,
                            (sum(Coalesce(demand.arrear_demand,0) ) - sum(Coalesce(prev_collection.total_prev_collection,0) )) as water_arrear_demand 
                            ,
                            sum(Coalesce(collection.arrear_collection,0) ) as water_arrear_collection,
                            (sum(Coalesce(demand.current_demand,0) )- sum(Coalesce(collection.current_collection,0) )) as water_current_outstanding,
                            ((sum(Coalesce(demand.arrear_demand,0) ) - sum(Coalesce(prev_collection.total_prev_collection,0) ))- sum(Coalesce(collection.arrear_collection,0) )) as water_arrear_outstanding,
                            sum(Coalesce(collection.total_collection,0)) as water_total_collection,
                            CASE 
                                WHEN SUM(COALESCE(demand.current_demand, 0)) > 0 
                                THEN (SUM(COALESCE(collection.current_collection, 0)) / SUM(COALESCE(demand.current_demand, 0))) * 100
                                ELSE 0
                            END AS water_current_collection_efficiency,
                            CASE 
                                WHEN (SUM(COALESCE(demand.arrear_demand, 0)) - SUM(COALESCE(prev_collection.total_prev_collection, 0))) > 0 
                                THEN (SUM(COALESCE(collection.arrear_collection, 0)) / (SUM(COALESCE(demand.arrear_demand, 0)) - SUM(COALESCE(prev_collection.total_prev_collection, 0)))) * 100
                                ELSE 0
                            END AS water_arrear_collection_efficiency
                        from demand
                        left join collection on collection.ulb_id = demand.ulb_id
                        left join prev_collection on prev_collection.ulb_id = demand.ulb_id
                        group by demand.ulb_id";

        $sql_water_zonal = "
                        SELECT 
                            zone_masters.id AS id,
                            CASE 
                                WHEN zone_masters.id = 1 THEN 'East Zone'
                                WHEN zone_masters.id = 2 THEN 'West Zone'
                                WHEN zone_masters.id = 3 THEN 'North Zone'
                                WHEN zone_masters.id = 4 THEN 'South Zone'
                                ELSE 'NA' 
                            END AS water_zone_name, 
                            COUNT(DISTINCT water_trans.related_id) AS water_total_customer, 
                            SUM(water_trans.amount) AS water_total_amount
                        FROM 
                            zone_masters
                        left JOIN 
                            water_second_consumers ON water_second_consumers.zone_mstr_id = zone_masters.id 
                        left join water_trans on water_trans.related_id = water_second_consumers.id
                        and water_trans.status in (1,2)
                        AND water_trans.tran_type = 'Demand Collection' and water_trans.tran_date  between '$currentfyStartDate' and '$currentfyEndDate' 
                        GROUP BY 
                            zone_masters.id 
                        ";

        $respons = [];
        $data = collect(DB::connection("pgsql_water")->select($sql_water_application_underprocess))->first();
        $respons["water_connection_underprocess"] = $data->water_connection_underprocess ?? 0;
        $data = collect(DB::connection("pgsql_water")->select($sql_water_connection_type));
        //dd((collect($data)->where("water_connection_type","meter")->first())->water_total_consumer);
        $respons["water_meter_connection_type"] = (collect($data)->where("water_connection_type", "meter")->first())->water_total_consumer ?? 0;
        $respons["water_fix_connection_type"] = (collect($data)->where("water_connection_type", "fix")->first())->water_total_consumer ?? 0;
        $data = collect(DB::connection("pgsql_water")->select($sql_water_demand))->first();
        $respons["water_current_demand"] = $data->water_current_demand ?? 0;
        $respons["water_arrear_demand"] = $data->water_arrear_demand ?? 0;
        $respons["water_total_prev_collection"] = $data->water_total_prev_collection ?? 0;
        $respons["water_current_collection"] = $data->water_current_collection ?? 0;
        $respons["water_arrear_collection"] = $data->water_arrear_collection ?? 0;
        $respons["water_total_collection"] = $data->water_total_collection ?? 0;
        $respons["water_current_collection_efficiency"] = $data->water_current_collection_efficiency ?? 0;
        $respons["water_arrear_collection_efficiency"] = $data->water_arrear_collection_efficiency ?? 0;
        $respons["water_current_outstanding"] = $data->water_current_outstanding ?? 0;
        $respons["water_arrear_outstanding"] = $data->water_arrear_outstanding ?? 0;

        $data = collect(DB::connection("pgsql_water")->select($sql_water_zonal));
        $respons["a_water_zone_name"] = (collect($data)->where("id", 1)->first()->water_zone_name) ?? 0;
        $respons["a_water_total_hh"] = (collect($data)->where("id", 1)->first()->water_total_customer) ?? 0;
        $respons["a_water_total_amount"] = (collect($data)->where("id", 1)->first()->water_total_amount) ?? 0;

        $respons["b_water_zone_name"] = (collect($data)->where("id", 2)->first()->water_zone_name) ?? 0;
        $respons["b_water_total_hh"] = (collect($data)->where("id", 2)->first()->water_total_customer) ?? 0;
        $respons["b_water_total_amount"] = (collect($data)->where("id", 2)->first()->water_total_amount) ?? 0;

        $respons["c_water_zone_name"] = (collect($data)->where("id", 3)->first()->water_zone_name) ?? 0;
        $respons["c_water_total_hh"] = (collect($data)->where("id", 3)->first()->water_total_customer) ?? 0;
        $respons["c_water_total_amount"] = (collect($data)->where("id", 3)->first()->water_total_amount) ?? 0;

        $respons["d_water_zone_name"] = (collect($data)->where("id", 4)->first()->water_zone_name) ?? 0;
        $respons["d_water_total_hh"] = (collect($data)->where("id", 4)->first()->water_total_customer) ?? 0;
        $respons["d_water_total_amount"] = (collect($data)->where("id", 4)->first()->water_total_amount) ?? 0;

        return (object)$respons;
    }

    public function marketdetails()
    {

        $currentFy = getFY();
        list($currentFyearFrom, $currentFyearEnd) = explode('-', $currentFy);
        $currentfyStartDate = $currentFyearFrom . "-04-01";
        $currentfyEndDate = $currentFyearEnd . "-03-31";
        $privfyStartDate = ($currentFyearFrom - 1) . "-04-01";
        $privfyEndDate = ($currentFyearEnd - 1) . "-03-31";

        $sql_market_zonal = "
                    select m_circle.id AS id,
                        CASE 
                            WHEN m_circle.id = 1 THEN 'East Zone'
                            WHEN m_circle.id = 2 THEN 'West Zone'
                            WHEN m_circle.id = 3 THEN 'North Zone'
                            WHEN m_circle.id = 4 THEN 'South Zone'
                            ELSE 'NA' 
                        END AS market_zone_name, 
                        COUNT(DISTINCT mar_shops.shop_id) AS market_shop_count,
                        COALESCE(SUM(mar_shops.amount),0) AS market_total_amount
                    FROM m_circle    
                    left join (
                            select case when mar_shops.circle_id is null then 1 else mar_shops.circle_id end as circle_id,
                                    --mar_shops.circle_id, 
                                mar_shops.id,
                                mar_shop_payments.shop_id,mar_shop_payments.amount
                            from mar_shops
                            JOIN mar_shop_payments on mar_shop_payments.shop_id = mar_shops.id
                                where mar_shop_payments.shop_id = mar_shops.id
                                and mar_shop_payments.is_active = '1'
                                    AND mar_shop_payments.payment_date between '$currentfyStartDate' and '$currentfyEndDate'
                                    --and mar_shops.circle_id is null
                            ) mar_shops
                            on mar_shops.circle_id = m_circle.id
                    GROUP BY 
                        m_circle.id 
                ";



        $respons = [];


        $data = collect(DB::connection("pgsql_advertisements")->select($sql_market_zonal));
        $respons["a_market_zone_name"] = (collect($data)->where("id", 1)->first()->market_zone_name) ?? 0;
        $respons["a_market_total_hh"] = (collect($data)->where("id", 1)->first()->market_shop_count) ?? 0;
        $respons["a_market_total_amount"] = (collect($data)->where("id", 1)->first()->market_total_amount) ?? 0;

        $respons["b_market_zone_name"] = (collect($data)->where("id", 2)->first()->market_zone_name) ?? 0;
        $respons["b_market_total_hh"] = (collect($data)->where("id", 2)->first()->market_shop_count) ?? 0;
        $respons["b_market_total_amount"] = (collect($data)->where("id", 2)->first()->market_total_amount) ?? 0;

        $respons["c_market_zone_name"] = (collect($data)->where("id", 3)->first()->market_zone_name) ?? 0;
        $respons["c_market_total_hh"] = (collect($data)->where("id", 3)->first()->market_shop_count) ?? 0;
        $respons["c_market_total_amount"] = (collect($data)->where("id", 3)->first()->market_total_amount) ?? 0;

        $respons["d_market_zone_name"] = (collect($data)->where("id", 4)->first()->market_zone_name) ?? 0;
        $respons["d_market_total_hh"] = (collect($data)->where("id", 4)->first()->market_shop_count) ?? 0;
        $respons["d_market_total_amount"] = (collect($data)->where("id", 4)->first()->market_total_amount) ?? 0;

        return (object)$respons;
    }

    public function mplReportCollectionnew1(Request $request)
    {
        try {
            $request->merge(["metaData" => ["pr111.1", 1.1, null, $request->getMethod(), null]]);
            $ulbId = $request->ulbId ?? 2;
            $currentDate = Carbon::now()->format("Y-m-d");

            // PropTransaction Query
            $propTransactionQuery = PropTransaction::select(DB::raw("
                        SUM(prop_transactions.amount) AS total_amount, 
                        COUNT(distinct (prop_transactions.property_id)) AS total_hh,
                        COUNT(distinct (prop_transactions.saf_id)) AS total_saf,
                        COUNT(id) as total_tran
                    "))
                ->wherein("status", [1, 2])
                ->where("tran_date", $currentDate)
                // ->wherenotnull("property_id")
                ->get();
            $propTransactionQuery = ($propTransactionQuery
                ->map(function ($val) {
                    $val->total_amount = $val->total_amount ? $val->total_amount : 0;
                    return ($val);
                }))
                ->first();

            // TradeTransaction Query
            $tradeTransactionQuery = TradeTransaction::select(DB::raw("sum(paid_amount) as total_amount, count(distinct(temp_id)) as total_license, count(id) as total_tran"))
                ->wherein("status", [1, 2])
                ->where("tran_date", $currentDate)
                ->get();
            $tradeTransactionQuery = ($tradeTransactionQuery
                ->map(function ($val) {
                    $val->total_amount = $val->total_amount ? $val->total_amount : 0;
                    return ($val);
                }))
                ->first();


            // WaterTransaction Query
            $waterTransactionQuery = WaterTran::select(
                DB::raw("sum(amount)as total_amount , count(distinct(related_id)) as total_consumer, count(id) as total_tran")
            )
                ->wherein("status", [1, 2])
                ->where("tran_date", $currentDate)
                ->where("tran_type", 'Demand Collection')
                ->get();
            $waterTransactionQuery = ($waterTransactionQuery
                ->map(function ($val) {
                    $val->total_amount = $val->total_amount ? $val->total_amount : 0;
                    return ($val);
                }))
                ->first();
            // Combine the results
            $toDayCollection = $propTransactionQuery->total_amount + $tradeTransactionQuery->total_amount + $waterTransactionQuery->total_amount;
            $data = [
                "toDayCollection" => ($toDayCollection ? $toDayCollection : 0),
                "propDetails" => $propTransactionQuery,
                "tradeDetails" => $tradeTransactionQuery,
                "waterDetails" => $waterTransactionQuery,
            ];

            return responseMsgs(true, "Mpl Report Today Coll", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | written by Prity Pandey 
     * / code for akola livedashboard today collection
     */
    public function mplReportCollectionnew(Request $request)
    {
        try {
            $request->merge(["metaData" => ["pr111.1", 1.1, null, $request->getMethod(), null]]);
            $ulbId = $request->ulbId ?? 2;
            $currentDate = Carbon::now()->format("Y-m-d");
            $currentfyear = getFY();
            $fromDate = explode("-", $currentfyear)[0] . "-04-01";
            $uptoDate = explode("-", $currentfyear)[1] . "-03-31";
            $propTransactionQuery = DB::select(DB::raw("
                            SELECT  
                                assessment_types.assessment_type, 
                                COALESCE(SUM(t.total_hh), 0) AS total_hh,
                                COALESCE(SUM(t.total_amount), 0) AS total_amount,  
                                COALESCE(SUM(t.total_tran), 0) AS total_tran
                            FROM
                                (SELECT DISTINCT assessment_type FROM prop_safs) AS assessment_types  
                            LEFT JOIN 
                                (
                                    SELECT 
                                        DISTINCT(assessment_type) AS assessment_type,
                                        COUNT(DISTINCT(prop_transactions.saf_id)) AS total_hh,
                                        SUM(prop_transactions.amount) AS total_amount,
                                        COUNT(prop_transactions.id) AS total_tran
                                    FROM prop_safs
                                    JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id
                                    WHERE prop_transactions.status IN (1, 2) 
                                    AND DATE(prop_transactions.tran_date) = ?
                                    GROUP BY assessment_type
                                ) AS t
                                ON t.assessment_type = assessment_types.assessment_type
                            GROUP BY 
                                assessment_types.assessment_type
                        "), [$currentDate]);

            $propTax = DB::select(DB::raw("
                                    SELECT  
                                        count(prop_transactions.id) as total_tran,
                                        count(distinct prop_transactions.property_id) as total_hh,
                                        COALESCE(sum(prop_transactions.amount),0)as total_tran_amount,
                                        COALESCE(sum(advance_amount),0)as advance_amount,
                                        COALESCE(sum(adjust_amount),0)as adjust_amount,
                                        (COALESCE(sum(dtls.arrear_collection),0) + COALESCE(sum(penalty_rebate.penalty),0) - COALESCE(sum(penalty_rebate.shasti_rebate), 0) - COALESCE(sum(adjust_amount),0)) as arrear_collection,
                                        (COALESCE(sum(dtls.arrear_hh),0) ) as arrear_hh,
                                        (COALESCE(sum(dtls.current_hh),0) ) as current_hh,
                                        (COALESCE(sum(dtls.current_collection),0) -  COALESCE(sum(penalty_rebate.rebate),0)+COALESCE(sum(advance_amount),0))as current_collection
                                    FROM prop_transactions
                                    LEFT JOIN (
                                        SELECT 
                                            prop_transactions.id,
                                            count(distinct prop_transactions.property_id) as total_prop,
                                            sum(CASE WHEN prop_demands.fyear < '$currentfyear' THEN prop_tran_dtls.paid_total_tax  - paid_exempted_general_tax  ELSE 0 END) as arrear_collection,
                                            count(distinct CASE WHEN prop_demands.fyear < '$currentfyear' THEN prop_demands.property_id ELSE null END) as arrear_hh,
                                            sum(CASE WHEN prop_demands.fyear = '$currentfyear' THEN prop_tran_dtls.paid_total_tax  - paid_exempted_general_tax ELSE 0 END) as current_collection,
                                            count(distinct CASE WHEN prop_demands.fyear = '$currentfyear' THEN prop_demands.property_id ELSE null END) as current_hh,
                                            sum(prop_tran_dtls.paid_total_tax) as paid_total_tax 
                                        FROM prop_transactions
                                        JOIN prop_tran_dtls ON prop_tran_dtls.tran_id = prop_transactions.id
                                        JOIN prop_demands ON prop_demands.id = prop_tran_dtls.prop_demand_id
                                        WHERE prop_transactions.status IN (1,2) 
                                            AND prop_transactions.tran_type = 'Property'
                                            AND tran_date = ?
                                        GROUP BY  prop_transactions.id			
                                    ) dtls ON dtls.id = prop_transactions.id
                                    LEFT JOIN (
                                        SELECT 
                                            tran_id,
                                            sum(CASE WHEN is_rebate != true THEN prop_penaltyrebates.amount ELSE 0 END) as penalty,
                                            sum(
                                                CASE WHEN is_rebate = true And head_name != 'Shasti Abhay Yojana' THEN prop_penaltyrebates.amount ELSE 0 END
                                              ) as rebate, 
                                              sum(
                                                CASE WHEN is_rebate = true And head_name = 'Shasti Abhay Yojana' THEN prop_penaltyrebates.amount ELSE 0 END
                                              ) as shasti_rebate 
                                        FROM prop_penaltyrebates
                                        JOIN prop_transactions ON prop_transactions.id = prop_penaltyrebates.tran_id
                                        WHERE prop_transactions.status IN (1,2) 
                                            AND prop_transactions.tran_type =  'Property'
                                            AND prop_transactions.tran_date = ?
                                        GROUP BY prop_penaltyrebates.tran_id
                                    ) penalty_rebate ON penalty_rebate.tran_id = prop_transactions.id
                                    left join(
                                        select tran_id, sum(prop_advances.amount)as advance_amount
                                        from prop_advances
                                        JOIN prop_transactions ON prop_transactions.id = prop_advances.tran_id
                                        WHERE prop_transactions.status IN (1,2)
                                            AND prop_transactions.tran_date = ?
                                        GROUP BY prop_advances.tran_id
                                    )prop_advances ON prop_advances.tran_id = prop_transactions.id
                                    left join(
                                        select tran_id, sum(prop_adjustments.amount)as adjust_amount
                                        from prop_adjustments
                                        JOIN prop_transactions ON prop_transactions.id = prop_adjustments.tran_id
                                        WHERE prop_transactions.status IN (1,2)
                                            AND prop_transactions.tran_date = ?
                                        GROUP BY prop_adjustments.tran_id
                                    )prop_adjustments ON prop_adjustments.tran_id = prop_transactions.id
                                    WHERE prop_transactions.status IN (1,2)
                                        AND tran_date = ?
                                        "), [$currentDate, $currentDate, $currentDate, $currentDate, $currentDate]);


            $waterTransactionQuery = DB::connection("pgsql_water")->select(DB::raw("
                        select *
                        from (
                            select count(water_trans.id) as total_tran,
                            sum(fixed_demand_collection) as fixed_demand_collection,
                            sum(fixed_hh) as fixed_hh,
                            sum(meter_demand_collection) as meter_demand_collection,
                            sum(meter_hh) as meter_hh
                            from water_trans
                            join (
                                select water_trans.id as tran_id ,
                                    sum(
                                    case when upper(water_consumer_demands.connection_type) = upper('Fixed') or water_consumer_demands.connection_type is null 
                                        then water_tran_details.paid_amount else 0 end 
                                    )as fixed_demand_collection,
                                count(distinct(
                                    case when upper(water_consumer_demands.connection_type) = upper('Fixed') or water_consumer_demands.connection_type is null 
                                        then water_consumer_demands.consumer_id else null end 
                                    ))as fixed_hh,
                                sum(
                                    case when upper(water_consumer_demands.connection_type) = upper('Meter') 
                                        then water_tran_details.paid_amount else 0 end 
                                    )as meter_demand_collection,
                                count(distinct(
                                    case when upper(water_consumer_demands.connection_type) = upper('Meter') 
                                        then water_consumer_demands.consumer_id else null end 
                                    ))as meter_hh
                                from water_tran_details	
                                join water_trans on water_trans.id = water_tran_details.tran_id
                                left join water_consumer_demands  on water_consumer_demands.id = water_tran_details.demand_id
                                where water_trans.status in (1,2) 
                                    and water_trans.tran_date = '$currentDate'
                                    and water_tran_details.status = 1 
                                    and water_trans.tran_type = 'Demand Collection'
                                group by water_trans.id 
                            )detls on detls.tran_id = water_trans.id
                            where status in (1,2) 
                                and tran_date = '$currentDate'
                        )counsumers,
                        (
                            select count(water_trans.id) as total_app_tran,
                            count(distinct(related_id)) as app_hh,
                            COALESCE(sum(amount),0) as total_app_amount
                            from water_trans	
                            where status in (1,2) 
                                AND water_trans.tran_type != 'Demand Collection'
                        )application
                        "));

            $tradeTransactionQuery = DB::connection("pgsql_trade")->select(DB::raw("
                            SELECT  
                                license_types.application_type,
                                COALESCE(SUM(trade_count), 0) AS trade_count,
                                COALESCE(SUM(total_amount), 0) AS total_amount
                            FROM  
                                (SELECT DISTINCT application_type FROM trade_param_application_types) AS license_types
                            LEFT JOIN 
                                (SELECT DISTINCT 
                                    trade_param_application_types.application_type AS license_type,
                                    COUNT(DISTINCT trade_transactions.id) AS trade_count,
                                    SUM(trade_transactions.paid_amount) AS total_amount
                                FROM 
                                    trade_param_application_types
                                JOIN trade_transactions ON trade_transactions.tran_type = trade_param_application_types.application_type
                                WHERE 
                                    trade_transactions.status IN (1, 2) 
                                AND trade_transactions.tran_date = '$currentDate'
                                GROUP BY 
                                    license_type
                                ) AS trans ON trans.license_type = license_types.application_type
                            GROUP BY 
                                license_types.application_type
                        "));


            $marketTransactionQuery =  DB::connection("pgsql_advertisements")->select(DB::raw("
                            SELECT --id,
                                COUNT(mar_shop_payments.id) AS total_tran,
                                COUNT(DISTINCT mar_shop_payments.shop_id) AS total_shop,
                                COALESCE(SUM(mar_shop_payments.amount), 0) AS total_tran_amount,
                                COALESCE(SUM(dtls.arrear_collection), 0) AS arrear_collection,
                                COALESCE(SUM(dtls.arrear_count), 0) AS arrear_count,
                                COALESCE(SUM(dtls.current_count), 0) AS current_count,
                                COALESCE(SUM(dtls.current_collection), 0) AS current_collection
                            FROM 
                                mar_shop_payments
                            JOIN 
                                (
                                SELECT 
                                    --mar_shop_payments.id,
                                    mar_shop_payments.id as tran_id,
                                    COUNT(DISTINCT mar_shop_payments.shop_id) AS total_shop,
                                    COALESCE(SUM(CASE WHEN mar_shop_demands.financial_year < '$currentfyear' THEN mar_shop_demands.amount ELSE 0 END),0) AS arrear_collection,
                                    COUNT(DISTINCT CASE WHEN mar_shop_demands.financial_year < '$currentfyear' THEN mar_shop_payments.shop_id ELSE NULL END) AS arrear_count,
                                    COALESCE(SUM(CASE WHEN mar_shop_demands.financial_year = '$currentfyear' THEN mar_shop_demands.amount ELSE 0 END),0) AS current_collection,
                                    COUNT(DISTINCT CASE WHEN mar_shop_demands.financial_year = '$currentfyear' THEN mar_shop_payments.shop_id ELSE NULL END) AS current_count,
                                    COALESCE(SUM(mar_shop_payments.amount),0) AS paid_total_amount 
                                FROM 
                                    mar_shop_payments
                                JOIN 
                                    mar_shop_demands ON mar_shop_demands.tran_id = mar_shop_payments.id
                                WHERE mar_shop_payments.is_active = '1' 
                                GROUP BY 
                                    mar_shop_payments.id
                                ) AS dtls ON dtls.tran_id = mar_shop_payments.id
                                where mar_shop_payments.payment_date = '$currentDate'

                        "));




            $data = [
                "propDetails" => $propTransactionQuery,

                "waterDetails" => $waterTransactionQuery,
                "tradeDetails" => $tradeTransactionQuery,
                "propTax" => $propTax,
                "marketDeatails" => $marketTransactionQuery,
                "date" => $currentDate
            ];

            return responseMsgs(true, "Mpl Report Today Coll", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | written by Prity Pandey 
     * / code for akola livedashboard overall  collection
     */

    public function mplReportOverallCollection(Request $request)
    {
        try {
            $request->merge(["metaData" => ["pr111.1", 1.1, null, $request->getMethod(), null]]);
            $ulbId = $request->ulbId ?? 2;
            $currentDate = Carbon::now()->format("Y-m-d");
            $currentfyear = getFY();
            $fromDate = explode("-", $currentfyear)[0] . "-04-01";
            $uptoDate = explode("-", $currentfyear)[1] . "-03-31";

            $propTransactionQuery = DB::select(DB::raw("
                            SELECT  
                                assessment_types.assessment_type, 
                                COALESCE(SUM(t.total_hh), 0) AS total_hh,
                                COALESCE(SUM(t.total_amount), 0) AS total_amount,  
                                COALESCE(SUM(t.total_tran), 0) AS total_tran
                            FROM
                                (SELECT DISTINCT assessment_type FROM prop_safs) AS assessment_types  
                            LEFT JOIN 
                                (
                                    SELECT 
                                        DISTINCT(assessment_type) AS assessment_type,
                                        COUNT(DISTINCT(prop_transactions.saf_id)) AS total_hh,
                                        SUM(prop_transactions.amount) AS total_amount,
                                        COUNT(prop_transactions.id) AS total_tran
                                    FROM prop_safs
                                    JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id
                                    WHERE prop_transactions.status IN (1, 2) 
                                    and prop_transactions.tran_date between '$fromDate' and '$uptoDate'
                                    GROUP BY assessment_type
                                ) AS t
                                ON t.assessment_type = assessment_types.assessment_type
                            GROUP BY 
                                assessment_types.assessment_type
                        "));

            $propTax = DB::select(DB::raw("
                                SELECT  
                                    count(prop_transactions.id) as total_tran,
                                    count(distinct prop_transactions.property_id) as total_hh,
                                    COALESCE(sum(prop_transactions.amount),0)as total_tran_amount,
                                    COALESCE(sum(prop_advances.advance_amount),0)as advance_amount,
                                    COALESCE(sum(prop_adjustments.adjust_amount),0)as adjust_amount,
                                    sum(penalty_rebate.penalty) as penalty,
                                    sum(penalty_rebate.rebate) as rebate,
                                    (COALESCE(sum(dtls.arrear_collection),0) 
                                     + COALESCE(sum(penalty_rebate.penalty),0) 
                                    - COALESCE(sum(adjust_amount),0) ) as arrear_collection,
                                    (COALESCE(sum(dtls.arrear_hh),0) ) as arrear_hh,
                                    (COALESCE(sum(dtls.current_hh),0) ) as current_hh,
                                    ((COALESCE(sum(dtls.current_collection),0)+COALESCE(sum(prop_advances.advance_amount),0)) -  COALESCE(sum(penalty_rebate.rebate),0))as current_collection
                                FROM prop_transactions
                                LEFT JOIN (
                                    SELECT 
                                        prop_transactions.id,
                                        count(distinct prop_transactions.property_id) as total_prop,
                                        sum(CASE WHEN prop_demands.fyear < '$currentfyear' THEN prop_tran_dtls.paid_total_tax ELSE 0 END) as arrear_collection,
                                        count(distinct CASE WHEN prop_demands.fyear < '$currentfyear' THEN prop_demands.property_id ELSE null END) as arrear_hh,
                                        sum(CASE WHEN prop_demands.fyear = '$currentfyear' THEN prop_tran_dtls.paid_total_tax ELSE 0 END) as current_collection,
                                        count(distinct CASE WHEN prop_demands.fyear = '$currentfyear' THEN prop_demands.property_id ELSE null END) as current_hh,
                                        sum(prop_tran_dtls.paid_total_tax) as paid_total_tax 
                                    FROM prop_transactions
                                    JOIN prop_tran_dtls ON prop_tran_dtls.tran_id = prop_transactions.id
                                    JOIN prop_demands ON prop_demands.id = prop_tran_dtls.prop_demand_id
                                    WHERE prop_transactions.status IN (1,2) 
                                        AND prop_transactions.tran_type = 'Property'
                                        and prop_transactions.tran_date  between '$fromDate' and '$uptoDate'
                                      
                
                                    GROUP BY  prop_transactions.id			
                                ) dtls ON dtls.id = prop_transactions.id
                                LEFT JOIN (
                                    SELECT 
                                        tran_id,
                                        sum(CASE WHEN is_rebate != true THEN prop_penaltyrebates.amount ELSE 0 END) as penalty,
                                        sum(CASE WHEN is_rebate = true THEN prop_penaltyrebates.amount ELSE 0 END) as rebate
                                    FROM prop_penaltyrebates
                                    JOIN prop_transactions ON prop_transactions.id = prop_penaltyrebates.tran_id
                                    WHERE prop_transactions.status IN (1,2) 
                                        AND prop_transactions.tran_type =  'Property'
                                        and prop_transactions.tran_date between '$fromDate' and '$uptoDate'
                                        
                
                                    GROUP BY prop_penaltyrebates.tran_id
                                ) penalty_rebate ON penalty_rebate.tran_id = prop_transactions.id
                                left join(
                                    select tran_id, sum(prop_advances.amount)as advance_amount
                                    from prop_advances
                                    JOIN prop_transactions ON prop_transactions.id = prop_advances.tran_id
                                    WHERE prop_transactions.status IN (1,2)
                                        and prop_transactions.tran_date between '$fromDate' and '$uptoDate'
                                     
                
                                    GROUP BY prop_advances.tran_id
                                )prop_advances ON prop_advances.tran_id = prop_transactions.id
                                left join(
                                    select tran_id, sum(prop_adjustments.amount)as adjust_amount
                                    from prop_adjustments
                                    JOIN prop_transactions ON prop_transactions.id = prop_adjustments.tran_id
                                    WHERE prop_transactions.status IN (1,2)
                                        and prop_transactions.tran_date  between '$fromDate' and '$uptoDate'
                                        
                
                                    GROUP BY prop_adjustments.tran_id
                                )prop_adjustments ON prop_adjustments.tran_id = prop_transactions.id
                                WHERE prop_transactions.status IN (1,2)
                                    and prop_transactions.tran_date  between '$fromDate' and '$uptoDate'
                                  
                
                        "));


            $waterTransactionQuery = DB::connection("pgsql_water")->select(DB::raw("
                                select *
                                from(
                                    select count(water_trans.id) as total_tran,
                                    sum(fixed_demand_collection) as fixed_demand_collection,
                                    sum(fixed_hh) as fixed_hh,
                                    sum(meter_demand_collection) as meter_demand_collection,
                                    sum(meter_hh) as meter_hh
                                from water_trans
                                join (
                                    select water_trans.id as tran_id ,
                                        sum(
                                        case when upper(water_consumer_demands.connection_type) = upper('Fixed') or water_consumer_demands.connection_type is null 
                                            then water_tran_details.paid_amount else 0 end 
                                        )as fixed_demand_collection,
                                    count(distinct(
                                        case when upper(water_consumer_demands.connection_type) = upper('Fixed') or water_consumer_demands.connection_type is null 
                                            then water_consumer_demands.consumer_id else null end 
                                        ))as fixed_hh,
                                    sum(
                                        case when upper(water_consumer_demands.connection_type) = upper('Meter') 
                                            then water_tran_details.paid_amount else 0 end 
                                        )as meter_demand_collection,
                                    count(distinct(
                                        case when upper(water_consumer_demands.connection_type) = upper('Meter') 
                                            then water_consumer_demands.consumer_id else null end 
                                        ))as meter_hh
                                    from water_tran_details	
                                    join water_trans on water_trans.id = water_tran_details.tran_id
                                    left join water_consumer_demands  on water_consumer_demands.id = water_tran_details.demand_id
                                    where water_trans.status in (1,2) and water_trans.tran_date   between '$fromDate' and '$uptoDate'
                                    
                                        and water_tran_details.status =1 and water_trans.tran_type = 'Demand Collection'
                                    group by water_trans.id 
                                )detls on detls.tran_id = water_trans.id
                                where status in (1,2) and water_trans.tran_date  between '$fromDate' and '$uptoDate'
                               
                                )counsumers,
                                (
                                    select count(water_trans.id) as total_app_tran,
                                    count(distinct(related_id)) as app_hh,
                                    COALESCE(sum(amount),0) as total_app_amount
                                    from water_trans	
                                    where status in (1,2) and water_trans.tran_date   between '$fromDate' and '$uptoDate'
                                    
                                        AND water_trans.tran_type != 'Demand Collection'
                                )application
                        "));

            $tradeTransactionQuery = DB::connection("pgsql_trade")->select(DB::raw("
                            
                                SELECT  
                                    license_types.application_type,
                                    COALESCE(SUM(trade_count), 0) AS trade_count,
                                    COALESCE(SUM(total_amount), 0) AS total_amount
                                FROM  
                                    (SELECT DISTINCT application_type FROM trade_param_application_types) AS license_types
                                LEFT JOIN 
                                    (SELECT DISTINCT 
                                        trade_param_application_types.application_type AS license_type,
                                        COUNT(DISTINCT trade_transactions.id) AS trade_count,
                                        SUM(trade_transactions.paid_amount) AS total_amount
                                    FROM 
                                        trade_param_application_types
                                    JOIN trade_transactions ON trade_transactions.tran_type = trade_param_application_types.application_type
                                    WHERE 
                                        trade_transactions.status IN (1, 2) 
                                    AND trade_transactions.tran_date  between '$fromDate' and '$uptoDate'
                                 
                                    GROUP BY 
                                        license_type
                                    ) AS trans ON trans.license_type = license_types.application_type
                                GROUP BY 
                                    license_types.application_type
                        "));


            $marketTransactionQuery =  DB::connection("pgsql_advertisements")->select(DB::raw("
                            SELECT 
                                COUNT(mar_shop_payments.id) AS total_tran,
                                COUNT(DISTINCT mar_shop_payments.shop_id) AS total_shop,
                                COALESCE(SUM(mar_shop_payments.amount), 0) AS total_tran_amount,
                                COALESCE(SUM(dtls.arrear_collection), 0) AS arrear_collection,
                                COALESCE(SUM(dtls.arrear_count), 0) AS arrear_count,
                                COALESCE(SUM(dtls.current_count), 0) AS current_count,
                                COALESCE(SUM(dtls.current_collection), 0) AS current_collection
                            FROM 
                                mar_shop_payments
                            JOIN 
                                (
                                SELECT 
                                    mar_shop_payments.id as tran_id,
                                    COUNT(DISTINCT mar_shop_payments.shop_id) AS total_shop,
                                    COALESCE(SUM(CASE WHEN mar_shop_demands.financial_year < '$currentfyear' THEN mar_shop_demands.amount ELSE 0 END),0) AS arrear_collection,
                                    COUNT(DISTINCT CASE WHEN mar_shop_demands.financial_year < '$currentfyear' THEN mar_shop_payments.shop_id ELSE NULL END) AS arrear_count,
                                    COALESCE(SUM(CASE WHEN mar_shop_demands.financial_year = '$currentfyear' THEN mar_shop_demands.amount ELSE 0 END),0) AS current_collection,
                                    COUNT(DISTINCT CASE WHEN mar_shop_demands.financial_year = '$currentfyear' THEN mar_shop_payments.shop_id ELSE NULL END) AS current_count,
                                    COALESCE(SUM(mar_shop_payments.amount),0) AS paid_total_amount 
                                FROM 
                                    mar_shop_payments
                                JOIN 
                                    mar_shop_demands ON mar_shop_demands.tran_id = mar_shop_payments.id
                                WHERE mar_shop_payments.is_active = '1' 
                                GROUP BY 
                                    mar_shop_payments.id
                                ) AS dtls ON dtls.tran_id = mar_shop_payments.id
                                where mar_shop_payments.payment_date  between '$fromDate' and '$uptoDate'
                               

                        "));




            $data = [
                "propDetails" => $propTransactionQuery,

                "waterDetails" => $waterTransactionQuery,
                "tradeDetails" => $tradeTransactionQuery,
                "propTax" => $propTax,
                "marketDeatails" => $marketTransactionQuery,
                "date" => $currentDate
            ];

            return responseMsgs(true, "Mpl Report Today Coll", $data, "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | End of Prity Pandey Code
     */


    public function AprovedRejectList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "appType" => "required|in:REJECTED,APPROVED",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        $request->merge(["metaData" => ["pr112.1", 1.1, null, $request->getMethod(), null,]]);
        $metaData = collect($request->metaData)->all();
        list($apiId, $version, $queryRunTime, $action, $deviceId) = $metaData;
        try {
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $ulbId = $userId = $key = $assessmentType = $wardId = $zoneId = null;
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
            if ($request->assessmentType) {
                $assessmentType = $request->assessmentType;
            }
            if ($request->key) {
                $key = trim($request->key);
            }
            $data = $this->_DB_READ->table("prop_safs")
                ->leftjoin(DB::raw(
                    "(
                select string_agg(owner_name,',') as owner_name,
                    string_agg(guardian_name,',') as guardian_name,
                    string_agg(mobile_no::text,',') as mobile_no,
                    string_agg(owner_name_marathi,',') as owner_name_marathi,
                    string_agg(guardian_name_marathi,',') as guardian_name_marathi,
                    saf_id
                from prop_safs_owners
                where status =1
                group by saf_id
            )owners"
                ), "owners.saf_id", "prop_safs.id");
            if ($request->appType == "REJECTED") {
                $data = $this->_DB_READ->table("prop_rejected_safs as prop_safs")
                    ->leftjoin(DB::raw(
                        "(
                    select string_agg(owner_name,',') as owner_name,
                        string_agg(guardian_name,',') as guardian_name,
                        string_agg(mobile_no::text,',') as mobile_no,
                        string_agg(owner_name_marathi,',') as owner_name_marathi,
                        string_agg(guardian_name_marathi,',') as guardian_name_marathi,
                        saf_id
                    from prop_rejected_safs_owners
                    where status =1
                    group by saf_id
                )owners"
                    ), "owners.saf_id", "prop_safs.id");
            }

            $data = $data->select(
                DB::raw("prop_safs.id as saf_id, prop_properties.id as prop_id,prop_safs.saf_no,
                            prop_safs.assessment_type, 	
                            prop_properties.holding_no,
                            prop_properties.property_no,
                            prop_properties.prop_address,
                            ulb_ward_masters.ward_name,
                            zone_masters.zone_name,
                            owners.owner_name,
                            owners.guardian_name,
                            owners.mobile_no,
                            owners.owner_name_marathi,
                            owners.guardian_name_marathi,
                            TO_CHAR(prop_safs.application_date, 'DD-MM-YYYY') as application_date,
                            TO_CHAR(workflow_tracks.track_date, 'DD-MM-YYYY') as approve_reject_date,
                            workflow_tracks.message as reason,
                            users.name as user_name
                            ")
            )

                ->join("workflow_tracks", "workflow_tracks.ref_table_id_value", "prop_safs.id")
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "prop_safs.ward_mstr_id")
                ->join("zone_masters", "zone_masters.id", "prop_safs.zone_mstr_id")
                ->join(DB::raw("
                            (
                                select max(id) as id
                                from workflow_tracks
                                where status = true
                                    and ref_table_dot_id = 'prop_active_safs.id'
                                group by ref_table_dot_id, ref_table_id_value
                            )lasts
                        "), "lasts.id", "workflow_tracks.id")
                ->leftjoin("prop_properties", "prop_properties.saf_id", "prop_safs.id")
                ->join("users", "users.id", "workflow_tracks.user_id")
                ->where("prop_safs.status", 1)
                ->whereBetween(DB::raw("cast(workflow_tracks.track_date as date)"), [$fromDate, $uptoDate]);
            if ($key) {
                $key = trim($key);
                $data = $data->where(function ($query) use ($key) {
                    $query->orwhere('prop_properties.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("prop_properties.property_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('prop_safs.saf_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owners.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owners.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owners.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            if ($userId) {
                $data = $data->where("users.id", $userId);
            }
            if ($wardId) {
                $data = $data->where("ulb_ward_masters.id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("zone_masters.id", $zoneId);
            }
            if ($assessmentType) {
                $data = $data->where("prop_safs.assessment_type", $assessmentType);
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
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function tranDeactivatedList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "ulbId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId" => "nullable|digits_between:1,9223372036854775807",
                "appType" => "nullable|in:PROPERTY,SAF",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        return $this->Repository->tranDeactivatedList($request);
    }

    /**
     * | Maker Checker Report  
     */
    public function makerChecker(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "userType" => "required|in:maker,checker",
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "wardId"   => "nullable|digits_between:1,9223372036854775807",
                "zoneId"   => "nullable|digits_between:1,9223372036854775807",
                "userId"   => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {

            $perPage  = $request->perPage;
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $userId   = $wardId = $zoneId = null;

            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }

            $mPropPropertyUpdateRequest =  new PropPropertyUpdateRequest();
            $data =  $mPropPropertyUpdateRequest->readConnection()->updateDetails();

            #maker
            if ($request->userType == 'maker') {
                $data =  $data
                    ->whereBetween('prop_property_update_requests.created_at', [$fromDate, $uptoDate]);

                if ($userId)
                    $data = $data->where("prop_property_update_requests.user_id", $userId);
            }

            #checker
            if ($request->userType == 'checker') {
                $data = $data
                    ->whereBetween('prop_property_update_requests.approval_date', [$fromDate, $uptoDate]);

                if ($userId)
                    $data = $data->where("prop_property_update_requests.approved_by", $userId);
            }

            if ($wardId) {
                $data = $data->where("prop_property_update_requests.ward_mstr_id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("prop_property_update_requests.zone_mstr_id", $zoneId);
            }
            $data = $data->paginate($perPage);

            return responseMsgs(true, "Data Retreived", $data, "", "", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }

        // ->paginate($perPage);
    }

    /**
     * | Maker Checker Summary  
     */
    public function makerCheckerSummary(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "required|date|date_format:Y-m-d",
                "uptoDate" => "required|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "wardId"   => "nullable|digits_between:1,9223372036854775807",
                "zoneId"   => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {

            $perPage  = $request->perPage;
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $userId   = $wardId = $zoneId = null;

            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            $makerCount = PropPropertyUpdateRequest::readConnection()->selectRaw('user_id,name as user_name, COUNT(prop_property_update_requests.*) as count')
                ->whereBetween('prop_property_update_requests.created_at', [$fromDate, $uptoDate])
                ->join('users', 'users.id', 'prop_property_update_requests.user_id')
                ->groupBy('user_id', 'name')
                ->orderby('name');

            if ($zoneId) {
                $makerCount = $makerCount->where("prop_property_update_requests.zone_mstr_id", $zoneId);
            }
            if ($wardId) {
                $makerCount = $makerCount->where("prop_property_update_requests.ward_mstr_id", $wardId);
            }
            $makerCount =  $makerCount
                ->get();

            $checkerCount = PropPropertyUpdateRequest::readConnection()->selectRaw('user_id,name as user_name, COUNT(prop_property_update_requests.*) as count')
                ->whereBetween('prop_property_update_requests.approval_date', [$fromDate, $uptoDate])
                ->join('users', 'users.id', 'prop_property_update_requests.user_id')
                ->groupBy('user_id', 'name')
                ->orderby('name');

            if ($zoneId) {
                $checkerCount = $checkerCount->where("prop_property_update_requests.zone_mstr_id", $zoneId);
            }
            if ($wardId) {
                $checkerCount = $checkerCount->where("prop_property_update_requests.ward_mstr_id", $wardId);
            }
            $checkerCount =  $checkerCount
                ->get();

            $rejectedCount = PropPropertyUpdateRequest::readConnection()->selectRaw('user_id,name as user_name, COUNT(prop_property_update_requests.*) as count,pending_status')
                ->whereBetween('prop_property_update_requests.approval_date', [$fromDate, $uptoDate])
                ->join('users', 'users.id', 'prop_property_update_requests.user_id')
                ->groupBy('user_id', 'name', 'pending_status')
                ->orderby('name');

            if ($zoneId) {
                $rejectedCount = $rejectedCount->where("prop_property_update_requests.zone_mstr_id", $zoneId);
            }
            if ($wardId) {
                $rejectedCount = $rejectedCount->where("prop_property_update_requests.ward_mstr_id", $wardId);
            }
            $rejectedCount =  $rejectedCount
                ->get();

            // $rejectedCount = PropPropertyUpdateRequest::selectRaw('user_id,name as user_name, COUNT(prop_property_update_requests.*) as count,pending_status')
            //     ->whereBetween('prop_property_update_requests.approval_date', [$fromDate, $uptoDate])
            //     ->join('users', 'users.id', 'prop_property_update_requests.user_id')
            //     ->groupBy('user_id', 'name', 'pending_status')
            //     ->orderby('name');

            // if ($zoneId) {
            //     $rejectedCount = $rejectedCount->where("prop_property_update_requests.zone_mstr_id", $zoneId);
            // }
            // if ($wardId) {
            //     $rejectedCount = $rejectedCount->where("prop_property_update_requests.ward_mstr_id", $wardId);
            // }
            // $rejectedCount =  $rejectedCount
            //     ->get();

            $rejectedCount = collect($checkerCount)->where('pending_status', 4)->values();

            $data['checker_count']  = $checkerCount;
            $data['maker_count']    = $makerCount;
            $data['rejected_count'] = $rejectedCount;
            $data['maker_total'] = $makerCount->sum('count');
            $data['checker_total'] = $checkerCount->sum('count');
            $data['rejected_total'] = $rejectedCount->sum('count');

            return responseMsgs(true, "Data Retreived", $data, "", "", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }


    /**
     * | Maker Checker List
     */
    public function makerCheckerUserList(Request $request)
    {
        try {

            $mPropPropertyUpdateRequest =  new PropPropertyUpdateRequest();
            $mUser =  new User();
            $makerId = $mPropPropertyUpdateRequest->readConnection()
                ->distinct()->pluck('user_id');

            $checkerId = $mPropPropertyUpdateRequest->readConnection()
                ->distinct()->pluck('approved_by');

            $userId = collect($makerId)->merge($checkerId)->filter(function ($value) {
                return $value !== null;
            })->unique()->values()->all();

            $data =  $mUser->select('id', 'name as user_name',  'user_type')->whereIn('id', $userId)->get();

            return responseMsgs(true, "Data Retreived", $data, "", "", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    /**
     * | Paid Transfer Fee
     */
    public function paidTransferFee(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate"    => "required|date|date_format:Y-m-d",
                "uptoDate"    => "required|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "wardId"      => "nullable|digits_between:1,9223372036854775807",
                "zoneId"      => "nullable|digits_between:1,9223372036854775807",
                "paymentMode" => "nullable",
                "type"        => "nullable|in:Mutation,Bifurcation",
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {

            $mPropTransaction =  new PropTransaction();
            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $paymentMode = $wardId = $zoneId = null;

            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->paymentMode) {
                $paymentMode = strtoupper($request->paymentMode);
            }

            $bata = $mPropTransaction->readConnection()
                ->select(DB::raw('SUM(amount) as amount'), 'transfer_mode_mstr_id', 'transfer_mode', 'assessment_type')
                ->join('prop_safs', 'prop_safs.id', 'prop_transactions.saf_id')
                ->join('ref_prop_transfer_modes', 'ref_prop_transfer_modes.id', 'prop_safs.transfer_mode_mstr_id')
                ->whereBetween('prop_transactions.tran_date', [$fromDate, $uptoDate])
                ->where('tran_type', 'Saf Proccess Fee')
                ->where('prop_transactions.status', 1)
                ->groupBy('transfer_mode_mstr_id', 'transfer_mode', 'assessment_type');
            // ->get();

            if ($wardId) {
                $bata = $bata->where("prop_safs.ward_mstr_id", $wardId);
            }
            if ($zoneId) {
                $bata = $bata->where("prop_safs.zone_mstr_id", $zoneId);
            }
            if ($paymentMode) {
                $bata = $bata->where("prop_transactions.payment_mode", $paymentMode);
            }

            $bata = $bata
                ->get();

            if ($request->type)
                $bata = collect($bata)->where('assessment_type', $request->type)->values();

            if (!$paymentMode)
                $paymentMode = "Cash/Cheque/DD/Online";

            $data['data'] = $bata;
            $data['total'] = collect($bata)->sum('amount');
            $data['payment_mode'] = $paymentMode;
            $data['from_date'] = Carbon::parse($fromDate)->format('d-m-Y');
            $data['upto_date'] = Carbon::parse($uptoDate)->format('d-m-Y');

            return responseMsgs(true, "Data Retreived", $data, "", "", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    /**
     * | assessmentWiseReport
     */
    public function assessmentWiseReport(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate"    => "required|date|date_format:Y-m-d",
                "uptoDate"    => "required|date|date_format:Y-m-d|after_or_equal:" . $request->fromDate,
                "wardId"      => "nullable|digits_between:1,9223372036854775807",
                "zoneId"      => "nullable|digits_between:1,9223372036854775807",
                "assessmentType"      => "nullable|in:Mutation,Bifurcation,New Assessment,Reassessment",
                // "type"        => "nullable|in:Mutation,Bifurcation",
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {

            $fromDate = $request->fromDate;
            $uptoDate = $request->uptoDate;
            $perPage  = $request->perPage ?? 10;
            $wardId = $zoneId = null;
            $assessmentType = $request->assessmentType;

            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }

            $data = PropActiveSaf::readConnection()->select(
                'prop_active_safs.id',
                'zone_name',
                'ward_name',
                'saf_no',
                'assessment_type',
                // 'users.name as applied_by',
                // 'role.role_name as applied_by_role',
                // 'application_date',
                'wf_roles.role_name as current_role',
                DB::raw("concat(users.name,' (',role.role_name,')') as applied_by"),
                DB::raw("TO_CHAR(application_date, 'DD-MM-YYYY') as application_date"),
            )
                ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                ->join('users', 'users.id', 'prop_active_safs.user_id')
                ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_active_safs.ward_mstr_id')
                ->join('zone_masters', 'zone_masters.id', 'prop_active_safs.zone_mstr_id')
                ->leftJoin('wf_roleusermaps', function ($join) {
                    $join->on('wf_roleusermaps.user_id', '=', 'prop_active_safs.user_id')
                        ->where('wf_roleusermaps.is_suspended', false);
                })
                ->join('wf_roles as role', 'role.id', 'wf_roleusermaps.wf_role_id')
                ->whereBetween('application_date', [$fromDate, $uptoDate])
                ->when(!empty($assessmentType), function ($query) use ($assessmentType) {
                    return $query->where("assessment_type", $assessmentType);
                })
                ->orderBy('zone')
                ->orderBy('ward_name');

            if ($wardId) {
                $data = $data->where("prop_active_safs.ward_mstr_id", $wardId);
            }
            if ($zoneId) {
                $data = $data->where("prop_active_safs.zone_mstr_id", $zoneId);
            }

            $data = $data->paginate($perPage);

            return responseMsgs(true, "Data Retreived", $data, "", "", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function zoneWishDailyColl(Request $request)
    {
        $ruls = [
            "fromDate"  => "required|date|date_format:Y-m-d|before_or_equal:" . Carbon::now()->format('Y-m-d'),
            "uptoDate"  => "required|date|date_format:Y-m-d|after_or_equal:" . Carbon::parse($request->fromDate)->format('Y-m-d'),
            "zonId"     => "nullable|digits_between:1,9223372036854775807",
        ];
        $validated = Validator::make($request->all(), $ruls);

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }
        try {
            $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
            $zoneId = null;
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            $fromFyear = getFY($fromDate);
            $uptoFyear = getFY($uptoDate);
            $sql = "
                SELECT zone_masters.id AS id,
                    zone_masters.zone_name AS zone_name, 
                    SUM(COALESCE(demands.current_demand_hh, 0::numeric)) AS current_demand_hh,   
                    SUM(COALESCE(demands.arrear_demand_hh, 0::numeric)) AS arrear_demand_hh,
                    SUM(COALESCE(collection.current_collection_hh, 0::numeric)) AS current_collection_hh,   
                    SUM(COALESCE(collection.arrear_collection_hh, 0::numeric)) AS arrear_collection_hh,
                    SUM(COALESCE(collection.collection_from_no_of_hh, 0::numeric)) AS collection_from_hh,
                
                    round(SUM((COALESCE(collection.arrear_collection_hh, 0::numeric) / (case when demands.arrear_demand_hh > 0 then demands.arrear_demand_hh else 1 end))*100),2) AS arrear_hh_eff,
                    round(SUM((COALESCE(collection.current_collection_hh, 0::numeric) / (case when demands.current_demand_hh > 0 then demands.current_demand_hh else 1 end))*100),2) AS current_hh_eff,
                
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
                
                    round(SUM((COALESCE(collection.arrear_collection, 0::numeric) / (case when demands.arrear_demand > 0 then demands.arrear_demand else 1 end))*100),2) AS arrear_eff,
                    round(SUM((COALESCE(collection.current_collection, 0::numeric) / (case when demands.current_demand > 0 then demands.current_demand else 1 end))*100),2) AS current_eff,
                
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
                
                FROM zone_masters 
                LEFT JOIN(
                    SELECT prop_properties.zone_mstr_id,
                        COUNT(
                            DISTINCT ( CASE WHEN prop_demands.fyear  >= '$fromFyear'  then prop_demands.property_id END)
                        ) as current_demand_hh,
                        SUM( CASE WHEN prop_demands.fyear >= '$fromFyear' then prop_demands.total_tax  ELSE 0 END ) AS current_demand,
                        COUNT(
                            DISTINCT ( CASE WHEN prop_demands.fyear < '$fromFyear' then prop_demands.property_id END)
                        ) as arrear_demand_hh,
                        SUM( CASE WHEN prop_demands.fyear < '$fromFyear' then prop_demands.total_tax ELSE 0 END ) AS arrear_demand,
                        SUM(total_tax) AS total_demand
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    WHERE prop_demands.status =1 
                        AND prop_demands.fyear <= '$uptoFyear'
                    GROUP BY prop_properties.zone_mstr_id
                )demands ON demands.zone_mstr_id = zone_masters.id
                LEFT JOIN (
                    SELECT prop_properties.zone_mstr_id,
                        COUNT(
                            DISTINCT (CASE WHEN prop_demands.fyear  >= '$fromFyear'  then prop_demands.property_id END)
                        ) as current_collection_hh,
                
                        COUNT(DISTINCT(prop_properties.id)) AS collection_from_no_of_hh,
                        SUM( CASE WHEN prop_demands.fyear  >= '$fromFyear' then prop_tran_dtls.paid_total_tax ELSE 0 END ) AS current_collection,
                
                        COUNT(
                            DISTINCT ( CASE WHEN prop_demands.fyear < '$fromFyear' then prop_demands.property_id END )
                        ) as arrear_collection_hh,
                
                        SUM(
                            CASE when prop_demands.fyear < '$fromFyear' then prop_tran_dtls.paid_total_tax ELSE 0 END
                        ) AS arrear_collection,
                        SUM(prop_tran_dtls.paid_total_tax) AS total_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_transactions.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND prop_demands.fyear <= '$uptoFyear'
                    GROUP BY prop_properties.zone_mstr_id
                )collection ON collection.zone_mstr_id = zone_masters.id
                LEFT JOIN ( 
                    SELECT prop_properties.zone_mstr_id,
                        SUM(prop_tran_dtls.paid_total_tax) AS total_prev_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_transactions.tran_date < '$fromDate'
                    GROUP BY prop_properties.zone_mstr_id
                )prev_collection ON prev_collection.zone_mstr_id = zone_masters.id
                WHERE 1=1 
                    " . ($zoneId ? " AND zone_masters.id = $zoneId" : "") . "
                GROUP BY zone_masters.id ,zone_masters.zone_name 
                ORDER BY zone_masters.id
            ";


            $online = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.zone_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHERE(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER('ONLINE')"))
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $doorToDoor = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.zone_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->JOIN(DB::RAW("(                                        
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
                    GROUP BY wf_roleusermaps.user_id
                    ORDER BY wf_roleusermaps.user_id
                ) collecter"), "collecter.role_user_id", "prop_transactions.user_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHERE(DB::RAW("UPPER(prop_transactions.payment_mode)"), "<>", DB::RAW("UPPER('ONLINE')"))
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $jsk = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.zone_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->JOIN(DB::RAW("(                                        
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
                    GROUP BY wf_roleusermaps.user_id
                    ORDER BY wf_roleusermaps.user_id
                ) collecter"), "collecter.role_user_id", "prop_transactions.user_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHERE(DB::RAW("UPPER(prop_transactions.payment_mode)"), "<>", DB::RAW("UPPER('ONLINE')"))
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $totalTrans = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.zone_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $penaltyRebates = $this->_DB_READ->table("prop_penaltyrebates")
                ->select(
                    DB::raw("prop_properties.zone_mstr_id ,
                                    COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                                    COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                                    COUNT(DISTINCT(prop_transactions.id)) AS tran_count, 
                                    SUM( CASE WHEN prop_penaltyrebates.is_rebate = TRUE then COALESCE(prop_penaltyrebates.amount, 0) else 0 end) AS rebate_amount,
                                    SUM( CASE WHEN prop_penaltyrebates.is_rebate = false then COALESCE(prop_penaltyrebates.amount, 0) else 0 end) AS penalty_amount
                                ")
                )
                ->join("prop_transactions", "prop_transactions.id", "prop_penaltyrebates.tran_id")
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            if ($zoneId) {
                $online = $online->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $doorToDoor = $doorToDoor->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $jsk =  $jsk->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $totalTrans =  $totalTrans->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $penaltyRebates = $penaltyRebates->WHERE("prop_properties.zone_mstr_id", $zoneId);
            }



            $online = $online->GROUPBY("prop_properties.zone_mstr_id");
            $doorToDoor =  $doorToDoor->GROUPBY("prop_properties.zone_mstr_id");
            $jsk = $jsk->GROUPBY("prop_properties.zone_mstr_id");
            $totalTrans = $totalTrans->GROUPBY("prop_properties.zone_mstr_id");
            $penaltyRebates = $penaltyRebates->GROUPBY("prop_properties.zone_mstr_id");

            $online = $online->get();
            $doorToDoor = $doorToDoor->get();
            $jsk        = $jsk->get();
            $totalTrans  = $totalTrans->get();
            $penaltyRebates = $penaltyRebates->get();



            $dcbData = collect($this->_DB_READ->select($sql))->map(function ($val) use ($online, $doorToDoor, $jsk, $totalTrans) {
                $tcTran = collect($doorToDoor)->where("zone_mstr_id", $val->id);
                $counterTran = collect($jsk)->where("zone_mstr_id", $val->id);
                $onlineTran = collect($online)->where("zone_mstr_id", $val->id);
                $totalTran  = collect($totalTrans)->where("zone_mstr_id", $val->id);
                $val->total_demand = $val->arrear_demand + $val->current_demand;
                $val->total_collection = $val->arrear_collection + $val->current_collection;
                $val->arrear_due = $val->old_due;
                $val->tc_tran_count = $tcTran->sum("tran_count");
                $val->counter_tran_count = $counterTran->sum("tran_count");
                $val->online_tran_count = $onlineTran->sum("tran_count");
                $val->total_tran_count = $totalTran->sum("tran_count");
                return $val;
            });
            $granTax = [
                "id"                    =>  0,
                "zone_name"             =>  "Grand Tax",
                "current_demand_hh"     =>  roundFigure($dcbData->sum("current_demand_hh")),
                "arrear_demand_hh"      =>  roundFigure($dcbData->sum("arrear_demand_hh")),
                "current_collection_hh"      =>  roundFigure($dcbData->sum("current_collection_hh")),
                "arrear_collection_hh"      =>  roundFigure($dcbData->sum("arrear_collection_hh")),
                "collection_from_hh"      =>  roundFigure($dcbData->sum("collection_from_hh")),
                "arrear_hh_eff"      =>  roundFigure($dcbData->sum("arrear_hh_eff")),
                "current_hh_eff"      =>  roundFigure($dcbData->sum("current_hh_eff")),
                "balance_hh"      =>  roundFigure($dcbData->sum("balance_hh")),
                "arrear_demand"      =>  roundFigure($dcbData->sum("arrear_demand")),
                "previous_collection"      =>  roundFigure($dcbData->sum("previous_collection")),
                "current_demand"      =>  roundFigure($dcbData->sum("current_demand")),
                "arrear_collection"      =>  roundFigure($dcbData->sum("arrear_collection")),
                "current_collection"      =>  roundFigure($dcbData->sum("current_collection")),
                "old_due"      =>  roundFigure($dcbData->sum("old_due")),
                "arrear_due"      =>  roundFigure($dcbData->sum("arrear_due")),
                "current_due"      =>  roundFigure($dcbData->sum("current_due")),
                "current_balance_hh"      =>  roundFigure($dcbData->sum("current_balance_hh")),
                "arrear_balance_hh"      =>  roundFigure($dcbData->sum("arrear_balance_hh")),
                "arrear_eff"      =>  roundFigure($dcbData->sum("arrear_eff")),
                "current_eff"      =>  roundFigure($dcbData->sum("current_eff")),
                "outstanding"      =>  roundFigure($dcbData->sum("outstanding")),
                "total_demand"      =>  roundFigure($dcbData->sum("total_demand")),
                "total_collection"      =>  roundFigure($dcbData->sum("total_collection")),
                "tc_tran_count"      =>  roundFigure($dcbData->sum("tc_tran_count")),
                "counter_tran_count"      =>  roundFigure($dcbData->sum("counter_tran_count")),
                "online_tran_count"      =>  roundFigure($dcbData->sum("online_tran_count")),
                "total_tran_count"      =>  roundFigure($dcbData->sum("total_tran_count")),
            ];
            $penalties = [
                "id"                    =>  0,
                "zone_name"             =>  "Total Intrest",
                "current_demand_hh"     =>  "---",
                "arrear_demand_hh"      =>  "---",
                "current_collection_hh"      =>  "---",
                "arrear_collection_hh"      =>  "---",
                "collection_from_hh"      =>  "---",
                "arrear_hh_eff"      =>  "---",
                "current_hh_eff"      =>  "---",
                "balance_hh"      =>  "---",
                "arrear_demand"      =>  "---",
                "previous_collection"      =>  "---",
                "current_demand"      => "---",
                "arrear_collection"      =>  roundFigure($penaltyRebates->sum("penalty_amount")),
                "current_collection"      =>  "---", #$penaltyRebates->sum("rebate_amount"),
                "old_due"      => "---",
                "arrear_due"      =>  "---",
                "current_due"      =>  "---",
                "current_balance_hh"      =>  "---",
                "arrear_balance_hh"      =>  "---",
                "arrear_eff"      =>  "---",
                "current_eff"      =>  "---",
                "outstanding"      =>  "---",
                "total_demand"      =>  "---",
                "total_collection"      =>  roundFigure($penaltyRebates->sum("penalty_amount")), #+ $penaltyRebates->sum("rebate_amount"),
                "tc_tran_count"      =>  "---",
                "counter_tran_count"      =>  "---",
                "online_tran_count"      =>  "---",
                "total_tran_count"      =>  roundFigure($dcbData->sum("tran_count")),
            ];
            $dcbData->push($granTax, $penalties, $granTax);

            return responseMsgs(true, "Zone Wise daily Collecton Report", $dcbData);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function wardWishDailyColl(Request $request)
    {
        $ruls = [
            "fromDate"  => "required|date|date_format:Y-m-d|before_or_equal:" . Carbon::now()->format('Y-m-d'),
            "uptoDate"  => "required|date|date_format:Y-m-d|after_or_equal:" . Carbon::parse($request->fromDate)->format('Y-m-d'),
            "zonId"     => "nullable|digits_between:1,9223372036854775807",
            "wardId"    => "nullable|digits_between:1,9223372036854775807",
        ];
        $validated = Validator::make($request->all(), $ruls);

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }
        try {
            $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
            $zoneId =  $wardId = null;
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            $fromFyear = getFY($fromDate);
            $uptoFyear = getFY($uptoDate);
            $sql = "
                SELECT ulb_ward_masters.id AS id,
                    ulb_ward_masters.ward_name AS ward_no, 
                    SUM(COALESCE(demands.current_demand_hh, 0::numeric)) AS current_demand_hh,   
                    SUM(COALESCE(demands.arrear_demand_hh, 0::numeric)) AS arrear_demand_hh,
                    SUM(COALESCE(collection.current_collection_hh, 0::numeric)) AS current_collection_hh,   
                    SUM(COALESCE(collection.arrear_collection_hh, 0::numeric)) AS arrear_collection_hh,
                    SUM(COALESCE(collection.collection_from_no_of_hh, 0::numeric)) AS collection_from_hh,
                
                    round(SUM((COALESCE(collection.arrear_collection_hh, 0::numeric) / (case when demands.arrear_demand_hh > 0 then demands.arrear_demand_hh else 1 end))*100),2) AS arrear_hh_eff,
                    round(SUM((COALESCE(collection.current_collection_hh, 0::numeric) / (case when demands.current_demand_hh > 0 then demands.current_demand_hh else 1 end))*100),2) AS current_hh_eff,
                
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
                
                    round(SUM((COALESCE(collection.arrear_collection, 0::numeric) / (case when demands.arrear_demand > 0 then demands.arrear_demand else 1 end))*100),2) AS arrear_eff,
                    round(SUM((COALESCE(collection.current_collection, 0::numeric) / (case when demands.current_demand > 0 then demands.current_demand else 1 end))*100),2) AS current_eff,
                
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
                
                FROM ulb_ward_masters                
                LEFT JOIN(
                    SELECT prop_properties.ward_mstr_id,
                        COUNT(
                            DISTINCT ( CASE WHEN prop_demands.fyear  >= '$fromFyear'  then prop_demands.property_id END)
                        ) as current_demand_hh,
                        SUM( CASE WHEN prop_demands.fyear >= '$fromFyear' then prop_demands.total_tax  ELSE 0 END ) AS current_demand,
                        COUNT(
                            DISTINCT ( CASE WHEN prop_demands.fyear < '$fromFyear' then prop_demands.property_id END)
                        ) as arrear_demand_hh,
                        SUM( CASE WHEN prop_demands.fyear < '$fromFyear' then prop_demands.total_tax ELSE 0 END ) AS arrear_demand,
                        SUM(total_tax) AS total_demand
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    WHERE prop_demands.status =1 
                        AND prop_demands.fyear <= '$uptoFyear'
                        " . ($zoneId ? " AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                    GROUP BY prop_properties.ward_mstr_id
                )demands ON demands.ward_mstr_id = ulb_ward_masters.id
                LEFT JOIN (
                    SELECT prop_properties.ward_mstr_id,
                        COUNT(
                            DISTINCT (CASE WHEN prop_demands.fyear  >= '$fromFyear'  then prop_demands.property_id END)
                        ) as current_collection_hh,
                
                        COUNT(DISTINCT(prop_properties.id)) AS collection_from_no_of_hh,
                        SUM( CASE WHEN prop_demands.fyear  >= '$fromFyear' then prop_tran_dtls.paid_total_tax ELSE 0 END ) AS current_collection,
                
                        COUNT(
                            DISTINCT ( CASE WHEN prop_demands.fyear < '$fromFyear' then prop_demands.property_id END )
                        ) as arrear_collection_hh,
                
                        SUM(
                            CASE when prop_demands.fyear < '$fromFyear' then prop_tran_dtls.paid_total_tax ELSE 0 END
                        ) AS arrear_collection,
                        SUM(prop_tran_dtls.paid_total_tax) AS total_collection
                    FROM prop_demands
                    JOIN prop_properties ON prop_properties.id = prop_demands.property_id
                    JOIN prop_tran_dtls ON prop_tran_dtls.prop_demand_id = prop_demands.id 
                        AND prop_tran_dtls.prop_demand_id is not null 
                    JOIN prop_transactions ON prop_transactions.id = prop_tran_dtls.tran_id 
                        AND prop_transactions.status in (1,2) AND prop_transactions.property_id is not null
                    WHERE prop_demands.status =1 
                        AND prop_transactions.tran_date  BETWEEN '$fromDate' AND '$uptoDate'
                        AND prop_demands.fyear <= '$uptoFyear'
                        " . ($zoneId ? " AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
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
                        AND prop_transactions.tran_date < '$fromDate'
                        " . ($zoneId ? " AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                        " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                    GROUP BY prop_properties.ward_mstr_id
                )prev_collection ON prev_collection.ward_mstr_id = ulb_ward_masters.id
                WHERE 1=1 
                    " . ($zoneId ? " AND ulb_ward_masters.zone = $zoneId" : "") . "
                    " . ($wardId ? " AND ulb_ward_masters.id = $wardId" : "") . "
                GROUP BY ulb_ward_masters.id ,ulb_ward_masters.ward_name 
            ";


            $online = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.ward_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHERE(DB::RAW("UPPER(prop_transactions.payment_mode)"), "=", DB::RAW("UPPER('ONLINE')"))
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $doorToDoor = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.ward_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->JOIN(DB::RAW("(                                        
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
                    GROUP BY wf_roleusermaps.user_id
                    ORDER BY wf_roleusermaps.user_id
                ) collecter"), "collecter.role_user_id", "prop_transactions.user_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHERE(DB::RAW("UPPER(prop_transactions.payment_mode)"), "<>", DB::RAW("UPPER('ONLINE')"))
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $jsk = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.ward_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->JOIN(DB::RAW("(                                        
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
                    GROUP BY wf_roleusermaps.user_id
                    ORDER BY wf_roleusermaps.user_id
                ) collecter"), "collecter.role_user_id", "prop_transactions.user_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHERE(DB::RAW("UPPER(prop_transactions.payment_mode)"), "<>", DB::RAW("UPPER('ONLINE')"))
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $totalTrans = $this->_DB_READ->table("prop_transactions")
                ->select(
                    DB::raw("prop_properties.ward_mstr_id ,
                        COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                        COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                        COUNT(prop_transactions.id) AS tran_count, 
                        SUM( COALESCE(prop_transactions.amount, 0)) AS amount
                    ")
                )
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            $penaltyRebates = $this->_DB_READ->table("prop_penaltyrebates")
                ->select(
                    DB::raw("prop_properties.ward_mstr_id ,
                                    COUNT( DISTINCT(prop_transactions.property_id) ) AS holding_count, 
                                    COUNT( DISTINCT(prop_transactions.user_id) ) AS user_count, 
                                    COUNT(DISTINCT(prop_transactions.id)) AS tran_count, 
                                    SUM( CASE WHEN prop_penaltyrebates.is_rebate = TRUE then COALESCE(prop_penaltyrebates.amount, 0) else 0 end) AS rebate_amount,
                                    SUM( CASE WHEN prop_penaltyrebates.is_rebate = false then COALESCE(prop_penaltyrebates.amount, 0) else 0 end) AS penalty_amount
                                ")
                )
                ->join("prop_transactions", "prop_transactions.id", "prop_penaltyrebates.tran_id")
                ->JOIN("prop_properties", "prop_properties.id", "prop_transactions.property_id")
                ->WHERENOTNULL("prop_transactions.property_id")
                ->WHEREIN("prop_transactions.status", [1, 2])
                ->WHEREBETWEEN("prop_transactions.tran_date", [$fromDate, $uptoDate]);

            if ($zoneId) {
                $online = $online->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $doorToDoor = $doorToDoor->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $jsk =  $jsk->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $totalTrans =  $totalTrans->WHERE("prop_properties.zone_mstr_id", $zoneId);
                $penaltyRebates = $penaltyRebates->WHERE("prop_properties.zone_mstr_id", $zoneId);
            }
            if ($wardId) {
                $online = $online->WHERE("prop_properties.ward_mstr_id", $wardId);
                $doorToDoor = $doorToDoor->WHERE("prop_properties.ward_mstr_id", $wardId);
                $jsk =  $jsk->WHERE("prop_properties.ward_mstr_id", $wardId);
                $totalTrans =  $totalTrans->WHERE("prop_properties.ward_mstr_id", $wardId);
                $penaltyRebates = $penaltyRebates->WHERE("prop_properties.ward_mstr_id", $wardId);
            }



            $online = $online->GROUPBY("prop_properties.ward_mstr_id");
            $doorToDoor =  $doorToDoor->GROUPBY("prop_properties.ward_mstr_id");
            $jsk = $jsk->GROUPBY("prop_properties.ward_mstr_id");
            $totalTrans = $totalTrans->GROUPBY("prop_properties.ward_mstr_id");
            $penaltyRebates = $penaltyRebates->GROUPBY("prop_properties.ward_mstr_id");

            $online = $online->get();
            $doorToDoor = $doorToDoor->get();
            $jsk        = $jsk->get();
            $totalTrans  = $totalTrans->get();
            $penaltyRebates = $penaltyRebates->get();



            $dcbData = collect($this->_DB_READ->select($sql))->map(function ($val) use ($online, $doorToDoor, $jsk, $totalTrans) {
                $tcTran = collect($doorToDoor)->where("ward_mstr_id", $val->id);
                $counterTran = collect($jsk)->where("ward_mstr_id", $val->id);
                $onlineTran = collect($online)->where("ward_mstr_id", $val->id);
                $totalTran  = collect($totalTrans)->where("ward_mstr_id", $val->id);
                $val->total_demand = $val->arrear_demand + $val->current_demand;
                $val->total_collection = $val->arrear_collection + $val->current_collection;
                $val->arrear_due = $val->old_due;
                $val->tc_tran_count = $tcTran->sum("tran_count");
                $val->counter_tran_count = $counterTran->sum("tran_count");
                $val->online_tran_count = $onlineTran->sum("tran_count");
                $val->total_tran_count = $totalTran->sum("tran_count");
                preg_match('/\d+/', $val->ward_no, $matches);
                preg_match('/[a-zA-Z]+/', $val->ward_no, $matchesC);
                $val->sl = (int) implode("", $matches);
                $val->slZ = implode("", $matchesC);
                return $val;
            });
            $dcbData = collect($dcbData)->sortBy(["slZ", "sl"])->values();
            $granTax = [
                "id"                    =>  0,
                "ward_no"             =>  "Grand Tax",
                "current_demand_hh"     =>  roundFigure($dcbData->sum("current_demand_hh")),
                "arrear_demand_hh"      =>  roundFigure($dcbData->sum("arrear_demand_hh")),
                "current_collection_hh"      =>  roundFigure($dcbData->sum("current_collection_hh")),
                "arrear_collection_hh"      =>  roundFigure($dcbData->sum("arrear_collection_hh")),
                "collection_from_hh"      =>  roundFigure($dcbData->sum("collection_from_hh")),
                "arrear_hh_eff"      =>  roundFigure($dcbData->sum("arrear_hh_eff")),
                "current_hh_eff"      =>  roundFigure($dcbData->sum("current_hh_eff")),
                "balance_hh"      =>  roundFigure($dcbData->sum("balance_hh")),
                "arrear_demand"      =>  roundFigure($dcbData->sum("arrear_demand")),
                "previous_collection"      =>  roundFigure($dcbData->sum("previous_collection")),
                "current_demand"      =>  roundFigure($dcbData->sum("current_demand")),
                "arrear_collection"      =>  roundFigure($dcbData->sum("arrear_collection")),
                "current_collection"      =>  roundFigure($dcbData->sum("current_collection")),
                "old_due"      =>  roundFigure($dcbData->sum("old_due")),
                "arrear_due"      =>  roundFigure($dcbData->sum("arrear_due")),
                "current_due"      =>  roundFigure($dcbData->sum("current_due")),
                "current_balance_hh"      =>  roundFigure($dcbData->sum("current_balance_hh")),
                "arrear_balance_hh"      =>  roundFigure($dcbData->sum("arrear_balance_hh")),
                "arrear_eff"      =>  roundFigure($dcbData->sum("arrear_eff")),
                "current_eff"      =>  roundFigure($dcbData->sum("current_eff")),
                "outstanding"      =>  roundFigure($dcbData->sum("outstanding")),
                "total_demand"      =>  roundFigure($dcbData->sum("total_demand")),
                "total_collection"      =>  roundFigure($dcbData->sum("total_collection")),
                "tc_tran_count"      =>  roundFigure($dcbData->sum("tc_tran_count")),
                "counter_tran_count"      =>  roundFigure($dcbData->sum("counter_tran_count")),
                "online_tran_count"      =>  roundFigure($dcbData->sum("online_tran_count")),
                "total_tran_count"      =>  roundFigure($dcbData->sum("total_tran_count")),
            ];
            $penalties = [
                "id"                    =>  0,
                "ward_no"             =>  "Total Intrest",
                "current_demand_hh"     =>  "---",
                "arrear_demand_hh"      =>  "---",
                "current_collection_hh"      =>  "---",
                "arrear_collection_hh"      =>  "---",
                "collection_from_hh"      =>  "---",
                "arrear_hh_eff"      =>  "---",
                "current_hh_eff"      =>  "---",
                "balance_hh"      =>  "---",
                "arrear_demand"      =>  "---",
                "previous_collection"      =>  "---",
                "current_demand"      => "---",
                "arrear_collection"      =>  roundFigure($penaltyRebates->sum("penalty_amount")),
                "current_collection"      =>  "---", #$penaltyRebates->sum("rebate_amount"),
                "old_due"      => "---",
                "arrear_due"      => "---",
                "current_due"      =>  "---",
                "current_balance_hh"      =>  "---",
                "arrear_balance_hh"      =>  "---",
                "arrear_eff"      =>  "---",
                "current_eff"      =>  "---",
                "outstanding"      =>  "---",
                "total_demand"      =>  "---",
                "total_collection"      =>  roundFigure($penaltyRebates->sum("penalty_amount")), #+ $penaltyRebates->sum("rebate_amount"),
                "tc_tran_count"      =>  "---",
                "counter_tran_count"      =>  "---",
                "online_tran_count"      =>  "---",
                "total_tran_count"      =>  roundFigure($dcbData->sum("tran_count")),
            ];
            $dcbData->push($granTax, $penalties, $granTax);

            return responseMsgs(true, "Zone Wise daily Collecton Report", $dcbData);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function individulaPropHeadWishDailyColl(Request $request)
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
            /*
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
            */
            $query = "
                    SELECT prop_transactions.id,
                        ROW_NUMBER() OVER( PARTITION BY prop_transactions.id  ORDER BY prop_demands.fyear DESC  )as row_num,
                        ROW_NUMBER() OVER( PARTITION BY prop_transactions.id  ORDER BY prop_demands.fyear ASC  )as row_num_second,
                        prop_transactions.tran_no,owners.owner_name,ulb_ward_masters.ward_name,zone_masters.zone_name,props.holding_no,props.property_no,'' as part,
                        prop_transactions.book_no,split_part(prop_transactions.book_no, '-',2) as receipt_no,
                        prop_transactions.tran_date,prop_demands.fyear,
                        prop_tran_dtls.paid_general_tax as general_tax, prop_tran_dtls.paid_road_tax as road_tax, prop_tran_dtls.paid_firefighting_tax as firefighting_tax,
                        prop_tran_dtls.paid_education_tax as education_tax, prop_tran_dtls.paid_water_tax as water_tax, prop_tran_dtls.paid_cleanliness_tax as cleanliness_tax,
                        prop_tran_dtls.paid_sewarage_tax as sewarage_tax, prop_tran_dtls.paid_tree_tax as tree_tax, prop_tran_dtls.paid_professional_tax as professional_tax,
                        prop_tran_dtls.paid_tax1 as tax1, prop_tran_dtls.paid_tax2 as tax2, prop_tran_dtls.paid_tax3 as tax3, 
                        prop_tran_dtls.paid_sp_education_tax as sp_education_tax, prop_tran_dtls.paid_water_benefit as water_benefit,
                        prop_tran_dtls.paid_water_bill as water_bill, prop_tran_dtls.paid_sp_water_cess as sp_water_cess,
                        prop_tran_dtls.paid_drain_cess as drain_cess, prop_tran_dtls.paid_light_cess as light_cess, prop_tran_dtls.paid_major_building as major_building,
                        prop_tran_dtls.paid_open_ploat_tax as open_ploat_tax, prop_tran_dtls.paid_exempted_general_tax as exempted_general_tax,
                        prop_tran_dtls.paid_total_tax as total_tax, prop_tran_dtls.paid_total_tax as total_tax,
                        prop_transactions.payment_mode, 
                        case when upper(prop_transactions.payment_mode) in('CHEQUE','DD') THEN prop_cheque_dtls.cheque_no  END AS cheque_or_dd_no,
                        case when upper(prop_transactions.payment_mode) in('RTGS','NEFT') THEN prop_cheque_dtls.cheque_no  END AS rtgs_or_neft_no,
                        fine_rebet.rebate, fine_rebet.penalty,advance.advance_amount, adjusted.adjusted_amount,
                        CASE WHEN first_tran.min_id = prop_transactions.id THEN mutation_fee.procces_fee ELSE 0 END AS procces_fee, 
	                    CASE WHEN first_tran.min_id = prop_transactions.id THEN mutation_fee.tran_date ELSE NULL END AS mutation_fee_tran_date
                    
                    from prop_tran_dtls
                    join prop_demands on prop_demands.id = prop_tran_dtls.prop_demand_id
                    join prop_transactions on prop_transactions.id = prop_tran_dtls.tran_id
                    join (
                        select prop_properties.id as pid ,prop_properties.saf_id, prop_properties.ward_mstr_id,prop_properties.zone_mstr_id,prop_properties.property_no,prop_properties.holding_no
                        from prop_properties
                        join prop_transactions on prop_transactions.property_id = prop_properties.id
                        where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                            and prop_transactions.status in(1,2)
                            " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                            " . ($wardId ? "AND prop_properties.ward_mstr_id = $wardId" : "") . "
                            " . ($zoneId ? "AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                            " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                        group BY prop_properties.id
                    )props on props.pid = prop_transactions.property_id
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
                                    group by prop_transactions.id
                                
                    )owners on owners.id = prop_transactions.id
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
                                " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                            group BY prop_properties.id
                        )props on props.pid = prop_transactions.property_id
                        where prop_transactions.tran_date between '$fromDate' and '$toDate'  
                            and prop_transactions.status in(1,2)
                            " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                            and prop_penaltyrebates.status =1 
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
                                " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                            group BY prop_properties.id
                        )props on props.pid = prop_transactions.property_id
                        where prop_transactions.tran_date between '$fromDate' and '$toDate'   
                            and prop_transactions.status in(1,2)
                            " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                            and prop_advances.status =1 
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
                                " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                            group BY prop_properties.id
                        )props on props.pid = prop_transactions.property_id
                        where prop_transactions.tran_date between '$fromDate' and '$toDate'  
                            and prop_transactions.status in(1,2)
                            and prop_adjustments.status =1
                            " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                        group by prop_transactions.id
                    )adjusted on adjusted.tran_id = prop_transactions.id
                    left join (
                        select min(id)as min_id,property_id
                        from prop_transactions
                        where saf_id is null  
                            and prop_transactions.status in(1,2)
                        group by property_id
                    )first_tran on first_tran.min_id = prop_transactions.id	
                    left join(
                        select saf_id, sum(amount)as procces_fee,max(tran_date)tran_date
                        from prop_transactions
                        where saf_id is not null  
                            and prop_transactions.status in(1,2)
                            and tran_type = 'Saf Proccess Fee'
                        group by saf_id
                    )mutation_fee on mutation_fee.saf_id=props.saf_id
                    left join ulb_ward_masters on ulb_ward_masters.id = props.ward_mstr_id	
                    left join zone_masters on zone_masters.id = props.zone_mstr_id
                    where prop_transactions.tran_date between '$fromDate' and '$toDate' 
                        and prop_transactions.status in(1,2)
                        and prop_demands.status =1 
                        and prop_tran_dtls.status =1 
                        " . ($paymentMode ? "AND UPPER(prop_transactions.payment_mode) = ANY (UPPER('{" . $paymentMode . "}')::TEXT[])" : "") . "
                        " . ($wardId ? "AND props.ward_mstr_id = $wardId" : "") . "
                        " . ($zoneId ? "AND props.zone_mstr_id = $zoneId" : "") . "
                        " . ($userId ? "AND prop_transactions.user_id = $userId" : "") . "
                    order by prop_transactions.tran_date,prop_transactions.id,prop_demands.fyear ASC
            ";


            $report = $this->_DB_READ->select($query);
            $test = collect($report);
            $report = collect($report)->map(function ($val) use ($uptoFyear, $test) {
                $val->rebate = $val->row_num == 1 ? $val->rebate : 0;
                $val->rebate = $val->row_num_second == 1 ? $val->procces_fee : 0;
                $penalty = ($val->row_num == 2) ? $val->penalty : 0;
                if ($val->row_num == 1) {
                    $penalty = (($test)->where("id", $val->id)->count("id") == 1 ? $val->penalty : 0);
                }
                $val->penalty = $penalty;
                $val->advance_amount = $val->row_num == 1 ? $val->advance_amount : 0;
                $val->adjusted_amount = $val->row_num == 1 ? $val->adjusted_amount : 0;
                return $val;
            });
            $arrearReports = $report->where("fyear", "<", $uptoFyear);
            $currentReports = $report->where("fyear", "=", $uptoFyear);
            $arrear = [
                "id"                    =>  0,
                "row_num"             =>  "1",
                "tran_no"     =>  "---",
                "owner_name"      =>  "Arrear Collection",
                "ward_name"      =>  "---",
                "zone_name"      =>  "---",
                "holding_no"      =>  "---",
                "property_no"      =>  "---",
                "part"      =>  "---",
                "book_no"      =>  "---",
                "receipt_no"      =>  "---",
                "tran_date"      =>  "---",
                "fyear"      => "---",
                "general_tax"      =>  roundFigure($arrearReports->sum("general_tax")),
                "road_tax"      =>  roundFigure($arrearReports->sum("road_tax")),
                "firefighting_tax"      => roundFigure($arrearReports->sum("firefighting_tax")),
                "education_tax"      =>  roundFigure($arrearReports->sum("education_tax")),
                "water_tax"      =>  roundFigure($arrearReports->sum("water_tax")),
                "cleanliness_tax"      =>  roundFigure($arrearReports->sum("cleanliness_tax")),
                "sewarage_tax"      =>  roundFigure($arrearReports->sum("sewarage_tax")),
                "tree_tax"      =>  roundFigure($arrearReports->sum("tree_tax")),
                "professional_tax"      =>  roundFigure($arrearReports->sum("professional_tax")),
                "tax1"      =>  roundFigure($arrearReports->sum("tax1")),
                "tax2"      =>  roundFigure($arrearReports->sum("tax2")),
                "tax3"      =>  roundFigure($arrearReports->sum("tax3")),
                "sp_education_tax"      =>  roundFigure($arrearReports->sum("sp_education_tax")),
                "water_benefit"      =>  roundFigure($arrearReports->sum("water_benefit")),
                "water_bill"      =>  roundFigure($arrearReports->sum("water_bill")),
                "sp_water_cess"      =>  roundFigure($arrearReports->sum("sp_water_cess")),
                "drain_cess"      =>  roundFigure($arrearReports->sum("drain_cess")),
                "light_cess"      =>  roundFigure($arrearReports->sum("light_cess")),
                "major_building"      =>  roundFigure($arrearReports->sum("major_building")),
                "open_ploat_tax"      =>  roundFigure($arrearReports->sum("open_ploat_tax")),
                "exempted_general_tax"      =>  roundFigure($arrearReports->sum("exempted_general_tax")),
                "total_tax"      =>  roundFigure($arrearReports->sum("total_tax")),
                "payment_mode"      =>  "---",
                "cheque_or_dd_no"      =>  "---",
                "rtgs_or_neft_no"      =>  "---",
                "rebate"      =>  roundFigure($arrearReports->sum("rebate")),
                "penalty"      =>  roundFigure($arrearReports->sum("penalty")),
                "advance_amount"      =>  roundFigure($arrearReports->sum("advance_amount")),
                "adjusted_amount"      =>  roundFigure($arrearReports->sum("adjusted_amount")),
                "procces_fee"      =>  roundFigure($arrearReports->sum("procces_fee")),
                "mutation_fee_tran_date"      =>  "---",
            ];
            $current = [
                "id"                    =>  0,
                "row_num"             =>  "1",
                "tran_no"     =>  "---",
                "owner_name"      =>  "Current Collection",
                "ward_name"      =>  "---",
                "zone_name"      =>  "---",
                "holding_no"      =>  "---",
                "property_no"      =>  "---",
                "part"      =>  "---",
                "book_no"      =>  "---",
                "receipt_no"      =>  "---",
                "tran_date"      =>  "---",
                "fyear"      => "---",
                "general_tax"      =>  roundFigure($currentReports->sum("general_tax")),
                "road_tax"      =>  roundFigure($currentReports->sum("road_tax")),
                "firefighting_tax"      => roundFigure($currentReports->sum("firefighting_tax")),
                "education_tax"      =>  roundFigure($currentReports->sum("education_tax")),
                "water_tax"      =>  roundFigure($currentReports->sum("water_tax")),
                "cleanliness_tax"      =>  roundFigure($currentReports->sum("cleanliness_tax")),
                "sewarage_tax"      =>  roundFigure($currentReports->sum("sewarage_tax")),
                "tree_tax"      =>  roundFigure($currentReports->sum("tree_tax")),
                "professional_tax"      =>  roundFigure($currentReports->sum("professional_tax")),
                "tax1"      =>  roundFigure($currentReports->sum("tax1")),
                "tax2"      =>  roundFigure($currentReports->sum("tax2")),
                "tax3"      =>  roundFigure($currentReports->sum("tax3")),
                "sp_education_tax"      =>  roundFigure($currentReports->sum("sp_education_tax")),
                "water_benefit"      =>  roundFigure($currentReports->sum("water_benefit")),
                "water_bill"      =>  roundFigure($currentReports->sum("water_bill")),
                "sp_water_cess"      =>  roundFigure($currentReports->sum("sp_water_cess")),
                "drain_cess"      =>  roundFigure($currentReports->sum("drain_cess")),
                "light_cess"      =>  roundFigure($currentReports->sum("light_cess")),
                "major_building"      =>  roundFigure($currentReports->sum("major_building")),
                "open_ploat_tax"      =>  roundFigure($currentReports->sum("open_ploat_tax")),
                "exempted_general_tax"      =>  roundFigure($currentReports->sum("exempted_general_tax")),
                "total_tax"      =>  roundFigure($currentReports->sum("total_tax")),
                "payment_mode"      =>  "---",
                "cheque_or_dd_no"      =>  "---",
                "rtgs_or_neft_no"      =>  "---",
                "rebate"      =>  roundFigure($currentReports->sum("rebate")),
                "penalty"      =>  roundFigure($currentReports->sum("penalty")),
                "advance_amount"      =>  roundFigure($currentReports->sum("advance_amount")),
                "adjusted_amount"      =>  roundFigure($currentReports->sum("adjusted_amount")),
                "procces_fee"      =>  roundFigure($arrearReports->sum("procces_fee")),
                "mutation_fee_tran_date"      =>  "---",
            ];

            $granTax = [
                "id"                    =>  0,
                "row_num"             =>  "1",
                "tran_no"     =>  "---",
                "owner_name"      =>  "Total Tax Collection",
                "ward_name"      =>  "---",
                "zone_name"      =>  "---",
                "holding_no"      =>  "---",
                "property_no"      =>  "---",
                "part"      =>  "---",
                "book_no"      =>  "---",
                "receipt_no"      =>  "---",
                "tran_date"      =>  "---",
                "fyear"      => "---",
                "general_tax"      =>  roundFigure($report->sum("general_tax")),
                "road_tax"      =>  roundFigure($report->sum("road_tax")),
                "firefighting_tax"      => roundFigure($report->sum("firefighting_tax")),
                "education_tax"      =>  roundFigure($report->sum("education_tax")),
                "water_tax"      =>  roundFigure($report->sum("water_tax")),
                "cleanliness_tax"      =>  roundFigure($report->sum("cleanliness_tax")),
                "sewarage_tax"      =>  roundFigure($report->sum("sewarage_tax")),
                "tree_tax"      =>  roundFigure($report->sum("tree_tax")),
                "professional_tax"      =>  roundFigure($report->sum("professional_tax")),
                "tax1"      =>  roundFigure($report->sum("tax1")),
                "tax2"      =>  roundFigure($report->sum("tax2")),
                "tax3"      =>  roundFigure($report->sum("tax3")),
                "sp_education_tax"      =>  roundFigure($report->sum("sp_education_tax")),
                "water_benefit"      =>  roundFigure($report->sum("water_benefit")),
                "water_bill"      =>  roundFigure($report->sum("water_bill")),
                "sp_water_cess"      =>  roundFigure($report->sum("sp_water_cess")),
                "drain_cess"      =>  roundFigure($report->sum("drain_cess")),
                "light_cess"      =>  roundFigure($report->sum("light_cess")),
                "major_building"      =>  roundFigure($report->sum("major_building")),
                "open_ploat_tax"      =>  roundFigure($report->sum("open_ploat_tax")),
                "exempted_general_tax"      =>  roundFigure($report->sum("exempted_general_tax")),
                "total_tax"      =>  roundFigure($report->sum("total_tax")),
                "payment_mode"      =>  "---",
                "cheque_or_dd_no"      =>  "---",
                "rtgs_or_neft_no"      =>  "---",
                "rebate"      =>  roundFigure($report->sum("rebate")),
                "penalty"      =>  roundFigure($report->sum("penalty")),
                "advance_amount"      =>  roundFigure($report->sum("advance_amount")),
                "adjusted_amount"      =>  roundFigure($report->sum("adjusted_amount")),
                "procces_fee"      =>  roundFigure($arrearReports->sum("procces_fee")),
                "mutation_fee_tran_date"      =>  "---",
            ];

            $report->push($arrear, $current, $granTax);
            $data["report"] = $report;
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

    public function propEachFyearHoldingDues(Request $request)
    {
        $ruls = [
            "zoneId"     => "required|digits_between:1,9223372036854775807",
            "wardId"    => "required|digits_between:1,9223372036854775807",
        ];
        $validated = Validator::make($request->all(), $ruls);

        if ($validated->fails()) {
            return validationErrorV2($validated);
        }
        try {
            $user = Auth()->user();
            $currentFyear = getFY();
            list($fromFyear, $uptoFyear) = explode("-", $currentFyear);
            $wardId = $zoneId = null;
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            $sql = "
            with demands as(
                select prop_demands.property_id, prop_demands.total_tax, prop_demands.balance, prop_demands.fyear, prop_demands.due_total_tax,
                    prop_demands.is_old, prop_demands.created_at,
                    SPLIT_PART(prop_demands.fyear,'-',2) as upto_year,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_total_tax 			
                        else total_tax end
                    )as actual_demand,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_general_tax 			
                        else general_tax end
                    )as general_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_road_tax 			
                        else road_tax end
                    )as road_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_firefighting_tax 			
                        else firefighting_tax end
                    )as firefighting_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_education_tax 			
                        else education_tax end
                    )as education_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_water_tax		
                        else water_tax end
                    )as water_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_cleanliness_tax		
                        else cleanliness_tax end
                    )as cleanliness_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_sewarage_tax		
                        else sewarage_tax end
                    )as sewarage_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_tree_tax		
                        else tree_tax end
                    )as tree_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_professional_tax		
                        else professional_tax end
                    )as professional_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_tax1		
                        else tax1 end
                    )as tax1,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_tax2		
                        else tax2 end
                    )as tax2,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_tax3		
                        else tax3 end
                    )as tax3,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_sp_education_tax		
                        else sp_education_tax end
                    )as sp_education_tax,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_water_benefit		
                        else water_benefit end
                    )as water_benefit,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_water_bill		
                        else water_bill end
                    )as water_bill,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_sp_water_cess		
                        else sp_water_cess end
                    )as sp_water_cess,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_drain_cess		
                        else drain_cess end
                    )as drain_cess,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_light_cess		
                        else light_cess end
                    )as light_cess,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_major_building		
                        else major_building end
                    )as major_building,
                    (
                        case when prop_demands.paid_status = 1 then prop_demands.due_open_ploat_tax		
                        else open_ploat_tax end
                    )as open_ploat_tax,		
                
                    (case when prop_demands.is_full_paid = false then prop_demands.due_total_tax else total_tax end) /12 as monthly_tax,
                    case when prop_properties.prop_type_mstr_id = 4 and  prop_demands.is_old != true and prop_demands.created_at is not null 
                            and (prop_demands.created_at::date between '$fromFyear-04-01' and '$uptoFyear-03-31')
                            then (
                                0
                            )
                        when prop_demands.fyear < '$currentFyear' and prop_demands.is_old != true
                            then (
                                (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-04-01') :: DATE)) * 12
                                + (DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-04-01') :: DATE))
                            )+1
                        when prop_demands.fyear < '$currentFyear' and prop_demands.is_old = true 
                        then (
                                (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-09-01') :: DATE)) * 12
                                + (DATE_PART('Month', current_date :: DATE) - DATE_PART('Month', concat(SPLIT_PART(prop_demands.fyear,'-',2),'-09-01') :: DATE))
                            )
                        
                        else 0 end
                         AS month_diff,
                    prop_properties.prop_type_mstr_id
                from prop_demands
                join prop_properties on prop_properties.id = prop_demands.property_id
                where prop_demands.status =1 
                " . ($wardId ? " AND prop_properties.ward_mstr_id = $wardId" : "") . "
                " . ($zoneId ? " AND prop_properties.zone_mstr_id = $zoneId" : "") . "
                order by prop_demands.property_id,prop_demands.fyear
            ),
            demand_with_penalty as (
                select property_id, 	
                    sum(actual_demand )as current_demand,
                    sum(actual_demand) as actual_demand,
                    SUM(general_tax)as general_tax,
                    SUM(road_tax)as road_tax,
                    SUM(firefighting_tax)as firefighting_tax,
                    SUM(education_tax)as education_tax,
                    SUM(water_tax)as water_tax,
                    SUM(cleanliness_tax)as cleanliness_tax,
                    SUM(sewarage_tax)as sewarage_tax,
                    SUM(tree_tax)as tree_tax,
                    SUM(professional_tax)as professional_tax,
                    SUM(tax1)as tax1,
                    SUM(tax2)as tax2,
                    SUM(tax3)as tax3,
                    SUM(sp_education_tax)as sp_education_tax,
                    SUM(water_benefit)as water_benefit,
                    SUM(water_bill)as water_bill,
                    SUM(sp_water_cess)as sp_water_cess,
                    SUM(drain_cess)as drain_cess,
                    SUM(light_cess)as light_cess,
                    SUM(major_building)as major_building,
                    SUM(open_ploat_tax)as open_ploat_tax,
                    (fyear) as fyear,
                    sum((actual_demand * month_diff *0.02)) as two_per_penalty ,
                    ROW_NUMBER() OVER(
                        PARTITION BY property_id
                        ORDER BY fyear
                    )as row_no
                from demands 
                where actual_demand > 0
                group by property_id,fyear
            ),
            arrea_pending_penalty as (
                select prop_pending_arrears.prop_id,demand_with_penalty.fyear,sum(CASE WHEN demand_with_penalty.row_no=1 AND demand_with_penalty.fyear != '$currentFyear' THEN prop_pending_arrears.due_total_interest ELSE 0 END ) as priv_total_interest
                from prop_pending_arrears
                join demand_with_penalty on demand_with_penalty.property_id = prop_pending_arrears.prop_id
                where prop_pending_arrears.paid_status =0 and prop_pending_arrears.status =1
                group by prop_pending_arrears.prop_id,demand_with_penalty.fyear
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
            select prop_properties.id,zone_masters.zone_name,ulb_ward_masters.ward_name,prop_properties.holding_no,prop_properties.property_no,
                prop_properties.prop_address ,
                owners.owner_name, owners.mobile_no, 
                demand_with_penalty.fyear, 
                demand_with_penalty.current_demand,
                
                demand_with_penalty.general_tax as general_tax,
                demand_with_penalty.road_tax as road_tax,
                demand_with_penalty.firefighting_tax as firefighting_tax,
                demand_with_penalty.education_tax as education_tax,
                demand_with_penalty.water_tax as water_tax,
                demand_with_penalty.cleanliness_tax as cleanliness_tax,
                demand_with_penalty.sewarage_tax as sewarage_tax,
                demand_with_penalty.tree_tax as tree_tax,
                demand_with_penalty.professional_tax as professional_tax,
                demand_with_penalty.tax1 as tax1,
                demand_with_penalty.tax2 as tax2,
                demand_with_penalty.tax3 as tax3,
                demand_with_penalty.sp_education_tax as sp_education_tax,
                demand_with_penalty.water_benefit as water_benefit,
                demand_with_penalty.water_bill as water_bill,
                demand_with_penalty.sp_water_cess as sp_water_cess,
                demand_with_penalty.drain_cess as drain_cess,
                demand_with_penalty.light_cess as light_cess,
                demand_with_penalty.major_building as major_building,
                demand_with_penalty.open_ploat_tax as open_ploat_tax,
                
                demand_with_penalty.actual_demand,
                demand_with_penalty.two_per_penalty,
                coalesce(arrea_pending_penalty.priv_total_interest,0) as priv_total_interest,
                (coalesce(demand_with_penalty.actual_demand,0)
                 + coalesce(demand_with_penalty.two_per_penalty,0)
                 + coalesce(arrea_pending_penalty.priv_total_interest,0)
                 ) as total_demand
            from demand_with_penalty
            join prop_properties on prop_properties.id = demand_with_penalty.property_id
            left join owners on owners.property_id = demand_with_penalty.property_id
            left join arrea_pending_penalty on arrea_pending_penalty.prop_id = demand_with_penalty.property_id AND arrea_pending_penalty.fyear = demand_with_penalty.fyear
            left join zone_masters on zone_masters.id = prop_properties.zone_mstr_id
            left join ulb_ward_masters on ulb_ward_masters.id = prop_properties.ward_mstr_id            
            ORDER BY prop_properties.id,zone_masters.id,ulb_ward_masters.id            
            ";
            $data = DB::select($sql);
            return responseMsgs(true, "", $data);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }



    public function tcAssessment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "fromDate" => "nullable|date|date_format:Y-m-d",
            "uptoDate" => "nullable|date|date_format:Y-m-d",
            'zoneId' => "nullable|integer",
            'wardId' => "nullable|integer",
            "applicationType" => "nullable|in:TC,TC Reassesment",
            "applicationNo" => "nullable|string",
            "holdingNo" => "nullable|string",
            "name" => "nullable|string",
            "mobileNo" => "nullable|string",
            'perPage' => "nullable|integer|min:1",
            'page' => "nullable|integer|min:1"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 200);
        }

        try {
            $perPage = $request->perPage ?? 10;
            $page = $request->page ?? 1;
            $fromDate = $request->fromDate ?? Carbon::now()->format('Y-m-d');
            $uptoDate = $request->uptoDate ?? Carbon::now()->format('Y-m-d');

            $activeSaf = new PropActiveSaf();
            $query = $activeSaf->getActiveSafDtlsv1();

            if ($request->wardId) {
                $query->where('ward_mstr_id', $request->wardId);
            }

            if ($request->zoneId) {
                $query->where('zone_mstr_id', $request->zoneId);
            }

            if ($fromDate && $uptoDate) {
                $query->whereBetween('application_date', [$fromDate, $uptoDate]);
            }

            if ($request->applicationType) {
                $query->where('applied_by', $request->applicationType);
            }

            if ($request->applicationNo) {
                $query->where('saf_no', $request->applicationNo);
            }

            if ($request->holdingNo) {
                $query->where('holding_no', $request->holdingNo);
            }

            if ($request->name) {
                $query->where('ownername', 'LIKE', '%' . $request->name . '%');
            }

            if ($request->mobileNo) {
                $query->where('mobile_no', 'LIKE', '%' . $request->mobileNo . '%');
            }

            $list = $query->paginate($perPage, ['*'], 'page', $page);

            $data = [
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'total' => $list->total(),
                'data' => $list->items()
            ];

            return responseMsgs(true, "Tc Applied New Assessment Reports", remove_null($data));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }



    public function bulkDemand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "zoneId" => "required|integer",
            "wardId" => "required|integer",
            "amount" => "nullable|in:20001 to 50000,50001 to 100000,100001 to 150000,1500001 to above",
            'perPage' => "nullable|integer|min:1",
            'page' => "nullable|integer|min:1"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 200);
        }

        try {
            $perPage = $request->perPage ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            // Base query for data
            $sql = "
        WITH demands AS (
            SELECT
                prop_demands.property_id,
                prop_demands.total_tax,
                prop_demands.balance,
                prop_demands.fyear,
                prop_demands.due_total_tax,
                prop_demands.is_old,
                prop_demands.created_at,
                SPLIT_PART(prop_demands.fyear, '-', 2) AS upto_year,
                CASE
                    WHEN prop_demands.paid_status = 1 THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END AS actual_demand,
                CASE
                    WHEN prop_demands.is_full_paid = false THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END / 12 AS monthly_tax,
                CASE
                    WHEN prop_properties.prop_type_mstr_id = 4
                        AND prop_demands.is_old != true
                        AND prop_demands.created_at IS NOT NULL
                        AND prop_demands.created_at::date BETWEEN '2024-04-01' AND '2025-03-31'
                    THEN 0
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old != true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE))
                    ) + 1
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old = true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE))
                    )
                    ELSE 0
                END AS month_diff,
                prop_properties.prop_type_mstr_id
            FROM prop_demands
            JOIN prop_properties ON prop_properties.id = prop_demands.property_id
            WHERE prop_demands.status = 1
        ),
        demand_with_penalty AS (
            SELECT
                property_id,
                SUM(CASE WHEN fyear < '2024-2025' THEN actual_demand ELSE 0 END) AS arrear_demand,
                SUM(CASE WHEN fyear = '2024-2025' THEN actual_demand ELSE 0 END) AS current_demand,
                SUM(actual_demand) AS actual_demand,
                MIN(fyear) AS from_year,
                MAX(fyear) AS upto_year,
                SUM((actual_demand * month_diff * 0.02)) AS two_per_penalty
            FROM demands
            WHERE actual_demand > 0
            GROUP BY property_id
        ),
        arrea_pending_penalty AS (
            SELECT
                prop_pending_arrears.prop_id,
                SUM(prop_pending_arrears.due_total_interest) AS priv_total_interest
            FROM prop_pending_arrears
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_pending_arrears.prop_id
            WHERE prop_pending_arrears.paid_status = 0 AND prop_pending_arrears.status = 1
            GROUP BY prop_pending_arrears.prop_id
        ),
        owners AS (
            SELECT
                prop_owners.property_id,
                STRING_AGG(
                    CASE
                        WHEN TRIM(prop_owners.owner_name_marathi) = '' THEN prop_owners.owner_name
                        ELSE prop_owners.owner_name_marathi
                    END, ', '
                ) AS owner_name,
                STRING_AGG(prop_owners.mobile_no, ', ') AS mobile_no
            FROM prop_owners
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_owners.property_id
            WHERE prop_owners.status = 1
            GROUP BY prop_owners.property_id
        ),
        total_count AS (
            SELECT COUNT(*) AS total
            FROM demand_with_penalty
            JOIN prop_properties ON prop_properties.id = demand_with_penalty.property_id
            LEFT JOIN owners ON owners.property_id = demand_with_penalty.property_id
            LEFT JOIN arrea_pending_penalty ON arrea_pending_penalty.prop_id = demand_with_penalty.property_id
            LEFT JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
            WHERE 1=1
        )
        SELECT
            prop_properties.id,
            zone_masters.zone_name,
            ulb_ward_masters.ward_name,
            prop_properties.holding_no,
            prop_properties.property_no,
            prop_properties.prop_address,
            owners.owner_name,
            owners.mobile_no,
            demand_with_penalty.from_year,
            demand_with_penalty.upto_year,
            demand_with_penalty.arrear_demand,
            demand_with_penalty.current_demand,
            demand_with_penalty.actual_demand,
            demand_with_penalty.two_per_penalty,
            arrea_pending_penalty.priv_total_interest,
            (
                COALESCE(demand_with_penalty.actual_demand, 0)
                + COALESCE(demand_with_penalty.two_per_penalty, 0)
                + COALESCE(arrea_pending_penalty.priv_total_interest, 0)
            ) AS total_demand
        FROM total_count
        JOIN demand_with_penalty ON total_count.total IS NOT NULL
        JOIN prop_properties ON prop_properties.id = demand_with_penalty.property_id
        LEFT JOIN owners ON owners.property_id = demand_with_penalty.property_id
        LEFT JOIN arrea_pending_penalty ON arrea_pending_penalty.prop_id = demand_with_penalty.property_id
        LEFT JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
        WHERE 1=1 and prop_properties.generated = 'false'
        ";

            // Apply filters if provided
            $parameters = [];

            if ($request->has('zoneId')) {
                $sql .= " AND prop_properties.zone_mstr_id = ?";
                $parameters[] = $request->zoneId;
            }

            if ($request->has('wardId')) {
                $sql .= " AND prop_properties.ward_mstr_id = ?";
                $parameters[] = $request->wardId;
            }

            if ($request->has('amount')) {
                // Parse the amount range
                $amountRange = $request->amount;
                list($minAmount, $maxAmount) = explode(' to ', str_replace('above', '9999999999', $amountRange));
                $sql .= " AND (COALESCE(demand_with_penalty.actual_demand, 0)
                  + COALESCE(demand_with_penalty.two_per_penalty, 0)
                  + COALESCE(arrea_pending_penalty.priv_total_interest, 0)) BETWEEN ? AND ?";
                $parameters[] = $minAmount;
                $parameters[] = $maxAmount;
            }

            // Finalize the query for pagination
            $sql .= " LIMIT ? OFFSET ?";
            $parameters[] = $perPage;
            $parameters[] = $offset;

            // Execute the query for paginated data
            $dataResults = DB::select(DB::raw($sql), $parameters);

            // Execute the query to get the total count
            $totalCountSql = "
        WITH demands AS (
            SELECT
                prop_demands.property_id,
                prop_demands.total_tax,
                prop_demands.balance,
                prop_demands.fyear,
                prop_demands.due_total_tax,
                prop_demands.is_old,
                prop_demands.created_at,
                SPLIT_PART(prop_demands.fyear, '-', 2) AS upto_year,
                CASE
                    WHEN prop_demands.paid_status = 1 THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END AS actual_demand,
                CASE
                    WHEN prop_demands.is_full_paid = false THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END / 12 AS monthly_tax,
                CASE
                    WHEN prop_properties.prop_type_mstr_id = 4
                        AND prop_demands.is_old != true
                        AND prop_demands.created_at IS NOT NULL
                        AND prop_demands.created_at::date BETWEEN '2024-04-01' AND '2025-03-31'
                    THEN 0
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old != true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE))
                    ) + 1
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old = true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE))
                    )
                    ELSE 0
                END AS month_diff,
                prop_properties.prop_type_mstr_id
            FROM prop_demands
            JOIN prop_properties ON prop_properties.id = prop_demands.property_id
            WHERE prop_demands.status = 1
        ),
        demand_with_penalty AS (
            SELECT
                property_id,
                SUM(CASE WHEN fyear < '2024-2025' THEN actual_demand ELSE 0 END) AS arrear_demand,
                SUM(CASE WHEN fyear = '2024-2025' THEN actual_demand ELSE 0 END) AS current_demand,
                SUM(actual_demand) AS actual_demand,
                MIN(fyear) AS from_year,
                MAX(fyear) AS upto_year,
                SUM((actual_demand * month_diff * 0.02)) AS two_per_penalty
            FROM demands
            WHERE actual_demand > 0
            GROUP BY property_id
        ),
        arrea_pending_penalty AS (
            SELECT
                prop_pending_arrears.prop_id,
                SUM(prop_pending_arrears.due_total_interest) AS priv_total_interest
            FROM prop_pending_arrears
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_pending_arrears.prop_id
            WHERE prop_pending_arrears.paid_status = 0 AND prop_pending_arrears.status = 1
            GROUP BY prop_pending_arrears.prop_id
        ),
        owners AS (
            SELECT
                prop_owners.property_id,
                STRING_AGG(
                    CASE
                        WHEN TRIM(prop_owners.owner_name_marathi) = '' THEN prop_owners.owner_name
                        ELSE prop_owners.owner_name_marathi
                    END, ', '
                ) AS owner_name,
                STRING_AGG(prop_owners.mobile_no, ', ') AS mobile_no
            FROM prop_owners
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_owners.property_id
            WHERE prop_owners.status = 1
            GROUP BY prop_owners.property_id
        )
        SELECT COUNT(*) AS total
        FROM demand_with_penalty
        JOIN prop_properties ON prop_properties.id = demand_with_penalty.property_id
        LEFT JOIN owners ON owners.property_id = demand_with_penalty.property_id
        LEFT JOIN arrea_pending_penalty ON arrea_pending_penalty.prop_id = demand_with_penalty.property_id
        LEFT JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
        WHERE 1=1 and prop_properties.generated = 'false'
        ";

            // Apply filters for total count query
            $totalParameters = [];

            if ($request->has('zoneId')) {
                $totalCountSql .= " AND prop_properties.zone_mstr_id = ?";
                $totalParameters[] = $request->zoneId;
            }

            if ($request->has('wardId')) {
                $totalCountSql .= " AND prop_properties.ward_mstr_id = ?";
                $totalParameters[] = $request->wardId;
            }

            if ($request->has('amount')) {
                // Parse the amount range
                $amountRange = $request->amount;
                list($minAmount, $maxAmount) = explode(' to ', str_replace('above', '9999999999', $amountRange));
                $totalCountSql .= " AND (COALESCE(demand_with_penalty.actual_demand, 0)
                  + COALESCE(demand_with_penalty.two_per_penalty, 0)
                  + COALESCE(arrea_pending_penalty.priv_total_interest, 0)) BETWEEN ? AND ?";
                $totalParameters[] = $minAmount;
                $totalParameters[] = $maxAmount;
            }

            // Execute the total count query
            $totalCountResult = DB::select(DB::raw($totalCountSql), $totalParameters);
            $totalCount = $totalCountResult[0]->total ?? 0;

            // Calculate pagination details
            $lastPage = (int) ceil($totalCount / $perPage);

            return response()->json([
                'status' => true,
                'message' => 'Demand Reports',
                'data' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'data' => remove_null($dataResults)
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 200);
        }
    }

    public function bulkDemandList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "zoneId" => "nullable|integer",
            "wardId" => "nullable|integer",
            "amount" => "nullable|in:20001 to 50000,50001 to 100000,100001 to 150000,1500001 to above",
            'perPage' => "nullable|integer|min:1",
            'page' => "nullable|integer|min:1"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 200);
        }

        try {
            $perPage = $request->perPage ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            // Base query for data
            $sql = "
        WITH demands AS (
            SELECT
                prop_demands.property_id,
                prop_demands.total_tax,
                prop_demands.balance,
                prop_demands.fyear,
                prop_demands.due_total_tax,
                prop_demands.is_old,
                prop_demands.created_at,
                SPLIT_PART(prop_demands.fyear, '-', 2) AS upto_year,
                CASE
                    WHEN prop_demands.paid_status = 1 THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END AS actual_demand,
                CASE
                    WHEN prop_demands.is_full_paid = false THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END / 12 AS monthly_tax,
                CASE
                    WHEN prop_properties.prop_type_mstr_id = 4
                        AND prop_demands.is_old != true
                        AND prop_demands.created_at IS NOT NULL
                        AND prop_demands.created_at::date BETWEEN '2024-04-01' AND '2025-03-31'
                    THEN 0
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old != true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE))
                    ) + 1
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old = true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE))
                    )
                    ELSE 0
                END AS month_diff,
                prop_properties.prop_type_mstr_id
            FROM prop_demands
            JOIN prop_properties ON prop_properties.id = prop_demands.property_id
            WHERE prop_demands.status = 1
             
        ),
        demand_with_penalty AS (
            SELECT
                property_id,
                SUM(CASE WHEN fyear < '2024-2025' THEN actual_demand ELSE 0 END) AS arrear_demand,
                SUM(CASE WHEN fyear = '2024-2025' THEN actual_demand ELSE 0 END) AS current_demand,
                SUM(actual_demand) AS actual_demand,
                MIN(fyear) AS from_year,
                MAX(fyear) AS upto_year,
                SUM((actual_demand * month_diff * 0.02)) AS two_per_penalty
            FROM demands
            WHERE actual_demand > 0
            GROUP BY property_id
        ),
        arrea_pending_penalty AS (
            SELECT
                prop_pending_arrears.prop_id,
                SUM(prop_pending_arrears.due_total_interest) AS priv_total_interest
            FROM prop_pending_arrears
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_pending_arrears.prop_id
            WHERE prop_pending_arrears.paid_status = 0 AND prop_pending_arrears.status = 1
            GROUP BY prop_pending_arrears.prop_id
        ),
        owners AS (
            SELECT
                prop_owners.property_id,
                STRING_AGG(
                    CASE
                        WHEN TRIM(prop_owners.owner_name_marathi) = '' THEN prop_owners.owner_name
                        ELSE prop_owners.owner_name_marathi
                    END, ', '
                ) AS owner_name,
                STRING_AGG(prop_owners.mobile_no, ', ') AS mobile_no
            FROM prop_owners
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_owners.property_id
            WHERE prop_owners.status = 1
            GROUP BY prop_owners.property_id
        ),
        total_count AS (
            SELECT COUNT(*) AS total
            FROM demand_with_penalty
            JOIN prop_properties ON prop_properties.id = demand_with_penalty.property_id
            LEFT JOIN owners ON owners.property_id = demand_with_penalty.property_id
            LEFT JOIN arrea_pending_penalty ON arrea_pending_penalty.prop_id = demand_with_penalty.property_id
            LEFT JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
            LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
            WHERE 1=1
        )
        SELECT
            prop_properties.id,
            prop_properties.generated,
            prop_properties.notice_no,
            prop_properties.downloaded,
            prop_properties.count,
            zone_masters.zone_name,
            ulb_ward_masters.ward_name,
            prop_properties.holding_no,
            prop_properties.property_no,
            prop_properties.prop_address,
            owners.owner_name,
            owners.mobile_no,
            demand_with_penalty.from_year,
            demand_with_penalty.upto_year,
            demand_with_penalty.arrear_demand,
            demand_with_penalty.current_demand,
            demand_with_penalty.actual_demand,
            demand_with_penalty.two_per_penalty,
            arrea_pending_penalty.priv_total_interest,
            (
                COALESCE(demand_with_penalty.actual_demand, 0)
                + COALESCE(demand_with_penalty.two_per_penalty, 0)
                + COALESCE(arrea_pending_penalty.priv_total_interest, 0)
            ) AS total_demand
        FROM total_count
        JOIN demand_with_penalty ON total_count.total IS NOT NULL
        JOIN prop_properties ON prop_properties.id = demand_with_penalty.property_id
        LEFT JOIN owners ON owners.property_id = demand_with_penalty.property_id
        LEFT JOIN arrea_pending_penalty ON arrea_pending_penalty.prop_id = demand_with_penalty.property_id
        LEFT JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
        WHERE 1=1 and prop_properties.generated = 'true'
        ";

            // Apply filters if provided
            $parameters = [];

            if ($request->has('zoneId')) {
                $sql .= " AND prop_properties.zone_mstr_id = ?";
                $parameters[] = $request->zoneId;
            }

            if ($request->has('wardId')) {
                $sql .= " AND prop_properties.ward_mstr_id = ?";
                $parameters[] = $request->wardId;
            }

            if ($request->has('amount')) {
                // Parse the amount range
                $amountRange = $request->amount;
                list($minAmount, $maxAmount) = explode(' to ', str_replace('above', '9999999999', $amountRange));
                $sql .= " AND (COALESCE(demand_with_penalty.actual_demand, 0)
                  + COALESCE(demand_with_penalty.two_per_penalty, 0)
                  + COALESCE(arrea_pending_penalty.priv_total_interest, 0)) BETWEEN ? AND ?";
                $parameters[] = $minAmount;
                $parameters[] = $maxAmount;
            }

            // Finalize the query for pagination
            $sql .= " LIMIT ? OFFSET ?";
            $parameters[] = $perPage;
            $parameters[] = $offset;

            // Execute the query for paginated data
            $dataResults = DB::select(DB::raw($sql), $parameters);

            // Execute the query to get the total count
            $totalCountSql = "
        WITH demands AS (
            SELECT
                prop_demands.property_id,
                prop_demands.total_tax,
                prop_demands.balance,
                prop_demands.fyear,
                prop_demands.due_total_tax,
                prop_demands.is_old,
                prop_demands.created_at,
                SPLIT_PART(prop_demands.fyear, '-', 2) AS upto_year,
                CASE
                    WHEN prop_demands.paid_status = 1 THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END AS actual_demand,
                CASE
                    WHEN prop_demands.is_full_paid = false THEN prop_demands.due_total_tax
                    ELSE prop_demands.total_tax
                END / 12 AS monthly_tax,
                CASE
                    WHEN prop_properties.prop_type_mstr_id = 4
                        AND prop_demands.is_old != true
                        AND prop_demands.created_at IS NOT NULL
                        AND prop_demands.created_at::date BETWEEN '2024-04-01' AND '2025-03-31'
                    THEN 0
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old != true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-04-01')::DATE))
                    ) + 1
                    WHEN prop_demands.fyear < '2024-2025'
                        AND prop_demands.is_old = true
                    THEN (
                        (DATE_PART('YEAR', current_date) - DATE_PART('YEAR', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE)) * 12
                        + (DATE_PART('Month', current_date::DATE) - DATE_PART('Month', CONCAT(SPLIT_PART(prop_demands.fyear, '-', 2), '-09-01')::DATE))
                    )
                    ELSE 0
                END AS month_diff,
                prop_properties.prop_type_mstr_id
            FROM prop_demands
            JOIN prop_properties ON prop_properties.id = prop_demands.property_id
            WHERE prop_demands.status = 1
        ),
        demand_with_penalty AS (
            SELECT
                property_id,
                SUM(CASE WHEN fyear < '2024-2025' THEN actual_demand ELSE 0 END) AS arrear_demand,
                SUM(CASE WHEN fyear = '2024-2025' THEN actual_demand ELSE 0 END) AS current_demand,
                SUM(actual_demand) AS actual_demand,
                MIN(fyear) AS from_year,
                MAX(fyear) AS upto_year,
                SUM((actual_demand * month_diff * 0.02)) AS two_per_penalty
            FROM demands
            WHERE actual_demand > 0
            GROUP BY property_id
        ),
        arrea_pending_penalty AS (
            SELECT
                prop_pending_arrears.prop_id,
                SUM(prop_pending_arrears.due_total_interest) AS priv_total_interest
            FROM prop_pending_arrears
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_pending_arrears.prop_id
            WHERE prop_pending_arrears.paid_status = 0 AND prop_pending_arrears.status = 1
            GROUP BY prop_pending_arrears.prop_id
        ),
        owners AS (
            SELECT
                prop_owners.property_id,
                STRING_AGG(
                    CASE
                        WHEN TRIM(prop_owners.owner_name_marathi) = '' THEN prop_owners.owner_name
                        ELSE prop_owners.owner_name_marathi
                    END, ', '
                ) AS owner_name,
                STRING_AGG(prop_owners.mobile_no, ', ') AS mobile_no
            FROM prop_owners
            JOIN demand_with_penalty ON demand_with_penalty.property_id = prop_owners.property_id
            WHERE prop_owners.status = 1
            GROUP BY prop_owners.property_id
        )
        SELECT COUNT(*) AS total
        FROM demand_with_penalty
        JOIN prop_properties ON prop_properties.id = demand_with_penalty.property_id
        LEFT JOIN owners ON owners.property_id = demand_with_penalty.property_id
        LEFT JOIN arrea_pending_penalty ON arrea_pending_penalty.prop_id = demand_with_penalty.property_id
        LEFT JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
        LEFT JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
        WHERE 1=1 and prop_properties.generated = 'true'
        ";

            // Apply filters for total count query
            $totalParameters = [];

            if ($request->has('zoneId')) {
                $totalCountSql .= " AND prop_properties.zone_mstr_id = ?";
                $totalParameters[] = $request->zoneId;
            }

            if ($request->has('wardId')) {
                $totalCountSql .= " AND prop_properties.ward_mstr_id = ?";
                $totalParameters[] = $request->wardId;
            }

            if ($request->has('amount')) {
                // Parse the amount range
                $amountRange = $request->amount;
                list($minAmount, $maxAmount) = explode(' to ', str_replace('above', '9999999999', $amountRange));
                $totalCountSql .= " AND (COALESCE(demand_with_penalty.actual_demand, 0)
                  + COALESCE(demand_with_penalty.two_per_penalty, 0)
                  + COALESCE(arrea_pending_penalty.priv_total_interest, 0)) BETWEEN ? AND ?";
                $totalParameters[] = $minAmount;
                $totalParameters[] = $maxAmount;
            }

            // Execute the total count query
            $totalCountResult = DB::select(DB::raw($totalCountSql), $totalParameters);
            $totalCount = $totalCountResult[0]->total ?? 0;

            // Calculate pagination details
            $lastPage = (int) ceil($totalCount / $perPage);

            return response()->json([
                'status' => true,
                'message' => 'Demand Reports',
                'data' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'data' => remove_null($dataResults)
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 200);
        }
    }

    public function generateNotice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propId' => 'required|array',
            'propId.*' => 'integer',
            'generated' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 200);
        }

        $noticeNos = []; // Initialize an array to store generated notice numbers

        try {
            $refparamId = Config::get('propertyConstaint.NOTICE_ID');
            $generated = $request->generated;

            // Update the generated status for properties
            PropProperty::whereIn('id', $request->propId)
                ->update(['generated' => $generated]);

            // Fetch properties for notice number generation
            $properties = PropProperty::whereIn('id', $request->propId)
                ->get();

            // Generate notice numbers for each property
            foreach ($properties as $property) {
                $idGeneration = new PrefixIdGenerator($refparamId ?? 58, 2); // Use ward_id for generation
                $noticeNo = $idGeneration->generatev1($property);

                // Update the property with the generated notice number
                $property->update(['notice_no' => $noticeNo]);

                // Store the generated notice number
                $noticeNos[$property->id] = $noticeNo;
            }

            return response()->json([
                'status' => true,
                'message' => 'Notice numbers generated successfully',
                'data' => $noticeNos
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 200); // Use 500 for server errors
        }
    }

    public function bulkDemandListDownloadCount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'propId' => 'required|array',
            'propId.*' => 'integer',
            'downloaded' => 'required|boolean',
            'count' => 'required|integer|in:1,2,3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 200);
        }

        try {
            $downloaded = $request->downloaded;
            $count = $request->count;
            PropProperty::whereIn('id', $request->propId)
                ->update([
                    'downloaded' => $downloaded,
                    'count' => $count
                ]);

            return response()->json([
                'status' => true,
                'message' => 'Properties updated successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 200);
        }
    }








    /*
     #====================================================
     
     public function safAppliedtypeDetails(Request $request)
     {
         $validator = Validator::make($request->all(), [
             "fromDate" => "nullable|date|date_format:Y-m-d",
             "uptoDate" => "nullable|date|date_format:Y-m-d",
             'zoneId' => "nullable",
             'wardId' => "nullable",
             'propertyType' => "nullable",
             'constructionType' => "nullable",
             'usageType' => "nullable",
         ]);
     
         if ($validator->fails()) {
             return response()->json([
                 'status' => false,
                 'message' => 'validation error',
                 'errors' => $validator->errors()
             ], 200);
         }
     
         try {
             $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
             $wardId = $zoneId = $userId = null;
     
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
     
             $perPage = $request->perPage ? $request->perPage : 10;
             $page = $request->page && $request->page > 0 ? $request->page : 1;
             $limit = $perPage;
             $offset = $request->page && $request->page > 1 ? ($request->page * $perPage) : 0;
     
             $query = "
                 SELECT 
                     prop_safs.saf_no,
                     prop_safs.id AS Saf_id,
                     zone_masters.zone_name,
                     ulb_ward_masters.ward_name,
                     prop_safs.prop_address,
                     prop_safs.holding_no,
                     prop_transactions.amount,
                     prop_transactions.payment_mode,
                     prop_safs.assessment_type,
                     owner.owner_name,
                     owner.mobile_no
                 FROM prop_transactions
                 JOIN (
                     (
                         SELECT
                             prop_safs.saf_no,
                             prop_safs.id,
                             prop_safs.prop_address,
                             prop_safs.assessment_type,
                             prop_safs.holding_no,
                             prop_safs.ward_mstr_id,
                             prop_safs.zone_mstr_id
                         FROM prop_active_safs as prop_safs
                         JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id
                     )	
                     UNION
                     (
                         SELECT
                             prop_safs.saf_no,
                             prop_safs.id,
                             prop_safs.prop_address,
                             prop_safs.assessment_type,
                             prop_safs.holding_no,
                             prop_safs.ward_mstr_id,
                             prop_safs.zone_mstr_id
                         FROM prop_rejected_safs as prop_safs
                         JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id
                     )
                     UNION
                     (
                         SELECT
                             prop_safs.saf_no,
                             prop_safs.id,
                             prop_safs.prop_address,
                             prop_safs.assessment_type,
                             prop_safs.holding_no,
                             prop_safs.ward_mstr_id,
                             prop_safs.zone_mstr_id
                         FROM prop_safs as prop_safs
                         JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id
                     )
                 ) prop_safs ON prop_safs.id = prop_transactions.saf_id
                 JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_safs.ward_mstr_id
                 JOIN zone_masters ON zone_masters.id = prop_safs.zone_mstr_id
                 LEFT JOIN (
                     (
                         SELECT
                             prop_owners.saf_id,
                             STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                             STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                         FROM prop_active_safs_owners as prop_owners
                         JOIN prop_transactions ON prop_transactions.saf_id = prop_owners.saf_id
                         WHERE prop_owners.status = 1
                         GROUP BY prop_owners.saf_id
                     )
                     UNION
                     (
                         SELECT
                             prop_owners.saf_id,
                             STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                             STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                         FROM prop_rejected_safs_owners as prop_owners
                         JOIN prop_transactions ON prop_transactions.saf_id = prop_owners.saf_id
                         WHERE prop_owners.status = 1
                         GROUP BY prop_owners.saf_id
                     )
                     UNION
                     (
                         SELECT
                             prop_owners.saf_id,
                             STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                             STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                         FROM prop_safs_owners as prop_owners
                         JOIN prop_transactions ON prop_transactions.saf_id = prop_owners.saf_id
                         WHERE prop_owners.status = 1
                         GROUP BY prop_owners.saf_id
                     )
                 ) AS owner ON owner.saf_id = prop_safs.id";
     
             if ($wardId) {
                 $query .= " WHERE prop_safs.ward_mstr_id = $wardId";
             }
     
             if ($zoneId) {
                 $query .= " AND prop_safs.zone_mstr_id = $zoneId";
             }
     
             if ($request->propertyType) {
                 $propertyType = $request->propertyType;
                 $query .= " AND prop_safs.property_type = '$propertyType'";
             }
     
             if ($request->constructionType) {
                 $constructionType = $request->constructionType;
                 $query .= " AND prop_safs.construction_type = '$constructionType'";
             }
     
             if ($request->usageType) {
                 $usageType = $request->usageType;
                 $query .= " AND prop_safs.usage_type = '$usageType'";
             }
     
             $count = $query;
             $query .= " LIMIT $limit OFFSET $offset ";
     
             $data = DB::table(DB::raw("($query) AS prop"))->get();
     
             $items = $data;
             $total = (collect(DB::select("SELECT COUNT(*) AS total FROM ($count) aggregate_table"))->first())->total ?? 0;
             $lastPage = ceil($total / $perPage);
             $list = [
                 "current_page" => $page,
                 "data" => $data,
                 "total" => $total,
                 "per_page" => $perPage,
                 "last_page" => $lastPage
             ];
     
             $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
             return responseMsg(true, "Transaction details List", $list, "010501", "1.0", "", "POST", $request->deviceId ?? "");
         } catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), $request->all());
         }
     }
 
     public function constructionTypeSummery(Request $request)
     {
         try {
             $result = DB::table('prop_properties')
                 ->join('prop_floors', 'prop_floors.property_id', '=', 'prop_properties.id')
                 ->join('ref_prop_construction_types', 'ref_prop_construction_types.id', '=', 'prop_floors.const_type_mstr_id')
                 ->where('prop_properties.prop_type_mstr_id', '!=', 4)
                 ->groupBy('ref_prop_construction_types.construction_type')
                 ->select(
                     'ref_prop_construction_types.construction_type AS construction_type',
                     DB::raw('COUNT(DISTINCT prop_properties.id) AS Total')
                 )
                 ->get();
 
                 return responseMsg(true, "Construction type detail reports", $result, "010501", "1.0", "", "POST", "");
             } catch (Exception $e) {
                 return responseMsg(false, $e->getMessage(),"");
             }
     }
 
     public function usageTypeSummery(Request $request)
     {
         try {
                 $result = DB::table('prop_properties')
                 ->join('prop_floors', 'prop_floors.property_id', '=', 'prop_properties.id')
                 ->join('ref_prop_usage_types', 'ref_prop_usage_types.id', '=', 'prop_floors.usage_type_mstr_id')
                 ->where('prop_properties.prop_type_mstr_id', '!=', 4)
                 ->groupBy('ref_prop_usage_types.usage_type')
                 ->select(
                     'ref_prop_usage_types.usage_type AS usage_type',
                     DB::raw('COUNT(DISTINCT prop_properties.id) AS Total')
                 )
                 ->get();
 
                 return responseMsg(true, "Usage type detail reports", $result, "010501", "1.0", "", "POST", "");
             } catch (Exception $e) {
                 return responseMsg(false, $e->getMessage(),"");
             }
     }
 
     public function propertyTypeSummary(Request $request)
     {
         try {
             $query = "
                 SELECT
                     ref_prop_types.property_type AS \"Property Type\",
                     COUNT(DISTINCT prop_properties.id) AS \"Total\"
                 FROM
                     prop_properties
                 JOIN ref_prop_types ON ref_prop_types.id = prop_properties.prop_type_mstr_id
                 GROUP BY
                     ref_prop_types.property_type
             ";
     
             $data = DB::table(DB::raw("($query) AS prop"))->get();
     
             return responseMsg(true, "Property type summary report", $data, "010501", "1.0", "", "POST", "");
         } catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
     }
 
     public function propertyTypeReport(Request $request)
     {
         try {
             $validator = Validator::make($request->all(), [
                 
                 "constructionType" => "nullable|array",
                 "usageType" => "nullable|array",
                 "propertyType" => "nullable|int",
                 "wardNo" => "nullable|string",
                 "zone" => "nullable|string",
                 "holdingNo"=>"nullable",
                 "ownerName"=>"nullable|string",
                 "mobileNo"=>"nullable",
                 "property_no"=>"nullable"
             ]);
     
             if ($validator->fails()) {
                 return response()->json([
                     'status' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 200);
             }
             
            
             $constructionType = $request->constructionType;
             $usageType = $request->usageType;
             $propertyType = $request->propertyType;
             $wardNo = $request->wardNo;
             $zone = $request->zone;
             $holdingNo = $request->holdingNo;
             $ownerName = $request->ownerName;
             $mobileNo = $request->mobileNo;
             $property_no = $request->property_no;
 
             $perPage = $request->perPage ? $request->perPage : 10;
             $page = $request->page && $request->page > 0 ? $request->page : 1;
             $limit = $perPage;
             $offset =  $request->page && $request->page > 1 ? ($request->page * $perPage) : 0;
 
 
             $query = "
                 SELECT
                     prop_properties.property_no,
                     prop_properties.holding_no,
                     zone_masters.zone_name,
                     ulb_ward_masters.ward_name,
                     prop_properties.prop_address,
                     ref_prop_types.property_type,
                     floors.total_floors,
                     floors.construction_type_ids,
                     floors.usage_type_ids,
                     floors.construction_type,
                     floors.usage_type,
                     owner.owner_name,
                     owner.mobile_no
                 FROM prop_properties
                 JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_properties.ward_mstr_id
                 JOIN zone_masters ON zone_masters.id = prop_properties.zone_mstr_id
                 LEFT JOIN ref_prop_types ON ref_prop_types.id = prop_properties.prop_type_mstr_id
                 LEFT JOIN (
                     SELECT
                         prop_owners.property_id,
                         STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                         STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no
                     FROM prop_owners
                     WHERE prop_owners.status = 1
                     GROUP BY prop_owners.property_id
                 ) AS owner ON owner.property_id = prop_properties.id
                 LEFT JOIN (
                     SELECT
                         prop_floors.property_id,
                         Count(prop_floors.id) as total_floors,
                         STRING_AGG(ref_prop_construction_types.construction_type, ', ') as construction_type,
                         STRING_AGG(ref_prop_construction_types.id::text, ', ') as construction_type_ids,
                         STRING_AGG(ref_prop_usage_types.usage_type, ', ') as usage_type,
                         STRING_AGG(ref_prop_usage_types.id::text, ', ') as usage_type_ids
                     FROM prop_floors 
                     JOIN ref_prop_construction_types ON ref_prop_construction_types.id = prop_floors.const_type_mstr_id
                     JOIN ref_prop_usage_types ON ref_prop_usage_types.id = prop_floors.usage_type_mstr_id
                     GROUP BY prop_floors.property_id
                 ) floors ON floors.property_id = prop_properties.id
             ";
             // dd($query);
            
             
                     
             $conditions = [];
     
     
             if ($constructionType) {
                 $conditions[] = "$constructionType[0] = ANY(STRING_TO_ARRAY(floors.construction_type_ids, ',')::INT[])";
             }
     
             if ($usageType) {
                 $conditions[] = "$usageType[0] =ANY(STRING_TO_ARRAY(floors.usage_type_ids, ',')::INT[])";
             }
     
             if ($propertyType) {
                 $conditions[] = "ref_prop_types.id = $propertyType";
             }
     
             if ($wardNo) {
                 $conditions[] = "ulb_ward_masters.ward_name = '$wardNo'";
             }
     
             if ($zone) {
                 $conditions[] = "zone_masters.zone_name = '$zone'";
             }
             if ($holdingNo) {
                 $conditions[] = "prop_properties.holding_no = '$holdingNo'";
             }
             if ($ownerName) {
                 $conditions[] = "owner.owner_name = '$ownerName'";
             }
             if ($mobileNo) {
                 $conditions[] = "owner.mobile_no = '$mobileNo'";
             }
             if ($property_no) {
                 $conditions[] = " prop_properties.property_no = '$property_no'";
             }
 
             
             // $count = $query;
 
             if (!empty($conditions)) {
                 $query .= " WHERE " . implode(" AND ", $conditions);
             }
             $count = $query;
             $query .= " LIMIT $limit OFFSET $offset "; 
     
            
     
             $data = DB::table(DB::raw("($query) AS prop"))->get();
             $items = $data;
             $total = (collect(DB::SELECT("SELECT COUNT(*) AS total FROM ($count) aggrigate_table"))->first())->total ?? 0;
             $lastPage = ceil($total / $perPage);
             $list = [                
                 "current_page" => $page,
                 "data" => $data,
                 "total" => $total,
                 "per_page" => $perPage,
                 "last_page" => $lastPage 
             ];
             return responseMsg(true, "Property type report", $list, "010501", "1.0", "", "POST", "");
         } catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
             
     }
 
 
     public function safReport(Request $request)
     {
         try {
             $validator = Validator::make($request->all(), [
                 "zoneId" => "nullable",
                 "wardId" => "nullable",
             ]);
 
             if ($validator->fails()) {
                 return response()->json([
                     'status' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 200);
             }
 
             $zoneId = $request->zoneId;
             $wardId = $request->wardNo;
 
             $query = "
                 SELECT
                     assessment_type AS \"SAF Type\",
                     COUNT(id) AS \"Total Transaction\",
                     SUM(amount) AS \"Total Amount\"
                 FROM (
                     SELECT
                         safs.assessment_type,
                         transactions.id,
                         transactions.amount,
                         zone_masters.zone_name,
                         ulb_ward_masters.ward_name
                     FROM
                         prop_active_safs safs
                     LEFT JOIN
                         prop_transactions transactions ON transactions.saf_id = safs.id
                     JOIN ulb_ward_masters ON ulb_ward_masters.id = safs.ward_mstr_id
                     JOIN zone_masters ON zone_masters.id = safs.zone_mstr_id
                     WHERE 
                     " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                     " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     
                     UNION 
                     
                     SELECT
                         safs.assessment_type,
                         transactions.id,
                         transactions.amount,
                         zone_masters.zone_name,
                         ulb_ward_masters.ward_name
                     FROM
                         prop_rejected_safs safs
                     LEFT JOIN
                         prop_transactions transactions ON transactions.saf_id = safs.id
                     JOIN ulb_ward_masters ON ulb_ward_masters.id = safs.ward_mstr_id
                     JOIN zone_masters ON zone_masters.id = safs.zone_mstr_id
                     WHERE 
                     " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                     " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     UNION 
                     
                     SELECT
                         safs.assessment_type,
                         transactions.id,
                         transactions.amount,
                         zone_masters.zone_name,
                         ulb_ward_masters.ward_name
                     FROM
                         prop_safs safs
                     LEFT JOIN
                         prop_transactions transactions ON transactions.saf_id = safs.id
                     JOIN ulb_ward_masters ON ulb_ward_masters.id = safs.ward_mstr_id
                     JOIN zone_masters ON zone_masters.id = safs.zone_mstr_id
                     WHERE 
                     " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                     " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                 ) AS combined_data
                 GROUP BY
                     assessment_type;
             ";
 
 
             $data = DB::select($query);
             return responseMsg(true, "Saf report", $data, "010501", "1.0", "", "POST", "");
         } catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
 
     }
 
     public function safCollectionReport(Request $request)
     {
         $validator = Validator::make($request->all(), [
             "fromDate" => "nullable|date|date_format:Y-m-d",
             "uptoDate" => "nullable|date|date_format:Y-m-d",
             'zoneId' => "nullable",
             'wardId' => "nullable",
 
         ]);
         if ($validator->fails()) {
             return response()->json([
                 'status' => false,
                 'message' => 'validation error',
                 'errors' => $validator->errors()
             ], 200);
         }
         try{
             $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
             $wardId = $zoneId = $userId = null;
             if ($request->fromDate ) {
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
             $query = "
                     WITH assessment_types AS (
                         (
                             SELECT DISTINCT (assessment_type) AS assessment_type
                             FROM prop_active_safs safs
                         )
                         UNION
                         (
                             SELECT DISTINCT (assessment_type) AS assessment_type
                             FROM prop_rejected_safs safs
                         )
                         UNION
                         (
                             SELECT DISTINCT (assessment_type) AS assessment_type
                             FROM prop_safs safs
                         )
                     ),
                     safs AS (
                         (
                             SELECT
                                 safs.assessment_type,
                                 safs.id,
                                 safs.ward_mstr_id,
                                 safs.zone_mstr_id,
                                 transactions.id AS tran_id
                             FROM prop_active_safs safs
                             JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                             WHERE
                                 safs.is_gb_saf != TRUE
                                 AND transactions.status IN (1, 2)
                                 AND safs.status = 1
                                 AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                 " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                                 " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                         )
                         UNION
                         (
                             SELECT
                                 safs.assessment_type,
                                 safs.id,
                                 safs.ward_mstr_id,
                                 safs.zone_mstr_id,
                                 transactions.id AS tran_id
                             FROM prop_rejected_safs safs
                             JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                             WHERE
                                 safs.is_gb_saf != TRUE
                                 AND transactions.status IN (1, 2)
                                 AND safs.status = 1
                                 AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                 " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                                 " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                         )
                         UNION
                         (
                             SELECT
                                 safs.assessment_type,
                                 safs.id,
                                 safs.ward_mstr_id,
                                 safs.zone_mstr_id,
                                 transactions.id AS tran_id
                             FROM prop_safs safs
                             JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                             WHERE
                                 safs.is_gb_saf != TRUE
                                 AND transactions.status IN (1, 2)
                                 AND safs.status = 1
                                 AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                 " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                                 " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                         )
                     ),
                     fine_rebate AS (
                         SELECT
                             SUM(CASE WHEN prop_penaltyrebates.is_rebate = TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS rebate,
                             SUM(CASE WHEN prop_penaltyrebates.is_rebate != TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS penalty,
                             tran_id
                         FROM prop_penaltyrebates
                         JOIN prop_transactions transactions ON transactions.id = prop_penaltyrebates.tran_id
                         GROUP BY prop_penaltyrebates.tran_id
                     )
                 
                     SELECT
                         assessment_types.assessment_type AS saf_type,
                         COUNT(DISTINCT safs.id) AS total_application,
                         COUNT(transactions.id) AS total_bill_cute,
                         SUM(COALESCE(transactions.amount, 0)) AS amount,
                         SUM(COALESCE(rebate, 0)) AS rebate,
                         SUM(COALESCE(penalty, 0)) AS penalty,
                         (SUM(COALESCE(transactions.amount, 0)) - SUM(COALESCE(rebate, 0))) AS total_demand
                     FROM assessment_types
                     LEFT JOIN safs ON safs.assessment_type = assessment_types.assessment_type
                     LEFT JOIN prop_transactions transactions ON safs.tran_id = transactions.id
                         AND transactions.status IN (1, 2)
                         AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                     LEFT JOIN fine_rebate ON fine_rebate.tran_id = transactions.id
                     GROUP BY assessment_types.assessment_type
                 ";
                 
             $data = DB::select($query);
             return responseMsg(true, "Saf collection report", $data, "010501", "1.0", "", "POST", "");
         }
         catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
     }
 
     public function gbSafCollectionReport(Request $request)
     {
         $validator = Validator::make($request->all(), [
             "fromDate" => "nullable|date|date_format:Y-m-d",
             "uptoDate" => "nullable|date|date_format:Y-m-d",
             'zoneId' => "nullable",
             'wardId' => "nullable",
 
         ]);
         if ($validator->fails()) {
             return response()->json([
                 'status' => false,
                 'message' => 'validation error',
                 'errors' => $validator->errors()
             ], 200);
         }
         try{
             $fromDate = $uptoDate = Carbon::now()->format('Y-m-d');
             $wardId = $zoneId = $userId = null;
             if ($request->fromDate ) {
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
             $query = "
                     WITH assessment_types AS (
                         (
                             SELECT DISTINCT (assessment_type) AS assessment_type
                             FROM prop_active_safs safs
                         )
                         UNION
                         (
                             SELECT DISTINCT (assessment_type) AS assessment_type
                             FROM prop_rejected_safs safs
                         )
                         UNION
                         (
                             SELECT DISTINCT (assessment_type) AS assessment_type
                             FROM prop_safs safs
                         )
                     ),
                     safs AS (
                         (
                             SELECT
                                 safs.assessment_type,
                                 safs.id,
                                 safs.ward_mstr_id,
                                 safs.zone_mstr_id,
                                 transactions.id AS tran_id
                             FROM prop_active_safs safs
                             JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                             WHERE
                                 safs.is_gb_saf = TRUE
                                 AND transactions.status IN (1, 2)
                                 AND safs.status = 1
                                 AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                 " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                                 " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                         )
                         UNION
                         (
                             SELECT
                                 safs.assessment_type,
                                 safs.id,
                                 safs.ward_mstr_id,
                                 safs.zone_mstr_id,
                                 transactions.id AS tran_id
                             FROM prop_rejected_safs safs
                             JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                             WHERE
                                 safs.is_gb_saf = TRUE
                                 AND transactions.status IN (1, 2)
                                 AND safs.status = 1
                                 AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                 " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                                 " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                         )
                         UNION
                         (
                             SELECT
                                 safs.assessment_type,
                                 safs.id,
                                 safs.ward_mstr_id,
                                 safs.zone_mstr_id,
                                 transactions.id AS tran_id
                             FROM prop_safs safs
                             JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                             WHERE
                                 safs.is_gb_saf = TRUE
                                 AND transactions.status IN (1, 2)
                                 AND safs.status = 1
                                 AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                                 " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                                 " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                         )
                     ),
                     fine_rebate AS (
                         SELECT
                             SUM(CASE WHEN prop_penaltyrebates.is_rebate = TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS rebate,
                             SUM(CASE WHEN prop_penaltyrebates.is_rebate != TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS penalty,
                             tran_id
                         FROM prop_penaltyrebates
                         JOIN prop_transactions transactions ON transactions.id = prop_penaltyrebates.tran_id
                         GROUP BY prop_penaltyrebates.tran_id
                     )
                 
                     SELECT
                         assessment_types.assessment_type AS saf_type,
                         COUNT(DISTINCT safs.id) AS total_application,
                         COUNT(transactions.id) AS total_bill_cute,
                         SUM(COALESCE(transactions.amount, 0)) AS amount,
                         SUM(COALESCE(rebate, 0)) AS rebate,
                         SUM(COALESCE(penalty, 0)) AS penalty,
                         (SUM(COALESCE(transactions.amount, 0)) - SUM(COALESCE(rebate, 0))) AS total_demand
                     FROM assessment_types
                     LEFT JOIN safs ON safs.assessment_type = assessment_types.assessment_type
                     LEFT JOIN prop_transactions transactions ON safs.tran_id = transactions.id
                         AND transactions.status IN (1, 2)
                         AND transactions.tran_date BETWEEN '$fromDate' AND '$uptoDate'
                     LEFT JOIN fine_rebate ON fine_rebate.tran_id = transactions.id
                     GROUP BY assessment_types.assessment_type
                 ";
                 
             $data = DB::select($query);
             return responseMsg(true, "Saf collection report", $data, "010501", "1.0", "", "POST", "");
         }
         catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
     }
 
     public function safAppliedReport(Request $request)
     {
         try {
             $validator = Validator::make($request->all(), [
                 'fromDate' => 'required|date',
                 'toDate' => 'required|date',
                 'zoneId' => "nullable",
                 'wardId' => "nullable",
             ]);
     
             if ($validator->fails()) {
                 return response()->json([ 
                     'status' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 200);
             }
             // $request->validate([
             //     'fromDate' => 'required|date',
             //     'toDate' => 'required|date',
             //     'zoneId' => "nullable",
             //     'wardId' => "nullable",
             // ]);
 
        
             $fromDate = $request->fromDate;
             $toDate = $request->toDate;
             $zoneId = $request->zoneId;
             $wardId = $request->wardId;
           
             $query = "
                 WITH assessment_types AS (
                     (
                         SELECT DISTINCT (assessment_type) AS assessment_type
                         FROM prop_active_safs safs
                     )
                     UNION
                     (
                         SELECT DISTINCT (assessment_type) AS assessment_type
                         FROM prop_rejected_safs safs
                     )
                     UNION
                     (
                         SELECT DISTINCT (assessment_type) AS assessment_type
                         FROM prop_safs safs
                     )
                 ),
                 safs AS (
                     (
                         SELECT
                             safs.assessment_type,
                             safs.id,
                             safs.ward_mstr_id,
                             safs.zone_mstr_id,
                             transactions.id AS tran_id
                         FROM prop_active_safs safs
                         JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                         WHERE
                             safs.is_gb_saf != TRUE
                             AND transactions.status IN (1, 2)
                             AND safs.status = 1
                             AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                             " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                             " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     )
                     UNION
                     (
                         SELECT
                             safs.assessment_type,
                             safs.id,
                             safs.ward_mstr_id,
                             safs.zone_mstr_id,
                             transactions.id AS tran_id
                         FROM prop_rejected_safs safs
                         JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                         WHERE
                             safs.is_gb_saf != TRUE
                             AND transactions.status IN (1, 2)
                             AND safs.status = 1
                             AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                             " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                             " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     )
                     UNION
                     (
                         SELECT
                             safs.assessment_type,
                             safs.id,
                             safs.ward_mstr_id,
                             safs.zone_mstr_id,
                             transactions.id AS tran_id
                         FROM prop_safs safs
                         JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                         WHERE
                             safs.is_gb_saf != TRUE
                             AND transactions.status IN (1, 2)
                             AND safs.status = 1
                             AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                             " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                             " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     )
                 ),
                 fine_rebate AS (
                     SELECT
                         SUM(CASE WHEN prop_penaltyrebates.is_rebate = TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS rebate,
                         SUM(CASE WHEN prop_penaltyrebates.is_rebate != TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS penalty,
                         tran_id
                     FROM prop_penaltyrebates
                     JOIN prop_transactions transactions ON transactions.id = prop_penaltyrebates.tran_id
                     GROUP BY prop_penaltyrebates.tran_id
                 )
                 
                 SELECT
                     assessment_types.assessment_type AS saf_type,
                     COUNT(DISTINCT safs.id) AS total_application,
                     COUNT(transactions.id) AS total_bill_cute,
                     SUM(COALESCE(transactions.amount, 0)) AS amount,
                     SUM(COALESCE(rebate, 0)) AS rebate,
                     SUM(COALESCE(penalty, 0)) AS penalty,
                     (SUM(COALESCE(transactions.amount, 0)) - SUM(COALESCE(rebate, 0))) AS total_demand
                 FROM assessment_types
                 LEFT JOIN safs ON safs.assessment_type = assessment_types.assessment_type
                 LEFT JOIN prop_transactions transactions ON safs.tran_id = transactions.id
                     AND transactions.status IN (1, 2)
                     AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                 LEFT JOIN fine_rebate ON fine_rebate.tran_id = transactions.id
                 GROUP BY assessment_types.assessment_type;
             ";
 
         
             $data = DB::select($query);
             return responseMsg(true, "Saf applied report", $data, "010501", "1.0", "", "POST", "");
         }
         catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
     }
 
     public function gbSafAppliedReport(Request $request)
     {
         try {            
             $validator = Validator::make($request->all(), [
                 'fromDate' => 'required|date',
                 'toDate' => 'required|date',
                 'zoneId' => "nullable",
                 'wardId' => "nullable",
             ]);
     
             if ($validator->fails()) {
                 return response()->json([
                     'status' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 200);
             }
 
        
             $fromDate = $request->fromDate;
             $toDate = $request->toDate;
             $zoneId = $request->zoneId;
             $wardId = $request->wardId;
           
             $query = "
                 WITH assessment_types AS (
                     (
                         SELECT DISTINCT (assessment_type) AS assessment_type
                         FROM prop_active_safs safs
                     )
                     UNION
                     (
                         SELECT DISTINCT (assessment_type) AS assessment_type
                         FROM prop_rejected_safs safs
                     )
                     UNION
                     (
                         SELECT DISTINCT (assessment_type) AS assessment_type
                         FROM prop_safs safs
                     )
                 ),
                 safs AS (
                     (
                         SELECT
                             safs.assessment_type,
                             safs.id,
                             safs.ward_mstr_id,
                             safs.zone_mstr_id,
                             transactions.id AS tran_id
                         FROM prop_active_safs safs
                         JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                         WHERE
                             safs.is_gb_saf = TRUE
                             AND transactions.status IN (1, 2)
                             AND safs.status = 1
                             AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                             " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                             " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     )
                     UNION
                     (
                         SELECT
                             safs.assessment_type,
                             safs.id,
                             safs.ward_mstr_id,
                             safs.zone_mstr_id,
                             transactions.id AS tran_id
                         FROM prop_rejected_safs safs
                         JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                         WHERE
                             safs.is_gb_saf = TRUE
                             AND transactions.status IN (1, 2)
                             AND safs.status = 1
                             AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                             " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                             " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     )
                     UNION
                     (
                         SELECT
                             safs.assessment_type,
                             safs.id,
                             safs.ward_mstr_id,
                             safs.zone_mstr_id,
                             transactions.id AS tran_id
                         FROM prop_safs safs
                         JOIN prop_transactions transactions ON transactions.saf_id = safs.id
                         WHERE
                             safs.is_gb_saf = TRUE
                             AND transactions.status IN (1, 2)
                             AND safs.status = 1
                             AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                             " . ($zoneId ? "AND safs.zone_mstr_id = $zoneId " : "") . "
                             " . ($wardId ? "AND safs.ward_mstr_id = $wardId " : "") . "
                     )
                 ),
                 fine_rebate AS (
                     SELECT
                         SUM(CASE WHEN prop_penaltyrebates.is_rebate = TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS rebate,
                         SUM(CASE WHEN prop_penaltyrebates.is_rebate != TRUE THEN prop_penaltyrebates.amount ELSE 0 END) AS penalty,
                         tran_id
                     FROM prop_penaltyrebates
                     JOIN prop_transactions transactions ON transactions.id = prop_penaltyrebates.tran_id
                     GROUP BY prop_penaltyrebates.tran_id
                 )
                 
                 SELECT
                     assessment_types.assessment_type AS saf_type,
                     COUNT(DISTINCT safs.id) AS total_application,
                     COUNT(transactions.id) AS total_bill_cute,
                     SUM(COALESCE(transactions.amount, 0)) AS amount,
                     SUM(COALESCE(rebate, 0)) AS rebate,
                     SUM(COALESCE(penalty, 0)) AS penalty,
                     (SUM(COALESCE(transactions.amount, 0)) - SUM(COALESCE(rebate, 0))) AS total_demand
                 FROM assessment_types
                 LEFT JOIN safs ON safs.assessment_type = assessment_types.assessment_type
                 LEFT JOIN prop_transactions transactions ON safs.tran_id = transactions.id
                     AND transactions.status IN (1, 2)
                     AND transactions.tran_date BETWEEN '$fromDate' AND '$toDate'
                 LEFT JOIN fine_rebate ON fine_rebate.tran_id = transactions.id
                 GROUP BY assessment_types.assessment_type;
             ";
 
         
             $data = DB::select($query);
             return responseMsg(true, "Saf applied report", $data, "010501", "1.0", "", "POST", "");
         }
         catch (Exception $e) {
             return responseMsg(false, $e->getMessage(), "");
         }
     }
 
     public function safAppliedTypesDetailsReport(Request $request)
     {
         try{
 
            $query = "
                        SELECT 
                    prop_safs.saf_no,
                    prop_safs.id AS Saf_id,
                    zone_masters.zone_name,
                    ulb_ward_masters.ward_name,
                    prop_safs.prop_address,
                    prop_safs.holding_no,
                    prop_transactions.amount,
                    prop_transactions.payment_mode,
                    prop_safs.assessment_type,
                
                    owner.owner_name,
                    owner.mobile_no
                FROM prop_transactions
                join (
                    (
                        select prop_safs.saf_no,prop_safs.id,prop_safs.prop_address,prop_safs.assessment_type,prop_safs.holding_no,
                            prop_safs.ward_mstr_id,prop_safs.zone_mstr_id
                        from prop_active_safs as prop_safs
                        join prop_transactions on prop_transactions.saf_id = prop_safs.id
                    )	
                    union(
                        select prop_safs.saf_no,prop_safs.id,prop_safs.prop_address,prop_safs.assessment_type,prop_safs.holding_no,
                            prop_safs.ward_mstr_id,prop_safs.zone_mstr_id
                        from prop_rejected_safs as prop_safs
                        join prop_transactions on prop_transactions.saf_id = prop_safs.id
                    )
                    union(
                        select prop_safs.saf_no,prop_safs.id,prop_safs.prop_address,prop_safs.assessment_type,prop_safs.holding_no,
                            prop_safs.ward_mstr_id,prop_safs.zone_mstr_id
                        from prop_safs as prop_safs
                        join prop_transactions on prop_transactions.saf_id = prop_safs.id
                    )
                )prop_safs on prop_safs.id = prop_transactions.saf_id
                JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_safs.ward_mstr_id
                JOIN zone_masters ON zone_masters.id = prop_safs.zone_mstr_id
                --JOIN prop_active_safs ON prop_active_safs.holding_no = prop_safs.holding_no
                --JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id 
    
                LEFT JOIN (
                    (
                        SELECT
                            prop_owners.saf_id,
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                            STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                        FROM prop_active_safs_owners as  prop_owners
                        join prop_transactions on prop_transactions.saf_id = prop_owners.saf_id
                        WHERE prop_owners.status = 1
                        GROUP BY prop_owners.saf_id
                    )
                    union(
                        SELECT
                            prop_owners.saf_id,
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                            STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                        FROM prop_rejected_safs_owners as  prop_owners
                        join prop_transactions on prop_transactions.saf_id = prop_owners.saf_id
                        WHERE prop_owners.status = 1
                        GROUP BY prop_owners.saf_id
                    )
                    union(
                        SELECT
                            prop_owners.saf_id,
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                            STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                        FROM prop_safs_owners as  prop_owners
                        join prop_transactions on prop_transactions.saf_id = prop_owners.saf_id
                        WHERE prop_owners.status = 1
                        GROUP BY prop_owners.saf_id
                    )
                ) AS owner ON owner.saf_id = prop_safs.id;
    
            ";
            $data = DB::select($query);
            return responseMsg(true, "Saf applied report", $data, "010501", "1.0", "", "POST", "");
        }
        catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
     }
 
     public function safDemandCollectionReport(Request $request)
     {
         try{
 
            $query = "
                        SELECT 
                    prop_safs.saf_no,
                    prop_safs.id AS Saf_id,
                    zone_masters.zone_name,
                    ulb_ward_masters.ward_name,
                    prop_safs.prop_address,
                    prop_safs.holding_no,
                    prop_transactions.amount,
                    prop_safs.assessment_type,
                
                    owner.owner_name,
                    owner.mobile_no
                FROM prop_transactions
                join (
                    (
                        select prop_safs.saf_no,prop_safs.id,prop_safs.prop_address,prop_safs.assessment_type,prop_safs.holding_no,
                            prop_safs.ward_mstr_id,prop_safs.zone_mstr_id
                        from prop_active_safs as prop_safs
                        join prop_transactions on prop_transactions.saf_id = prop_safs.id
                    )	
                    union(
                        select prop_safs.saf_no,prop_safs.id,prop_safs.prop_address,prop_safs.assessment_type,prop_safs.holding_no,
                            prop_safs.ward_mstr_id,prop_safs.zone_mstr_id
                        from prop_rejected_safs as prop_safs
                        join prop_transactions on prop_transactions.saf_id = prop_safs.id
                    )
                    union(
                        select prop_safs.saf_no,prop_safs.id,prop_safs.prop_address,prop_safs.assessment_type,prop_safs.holding_no,
                            prop_safs.ward_mstr_id,prop_safs.zone_mstr_id
                        from prop_safs as prop_safs
                        join prop_transactions on prop_transactions.saf_id = prop_safs.id
                    )
                )prop_safs on prop_safs.id = prop_transactions.saf_id
                JOIN ulb_ward_masters ON ulb_ward_masters.id = prop_safs.ward_mstr_id
                JOIN zone_masters ON zone_masters.id = prop_safs.zone_mstr_id
                --JOIN prop_active_safs ON prop_active_safs.holding_no = prop_safs.holding_no
                --JOIN prop_transactions ON prop_transactions.saf_id = prop_safs.id 
    
                LEFT JOIN (
                    (
                        SELECT
                            prop_owners.saf_id,
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                            STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                        FROM prop_active_safs_owners as  prop_owners
                        join prop_transactions on prop_transactions.saf_id = prop_owners.saf_id
                        WHERE prop_owners.status = 1
                        GROUP BY prop_owners.saf_id
                    )
                    union(
                        SELECT
                            prop_owners.saf_id,
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                            STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                        FROM prop_rejected_safs_owners as  prop_owners
                        join prop_transactions on prop_transactions.saf_id = prop_owners.saf_id
                        WHERE prop_owners.status = 1
                        GROUP BY prop_owners.saf_id
                    )
                    union(
                        SELECT
                            prop_owners.saf_id,
                            STRING_AGG(prop_owners.owner_name, ',') AS owner_name,
                            STRING_AGG(CAST(prop_owners.mobile_no AS TEXT), ',') AS mobile_no       
                        FROM prop_safs_owners as  prop_owners
                        join prop_transactions on prop_transactions.saf_id = prop_owners.saf_id
                        WHERE prop_owners.status = 1
                        GROUP BY prop_owners.saf_id
                    )
                ) AS owner ON owner.saf_id = prop_safs.id;
    
            ";
            $data = DB::select($query);
            return responseMsg(true, "Saf applied report", $data, "010501", "1.0", "", "POST", "");
        }
        catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
     }
     #======================================================*/
}
