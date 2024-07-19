<?php

namespace App\Http\Controllers\Water;

use App\Http\Controllers\Controller;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\Workflows\WfActiveDocument;
use App\Models\CustomDetail;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterApprovalApplicationDetail;
use App\Models\Water\WaterConsumerActiveRequest;
use App\Models\Water\WaterConsumerOwner;
use App\Models\Water\WaterSecondConsumer;
use App\Models\Water\WaterSiteInspection;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use App\Traits\Ward;
use App\Traits\Water\WaterTrait;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | ----------------------------------------------------------------------------------
 * | Water Module | Consumer Workflow
 * |-----------------------------------------------------------------------------------
 * | Created On- 17-07-2023
 * | Created By- Sam kerketta 
 * | Created For- Water consumer workflow related operations
 */

class WaterConsumerWfController extends Controller
{
    use Ward;
    use Workflow;
    use WaterTrait;

    private $_waterRoles;
    private $_waterModuleId;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct()
    {
        $this->_waterRoles      = Config::get('waterConstaint.ROLE-LABEL');
        $this->_waterModuleId   = Config::get('module-constants.WATER_MODULE_ID');
        $this->_DB_NAME = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
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
     * | List the consumer request inbox details 
        | Serial No : 01
        | Working
     */
    public function consumerInbox(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'perPage' => 'nullable|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                   = authUser($req);
            $pages                  = $req->perPage ?? 10;
            $userId                 = $user->id;
            $ulbId                  = $user->ulb_id;
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();

            $occupiedWards  = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId         = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds    = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $inboxDetails = $this->getConsumerWfBaseQuerry($workflowIds, $ulbId)
                ->whereIn('water_consumer_active_requests.current_role', $roleId)
                // ->whereIn('water_consumer_active_requests.ward_mstr_id', $occupiedWards)
                ->where('water_consumer_active_requests.is_escalate', false)
                ->where('water_consumer_active_requests.parked', false)
                ->orderByDesc('water_consumer_active_requests.id')
                ->get();
            return responseMsgs(true, "Successfully listed consumer req inbox details!",  remove_null($inboxDetails), "", "01", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '', '01', responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Consumer Outbox 
     * | Get Consumer Active outbox details 
        | Serial No :
        | Working 
     */
    public function consumerOutbox(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'perPage' => 'nullable|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $user                   = authUser($req);
            $pages                  = $req->perPage ?? 10;
            $userId                 = $user->id;
            $ulbId                  = $user->ulb_id;
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();

            $occupiedWards  = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId         = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds    = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $outboxDetails = $this->getConsumerWfBaseQuerry($workflowIds, $ulbId)
                ->whereNotIn('water_consumer_active_requests.current_role', $roleId)
                ->whereIn('water_consumer_active_requests.ward_mstr_id', $occupiedWards)
                ->orderByDesc('water_consumer_active_requests.id')
                ->get();
            return responseMsgs(true, "Successfully listed consumer req inbox details!",  remove_null($outboxDetails), "", "01", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '', '01', responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Get details of application for displaying 
        | Serial No :
        | Under Con
     */
    public function getConApplicationDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'nullable|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $returDetails = $this->getConActiveAppDetails($request->applicationId)
                ->where('wc.status', 2)
                ->first();
            if (!$returDetails) {
                throw new Exception("Application Details Not found!");
            }
            return responseMsgs(true, "Application Detials!", remove_null($returDetails), '', '01', responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '', '01', responseTime(), $request->getMethod(), $request->deviceId);
        }
    }

    /**
     * | Get the Citizen applied applications 
     * | Application list according to citizen 
        | Serial No :
        | Under Con
     */
    public function getRequestedApplication(Request $request)
    {
        try {
            $user                           = authUser($request);
            $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
            $refUserType                    = Config::get('waterConstaint.REF_USER_TYPE');

            # User type changes 
            $detailsDisconnections = $mWaterConsumerActiveRequest->getApplicationByUser($user->id)->get();
            if (!collect($detailsDisconnections)->first()) {
                throw new Exception("Data not found!");
            }
            return responseMsgs(true, "list of disconnection ", remove_null($detailsDisconnections), "", "1.0", "350ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $e->getCode(), "1.0", "", 'POST', "");
        }
    }
    /**
     * postnext level water Disconnection
     * 
     */
    public function consumerPostNextLevel(Request $request)
    {
        $wfLevels = Config::get('waterConstaint.ROLE-LABEL');
        $request->validate([
            'applicationId'     => 'required',
            'senderRoleId'      => 'required',
            'receiverRoleId'    => 'required',
            'action'            => 'required|In:forward,backward',
            'comment'           => $request->senderRoleId == $wfLevels['DA'] ? 'nullable' : 'required',

        ]);
        try {
            return $this->postNextLevelRequest($request);
        } catch (Exception $error) {
            DB::rollBack();
            return responseMsg(false, $error->getMessage(), "");
        }
    }

    /**
     * post next level for water consumer other request 
     */

    public function postNextLevelRequest($req)
    {

        $mWfWorkflows        = new WfWorkflow();
        $mWfRoleMaps         = new WfWorkflowrolemap();

        $current             = Carbon::now();
        $wfLevels            = Config::get('waterConstaint.ROLE-LABEL');
        $waterConsumerActive = WaterConsumerActiveRequest::find($req->applicationId);

        # Derivative Assignments
        $senderRoleId   = $waterConsumerActive->current_role;
        $ulbWorkflowId  = $waterConsumerActive->workflow_id;
        $ulbWorkflowMaps = $mWfWorkflows->getWfDetails($ulbWorkflowId);
        $roleMapsReqs   = new Request([
            'workflowId' => $ulbWorkflowMaps->id,
            'roleId' => $senderRoleId
        ]);
        $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIds($roleMapsReqs);

        DB::beginTransaction();
        if ($req->action == 'forward') {
            $this->checkPostCondition($req->senderRoleId, $wfLevels, $waterConsumerActive);            // Check Post Next level condition
            if ($waterConsumerActive->current_role == $wfLevels['JE']) {
                $waterConsumerActive->is_field_verified = true;
            }
            $metaReqs['verificationStatus'] = 1;
            $waterConsumerActive->current_role = $forwardBackwardIds->forward_role_id;
            $waterConsumerActive->last_role_id =  $forwardBackwardIds->forward_role_id;                                      // Update Last Role Id

        }
        if ($req->action == 'backward') {
            $waterConsumerActive->current_role = $forwardBackwardIds->backward_role_id;
        }

        $waterConsumerActive->save();
        $metaReqs['moduleId']           =  $this->_waterModuleId;
        $metaReqs['workflowId']         = $waterConsumerActive->workflow_id;
        $metaReqs['refTableDotId']      = 'water_consumer_active_requests.id';
        $metaReqs['refTableIdValue']    = $req->applicationId;
        $metaReqs['user_id']            = authUser($req)->id;
        $req->request->add($metaReqs);
        $waterTrack         = new WorkflowTrack();
        $waterTrack->saveTrack($req);

        # check in all the cases the data if entered in the track table 
        // Updation of Received Date
        $preWorkflowReq = [
            'workflowId'        => $waterConsumerActive->workflow_id,
            'refTableDotId'     => "water_consumer_active_requests.id",
            'refTableIdValue'   => $req->applicationId,
            'receiverRoleId'    => $senderRoleId
        ];

        $previousWorkflowTrack = $waterTrack->getWfTrackByRefId($preWorkflowReq);
        $previousWorkflowTrack->update([
            'forward_date' => $current,
            'forward_time' => $current
        ]);
        DB::commit();
        return responseMsgs(true, "Successfully Forwarded The Application!!", "", "", "", '01', '.ms', 'Post', '');
    }

    public function checkPostCondition($senderRoleId, $wfLevels, $application)
    {
        $mWaterSiteInspection = new WaterSiteInspection();

        $refRole = Config::get("waterConstaint.ROLE-LABEL");
        switch ($senderRoleId) {
            case $wfLevels['DA']:
                if ($application->doc_upload_status == false) {
                    throw new Exception("Document Not Fully Uploaded");
                }                                                                       // DA Condition
                if ($application->doc_status == false)
                    throw new Exception("Document Not Fully Verified");
                break;
            case $wfLevels['JE']:                                                                       // JE Coditon in case of site adjustment
                if ($application->is_field_verified == false) {
                    throw new Exception("Document Not Fully Uploaded or site inspection not done!");
                }
                $siteDetails = $mWaterSiteInspection->getSiteDetails($application->id)
                    ->where('order_officer', $refRole['JE'])
                    ->first();
                if (!$siteDetails) {
                    throw new Exception("Site Not Verified!");
                }
                break;
        }
    }
    /**
     * water disconnection approval or reject 
     */
    public function consumerApprovalRejection(Request $request)
    {
        $request->validate([
            "applicationId" => "required",
            "status"        => "required",
            "comment"       => "required"
        ]);
        try {
            $mWfRoleUsermap = new WfRoleusermap();
            $waterDetails = WaterConsumerActiveRequest::find($request->applicationId);

            # check the login user is AE or not
            $userId = authUser($request)->id;
            $workflowId = $waterDetails->workflow_id;
            $getRoleReq = new Request([                                                 // make request to get role id of the user
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);
            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            $roleId = $readRoleDtls->wf_role_id;
            if ($roleId != $waterDetails->finisher) {
                throw new Exception("You are not the Finisher!");
            }
            DB::beginTransaction();
            $this->approvalRejectionWater($request, $roleId);
            DB::commit();
            return responseMsg(true, "Request approved/rejected successfully", "");;
        } catch (Exception $e) {
            // DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Function for Final Approval Or Rejection 
     */
    public function approvalRejectionWater($request, $roleId)
    {

        $mWaterConsumerActive  =  new WaterConsumerActiveRequest();
        $consumerParamId    = Config::get("waterConstaint.PARAM_IDS.DISC");
        $refJe              = Config::get("waterConstaint.ROLE-LABEL.JE");
        $refWaterDetails  = $this->preApprovalConditionCheck($request, $roleId);


        # Approval of water application 
        if ($request->status == 1) {
            $idGeneration   = new PrefixIdGenerator($consumerParamId, $refWaterDetails['ulb_id']);
            $consumerNo     = $idGeneration->generate();
            $consumerNo     = str_replace('/', '-', $consumerNo);

            $this->finalApproval($request, $refJe, $consumerNo);
            $msg = "Application Successfully Approved !!";
        }
        # Rejection of water application
        if ($request->status == 0) {
            $this->finalRejectionOfAppication($request);
            $msg = "Application Successfully Rejected !!";
        }
        return responseMsgs(true, $msg, $request ?? "Empty", '', 01, '.ms', 'Post', $request->deviceId);
    }
    /**
    //  * | Check in the database for the final approval of application
    //  * | only for EO
    //  * | @param request
    //  * | @param roleId
    //     | working
    //     | Check payment,docUpload,docVerify,feild
    //  */
    // public function checkDataApprovalCondition($request, $roleId, $waterDetails)
    // {
    //     $mWaterConnectionCharge = new WaterConnectionCharge();

    //     $applicationCharges = $mWaterConnectionCharge->getWaterchargesById($waterDetails->id)->get();
    //     $paymentStatus = collect($applicationCharges)->map(function ($value) {
    //         return $value['paid_status'];
    //     })->values();
    //     $uniqueArray = array_unique($paymentStatus->toArray());

    //     // if (count($uniqueArray) === 1 && $uniqueArray[0] === 1) {
    //     //     $payment = true;
    //     // } else {
    //     //     throw new Exception("full payment for the application is not done!");
    //     // }
    // }

    /**
     * function for check pre condition for 
     * approval and reject 
     */

    public function preApprovalConditionCheck($request, $roleId)
    {
        $waterDetails = WaterConsumerActiveRequest::find($request->applicationId);
        if ($waterDetails->finisher != $roleId) {
            throw new Exception("You're Not the finisher ie. AE!");
        }
        if ($waterDetails->current_role != $roleId) {
            throw new Exception("Application has not Reached to the finisher ie. AE!");
        }

        // if ($waterDetails->payment_status != 1) {
        //     throw new Exception("Payment Not Done or not verefied!");
        // }
        // if ($waterDetails->is_field_verified == 0) {
        //     throw new Exception("Field Verification Not Done!!");
        // }
        // $this->checkDataApprovalCondition($request, $roleId, $waterDetails);   // Reminder
        return $waterDetails;
    }

    /**
     * |------------------- Final Approval of the water disconnection application -------------------|
     * | @param request
     * | @param consumerNo
     */
    public function finalApproval($request, $refJe, $consumerNo)
    {
        # object creation
        $mwaterConsumerActiveRequest      = new WaterConsumerActiveRequest();
        $mWaterSiteInspection             = new WaterSiteInspection();
        $mWaterConsumer                   = new WaterSecondConsumer();
        $waterTrack                       = new WorkflowTrack();

        # checking if consumer already exist 
        $approvedWater = WaterConsumerActiveRequest::query()
            ->where('id', $request->applicationId)
            ->first();
        $checkExist = $mwaterConsumerActiveRequest->getApproveApplication($approvedWater->id);
        if (!$checkExist) {
            throw new Exception("Application Not Found");
        } elseif ($checkExist->verify_status == 1) {

            throw new Exception('Already Approve Application');
        } elseif ($checkExist->verify_status == 2) {
            throw new Exception('Already Rejected Applications');
        }
        $checkconsumer = $mWaterConsumer->getConsumerByAppId($approvedWater->id);
        if ($checkconsumer) {
            throw new Exception("Access Denied ! Consumer Already Exist!");
        }
        # data formating for save the consumer details 
        $siteDetails = $mWaterSiteInspection->getSiteDetails($request->applicationId)
            // ->where('payment_status', 1)
            ->where('order_officer', $refJe)
            ->first();
        if (isset($siteDetails)) {
            $refData = [
                'connection_type_id'    => $siteDetails['connection_type_id'],
                'connection_through'    => $siteDetails['connection_through'],
                'pipeline_type_id'      => $siteDetails['pipeline_type_id'],
                'property_type_id'      => $siteDetails['property_type_id'],
                'category'              => $siteDetails['category'],
                'area_sqft'             => $siteDetails['area_sqft'],
                'area_asmt'             => sqFtToSqMt($siteDetails['area_sqft'])
            ];
            $approvedWaterRep = collect($approvedWater)->merge($refData);
        }

        # dend record in the track table 
        $metaReqs = [
            'moduleId'          => Config::get("module-constants.WATER_MODULE_ID"),
            'workflowId'        => $approvedWater->workflow_id,
            'refTableDotId'     => 'water_applications.id',
            'refTableIdValue'   => $approvedWater->id,
            'user_id'           => authUser($request)->id,
        ];
        $request->request->add($metaReqs);
        $waterTrack->saveTrack($request);
        # update verify status
        $mwaterConsumerActiveRequest->updateVerifystatus($request->applicationId, $request->status);

        #deactivate Consumer Permantly
        $mWaterConsumer->dissconnetConsumer($checkExist->consumer_id);
    }

    /**
     * |------------------- Final rejection of the Application -------------------|
     * | Transfer the data to new table
     */
    public function finalRejectionOfAppication($request)
    {
        $rejectedWater = WaterConsumerActiveRequest::query()
            ->where('id', $request->applicationId)
            ->first();


        # save record in track table 
        $waterTrack = new WorkflowTrack();
        $metaReqs['moduleId'] =  Config::get("module-constants.WATER_MODULE_ID");
        $metaReqs['workflowId'] = $rejectedWater->workflow_id;
        $metaReqs['refTableDotId'] = 'water_applications.id';
        $metaReqs['refTableIdValue'] = $rejectedWater->id;
        $metaReqs['user_id'] = authUser($request)->id;
        $request->request->add($metaReqs);
        $waterTrack->saveTrack($request);

        #update Verify Status
        $rejectedWater->updateVerifystatus($request->applicationId, $request->status);
    }
    /**
     * get all applications details by id from workflow
     |working ,not completed
     */
    public function getWorkflow(Request $request)
    {

        $request->validate([
            'applicationId' => "required"

        ]);

        try {
            return $this->getApplicationsDetails($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function getApplicationsDetails($request)
    {

        $forwardBackward        = new WorkflowMap();
        $mWorkflowTracks        = new WorkflowTrack();
        $mCustomDetails         = new CustomDetail();
        $mUlbNewWardmap         = new UlbWardMaster();
        $mwaterConsumerActive   = new WaterConsumerActiveRequest();
        $mwaterOwner            = new WaterConsumerOwner();
        # applicatin details
        $applicationDetails = $mwaterConsumerActive->fullWaterDetails($request)->get();
        if (collect($applicationDetails)->first() == null) {
            return responseMsg(false, "Application Data Not found!", $request->applicationId);
        }
        $consumerId = $applicationDetails->pluck('consumer_id');
        # Ward Name
        $refApplication = collect($applicationDetails)->first();
        // $wardDetails = $mUlbNewWardmap->getWard($refApplication->ward_mstr_id);
        # owner Details
        $ownerDetails = $mwaterOwner->getConsumerOwner($consumerId)->get();
        $ownerDetail = collect($ownerDetails)->map(function ($value, $key) {
            return $value;
        });
        $aplictionList = [
            'application_no' => collect($applicationDetails)->first()->application_no,
            'apply_date' => collect($applicationDetails)->first()->apply_date
        ];


        # DataArray
        $basicDetails = $this->getBasicDetails($applicationDetails);

        $firstView = [
            'headerTitle' => 'Basic Details',
            'data' => $basicDetails
        ];
        $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$firstView]);
        # CardArray
        $cardDetails = $this->getCardDetails($applicationDetails, $ownerDetail);
        $cardData = [
            'headerTitle' => 'Water Disconnection',
            'data' => $cardDetails
        ];
        $fullDetailsData['fullDetailsData']['cardArray'] = new Collection($cardData);
        # TableArray
        $ownerList = $this->getOwnerDetails($ownerDetail);
        $ownerView = [
            'headerTitle' => 'Owner Details',
            'tableHead' => ["#", "Owner Name", "Guardian Name", "Mobile No", "Email", "City", "District"],
            'tableData' => $ownerList
        ];
        $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerView]);

        # Level comment
        $mtableId = $applicationDetails->first()->id;
        $mRefTable = "water_consumer_active_requests.id";
        $levelComment['levelComment'] = $mWorkflowTracks->getTracksByRefId($mRefTable, $mtableId);

        #citizen comment
        $refCitizenId = $applicationDetails->first()->citizen_id;
        $citizenComment['citizenComment'] = $mWorkflowTracks->getCitizenTracks($mRefTable, $mtableId, $refCitizenId);

        # Role Details
        $data = json_decode(json_encode($applicationDetails->first()), true);
        $metaReqs = [
            'customFor' => 'Water Disconnection',
            'wfRoleId' => $data['current_role'],
            'workflowId' => $data['workflow_id'],
            'lastRoleId' => $data['last_role_id']
        ];
        $request->request->add($metaReqs);
        $forwardBackward = $forwardBackward->getRoleDetails($request);
        $roleDetails['roleDetails'] = collect($forwardBackward)['original']['data'];

        # Timeline Data
        $timelineData['timelineData'] = collect($request);

        # Departmental Post
        $custom = $mCustomDetails->getCustomDetails($request);
        $departmentPost['departmentalPost'] = collect($custom)['original']['data'];
        # Payments Details
        $returnValues = array_merge($aplictionList, $fullDetailsData, $levelComment, $citizenComment, $roleDetails, $timelineData, $departmentPost);
        return responseMsgs(true, "listed Data!", remove_null($returnValues), "", "02", ".ms", "POST", "");
    }
    /**
     * function for return data of basic details
     */
    public function getBasicDetails($applicationDetails)
    {
        $collectionApplications = collect($applicationDetails)->first();
        return new Collection([
            ['displayString' => 'Ward No',            'key' => 'WardNo',              'value' => $collectionApplications->ward_name],
            // ['displayString' => 'Charge Category',    'key' => 'chargeCategory',      'value' => $collectionApplications->charge_category],
            // ['displayString' => 'Ubl Id',             'key' => 'ulbId',               'value' => $collectionApplications->ulb_id],
            ['displayString' => 'ApplyDate',           'key' => 'applyDate',          'value' => $collectionApplications->apply_date],
        ]);
    }
    /**
     * return data fro card details 
     */
    public function getCardDetails($applicationDetails, $ownerDetail)
    {
        $ownerName = collect($ownerDetail)->map(function ($value) {
            return $value['owner_name'];
        });
        $ownerDetail = $ownerName->implode(',');
        $collectionApplications = collect($applicationDetails)->first();
        return new Collection([
            ['displayString' => 'Ward No.',             'key' => 'WardNo.',           'value' => $collectionApplications->ward_name],
            ['displayString' => 'Application No.',      'key' => 'ApplicationNo.',    'value' => $collectionApplications->application_no],
            ['displayString' => 'Owner Name',           'key' => 'OwnerName',         'value' => $ownerDetail],
            ['displayString' => 'Request Type',         'key' => 'RequestType',       'value' => $collectionApplications->charge_category],


        ]);
    }
    /**
     * return data of consumer owner data on behalf of disconnection 
     */
    public function getOwnerDetails($ownerDetails)
    {
        return collect($ownerDetails)->map(function ($value, $key) {
            return [
                $key + 1,
                $value['owner_name'],
                $value['guardian_name'],
                $value['mobile_no'],
                $value['email'],
                $value['city'],
                $value['district']
            ];
        });
    }

    /**
     * |Get the upoaded docunment
        | Serial No : 
        | Working
     */
    public function getDiscUploadDocuments(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWfActiveDocument    = new WfActiveDocument();
            $mWaterActiveRequestApplication = new WaterConsumerActiveRequest();
            $moduleId = Config::get('module-constants.WATER_MODULE_ID');

            $waterDetails = $mWaterActiveRequestApplication->getActiveRequest($req->applicationId)->first();
            if (!$waterDetails)
                throw new Exception("Application Not Found for this application Id");

            $workflowId = $waterDetails->workflow_id;
            $documents = $mWfActiveDocument->getWaterDocsByAppNo($req->applicationId, $workflowId, $moduleId);
            $returnData = collect($documents)->map(function ($value) {                          // Static
                $path =  $this->readDocumentPath($value->ref_doc_path);
                $value->doc_path = !empty(trim($value->ref_doc_path)) ? $path : null;
                return $value;
            });
            return responseMsgs(true, "Uploaded Documents", remove_null($returnData), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }
    /**
     * |----------------------------- Read the server url ------------------------------|
        | Serial No : 
     */
    public function readDocumentPath($path)
    {
        $path = (config('app.url') . "/" . $path);
        return $path;
    }

    /**
     * | Document Verify Reject
     * | @param req
        | Serial No :  
        | Discuss about the doc_upload_status should be 0 or not 
     */
    public function consumerDocVerifyReject(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'id'            => 'required|digits_between:1,9223372036854775807',
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                'docRemarks'    =>  $req->docStatus == "Rejected" ? 'required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/' : "nullable",
                'docStatus'     => 'required|in:Verified,Rejected'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            # Variable Assignments
            $mWfDocument                 = new WfActiveDocument();
            $mWaterActiveReqApplication  = new WaterConsumerActiveRequest();
            $mWfRoleusermap              = new WfRoleusermap();
            $wfDocId                     = $req->id;
            $applicationId               = $req->applicationId;
            $userId                      = authUser($req)->id;
            $wfLevel                     = Config::get('waterConstaint.ROLE-LABEL');

            # validating application
            $waterApplicationDtl = $mWaterActiveReqApplication->getActiveRequest($applicationId)
                ->firstOrFail();
            if (!$waterApplicationDtl || collect($waterApplicationDtl)->isEmpty())
                throw new Exception("Application Details Not Found");

            # validating roles
            $waterReq = new Request([
                'userId'        => $userId,
                'workflowId'    => $waterApplicationDtl['workflow_id']
            ]);
            $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfId($waterReq);
            if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                throw new Exception("Role Not Available");

            # validating role for DA
            $senderRoleId = $senderRoleDtls->wf_role_id;
            if ($senderRoleId != $wfLevel['DA'])                                    // Authorization for Dealing Assistant Only
                throw new Exception("You are not Authorized");

            # validating if full documet is uploaded
            $ifFullDocVerified = $this->ifFullDocVerified($applicationId);          // (Current Object Derivative Function 0.1)
            if ($ifFullDocVerified == 1)
                throw new Exception("Document Fully Verified");

            $this->begin();
            if ($req->docStatus == "Verified") {
                $status = 1;
            }
            if ($req->docStatus == "Rejected") {
                # For Rejection Doc Upload Status and Verify Status will disabled 
                $status = 2;
                // $waterApplicationDtl->doc_upload_status = 0;
                $waterApplicationDtl->doc_status = 0;
                $waterApplicationDtl->save();
            }
            $reqs = [
                'remarks'           => $req->docRemarks,
                'verify_status'     => $status,
                'action_taken_by'   => $userId
            ];

            $mWfDocument->docVerifyReject($wfDocId, $reqs);
            if ($req->docStatus == 'Verified')
                $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId);
            else
                $ifFullDocVerifiedV1 = 0;

            if ($ifFullDocVerifiedV1 == 1) {                                        // If The Document Fully Verified Update Verify Status
                $mWaterActiveReqApplication->updateAppliVerifyStatus($applicationId);
            }
            $this->commit();
            return responseMsgs(true, $req->docStatus . " Successfully", "", "010204", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check if the Document is Fully Verified or Not (0.1) | up
     * | @param
     * | @var 
     * | @return
        | Serial No :  
        | Working 
     */
    public function ifFullDocVerified($applicationId)
    {
        $mWaterApplication = new WaterConsumerActiveRequest();
        $mWfActiveDocument = new WfActiveDocument();
        $refapplication = $mWaterApplication->getActiveRequest($applicationId)
            ->firstOrFail();

        $refReq = [
            'activeId'      => $applicationId,
            'workflowId'    => $refapplication['workflow_id'],
            'moduleId'      => Config::get('module-constants.WATER_MODULE_ID')
        ];

        $req = new Request($refReq);
        $refDocList = $mWfActiveDocument->getDocsByActiveId($req);
        $ifPropDocUnverified = $refDocList->contains('verify_status', 0);
        if ($ifPropDocUnverified == true)
            return 0;
        else
            return 1;
    }

    # get Details of Disconnections
    public function getDetailsDisconnections(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'id'            => 'required|digits_between:1,9223372036854775807',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $user                           = authUser($request);
            $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
            $refUserType                    = Config::get('waterConstaint.REF_USER_TYPE');

            # User type changes 
            $detailsDisconnections = $mWaterConsumerActiveRequest->getApplicationsById($request->id)->first();
            if (!collect($detailsDisconnections)->first()) {
                throw new Exception("Data not found!");
            }
            return responseMsgs(true, "Data Disconnection", remove_null($detailsDisconnections), "", "1.0", "350ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", $e->getCode(), "1.0", "", 'POST', "");
        }
    }
}
