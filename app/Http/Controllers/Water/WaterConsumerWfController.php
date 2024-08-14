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
use App\Models\Masters\RefRequiredDocument;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterConsumerMeter;
use App\Http\Controllers\Water\WaterConsumer;
use App\MicroServices\DocUpload;
use App\Models\Water\WaterConnectionCharge;
use App\Models\Water\WaterTran;
use App\Models\Workflows\WfWardUser;
use App\Repository\Water\Concrete\WaterNewConnection;
use App\Repository\Water\Interfaces\IConsumer;
use App\Repository\Common\CommonFunction;

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
    protected $waterConsumer;
    protected $_COMMONFUNCTION;

    public function __construct(WaterConsumer $waterConsumer)
    {
        $this->_waterRoles      = Config::get('waterConstaint.ROLE-LABEL');
        $this->_waterModuleId   = Config::get('module-constants.WATER_MODULE_ID');
        $this->_DB_NAME = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->waterConsumer = $waterConsumer;
        $this->_COMMONFUNCTION = new CommonFunction();
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
            if ($req->workFlow == 204) {
                $workflowIds = ['204'];
            } else {
                $workflowIds = ['193'];
            }



            $inboxDetails = $this->getConsumerWfBaseQuerry($workflowIds, $ulbId)
                ->whereIn('water_consumer_active_requests.current_role', $roleId)
                ->where('water_consumer_active_requests.verify_status', 0)
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

            $workflowRoles = $this->getRoleIdByUserId($userId);
            $roleId = $workflowRoles->map(function ($value) {                         // Get user Workflow Roles
                return $value->wf_role_id;
            });
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');
            $outboxDetails = $this->getConsumerWfBaseQuerry($workflowIds, $ulbId)
                ->whereNotIn('water_consumer_active_requests.current_role', $roleId)
                // ->whereIn('water_consumer_active_requests.ward_mstr_id', $occupiedWards)
                // ->where('water_consumer_active_requests.verify_status', 1)
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
                if ($application->doc_verify_status == false)
                    throw new Exception("Document Not Fully Verified");
                break;
            case $wfLevels['JE']:
                // JE Condition in case of site adjustment
                if ($application->charge_catagory_id == 10) {
                    if ($application->is_field_verified == false) {
                        throw new Exception("Field Not Verified!");
                    }
                    if ($application->doc_verify_status == false) {
                        throw new Exception("Document Not Fully Verified");
                    }
                }
                // $siteDetails = $mWaterSiteInspection->getSiteDetails($application->id)
                //     ->where('order_officer', $refRole['JE'])
                //     ->first();
                // if (!$siteDetails) {
                //     throw new Exception("Site Not Verified!");
                // }
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
            $this->begin();
            $this->approvalRejectionWater($request, $roleId);
            $this->commit();
            return responseMsg(true, "Request approved/rejected successfully", "");;
        } catch (Exception $e) {
            $this->rollback();
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
        $mWaterConsumerMeter              = new WaterConsumerMeter();
        $mWaterConsumerDemand             = new WaterConsumerDemand();
        $mWaterConsumerOwner              = new WaterConsumerOwner();
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
        $checkconsumer = $mWaterConsumer->getConsumerById($approvedWater->consumer_id);
        if (!$checkconsumer) {
            throw new Exception("Consumer Not Found");
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
        $mwaterConsumerActiveRequest->updateVerifystatus($metaReqs, $request->status);
        $consumerOwnedetails = $mWaterConsumerOwner->getConsumerOwner($checkExist->consumer_id)->first();
        // Here update all the entities related to request of consumers
        if ($checkExist->charge_catagory_id == 2) { // This for Disconnection
            $mWaterConsumer->dissconnetConsumer($consumerOwnedetails->consumer_id);
        }

        # Prepare request data for updating consumer details
        $updateData = [];
        # Prepare request data for updating consumer details
        $updateData = [];
        if ($checkExist->charge_catagory_id == 6) {
            // Only pass tapsize when charge_catagory_id is 9
            $updateData = [
                'consumerId'    => $consumerOwnedetails->consumer_id,
                'mobile_no'     => $request->mobile_no ?? $consumerOwnedetails->mobile_no,
                'email'         => $request->email ?? $consumerOwnedetails->email,
                'applicant_name' => $request->applicant_name ?? $checkExist->new_name,
                'guardian_name' => $request->guardian_name ?? $consumerOwnedetails->guardian_name,
                'zoneId'        => $request->zoneId ?? $checkconsumer->zone_mstr_id,
                'wardId'        => $request->wardId ?? $checkconsumer->ward_mstr_id,
                'address'       => $request->address ?? $checkconsumer->address,
                'property_no'   => $request->property_no ?? $checkconsumer->property_no,
                'dtcCode'       => $request->dtcCode ?? $checkconsumer->dtc_code,
                'oldConsumerNo' => $request->oldConsumerNo ?? $checkconsumer->old_consumer_no,
                'category'      => $request->category ?? $checkconsumer->category,
                'propertytype'  => $request->propertytype ?? $checkconsumer->property_type_id,
                'landmark'      => $request->landmark ?? $checkconsumer->landmark,
                'document'      => $request->document,
                'remarks'       => $checkExist->remarks,
                'tapsize'       => $request->tapsize ?? $checkconsumer->tab_size,
            ];
        } elseif ($checkExist->charge_catagory_id == 7) {                                                                // this is change for 

            $updateData = [
                'consumerId'    => $consumerOwnedetails->consumer_id,
                'mobile_no'     => $request->mobile_no ?? $consumerOwnedetails->mobile_no,
                'email'         => $request->email ?? $consumerOwnedetails->email,
                'applicant_name' => $request->applicant_name ?? $consumerOwnedetails->applicant_name,
                'guardian_name' => $request->guardian_name ?? $consumerOwnedetails->guardian_name,
                'zoneId'        => $request->zoneId ?? $checkconsumer->zone_mstr_id,
                'wardId'        => $request->wardId ?? $checkconsumer->ward_mstr_id,
                'address'       => $request->address ?? $checkconsumer->address,
                'property_no'   => $request->property_no ?? $checkconsumer->property_no,
                'dtcCode'       => $request->dtcCode ?? $checkconsumer->dtc_code,
                'oldConsumerNo' => $request->oldConsumerNo ?? $checkconsumer->old_consumer_no,
                'category'      => $request->category ?? $checkconsumer->category,
                'propertytype'  => $request->propertytype ?? $checkconsumer->property_type_id,
                'landmark'      => $request->landmark ?? $checkconsumer->landmark,
                'document'      => $request->document,
                'remarks'       => $checkExist->remarks,
                'meterNo'       => $checkExist->meter_number,
            ];
        } elseif ($checkExist->charge_catagory_id == 8) {
            // Pass all other fields when charge_catagory_id is not 9
            $propertyTypeId = $checkExist->property_type == 'Residential' ? 1 : 2;
            $updateData = [
                'consumerId'    => $consumerOwnedetails->consumer_id,
                'mobile_no'     => $request->mobile_no ?? $consumerOwnedetails->mobile_no,
                'email'         => $request->email ?? $consumerOwnedetails->email,
                'applicant_name' => $request->applicant_name ?? $consumerOwnedetails->applicant_name,
                'guardian_name' => $request->guardian_name ?? $consumerOwnedetails->guardian_name,
                'zoneId'        => $request->zoneId ?? $checkconsumer->zone_mstr_id,
                'wardId'        => $request->wardId ?? $checkconsumer->ward_mstr_id,
                'address'       => $request->address ?? $checkconsumer->address,
                'property_no'   => $request->property_no ?? $checkconsumer->property_no,
                'dtcCode'       => $request->dtcCode ?? $checkconsumer->dtc_code,
                'oldConsumerNo' => $request->oldConsumerNo ?? $checkconsumer->old_consumer_no,
                'category'      => $request->category ?? $checkExist->category,
                'propertytype'  => $propertyTypeId,
                'landmark'      => $request->landmark ?? $checkconsumer->landmark,
                'document'      => $request->document,
                'remarks'       => $checkExist->remarks,
            ];
        } elseif ($checkExist->charge_catagory_id == 9) {
            // Pass all other fields when charge_catagory_id is not 9

            $updateData = [
                'consumerId'    => $consumerOwnedetails->consumer_id,
                'mobile_no'     => $request->mobile_no ?? $consumerOwnedetails->mobile_no,
                'email'         => $request->email ?? $consumerOwnedetails->email,
                'applicant_name' => $request->applicant_name ?? $consumerOwnedetails->applicant_name,
                'guardian_name' => $request->guardian_name ?? $consumerOwnedetails->guardian_name,
                'zoneId'        => $request->zoneId ?? $checkconsumer->zone_mstr_id,
                'wardId'        => $request->wardId ?? $checkconsumer->ward_mstr_id,
                'address'       => $request->address ?? $checkconsumer->address,
                'property_no'   => $request->property_no ?? $checkconsumer->property_no,
                'dtcCode'       => $request->dtcCode ?? $checkconsumer->dtc_code,
                'oldConsumerNo' => $request->oldConsumerNo ?? $checkconsumer->old_consumer_no,
                'category'      => $request->category ?? $checkExist->category,
                'propertytype'  => $$request->propertytype ?? $checkconsumer->property_type_id,
                'landmark'      => $request->landmark ?? $checkconsumer->landmark,
                'document'      => $request->document,
                'remarks'       => $checkExist->remarks,
                'tapsize'       => $request->tapsize ?? $checkExist->tab_size,
            ];
        }

        # Create a request object
        $updateRequest = new Request($updateData);

        # Call updateConsumerDetails function
        // $otherController = new WaterConsumer();
        $this->waterConsumer->updateConsumerDetails($updateRequest);
    }

    /**
     * |------------------- Final rejection of the Application -------------------|
     * | Transfer the data to new table
     */
    public function finalRejectionOfAppication($request)
    {
        $userId = authUser($request)->id;
        $mWaterConsumerActive  =  new WaterConsumerActiveRequest();
        $rejectedWater = WaterConsumerActiveRequest::query()
            ->where('id', $request->applicationId)
            ->first();


        # save record in track table 
        $waterTrack = new WorkflowTrack();
        $metaReqs['moduleId'] =  Config::get("module-constants.WATER_MODULE_ID");
        $metaReqs['workflowId'] = $rejectedWater->workflow_id;
        $metaReqs['refTableDotId'] = 'water_consumer_active_requests.id';
        $metaReqs['refTableIdValue'] = $rejectedWater->id;
        $metaReqs['user_id'] = authUser($request)->id;
        $request->request->add($metaReqs);
        $waterTrack->saveTrack($request);

        #update Verify Status
        $mWaterConsumerActive->updateVerifyComplainRequest($request, $userId);
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
            'apply_date' => collect($applicationDetails)->first()->apply_date,
            'charge_catagory_id' => $applicationDetails->pluck('charge_catagory_id')->first()
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
        $chargeCatgory =  $applicationDetails->pluck('charge_category');
        $chargeCatgoryId = $applicationDetails->pluck('charge_catagory_id')->first();
        $cardData = [
            'headerTitle' => $chargeCatgory,
            'data' => $cardDetails
        ];
        $fullDetailsData['fullDetailsData']['cardArray'] = new Collection($cardData);
        # TableArray
        $ownerView = [];
        if ($chargeCatgoryId != 10) {
            $ownerList = $this->getOwnerDetails($ownerDetail);
            $ownerView = [
                'headerTitle' => 'Owner Details',
                'tableHead' => ["#", "Owner Name", "Guardian Name", "Mobile No", "Email", "City", "District"],
                'tableData' => $ownerList
            ];
        }

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
     * Function for returning basic details data
     */
    public function getBasicDetails($applicationDetails)
    {
        $collectionApplications = collect($applicationDetails)->first();
        $basicDetails = [];

        // Common basic details
        $commonDetails = [
            ['displayString' => 'Ward No', 'key' => 'WardNo', 'value' => $collectionApplications->ward_name],
            ['displayString' => 'Apply Date', 'key' => 'applyDate', 'value' => $collectionApplications->apply_date],
            ['displayString' => 'Zone', 'key' => 'Zone', 'value' => $collectionApplications->zone_name],
            ['displayString' => 'Road Width', 'key' => 'RoadWidth', 'value' => $collectionApplications->per_meter],
            ['displayString' => 'Mobile Number', 'key' => 'MobileNumber', 'value' => $collectionApplications->basicmobile],
            ['displayString' => 'Road Type', 'key' => 'RoadType', 'value' => $collectionApplications->road_type],
            ['displayString' => 'Initial Reading', 'key' => 'InitialReading', 'value' => $collectionApplications->initial_reading],
            ['displayString' => 'Land Mark', 'key' => 'LandMark', 'value' => $collectionApplications->land_mark],
        ];

        // Additional details based on charge category ID
        if ($collectionApplications->charge_catagory_id != 10) {
            $basicDetails = array_merge($commonDetails, [
                ['displayString' => 'Tap Size', 'key' => 'tapSize', 'value' => $collectionApplications->tab_size],
                ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $collectionApplications->property_type],
                ['displayString' => 'Address', 'key' => 'Address', 'value' => $collectionApplications->address],
                ['displayString' => 'Category', 'key' => 'category', 'value' => $collectionApplications->category],
            ]);

            // Add specific fields based on the charge category ID
            switch ($collectionApplications->charge_catagory_id) {
                case 6:
                    $basicDetails[] = ['displayString' => 'New Name', 'key' => 'NewName', 'value' => $collectionApplications->new_name];
                    break;
                case 7:
                    $basicDetails[] = ['displayString' => 'New Meter No', 'key' => 'NewMeterNo', 'value' => $collectionApplications->newMeterNo];
                    $basicDetails[] = ['displayString' => 'Old Meter No', 'key' => 'OldMeterNo', 'value' => $collectionApplications->meter_no];
                    break;
                case 8:
                    $basicDetails[] = ['displayString' => 'New Property Type', 'key' => 'NewPropertyType', 'value' => $collectionApplications->newPropertyType];
                    $basicDetails[] = ['displayString' => 'New Category', 'key' => 'NewCategory', 'value' => $collectionApplications->newCategory];
                    break;
                case 9:
                    $basicDetails[] = ['displayString' => 'New Tap Size', 'key' => 'NewTapSize', 'value' => $collectionApplications->newTabSize];
                    break;
            }
        } else {
            $basicDetails = array_merge($commonDetails, [
                ['displayString' => 'City', 'key' => 'City',                    'value' => $collectionApplications->city],
                ['displayString' => 'ComplainerName', 'key' => 'ComplainerName', 'value' => $collectionApplications->respodent_name],
                ['displayString' => 'Address', 'key' => 'Address',                 'value' => $collectionApplications->complainAddress],
                ['displayString' => 'Mobile No', 'key' => 'mobileNo',               'value' => $collectionApplications->mobile_no],
                ['displayString' => 'Consumer No', 'key' => 'consumerNo',            'value' => $collectionApplications->consumerNoofCompain],
                ['displayString' => 'Je Status', 'key' => 'JeStatus',            'value' => $collectionApplications->je_status],
            ]);
        }

        return collect($basicDetails);
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
            ['displayString' => 'Request Type',         'key' => 'RequestType',       'value' => $collectionApplications->charge_category],
            ['displayString' => 'Consumer No ',         'key' => 'Consumer',           'value' => $collectionApplications->consumer_no],
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
                $value['applicant_name'],
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
            $authorizedRoles = [$wfLevel['DA'], $wfLevel['JE']];
            if (!in_array($senderRoleId, $authorizedRoles)) {                                    // Authorization for Dealing Assistant Only
                throw new Exception("You are not Authorized");
            }

            # validating if full documet is uploaded
            $ifFullDocVerified = $this->ifFullDocVerified($applicationId);                          // (Current Object Derivative Function 0.1)
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
                $waterApplicationDtl->doc_verify_status = false;
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
    /**
     * | Reupload Rejected Documents
     * | Function - 24
     * | API - 21
     */
    public function reuploadDocument(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'id' => 'required|digits_between:1,9223372036854775807',
            'image' => 'required|mimes:png,jpeg,pdf,jpg'
        ]);
        if ($validator->fails()) {
            return ['status' => false, 'message' => $validator->errors()];
        }
        try {
            // Variable initialization
            $mWaterConsumerActiveRequest = new WaterConsumerActiveRequest();
            $Image                   = $req->image;
            $docId                   = $req->id;
            $this->begin();
            $appId = $mWaterConsumerActiveRequest->reuploadDocument($req, $Image, $docId);
            $this->checkFullUpload($appId, $req);
            $this->commit();

            return responseMsgs(true, "Document Uploaded Successfully", "", "050821", 1.0, responseTime(), "POST", "", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, "Document Not Uploaded", "050821", 010717, 1.0, "", "POST", "", "");
        }
    }

    /**
     * | Check full document uploaded or not
     * | Function - 23
     */
    public function checkFullUpload($applicationId, $req)
    {
        $docCode = $req->docCode;
        $mWfActiveDocument = new WfActiveDocument();
        $mRefRequirement = new RefRequiredDocument();
        $moduleId = $this->_waterModuleId;
        $totalRequireDocs = $mRefRequirement->totalNoOfDocs($moduleId, $docCode);
        $appDetails = WaterConsumerActiveRequest::find($applicationId);
        $totalUploadedDocs = $mWfActiveDocument->totalUploadedDocs($applicationId, $appDetails->workflow_id, $moduleId);
        if ($totalRequireDocs == $totalUploadedDocs) {
            $appDetails->doc_upload_status = true;
            $appDetails->doc_verify_status = '0';
            $appDetails->parked = false;
            $appDetails->save();
        } else {
            $appDetails->doc_upload_status = '0';
            $appDetails->doc_verify_status = '0';
            $appDetails->save();
        }
    }
    /**
     * | get doc to upload on JE Side
     */
    public function getDocListForJe(Request $req)
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
            $mWaterApplication  = new WaterConsumerActiveRequest();
            $refWaterApplication = $mWaterApplication->getActiveReqById($req->applicationId)->first();                      // Get Saf Details
            if (!$refWaterApplication) {
                throw new Exception("Application Not Found for this id");
            }
            // $refWaterApplicant = $mWaterApplicant->getOwnerList($req->applicationId)->get();
            $documentList = $this->getWaterDocLists($refWaterApplication, $req);
            $waterTypeDocs['listDocs'] = collect($documentList)->map(function ($value, $key) use ($refWaterApplication) {
                return $this->filterDocument($value, $refWaterApplication)->first();
            });

            // $waterOwnerDocs['ownerDocs'] = collect($refWaterApplicant)->map(function ($owner) use ($refWaterApplication) {
            //     return $this->getOwnerDocLists($owner, $refWaterApplication);
            // });
            // $waterOwnerDocs;

            $totalDocLists = collect($waterTypeDocs); //->merge($waterOwnerDocs);
            $totalDocLists['docUploadStatus'] = $refWaterApplication->doc_upload_status;
            $totalDocLists['docVerifyStatus'] = $refWaterApplication->doc_status;
            return responseMsgs(true, "", remove_null($totalDocLists), "010203", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    /** 
     * |---------------------------- List of the doc to upload For Je ----------------------------|
     * | Calling function
     * | 01.01
        | Serial No :  
     */
    public function getWaterDocLists($application, $req)
    {
        $user           = authUser($req);
        $mRefReqDocs    = new RefRequiredDocument();
        $moduleId       = Config::get('module-constants.WATER_MODULE_ID');
        $refUserType    = Config::get('waterConstaint.REF_USER_TYPE');
        $type = [];
        if ($application->charge_catagory_id == 10) {
            $type = ["INSPECTION_REPORT"];
        } elseif ($application->charge_catagory_id == 11) {
            $type = ["PRESSURE REPORT"];
        } elseif ($application->charge_catagory_id == 12) {
            $type = ["QUALITY_REPORT"];
        }
        return $mRefReqDocs->getCollectiveDocByCode($moduleId, $type);
    }
    /**
     * |---------------------------- Filter The Document For Viewing ----------------------------|
     * | @param documentList
     * | @param refWaterApplication
     * | @param ownerId
     * | @var mWfActiveDocument
     * | @var applicationId
     * | @var workflowId
     * | @var moduleId
     * | @var uploadedDocs
     * | Calling Function 01.01.01/ 01.02.01
        | Serial No : 
     */
    public function filterDocument($documentList, $refWaterApplication, $ownerId = null)
    {
        $mWfActiveDocument  = new WfActiveDocument();
        $applicationId      = $refWaterApplication->id;
        $workflowId         = $refWaterApplication->workflow_id;
        $moduleId           = Config::get('module-constants.WATER_MODULE_ID');
        $uploadedDocs       = $mWfActiveDocument->getDocByRefIdsV4($applicationId, $workflowId, $moduleId);

        $explodeDocs = collect(explode('#', $documentList->requirements));
        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs, $ownerId, $documentList) {

            # var defining
            $document   = explode(',', $explodeDoc);
            $key        = array_shift($document);
            $label      = array_shift($document);
            $documents  = collect();

            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId, $documentList) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                if ($uploadedDoc) {
                    $path = $this->readDocumentPath($uploadedDoc->doc_path);
                    $fullDocPath = !empty(trim($uploadedDoc->doc_path)) ? $path : null;
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode"  => $item,
                        "ownerId"       => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath"       => $fullDocPath ?? "",
                        "verifyStatus"  => $uploadedDoc->verify_status ?? "",
                        "remarks"       => $uploadedDoc->remarks ?? "",
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType']      = $key;
            $reqDoc['uploadedDoc']  = $documents->last();
            $reqDoc['docName']      = substr($label, 1, -1);
            // $reqDoc['refDocName'] = substr($label, 1, -1);

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                if (isset($uploadedDoc)) {
                    $path =  $this->readDocumentPath($uploadedDoc->doc_path);
                    $fullDocPath = !empty(trim($uploadedDoc->doc_path)) ? $path : null;
                }
                $arr = [
                    "documentCode"  => $doc,
                    "docVal"        => ucwords($strReplace),
                    "uploadedDoc"   => $fullDocPath ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $uploadedDoc->verify_status ?? "",
                    "remarks"       => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });
        return $filteredDocs;
    }

    /**
     * | Upload Application Documents 
     * | @param req
        | Serial No :
        | Working 
        | Look on the concept of deactivation of the rejected documents 
        | Put the static "verify status" 2 in config  
     */
    public function uploadWaterDocJe(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "applicationId" => "required|numeric",
                "document"      => "required|mimes:pdf,jpeg,png,jpg|max:2048",
                "docCode"       => "required",
                "docCategory"   => "required",                                  // Recheck in case of undefined
                "ownerId"       => "nullable|numeric"
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user               = authUser($req);
            $metaReqs           = array();
            $applicationId      = $req->applicationId;
            $document           = $req->document;
            $docUpload          = new DocUpload;
            $mWfActiveDocument  = new WfActiveDocument();
            $mWaterApplication  = new WaterConsumerActiveRequest();
            $relativePath       = Config::get('waterConstaint.WATER_RELATIVE_PATH');
            $refmoduleId        = Config::get('module-constants.WATER_MODULE_ID');

            $getWaterDetails    = $mWaterApplication->fullWaterDetails($req)->firstOrFail();;
            $refImageName       = $req->docRefName;
            $refImageName       = $getWaterDetails->id . '-' . str_replace(' ', '_', $refImageName);
            $imageName          = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs = [
                'moduleId'      => $refmoduleId,
                'activeId'      => $getWaterDetails->id,
                'workflowId'    => $getWaterDetails->workflow_id,
                'ulbId'         => $getWaterDetails->ulb_id ?? 2,
                'relativePath'  => $relativePath,
                'document'      => $imageName,
                'docCode'       => $req->docCode,
                'ownerDtlId'    => $req->ownerId,
                'docCategory'   => $req->docCategory,
                'auth'          => $req->auth
            ];

            # Check the diff in user and citizen
            if ($user->user_type == "Citizen") {                                                // Static
                $isCitizen = true;
                $this->checkParamForDocUpload($isCitizen, $getWaterDetails, $user);
            } else {
                $isCitizen = false;
                $this->checkParamForDocUpload($isCitizen, $getWaterDetails, $user);
            }

            $this->begin();
            $ifDocExist = $mWfActiveDocument->isDocCategoryExists($getWaterDetails->id, $getWaterDetails->workflow_id, $refmoduleId, $req->docCategory, $req->ownerId)->first();   // Checking if the document is already existing or not
            $metaReqs = new Request($metaReqs);
            if (collect($ifDocExist)->isEmpty()) {
                $mWfActiveDocument->postDocuments($metaReqs);
            }
            if (collect($ifDocExist)->isNotEmpty()) {
                $mWfActiveDocument->editDocuments($ifDocExist, $metaReqs);
            }

            #check full doc upload
            // $refCheckDocument = $this->checkFullDocUpload($req);

            # Update the Doc Upload Satus in Application Table
            // if ($refCheckDocument->contains(false)) {
            //     $mWaterApplication->deactivateUploadStatus($applicationId);
            // } else {
            //     $this->updateWaterStatus($req, $getWaterDetails);
            // }

            # if the application is parked and btc s
            if ($getWaterDetails->parked == true) {
                $mWfActiveDocument->deactivateRejectedDoc($metaReqs);
                $refReq = new Request([
                    'applicationId' => $applicationId
                ]);
                $documentList = $this->getDocListForJe($refReq);
                $DocList = collect($documentList)['original']['data'];
                $refVerifyStatus = $DocList->where('doc_category', '!=', $req->docCategory)->pluck('verify_status');
                if (!in_array(2, $refVerifyStatus->toArray())) {                                    // Static "2"
                    $status = false;
                    $mWaterApplication->updateParkedstatus($status, $applicationId);
                }
            }
            $this->commit();
            return responseMsgs(true, "Document Uploadation Successful", "", "", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check if the params for document upload
     * | @param isCitizen
     * | @param applicantDetals
     * | @param user
        | Serial No : 
     */
    public function checkParamForDocUpload($isCitizen, $applicantDetals, $user)
    {
        $refWorkFlowMaster = Config::get('workflow-constants.WATER_MASTER_ID');
        switch ($isCitizen) {
                # For citizen 
                // case (true):
                //     if (!is_null($applicantDetals->current_role) && $applicantDetals->parked == true) {
                //         return true;
                //     }
                //     if (!is_null($applicantDetals->current_role)) {
                //         throw new Exception("You aren't allowed to upload document!");
                //     }
                //     break;
                # For user
                // case (false):
                //     $userId = $user->id;
                //     $ulbId = $applicantDetals->ulb_id;
                //     $role = $this->_commonFunction->getUserRoll($userId, $ulbId, $refWorkFlowMaster);
                //     if (is_null($role)) {
                //         throw new Exception("You dont have any role!");
                //     }
                //     if ($role->can_upload_document != true) {
                //         throw new Exception("You dont have permission to upload Document!");
                //     }
                //     break;
        }
    }

    /**
     * | Caheck the Document if Fully Upload or not
     * | @param req
        | Up
        | Serial No :
     */
    public function checkFullDocUpload($req)
    {
        # Check the Document upload Status
        $documentList = $this->getDocToUpload($req);
        $refDoc = collect($documentList)['original']['data']['documentsList'];
        // $refOwnerDoc = collect($documentList)['original']['data']['ownersDocList'];
        $checkDocument = collect($refDoc)->map(function ($value, $key) {
            if ($value['isMadatory'] == 1) {
                $doc = collect($value['uploadDoc'])->first();
                if (is_null($doc)) {
                    return false;
                }
                return true;
            }
            return true;
        });
        // $checkOwnerDocument = collect($refOwnerDoc)->map(function ($value) {
        //     if ($value['isMadatory'] == 1) {
        //         $doc = collect($value['uploadDoc'])->first();
        //         if (is_null($doc)) {
        //             return false;
        //         }
        //         return true;
        //     }
        //     return true;
        // });
        return $checkDocument; //->merge($checkOwnerDocument);
    }
    /**
     * | Updating the water Application Status
     * | @param req
     * | @param application
        | Serial No :  
        | Up 
        | Check the concept of auto forward
     */
    public function updateWaterStatus($req, $application)
    {
        $mWaterTran         = new WaterTran();
        $mWaterApplication  = new WaterConsumerActiveRequest();
        $refApplyFrom       = Config::get("waterConstaint.APP_APPLY_FROM");
        $mWaterApplication->activateUploadStatus($req->applicationId);
        # Auto forward to Da
        if ($application->payment_status == 1) {
            $waterTransaction = $mWaterTran->getTransNo($application->id, true)->first();
            if (is_null($waterTransaction)) {
                throw new Exception("Transaction Details not found!");
            }
            if ($application->apply_from == $refApplyFrom['1']) {
                $this->autoForwardProcess($waterTransaction, $req, $application);
            }
        }
    }
    /**
     * | Auto forward process 
        | Serial No : 
     */
    public function autoForwardProcess($waterTransaction, $req, $application)
    {
        $waterRoles         = $this->_waterRoles;
        $refChargeCatagory  = Config::get("waterConstaint.CHARGE_CATAGORY");
        $mWaterApplication  = new WaterConsumerActiveRequest();
        if ($waterTransaction->tran_type == $refChargeCatagory['SITE_INSPECTON']) {
            throw new Exception("Error there is different charge catagory in application!");
        }
        if ($application->current_role == null) {
            $mWaterApplication->updateCurrentRoleForDa($req->applicationId, $waterRoles['DA']);
        }
    }

    /**
     * | Get the document to be upoaded with list of dock uploaded 
        | Serial No :  
        | Working / Citizen Upload
     */
    public function getDocToUpload(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|numeric'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $user                   = authUser($request);
            $refApplication         = (array)null;
            $refOwneres             = (array)null;
            $requiedDocs            = (array)null;
            $testOwnersDoc          = (array)null;
            $data                   = (array)null;
            $refWaterNewConnection  = new WaterNewConnection();
            $refWfActiveDocument    = new WfActiveDocument();
            $mWaterConnectionCharge = new WaterConnectionCharge();
            $mWaterApplication      = new WaterConsumerActiveRequest();   // Application 
            $mWaterApprovalApplications = new WaterApprovalApplicationDetail();
            $moduleId               = Config::get('module-constants.WATER_MODULE_ID');

            $connectionId = $request->applicationId;
            $refApplication = $mWaterApplication->getApplicationDtls($connectionId);
            if ($refApplication == null) {
                $refApplication = $mWaterApprovalApplications->getApplicationById($connectionId)->first();
            }
            if (!$refApplication) {
                throw new Exception("Application Not Found!");
            }
            $connectionCharges = $mWaterConnectionCharge->getWaterchargesById($connectionId)
                ->where('charge_category', '!=', "Site Inspection")                         # Static
                ->first();
            $connectionCharges['type'] = Config::get('waterConstaint.New_Connection');
            $connectionCharges['applicationNo'] = $refApplication->application_no;
            $connectionCharges['applicationId'] = $refApplication->id;

            $requiedDocType = $refWaterNewConnection->getDocumentTypeList($refApplication, $user);  # get All Related Document Type List
            $refOwneres = $refWaterNewConnection->getOwnereDtlByLId($refApplication->id);    # get Owneres List
            $ownerList = collect($refOwneres)->map(function ($value) {
                $return['applicant_name'] = $value['applicant_name'];
                $return['ownerID'] = $value['id'];
                return $return;
            });
            foreach ($requiedDocType as $val) {
                $doc = (array) null;
                $doc["ownerName"] = $ownerList;
                $doc['docName'] = $val->doc_for;
                $refDocName  = str_replace('_', ' ', $val->doc_for);
                $doc["refDocName"] = ucwords(strtolower($refDocName));
                $doc['isMadatory'] = $val->is_mandatory;
                $ref['docValue'] = $refWaterNewConnection->getDocumentList($val->doc_for);  # get All Related Document List
                $doc['docVal'] = $docFor = collect($ref['docValue'])->map(function ($value) {
                    $refDoc = $value['doc_name'];
                    $refText = str_replace('_', ' ', $refDoc);
                    $value['dispayName'] = ucwords(strtolower($refText));
                    return $value;
                });
                $docFor = collect($ref['docValue'])->map(function ($value) {
                    return $value['doc_name'];
                });

                $doc['uploadDoc'] = [];
                $uploadDoc = $refWfActiveDocument->getDocByRefIdsDocCodeV2($refApplication->id, $refApplication->workflow_id, $moduleId, $docFor); # Check Document is Uploaded Of That Type
                if (isset($uploadDoc->first()->doc_path)) {
                    $path = $refWaterNewConnection->readDocumentPath($uploadDoc->first()->doc_path);
                    $doc["uploadDoc"]["doc_path"] = !empty(trim($uploadDoc->first()->doc_path)) ? $path : null;
                    $doc["uploadDoc"]["doc_code"] = $uploadDoc->first()->doc_code;
                    $doc["uploadDoc"]["verify_status"] = $uploadDoc->first()->verify_status;
                }
                array_push($requiedDocs, $doc);
            }
            // foreach ($refOwneres as $key => $val) {
            //     $docRefList = ["ID_PROOF"];
            //     foreach ($docRefList as $key => $refOwnerDoc) {
            //         $doc = (array) null;
            //         $testOwnersDoc[] = (array) null;
            //         $doc["ownerId"] = $val->id;
            //         $doc["ownerName"] = $val->applicant_name;
            //         $doc["docName"]   = $refOwnerDoc;
            //         $refDocName  = str_replace('_', ' ', $refOwnerDoc);
            //         $doc["refDocName"] = ucwords(strtolower($refDocName));
            //         $doc['isMadatory'] = 1;
            //         $ref['docValue'] = $refWaterNewConnection->getDocumentList([$refOwnerDoc]);   #"CONSUMER_PHOTO"
            //         $doc['docVal'] = $docFor = collect($ref['docValue'])->map(function ($value) {
            //             $refDoc = $value['doc_name'];
            //             $refText = str_replace('_', ' ', $refDoc);
            //             $value['dispayName'] = ucwords(strtolower($refText));
            //             return $value;
            //         });
            //         $refdocForId = collect($ref['docValue'])->map(function ($value, $key) {
            //             return $value['doc_name'];
            //         });
            //         $doc['uploadDoc'] = [];
            //         $uploadDoc = $refWfActiveDocument->getOwnerDocByRefIdsDocCode($refApplication->id, $refApplication->workflow_id, $moduleId, $refdocForId, $doc["ownerId"]); # Check Document is Uploaded Of That Type
            //         if (isset($uploadDoc->first()->doc_path)) {
            //             $path = $refWaterNewConnection->readDocumentPath($uploadDoc->first()->doc_path);
            //             $doc["uploadDoc"]["doc_path"] = !empty(trim($uploadDoc->first()->doc_path)) ? $path : null;
            //             $doc["uploadDoc"]["doc_code"] = $uploadDoc->first()->doc_code;
            //             $doc["uploadDoc"]["verify_status"] = $uploadDoc->first()->verify_status;
            //         }
            //         array_push($testOwnersDoc, $doc);
            //     }
            // }
            // $ownerDoc = collect($testOwnersDoc)->filter()->values();

            $data["documentsList"]  = $requiedDocs;
            // $data["ownersDocList"]  = $ownerDoc;
            $data['doc_upload_status'] = $refApplication['doc_upload_status'];
            $data['connectionCharges'] = $connectionCharges;
            return responseMsg(true, "Document Uploaded!", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
    /**
     * | unauthorized tap connnection status
     */
    public function unauthorizedTapUpdateStatus(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|numeric',
                'checkcompalin' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $user = authUser($request);
            $userName = $user->name;
            if ($userName != 'JE') {
                throw new Exception('You Are Not Authorized Person');
            }
            $applicationId = $request->applicationId;
            $mWaterConsumerActiveRequest  = new WaterConsumerActiveRequest();
            $waterRequestdtl  = $mWaterConsumerActiveRequest->getActiveReqById($applicationId)->first();
            if (!$waterRequestdtl) {
                throw new Exception('Application Details Not Found');
            }
            $mWaterConsumerActiveRequest->updateJeStatus($request);
            return responseMsg(true, "Status Updated!", $request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    /**
     * | Back to Citizen 
     * | @param req
        | Check if the current role of the application will be changed for iniciater role 
     */
    public function backToCitizen(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|integer',
                'comment'       => 'required|string'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user               = authUser($req);
            $WorkflowTrack      = new WorkflowTrack();
            $refWorkflowId      = Config::get("workflow-constants.WATER_MASTER_ID");
            $refApplyFrom       = config::get("waterConstaint.APP_APPLY_FROM");
            $mWaterApplication  = WaterConsumerActiveRequest::findOrFail($req->applicationId);

            $role = $this->_COMMONFUNCTION->getUserRoll($user->id, $mWaterApplication->ulb_id, $refWorkflowId);
            $this->btcParamcheck($role, $mWaterApplication);

            $this->begin();
            # if application is not applied by citizen 
            if ($mWaterApplication->apply_from != $refApplyFrom['1']) {
                $mWaterApplication->current_role = $mWaterApplication->last_role_id;
                $mWaterApplication->parked = true;                          //  Pending Status true
                $mWaterApplication->doc_upload_status = false;              //  Docupload Status false
            }
            # if citizen applied 
            else {
                $mWaterApplication->parked = true;                          //  Pending Status true
                $mWaterApplication->doc_upload_status = false;              //  Docupload Status false
            }
            $mWaterApplication->save();
            $metaReqs['moduleId']           = Config::get('module-constants.WATER_MODULE_ID');
            $metaReqs['workflowId']         = $mWaterApplication->workflow_id;
            $metaReqs['refTableDotId']      = 'water_approval_application_details.id';
            $metaReqs['refTableIdValue']    = $req->applicationId;
            $metaReqs['senderRoleId']       = $role->role_id;
            $req->request->add($metaReqs);
            $WorkflowTrack->saveTrack($req);

            $this->commit();
            return responseMsgs(true, "Successfully Done", "", "", "1.0", "350ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    /**
     * | check the application for back to citizen case
     * | check for the
        | Check who can use BTC operatio 
     */
    public function btcParamcheck($role, $mWaterApplication)
    {
        $refReq = new Request([
            "applicationId" => $mWaterApplication->id
        ]);
        $rawDoc = $this->getUploadDocuments($refReq);
        if ($role->is_btc != true) {
            throw new Exception("You dont have permission to BTC!");
        }
        if ($mWaterApplication->current_role != $role->role_id) {
            throw new Exception("the application is not under your possession!");
        }
        $activeDocs = collect($rawDoc)['original']['data'];
        $canBtc = $activeDocs->contains(function ($item) {
            return $item['verify_status'] == 2;
        });
        if (!$canBtc) {
            throw new Exception("Document not rejected! cannot perform BTC!");
        }
    }
    /**
     * |Get the upoaded docunment
        | Serial No : 
        | Working
     */
    public function getUploadDocuments(Request $req)
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
            $mWfActiveDocument = new WfActiveDocument();
            $mWaterApplication = new WaterConsumerActiveRequest();
            $mWaterApprovalApplications = new WaterApprovalApplicationDetail();
            $moduleId = Config::get('module-constants.WATER_MODULE_ID');

            $waterDetails = $mWaterApplication->getActiveReqById($req->applicationId)->first();
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

    public function btcInbox(Request $req)
    {
        try {
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $mDeviceId = $req->deviceId ?? "";

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $waterList = $this->getWaterRequestList($workflowIds, $ulbId)
                ->whereIn('water_consumer_active_requests.current_role', $roleId)
                // ->whereIn('water_approval_application_details.ward_id', $occupiedWards)
                ->where('water_consumer_active_requests.is_escalate', false)
                ->where('water_consumer_active_requests.parked', true)
                ->orderByDesc('water_consumer_active_requests.id')
                ->get();
            $filterWaterList = collect($waterList)->unique('id')->values();
            return responseMsgs(true, "BTC Inbox List", remove_null($filterWaterList), "", 1.0, "560ms", "POST", $mDeviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010123, 1.0, "271ms", "POST",);
        }
    }
    /**
     * | Application's Post Escalated
        | Serial No :
     */
    public function postEscalate(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "escalateStatus" => "required|int",
                "applicationId" => "required|int",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $userId = authUser($request)->id;
            $applicationId = $request->applicationId;
            $applicationsData = WaterConsumerActiveRequest::find($applicationId);
            if (!$applicationsData) {
                throw new Exception("Application details not found!");
            }
            $applicationsData->is_escalate = $request->escalateStatus;
            $applicationsData->escalate_by = $userId;
            $applicationsData->save();
            return responseMsgs(true, $request->escalateStatus == 1 ? 'Water is Escalated' : "Water is removed from Escalated", '', "", "1.0", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    /**
     * | Water Special Inbox
     * | excalated applications
        | Serial No :
     */
    public function waterSpecialInbox(Request $request)
    {
        try {
            $mWfWardUser            = new WfWardUser();
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();
            $userId = authUser($request)->id;
            $ulbId  = authUser($request)->ulb_id;

            $occupiedWard = $mWfWardUser->getWardsByUserId($userId);                        // Get All Occupied Ward By user id using trait
            $wardId = $occupiedWard->map(function ($item, $key) {                           // Filter All ward_id in an array using laravel collections
                return $item->ward_id;
            });

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $waterData = $this->getConsumerWfBaseQuerry($workflowIds, $ulbId)                              // Repository function to get SAF Details
                ->where('water_consumer_active_requests.is_escalate', 1)
                // ->whereIn('water_consumer_active_requests.ward_id', $wardId)
                ->orderByDesc('water_consumer_active_requests.id')
                ->get();
            $filterWaterList = collect($waterData)->unique('id')->values();
            return responseMsgs(true, "Data Fetched", remove_null($filterWaterList), "010107", "1.0", "251ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "0.1", ".ms", "POST", $request->deviceId);
        }
    }
}
