<?php

namespace App\Http\Controllers\Property;

use App\EloquentModels\Common\ModelWard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\PropertyDeactivation\reqDeactivatProperty;
use App\Http\Requests\Property\PropertyDeactivation\reqPostNext;
use App\Http\Requests\Property\PropertyDeactivation\reqReadProperty;
use App\Models\Property\PropActiveDeactivationRequest;
use App\Models\Property\PropDeactivationRequest;
use App\Models\Property\PropProperty;
use App\Models\Property\PropRejectedDeactivationRequest;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Concrete\PropertyDeactivate;
use App\Repository\Property\Interfaces\IPropertyDeactivate;
use App\Repository\Property\Interfaces\iSafRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PropertyDeactivateController extends Controller
{
    /**
     * | Created On-19-11-2022 
     * | Created By-Sandeep Bara
     * --------------------------------------------------------------------------------------
     * | Controller regarding with Propery Module (Property Deactivation)
     * | Status-Open
     */
    private $Repository;
    private $saf_repository;
    protected $_common;
    protected $_modelWard;
    protected $_track;

    protected $_REPOSITORY;
    protected $_SAF_REPOSITORY;
    protected $_COMMON_FUNCTION;
    protected $_PROPERTY_CONSTAINT;
    protected $_MODEL_WARD;
    protected $_WF_MASTER_ID;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_DOC_PATH;
    protected $_MODULE_CONSTAINT;
    protected $_WORKFLOW_TRACK;
    protected $_APPLICATION_NO_CONST;

    public function __construct(IPropertyDeactivate $PropertyDeactivate, iSafRepository $saf_repository)
    {
        $this->_REPOSITORY = $PropertyDeactivate;
        $this->_SAF_REPOSITORY = new ActiveSafController($saf_repository);
        $this->_COMMON_FUNCTION = new CommonFunction();
        $this->_MODEL_WARD = new ModelWard();
        $this->_WORKFLOW_TRACK = new WorkflowTrack();
        $this->_WF_MASTER_ID = Config::get('workflow-constants.PROPERTY_DEACTIVATION_MASTER_ID');
        $this->_MODULE_CONSTAINT = Config::get('module-constants');
        $this->_PROPERTY_CONSTAINT = Config::get("PropertyConstaint");
        $this->_APPLICATION_NO_CONST =  $this->_PROPERTY_CONSTAINT["DEACTIV_PARAM_ID"] ?? 0;
        $this->_MODULE_ID = $this->_PROPERTY_CONSTAINT["PROPERTY_MODULE_ID"] ?? NULL;
        $this->_REF_TABLE = null;
        $this->_DOC_PATH = null;
    }
    public function readHoldigbyNo(Request $request)
    {
        return $this->_REPOSITORY->readHoldigbyNo($request);
    }
    public function readPorertyById(reqReadProperty $request)
    {
        try {
            $mProperty = $this->_SAF_REPOSITORY->getPropByHoldingNo($request);
            if (!$mProperty->original['status']) {
                throw new Exception($mProperty->original['message']);
            }
            if ($mProperty->original['data']['status'] != 1) {
                throw new Exception("Property Alerady Deactivated");
            }
            $deactivationStatus = 0;
            $PropDeactivationRequest    = PropActiveDeactivationRequest::select("*")
                ->where("property_id", $request->propertyId)
                ->where("status", 1)
                ->orderBy("id", "DESC")
                ->first();
            if ($PropDeactivationRequest) {
                $deactivationStatus = 1;
                // throw new Exception("Request is already submited. Please check request status...!");
            }
            $mProperty->original['data']['deactivationStatus'] = $deactivationStatus;
            return responseMsgs(true, $mProperty->original['message'], $mProperty->original['data'], "00001", "1.0", "", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), "00001", "1.0", "", "POST", $request->deviceId);
        }
    }
    public function deactivatProperty(reqDeactivatProperty $request)
    {
        try {
            $PropDeactivationRequest    = PropDeactivationRequest::select("*")
                ->where("property_id", $request->propertyId)
                ->where("status", 1)
                ->orderBy("id", "DESC")
                ->first();
            if ($PropDeactivationRequest) {
                throw new Exception("Request is already submited. Please check request status with APPN - $PropDeactivationRequest->application_no !....");
            }
            return $this->_REPOSITORY->deactivatProperty($request);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), "00002", "1.0", "", "POST", $request->deviceId);
        }
    }
    public function inbox(Request $request)
    {
        return $this->_REPOSITORY->inbox($request);
    }

    public function outbox(Request $request)
    {
        return $this->_REPOSITORY->outbox($request);
    }

    public function specialInbox(Request $request)
    {
        return $this->_REPOSITORY->specialInbox($request);
    }

    public function postNextLevel(reqPostNext $request)
    {
        try {
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $refWorkflowId = Config::get('workflow-constants.PROPERTY_DEACTIVATION_WORKFLOW_ID');
            $workflowId = WfWorkflow::where('id', $refWorkflowId)
                ->where('ulb_id', $ulb_id)
                ->first();
            if (!$workflowId) {
                throw new Exception("Workflow Not Available");
            }
            $refDeactivationReq = PropActiveDeactivationRequest::find($request->applicationId);
            $role = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id, $refWorkflowId);
            $init_finish = $this->_COMMON_FUNCTION->iniatorFinisher($user_id, $ulb_id, $refWorkflowId);
            if (!$refDeactivationReq) {
                throw new Exception("Data Not Found");
            }
            if ($refDeactivationReq->pending_status == 5) {
                throw new Exception("Deactivation Request Is Already Approved");
            }
            if ($refDeactivationReq->current_role != $role->role_id) {
                throw new Exception("You are not authorised for this action");
            }
            if (!$init_finish) {
                throw new Exception("Full Work Flow Not Desigen Properly. Please Contact Admin !!!...");
            }
            if (!$init_finish["initiator"]) {
                throw new Exception("Initiar Not Available. Please Contact Admin !!!...");
            }
            if (!$init_finish["finisher"]) {
                throw new Exception("Finisher Not Available. Please Contact Admin !!!...");
            }
            $allRolse = collect($this->_COMMON_FUNCTION->getAllRoles($user_id, $ulb_id, $refWorkflowId, 0, true));
            $receiverRole = array_values(objToArray($allRolse->where("id", $request->receiverRoleId)))[0] ?? [];

            $sms = "Application BackWord To " . $receiverRole["role_name"] ?? "";
            if ($refDeactivationReq->max_level_attained < ($receiverRole["serial_no"] ?? 0)) {
                $sms = "Application Forward To " . $receiverRole["role_name"] ?? "";
            }
            DB::beginTransaction();
            if ($refDeactivationReq->max_level_attained < $receiverRole["serial_no"] ?? 0) {
                $refDeactivationReq->max_level_attained = $receiverRole["serial_no"];
                $refDeactivationReq->current_role = $request->receiverRoleId;
                $refDeactivationReq->update();
            }
            $myRequest = new \Illuminate\Http\Request();
            $myRequest->setMethod($request->getMethod());
            foreach ($request->all() as $key2 => $val2) {
                $myRequest->request->add([$key2 => $val2]);
            }
            $metaReqs['moduleId'] = $this->_MODULE_ID;
            $metaReqs['workflowId'] = $refWorkflowId;
            $metaReqs['refTableDotId'] = 'prop_active_deactivation_requests';
            $metaReqs['refTableIdValue'] = $request->applicationId;
            $myRequest->request->add($metaReqs);
            $this->_WORKFLOW_TRACK->saveTrack($myRequest);

            DB::commit();

            return responseMsgs(true, $sms, "", "00003", "1.0", "", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), "00003", "1.0", "", "POST", $request->deviceId);
        }
    }

    public function approvalRejection(Request $request)
    {
        $request->validate([
            'roleId' => 'required|integer',
            'applicationId' => 'required|integer',
            'status' => 'required|integer'
        ]);

        try {
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            // Check if the Current User is Finisher or Not
            $refDeactivationReq = PropActiveDeactivationRequest::find($request->applicationId);
            if (!$refDeactivationReq) {
                throw new Exception("Data NOt Found!......");
            }
            // dd($refDeactivationReq);
            if ($refDeactivationReq->finisher_role != $request->roleId) {
                throw new Exception("Forbidden Access");
            }

            $PropProperty = PropProperty::find($refDeactivationReq->property_id);
            if (!$PropProperty) {
                throw new Exception("Property Not Found!..........");
            }
            DB::beginTransaction();
            if ($request->status == 1) {
                $verifired = new PropDeactivationRequest();
                $this->transeferData($verifired, $refDeactivationReq);
                $verifired->status = 5;
                $verifired->approve_date = Carbon::now()->formate('Y-m-d');
                $verifired->approve_by = $user_id;
                $PropProperty->status = 2;
                $PropProperty->update();
                $msg = "Property Deactivated Successfully !! Holding No " . $PropProperty->holding_no;
            }
            // Rejection
            if ($request->status == 0) {
                $verifired = new PropRejectedDeactivationRequest();
                $this->transeferData($verifired, $refDeactivationReq);
                $verifired->status = 0;
                $verifired->approve_date = Carbon::now()->formate('Y-m-d');
                $verifired->approve_by = $user_id;
                $msg = "Application Rejected Successfully";
            }
            $verifired->save();
            $refDeactivationReq->forceDelete();
            DB::commit();
            return responseMsgs(true, $msg, [], "00004", "1.0", "410ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    private function transeferData($targerModel, $sorseModel)
    {
        $targerModel->id             = $sorseModel->id;
        $targerModel->ulb_id         = $sorseModel->ulb_id;
        $targerModel->property_id    = $sorseModel->property_id;
        $targerModel->apply_date     = $sorseModel->apply_date;
        $targerModel->emp_detail_id  = $sorseModel->emp_detail_id;
        $targerModel->remarks        = $sorseModel->remarks;
        $targerModel->documents      = $sorseModel->documents;
        $targerModel->workflow_id    = $sorseModel->workflow_id;
        $targerModel->max_level_attained = $sorseModel->max_level_attained;
        $targerModel->is_parked      = $sorseModel->is_parked;
        $targerModel->current_role   = $sorseModel->current_role;
        $targerModel->initiator_role = $sorseModel->initiator_role;
        $targerModel->finisher_role  = $sorseModel->finisher_role;
        $targerModel->is_escalate    = $sorseModel->is_escalate;
        $targerModel->escalate_by    = $sorseModel->escalate_by;
        $targerModel->create_at      = $sorseModel->create_at;
        $targerModel->create_at      = $sorseModel->create_at;
    }
    public function readDeactivationReq(Request $request)
    {
        return $this->_REPOSITORY->readDeactivationReq($request);
    }
    public function commentIndependent(Request $request)
    {
        $request->validate([
            'comment' => 'required|min:10|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/',
            'applicationId' => 'required|digits_between:1,9223372036854775807',
            'senderRoleId' => 'nullable|integer'
        ]);

        try {
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $refDeactivationReq = PropActiveDeactivationRequest::find($request->applicationId);                // SAF Details
            $mModuleId = $this->_MODULE_ID;
            $metaReqs = array();
            DB::beginTransaction();
            // Save On Workflow Track For Level Independent
            $metaReqs = [
                'workflowId' => $refDeactivationReq->workflow_id,
                'moduleId' => $mModuleId,
                'refTableDotId' => "prop_active_deactivation_requests",
                'refTableIdValue' => $refDeactivationReq->id,
                'message' => $request->comment
            ];
            // For Citizen Independent Comment
            $metaReqs = array_merge($metaReqs, ['citizenId' => $user_id]);

            $request->request->add($metaReqs);
            $this->_WORKFLOW_TRACK->saveTrack($request);
            DB::commit();
            return responseMsgs(true, "You Have Commented Successfully!!", ['Comment' => $request->comment], "00006", "1.0", "", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function postEscalate(Request $request)
    {
        $request->validate([
            "escalateStatus" => "required|int",
            "applicationId" => "required|digits_between:1,9223372036854775807",
        ]);
        try {
            $userId = Auth()->user()->id;
            $refDeactivationReq = PropActiveDeactivationRequest::find($request->applicationId);
            $refDeactivationReq->is_escalate = $request->escalateStatus <= 0 ? true : false;
            $refDeactivationReq->escalate_by = $userId;
            $refDeactivationReq->save();
            return responseMsgs(true, $request->escalateStatus <= 0 ? "Data is removed from Escalated" : 'Data is Escalated', '', "00007", "1.0", "353ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    public function getUplodedDocuments(Request $request)
    {
        $request->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807',
        ]);
        try {
            $refDeactivationReq = PropActiveDeactivationRequest::find($request->applicationId);
            if (!$refDeactivationReq) {
                throw new Exception("Data Not Found!.......");
            }
            $docpath = !empty(trim($refDeactivationReq->documents)) ? $this->_REPOSITORY->readDocumentPath($refDeactivationReq->documents) : "";

            return responseMsgs(true, "Document Fetched", $docpath, "00008", "1.0", "", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
}
