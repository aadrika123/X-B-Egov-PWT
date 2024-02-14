<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\MicroServices\DocUpload;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\CustomDetail;
use App\Models\Masters\RefRequiredDocument;
use App\Models\Property\PropActiveConcession;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Pipelines\ConcessionInbox\ConcessionByApplicationNo;
use App\Pipelines\ConcessionInbox\ConcessionByName;
use App\Repository\Property\Concrete\PropertyBifurcation;
use App\Repository\Property\Interfaces\iConcessionRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use Illuminate\Http\Request;
use App\Traits\Workflow\Workflow as WorkflowTrait;
use App\Traits\Property\Concession;
use App\Traits\Property\SafDetailsTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Exception;
use Illuminate\Pipeline\Pipeline;

/**
 * | Created On-15-11-2022 
 * | Created By-Mrinal Kumar
 * --------------------------------------------------------------------------------------
 * | Controller for Concession
 * | --------------------------- Workflow Parameters ---------------------------------------
 * | Concession Master ID   = 35                
 * */


class ConcessionController extends Controller
{
    use WorkflowTrait;
    use Concession;
    use SafDetailsTrait;

    private $_todayDate;
    private $_bifuraction;
    private $_workflowId;

    protected $concession_repository;
    protected $Repository;
    public function __construct(iConcessionRepository $concession_repository)
    {
        $this->Repository = $concession_repository;
        $this->_todayDate = Carbon::now();
        $this->_bifuraction = new PropertyBifurcation();
        $this->_workflowId = Config::get('workflow-constants.PROPERTY_CONCESSION_ID');
    }


    /**
     * | Query Costing-464ms 
     * | Rating-3
     * | Status-Closed
     */
    public function applyConcession(Request $request)
    {
        // return $request;

        $request->validate([
            'propId' => "required",
            "applicantName" => "required"
        ]);

        try {
            $user = authUser($request);
            $userType = $user->user_type;
            $userId = $user->id;
            $track = new WorkflowTrack();
            $mPropProperty = new PropProperty();
            $conParamId = Config::get('PropertyConstaint.CON_PARAM_ID');
            $concessionData = $request->concessionData;
            $propDtl = $mPropProperty->getPropById($request->propId);
            $ulbId = $propDtl->ulb_id;
            $concessionNo = "";

            $ulbWorkflowId = WfWorkflow::where('wf_master_id', $this->_workflowId)
                ->where('ulb_id', $ulbId)
                ->first();

            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);                // Get Current Initiator ID
            $initiatorRoleId = DB::select($refInitiatorRoleId);

            $refFinisherRoleId = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId = DB::select($refFinisherRoleId);

            DB::beginTransaction();
            $concession = new PropActiveConcession;
            $concession->property_id = $request->propId;
            $concession->prop_owner_id = $request->ownerId;
            $concession->applicant_name = strtoupper($request->applicantName);
            $concession->ulb_id = $ulbId;
            $concession->workflow_id = $ulbWorkflowId->id;
            $concession->current_role = collect($initiatorRoleId)->first()->role_id;
            $concession->initiator_role_id = collect($initiatorRoleId)->first()->role_id;
            $concession->last_role_id = collect($initiatorRoleId)->first()->role_id;
            $concession->user_id = $userId;
            $concession->remarks = $request->remarks;

            if ($userType == 'Citizen') {
                $concession->current_role = collect($initiatorRoleId)->first()->forward_role_id;
                $concession->initiator_role_id = collect($initiatorRoleId)->first()->forward_role_id;      // Send to DA in Case of Citizen
                $concession->last_role_id = collect($initiatorRoleId)->first()->forward_role_id;
                $concession->user_id = null;
                $concession->citizen_id = $userId;
                $concession->doc_upload_status = 1;
            }
            $concession->finisher_role_id = collect($finisherRoleId)->first()->role_id;
            $concession->date = Carbon::now();

            $appliedFor = collect($concessionData)->pluck('appliedFor');
            $concession->applied_for = $appliedFor->implode(',', $appliedFor);
            $concession->save();

            collect($concessionData)->map(function ($item) use ($concession) {
                if ($item['appliedFor'] == 'Gender')
                    $this->saveGenderData($item, $concession);
                if ($item['appliedFor'] == 'Senior Citizen')
                    $this->saveSeniorCitizenData($item, $concession);
                if ($item['appliedFor'] == 'Specially Abled')
                    $this->saveSpeciallyAbledData($item, $concession);
                if ($item['appliedFor'] == 'Armed Force')
                    $this->saveArmedForceData($item, $concession);
            });

            $wfReqs['workflowId'] = $ulbWorkflowId->id;
            $wfReqs['refTableDotId'] = 'prop_active_concessions.id';
            $wfReqs['refTableIdValue'] = $concession->id;
            $wfReqs['user_id'] = $userId;
            if ($userType == 'Citizen') {
                $wfReqs['citizenId'] = $userId;
                $wfReqs['user_id'] = NULL;
                $wfReqs['ulb_id'] = $concession->ulb_id;
            }
            $wfReqs['receiverRoleId'] = $concession->current_role;
            $wfReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $request->request->add($wfReqs);
            $track->saveTrack($request);

            //concession number through id generation
            $idGeneration = new PrefixIdGenerator($conParamId, $concession->ulb_id);
            $concessionNo = $idGeneration->generate();

            PropActiveConcession::where('id', $concession->id)
                ->update(['application_no' => $concessionNo]);

            // Armed force Doc
            // if ($file = $request->file('armedForceDoc')) {

            //     $docUpload = new DocUpload;
            //     $mWfActiveDocument = new WfActiveDocument();
            //     $relativePath = Config::get('PropertyConstaint.CONCESSION_RELATIVE_PATH');
            //     $refImageName = $request->armedForceCode;
            //     $refImageName = $concession->id . '-' . str_replace(' ', '_', $refImageName);
            //     $document = $request->armedForceDoc;

            //     $imageName = $docUpload->upload($refImageName, $document, $relativePath);
            //     $armedForceReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            //     $armedForceReqs['activeId'] = $concession->id;
            //     $armedForceReqs['workflowId'] = $concession->workflow_id;
            //     $armedForceReqs['ulbId'] = $concession->ulb_id;
            //     $armedForceReqs['relativePath'] = $relativePath;
            //     $armedForceReqs['document'] = $imageName;
            //     $armedForceReqs['docCode'] = $request->armedForceCode;

            //     $armedForceReqs = new Request($armedForceReqs);
            //     $mWfActiveDocument->postDocuments($armedForceReqs);

            //     PropActiveConcession::where('id', $concession->id)
            //         ->update(['doc_upload_status' => 1]);
            // }

            DB::commit();
            return responseMsgs(true, 'Successfully Applied The Application', $concessionNo, '010701', '01', '382ms-547ms', 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * |
     */
    public function saveGenderData($req, $concession)
    {
        PropActiveConcession::where('id', $concession->id)
            ->update(['gender' => $req['value']]);
        // $concession->gender = $req['value'];
        $this->saveDoc($req, $concession);
    }

    /**
     * |
     */
    public function saveSeniorCitizenData($req, $concession)
    {
        PropActiveConcession::where('id', $concession->id)
            ->update(['dob' => $req['value']]);
        // $concession->dob = $req['value'];
        $this->saveDoc($req, $concession);
    }

    /**
     * |
     */
    public function saveSpeciallyAbledData($req, $concession)
    {
        PropActiveConcession::where('id', $concession->id)
            ->update([
                'is_specially_abled' => $req['value'],
                'specially_abled_percentage' => $req['percentage'],
            ]);
        // $concession->is_specially_abled = $req['value'];
        // $concession->specially_abled_percentage = $req['percentage'];
        $this->saveDoc($req, $concession);
    }

    /**
     * |
     */
    public function saveArmedForceData($req, $concession)
    {
        PropActiveConcession::where('id', $concession->id)
            ->update([
                'is_armed_force' => $req['value'],
            ]);
        // $concession->is_armed_force = $req['value'];
        $this->saveDoc($req, $concession);
    }

    public function saveDoc($request, $concession)
    {
        $docUpload = new DocUpload;
        $mWfActiveDocument = new WfActiveDocument();
        $relativePath = Config::get('PropertyConstaint.CONCESSION_RELATIVE_PATH');
        $refImageName = $request['code'];
        $refImageName = $concession->id . '-' . str_replace(' ', '_', $refImageName);
        $document = $request['doc'];

        $imageName = $docUpload->upload($refImageName, $document, $relativePath);
        $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
        $metaReqs['activeId'] = $concession->id;
        $metaReqs['workflowId'] = $concession->workflow_id;
        $metaReqs['ulbId'] = $concession->ulb_id;
        $metaReqs['document'] = $imageName;
        $metaReqs['relativePath'] = $relativePath;
        $metaReqs['docCode'] = $request['code'];

        $metaReqs = new Request($metaReqs);
        $mWfActiveDocument->postDocuments($metaReqs);
    }

    //post Holding
    public function postHolding(Request $request)
    {
        $request->validate([
            'holdingNo' => 'required'
        ]);
        try {
            $user = PropProperty::where('holding_no', $request->holdingNo)
                ->get();
            if (!empty($user['0'])) {
                return responseMsgs(true, 'True', $user, '010702', '01', '334ms-401ms', 'Post', '');
            }
            return responseMsg(false, "False", "");
            // return $user['0'];
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Property Concession Inbox List
     * | @var auth autheticated user data
     * | Query Costing-293ms 
     * | Rating-3
     * | Status-Closed
     */
    public function inbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $userId = authUser($req)->id;
            $ulbId  = authUser($req)->ulb_id;
            $occupiedWards = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');                       // Model () to get Occupied Wards of Current User

            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');                      // Model to () get Role By User Id
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $concessions = $this->getConcessionList($workflowIds)
                ->where('prop_active_concessions.ulb_id', $ulbId)
                ->whereIn('prop_active_concessions.current_role', $roleIds)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_concessions.id');

            $inboxList = app(Pipeline::class)
                ->send(
                    $concessions
                )
                ->through([
                    ConcessionByApplicationNo::class,
                    ConcessionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Inbox List", remove_null($inboxList), '010703', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Outbox List
     * | @var auth authenticated user list
     * | @var ulbId authenticated user ulb
     * | @var userid authenticated user id
     * | Query Costing-309 
     * | Rating-3
     * | Status-Closed
     */
    public function outbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $occupiedWards = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');                       // Model () to get Occupied Wards of Current User

            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');                      // Model to () get Role By User Id
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $concessions = $this->getConcessionList($workflowIds)
                ->where('prop_active_concessions.ulb_id', $ulbId)
                ->whereNotIn('prop_active_concessions.current_role', $roleIds)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_concessions.id');

            $inboxList = app(Pipeline::class)
                ->send(
                    $concessions
                )
                ->through([
                    ConcessionByApplicationNo::class,
                    ConcessionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Outbox List", remove_null($inboxList), '010704', '01', '355ms-419ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // Get Concession Details by ID
    public function getDetailsById(Request $req)
    {
        $req->validate([
            'applicationId' => 'required'
        ]);

        try {
            $details = array();
            $mPropActiveConcession = new PropActiveConcession();
            $mPropOwners = new PropOwner();
            $mPropFloors = new PropFloor();
            $mWorkflowTracks = new WorkflowTrack();
            $mCustomDetails = new CustomDetail();
            $mForwardBackward = new WorkflowMap();
            $mRefTable = Config::get('PropertyConstaint.SAF_CONCESSION_REF_TABLE');
            $details = $mPropActiveConcession->getDetailsById($req->applicationId);

            if (!$details)
                throw new Exception("Application Not Found for this id");

            // Data Array
            $basicDetails = $this->generateBasicDetails($details);         // (Basic Details) Trait function to get Basic Details
            $basicElement = [
                'headerTitle' => "Basic Details",
                "data" => $basicDetails
            ];

            $propertyDetails = $this->generatePropertyDetails($details);   // (Property Details) Trait function to get Property Details
            $propertyElement = [
                'headerTitle' => "Property Details & Address",
                'data' => $propertyDetails
            ];

            $fullDetailsData['application_no'] = $details->application_no;
            $fullDetailsData['apply_date'] = $details->date;
            $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$basicElement, $propertyElement]);

            // Table Array
            $ownerList = $mPropOwners->getOwnersByPropId($details->property_id);
            $ownerList = json_decode(json_encode($ownerList), true);       // Convert Std class to array
            $ownerDetails = $this->generateOwnerDetails($ownerList);
            $ownerElement = [
                'headerTitle' => 'Owner Details',
                'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                'tableData' => $ownerDetails
            ];

            $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerElement]);
            // Card Details
            $cardElement = $this->generateConcessionCardDtls($details, $ownerList);
            $fullDetailsData['fullDetailsData']['cardArray'] = $cardElement;

            $levelComment = $mWorkflowTracks->getTracksByRefId($mRefTable, $req->applicationId);
            $fullDetailsData['levelComment'] = $levelComment;

            $citizenComment = $mWorkflowTracks->getCitizenTracks($mRefTable, $req->applicationId, $details->user_id);
            $fullDetailsData['citizenComment'] = $citizenComment;

            $metaReqs['customFor'] = 'PROPERTY-CONCESSION';
            $metaReqs['wfRoleId'] = $details->current_role;
            $metaReqs['workflowId'] = $details->workflow_id;
            $metaReqs['lastRoleId'] = $details->last_role_id;
            $req->request->add($metaReqs);

            $forwardBackward = $mForwardBackward->getRoleDetails($req);
            $fullDetailsData['roleDetails'] = collect($forwardBackward)['original']['data'];

            $fullDetailsData['timelineData'] = collect($req);

            $custom = $mCustomDetails->getCustomDetails($req);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];

            return responseMsgs(true, "Concession Details", remove_null($fullDetailsData), '010705', '01', '', 'POST', '');
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '010705', '01', '', 'POST', '');
        }
    }

    /**
     * | Escalate application
     * | @param req request parameters
     * | Query Costing-400ms 
     * | Rating-2
     * | Status-Closed
     */
    public function escalateApplication(Request $req)
    {
        try {
            $req->validate([
                'id' => 'required',
                'escalateStatus' => 'required'
            ]);

            $escalate = new PropActiveConcession();
            $msg = $escalate->escalate($req);

            return responseMsgs(true, $msg, "", '010706', '01', '400ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Special Inbox (Escalated Applications)
     * | Query Costing-303 ms 
     * | Rating-2
     * | Status-Closed
     */
    public function specialInbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $occupiedWards = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');                       // Model () to get Occupied Wards of Current User

            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');                      // Model to () get Role By User Id
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $concessions = $this->getConcessionList($workflowIds)                                        // Get Concessions
                ->where('prop_active_concessions.ulb_id', $ulbId)
                ->where('prop_active_concessions.is_escalate', true)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_concessions.id');

            $inboxList = app(Pipeline::class)
                ->send(
                    $concessions
                )
                ->through([
                    ConcessionByApplicationNo::class,
                    ConcessionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsg(true, "Inbox List", remove_null($inboxList), "", '010707', '01', '303ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Back To Citizen Inbox
     */
    public function btcInbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $occupiedWards = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');                       // Model () to get Occupied Wards of Current User

            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');                      // Model to () get Role By User Id
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $concessions = $this->getConcessionList($workflowIds)                                        // Get Concessions
                ->where('prop_active_concessions.ulb_id', $ulbId)
                ->whereIn('prop_active_concessions.current_role', $roleIds)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->where('parked', true)
                ->orderByDesc('prop_active_concessions.id');

            $inboxList = app(Pipeline::class)
                ->send(
                    $concessions
                )
                ->through([
                    ConcessionByApplicationNo::class,
                    ConcessionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "BTC Inbox List", remove_null($inboxList), 010717, 1.0, "271ms", "POST", "", "");;
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010717, 1.0, "271ms", "POST", "", "");
        }
    }

    // Post Next Level Application
    public function postNextLevel(Request $req)
    {
        $wfLevels = Config::get('PropertyConstaint.CONCESSION-LABEL');
        $req->validate([
            'applicationId' => 'required|integer',
            'receiverRoleId' => 'nullable|integer',
            'action' => 'required|In:forward,backward',
        ]);
        try {
            $userId = authUser($req)->id;
            $track = new WorkflowTrack();
            $mWfWorkflows = new WfWorkflow();
            $mWfRoleMaps = new WfWorkflowrolemap();
            $concession = PropActiveConcession::find($req->applicationId);
            $senderRoleId = $concession->current_role;
            $ulbWorkflowId = $concession->workflow_id;
            $req->validate([
                'comment' => $senderRoleId == $wfLevels['BO'] ? 'nullable' : 'required',
            ]);

            $ulbWorkflowMaps = $mWfWorkflows->getWfDetails($ulbWorkflowId);
            $roleMapsReqs = new Request([
                'workflowId' => $ulbWorkflowMaps->id,
                'roleId' => $senderRoleId
            ]);
            $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIds($roleMapsReqs);

            DB::beginTransaction();
            if ($req->action == 'forward') {
                $this->checkPostCondition($senderRoleId, $wfLevels, $concession);          // Check Post Next level condition
                $concession->current_role = $forwardBackwardIds->forward_role_id;
                $concession->last_role_id =  $forwardBackwardIds->forward_role_id;         // Update Last Role Id
                $metaReqs['verificationStatus'] = 1;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->forward_role_id;
            }

            if ($req->action == 'backward') {
                $concession->current_role = $forwardBackwardIds->backward_role_id;
                $metaReqs['verificationStatus'] = 0;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->backward_role_id;
            }
            $concession->save();

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $concession->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_active_concessions.id';
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;

            $req->request->add($metaReqs);
            $track->saveTrack($req);

            // Updation of Received Date
            $preWorkflowReq = [
                'workflowId' => $concession->workflow_id,
                'refTableDotId' => 'prop_active_concessions.id',
                'refTableIdValue' => $req->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            $previousWorkflowTrack->update([
                'forward_date' => $this->_todayDate->format('Y-m-d'),
                'forward_time' => $this->_todayDate->format('H:i:s')
            ]);

            DB::commit();
            return responseMsgs(true, "Successfully Forwarded The Application!!", "", "", '010708', '01', '', 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Concession Application Approval or Rejected 
     * | @param req
     * | Status-closed
     * | Query Costing-376 ms
     * | Rating-2
     * | Status-Closed
     */
    public function approvalRejection(Request $req)
    {
        try {
            $req->validate([
                "applicationId" => "required",
                "status" => "required"
            ]);
            // Check if the Current User is Finisher or Not
            $mWfRoleUsermap = new WfRoleusermap();
            $mActiveConcession = new PropActiveConcession();
            $track = new WorkflowTrack();

            $activeConcession = $mActiveConcession->getConcessionById($req->applicationId);
            $propOwners = PropOwner::where('id', $activeConcession->prop_owner_id)
                ->first();
            $userId = authUser($req)->id;
            $getFinisherQuery = $this->getFinisherId($req->workflowId);                                 // Get Finisher using Trait
            $refGetFinisher = collect(DB::select($getFinisherQuery))->first();

            $workflowId = $activeConcession->workflow_id;
            $senderRoleId = $activeConcession->current_role;
            $getRoleReq = new Request([                                                 // make request to get role id of the user
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);
            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            $roleId = $readRoleDtls->wf_role_id;

            if ($refGetFinisher->role_id != $roleId) {
                return responseMsg(false, "Forbidden Access", "");
            }
            DB::beginTransaction();

            // Approval
            if ($req->status == 1) {
                // Concession Application replication

                $approvedConcession = $activeConcession->replicate();
                $approvedConcession->setTable('prop_concessions');
                $approvedConcession->id = $activeConcession->id;
                $approvedConcession->save();
                $activeConcession->delete();

                $approvedOwners = $propOwners->replicate();
                $approvedOwners->setTable('log_prop_owners');
                $approvedOwners->id = $propOwners->id;
                $approvedOwners->save();

                $this->updateOwner($propOwners, $activeConcession);

                $msg =  "Application Successfully Approved !!";
                $metaReqs['verificationStatus'] = 1;
            }
            // Rejection
            if ($req->status == 0) {
                // Concession Application replication
                $activeConcession = PropActiveConcession::query()
                    ->where('id', $req->applicationId)
                    ->first();

                $approvedConcession = $activeConcession->replicate();
                $approvedConcession->setTable('prop_rejected_concessions');
                $approvedConcession->id = $activeConcession->id;
                $approvedConcession->save();
                $activeConcession->delete();
                $msg =  "Application Successfully Rejected !!";
                $metaReqs['verificationStatus'] = 0;
            }

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $activeConcession->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_active_concessions.id';
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;
            $metaReqs['trackDate'] = $this->_todayDate->format('Y-m-d H:i:s');
            $req->request->add($metaReqs);
            $track->saveTrack($req);

            // Updation of Received Date
            $preWorkflowReq = [
                'workflowId' => $activeConcession->workflow_id,
                'refTableDotId' => 'prop_active_concessions.id',
                'refTableIdValue' => $req->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            $previousWorkflowTrack->update([
                'forward_date' => $this->_todayDate->format('Y-m-d'),
                'forward_time' => $this->_todayDate->format('H:i:s')
            ]);
            // dd('Test');
            DB::commit();
            return responseMsgs(true, $msg, "", "", '010709', '01', '376ms', 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Update Owner Details after approval
     */
    public function updateOwner($propOwners, $activeConcession)
    {
        if (isset($activeConcession->gender)) {
            $propOwners->gender = $activeConcession->gender;
            $propOwners->save();
        }
        if (isset($activeConcession->dob)) {
            $propOwners->dob = $activeConcession->dob;
            $propOwners->save();
        }
        if (isset($activeConcession->is_specially_abled)) {
            $propOwners->is_specially_abled = $activeConcession->is_specially_abled;
            $propOwners->save();
        }
        if (isset($activeConcession->is_armed_force)) {
            $propOwners->is_armed_force = $activeConcession->is_armed_force;
            $propOwners->save();
        }
    }

    /**
     * | Back to Citizen
     * | @param req
     * | Status-Closed
     * | Query Costing-358 ms 
     * | Rating-2
     * | Status-Closed
     */
    public function backToCitizen(Request $req)
    {
        $req->validate([
            'applicationId' => "required"
        ]);
        try {
            $redis = Redis::connection();
            $concession = PropActiveConcession::find($req->applicationId);
            $senderRoleId = $concession->current_role;

            $workflowId = $concession->workflow_id;
            $backId = json_decode(Redis::get('workflow_initiator_' . $workflowId));
            if (!$backId) {
                $backId = WfWorkflowrolemap::where('workflow_id', $workflowId)
                    ->where('is_initiator', true)
                    ->first();
                $redis->set('workflow_initiator_' . $workflowId, json_encode($backId));
            }

            $concession->current_role = $backId->wf_role_id;
            $concession->parked = 1;
            $concession->save();

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $concession->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_active_concessions.id';
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['verificationStatus'] = 2;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $req->request->add($metaReqs);
            $track = new WorkflowTrack();
            $track->saveTrack($req);

            return responseMsgs(true, "Successfully Done", "", "", '010710', '01', '358ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // get owner details by propId
    public function getOwnerDetails(Request $request)
    {
        try {
            $request->validate([
                'propId' => "required|integer"
            ]);
            $ownerDetails = PropOwner::select(
                'owner_name as ownerName',
                'prop_owners.id as ownerId',
                'ulb_id as ulbId'
            )
                ->join('prop_properties', 'prop_properties.id', 'prop_owners.property_id')
                ->where('property_id', $request->propId)
                ->orderBy('prop_owners.id')
                ->first();

            return responseMsgs(true, "Property 1st Owner Detail", remove_null($ownerDetails), "", '010711', '01', responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", '010711', '01', responseTime(), $request->getMethod(), $request->deviceId);
        }
    }



    /**
     * | Citizen and Level Pendings Independent Comments
     */
    public function commentIndependent(Request $req)
    {
        $req->validate([
            'comment' => 'required',
            'applicationId' => 'required|integer',
            'senderRoleId' => 'nullable|integer'
        ]);

        try {
            $workflowTrack = new WorkflowTrack();
            $concession = PropActiveConcession::find($req->applicationId);                         // Concessions
            $mModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs = array();
            DB::beginTransaction();
            // Save On Workflow Track For Level Independent
            $metaReqs = [
                'workflowId' => $concession->workflow_id,
                'moduleId' => $mModuleId,
                'refTableDotId' => "prop_active_concessions.id",
                'refTableIdValue' => $concession->id,
                'message' => $req->comment
            ];
            // For Citizen Independent Comment
            if (!$req->senderRoleId) {
                $metaReqs = array_merge($metaReqs, ['citizenId' => $concession->user_id]);
            }

            $req->request->add($metaReqs);
            $workflowTrack->saveTrack($req);

            DB::commit();
            return responseMsgs(true, "You Have Commented Successfully!!", ['Comment' => $req->comment], "010108", "1.0", "", "POST", "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     *  get document ttype
     */
    public function getDocType(Request $req)
    {
        switch ($req->doc) {
            case ('gender'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'CONCESSION_GENDER')
                    ->first();

                $code = $this->filterCitizenDoc($data);
                break;

            case ('seniorCitizen'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'CONCESSION_DOB')
                    ->first();

                $code = $this->filterCitizenDoc($data);
                break;

            case ('speciallyAbled'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'CONCESSION_SPECIALLY_ABLED')
                    ->first();

                $code = $this->filterCitizenDoc($data);
                break;

            case ('armedForce'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'CONCESSION_ARMED_FORCE')
                    ->first();

                $code = $this->filterCitizenDoc($data);
                break;
        }

        return responseMsgs(true, "Citizen Doc List", remove_null($code), 010717, 1.0, "413ms", "POST", "", "");
    }

    /**
     * | Filter Doc
     */
    public function filterCitizenDoc($data)
    {
        $document = explode(',', $data->requirements);
        $key = array_shift($document);
        $code = collect($document);
        $label = array_shift($document);
        $documents = collect();

        $reqDoc['docType'] = $key;
        $reqDoc['docName'] = substr($label, 1, -1);
        $reqDoc['uploadedDoc'] = $documents->first();

        $reqDoc['masters'] = collect($document)->map(function ($doc) {
            $strLower = strtolower($doc);
            $strReplace = str_replace('_', ' ', $strLower);
            $arr = [
                "documentCode" => $doc,
                "docVal" => ucwords($strReplace),
            ];
            return $arr;
        });
        return $reqDoc;
    }


    /**
     *  get uploaded documents
     */
    public function getUploadedDocuments(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|numeric'
        ]);
        try {
            $mWfActiveDocument = new WfActiveDocument();
            $mPropActiveConcession = new PropActiveConcession();
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');

            $concessionDetails = $mPropActiveConcession->getConcessionNo($req->applicationId);
            if (!$concessionDetails)
                throw new Exception("Application Not Found for this application Id");

            $workflowId = $concessionDetails->workflow_id;
            $documents = $mWfActiveDocument->getDocsByAppId($req->applicationId, $workflowId, $moduleId);
            return responseMsgs(true, "Uploaded Documents", remove_null($documents), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * |
     */
    public function uploadDocument(Request $req)
    {
        $req->validate([
            "applicationId" => "required|numeric",
            "document" => "required|mimes:pdf,jpeg,png,jpg",
            "docCode" => "required",
            "ownerId" => "nullable|numeric"
        ]);
        $extention = $req->document->getClientOriginalExtension();
        $req->validate([
            'document' => $extention == 'pdf' ? 'max:10240' : 'max:1024',
        ]);

        try {
            $metaReqs = array();
            $docUpload = new DocUpload;
            $mWfActiveDocument = new WfActiveDocument();
            $mPropActiveConcession = new PropActiveConcession();
            $relativePath = Config::get('PropertyConstaint.CONCESSION_RELATIVE_PATH');
            $getConcessionDtls = $mPropActiveConcession->getConcessionNo($req->applicationId);
            $refImageName = $req->docCode;
            $refImageName = $getConcessionDtls->id . '-' . $refImageName;
            $document = $req->document;
            $imageName = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['activeId'] = $getConcessionDtls->id;
            $metaReqs['workflowId'] = $getConcessionDtls->workflow_id;
            $metaReqs['ulbId'] = $getConcessionDtls->ulb_id;
            $metaReqs['relativePath'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['docCode'] = $req->docCode;
            $metaReqs['ownerDtlId'] = $getConcessionDtls->prop_owner_id;

            $metaReqs = new Request($metaReqs);
            $mWfActiveDocument->postDocuments($metaReqs);

            $docUploadStatus = $this->checkFullDocUpload($req->applicationId);
            if ($docUploadStatus == 1) {                                        // Doc Upload Status Update
                $getConcessionDtls->doc_upload_status = 1;
                if ($getConcessionDtls->parked == true)                                // Case of Back to Citizen
                    $getConcessionDtls->parked = false;

                $getConcessionDtls->save();
            }
            return responseMsgs(true, "Document Uploadation Successful", "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check Full Upload Doc Status
     */
    public function checkFullDocUpload($applicationId)
    {
        $mPropActiveConcession = new PropActiveConcession();
        $mWfActiveDocument = new WfActiveDocument();
        $getConcessionDtls = $mPropActiveConcession->getConcessionNo($applicationId);                      // Get Saf Details
        $refReq = [
            'activeId' => $applicationId,
            'workflowId' => $getConcessionDtls->workflow_id,
            'moduleId' => 1
        ];
        $req = new Request($refReq);
        $refDocList = $mWfActiveDocument->getDocsByActiveId($req);
        return $this->isAllDocs($applicationId, $refDocList, $getConcessionDtls);
    }

    public function isAllDocs($applicationId, $refDocList, $getConcessionDtls)
    {
        $docList = array();
        $verifiedDocList = array();
        // $mSafsOwners = new PropActiveSafsOwner();
        // $refSafOwners = $mSafsOwners->getOwnersBySafId($applicationId);
        $concessionDocs = $this->getDocList($getConcessionDtls);
        $docList['concessionDocs'] = explode('#', $concessionDocs);

        $verifiedDocList['concessionDocs'] = $refDocList->where('owner_dtl_id', '!=', null)->values();
        $collectUploadDocList = collect();
        collect($verifiedDocList['concessionDocs'])->map(function ($item) use ($collectUploadDocList) {
            return $collectUploadDocList->push($item['doc_code']);
        });

        // $mPropDocs = $concessionDocs;
        // Property List Documents
        $flag = 1;
        foreach ($concessionDocs as $item) {
            $explodeDocs = explode(',', $item);
            array_shift($explodeDocs);
            foreach ($explodeDocs as $explodeDoc) {
                $changeStatus = 0;
                if (in_array($explodeDoc, $collectUploadDocList->toArray())) {
                    $changeStatus = 1;
                    break;
                }
            }
            if ($changeStatus == 0) {
                $flag = 0;
                break;
            }
        }

        if ($flag == 0)
            return 0;
        else
            return 1;
    }

    /**
     * | Concession Document List
     */
    public function concessionDocList(Request $req)
    {
        try {
            $mPropActiveConcession = new PropActiveConcession();
            $refApplication = $mPropActiveConcession->getConcessionNo($req->applicationId);                      // Get Saf Details
            if (!$refApplication)
                throw new Exception("Application Not Found for this id");
            // $concessionDoc['listDocs'] = $this->getDocList($refApplication);             // Current Object(Concession Docuement List)
            $filterDocs = $this->getDocList($refApplication);             // Current Object(Saf Docuement List)
            if (!empty($filterDocs))
                $concessionDoc['listDocs'] = $this->filterDocument($filterDocs, $refApplication);                                     // function(1.2)
            else
                $concessionDoc['listDocs'] = [];

            return responseMsgs(true, "Successfully Done", remove_null($concessionDoc), "", '010714', '01', '314ms-451ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), '', "", '010714', '01', '314ms-451ms', 'Post', '');
        }
    }

    /**
     * | Get Doc List
     */
    public function getDocList($refApplication)
    {
        $mRefReqDocs = new RefRequiredDocument();
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $isSpeciallyAbled = $refApplication->is_specially_abled;
        $isArmedForce = $refApplication->is_armed_force;
        $gender = $refApplication->gender;
        $dob = $refApplication->dob;
        $documentList = collect();

        if ($isSpeciallyAbled == true) {
            $document = $mRefReqDocs->getDocsByDocCode($moduleId, "CONCESSION_SPECIALLY_ABLED")->requirements;
            $documentList->push($document);
        }
        if ($isArmedForce == true) {
            $document = $mRefReqDocs->getDocsByDocCode($moduleId, "CONCESSION_ARMED_FORCE")->requirements;
            $documentList->push($document);
        }
        if (isset($gender) && isset($dob)) {
            $document = $mRefReqDocs->getDocsByDocCode($moduleId, "CONCESION_GENDER_DOB")->requirements;
            $documentList->push($document);
        } else if (isset($gender)) {
            $document = $mRefReqDocs->getDocsByDocCode($moduleId, "CONCESSION_GENDER")->requirements;
            $documentList->push($document);
        } else if (isset($dob)) {
            $document = $mRefReqDocs->getDocsByDocCode($moduleId, "CONCESSION_DOB")->requirements;
            $documentList->push($document);
        }

        return $documentList;
    }

    /**
     *  | Filtering
     */
    public function filterDocument($documentList, $refApplication)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $safId = $refApplication->id;
        $workflowId = $refApplication->workflow_id;
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $uploadedDocs = $mWfActiveDocument->getDocByRefIds($safId, $workflowId, $moduleId);
        $explodeDocs = $documentList;

        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs) {
            $document = explode(',', $explodeDoc);
            $key = array_shift($document);
            $label = array_shift($document);
            $documents = collect();

            collect($document)->map(function ($item) use ($uploadedDocs, $documents) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                    // ->where('owner_dtl_id', $ownerId)
                    ->first();

                if ($uploadedDoc) {
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode" => $item,
                        "ownerId" => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath" => $uploadedDoc->doc_path ?? "",
                        "verifyStatus" => $uploadedDoc->verify_status ?? "",
                        "remarks" => $uploadedDoc->remarks ?? "",
                    ];
                    $documents->push($response);
                }
            });

            $reqDoc['docType'] = $key;
            $reqDoc['docName'] = substr($label, 1, -1);
            $reqDoc['uploadedDoc'] = $documents->sortByDesc('uploadedDocId')->first();

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                $arr = [
                    "documentCode" => $doc,
                    "docVal" => ucwords($strReplace),
                    "uploadedDoc" => $uploadedDoc->doc_path ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $uploadedDoc->verify_status ?? "",
                    "remarks" => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });
        return $filteredDocs;
    }

    /**
     * | Document Verify Reject
     */
    public function docVerifyReject(Request $req)
    {
        $req->validate([
            'id' => 'required|digits_between:1,9223372036854775807',
            'applicationId' => 'required|digits_between:1,9223372036854775807',
            'docRemarks' =>  $req->docStatus == "Rejected" ? 'required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/' : "nullable",
            'docStatus' => 'required|in:Verified,Rejected'
        ]);

        try {
            // Variable Assignments
            $mWfDocument = new WfActiveDocument();
            $mPropActiveConcession = new PropActiveConcession();
            $mWfRoleusermap = new WfRoleusermap();
            $wfDocId = $req->id;
            $userId = authUser($req)->id;
            $applicationId = $req->applicationId;
            $wfLevel = Config::get('PropertyConstaint.SAF-LABEL');
            // Derivative Assigments
            $concessionDtl = $mPropActiveConcession->getConcessionNo($applicationId);
            $safReq = new Request([
                'userId' => $userId,
                'workflowId' => $concessionDtl->workflow_id
            ]);
            $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfId($safReq);
            if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                throw new Exception("Role Not Available");

            $senderRoleId = $senderRoleDtls->wf_role_id;

            if ($senderRoleId != $wfLevel['DA'])                                // Authorization for Dealing Assistant Only
                throw new Exception("You are not Authorized");

            if (!$concessionDtl || collect($concessionDtl)->isEmpty())
                throw new Exception("Application Details Not Found");

            $ifFullDocVerified = $this->ifFullDocVerified($applicationId);       // (Current Object Derivative Function 4.1)

            if ($ifFullDocVerified == 1)
                throw new Exception("Document Fully Verified");

            DB::beginTransaction();
            if ($req->docStatus == "Verified") {
                $status = 1;
            }
            if ($req->docStatus == "Rejected") {
                $status = 2;
                // For Rejection Doc Upload Status and Verify Status will disabled
                $concessionDtl->doc_upload_status = 0;
                $concessionDtl->doc_verify_status = 0;
                $concessionDtl->save();
            }

            $reqs = [
                'remarks' => $req->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $userId
            ];
            $mWfDocument->docVerifyReject($wfDocId, $reqs);
            $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId);

            if ($ifFullDocVerifiedV1 == 1) {                                     // If The Document Fully Verified Update Verify Status
                $concessionDtl->doc_verify_status = 1;
                $concessionDtl->save();
            }

            DB::commit();
            return responseMsgs(true, $req->docStatus . " Successfully", "", "010204", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check if the Document is Fully Verified or Not (4.1)
     */
    public function ifFullDocVerified($applicationId)
    {
        $mPropActiveConcession = new PropActiveConcession();
        $mWfActiveDocument = new WfActiveDocument();
        $getConcessionDtls = $mPropActiveConcession->getConcessionNo($applicationId);                      // Get Saf Details
        $refReq = [
            'activeId' => $applicationId,
            'workflowId' => $getConcessionDtls->workflow_id,
            'moduleId' => Config::get('module-constants.PROPERTY_MODULE_ID')
        ];
        $req = new Request($refReq);
        $refDocList = $mWfActiveDocument->getDocsByActiveId($req);
        // Property List Documents
        $ifPropDocUnverified = $refDocList->contains('verify_status', 0);
        if ($ifPropDocUnverified == 1)
            return 0;
        else
            return 1;
    }
}
