<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Http\Requests\Property\Reports\CollectionReport;
use App\Http\Requests\Property\Reports\Levelformdetail;
use App\Http\Requests\Property\Reports\LevelUserPending;
use App\Http\Requests\Property\Reports\SafPropIndividualDemandAndCollection;
use App\Http\Requests\Property\Reports\UserWiseLevelPending;
use App\Http\Requests\Property\Reports\UserWiseWardWireLevelPending;
use App\Models\MplYearlyReport;
use App\Models\Property\PropDemand;
use App\Models\Property\PropTransaction;
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

#------------date 13/03/2023 -------------------------------------------------------------------------
#   Code By Sandeep Bara
#   Payment Mode Wise Collection Report

class ReportController extends Controller
{
    use Auth;
    use Report;

    private $Repository;
    private $_common;
    public function __construct(IReport $TradeRepository)
    {
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

        $data = $mPropDemand->wardWiseHolding($mreq);

        $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
        return responseMsgs(true, "Ward Wise Holding Data!", $data, 'pr6.1', '1.1', $queryRunTime, 'Post', '');
    }

    /**
     * | List of financial year
     */
    public function listFY(Request $request)
    {
        $currentYear = Carbon::now()->year;
        $financialYears = array();
        $currentYear = date('Y');

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

            $transaction = $mpropTransaction->tranDtl($userId, $fromDate, $toDate);

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

    /**
     * | Dcb Pie Chart
     */
    public function dcbPieChart(Request $request)
    {
        return $this->Repository->dcbPieChart($request);
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
            $request->merge(["userId" => $userId]);
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
            $data['Payment Modes']['current_year_online_collection']     = round(($currentYearData->online_application_amount_this_year ?? 0) / 10000000, 2);               #_in cr
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
            $data['Collection']['prev_year']         = round(($prevYearData->demand_coll_this_year ?? 0) / 10000000, 2); #_in cr
            $data['Collection']['current_year']      = round(($currentYearData->demand_coll_this_year ?? 0) / 10000000, 2); #_in cr
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
            $toDayCollection = PropTransaction::whereIn('status', [1, 2])->where("tran_date", $currentDate)->sum("amount");
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
                    GROUP BY prop_transactions.user_id
                    ORDER BY prop_transactions.user_id
                )prop_transactions
                JOIN users ON users.id = prop_transactions.user_id
            ";
            $data = DB::select($sql . " limit $limit offset $offset");
            $count = (collect(DB::SELECT("SELECT COUNT(*)AS total, SUM(total_amount) AS total_amount,sum(total_tran) as total_tran
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

    /**
     * | Inserting Data in report table for live dashboard
     */
    public function data(Request $request)
    {

        // return  SMSAKGOVT(8797770238, "hello", "123");

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
                        AND application_date BETWEEN '2023-04-01' 	-- Parameterise This
                        AND '2024-03-31' 							-- Parameterise this
                      UNION ALL 
                      SELECT 
                        id 
                      FROM 
                        prop_safs 
                      WHERE 
                        status = 1  AND ulb_id=2
                        AND application_date BETWEEN '2023-04-01' 	-- Parameterise this
                        AND '2024-03-31' 								-- Parameterise this
                      UNION ALL 
                      SELECT 
                        id 
                      FROM 
                        prop_rejected_safs 
                      WHERE 
                        status = 1 AND ulb_id=2
                        AND application_date BETWEEN '2023-04-01' 	-- Parameterise this
                        AND '2024-03-31'								-- Parameterise this
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
                        AND application_date BETWEEN '2023-04-01' 			--Parameterise this
                        AND '2024-03-31' 									--Parameterise this
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
                        AND application_date BETWEEN '2023-04-01' 			--Parameterise this
                        AND '2024-03-31' 									--Parameterise this
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
                        AND application_date BETWEEN '2023-04-01' 			--Parameterise this
                        AND '2024-03-31'									--Parameterise this
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
                              WHERE d.fyear='2023-2024'
                              AND d.status=1 AND d.paid_status=0 AND p.status=1 AND p.ulb_id=2
                          ) AS outstandings,
                          (
                              SELECT SUM(balance) AS recoverable_demand_currentyr
                                     FROM prop_demands
                              WHERE status=1 AND fyear='2023-2024' AND ulb_id=2      					-- Parameterise This
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
                            
                                   CASE WHEN tran_date BETWEEN '2023-04-01' AND '2024-03-31' THEN SUM(amount) END AS currentyr_pmt_amt,	-- Parameterize this for current yr fyear range date
                                   CASE WHEN tran_date BETWEEN '2023-04-01' AND '2024-03-31' THEN COUNT(id) END AS currentyr_pmt_cnt      -- Parameterize this for current yr fyear range date
                            
                              FROM prop_transactions
                              WHERE tran_date BETWEEN '2022-04-01' AND '2024-03-31' AND status=1	AND ulb_id=2			-- Parameterize this for last two yrs fyear range date
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
                                          COALESCE(CASE WHEN application_date BETWEEN '2023-04-01' AND '2024-03-31'		-- Parameterize this for current fyear range date
                                                   THEN count(id) END,0) AS current_yr_mutation_count
                                
                                          FROM prop_active_safs
                                          WHERE assessment_type='Mutation' AND status=1
                                          AND application_date BETWEEN '2022-04-01' AND '2024-03-31' AND ulb_id=2
                                      GROUP BY application_date
                                
                                      UNION ALL 
                                
                                      SELECT 
                                
                                          COALESCE(CASE WHEN application_date BETWEEN '2022-04-01' AND '2023-03-31'         -- Parameterize this for last yr fyear range date
                                                  THEN count(id) END,0)AS last_yr_mutation_count,
                                          COALESCE(CASE WHEN application_date BETWEEN '2023-04-01' AND '2024-03-31'		   -- Parameterize this for current fyear range date
                                                   THEN count(id) END,0) AS current_yr_mutation_count
                                
                                          FROM prop_safs
                                          WHERE assessment_type='Mutation' AND status=1
                                          AND application_date BETWEEN '2022-04-01' AND '2024-03-31' AND ulb_id=2
                                      GROUP BY application_date
                                
                                      UNION ALL
                                
                                      SELECT 
                                
                                          COALESCE(CASE WHEN application_date BETWEEN '2022-04-01' AND '2023-03-31'		-- Parameterize this for last yr fyear range date
                                                   THEN count(id) END,0)AS last_yr_mutation_count,
                                          COALESCE(CASE WHEN application_date BETWEEN '2023-04-01' AND '2024-03-31'		-- Parameterize this for current fyear range date
                                                   THEN count(id) END,0) AS current_yr_mutation_count
                                
                                          FROM prop_rejected_safs
                                          WHERE assessment_type='Mutation' AND status=1
                                          AND application_date BETWEEN '2022-04-01' AND '2024-03-31' AND ulb_id=2			-- Parameterize this for last two fyear range date
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
                                  WHERE t.tran_date BETWEEN '2023-04-01' AND '2024-03-31'							-- Parameterize this for current fyear range date
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
                                              WHERE application_date BETWEEN '2023-04-01' AND '2024-03-31'       -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id

                                                  UNION ALL 


                                          SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_safs
                                              WHERE application_date BETWEEN '2023-04-01' AND '2024-03-31'     -- Parameterize this for current fyear range date
                                              AND ulb_id=2
                                              GROUP BY ward_mstr_id

                                                  UNION ALL 

                                          SELECT 
                                                   COUNT(id) AS application_count,
                                                   ward_mstr_id

                                              FROM prop_rejected_safs
                                              WHERE application_date BETWEEN '2023-04-01' AND '2024-03-31'   -- Parameterize this for current fyear range date
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
                                          WHERE fyear='2023-2024'								-- Parameterize this for current fyear range date
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
                           WHERE tran_date BETWEEN '2023-04-01' AND '2024-03-31'					-- Parameterize this for current fyear range date
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
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '2023-04-01' AND '2024-03-31') THEN t.amount ELSE 0 END),0) AS current_year_jskcollection, -- Parameterize this for current fyear range date
                             COALESCE(SUM(CASE WHEN (t.tran_date BETWEEN '2023-04-01' AND '2024-03-31') THEN 1 ELSE 0 END),0) AS current_year_jskcount			  -- Parameterize this for current fyear range date

                          FROM prop_transactions t
                          JOIN users u ON u.id=t.user_id   
                          WHERE UPPER(u.user_type)='JSK' AND u.suspended=false  AND t.status=1
                          AND t.tran_date BETWEEN '2022-04-01' AND '2024-03-31'  AND t.ulb_id=2	-- Parameterize this for last two fyears
                  )

                       SELECT * FROM current_payments,lastyear_payments,jsk_collections
                ) AS payment_modes";
        $data = DB::select($sql);
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
        ];

        $mMplYearlyReport->where('fyear', $currentFy)
            ->update($updateReqs);
    }
}
