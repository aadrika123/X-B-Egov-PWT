<?php

namespace App\Repository\Water\Concrete;

use App\EloquentModels\Common\ModelWard;
use App\Http\Controllers\Water\NewConnectionController;
use App\Http\Controllers\Water\WaterPaymentController;
use App\Models\ActiveCitizen;
use App\Models\Payment\IciciPaymentReq;
use App\Models\Payment\WebhookPaymentData;
use App\Models\UlbMaster;
use App\Models\User;
use App\Models\Water\WaterApplicant;
use App\Models\Water\WaterApplicantDoc;
use App\Models\Water\WaterApplication;
use App\Models\Water\WaterConnectionCharge;
use App\Models\Water\WaterConsumer;
use App\Models\Water\WaterIciciRequest;
use App\Models\Water\WaterParamConnFee;
use App\Models\Water\WaterParamConnFeeOld;
use App\Models\Water\WaterParamDocumentType;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterRazorPayRequest;
use App\Models\Water\WaterRazorPayResponse;
use App\Models\Water\WaterSiteInspection;
use App\Models\Water\WaterSiteInspectionsScheduling;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterTranDetail;
use App\Models\Water\WaterTranFineRebate;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Common\CommonFunction;
use App\Repository\Water\Interfaces\IWaterNewConnection;
use App\Traits\Auth;
use App\Traits\Payment\Razorpay;
use App\Traits\Property\WardPermission;
use App\Traits\Water\WaterTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Predis\Configuration\Option\Exceptions;

use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class WaterNewConnection implements IWaterNewConnection
{
    use Auth;               // Trait Used added by sandeep bara date 17-09-2022
    use WardPermission;
    use Razorpay;
    use WaterTrait;

    /**
     * | Created On-01-12-2022
     * | Created By- Sandeep Bara
     * -----------------------------------------------------------------------------------------
     * | WATER Module
     */

    protected $_modelWard;
    protected $_parent;
    protected $_shortUlbName;
    private $_dealingAssistent;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct()
    {
        $this->_modelWard           = new ModelWard();
        $this->_parent              = new CommonFunction();
        $this->_dealingAssistent    = Config::get('workflow-constants.DEALING_ASSISTENT_WF_ID');
        $this->_DB_NAME             = "pgsql_water";
        $this->_DB                  = DB::connection($this->_DB_NAME);
    }


    /**
     * | Database transaction
     */
    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        DB::beginTransaction();
        if ($db1 != $db2)
            $this->_DB->beginTransaction();
    }
    /**
     * | Database transaction
     */
    public function rollback()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
    }
    /**
     * | Database transaction
     */
    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
    }


    /**
     * | Search the Citizen Related Water Application
       query cost (2.30)
       Site Inspection Condition Hamper
     * ---------------------------------------------------------------------
     * | @var refUser            = authUser()
     * | @var refUserId          = refUser->id      | loging user Id
     * | @var refUlbId           = refUser->ulb_id  | loging user ulb Id
     * | @var connection         = query data  [Model use (WaterApplication , WaterConnectionCharge)]
     * 
     */
    public function getCitizenApplication(Request $request)
    {
        $refUser                = authUser($request);
        $refUserId              = $refUser->id;
        $roleDetails            = Config::get('waterConstaint.ROLE-LABEL');

        $mWaterTran             = new WaterTran();
        $mWaterParamConnFee     = new WaterParamConnFee();
        $mWaterConnectionCharge = new WaterConnectionCharge();
        $mWaterSiteInspection   = new WaterSiteInspection();

        $mWaterPenaltyInstallment           = new WaterPenaltyInstallment();
        $mWaterSiteInspectionsScheduling    = new WaterSiteInspectionsScheduling();
        $refChargeCatagory                  = Config::get("waterConstaint.CHARGE_CATAGORY");
        $refChargeCatagoryValue             = Config::get("waterConstaint.CONNECTION_TYPE");


        $connection = WaterApplication::select(
            "water_applications.id",
            "water_applications.application_no",
            "water_applications.property_type_id",
            "water_applications.address",
            "water_applications.area_sqft",
            "water_applications.payment_status",
            "water_applications.doc_status",
            "water_applications.ward_id",
            "water_applications.workflow_id",
            "water_applications.doc_upload_status",
            "water_applications.apply_from",
            "water_applications.is_field_verified",
            "water_applications.current_role",
            "water_applications.parked",
            "water_applications.category",
            "water_applications.connection_type_id",
            "ulb_ward_masters.ward_name",
            "charges.amount",
            "wf_roles.role_name as current_role_name",
            DB::raw("'connection' AS type,
                                        water_applications.apply_date::date AS apply_date")
        )
            ->join(
                DB::raw("( 
                                        SELECT DISTINCT(water_applications.id) AS application_id , SUM(COALESCE(amount,0)) AS amount
                                        FROM water_applications 
                                        LEFT JOIN water_connection_charges 
                                            ON water_applications.id = water_connection_charges.application_id 
                                            AND ( 
                                                water_connection_charges.paid_status ISNULL  
                                                OR water_connection_charges.paid_status= 0 
                                            )  
                                            AND( 
                                                    water_connection_charges.status = TRUE
                                                    OR water_connection_charges.status ISNULL  
                                                )
                                        WHERE water_applications.user_id = $refUserId
                                        GROUP BY water_applications.id
                                        ) AS charges
                                    "),
                function ($join) {
                    $join->on("charges.application_id", "water_applications.id");
                }
            )
            // ->whereNotIn("status",[0,6,7])
            ->leftjoin('wf_roles', 'wf_roles.id', "=", "water_applications.current_role")
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_applications.ward_id')
            ->where("water_applications.user_id", $refUserId)
            ->orderbydesc('water_applications.id')
            ->get();

        $checkData = collect($connection)->first();
        if (is_null($checkData))
            throw new Exception("Water Applications not found!");

        $returnValue = collect($connection)->map(function ($value)
        use ($mWaterPenaltyInstallment, $refChargeCatagoryValue, $refChargeCatagory, $mWaterTran, $mWaterParamConnFee, $mWaterConnectionCharge, $mWaterSiteInspection, $mWaterSiteInspectionsScheduling, $roleDetails) {

            # checking Penalty payment
            if ($value['payment_status'] == 1 && $value['connection_type_id'] == $refChargeCatagoryValue['REGULAIZATION']) {
                $penaltyDetails = $mWaterPenaltyInstallment->getPenaltyByApplicationId($value['id'])
                    ->where('paid_status', 0)
                    ->get();
                $checkPenalty = collect($penaltyDetails)->first();
                if (is_null($checkPenalty)) {
                    $value['actualPaymentStatus'] = 1;
                } else {
                    $value['actualPaymentStatus'] = 0;
                }
            }

            # show connection charges
            switch ($value['connection_type_id']) {
                case ($refChargeCatagoryValue['REGULAIZATION']):
                    $value['connection_type_name'] = $refChargeCatagory['REGULAIZATION'];
                    break;

                case ($refChargeCatagoryValue['NEW_CONNECTION']):
                    $value['connection_type_name'] = $refChargeCatagory['NEW_CONNECTION'];
                    break;
            }

            $value['transDetails'] = $mWaterTran->getTransNo($value['id'], null)->first();
            $value['calcullation'] = $mWaterParamConnFee->getCallParameter($value['property_type_id'], $value['area_sqft'])->first();
            $refConnectionCharge = $mWaterConnectionCharge->getWaterchargesById($value['id'])
                ->where('paid_status', 0)
                ->first();
            # Formating connection type id 
            $chargeId =  null;
            if (!is_null($refConnectionCharge)) {
                switch ($refConnectionCharge['charge_category']) {
                    case ($refChargeCatagory['SITE_INSPECTON']):
                        $chargeId = $refChargeCatagoryValue['SITE_INSPECTON'];
                        break;
                    case ($refChargeCatagory['NEW_CONNECTION']):
                        $chargeId = $refChargeCatagoryValue['NEW_CONNECTION'];
                        break;
                    case ($refChargeCatagory['REGULAIZATION']):
                        $chargeId = $refChargeCatagoryValue['REGULAIZATION'];
                        break;
                }
                $refConnectionCharge['connectionTypeId'] = $chargeId;
            }
            $refConnectionCharge['type'] = $value['type'];
            $refConnectionCharge['applicationId'] = $value['id'];
            $refConnectionCharge['applicationNo'] = $value['application_no'];
            $value['connectionCharges'] = $refConnectionCharge;

            # Site Details 
            $siteDetails = $mWaterSiteInspection->getInspectionById($value['id'])
                ->where('order_officer', $roleDetails['JE'])
                ->first();
            $checkEmpty = collect($siteDetails)->first();
            if (!is_null($checkEmpty)) {
                $value['siteInspectionCall'] = $mWaterParamConnFee->getCallParameter(
                    $siteDetails['site_inspection_property_type_id'],
                    $siteDetails['site_inspection_area_sqft']
                )->first();
            }
            if ($value['current_role'] == $roleDetails['JE']) {
                $inspectionTime = $mWaterSiteInspectionsScheduling->getInspectionData($value['id'])->first();
                $value['scheduledTime'] = $inspectionTime->inspection_time ?? null;
                $value['scheduledDate'] = $inspectionTime->inspection_date ?? null;
            }

            return $value;
        });
        return $returnValue;
    }

    /**
     *  Genrate the RazorPay OrderId 
       Query const(3.30)
     * ---------------------------------------------------------------------------
     * | @var refUser            = authUser()
     * | @var refUserId          = refUser->id      | loging user Id
     * | @var refUlbId           = refUser->ulb_id  | loging user ulb Id
     */
    public function handeRazorPay(Request $request)
    {
        try {
            $refUser            = authUser($request);
            $isRebate           = null;

            $paramChargeCatagory    = Config::get('waterConstaint.CONNECTION_TYPE');
            $refRegulization        = Config::get('waterConstaint.CHARGE_CATAGORY');
            $url                    = Config::get('razorpay.PAYMENT_GATEWAY_URL');
            $endPoint               = Config::get('razorpay.PAYMENT_GATEWAY_END_POINT');

            $rules = [
                'id'                => 'required|digits_between:1,9223372036854775807',
                'applycationType'   => 'required|string|in:connection,consumer',
            ];
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            #------------ new connection --------------------
            $this->begin();
            if ($request->applycationType == "connection") {
                $application = WaterApplication::find($request->id);
                if (!$application) {
                    throw new Exception("Data Not Found!......");
                }
                $cahges = $this->getWaterConnectionChages($application->id);
                if (!$cahges) {
                    throw new Exception("No Anny Due Amount!......");
                }
                $myRequest = new \Illuminate\Http\Request();
                $myRequest->setMethod('POST');
                $totalAmount = 0;
                $amount = 0;
                $penalty = 0;
                $rebat = 0;
                $amount = $cahges["amount"];
                if (isset($request->isInstallment)) {
                    $validated = Validator::make(
                        $request->all(),
                        [
                            'isInstallment' => 'nullable|in:yes,no'
                        ]
                    );
                    if ($validated->fails())
                        return validationError($validated);
                }
                switch ($request->isInstallment) {
                    case ("no"):
                        if ($application->connection_type_id != $paramChargeCatagory['REGULAIZATION']) {
                            throw new Exceptions("Payment is not under Regulaization!");
                        }
                        $isRebate = 1;
                        $amount = $cahges["amount"];
                        $rebat = $cahges["rabate"];
                        $cahges["penaltyIds"] = $cahges['installment_ids'];
                        $cahges['charge_for'] = $refRegulization['REGULAIZATION'];
                        break;
                    case ("yes"):
                        $validated = Validator::make(
                            $request->all(),
                            [
                                'penaltyIds' => 'required|array',
                            ]
                        );
                        if ($validated->fails())
                            return validationError($validated);
                        if ($application->connection_type_id != $paramChargeCatagory['REGULAIZATION']) {
                            throw new Exceptions("Payment is not under Regulaization!");
                        }
                        $mWaterPenaltyInstallment = new WaterPenaltyInstallment();
                        $peanltyDetails = $mWaterPenaltyInstallment->getPenaltyByArrayOfId($request->penaltyIds);
                        $refPenaltyAmount = collect($peanltyDetails)->sum('balance_amount');
                        $amount = $cahges['conn_fee'];
                        $penalty = $refPenaltyAmount;
                        $cahges["penaltyIds"] = implode(',', $request->penaltyIds);
                        $cahges['charge_for'] = $refRegulization['REGULAIZATION'];
                        break;
                }
                if ($cahges['charge_for'] == $refRegulization['SITE_INSPECTON']) {
                    $amount = $cahges["amount"];
                    $cahges["penaltyIds"] = $cahges['installment_ids'];
                }
                $totalAmount = $amount + $penalty - $rebat;
                if (!$totalAmount) {
                    throw new Exception("minimum 1 rs to be pay");
                }
                $myRequest = new Request([
                    'amount'        => $totalAmount,
                    'workflowId'    => $application->workflow_id,
                    'id'            => $application->id,
                    'departmentId'  => 2,
                    'auth'          => $refUser,
                ]);
                $temp = $this->saveGenerateOrderid($myRequest);
                // $temp = Http::withHeaders([])
                //     ->post($url . $endPoint, $myRequest);                                                   // Static
                // $temp = $temp['data'];

                $RazorPayRequest = new WaterRazorPayRequest;
                $RazorPayRequest->related_id        = $application->id;
                $RazorPayRequest->payment_from      = $cahges['charge_for'];
                $RazorPayRequest->amount            = $totalAmount;
                $RazorPayRequest->demand_from_upto  = $cahges["ids"] == "" ? null : $cahges["ids"];
                $RazorPayRequest->penalty_id        = $cahges["penaltyIds"] ?? null;
                $RazorPayRequest->ip_address        = $request->ip();
                $RazorPayRequest->order_id          = $temp["orderId"];
                $RazorPayRequest->department_id     = $temp["departmentId"];
                $RazorPayRequest->is_rebate         = $isRebate;
                $RazorPayRequest->save();
            }
            #--------------------water Consumer----------------------
            else {
            }
            $whatsapp2 = (Whatsapp_Send(
                "",
                "payment_status",
                [
                    "conten_type" => "text",
                    [
                        $totalAmount,
                        $application->application_no,
                    ]
                ]
            ));
            $this->commit();
            $temp['name']       = $refUser->user_name;
            $temp['mobile']     = $refUser->mobile;
            $temp['email']      = $refUser->email;
            $temp['userId']     = $refUser->id;
            $temp['ulbId']      = $refUser->ulb_id ?? $temp['ulbId'];
            $temp["applycationType"] = $request->applycationType;
            return responseMsgs(true, "", $temp, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), $request->all(), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Differenciate the water module payment 
     */
    public function razorPayResponse($args)
    {
        try {
            # valication 
            $RazorPayRequest = WaterRazorPayRequest::select("*")
                ->where("order_id", $args["orderId"])
                ->where("related_id", $args["id"])
                ->where("status", 2)
                ->first();
            if (!$RazorPayRequest) {
                throw new Exception("Data Not Found");
            }
            switch ($RazorPayRequest->payment_from) {
                case ("New Connection"):
                    $response = $this->waterConnectionPayment($args);
                    break;
                case ("Regulaization"):                                                                   // Static
                    $response = $this->waterConnectionPayment($args);
                    break;
                case ("Site Inspection"):
                    $response = $this->waterConnectionPayment($args);
                    break;
                case ("Demand Collection"):
                    $mWaterPaymentController = new WaterPaymentController();
                    $response = $mWaterPaymentController->endOnlineDemandPaymentV1($args, $RazorPayRequest);
                    break;
                case ("Ferrule Cleaning Checking"):
                    $mWaterPaymentController = new WaterPaymentController();
                    $response = $mWaterPaymentController->endOnlineConReqPayment($args, $RazorPayRequest);
                    break;
                case ("Pipe Shifting Alteration"):
                    $mWaterPaymentController = new WaterPaymentController();
                    $response = $mWaterPaymentController->endOnlineConReqPayment($args, $RazorPayRequest);
                    break;
                default:
                    throw new Exception("Invalide Transaction");
            }
            return $response;
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $args);
        }
    }



    /**
     * | Test the icici payment details 
     */
    public function testIciciResposnse(Request $request)
    {
        $webhook = collect($request->all())->toArray();
        $this->iciciPayResposne($webhook);
        return ["test" => true];
    }


    /**
     * | distribute the icic payment details according to paymet maode 
        | Serial No :
        | Under con
     */
    public function iciciPayResposne($webhookData)
    {
        $mWaterIciciRequest = new WaterIciciRequest();
        try {
            $waterRequestData = $mWaterIciciRequest->getReqDataByRefNo($webhookData['reqRefNo'])->where('status', 2)->first();
            if (!$waterRequestData) {
                throw new Exception("Request Details Not Found");
            }
            switch ($waterRequestData->payment_from) {
                case ("Demand Collection"):
                    $mWaterPaymentController = new WaterPaymentController();
                    $paymentResponse = $mWaterPaymentController->endOnlineDemandPayment($webhookData, $waterRequestData);
                    break;
                default:
                    throw new Exception("Invalide Transaction");
            }
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $webhookData);
        }
    }

    public function waterConnectionPayment($args)
    {
        try {
            $refUserId      = $args["userId"];
            $refUlbId       = $args["ulbId"];
            $mNowDate       = Carbon::now()->format('Y-m-d');
            $mTimstamp      = Carbon::now()->format('Y-m-d H:i:s');
            $cahges         = null;
            $chargeData     = (array)null;
            $application    = null;
            $mDemands       = (array) null;
            $mPenalty       = (array) null;
            $refConnectionCharge = Config::get("waterConstaint.CHARGE_CATAGORY");

            #-----------valication------------------- 
            $RazorPayRequest = WaterRazorPayRequest::select("*")
                ->where("order_id", $args["orderId"])
                ->where("related_id", $args["id"])
                ->where("status", 2)
                ->first();
            // $RazorPayRequest = collect($RazorPayRequest)->filter();
            if (!$RazorPayRequest) {
                throw new Exception("Data Not Found");
            }
            if (in_array($RazorPayRequest->payment_from, ["New Connection", "Site Inspection", "Regulaization"])) {                     // Static
                $application = WaterApplication::find($args["id"]);
                $cahges = 0;
                $id = explode(",", $RazorPayRequest->demand_from_upto);
                $penalty_id = explode(",", $RazorPayRequest->penalty_id);
                if ($RazorPayRequest['payment_from'] == $refConnectionCharge['NEW_CONNECTION']) {
                    if ($id) {
                        $mDemands = WaterConnectionCharge::select("*")
                            ->whereIn("id", $id)
                            ->get();
                        $cahges = $cahges + ($mDemands->sum("amount") ?? 0);
                    }
                }
                if ($RazorPayRequest['payment_from'] == $refConnectionCharge['SITE_INSPECTON']) {
                    $mDemands = WaterConnectionCharge::select("*")
                        ->where("application_id", $args['id'])
                        ->where("charge_category", $refConnectionCharge['SITE_INSPECTON'])
                        ->get();
                    $cahges = $mDemands->sum("conn_fee") ?? 0;
                    if ($penalty_id) {
                        $mPenalty = WaterPenaltyInstallment::select("*")
                            ->whereIn("id", $penalty_id)
                            ->get();
                        $cahges = $cahges + ($mPenalty->sum("balance_amount"));
                    }
                }
                if ($RazorPayRequest['payment_from'] == $refConnectionCharge['REGULAIZATION']) {
                    $isPenalty = 1;
                    $filteredArray = array_filter($id, function ($value, $key) {
                        return ($key !== 0) || ($value !== '');
                    }, ARRAY_FILTER_USE_BOTH);
                    if ($filteredArray) {
                        $mDemands = WaterConnectionCharge::select("*")
                            ->whereIn("id", $id)
                            ->get();
                        $isPenalty = 0;
                    }
                    if ($penalty_id) {
                        $mPenalty = WaterPenaltyInstallment::select("*")
                            ->whereIn("id", $penalty_id)
                            ->get();
                    }
                    $cahges = $RazorPayRequest['amount'];
                }
                $chargeData["total_charge"] = $cahges;
            }
            if (!$application) {
                throw new Exception("Application Not Found!......");
            }
            $applicationId = $args["id"];
            #-----------End valication----------------------------

            #-------------Calculation----------------------------- 
            if (!$chargeData || round($args['amount']) != round($chargeData['total_charge'])) {
                throw new Exception("Payble Amount Missmatch!!!");
            }

            $transactionType = $RazorPayRequest->payment_from;

            $totalCharge = $chargeData['total_charge'];
            $refUserDetails = ActiveCitizen::where('id', $refUserId)
                ->first();
            #-------------End Calculation-----------------------------
            #-------- Transection -------------------
            $this->begin();

            $RazorPayResponse = new WaterRazorPayResponse;
            $RazorPayResponse->related_id   = $RazorPayRequest->related_id;
            $RazorPayResponse->request_id   = $RazorPayRequest->id;
            $RazorPayResponse->amount       = $args['amount'];
            $RazorPayResponse->merchant_id  = $args['merchantId'] ?? null;
            $RazorPayResponse->order_id     = $args["orderId"];
            $RazorPayResponse->payment_id   = $args["paymentId"];
            $RazorPayResponse->save();

            $RazorPayRequest->status = 1;
            $RazorPayRequest->update();

            $watertransaction = new WaterTran;
            $watertransaction->related_id       = $applicationId;
            $watertransaction->ward_id          = $application->ward_id;
            $watertransaction->tran_type        = $transactionType;
            $watertransaction->tran_date        = $mNowDate;
            $watertransaction->payment_mode     = "Online";
            $watertransaction->amount           = $totalCharge;
            $watertransaction->citizen_id       = $refUserId;
            $watertransaction->is_jsk           = false;
            $watertransaction->created_at       = $mTimstamp;
            $watertransaction->ip_address       = '';
            $watertransaction->ulb_id           = $refUlbId;
            $watertransaction->user_type        = $refUserDetails->user_type;
            $watertransaction->pg_response_id   = $RazorPayResponse->id ?? null;
            $watertransaction->pg_id            = $args['gatewayType'] ?? null;
            $watertransaction->penalty_ids      = $RazorPayRequest->penalty_id ?? null;
            $watertransaction->is_penalty       = $isPenalty ?? 0;
            $watertransaction->save();
            $transaction_id                     = $watertransaction->id;
            $watertransaction->tran_no          = $args["transactionNo"];
            $watertransaction->update();

            foreach ($mDemands as $val) {
                $TradeDtl = new WaterTranDetail;
                $TradeDtl->tran_id        = $transaction_id;
                $TradeDtl->demand_id      = $val->id;
                $TradeDtl->total_demand   = $val->amount;
                $TradeDtl->application_id   = $val->application_id;
                $TradeDtl->created_at     = $mTimstamp;
                $TradeDtl->save();

                $val->paid_status = 1;
                $val->update();
            }

            # Check and write code to save data in the track table
            if ($RazorPayRequest->payment_from == "New Connection") {
                $application->current_role = !$application->current_role ? $this->_dealingAssistent : $application->current_role;
                $application->update();
            }
            if ($RazorPayRequest->payment_from == $refConnectionCharge['REGULAIZATION'] && $application->payment_status == 0) {
                if ($RazorPayRequest->is_rebate = 1) {
                    $waterTrans['id'] = $transaction_id;
                    $req = new Request([
                        "applicationId" => $applicationId
                    ]);
                    $this->saveRebateForTran($req, $mDemands, $waterTrans);
                }
                $application->current_role = !$application->current_role ? $this->_dealingAssistent : $application->current_role;
                $application->update();
            }
            if ($RazorPayRequest->payment_from == "Site Inspection") {
                $mWaterSiteInspection = new WaterSiteInspection();
                $mWaterSiteInspection->saveSitePaymentStatus($applicationId);
            }

            foreach ($mPenalty as $val) {
                $val->paid_status = 1;
                $val->update();
            }
            $application->payment_status = 1;
            $application->update();

            $this->commit();
            #----------End transaction------------------------
            #----------Response------------------------------
            $res['transactionId'] = $transaction_id;
            $res['paymentRecipt'] = config('app.url') . "/api/water/paymentRecipt/" . $applicationId . "/" . $transaction_id;
            return responseMsg(true, "", $res);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), $args);
        }
    }

    public function readTransectionAndApl(Request $request)
    {
        try {
            $refUser        = authUser($request);
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $mDemands       = null;
            $application = null;
            $transection = null;
            $path = "/api/water/paymentRecipt/";
            $rules = [
                'orderId'    => 'required|string',
                'paymentId'  => 'required|string',
            ];
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $WaterRazorPayResponse = WaterRazorPayResponse::select("water_razor_pay_requests.*")
                ->join("water_razor_pay_requests", "water_razor_pay_requests.id", "water_razor_pay_responses.request_id")
                ->where("water_razor_pay_responses.order_id", $request->orderId)
                ->where("water_razor_pay_responses.payment_id", $request->paymentId)
                ->where("water_razor_pay_requests.status", 1)
                ->first();
            if (!$WaterRazorPayResponse) {
                throw new Exception("Not Transection Found...");
            }
            if ($WaterRazorPayResponse->payment_from == "New Connection") {
                $application = WaterApplication::find($WaterRazorPayResponse->related_id);
                $transection = WaterTran::select("*")
                    ->where("related_id", $WaterRazorPayResponse->related_id)
                    ->where("tran_type", $WaterRazorPayResponse->payment_from)
                    ->first();
            }
            if ($WaterRazorPayResponse->payment_from == "Site Inspection") {
                $application = WaterApplication::find($WaterRazorPayResponse->related_id);
                $transection = WaterTran::select("*")
                    ->where("related_id", $WaterRazorPayResponse->related_id)
                    ->where("tran_type", $WaterRazorPayResponse->payment_from)
                    ->first();
            }
            if ($WaterRazorPayResponse->payment_from == "Demand Collection") {
                $application = WaterConsumer::find($WaterRazorPayResponse->related_id);
                $transection = WaterTran::select("*")
                    ->where("related_id", $WaterRazorPayResponse->related_id)
                    ->where("tran_type", $WaterRazorPayResponse->payment_from)
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$application) {
                throw new Exception("Application Not Found....");
            }
            if (!$transection) {
                throw new Exception("Not Transection Data Found....");
            }
            $data["amount"]            = $WaterRazorPayResponse->amount;
            $data["applicationId"]     = $WaterRazorPayResponse->related_id;
            $data["applicationNo"]     = $application->application_no;
            $data["tranType"]          = $WaterRazorPayResponse->payment_from;
            $data["transectionId"]     = $transection->id;
            $data["transectionNo"]     = $transection->tran_no;
            $data["transectionDate"]   = $transection->tran_date;
            $data['paymentRecipt']     = config('app.url') . $path . $WaterRazorPayResponse->related_id . "/" . $transection->id;
            return responseMsg(true, "", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    /**
     * get And Uploade Water Requied Documents
       Query cost(3.00)
     * ------------------------------------------------------------------ 
     * | @var refUser            = authUser()
     * | @var refUserId          = refUser->id      | loging user Id
     * | @var refUlbId           = refUser->ulb_id  | loging user ulb Id
     * | @var refApplication     = WaterApplication(Model);
     * | @var refOwneres         = WaterApplicant(Model);
     * | @var requiedDocType     = $this->getDocumentTypeList()         |
     * | @var requiedDocs        = Application Related Required Documents
     * | @var ownersDoc          = Owners Related Required Documents
     * ----------------fucntion use---------------------------------------
     * | @var requiedDocType = $this->getDocumentTypeList(refApplication);
     * | @var refOwneres     = $this->getOwnereDtlByLId(refApplication->id);
     * | $this->getDocumentList($val->doc_for) |get All Related Document List
     * | $this->check_doc_exist(connectionId,$val->doc_for); |  Check Document is Uploaded Of That Type
     * | $this->readDocumentPath($doc['uploadDoc']["document_path"]); |  Create The Relative Path For Document Read
     * | $this->check_doc_exist_owner(refApplication->id,$val->id); # check Owners  Documents
     */
    public function documentUpload(Request $request)
    {
        try {
            $refUser            = authUser($request);
            $refUserId          = $refUser->id ?? $request->userId;
            $refUlbId           = $refUser->ulb_id ?? $request->ulbId;
            $refApplication     = (array)null;
            $refOwneres         = (array)null;
            $mUploadDocument    = (array)null;
            $mDocumentsList     = (array)null;
            $requiedDocs        = (array)null;
            $ownersDoc          = (array)null;
            $testOwnersDoc      = (array)null;
            $data               = (array)null;
            $sms                = "";
            $refWaterWorkflowId = Config::get('workflow-constants.WATER_WORKFLOW_ID');
            $refWaterModuleId   = Config::get('module-constants.WATER_MODULE_ID');

            $rules = [
                'applicationId'     => 'required|digits_between:1,9223372036854775807',
            ];
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $connectionId = $request->applicationId;
            $refApplication = WaterApplication::where("status", 1)->find($connectionId);
            if (!$refApplication) {
                throw new Exception("Application Not Found.....");
            }
            // elseif($refApplication->doc_verify_status)
            // {
            //     throw new Exception("Documernt Already Verifed.....");
            // }
            $requiedDocType = $this->getDocumentTypeList($refApplication, $refUser);  # get All Related Document Type List
            $refOwneres = $this->getOwnereDtlByLId($refApplication->id);    # get Owneres List
            foreach ($requiedDocType as $val) {
                $doc = (array) null;
                $doc['docName'] = $val->doc_for;
                $doc['isMadatory'] = $val->is_mandatory;
                $doc['docVal'] = $this->getDocumentList($val->doc_for);  # get All Related Document List
                $doc['uploadDoc'] = $this->check_doc_exist($connectionId, $val->doc_for); # Check Document is Uploaded Of That Type
                if (isset($doc['uploadDoc']["document_path"])) {
                    $path = $this->readDocumentPath($doc['uploadDoc']["document_path"]); # Create The Relative Path For Document Read
                    $doc['uploadDoc']["document_path"] = !empty(trim($doc['uploadDoc']["document_path"])) ? $path : null;
                }
                array_push($requiedDocs, $doc);
            }
            foreach ($refOwneres as $key => $val) {
                $doc = (array) null;
                $testOwnersDoc[$key] = (array) null;
                $doc["ownerId"] = $val->id;
                $doc["ownerName"] = $val->applicant_name;
                $doc["docName"]   = "ID Proof";
                $doc['isMadatory'] = 1;
                $doc['docVal'] = $this->getDocumentList("ID Proof");
                $refOwneres[$key]["ID Proof"] = $this->check_doc_exist_owner($refApplication->id, $val->id); # check Owners ID Proof Documents             
                $doc['uploadDoc'] = $refOwneres[$key]["ID Proof"];
                if (isset($refOwneres[$key]["ID Proof"]["document_path"])) {
                    $path = $this->readDocumentPath($refOwneres[$key]["ID Proof"]["document_path"]);
                    $refOwneres[$key]["ID Proof"]["document_path"] = !empty(trim($refOwneres[$key]["ID Proof"]["document_path"])) ? $path : null;
                    $doc['uploadDoc']["document_path"] = $path;
                }
                // array_push($ownersDoc, $doc);
                // array_push($testOwnersDoc[$key], $doc);
                # use of doc2
                $doc2 = (array) null;
                $doc2["ownerId"] = $val->id;
                $doc2["ownerName"] = $val->owner_name;
                $doc2["docName"]   = "image";
                $doc2['isMadatory'] = 0;
                $doc2['docVal'][] = ["id" => 0, "doc_name" => "Photo"];
                $refOwneres[$key]["image"] = $this->check_doc_exist_owner($refApplication->id, $val->id, 0);
                $doc2['uploadDoc'] = $refOwneres[$key]["image"];
                if (isset($refOwneres[$key]["image"]["document_path"])) {
                    $path = $this->readDocumentPath($refOwneres[$key]["image"]["document_path"]);
                    $refOwneres[$key]["image"]["document_path"] = !empty(trim($refOwneres[$key]["image"]["document_path"])) ? storage_path('app/public/' . $refOwneres[$key]["image"]["document_path"]) : null;
                    $refOwneres[$key]["image"]["document_path"] = !empty(trim($refOwneres[$key]["image"]["document_path"])) ? $path : null;
                    $doc2['uploadDoc']["document_path"] = $path;
                }
                array_push($ownersDoc, $doc);
                array_push($testOwnersDoc[$key], $doc);
            }

            #---------- upload the documents--------------
            // if (isset($request->docFor)) {
            //     #connection Doc
            //     if (in_array($request->docFor, objToArray($requiedDocType->pluck("doc_for")))) {
            //         $rules = [
            //             'docPath'        => 'required|max:30720|mimes:pdf,jpg,jpeg,png',
            //             'docMstrId'      => 'required|digits_between:1,9223372036854775807',
            //             'docFor'         => "required|string",
            //         ];
            //         $validator = Validator::make($request->all(), $rules,);
            //         if ($validator->fails()) {
            //             return responseMsg(false, $validator->errors(), $request->all());
            //         }
            //         $file = $request->file('docPath');
            //         $doc_for = "docFor";
            //         $doc_mstr_id = "docMstrId";
            //         $ids = objToArray(collect($this->getDocumentList($request->$doc_for))->pluck("id"));
            //         if ($file->IsValid() && in_array($request->$doc_mstr_id, $ids)) {
            //             if ($app_doc_dtl_id = $this->check_doc_exist($connectionId, $request->$doc_for)) {
            //                 if ($app_doc_dtl_id->verify_status == 0) {
            //                     $delete_path = storage_path('app/public/' . $app_doc_dtl_id['document_path']);
            //                     if (file_exists($delete_path)) {
            //                         unlink($delete_path);
            //                     }
            //                     $newFileName = $app_doc_dtl_id['id'];

            //                     $file_ext = $data["exten"] = $file->getClientOriginalExtension();
            //                     $fileName = "water_conn_doc/$newFileName.$file_ext";
            //                     $filePath = $this->uplodeFile($file, $fileName);
            //                     $app_doc_dtl_id->doc_name       =  $filePath;
            //                     $app_doc_dtl_id->document_id    =  $request->$doc_mstr_id;
            //                     $app_doc_dtl_id->update();
            //                 } else {
            //                     $app_doc_dtl_id->status = 0;
            //                     $app_doc_dtl_id->update();

            //                     $waterDoc = new WaterApplicantDoc;
            //                     $waterDoc->application_id = $connectionId;
            //                     $waterDoc->doc_for    = $request->$doc_for;
            //                     $waterDoc->document_id = $request->$doc_mstr_id;
            //                     $waterDoc->emp_details_id = $refUserId;

            //                     // $waterDoc = new WfActiveDocument();
            //                     // $waterDoc->active_id = $refApplication->application_no;
            //                     // $waterDoc->workflow_id = $refWaterWorkflowId;
            //                     // $waterDoc->ulb_id = $refUlbId;
            //                     // $waterDoc->module_id = $refWaterModuleId;
            //                     // $waterDoc->relative_path =
            //                     // $waterDoc->image =
            //                     // $waterDoc->uploaded_by =$refUserId

            //                     $waterDoc->save();
            //                     $newFileName = $waterDoc->id;

            //                     $file_ext = $data["exten"] = $file->getClientOriginalExtension();
            //                     $fileName = "water_conn_doc/$newFileName.$file_ext";
            //                     $filePath = $this->uplodeFile($file, $fileName);
            //                     $waterDoc->doc_name =  $filePath;
            //                     $waterDoc->update();
            //                 }
            //                 $sms = $app_doc_dtl_id->doc_for . " Update Successfully";
            //             } else {
            //                 $waterDoc = new WaterApplicantDoc;
            //                 $waterDoc->application_id = $connectionId;
            //                 $waterDoc->doc_for    = $request->$doc_for;
            //                 $waterDoc->document_id = $request->$doc_mstr_id;
            //                 $waterDoc->emp_details_id = $refUserId;

            //                 $waterDoc->save();
            //                 $newFileName = $waterDoc->id;

            //                 $file_ext = $data["exten"] = $file->getClientOriginalExtension();
            //                 $fileName = "water_conn_doc/$newFileName.$file_ext";
            //                 $filePath = $this->uplodeFile($file, $fileName);
            //                 $waterDoc->doc_name =  $filePath;
            //                 $waterDoc->update();
            //                 $sms = $waterDoc->doc_for . " Upload Successfully";
            //             }
            //         } else {
            //             return responseMsg(false, "something errors in Document Uploades", $request->all());
            //         }
            //     }
            //     #owners Doc
            //     elseif (in_array($request->docFor, objToArray(collect($ownersDoc)->pluck("docName")))) {
            //         $rules = [
            //             'docPath'        => 'required|max:30720|mimes:pdf,jpg,jpeg,png',
            //             'docMstrId'      => 'required|digits_between:1,9223372036854775807',
            //             'docFor'         => "required|string",
            //             'ownerId'        => "required|digits_between:1,9223372036854775807",
            //         ];
            //         $validator = Validator::make($request->all(), $rules,);
            //         if ($validator->fails()) {
            //             return responseMsg(false, $validator->errors(), $request->all());
            //         }
            //         $file = $request->file('docPath');
            //         $doc_for = "docFor";
            //         $doc_mstr_id = "docMstrId";
            //         if ($request->$doc_for == "image") {
            //             $ids = [0];
            //         } else {

            //             $ids = objToArray(collect($this->getDocumentList($request->$doc_for))->pluck("id"));
            //         }
            //         if (!in_array($request->ownerId, objToArray(collect($ownersDoc)->pluck("ownerId")))) {
            //             throw new Exception("Invalid Owner Id supply.....");
            //         }
            //         if ($file->IsValid() && in_array($request->$doc_mstr_id, $ids)) {
            //             if ($app_doc_dtl_id = $this->check_doc_exist_owner($connectionId, $request->ownerId, $request->docMstrId)) {
            //                 if ($app_doc_dtl_id->verify_status == 0) {
            //                     $delete_path = storage_path('app/public/' . $app_doc_dtl_id['document_path']);
            //                     if (file_exists($delete_path)) {
            //                         unlink($delete_path);
            //                     }
            //                     $newFileName = $app_doc_dtl_id['id'];

            //                     $file_ext = $data["exten"] = $file->getClientOriginalExtension();
            //                     $fileName = "water_conn_doc/$newFileName.$file_ext";
            //                     $filePath = $this->uplodeFile($file, $fileName);
            //                     $app_doc_dtl_id->doc_name       =  $filePath;
            //                     $app_doc_dtl_id->document_id    =  $request->$doc_mstr_id;
            //                     $app_doc_dtl_id->update();
            //                 } else {
            //                     $app_doc_dtl_id->status    =  0;
            //                     $app_doc_dtl_id->update();

            //                     $waterDoc                = new WaterApplicantDoc;
            //                     $waterDoc->application_id    = $connectionId;
            //                     $waterDoc->doc_for       = $request->docFor;
            //                     $waterDoc->document_id   = $request->docMstrId;
            //                     $waterDoc->applicant_id  = $request->ownerId;
            //                     $waterDoc->emp_details_id = $refUserId;

            //                     $waterDoc->save();
            //                     $newFileName = $waterDoc->id;

            //                     $file_ext = $data["exten"] = $file->getClientOriginalExtension();
            //                     $fileName = "water_conn_doc/$newFileName.$file_ext";
            //                     $filePath = $this->uplodeFile($file, $fileName);
            //                     $waterDoc->doc_name =  $filePath;
            //                     $waterDoc->update();
            //                 }
            //                 $sms = $app_doc_dtl_id->doc_for . " Update Successfully";
            //             } else {
            //                 $waterDoc                = new WaterApplicantDoc;
            //                 $waterDoc->application_id    = $connectionId;
            //                 $waterDoc->doc_for       = $request->docFor;
            //                 $waterDoc->document_id   = $request->docMstrId;
            //                 $waterDoc->applicant_id  = $request->ownerId;
            //                 $waterDoc->emp_details_id = $refUserId;

            //                 $waterDoc->save();
            //                 $newFileName = $waterDoc->id;

            //                 $file_ext = $data["exten"] = $file->getClientOriginalExtension();
            //                 $fileName = "water_conn_doc/$newFileName.$file_ext";
            //                 $filePath = $this->uplodeFile($file, $fileName);
            //                 $waterDoc->doc_name =  $filePath;
            //                 $waterDoc->update();
            //                 $sms = $waterDoc->doc_for . " Upload Successfully";
            //             }
            //         } else {
            //             return responseMsg(false, "something errors in Document Uploades", $request->all());
            //         }
            //     } else {
            //         throw new Exception("Invalid Document type Passe");
            //     }
            //     return responseMsg(true, $sms, "");
            // }
            $data["documentsList"]  = $requiedDocs;
            $data["ownersDocList"]  = collect($testOwnersDoc)->first();
            return responseMsg(true, $sms, $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    /**
     * Get Uploade Document Of Water Application
        Query Cost(2.30)
     * | --------------------------------------------------
     * | @var applicationId     = request->applicationId
     * | @var refApplication    = WaterApplication(Model);
     * | @var mUploadDocument   = $this->getWaterDocuments(applicationId)
     * ----------------------function use----------------------------------------
     * | @var mUploadDocument   = $this->getWaterDocuments(applicationId)
     * | $this->readDocumentPath( $val["document_path"])
     */
    public function getUploadDocuments(Request $request)
    {
        try {
            $rules = [
                'applicationId'     => 'required|digits_between:1,9223372036854775807',
            ];
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $applicationId = $request->applicationId;
            if (!$applicationId) {
                throw new Exception("Applicatin Id Required");
            }
            $refApplication = WaterApplication::where("status", 1)->find($applicationId);;
            if (!$refApplication) {
                throw new Exception("Data Not Found");
            }
            $mUploadDocument = $this->getWaterDocuments($applicationId)->map(function ($val) {
                if (isset($val["document_path"])) {
                    $path = $this->readDocumentPath($val["document_path"]);
                    $val["document_path"] = !empty(trim($val["document_path"])) ? $path : null;
                }
                return $val;
            });
            $data["uploadDocument"] = $mUploadDocument;
            return responseMsg(true, "", $data);
        } catch (Exception $e) {

            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }


    public function calWaterConCharge(Request $request)
    {
        try {
            if (($request->applyDate && $request->applyDate < "2021-04-01") && $request->pipelineTypeId == "1") {
                $res = $this->conRuleSet1($request);
            } else {
                $res = $this->conRuleSet2($request);
            }
            return collect($res);
        } catch (Exception $e) {
            $response["status"] = false;
            $response["errors"] = $e->getMessage();
            return collect($response);
        }
    }
    public function conRuleSet2(Request $request)
    {
        $response = (array)null;
        $response["status"] = false;
        $response["ruleSete"] = "RuleSet2";
        try {
            $response["water_fee_mstr_id"] = 0;
            $response["water_fee_mstr"] = [];
            $response["installment_amount"] = [];
            $conneFee  = 0;
            $mPenalty  = 0;
            $mNowDate  = Carbon::now()->format("Y-m-d");
            $mEffectiveFrom  = Carbon::parse("2021-01-01")->format('Y-m-d');
            $mSixMonthsAfter = Carbon::parse("2021-01-01")->addMonth(6)->format('Y-m-d');
            if ($request->category != "BPL") {
                $waterConFee = WaterParamConnFee::select("*")
                    ->where("property_type_id", $request->propertyTypeId)
                    ->where("effective_date", "<=", $mNowDate);
                if (in_array($request->propertyTypeId, [1, 7, 8])) {
                    $waterConFee = $waterConFee->where(function ($where) use ($request) {
                        $where->where("area_from_sqft", "<=", ceil($request->areaSqft))
                            ->where("area_upto_sqft", ">=", ceil($request->areaSqft));
                    });
                }

                $waterConFee = $waterConFee->first();
                $response["water_fee_mstr"] = collect($waterConFee);
                $response["water_fee_mstr_id"]   =   $waterConFee->id;
                if ($waterConFee->calculation_type == 'Fixed') {
                    $conneFee   = $waterConFee->conn_fee;
                } else {
                    $conneFee   = $waterConFee->conn_fee * $request->areaSqft;
                }
            }

            $conn_fee_charge = array();
            $conn_fee_charge['charge_for'] = 'New Connection';
            $conn_fee_charge['conn_fee']   = (float)$conneFee;

            // Regularization
            # penalty 4000 for residential 10000 for commercial in regularization effective from 
            # 01-01-2021 and half the amount is applied for connection who applied under 6 months from 01-01-2021 
            if ($request->connectionTypeId == 2) {
                $mPenalty = 10000;
                if ($request->propertyTypeId == 1) {
                    $mPenalty = 4000;
                }
                if ($mNowDate < $mSixMonthsAfter) {
                    $mPenalty = $mPenalty / 2;
                }

                $inltment40Per = ($mPenalty * 40) / 100;
                $inltment30Per = ($mPenalty * 30) / 100;
                for ($j = 1; $j <= 3; $j++) {
                    if ($j == 1) {
                        $installment_amount = $inltment40Per;
                    } else {
                        $installment_amount = $inltment30Per;
                    }
                    $penalty_installment = array();
                    $penalty_installment['penalty_head'] = "$j" . " Installment";
                    $penalty_installment['installment_amount'] = $installment_amount;
                    $penalty_installment['balance_amount'] = $installment_amount;
                    array_push($response["installment_amount"], $penalty_installment);
                }
            }
            $conn_fee_charge['penalty'] = $mPenalty;
            $conn_fee_charge['amount']  = $mPenalty + $conneFee;
            $response["conn_fee_charge"] =  $conn_fee_charge;
            $response["status"] = true;
            return collect($response);
        } catch (Exception $e) {
            $response["errors"] = $e->getMessage();
            return collect($response);
        }
    }
    public function conRuleSet1(Request $request)
    {
        $response = (array)null;
        $response["status"] = false;
        $response["ruleSete"] = "RuleSet1";
        try {
            $response["water_fee_mstr_id"] = 0;
            $response["water_fee_mstr"] = [];
            $response["installment_amount"] = [];
            $conneFee  = 0;
            $mPenalty  = 0;
            $connection_through = (isset($request->connection_through) && $request->connection_through == 3 ? 2 : $request->connection_through) ?? 1;
            $mNowDate  = $request->applyDate ? Carbon::parse($request->applyDate)->format('Y-m-d') : Carbon::now()->format("Y-m-d");
            DB::enableQueryLog();
            $waterConFee = WaterParamConnFeeOld::select("*", DB::raw("'Fixed' AS calculation_type"))
                ->where("property_type_id", $request->propertyTypeId)
                ->where("pipeline_type_id", $request->pipelineTypeId ?? 2)
                ->where("connection_type_id", $request->connectionTypeId ?? 2)
                ->where("connection_through_id", $connection_through ?? 1)
                ->where("category", $request->category ?? "APL")
                ->where("effect_date", "<=", $mNowDate)
                ->where("status", 1)
                ->orderBy("effect_date", "DESC")
                ->orderBy("id", "ASC")
                ->first();
            // dd(DB::getyQueryLog());

            $response["water_fee_mstr_id"] = $waterConFee->id;
            $response["water_fee_mstr"] = $waterConFee;
            $conneFee   = $waterConFee->reg_fee + $waterConFee->proc_fee + $waterConFee->app_fee + $waterConFee->sec_fee + $waterConFee->conn_fee;

            $conn_fee_charge = array();
            $conn_fee_charge['charge_for'] = 'New Connection';
            $conn_fee_charge['conn_fee']   = (float)$conneFee;

            // Regularization
            # penalty 4000 for residential 10000 for commercial in regularization effective from 
            # 01-01-2021 and half the amount is applied for connection who applied under 6 months from 01-01-2021 

            $conn_fee_charge['penalty'] = $mPenalty;
            $conn_fee_charge['amount']  = $mPenalty + $conneFee;
            $response["conn_fee_charge"] =  $conn_fee_charge;
            $response["status"] = true;
            return collect($response);
        } catch (Exception $e) {
            $response["errors"] = $e->getMessage();
            return collect($response);
        }
    }

    #---------- core function --------------------------------------------------

    public function getWaterConnectionChages($applicationId, $ids = "")
    {
        try {
            $conn_fee_charge = WaterConnectionCharge::select(DB::raw("SUM(COALESCE(amount,0)) AS amount,
                                                            SUM(COALESCE(conn_fee,0))AS conn_fee,
                                                            STRING_AGG(id::TEXT,',') AS ids
                                                            "))
                ->where("application_id", $applicationId)
                ->Where(function ($where) {
                    $where->orWhere("paid_status", 0)
                        ->orWhereNull("paid_status");
                })
                ->Where(function ($where) {
                    $where->orWhere("status", TRUE)
                        ->orWhereNull("status");
                })
                ->groupBy("application_id")
                ->first();
            $charge_for = WaterConnectionCharge::select("charge_category")
                ->where("application_id", $applicationId)
                ->Where(function ($where) {
                    $where->orWhere("paid_status", 0)
                        ->orWhereNull("paid_status");
                })
                ->Where(function ($where) {
                    $where->orWhere("status", TRUE)
                        ->orWhereNull("status");
                })
                ->orderBy("id")
                ->first();

            $penalty_installment = WaterPenaltyInstallment::select(DB::raw("SUM(COALESCE(balance_amount,0)) AS balance_amount,
                                                                SUM(COALESCE(installment_amount,0))AS installment_amount,
                                                                STRING_AGG(id::TEXT,',') AS ids"))
                ->where("apply_connection_id", $applicationId)
                ->Where(function ($where) {
                    $where->orWhere("paid_status", 0)
                        ->orWhereNull("paid_status");
                })
                ->Where(function ($where) {
                    $where->orWhere("status", 1)
                        ->orWhereNull("status");
                });
            if ($ids) {
                $penalty_installment = $penalty_installment->whereIn("id", explode(",", $ids));
            }
            $penalty_installment = $penalty_installment->groupBy("apply_connection_id")
                ->first();

            $paid_penalty = WaterPenaltyInstallment::Where("apply_connection_id", $applicationId)
                ->Where("paid_status", 1)
                ->Where(function ($where) {
                    $where->orWhere("status", TRUE)
                        ->orWhereNull("status");
                })
                ->count("id");
            $cahges = collect([
                "charge_for"            => $charge_for->charge_category ?? "New Connection",
                "amount"                => $conn_fee_charge->amount ?? 0,
                "conn_fee"              => $conn_fee_charge->conn_fee ?? 0,
                "ids"                   => $conn_fee_charge->ids ?? "",
                "balance_amount"        => $penalty_installment->balance_amount ?? 0,
                "installment_amount"    => $penalty_installment->installment_amount ?? 0,
                "installment_ids"       => $penalty_installment->ids ?? "",
                "is_rebate"             => $paid_penalty > 0 ? false : true,
                "rabate"                => $paid_penalty > 0 ? 0.0 : round((($penalty_installment->balance_amount ?? 0) / 10), 2),
            ]);
            return $cahges;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getDocumentTypeList($application, $user)
    {
        $refUserType = Config::get('waterConstaint.REF_USER_TYPE');
        $type = ["FORM_SCAN_COPY", "STAMP", "ID_PROOF", "PROPERTY TAX"];
        // Check if user_type is not equal to 1
        if ($user->user_type == $refUserType['1']) {
            // Modify $type array for user_type not equal to 1
            $type = ["STAMP", "ID_PROOF", "PROPERTY TAX", "METER", "PLUMBER LICENSE", "AMC FTTER REPORT", "PIPELINE MAP "];
        }

        $doc = WaterParamDocumentType::select(
            "doc_for",
            DB::raw("CASE WHEN doc_for ='OTHER' THEN 0 
            ELSE 1 END AS is_mandatory")
        )
            ->whereIn("doc_for", $type)
            ->where("status", 1)
            ->groupBy("doc_for")
            ->get();
        return $doc;
    }

    // Not used 
    public function getDocumentList($doc_for)
    {
        try {
            $data = WaterParamDocumentType::select("id", "document_name as doc_name")
                ->where("status", 1)
                ->where("doc_for", $doc_for)
                ->get();
            return $data;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    // Not used
    public function check_doc_exist($applicationId, $doc_for, $doc_mstr_id = null, $woner_id = null)
    {
        try {

            $doc = WaterApplicantDoc::select(
                "id",
                "doc_for",
                "verify_status",
                "water_applicant_docs.remarks",
                DB::raw("doc_name AS document_path"),
                "document_id"
            )
                ->where('application_id', $applicationId)
                ->where('doc_for', $doc_for);
            if ($doc_mstr_id) {
                $doc = $doc->where('document_id', $doc_mstr_id);
            }
            if ($woner_id) {
                $doc = $doc->where('applicant_id', $woner_id);
            }
            $doc = $doc->where('status', 1)
                ->orderBy('id', 'DESC')
                ->first();
            return $doc;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    // Not used
    public function check_doc_exist_owner($applicationId, $owner_id, $document_id = null)
    {
        try {
            // DB::enableQueryLog();
            $doc = WaterApplicantDoc::select(
                "id",
                "doc_for",
                "verify_status",
                "water_applicant_docs.remarks",
                DB::raw("doc_name AS document_path"),
                "document_id"
            )
                ->where('application_id', $applicationId)
                ->where('applicant_id', $owner_id);
            if ($document_id !== null) {
                $document_id = (int)$document_id;
                $doc = $doc->where('document_id', $document_id);
            } else {
                $doc = $doc->where("document_id", "<>", 0);
            }
            $doc = $doc->where('status', 1)
                ->orderBy('id', 'DESC')
                ->first();
            //    print_var(DB::getQueryLog());                    
            return $doc;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    public function getOwnereDtlByLId($applicationId)
    {
        try {
            $ownerDtl   = WaterApplicant::select("*")
                ->where("application_id", $applicationId)
                ->where("status", 1)
                ->get();
            return $ownerDtl;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    // Not used
    public function uplodeFile($file, $custumFileName)
    {
        $filePath = $file->storeAs('uploads/Water', $custumFileName, 'public');
        return  $filePath;
    }
    public function readDocumentPath($path)
    {
        $path = (config('app.url') . "/" . $path);
        return $path;
    }
    // Not used
    public function getWaterDocuments($id)
    {
        try {
            $doc =  WaterApplicantDoc::select(
                "water_applicant_docs.id",
                "water_applicant_docs.remarks",
                "water_applicant_docs.verify_status",
                // "trade_licence_documents.doc_for",
                DB::raw("
                            CASE WHEN water_applicants.id NOTNULL THEN CONCAT(water_applicants.applicant_name,'( ',water_applicant_docs.doc_for,' )') 
                            ELSE water_applicant_docs.doc_for 
                            END doc_for,
                            water_applicant_docs.doc_name AS document_path
                            ")
            )
                ->leftjoin("water_applicants", function ($join) {
                    $join->on("water_applicants.id", "water_applicant_docs.applicant_id");
                })
                ->where('water_applicant_docs.application_id', $id)
                ->where('water_applicant_docs.status', 1)
                ->orderBy('water_applicant_docs.id', 'desc')
                ->get();
            return $doc;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    #-----------------incomplite Code------------------------------#

}
