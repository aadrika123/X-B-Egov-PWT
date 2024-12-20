<?php

namespace App\Http\Controllers\Trade;

use Exception;
use Carbon\Carbon;
use App\Models\UlbMaster;
use Illuminate\Http\Request;
use App\Models\WorkflowTrack;
use App\Repository\Trade\Trade;
use App\MicroServices\DocUpload;
use App\Models\Trade\TradeOwner;
use App\Repository\Trade\ITrade;
use App\Models\Trade\TradeLicence;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Workflows\WfWorkflow;
use Illuminate\Foundation\Auth\User;
use App\Http\Requests\Trade\ReqInbox;
use Illuminate\Support\Facades\Config;
use App\EloquentModels\Common\ModelWard;
use App\Http\Requests\Trade\ApplicationId;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\TradeParamFirmType;
use App\Models\Trade\TradeParamItemType;
use App\Repository\Common\CommonFunction;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Trade\ReqAddRecorde;
use App\Models\Workflows\WfActiveDocument;
use App\Http\Requests\Trade\paymentCounter;
use App\Http\Requests\Trade\ReqApplyDenail;
use App\Http\Requests\Trade\ReqGetUpdateBasicDtl;
use App\Http\Requests\Trade\ReqPaybleAmount;
use App\Http\Requests\Trade\ReqTempAddRecord;
use App\Models\Trade\TradeParamCategoryType;
use App\Models\Trade\TradeParamOwnershipType;
use App\Http\Requests\Trade\ReqUpdateBasicDtl;
use App\Models\ActiveCitizen;
use App\Models\Property\ZoneMaster;
use App\Models\Trade\ActiveTradeOwner;
use App\Models\Trade\AkolaTradeParamItemType;
use App\Models\Trade\RejectedTradeOwner;
use App\Models\Trade\TradeRenewal;
use App\Models\Workflows\WfRoleusermap;
use App\Traits\Trade\TradeTrait;

class TradeApplication extends Controller
{
    use TradeTrait;

    /**
     * | Created On-01-10-2022 
     * | Created By-Sandeep Bara
     * --------------------------------------------------------------------------------------
     * | Controller regarding with Trade Module
     */

    // Initializing function for Repository

    protected $_DB;
    protected $_DB_MASTER;
    protected $_DB_NAME;
    protected $_NOTICE_DB;
    protected $_NOTICE_DB_NAME;
    protected $_MODEL_WARD;
    protected $_COMMON_FUNCTION;
    protected $_REPOSITORY;
    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;
    protected $_WF_TEMP_MASTER_Id;

    protected $_CONTROLLER_TRADE;

    protected $_MODEL_TradeParamFirmType;
    protected $_MODEL_TradeParamOwnershipType;
    protected $_MODEL_TradeParamCategoryType;
    protected $_MODEL_TradeParamItemType;
    protected $_MODEL_ActiveTradeLicence;
    protected $_MODEL_ActiveTradeOwner;
    protected $_MODEL_AkolaTradeParamItemType;

    public function __construct(ITrade $TradeRepository)
    {
        $this->_DB_NAME = "pgsql_trade";
        $this->_NOTICE_DB = "pgsql_notice";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_DB_MASTER = DB::connection("pgsql_master");
        $this->_NOTICE_DB = DB::connection($this->_NOTICE_DB);
        DB::enableQueryLog();
        // $this->_DB->enableQueryLog();
        // $this->_NOTICE_DB->enableQueryLog();

        $this->_REPOSITORY = $TradeRepository;
        $this->_MODEL_WARD = new ModelWard();
        $this->_COMMON_FUNCTION = new CommonFunction();
        $this->_CONTROLLER_TRADE = new Trade();

        $this->_MODEL_TradeParamFirmType = new TradeParamFirmType($this->_DB_NAME);
        $this->_MODEL_TradeParamOwnershipType = new TradeParamOwnershipType($this->_DB_NAME);
        $this->_MODEL_TradeParamCategoryType = new TradeParamCategoryType($this->_DB_NAME);
        $this->_MODEL_TradeParamItemType = new TradeParamItemType($this->_DB_NAME);
        $this->_MODEL_ActiveTradeLicence = new ActiveTradeLicence($this->_DB_NAME);
        $this->_MODEL_ActiveTradeOwner  = new ActiveTradeOwner($this->_DB_NAME);
        $this->_MODEL_AkolaTradeParamItemType = new AkolaTradeParamItemType($this->_DB_NAME);

        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_TEMP_MASTER_Id = Config::get('workflow-constants.TRADE_TEMP_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];
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
    public function getMstrForNewLicense(Request $request)
    {
        try {
            $request->request->add(["applicationType" => "NEWLICENSE"]);
            return $this->getApplyData($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function getMstrForRenewal(Request $request)
    {
        try {
            $request->request->add(["applicationType" => "RENEWAL"]);
            return $this->getApplyData($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function getMstrForAmendment(Request $request)
    {
        try {
            $request->request->add(["applicationType" => "AMENDMENT"]);
            return $this->getApplyData($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function getMstrForSurender(Request $request)
    {
        try {
            $request->request->add(["applicationType" => "SURRENDER"]);
            return $this->getApplyData($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function getApplyData(Request $request)
    {
        try {
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id ?? $request->ulbId;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mApplicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType] ?? null;
            $mnaturOfBusiness   = null;
            $data               = array();
            $rules["applicationType"] = "required|string|in:" . collect(flipConstants($this->_TRADE_CONSTAINT["APPLICATION-TYPE"]))->implode(",");
            if (!in_array($mApplicationTypeId, [1])) {
                $rules["licenseId"] = "required|digits_between:1,9223372036854775807";
            }
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), "");
            }
            #------------------------End Declaration-----------------------                       

            $data['userType']           = $mUserType;
            $data['userType']           = $mUserType;
            $data["zone"]  = (new ZoneMaster())->getZone($refUlbId);
            $data["firmTypeList"]       = $this->_MODEL_TradeParamFirmType->readConnection()->List();
            $data["ownershipTypeList"]  = $this->_MODEL_TradeParamOwnershipType->readConnection()->List();
            $data["categoryTypeList"]   = $this->_MODEL_TradeParamCategoryType->readConnection()->List();
            $data["natureOfBusiness"]   =  $this->_MODEL_AkolaTradeParamItemType->readConnection()->List(); #$this->_MODEL_TradeParamItemType->List(true);
            if (isset($request->licenseId) && $request->licenseId  && $mApplicationTypeId != 1) {
                $mOldLicenceId = $request->licenseId;
                $nextMonth = Carbon::now()->addMonths(3)->format('Y-m-d');
                $refOldLicece = $this->_REPOSITORY->getLicenceById($mOldLicenceId); //TradeLicence::find($mOldLicenceId)
                if (!$refOldLicece) {
                    throw new Exception("Old Licence Not Found");
                }
                if (!$refOldLicece->is_active) {
                    $newLicense = $this->_MODEL_ActiveTradeLicence->readConnection()->where("license_no", $refOldLicece->license_no)
                        ->orderBy("id")
                        ->first();
                    throw new Exception("Application Already Apply Please Track  " . $newLicense->application_no);
                }
                if ($refOldLicece->valid_upto > $nextMonth && !in_array($mApplicationTypeId, [3, 4])) {
                    throw new Exception("Licence Valice Upto " . $refOldLicece->valid_upto);
                }
                if ($refOldLicece->valid_upto < (Carbon::now()->format('Y-m-d')) && in_array($mApplicationTypeId, [3, 4])) {
                    throw new Exception("Licence Was Expired Please Renewal First");
                }
                if ($refOldLicece->pending_status != 5) {
                    throw new Exception("Application not approved Please Track  " . $refOldLicece->application_no);
                }
                if ($refOldLicece->payment_status != 1) {
                    throw new Exception("Application payment not clear Please pay application charge first " . $refOldLicece->application_no);
                }
                $refOldOwneres = TradeOwner::owneresByLId($request->licenseId);
                $mnaturOfBusiness = $this->_MODEL_AkolaTradeParamItemType->readConnection()->itemsById($refOldLicece->nature_of_bussiness);
                $natur = array();
                foreach ($mnaturOfBusiness as $val) {
                    $natur[] = [
                        "id" => $val->id,
                        "trade_item" => "(" . $val->trade_code . ") " . $val->trade_item
                    ];
                }
                $refOldLicece->nature_of_bussiness = $natur;
                $data["licenceDtl"]     =  $refOldLicece;
                $data["ownerDtl"]       = $refOldOwneres;
                $refUlbId = $refOldLicece->ulb_id;
            }
            if (in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {
                $data['wardList'] = $this->_MODEL_WARD->getOldWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $data['wardList'] = objToArray($data['wardList']);
            } else {
                $data['wardList'] = $this->_COMMON_FUNCTION->oldWardPermission($refUserId);
            }
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    # Serial No : 01
    public function applyApplication(ReqAddRecorde $request)
    {
        $refUser            = Auth()->user();
        $refUserId          = $refUser->id;
        $refUlbId           = $refUser->ulb_id;
        if ($refUser->user_type == $this->_TRADE_CONSTAINT["CITIZEN"]) {
            $refUlbId = $request->ulbId ?? 0;
        }
        $refWorkflowId      = $this->_WF_MASTER_Id;
        $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
        $refWorkflows       = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
        $mApplicationTypeId = ($this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType] ?? null);
        try {
            if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }
            if (!in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-APPLY-APPLICATION"])) {
                throw new Exception("You Are Not Authorized For This Action !");
            }
            if (!$mApplicationTypeId) {
                throw new Exception("Invalide Application Type");
            }
            if (!$refWorkflows) {
                throw new Exception("Workflow Not Available");
            }
            if (!$refWorkflows['initiator']) {
                throw new Exception("Initiator Not Available");
            }
            if (!$refWorkflows['finisher']) {
                throw new Exception("Finisher Not Available");
            }
            // return $request->applicationType;
            if (in_array($mApplicationTypeId, ["2", "3", "4"]) && (!$request->licenseId || !is_numeric($request->licenseId))) {
                throw new Exception("Old licence Id Requird");
            }
            return $this->_REPOSITORY->addRecord($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    # Serial No : 02
    public function applyTempApplication(ReqTempAddRecord $request)
    {
        // $refUser            = Auth()->user();
        // $refUserId          = $refUser->id;
        $refUserId          = 203;
        $refUlbId           = 2;
        // if ($refUser->user_type == $this->_TRADE_CONSTAINT["CITIZEN"]) {
        //     $refUlbId = $request->ulbId ?? 0;
        // }
        $refWorkflowId      = $this->_WF_TEMP_MASTER_Id;
        $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
        $refWorkflows       = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
        $mApplicationTypeId = ($this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType] ?? null);
        try {
            // if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
            //     throw new Exception("Citizen Not Allowed");
            // }
            if (!in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-APPLY-APPLICATION"])) {
                throw new Exception("You Are Not Authorized For This Action !");
            }
            if (!$mApplicationTypeId) {
                throw new Exception("Invalide Application Type");
            }
            if (!$refWorkflows) {
                throw new Exception("Workflow Not Available");
            }
            if (!$refWorkflows['initiator']) {
                throw new Exception("Initiator Not Available");
            }
            if (!$refWorkflows['finisher']) {
                throw new Exception("Finisher Not Available");
            }
            // return $request->applicationType;
            if (in_array($mApplicationTypeId, ["2", "3", "4"]) && (!$request->licenseId || !is_numeric($request->licenseId))) {
                throw new Exception("Old licence Id Requird");
            }
            return $this->_REPOSITORY->addRecordTemp($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function paymentCounter(paymentCounter $request)
    {
        try {
            if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }
            return $this->_REPOSITORY->paymentCounter($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    # Serial No : 02
    public function updateLicenseBo(ReqGetUpdateBasicDtl $request)
    {
        return $this->_REPOSITORY->updateLicenseBo($request);
    }

    public function updateBasicDtl(ReqUpdateBasicDtl $request)
    {
        return $this->_REPOSITORY->updateBasicDtl($request);
    }

    public function getDocList(ApplicationId $request)
    {
        $tradC = $this->_CONTROLLER_TRADE;
        $response =  $tradC->getLicenseDocLists($request);
        if ($response->original["status"]) {
            $ownerDoc = $response->original["data"]["ownerDocs"];
            $Wdocuments = collect();

            // $ownerDocCount = $ownerDoc->count();
            // $response->original["data"]["ownerDocCount"] = $ownerDocCount;
            $ownerDoc->map(function ($val) use ($Wdocuments) {

                $ownerId = $val["ownerDetails"]["ownerId"] ?? "";
                $val["documents"]->map(function ($val1) use ($Wdocuments, $ownerId) {
                    $val1["ownerId"] = $ownerId;
                    $val1["is_uploded"] = (in_array($val1["docType"], ["R", "OR"]))  ? ((!empty($val1["uploadedDoc"])) ? true : false) : true;
                    $val1["is_docVerify"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"] != 0) ? true : false) : true;

                    collect($val1["masters"])->map(function ($v) use ($Wdocuments) {
                        $Wdocuments->push($v);
                    });
                });
            });
            if ($Wdocuments->isEmpty()) {
                $newrespons = [];
                foreach ($response->original["data"] as $key => $val) {
                    if ($key != "ownerDocs") {
                        $newrespons[$key] = $val;
                    }
                }

                $response->original["data"] = $newrespons;
                $response = responseMsgs(
                    $response->original["status"],
                    $response->original["message"],
                    $response->original["data"],
                    $response->original["meta-data"]["apiId"] ?? "",
                    $response->original["meta-data"]["version"] ?? "",
                    $response->original["meta-data"]["responsetime"] ?? "",
                    $response->original["meta-data"]["action"] ?? "",
                    $response->original["meta-data"]["deviceId"] ?? ""
                );
            }
        }
        return $response;
    }


    # Serial No : 04
    public function paymentReceipt(Request $request)
    {
        $id = $request->id;
        $transectionId =  $request->transectionId;
        $request->setMethod('POST');
        $request->request->add(["id" => $id, "transectionId" => $transectionId]);
        $rules = [
            "id" => "required|digits_between:1,9223372036854775807",
            "transectionId" => "required|digits_between:1,9223372036854775807",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), $request->all());
        }
        return $this->_REPOSITORY->readPaymentReceipt($id, $transectionId);
    }


    # Serial No : 07
    public function documentVerify(Request $request)
    {
        $request->validate([
            'id' => 'required|digits_between:1,9223372036854775807',
            'applicationId' => 'required|digits_between:1,9223372036854775807',
            'docRemarks' =>  $request->docStatus == "Rejected" ? 'required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/' : "nullable",
            'docStatus' => 'required|in:Verified,Rejected'
        ]);
        try {
            if ((!$this->_COMMON_FUNCTION->checkUsersWithtocken("users"))) {
                throw new Exception("Citizen Not Allowed");
            }
            // Variable Assignments
            $user = Auth()->user();
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $mWfDocument = new WfActiveDocument();
            $workflow_id = $this->_WF_MASTER_Id;
            $rolles = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $workflow_id);
            if (!$rolles || !$rolles->can_verify_document) {
                throw new Exception("You are Not Authorized For Document Verify");
            }
            $wfDocId = $request->id;
            $applicationId = $request->applicationId;
            $this->begin();
            if ($request->docStatus == "Verified") {
                $status = 1;
            }
            if ($request->docStatus == "Rejected") {
                $status = 2;
            }

            $myRequest = [
                'remarks' => $request->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $userId
            ];
            $mWfDocument->docVerifyReject($wfDocId, $myRequest);
            $this->commit();
            $tradR = $this->_CONTROLLER_TRADE;
            $doc = $tradR->getLicenseDocLists($request);
            $docVerifyStatus = $doc->original["data"]["docVerifyStatus"] ?? 0;

            return responseMsgs(true, ["docVerifyStatus" => $docVerifyStatus], "", "tc7.1", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "tc7.1", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }
    # Serial No : 08 
    public function getLicenceDtl(Request $request)
    {

        $rules["applicationId"] = "required|digits_between:1,9223372036854775807";
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), $request->all());
        }
        return $this->_REPOSITORY->readLicenceDtl($request);
    }

    public function getLicenceDtlv1(Request $request)
    {

        $rules["applicationId"] = "required|digits_between:1,9223372036854775807";
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), $request->all());
        }
        return $this->_REPOSITORY->readLicenceDtlv1($request);
    }
    # Serial No : 09 
    public function getDenialDetails(Request $request)
    {
        return $this->_REPOSITORY->readDenialdtlbyNoticno($request);
    }
    # Serial No : 10 
    public function paybleAmount(ReqPaybleAmount $request)
    {
        return $this->_REPOSITORY->getPaybleAmount($request);
    }

    # Serial No : 12 
    public function validateHoldingNo(Request $request)
    {
        return $this->_REPOSITORY->isvalidateHolding($request);
    }
    # Serial No : 13 
    public function searchLicence(Request $request)
    {
        return $this->_REPOSITORY->searchLicenceByNo($request);
    }
    # Serial No : 14
    public function readApplication(Request $request)
    {
        $rules = [
            "entityValue"   =>  "required",
            "entityName"    =>  "required",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), "");
        }
        return $this->_REPOSITORY->readApplication($request);
    }

    public function readApplicationv1(Request $request)
    {
        $rules = [
            "entityValue"   =>  "required",
            "entityName"    =>  "required",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), "");
        }
        return $this->_REPOSITORY->readApplicationv1($request);
    }
    public function workflowDashordDetails(Request $request)
    {
        try {

            $track = new WorkflowTrack();
            $mWfWorkflow = new WfWorkflow();
            $tradC = $this->_CONTROLLER_TRADE;
            $refUser = Auth()->user();
            $refUserId = $refUser->id;
            $refUlbId = $refUser->ulb_id;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $userRole = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            $wfAllRoles         = $this->_COMMON_FUNCTION->getWorkFlowAllRoles($refUserId, $refUlbId, $refWorkflowId, true);
            $workflow           = $mWfWorkflow->getulbWorkflowId($refWorkflowId, $refUlbId);
            if (!$userRole) {
                throw new Exception("Access Denied! No Role");
            }
            $canView = true;
            if (((!$userRole->forward_role_id ?? false) && !$userRole->backward_role_id)) {
                $canView = false;
            }

            $metaRequest = new Request([
                'workflowId'    => $workflow->id,
                'ulbId'         => $refUlbId,
                'moduleId'      => $this->_MODULE_ID
            ]);
            $dateWiseData = $track->getWfDashbordData($metaRequest)->get();
            $request->request->add(["all" => true]);
            $inboxData = $this->_REPOSITORY->inbox($request);
            $returnData = [
                'canView'               => $canView,
                'userDetails'           => $refUser,
                'roleId'                => $userRole->role_id,
                'roleName'              => $userRole->role_name,
                "shortRole"             => ($this->_TRADE_CONSTAINT['USER-TYPE-SHORT-NAME'][strtoupper($userRole->role_name)]) ?? "N/A",
                'todayForwardCount'     => collect($dateWiseData)->where('sender_role_id', $userRole->role_id)->count(),
                'todayReceivedCount'    => $userRole->is_initiator == false
                    ?
                    collect($dateWiseData)->where('receiver_role_id', $userRole->role_id)->count()
                    : (
                        (
                            $inboxData->original['data']->where("application_date", Carbon::now()->format('d-m-Y'))->whereNotIn("id", $dateWiseData->pluck("ref_table_id_value"))->count() ?? 0 ?? 0
                        )
                        +
                        (
                            collect($dateWiseData)->where('receiver_role_id', $userRole->role_id)->count() ?? 0
                        )
                    ),
                'pendingApplication'    => $inboxData->original['data']->count() ?? 0,
                'newLicense'            => $inboxData->original['data']->where("application_type_id", 1)->count() ?? 0,
                'renewalLicense'        => $inboxData->original['data']->where("application_type_id", 2)->count() ?? 0,
                'amendmentLicense'      => $inboxData->original['data']->where("application_type_id", 3)->count() ?? 0,
                'surenderLicense'       => $inboxData->original['data']->where("application_type_id", 4)->count() ?? 0
            ];
            return responseMsgs(true, "", remove_null($returnData), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "01", ".ms", "POST", "");
        }
    }
    # Serial No : 15
    public function postEscalate(Request $request)
    {
        return $this->_REPOSITORY->postEscalate($request);
    }
    public function specialInbox(ReqInbox $request)
    {
        return $this->_REPOSITORY->specialInbox($request);
    }
    public function btcInbox(ReqInbox $request)
    {
        return $this->_REPOSITORY->btcInbox($request);
    }
    # Serial No : 16 App\Http\Requests\Trade\ReqInbox
    public function inbox(ReqInbox $request)
    {
        return $this->_REPOSITORY->inbox($request);
    }
    # Serial No : 17
    public function outbox(ReqInbox $request)
    {
        return $this->_REPOSITORY->outbox($request);
    }

    public function approvedButNotPayment(ReqInbox $request)
    {
        $rules = [
            "entityName"    =>  "nullable",
            "entityValue"   =>  "nullable|required_with:entityName",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), "");
        }
        return $this->_REPOSITORY->approvedButNotPayment($request);
    }

    # Serial No
    public function backToCitizen(Request $req)
    {
        $user = Auth()->user();
        $user_id = $user->id;
        $ulb_id = $user->ulb_id;

        $refWorkflowId = $this->_WF_MASTER_Id;
        $role = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id, $refWorkflowId);


        try {
            $req->validate([
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                // 'workflowId' => 'required|integer',
                'currentRoleId' => 'required|integer',
                'comment' => 'required|string'
            ]);

            $activeLicence = $this->_MODEL_ActiveTradeLicence->find($req->applicationId);
            if (!$activeLicence) {
                throw new Exception("Application Not Found");
            }
            if ($activeLicence->is_parked) {
                throw new Exception("Application Already BTC");
            }
            if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }
            if (!$req->senderRoleId) {
                $req->merge(["senderRoleId" => $role->role_id ?? 0]);
            }
            if (!$req->receiverRoleId) {
                $req->merge(["receiverRoleId" => $role->backward_role_id ?? 0]);
            }
            if ($activeLicence->current_role != $req->senderRoleId) {
                throw new Exception("Application Access Forbiden");
            }
            $track = new WorkflowTrack();
            $lastworkflowtrack = $track->select("*")
                ->where('ref_table_id_value', $req->applicationId)
                ->where('module_id', $this->_MODULE_ID)
                ->where('ref_table_dot_id', "active_trade_licences")
                ->whereNotNull('sender_role_id')
                ->orderBy("track_date", 'DESC')
                ->first();
            $this->begin();
            $initiatorRoleId = $activeLicence->initiator_role;
            // $activeLicence->current_role = $initiatorRoleId;
            $activeLicence->is_parked = true;
            $activeLicence->save();

            $metaReqs['moduleId'] = $this->_MODULE_ID;
            $metaReqs['workflowId'] = $activeLicence->workflow_id;
            $metaReqs['refTableDotId'] = $this->_REF_TABLE;
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['trackDate'] = $lastworkflowtrack && $lastworkflowtrack->forward_date ? ($lastworkflowtrack->forward_date . " " . $lastworkflowtrack->forward_time) : Carbon::now()->format('Y-m-d H:i:s');
            $metaReqs['forwardDate'] = Carbon::now()->format('Y-m-d');
            $metaReqs['forwardTime'] = Carbon::now()->format('H:i:s');
            $metaReqs['verificationStatus'] = $this->_TRADE_CONSTAINT["VERIFICATION-STATUS"]["BTC"]; #2
            $metaReqs['user_id'] = $user_id;
            $metaReqs['ulb_id'] = $ulb_id;
            $req->merge($metaReqs);
            $track->saveTrack($req);

            $this->commit();
            return responseMsgs(true, "Successfully Done", "", "010111", "1.0", "350ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    # Serial No : 18
    #please not use custome request
    public function postNextLevel(Request $request)
    {
        $user = Auth()->user();
        $user_id = $user->id;
        $ulb_id = $user->ulb_id;

        $refWorkflowId = $this->_WF_MASTER_Id;
        $role = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id, $refWorkflowId);

        $request->validate([
            "action"        => 'required|in:forward,backward',
            'applicationId' => 'required|digits_between:1,9223372036854775807',
            'senderRoleId' => 'nullable|integer',
            'receiverRoleId' => 'nullable|integer',
            'comment' => ($role->is_initiator ?? false) ? "nullable" : 'required',
        ]);

        try {
            if (!$request->senderRoleId) {
                $request->merge(["senderRoleId" => $role->role_id ?? 0]);
            }
            if (!$request->receiverRoleId) {
                if ($request->action == 'forward') {
                    $request->merge(["receiverRoleId" => $role->forward_role_id ?? 0]);
                }
                if ($request->action == 'backward') {
                    $request->merge(["receiverRoleId" => $role->backward_role_id ?? 0]);
                }
            }


            #if finisher forward then
            if (($role->is_finisher ?? 0) && $request->action == 'forward') {
                $request->merge(["status" => 1]);
                return $this->approveReject($request);
            }

            if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }

            #Trade Application Update Current Role Updation

            $workflowId = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $ulb_id)
                ->first();
            if (!$workflowId) {
                throw new Exception("Workflow Not Available");
            }

            $licence = $this->_MODEL_ActiveTradeLicence->find($request->applicationId);
            if (!$licence) {
                throw new Exception("Data Not Found");
            }
            // if($licence->is_parked && $request->action=='forward')
            // {
            //      $request->request->add(["receiverRoleId"=>$licence->current_role??0]);
            // }
            $allRolse     = collect($this->_COMMON_FUNCTION->getAllRoles($user_id, $ulb_id, $refWorkflowId, 0, true));

            $initFinish   = $this->_COMMON_FUNCTION->iniatorFinisher($user_id, $ulb_id, $refWorkflowId);
            $receiverRole = array_values(objToArray($allRolse->where("id", $request->receiverRoleId)))[0] ?? [];
            $senderRole   = array_values(objToArray($allRolse->where("id", $request->senderRoleId)))[0] ?? [];

            // if ($licence->payment_status != 1 && ($role->serial_no  < $receiverRole["serial_no"] ?? 0)) {
            //     throw new Exception("Payment Not Clear");
            // }
            if ((!$role->is_finisher ?? 0) && $request->action == 'backward' && $receiverRole["id"] == $initFinish['initiator']['id']) {
                $request->merge(["currentRoleId" => $request->senderRoleId]);
                return $this->backToCitizen($request);
            }

            if ($licence->current_role != $role->role_id && (!$licence->is_parked)) {
                throw new Exception("You Have Not Pending This Application");
            }
            if ($licence->is_parked && !$role->is_initiator) {
                throw new Exception("You Aer Not Authorized For Forword BTC Application");
            }

            $sms = "Application BackWord To " . $receiverRole["role_name"] ?? "";

            if ($role->serial_no  < $receiverRole["serial_no"] ?? 0) {
                $sms = "Application Forward To " . $receiverRole["role_name"] ?? "";
            }
            $tradC = $this->_CONTROLLER_TRADE;
            $documents = $tradC->checkWorckFlowForwardBackord($request);

            if ((($senderRole["serial_no"] ?? 0) < ($receiverRole["serial_no"] ?? 0)) && !$documents) {
                if (($role->can_upload_document ?? false) && $licence->is_parked) {
                    throw new Exception("Rejected documents are not uploaded");
                }
                if (($role->can_upload_document ?? false)) {
                    throw new Exception("No all mandatory documents are uploaded");
                }
                if ($role->can_verify_document ?? false) {
                    throw new Exception("Not all documents have been verified, or the mandatory document has been rejected.");
                }
                throw new Exception("Not all actions are performed");
            }
            if ($role->can_upload_document) {
                if (($role->serial_no < $receiverRole["serial_no"] ?? 0)) {
                    $licence->document_upload_status = true;
                    $licence->pending_status = 1;
                    $licence->is_parked = false;
                }
                if (($role->serial_no > $receiverRole["serial_no"] ?? 0)) {
                    $licence->document_upload_status = false;
                }
            }
            if ($role->can_verify_document) {
                if (($role->serial_no < $receiverRole["serial_no"] ?? 0)) {
                    $licence->is_doc_verified = true;
                    $licence->doc_verified_by = $user_id;
                    $licence->doc_verify_date = Carbon::now()->format("Y-m-d");
                }
                if (($role->serial_no > $receiverRole["serial_no"] ?? 0)) {
                    $licence->is_doc_verified = false;
                }
            }

            $this->begin();
            $licence->max_level_attained = ($licence->max_level_attained < ($receiverRole["serial_no"] ?? 0)) ? ($receiverRole["serial_no"] ?? 0) : $licence->max_level_attained;
            $licence->current_role = $request->receiverRoleId;
            if ($licence->is_parked && $request->action == 'forward') {
                $licence->is_parked = false;
            }
            $licence->update();

            $track = new WorkflowTrack();
            $lastworkflowtrack = $track->select("*")
                ->where('ref_table_id_value', $request->applicationId)
                ->where('module_id', $this->_MODULE_ID)
                ->where('ref_table_dot_id', $this->_REF_TABLE)
                ->whereNotNull('sender_role_id')
                ->orderBy("track_date", 'DESC')
                ->first();


            $metaReqs['moduleId'] = $this->_MODULE_ID;
            $metaReqs['workflowId'] = $licence->workflow_id;
            $metaReqs['refTableDotId'] = $this->_REF_TABLE;
            $metaReqs['refTableIdValue'] = $request->applicationId;
            $metaReqs['user_id'] = $user_id;
            $metaReqs['ulb_id'] = $ulb_id;
            $metaReqs['trackDate'] = $lastworkflowtrack && $lastworkflowtrack->forward_date ? ($lastworkflowtrack->forward_date . " " . $lastworkflowtrack->forward_time) : Carbon::now()->format('Y-m-d H:i:s');
            $metaReqs['forwardDate'] = Carbon::now()->format('Y-m-d');
            $metaReqs['forwardTime'] = Carbon::now()->format('H:i:s');
            $metaReqs['verificationStatus'] = ($request->action == 'forward') ? $this->_TRADE_CONSTAINT["VERIFICATION-STATUS"]["VERIFY"] : $this->_TRADE_CONSTAINT["VERIFICATION-STATUS"]["BACKWARD"];
            $request->merge($metaReqs);
            $track->saveTrack($request);

            $this->commit();
            return responseMsgs(true, $sms, "", "010109", "1.0", "286ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No 
    #please not use custome request
    public function approveReject(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                "applicationId" => "required",
                "status" => "required",
                "comment" => $req->status == 0 ? "required" : "nullable",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 200);
            }
            if (!$this->_COMMON_FUNCTION->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }

            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $refWorkflowId = $this->_WF_MASTER_Id;

            $activeLicence = $this->_MODEL_ActiveTradeLicence->find($req->applicationId);

            $role = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id, $refWorkflowId);

            if (!$activeLicence) {
                throw new Exception("Data Not Found!");
            }
            if ($activeLicence->finisher_role != $role->role_id) {
                throw new Exception("Forbidden Access");
            }
            if (!$req->senderRoleId) {
                $req->merge(["senderRoleId" => $role->role_id ?? 0]);
            }
            if (!$req->receiverRoleId) {
                if ($req->action == 'forward') {
                    $req->merge(["receiverRoleId" => $role->forward_role_id ?? 0]);
                }
                if ($req->action == 'backward') {
                    $req->merge(["receiverRoleId" => $role->backward_role_id ?? 0]);
                }
            }
            $track = new WorkflowTrack();
            $lastworkflowtrack = $track->select("*")
                ->where('ref_table_id_value', $req->applicationId)
                ->where('module_id', $this->_MODULE_ID)
                ->where('ref_table_dot_id', "active_trade_licences")
                ->whereNotNull('sender_role_id')
                ->orderBy("track_date", 'DESC')
                ->first();
            $metaReqs['moduleId'] = $this->_MODULE_ID;
            $metaReqs['workflowId'] = $activeLicence->workflow_id;
            $metaReqs['refTableDotId'] = 'active_trade_licences';
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['user_id'] = $user_id;
            $metaReqs['ulb_id'] = $ulb_id;
            $metaReqs['trackDate'] = $lastworkflowtrack && $lastworkflowtrack->forward_date ? ($lastworkflowtrack->forward_date . " " . $lastworkflowtrack->forward_time) : Carbon::now()->format('Y-m-d H:i:s');
            $metaReqs['forwardDate'] = Carbon::now()->format('Y-m-d');
            $metaReqs['forwardTime'] = Carbon::now()->format('H:i:s');
            $metaReqs['verificationStatus'] = ($req->status == 1) ? $this->_TRADE_CONSTAINT["VERIFICATION-STATUS"]["APROVE"] : $this->_TRADE_CONSTAINT["VERIFICATION-STATUS"]["REJECT"];
            $req->merge($metaReqs);

            $this->begin();

            $track->saveTrack($req);
            // Approval
            if ($req->status == 1) {
                $refUlbDtl          = UlbMaster::find($activeLicence->ulb_id);
                // Objection Application replication
                $approvedLicence = $activeLicence->replicate();
                $approvedLicence->setTable('trade_licences');
                $approvedLicence->pending_status = 5;
                $approvedLicence->id = $activeLicence->id;
                $status = $this->giveValidity($approvedLicence);
                if (!$status) {
                    throw new Exception("Some Error Occurs");
                }
                $approvedLicence->save();
                $owneres = $this->_MODEL_ActiveTradeOwner->select("*")
                    ->where("temp_id", $activeLicence->id)
                    ->get();
                foreach ($owneres as $val) {
                    $refOwners = $val->replicate();
                    // $refOwners->id = $val->id;
                    $refOwners->setTable('trade_owners');
                    $refOwners->save();
                    $val->delete();
                }
                $activeLicence->delete();
                $licenseNo = $approvedLicence->license_no;
                $msg =  "Application Successfully Approved !!. Your License No Is " . $licenseNo;
                $sms = trade(["application_no" => $approvedLicence->application_no, "licence_no" => $approvedLicence->license_no, "ulb_name" => $refUlbDtl->ulb_name ?? ""], "Application Approved");
            }

            // Rejection
            if ($req->status == 0) {
                // Objection Application replication
                $approvedLicence = $activeLicence->replicate();
                $approvedLicence->setTable('rejected_trade_licences');
                $approvedLicence->id = $activeLicence->id;
                $approvedLicence->pending_status = 4;
                $approvedLicence->reject_remarks = $req->comment;
                $approvedLicence->save();
                $owneres = $this->_MODEL_ActiveTradeOwner->select("*")
                    ->where("temp_id", $activeLicence->id)
                    ->get();
                foreach ($owneres as $val) {
                    $refOwners = $val->replicate();
                    $refOwners->id = $val->id;
                    $refOwners->setTable('rejected_trade_owners');
                    $refOwners->save();
                    $val->delete();
                }
                $activeLicence->delete();
                $msg = "Application Successfully Rejected !!";
                // $sms = trade(["application_no"=>$approvedLicence->application_no,"licence_no"=>$approvedLicence->license_no,"ulb_name"=>$refUlbDtl->ulb_name??""],"Application Approved");
            }
            if (($sms["status"] ?? false)) {
                $tradC = $this->_CONTROLLER_TRADE;
                $owners = $tradC->getAllOwnereDtlByLId($req->applicationId);
                foreach ($owners as $val) {
                    $respons = send_sms($val["mobile_no"], $sms["sms"], $sms["temp_id"]);
                }
            }
            $this->commit();

            return responseMsgs(true, $msg, "", '010811', '01', '474ms-573', 'Post', '');
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    # Serial No : 19
    public function provisionalCertificate(Request $request)
    {
        $id = $request->id;
        $request->setMethod('POST');
        $request->request->add(["id" => $id]);
        $rules = [
            "id" => "required|digits_between:1,9223372036854775807",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), "");
        }
        return $this->_REPOSITORY->provisionalCertificate($request->id);
    }
    # Serial No : 20
    public function licenceCertificate(Request $request)
    {
        $id = $request->id;
        $request->setMethod('POST');
        $request->request->add(["id" => $id]);
        $rules = [
            "id" => "required|digits_between:1,9223372036854775807",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), "");
        }
        return $this->_REPOSITORY->licenceCertificate($request->id);
    }

    # Serial No : 22
    public function addIndependentComment(Request $request)
    {
        return $this->_REPOSITORY->addIndependentComment($request);
    }
    # Serial No : 23
    public function readIndipendentComment(Request $request)
    {
        return $this->_REPOSITORY->readIndipendentComment($request);
    }

    # Serial No : 26
    public function approvedApplication(Request $request)
    {
        return $this->_REPOSITORY->approvedApplication($request);
    }



    /**
     *  get uploaded documents
     */
    public function getUploadDocuments(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807'
        ]);
        try {
            $mWfActiveDocument = new WfActiveDocument();
            $mActiveTradeLicence = $this->_MODEL_ActiveTradeLicence;
            $modul_id = $this->_MODULE_ID;
            $licenceDetails = $mActiveTradeLicence->getLicenceNo($req->applicationId);
            if (!$licenceDetails)
                throw new Exception("Application Not Found for this application Id");

            $appNo = $licenceDetails->application_no;
            $tradR = $this->_CONTROLLER_TRADE;
            $documents = $mWfActiveDocument->getTradeDocByAppNo($licenceDetails->id, $licenceDetails->workflow_id, $modul_id)->map(function ($val) {
                $uploadeUser = $val->uploaded_by_type != "Citizen" ? User::find($val->uploaded_by ?? 0) : ActiveCitizen::find($val->uploaded_by ?? 0);
                $val->uploadedBy = ($uploadeUser->name ?? ($uploadeUser->user_name ?? "")) . " (" . $val->uploaded_by_type . ")";
                return $val;
            });

            $doc = $tradR->getLicenseDocLists($req);
            $docVerifyStatus = $doc->original["data"]["docVerifyStatus"] ?? 0;
            return responseMsgs(true, ["docVerifyStatus" => $docVerifyStatus], remove_null($documents), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }


    /**
     * 
     */
    public function uploadDocument(Request $req)
    {
        try {
            $rules = [
                "applicationId" => "required|digits_between:1,9223372036854775807",
                "document" => "required|mimes:pdf,jpeg,png,jpg,gif",
                "docName" => "required",
                "docCode" => "required",
                "ownerId" => "nullable|digits_between:1,9223372036854775807"
            ];
            $validator = Validator::make($req->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), "");
            }

            $tradC = $this->_CONTROLLER_TRADE;
            $docUpload = new DocUpload;
            $mWfActiveDocument = new WfActiveDocument();
            $mActiveTradeLicence = $this->_MODEL_ActiveTradeLicence;
            $relativePath = $this->_TRADE_CONSTAINT["TRADE_RELATIVE_PATH"];
            $getLicenceDtls = $mActiveTradeLicence->getLicenceNo($req->applicationId);
            if (!$getLicenceDtls) {
                throw new Exception("Data Not Found!!!!!");
            }
            if ($getLicenceDtls->is_doc_verified) {
                throw new Exception("Document Are Verifed You Cant Reupload Documents");
            }
            $documents = $tradC->getLicenseDocLists($req);
            if (!$documents->original["status"]) {
                throw new Exception($documents->original["message"]);
            };
            $refUlbDtl      = UlbMaster::find($getLicenceDtls->ulb_id);
            $mShortUlbName  = $refUlbDtl->short_name ?? "";
            if (!$mShortUlbName) {
                foreach ((explode(' ', $refUlbDtl->ulb_name) ?? "") as $val) {
                    $mShortUlbName .= $val[0];
                }
            }
            // $relativePath = trim($relativePath."/".$mShortUlbName,"/");
            $applicationDoc = $documents->original["data"]["listDocs"];
            $applicationDocName = $applicationDoc->implode("docName", ",");
            $applicationDocCode = $applicationDoc->where("docName", $req->docName)->first();
            $applicationCode = $applicationDocCode ? $applicationDocCode["masters"]->implode("documentCode", ",") : "";
            // $mandetoryDoc = $applicationDoc->whereIn("docType",["R","OR"]);

            $ownerDoc = $documents->original["data"]["ownerDocs"];
            $ownerDocsName = $ownerDoc->map(function ($val) {
                $doc = $val["documents"]->map(function ($val1) {
                    return ["docType" => $val1["docType"], "docName" => $val1["docName"], "documentCode" => $val1["masters"]->implode("documentCode", ",")];
                });
                $ownereId = $val["ownerDetails"]["ownerId"];
                $docNames = $val["documents"]->implode("docName", ",");
                return ["ownereId" => $ownereId, "docNames" => $docNames, "doc" => $doc];
            });
            $ownerDocNames = $ownerDocsName->implode("docNames", ",");

            $ownerIds = $ownerDocsName->implode("ownereId", ",");
            $particuler = (collect($ownerDocsName)->where("ownereId", $req->ownerId)->values())->first();

            $ownereDocCode = $particuler ? collect($particuler["doc"])->where("docName", $req->docName)->all() : "";

            $particulerDocCode = collect($ownereDocCode)->implode("documentCode", ",");
            if (!(in_array($req->docName, explode(",", $applicationDocName)) == true || in_array($req->docName, explode(",", $ownerDocNames)) == true) && !in_array($req->docCode, ["OTHER DOC"])) {
                throw new Exception("Invalid Doc Name Pass");
            }
            if (in_array($req->docName, explode(",", $applicationDocName)) && (empty($applicationDocCode) || !(in_array($req->docCode, explode(",", $applicationCode))))) {
                throw new Exception("Invalid Application Doc Code Pass");
            }
            if (in_array($req->docName, explode(",", $ownerDocNames)) && (!(in_array($req->ownerId, explode(",", $ownerIds))))) {
                throw new Exception("Invalid ownerId Pass");
            }
            if (in_array($req->docName, explode(",", $ownerDocNames)) && ($ownereDocCode && !(in_array($req->docCode, explode(",", $particulerDocCode))))) {
                throw new Exception("Invalid Ownere Doc Code Pass");
            }

            $metaReqs = array();

            $refImageName = $req->docCode;
            $refImageName = $getLicenceDtls->id . '-' . str_replace(' ', '_', $refImageName);
            $document = $req->document;

            $imageName = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs['moduleId'] = $this->_MODULE_ID;
            $metaReqs['activeId'] = $getLicenceDtls->id;
            $metaReqs['workflowId'] = $getLicenceDtls->workflow_id;
            $metaReqs['ulbId'] = $getLicenceDtls->ulb_id;
            $metaReqs['relativePath'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['docCode'] = $req->docName; //$req->docCode;

            if (in_array($req->docName, explode(",", $ownerDocNames))) {
                $metaReqs['ownerDtlId'] = $req->ownerId;
            }
            $this->begin();
            #reupload documents;
            $sms = "";
            if ($privDoc = $mWfActiveDocument->getTradeAppByAppNoDocId($getLicenceDtls->id, $getLicenceDtls->ulb_id, collect($req->docName), $getLicenceDtls->workflow_id, $metaReqs['ownerDtlId'] ?? null)) {
                if ($privDoc->verify_status != 2) {
                    // dd("update");
                    $arr["verify_status"] = 0;
                    $arr['relative_path'] = $relativePath;
                    $arr['document'] = $imageName;
                    $arr['doc_code'] = $req->docName;
                    $arr['owner_dtl_id'] = $metaReqs['ownerDtlId'] ?? null;
                    $mWfActiveDocument->docVerifyReject($privDoc->id, $arr);
                } else {
                    // dd("reupload");
                    $mWfActiveDocument->docVerifyReject($privDoc->id, ["status" => 0]);
                    $metaReqs = new Request($metaReqs);
                    $metaReqs =  $mWfActiveDocument->metaReqs($metaReqs);
                    foreach ($metaReqs as $key => $val) {
                        $mWfActiveDocument->$key = $val;
                    }
                    $mWfActiveDocument->save();
                    // $mWfActiveDocument->postDocuments($metaReqs);
                }
                $sms = " Update Successful";
                // return responseMsgs(true, $req->docName . " Update Successful", "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
            } else {
                #new documents;
                $metaReqs = new Request($metaReqs);
                $metaReqs =  $mWfActiveDocument->metaReqs($metaReqs);
                foreach ($metaReqs as $key => $val) {
                    $mWfActiveDocument->$key = $val;
                }
                $mWfActiveDocument->save();
                // $mWfActiveDocument->postDocuments($metaReqs);
                $sms = " Uploadation Successful";
            }
            $this->commit();
            return responseMsgs(true,  $req->docName . $sms, "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    public function ReAmentSurrenderMetaList()
    {
        return $this->_DB->table("trade_licences")
            ->JOIN("trade_param_application_types", "trade_param_application_types.id", "trade_licences.application_type_id")
            ->join(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                                    STRING_AGG(email_id,',') AS email_id,
                                                    temp_id
                                                FROM trade_owners 
                                                WHERE is_active = TRUE
                                                GROUP BY temp_id
                                                )owner"), function ($join) {
                $join->on("owner.temp_id", "trade_licences.id");
            })
            ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "trade_licences.firm_type_id")
            ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "=", "trade_licences.ward_id")
            ->leftjoin("zone_masters", "zone_masters.id", "=", "trade_licences.zone_id")
            ->select(
                "trade_licences.id",
                "trade_licences.application_no",
                "trade_licences.provisional_license_no",
                "trade_licences.license_no",
                "trade_licences.document_upload_status",
                "trade_licences.payment_status",
                "trade_licences.firm_name",
                "trade_licences.apply_from",
                "trade_licences.workflow_id",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                "trade_licences.application_type_id",
                "trade_param_application_types.application_type",
                "trade_param_firm_types.firm_type",
                "trade_licences.firm_type_id",
                'ulb_ward_masters.ward_name',
                'zone_masters.zone_name',
                DB::raw("TO_CHAR(CAST(trade_licences.application_date AS DATE), 'DD-MM-YYYY') as application_date"),
            )
            ->where("trade_licences.payment_status", 1)
            ->where('trade_licences.application_type_id', '!=', 4)
            ->WHERE("trade_licences.is_active", TRUE);
    }

    public function ReAmentSurrenderList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "applicationType" => "required|string|in:" . collect(flipConstants($this->_TRADE_CONSTAINT["APPLICATION-TYPE"]))->implode(","),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 200);
        }
        try {
            $mApplicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType] ?? null;
            $respons = "";
            switch ($mApplicationTypeId) {
                case 2:
                    $respons = $this->CRenewalList($request);
                    break;
                case 3:
                    $respons = $this->CAmendmentList($request);
                    break;
                case 4:
                    $respons = $this->CSurrenderList($request);
                    break;
                default:
                    throw new Exception("Invalid Application Type Pass");
            }
            return $respons;
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }
    # Serial No
    public function CRenewalList(Request $request)
    {
        try {

            $refUserId = Auth()->user()->id;
            $mNextMonth = Carbon::now()->addMonths(1)->format('Y-m-d');
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);
            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            $data = $this->ReAmentSurrenderMetaList()
                ->where('trade_licences.valid_upto', '<', $mNextMonth)
                ->whereIn('trade_licences.ward_id', $mWardIds);
            if (trim($request->key)) {
                $key = trim($request->key);
                $data = $data->where(function ($query) use ($key) {
                    $query->orwhere('trade_licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('trade_licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("trade_licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("trade_licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), '');
        }
    }
    # Serial No
    public function CAmendmentList(Request $request)
    {
        try {
            $refUserId = Auth()->user()->id;
            $mNextMonth = Carbon::now()->format('Y-m-d');
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);
            $data = $this->ReAmentSurrenderMetaList()
                ->where('trade_licences.valid_upto', '>', $mNextMonth)
                ->whereIn('trade_licences.ward_id', $mWardIds);

            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), '');
        }
    }

    # Serial No
    public function CSurrenderList(Request $request)
    {
        try {
            $refUserId = Auth()->user()->id;
            $mNextMonth = Carbon::now()->format('Y-m-d');
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);

            $data = $this->ReAmentSurrenderMetaList()
                ->where('trade_licences.valid_upto', '>', $mNextMonth)
                ->whereIn('trade_licences.ward_id', $mWardIds);

            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), '');
        }
    }

    public function editOldApp(ApplicationId $request)
    {
        $db_connection_name = ((new ActiveTradeLicence())->getConnectionName());
        $mFramNameRegex = "/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i";
        $mOwnerName = "/^([a-zA-Z0-9]+)(\s[a-zA-Z0-9\.\,\']+)*$/i";
        $mMobileNo  = "/[0-9]{10}/";
        $rules = [
            "validFrom"             => "required|date|date_format:Y-m-d",
            "validUpto"             => "required|date|date_format:Y-m-d|after:" . $request->validFrom,
            "firmEstdDate"          => "required|date|before_or_equal:" . $request->validFrom,
            "zoneId"                => "required|digits_between:1,9223372036854775807",
            "wardNo"                => "required|digits_between:1,9223372036854775807",
            "firmName"              => "required|regex:$mFramNameRegex",
            "firmType"              => "required|digits_between:1,9223372036854775807",
            "ownershipType"         => "required|digits_between:1,9223372036854775807",
            "renewalHistory"        => "nullable|array",
            "renewalHistory.*.validFrom"  => "required|date|date_format:Y-m-d|after_or_equal:" . $request->validUpto,
            "renewalHistory.*.validUpto"  => "required|date|date_format:Y-m-d|after_or_equal:" . $request->validUpto,
            "renewalHistory.*.applicationNo"  => "required",
        ];
        if (is_array($request->renewalHistory) && $request->renewalHistory) {
            $rules["renewalHistory.0.validFrom"] = "required|date|date_format:Y-m-d|after_or_equal:" . $request->validUpto;
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 200);
        }
        try {
            $valid_from = $request->validFrom;
            $valid_upto = $request->validUpto;
            foreach (collect($request->renewalHistory) as $val) {
                if (Carbon::parse($valid_upto)->greaterThan(Carbon::parse($val["validFrom"]))) {
                    throw new Exception("application no (" . $val["applicationNo"] . ") valid from can not less than priveuse application valid upto (" . $valid_upto . ")");
                }
                if (Carbon::parse($val["validFrom"])->greaterThan(Carbon::parse($val["validUpto"]))) {
                    throw new Exception("application no (" . $val["applicationNo"] . ") valid from can not grater than valid upto(" . $val["validUpto"] . ")");
                }
                $valid_upto = $val["validUpto"];
            }
            $appId = $request->applicationId;
            $oldData = TradeLicence::find($appId);
            if (!$oldData) {
                throw new Exception("Data not found");
            }
            if ($oldData->update_counter > 1) {
                throw new Exception("You Have Already Exceed The Updation Limit");
            }
            if (strtoupper($oldData->apply_from)    != strtoupper("Existing")) {
                throw new Exception("this is new application. You can not update form hear");
            }
            // $owners = $oldData->owneres()->where("is_active",true)->get();
            $request->merge(["ulbId" => $oldData->ulb_id]);
            $validFrom = $request->validFrom ? $request->validFrom : $oldData->valid_from;
            $validUpto = $request->validUpto ? $request->validUpto : $oldData->valid_upto;
            $yearDiff = (int)round(Carbon::parse($validFrom)->diffInDays(Carbon::parse($validUpto)) / 365);
            $this->begin();
            #application update 
            $oldData->valid_from            = $validFrom;
            $oldData->valid_upto            = $validUpto;
            $oldData->zone_id               = $request->zoneId ? $request->zoneId : $oldData->zone_id;
            $oldData->ward_id               = $request->wardNo ? $request->wardNo : $oldData->ward_id;
            $oldData->establishment_date    = $request->firmEstdDate ? $request->firmEstdDate : $oldData->establishment_date;
            $oldData->firm_name             = $request->firmName ? $request->firmName : $oldData->firm_name;
            $oldData->firm_name_marathi     = $request->firmNameMarathi ? $request->firmNameMarathi : $oldData->firm_name_marathi;
            $oldData->firm_type_id          = $request->firmType ? $request->firmType : $oldData->firm_type_id;
            $oldData->ownership_type_id     = $request->ownershipType ? $request->ownershipType : $oldData->ownership_type_id;
            $oldData->licence_for_years     = $yearDiff;
            $oldData->update_counter += 1;
            $oldData->update();
            $parentId = $this->insertHistory($oldData, $request);
            $this->commit();
            $message = "Application Updated " . trim(getNumberToSentence($oldData->update_counter)) . ($oldData->update_counter > 99 ? " times" : " time");
            return responseMsg(true, $message, '');
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), '');
        }
    }

    private function insertHistory(TradeLicence $oldData, Request $request)
    {

        $parentId = $oldData->id;
        $tradId = $oldData->id;
        $history = collect();
        $owners = $oldData->owneres()->where("is_active", true)->get();
        $apliedApplication = $oldData->getPendingApplication()->first();
        foreach (collect($request->renewalHistory) as $key => $val) {

            $yearDiffh = (int)round(Carbon::parse($val["validFrom"])->diffInDays(Carbon::parse($val["validUpto"])) / 365);
            $Obj  = new ActiveTradeLicence();
            $newObj = $oldData->replicate();
            $newObj->valid_from = $val["validFrom"];
            $newObj->valid_upto = $val["validUpto"];
            $newObj->license_date = $val["validFrom"];
            $newObj->application_date = $val["validFrom"];
            $newObj->application_no = $val["applicationNo"];
            $newObj->application_type_id = 2;
            $newObj->parent_ids = trim($parentId, ',');
            $newObj->trade_id = $tradId;
            $newObj->licence_for_years     = $yearDiffh;

            $newObj->setTable($Obj->getTable());
            $newObj->save();

            $parentId = $newObj->id . "," . $parentId;
            $tradId = $newObj->id;

            if ($key < (collect($request->renewalHistory)->count() - 1)) {
                $reniwal = $newObj->replicate();
                $reniwal->setTable('trade_renewals');
                $reniwal->id = $newObj->id;
                $reniwal->is_active = true;
                $reniwal->save();
                $history->push($reniwal);
            }
            if ($key == (collect($request->renewalHistory)->count() - 1)) {
                $las = $newObj->replicate();
                $las->setTable($oldData->getTable());
                $las->id = $newObj->id;
                $las->save();

                $las2 = $oldData->replicate();
                $las2->setTable("trade_renewals");
                $las2->id = $oldData->id;
                $las2->is_active = true;
                $las2->save();

                $oldData->forceDelete();
                $history->push($newObj);
            }
            $ownerObj = new ActiveTradeOwner();
            $owners->map(function ($owner) use ($ownerObj, $tradId) {
                $newOwner = $owner->replicate();
                $newOwner->setTable($ownerObj->getTable());
                $newOwner->temp_id = $tradId;
                $newOwner->save();
                $newOwner->forceDelete();
                $newOwner2 = $newOwner->replicate();
                $newOwner2->setTable($owner->getTable());
                $newOwner2->id = $newOwner->id;
                $newOwner2->save();
            });
            $newObj->forceDelete();
        }
        if (collect($request->renewalHistory)->isNotEmpty() && $apliedApplication) {
            $apliedApplication->trade_id = $tradId;
            $apliedApplication->parent_ids = trim($parentId, ',');
            $apliedApplication->update();
        }
        return $parentId;
    }

    public function updateLicenseCounter(Request $request)
    {
        $id = $request->id;
        $request->setMethod('POST');
        $request->request->add(["id" => $id]);
        $rules = [
            "id" => "required|digits_between:1,9223372036854775807",
        ];
        $validator = Validator::make($request->all(), $rules,);
        if ($validator->fails()) {
            return responseMsg(false, $validator->errors(), "");
        }
        return $this->_REPOSITORY->updateLicenseCounter($request->id);
    }

    public function sendSms(Request $request)
    {
        try {
            $currentDate = Carbon::now();
            $oneMonthLater = $currentDate->copy()->addMonth();
            $mTradeLicence = new TradeLicence();
            // Get the details of licenses expiring within one month
            // $details = $mTradeLicence->dtls()
            //     ->where('valid_upto', '>', $currentDate)
            //     ->where('valid_upto', '<=', $oneMonthLater)
            //     ->get();
            $detail = 'https://vm.ltd/AKOLAM/hGbyXn';

            // foreach ($details as $detail) {
            // $sms = AkolaTrade(["owner_name" => $detail->license_no, "application_no" => $detail->valid_upto], "Renewal");
            $sms = AkolaTrade(["url" => $detail], "Renewal");

            if ($sms["status"] !== false) {
                $respons = SMSAKGOVT(8092343431, $sms["sms"], $sms["temp_id"]);
            }
            // }
        } catch (\Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }
}
