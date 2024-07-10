<?php

namespace App\Repository\Water\Concrete;

use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\CustomDetail;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropApartmentDtl;
use App\Models\Property\PropProperty;
use App\Models\Ulb\UlbNewWardmap;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterApplicant;
use App\Models\Water\WaterApplication;
use App\Models\Water\WaterApprovalApplicant;
use App\Models\Water\WaterApprovalApplicationDetail;
use App\Models\Water\WaterConnectionCharge;
use App\Models\Water\WaterConsumer;
use App\Models\Water\WaterConsumerOwner;
use App\Models\Water\WaterParamConnFee;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterSiteInspection;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterTranDetail;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Repository\Water\Interfaces\iNewConnection;
use App\Traits\Ward;
use App\Traits\Workflow\Workflow;
use App\Traits\Property\SAF;
use App\Traits\Water\WaterTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use Illuminate\Validation\Rules\Exists;
use Nette\Utils\Random;

/**
 * | -------------- Repository for the New Water Connection Operations ----------------------- |
 * | Created On-07-10-2022 
 * | Created By-Anshu Kumar
 * | Created By-Sam kerketta
 */

class NewConnectionRepository implements iNewConnection
{
    use SAF;
    use Workflow;
    use Ward;
    use WaterTrait;

    private $_dealingAssistent;
    private $_vacantLand;
    private $_waterWorkflowId;
    private $_waterModulId;
    private $_juniorEngRoleId;
    private $_waterRoles;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct()
    {
        $this->_dealingAssistent = Config::get('workflow-constants.DEALING_ASSISTENT_WF_ID');
        $this->_vacantLand = Config::get('PropertyConstaint.VACANT_LAND');
        $this->_waterWorkflowId = Config::get('workflow-constants.WATER_MASTER_ID');
        $this->_waterModulId = Config::get('module-constants.WATER_MODULE_ID');
        $this->_juniorEngRoleId  = Config::get('workflow-constants.WATER_JE_ROLE_ID');
        $this->_waterRoles = Config::get('waterConstaint.ROLE-LABEL');
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
     * | -------------------------  Apply for the new Application for Water Application  --------------------- |
     * | @param req
     * | @var vacantLand
     * | @var workflowID
     * | @var ulbId
     * | @var ulbWorkflowObj : object for the model (WfWorkflow)
     * | @var ulbWorkflowId : calling the function on model:WfWorkflow 
     * | @var objCall : object for the model (WaterNewConnection)
     * | @var newConnectionCharges :
     * | Post the value in Water Application table
     * | post the value in Water Applicants table by loop
     * | 
     * | rating : 5
     * ------------------------------------------------------------------------------------
     * | Generating the demand amount for the applicant in Water Connection Charges Table 
        | Serila No : 01
        | Check the ulb_id
        | make Application No using id generation ✔
        | send it in track / while sending the record to track through jsk check the role id 
     */
    public function store(Request $req)
    {
        # ref variables
        $user       = authUser($req);
        $vacantLand = $this->_vacantLand;
        $workflowID = $this->_waterWorkflowId;
        $waterRoles = $this->_waterRoles;
        $owner      = $req['owners'];
        $tenant     = $req['tenant'];
        $ulbId      = $req->ulbId;
        $reftenant  = true;
        $citizenId  = null;

        $ulbWorkflowObj             = new WfWorkflow();
        $mWaterNewConnection        = new WaterNewConnection();
        $objNewApplication          = new WaterApplication();
        $mWaterApplicant            = new WaterApplicant();
        $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();
        $mWaterConnectionCharge     = new WaterConnectionCharge();
        $mWaterTran                 = new WaterTran();
        $waterTrack                 = new WorkflowTrack();
        $refParamId                 = Config::get('waterConstaint.PARAM_IDS');

        # Connection Type 
        switch ($req->connectionTypeId) {
            case (1):
                $connectionType = "New Connection";                                     // Static
                break;

            case (2):
                $connectionType = "Regulaization";                                      // Static
                break;
        }

        # check the property type on vacant land
        $checkResponse = $this->checkVacantLand($req, $vacantLand);
        if ($checkResponse) {
            return $checkResponse;
        }

        # get initiater and finisher
        $ulbWorkflowId = $ulbWorkflowObj->getulbWorkflowId($workflowID, $ulbId);
        if (!$ulbWorkflowId) {
            throw new Exception("Respective Ulb is not maped to Water Workflow!");
        }
        $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);
        $refFinisherRoleId  = $this->getFinisherId($ulbWorkflowId->id);
        $finisherRoleId     = DB::select($refFinisherRoleId);
        $initiatorRoleId    = DB::select($refInitiatorRoleId);
        if (!$finisherRoleId || !$initiatorRoleId) {
            throw new Exception("initiatorRoleId or finisherRoleId not found for respective Workflow!");
        }

        # Generating Demand 
        $newConnectionCharges = objToArray($mWaterNewConnection->calWaterConCharge($req));
        if (!$newConnectionCharges['status']) {
            throw new Exception(
                $newConnectionCharges['errors']
            );
        }
        $installment            = $newConnectionCharges['installment_amount'];
        $waterFeeId             = $newConnectionCharges['water_fee_mstr_id'];
        $totalConnectionCharges = $newConnectionCharges['conn_fee_charge']['amount'];

        $this->begin();
        # Generating Application No
        $idGeneration   = new PrefixIdGenerator($refParamId["WAPP"], $ulbId);
        $applicationNo  = $idGeneration->generate();
        $applicationNo  = str_replace('/', '-', $applicationNo);

        # water application
        $applicationId = $objNewApplication->saveWaterApplication($req, $ulbWorkflowId, $initiatorRoleId, $finisherRoleId, $ulbId, $applicationNo, $waterFeeId, $newConnectionCharges);
        # water applicant
        foreach ($owner as $owners) {
            $mWaterApplicant->saveWaterApplicant($applicationId, $owners, null);
        }
        # water applicant in case of tenant
        if (!empty($tenant)) {
            foreach ($tenant as $tenants) {
                $mWaterApplicant->saveWaterApplicant($applicationId, $tenants, $reftenant);
            }
        }
        # connection charges
        $connectionId = $mWaterConnectionCharge->saveWaterCharge($applicationId, $req, $newConnectionCharges);
        # water penalty
        if (!empty($installment)) {
            foreach ($installment as $installments) {
                $mWaterPenaltyInstallment->saveWaterPenelty($applicationId, $installments, $connectionType, $connectionId, null);
            }
        }
        # in case of connection charge is 0
        if ($totalConnectionCharges == 0) {
            $mWaterTran->saveZeroConnectionCharg($totalConnectionCharges, $ulbId, $req, $applicationId, $connectionId, $connectionType);
            if ($user->user_type != "Citizen") {                                                    // Static
                $objNewApplication->updateCurrentRoleForDa($applicationId, $waterRoles['BO']);
            }
        }

        # Save the record in the tracks
        if ($user->user_type == "Citizen") {                                                        // Static
            $receiverRoleId = $waterRoles['DA'];
        }
        if ($user->user_type != "Citizen") {                                                        // Static
            $receiverRoleId = collect($initiatorRoleId)->first()->role_id;
        }
        $metaReqs = new Request(
            [
                'citizenId'         => $citizenId,
                'moduleId'          => $this->_waterModulId,
                'workflowId'        => $ulbWorkflowId['id'],
                'refTableDotId'     => 'water_applications.id',                                     // Static
                'refTableIdValue'   => $applicationId,
                'user_id'           => $user->id,
                'ulb_id'            => $ulbId,
                'senderRoleId'      => $senderRoleId ?? null,
                'receiverRoleId'    => $receiverRoleId ?? null
            ]
        );
        $waterTrack->saveTrack($metaReqs);

        # watsapp message
        // Register_message
        // $whatsapp2 = (Whatsapp_Send(
        //     "",
        //     "trn_2_var",
        //     [
        //         "conten_type" => "text",
        //         [
        //             $owner[0]["ownerName"],
        //             $applicationNo,
        //         ]
        //     ]
        // ));
        $this->commit();
        $returnResponse = [
            'applicationNo' => $applicationNo,
            'applicationId' => $applicationId
        ];
        return responseMsgs(true, "Successfully Saved!", $returnResponse, "", "02", "", "POST", "");
    }


    /**
     * |--------------------------------- Check property for the vacant land ------------------------------|
     * | @param req
     * | @param vacantLand
     * | @param isExist
     * | @var propetySafCheck
     * | @var propetyHoldingCheck
     * | Operation : check if the applied application is in vacant land 
        | Serial No : 01.02
     */
    public function checkVacantLand($req, $vacantLand)
    {
        switch ($req) {
            case (!is_null($req->safNo) && $req->connection_through == 2):                           // Static
                $isExist = $this->checkPropertyExist($req);
                if ($isExist) {
                    $propetySafCheck = PropActiveSaf::select('prop_type_mstr_id')
                        ->where('saf_no', $req->safNo)
                        ->where('ulb_id', $req->ulbId)
                        ->first();
                    if ($propetySafCheck->prop_type_mstr_id == $vacantLand) {
                        return responseMsg(false, "water cannot be applied on Vacant land!", "");
                    }
                } else {
                    return responseMsg(false, "Saf Not Exist!", $req->safNo);
                }
                break;
            case (!is_null($req->holdingNo) && $req->connection_through == 1):
                $isExist = $this->checkPropertyExist($req);
                if ($isExist) {
                    $propetyHoldingCheck = PropProperty::select('prop_type_mstr_id')
                        ->where('new_holding_no', $req->holdingNo)
                        ->orwhere('holding_no', $req->holdingNo)
                        ->where('ulb_id', $req->ulbId)
                        ->first();
                    if ($propetyHoldingCheck->prop_type_mstr_id == $vacantLand) {
                        return responseMsg(false, "water cannot be applied on Vacant land!", "");
                    }
                } else {
                    return responseMsg(false, "Holding Not Exist!", $req->holdingNo);
                }
                break;
        }
    }


    /**
     * |---------------------------------------- check if the porperty ie,(saf/holdin) Exist ------------------------------------------------|
     * | @param req
     * | @var safCheck
     * | @var holdingCheck
     * | @return value : true or nothing 
        | Serial No : 01.02.01
     */
    public function checkPropertyExist($req)
    {
        switch ($req) {
            case (!is_null($req->safNo) && $req->connection_through == 2): {
                    $safCheck = PropActiveSaf::select(
                        'id',
                        'saf_no'
                    )
                        ->where('saf_no', $req->safNo)
                        ->where('ulb_id', $req->ulbId)
                        ->first();
                    if ($safCheck) {
                        return true;
                    }
                }
            case (!is_null($req->holdingNo) && $req->connection_through == 1): {
                    $holdingCheck = PropProperty::select(
                        'id',
                        'new_holding_no'
                    )
                        ->where('new_holding_no', $req->holdingNo)
                        ->orwhere('holding_no', $req->holdingNo)
                        ->where('ulb_id', $req->ulbId)
                        ->first();
                    if ($holdingCheck) {
                        return true;
                    }
                }
        }
    }

    /**
     * |---------------------------------------- Get the user Role details and the details of forword and backword details ------------------------------------------------|
     * | @param user
     * | @param ulbWorkflowId
        | Serial No : 01.03  
     */
    public function getUserRolesDetails($user, $ulbWorkflowId)
    {
        $mWfRoleUsermap = new WfRoleUsermap();
        $userId = $user->id;
        $getRoleReq = new Request([                                                 // make request to get role id of the user
            'userId' => $userId,
            'workflowId' => $ulbWorkflowId
        ]);
        $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
        if (is_null($readRoleDtls)) {
            throw new Exception("Role details not found!");
        }
        return $readRoleDtls;
    }


    /**
     * |------------------------------------------ Post Application to the next level ---------------------------------------|
     * | @param req
     * | @var metaReqs
     * | @var waterTrack
     * | @var waterApplication
        | Serial No : 04
        | Working 
        | Check for the commented code 
     */
    public function postNextLevel($req)
    {
        $mWfWorkflows       = new WfWorkflow();
        $waterTrack         = new WorkflowTrack();
        $mWfRoleMaps        = new WfWorkflowrolemap();
        $current            = Carbon::now();
        $wfLevels           = Config::get('waterConstaint.ROLE-LABEL');
        $waterApplication   = WaterApplication::find($req->applicationId);

        # Derivative Assignments
        $senderRoleId = $waterApplication->current_role;
        $ulbWorkflowId = $waterApplication->workflow_id;
        $ulbWorkflowMaps = $mWfWorkflows->getWfDetails($ulbWorkflowId);
        $roleMapsReqs = new Request([
            'workflowId' => $ulbWorkflowMaps->id,
            'roleId' => $senderRoleId
        ]);
        $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIds($roleMapsReqs);

        $this->begin();
        if ($req->action == 'forward') {
            $this->checkPostCondition($senderRoleId, $wfLevels, $waterApplication);            // Check Post Next level condition
            if ($waterApplication->current_role == $wfLevels['JE']) {
                $waterApplication->is_field_verified = true;
            }
            $metaReqs['verificationStatus'] = 1;
            $metaReqs['receiverRoleId']     = $forwardBackwardIds->forward_role_id;
            $waterApplication->current_role = $forwardBackwardIds->forward_role_id;
            $waterApplication->last_role_id =  $forwardBackwardIds->forward_role_id;                                      // Update Last Role Id

        }
        if ($req->action == 'backward') {
            $metaReqs['receiverRoleId']     = $forwardBackwardIds->backward_role_id;
            $waterApplication->current_role = $forwardBackwardIds->backward_role_id;
        }

        $waterApplication->save();
        $metaReqs['moduleId']           = $this->_waterModulId;
        $metaReqs['workflowId']         = $waterApplication->workflow_id;
        $metaReqs['refTableDotId']      = 'water_applications.id';                                                          // Static
        $metaReqs['refTableIdValue']    = $req->applicationId;
        $metaReqs['senderRoleId']       = $senderRoleId;
        $metaReqs['user_id']            = authUser($req)->id;
        $metaReqs['trackDate']          = $current->format('Y-m-d H:i:s');
        $req->request->add($metaReqs);
        $waterTrack->saveTrack($req);

        # check in all the cases the data if entered in the track table 
        // Updation of Received Date
        $preWorkflowReq = [
            'workflowId'        => $waterApplication->workflow_id,
            'refTableDotId'     => "water_applications.id",
            'refTableIdValue'   => $req->applicationId,
            'receiverRoleId'    => $senderRoleId
        ];

        $previousWorkflowTrack = $waterTrack->getWfTrackByRefId($preWorkflowReq);
        $previousWorkflowTrack->update([
            'forward_date' => $current->format('Y-m-d'),
            'forward_time' => $current->format('H:i:s')
        ]);
        $this->commit();
        return responseMsgs(true, "Successfully Forwarded The Application!!", "", "", "", '01', '.ms', 'Post', '');
    }

    /**
     * | check Post Condition for backward forward
        | Serial No : 04.01
        | working 
     */
    public function checkPostCondition($senderRoleId, $wfLevels, $application)
    {
        $mWaterSiteInspection = new WaterSiteInspection();
        $refRole = Config::get("waterConstaint.ROLE-LABEL");
        switch ($senderRoleId) {
            case $wfLevels['BO']:                                                                       // Back Office Condition
                if ($application->doc_upload_status == false)
                    throw new Exception("Document Not Fully Uploaded or Payment in not Done!");
                break;
            case $wfLevels['DA']:                                                                       // DA Condition
                if ($application->doc_status == false)
                    throw new Exception("Document Not Fully Verified");
                break;
            case $wfLevels['JE']:                                                                       // JE Coditon in case of site adjustment
                if ($application->doc_status == false)
                    throw new Exception("Document Not Fully Verified or Payment in not Done!");
                if ($application->doc_upload_status == false) {
                    throw new Exception("Document Not Fully Uploaded");
                }
                $siteDetails = $mWaterSiteInspection->getSiteDetails($application->id)
                    // ->where('order_officer', $refRole['JE'])
                    // ->where('payment_status', 1)
                    ->first();
                if (!$siteDetails) {
                    throw new Exception("Site Not Verified!");
                }
                break;
            case $wfLevels['SH']:                                                                       // SH conditional checking
                if ($application->doc_status == false || $application->payment_status != 1)
                    throw new Exception("Document Not Fully Verified or Payment in not Done!");
                if ($application->doc_upload_status == false || $application->is_field_verified == false) {
                    throw new Exception("Document Not Fully Uploaded or site inspection not done!");
                }
                break;
            case $wfLevels['AE']:                                                                       // AE conditional checking
                if ($application->doc_status == false || $application->payment_status != 1)
                    throw new Exception("Document Not Fully Verified or Payment in not Done!");
                if ($application->doc_upload_status == false || $application->is_field_verified == false) {
                    throw new Exception("Document Not Fully Uploaded or site inspection not done!");
                }
                $siteDetails = $mWaterSiteInspection->getSiteDetails($application->id)
                    ->where('order_officer', $refRole['AE'])
                    ->first();
                if (is_null($siteDetails)) {
                    throw new Exception("Technical Inspection is not done!");
                }
                break;
        }
    }


    /**
     * |------------------------------ Approval Rejection Water -------------------------------|
     * | @param request 
     * | @var waterDetails
     * | @var approvedWater
     * | @var rejectedWater
     * | @var msg
        | Serial No : 07 
        | Working / Check it / remove the comment ?? for delete / save the Details of the site inspection
        | Use the microservice for the consumerId 
        | Save it in the track 
     */
    public function approvalRejectionWater($request, $roleId)
    {
        # Condition while the final Check
        $mWaterApplication  = new WaterApplication();
        $mWaterApplicant    = new WaterApplicant();
        $refJe              = Config::get("waterConstaint.ROLE-LABEL.JE");
        $consumerParamId    = Config::get("waterConstaint.PARAM_IDS.WCON");
        $refWaterDetails    = $this->preApprovalConditionCheck($request, $roleId);

        # Approval of water application 
        if ($request->status == 1) {
            # Consumer no generation
            $idGeneration   = new PrefixIdGenerator($consumerParamId, $refWaterDetails['ulb_id']);
            $consumerNo     = $idGeneration->generate();
            $consumerNo     = str_replace('/', '-', $consumerNo);

            $this->saveWaterConnInProperty($refWaterDetails,);
            $consumerId = $mWaterApplication->finalApproval($request, $refJe, $consumerNo);
            $mWaterApplicant->finalApplicantApproval($request, $consumerId);
            $msg = "Application Successfully Approved !!";
        }
        # Rejection of water application
        if ($request->status == 0) {
            $mWaterApplication->finalRejectionOfAppication($request);
            $mWaterApplicant->finalOwnerRejection($request);
            $msg = "Application Successfully Rejected !!";
        }
        return responseMsgs(true, $msg, $consumerNo ?? "Empty", '', 01, '.ms', 'Post', $request->deviceId);
    }


    /**
     * | Check the Conditions for the approval of the application
     * | Only for the EO approval
     * | @param request
     * | @param roleId
        | Working
        | check the field verified status 
        | uncomment the line of code  
     */
    public function preApprovalConditionCheck($request, $roleId)
    {
        $waterDetails = WaterApplication::find($request->applicationId);
        if ($waterDetails->finisher != $roleId) {
            throw new Exception("You're Not the finisher ie. EO!");
        }
        if ($waterDetails->current_role != $roleId) {
            throw new Exception("Application has not Reached to the finisher ie. EO!");
        }
        if ($waterDetails->doc_status == false) {
            throw new Exception("Documet is Not verified!");
        }
        // if ($waterDetails->payment_status != 1) {
        //     throw new Exception("Payment Not Done or not verefied!");
        // }
        if ($waterDetails->doc_upload_status == false) {
            throw new Exception("Full document is Not Uploaded!");
        }
        // if ($waterDetails->is_field_verified == 0) {
        //     throw new Exception("Field Verification Not Done!!");
        // }
        $this->checkDataApprovalCondition($request, $roleId, $waterDetails);   // Reminder
        return $waterDetails;
    }


    /**
     * | Check in the database for the final approval of application
     * | only for EO
     * | @param request
     * | @param roleId
        | working
        | Check payment,docUpload,docVerify,feild
     */
    public function checkDataApprovalCondition($request, $roleId, $waterDetails)
    {
        $mWaterConnectionCharge = new WaterConnectionCharge();

        $applicationCharges = $mWaterConnectionCharge->getWaterchargesById($waterDetails->id)->get();
        $paymentStatus = collect($applicationCharges)->map(function ($value) {
            return $value['paid_status'];
        })->values();
        $uniqueArray = array_unique($paymentStatus->toArray());

        // if (count($uniqueArray) === 1 && $uniqueArray[0] === 1) {
        //     $payment = true;
        // } else {
        //     throw new Exception("full payment for the application is not done!");
        // }
    }


    /**
     * | save the water details in property or saf data
     * | save water connection no in prop or saf table
     * | @param 
        | Recheck
     */
    public function saveWaterConnInProperty($refWaterDetails,)
    {
        $appartmentsPropIds     = array();
        $mPropProperty          = new PropProperty();
        $mPropActiveSaf         = new PropActiveSaf();
        $refPropType            = Config::get("waterConstaint.PROPERTY_TYPE");
        $refConnectionThrough   = Config::get("waterConstaint.CONNECTION_THROUGH");

        switch ($refWaterDetails) {
                # For holding  
            case ($refWaterDetails->connection_through == $refConnectionThrough['HOLDING']):
                $appartmentsPropIds = collect($refWaterDetails->prop_id);
                if (in_array($refWaterDetails->property_type_id, [$refPropType['Apartment'], $refPropType['MultiStoredUnit']])) {
                    $propDetails            = PropProperty::findOrFail($refWaterDetails->prop_id);
                    $apartmentId            = $propDetails['apartment_details_id'];
                    $appartmentsProperty    = $mPropProperty->getPropertyByApartmentId($apartmentId)->get();
                    $appartmentsPropIds     = collect($appartmentsProperty)->pluck('id');
                }
                // $mPropProperty->updateWaterConnection($appartmentsPropIds, $consumerNo);
                break;
                # For Saf
            case ($refWaterDetails->connection_through == $refConnectionThrough['SAF']):
                $appartmentsSafIds = collect($refWaterDetails->saf_id);
                if (in_array($refWaterDetails->property_type_id, [$refPropType['Apartment'], $refPropType['MultiStoredUnit']])) {
                    $safDetails         = PropActiveSaf::findOrFail($refWaterDetails->saf_id);
                    $apartmentId        = $safDetails['apartment_details_id'];
                    $appartmentsSaf     = $mPropActiveSaf->getActiveSafByApartmentId($apartmentId)->get();
                    $appartmentsSafIds  = collect($appartmentsSaf)->pluck('id');
                }
                // $mPropActiveSaf->updateWaterConnection($appartmentsSafIds, $consumerNo);
                break;
        }
    }



    /**
     * |------------------------------ Get Application details --------------------------------|
     * | @param request
     * | @var ownerDetails
     * | @var applicantDetails
     * | @var applicationDetails
     * | @var returnDetails
     * | @return returnDetails : list of individual applications
        | Serial No : 08
        | Workinig 
     */
    public function getApplicationsDetails($request)
    {
        # object assigning
        $waterObj               = new WaterApplication();
        $ownerObj               = new WaterApplicant();
        $forwardBackward        = new WorkflowMap;
        $mWorkflowTracks        = new WorkflowTrack();
        $mCustomDetails         = new CustomDetail();
        $mUlbNewWardmap         = new UlbWardMaster();

        # application details
        $applicationDetails = $waterObj->fullWaterDetails($request)->get();
        if (collect($applicationDetails)->first() == null) {
            return responseMsg(false, "Application Data Not found!", $request->applicationId);
        }

        // # Ward Name
        // $refApplication = collect($applicationDetails)->first();
        // $wardDetails = $mUlbNewWardmap->getWard($refApplication->ward_id);
        # owner Details
        $ownerDetails = $ownerObj->ownerByApplication($request)->get();
        $ownerDetail = collect($ownerDetails)->map(function ($value, $key) {
            return $value;
        });
        $aplictionList = [
            'application_no' => collect($applicationDetails)->first()->application_no,
            'apply_date' => collect($applicationDetails)->first()->apply_date
        ];

        # DataArray
        $basicDetails = $this->getBasicDetails($applicationDetails);
        $propertyDetails = $this->getpropertyDetails($applicationDetails);
        $electricDetails = $this->getElectricDetails($applicationDetails);

        $firstView = [
            'headerTitle' => 'Basic Details',
            'data' => $basicDetails
        ];
        $secondView = [
            'headerTitle' => 'Applicant Property Details',
            'data' => $propertyDetails
        ];
        $thirdView = [
            'headerTitle' => 'Applicant Electricity Details',
            'data' => $electricDetails
        ];
        $fullDetailsData['fullDetailsData']['dataArray'] = new collection([$firstView, $secondView, $thirdView]);

        # CardArray
        $cardDetails = $this->getCardDetails($applicationDetails, $ownerDetails);
        $cardData = [
            'headerTitle' => 'Water Connection',
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
        $mRefTable = "water_applications.id";
        $levelComment['levelComment'] = $mWorkflowTracks->getTracksByRefId($mRefTable, $mtableId);

        #citizen comment
        $refCitizenId = $applicationDetails->first()->user_id;
        $citizenComment['citizenComment'] = $mWorkflowTracks->getCitizenTracks($mRefTable, $mtableId, $refCitizenId);

        # Role Details
        $data = json_decode(json_encode($applicationDetails->first()), true);
        $metaReqs = [
            'customFor' => 'Water',
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
     * |------------------ Basic Details ------------------|
     * | @param applicationDetails
     * | @var collectionApplications
        | Serial No : 08.01
        | Workinig 
     */
    public function getBasicDetails($applicationDetails)
    {
        $collectionApplications = collect($applicationDetails)->first();
        return new Collection([

            ['displayString' => 'Type of Connection', 'key' => 'TypeOfConnection',    'value' => $collectionApplications->connection_type],
            ['displayString' => 'Property Type',      'key' => 'PropertyType',        'value' => $collectionApplications->property_type],
            ['displayString' => 'Connection Through', 'key' => 'ConnectionThrough',   'value' => $collectionApplications->connection_through],
            ['displayString' => 'Category',           'key' => 'Category',            'value' => $collectionApplications->category],
            ['displayString' => 'Flat Count',         'key' => 'FlatCount',           'value' => $collectionApplications->flat_count],
            ['displayString' => 'Pipeline Type',      'key' => 'PipelineType',        'value' => $collectionApplications->pipeline_type],
            ['displayString' => 'Apply From',         'key' => 'ApplyFrom',           'value' => $collectionApplications->apply_from],
            ['displayString' => 'Apply Date',         'key' => 'ApplyDate',           'value' => $collectionApplications->apply_date]
        ]);
    }

    /**
     * |------------------ Property Details ------------------|
     * | @param applicationDetails
     * | @var propertyDetails
     * | @var collectionApplications
        | Serial No : 08.02
        | Workinig 
     */
    public function getpropertyDetails($applicationDetails)
    {
        $propertyDetails = array();
        $collectionApplications = collect($applicationDetails)->first();
        if (!is_null($collectionApplications->holding_no)) {
            array_push($propertyDetails, ['displayString' => 'Holding No',    'key' => 'AppliedBy',  'value' => $collectionApplications->holding_no]);
        }
        if (!is_null($collectionApplications->saf_no)) {
            array_push($propertyDetails, ['displayString' => 'Saf No',        'key' => 'AppliedBy',   'value' => $collectionApplications->saf_no]);
        }
        if (is_null($collectionApplications->saf_no) && is_null($collectionApplications->holding_no)) {
            array_push($propertyDetails, ['displayString' => 'Applied By',    'key' => 'AppliedBy',   'value' => 'Id Proof']);
        }
        array_push($propertyDetails, ['displayString' => 'Area in Sqft',  'key' => 'AreaInSqft',  'value' => $collectionApplications->area_sqft]);
        array_push($propertyDetails, ['displayString' => 'Address',       'key' => 'Address',     'value' => $collectionApplications->address]);
        array_push($propertyDetails, ['displayString' => 'Landmark',      'key' => 'Landmark',    'value' => $collectionApplications->landmark]);
        array_push($propertyDetails, ['displayString' => 'Pin',           'key' => 'Pin',         'value' => $collectionApplications->pin]);

        return $propertyDetails;
    }

    /**
     * |------------------ Electric details ------------------|
     * | @param applicationDetails
     * | @var collectionApplications
        | Serial No : 08.03
        | Workinig 
        | May Not used
     */
    public function getElectricDetails($applicationDetails)
    {
        $collectionApplications = collect($applicationDetails)->first();
        return new Collection([
            ['displayString' => 'K.No',             'key' => 'KNo',             'value' => $collectionApplications->elec_k_no],
            ['displayString' => 'Bind Book No',     'key' => 'BindBookNo',      'value' => $collectionApplications->elec_bind_book_no],
            ['displayString' => 'Elec Account No',  'key' => 'ElecAccountNo',   'value' => $collectionApplications->elec_account_no],
            ['displayString' => 'Elec Category',    'key' => 'ElecCategory',    'value' => $collectionApplications->elec_category]
        ]);
    }

    /**
     * |------------------ Owner details ------------------|
     * | @param ownerDetails
        | Serial No : 08.04
        | Workinig 
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
     * |------------------ Get Card Details ------------------|
     * | @param applicationDetails
     * | @param ownerDetails
     * | @var ownerDetail
     * | @var collectionApplications
        | Serial No : 08.05
        | Workinig 
     */
    public function getCardDetails($applicationDetails, $ownerDetails)
    {
        $ownerName = collect($ownerDetails)->map(function ($value) {
            return $value['owner_name'];
        });
        $ownerDetail = $ownerName->implode(',');
        $collectionApplications = collect($applicationDetails)->first();
        return new Collection([

            ['displayString' => 'Application No.',      'key' => 'ApplicationNo.',    'value' => $collectionApplications->application_no],
            ['displayString' => 'Owner Name',           'key' => 'OwnerName',         'value' => $ownerDetail],
            ['displayString' => 'Property Type',        'key' => 'PropertyType',      'value' => $collectionApplications->property_type],
            ['displayString' => 'Connection Type',      'key' => 'ConnectionType',    'value' => $collectionApplications->connection_type],
            ['displayString' => 'Connection Through',   'key' => 'ConnectionThrough', 'value' => $collectionApplications->connection_through],
            ['displayString' => 'Apply-Date',           'key' => 'ApplyDate',         'value' => $collectionApplications->apply_date],
            ['displayString' => 'Total Area (sqt)',     'key' => 'TotalArea',         'value' => $collectionApplications->area_sqft]
        ]);
    }


    /**
     * |-------------------------- Get Approved Application Details According to Consumer No -----------------------|
     * | @param request
     * | @var obj
     * | @var approvedWater
     * | @var applicationId
     * | @var connectionCharge
     * | @return connectionCharge : list of approved application by Consumer Id
        | Serial No :10
        | Working / Flag / Check / reused
     */
    public function getApprovedWater($request)
    {
        $mWaterConsumer         = new WaterConsumer();
        $mWaterConnectionCharge = new WaterConnectionCharge();
        $mWaterConsumerOwner    = new WaterConsumerOwner();
        $mWaterParamConnFee     = new WaterParamConnFee();

        $key = collect($request)->map(function ($value, $key) {
            return $key;
        })->first();
        $string         = preg_replace("/([A-Z])/", "_$1", $key);
        $refstring      = strtolower($string);
        $approvedWater  = $mWaterConsumer->getConsumerByConsumerNo($refstring, $request->id);
        $connectionCharge['connectionCharg'] = $mWaterConnectionCharge->getWaterchargesById($approvedWater['apply_connection_id'])
            ->where('charge_category', '!=', 'Site Inspection')                                     # Static
            ->first();
        $waterOwner['ownerDetails'] = $mWaterConsumerOwner->getConsumerOwner($approvedWater['consumer_id'])->get();
        $water['calcullation']      = $mWaterParamConnFee->getCallParameter($approvedWater['property_type_id'], $approvedWater['area_sqft'])->first();

        $consumerDetails = collect($approvedWater)->merge($connectionCharge)->merge($waterOwner)->merge($water);
        return remove_null($consumerDetails);
    }

    /**
     * |------------------------------ Get Application details --------------------------------|
     * | @param request
     * | @var ownerDetails
     * | @var applicantDetails
     * | @var applicationDetails
     * | @var returnDetails
     * | @return returnDetails : list of individual applications
        | Serial No : 08
        | Workinig 
     */
    public function getApproveApplicationsDetails($request)
    {
        # object assigning
        $waterObj               = new WaterApprovalApplicationDetail();
        $ownerObj               = new WaterApprovalApplicant();
        $forwardBackward        = new WorkflowMap;
        $mWorkflowTracks        = new WorkflowTrack();
        $mCustomDetails         = new CustomDetail();
        $mUlbNewWardmap         = new UlbWardMaster();

        # application details
        $applicationDetails = $waterObj->fullWaterDetails($request)->get();
        if (collect($applicationDetails)->first() == null) {
            return responseMsg(false, "Application Data Not found!", $request->applicationId);
        }

        // # Ward Name
        // $refApplication = collect($applicationDetails)->first();
        // $wardDetails = $mUlbNewWardmap->getWard($refApplication->ward_id);
        # owner Details
        $ownerDetails = $ownerObj->ownerByApplication($request)->get();
        $ownerDetail = collect($ownerDetails)->map(function ($value, $key) {
            return $value;
        });
        $aplictionList = [
            'application_no' => collect($applicationDetails)->first()->application_no,
            'apply_date' => collect($applicationDetails)->first()->apply_date
        ];

        # DataArray
        $basicDetails = $this->getBasicDetails($applicationDetails);
        $propertyDetails = $this->getpropertyDetails($applicationDetails);
        $electricDetails = $this->getElectricDetails($applicationDetails);

        $firstView = [
            'headerTitle' => 'Basic Details',
            'data' => $basicDetails
        ];
        $secondView = [
            'headerTitle' => 'Applicant Property Details',
            'data' => $propertyDetails
        ];
        $thirdView = [
            'headerTitle' => 'Applicant Electricity Details',
            'data' => $electricDetails
        ];
        $fullDetailsData['fullDetailsData']['dataArray'] = new collection([$firstView, $secondView, $thirdView]);

        # CardArray
        $cardDetails = $this->getCardDetails($applicationDetails, $ownerDetails);
        $cardData = [
            'headerTitle' => 'Water Connection',
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
        $mRefTable = "water_applications.id";
        $levelComment['levelComment'] = $mWorkflowTracks->getTracksByRefId($mRefTable, $mtableId);

        #citizen comment
        $refCitizenId = $applicationDetails->first()->user_id;
        $citizenComment['citizenComment'] = $mWorkflowTracks->getCitizenTracks($mRefTable, $mtableId, $refCitizenId);

        # Role Details
        $data = json_decode(json_encode($applicationDetails->first()), true);
        $metaReqs = [
            'customFor' => 'Water',
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
}
