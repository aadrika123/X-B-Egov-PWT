<?php

namespace App\Http\Controllers\Property;

use App\BLL\Property\CalculatePropById;
use App\Http\Controllers\Controller;
use App\MicroServices\DocUpload;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\CustomDetail;
use App\Models\Masters\RefRequiredDocument;
use App\Models\Property\PropActiveHarvesting;
use App\Models\Property\PropFloor;
use App\Models\Property\PropHarvestingGeotagUpload;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\PropRwhVerification;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Pipelines\HarvestingInbox\HarvestingByApplicationNo;
use App\Pipelines\HarvestingInbox\HarvestingByName;
use App\Pipelines\SearchHolding;
use App\Pipelines\SearchPtn;
use App\Repository\Property\Concrete\PropertyBifurcation;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use Illuminate\Http\Request;
use App\Traits\Property\SAF;
use App\Traits\Property\SafDetailsTrait;
use App\Traits\Ward;
use App\Traits\Workflow\Workflow;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Redis;

/**
 * | Created On - 18-11-2022
 * | Created By -  Mrinal Kumar
 * | Property RainWaterHarvesting apply
 */

class RainWaterHarvestingController extends Controller
{
    use SAF;
    use Workflow;
    use Ward;
    use SafDetailsTrait;
    private $_todayDate;
    private $_bifuraction;
    private $_workflowId;

    public function __construct()
    {
        $this->_todayDate = Carbon::now();
        $this->_bifuraction = new PropertyBifurcation();
        $this->_workflowId  = Config::get('workflow-constants.RAIN_WATER_HARVESTING_ID');
    }


    /**
     * |----------------------- getWardMasterData --------------------------
     * |Query cost => 400-438 ms 
     * |@param request
     * |@var ulbId
     * |@var wardList
     * | Rating : 1
     */
    public function getWardMasterData(Request $request)
    {
        try {
            $ulbId = authUser()->ulb_id;
            $wardList = $this->getAllWard($ulbId);
            return responseMsg(true, "List of wards", $wardList);
        } catch (Exception $error) {
            return responseMsg(false, "Error!", $error->getMessage());
        }
    }

    /**
     * |----------------------- postWaterHarvestingApplication 1 --------------------------
     * |  Query cost => 350 - 490 ms 
     * | @param request
     * | @var ulbId
     * | @var wardList
     * | request : propertyId, isWaterHarvestingBefore , dateOfCompletion
     * | Rating :2
     */
    public function waterHarvestingApplication(Request $request)
    {
        try {
            $request->validate([
                // 'isWaterHarvestingBefore' => 'required',
                'dateOfCompletion' => 'required|date',
                'propertyId' => 'required',
                'ulbId' => 'required'
            ]);

            $ulbId = $request->ulbId;
            $userId = authUser()->id;
            $userType = authUser()->user_type;
            $track = new WorkflowTrack();
            $harParamId = Config::get('PropertyConstaint.HAR_PARAM_ID');

            $propId = PropActiveHarvesting::where('property_id', $request->propertyId)
                ->where('status', 1)
                ->orderByDesc('id')
                ->first();

            if ($propId)
                throw new Exception('Your Application is already in workflow');

            $ulbWorkflowId = WfWorkflow::where('wf_master_id', $this->_workflowId)
                ->where('ulb_id', $ulbId)
                ->first();

            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);                // Get Current Initiator ID
            $refFinisherRoleId = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId = DB::select($refFinisherRoleId);
            $initiatorRoleId = DB::select($refInitiatorRoleId);

            DB::beginTransaction();
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $waterHaravesting  = $mPropActiveHarvesting->saves($request, $ulbWorkflowId, $initiatorRoleId, $finisherRoleId,  $userId);

            if ($userType == 'Citizen') {
                $waterHaravesting->current_role = collect($initiatorRoleId)->first()->forward_role_id;
                $waterHaravesting->initiator_role_id = collect($initiatorRoleId)->first()->forward_role_id;      // Send to DA in Case of Citizen
                $waterHaravesting->last_role_id = collect($initiatorRoleId)->first()->forward_role_id;
                $waterHaravesting->user_id = null;
                $waterHaravesting->citizen_id = $userId;
                $waterHaravesting->doc_upload_status = 1;
            }
            $waterHaravesting->save();

            $idGeneration = new PrefixIdGenerator($harParamId, $waterHaravesting->ulb_id);
            $harvestingNo = $idGeneration->generate();

            PropActiveHarvesting::where('id', $waterHaravesting->id)
                ->update(['application_no' => $harvestingNo]);

            if ($userType == 'Citizen') {
                $metaReqs = array();
                $docUpload = new DocUpload;
                $mWfActiveDocument = new WfActiveDocument();
                $mPropActiveHarvesting = new PropActiveHarvesting();
                $relativePath = Config::get('PropertyConstaint.HARVESTING_RELATIVE_PATH');
                // $getHarvestingDtls = $mPropActiveHarvesting->getHarvestingNo($request->applicationId);
                $refImageName = $request->docCode;
                $refImageName = $waterHaravesting->id . '-' . $refImageName;
                $document = $request->document;
                $imageName = $docUpload->upload($refImageName, $document, $relativePath);

                $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
                $metaReqs['activeId'] = $waterHaravesting->id;
                $metaReqs['workflowId'] = $waterHaravesting->workflow_id;
                $metaReqs['ulbId'] = $waterHaravesting->ulb_id;
                $metaReqs['relativePath'] = $relativePath;
                $metaReqs['document'] = $imageName;
                $metaReqs['docCode'] = $request->docCode;

                $metaReqs = new Request($metaReqs);
                $mWfActiveDocument->postDocuments($metaReqs);
            }

            $wfReqs['workflowId'] = $ulbWorkflowId->id;
            $wfReqs['refTableDotId'] = 'prop_active_harvestings.id';
            $wfReqs['refTableIdValue'] = $waterHaravesting->id;
            $wfReqs['ulb_id'] = $waterHaravesting->ulb_id;
            $wfReqs['user_id'] = $userId;
            if ($userType == 'Citizen') {
                $wfReqs['citizenId'] = $userId;
                $wfReqs['user_id'] = NULL;
            }
            $wfReqs['receiverRoleId'] = $waterHaravesting->current_role;
            $wfReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $request->request->add($wfReqs);
            $track->saveTrack($request);
            DB::commit();
            return responseMsgs(true, "Application applied!", $harvestingNo);
        } catch (Exception $error) {
            DB::rollBack();
            return responseMsgs(false, "Error!", $error->getMessage());
        }
    }

    /**
     * |----------------------- function for the Inbox  --------------------------
     * |@param ulbId
     * |@param userId
     * |@var applicationId
     * | Rating : 2
     * |status :closed
     */
    public function harvestingInbox(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $harvestingList = new PropActiveHarvesting();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $harvesting = $harvestingList->getHarvestingList($workflowIds)
                ->where('prop_active_harvestings.ulb_id', $ulbId)
                ->whereIn('prop_active_harvestings.current_role', $roleId)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_harvestings.id');

            $inboxList = app(Pipeline::class)
                ->send(
                    $harvesting
                )
                ->through([
                    HarvestingByApplicationNo::class,
                    HarvestingByName::class,
                    SearchPtn::class,
                    SearchHolding::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Inbox List", remove_null($inboxList), '011108', 01, '364ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * |----------------------- function for the Special Inbox (Escalated Applications) for harvesting --------------------------
     * |@param ulbId
     * | Rating : 2
     */
    public function specialInbox(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $harvestingList = new PropActiveHarvesting();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $harvesting = $harvestingList->getHarvestingList($workflowIds)
                ->where('prop_active_harvestings.ulb_id', $ulbId)                                        // Get harvesting
                ->where('prop_active_harvestings.is_escalated', true)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_harvestings.id');

            $specialList = app(Pipeline::class)
                ->send(
                    $harvesting
                )
                ->through([
                    HarvestingByApplicationNo::class,
                    HarvestingByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsg(true, "Inbox List", remove_null($specialList));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Fields Verified Inbox
     */
    public function fieldVerifiedInbox(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $harvestingList = new PropActiveHarvesting();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $harvesting = $harvestingList->getHarvestingList($workflowIds)
                ->where('prop_active_harvestings.ulb_id', $ulbId)                  // Repository function getSAF
                ->where('is_field_verified', true)
                ->whereIn('prop_active_harvestings.current_role', $roleId)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_harvestings.id')
                ->paginate($perPage);

            return responseMsgs(true, "field Verified Inbox!", remove_null($harvesting), 010125, 1.0, "", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010125, 1.0, "", "POST", $req->deviceId);
        }
    }




    /**
     * |----------------------- function for the Outbox --------------------------
     * |@param ulbId
     * |@param userId
     * |@var applicationId
     * | Rating : 2
     * | status :closed
     */
    public function harvestingOutbox(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $harvestingList = new PropActiveHarvesting();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $harvesting = $harvestingList->getHarvestingList($workflowIds)
                ->where('prop_active_harvestings.ulb_id', $ulbId)
                ->whereNotIn('prop_active_harvestings.current_role', $roleId)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_harvestings.id');

            $outboxList = app(Pipeline::class)
                ->send(
                    $harvesting
                )
                ->through([
                    HarvestingByApplicationNo::class,
                    HarvestingByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsg(true, "Outbox List", remove_null($outboxList), '011109', 01, '446ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * |----------------------- function for the escalate Application for harvesting --------------------------
     * |@param ulbId
     * |@param userId
     * |@var applicationId
     * | Rating : 2
     */
    public function postEscalate(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|integer',
            'escalateStatus' => 'required|bool',
        ]);
        try {
            $userId = auth()->user()->id;
            if ($req->escalateStatus == 1) {
                $harvesting = PropActiveHarvesting::find($req->applicationId);
                $harvesting->is_escalated = 1;
                $harvesting->escalated_by = $userId;
                $harvesting->save();
                return responseMsg(true, "Successfully Escalated the application", "");
            }
            if ($req->escalateStatus == 0) {
                $harvesting = PropActiveHarvesting::find($req->id);
                $harvesting->is_escalated = 0;
                $harvesting->escalated_by = null;
                $harvesting->save();
                return responseMsg(true, "Successfully De-Escalated the application", "");
            }
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Static details
     */
    public function staticDetails(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|integer',
        ]);
        try {
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $mPropHarvestingGeotagUpload = new PropHarvestingGeotagUpload();
            $mWfActiveDocument =  new WfActiveDocument();
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');

            $details = $mPropActiveHarvesting->getDetailsById($req->applicationId);
            $geotagDtl = $mPropHarvestingGeotagUpload->getLatLong($req->applicationId);

            $docs =  $mWfActiveDocument->getDocByRefIdsDocCode($req->applicationId, $details->workflow_id, $moduleId, ['WATER_HARVESTING'])->last();
            $data = [
                'id' => $details->id,
                'applicationNo' => $details->application_no,
                'harvestingBefore2017' => $details->harvesting_status,
                'holdingNo' => $details->holding_no,
                'newHoldingNo' => $details->new_holding_no,
                'guardianName' => $details->guardian_name,
                'applicantName' => $details->owner_name,
                'wardNo' => $details->new_ward_no,
                'propertyAddress' => $details->prop_address,
                'mobileNo' => $details->mobile_no,
                'dateOfCompletion' => $details->date_of_completion,
                'harvestingImage' => $docs->doc_path,
                'latitude' => $geotagDtl->latitude ?? null,
                'longitude' => $geotagDtl->longitude ?? null,
            ];

            return responseMsgs(true, "Static Details!", remove_null($data), 010125, 1.0, "", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010125, 1.0, "", "POST", $req->deviceId);
        }
    }

    /**
     * | Harvesting Details
     */
    public function getDetailsById(Request $req)
    {
        $req->validate([
            'applicationId' => 'required'
        ]);
        try {
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $mPropOwners = new PropOwner();
            $mPropFloors = new PropFloor();
            $mWorkflowTracks = new WorkflowTrack();
            $mCustomDetails = new CustomDetail();
            $mForwardBackward = new WorkflowMap();
            $mRefTable = Config::get('PropertyConstaint.SAF_HARVESTING_REF_TABLE');
            $details = $mPropActiveHarvesting->getDetailsById($req->applicationId);

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

            // $corrDetails = $this->generateCorrDtls($details);              // (Corresponding Address Details) Trait function to generate corresponding address details
            // $corrElement = [
            //     'headerTitle' => 'Corresponding Address',
            //     'data' => $corrDetails,
            // ];

            // $electDetails = $this->generateElectDtls($details);            // (Electricity & Water Details) Trait function to generate Electricity Details
            // $electElement = [
            //     'headerTitle' => 'Electricity & Water Details',
            //     'data' => $electDetails
            // ];

            $fullDetailsData['application_no'] = $details->application_no;
            $fullDetailsData['apply_date'] = Carbon::parse($details->created_at)->format('Y-m-d');
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

            // $floorList = $mPropFloors->getPropFloors($details->property_id);    // Model Function to Get Floor Details
            // $floorDetails = $this->generateFloorDetails($floorList);
            // $floorElement = [
            //     'headerTitle' => 'Floor Details',
            //     'tableHead' => ["#", "Floor", "Usage Type", "Occupancy Type", "Construction Type", "Build Up Area", "From Date", "Upto Date"],
            //     'tableData' => $floorDetails
            // ];

            $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerElement]);
            // Card Details
            $cardElement = $this->generateHarvestingCardDtls($details, $ownerList);
            $fullDetailsData['fullDetailsData']['cardArray'] = $cardElement;

            $levelComment = $mWorkflowTracks->getTracksByRefId($mRefTable, $req->id);
            $fullDetailsData['levelComment'] = $levelComment;

            $citizenComment = $mWorkflowTracks->getCitizenTracks($mRefTable, $req->id, $details->citizen_user_id);
            $fullDetailsData['citizenComment'] = $citizenComment;

            $metaReqs['customFor'] = 'PROPERTY-HARVESTING';
            $metaReqs['wfRoleId'] = $details->current_role;
            $metaReqs['workflowId'] = $details->workflow_id;
            $metaReqs['lastRoleId'] = $details->last_role_id;
            $req->request->add($metaReqs);

            $forwardBackward = $mForwardBackward->getRoleDetails($req);
            $fullDetailsData['roleDetails'] = collect($forwardBackward)['original']['data'];

            $fullDetailsData['timelineData'] = collect($req);

            $custom = $mCustomDetails->getCustomDetails($req);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];

            return responseMsg(true, "", remove_null($fullDetailsData));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * |--------------------------- Post Next Level Application(forward or backward application) ------------------------------------------------|
     * | Rating-
     * | Status - Closed
     * | Query Cost - 446ms
     */
    public function postNextLevel(Request $req)
    {
        $wfLevels = Config::get('PropertyConstaint.HARVESTING-LABEL');
        try {
            $req->validate([
                'applicationId' => 'required|integer',
                'receiverRoleId' => 'nullable|integer',
                'action' => 'required|In:forward,backward',
            ]);

            $userId = authUser()->id;
            $track = new WorkflowTrack();
            $harvesting = PropActiveHarvesting::findorFail($req->applicationId);
            $mWfWorkflows = new WfWorkflow();
            $mWfRoleMaps = new WfWorkflowrolemap();
            $senderRoleId = $harvesting->current_role;
            $ulbWorkflowId = $harvesting->workflow_id;
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
                $wfMstrId = $mWfWorkflows->getWfMstrByWorkflowId($harvesting->workflow_id);
                $this->checkPostCondition($senderRoleId, $wfLevels, $harvesting);          // Check Post Next level condition
                $harvesting->current_role = $forwardBackwardIds->forward_role_id;
                $harvesting->last_role_id =  $forwardBackwardIds->forward_role_id;         // Update Last Role Id
                $metaReqs['verificationStatus'] = 1;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->forward_role_id;
            }
            if ($req->action == 'backward') {
                $harvesting->current_role = $forwardBackwardIds->backward_role_id;
                $metaReqs['verificationStatus'] = 0;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->backward_role_id;
            }

            $harvesting->save();
            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $harvesting->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_active_harvestings.id';
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;

            $req->request->add($metaReqs);
            $track->saveTrack($req);

            DB::commit();
            return responseMsgs(true, "Successfully Forwarded The Application!!", "", '011110', 01, '446ms', 'Post', $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * |-------------------------------------Final Approval and Rejection of the Application ------------------------------------------------|
     * | Rating-
     * | Status- Closed
     */
    public function finalApprovalRejection(Request $req)
    {
        try {
            $req->validate([
                'applicationId' => 'required',
                'status' => 'required',

            ]);
            // Check if the Current User is Finisher or Not    
            $mWfRoleUsermap = new WfRoleusermap();
            $track = new WorkflowTrack();
            $userId = authUser()->id;
            $activeHarvesting = PropActiveHarvesting::find($req->applicationId);
            $propProperties = PropProperty::where('id', $activeHarvesting->property_id)
                ->first();

            $workflowId = $activeHarvesting->workflow_id;
            $senderRoleId = $activeHarvesting->current_role;
            $getRoleReq = new Request([                                                 // make request to get role id of the user
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);
            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            $roleId = $readRoleDtls->wf_role_id;

            $getFinisherQuery = $this->getFinisherId($activeHarvesting->workflow_id);                                 // Get Finisher using Trait
            $refGetFinisher = collect(DB::select($getFinisherQuery))->first();
            if ($refGetFinisher->role_id != $roleId) {
                return responseMsg(false, " Access Forbidden", "");
            }

            DB::beginTransaction();
            // Approval
            if ($req->status == 1) {
                // Harvesting Application replication
                $activeHarvesting->approved_date = Carbon::now();
                $activeHarvesting->save();

                $approvedHarvesting = $activeHarvesting->replicate();
                $approvedHarvesting->setTable('prop_harvestings');
                $approvedHarvesting->id = $activeHarvesting->id;
                $approvedHarvesting->save();
                $activeHarvesting->delete();

                $approvedProperties = $propProperties->replicate();
                $approvedProperties->setTable('log_prop_properties');
                $approvedProperties->id = $propProperties->id;
                $approvedProperties->save();

                $propProperties->is_water_harvesting = true;
                $propProperties->rwh_date_from = $activeHarvesting->date_of_completion;
                $propProperties->save();

                $req->merge([
                    "property_id" => $activeHarvesting->property_id,
                ]);

                $calculatePropById = new CalculatePropById;
                return $demand = $calculatePropById->calculatePropTax($req);
                dd($demand);

                $msg = "Application Successfully Approved !";
                $metaReqs['verificationStatus'] = 1;
            }
            // Rejection
            if ($req->status == 0) {
                // Harvesting Application replication
                $rejectedHarvesting = $activeHarvesting->replicate();
                $rejectedHarvesting->setTable('prop_rejected_harvestings');
                $rejectedHarvesting->id = $activeHarvesting->id;
                $rejectedHarvesting->save();
                $activeHarvesting->delete();
                $msg = "Application Successfully Rejected !!";
                $metaReqs['verificationStatus'] = 0;
            }

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $activeHarvesting->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_active_harvestings.id';
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;
            $metaReqs['trackDate'] = $this->_todayDate->format('Y-m-d H:i:s');
            $req->request->add($metaReqs);
            $track->saveTrack($req);

            // Updation of Received Date
            $preWorkflowReq = [
                'workflowId' => $activeHarvesting->workflow_id,
                'refTableDotId' => 'prop_active_harvestings.id',
                'refTableIdValue' => $req->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            $previousWorkflowTrack->update([
                'forward_date' => $this->_todayDate->format('Y-m-d'),
                'forward_time' => $this->_todayDate->format('H:i:s')
            ]);
            dd('ok');
            DB::commit();
            return responseMsgs(true, $msg, "", '011111', 01, '391ms', 'Post', $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * |-------------------------------------  Rejection of the Harvesting ------------------------------------------------|
     * | Rating- 
     * | Status - open
     */
    public function rejectionOfHarvesting(Request $req)
    {
        try {
            $req->validate([
                'applicationId' => 'required',
            ]);
            $userId = authUser()->id;
            $getRole = $this->getRoleIdByUserId($userId);
            $roleId = $getRole->map(function ($value, $key) {                         // Get user Workflow Roles
                return $value->wf_role_id;
            });

            if (collect($roleId)->first() != $req->roleId) {
                return responseMsg(false, " Access Forbidden!", "");
            }

            $activeHarvesting = PropActiveHarvesting::query()
                ->where('id', $req->applicationId)
                ->first();

            $rejectedHarvesting = $activeHarvesting->replicate();
            $rejectedHarvesting->setTable('prop_rejected_harvestings');
            $rejectedHarvesting->id = $activeHarvesting->id;
            $rejectedHarvesting->save();
            $activeHarvesting->delete();

            return responseMsgs(true, "Application Rejected !!", "", '011112', 01, '348ms', 'Post', $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // //harvesting doc by id
    // public function harvestingDocList(Request $req)
    // {
    //     try {
    //         $list = PropHarvestingDoc::select(
    //             'id',
    //             'doc_type as docName',
    //             'relative_path',
    //             'doc_name as docUrl',
    //             'verify_status as docStatus',
    //             'remarks as docRemarks'
    //         )
    //             ->where('prop_harvesting_docs.status', 1)
    //             ->where('prop_harvesting_docs.harvesting_id', $req->id)
    //             ->get();

    //         $list = $list->map(function ($val) {
    //             $path = $this->_bifuraction->readDocumentPath($val->relative_path . $val->docUrl);
    //             $val->docUrl = $path;
    //             return $val;
    //         });

    //         if ($list == Null) {
    //             return responseMsg(false, "No Data Found", '');
    //         } else
    //             return responseMsgs(true, "Success", remove_null($list), '011105', 01, '311ms - 379ms', 'Post', $req->deviceId);
    //     } catch (Exception $e) {
    //         echo $e->getMessage();
    //     }
    // }

    /**
     * | Independent Comments
     */
    public function commentIndependent(Request $request)
    {
        $request->validate([
            'comment' => 'required',
            'applicationId' => 'required|integer',
        ]);

        try {
            $userId = authUser()->id;
            $userType = authUser()->user_type;
            $workflowTrack = new WorkflowTrack();
            $mWfRoleUsermap = new WfRoleusermap();
            $harvesting = PropActiveHarvesting::findOrFail($request->applicationId);                // SAF Details
            $mModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs = array();
            DB::beginTransaction();
            // Save On Workflow Track For Level Independent
            $metaReqs = [
                'workflowId' => $harvesting->workflow_id,
                'moduleId' => $mModuleId,
                'refTableDotId' => "prop_active_harvestings.id",
                'refTableIdValue' => $harvesting->id,
                'message' => $request->comment
            ];

            if ($userType != 'Citizen') {
                $roleReqs = new Request([
                    'workflowId' => $harvesting->workflow_id,
                    'userId' => $userId,
                ]);
                $wfRoleId = $mWfRoleUsermap->getRoleByUserWfId($roleReqs);
                $metaReqs = array_merge($metaReqs, ['senderRoleId' => $wfRoleId->wf_role_id]);
                $metaReqs = array_merge($metaReqs, ['user_id' => $userId]);
            }
            DB::beginTransaction();
            // For Citizen Independent Comment
            if ($userType == 'Citizen') {
                $metaReqs = array_merge($metaReqs, ['citizenId' => $userId]);
                $metaReqs = array_merge($metaReqs, ['ulb_id' => $harvesting->ulb_id]);
                $metaReqs = array_merge($metaReqs, ['user_id' => NULL]);
            }

            $request->request->add($metaReqs);
            $workflowTrack->saveTrack($request);

            DB::commit();
            return responseMsgs(true, "You Have Commented Successfully!!", ['Comment' => $request->comment], "010108", "1.0", "", "POST", "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
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
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');

            $harvestingDetails = $mPropActiveHarvesting->getHarvestingNo($req->applicationId);
            if (!$harvestingDetails)
                throw new Exception("Application Not Found for this application Id");

            $workflowId = $harvestingDetails->workflow_id;
            $documents = $mWfActiveDocument->getDocsByAppId($req->applicationId, $workflowId, $moduleId);
            return responseMsgs(true, "Uploaded Documents", remove_null($documents), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }


    /**
     * to upload documenr
     */
    public function uploadDocument(Request $req)
    {
        $req->validate([
            "applicationId" => "required|numeric",
            "document" => "required|mimes:pdf,jpeg,png,jpg",
            "docCode" => "required",
        ]);
        $extention = $req->document->getClientOriginalExtension();
        $req->validate([
            'document' => $extention == 'pdf' ? 'max:10240' : 'max:1024',
        ]);

        try {
            $metaReqs = array();
            $docUpload = new DocUpload;
            $mWfActiveDocument = new WfActiveDocument();
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $relativePath = Config::get('PropertyConstaint.HARVESTING_RELATIVE_PATH');
            $getHarvestingDtls = $mPropActiveHarvesting->getHarvestingNo($req->applicationId);
            $refImageName = $req->docCode;
            $refImageName = $getHarvestingDtls->id . '-' . $refImageName;
            $document = $req->document;
            $imageName = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['activeId'] = $getHarvestingDtls->id;
            $metaReqs['workflowId'] = $getHarvestingDtls->workflow_id;
            $metaReqs['ulbId'] = $getHarvestingDtls->ulb_id;
            $metaReqs['relativePath'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['docCode'] = $req->docCode;

            $metaReqs = new Request($metaReqs);
            $mWfActiveDocument->postDocuments($metaReqs);

            $getHarvestingDtls->doc_upload_status = 1;
            $getHarvestingDtls->save();

            return responseMsgs(true, "Document Uploadation Successful", "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }


    /**
     *  send back to citizen
     */
    public function backToCitizen(Request $req)
    {
        $req->validate([
            'applicationId' => "required"
        ]);
        try {
            $redis = Redis::connection();
            $harvesting = PropActiveHarvesting::find($req->applicationId);
            $senderRoleId = $harvesting->current_role;

            $workflowId = $harvesting->workflow_id;
            $backId = json_decode(Redis::get('workflow_initiator_' . $workflowId));
            if (!$backId) {
                $backId = WfWorkflowrolemap::where('workflow_id', $workflowId)
                    ->where('is_initiator', true)
                    ->first();
                $redis->set('workflow_initiator_' . $workflowId, json_encode($backId));
            }

            $harvesting->current_role = $backId->wf_role_id;
            $harvesting->parked = 1;
            $harvesting->save();

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $harvesting->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_active_concessions.id';
            $metaReqs['refTableIdValue'] = $req->concessionId;
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

    /**
     * | Back To Citizen Inbox
     */
    public function btcInboxList(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $harvestingList = new PropActiveHarvesting();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $harvesting = $harvestingList->getHarvestingList($workflowIds)
                ->where('prop_active_harvestings.ulb_id', $ulbId)
                ->whereIn('prop_active_harvestings.current_role', $roleId)
                ->whereIn('a.ward_mstr_id', $occupiedWards)
                ->where('parked', true)
                ->orderByDesc('prop_active_harvestings.id')
                ->paginate($perPage);

            return responseMsgs(true, "BTC Inbox List", remove_null($harvesting), 010717, 1.0, "271ms", "POST", "", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010717, 1.0, "271ms", "POST", "", "");
        }
    }

    /**
     * 
     */
    public function getDocList(Request $req)
    {
        try {
            $mPropActiveHarvesting = new PropActiveHarvesting();

            $refApplication = $mPropActiveHarvesting->getHarvestingNo($req->applicationId);
            if (!$refApplication)
                throw new Exception("Application Not Found for this id");

            $harvestingDoc['listDocs'] = $this->getHarvestingDoc($refApplication);

            return responseMsgs(true, "Doc List", remove_null($harvestingDoc), 010717, 1.0, "271ms", "POST", "", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }


    public function getHarvestingDoc($refApplication)
    {
        $mRefReqDocs = new RefRequiredDocument();
        $mWfActiveDocument = new WfActiveDocument();
        $applicationId = $refApplication->id;
        $workflowId = $refApplication->workflow_id;
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "PROP_RAIN_WATER_HARVESTING")->requirements;

        $uploadedDocs = $mWfActiveDocument->getDocByRefIds($applicationId, $workflowId, $moduleId);
        $explodeDocs = collect(explode('#', $documentList));

        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs) {
            $document = explode(',', $explodeDoc);
            $key = array_shift($document);
            $label = array_shift($document);
            $documents = collect();

            collect($document)->map(function ($item) use ($uploadedDocs, $documents) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $item)->first();
                if ($uploadedDoc) {
                    $response = [
                        "documentCode" => $item,
                        "ownerId" => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath" => $uploadedDoc->doc_path ?? ""
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType'] = $key;
            $reqDoc['docName'] = substr($label, 1, -1);
            $reqDoc['uploadedDoc'] = $documents->first();

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
     * citizen document list
     */
    public function citizenDocList()
    {
        $data =  RefRequiredDocument::where('code', 'PROP_RAIN_WATER_HARVESTING')
            ->first();

        $reqDoc = $this->getReqDoc($data);

        return responseMsgs(true, "Citizen Doc List", remove_null($reqDoc), 010717, 1.0, "413ms", "POST", "", "");
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
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $mWfRoleusermap = new WfRoleusermap();
            $wfDocId = $req->id;
            $userId = authUser()->id;
            $applicationId = $req->applicationId;
            $wfLevel = Config::get('PropertyConstaint.SAF-LABEL');
            // Derivative Assigments
            $harvestingDtl = $mPropActiveHarvesting->getHarvestingNo($applicationId);
            $safReq = new Request([
                'userId' => $userId,
                'workflowId' => $harvestingDtl->workflow_id
            ]);
            $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfId($safReq);
            if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                throw new Exception("Role Not Available");

            $senderRoleId = $senderRoleDtls->wf_role_id;

            if ($senderRoleId != $wfLevel['UTC'])                                // Authorization for Dealing Assistant Only
                throw new Exception("You are not Authorized");

            if (!$harvestingDtl || collect($harvestingDtl)->isEmpty())
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
                $harvestingDtl->doc_upload_status = 0;
                $harvestingDtl->doc_verify_status = 0;
                $harvestingDtl->save();
            }

            $reqs = [
                'remarks' => $req->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $userId
            ];
            $mWfDocument->docVerifyReject($wfDocId, $reqs);
            $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId);

            if ($ifFullDocVerifiedV1 == 1) {                                     // If The Document Fully Verified Update Verify Status
                $harvestingDtl->doc_verify_status = 1;
                $harvestingDtl->save();
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
        $mPropActiveHarvesting = new PropActiveHarvesting();
        $mWfActiveDocument = new WfActiveDocument();
        $refSafs = $mPropActiveHarvesting->getHarvestingNo($applicationId);                      // Get Saf Details
        $refReq = [
            'activeId' => $applicationId,
            'workflowId' => $refSafs->workflow_id,
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


    /**
     * | Site Verification
     * | @param req requested parameter
     */
    public function siteVerification(Request $req)
    {
        $req->validate([
            "applicationId" => "required|numeric",
            "verificationStatus" => "required|In:1,0",
            // "harvestingImage.*" => "required|image|mimes:jpeg,jpg,png",
        ]);
        try {
            $taxCollectorRole = Config::get('PropertyConstaint.SAF-LABEL.TC');
            $ulbTaxCollectorRole = Config::get('PropertyConstaint.SAF-LABEL.UTC');
            $relativePath = Config::get('PropertyConstaint.GEOTAGGING_RELATIVE_PATH');
            $verificationStatus = $req->verificationStatus;                                             // Verification Status true or false
            $images = $req->harvestingImage;
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $refImageName = 'harvesting-geotagging-' .  $req->applicationId;
            $propActiveHarvesting = new PropActiveHarvesting();
            $verification = new PropRwhVerification();
            $mWfRoleUsermap = new WfRoleusermap();
            $docUpload = new DocUpload;
            $geoTagging = new PropHarvestingGeotagUpload();
            $mWfActiveDocument = new WfActiveDocument();
            $mRefRequiredDocument = new RefRequiredDocument();
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;

            $applicationDtls = $propActiveHarvesting->getHarvestingNo($req->applicationId);
            $workflowId = $applicationDtls->workflow_id;
            $getRoleReq = new Request([                                                 // make request to get role id of the user
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);

            $doc = $mRefRequiredDocument->getDocsByDocCode($moduleId, 'PROP_HARVESTING_FIELD_IMAGE');
            $reqDoc = $this->getReqDoc($doc);
            $docCode = $reqDoc['masters']->first();

            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            $roleId = $readRoleDtls->wf_role_id;

            switch ($roleId) {
                case $taxCollectorRole;
                    if ($verificationStatus == 1) {
                        $req->agencyVerification = true;
                        $msg = "Site Successfully Verified";
                    }
                    if ($verificationStatus == 0) {
                        $req->agencyVerification = false;
                        $msg = "Site Successfully rebuted";
                    }

                    //GEO TAGGING
                    $docReqs = [
                        'application_id' => $req->applicationId,
                        'property_id' => $applicationDtls->property_id,
                        'image_path' => $refImageName,
                        'longitude' => $req->longitude,
                        'latitude' => $req->latitude,
                        'relative_path' => $relativePath,
                        'user_id' => authUser()->id
                    ];

                    $imageName = $docUpload->upload($refImageName, $images, $relativePath);         // <------- Get uploaded image name and move the image in folder
                    $geoTagging->add($docReqs);
                    // $geoTagging->add($req, $imageName, $relativePath, $geoTagging);

                    $metaReqs['moduleId'] = $moduleId;
                    $metaReqs['activeId'] = $req->applicationId;
                    $metaReqs['workflowId'] = $applicationDtls->workflow_id;
                    $metaReqs['ulbId'] = $applicationDtls->ulb_id;
                    $metaReqs['relativePath'] = $relativePath;
                    $metaReqs['document'] = $imageName;
                    $metaReqs['docCode'] = $docCode['documentCode'];
                    $metaReqs['verifyStatus'] = 1;

                    $metaReqs = new Request($metaReqs);
                    $mWfActiveDocument->postDocuments($metaReqs);

                    break;
                    DB::beginTransaction();
                case $ulbTaxCollectorRole;                                                                // In Case of Ulb Tax Collector
                    if ($verificationStatus == 1) {
                        $req->ulbVerification = true;
                        $msg = "Site Successfully Verified";
                    }
                    if ($verificationStatus == 0) {
                        $req->ulbVerification = false;
                        $msg = "Site Successfully rebuted";
                    }
                    $propActiveHarvesting->verifyFieldStatus($req->applicationId);                                         // Enable Fields Verify Status
                    break;

                default:
                    return responseMsg(false, "Forbidden Access", "");
            }

            $req->merge([
                'propertyId' => $applicationDtls->property_id,
                'harvestingId' => $applicationDtls->id,
                'harvestingStatus' => $applicationDtls->harvesting_status,
                'userId' => $userId,
                'ulbId' => $ulbId,
            ]);
            $verificationId = $verification->store($req);

            DB::commit();
            return responseMsgs(true, $msg, "", "010118", "1.0", "310ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    // Get TC Verifications
    public function getTcVerifications(Request $req)
    {
        try {
            $data = array();
            $mPropRwhVerification = new PropRwhVerification();
            $mWfActiveDocument = new WfActiveDocument();
            $mPropHarvestingGeotagUpload = new PropHarvestingGeotagUpload();
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');

            $applicationDtls = $mPropActiveHarvesting->getDetailsById($req->applicationId);
            $data = $mPropRwhVerification->getVerificationsData($req->applicationId);
            $geotagDtl = $mPropHarvestingGeotagUpload->getLatLong($req->applicationId);

            if (collect($data)->isEmpty())
                throw new Exception("Tc Verification Not Done");

            $document = $mWfActiveDocument->getDocByRefIdsDocCode($req->applicationId, $applicationDtls->workflow_id, $moduleId, ['WATER_HARVESTING_FIELD_IMAGE'])->first();
            $data->doc_path = $document->doc_path;
            $data->latitude = $geotagDtl->latitude;
            $data->longitude = $geotagDtl->longitude;

            return responseMsgs(true, "TC Verification Details", remove_null($data), "010120", "1.0", "258ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | check Post Condition for backward forward
     */
    public function checkPostCondition($senderRoleId, $wfLevels, $harvesting)
    {
        switch ($senderRoleId) {
            case $wfLevels['BO']:                        // Back Office Condition
                if ($harvesting->doc_upload_status == 0)
                    throw new Exception("Document Not Fully Uploaded");
                break;
                // case $wfLevels['DA']:                       // DA Condition
                //     if ($harvesting->doc_verify_status == 0)
                //         throw new Exception("Document Not Fully Verified");
                //     break;
        }
    }

    /**
     * | Get Req Docs
     */
    public function getReqDoc($data)
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
}
