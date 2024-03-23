<?php

/**
 * | Created On-22-12-2022 
 * | Created By-Sandeep Bara
 * --------------------------------------------------------------------------------------
 * | Controller regarding with Trade Module From Counter Side 
 */

namespace App\Repository\Trade;

use App\EloquentModels\Common\ModelWard;
use App\Http\Requests\Trade\CitizenApplication;
use App\Models\Trade\ActiveLicence;
use App\Models\Trade\ExpireLicence;
use App\Models\Trade\TradeFineRebetDetail;
use App\Models\Trade\TradeParamItemType;
use App\Models\Trade\TradeRazorPayRequest;
use App\Models\Trade\TradeRazorPayResponse;
use App\Models\Trade\TradeTransaction;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Repository\Common\CommonFunction;
use App\Traits\Auth;
use App\Traits\Payment\Razorpay;
use App\Traits\Property\WardPermission;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\AkolaTradeParamItemType;
use App\Models\Trade\RejectedTradeLicence;
use App\Models\Trade\TradeFineRebete;
use App\Models\Trade\TradeLicence;
use App\Models\Trade\TradePinelabPayRequest;
use App\Models\Trade\TradeRenewal;
use App\Models\Workflows\WfActiveDocument;
use App\Models\WorkflowTrack;
use App\Repository\Notice\Notice;
use Illuminate\Support\Facades\Validator;

class TradeCitizen implements ITradeCitizen
{
    use Auth;               // Trait Used added by sandeep bara date 17-09-2022
    use WardPermission;
    use Razorpay;

    protected $_DB;
    protected $_DB_MASTER;
    protected $_DB_NAME;
    protected $_NOTICE_DB;
    protected $_NOTICE_DB_NAME;
    protected $_NOTICE;
    protected $_MODEL_WARD;
    protected $_COMMON_FUNCTION;
    protected $_REPOSITORY_TRADE;
    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;
    protected $_MODEL_WfActiveDocument;
    protected $_META_DATA;
    protected $_QUERY_RUN_TIME;
    protected $_API_ID;


    public function __construct()
    {
        $this->_DB_NAME = "pgsql_trade";
        $this->_NOTICE_DB = "pgsql_notice";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_DB_MASTER = DB::connection("pgsql_master");
        $this->_NOTICE_DB = DB::connection($this->_NOTICE_DB);
        DB::enableQueryLog();
        // $this->_DB->enableQueryLog();
        // $this->_NOTICE_DB->enableQueryLog();

        $this->_NOTICE = new Notice();
        $this->_MODEL_WARD = new ModelWard();
        $this->_COMMON_FUNCTION = new CommonFunction();
        $this->_REPOSITORY_TRADE = new Trade();
        $this->_MODEL_WfActiveDocument = new WfActiveDocument();
        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];

        $this->_META_DATA = [
            "apiId" => $this->_API_ID,
            "version" => 1.1,
            'queryRunTime' => $this->_QUERY_RUN_TIME,
        ];
    }

    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_NOTICE_DB->getDatabaseName();
        $db4 = $this->_DB_MASTER->getDatabaseName();
        DB::beginTransaction();
        if ($db1 != $db2)
            $this->_DB->beginTransaction();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_NOTICE_DB->beginTransaction();
        if ($db1 != $db4 && $db2 != $db4 && $db3 != $db4)
            $this->_DB_MASTER->beginTransaction();
    }

    public function rollback()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_NOTICE_DB->getDatabaseName();
        $db4 = $this->_DB_MASTER->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_NOTICE_DB->rollBack();
        if ($db1 != $db4 && $db2 != $db4 && $db3 != $db4)
            $this->_DB_MASTER->rollBack();
    }

    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_NOTICE_DB->getDatabaseName();
        $db4 = $this->_DB_MASTER->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_NOTICE_DB->commit();
        if ($db1 != $db4 && $db2 != $db4 && $db3 != $db4)
            $this->_DB_MASTER->commit();
    }

    public function addRecord(Request $request)
    {
        $this->_META_DATA["apiId"] = "c2";
        $this->_META_DATA["queryRunTime"] = 2.48;
        $this->_META_DATA["action"]    = $request->getMethod();
        $this->_META_DATA["deviceId"] = $request->ip();
        try {
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $request->ulbId;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mApplicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType];
            if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("active_citizens")) {
                throw new Exception("You Are Not Authorized For This Action. Please Apply From Counter");
            }
            if ($mApplicationTypeId != 1) {
                $mOldLicenceId = $request->licenseId;
                $nextMonth = Carbon::now()->addMonths(1)->format('Y-m-d');
                $refOldLicece = TradeLicence::find($mOldLicenceId);
                if (!$refOldLicece) {
                    throw new Exception("Old Licence Not Found");
                }
                if ($refOldLicece->valid_upto > $nextMonth && !in_array($mApplicationTypeId, [3, 4])) {
                    throw new Exception("Licence Valice Upto " . $refOldLicece->valid_upto);
                }
                if ($refOldLicece->valid_upto < (Carbon::now()->format('Y-m-d')) && in_array($mApplicationTypeId, [3, 4])) {
                    throw new Exception("Licence Was Expired Please Renewal First");
                }
                if ($refOldLicece->pending_status != 5) {
                    throw new Exception("Application Aready Apply Please Track  " . $refOldLicece->application_no);
                }
                if (in_array($mApplicationTypeId, [3, 4]) && $refOldLicece->valid_upto < Carbon::now()->format('Y-m-d')) {
                    throw new Exception("Application was Expired.You Can't Apply " . $request->applicationType . ". Please Renew First.");
                }
                if ($refUlbId != $refOldLicece->ulb_id) {
                    throw new Exception("Application ulb Deffrence " . $refOldLicece->application_no);
                }
            }
            $this->begin();
            $response = $this->_REPOSITORY_TRADE->addRecord($request);
            if (!$response->original["status"]) {
                throw new Exception($response->original["message"]);
            }
            $this->commit();
            return responseMsgs(
                true,
                $response->original["message"],
                $response->original["data"],
                $this->_META_DATA["apiId"],
                $this->_META_DATA["version"],
                $this->_META_DATA["queryRunTime"],
                $this->_META_DATA["action"],
                $this->_META_DATA["deviceId"]
            );
        } catch (Exception $e) {
            return responseMsgs(
                false,
                $e->getMessage(),
                "",
                $this->_META_DATA["apiId"],
                $this->_META_DATA["version"],
                $this->_META_DATA["queryRunTime"],
                $this->_META_DATA["action"],
                $this->_META_DATA["deviceId"]
            );
        }
    }
    public function razorPayResponse($args)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id ?? $args["userId"];
            $refUlbId       = $refUser->ulb_id ?? $args["ulbId"];
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $refWorkflows   = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $refNoticeDetails = null;
            $refDenialId    = null;
            $refUlbDtl      = UlbMaster::find($refUlbId);
            $refUlbName     = explode(' ', $refUlbDtl->ulb_name);
            $mNowDate       = Carbon::now()->format('Y-m-d');
            $mTimstamp      = Carbon::now()->format('Y-m-d H:i:s');
            $mDenialAmount  = 0;
            $mPaymentStatus = 1;
            $mNoticeDate    = null;
            $mShortUlbName  = $refUlbDtl->short_name??"";
            $mWardNo        = "";
            if(!$mShortUlbName)
            {
                foreach ($refUlbName as $val) {
                    $mShortUlbName .= $val[0];
                }

            }

            #-----------valication-------------------   
            $RazorPayRequest = TradeRazorPayRequest::select("*")
                ->where("order_id", $args["orderId"])
                ->where("temp_id", $args["id"])
                ->where("status", 2)
                ->first();
            if (!$RazorPayRequest) {
                throw new Exception("Data Not Found");
            }
            $refLecenceData = TradeLicence::find($args["id"]);
            $licenceId = $args["id"];
            $refLevelData = $this->_REPOSITORY_TRADE->getWorkflowTrack($licenceId); //TradeLevelPending::getLevelData($licenceId);
            if (!$refLecenceData) {
                throw new Exception("Licence Data Not Found !!!!!");
            } elseif ($refLecenceData->application_type_id == 4) {
                throw new Exception("Surender Application Not Pay Anny Amount");
            } elseif (in_array($refLecenceData->payment_status, [1, 2])) {
                throw new Exception("Payment Already Done Of This Application");
            }
            if ($refNoticeDetails = $this->_NOTICE->getNoticDtlById($refLecenceData->denial_id)) {
                $refDenialId = $refNoticeDetails->id;
                $mNoticeDate = date("Y-m-d", strtotime($refNoticeDetails['notice_date'])); //notice date 
            }

            $ward_no = UlbWardMaster::select("ward_name")
                ->where("id", $refLecenceData->ward_id)
                ->first();
            $mWardNo = $ward_no['ward_name'];

            #-----------End valication-------------------

            #-------------Calculation-----------------------------  
            $args['curdate']             = $refLecenceData->application_date;
            $args['areaSqft']            = (float)$refLecenceData->area_in_sqft;
            $args['application_type_id'] = $refLecenceData->application_type_id;
            $args['firmEstdDate'] = !empty(trim($refLecenceData->valid_from)) ? $refLecenceData->valid_from : $refLecenceData->apply_date;
            if ($refLecenceData->application_type_id == 1) {
                $args['firmEstdDate'] = $refLecenceData->establishment_date;
            }
            $args['tobacco_status']      = $refLecenceData->tobacco_status;
            $args['licenseFor']          = $refLecenceData->licence_for_years;
            $args['nature_of_business']  = $refLecenceData->nature_of_bussiness;
            $args['noticeDate']          = $mNoticeDate;
            // $chargeData = $this->_REPOSITORY_TRADE->cltCharge($args);
            $chargeData = $this->_REPOSITORY_TRADE->AkolaCltCharge($args);
            if ($chargeData['response'] == false || round($args['amount']) != round($chargeData['total_charge'])) {
                throw new Exception("Payble Amount Missmatch!!!");
            }

            $transactionType = $this->_TRADE_CONSTAINT["APPLICATION-TYPE-BY-ID"][$refLecenceData->application_type_id];

            $totalCharge = $chargeData['total_charge'];
            $mDenialAmount = $chargeData['notice_amount'];
            #-------------End Calculation-----------------------------
            #-------- Transection -------------------
            $this->begin();

            $RazorPayResponse = new TradeRazorPayResponse();
            $RazorPayResponse->temp_id   = $RazorPayRequest->temp_id;
            $RazorPayResponse->request_id   = $RazorPayRequest->id;
            $RazorPayResponse->amount       = $args['amount'];
            $RazorPayResponse->merchant_id  = $args['merchantId'] ?? null;
            $RazorPayResponse->order_id     = $args["orderId"];
            $RazorPayResponse->payment_id   = $args["paymentId"];
            $RazorPayResponse->save();

            $RazorPayRequest->status = 1;
            $RazorPayRequest->update();

            $Tradetransaction = new TradeTransaction();
            $Tradetransaction->temp_id          = $licenceId;
            $Tradetransaction->response_id      = $RazorPayResponse->id;
            $Tradetransaction->payment_gateway_type = "razor_pay";
            $Tradetransaction->ward_id          = $refLecenceData->ward_id;
            $Tradetransaction->tran_type        = $transactionType;
            $Tradetransaction->tran_date        = $mNowDate;
            $Tradetransaction->payment_mode     = "Online";
            $Tradetransaction->paid_amount      = $totalCharge;
            $Tradetransaction->penalty          = $chargeData['penalty'] + $mDenialAmount + $chargeData['arear_amount'];
            $Tradetransaction->emp_dtl_id       = $refUserId;
            $Tradetransaction->created_at       = $mTimstamp;
            $Tradetransaction->ip_address       = '';
            $Tradetransaction->ulb_id           = $refUlbId;
            $Tradetransaction->save();
            $transaction_id                     = $Tradetransaction->id;
            $Tradetransaction->tran_no   = $this->_REPOSITORY_TRADE->createTransactionNo($transaction_id,$mShortUlbName);#$args["transactionNo"]; ;//"TRANML" . date('d') . $transaction_id . date('Y') . date('m') . date('s');
            $Tradetransaction->update();

            $TradeFineRebet = new TradeFineRebete();
            $TradeFineRebet->tran_id = $transaction_id;
            $TradeFineRebet->type      = 'Delay Apply License';
            $TradeFineRebet->amount         = $chargeData['penalty'];
            $TradeFineRebet->created_at     = $mTimstamp;
            $i = $TradeFineRebet->save();

            $mDenialAmount = $mDenialAmount + $chargeData['arear_amount'];
            if ($mDenialAmount > 0) {
                $TradeFineRebet2 = new TradeFineRebete;
                $TradeFineRebet2->tran_id = $transaction_id;
                $TradeFineRebet2->type      = 'Denial Apply';
                $TradeFineRebet2->amount         = $mDenialAmount;
                $TradeFineRebet2->created_at     = $mTimstamp;
                $TradeFineRebet2->save();
            }
            $request = new Request(["applicationId" => $licenceId, "ulb_id" => $refUlbId, "user_id" => $refUserId]);
            // if ($mPaymentStatus == 1 && $this->_REPOSITORY_TRADE->checkWorckFlowForwardBackord($request) && $refLecenceData->pending_status == 0 ) {
            //     $refLecenceData->current_role = $refWorkflows['initiator']['forward_role_id'];
            //     $refLecenceData->document_upload_status = 1;
            //     $refLecenceData->pending_status  = 1;
            //     $metaReqs['applicationId'] = $licenceId;
            //     $metaReqs['senderRoleId'] = $refWorkflows['initiator']['id'];
            //     $metaReqs['receiverRoleId'] = $refWorkflows['initiator']['forward_role_id'];
            //     $metaReqs['comment'] = "";
            //     $metaReqs['moduleId'] = $this->_MODULE_ID;
            //     $metaReqs['workflowId'] = $refLecenceData->workflow_id;
            //     $metaReqs['refTableDotId'] = 'active_trade_licences';
            //     $metaReqs['refTableIdValue'] = $licenceId;
            //     $metaReqs['user_id'] = $refUserId;
            //     $metaReqs['ulb_id'] = $refUlbId;
            //     $myrequest = new request($metaReqs);

            //     $track = new WorkflowTrack();
            //     $tem = $track->saveTrack($myrequest);
            // }

            $provNo = $this->_REPOSITORY_TRADE->createProvisinalNo($mShortUlbName, $mWardNo, $licenceId);
            $refLecenceData->provisional_license_no = $provNo;
            $refLecenceData->payment_status         = $mPaymentStatus;
            if ($refNoticeDetails) {
                $this->_NOTICE->noticeClose($refLecenceData->denial_id);
                // $this->_REPOSITORY_TRADE->updateStatusFine($refDenialId, $chargeData['notice_amount'], $licenceId, 1); //update status and fineAmount                     
            }
            ($refLecenceData->id);
            $refLecenceData->update();
            $this->commit();
            #----------End transaction------------------------
            #----------Response------------------------------
            $res['transactionId'] = $transaction_id; #config('app.url') .
            $res['paymentReceipt'] =  "/api/trade/payment-receipt/" . $licenceId . "/" . $transaction_id;
            return responseMsg(true, "", $res);
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), $args);
        }
    }

    public function pinelabResponse($args)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id ?? $args["userId"];
            $refUlbId       = $refUser->ulb_id ?? $args["ulbId"];
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $refWorkflows   = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $refNoticeDetails = null;
            $refDenialId    = null;
            $refUlbDtl      = UlbMaster::find($refUlbId);
            $refUlbName     = explode(' ', $refUlbDtl->ulb_name);
            $mNowDate       = Carbon::now()->format('Y-m-d');
            $mTimstamp      = Carbon::now()->format('Y-m-d H:i:s');
            $mDenialAmount  = 0;
            $mPaymentStatus = 1;
            $mNoticeDate    = null;
            $mShortUlbName  = $refUlbDtl->short_name??"";
            $mWardNo        = "";
            if(!$mShortUlbName)
            {
                foreach ($refUlbName as $val) {
                    $mShortUlbName .= $val[0];
                }

            }

            #-----------valication-------------------   
            $PinelabPay = TradePinelabPayRequest::select("*")
                ->where("order_id", $args["BillingRefNo"])
                ->where("temp_id", $args["id"])
                ->where("status", 2)
                ->orderBy("id", "DESC")
                ->first();
            if (!$PinelabPay) {
                throw new Exception("Data Not Found");
            }
            $refLecenceData = TradeLicence::find($args["id"]);
            $licenceId = $args["id"];
            if (!$refLecenceData) {
                throw new Exception("Licence Data Not Found !!!!!");
            } elseif ($refLecenceData->application_type_id == 4) {
                throw new Exception("Surender Application Not Pay Anny Amount");
            } elseif (in_array($refLecenceData->payment_status, [1, 2])) {
                throw new Exception("Payment Already Done Of This Application");
            }
            if ($refNoticeDetails = $this->_NOTICE->getNoticDtlById($refLecenceData->denial_id)) {
                $refDenialId = $refNoticeDetails->id;
                $mNoticeDate = date("Y-m-d", strtotime($refNoticeDetails['notice_date'])); //notice date 
            }

            $ward_no = UlbWardMaster::select("ward_name")
                ->where("id", $refLecenceData->ward_id)
                ->first();
            $mWardNo = $ward_no['ward_name'];

            #-----------End valication-------------------

            #-------------Calculation-----------------------------  
            $args['areaSqft']            = (float)$refLecenceData->area_in_sqft;
            $args['application_type_id'] = $refLecenceData->application_type_id;
            $args['firmEstdDate'] = !empty(trim($refLecenceData->valid_from)) ? $refLecenceData->valid_from : $refLecenceData->apply_date;
            if ($refLecenceData->application_type_id == 1) {
                $args['firmEstdDate'] = $refLecenceData->establishment_date;
            }
            $args['tobacco_status']      = $refLecenceData->tobacco_status;
            $args['application_no']      = $refLecenceData->application_no;
            $args['licenseFor']          = $refLecenceData->licence_for_years;
            $args['nature_of_business']  = $refLecenceData->nature_of_bussiness;
            $args['noticeDate']          = $mNoticeDate;
            $args['curdate']             = $refLecenceData->application_date;
            
            // $chargeData = $this->_REPOSITORY_TRADE->cltCharge($args);
            $chargeData = $this->_REPOSITORY_TRADE->AkolaCltCharge($args);
            if ($chargeData['response'] == false || round($args['amount']) != round($chargeData['total_charge'])) {
                throw new Exception("Payble Amount Missmatch!!!");
            }

            $transactionType = $this->_TRADE_CONSTAINT["APPLICATION-TYPE-BY-ID"][$refLecenceData->application_type_id];

            $totalCharge = $chargeData['total_charge'];
            $mDenialAmount = $chargeData['notice_amount'];
            #-------------End Calculation-----------------------------
            #-------- Transection -------------------
            $this->begin();

            $RazorPayResponse = new TradeRazorPayResponse();
            $RazorPayResponse->temp_id   = $PinelabPay->temp_id;
            $RazorPayResponse->request_id   = $PinelabPay->id;
            $RazorPayResponse->amount       = $args['amount'];
            $RazorPayResponse->merchant_id  = $args['merchantId'] ?? null;
            $RazorPayResponse->order_id     = $args["orderId"];
            $RazorPayResponse->payment_id   = $args["paymentId"];
            $RazorPayResponse->save();

            $PinelabPay->status = 1;
            $PinelabPay->update();

            $Tradetransaction = new TradeTransaction();
            $Tradetransaction->temp_id          = $licenceId;
            $Tradetransaction->payment_gateway_type = "pinelab_pay";
            $Tradetransaction->response_id      = $RazorPayResponse->id;
            $Tradetransaction->ward_id          = $refLecenceData->ward_id;
            $Tradetransaction->tran_type        = $transactionType;
            $Tradetransaction->tran_date        = $mNowDate;
            $Tradetransaction->payment_mode     = "Online";
            $Tradetransaction->paid_amount      = $totalCharge;
            $Tradetransaction->penalty          = $chargeData['penalty'] + $mDenialAmount + $chargeData['arear_amount'];
            $Tradetransaction->emp_dtl_id       = $refUserId;
            $Tradetransaction->created_at       = $mTimstamp;
            $Tradetransaction->ip_address       = '';
            $Tradetransaction->ulb_id           = $refUlbId;
            $Tradetransaction->save();
            $transaction_id                     = $Tradetransaction->id;
            $Tradetransaction->tran_no   = $this->_REPOSITORY_TRADE->createTransactionNo($transaction_id,$mShortUlbName); //$args["transactionNo"];//"TRANML" . date('d') . $transaction_id . date('Y') . date('m') . date('s');
            $Tradetransaction->update();

            $TradeFineRebet = new TradeFineRebete();
            $TradeFineRebet->tran_id = $transaction_id;
            $TradeFineRebet->type      = 'Delay Apply License';
            $TradeFineRebet->amount         = $chargeData['penalty'];
            $TradeFineRebet->created_at     = $mTimstamp;
            $i = $TradeFineRebet->save();

            $mDenialAmount = $mDenialAmount + $chargeData['arear_amount'];
            if ($mDenialAmount > 0) {
                $TradeFineRebet2 = new TradeFineRebete;
                $TradeFineRebet2->tran_id = $transaction_id;
                $TradeFineRebet2->type      = 'Denial Apply';
                $TradeFineRebet2->amount         = $mDenialAmount;
                $TradeFineRebet2->created_at     = $mTimstamp;
                $TradeFineRebet2->save();
            }
            $request = new Request(["applicationId" => $licenceId, "ulb_id" => $refUlbId, "user_id" => $refUserId]);            

            $provNo = $this->_REPOSITORY_TRADE->createProvisinalNo($mShortUlbName, $mWardNo, $licenceId);
            $refLecenceData->provisional_license_no = $provNo;
            $refLecenceData->payment_status         = $mPaymentStatus;
            if ($refNoticeDetails) {
                $this->_NOTICE->noticeClose($refLecenceData->denial_id);                    
            }
            ($refLecenceData->id);
            $refLecenceData->update();
            $this->commit();
            #----------End transaction------------------------
            #----------Response------------------------------
            $res['transactionId'] = $transaction_id; #config('app.url') .
            $res['paymentReceipt'] =  "/api/trade/payment-receipt/" . $licenceId . "/" . $transaction_id;
            return responseMsg(true, "", $res);
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), $args);
        }
    }
    

    # Serial No : 27
    public function citizenApplication_old(Request $request)
    {
        try {

            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $request->merge(["citizenId" => $refUserId]);
            $select = [
                "licences.id",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.license_date",
                "licences.valid_from",
                "licences.valid_upto",
                "licences.document_upload_status",
                "licences.payment_status",
                "licences.pending_status",
                "licences.firm_name",
                "licences.application_date",
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
            $ActiveSelect[] = DB::raw("'active' as license_type");
            $ActiveLicence = $this->_DB->TABLE("active_trade_licences AS licences")
                ->select($ActiveSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                    STRING_AGG(email_id,',') AS email_id,
                                    active_trade_owners.temp_id
                                    FROM active_trade_owners 
                                    JOIN active_trade_licences on active_trade_licences.citizen_id = $refUserId 
                                        AND active_trade_licences.id = active_trade_owners.temp_id 
                                    WHERE active_trade_owners.is_active = true
                                    GROUP BY active_trade_owners.temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.citizen_id", $refUserId);
            // ->get();

            $RejectedSelect = $select;
            $RejectedSelect[] = DB::raw("'rejected' as license_type");
            $RejectedLicence = $this->_DB->TABLE("rejected_trade_licences AS licences")
                ->select($RejectedSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                    STRING_AGG(email_id,',') AS email_id,
                                    rejected_trade_owners.temp_id
                                    FROM rejected_trade_owners
                                    JOIN rejected_trade_licences on rejected_trade_licences.citizen_id = $refUserId 
                                        AND rejected_trade_licences.id = rejected_trade_owners.temp_id 
                                    WHERE rejected_trade_owners.is_active = true
                                    GROUP BY rejected_trade_owners.temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.citizen_id", $refUserId);
            // ->get();

            $ApprovedSelect = $select;
            $ApprovedSelect[] = DB::raw("'approved' as license_type");
            $ApprovedLicence = $this->_DB->TABLE("trade_licences AS licences")
                ->select($ApprovedSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        trade_owners.temp_id
                                        FROM trade_owners
                                        JOIN trade_licences on trade_licences.citizen_id = $refUserId 
                                        AND trade_licences.id = trade_owners.temp_id 
                                        WHERE trade_owners.is_active = true
                                        GROUP BY trade_owners.temp_id
                                        )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.citizen_id", $refUserId);
            // ->get();


            $OldSelect = $select;
            $OldSelect[] = DB::raw("'old' as license_type");
            $OldLicence = $this->_DB->TABLE("trade_renewals AS licences")
                ->select($OldSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        trade_owners.temp_id
                                        FROM trade_owners
                                        JOIN trade_renewals on trade_renewals.citizen_id = $refUserId 
                                        AND trade_renewals.id = trade_owners.temp_id 
                                        WHERE trade_owners.is_active = true
                                        GROUP BY trade_owners.temp_id
                                        )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.citizen_id", $refUserId);

            $final = $ActiveLicence->union($RejectedLicence)
                ->union($ApprovedLicence)->union($OldLicence)
                ->get();
            $final->map(function ($val) use ($refUserId) {
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
                if (trim($val->license_type) == "approved" && $val->pending_status == 5 && $val->application_type_id == 4 && $validUpto >= Carbon::now()->format('Y-m-d')) {
                    $option = [];
                }
                $val->option = $option;
                $val->pending_at = $this->_REPOSITORY_TRADE->applicationStatus($val->id, false);
                if (str_contains(strtoupper($val->pending_at), strtoupper("All Required Documents Are Uploaded"))) {
                    $val->document_upload_status = 1;
                }
                return $val;
            });
            return responseMsg(true, "", remove_null($final));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function citizenApplicationByCitizenId(Request $request)
    {
        try {
            $refUserId = $request->citizenId;
            $ulbId     = $request->ulbId;
            $cotegory = $request->cotegory;
            $status  = $request->status;
            $applicationTypeId = null;
            if ($request->applicationType) {
                $applicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType];
            }
            $select = [
                "licences.id",
                "trade_param_application_types.application_type",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.license_date",
                "licences.valid_from",
                "licences.valid_upto",
                "licences.document_upload_status",
                "licences.payment_status",
                "licences.pending_status",
                "licences.firm_name",
                "licences.application_date",
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
            $ActiveSelect[] = DB::raw("'active' as license_type");
            $ActiveLicence = $this->_DB->TABLE("active_trade_licences AS licences")
                ->select($ActiveSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                    STRING_AGG(email_id,',') AS email_id,
                                    active_trade_owners.temp_id
                                    FROM active_trade_owners 
                                    JOIN active_trade_licences on active_trade_licences.citizen_id = $refUserId 
                                        AND active_trade_licences.id = active_trade_owners.temp_id 
                                    WHERE active_trade_owners.is_active = true
                                    GROUP BY active_trade_owners.temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.citizen_id", $refUserId);
            // ->get();

            $RejectedSelect = $select;
            $RejectedSelect[] = DB::raw("'rejected' as license_type");
            $RejectedLicence = $this->_DB->TABLE("rejected_trade_licences AS licences")
                ->select($RejectedSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                    STRING_AGG(email_id,',') AS email_id,
                                    rejected_trade_owners.temp_id
                                    FROM rejected_trade_owners
                                    JOIN rejected_trade_licences on rejected_trade_licences.citizen_id = $refUserId 
                                        AND rejected_trade_licences.id = rejected_trade_owners.temp_id 
                                    WHERE rejected_trade_owners.is_active = true
                                    GROUP BY rejected_trade_owners.temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.citizen_id", $refUserId);
            // ->get();

            $ApprovedSelect = $select;
            $ApprovedSelect[] = DB::raw("'approved' as license_type");
            $ApprovedLicence = $this->_DB->TABLE("trade_licences AS licences")
                ->select($ApprovedSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        trade_owners.temp_id
                                        FROM trade_owners
                                        JOIN trade_licences on trade_licences.citizen_id = $refUserId 
                                        AND trade_licences.id = trade_owners.temp_id 
                                        WHERE trade_owners.is_active = true
                                        GROUP BY trade_owners.temp_id
                                        )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.is_active", true)
                ->where("licences.citizen_id", $refUserId);
            // ->get();


            $OldSelect = $select;
            $OldSelect[] = DB::raw("'old' as license_type");
            $OldLicence = $this->_DB->TABLE("trade_renewals AS licences")
                ->select($OldSelect)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        trade_owners.temp_id
                                        FROM trade_owners
                                        JOIN trade_renewals on trade_renewals.citizen_id = $refUserId 
                                        AND trade_renewals.id = trade_owners.temp_id 
                                        WHERE trade_owners.is_active = true
                                        GROUP BY trade_owners.temp_id
                                        )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->where("licences.citizen_id", $refUserId);
            if ($applicationTypeId) {
                $ActiveLicence = $ActiveLicence->where("licences.application_type_id", $applicationTypeId);
                $RejectedLicence = $RejectedLicence->where("licences.application_type_id", $applicationTypeId);
                $ApprovedLicence = $ApprovedLicence->where("licences.application_type_id", $applicationTypeId);
                $OldLicence      = $OldLicence->where("licences.application_type_id", $applicationTypeId);
            }
            if ($ulbId) {
                $ActiveLicence = $ActiveLicence->where("licences.ulb_id", $ulbId);
                $RejectedLicence = $RejectedLicence->where("licences.ulb_id", $ulbId);
                $ApprovedLicence = $ApprovedLicence->where("licences.ulb_id", $ulbId);
                $OldLicence      = $OldLicence->where("licences.ulb_id", $ulbId);
            }
            if ($status) {
                $ActiveLicence = $ActiveLicence->where("licences.is_active", $status);
                $RejectedLicence = $RejectedLicence->where("licences.is_active", $status);
                $ApprovedLicence = $ApprovedLicence->where("licences.is_active", $status);
                $OldLicence      = $OldLicence->where("licences.is_active", $status);
            } else {
                $ActiveLicence = $ActiveLicence->where("licences.is_active", true);
                $RejectedLicence = $RejectedLicence->where("licences.is_active", true);
                $ApprovedLicence = $ApprovedLicence->where("licences.is_active", true);
                $OldLicence      = $OldLicence->where("licences.is_active", true);
            }
            switch ($cotegory) {
                case "PENDIG":
                    $final = $ActiveLicence->get();
                    break;
                case "REJECT":
                    $final = $RejectedLicence->get();
                    break;
                case "APROVE":
                    $final = $ApprovedLicence->union($OldLicence)->get();
                    break;
                case "OLD":
                    $final = $OldLicence->get();
                    break;
                default:
                    $final = $ActiveLicence->union($RejectedLicence)->union($ApprovedLicence)->union($OldLicence)->get();
                    break;
            }

            $final->map(function ($val) use ($refUserId) {
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
                if (trim($val->license_type) == "approved" && $val->pending_status == 5 && $val->application_type_id == 4 && $validUpto >= Carbon::now()->format('Y-m-d')) {
                    $option = [];
                }
                $val->option = $option;
                $val->pending_at = $this->_REPOSITORY_TRADE->applicationStatus($val->id, false);
                if (str_contains(strtoupper($val->pending_at), strtoupper("All Required Documents Are Uploaded"))) {
                    $val->document_upload_status = 1;
                }
                return $val;
            });
            return responseMsg(true, "", remove_null($final));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function citizenApplication(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $request->merge(["citizenId" => $refUserId]);
            return $this->citizenApplicationByCitizenId($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 28
    public function readCitizenLicenceDtl(Request $request)
    {
        try {
            $id = $request->id;
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id??0;
            $refUlbId       = $refUser->ulb_id ?? 0;
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $modul_id = $this->_MODULE_ID;


            $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $refApplication = $this->_REPOSITORY_TRADE->getAllLicenceById($id);
            $mStatus = $this->_REPOSITORY_TRADE->applicationStatus($id);
            $mItemName      = "";
            $mCods          = "";
            if (!$refApplication) {
                throw new Exception("Data Not Found");
            }
            if (!$refUlbId) {
                $refUlbId = $refApplication->ulb_id;
            }

            $init_finish = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $finisher = $init_finish['finisher'];
            $finisher['short_user_name'] = $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][strtoupper($init_finish['finisher']['role_name'])];

            if ($refApplication->nature_of_bussiness) {
                $items = AkolaTradeParamItemType::itemsById($refApplication->nature_of_bussiness);
                foreach ($items as $val) {
                    $mItemName  .= $val->trade_item . ",";
                    $mCods      .= $val->trade_code . ",";
                }
                $mItemName = trim($mItemName, ',');
                $mCods = trim($mCods, ',');
            }
            $refApplication->items      = $mItemName;
            $refApplication->items_code = $mCods;
            $refOwnerDtl                = $this->_REPOSITORY_TRADE->getAllOwnereDtlByLId($id);
            $refTransactionDtl          = TradeTransaction::listByLicId($id);
            $refUploadDocuments         = $this->_MODEL_WfActiveDocument->getTradeDocByAppNo($refApplication->id, $refApplication->workflow_id, $modul_id);

            $pendingAt  = $init_finish['initiator']['id'];
            $mlevelData = $this->_REPOSITORY_TRADE->getWorkflowTrack($id);
            if ($mlevelData) {
                $pendingAt = $mlevelData->receiver_user_type_id;
            }

            $data['licenceDtl']     = $refApplication;
            $data['ownerDtl']       = $refOwnerDtl;
            $data['transactionDtl'] = $refTransactionDtl;
            $data['pendingStatus']  = $mStatus;
            $data['documents']      = $refUploadDocuments;
            $data["userType"]       = $mUserType;
            $data["pendingAt"]      = $pendingAt;
            $data = remove_null($data);

            return responseMsg(true, "", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), '');
        }
    }
}
