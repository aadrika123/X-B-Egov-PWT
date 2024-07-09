<?php

namespace App\Http\Controllers\Water;

use App\Http\Controllers\Controller;
use App\Http\Requests\Water\newApplyRules;
use  App\Models\water\WaterSecondConnectionCharge;
use App\Http\Requests\Water\reqSiteVerification;
use  App\Http\Requests\water\reqeustFileWater;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\MicroServices\DocUpload;
use App\Http\Requests\water\newWaterRequest;
use App\Models\Masters\RefRequiredDocument;
use App\Models\Payment\WebhookPaymentData;
use App\Models\Property\PropActiveObjection;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropApartmentDtl;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\UlbWardMaster;
use App\Models\Water\WaterApplicant;
use App\Models\Water\WaterApplication;
use App\Models\Water\WaterApprovalApplicant;
use App\Models\Water\WaterApprovalApplicationDetail;
use App\Models\Water\waterAudit;
use App\Models\Water\WaterConnectionCharge;
use App\Models\Water\WaterConnectionThroughMstr;
use App\Models\Water\WaterConnectionThroughMstrs;
use App\Models\Water\WaterConnectionTypeCharge;
use App\Models\Water\WaterConnectionTypeMstr;
use App\Models\Water\WaterConsumer;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterConsumerInitialMeter;
use App\Models\Water\WaterConsumerMeter;
use App\Models\Water\WaterConsumerOwner;
use App\Models\Water\WaterConsumerTax;
use App\Models\Water\WaterOwnerTypeMstr;
use App\Models\Water\WaterParamConnFee;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterPropertyTypeMstr;
use App\Models\Water\WaterSiteInspection;
use App\Models\Water\WaterSiteInspectionsScheduling;
use App\Models\Water\WaterTran;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Repository\Common\CommonFunction;
use App\Repository\Water\Concrete\NewConnectionRepository;
use App\Repository\Water\Concrete\WaterNewConnection;
use Illuminate\Http\Request;
use App\Repository\Water\Interfaces\iNewConnection;
use App\Traits\Property\SAF;
use App\Traits\Ward;
use App\Traits\Water\WaterTrait;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use DateTime;
use Exception;
use App\Models\Water\WaterSecondConsumer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Ramsey\Collection\Collection as CollectionCollection;
use SebastianBergmann\Type\VoidType;
use Symfony\Contracts\Service\Attribute\Required;

class NewConnectionController extends Controller
{
    use Ward;
    use Workflow;
    use WaterTrait;
    use SAF;

    private iNewConnection $newConnection;
    private $_dealingAssistent;
    private $_waterRoles;
    protected $_commonFunction;
    private $_waterModulId;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct(iNewConnection $newConnection)
    {
        $this->_DB_NAME = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_commonFunction = new CommonFunction();
        $this->newConnection = $newConnection;
        $this->_dealingAssistent = Config::get('workflow-constants.DEALING_ASSISTENT_WF_ID');
        $this->_waterRoles = Config::get('waterConstaint.ROLE-LABEL');
        $this->_waterModulId = Config::get('module-constants.WATER_MODULE_ID');
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * @param \ newApplyRules 
     */
    public function store(newApplyRules $request)
    {
        try {
            return $this->newConnection->store($request);
        } catch (Exception $error) {
            $this->rollback();
            return responseMsg(false, $error->getMessage(), "");
        }
    }

    /**
     * apply consumer for water connection for al
     */


    /**
     * |--------------------------------------------- Water workflow -----------------------------------------------|
     */

    /**
     * | Water Inbox
     * | workflow
     * | Repositiory Call
        | Serial No :
        | Working
     */
    public function waterInbox(Request $request)
    {
        try {
            $user   = authUser($request);
            $userId = $user->id;
            $ulbId  = $user->ulb_id;
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            // $occupiedWards = $this->getWardByUserId($userId)->pluck('ward_id');
            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $waterList = $this->getWaterApplicatioList($workflowIds, $ulbId)
                ->whereIn('water_applications.current_role', $roleId)
                // ->whereIn('water_approval_application_details.ward_id', $occupiedWards)
                ->where('water_applications.is_escalate', false)
                ->where('water_applications.parked', false)
                ->orderByDesc('water_applications.id')
                ->get();
            $filterWaterList = collect($waterList)->unique('id')->values();
            return responseMsgs(true, "Inbox List Details!", remove_null($filterWaterList), '', '02', '', 'Post', '');
        } catch (Exception $error) {
            return responseMsg(false, $error->getMessage(), "");
        }
    }

    /**
     * | Water Outbox
     * | Workflow
     * | Reposotory Call
        | Serial No :
        | Working
     */
    public function waterOutbox(Request $req)
    {
        try {
            $mWfWardUser            = new WfWardUser();
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();

            $user   = authUser($req);
            $userId = $user->id;
            $ulbId  = $user->ulb_id;

            $workflowRoles = $this->getRoleIdByUserId($userId);
            $roleId = $workflowRoles->map(function ($value) {                         // Get user Workflow Roles
                return $value->wf_role_id;
            });

            // $refWard = $mWfWardUser->getWardsByUserId($userId);
            // $wardId = $refWard->map(function ($value) {
            //     return $value->ward_id;
            // });

            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');
            $waterList = $this->getWaterApplicatioList($workflowIds, $ulbId)
                ->whereNotIn('water_applications.current_role', $roleId)
                // ->whereIn('water_approval_application_details.ward_id', $wardId)
                ->orderByDesc('water_applications.id')
                ->get();
            $filterWaterList = collect($waterList)->unique('id')->values();
            return responseMsgs(true, "Outbox List", remove_null($filterWaterList), '', '01', '.ms', 'Post', '');
        } catch (Exception $error) {
            return responseMsg(false, $error->getMessage(), "");
        }
    }

    /**
     * | Back to citizen Inbox
     * | Workflow
     * | @param req
     * | @var mWfWardUser
     * | @var userId
     * | @var ulbId
     * | @var mDeviceId
     * | @var workflowRoles
     * | @var roleId
     * | @var refWard
     * | @var wardId
     * | @var waterList
     * | @var filterWaterList
     * | @return filterWaterList 
        | Serial No : 
        | Use
     */
    public function btcInbox(Request $req)
    {
        try {
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $mDeviceId = $req->deviceId ?? "";

            $workflowRoles = $this->getRoleIdByUserId($userId);
            $roleId = $workflowRoles->map(function ($value) {                         // Get user Workflow Roles
                return $value->wf_role_id;
            });

            $refWard = $mWfWardUser->getWardsByUserId($userId);
            $wardId = $refWard->map(function ($value) {
                return $value->ward_id;
            });
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $waterList = $this->getWaterApplicatioList($workflowIds, $ulbId)
                ->whereIn('water_approval_application_details.ward_id', $wardId)
                ->where('parked', true)
                ->orderByDesc('water_approval_application_details.id')
                ->get();

            $filterWaterList = collect($waterList)->unique('id');
            $filterWaterList = $filterWaterList->values();
            return responseMsgs(true, "BTC Inbox List", remove_null($filterWaterList), "", 1.0, "560ms", "POST", $mDeviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010123, 1.0, "271ms", "POST", $mDeviceId);
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

            $waterData = $this->getWaterApplicatioList($workflowIds, $ulbId)                              // Repository function to get SAF Details
                ->where('water_approval_application_details.is_escalate', 1)
                ->whereIn('water_approval_application_details.ward_id', $wardId)
                ->orderByDesc('water_approval_application_details.id')
                ->get();
            $filterWaterList = collect($waterData)->unique('id')->values();
            return responseMsgs(true, "Data Fetched", remove_null($filterWaterList), "010107", "1.0", "251ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "0.1", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | Post next level 
        | Serial No : 
     */
    public function postNextLevel(Request $request)
    {
        $wfLevels = Config::get('waterConstaint.ROLE-LABEL');
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId'     => 'required',
                'senderRoleId'      => 'required',
                'receiverRoleId'    => 'required',
                'action'            => 'required|In:forward,backward',
                'comment'           => $request->senderRoleId == $wfLevels['BO'] ? 'nullable' : 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            return $this->newConnection->postNextLevel($request);
        } catch (Exception $error) {
            $this->rollback();
            return responseMsg(false, $error->getMessage(), "");
        }
    }


    /**
     * | Water Application details for the view in workflow
        | Serial No :
     */
    public function getApplicationsDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            return $this->newConnection->getApplicationsDetails($request);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
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
            $applicationsData = WaterApplication::find($applicationId);
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
     * | final Approval or Rejection of the Application
        | Serial No :
        | Recheck
     */
    public function approvalRejectionWater(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required",
                "status"        => "required",
                "comment"       => "required"
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mWfRoleUsermap = new WfRoleusermap();
            $waterDetails = WaterApplication::findOrFail($request->applicationId);

            # check the login user is EO or not
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
            $returnData = $this->newConnection->approvalRejectionWater($request, $roleId);
            $this->commit();
            return $returnData;
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Indipendent Comment on the Water Applications
        | Serial No :  
        | Recheck
        | Use
     */
    public function commentIndependent(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'comment'       => 'required',
                'applicationId' => 'required',
                'senderRoleId'  => 'nullable|integer'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $metaReqs       = array();
            $user           = authUser($request);
            $userType       = $user->user_type;
            $userId         = $user->id;
            $workflowTrack  = new WorkflowTrack();
            $mWfRoleUsermap = new WfRoleusermap();
            $mModuleId      = $this->_waterModulId;

            $applicationId = WaterApplication::find($request->applicationId);
            if (!$applicationId) {
                throw new Exception("Application Don't Exist!");
            }

            # Save On Workflow Track
            $metaReqs = [
                'workflowId'        => $applicationId->workflow_id,
                'moduleId'          => $mModuleId,
                'refTableDotId'     => "water_approval_application_details.id",                                     // Static
                'refTableIdValue'   => $applicationId->id,
                'message'           => $request->comment
            ];
            $this->begin();
            if ($userType != 'Citizen') {                                                           // Static
                $roleReqs = new Request([
                    'workflowId' => $applicationId->workflow_id,
                    'userId' => $userId,
                ]);
                $wfRoleId = $mWfRoleUsermap->getRoleByUserWfId($roleReqs);
                $metaReqs = array_merge($metaReqs, ['senderRoleId' => $wfRoleId->wf_role_id]);
                $metaReqs = array_merge($metaReqs, ['user_id' => $userId]);
            }
            # For Citizen Independent Comment
            if ($userType == 'Citizen') {                                                           // Static
                $metaReqs = array_merge($metaReqs, ['citizenId' => $userId]);
                $metaReqs = array_merge($metaReqs, ['ulb_id' => $applicationId->ulb_id]);
                $metaReqs = array_merge($metaReqs, ['user_id' => NULL]);
            }
            $request->request->add($metaReqs);
            $workflowTrack->saveTrack($request);
            $this->commit();
            return responseMsgs(true, "You Have Commented Successfully!!", ['Comment' => $request->comment], "010108", "1.0", "427ms", "POST", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Get Approved Water Appliction 
        | Serial No :  
        | Recheck / Updated 
     */
    public function approvedWaterApplications(Request $request)
    {
        try {
            if ($request->id) {
                $validated = Validator::make(
                    $request->all(),
                    [
                        "id" => "nullable|int",
                    ]
                );
                if ($validated->fails())
                    return validationError($validated);

                $refConsumerId              = $request->id;
                $mWaterConsumerMeter        = new WaterConsumerMeter();
                $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
                $mWaterConsumerDemand       = new WaterConsumerDemand();
                $refConnectionName          = Config::get('waterConstaint.METER_CONN_TYPE');
                $flipConnection             = collect($refConnectionName)->flip();

                $consumerDetails = $this->newConnection->getApprovedWater($request);
                $refApplicationId['applicationId'] = $consumerDetails['consumer_id'];
                // $metaRequest = new Request($refApplicationId);
                // $refDocumentDetails = $this->getUploadDocuments($metaRequest);
                // $documentDetails['documentDetails'] = collect($refDocumentDetails)['original']['data'];

                # meter Details 
                $refMeterData = $mWaterConsumerMeter->getMeterDetailsByConsumerId($request->id)->first();
                if (isset($refMeterData)) {
                    switch ($refMeterData['connection_type']) {
                        case (1):
                            if ($refMeterData['meter_status'] == 1) {
                                $connectionName = $refConnectionName['1'];
                                $consumerDemand['connectionId'] = $flipConnection['Meter'];
                                $fialMeterReading = $mWaterConsumerInitialMeter->getmeterReadingAndDetails($refConsumerId)
                                    ->orderByDesc('id')
                                    ->first();
                                $consumerDemand['lastMeterReading'] = $fialMeterReading->initial_reading ?? 0;
                                break;
                            }
                            $connectionName = $refConnectionName['4'];
                            $consumerDemand['connectionId'] = $flipConnection['Meter/Fixed'];
                            $refConsumerDemand = $mWaterConsumerDemand->consumerDemandByConsumerId($refConsumerId);
                            $fialMeterReading = $mWaterConsumerInitialMeter->getmeterReadingAndDetails($refConsumerId)
                                ->orderByDesc('id')
                                ->first();
                            $finalSecondLastReading = $mWaterConsumerInitialMeter->getSecondLastReading($refConsumerId, $fialMeterReading->id);
                            if (is_null($refConsumerDemand)) {
                                throw new Exception("There should be demand for the previous meter entry!");
                            }
                            $consumerDemand['demandFrom'] = collect($refConsumerDemand)['demand_from'];
                            $consumerDemand['demandUpto'] = collect($refConsumerDemand)['demand_upto'];
                            $startDate  = Carbon::parse($consumerDemand['demandFrom']);
                            $endDate    = Carbon::parse($consumerDemand['demandUpto']);
                            $diffInDays = $endDate->diffInDays($startDate);
                            $refTaxUnitConsumed = ($fialMeterReading['initial_reading'] ?? 0) - ($finalSecondLastReading['initial_reading'] ?? 0);

                            $consumerDemand['lastConsumedUnit'] = round($refTaxUnitConsumed, 2);
                            $consumerDemand['avgReading'] = round(($refTaxUnitConsumed / $diffInDays), 2);
                            $consumerDemand['lastMeterReading'] = $fialMeterReading->initial_reading ?? 0;

                            break;
                        case (2):
                            $connectionName = $refConnectionName['2'];
                            $consumerDemand['connectionId'] = $flipConnection['Gallon'];
                            $fialMeterReading = $mWaterConsumerInitialMeter->getmeterReadingAndDetails($refConsumerId)
                                ->orderByDesc('id')
                                ->first();
                            $consumerDemand['lastMeterReading'] = $fialMeterReading->initial_reading ?? 0;
                            break;
                        case (3):
                            $connectionName = $refConnectionName['3'];
                            $consumerDemand['connectionId'] = $flipConnection['Fixed'];
                            break;
                    }
                    $consumerDemand['meterDetails'] = $refMeterData;
                    $consumerDemand['connectionName'] = $connectionName;
                    $consumerDetails = $consumerDetails->merge($consumerDemand);
                }
                $consumerDetails = $consumerDetails; //->merge($documentDetails);
                return responseMsgs(true, "Consumer Details!", remove_null($consumerDetails), "", "01", ".ms", "POST", $request->deviceId);
            }

            # Get all consumer details 
            $mWaterConsumer = new WaterConsumer();
            $approvedWater = $mWaterConsumer->getConsumerDetails($request);
            $checkExist = $approvedWater->first();
            if ($checkExist) {
                return responseMsgs(true, "Approved Application Details!", $approvedWater, "", "03", "ms", "POST", "");
            }
            throw new Exception("data Not found!");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Get the Field fieldVerifiedInbox 
        | Serial No : 
     */
    public function fieldVerifiedInbox(Request $request)
    {
        try {
            $mWfWardUser            = new WfWardUser();
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();
            $userId                 = authUser($request)->id;
            $ulbId                  = authUser($request)->ulb_id;

            $refWard = $mWfWardUser->getWardsByUserId($userId);
            $wardId = $refWard->map(function ($value) {
                return $value->ward_id;
            });

            $roleId = $this->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleId)->pluck('workflow_id');

            $waterList = $this->getWaterApplicatioList($workflowIds, $ulbId)
                ->whereIn('water_approval_application_details.ward_id', $wardId)
                ->where('is_field_verified', true)
                ->orderByDesc('water_approval_application_details.id')
                ->get();

            return responseMsgs(true, "field Verified Inbox", remove_null($waterList), 010125, 1.0, "", "POST", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
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
            $mWaterApplication  = WaterApplication::findOrFail($req->applicationId);

            $role = $this->_commonFunction->getUserRoll($user->id, $mWaterApplication->ulb_id, $refWorkflowId);
            $this->btcParamcheck($role, $mWaterApplication);

            $this->begin();
            # if application is not applied by citizen 
            if ($mWaterApplication->apply_from != $refApplyFrom['1']) {
                $mWaterApplication->current_role = $mWaterApplication->initiator_role_id;
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
     * | Delete the Application
        | Caution Dont Perform Delete Operation
     */
    public function deleteWaterApplication(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|integer'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                       = authUser($req);
            $mWaterApplication          = new WaterApplication();
            $mWaterApplicant            = new WaterApplicant();
            $mWaterConnectionCharge     = new WaterConnectionCharge();
            $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();

            $applicantDetals = $mWaterApplication->getWaterApplicationsDetails($req->applicationId);
            $this->checkParamsForApplicationDelete($applicantDetals, $user);

            $this->begin();
            $mWaterApplication->deleteWaterApplication($req->applicationId);
            $mWaterApplicant->deleteWaterApplicant($req->applicationId);
            $mWaterConnectionCharge->deleteWaterConnectionCharges($req->applicationId);
            $mWaterPenaltyInstallment->deleteWaterPenelty($req->applicationId);
            $this->commit();
            return responseMsgs(true, "Application Successfully Deleted", "", "", "1.0", "", "POST", $req->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Check the parameter for deleting Application 
     * | @param applicationDetails
     * | @param user
     */
    public function checkParamsForApplicationDelete($applicationDetails, $user)
    {
        $refUserType = Config::get("waterConstaint.REF_USER_TYPE");
        if (!$applicationDetails) {
            throw new Exception("Relted Data or Owner not found!");
        }
        if ($applicationDetails->payment_status == 1) {
            throw new Exception("Your paymnet is done application Cannot be Deleted!");
        }
        if ($user->user_type != $refUserType["1"]) {
            throw new Exception("You are not logedIn as Citizen!");
        }
        if ($applicationDetails->user_id != $user->id) {
            throw new Exception("You'r not the user of this form!");
        }
        if (!is_null($applicationDetails->current_role)) {
            throw new Exception("application is under process can't be deleted!");
        }
    }



    /**
     * | Edit the Water Application
        | Not / validate the payment status / Check the use / Not used
        | 00 ->
     */
    public function editWaterAppliction(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicatonId'  => 'required|integer',
                'owner'         => 'nullable|array',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWaterApplication          = new WaterApplication();
            $mWaterApplicant            = new WaterApplicant();
            $mWaterConnectionCharge     = new WaterConnectionCharge();
            $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();
            $repNewConnectionRepository = new NewConnectionRepository();
            $mwaterAudit                = new waterAudit();
            $levelRoles                 = Config::get('waterConstaint.ROLE-LABEL');
            $refApplicationId           = $req->applicatonId;

            $refWaterApplications = $mWaterApplication->getApplicationById($refApplicationId)->firstorFail();
            $this->checkEditParameters($req, $refWaterApplications);

            $this->begin();
            if ($refWaterApplications->current_role == $levelRoles['BO']) {
                $this->boApplicationEdit($req, $refWaterApplications, $mWaterApplication);
                return responseMsgs(true, "application Modified!", "", "", "01", "ms", "POST", "");
            }

            $refConnectionCharges = $mWaterConnectionCharge->getWaterchargesById($refApplicationId)->firstOrFail();
            $Waterowner = $mWaterApplicant->getOwnerList($refApplicationId)->get();
            $refWaterowner = collect($Waterowner)->map(function ($value) {
                return $value['id'];
            });
            $penaltyInstallment = $mWaterPenaltyInstallment->getPenaltyByApplicationId($refApplicationId)->get();
            $checkPenalty = collect($penaltyInstallment)->first()->values();
            if ($checkPenalty) {
                $refPenaltyInstallment = collect($penaltyInstallment)->map(function ($value) {
                    return  $value['id'];
                });
            }
            $mwaterAudit->saveUpdatedDetailsId($refWaterApplications->id, $refWaterowner, $refConnectionCharges->id, $refPenaltyInstallment);
            $this->deactivateAndUpdateWater($refWaterApplications->id);
            // $refRequest = new Request([
            //     'connectionTypeId'      => $that,
            //     'propertyTypeId'        => $that,
            //     'ownerType'             => $that,
            //     'wardId'                => $that,
            //     'areaSqft'              => $that,
            //     'pin'                   => $that,
            //     'connection_through'    => $that,
            //     'ulbId'                 => $that,
            //     'owners'                => $that,
            // ])
            $repNewConnectionRepository->store($req); // here<-----------------------
            $this->commit();
            return responseMsgs(true, "Successfully Updated the Data", "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Check the Water parameter 
     * | @param req
        | 01<- 
        | Not used
     */
    public function checkEditParameters($request, $refApplication)
    {
        $online = Config::get('payment-constants.ONLINE');
        switch ($refApplication) {
            case ($refApplication->apply_from == $online):
                if ($refApplication->current_role) {
                    throw new Exception("Application is already in Workflow!");
                }
                if ($refApplication->user_id != authUser($request)->id) {
                    throw new Exception("You are not the Autherised Person!");
                }
                if ($refApplication->payment_status == 1) {
                    throw new Exception("Payment has been made Water Cannot be Modified!");
                }
                break;
        }
    }

    /**
     * | Edit the water aplication by Bo
     * | @param req
     * | @param refApplication
    | 01<-
    | Not used
     */
    public function boApplicationEdit($req, $refApplication, $mWaterApplication)
    {
        switch ($refApplication) {
            case ($refApplication->current_role != authUser($req)->id):
                throw new Exception("You Are Not the Valid Person!");
                break;
        }
        $mWaterApplication->editWaterApplication($req);
    }


    /**
     * | Deactivate the Water Deatils
     * | @param
     * | @param
     * | @param
     * | @param
        | 01 <-
        | Not used
        | Rethink
     */
    public function deactivateAndUpdateWater($refWaterApplicationId)
    {
        $mWaterApplication          = new WaterApplication();
        $mWaterApplicant            = new WaterApplicant();
        $mWaterConnectionCharge     = new WaterConnectionCharge();
        $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();

        $mWaterApplication->deactivateApplication($refWaterApplicationId);
        $mWaterApplicant->deactivateApplicant($refWaterApplicationId);
        $mWaterConnectionCharge->deactivateCharges($refWaterApplicationId);
        $mWaterPenaltyInstallment->deactivatePenalty($refWaterApplicationId);
    }


    /**
     * | Citizen view : Get Application Details of viewind
        | Serial No : 
     */
    public function getApplicationDetails(Request $request)
    {

        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user = authUser($request);
            $mWaterSiteInspectionsScheduling = new WaterSiteInspectionsScheduling();
            $mWaterConnectionCharge  = new WaterConnectionCharge();
            $mWaterApplication = new WaterApplication();
            $mWaterApproveApplications   = new WaterApprovalApplicationDetail();
            $mWaterApproveApplicants = new WaterApprovalApplicant();
            $mWaterApplicant = new WaterApplicant();
            $mWaterTran = new WaterTran();
            $roleDetails = Config::get('waterConstaint.ROLE-LABEL');

            # Application Details
            $applicationDetails['applicationDetails'] = $mWaterApplication->fullWaterDetails($request)->first();

            # Document Details
            $metaReqs = [
                'userId'    => $user->id,
                'ulbId'     => $user->ulb_id ?? $applicationDetails['applicationDetails']['ulb_id'],
            ];
            $request->request->add($metaReqs);
            // $document = $this->getDocToUpload($request);                                                    // get the doc details
            // $documentDetails['documentDetails'] = collect($document)['original']['data'];

            # owner details
            $ownerDetails['ownerDetails'] = $mWaterApplicant->getOwnerList($request->applicationId)->get();

            # Payment Details 
            $refAppDetails = collect($applicationDetails)->first();
            $waterTransaction = $mWaterTran->getTransNo($refAppDetails->id, $refAppDetails->connection_type)->get();
            $waterTransDetail['waterTransDetail'] = $waterTransaction;

            # calculation details
            $charges = $mWaterConnectionCharge->getWaterchargesById($refAppDetails['id'])
                ->where('paid_status', 0)
                ->first();
            if ($charges) {
                $calculation['calculation'] = [
                    'connectionFee'     => $charges['conn_fee'],
                    'penalty'           => $charges['penalty'],
                    'totalAmount'       => $charges['amount'],
                    'chargeCatagory'    => $charges['charge_category'],
                    'paidStatus'        => $charges['paid_status']
                ];
                $waterTransDetail = array_merge($waterTransDetail, $calculation);
            }

            # Site inspection schedule time/date Details 
            if ($applicationDetails['applicationDetails']['current_role'] == $roleDetails['JE']) {
                $inspectionTime = $mWaterSiteInspectionsScheduling->getInspectionData($applicationDetails['applicationDetails']['id'])->first();
                $applicationDetails['applicationDetails']['scheduledTime'] = $inspectionTime->inspection_time ?? null;
                $applicationDetails['applicationDetails']['scheduledDate'] = $inspectionTime->inspection_date ?? null;
            }

            $returnData = array_merge($applicationDetails, $ownerDetails, $waterTransDetail); //$documentDetails,
            return responseMsgs(true, "Application Data!", remove_null($returnData), "", "", "", "Post", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Upload Application Documents 
     * | @param req
        | Serial No :
        | Working 
        | Look on the concept of deactivation of the rejected documents 
        | Put the static "verify status" 2 in config  
     */
    public function uploadWaterDoc(Request $req)
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
            $mWaterApplication  = new WaterApplication();
            $relativePath       = Config::get('waterConstaint.WATER_RELATIVE_PATH');
            $refmoduleId        = Config::get('module-constants.WATER_MODULE_ID');

            $getWaterDetails    = $mWaterApplication->getWaterApplicationsDetails($applicationId);
            $refImageName       = $req->docRefName;
            $refImageName       = $getWaterDetails->id . '-' . str_replace(' ', '_', $refImageName);
            $imageName          = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs = [
                'moduleId'      => $refmoduleId,
                'activeId'      => $getWaterDetails->id,
                'workflowId'    => $getWaterDetails->workflow_id,
                'ulbId'         => $getWaterDetails->ulb_id,
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
            $refCheckDocument = $this->checkFullDocUpload($req);

            # Update the Doc Upload Satus in Application Table
            if ($refCheckDocument->contains(false)) {
                $mWaterApplication->deactivateUploadStatus($applicationId);
            } else {
                $this->updateWaterStatus($req, $getWaterDetails);
            }

            # if the application is parked and btc 
            if ($getWaterDetails->parked == true) {
                $mWfActiveDocument->deactivateRejectedDoc($metaReqs);
                $refReq = new Request([
                    'applicationId' => $applicationId
                ]);
                $documentList = $this->getUploadDocuments($refReq);
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
        $mWaterApplication  = new WaterApplication();
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
        $mWaterApplication  = new WaterApplication();
        if ($waterTransaction->tran_type == $refChargeCatagory['SITE_INSPECTON']) {
            throw new Exception("Error there is different charge catagory in application!");
        }
        if ($application->current_role == null) {
            $mWaterApplication->updateCurrentRoleForDa($req->applicationId, $waterRoles['DA']);
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
            $mWaterApplication = new WaterApplication();
            $moduleId = Config::get('module-constants.WATER_MODULE_ID');

            $waterDetails = $mWaterApplication->getApplicationById($req->applicationId)->first();
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
            $mWaterSecondConsumer   = new WaterApplication();   // Application 
            $moduleId               = Config::get('module-constants.WATER_MODULE_ID');

            $connectionId = $request->applicationId;
            $refApplication = $mWaterSecondConsumer->getApplicationById($connectionId)->first();
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
                $uploadDoc = $refWfActiveDocument->getDocByRefIdsDocCode($refApplication->id, $refApplication->workflow_id, $moduleId, $docFor); # Check Document is Uploaded Of That Type
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
     * | Serch the holding and the saf details
     * | Serch the property details for filling the water Application Form
     * | @param request
     * | 01
        | Serial No : 
     */
    public function getSafHoldingDetail(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'connectionThrough' => 'required|int|in:1,2',
                'id'                => 'required',
                'ulbId'             => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mPropProperty          = new PropProperty();
            $mPropOwner             = new PropOwner();
            $mPropFloor             = new PropFloor();
            $mPropActiveSafOwners   = new PropActiveSafsOwner();
            $mPropActiveSafsFloor   = new PropActiveSafsFloor();
            $mPropActiveSaf         = new PropActiveSaf();
            $key                    = $request->connectionThrough;
            $refTenanted            = Config::get('PropertyConstaint.OCCUPANCY-TYPE.TENANTED');
            $refPropertyTypes       = Config::get('PropertyConstaint.PROPERTY-TYPE');
            $refPropertyTypes       = collect($refPropertyTypes)->flip();

            switch ($key) {
                case ("1"):
                    $application = collect($mPropProperty->getPropByHolding($request->id, $request->ulbId));
                    $checkExist = collect($application)->first();
                    if (!$checkExist) {
                        throw new Exception("Data According to Holding Not Found!");
                    }
                    if ($application['prop_type_mstr_id'] == $refPropertyTypes['VACANT LAND']) {
                        throw new Exception("water Cannot Be applied on VACANT LAND!");
                    }
                    if (isset($application['apartment_details_id'])) {
                        $appartmentData = $this->getAppartmentDetails($key, $application);
                        return responseMsgs(true, "related Details!", $appartmentData, "", "", "", "POST", "");
                        break;
                    }
                    # collecting all data 
                    $floorDetails               = $mPropFloor->getFloorsByPropId($application['id']);
                    $builtupArea                = collect($floorDetails)->sum('builtup_area');
                    $areaInSqft['areaInSqFt']   = $builtupArea;
                    $propUsageType              = $this->getPropUsageType($request, $application['id']);
                    $occupancyOwnerType         = collect($mPropFloor->getOccupancyType($application['id'], $refTenanted));
                    $owners['owners']           = collect($mPropOwner->getOwnerByPropId($application['id']));

                    # merge all data for return 
                    $details = $application->merge($areaInSqft)->merge($owners)->merge($occupancyOwnerType)->merge($propUsageType);
                    return responseMsgs(true, "related Details!", $details, "", "", "", "POST", "");
                    break;

                case ("2"):
                    $application = collect($mPropActiveSaf->getSafDtlBySafUlbNo($request->id, $request->ulbId));
                    $checkExist = collect($application)->first();
                    if (!$checkExist) {
                        throw new Exception("Data According to SAF Not Found!");
                    }
                    if ($application['prop_type_mstr_id'] == $refPropertyTypes['VACANT LAND']) {
                        throw new Exception("water Cannot Be applied on VACANT LAND!");
                    }
                    if (isset($application['apartment_details_id'])) {
                        $appartmentData = $this->getAppartmentDetails($key, $application);
                        return responseMsgs(true, "related Details!", $appartmentData, "", "", "", "POST", "");
                        break;
                    }
                    # collecting all data 
                    $floorDetails               = $mPropActiveSafsFloor->getSafFloorsBySafId($application['id']);
                    $areaInSqft['areaInSqFt']   = collect($floorDetails)->sum('builtup_area');
                    $safUsageType               = $this->getPropUsageType($request, $application['id']);
                    $occupancyOwnerType         = collect($mPropActiveSafsFloor->getOccupancyType($application['id'], $refTenanted));
                    $owners['owners']           = collect($mPropActiveSafOwners->getOwnerDtlsBySafId($application['id']));

                    # merge all data for return 
                    $details = $application->merge($areaInSqft)->merge($owners)->merge($occupancyOwnerType)->merge($safUsageType);
                    return responseMsgs(true, "related Details!", $details, "", "", "", "POST", "");
                    break;
            }
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get Usage type according to holding
     * | Calling function : for the search of the property usage type 01.02
        | Serial No : 
     */
    public function getPropUsageType($request, $id)
    {
        $mPropActiveSafsFloor   = new PropActiveSafsFloor();
        $mPropFloor             = new PropFloor();
        $refPropertyTypeId      = config::get('waterConstaint.PROPERTY_TYPE');

        switch ($request->connectionThrough) {
            case ('1'):
                $usageCatagory = $mPropFloor->getPropUsageCatagory($id);
                break;
            case ('2'):
                $usageCatagory = $mPropActiveSafsFloor->getSafUsageCatagory($id);
        }

        $usage = collect($usageCatagory)->map(function ($value) use ($refPropertyTypeId) {
            $var = $value['usage_code'];
            switch (true) {
                case ($var == 'A'):
                    return [
                        'id'        => $refPropertyTypeId['Residential'],
                        'usageType' => 'Residential'                                        // Static
                    ];
                    break;
                case ($var == 'F'):
                    return [
                        'id'        => $refPropertyTypeId['Industrial'],
                        'usageType' => 'Industrial'                                         // Static
                    ];
                    break;
                case ($var == 'G' || $var == 'I'):
                    return [
                        'id'        => $refPropertyTypeId['Government'],
                        'usageType' => 'Government & PSU'                                   // Static
                    ];
                    break;
                case ($var == 'B' || $var == 'C' || $var == 'D' || $var == 'E'):
                    return [
                        'id'        => $refPropertyTypeId['Commercial'],
                        'usageType' => 'Commercial'                                         // Static
                    ];
                    break;
                case ($var == 'H' || $var == 'J' || $var == 'K' || $var == 'L'):
                    return [
                        'id'        => $refPropertyTypeId['Institutional'],
                        'usageType' => 'Institutional'                                      // Static
                    ];
                    break;
                case ($var == 'M'):                                                         // Check wether the property (M) belongs to the commercial catagory
                    return [
                        'id'        => $refPropertyTypeId['Commercial'],
                        'usageType' => 'Other / Commercial'                                 // Static
                    ];
                    break;
            }
        });
        $returnData['usageType'] = $usage->unique()->values();
        return $returnData;
    }

    /**
     * | Get appartment details 
     * | @param propData
     * | @param request
        | Serial No : 
     */
    public function getAppartmentDetails($key, $propData)
    {
        $apartmentId            = $propData['apartment_details_id'];
        $refPropertyTypeId      = Config::get('waterConstaint.PROPERTY_TYPE');
        $mPropFloor             = new PropFloor();
        $mPropProperty          = new PropProperty();
        $mPropOwner             = new PropOwner();
        $mPropActiveSaf         = new PropActiveSaf();
        $mPropActiveSafsFloor   = new PropActiveSafsFloor();
        $mPropActiveSafsOwner   = new PropActiveSafsOwner();

        switch ($key) {
            case ('1'): # For holdingNo
                $propertyDetails    = $mPropProperty->getPropByApartmentId($apartmentId)->get();    # here 
                $propertyIds        = collect($propertyDetails)->pluck('id');
                $floorDetails       = $mPropFloor->getAppartmentFloor($propertyIds)->get();
                $totalBuildupArea   = collect($floorDetails)->sum('builtup_area');

                $returnData['areaInSqFt'] = $totalBuildupArea;
                $returnData['usageType'][] = [
                    'id'        => $refPropertyTypeId['Apartment'],
                    'usageType' => 'Apartment'
                ];
                $returnData['tenanted'] = false;
                $returnData['owners'] = collect($mPropOwner->getOwnerByPropId($propData['id']));
                return $propData->merge($returnData);
                break;

            case ('2'): # For SafNo
                $safDetails         = $mPropActiveSaf->getSafByApartmentId($apartmentId)->get();    # here
                $safIds             = collect($safDetails)->pluck('id');
                $floorDetails       = $mPropActiveSafsFloor->getSafAppartmentFloor($safIds)->get();
                $totalBuildupArea   = collect($floorDetails)->sum('builtup_area');

                $returnData['areaInSqFt'] = $totalBuildupArea;
                $returnData['usageType'][] = [
                    'id'        => $refPropertyTypeId['Apartment'],
                    'usageType' => 'Apartment'
                ];
                $returnData['tenanted'] = false;
                $returnData['owners'] = collect($mPropActiveSafsOwner->getOwnerDtlsBySafId($propData['id']));
                return $propData->merge($returnData);
                break;
        }
    }

    /**
        |-------------------------------------------------------------------------------------------------------|
     */

    /**
     * |---------------------------- Get Document Lists To Upload ----------------------------|
     * | @param req "applicationId"
     * | @var mWaterApplication "Model for WaterApplication"
     * | @var mWaterApplicant "Model for WaterApplicant"
     * | @var refWaterApplication "Contain the detail of water Application"
     * | @var refWaterApplicant "Contain the list of owners"
     * | @var waterTypeDocs "contain the list of Doc to Upload"
     * | @var waterOwnerDocs "Contain the list of owner Doc to Upload"
     * | @var totalDocLists "Application's Doc details"
     * | @return totalDocLists "Collective Data of Doc is returned"
     * | Doc Upload for the Workflow
     * | 01
        | RECHECK
        | Serial No : 
     */
    public function getDocList(Request $req)
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
            $mWaterApplication  = new WaterApplication();
            // $mWaterApplicant    = new WaterApplicant();

            $refWaterApplication = $mWaterApplication->getApplicationById($req->applicationId)->first();                      // Get Saf Details
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
        $uploadedDocs       = $mWfActiveDocument->getDocByRefIds($applicationId, $workflowId, $moduleId);

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
     * |---------------------------- List of the doc to upload ----------------------------|
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

        $type = ["FORM_SCAN_COPY", "STAMP", "ID_PROOF", "PROPERTY TAX"];

        // Check if user_type is not equal to 1
        if ($user->user_type == $refUserType['1']) {
            // Modify $type array for user_type not equal to 1
            $type = ["STAMP", "ID_PROOF"];
        }

        return $mRefReqDocs->getCollectiveDocByCode($moduleId, $type);
    }



    /**
     * |---------------------------- Get owner Doc list ----------------------------|
     * | Calling Function
     * | 01.02
        | Serial No :
     */
    public function getOwnerDocLists($refOwners, $application)
    {
        $mRefReqDocs        = new RefRequiredDocument();
        $mWfActiveDocument  = new WfActiveDocument();
        $moduleId           = Config::get('module-constants.WATER_MODULE_ID');
        $type               = ["ID_PROOF", "CONSUMER_PHOTO"];

        $documentList = $mRefReqDocs->getCollectiveDocByCode($moduleId, $type);
        $ownerDocList['documents'] = collect($documentList)->map(function ($value, $key) use ($application, $refOwners) {
            return $filteredDocs = $this->filterDocument($value, $application, $refOwners['id'])->first();
        });
        if (!empty($documentList)) {
            $ownerPhoto = $mWfActiveDocument->getWaterOwnerPhotograph($application['id'], $application->workflow_id, $moduleId, $refOwners['id']);
            if ($ownerPhoto) {
                $path =  $this->readDocumentPath($ownerPhoto->doc_path);
                $fullDocPath = !empty(trim($ownerPhoto->doc_path)) ? $path : null;
            }
            $ownerDocList['ownerDetails'] = [
                'ownerId'       => $refOwners['id'],
                'name'          => $refOwners['applicant_name'],
                'mobile'        => $refOwners['mobile_no'],
                'guardian'      => $refOwners['guardian_name'],
                'uploadedDoc'   => $fullDocPath ?? "",
                'verifyStatus'  => $ownerPhoto->verify_status ?? ""
            ];
            $ownerDocList['ownerDetails']['reqDocCount'] = $ownerDocList['documents']->count();
            $ownerDocList['ownerDetails']['uploadedDocCount'] = $ownerDocList['documents']->whereNotNull('uploadedDoc')->count();
            return $ownerDocList;
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
     * |---------------------------- Search Application ----------------------------|
     * | Search Application using provided condition For the Admin 
        | Serial No : 
     */
    public function searchWaterConsumer(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'required',
                'parameter' => 'required',
                'pages'     => 'nullable',
                'wardId'    => 'nullable',
                'zoneId'    => 'nullable'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWaterConsumer = new WaterSecondConsumer();
            $mWaterConsumerDemand = new WaterConsumerDemand();
            $key            = $request->filterBy;
            $paramenter     = $request->parameter;
            $pages          = $request->perPage ? $request->perPage : 10;
            $string         = preg_replace("/([A-Z])/", "_$1", $key);
            $refstring      = strtolower($string);
            $wardId         = $request->wardId;
            $zoneId         = $request->zoneId;
            $zone           = null;
            if (in_array($zoneId, ['gov', 'SUS'])) {
                $zoneId = null;
                $zone = $request->zoneId;
            }


            switch ($key) {
                case ("consumerNo"):                                                                        // Static
                    $waterReturnDetails = $mWaterConsumer->getConsumerByItsDetailsV2($request, $refstring, $paramenter, $wardId, $zoneId, $zone)->paginate($pages);
                    $checkVal = collect($waterReturnDetails)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("propertyNo"):
                    $refstring = "folio_no";
                    $waterReturnDetails = $mWaterConsumer->getConsumerByItsDetailsV2($request, $refstring, $paramenter, $wardId, $zoneId, $zone)->paginate($pages);
                    $checkVal = collect($waterReturnDetails)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("applicantName"):
                    $paramenter = strtoupper($paramenter);
                    $waterReturnDetails = $mWaterConsumer->getDetailByOwnerDetails($refstring, $paramenter)->paginate($pages);
                    if (!$waterReturnDetails)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("mobileNo"):
                    $paramenter = strtoupper($paramenter);
                    $waterReturnDetails = $mWaterConsumer->getConsumerByItsDetailsV2($request, $refstring, $paramenter, $wardId, $zoneId, $zone)->paginate($pages);
                    if (!$waterReturnDetails)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ('applicationNo'):
                    $waterReturnDetails = $mWaterConsumer->getDetailByApplicationNo($paramenter)->paginate($pages);
                    $checkVal = collect($waterReturnDetails)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                default:
                    throw new Exception("Data provided in filterBy is not valid!");
            }
            $list = [
                "current_page" => $waterReturnDetails->currentPage(),
                "last_page" => $waterReturnDetails->lastPage(),
                "data" => $waterReturnDetails->items(),
                "total" => $waterReturnDetails->total(),
            ];
            return responseMsgs(true, "Water Consumer Data According To Parameter!", remove_null($list), "", "01", "652 ms", "POST", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Search the Active Application 
     * | @param request
        | Serial No :  
     */
    public function getActiveApplictaions(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'required|in:newConnection,regularization,name,mobileNo,safNo,holdingNo',
                'parameter' => $request->filterBy == 'mobileNo' ? 'required|numeric|digits:10' : "required",
                'pages'     => 'nullable|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            // return $request->all();
            $key                = $request->filterBy;
            $parameter          = $request->parameter;
            $pages              = $request->pages ?? 10;
            $mWaterApplicant    = new WaterApplication();
            $mWaterApplcationDetails = new WaterApprovalApplicationDetail();
            $connectionTypes    = Config::get('waterConstaint.CONNECTION_TYPE');

            switch ($key) {
                case ("newConnection"):                                                                     // Static
                    $returnData = $mWaterApplcationDetails->getDetailsByApplicationNo($request, $connectionTypes['NEW_CONNECTION'], $parameter)->paginate($pages);
                    $checkVal = collect($returnData)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("regularization"):                                                                    // Static
                    $returnData = $mWaterApplicant->getDetailsByApplicationNo($request, $connectionTypes['REGULAIZATION'], $parameter)->paginate($pages);
                    $checkVal = collect($returnData)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("name"):                                                                              // Static
                    $returnData = $mWaterApplicant->getDetailsByParameters($request)
                        ->where("water_applicants.applicant_name", 'ILIKE', '%' . $parameter . '%')
                        ->paginate($pages);
                    $checkVal = collect($returnData)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("mobileNo"):                                                                          // Static
                    $returnData = $mWaterApplicant->getDetailsByParameters($request)
                        ->where("water_applicants.mobile_no", $parameter)
                        ->paginate($pages);
                    $checkVal = collect($returnData)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("safNo"):                                                                             // Static
                    $returnData = $mWaterApplicant->getDetailsByParameters($request)
                        ->where("water_approval_application_details.saf_no", 'LIKE', '%' . $parameter . '%')
                        ->paginate($pages);
                    $checkVal = collect($returnData)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("holdingNo"):                                                                         // Static
                    $returnData = $mWaterApplicant->getDetailsByParameters($request)
                        ->where("water_approval_application_details.holding_no", 'LIKE', '%' . $parameter . '%')
                        ->paginate($pages);
                    $checkVal = collect($returnData)->last();
                    if (!$checkVal || $checkVal == 0)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
            }
            return responseMsgs(true, "List of Appication!", $returnData, "", "01", "723 ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | Document Verify Reject
     * | @param req
        | Serial No :  
        | Discuss about the doc_upload_status should be 0 or not 
     */
    public function docVerifyRejects(Request $req)
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
            $mWfDocument        = new WfActiveDocument();
            $mWaterApplication  = new WaterApplication();
            $mWfRoleusermap     = new WfRoleusermap();
            $wfDocId            = $req->id;
            $applicationId      = $req->applicationId;
            $userId             = authUser($req)->id;
            $wfLevel            = Config::get('waterConstaint.ROLE-LABEL');

            # validating application
            $waterApplicationDtl = $mWaterApplication->getApplicationById($applicationId)
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
                $mWaterApplication->updateAppliVerifyStatus($applicationId);
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
        $mWaterApplication = new WaterApplication();
        $mWfActiveDocument = new WfActiveDocument();
        $refapplication = $mWaterApplication->getApplicationById($applicationId)
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

    public function isAllDocs($applicationId, $refDocList, $refapp)
    {
        $docList = array();
        $verifiedDocList = array();
        $verifiedDocList['rigDocs'] = $refDocList->where('owner_dtl_id', null)->values();
        $collectUploadDocList = collect();
        $rigListDocs = $this->getRigTypeDocList($refapp);
        $docList['rigDocs'] = explode('#', $rigListDocs);
        collect($verifiedDocList['rigDocs'])->map(function ($item) use ($collectUploadDocList) {
            return $collectUploadDocList->push($item['doc_code']);
        });
        $mrigDocs = collect($docList['rigDocs']);
        // List Documents
        $flag = 1;
        foreach ($mrigDocs as $item) {
            if (!$item) {
                continue;
            }
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

    #get doc which is required 
    public function getRigTypeDocList($refapps)
    {
        $moduleId = 2;

        $mrefRequiredDoc = RefRequiredDocument::firstWhere('module_id', $moduleId);
        if ($mrefRequiredDoc && isset($mrefRequiredDoc['requirements'])) {
            $documentLists = $mrefRequiredDoc['requirements'];
        } else {
            $documentLists = [];
        }
        return $documentLists;
    }


    /**
     * | Admin view : Get Application Details of viewind
     * | @param 
     * | @var 
     * | @return 
        | Serial No : 
        | Used Only for new Connection or New Regulization
     */
    public function getApplicationDetailById(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $applicationId              = $request->applicationId;
            $mWaterApplication          = new WaterApplication();
            $mWaterApproveApplication   = new WaterApprovalApplicationDetail();
            $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();
            $mWaterTran                 = new WaterTran();
            $refChargeCatagory          = Config::get("waterConstaint.CHARGE_CATAGORY");
            $refChargeCatagoryValue     = Config::get("waterConstaint.CONNECTION_TYPE");

            # Application Details
           $applicationDetails['applicationDetails'] = $mWaterApproveApplication->fullWaterDetail($applicationId)->first();

            # Payment Details 
            $refAppDetails = collect($applicationDetails)->first();
            if (is_null($refAppDetails))
                throw new Exception("Application Not Found!");

            $waterTransaction = $mWaterTran->getTransNo($refAppDetails->id, $refAppDetails->connection_type)
                ->get();
            $waterTransDetail['waterTransDetail'] = $waterTransaction;
            $returnData = $applicationDetails;    // array_merge($applicationDetails, $waterTransDetail);
            return responseMsgs(true, "Application Data!", remove_null($returnData), "", "", "", "Post", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | List application applied according to its user type
     * | Serch Application btw Dates
     * | @param request
     * | @var 
     * | @return 
        | Serial No:
     */
    public function listApplicationBydate(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'fromDate' => 'required|date_format:Y-m-d',
                'toDate'   => 'required|date_format:Y-m-d',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWaterConnectionCharge     = new WaterConnectionCharge();
            $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();
            $mWaterApplication          = new WaterApplication();
            $refTimeDate = [
                "refStartTime"  => date($request->fromDate),
                "refEndTime"    => date($request->toDate)
            ];
            #application Details according to date
            $refApplications = $mWaterApplication->getapplicationByDate($refTimeDate)
                ->where('water_approval_application_details.user_id', authUser($request)->id)
                ->get();
            # Final Data to return
            $returnValue = collect($refApplications)->map(function ($value, $key)
            use ($mWaterConnectionCharge, $mWaterPenaltyInstallment) {

                # calculation details
                $penaltyList = $mWaterPenaltyInstallment->getPenaltyByApplicationId($value['id'])->get();
                $charges = $mWaterConnectionCharge->getWaterchargesById($value['id'])->get();

                $value['all_payment_status'] = $this->getAllPaymentStatus($charges, $penaltyList);
                $value['calculation'] = collect($charges)->map(function ($values) {
                    return  [
                        'connectionFee'     => $values['conn_fee'],
                        'penalty'           => $values['penalty'],
                        'totalAmount'       => $values['amount'],
                        'chargeCatagory'    => $values['charge_category'],
                        'paidStatus'        => $values['paid_status']
                    ];
                });
                return $value;
            });
            return responseMsgs(true, "listed Application!", remove_null($returnValue), "", "01", "ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", "ms", "POST", "");
        }
    }

    /**
     * | Get all the payment list and payment Status
     * | Checking the payment Satatus
     * | @param 
     * | @param
        | Serial No :
     */
    public function getAllPaymentStatus($charges, $penalties)
    {
        # Connection Charges
        $chargePaymentList = collect($charges)->map(function ($value1) {
            if ($value1['paid_status'] == 0) {
                return false;
            }
            return true;
        });
        if ($chargePaymentList->contains(false)) {
            return false;
        }

        # Penaty listing 
        $penaltyPaymentList = collect($penalties)->map(function ($value2) {
            if ($value2['paid_status'] == 0) {
                return false;
            }
            return true;
        });
        if ($penaltyPaymentList->contains(false)) {
            return false;
        }
        return true;
    }



    #----------------------------------------- Site Inspection ----------------------------------------|


    /**
     * | Search Application for Site Inspection
     * | @param request
     * | @var 
        | Serial No : 
        | Recheck
     */
    public function searchApplicationByParameter(Request $request)
    {
        $filterBy   = Config::get('waterConstaint.FILTER_BY');
        $roleId     = Config::get('waterConstaint.ROLE-LABEL.JE');
        $validated  = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'required',
                'parameter' => $request->filterBy == $filterBy['APPLICATION'] ? 'required' : 'nullable',
                'fromDate'  => $request->filterBy == $filterBy['DATE'] ? 'required|date_format:Y-m-d' : 'nullable',
                'toDate'    => $request->filterBy == $filterBy['DATE'] ? 'required|date_format:Y-m-d' : 'nullable',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $key = $request->filterBy;
            $mWaterApplicant = new WaterApplication();
            $mWaterSiteInspectionsScheduling = new WaterSiteInspectionsScheduling();

            switch ($key) {
                case ("byApplication"):                                                 // Static
                    $refSiteDetails['SiteInspectionDate'] = null;
                    $refApplication = $mWaterApplicant->getApplicationByNo($request->parameter, $roleId)->get();
                    $returnData = collect($refApplication)->map(function ($value) use ($mWaterSiteInspectionsScheduling) {
                        $refViewSiteDetails['viewSiteDetails'] = false;
                        $refSiteDetails['SiteInspectionDate'] = $mWaterSiteInspectionsScheduling->getInspectionById($value['id'])->first();
                        if (isset($refSiteDetails['SiteInspectionDate'])) {
                            $refViewSiteDetails['viewSiteDetails'] = $this->canViewSiteDetails($refSiteDetails['SiteInspectionDate']);
                            return  collect($value)->merge(collect($refSiteDetails))->merge(collect($refViewSiteDetails));
                        }
                        $refSiteDetails['SiteInspectionDate'] = $mWaterSiteInspectionsScheduling->getInspectionData($value['id'])->first();
                        return  collect($value)->merge(collect($refSiteDetails))->merge(collect($refViewSiteDetails));
                    });

                    break;
                case ("byDate"):                                                         // Static
                    $refTimeDate = [
                        "refStartTime"  => Carbon::parse($request->fromDate)->format('Y-m-d'),
                        "refEndTime"    => Carbon::parse($request->toDate)->format('Y-m-d')
                    ];
                    $refData = $mWaterApplicant->getapplicationByDate($refTimeDate)->get();
                    $returnData = collect($refData)->map(function ($value) use ($roleId, $mWaterSiteInspectionsScheduling) {
                        if ($value['current_role'] == $roleId) {
                            $refViewSiteDetails['viewSiteDetails'] = false;
                            $refSiteDetails['SiteInspectionDate'] = $mWaterSiteInspectionsScheduling->getInspectionById($value['id'])->first();
                            if (isset($refSiteDetails['SiteInspectionDate'])) {
                                $refViewSiteDetails['viewSiteDetails'] = $this->canViewSiteDetails($refSiteDetails['SiteInspectionDate']);
                                return  collect($value)->merge(collect($refSiteDetails))->merge(collect($refViewSiteDetails));
                            }
                            $refSiteDetails['SiteInspectionDate'] = $mWaterSiteInspectionsScheduling->getInspectionData($value['id'])->first();
                            return  collect($value)->merge(collect($refSiteDetails))->merge(collect($refViewSiteDetails));
                            return $value;
                        }
                    })->filter()->values();
                    break;
            }
            return responseMsgs(true, "Searched Data!", remove_null($returnData), "", "01", "ms", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", "ms", "POST", "");
        }
    }


    /**
     * | Can View Site Details 
     * | Check if the provided date is matchin to the current date
     * | @param sitDetails 
        | Serial No :   
        | Recheck
     */
    public function canViewSiteDetails($sitDetails)
    {
        if ($sitDetails['inspection_date'] == Carbon::now()->format('Y-m-d')) {
            return true;
        }
        return false;
    }

    /**
     * | Cancel Site inspection 
     * | In case of date missmatch or changes
     * | @param request
     * | @var
     * | @return  
        | Serial No :
        | Working
     */
    public function cancelSiteInspection(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $this->checkForSaveDateTime($request);
            $this->checkforPaymentStatus($request);

            $refApplicationId                   = $request->applicationId;
            $mWaterSiteInspectionsScheduling    = new WaterSiteInspectionsScheduling();
            $mWaterConnectionCharge             = new WaterConnectionCharge();
            $mWaterPenaltyInstallment           = new WaterPenaltyInstallment();
            $mWaterApplication                  = new WaterApplication();
            $mWaterSiteInspection               = new WaterSiteInspection();
            $refSiteInspection                  = Config::get("waterConstaint.CHARGE_CATAGORY.SITE_INSPECTON");
            $refJeRole                          = Config::get("waterConstaint.ROLE-LABEL.JE");

            $refSiteInspection = $mWaterSiteInspection->getSiteDetails($refApplicationId)
                ->where('order_officer', $refJeRole)
                ->first();

            $this->begin();
            $mWaterSiteInspectionsScheduling->cancelInspectionDateTime($refApplicationId);
            $mWaterConnectionCharge->deactivateSiteCharges($refApplicationId, $refSiteInspection);
            $mWaterPenaltyInstallment->deactivateSitePenalty($refApplicationId, $refSiteInspection);
            # Check if the payment status is done or not 
            $mWaterApplication->updateOnlyPaymentstatus($refApplicationId);                                 // make the payment status of the application true
            if (!is_null($refSiteInspection)) {
                $mWaterSiteInspection->deactivateSiteDetails($refSiteInspection->site_inspection_id);
            }
            $this->commit();
            return responseMsgs(true, "Scheduled Date is Cancelled!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }


    /**
     * | Check the param for cancel of site inspection date and corresponding data 
     * | @param request
        | Serial No :
        | Recheck
     */
    public function checkforPaymentStatus($request)
    {
        $applicationId  = $request->applicationId;
        $mWaterTran     = new WaterTran();

        $sitePayment = $mWaterTran->siteInspectionTransaction($applicationId)->first();
        if ($sitePayment) {
            throw new Exception("Payment for Site Inspction has done!");
        }
    }


    /**
     * | Save the site Inspection Date and Time 
     * | Create record behalf of the date and time with respective to application no
     * | @param request
     * | @var 
     * | @return 
        | Serial No : 
        | Working
     */
    public function saveInspectionDateTime(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId'     => 'required',
                'inspectionDate'    => 'required|date|date_format:Y-m-d',
                'inspectionTime'    => 'required|date_format:H:i'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $this->checkForSaveDateTime($request);
            $mWaterSiteInspectionsScheduling = new WaterSiteInspectionsScheduling();
            $refDate = Carbon::now()->format('Y-m-d');

            $this->begin();
            $mWaterSiteInspectionsScheduling->saveSiteDateTime($request);
            if ($request->inspectionDate == $refDate) {
                $canView['canView'] = true;
            } else {
                $canView['canView'] = false;
            }
            $this->commit();
            return responseMsgs(true, "Date for the Site Inspection is Saved!", $canView, "", "01", ".ms", "POST", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Check the validation for saving the site inspection 
     * | @param request
        | Serial No :
        | Working
        | Add more Validation 
     */
    public function checkForSaveDateTime($request)
    {
        $mWfRoleUser        = new WfRoleusermap();
        $refApplication     = WaterApplication::findOrFail($request->applicationId);
        $WaterRoles         = Config::get('waterConstaint.ROLE-LABEL');
        $metaReqs = new Request([
            'userId'        => authUser($request)->id,
            'workflowId'    => $refApplication->workflow_id
        ]);
        $readRoles = $mWfRoleUser->getRoleByUserWfId($metaReqs);                      // Model to () get Role By User Id
        if (is_null($readRoles)) {
            throw new Exception("Role not found!");
        }
        if ($refApplication['current_role'] != $WaterRoles['JE']) {
            throw new Exception("Application is not Under JE!");
        }
        if ($readRoles->wf_role_id != $WaterRoles['JE']) {
            throw new Exception("you Are Not Autherised for the process!");
        }
    }


    /**
     * | Get the Date/Time alog with site details 
     * | Site Details  
        | Serial No :
        | Working
        | Recheck
     */
    public function getSiteInspectionDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $refReturnData['canInspect'] = false;
            $mWaterSiteInspectionsScheduling = new WaterSiteInspectionsScheduling();
            $siteInspection = $mWaterSiteInspectionsScheduling->getInspectionById($request->applicationId)->first();
            if (isset($siteInspection)) {
                $canInspect = $this->checkCanInspect($siteInspection);
                $returnData = [
                    "inspectionDate" => $siteInspection->inspection_date,
                    "inspectionTime" => $siteInspection->inspection_time,
                    "canInspect"     => $canInspect
                ];
                return responseMsgs(true, "Site InspectionDetails!", $returnData, "", "01", ".ms", "POST", "");
            }
            return responseMsgs(false, "Data not found!", $refReturnData, "01", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", "");
        }
    }


    /**
     * | Check if the current Application will be Inspected
     * | Checking the sheduled Date for inspection
     * | @param
     * | @var 
        | Serial No : 
        | Working
     */
    public function checkCanInspect($siteInspection)
    {
        $refDate = Carbon::now()->format('Y-m-d');
        if ($siteInspection->inspection_date == $refDate) {
            $canInspect = true;
        } else {
            $canInspect = false;
        }
        return $canInspect;
    }


    /**
     * | Online site Inspection 
     * | Assistent Enginer site detail Entry
     * | @param request
     * | @var 
     * | @return 
        | Serial No :
        | Working
        | Check for deactivation of technical site inspection details 
        | opration should be adding a new record
     */
    public function onlineSiteInspection(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required',
                'waterLockArng' => 'required',
                'gateValve'     => 'required',
                'pipelineSize'  => 'required',
                'pipeSize'      => 'required|in:15,20,25',
                'ferruleType'   => 'required|in:6,10,12,16'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                   = authUser($request);
            $current                = Carbon::now();
            $currentDate            = $current->format('Y-m-d');
            $currentTime            = $current->format('H:i:s');
            $mWaterSiteInspection   = new WaterSiteInspection();
            $refAeRole              = Config::get("waterConstaint.ROLE-LABEL.AE");

            $refDetails = $this->onlineSitePreConditionCheck($request);
            $refTechnicalDetails = $mWaterSiteInspection->getSiteDetails($request->applicationId)
                ->where('order_officer', $refAeRole)
                ->first();
            $request->request->add([
                'wardId'            => $refDetails['refApplication']->ward_id,
                'userId'            => $user->id,
                'applicationId'     => $refDetails['refApplication']->id,
                'roleId'            => $refDetails['roleDetails']->wf_role_id,
                'inspectionDate'    => $currentDate,
                'inspectionTime'    => $currentTime
            ]);
            $this->begin();
            $mWaterSiteInspection->saveOnlineSiteDetails($request);
            if ($refTechnicalDetails) {
                $mWaterSiteInspection->deactivateSiteDetails($refTechnicalDetails->site_inspection_id);
            }
            $this->commit();
            return responseMsgs(true, "Technical Inspection Completed!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Check the Pre Site inspection Details 
     * | pre conditional Check for the AE online Site inspection
     * | @param
     * | @var mWfRoleUser
        | Serial No :
        | Working 
     */
    public function onlineSitePreConditionCheck($request)
    {
        $mWfRoleUser    = new WfRoleusermap();
        $WaterRoles     = Config::get('waterConstaint.ROLE-LABEL');
        $refApplication = WaterApplication::findOrFail($request->applicationId);
        $workflowId     = $refApplication->workflow_id;

        $metaReqs =  new Request([
            'userId'        => authUser($request)->id,
            'workflowId'    => $workflowId
        ]);
        $readRoles = $mWfRoleUser->getRoleByUserWfId($metaReqs);                      // Model to () get Role By User Id

        # Condition checking
        if ($refApplication['current_role'] != $WaterRoles['AE']) {
            throw new Exception("Application is not under Assistent Engineer!");
        }
        if ($readRoles->wf_role_id != $WaterRoles['AE']) {
            throw new Exception("You are not autherised for the process!");
        }
        if ($refApplication['is_field_verified'] == false) {
            throw new Exception("Site verification by Junier Engineer is not done!");
        }
        return [
            'refApplication' => $refApplication,
            'roleDetails' => $readRoles
        ];
    }


    /**
     * | Get Site Inspection Details done by Je
     * | Details Filled by JE
     * | @param request
     * | @var 
     * | @return 
        | Serial No : 
        | Working
     */
    public function getJeSiteDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            # variable defining
            $returnData['final_verify']         = false;
            $mWaterSiteInspection               = new WaterSiteInspection();
            $mWaterSiteInspectionsScheduling    = new WaterSiteInspectionsScheduling();
            $refJe                              = Config::get("waterConstaint.ROLE-LABEL.JE");
            $propertyTypeMapping                = Config::get('waterConstaint.PROPERTY_TYPE');
            $connectionTypeMapping              = Config::get('waterConstaint.REF_CONNECTION_TYPE');
            $pipelineTypeMapping                = Config::get('waterConstaint.PARAM_PIPELINE');

            $flipPropertyTypeMapping    = collect($propertyTypeMapping)->flip();
            $flipConnectionTypeMapping  = collect($connectionTypeMapping)->flip();
            $flipPipelineTypeMapping    = collect($pipelineTypeMapping)->flip();

            # level logic
            $sheduleDate = $mWaterSiteInspectionsScheduling->getInspectionData($request->applicationId)->first();
            if (!is_null($sheduleDate) && $sheduleDate->site_verify_status == true) {
                $returnData = $mWaterSiteInspection->getSiteDetails($request->applicationId)
                    ->where('order_officer', $refJe)
                    ->first();
                $returnData['final_verify']         = true;
                $returnData['property_type_name']   = $flipPropertyTypeMapping[$returnData->property_type_id];
                $returnData['connection_type_name'] = $flipConnectionTypeMapping[$returnData->connection_type_id];
                $returnData['pipeline_type_name']   = $flipPipelineTypeMapping[$returnData->pipeline_type_id];
                return responseMsgs(true, "JE Inspection details!", remove_null($returnData), "", "01", ".ms", "POST", $request->deviceId);
            }
            return responseMsgs(true, "Data not Found!", remove_null($returnData), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", $request->deviceId);
        }
    }

    /**
     * | Get AE technical Inspection
     * | Pick the first details for the respective application 
     * | @param request
     * | @var 
     * | @return 
        | Serial No :
        | Working
     */
    public function getTechnicalInsDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            # variable defining
            $mWaterSiteInspection   = new WaterSiteInspection();
            $refRole                = Config::get("waterConstaint.ROLE-LABEL");
            # level logic
            $returnData['aeData'] = $mWaterSiteInspection->getSiteDetails($request->applicationId)
                ->where('order_officer', $refRole['AE'])
                ->first();
            $jeData = $this->jeSiteInspectDetails($request, $refRole);
            $returnData['jeData'] = $jeData;
            return responseMsgs(true, "AE Inspection details!", remove_null($returnData), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", $request->deviceId);
        }
    }

    /**
     * | Check and get the je site inspection details
     * | @param request
        | Serial No :
        | Working
     */
    public function jeSiteInspectDetails($request, $refRole)
    {
        $mWaterApplication      = new WaterApplication();
        $mWaterSiteInspection   = new WaterSiteInspection();
        $applicationId          = $request->applicationId;

        $applicationDetails = $mWaterApplication->getApplicationById($applicationId)
            ->where('is_field_verified', true)
            ->first();
        if (!$applicationDetails) {
            throw new Exception("Application not found!");
        }
        $jeData = $mWaterSiteInspection->getSiteDetails($applicationId)
            ->where('order_officer', $refRole['JE'])
            ->first();
        if (!$jeData) {
            throw new Exception("JE site inspection data not found!");
        }
        $returnData = [
            'pipeline_size' => $jeData->pipeline_size,
            'pipe_size'     => $jeData->pipe_size,
            'ferrule_type'  => $jeData->ferrule_type
        ];
        return $returnData;
    }


    /**
        | May be used recheck
        | Not used
     */
    // public function btcDocUpload(Request $req)
    // {
    //     $req->validate([
    //         "applicationId" => "required|numeric",
    //     ]);
    //     try {
    //         $response = true;
    //         $applicationId = $req->applicationId;
    //         $mWaterApplication = new WaterApplication();

    //         $mWaterApplication->getWaterApplicationsDetails($applicationId);
    //         $this->begin();
    //         #check full doc upload
    //         $refCheckDocument = $this->checkFullDocUpload($req);
    //         # Update the Doc Upload Satus in Application Table
    //         if ($refCheckDocument->contains(false)) {
    //             $mWaterApplication->deactivateUploadStatus($applicationId);
    //             $response = false;
    //         } else {
    //             $status = true;
    //             $mWaterApplication->updateParkedstatus($status, $applicationId);
    //         }
    //         $this->commit();
    //         if ($response == false)
    //             throw new Exception("Full document not uploaded!");
    //         return responseMsgs(true, "Document Uploadation Successful", "", "", "1.0", "", "POST", $req->deviceId ?? "");
    //     } catch (Exception $e) {
    //         return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $req->deviceId);
    //     }
    // }

    /**
     * get all details 
     */
    public function getdetailsbyId(Request $req)
    {
        $req->validate([
            "applicationId" => "required|numeric",
        ]);

        try {
            $mwaterSecondConsumer = new WaterSecondConsumer();
            $water = $mwaterSecondConsumer->getallDetails($req->input('applicationId'));
            if (!$water) {
                throw new Exception('Application not found');
            }

            return responseMsgs(true, "get all details", $water, "", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) { // Catch a more specific exception
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
 apply water connection for akola

     */
    public function applyWaterNew(Request $req)
    {
        # ref variables
        try {
            $user           = authUser($req);
            $ulbId          = $req->ulbId;
            $owner          = $req['onwerDetails'];
            $connectypeId   = $req->connectionTypeId ?? 1;

            $ulbWorkflowObj         = new WfWorkflow();
            $mWaterNewConnection    = new WaterNewConnection();
            $mWaterApplication      = new WaterApplication();
            $mWaterApplicant        = new WaterApplicant();
            $mWaterCharges          = new WaterConnectionCharge();
            $mWorkflowTrack         = new WorkflowTrack();
            $mWaterChrges           = new WaterConnectionTypeCharge();
            $mPropProperty          = new PropProperty();
            $workflowID             = Config::get('workflow-constants.WATER_MASTER_ID');
            $refUserType            = Config::get('waterConstaint.REF_USER_TYPE');
            $refApplyFrom           = Config::get('waterConstaint.APP_APPLY_FROM');
            $refPropertyType        = Config::get("waterConstaint.PAYMENT_FOR_CONSUMER");
            $waterRole              = Config::get("waterConstaint.ROLE-LABEL");
            $confModuleId           = Config::get('module-constants.WATER_MODULE_ID');
            $refParamId             = Config::get('waterConstaint.PARAM_IDS');

            # Connection Type 
            switch ($req->connectionTypeId) {
                case (1):
                    $connectionType = "New Connection";                                     // Static
                    break;
            }
            if ($req->Category == 'Slum' && $req->TabSize != 15) {
                throw new Exception('Tab size must be 15 for Slum');
            }
            if ($req->PropertyType == '2' && $req->Category == 'Slum') {
                throw new Exception('slum is not under the commercial');
            }

            $mProperty = $mPropProperty->getPropertyId($req->propertyNo);
            if (!$mProperty) {
                throw new Exception('holding not found');
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
            #coonection charges 
            $refRequest["initiatorRoleId"]   = collect($initiatorRoleId)->first()->role_id;
            $refRequest["finisherRoleId"]    = collect($finisherRoleId)->first()->role_id;
            $refRequest['roleId']            = $roleId ?? null;
            $refRequest['userType']          = $user->user_type;

            # Get the role details and distinguish btw user and employ
            # If the user is not citizen
            if ($user->user_type != $refUserType['1']) {
                $req->merge(['workflowId' => $ulbWorkflowId->id]);
                $roleDetails = $this->getRole($req);
                if (!$roleDetails) {
                    throw new Exception("Role detail Not found!");
                }
                $roleId = $roleDetails['wf_role_id'];
                $refRequest = [
                    "applyFrom" => $user->user_type,
                    "empId"     => $user->id
                ];
            } else {
                $refRequest = [
                    "applyFrom" => $refApplyFrom['1'],
                    "citizenId" => $user->id
                ];
            }

            # collect the application charges 
            $Charges = $mWaterChrges->getChargesByIds($connectypeId);

            $this->begin();
            # Generating Application No
            $idGeneration   = new PrefixIdGenerator($refParamId["WAPP"], $ulbId);
            $applicationNo  = $idGeneration->generate();
            $applicationNo  = str_replace('/', '-', $applicationNo);

            $applicationId = $mWaterApplication->saveWaterApplications($connectypeId, $req, $ulbWorkflowId, $initiatorRoleId, $finisherRoleId, $ulbId, $applicationNo);
            $meta = [
                'applicationId'     => $applicationId->id,
                "amount"            => $Charges->amount,
                "chargeCategory"    => $Charges->charge_category,
            ];

            # water applicant
            foreach ($owner as $owners) {
                $mWaterApplicant->saveWaterApplicant($meta, $owners);
            }

            $mWaterApplicant = $mWaterCharges->saveWaterCharges($meta);
            # save for  work flow track
            $metaReqs = new Request(
                [
                    'citizenId'         => $refRequest['citizenId'] ?? null,
                    'moduleId'          => $confModuleId,
                    'workflowId'        => $ulbWorkflowId->id,
                    'refTableDotId'     => 'water_applications.id',             // Static                          // Static                              // Static
                    'refTableIdValue'   => $meta['applicationId'],
                    'user_id'           => $refRequest['empId'] ?? null,
                    'ulb_id'            => $ulbId,
                    'senderRoleId'      => $roleId ?? null,
                    'receiverRoleId'    => collect($initiatorRoleId)->first()->role_id,
                ]
            );
            $mWorkflowTrack->saveTrack($metaReqs);
            $this->commit();
            $returnResponse = [
                'applicationId' => $meta['applicationId'],
                'applicationNo' => $applicationNo,

            ];
            return responseMsgs(true, "Successfully Saved!", $returnResponse, "", "02", "", "POST", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * search holding  
     */
    public function searchHolding(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'PropertyNo' => 'required',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $holding    = $request->PropertyNo;
            $mPropPerty = new PropProperty();
            $mPropOwner = new PropOwner();
            $holdingDetails = $mPropPerty->getPropert($holding);
            if (!$holdingDetails) {
                throw new Exception('holding not found !');
            }
            $holdingOwnerDeails = $mPropOwner->getOwnerByPropId($holdingDetails->id);
            $holdingDetails['ownerDetails'] = $holdingOwnerDeails;
            return responseMsgs(true, "Property Details!", remove_null($holdingDetails), "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    public function getPropUsageTypes($request, $id)
    {
        $mPropActiveSafsFloor   = new PropActiveSafsFloor();
        $mPropFloor             = new PropFloor();
        $refPropertyTypeId      = config::get('waterConstaint.PROPERTY_TYPE');

        switch ($request->connectionThrough) {
            case ('1'):
                $usageCatagory = $mPropFloor->getPropUsageCatagory($id);
                break;
            case ('2'):
                $usageCatagory = $mPropActiveSafsFloor->getSafUsageCatagory($id);
        }

        $usage = collect($usageCatagory)->map(function ($value) use ($refPropertyTypeId) {
            $var = $value['usage_code'];
            switch (true) {
                case ($var == 'A'):
                    return [
                        'id'        => $refPropertyTypeId['Residential'],
                        'usageType' => 'Residential'                                        // Static
                    ];
                    break;
                case ($var == 'F'):
                    return [
                        'id'        => $refPropertyTypeId['Industrial'],
                        'usageType' => 'Industrial'                                         // Static
                    ];
                    break;
                case ($var == 'G' || $var == 'I'):
                    return [
                        'id'        => $refPropertyTypeId['Government'],
                        'usageType' => 'Government & PSU'                                   // Static
                    ];
                    break;
                case ($var == 'B' || $var == 'C' || $var == 'D' || $var == 'E'):
                    return [
                        'id'        => $refPropertyTypeId['Commercial'],
                        'usageType' => 'Commercial'                                         // Static
                    ];
                    break;
                case ($var == 'H' || $var == 'J' || $var == 'K' || $var == 'L'):
                    return [
                        'id'        => $refPropertyTypeId['Institutional'],
                        'usageType' => 'Institutional'                                      // Static
                    ];
                    break;
                case ($var == 'M'):                                                         // Check wether the property (M) belongs to the commercial catagory
                    return [
                        'id'        => $refPropertyTypeId['Commercial'],
                        'usageType' => 'Other / Commercial'                                 // Static
                    ];
                    break;
            }
        });
        $returnData['usageType'] = $usage->unique()->values();
        return $returnData;
    }

    #check only
    public function check(Request $request)
    {
        try {
            $articlesSet = array_flip([
                "a", "an", "the", "and", "but", "or", "for", "nor", "so",
                "yet", "in", "on", "at", "by", "with", "about", "before", "after",
                "during", "under", "over", "between", "through", "above", "below", "I", "you", "he",
                "she", "it", "we", "they", "me", "him", "her", "us", "them", "am", "is", "are", "was",
                "were", "be", "being", "been", "do", "does", "did", "have", "has", "had", "shall",
                "will", "should", "would", "may", "might", "must", "can", "could", "this", "that",
                "these", "those", "my", "your", "his", "her", "its", "our", "their", "oh", "wow", "ouch", "hey", "hello", "hi"
            ]);

            $inputText = $request->input('var');
            $words = preg_split("/\s+/", $inputText);

            // Filter out words that are in the $articles set
            $filteredWords = array_filter($words, function ($word) use ($articlesSet) {
                return !isset($articlesSet[strtolower($word)]);
            });

            $resultText = implode(" ", $filteredWords);

            return responseMsgs(true, "check", $resultText, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Water Approve Application
     */

    public function getCitizenApproveApplication(Request $request)
    {

        try {


            $refUser                = authUser($request);
            $refUserId              = $refUser->id;
            $roleDetails            = Config::get('waterConstaint.ROLE-LABEL');

            $mWaterTran             = new WaterTran();
            $mWaterParamConnFee     = new WaterParamConnFee();
            $mWaterConnectionCharge = new WaterConnectionCharge();
            $mWaterSiteInspection   = new WaterSiteInspection();

            $mWaterPenaltyInstallment           = new WaterPenaltyInstallment();
            $mWaterSiteInspectionsScheduling    = new WaterSiteInspectionsScheduling();
            $refChargeCatagory                  = Config::get("waterConstaint.CHARGE_CATAGORY");
            $refChargeCatagoryValue             = Config::get("waterConstaint.CONNECTION_TYPE");


            $connection = WaterApprovalApplicationDetail::select(
                "water_approval_application_details.id",
                "water_approval_application_details.application_no",
                "water_approval_application_details.property_type_id",
                "water_approval_application_details.address",
                "water_approval_application_details.area_sqft",
                "water_approval_application_details.payment_status",
                "water_approval_application_details.doc_status",
                "water_approval_application_details.ward_id",
                "water_approval_application_details.workflow_id",
                "water_approval_application_details.doc_upload_status",
                "water_approval_application_details.apply_from",
                "water_approval_application_details.is_field_verified",
                "water_approval_application_details.current_role",
                "water_approval_application_details.parked",
                "water_approval_application_details.category",
                "water_approval_application_details.connection_type_id",
                "ulb_ward_masters.ward_name",
                "charges.amount",
                "wf_roles.role_name as current_role_name",
                DB::raw("'connection' AS type,
                                        water_approval_application_details.apply_date::date AS apply_date")
            )
                ->join(
                    DB::raw("( 
                                        SELECT DISTINCT(water_approval_application_details.id) AS application_id , SUM(COALESCE(amount,0)) AS amount
                                        FROM water_approval_application_details 
                                        LEFT JOIN water_connection_charges 
                                            ON water_approval_application_details.id = water_connection_charges.application_id 
                                            AND ( 
                                                water_connection_charges.paid_status ISNULL  
                                                OR water_connection_charges.paid_status= 0 
                                            )  
                                            AND( 
                                                    water_connection_charges.status = TRUE
                                                    OR water_connection_charges.status ISNULL  
                                                )
                                        WHERE water_approval_application_details.user_id = $refUserId
                                        GROUP BY water_approval_application_details.id
                                        ) AS charges
                                    "),
                    function ($join) {
                        $join->on("charges.application_id", "water_approval_application_details.id");
                    }
                )
                // ->whereNotIn("status",[0,6,7])
                ->leftjoin('wf_roles', 'wf_roles.id', "=", "water_approval_application_details.current_role")
                ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_approval_application_details.ward_id')
                ->where("water_approval_application_details.user_id", $refUserId)
                ->orderbydesc('water_approval_application_details.id')
                ->get();

            $checkData = collect($connection)->first();
            if (is_null($checkData))
                throw new Exception("Water Applications not found!");

            $returnValue = collect($connection)->map(function ($value)
            use ($mWaterPenaltyInstallment, $refChargeCatagoryValue, $refChargeCatagory, $mWaterTran, $mWaterParamConnFee, $mWaterConnectionCharge, $mWaterSiteInspection, $mWaterSiteInspectionsScheduling, $roleDetails) {

                # checking Penalty payment
                if ($value['payment_status'] == 1 && $value['connection_type_id'] == $refChargeCatagoryValue['REGULAIZATION']) {
                    $penaltyDetails = $mWaterPenaltyInstallment->getPenaltyByApplicationId($value['id'])
                        ->where('paid_status', 0)
                        ->get();
                    $checkPenalty = collect($penaltyDetails)->first();
                    if (is_null($checkPenalty)) {
                        $value['actualPaymentStatus'] = 1;
                    } else {
                        $value['actualPaymentStatus'] = 0;
                    }
                }

                # show connection charges
                switch ($value['connection_type_id']) {
                    case ($refChargeCatagoryValue['REGULAIZATION']):
                        $value['connection_type_name'] = $refChargeCatagory['REGULAIZATION'];
                        break;

                    case ($refChargeCatagoryValue['NEW_CONNECTION']):
                        $value['connection_type_name'] = $refChargeCatagory['NEW_CONNECTION'];
                        break;
                }

                $value['transDetails'] = $mWaterTran->getTransNo($value['id'], null)->first();
                $value['calcullation'] = $mWaterParamConnFee->getCallParameter($value['property_type_id'], $value['area_sqft'])->first();
                $refConnectionCharge = $mWaterConnectionCharge->getWaterchargesById($value['id'])
                    ->where('paid_status', 0)
                    ->first();
                # Formating connection type id 
                $chargeId =  null;
                if (!is_null($refConnectionCharge)) {
                    switch ($refConnectionCharge['charge_category']) {
                        case ($refChargeCatagory['SITE_INSPECTON']):
                            $chargeId = $refChargeCatagoryValue['SITE_INSPECTON'];
                            break;
                        case ($refChargeCatagory['NEW_CONNECTION']):
                            $chargeId = $refChargeCatagoryValue['NEW_CONNECTION'];
                            break;
                        case ($refChargeCatagory['REGULAIZATION']):
                            $chargeId = $refChargeCatagoryValue['REGULAIZATION'];
                            break;
                    }
                    $refConnectionCharge['connectionTypeId'] = $chargeId;
                }
                $refConnectionCharge['type'] = $value['type'];
                $refConnectionCharge['applicationId'] = $value['id'];
                $refConnectionCharge['applicationNo'] = $value['application_no'];
                $value['connectionCharges'] = $refConnectionCharge;

                # Site Details 
                $siteDetails = $mWaterSiteInspection->getInspectionById($value['id'])
                    ->where('order_officer', $roleDetails['JE'])
                    ->first();
                $checkEmpty = collect($siteDetails)->first();
                if (!is_null($checkEmpty)) {
                    $value['siteInspectionCall'] = $mWaterParamConnFee->getCallParameter(
                        $siteDetails['site_inspection_property_type_id'],
                        $siteDetails['site_inspection_area_sqft']
                    )->first();
                }
                if ($value['current_role'] == $roleDetails['JE']) {
                    $inspectionTime = $mWaterSiteInspectionsScheduling->getInspectionData($value['id'])->first();
                    $value['scheduledTime'] = $inspectionTime->inspection_time ?? null;
                    $value['scheduledDate'] = $inspectionTime->inspection_date ?? null;
                }

                return $value;
            });

            return responseMsg(true, "", remove_null($returnValue));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Citizen view : Get Application Details of viewind
        | Serial No : 
     */
    public function getApproveApplicationsDetails(Request $request)
    {

        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|integer',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user = authUser($request);
            $mWaterSiteInspectionsScheduling = new WaterSiteInspectionsScheduling();
            $mWaterConnectionCharge  = new WaterConnectionCharge();
            $mWaterApplication = new WaterApplication();
            $mWaterApproveApplications   = new WaterApprovalApplicationDetail();
            $mWaterApproveApplicants = new WaterApprovalApplicant();
            $mWaterApplicant = new WaterApplicant();
            $mWaterTran = new WaterTran();
            $roleDetails = Config::get('waterConstaint.ROLE-LABEL');

            # Application Details
            $applicationDetails['applicationDetails'] = $mWaterApproveApplications->fullWaterDetails($request)->first();

            # Document Details
            $metaReqs = [
                'userId'    => $user->id,
                'ulbId'     => $user->ulb_id ?? $applicationDetails['applicationDetails']['ulb_id'],
            ];
            $request->request->add($metaReqs);
            // $document = $this->getDocToUpload($request);                                                    // get the doc details
            // $documentDetails['documentDetails'] = collect($document)['original']['data'];

            # owner details
            $ownerDetails['ownerDetails'] = $mWaterApproveApplicants->getOwnerList($request->applicationId)->get();

            # Payment Details 
            $refAppDetails = collect($applicationDetails)->first();
            $waterTransaction = $mWaterTran->getTransNo($refAppDetails->id, $refAppDetails->connection_type)->get();
            $waterTransDetail['waterTransDetail'] = $waterTransaction;

            # calculation details
            $charges = $mWaterConnectionCharge->getWaterchargesById($refAppDetails['id'])
                ->where('paid_status', 0)
                ->first();
            if ($charges) {
                $calculation['calculation'] = [
                    'connectionFee'     => $charges['conn_fee'],
                    'penalty'           => $charges['penalty'],
                    'totalAmount'       => $charges['amount'],
                    'chargeCatagory'    => $charges['charge_category'],
                    'paidStatus'        => $charges['paid_status']
                ];
                $waterTransDetail = array_merge($waterTransDetail, $calculation);
            }

            # Site inspection schedule time/date Details 
            if ($applicationDetails['applicationDetails']['current_role'] == $roleDetails['JE']) {
                $inspectionTime = $mWaterSiteInspectionsScheduling->getInspectionData($applicationDetails['applicationDetails']['id'])->first();
                $applicationDetails['applicationDetails']['scheduledTime'] = $inspectionTime->inspection_time ?? null;
                $applicationDetails['applicationDetails']['scheduledDate'] = $inspectionTime->inspection_date ?? null;
            }

            $returnData = array_merge($applicationDetails, $ownerDetails, $waterTransDetail); //$documentDetails,
            return responseMsgs(true, "Application Data!", remove_null($returnData), "", "", "", "Post", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
}
