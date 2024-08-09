<?php

namespace App\Repository\Water\Concrete;

use App\BLL\Water\WaterMonthelyCall;
use App\Http\Requests\Water\reqMeterEntry;
use App\MicroServices\DocUpload;
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
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterConsumerInitialMeter;
use App\Models\Water\WaterConsumerMeter;
use App\Models\Water\WaterConsumerOwner;
use App\Models\Water\WaterConsumerTax;
use App\Models\Water\WaterMeterReadingDoc;
use App\Models\Water\WaterParamConnFee;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterSecondConsumer;
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
use Illuminate\Support\Facades\Validator;
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
            case $wfLevels['DA']:
                if ($application->doc_upload_status == false) {
                    throw new Exception("Document Not Fully Uploaded");
                }                                                                       // DA Condition
                if ($application->doc_status == false)
                    throw new Exception("Document Not Fully Verified");
                break;
            case $wfLevels['JE']:                                                                       // JE Coditon in case of site adjustment
                if ($application->is_field_verified == false) {
                    throw new Exception("site inspection not done!");
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
            $data = $mWaterApplication->finalApproval($request, $refJe, $consumerNo);
            // Creating a new instance of reqMeterEntry with necessary data
            $updateRequest = new reqMeterEntry([
                "consumerId"    => $data['consumerId'],
                "connectionType" => $data['connectionType'], // Ensure this key exists in $request
                "connectionDate" => $data['connectionDate'], // Ensure this key exists in $request
                "newMeterInitialReading" => $data['newMeterInitialReading'], // Ensure this key exists in $request
                "meterNo" => $data['meterNo'], // Ensure this key exists in $request
            ]);
            $this->saveUpdateMeterDetails($updateRequest);
            $mWaterApplicant->finalApplicantApproval($request, $data['consumerId']);
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
     * | Save the Meter details 
     * | @param request
        | Serial No : 04
        | Working  
        | Check the parameter for the autherised person
        | Chack the Demand for the fixed rate 
        | Re discuss
     */
    public function saveUpdateMeterDetails(reqMeterEntry $request)
    {
        try {
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            // $mWaterConsumerInitial  = new WaterConsumerInitialMeter();
            $meterRefImageName      = config::get('waterConstaint.WATER_METER_CODE');
            $param                  = $this->checkParamForMeterEntry($request);

            $this->begin();
            $metaRequest = new Request([
                "consumerId"    => $request->consumerId,
                "finalRading"   => $request->oldMeterFinalReading,
                "demandUpto"    => $request->connectionDate,
                "document"      => $request->document,
            ]);
            if ($param['meterStatus'] != false) {
                $this->saveGenerateConsumerDemand($metaRequest);
            }
            if ($request->document != null) (
                $documentPath = $this->saveDocument($request, $meterRefImageName)
            );
            $documentPath = null;
            $mWaterConsumerMeter->saveMeterDetails($request, $documentPath, $fixedRate = null);
            // $userDetails =[
            //     'emp_id' =>$mWaterConsumerMeter->emp_details_id
            // ];
            // $mWaterConsumerInitial->saveConsumerReading($request,$metaRequest,$userDetails);             # when initial meter data save  
            $this->commit();
            return responseMsgs(true, "Meter Detail Entry Success !", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Chech the parameter before Meter entry
     * | Validate the Admin For entring the meter details
     * | @param request
        | Serial No : 04.01
        | Working
        | Look for the meter status true condition while returning data
        | Recheck the process for meter and non meter 
        | validation for the respective meter conversion and verify the new consumer.
     */
    public function     checkParamForMeterEntry($request)
    {
        $refConsumerId  = $request->consumerId;
        $todayDate      = Carbon::now();

        $mWaterWaterSecondConsumer    = new waterSecondConsumer();
        $mWaterConsumerMeter    = new WaterConsumerMeter();
        $mWaterConsumerDemand   = new WaterConsumerDemand();
        $refMeterConnType       = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');

        $refConsumerDetails     = $mWaterWaterSecondConsumer->getConsumerDetailById($refConsumerId);
        if (!$refConsumerDetails) {
            throw new Exception("Consumer Details Not Found!");
        }
        $consumerMeterDetails   = $mWaterConsumerMeter->getMeterDetailsByConsumerId($refConsumerId)->first();
        $consumerDemand         = $mWaterConsumerDemand->getFirstConsumerDemand($refConsumerId)->first();

        # Check the meter/fixed case 
        $this->checkForMeterFixedCase($request, $consumerMeterDetails, $refMeterConnType);

        switch ($request) {
            case (strtotime($request->connectionDate) > strtotime($todayDate)):
                throw new Exception("Connection Date can not be greater than Current Date!");
                break;
            case ($request->connectionType != $refMeterConnType['Meter/Fixed']):
                if (!is_null($consumerMeterDetails)) {
                    if ($consumerMeterDetails->final_meter_reading >= $request->oldMeterFinalReading) {
                        throw new Exception("Reading Should be Greater Than last Reading!");
                    }
                }
                break;
            case ($request->connectionType != $refMeterConnType['Meter']):
                if (!is_null($consumerMeterDetails)) {
                    if ($consumerMeterDetails->connection_type == $request->connectionType) {
                        throw new Exception("You can not update same connection type as before!");
                    }
                }
                break;
        }

        # If Previous meter details exist
        if ($consumerMeterDetails) {
            # If fixed meter connection is changing to meter connection as per rule every connection should be in meter
            if ($request->connectionType != $refMeterConnType['Fixed'] && $consumerMeterDetails->connection_type == $refMeterConnType['Fixed']) {
                if ($consumerDemand) {
                    throw new Exception("Please pay the old demand Amount! as per rule to change fixed connection to meter!");
                }
                throw new Exception("Please apply for regularization as per rule 16 your connection should be in meter!");
            }
            # If there is previous meter detail exist
            $reqConnectionDate = $request->connectionDate;
            if (strtotime($consumerMeterDetails->connection_date) > strtotime($reqConnectionDate)) {
                throw new Exception("Connection date should be greater than previous connection date!");
            }
            # Check the Conversion of the Connection
            $this->checkConnectionTypeUpdate($request, $consumerMeterDetails, $refMeterConnType);
        }

        # If the consumer demand exist
        if (isset($consumerDemand)) {
            $reqConnectionDate = $request->connectionDate;
            $reqConnectionDate = Carbon::parse($reqConnectionDate)->format('m');
            $consumerDmandDate = Carbon::parse($consumerDemand->demand_upto)->format('m');
            switch ($consumerDemand) {
                case ($consumerDmandDate >= $reqConnectionDate):
                    throw new Exception("Cannot update connection Date, Demand already generated upto that month!");
                    break;
            }
        }
        # If the meter detail do not exist 
        if (is_null($consumerMeterDetails)) {
            if (!in_array($request->connectionType, [$refMeterConnType['Meter'], $refMeterConnType['Gallon']])) {
                throw new Exception("New meter connection should be in meter and gallon!");
            }
            $returnData['meterStatus'] = false;
        }
        return $returnData;
    }

    /**
     * | Check for the Meter/Fixed 
     * | @param request
     * | @param consumerMeterDetails
        | Serial No : 04.01.01
        | Not Working
     */
    public function checkForMeterFixedCase($request, $consumerMeterDetails, $refMeterConnType)
    {
        if ($request->connectionType == $refMeterConnType['Meter/Fixed']) {
            $refConnectionType = 1;
            if ($consumerMeterDetails->connection_type == $refConnectionType && $consumerMeterDetails->meter_status == 0) {
                throw new Exception("You can not update same connection type as before!");
            }
            if ($request->meterNo != $consumerMeterDetails->meter_no) {
                throw new Exception("You Can Meter/Fixed The Connection On Previous Meter");
            }
        }
    }
    /**
     * | Save the consumer demand 
     * | Also generate demand 
     * | @param request
     * | @var mWaterConsumerInitialMeter
     * | @var mWaterConsumerMeter
     * | @var refMeterConnectionType
     * | @var consumerDetails
     * | @var calculatedDemand
     * | @var demandDetails
     * | @var meterId
     * | @return 
        | Serial No : 03
        | Not Tested
        | Work on the valuidation and the saving of the meter details document
     */
    public function saveGenerateConsumerDemand(Request $request)
    {
        $mNowDate = carbon::now()->format('Y-m-d');
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'       => "required|digits_between:1,9223372036854775807",
                "demandUpto"       => "nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate",
                'finalRading'      => "nullable|numeric",
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        // return $request->all();

        try {
            $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
            $mWaterConsumerMeter        = new WaterConsumerMeter();
            $mWaterMeterReadingDoc      = new WaterMeterReadingDoc();
            $mWaterSecondConsumer       = new WaterSecondConsumer();
            $refMeterConnectionType     = Config::get('waterConstaint.METER_CONN_TYPE');
            $meterRefImageName          = config::get('waterConstaint.WATER_METER_CODE');
            $demandIds = array();

            # Check and calculate Demand                  
            $consumerDetails = $mWaterSecondConsumer->getConsumerDetails($request->consumerId)->first();
            if (!$consumerDetails) {
                throw new Exception("Consumer detail not found!");
            }
            // $this->checkDemandGeneration($request, $consumerDetails);                                       // unfinished function

            # Calling BLL for call
            $returnData = new WaterMonthelyCall($request->consumerId, $request->demandUpto, $request->finalRading); #WaterSecondConsumer::get();
            if (!$request->isNotstrickChek) {
                $returnData->checkDemandGenerationCondition();
            }
            $calculatedDemand = $returnData->parentFunction($request);
            if ($calculatedDemand['status'] == false) {
                throw new Exception($calculatedDemand['errors']);
            }
            # Save demand details 
            $this->begin();
            $userDetails = $this->checkUserType($request);
            if (isset($calculatedDemand)) {
                $demandDetails = collect($calculatedDemand['consumer_tax']['0']);
                switch ($demandDetails['charge_type']) {
                        # For Meter Connection
                    case ($refMeterConnectionType['1']):
                        $validated = Validator::make(
                            $request->all(),
                            [
                                'document' => "required|mimes:pdf,jpeg,png,jpg",
                            ]
                        );
                        if ($validated->fails())
                            return validationError($validated);
                        $meterDetails = $mWaterConsumerMeter->saveMeterReading($request);
                        $mWaterConsumerInitialMeter->saveConsumerReading($request, $meterDetails, $userDetails);
                        $demandIds = $this->savingDemand($calculatedDemand, $request, $consumerDetails, $demandDetails['charge_type'], $refMeterConnectionType, $userDetails);
                        # save the chages doc
                        $documentPath = $this->saveDocument($request, $meterRefImageName);
                        collect($demandIds)->map(function ($value)
                        use ($mWaterMeterReadingDoc, $meterDetails, $documentPath) {
                            $mWaterMeterReadingDoc->saveDemandDocs($meterDetails, $documentPath, $value);
                        });
                        break;

                        # For Fixed connection
                    case ($refMeterConnectionType['3']):
                        $this->savingDemand($calculatedDemand, $request, $consumerDetails, $demandDetails['charge_type'], $refMeterConnectionType, $userDetails);
                        break;
                }
                // $sms = AkolaProperty(["owner_name" => $request['arshad'], "saf_no" => $request['tranNo']], "New Assessment");
                // if (($sms["status"] !== false)) {
                //     $respons = SMSAKGOVT(6206998554, $sms["sms"], $sms["temp_id"]);
                // }
                $this->commit();
                $respons = $documentPath ?? [];
                $respons["consumerId"]  =   $request->consumerId;
                return responseMsgs(true, "Demand Generated! for" . " " . $request->consumerId, $respons, "", "02", ".ms", "POST", "");
            }
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), [], "", "01", "ms", "POST", "");
        }
    }
    /**
     * | Check the user type and return its id
        | Serial No :
        | Working
     */
    public function checkUserType($req)
    {
        $user = authUser($req);
        $confUserType = Config::get("waterConstaint.REF_USER_TYPE");
        $userType = $user->user_type;

        if ($userType == $confUserType['1']) {
            return [
                "citizen_id"    => $user->id,
                "user_type"     => $userType
            ];
        } else {
            return [
                "emp_id"    => $user->id,
                "user_type" => $userType
            ];
        }
    }
    /**
     * | Check the meter connection type in the case of meter updation 
     * | If the meter details exist check the connection type 
        | Serial No :
        | Under Con
     */
    public function checkConnectionTypeUpdate($request, $consumerMeterDetails, $refMeterConnType)
    {
        $currentConnectionType      = $consumerMeterDetails->connection_type;
        $requestedConnectionType    = $request->connectionType;

        switch ($currentConnectionType) {
                # For Fixed Connection
            case ($refMeterConnType['Fixed']):
                if ($requestedConnectionType != $refMeterConnType['Meter'] || $requestedConnectionType != $refMeterConnType['Gallon']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # For Fixed Meter Connection
            case ($refMeterConnType['Meter']):
                if ($requestedConnectionType != $refMeterConnType['Meter'] || $requestedConnectionType != $refMeterConnType['Gallon'] || $requestedConnectionType != $refMeterConnType['Meter/Fixed']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # For Fixed Gallon Connection
            case ($refMeterConnType['Gallon']):
                if ($requestedConnectionType != $refMeterConnType['Meter']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # For Fixed Meter/Fixed Connection
            case ($refMeterConnType['Meter/Fixed']):
                if ($requestedConnectionType != $refMeterConnType['Meter']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # Default
            default:
                throw new Exception("Invalid Meter Connection!");
                break;
        }
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
     * | Save the Details for the Connection Type Meter 
     * | In Case Of Connection Type is meter OR Gallon 
     * | @param Request  
     * | @var mWaterConsumerDemand
     * | @var mWaterConsumerTax
     * | @var generatedDemand
     * | @var taxId
     * | @var meterDetails
     * | @var refDemands
        | Serial No : 03.01
        | Not Tested
     */
    public function savingDemand($calculatedDemand, $request, $consumerDetails, $demandType, $refMeterConnectionType, $userDetails)
    {
        $mWaterConsumerTax      = new WaterConsumerTax();
        $mWaterConsumerDemand   = new WaterConsumerDemand();
        $generatedDemand        = $calculatedDemand['consumer_tax'];

        $returnDemandIds = collect($generatedDemand)->map(function ($firstValue)
        use ($mWaterConsumerDemand, $consumerDetails, $request, $mWaterConsumerTax, $demandType, $refMeterConnectionType, $userDetails) {
            $taxId = $mWaterConsumerTax->saveConsumerTax($firstValue, $consumerDetails, $userDetails);
            // $refDemandIds = array();
            # User for meter details entry
            $meterDetails = [
                "charge_type"       => $firstValue['charge_type'],
                "amount"            => $firstValue['charge_type'],
                "effective_from"    => $firstValue['effective_from'],
                "initial_reading"   => $firstValue['initial_reading'],
                "final_reading"     => $firstValue['final_reading'],
                "rate_id"           => $firstValue['rate_id'],
            ];
            switch ($demandType) {
                case ($refMeterConnectionType['1']):
                    $refDemands = $firstValue['consumer_demand'];
                    $check = collect($refDemands)->first();
                    if (is_array($check)) {
                        $refDemandIds = collect($refDemands)->map(function ($secondValue)
                        use ($mWaterConsumerDemand, $consumerDetails, $request, $taxId, $userDetails) {
                            $refDemandId = $mWaterConsumerDemand->saveConsumerDemand($secondValue, $consumerDetails, $request, $taxId, $userDetails);
                            return $refDemandId;
                        });
                        break;
                    }
                    $refDemandIds = $mWaterConsumerDemand->saveConsumerDemand($refDemands, $consumerDetails, $request, $taxId, $userDetails);
                    break;
                case ($refMeterConnectionType['3']):
                    $refDemands = $firstValue['consumer_demand'];
                    $check = collect($refDemands)->first();
                    if (is_array($check)) {
                        $refDemandIds = collect($refDemands)->map(function ($secondValue)
                        use ($mWaterConsumerDemand, $consumerDetails, $request, $taxId, $userDetails) {
                            $refDemandId = $mWaterConsumerDemand->saveConsumerDemand($secondValue,  $consumerDetails, $request, $taxId, $userDetails);
                            return $refDemandId;
                        });
                        break;
                    }
                    $refDemandIds = $mWaterConsumerDemand->saveConsumerDemand($refDemands, $consumerDetails, $request, $taxId, $userDetails);
                    break;
            }
            return $refDemandIds;
        });
        return $returnDemandIds->first();
    }

    /**
     * | Save the Document for the Meter Entry 
     * | Return the Document Path
     * | @param request
        | Serial No : 04.02 / 06.02
        | Working
        | Common function
     */
    public function saveDocument($request, $refImageName, $folder = null)
    {
        $document       = $request->document;
        $docUpload      = new DocUpload;
        $relativePath   = trim(Config::get('waterConstaint.WATER_RELATIVE_PATH') . "/" . $folder, "/");

        $imageName = $docUpload->upload($refImageName, $document, $relativePath);
        $doc = [
            "document"      => $imageName,
            "relaivePath"   => $relativePath
        ];
        return $doc;
    }

    /**
     * | save the water details in property or saf data
     * | save water connection no in prop or saf table
     * | @param 
        | Recheck
     */
    public function saveWaterConnInProperty($refWaterDetails)
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
        $mPropPerty             = new PropProperty();


        # application details
        $applicationDetails = $waterObj->fullWaterDetails($request)->get();
        if (collect($applicationDetails)->first() == null) {
            return responseMsg(false, "Application Data Not found!", $request->applicationId);
        }
        $holding = $applicationDetails->pluck('property_no')->toArray();

        # get property basic detail by holding number 
        $holdingDetails = $mPropPerty->getPropert($holding);
        if (!$holdingDetails) {
            throw new Exception('Holding not found!');
        }
        $applicationDetail  = collect($applicationDetails);

        # set attribute of property in water details 
        $applicationDetails = $applicationDetails->map(function ($applicationDetail) use ($holdingDetails) {
            if (isset($holdingDetails)) {
                $applicationDetail->setAttribute('area_of_plot', $holdingDetails->area_of_plot);
            } else {
                $applicationDetail->setAttribute('area_of_plot', null);                                  // Set to null or a default value if not found
            }
            $applicationDetail->setAttribute('village_mauja_name', $holdingDetails->village_mauja_name); // Replace 'your_value_here' with the desired value
            return $applicationDetail;
        });


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
        // $electricDetails = $this->getElectricDetails($applicationDetails);

        $firstView = [
            'headerTitle' => 'Basic Details',
            'data' => $basicDetails
        ];
        $secondView = [
            'headerTitle' => 'Applicant Property Details',
            'data' => $propertyDetails
        ];
        // $thirdView = [
        //     'headerTitle' => 'Applicant Electricity Details',
        //     'data' => $electricDetails
        // ];
        $fullDetailsData['fullDetailsData']['dataArray'] = new collection([$firstView, $secondView]);    // $thirdView

        # CardArray
        $cardDetails = $this->getCardDetails($applicationDetails, $ownerDetails);
        $cardData = [
            'headerTitle' => 'Water Connection',
            'data' => $cardDetails
        ];
        $fullDetailsData['fullDetailsData']['cardArray'] = new Collection($cardData);
        # set attribute of owner name in marathi

        $ownerDetail = $ownerDetail->map(function ($ownerDetail) use ($holdingDetails) {
            if (isset($holdingDetails)) {
                $ownerDetail->setAttribute('owner_name_marathi', $holdingDetails->owner_name_marathi);
            } else {
                $ownerDetail->setAttribute('owner_name_marathi', null);  // Set to null or a default value if not found
            }
            return $ownerDetail;
        });
        # TableArray
        $ownerList = $this->getOwnerDetails($ownerDetail);


        $ownerView = [
            'headerTitle' => 'Owner Details',
            'tableHead' => ["#", "Owner Name", "Owner NAME Marathi", "Mobile No", "Email", "City", "District"],
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
            // ['displayString' => 'Connection Through', 'key' => 'ConnectionThrough',   'value' => $collectionApplications->connection_through ],
            ['displayString' => 'Category',           'key' => 'Category',            'value' => $collectionApplications->category],
            ['displayString' => 'Apply From',         'key' => 'ApplyFrom',           'value' => $collectionApplications->user_type],
            ['displayString' => 'Apply Date',         'key' => 'ApplyDate',           'value' => $collectionApplications->apply_date],
            ['displayString' => 'Ward Number',        'key' => 'WardNumber',          'value' => $collectionApplications->ward_name],
            ['displayString' => 'Zone',               'key' => 'zone',                'value' => $collectionApplications->zone_name],
            ['displayString' => 'Holding',             'key' => 'HoldingNumber',      'value' => $collectionApplications->property_no],
            ['displayString' => 'Meter Number',        'key' => 'MeterNumber',      'value' => $collectionApplications->meter_no],
            ['displayString' => 'Initial Reading',     'key' => 'InitialReading',      'value' => $collectionApplications->initial_reading],
            ['displayString' => 'MobileNumber',           'key' => 'MobileNumber',      'value' => $collectionApplications->mobile_no],
            ['displayString' => 'Email',               'key' => 'Email',                'value' => $collectionApplications->email],
            ['displayString' => 'TradeLicense',               'key' => 'TradeLicense',                'value' => $collectionApplications->trade_license],
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
        // if (is_null($collectionApplications->saf_no) && is_null($collectionApplications->holding_no)) {
        //     array_push($propertyDetails, ['displayString' => 'Applied By',    'key' => 'AppliedBy',   'value' => 'Id Proof']);
        // }
        array_push($propertyDetails, ['displayString' => 'Area in Sqft',  'key' => 'AreaInSqft',  'value' => $collectionApplications->area_of_plot]);
        array_push($propertyDetails, ['displayString' => 'Address',       'key' => 'Address',     'value' => $collectionApplications->address]);
        array_push($propertyDetails, ['displayString' => 'Landmark',      'key' => 'Landmark',    'value' => $collectionApplications->landmark]);
        array_push($propertyDetails, ['displayString' => 'Pin',           'key' => 'Pin',         'value' => $collectionApplications->pin]);
        array_push($propertyDetails, ['displayString' => 'VillageMaujaName',  'key' => 'VillageMaujaName',         'value' => $collectionApplications->village_mauja_name]);

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
                $value['owner_name_marathi'],
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
            ['displayString' => 'Connection Through',   'key' => 'ConnectionThrough', 'value' => $collectionApplications->connection_through ?? "HOLDING"],
            ['displayString' => 'Apply-Date',           'key' => 'ApplyDate',         'value' => $collectionApplications->apply_date],
            ['displayString' => 'Total Area (sqt)',     'key' => 'TotalArea',         'value' => $collectionApplications->area_of_plot]
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
        $mWaterSecondConsumer         = new WaterSecondConsumer();
        $mWaterConnectionCharge = new WaterConnectionCharge();
        $mWaterConsumerOwner    = new WaterConsumerOwner();
        $mWaterParamConnFee     = new WaterParamConnFee();

        $key = collect($request)->map(function ($value, $key) {
            return $key;
        })->first();
        $string         = preg_replace("/([A-Z])/", "_$1", $key);
        $refstring      = strtolower($string);
        $approvedWater  = $mWaterSecondConsumer->getConsumerByConsumerNo($refstring, $request->id);
        $connectionCharge['connectionCharg'] = $mWaterConnectionCharge->getWaterchargesById($approvedWater['apply_connection_id'])
            ->where('charge_category', '!=', 'Site Inspection')                                     # Static
            ->first();
        $waterOwner['ownerDetails'] = $mWaterConsumerOwner->getConsumerOwner($approvedWater['consumer_id'])->get();
        $water = [];
        if ($approvedWater['area_sqft'] != null) {
            $water['calcullation']      = $mWaterParamConnFee->getCallParameter($approvedWater['property_type_id'], $approvedWater['area_sqft'])->first();
        }


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
