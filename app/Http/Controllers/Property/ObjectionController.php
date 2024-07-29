<?php

namespace App\Http\Controllers\Property;

use App\BLL\Property\CalculatePropById;
use App\Repository\Property\Interfaces\iObjectionRepository;
use App\Http\Controllers\Controller;
use App\MicroServices\DocUpload;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\CustomDetail;
use App\Models\Masters\RefRequiredDocument;
use App\Models\PropActiveObjectionDtl;
use App\Models\PropActiveObjectionFloor;
use App\Models\Property\MPropForgeryType;
use App\Models\Property\PropActiveObjection;
use App\Models\Property\PropActiveObjectionOwner;
use App\Models\Property\PropFloor;
use Illuminate\Http\Request;
use App\Traits\Workflow\Workflow as WorkflowTrait;
use App\Traits\Property\Objection;
use App\Models\Property\RefPropObjectionType;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\PropPropertyUpdateRequest;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Pipelines\ObjectionInbox\ObjectionByApplicationNo;
use App\Pipelines\ObjectionInbox\ObjectionByName;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use App\Traits\Property\SafDetailsTrait;
use App\Traits\Property\SafDoc;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Redis;

/**
 * | Created On-22-11-2022 
 * | Created By-Mrinal Kumar
 * --------------------------------------------------------------------------------------
 * */

class ObjectionController extends Controller
{
    use SafDoc;
    use WorkflowTrait;
    use Objection;
    use SafDetailsTrait;

    private $_todayDate;
    protected $objection;
    protected $Repository;
    public function __construct(iObjectionRepository $objection)
    {
        $this->Repository = $objection;
        $this->_todayDate = Carbon::now();
    }

    //Objection for Clerical Mistake
    public function applyObjection(Request $request)
    {
        $request->validate([
            'propId' => 'required|integer'
        ]);
        return $this->Repository->applyObjection($request);
    }

    //objection type list
    public function objectionType()
    {
        try {
            $objType = new RefPropObjectionType();
            return $objType->objectionType();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * | forgery types
     */
    public function forgeryType()
    {
        try {
            $mPropForgeryType = new MPropForgeryType();
            $forgeryType = $mPropForgeryType->forgeryType();

            return responseMsgs(true, "Forgery Types", $forgeryType, "", '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // get owner Details
    public function ownerDetailById(Request $request)
    {
        try {
            $Details = new PropOwner();
            $ownerDetails = $Details->getOwnerDetail($request);

            return responseMsg(true, "Successfully Retrieved", remove_null($ownerDetails));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get Inbox List of Objection Workflow
     */
    public function inbox(Request $req)
    {
        try {
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $objection = $this->getObjectionList($workflowIds)                                            // Objection List
                ->where('prop_active_objections.ulb_id', $ulbId)
                ->whereIn('prop_active_objections.current_role', $roleId)
                ->whereIn('p.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_objections.id');

            $inboxList = app(Pipeline::class)
                ->send(
                    $objection
                )
                ->through([
                    ObjectionByApplicationNo::class,
                    ObjectionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "", remove_null($inboxList), '010805', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get the Objection Outbox
     */
    public function outbox(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $objections = $this->getObjectionList($workflowIds)                                   // Get Outbox Objection List
                ->where('prop_active_objections.ulb_id', $ulbId)
                ->whereNotIn('prop_active_objections.current_role', $roleId)
                ->whereIn('p.ward_mstr_id', $occupiedWards)
                ->orderByDesc('prop_active_objections.id');

            $outboxList = app(Pipeline::class)
                ->send(
                    $objections
                )
                ->through([
                    ObjectionByApplicationNo::class,
                    ObjectionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Outbox List", remove_null($outboxList), '010806', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // Get Details by id
    public function getDetailsById(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|integer'
        ]);

        try {
            $mPropActiveObjection = new PropActiveObjection();
            $mObjectionOwners = new PropActiveObjectionOwner();
            $mPropActiveObjectionDtl = new PropActiveObjectionDtl();
            $mPropActiveObjectionFloor = new PropActiveObjectionFloor();
            $mPropOwners = new PropOwner();
            $mPropFloors = new PropFloor();
            $mCustomDetails = new CustomDetail();
            $mForwardBackward = new WorkflowMap();
            $mWorkflowTracks = new WorkflowTrack();
            $mRefTable = Config::get('PropertyConstaint.SAF_OBJECTION_REF_TABLE');
            $details = $mPropActiveObjection->getObjectionById($req->applicationId);

            if (!$details)
                throw new Exception("Application Not Found for this id");

            $basicDetails = $this->generateBasicDetails($details);         // (Basic Details) Trait function to get Basic Details
            $basicElement = [
                'headerTitle' => "Basic Details",
                "data" => $basicDetails
            ];

            $fullDetailsData['application_no'] = $details->objection_no;
            $fullDetailsData['apply_date'] = $details->date;
            $fullDetailsData['objection_for'] = $details->objection_for;
            $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$basicElement]);

            if ($details->objection_for == 'Clerical Mistake') {
                // Table Array
                $ownerList = $mPropOwners->getOwnersByPropId($details->property_id);
                $ownerList = json_decode(json_encode($ownerList), true);       // Convert Std class to array
                $ownerDetails = $this->generateOwnerDetails($ownerList);
                $ownerElement = [
                    'headerTitle' => 'Owner Details',
                    'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                    'tableData' => $ownerDetails
                ];

                //Clerical Mistake  
                $objectionOwnerList = $mObjectionOwners->getOwnerDetail($details->objection_id);
                if ($objectionOwnerList->first()->prop_owner_id == Null)
                    $headerTitle = 'Objection Add Owner Details';
                else
                    $headerTitle = 'Objection Owner Details';
                $objectionOwnerList = json_decode(json_encode($objectionOwnerList), true);       // Convert Std class to array
                $objectionOwnerDetails = $this->objectionOwnerDetails($objectionOwnerList);
                $objectionOwnerElement = [
                    'headerTitle' => $headerTitle,
                    'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Relation", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                    'tableData' => $objectionOwnerDetails
                ];
                $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerElement, $objectionOwnerElement]);
            }



            if ($details->objection_for == 'Assessment Error') {
                // Table Array
                $ownerList = $mPropOwners->getOwnersByPropId($details->property_id);
                $ownerList = json_decode(json_encode($ownerList), true);


                $sql = "SELECT Odtls.*,
                            ot.type,
                            CASE
                                WHEN Odtls.objection_type_id NOT IN (2, 4) THEN Odtls.applicant_data
                                WHEN Odtls.objection_type_id = 2 AND Odtls.applicant_data = '1' THEN 'YES'
                                WHEN Odtls.objection_type_id = 2 AND Odtls.applicant_data = '0' THEN 'NO'
                                WHEN Odtls.objection_type_id = 4 THEN objection_type_prop.property_type
                            END AS obj_valu,
                            CASE
                                WHEN Odtls.objection_type_id NOT IN (2, 4) THEN Odtls.assesment_data
                                WHEN Odtls.objection_type_id = 2 AND Odtls.assesment_data = '1' THEN 'YES'
                                WHEN Odtls.objection_type_id = 2 AND Odtls.assesment_data = '0' THEN 'NO'
                                WHEN Odtls.objection_type_id = 4 THEN ref_prop_types.property_type
                            END AS asses_valu,
                            ref_prop_types.property_type
                        FROM prop_active_objection_dtls as Odtls
                        inner join ref_prop_objection_types as ot on ot.id = Odtls.objection_type_id
                        left join ref_prop_types on ref_prop_types.id::text = Odtls.assesment_data and Odtls.objection_type_id =4
                        left join ref_prop_types objection_type_prop on objection_type_prop.id::text = Odtls.applicant_data and Odtls.objection_type_id =4

                        where objection_id = $details->objection_id";
                $objectionList =  DB::select($sql);


                // //Assessment Details
                //  $objectionList = $mPropActiveObjectionDtl->getDtlbyObjectionId($details->objection_id);

                $objectionList = json_decode(json_encode($objectionList), true);       // Convert Std class to array
                $objectionDetails = $this->objectionDetails($objectionList);
                $objectionElement = [
                    'headerTitle' => 'Objection Details',
                    'tableHead' => ["#", "Objection Type", "Self-Assessed", "Objection"],
                    'tableData' => $objectionDetails
                ];
                $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$objectionElement]);


                //floor Details
                $objectionFlooorDtls = $mPropActiveObjectionFloor->getfloorObjectionId($details->objection_id);
                if ($objectionFlooorDtls->isNotEmpty()) {

                    $objectionFloorElement = [
                        'headerTitle' => 'Objection Floor Details',
                        'tableHead' => ["Floor No.", "Usage Type", "Occupancy Type", "Construction Type", "Built Up Area (in Sq. Ft.)", "Date From", "Date Upto"],
                        'tableData' => array()
                    ];
                    $floorElement = [
                        'headerTitle' => 'Floor Details',
                        'tableHead' => ["#", "Floor", "Usage Type", "Occupancy Type", "Construction Type", "Build Up Area (in Sq. Ft.)", "Date From", "Date Upto"],
                        'tableData' => array()
                    ];
                    foreach ($objectionFlooorDtls as $objectionFlooorDtl) {

                        $floorId = $objectionFlooorDtl->prop_floor_id;
                        $floorList = $mPropFloors->getFloorByFloorMstrId($floorId);

                        $objectionFlooorDtl = json_decode(json_encode($objectionFlooorDtl), true);       // Convert Std class to array

                        $objectionFloorDetails = $this->generateObjectionFloorDetails($objectionFlooorDtl);
                        array_push($objectionFloorElement['tableData'], $objectionFloorDetails);

                        $floorDetails = $this->generateFloorDetails($floorList);

                        array_push($floorElement['tableData'], $floorDetails->first());
                    }
                    $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$objectionElement,  $objectionFloorElement, $floorElement]);
                }
            }

            if ($details->objection_for == 'Forgery') {

                // Table Array
                $ownerList = $mPropOwners->getOwnersByPropId($details->property_id);
                $ownerList = json_decode(json_encode($ownerList), true);       // Convert Std class to array
                $ownerDetails = $this->generateOwnerDetails($ownerList);
                $ownerElement = [
                    'headerTitle' => 'Owner Details',
                    'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                    'tableData' => $ownerDetails
                ];

                $objectionOwnerList = $mObjectionOwners->getOwnerDetail($details->objection_id);
                $objectionOwnerList = json_decode(json_encode($objectionOwnerList), true);       // Convert Std class to array
                $objectionOwnerDetails = $this->objectionOwnerDetails($objectionOwnerList);
                $objectionOwnerElement = [
                    'headerTitle' => 'Applicant Details',
                    'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Relation", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                    'tableData' => $objectionOwnerDetails
                ];

                $forgeryDetails = $this->generateForgeryType($details);         // (Basic Details) Trait function to get Basic Details
                $forgeryElement = [
                    'headerTitle' => "Reason of Forgery",
                    "data" => $forgeryDetails
                ];
                $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$forgeryElement, $basicElement]);
                $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerElement, $objectionOwnerElement]);
            }

            // Card Details
            $cardElement = $this->generateObjCardDtls($details, $ownerList);
            $fullDetailsData['fullDetailsData']['cardArray'] = $cardElement;

            $levelComment = $mWorkflowTracks->getTracksByRefId($mRefTable, $req->id);
            $fullDetailsData['levelComment'] = $levelComment;

            $citizenComment = $mWorkflowTracks->getCitizenTracks($mRefTable, $req->id, $details->user_id);
            $fullDetailsData['citizenComment'] = $citizenComment;

            $metaReqs['customFor']  = 'PROPERTY-OBJECTION';
            $metaReqs['wfRoleId']   =  $details->current_role;
            $metaReqs['workflowId'] =  $details->workflow_id;
            $metaReqs['lastRoleId'] =  $details->last_role_id;
            $req->request->add($metaReqs);

            //role details
            $forwardBackward = $mForwardBackward->getRoleDetails($req);
            $fullDetailsData['roleDetails'] = collect($forwardBackward)['original']['data'];
            $fullDetailsData['timelineData'] = collect($req);

            $custom = $mCustomDetails->getCustomDetails($req);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];

            $custom = $mCustomDetails->getCustomDetails($req);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];

            return responseMsgs(true, "Objection Details", remove_null($fullDetailsData), '010807', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", '010807', '01', responseTime(), 'Post', '');
        }
    }

    /**
     * | Post Escalate the Applications
     * | @param req $req
     */
    public function postEscalate(Request $req)
    {
        try {
            $req->validate([
                'escalateStatus' => 'required|bool',
                'applicationId' => 'required|integer'
            ]);

            DB::beginTransaction();
            $userId = authUser()->id;
            $objId = $req->applicationId;
            $data = PropActiveObjection::find($objId);
            $data->is_escalated = $req->escalateStatus;
            $data->escalated_by = $userId;
            $data->save();
            DB::commit();
            return responseMsgs(true, $req->escalateStatus == 1 ? 'Objection is Escalated' : "Objection is removed from Escalated", '', '010808', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // List of the Escalated Application
    public function specialInbox(Request $req)
    {
        try {
            $userId = authUser()->id;
            $ulbId = authUser()->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $objections = $this->getObjectionList($workflowIds)
                ->where('prop_active_objections.ulb_id', $ulbId)
                ->where('prop_active_objections.is_escalated', true)
                ->whereIn('prop_active_objections.current_role', $roleId)
                ->whereIn('p.ward_mstr_id', $occupiedWards);

            $specialList = app(Pipeline::class)
                ->send(
                    $objections
                )
                ->through([
                    ObjectionByApplicationNo::class,
                    ObjectionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Data Fetched", remove_null($specialList), '010809', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Back to Citizen List
     */
    public function btcInboxList(Request $req)
    {
        try {
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $objections = $this->getObjectionList($workflowIds)
                ->where('prop_active_objections.ulb_id', $ulbId)
                ->whereIn('p.ward_mstr_id', $occupiedWards)
                ->where('prop_active_objections.parked', true);

            $btcList = app(Pipeline::class)
                ->send(
                    $objections
                )
                ->through([
                    ObjectionByApplicationNo::class,
                    ObjectionByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Data Fetched", remove_null($btcList), '010809', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // Post Next Level Application
    public function postNextLevel(Request $req)
    {
        $wfLevels = Config::get('PropertyConstaint.OBJECTION-LABEL');
        try {
            $req->validate([
                'applicationId' => 'required|integer',
                'receiverRoleId' => 'nullable|integer',
                'action' => 'required|In:forward,backward',
            ]);
            $userId = authUser()->id;
            $mRefTable = Config::get('PropertyConstaint.SAF_OBJECTION_REF_TABLE');
            $objection = PropActiveObjection::find($req->applicationId);
            $track = new WorkflowTrack();
            $mWfWorkflows = new WfWorkflow();
            $mWfRoleMaps = new WfWorkflowrolemap();
            $senderRoleId = $objection->current_role;
            $ulbWorkflowId = $objection->workflow_id;
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
                // $this->checkPostCondition($senderRoleId, $wfLevels, $objection);          // Check Post Next level condition
                $objection->current_role = $forwardBackwardIds->forward_role_id;
                $objection->last_role_id =  $forwardBackwardIds->forward_role_id;         // Update Last Role Id
                $metaReqs['verificationStatus'] = 1;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->forward_role_id;
            }

            if ($req->action == 'backward') {
                $objection->current_role = $forwardBackwardIds->backward_role_id;
                $metaReqs['verificationStatus'] = 0;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->backward_role_id;
            }

            $objection->save();
            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $objection->workflow_id;
            $metaReqs['refTableDotId'] = $mRefTable;
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;

            $req->request->add($metaReqs);
            $track->saveTrack($req);

            DB::commit();

            return responseMsgs(true, "Successfully Forwarded The Application!!", "", '010810', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Objection Application approval Rejection
     * | @param request $req
     */
    public function approvalRejection(Request $req)
    {
        $req->validate([
            "applicationId" => "required",
            "status" => "required"
        ]);
        try {
            $mWfRoleUsermap = new WfRoleusermap();
            $mPropOwner = new PropOwner();
            $mPropActiveObjectionOwner = new PropActiveObjectionOwner();
            $mPropActiveObjectionDtl = new PropActiveObjectionDtl();
            $mPropActiveObjectionFloor = new PropActiveObjectionFloor();
            $track = new WorkflowTrack();
            $userId = authUser()->id;
            $activeObjection = PropActiveObjection::where('id', $req->applicationId)
                ->first();

            if (!$activeObjection)
                throw new Exception('Application does not Exist');

            // Check if the Current User is Finisher or Not
            $getFinisherQuery = $this->getFinisherId($activeObjection->workflow_id);                 // Get Finisher using Trait
            $refGetFinisher = collect(DB::select($getFinisherQuery))->first();

            $workflowId = $activeObjection->workflow_id;
            $senderRoleId = $activeObjection->current_role;
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
                // Objection Application replication

                $approvedObjection = $activeObjection->replicate();
                $approvedObjection->setTable('prop_objections');
                $approvedObjection->id = $activeObjection->id;
                $approvedObjection->save();
                $activeObjection->delete();


                if ($activeObjection->objection_for == 'Clerical Mistake') {

                    $ownerDtl =  $mPropActiveObjectionOwner->getOwnerEditDetail($activeObjection->id);

                    //create log missing
                    if ($ownerDtl->prop_owner_id) {
                        PropOwner::where('id', $ownerDtl->prop_owner_id)
                            ->update(
                                [
                                    'owner_name' => $ownerDtl->owner_name,
                                    'mobile_no' => $ownerDtl->owner_mobile,
                                ]
                            );
                    }
                    //create log missing
                    if ($ownerDtl->corr_address) {
                        PropProperty::where('id', $activeObjection->id)
                            ->update(
                                [
                                    'corr_address' => $ownerDtl->corr_address,
                                    'corr_city' => $ownerDtl->corr_city,
                                    'corr_dist' => $ownerDtl->corr_dist,
                                    'corr_pin_code' => $ownerDtl->corr_pin_code,
                                    'corr_state' => $ownerDtl->corr_state,
                                ]
                            );
                    }

                    $ownerDetails =  $mPropActiveObjectionOwner->getOwnerDetail($activeObjection->id);

                    foreach ($ownerDetails as $ownerDetail) {

                        $metaReqs =  new Request([
                            'property_id' => $activeObjection->property_id,
                            'owner_name' => $ownerDetail->owner_name,
                            'guardian_name' => $ownerDetail->guardian_name,
                            'relation_type' => $ownerDetail->relation,
                            'mobile_no' => $ownerDetail->owner_mobile,
                            'email' => $ownerDetail->email,
                            'pan_no' => $ownerDetail->pan,
                            'gender' => $ownerDetail->gender,
                            'dob' => $ownerDetail->dob,
                            'is_armed_force' => $ownerDetail->is_armed_force,
                            'is_specially_abled' => $ownerDetail->is_specially_abled,
                        ]);
                        $mPropOwner->postOwner($metaReqs);
                    }
                }

                if ($activeObjection->objection_for == 'Assessment Error') {
                    $objDtls = $mPropActiveObjectionDtl->getDtlbyObjectionId($activeObjection->id);

                    //create log
                    if ($objDtls->isNotEmpty()) {

                        foreach ($objDtls as $objDtl) {
                            switch ($objDtl->objection_type_id) {
                                case (2):
                                    PropProperty::where('id', $activeObjection->id)
                                        ->update(['is_water_harvesting' => $objDtl->applicant_data]);
                                    break;

                                case (3):
                                    PropProperty::where('id', $activeObjection->id)
                                        ->update(['road_type_mstr_id' => $objDtl->applicant_data]);
                                    break;

                                case (4):
                                    PropProperty::where('id', $activeObjection->id)
                                        ->update(['prop_type_mstr_id' => $objDtl->applicant_data]);
                                    break;

                                case (8):
                                    PropProperty::where('id', $activeObjection->id)
                                        ->update(['rwh_date_from' => $objDtl->applicant_data]);
                                    break;
                            }
                        }
                    }
                }
                $floorDtls = $mPropActiveObjectionFloor->getfloorObjectionId($activeObjection->id);
                //create log
                if ($floorDtls->isNotEmpty()) {

                    foreach ($floorDtls as $floorDtl) {
                        PropFloor::where('id', $floorDtl->prop_floor_id)
                            ->update(
                                [
                                    'floor_mstr_id' => $floorDtl->floor_mstr_id,
                                    'usage_type_mstr_id' => $floorDtl->usage_type_mstr_id,
                                    'occupancy_type_mstr_id' => $floorDtl->occupancy_type_mstr_id,
                                    'const_type_mstr_id' => $floorDtl->const_type_mstr_id,
                                    'builtup_area' => $floorDtl->builtup_area,
                                    'carpet_area' => $floorDtl->carpet_area,
                                ]
                            );
                    }
                }
                $msg =  "Application Successfully Approved !!";
                $metaReqs['verificationStatus'] = 1;

                // $var = new CalculatePropById;
                // return  $data = $var->calculatePropTax($activeObjection);
                // dd($data);
            }

            // Rejection
            if ($req->status == 0) {
                // Objection Application replication
                $approvedObjection = $activeObjection->replicate();
                $approvedObjection->setTable('prop_rejected_objections');
                $approvedObjection->id = $activeObjection->id;
                $approvedObjection->save();
                $activeObjection->delete();
                $msg = "Application Successfully Rejected !!";
                $metaReqs['verificationStatus'] = 0;
            }

            $metaReqs  = [
                'moduleId' => Config::get('module-constants.PROPERTY_MODULE_ID'),
                'workflowId' => $activeObjection->workflow_id,
                'refTableDotId' => 'prop_active_objections.id',
                'refTableIdValue' => $req->applicationId,
                'senderRoleId' => $senderRoleId,
                'user_id' => $userId,
                'trackDate' => $this->_todayDate->format('Y-m-d H:i:s'),
            ];
            $req->merge($metaReqs);
            $track->saveTrack($req);

            // Updation of Received Date
            $preWorkflowReq = [
                'workflowId' => $activeObjection->workflow_id,
                'refTableDotId' => 'prop_active_objections.id',
                'refTableIdValue' => $req->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            $previousWorkflowTrack->update([
                'forward_date' => $this->_todayDate->format('Y-m-d'),
                'forward_time' => $this->_todayDate->format('H:i:s')
            ]);
            DB::commit();

            return responseMsgs(true, $msg, "", '010811', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    // Application back To citizen
    public function backToCitizen(Request $req)
    {
        $req->validate([
            'applicationId' => "required"
        ]);
        try {
            $redis = Redis::connection();
            $objection = PropActiveObjection::find($req->applicationId);
            $workflowId = $objection->workflow_id;
            $senderRoleId = $objection->current_role;
            $mRefTable = Config::get('PropertyConstaint.SAF_OBJECTION_REF_TABLE');
            $backId = json_decode(Redis::get('workflow_initiator_' . $workflowId));
            if (!$backId) {
                $backId = WfWorkflowrolemap::where('workflow_id', $workflowId)
                    ->where('is_initiator', true)
                    ->first();
                $redis->set('workflow_initiator_' . $workflowId, json_encode($backId));
            }
            DB::beginTransaction();
            $objection->current_role = $backId->wf_role_id;
            $objection->parked = 1;
            $objection->save();

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $objection->workflow_id;
            $metaReqs['refTableDotId'] = $mRefTable;
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['verificationStatus'] = 2;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $req->request->add($metaReqs);
            $track = new WorkflowTrack();
            $track->saveTrack($req);

            DB::commit();
            return responseMsgs(true, "Successfully Done", "", '010812', '01', responseTime(), 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "", "", '010812', '01', responseTime(), 'Post', '');
        }
    }

    /**
     * | Independent Comment
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
            $objection = PropActiveObjection::find($req->applicationId);                // SAF Details
            $mModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $mRefTable = Config::get('PropertyConstaint.SAF_OBJECTION_REF_TABLE');
            $metaReqs = array();
            DB::beginTransaction();
            // Save On Workflow Track For Level Independent
            $metaReqs = [
                'workflowId' => $objection->workflow_id,
                'moduleId' => $mModuleId,
                'refTableDotId' => $mRefTable,
                'refTableIdValue' => $objection->id,
                'message' => $req->comment
            ];
            // For Citizen Independent Comment
            if (!$req->senderRoleId) {
                $metaReqs = array_merge($metaReqs, ['citizenId' => $objection->user_id]);
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
     *  get uploaded documents
     */
    public function getUploadedDocuments(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|numeric'
        ]);
        try {
            $mWfActiveDocument = new WfActiveDocument();
            $obj = new PropPropertyUpdateRequest();
            $mPropActiveObjection = new PropActiveObjection();
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');

            $objectionDetails = $mPropActiveObjection->getObjectionNo($req->applicationId);
            if (!$objectionDetails){
                $objectionDetails = $obj->getDetail($req->applicationId);
            }
                throw new Exception("Application Not Found for this application Id");

            $workflowId = $objectionDetails->workflow_id;
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
        ]);
        $extention = $req->document->getClientOriginalExtension();
        $req->validate([
            'document' => $extention == 'pdf' ? 'max:10240' : 'max:1024',
        ]);

        try {
            $metaReqs = array();
            $docUpload = new DocUpload;
            $mWfActiveDocument = new WfActiveDocument();
            $mPropActiveObjection = new PropActiveObjection();
            $relativePath = Config::get('PropertyConstaint.OBJECTION_RELATIVE_PATH');
            $getObjectionDtls = $mPropActiveObjection->getObjectionNo($req->applicationId);
            $refImageName = $req->docCode;
            $refImageName = $getObjectionDtls->id . '-' . $refImageName;
            $document = $req->document;
            $imageName = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['activeId'] = $getObjectionDtls->id;
            $metaReqs['workflowId'] = $getObjectionDtls->workflow_id;
            $metaReqs['ulbId'] = $getObjectionDtls->ulb_id;
            $metaReqs['relativePath'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['docCode'] = $req->docCode;

            $metaReqs = new Request($metaReqs);
            $mWfActiveDocument->postDocuments($metaReqs);
            return responseMsgs(true, "Document Uploadation Successful", "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    //get document status by id
    public function objectionDocList(Request $req)
    {
        try {
            $mPropActiveObjection = new PropActiveObjection();
            $mPropActiveObjectionOwner = new PropActiveObjectionOwner();

            $refApplication = $mPropActiveObjection->getObjectionNo($req->applicationId);
            $objectionType = collect($refApplication->objection_for)->first();
            $ownerDetails = $mPropActiveObjectionOwner->getOwnerDetail($req->applicationId);      // Get Owner Details
            if (!$refApplication)
                throw new Exception("Application Not Found for this id");
            $objectionDoc['listDocs'] = $this->getDocList($refApplication, $ownerDetails, $objectionType);
            // $objectionDoc['ownerDocs'] = collect($ownerDetails)->map(function ($owner) use ($refApplication) {
            //     return $this->getOwnerDocLists($owner, $refApplication);
            // });

            return responseMsgs(true, "", remove_null($objectionDoc), "010203", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    /**
     * | Get Doc List
     */
    public function getDocList($refApplication, $ownerDetails, $objectionType)
    {
        $mRefReqDocs = new RefRequiredDocument();
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        if ($objectionType == 'Clerical Mistake') {
            $ownerDetails = $ownerDetails->first();
            $isOwner = $ownerDetails->owner_name;
            $pincode = $ownerDetails->corr_pin_code;
            $documentList = "";

            if (isset($isOwner))
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_CLERICAL_ID")->requirements;
            if (isset($pincode))
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_CLERICAL_ADDRESS")->requirements;
            if (isset($pincode)  && isset($isOwner))
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_CLERICAL_ADDRESS_ID")->requirements;
            if ($ownerDetails->prop_owner_id == null)
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_CLERICAL_ADD_OWNER")->requirements;
        }

        if ($objectionType == 'Assessment Error') {
            $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_EVIDENCE_DOC")->requirements;
        }

        if ($objectionType == 'Forgery') {
            if ($refApplication->forgery_type_mstr_id == 1)
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_FORGERY_ADVERSE_POSSESSION")->requirements;
            if ($refApplication->forgery_type_mstr_id == 2)
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_FORGERY_SURVIVORSHIP")->requirements;
            if ($refApplication->forgery_type_mstr_id == 3)
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "OBJECTION_FORGERY_TITLE_DISPUTE")->requirements;
        }

        if (!empty($documentList))
            $filteredDocs = $this->filterDocument($documentList, $refApplication);                                     // function(1.2)
        else
            $filteredDocs = [];
        return $filteredDocs;
    }

    /**
     * | Get Owner Document Lists
     */
    public function getOwnerDocLists($refOwners, $refSafs)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $documentList = $this->getOwnerDocs($refOwners);

        if (!empty($documentList)) {
            $ownerPhoto = $mWfActiveDocument->getOwnerPhotograph($refSafs['id'], $refSafs->workflow_id, $moduleId, $refOwners['id']);
            $filteredDocs['ownerDetails'] = [
                'ownerId' => $refOwners['id'],
                'name' => $refOwners['owner_name'],
                'mobile' => $refOwners['mobile_no'],
                'guardian' => $refOwners['guardian_name'],
                'uploadedDoc' => $ownerPhoto->doc_path ?? "",
                'verifyStatus' => $ownerPhoto->verify_status ?? ""
            ];
            $filteredDocs['documents'] = $this->filterDocument($documentList, $refSafs, $refOwners['id']);                                     // function(1.2)
        } else
            $filteredDocs = [];

        $filteredDocs['ownerDetails']['reqDocCount'] = $filteredDocs['documents']->count();
        $filteredDocs['ownerDetails']['uploadedDocCount'] = $filteredDocs['documents']->whereNotNull('uploadedDoc')->count();
        return $filteredDocs;
    }

    /**
     *  filterring
     */

    public function filterDocument($documentList, $refApplication)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $applicationId = $refApplication->id;
        $workflowId = $refApplication->workflow_id;
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $uploadedDocs = $mWfActiveDocument->getDocByRefIds($applicationId, $workflowId, $moduleId);
        $explodeDocs = collect(explode('#', $documentList));

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
     *  add members for clerical mistake
     */
    public function addMembers(Request $request)
    {
        // $request->validate([
        //     // "objectionFor" => "required",
        //     // "document" => "required|mimes:pdf,jpeg,png,jpg,gif",
        //     // "docRefName" => "required"
        // ]);

        // return $request->owners[0]['gender'];
        try {
            $mPropProperty = new PropProperty();
            $user = authUser();
            $userId = $user->id;
            $userType = $user->user_type;
            $objectionFor = $request->objectionFor;
            $objParamId = Config::get('PropertyConstaint.OBJ_PARAM_ID');
            $owner = $request->owners;
            $propDtl = $mPropProperty->getPropById($request->propId);
            $ulbId = $propDtl->ulb_id;

            $ulbWorkflowId = WfWorkflow::where('wf_master_id', Config::get('workflow-constants.PROPERTY_OBJECTION_CLERICAL'))
                ->where('ulb_id', $ulbId)
                ->first();

            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);            // Get Current Initiator ID
            $refFinisherRoleId = $this->getFinisherId($ulbWorkflowId->id);              // Get Finisher ID
            $initiatorRoleId = DB::select($refInitiatorRoleId);
            $finisherRoleId = DB::select($refFinisherRoleId);

            DB::beginTransaction();

            //saving objection details
            # Flag : call model <-----
            $objection = new PropActiveObjection();
            $objection->ulb_id = $ulbId;
            $objection->user_id = $userId;
            $objection->objection_for =  $objectionFor;
            $objection->property_id = $request->propId;
            $objection->remarks = $request->remarks;
            $objection->date = Carbon::now();
            $objection->workflow_id = $ulbWorkflowId->id;
            $objection->current_role = $initiatorRoleId[0]->role_id;
            $objection->initiator_role_id = collect($initiatorRoleId)->first()->role_id;
            $objection->finisher_role_id = collect($finisherRoleId)->first()->role_id;
            $objection->last_role_id = collect($initiatorRoleId)->first()->role_id;

            if ($userType == 'Citizen') {
                $objection->current_role = collect($initiatorRoleId)->first()->forward_role_id;
                $objection->initiator_role_id = collect($initiatorRoleId)->first()->forward_role_id;      // Send to DA in Case of Citizen
                $objection->last_role_id = collect($initiatorRoleId)->first()->forward_role_id;
                $objection->user_id = null;
                $objection->citizen_id = $userId;
            }
            $objection->save();

            $idGeneration = new PrefixIdGenerator($objParamId, $objection->ulb_id);
            $objectionNo = $idGeneration->generate();

            PropActiveObjection::where('id', $objection->id)
                ->update(['objection_no' => $objectionNo]);

            if ($request->document) {
                $docUpload = new DocUpload;
                $mWfActiveDocument = new WfActiveDocument();
                $relativePath = Config::get('PropertyConstaint.OBJECTION_RELATIVE_PATH');
                $refImageName = $request->docCode;
                $refImageName = $objection->id . '-' . str_replace(' ', '_', $refImageName);
                $document = $request->document;
                $imageName = $docUpload->upload($refImageName, $document, $relativePath);

                $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
                $metaReqs['activeId'] = $objection->id;
                $metaReqs['workflowId'] = $objection->workflow_id;
                $metaReqs['ulbId'] = $objection->ulb_id;
                $metaReqs['document'] = $imageName;
                $metaReqs['relativePath'] = $relativePath;
                $metaReqs['docCode'] = $request->docCode;

                $metaReqs = new Request($metaReqs);
                $mWfActiveDocument->postDocuments($metaReqs);

                PropActiveObjection::where('id', $objection->id)
                    ->update(['doc_upload_status' => 1]);
            }

            //saving objection owner details
            foreach ($owner as $owners) {
                $objectionOwner = new PropActiveObjectionOwner();
                $objectionOwner->objection_id = $objection->id;
                $objectionOwner->gender = $owners['gender'] ?? null;
                $objectionOwner->owner_name = $owners['ownerName'] ?? null;
                $objectionOwner->owner_mobile = $owners['mobileNo'] ?? null;
                $objectionOwner->aadhar = $owners['aadhar'] ?? null;
                $objectionOwner->dob = $owners['dob'] ?? null;
                $objectionOwner->guardian_name = $owners['guardianName'] ?? null;
                $objectionOwner->relation = $owners['relation'] ?? null;
                $objectionOwner->pan = $owners['pan'] ?? null;
                $objectionOwner->email = $owners['email'] ?? null;
                $objectionOwner->is_armed_force = $owners['isArmedForce'] ?? false;
                $objectionOwner->is_specially_abled = $owners['isSpeciallyAbled'] ?? false;
                $objectionOwner->created_at = Carbon::now();
                $objectionOwner->save();
            }
            DB::commit();

            return responseMsgs(true, "member added use this for future use", $objectionNo, "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        }
    }

    /**
     * | forgery Doc list for citizen
     */
    public function citizenForgeryDocList(Request $req)
    {
        switch ($req->forgeryTypeMstrId) {
            case ('1'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_FORGERY_ADVERSE_POSSESSION')
                    ->first()->requirements;
                $code = $this->citizenForgeryfilterDoc($data);
                break;

            case ('2'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_FORGERY_SURVIVORSHIP')
                    ->first()->requirements;
                $code = $this->citizenForgeryfilterDoc($data);
                break;

            case ('3'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_FORGERY_TITLE_DISPUTE')
                    ->first()->requirements;
                $code = $this->citizenForgeryfilterDoc($data);
                break;

            case ('4'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', '')
                    ->first()->requirements;
                $code = $this->citizenForgeryfilterDoc($data);
                break;
        }
        return responseMsgs(true, "Citizen Doc List", remove_null($code), 010717, 1.0, responseTime(), "POST", "", "");
    }

    /**
     * | 
     */
    public function citizenForgeryfilterDoc($documentList)
    {
        $explodeDocs = collect(explode('#', $documentList));

        $filteredDocs = $explodeDocs->map(function ($explodeDoc) {
            $document = explode(',', $explodeDoc);
            $key = array_shift($document);
            $label = array_shift($document);
            // $documents = collect();

            $reqDoc['docType'] = $key;
            $reqDoc['docName'] = substr($label, 1, -1);
            // $reqDoc['uploadedDoc'] = $documents->first();

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
        });
        return $filteredDocs;
    }

    public function citizenDocList(Request $req)
    {
        switch ($req->doc) {
            case ('name'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_CLERICAL_ID')
                    ->first();
                $code = $this->filterCitizenDoc($data);
                break;

            case ('address'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_CLERICAL_ADDRESS')
                    ->first();
                $code = $this->filterCitizenDoc($data);
                break;
            case ('occupantName'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_CLERICAL_ID')
                    ->first();
                $code = $this->filterCitizenDoc($data);
                break;
            case ('dob'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_CLERICAL_ID')
                    ->first();
                $code = $this->filterCitizenDoc($data);
                break;
            case ('gender'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_CLERICAL_ID')
                    ->first();
                $code = $this->filterCitizenDoc($data);
                break;
            case ('addOwner'):
                $data =  RefRequiredDocument::select('*')
                    ->where('code', 'OBJECTION_CLERICAL_ADD_OWNER')
                    ->first();
                $code = $this->filterCitizenDoc($data);
                break;
        }
        return responseMsgs(true, "Citizen Doc List", remove_null($code), 010717, 1.0, responseTime(), "POST", "", "");
    }

    /**
     * 
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
        $reqDoc['uploadedDoc'] = $documents->last();

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
            $mPropActiveObjection = new PropActiveObjection();
            $mWfRoleusermap = new WfRoleusermap();
            $wfDocId = $req->id;
            $userId = authUser()->id;
            $applicationId = $req->applicationId;
            $wfLevel = Config::get('PropertyConstaint.SAF-LABEL');
            // Derivative Assigments
            $objectionDtl = $mPropActiveObjection->getObjectionNo($applicationId);
            $safReq = new Request([
                'userId' => $userId,
                'workflowId' => $objectionDtl->workflow_id
            ]);
            $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfId($safReq);
            if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                throw new Exception("Role Not Available");

            $senderRoleId = $senderRoleDtls->wf_role_id;

            if ($senderRoleId != $wfLevel['SI'])                                // Authorization for Dealing Assistant Only
                throw new Exception("You are not Authorized");

            if (!$objectionDtl || collect($objectionDtl)->isEmpty())
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
                $objectionDtl->doc_upload_status = 0;
                $objectionDtl->doc_verify_status = 0;
                $objectionDtl->save();
            }

            $reqs = [
                'remarks' => $req->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $userId
            ];
            $mWfDocument->docVerifyReject($wfDocId, $reqs);
            $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId);

            if ($ifFullDocVerifiedV1 == 1) {                                     // If The Document Fully Verified Update Verify Status
                $objectionDtl->doc_verify_status = 1;
                $objectionDtl->save();
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
        $mPropActiveObjection = new PropActiveObjection();
        $mWfActiveDocument = new WfActiveDocument();
        $refSafs = $mPropActiveObjection->getObjectionNo($applicationId);                      // Get Saf Details
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
}
