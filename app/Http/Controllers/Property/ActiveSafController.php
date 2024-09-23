<?php

namespace App\Http\Controllers\Property;

use App\BLL\Property\Akola\CalculatePropTaxByPropId;
use App\BLL\Property\Akola\CalculateSafTaxById;
use App\BLL\Property\Akola\SafApprovalBll;
use App\BLL\Property\CalculateSafById;
use App\BLL\Property\PaymentReceiptHelper;
use App\BLL\Property\PostRazorPayPenaltyRebate;
use App\BLL\Property\PostSafPropTaxes;
use App\BLL\Property\PreviousHoldingDeactivation;
use App\BLL\Property\TcVerificationDemandAdjust;
use App\BLL\Property\UpdateSafDemand;
use App\EloquentClass\Property\PenaltyRebateCalculation;
use App\EloquentClass\Property\SafCalculation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\ReqEditSaf;
use App\Http\Requests\Property\ReqPayment;
use App\Http\Requests\Property\ReqSiteVerification;
use App\MicroServices\DocUpload;
use App\MicroServices\IdGeneration;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\MicroServices\IdGenerator\PropIdGenerator;
use App\Models\CustomDetail;
use App\Models\Payment\TempTransaction;
use App\Models\Property\Logs\LogPropFloor;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropChequeDtl;
use App\Models\Property\PropDemand;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropPenaltyrebate;
use App\Models\Property\PropProperty;
use App\Models\Property\PropRazorpayPenalrebate;
use App\Models\Property\PropRazorpayRequest;
use App\Models\Property\PropRazorpayResponse;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafGeotagUpload;
use App\Models\Property\PropSafMemoDtl;
use App\Models\Property\PropSafsDemand;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\PropSafsOwner;
use App\Models\Property\PropSafTax;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropSafVerificationDtl;
use App\Models\Property\PropTax;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\Property\RefPropConstructionType;
use App\Models\Property\RefPropFloor;
use App\Models\Property\RefPropGbbuildingusagetype;
use App\Models\Property\RefPropGbpropusagetype;
use App\Models\Property\RefPropOccupancyType;
use App\Models\Property\RefPropOwnershipType;
use App\Models\Property\RefPropRoadType;
use App\Models\Property\RefPropTransferMode;
use App\Models\Property\RefPropType;
use App\Models\Property\RefPropUsageType;
use App\Models\Property\ZoneMaster;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Pipelines\SafInbox\SearchByApplicationNo;
use App\Pipelines\SafInbox\SearchByMobileNo;
use App\Pipelines\SafInbox\SearchByName;
use Illuminate\Http\Request;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use App\Traits\Payment\Razorpay;
use App\Traits\Property\SAF;
use App\Traits\Property\SafDetailsTrait;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\MicroServices\IdGenerator\HoldingNoGenerator;
use App\Models\ActiveCitizen;
use App\Models\Property\Logs\PropSmsLog;
use App\Models\Property\Logs\SafAmalgamatePropLog;
use App\Models\Property\PropSafJahirnamaDoc;
use App\Models\Property\RefPropCategory;
use App\Models\Property\RefPropVacantLand;
use App\Models\Property\SecondaryDocVerification;
use App\Models\User;
use App\Models\Workflows\WfMaster;
use App\Models\Workflows\WfRole;
use App\Repository\Common\CommonFunction;
use Barryvdh\DomPDF\Facade\PDF;
use Hamcrest\Type\IsNumeric;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ActiveSafController extends Controller
{
    use Workflow;
    use SAF;
    use Razorpay;
    use SafDetailsTrait;
    /**
     * | Created On-10-08-2022
     * | Created By-Anshu Kumar
     * | Status - Open
     * -----------------------------------------------------------------------------------------
     * | SAF Module all operations 
     * | --------------------------- Workflow Parameters ---------------------------------------
     * |                                 # SAF New Assessment
     * | wf_master id=1 
     * | wf_workflow_id=4
     * |                                 # SAF Reassessment 
     * | wf_mstr_id=2
     * | wf_workflow_id=3
     * |                                 # SAF Mutation
     * | wf_mstr_id=3
     * | wf_workflow_id=5
     * |                                 # SAF Bifurcation
     * | wf_mstr_id=4
     * | wf_workflow_id=182 
     * |                                 # SAF Amalgamation
     * | wf_mstr_id=5
     * | wf_workflow_id=381
     */

    protected $user_id;
    protected $_todayDate;
    protected $Repository;
    protected $_moduleId;
    // Initializing function for Repository
    protected $saf_repository;
    public $_replicatedPropId;
    protected $_COMMONFUNCTION;
    protected $_SkipFiledWorkWfMstrId = [];
    protected $_alowJahirnamaWorckflows = [];
    public function __construct(iSafRepository $saf_repository)
    {
        $this->Repository = $saf_repository;
        $this->_todayDate = Carbon::now();
        $this->_moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $this->_COMMONFUNCTION = new CommonFunction();
        $wfContent = Config::get('workflow-constants');
        $this->_SkipFiledWorkWfMstrId = [
            $wfContent["SAF_MUTATION_ID"],
            $wfContent["SAF_BIFURCATION_ID"],
        ];
        $this->_alowJahirnamaWorckflows = [
            $wfContent["SAF_MUTATION_ID"],
            $wfContent["SAF_BIFURCATION_ID"],
        ];
    }

    /**
     * | Master data in Saf Apply
     * | @var ulbId Logged In User Ulb 
     * | Status-Closed
     * | Query Costing-369ms 
     * | Rating-3
     */
    public function masterSaf(Request $req)
    {
        try {
            $redisConn = Redis::connection();
            $data = [];

            $ulbWardMaster = new UlbWardMaster();
            $refPropOwnershipType = new RefPropOwnershipType();
            $refPropType = new RefPropType();
            $refPropFloor = new RefPropFloor();
            $refPropUsageType = new RefPropUsageType();
            $refPropOccupancyType = new RefPropOccupancyType();
            $refPropConstructionType = new RefPropConstructionType();
            $mZoneMasters = new ZoneMaster();
            $mRefPropCategory = new RefPropCategory();
            $refPropTransferMode = new RefPropTransferMode();
            $vacantLandType = new RefPropVacantLand();

            // Getting Masters from Redis Cache
            $wards = json_decode(Redis::get('wards-ulb'));
            $ownershipTypes = json_decode(Redis::get('prop-ownership-types'));
            $propertyType = json_decode(Redis::get('property-types'));
            $floorType = json_decode(Redis::get('property-floors'));
            $usageType = json_decode(Redis::get('property-usage-types'));
            $occupancyType = json_decode(Redis::get('property-occupancy-types'));
            $constructionType = json_decode(Redis::get('akola-property-construction-types'));
            $zone = json_decode(Redis::get('zones'));
            $categories = json_decode(Redis::get('ref_prop_categories'));
            $transferModuleType = json_decode(Redis::get('property-transfer-modes'));
            $vacLand = json_decode(Redis::get('ref_prop_vacant_lands'));

            // Ward Masters
            if (!$wards) {
                $wards = collect();
                $wardMaster = $ulbWardMaster->getAllWards();   // <----- Get Ward by Ulb ID By Model Function
                $groupByWards = $wardMaster->groupBy('ward_name');
                foreach ($groupByWards as $ward) {
                    $wards->push(collect($ward)->first());
                }
                $wards->sortBy('ward_name')->values();
                $redisConn->set('wards-ulb', json_encode($wards));            // Caching
            }

            $data['ward_master'] = collect($wards)->sortBy('id')->values();

            // Ownership Types
            if (!$ownershipTypes) {
                $ownershipTypes = $refPropOwnershipType->getPropOwnerTypes();   // <--- Get Property OwnerShip Types
                $redisConn->set('prop-ownership-types', json_encode($ownershipTypes));
            }

            $data['ownership_types'] = $ownershipTypes;

            // Property Types
            if (!$propertyType) {
                $propertyType = $refPropType->propPropertyType();
                $redisConn->set('property-types', json_encode($propertyType));
            }

            $data['property_type'] = $propertyType;

            // Property Floors
            if (!$floorType) {
                $floorType = $refPropFloor->getPropTypes();
                $redisConn->set('propery-floors', json_encode($floorType));
            }

            $data['floor_type'] = $floorType;

            // Property Usage Types
            if (!$usageType) {
                $usageType = $refPropUsageType->propUsageType();
                $redisConn->set('property-usage-types', json_encode($usageType));
            }

            $data['usage_type'] = $usageType;

            // Property Occupancy Types
            if (!$occupancyType) {
                $occupancyType = $refPropOccupancyType->propOccupancyType();
                $redisConn->set('property-occupancy-types', json_encode($occupancyType));
            }

            $data['occupancy_type'] = $occupancyType;

            // property construction types
            if (!$constructionType) {
                $constructionType = $refPropConstructionType->propConstructionType();
                $redisConn->set('akola-property-construction-types', json_encode($constructionType));
            }

            $data['construction_type'] = $constructionType;

            if (!$zone) {
                $zone = $mZoneMasters->getZone();
                $redisConn->set('zones', json_encode($zone));
            }

            $data['zone'] = $zone;

            if (!$categories) {
                $categories = $mRefPropCategory::all();
                $redisConn->set('categories', json_encode($categories));
            }

            $data['categories'] = $categories;

            // property transfer modes
            if (!$transferModuleType) {
                $transferModuleType = $refPropTransferMode->getTransferModes();
                $redisConn->set('property-transfer-modes', json_encode($transferModuleType));
            }

            $data['transfer_mode'] = $transferModuleType;
            // Property Types
            if (!$vacLand) {
                $vacLand = $vacantLandType->propPropertyVacantLandType();
                $redisConn->set('ref_prop_vacant_lands', json_encode($vacLand));
            }

            $data['vacant_land_type'] = $vacLand;

            return responseMsgs(true, 'Property Masters', $data, "010101", "1.0", responseTime(), "GET", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Edit Applied Saf by SAF Id for BackOffice
     * | @param request $req
     */
    public function editSafOld(Request $req)
    {
        $rules = [
            'id'                        => 'required|numeric',
            'owner'                     => 'array',
            'owner.*.propOwnerDetailId' => 'required|numeric',
            'owner.*.ownerName'         => 'required',
            'owner.*.guardianName'      => 'nullable',
            'owner.*.relation'          => 'nullable',
            'owner.*.mobileNo'          => 'numeric|digits:10',
            'owner.*.aadhar'            => 'numeric|digits:12|nullable',
            'owner.*.email'             => 'email|nullable',
        ];
        $validated = Validator::make(
            $req->all(),
            $rules
        );
        if ($validated->fails()) {
            return response()->json([
                'status'    => false,
                'message'   => 'validation error',
                'errors'    => $validated->errors()
            ]);
        }
        // $req->validate([
        //     'id' => 'required|numeric',
        //     'owner' => 'array',
        //     'owner.*.ownerId' => 'required|numeric',
        //     'owner.*.ownerName' => 'required',
        //     'owner.*.guardianName' => 'required',
        //     'owner.*.relation' => 'required',
        //     'owner.*.mobileNo' => 'numeric|string|digits:10',
        //     'owner.*.aadhar' => 'numeric|string|digits:12|nullable',
        //     'owner.*.email' => 'email|nullable',
        // ]);

        try {
            $mPropSaf = new PropActiveSaf();
            $mPropSafOwners = new PropActiveSafsOwner();
            $mOwners = $req->owner;

            DB::beginTransaction();
            $mPropSaf->edit($req);                                                      // Updation SAF Basic Details

            collect($mOwners)->map(function ($owner) use ($mPropSafOwners) {            // Updation of Owner Basic Details
                $owner["safOwnerId"] = $owner["propOwnerDetailId"];
                $mPropSafOwners->edit($owner);
            });

            DB::commit();
            return responseMsgs(true, "Successfully Updated the Data", "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    public function editSaf(ReqEditSaf $req)
    {
        try {

            $applysafController = new ApplySafController();
            $mPropSaf           = new PropActiveSaf();
            $mPropSafOwners     = new PropActiveSafsOwner();
            $mFloors              = new PropActiveSafsFloor();
            $safId              = $req->id;
            $oldSaf             = $mPropSaf->find($safId);
            $user               = Auth()->user();
            $userId             = $user->id ?? null;
            $ulb_id             = $user->ulb_id ?? $req->ulbId;
            $assessmentType     = $req->assessmentType;
            if ($oldSaf->current_role != $oldSaf->initiator_role_id && !$oldSaf->parked) {
                throw new Exception("Can not edit this application");
            }

            $ulbWorkflowId = (new ApplySafController())->readAssessUlbWfId($req, $ulb_id);
            $roadWidthType = $applysafController->readRoadWidthType($req->roadType);
            $mutationProccessFee = $applysafController->readProccessFee($req->assessmentType, $req->saleValue, $req->propertyType, $req->transferModeId);
            $metaReqs['holdingType'] = $applysafController->holdingType($req['floor']);
            $metaReqs["road_type_mstr_id"] = $roadWidthType;
            $req->merge($metaReqs);
            $req->merge(["proccessFee" => $mutationProccessFee]);
            if ($oldSaf->workflow_id == Config::get('workflow-constants.ULB_WORKFLOW_ID_OLD_MUTATION')) {
                $req->merge(["saleValue" => 0, "proccessFee" => 0]);
            }
            $mOwners = $req->owner;
            $floars = $req->floor;
            $updateOwnerId = collect($mOwners)->whereNotNull("propOwnerDetailId")->unique("propOwnerDetailId")->pluck("propOwnerDetailId");
            $updateFloorId = collect($floars)->whereNotNull("propFloorDetailId")->unique("propFloorDetailId")->pluck("propFloorDetailId");
            $oldData =             $mPropSaf->find($req->id);
            DB::beginTransaction();
            $mPropSaf->edit($req);                                                   // Updation SAF Basic Details

            #deactivateOldOwners
            $deactivateOwner = $mPropSafOwners->where("saf_id", $safId)->update(["status" => 0]);
            #deactivateOldFloors  
            $deactivateFloor = $mFloors->where("saf_id", $safId)->update(["status" => 0]);

            collect($mOwners)->map(function ($owner) use ($mPropSafOwners, $safId) {            // Updation of Owner Basic Details
                $owner["safOwnerId"] = $owner["propOwnerDetailId"] ?? null;
                $owner["safId"] = $safId;
                if ($owner["safOwnerId"]) {
                    $oldOwners = $mPropSafOwners->find($owner["safOwnerId"]);
                    $owner["status"] = 1;
                    $owner["propOwnerDetailId"] = $oldOwners ? $oldOwners->prop_owner_id : null;
                    $mPropSafOwners->editOwnerById($owner["safOwnerId"], $owner);
                } else {
                    $owner["propOwnerDetailId"] = null;
                    $mPropSafOwners->addOwner($owner, $safId, null);
                }
            });
            if ($req->propertyType != 4) {
                collect($floars)->map(function ($floar) use ($mFloors, $safId, $userId, $assessmentType) {            // Updation of Owner Basic Details
                    $floar["propFloorId"] = $floar["propFloorDetailId"] ?? null;
                    $floar["assessmentType"] = $assessmentType;
                    $floar["safId"] = $safId;
                    if ($floar["propFloorId"]) {
                        $oldFloors = $mFloors->find($floar["propFloorId"]);
                        $floar["status"] = 1;
                        $floar["propFloorDetailId"] = $oldFloors ? $oldFloors->prop_floor_details_id : null;
                        $mFloors->editFloorById($floar["propFloorId"], $floar);
                    } else {
                        $floar["propFloorDetailId"] = null;
                        $mFloors->addfloor($floar, $safId, $userId, $assessmentType, null);
                    }
                });
            }

            DB::commit();
            return responseMsgs(true, "Successfully Updated the Data", "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    /**
     * ---------------------- Saf Workflow Inbox --------------------
     * | Initialization
     * -----------------
     * | @var userId > logged in user id
     * | @var ulbId > Logged In user ulb Id
     * | @var refWorkflowId > Workflow ID 
     * | @var workflowId > SAF Wf Workflow ID 
     * | @var query > Contains the Pg Sql query
     * | @var workflow > get the Data in laravel Collection
     * | @var checkDataExisting > check the fetched data collection in array
     * | @var roleId > Fetch all the Roles for the Logged In user
     * | @var data > all the Saf data of current logged roleid 
     * | @var occupiedWard > get all Permitted Ward Of current logged in user id
     * | @var wardId > filtered Ward Id from the data collection
     * | @var safInbox > Final returned Data
     * | @return response #safInbox
     * | Status-Closed
     * | Query Cost-327ms 
     * | Rating-3
     * ---------------------------------------------------------------
     */
    #Inbox
    public function inbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $perPage = $req->perPage ?? 10;

            $occupiedWards = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');                       // Model () to get Occupied Wards of Current User
            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');                      // Model to () get Role By User Id

            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $safDtl = $this->Repository->getSaf($workflowIds)                                          // Repository function to get SAF Details
                ->where('parked', false)
                ->where('prop_active_safs.ulb_id', $ulbId)
                ->where('prop_active_safs.status', 1)
                ->whereIn('current_role', $roleIds)
                ->whereIn('ward_mstr_id', $occupiedWards)
                ->orderByDesc('id')
                ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name', 'jhirnama_no', 'has_any_objection', 'generation_date');
            if ($roleIds->contains(11)) {
                $safDtl->whereNull('citizen_id');
            }
            $safInbox = app(Pipeline::class)
                ->send(
                    $safDtl
                )
                ->through([
                    SearchByApplicationNo::class,
                    SearchByMobileNo::class,
                    SearchByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Data Fetched", remove_null($safInbox), "010103", "1.0", responseTime(), "POST", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Inbox for the Back To Citizen parked true
     * | @var mUserId authenticated user id
     * | @var mUlbId authenticated user ulb id
     * | @var readWards get all the wards of the user id
     * | @var occupiedWardsId get all the wards id of the user id
     * | @var readRoles get all the roles of the user id
     * | @var roleIds get all the logged in user role ids
     */
    public function btcInbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            $mUserId = authUser($req)->id;
            $mUlbId = authUser($req)->ulb_id;
            $mDeviceId = $req->deviceId ?? "";
            $perPage = $req->perPage ?? 10;

            $occupiedWardsId = $mWfWardUser->getWardsByUserId($mUserId)->pluck('ward_id');                  // Model function to get ward list

            $roleIds = $mWfRoleUser->getRoleIdByUserId($mUserId)->pluck('wf_role_id');                 // Model function to get Role By User Id

            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');
            $safDtl = $this->Repository->getSaf($workflowIds)                 // Repository function getSAF
                ->selectRaw(DB::raw(
                    "case when prop_active_safs.citizen_id is not null then 'true'
                          else false end
                          as btc_for_citizen"
                ))
                ->where('parked', true)
                ->where('prop_active_safs.ulb_id', $mUlbId)
                ->where('prop_active_safs.status', 1)
                ->whereIn('ward_mstr_id', $occupiedWardsId)
                ->orderByDesc('id')
                ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name', 'jhirnama_no', 'has_any_objection', 'generation_date');
            // ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name');

            $safInbox = app(Pipeline::class)
                ->send(
                    $safDtl
                )
                ->through([
                    SearchByApplicationNo::class,
                    SearchByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "BTC Inbox List", remove_null($safInbox), 010123, 1.0, responseTime(), "POST", $mDeviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010123, 1.0, responseTime(), "POST", $mDeviceId);
        }
    }

    /**
     * | Fields Verified Inbox
     */
    public function fieldVerifiedInbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            $mUserId = authUser($req)->id;
            $mUlbId = authUser($req)->ulb_id;
            $mDeviceId = $req->deviceId ?? "";
            $perPage = $req->perPage ?? 10;

            $occupiedWardsId = $mWfWardUser->getWardsByUserId($mUserId)->pluck('ward_id');                  // Model function to get ward list
            $roleIds = $mWfRoleUser->getRoleIdByUserId($mUserId)->pluck('wf_role_id');                 // Model function to get Role By User Id
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $safInbox = $this->Repository->getSaf($workflowIds)                 // Repository function getSAF
                ->where('is_field_verified', true)
                ->where('prop_active_safs.ulb_id', $mUlbId)
                ->where('prop_active_safs.status', 1)
                ->whereIn('current_role', $roleIds)
                ->whereIn('ward_mstr_id', $occupiedWardsId)
                ->orderByDesc('id')
                ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name', 'jhirnama_no', 'has_any_objection', 'generation_date')
                // ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name')
                ->paginate($perPage);

            return responseMsgs(true, "field Verified Inbox!", remove_null($safInbox), 010125, 1.0, "", "POST", $mDeviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010125, 1.0, "", "POST", $mDeviceId);
        }
    }

    /**
     * | Saf Outbox
     * | @var userId authenticated user id
     * | @var ulbId authenticated user Ulb Id
     * | @var workflowRoles get All Roles of the user id
     * | @var roles filteration of roleid from collections
     * | Status-Closed
     * | Query Cost-369ms 
     * | Rating-4
     */

    public function outbox(Request $req)
    {
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWardUser = new WfWardUser();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();

            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $perPage = $req->perPage ?? 10;

            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $wardId = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');

            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');
            $safDtl = $this->Repository->getSaf($workflowIds)   // Repository function to get SAF
                ->where('prop_active_safs.parked', false)
                ->where('prop_active_safs.ulb_id', $ulbId)
                ->whereNotIn('current_role', $roleIds)
                ->whereIn('ward_mstr_id', $wardId)
                ->orderByDesc('id')
                ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name', 'jhirnama_no', 'has_any_objection', 'generation_date');
            // ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name');

            $safData = app(Pipeline::class)
                ->send(
                    $safDtl
                )
                ->through([
                    SearchByApplicationNo::class,
                    SearchByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Data Fetched", remove_null($safData), "010104", "1.0", "274ms", "POST", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | @var ulbId authenticated user id
     * | @var ulbId authenticated ulb Id
     * | @var occupiedWard get ward by user id using trait
     * | @var wardId Filtered Ward ID from the collections
     * | @var safData SAF Data List
     * | @return
     * | @var \Illuminate\Support\Collection $safData
     * | Status-Closed
     * | Query Costing-336ms 
     * | Rating-2 
     */
    public function specialInbox(Request $req)
    {
        try {
            $mWfWardUser = new WfWardUser();
            $mWfRoleUserMaps = new WfRoleusermap();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $perPage = $req->perPage ?? 10;

            $wardIds = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');                        // Get All Occupied Ward By user id using trait
            $roleIds = $mWfRoleUserMaps->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = $mWfWorkflowRoleMaps->getWfByRoleId($roleIds)->pluck('workflow_id');

            $safDtl = $this->Repository->getSaf($workflowIds)                      // Repository function to get SAF Details
                ->where('is_escalate', 1)
                ->where('prop_active_safs.ulb_id', $ulbId)
                ->whereIn('ward_mstr_id', $wardIds)
                ->orderByDesc('id')
                ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name', 'jhirnama_no', 'has_any_objection', 'generation_date');
            // ->groupBy('prop_active_safs.id', 'prop_active_safs.saf_no', 'ward.ward_name', 'p.property_type');

            $safData = app(Pipeline::class)
                ->send(
                    $safDtl
                )
                ->through([
                    SearchByApplicationNo::class,
                    SearchByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Data Fetched", remove_null($safData), "010107", "1.0", "251ms", "POST", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * @param \Illuminate\Http\Request $req
     * @return \Illuminate\Http\JsonResponse
     * desc This function get the application brief details 
     * request : saf_id (requirde)
     * ---------------Tables-----------------
     * active_saf_details            |
     * ward_mastrs                   | Saf details
     * property_type                 |
     * active_saf_owner_details      -> Saf Owner details
     * active_saf_floore_details     -> Saf Floore Details
     * workflow_tracks               |  
     * users                         | Comments and  date rolles
     * role_masters                  |
     * =======================================
     * helpers : Helpers/utility_helper.php   ->remove_null() -> for remove  null values
     * | Status-Closed
     * | Query Cost-378ms 
     * | Rating-4 
     */
    #Saf Details
    public function safDetails(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807'
        ]);

        try {
            $mPropActiveSaf = new PropActiveSaf();
            $mPropSaf = new PropSaf();
            $mPropActiveSafOwner = new PropActiveSafsOwner();
            $mActiveSafsFloors = new PropActiveSafsFloor();
            $mWorkflowTracks = new WorkflowTrack();
            $mCustomDetails = new CustomDetail();
            $forwardBackward = new WorkflowMap;
            $mVerification = new PropSafVerification();
            $verificationDtl = collect();
            $mVerificationDtls = new PropSafVerificationDtl();
            $mRefTable = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $ownershipTypes = Config::get('PropertyConstaint.OWNERSHIP-TYPE');
            $transferMode   = Config::get('PropertyConstaint.TRANSFER_MODES');
            $ownershipTypes = collect($ownershipTypes)->flip();
            $transferMode   = collect($transferMode)->flip();
            $jahirnamaDoc = new PropSafJahirnamaDoc();
            $approveDate = "";

            // Saf Details
            $data = array();
            $fullDetailsData = array();
            if ($req->applicationId) {                                       //<------- Search By SAF ID
                $data = $mPropActiveSaf->getActiveSafDtls()      // <------- Model function Active SAF Details
                    ->where('prop_active_safs.id', $req->applicationId)
                    ->first();

                if (collect($data)->isEmpty()) {
                    $data = $mPropSaf->getSafDtls()
                        ->where('prop_safs.id', $req->applicationId)
                        ->first();
                    if (!empty($data)) {
                        $data->current_role_name = 'Approved By ' . $data->current_role_name;
                        $approveDate = $data->saf_approved_date ?? null; // Safely set $approveDate if available
                    }
                }
            }
            if ($req->safNo) {                                  // <-------- Search By SAF No
                $data = $mPropActiveSaf->getActiveSafDtls()    // <------- Model Function Active SAF Details
                    ->where('prop_active_safs.saf_no', $req->safNo)
                    ->first();

                if (collect($data)->isEmpty()) {
                    $data = $mPropSaf->getSafDtls()
                        ->where('prop_safs.saf_no', $req->applicationId)
                        ->first();
                    if (!empty($data)) {
                        $data->current_role_name = 'Approved By ' . $data->current_role_name;
                        $approveDate = $data->saf_approved_date ?? null; // Safely set $approveDate if available
                    }
                }
            }

            if (!$data)
                throw new Exception("Application Not Found for this id");

            if ($data->payment_status == 0) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Payment is Pending";
            } else
                $data->current_role_name2 = $data->current_role_name;

            $safVerification = $mVerification->getLastVerification($data->id);
            if ($safVerification) {
                $data->old_ward_no = $safVerification->ward_name ? $safVerification->ward_name : $data->old_ward_no;
                $data->category = $safVerification->category ? $safVerification->category : $data->category;
                $verificationDtl = $mVerificationDtls->getVerificationDtls($safVerification->id);
            }
            // Basic Details
            $basicDetails = $this->generateBasicDetails($data);      // Trait function to get Basic Details
            $basicElement = [
                'headerTitle' => "Basic Details",
                "data" => $basicDetails
            ];

            // Property Details
            $propertyDetails = $this->generatePropertyDetails($data);   // Trait function to get Property Details
            $propertyElement = [
                'headerTitle' => "Property Details & Address",
                'data' => $propertyDetails
            ];

            // Corresponding Address Details
            $corrDetails = $this->generateCorrDtls($data);              // Trait function to generate corresponding address details
            $corrElement = [
                'headerTitle' => 'Corresponding Address',
                'data' => $corrDetails,
            ];

            // Electricity & Water Details
            $electDetails = $this->generateElectDtls($data);            // Trait function to generate Electricity Details
            $electElement = [
                'headerTitle' => 'Electricity & Water Details',
                'data' => $electDetails
            ];


            $jahirnama = $jahirnamaDoc->getJahirnamaBysafIdOrm($data->id)->first();
            $fullDetailsData['application_no'] = $data->saf_no;
            $fullDetailsData['apply_date'] = $data->application_date;
            $fullDetailsData['ward_mstr_id'] = $data->ward_mstr_id;
            $fullDetailsData['property_no'] = (!$data->property_no) ? true : false;
            $fullDetailsData['can_jahirnama_genrate'] = (!$data->is_jahirnama_genrated || !$jahirnama) ? true : false;
            $fullDetailsData['can_jahirnama_update'] = (!$data->is_jahirnama_genrated || !$jahirnama || !($jahirnama->is_update_objection ?? false)) ? true : false;

            $fullDetailsData['doc_verify_status'] = $data->doc_verify_status;
            $fullDetailsData['doc_upload_status'] = $data->doc_upload_status;
            $fullDetailsData['payment_status'] = $data->payment_status;
            $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([
                $basicElement,
                $propertyElement,
                $corrElement,
                $electElement
            ]);
            $fullDetailsData['approveDate'] = $approveDate;
            // Table Array
            // Owner Details
            $getOwnerDetails = $mPropActiveSafOwner->getOwnersBySafId($data->id);    // Model function to get Owner Details
            $ownerDetails = $this->generateOwnerDetails($getOwnerDetails);
            $ownerElement = [
                'headerTitle' => 'Owner Details',
                'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                'tableData' => $ownerDetails
            ];

            //Bhagwatdar In the case of Imla and Occupier
            if ($data->transfer_mode_mstr_id == $transferMode['Imla'] || $data->ownership_type_mstr_id == $ownershipTypes['OCCUPIER'])
                $ownerElement = [
                    'headerTitle' => 'Owner Details',
                    'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Bhogwatdar/ Occupant Name", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                    'tableData' => $ownerDetails
                ];

            // Floor Details            
            $getFloorDtls = $mActiveSafsFloors->getFloorsBySafId($data->id)->map(function ($val) use ($verificationDtl) {
                $new = $verificationDtl->where("saf_floor_id", $val->id)->first();
                $val->floor_name = $new ? $new->floor_name : $val->floor_name;
                $val->usage_type = $new ? $new->usage_type : $val->usage_type;
                $val->occupancy_type = $new ? $new->occupancy_type : $val->occupancy_type;
                $val->construction_type = $new ? $new->construction_type : $val->construction_type;
                return $val;
            });      // Model Function to Get Floor Details
            $floorDetails = $this->generateFloorDetails($getFloorDtls);
            $floorElement = [
                'headerTitle' => 'Floor Details',
                'tableHead' => ["#", "Floor", "Usage Type", "Occupancy Type", "Construction Type", "Build Up Area", "From Date", "Upto Date"],
                'tableData' => $floorDetails
            ];
            if ($data->assessment_type == 'Bifurcation') {
                $floorDetails = $this->generateBiFloorDetails($getFloorDtls);
                $floorElement = [
                    'headerTitle' => 'Floor Details',
                    'tableHead' => ["#", "Floor", "Usage Type", "Occupancy Type", "Construction Type", "Build Up Area", "Bifurcated From Build Up Area", "From Date", "Upto Date"],
                    'tableData' => $floorDetails
                ];
            }
            $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerElement, $floorElement]);
            // Card Detail Format
            $cardDetails = $this->generateCardDetails($data, $getOwnerDetails);
            $cardElement = [
                'headerTitle' => "About Property",
                'data' => $cardDetails
            ];
            if ($data->assessment_type == 'Bifurcation') {
                // Card Detail Format
                $cardDetails = $this->generateBiCardDetails($data, $getOwnerDetails);
                $cardElement = [
                    'headerTitle' => "About Property",
                    'data' => $cardDetails
                ];
            }
            $fullDetailsData['fullDetailsData']['cardArray'] = new Collection($cardElement);
            $data = json_decode(json_encode($data), true);
            $metaReqs['customFor'] = 'SAF';
            $metaReqs['wfRoleId'] = $data['current_role'];
            $metaReqs['workflowId'] = $data['workflow_id'];
            $metaReqs['lastRoleId'] = $data['last_role_id'];

            $levelComment = $mWorkflowTracks->getTracksByRefId($mRefTable, $data['id']);
            $fullDetailsData['levelComment'] = $levelComment;

            $citizenComment = $mWorkflowTracks->getCitizenTracks($mRefTable, $data['id'], $data['citizen_id']);
            $fullDetailsData['citizenComment'] = $citizenComment;

            $req->request->add($metaReqs);
            $forwardBackward = $forwardBackward->getRoleDetails($req);
            $fullDetailsData['roleDetails'] = collect($forwardBackward)['original']['data'];
            $docDetail = $mPropActiveSaf->getDocDetail($req->applicationId);
            $fullDetailsData['timelineData'] = collect($req);
            $fullDetailsData['DocDetail'] = $docDetail;
            //$fullDetailsData['saf_approved_date'] = $data->saf_approved_date;

            $custom = $mCustomDetails->getCustomDetails($req);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];

            return responseMsgs(true, 'Data Fetched', remove_null($fullDetailsData), "010104", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get Static Saf Details
     */
    public function getStaticSafDetails(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807'
        ]);
        try {
            // Variable Assignments
            $mPropOwner = new PropOwner();
            $mPropActiveSaf = new PropActiveSaf();
            $mPropSafOwner = new PropSafsOwner();
            $mPropSaf = new PropSaf();
            $mPropSafsFloors = new PropSafsFloor();
            $mPropActiveSafOwner = new PropActiveSafsOwner();
            $mActiveSafsFloors = new PropActiveSafsFloor();
            $mPropSafMemoDtls = new PropSafMemoDtl();
            $mPropTransaction = new PropTransaction();
            $mVerification = new PropSafVerification();
            $verificationDtl = collect();
            $mVerificationDtls = new PropSafVerificationDtl();
            $memoDtls = array();
            $data = array();
            $prevOwnerDtls = array();
            $user = Auth()->user();
            $is_approved = false;
            $adminAllows = false;
            // Derivative Assignments
            $data = $mPropActiveSaf->getActiveSafDtls()                         // <------- Model function Active SAF Details
                ->where('prop_active_safs.id', $req->applicationId)
                ->first();
            // if (!$data)
            // throw new Exception("Application Not Found");
            if (collect($data)->isNotEmpty()) {
                $assessmentType = $data->assessment_type;
            }
            if (collect($data)->isEmpty()) {
                $data = $mPropSaf->getSafDtls()
                    ->where('prop_safs.id', $req->applicationId)
                    ->first();
                $is_approved = true;
            }

            if (collect($data)->isEmpty())
                throw new Exception("Application Not Found");

            $data->current_role_name = 'Approved By ' . $data->current_role_name;
            $safVerification = $mVerification->where("saf_id", $data->id)->where("status", 1)->orderBy("id", "DESC")->first();
            $verificationDtl = $mVerificationDtls->getVerificationDtls($safVerification->id ?? 0);
            if ($safVerification) {
                $data = $this->addjustVerifySafDtls($data, $safVerification);
                $data = $this->addjustVerifySafDtlVal($data);
            }
            if ($data->payment_status == 0) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Payment is Pending";
            } elseif ($data->payment_status == 2) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Cheque Payment Verification Pending";
            } else
                $data->current_role_name2 = $data->current_role_name;

            // if ($data->previous_holding_id)
            //     $prevOwnerDtls = $mPropOwner->getOwnersByPropIdV2($data->previous_holding_id);

            // if ($data->assessment_type == 'Amalgamation') {
            //     $prevHoldingIds = explode(",", $data->previous_holding_id);
            //     $prevOwnerDtls = $mPropOwner->getOwnerByPropIds($prevHoldingIds);
            // } else 
            if ($data->previous_holding_id) {
                $prevHoldingIds = explode(",", $data->previous_holding_id);
                $prevOwnerDtls = $mPropOwner->getOwnerByPropIds($prevHoldingIds);
            }

            $data = json_decode(json_encode($data), true);

            $ownerDtls = $mPropActiveSafOwner->getOwnersBySafId($data['id']);
            if (collect($ownerDtls)->isEmpty())
                $ownerDtls = $mPropSafOwner->getOwnersBySafId($data['id']);

            $data['owners'] = $ownerDtls;
            $data['previous_owners'] = $prevOwnerDtls;
            $getFloorDtls = $mActiveSafsFloors->getFloorsBySafId($data['id']);      // Model Function to Get Floor Details
            if (collect($getFloorDtls)->isEmpty())
                $getFloorDtls = $mPropSafsFloors->getFloorsBySafId($data['id']);
            $notInApplication = collect($verificationDtl)->whereNotIn("saf_floor_id", collect($getFloorDtls)->pluck("id"));
            $getFloorDtls = collect($getFloorDtls)->map(function ($val) use ($verificationDtl) {
                $newFloors = $verificationDtl->where("saf_floor_id", $val->id)->first();
                $val = $this->addjustVerifyFloorDtls($val, $newFloors);
                $val = $this->addjustVerifyFloorDtlVal($val);
                return $val;
            });
            $notInApplication->map(function ($val) use ($getFloorDtls) {
                $newfloorObj = new PropActiveSafsFloor();
                $val = $this->addjustVerifyFloorDtls($newfloorObj, $val);
                $val = $this->addjustVerifyFloorDtlVal($val);
                $getFloorDtls->push($val);
            });
            $amalgamatePropsList = SafAmalgamatePropLog::where("saf_id", $data['id'])->get();
            $amalgamateProps = collect();
            foreach ($amalgamatePropsList as $val) {
                $aProp = new PropProperty(json_decode($val->property_json, true));
                $aProp = $this->addjustVerifySafDtlVal($aProp);
                $aFloors = new PropFloor(json_decode($val->floors_json, true));
                $aFloors = collect($aFloors)->map(function ($f) {
                    return $this->addjustVerifyFloorDtlVal(new PropFloor($f));
                });
                $aProp->floors = $aFloors;
                if (!$aProp->holding_type)
                    $aProp->holding_type = $this->propHoldingType($aFloors);
                $aOwneres = new PropOwner(json_decode($val->owners_json, true));
                $aProp->owneres = $aOwneres;
                $amalgamateProps->push($aProp);
            }
            $data["amalgamateProps"] = $amalgamateProps;

            $data["builtup_area"] = $data['area_of_plot'];
            if ($data['prop_type_mstr_id'] != 4) {
                $data["builtup_area"] = $getFloorDtls->sum("builtup_area");
            }
            if (isset($assessmentType) && $assessmentType == "Reassessment") {
                $data["builtup_area"] = $getFloorDtls->whereNotNull('prop_floor_details_id')->sum("builtup_area");
                $data["new_builtup_area"] = $getFloorDtls->whereNull('prop_floor_details_id')->sum("builtup_area");
            }

            $data['floors'] = $getFloorDtls;
            $data["tranDtl"] = $mPropTransaction->getSafTranList($data['id']);
            $data["userDtl"] = [
                "user_id" => $user->id ?? 0,
                "user_type" => $user->user_type ?? "",
                "ulb_id" => $user->ulb_id ?? 0,
                "user_name" => $user->name ?? ""
            ];
            $memoDtls = $mPropSafMemoDtls->memoLists($data['id']);
            $data['memoDtls'] = $memoDtls;
            if ($status = ((new \App\Repository\Property\Concrete\SafRepository())->applicationStatus($req->applicationId, true))) {
                $data["current_role_name2"] = $status;
            }
            $usertype = $this->_COMMONFUNCTION->getUserAllRoles();
            $testRole = collect($usertype)->whereIn("sort_name", Config::get("TradeConstant.CANE-CUTE-PAYMENT"));
            $testAdminRole = collect($usertype)->whereIn("sort_name", Config::get("TradeConstant.CANE-REJECT-APPLICATION"));
            if ($testAdminRole->isNotEmpty() && !$is_approved) {
                $adminAllows = true;
            }
            $data["can_deactivate_saf"] = $adminAllows;
            $data["can_take_payment"] = ($is_approved && collect($testRole)->isNotEmpty() && ($data["proccess_fee_paid"] ?? 1) == 0) ? true : false;
            $document = $this->getUploadDoc($req);
            $data["documents"] = $document;
            if ($this->_COMMONFUNCTION->checkUsersWithtocken("active_citizens")) {
                $data["can_take_payment"] = (($data["proccess_fee_paid"] ?? 1) == 0 && $is_approved) ? true : false;
            }

            return responseMsgs(true, "Saf Dtls", remove_null($data), "010127", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(true, $e->getMessage(), [], "010127", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    public function getSafOrignalDetails(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807'
        ]);
        try {
            // Variable Assignments
            $mPropActiveSaf = new PropActiveSaf();
            $mPropSafOwner = new PropSafsOwner();
            $mPropSaf = new PropSaf();
            $mPropSafsFloors = new PropSafsFloor();
            $mPropActiveSafOwner = new PropActiveSafsOwner();
            $mActiveSafsFloors = new PropActiveSafsFloor();
            $mPropSafMemoDtls = new PropSafMemoDtl();
            $mPropTransaction = new PropTransaction();
            $mVerification = new PropSafVerification();
            $verificationDtl = collect();
            $mVerificationDtls = new PropSafVerificationDtl();
            //$safdoc = new SafDocController();
            $memoDtls = array();
            $data = array();
            $user = Auth()->user();

            // Derivative Assignments
            $data = $mPropActiveSaf->getActiveSafDtls()                         // <------- Model function Active SAF Details
                ->where('prop_active_safs.id', $req->applicationId)
                ->first();
            // if (!$data)
            // throw new Exception("Application Not Found");

            if (collect($data)->isEmpty()) {
                $data = $mPropSaf->getSafDtls()
                    ->where('prop_safs.id', $req->applicationId)
                    ->first();
            }

            if (collect($data)->isEmpty())
                throw new Exception("Application Not Found");

            $data->current_role_name = 'Approved By ' . $data->current_role_name;
            // $data->
            if ($data->payment_status == 0) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Payment is Pending";
            } elseif ($data->payment_status == 2) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Cheque Payment Verification Pending";
            } else
                $data->current_role_name2 = $data->current_role_name;

            $data = json_decode(json_encode($data), true);
            $lastTcVerificationData = PropSafVerification::select(
                'prop_saf_verifications.*',
                'p.property_type',
                'u.ward_name as ward_no',
                "users.name as user_name"
            )
                ->leftjoin('ref_prop_types as p', 'p.id', '=', 'prop_saf_verifications.prop_type_id')
                ->leftjoin('ulb_ward_masters as u', 'u.id', '=', 'prop_saf_verifications.ward_id')
                ->leftjoin('users', 'users.id', '=', 'prop_saf_verifications.user_id')
                ->where("prop_saf_verifications.saf_id", $req->applicationId)
                ->where("prop_saf_verifications.agency_verification", true)
                ->where("prop_saf_verifications.status", 1)
                ->orderBy("prop_saf_verifications.id", "DESC")
                ->first();
            $lastTcFloorVerificationData =  PropSafVerificationDtl::select('prop_saf_verification_dtls.*', 'f.floor_name', 'u.usage_type', 'o.occupancy_type', 'c.construction_type')
                ->leftjoin('ref_prop_floors as f', 'f.id', '=', 'prop_saf_verification_dtls.floor_mstr_id')
                ->leftjoin('ref_prop_usage_types as u', 'u.id', '=', 'prop_saf_verification_dtls.usage_type_id')
                ->leftjoin('ref_prop_occupancy_types as o', 'o.id', '=', 'prop_saf_verification_dtls.occupancy_type_id')
                ->leftjoin('ref_prop_construction_types as c', 'c.id', '=', 'prop_saf_verification_dtls.construction_type_id')
                ->where("verification_id", $lastTcVerificationData->id ?? 0)
                ->get();
            $ownerDtls = $mPropActiveSafOwner->getOwnersBySafId($data['id']);
            if (collect($ownerDtls)->isEmpty())
                $ownerDtls = $mPropSafOwner->getOwnersBySafId($data['id']);

            $data['owners'] = $ownerDtls;
            $getFloorDtls = $mActiveSafsFloors->getFloorsBySafId($data['id']);      // Model Function to Get Floor Details
            if (collect($getFloorDtls)->isEmpty())
                $getFloorDtls = $mPropSafsFloors->getFloorsBySafId($data['id']);

            $notInApplication = collect($lastTcFloorVerificationData)->whereNotIn("saf_floor_id", collect($getFloorDtls)->pluck("id"));

            $getFloorDtls = collect($getFloorDtls)->map(function ($val) use ($lastTcFloorVerificationData) {
                $newFloors = $lastTcFloorVerificationData->where("saf_floor_id", $val->id)->first();
                $newFloorsIds = $this->addjustVerifyFloorDtls($val, $newFloors);
                $newFloorsVals = $this->addjustVerifyFloorDtlVal($newFloorsIds);
                $val->tc_verified_usage_type_mstr_id = $newFloors->usage_type_id ?? "";
                $val->tc_verified_const_type_mstr_id = $newFloors->construction_type_id ?? "";
                $val->tc_verified_occupancy_type_mstr_id = $newFloors->occupancy_type_id ?? "";
                $val->tc_verified_builtup_area = $newFloors->builtup_area ?? "";
                $val->tc_verified_date_from = $newFloors->date_from ?? "";
                $val->tc_verified_date_upto = $newFloors->date_to ?? "";
                $val->tc_verified_carpet_area = $newFloors->carpet_area ?? "";
                $val->tc_verified_floor_name = $newFloors ? $newFloorsVals->floor_name : "";
                $val->tc_verified_usage_type = $newFloors ? $newFloorsVals->usage_type  : "";
                $val->tc_verified_occupancy_type = $newFloors ? $newFloorsVals->occupancy_type  : "";
                $val->tc_verified_construction_type = $newFloors ? $newFloorsVals->construction_type  : "";
                return $val;
            });
            $notInApplication->map(function ($val) use ($getFloorDtls, $data) {
                $newfloorObj = new PropActiveSafsFloor();
                $val = $this->addjustVerifyFloorDtls($newfloorObj, $val);
                $val = $this->addjustVerifyFloorDtlVal($val);

                $val->tc_verified_usage_type_mstr_id = $val->usage_type_mstr_id ?? "";
                $val->tc_verified_const_type_mstr_id = $val->const_type_mstr_id ?? "";
                $val->tc_verified_occupancy_type_mstr_id = $val->occupancy_type_mstr_id ?? "";
                $val->tc_verified_builtup_area = $val->builtup_area ?? "";
                $val->tc_verified_date_from = $val->date_from ?? "";
                $val->tc_verified_date_upto = $val->date_upto ?? "";
                $val->tc_verified_carpet_area = $val->carpet_area ?? "";
                $val->tc_verified_floor_name = $val->floor_name ?? "";
                $val->tc_verified_usage_type = $val->usage_type ?? "";
                $val->tc_verified_occupancy_type = $val->occupancy_type ?? "";
                $val->tc_verified_construction_type = $val->construction_type ?? "";

                $val->id = 0;
                $val->saf_id = $data['id'];
                // $val->floor_mstr_id = null ;    
                // $val->usage_type_mstr_id = null ;    
                // $val->const_type_mstr_id = null ;      
                // $val->occupancy_type_mstr_id = null ; 
                // $val->builtup_area = null ; 
                // $val->date_from = null ; 
                // $val->date_upto = null ; 
                // $val->status = null ; 
                // $val->carpet_area = null ; 
                // $val->prop_floor_details_id = null ; 
                // $val->user_id = null ; 
                // $val->old_floor_id = null ; 
                // $val->no_of_rooms = null ; 
                // $val->no_of_toilets = null ; 
                // $val->floor_name = null ; 
                // $val->usage_type = null ; 
                // $val->occupancy_type = null ; 
                // $val->construction_type = null ; 
                $getFloorDtls->push($val);
            });
            $document = $this->getUploadDoc($req);
            $data['floors'] = $getFloorDtls;
            $data["tranDtl"] = $mPropTransaction->getSafTranList($data['id']);
            $data["userDtl"] = [
                "user_id" => $user->id ?? 0,
                "user_type" => $user->user_type ?? "",
                "ulb_id" => $user->ulb_id ?? 0,
                "user_name" => $user->name ?? ""
            ];
            $memoDtls = $mPropSafMemoDtls->memoLists($data['id']);
            $data['memoDtls'] = $memoDtls;
            if ($status = ((new \App\Repository\Property\Concrete\SafRepository())->applicationStatus($req->applicationId, true))) {
                $data["current_role_name2"] = $status;
            }
            $usertype = $this->_COMMONFUNCTION->getUserAllRoles();
            $testRole = collect($usertype)->whereIn("sort_name", Config::get("TradeConstant.CANE-CUTE-PAYMENT"));
            $data["can_take_payment"] = (collect($testRole)->isNotEmpty() && ($data["proccess_fee_paid"] ?? 1) == 0) ? true : false;
            if ($this->_COMMONFUNCTION->checkUsersWithtocken("active_citizens")) {
                $data["can_take_payment"] = (($data["proccess_fee_paid"] ?? 1) == 0) ? true : false;
            }
            $data["lastTcVerificationData"] = $lastTcVerificationData;
            $data["lastTcFloorVerificationData"] = $lastTcFloorVerificationData;
            $data["documents"] = $document;
            return responseMsgs(true, "Saf Dtls", remove_null($data), "010127", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(true, $e->getMessage(), [], "010127", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    public function getOrignalSaf(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807'
        ]);
        try {
            // Variable Assignments
            $mPropActiveSaf = new PropActiveSaf();
            $mPropSafOwner = new PropSafsOwner();
            $mPropSaf = new PropSaf();
            $mPropSafsFloors = new PropSafsFloor();
            $mPropActiveSafOwner = new PropActiveSafsOwner();
            $mActiveSafsFloors = new PropActiveSafsFloor();
            $mPropSafMemoDtls = new PropSafMemoDtl();
            $mPropTransaction = new PropTransaction();

            $memoDtls = array();
            $data = array();
            $user = Auth()->user();

            // Derivative Assignments
            $data = $mPropActiveSaf->getActiveSafDtls()                         // <------- Model function Active SAF Details
                ->where('prop_active_safs.id', $req->applicationId)
                ->first();
            // if (!$data)
            // throw new Exception("Application Not Found");

            if (collect($data)->isEmpty()) {
                $data = $mPropSaf->getSafDtls()
                    ->where('prop_safs.id', $req->applicationId)
                    ->first();
            }

            if (collect($data)->isEmpty())
                throw new Exception("Application Not Found");

            $data->current_role_name = 'Approved By ' . $data->current_role_name;
            // $data->
            if ($data->payment_status == 0) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Payment is Pending";
            } elseif ($data->payment_status == 2) {
                $data->current_role_name = null;
                $data->current_role_name2 = "Cheque Payment Verification Pending";
            } else
                $data->current_role_name2 = $data->current_role_name;

            $data = json_decode(json_encode($data), true);

            $assessmentType = collect(Config::get("PropertyConstaint.ASSESSMENT-TYPE"))->flip();
            $data["assessment_type_id"] = $assessmentType[$data["assessment_type"]] ?? null;
            $flipArea = $data["bifurcated_from_plot_area"];
            if (in_array($data["assessment_type_id"], [4])) {
                $data["bifurcated_from_plot_area"] = $data["area_of_plot"];
                $data["area_of_plot"] = $flipArea;
            }

            $ownerDtls = $mPropActiveSafOwner->getOwnersBySafId($data['id']);
            if (collect($ownerDtls)->isEmpty())
                $ownerDtls = $mPropSafOwner->getOwnersBySafId($data['id']);

            $data['owners'] = $ownerDtls;
            $getFloorDtls = $mActiveSafsFloors->getFloorsBySafId($data['id']);      // Model Function to Get Floor Details
            if (collect($getFloorDtls)->isEmpty())
                $getFloorDtls = $mPropSafsFloors->getFloorsBySafId($data['id']);

            $getFloorDtls->map(function ($val) use ($data) {
                $flipArea = roundFigure(is_numeric($val->bifurcated_from_buildup_area) ? $val->bifurcated_from_buildup_area : 0);
                if (in_array($data["assessment_type_id"], [4])) {
                    $val->bifurcated_from_buildup_area = roundFigure(is_numeric($val->builtup_area) ? $val->builtup_area : 0);
                    $val->builtup_area = $flipArea;
                }
                return $val;
            });
            $data['floors'] = $getFloorDtls;
            $data["tranDtl"] = $mPropTransaction->getSafTranList($data['id']);
            $data["userDtl"] = [
                "user_id" => $user->id ?? 0,
                "user_type" => $user->user_type ?? "",
                "ulb_id" => $user->ulb_id ?? 0,
                "user_name" => $user->name ?? ""
            ];
            $memoDtls = $mPropSafMemoDtls->memoLists($data['id']);
            $data['memoDtls'] = $memoDtls;
            if ($status = ((new \App\Repository\Property\Concrete\SafRepository())->applicationStatus($req->applicationId, true))) {
                $data["current_role_name2"] = $status;
            }
            $usertype = $this->_COMMONFUNCTION->getUserAllRoles();
            $testRole = collect($usertype)->whereIn("sort_name", Config::get("TradeConstant.CANE-CUTE-PAYMENT"));
            $data["can_take_payment"] = (collect($testRole)->isNotEmpty() && ($data["proccess_fee_paid"] ?? 1) == 0) ? true : false;
            if ($this->_COMMONFUNCTION->checkUsersWithtocken("active_citizens")) {
                $data["can_take_payment"] = (($data["proccess_fee_paid"] ?? 1) == 0) ? true : false;
            }
            return responseMsgs(true, "Saf Dtls", remove_null($data), "010127", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(true, $e->getMessage(), [], "010127", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * @var userId Logged In User Id
     * desc This function set OR remove application on special category
     * request : escalateStatus (required, int type), safId(required)
     * -----------------Tables---------------------
     *  active_saf_details
     * ============================================
     * active_saf_details.is_escalate <- request->escalateStatus 
     * active_saf_details.escalate_by <- request->escalateStatus 
     * ============================================
     * #message -> return response 
     * Status-Closed
     * | Query Cost-353ms 
     * | Rating-1
     */
    public function postEscalate(Request $request)
    {
        $request->validate([
            "escalateStatus" => "required|int",
            "applicationId" => "required|int",
        ]);
        try {
            $userId = authUser($request)->id;
            $saf_id = $request->applicationId;
            $data = PropActiveSaf::find($saf_id);
            $data->is_escalate = $request->escalateStatus;
            $data->escalate_by = $userId;
            $data->save();
            return responseMsgs(true, $request->escalateStatus == 1 ? 'Saf is Escalated' : "Saf is removed from Escalated", '', "010106", "1.0", "353ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    // Post Independent Comment
    public function commentIndependent(Request $request)
    {
        $request->validate([
            'comment' => 'required',
            'applicationId' => 'required|integer',
        ]);

        try {
            $userId = authUser($request)->id;
            $userType = authUser($request)->user_type;
            $workflowTrack = new WorkflowTrack();
            $mWfRoleUsermap = new WfRoleusermap();
            $saf = PropActiveSaf::findOrFail($request->applicationId);                // SAF Details
            $mModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs = array();
            // Save On Workflow Track For Level Independent
            $metaReqs = [
                'workflowId' => $saf->workflow_id,
                'moduleId' => $mModuleId,
                'refTableDotId' => "prop_active_safs.id",
                'refTableIdValue' => $saf->id,
                'message' => $request->comment
            ];
            if ($userType != 'Citizen') {
                $roleReqs = new Request([
                    'workflowId' => $saf->workflow_id,
                    'userId' => $userId,
                ]);
                $wfRoleId = $mWfRoleUsermap->getRoleByUserWfId($roleReqs);
                $metaReqs = array_merge($metaReqs, ['senderRoleId' => $wfRoleId->wf_role_id]);
                $metaReqs = array_merge($metaReqs, ['user_id' => $userId]);
            }
            DB::beginTransaction();
            DB::connection('pgsql_master');
            // For Citizen Independent Comment
            if ($userType == 'Citizen') {
                $metaReqs = array_merge($metaReqs, ['citizenId' => $userId]);
                $metaReqs = array_merge($metaReqs, ['ulb_id' => $saf->ulb_id]);
                $metaReqs = array_merge($metaReqs, ['user_id' => NULL]);
            }

            $request->request->add($metaReqs);
            $workflowTrack->saveTrack($request);

            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "You Have Commented Successfully!!", ['Comment' => $request->comment], "010108", "1.0", "", "POST", "");
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Function for Post Next Level(9)
     * | @param mixed $request
     * | @var preLevelPending Get the Previous level pending data for the saf id
     * | @var levelPending new Level Pending to be add
     * | Status-Closed
     * | Rating-3 
     */
    public function postNextLevel(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId' => 'required|integer',
                'receiverRoleId' => 'nullable|integer',
                'action' => 'required|In:forward,backward'
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            // Variable Assigments
            $userId = authUser($request)->id;
            $wfLevels = Config::get('PropertyConstaint.SAF-LABEL');
            $saf = PropActiveSaf::findOrFail($request->applicationId);
            $mWfMstr = new WfWorkflow();
            $track = new WorkflowTrack();
            $mWfWorkflows = new WfWorkflow();
            $mWfRoleMaps = new WfWorkflowrolemap();
            $mPropSafGeotagUpload = new PropSafGeotagUpload();
            $propSafVerification = new PropSafVerification();
            $samHoldingDtls = array();
            $safId = $saf->id;

            // Derivative Assignments
            $senderRoleId = $saf->current_role;
            if ($saf->parked)
                $senderRoleId = $saf->initiator_role_id;

            if (!$senderRoleId)
                throw new Exception("Current Role Not Available");

            $request->validate([
                'comment' => $senderRoleId == $wfLevels['BO'] ? 'nullable' : 'required',

            ]);
            $ulbWorkflowId = $saf->workflow_id;
            $ulbWorkflowMaps = $mWfWorkflows->getWfDetails($ulbWorkflowId);
            $roleMapsReqs = new Request([
                'workflowId' => $ulbWorkflowMaps->id,
                'roleId' => $senderRoleId
            ]);
            $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIds($roleMapsReqs);
            if (!$forwardBackwardIds) {
                $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIdsV2($roleMapsReqs);
            }
            if (!$forwardBackwardIds) {
                throw new Exception("You Are Not Authorize For This Workflow");
            }
            if (in_array($saf->workflow_id, $this->_alowJahirnamaWorckflows) && $request->action == 'forward') {
                $this->checkMutionCondition($wfLevels, $saf);
            }
            $wfMstrId = $mWfMstr->getWfMstrByWorkflowId($saf->workflow_id)->wf_master_id ?? null;
            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            if ($request->action == 'forward') {
                if ($saf->doc_upload_status == 0 && $senderRoleId == $wfLevels['BO']) {
                    $docUploadStatus = (new SafDocController())->checkFullDocUpload($saf->id);
                    $saf->doc_upload_status = $docUploadStatus ? 1 : $saf->doc_upload_status;
                }
                if ($saf->doc_verify_status == 0 && $senderRoleId == $wfLevels['DA']) {
                    $docUploadStatus = (new SafDocController())->ifFullDocVerified($saf->id);
                    $saf->doc_verify_status = $docUploadStatus ? 1 : $saf->doc_verify_status;
                }
                $gioTag = $mPropSafGeotagUpload->getGeoTags($saf->id);
                $fieldVerifiedSaf = $propSafVerification->getVerificationsBySafId($safId);
                if ($saf->prop_type_mstr_id == 4 && collect($fieldVerifiedSaf)->isEmpty()) {
                    $fieldVerifiedSaf = $propSafVerification->getVerifications($safId);
                    if (collect($fieldVerifiedSaf)->isEmpty()) {
                        $fieldVerifiedSaf = $propSafVerification->getVerifications2($safId);
                    }
                }
                if (collect($fieldVerifiedSaf)->isNotEmpty() && $saf->current_role == $wfLevels['UTC']) {
                    $saf->is_field_verified = true;
                }
                if (!$gioTag->isEmpty()) {
                    $saf->is_geo_tagged = true;
                }
                if (!$saf->is_field_verified && $saf->prop_type_mstr_id != 4 && $saf->current_role == $wfLevels['UTC']) #make option UTC Verification
                {
                    $saf->is_field_verified = true;
                }
                $saf->update();

                $samHoldingDtls = $this->checkPostCondition($senderRoleId, $wfLevels, $saf, $wfMstrId, $userId);          // Check Post Next level condition

                $geotagExist = $saf->is_field_verified == true;
                if ($saf->prop_type_mstr_id == 4) {
                    $geotagExist = $saf->is_agency_verified;
                }

                if ($saf->prop_type_mstr_id == 4 && $saf->current_role == $wfLevels['TC']) #only for Vacant Land
                {
                    $saf->is_agency_verified = true;
                    $saf->update();
                    $forwardBackwardIds->forward_role_id = $wfLevels['DA'];
                }
                if (!$geotagExist && $saf->current_role == $wfLevels['DA'] && !in_array($wfMstrId, $this->_SkipFiledWorkWfMstrId)) {
                    $forwardBackwardIds->forward_role_id = $wfLevels['UTC'];
                    if ($saf->prop_type_mstr_id == 4) {
                        $forwardBackwardIds->forward_role_id = $wfLevels['TC'];
                    }
                }

                if ($saf->is_bt_da == true) {
                    $forwardBackwardIds->forward_role_id = $wfLevels['SI'];
                    $saf->is_bt_da = false;
                }

                if ($saf->parked == true) {
                    $saf->parked = false;
                    $forwardBackwardIds->forward_role_id =  $saf->current_role;
                }

                $saf->current_role = $forwardBackwardIds->forward_role_id;
                $saf->last_role_id =  $forwardBackwardIds->forward_role_id;                     // Update Last Role Id
                $saf->parked = false;
                $metaReqs['verificationStatus'] = 1;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->forward_role_id;
            }
            // SAF Application Update Current Role Updation
            if ($request->action == 'backward') {
                $samHoldingDtls = $this->checkBackwardCondition($senderRoleId, $wfLevels, $saf);          // Check Backward condition

                #_Back to Dealing Assistant by Section Incharge
                if ($saf->current_role == $wfLevels['TC']) {
                    $saf->is_agency_verified = $saf->is_agency_verified == true ? false :  $saf->is_agency_verified;
                    $saf->is_geo_tagged = $saf->is_geo_tagged == true ? false :  $saf->is_geo_tagged;
                }
                if ($saf->current_role == $wfLevels['UTC']) {
                    $saf->is_agency_verified = $saf->is_agency_verified == true ? false :  $saf->is_agency_verified;
                    $saf->is_geo_tagged = $saf->is_geo_tagged == true ? false :  $saf->is_geo_tagged;
                    $saf->is_field_verified = $saf->is_field_verified == true ? false :  $saf->is_field_verified;
                }
                if ($request->isBtd == true) {
                    $saf->is_bt_da = true;
                    $forwardBackwardIds->backward_role_id = $wfLevels['DA'];
                }
                if ($saf->prop_type_mstr_id == 4 && $saf->current_role == $wfLevels['DA']) #only for Vacant Land
                {
                    $forwardBackwardIds->backward_role_id = $wfLevels['TC'];
                }

                if ($saf->prop_type_mstr_id == 4 && $saf->current_role == $wfLevels['DA'] && in_array($wfMstrId, $this->_SkipFiledWorkWfMstrId)) {
                    $forwardBackwardIds->backward_role_id = $wfLevels['BO'];
                }

                $saf->current_role = $forwardBackwardIds->backward_role_id;
                $metaReqs['verificationStatus'] = 0;
                $metaReqs['receiverRoleId'] = $forwardBackwardIds->backward_role_id;
            }

            $saf->save();
            // $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            // $metaReqs['workflowId'] = $saf->workflow_id;
            // $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            // $metaReqs['refTableIdValue'] = $request->applicationId;
            // $metaReqs['senderRoleId'] = $senderRoleId;
            // $metaReqs['user_id'] = $userId;
            // $metaReqs['trackDate'] = $this->_todayDate->format('Y-m-d H:i:s');
            // $request->request->add($metaReqs);
            // $track->saveTrack($request);

            // // Updation of Received Date
            // $preWorkflowReq = [
            //     'workflowId' => $saf->workflow_id,
            //     'refTableDotId' => Config::get('PropertyConstaint.SAF_REF_TABLE'),
            //     'refTableIdValue' => $request->applicationId,
            //     'receiverRoleId' => $senderRoleId
            // ];
            // $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            // if ($previousWorkflowTrack) {
            //     $previousWorkflowTrack->update([
            //         'forward_date' => $this->_todayDate->format('Y-m-d'),
            //         'forward_time' => $this->_todayDate->format('H:i:s')
            //     ]);
            // }

            //changes by prity pandey
            $preWorkflowReq = [
                'workflowId' => $saf->workflow_id,
                'refTableDotId' => Config::get('PropertyConstaint.SAF_REF_TABLE'),
                'refTableIdValue' => $request->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getLastWfTrackByRefId($preWorkflowReq);
            if ($previousWorkflowTrack && !$previousWorkflowTrack->forward_date) {
                $previousWorkflowTrack->update([
                    'forward_date' => Carbon::parse($previousWorkflowTrack->created_at)->format('Y-m-d'),
                    'forward_time' => Carbon::parse($previousWorkflowTrack->created_at)->format('H:i:s')
                ]);
            }

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $saf->workflow_id;
            $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $metaReqs['refTableIdValue'] = $request->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;
            $metaReqs['forwardDate'] = $this->_todayDate->format('Y-m-d');
            $metaReqs['forwardTime'] = $this->_todayDate->format('H:i:s');
            $metaReqs['trackDate'] = $previousWorkflowTrack ? Carbon::parse($previousWorkflowTrack->forward_date . " " . $previousWorkflowTrack->forward_time)->format('Y-m-d H:i:s') : $this->_todayDate->format('Y-m-d H:i:s');
            $request->request->add($metaReqs);
            $track->saveTrack($request);
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Successfully " . $request->action . " The Application!!", $samHoldingDtls, "010109", "1.0", "", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsg(false, $e->getMessage(), "", "010109", "1.0", "", "POST", $request->deviceId);
        }
    }

    /**
     * | For Tc And Utc
     */
    public function postNextLevelV2(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId'  => 'required|integer',
                'receiverRoleId' => 'required|integer',
                'action'         => 'required|In:forward,backward'
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            // Variable Assigments
            $userId               = authUser($request)->id;
            $wfLevels             = Config::get('PropertyConstaint.SAF-LABEL');
            $saf                  = PropActiveSaf::findOrFail($request->applicationId);
            $mWfMstr              = new WfWorkflow();
            $track                = new WorkflowTrack();
            $mWfWorkflows         = new WfWorkflow();
            $mWfRoleMaps          = new WfWorkflowrolemap();
            $mPropSafGeotagUpload = new PropSafGeotagUpload();
            $propSafVerification  = new PropSafVerification();
            $samHoldingDtls       = array();
            $safId                = $saf->id;

            // Derivative Assignments
            $senderRoleId = $saf->current_role;
            if ($saf->parked)
                $senderRoleId = $saf->initiator_role_id;

            if (!$senderRoleId)
                throw new Exception("Current Role Not Available");

            $request->validate([
                'comment' => $senderRoleId == $wfLevels['BO'] ? 'nullable' : 'required',
            ]);

            $ulbWorkflowId = $saf->workflow_id;
            $ulbWorkflowMaps = $mWfWorkflows->getWfDetails($ulbWorkflowId);
            $roleMapsReqs = new Request([
                'workflowId' => $ulbWorkflowMaps->id,
                'roleId' => $senderRoleId
            ]);
            $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIds($roleMapsReqs);
            if (!$forwardBackwardIds) {
                $forwardBackwardIds = $mWfRoleMaps->getWfBackForwardIdsV2($roleMapsReqs);
            }
            if (!$forwardBackwardIds) {
                throw new Exception("You Are Not Authorize For This Workflow");
            }
            if (in_array($saf->workflow_id, $this->_alowJahirnamaWorckflows) && $request->action == 'forward') {
                $this->checkMutionCondition($wfLevels, $saf);
            }
            $wfMstrId = $mWfMstr->getWfMstrByWorkflowId($saf->workflow_id)->wf_master_id ?? null;
            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            if ($request->action == 'forward') {
                if ($saf->doc_upload_status == 0 && $senderRoleId == $wfLevels['BO']) {
                    $docUploadStatus = (new SafDocController())->checkFullDocUpload($saf->id);
                    $saf->doc_upload_status = $docUploadStatus ? 1 : $saf->doc_upload_status;
                }
                if ($saf->doc_verify_status == 0 && $senderRoleId == $wfLevels['DA']) {
                    $docUploadStatus = (new SafDocController())->ifFullDocVerified($saf->id);
                    $saf->doc_verify_status = $docUploadStatus ? 1 : $saf->doc_verify_status;
                }
                $gioTag = $mPropSafGeotagUpload->getGeoTags($saf->id);
                $fieldVerifiedSaf = $propSafVerification->getVerificationsBySafId($safId);
                if ($saf->prop_type_mstr_id == 4 && collect($fieldVerifiedSaf)->isEmpty()) {
                    $fieldVerifiedSaf = $propSafVerification->getVerifications($safId);
                    if (collect($fieldVerifiedSaf)->isEmpty()) {
                        $fieldVerifiedSaf = $propSafVerification->getVerifications2($safId);
                    }
                }
                if (collect($fieldVerifiedSaf)->isNotEmpty() && $saf->current_role == $wfLevels['UTC']) {
                    $saf->is_field_verified = true;
                }
                if (!$gioTag->isEmpty()) {
                    $saf->is_geo_tagged = true;
                }
                // if (!$saf->is_field_verified && $saf->prop_type_mstr_id != 4 && $saf->current_role == $wfLevels['UTC']) #make option UTC Verification
                // {
                //     $saf->is_field_verified = true;
                // }
                $saf->update();

                $samHoldingDtls = $this->checkPostCondition($senderRoleId, $wfLevels, $saf, $wfMstrId, $userId);          // Check Post Next level condition

                $geotagExist = $saf->is_field_verified == true;
                if ($saf->prop_type_mstr_id == 4) {
                    $geotagExist = $saf->is_agency_verified;
                }

                if ($saf->prop_type_mstr_id == 4 && $saf->current_role == $wfLevels['TC']) #only for Vacant Land
                {
                    $saf->is_agency_verified = true;
                    $saf->update();
                    // $forwardBackwardIds->forward_role_id = $wfLevels['DA'];
                }
                // if (!$geotagExist && $saf->current_role == $wfLevels['DA'] && !in_array($wfMstrId, $this->_SkipFiledWorkWfMstrId)) {
                //     $forwardBackwardIds->forward_role_id = $wfLevels['UTC'];
                //     if ($saf->prop_type_mstr_id == 4) {
                //         $forwardBackwardIds->forward_role_id = $wfLevels['TC'];
                //     }
                // }

                if ($saf->is_bt_da == true) {
                    // $forwardBackwardIds->forward_role_id = $wfLevels['SI'];
                    $saf->is_bt_da = false;
                }

                if ($saf->parked == true) {
                    $saf->parked = false;
                    // $forwardBackwardIds->forward_role_id =  $saf->current_role;
                }

                $saf->current_role = $request->receiverRoleId;
                $saf->last_role_id = $request->receiverRoleId;                     // Update Last Role Id
                $saf->parked = false;
                $metaReqs['verificationStatus'] = 1;
                $metaReqs['receiverRoleId'] = $request->receiverRoleId;
            }
            // SAF Application Update Current Role Updation
            // if ($request->action == 'backward') {
            //     $samHoldingDtls = $this->checkBackwardCondition($senderRoleId, $wfLevels, $saf);          // Check Backward condition

            //     #_Back to Dealing Assistant by Section Incharge
            //     if ($saf->current_role == $wfLevels['TC']) {
            //         $saf->is_agency_verified = $saf->is_agency_verified == true ? false :  $saf->is_agency_verified;
            //         $saf->is_geo_tagged = $saf->is_geo_tagged == true ? false :  $saf->is_geo_tagged;
            //     }
            //     if ($saf->current_role == $wfLevels['UTC']) {
            //         $saf->is_agency_verified = $saf->is_agency_verified == true ? false :  $saf->is_agency_verified;
            //         $saf->is_geo_tagged = $saf->is_geo_tagged == true ? false :  $saf->is_geo_tagged;
            //         $saf->is_field_verified = $saf->is_field_verified == true ? false :  $saf->is_field_verified;
            //     }
            //     if ($request->isBtd == true) {
            //         $saf->is_bt_da = true;
            //         $forwardBackwardIds->backward_role_id = $wfLevels['DA'];
            //     }
            //     if ($saf->prop_type_mstr_id == 4 && $saf->current_role == $wfLevels['DA']) #only for Vacant Land
            //     {
            //         $forwardBackwardIds->backward_role_id = $wfLevels['TC'];
            //     }

            //     if ($saf->prop_type_mstr_id == 4 && $saf->current_role == $wfLevels['DA'] && in_array($wfMstrId, $this->_SkipFiledWorkWfMstrId)) {
            //         $forwardBackwardIds->backward_role_id = $wfLevels['BO'];
            //     }

            //     $saf->current_role = $forwardBackwardIds->backward_role_id;
            //     $metaReqs['verificationStatus'] = 0;
            //     $metaReqs['receiverRoleId'] = $forwardBackwardIds->backward_role_id;
            // }

            $saf->save();
            // $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            // $metaReqs['workflowId'] = $saf->workflow_id;
            // $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            // $metaReqs['refTableIdValue'] = $request->applicationId;
            // $metaReqs['senderRoleId'] = $senderRoleId;
            // $metaReqs['user_id'] = $userId;
            // $metaReqs['trackDate'] = $this->_todayDate->format('Y-m-d H:i:s');
            // $request->request->add($metaReqs);
            // $track->saveTrack($request);

            // // Updation of Received Date
            // $preWorkflowReq = [
            //     'workflowId' => $saf->workflow_id,
            //     'refTableDotId' => Config::get('PropertyConstaint.SAF_REF_TABLE'),
            //     'refTableIdValue' => $request->applicationId,
            //     'receiverRoleId' => $senderRoleId
            // ];
            // $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            // if ($previousWorkflowTrack) {
            //     $previousWorkflowTrack->update([
            //         'forward_date' => $this->_todayDate->format('Y-m-d'),
            //         'forward_time' => $this->_todayDate->format('H:i:s')
            //     ]);
            // }
            //changes by prity pandey
            $preWorkflowReq = [
                'workflowId' => $saf->workflow_id,
                'refTableDotId' => Config::get('PropertyConstaint.SAF_REF_TABLE'),
                'refTableIdValue' => $request->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getLastWfTrackByRefId($preWorkflowReq);
            if ($previousWorkflowTrack && !$previousWorkflowTrack->forward_date) {
                $previousWorkflowTrack->update([
                    'forward_date' => Carbon::parse($previousWorkflowTrack->created_at)->format('Y-m-d'),
                    'forward_time' => Carbon::parse($previousWorkflowTrack->created_at)->format('H:i:s')
                ]);
            }

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $saf->workflow_id;
            $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $metaReqs['refTableIdValue'] = $request->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;
            $metaReqs['forwardDate'] = $this->_todayDate->format('Y-m-d');
            $metaReqs['forwardTime'] = $this->_todayDate->format('H:i:s');
            $metaReqs['trackDate'] = $previousWorkflowTrack ? Carbon::parse($previousWorkflowTrack->forward_date . " " . $previousWorkflowTrack->forward_time)->format('Y-m-d H:i:s') : $this->_todayDate->format('Y-m-d H:i:s');
            $request->request->add($metaReqs);
            $track->saveTrack($request);
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Successfully " . $request->action . " The Application!!", $samHoldingDtls, "010109", "1.0", "", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsg(false, $e->getMessage(), "", "010109", "1.0", "", "POST", $request->deviceId);
        }
    }

    public function sendToLevel(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId'    => 'required|digits_between:1,9223372036854775807',
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id ?? 0;
            $track = new WorkflowTrack();
            $safDocController = App::makeWith(SafDocController::class);
            $saf = PropActiveSaf::find($request->applicationId);
            if (!$saf) {
                throw new Exception("Data Not Found!!!");
            }
            $wfMasters = WfWorkflow::find($saf->workflow_id);
            if (!$wfMasters) {
                throw new Exception("Workflow Not Find");
            }

            $refWorkflowId  = $wfMasters->wf_master_id;
            if (!$refUlbId) {
                $refUlbId = $saf->ulb_id;
            }
            $request->merge(["ulb_id" => $refUlbId]);
            $refWorkflows   = $this->_COMMONFUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $allRolse     = collect($this->_COMMONFUNCTION->getAllRoles($refUserId, $refUlbId, $refWorkflowId, 0, true));

            $docUploadStatus = $safDocController->checkFullDocUpload($request->applicationId);
            if (!$docUploadStatus) {
                throw new Exception("All Document Are Not Uploded");
            }

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $saf->workflow_id;
            $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $metaReqs['refTableIdValue'] = $saf->id;
            $metaReqs['citizenId'] = $refUserId;
            $metaReqs['ulb_id'] = $refUlbId;
            $metaReqs['trackDate'] = Carbon::now()->format('Y-m-d H:i:s');
            $metaReqs['forwardDate'] = Carbon::now()->format('Y-m-d');
            $metaReqs['forwardTime'] = Carbon::now()->format('H:i:s');
            $metaReqs['senderRoleId'] = $refWorkflows['initiator']['id'];
            $metaReqs["receiverRoleId"] = $refWorkflows['initiator']['forward_role_id'];
            $metaReqs['verificationStatus'] = 1;
            $metaReqs['comment'] = "Citizen Send For Verification";
            $request->merge($metaReqs);

            $receiverRole = array_values(objToArray($allRolse->where("id", $request->receiverRoleId)))[0] ?? [];
            $sms = "";
            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            $track->saveTrack($request);

            if (!$saf->current_role || ($saf->current_role == $refWorkflows['initiator']['id'])) {
                $saf->current_role = $refWorkflows['initiator']['forward_role_id'];
                $saf->last_role_id = $refWorkflows['initiator']['forward_role_id'];
                $saf->doc_upload_status = 1;
                $saf->update();
                $sms = "Application Forwarded To " . ($receiverRole["role_name"] ?? "");
            } elseif ($saf->parked) {
                $role = WfRole::find($saf->current_role);
                $saf->parked = false;
                $saf->doc_upload_status = 1;
                $saf->update();
                $sms = "Application Forwarded To " . ($role->role_name ?? "");
            }
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsg(true, $sms, "",);
        } catch (Exception $e) {
            DB::rollback();
            DB::connection('pgsql_master')->rollback();
            return responseMsg(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "",);
        }
    }

    /**
     * | check Post Condition for backward forward(9.1)
     */
    public function checkPostCondition($senderRoleId, $wfLevels, $saf, $wfMstrId, $userId)
    {
        $workflowId = WfWorkflow::where('id', $saf->workflow_id)
            ->first();
        $wfContent = Config::get('workflow-constants');
        $skipFiledWorkWfMstrId = [
            $wfContent["SAF_MUTATION_ID"],
        ];
        // Derivative Assignments
        switch ($senderRoleId) {
            case $wfLevels['BO']:                        // Back Office Condition
                if ($saf->doc_upload_status == 0)
                    throw new Exception("Document Not Fully Uploaded");
                break;

            case $wfLevels['DA']:                       // DA Condition
                if ($saf->doc_verify_status == 0)
                    throw new Exception("Document Not Fully Verified");
                break;

            case $wfLevels['TC']:
                if ($saf->is_agency_verified == false && $saf->prop_type_mstr_id != 4 && (!in_array(($workflowId->id ?? 0), $skipFiledWorkWfMstrId)))
                    throw new Exception("Agency Verification Not Done");
                if ($saf->is_geo_tagged == false && (!in_array(($workflowId->id ?? 0), $skipFiledWorkWfMstrId)))
                    throw new Exception("Geo Tagging Not Done");
                break;

            case $wfLevels['UTC']:
                if ($saf->is_field_verified == false && (!in_array(($workflowId->id ?? 0), $skipFiledWorkWfMstrId)))
                    throw new Exception("Field Verification Not Done");
                break;
        }
        return [
            'holdingNo' =>  $saf->holding_no ?? "",
            'samNo' => $samNo ?? "",
            'ptNo' => $ptNo ?? "",
        ];
    }

    /**
     * |
     */
    public function checkBackwardCondition($senderRoleId, $wfLevels, $saf)
    {
        $mPropSafGeotagUpload = new PropSafGeotagUpload();

        switch ($senderRoleId) {
            case $wfLevels['TC']:
                $saf->is_agency_verified = false;
                $saf->save();
                break;
            case $wfLevels['UTC']:
                $saf->is_geo_tagged = false;
                $saf->save();

                $mPropSafGeotagUpload->where('saf_id', $saf->id)
                    ->update(['status' => 0]);
                break;
        }
    }

    public function checkMutionCondition($wfLevels, $saf)
    {
        $jahirnamaDoc = new PropSafJahirnamaDoc();
        $jahirnama = $jahirnamaDoc->getJahirnamaBysafIdOrm($saf->id)->first();
        $jahirnamaHoldsDays = Carbon::parse($jahirnama->generation_date ?? null)->addDays(($jahirnama->min_holds_period_in_days ?? 0));

        if ($saf->current_role == $wfLevels["SI"] && in_array($saf->workflow_id, $this->_alowJahirnamaWorckflows) && !$jahirnama) {
            throw new Exception("Please Generate Jahirnama First");
        }
        if ($saf->current_role == $wfLevels["SI"] && in_array($saf->workflow_id, $this->_alowJahirnamaWorckflows) && Carbon::parse($this->_todayDate->copy())->lte($jahirnamaHoldsDays)) {
            $diffDay = Carbon::parse($this->_todayDate->copy())->diffInDays($jahirnamaHoldsDays) + 1;
            throw new Exception("Please wait for $diffDay " . ($diffDay > 1 ? "days" : "day"));
        }
        if ($saf->current_role == $wfLevels["SI"] && in_array($saf->workflow_id, $this->_alowJahirnamaWorckflows) && Carbon::parse($this->_todayDate->copy())->gt($jahirnamaHoldsDays) && !($jahirnama->is_update_objection ?? false)) {
            throw new Exception("Please update objection in jarinama and remarks of this property for further proccess");
        }
    }

    /**
     * | Replicate Tables of saf to property
     */
    public function replicateSaf($safId)
    {
        $activeSaf = PropActiveSaf::query()
            ->where('id', $safId)
            ->first();
        $ownerDetails = PropActiveSafsOwner::query()
            ->where('saf_id', $safId)
            ->get();
        $floorDetails = PropActiveSafsFloor::query()
            ->where('saf_id', $safId)
            ->get();

        $toBeProperties = PropActiveSaf::where('id', $safId)
            ->select(
                'saf_no',
                'ulb_id',
                'cluster_id',
                'holding_no',
                'applicant_name',
                'ward_mstr_id',
                'ownership_type_mstr_id',
                'prop_type_mstr_id',
                'appartment_name',
                'no_electric_connection',
                'elect_consumer_no',
                'elect_acc_no',
                'elect_bind_book_no',
                'elect_cons_category',
                'building_plan_approval_no',
                'building_plan_approval_date',
                'water_conn_no',
                'water_conn_date',
                'khata_no',
                'plot_no',
                'village_mauja_name',
                'road_type_mstr_id',
                'road_width',
                'area_of_plot',
                'prop_address',
                'prop_city',
                'prop_dist',
                'prop_pin_code',
                'prop_state',
                'corr_address',
                'corr_city',
                'corr_dist',
                'corr_pin_code',
                'corr_state',
                'is_mobile_tower',
                'tower_area',
                'tower_installation_date',
                'is_hoarding_board',
                'hoarding_area',
                'hoarding_installation_date',
                'is_petrol_pump',
                'under_ground_area',
                'petrol_pump_completion_date',
                'is_water_harvesting',
                'land_occupation_date',
                'new_ward_mstr_id',
                'zone_mstr_id',
                'flat_registry_date',
                'assessment_type',
                'holding_type',
                'apartment_details_id',
                'ip_address',
                'status',
                'user_id',
                'citizen_id',
                'pt_no',
                'building_name',
                'street_name',
                'location',
                'landmark',
                'is_gb_saf',
                'gb_office_name',
                'gb_usage_types',
                'gb_prop_usage_types',
                'is_trust',
                'trust_type',
                'is_trust_verified',
                'rwh_date_from'
            )->first();

        $assessmentType = $activeSaf->assessment_type;

        if (in_array($assessmentType, ['New Assessment', 'Bifurcation', 'Amalgamation', 'Mutation'])) { // Make New Property For New Assessment,Bifurcation and Amalgamation
            $propProperties = $toBeProperties->replicate();
            $propProperties->setTable('prop_properties');
            $propProperties->saf_id = $activeSaf->id;
            $propProperties->new_holding_no = $activeSaf->holding_no;
            $propProperties->save();

            $this->_replicatedPropId = $propProperties->id;
            // SAF Owners replication
            foreach ($ownerDetails as $ownerDetail) {
                $approvedOwners = $ownerDetail->replicate();
                $approvedOwners->setTable('prop_owners');
                $approvedOwners->property_id = $propProperties->id;
                $approvedOwners->save();
            }

            // SAF Floors Replication
            foreach ($floorDetails as $floorDetail) {
                $propFloor = $floorDetail->replicate();
                $propFloor->setTable('prop_floors');
                $propFloor->property_id = $propProperties->id;
                $propFloor->save();
            }
        }

        // Edit In Case of Reassessment,Mutation
        if (in_array($assessmentType, ['Reassessment'])) {         // Edit Property In case of Reassessment, Mutation
            $propId = $activeSaf->previous_holding_id;
            $this->_replicatedPropId = $propId;
            $mProperty = new PropProperty();
            $mPropOwners = new PropOwner();
            $mPropFloors = new PropFloor();
            $mLogPropFloors = new LogPropFloor();
            // Edit Property
            $mProperty->editPropBySaf($propId, $activeSaf);
            // Edit Owners 
            foreach ($ownerDetails as $ownerDetail) {
                if ($assessmentType == 'Reassessment') {            // In Case of Reassessment Edit Owners

                    if (!is_null($ownerDetail->prop_owner_id))
                        $ifOwnerExist = $mPropOwners->getOwnerByPropOwnerId($ownerDetail->prop_owner_id);

                    if (isset($ifOwnerExist)) {
                        $ownerDetail = array_merge($ownerDetail->toArray(), ['property_id' => $propId]);
                        $propOwner = $mPropOwners::find($ownerDetail['prop_owner_id']);
                        if (collect($propOwner)->isEmpty())
                            throw new Exception("Owner Not Exists");
                        unset($ownerDetail['id']);
                        $propOwner->update($ownerDetail);
                    }
                }
                if ($assessmentType == 'Mutation') {            // In Case of Mutation Add Owners
                    $ownerDetail = array_merge($ownerDetail->toArray(), ['property_id' => $propId]);
                    $ownerDetail = new Request($ownerDetail);
                    $mPropOwners->postOwner($ownerDetail);
                }
            }
            // Edit Floors
            foreach ($floorDetails as $floorDetail) {
                if (!is_null($floorDetail->prop_floor_details_id))
                    $ifFloorExist = $mPropFloors->getFloorByFloorId($floorDetail->prop_floor_details_id);
                $floorReqs = new Request([
                    'floor_mstr_id' => $floorDetail->floor_mstr_id,
                    'usage_type_mstr_id' => $floorDetail->usage_type_mstr_id,
                    'const_type_mstr_id' => $floorDetail->const_type_mstr_id,
                    'occupancy_type_mstr_id' => $floorDetail->occupancy_type_mstr_id,
                    'builtup_area' => $floorDetail->builtup_area,
                    'date_from' => $floorDetail->date_from,
                    'date_upto' => $floorDetail->date_upto,
                    'carpet_area' => $floorDetail->carpet_area,
                    'property_id' => $propId,
                    'saf_id' => $safId,
                    'saf_floor_id' => $floorDetail->id,
                    'prop_floor_details_id' => $floorDetail->prop_floor_details_id

                ]);
                if (isset($ifFloorExist))
                    $mPropFloors->editFloor($ifFloorExist, $floorReqs);
                else                      // If floor Not Exist by Prop Saf Id
                {
                    $isFloorBySafFloorId = $mPropFloors->getFloorBySafFloorId($safId, $floorDetail->id);        // Check the Floor Existance by Saf Floor Id
                    if ($isFloorBySafFloorId)       // If Floor Exist By Saf Floor Id
                        $mPropFloors->editFloor($isFloorBySafFloorId, $floorReqs);
                    else
                        $mPropFloors->postFloor($floorReqs);
                }
            }
        }
    }

    /**
     * | Approve or Reject The SAF Application
     * --------------------------------------------------
     * | ----------------- Initialization ---------------
     * | @param mixed $req
     * | @var activeSaf The Saf Record by Saf Id
     * | @var approvedSaf replication of the saf record to be approved
     * | @var rejectedSaf replication of the saf record to be rejected
     * ------------------- Alogrithm ---------------------
     * | $req->status (if 1 Application to be approved && if 0 application to be rejected)
     * ------------------- Dump --------------------------
     * | @return msg
     * | Status-Closed
     * | Query Cost-430ms 
     * | Rating-3
     */
    public function approvalRejectionSaf(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'applicationId' => 'required|integer',
            'status' => 'required|integer'
        ]);

        if ($validator->fails())
            return responseMsgs(false, $validator->errors(), "", "011610", "1.0", "", "POST", $req->deviceId ?? "");


        try {
            // Check if the Current User is Finisher or Not (Variable Assignments)
            $mWfRoleUsermap = new WfRoleusermap();
            $propSafVerification = new PropSafVerification();
            $track = new WorkflowTrack();
            $mPropActiveSaf = new PropActiveSaf();
            $mPropActiveSafOwner = new PropActiveSafsOwner();
            $mPropActiveSafFloor = new PropActiveSafsFloor();
            $safApprovalBll = new SafApprovalBll;
            $mPropSmsLog = new PropSmsLog();
            $holdingNo = null;
            $ptNo = null;
            $famNo = null;
            $famId = null;

            $userId = authUser($req)->id;
            $safId = $req->applicationId;
            $usertype = $this->_COMMONFUNCTION->getUserAllRoles();
            $adminAllows = false;
            $testAdminRole = collect($usertype)->whereIn("sort_name", Config::get("TradeConstant.CANE-REJECT-APPLICATION"));
            if ($usertype && $testAdminRole->isNotEmpty() && $req->status == 0) {
                $adminAllows = true;
            }
            // Derivative Assignments
            $safDetails = PropActiveSaf::find($req->applicationId);
            if (!$safDetails) {
                $saf = PropSaf::find($req->applicationId);
                if (!$saf) {
                    $saf = DB::table("prop_rejected_safs")->where("id", $req->applicationId)->first();
                    if (!$saf)
                        throw new Exception("data not Found!");
                    else {
                        throw new Exception("Application is already Rejectd");
                    }
                }
                throw new Exception("Application is already Approved");
            }
            if ($safDetails->workflow_id == 202) {
                $controller = new PropertyMutationController();
                return $controller->approve($req);
            }
            $senderRoleId = $safDetails->current_role;
            $workflowId = $safDetails->workflow_id;
            $getRoleReq = new Request([                                                 // make request to get role id of the user
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);
            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            if (collect($readRoleDtls)->isEmpty() && !$adminAllows)
                throw new Exception("You Are Not Authorized for this workflow");

            $roleId = $readRoleDtls->wf_role_id ?? null;

            if ($safDetails->finisher_role_id != $roleId && !$adminAllows)
                throw new Exception("Forbidden Access");

            $activeSaf = $mPropActiveSaf->getQuerySafById($req->applicationId);
            $ownerDetails = $mPropActiveSafOwner->getQueSafOwnersBySafId($req->applicationId);
            $floorDetails = $mPropActiveSafFloor->getQSafFloorsBySafId($req->applicationId);


            if ($safDetails->prop_type_mstr_id != 4)
                $fieldVerifiedSaf = $propSafVerification->getVerificationsBySafId($safId);          // Get fields Verified Saf with all Floor Details
            else {
                $fieldVerifiedSaf = $propSafVerification->getVerifications($safId);
            }
            if (collect($fieldVerifiedSaf)->isEmpty()) {
                $fieldVerifiedSaf = $propSafVerification->getVerifications2($safId);
            }

            if (collect($fieldVerifiedSaf)->isEmpty() && !in_array($safDetails->workflow_id, $this->_SkipFiledWorkWfMstrId) && $req->status != 0)
                throw new Exception("Site Verification not Exist");

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            // Approval
            if ($req->status == 1) {
                $safDetails->saf_pending_status = 0;
                $safDetails->save();
                $safApprovalBll->approvalProcess($safId);
                $msg = "Application Approved Successfully";
                $metaReqs['verificationStatus'] = 1;
                $holdingNo = $safApprovalBll->_holdingNo;
                $ptNo = $safApprovalBll->_ptNo;
                $famNo = $safApprovalBll->_famNo;
                $famId = $safApprovalBll->_famId;

                $mobileNo = $ownerDetails[0]['mobile_no'];
                $ownerName = $ownerDetails[0]['owner_name'];
                $applicationNo = $activeSaf->saf_no;

                #_sms in case of approval
                if (strlen($mobileNo) == 10) {
                    $newReqs = new Request(["propId" => $safApprovalBll->_replicatedPropId]);
                    $holdingTaxController = App::makeWith(HoldingTaxController::class, ["iSafRepository", iSafRepository::class]);
                    $holdingDues = $holdingTaxController->getHoldingDues($newReqs);
                    $currentDemand = $holdingDues->original['data']['currentDemand'];
                    $sms      = "Dear " . $ownerName . ", congratulations, your application Ref No. " . $applicationNo . " has been approved. Your Property ID is: " . $holdingNo . ". Please pay Rs. " . $currentDemand . " against Property Tax. For more details visit www.akolamc.org/call us at:18008907909 SWATI INDUSTRIES";
                    $response = send_sms($mobileNo, $sms, 1707169564214439001);

                    $smsReqs = [
                        "emp_id" => $userId,
                        "ref_id" => $safId,
                        "ref_type" => 'SAF',
                        "mobile_no" => $mobileNo,
                        "purpose" => 'Saf Approval',
                        "template_id" => 1707169564214439001,
                        "message" => $sms,
                        "response" => $response['status'],
                        "smgid" => $response['msg'],
                        "stampdate" => Carbon::now(),
                    ];
                    $mPropSmsLog->create($smsReqs);
                }
            }
            // Rejection
            if ($req->status == 0) {
                $this->finalRejectionSafReplica($activeSaf, $ownerDetails, $floorDetails);
                $msg = "Application Rejected Successfully";
                $metaReqs['verificationStatus'] = 0;

                $mobileNo = $ownerDetails[0]['mobile_no'];
                $ownerName = $ownerDetails[0]['owner_name'];
                $applicationNo = $activeSaf->saf_no;
                $holdingNo = $activeSaf->holding_no;

                #_sms in case if rejection
                if (strlen($mobileNo) == 10) {
                    $sms      = "Dear " . $ownerName . ", your application Ref No. " . $applicationNo . " has been returned by AMC due to incomplete Documents/Information for the Property having Holding No. " . $holdingNo . ". For more details visit www.akolamc.org/call us at:18008907909 SWATI INDUSTRIES";
                    $response = send_sms($mobileNo, $sms, 1707169564208638633);

                    $smsReqs = [
                        "emp_id" => $userId,
                        "ref_id" => $safId,
                        "ref_type" => 'SAF',
                        "mobile_no" => $mobileNo,
                        "purpose" => 'Saf Rejection',
                        "template_id" => 1707169564208638633,
                        "message" => $sms,
                        "response" => $response['status'],
                        "smgid" => $response['msg'],
                        "stampdate" => Carbon::now(),
                    ];
                    $mPropSmsLog->create($smsReqs);
                }
            }
            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $safDetails->workflow_id;
            $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['comment'] = $req->comment;
            $metaReqs['verificationStatus'] = 1;
            $metaReqs['user_id'] = $userId;
            $metaReqs['trackDate'] = $this->_todayDate->format('Y-m-d H:i:s');
            $req->request->add($metaReqs);
            $track->saveTrack($req);

            // Updation of Received Date
            $preWorkflowReq = [
                'workflowId' => $safDetails->workflow_id,
                'refTableDotId' => Config::get('PropertyConstaint.SAF_REF_TABLE'),
                'refTableIdValue' => $req->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getWfTrackByRefId($preWorkflowReq);
            if ($previousWorkflowTrack) {
                $previousWorkflowTrack->update([
                    'forward_date' => $this->_todayDate->format('Y-m-d'),
                    'forward_time' => $this->_todayDate->format('H:i:s')
                ]);
            }

            $responseFields = [
                'holdingNo' => $holdingNo,
                'ptNo' => $ptNo,
                'famNo' => $famNo,
                'famId' => $famId,
                'propId' => $safApprovalBll->_replicatedPropId
            ];
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, $msg, $responseFields, "010110", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Replication of Final Approval SAf(10.1)
     */
    public function finalApprovalSafReplica($mPropProperties, $propId, $fieldVerifiedSaf, $activeSaf, $ownerDetails, $floorDetails, $safId)
    {
        $mPropFloors = new PropFloor();
        $mPropProperties->replicateVerifiedSaf($propId, collect($fieldVerifiedSaf)->first());             // Replicate to Prop Property Table
        $approvedSaf = $activeSaf->replicate();
        $approvedSaf->setTable('prop_safs');
        $approvedSaf->id = $activeSaf->id;
        $approvedSaf->property_id = $propId;
        $approvedSaf->save();
        $activeSaf->delete();

        // Saf Owners Replication
        foreach ($ownerDetails as $ownerDetail) {
            $approvedOwner = $ownerDetail->replicate();
            $approvedOwner->setTable('prop_safs_owners');
            $approvedOwner->id = $ownerDetail->id;
            $approvedOwner->save();
            $ownerDetail->delete();
        }
        if ($activeSaf->prop_type_mstr_id != 4) {               // Applicable Not for Vacant Land
            // Saf Floors Replication
            foreach ($floorDetails as $floorDetail) {
                $approvedFloor = $floorDetail->replicate();
                $approvedFloor->setTable('prop_safs_floors');
                $approvedFloor->id = $floorDetail->id;
                $approvedFloor->save();
                $floorDetail->delete();
            }

            // Deactivate Existing Prop Floors by Saf Id
            $existingFloors = $mPropFloors->getFloorsByPropId($propId);
            if ($existingFloors)
                $mPropFloors->deactivateFloorsByPropId($propId);
            foreach ($fieldVerifiedSaf as $key) {
                $floorReqs = new Request([
                    'floor_mstr_id' => $key->floor_mstr_id,
                    'usage_type_mstr_id' => $key->usage_type_id,
                    'const_type_mstr_id' => $key->construction_type_id,
                    'occupancy_type_mstr_id' => $key->occupancy_type_id,
                    'builtup_area' => $key->builtup_area,
                    'date_from' => $key->date_from,
                    'date_upto' => $key->date_to,
                    'carpet_area' => $key->carpet_area,
                    'property_id' => $propId,
                    'saf_id' => $safId
                ]);
                $mPropFloors->postFloor($floorReqs);
            }
        }
    }

    /**
     * | Replication of Final Rejection Saf(10.2)
     */
    public function finalRejectionSafReplica($activeSaf, $ownerDetails, $floorDetails)
    {
        // Rejected SAF Application replication
        $rejectedSaf = $activeSaf->replicate();
        $rejectedSaf->setTable('prop_rejected_safs');
        $rejectedSaf->saf_approved_date = Carbon::now()->format("Y-m-d");
        $rejectedSaf->id = $activeSaf->id;
        $rejectedSaf->push();
        $activeSaf->delete();

        // SAF Owners replication
        foreach ($ownerDetails as $ownerDetail) {
            $approvedOwner = $ownerDetail->replicate();
            $approvedOwner->setTable('prop_rejected_safs_owners');
            $approvedOwner->id = $ownerDetail->id;
            $approvedOwner->save();
            $ownerDetail->delete();
        }

        if ($activeSaf->prop_type_mstr_id != 4) {           // Not Applicable for Vacant Land
            // SAF Floors Replication
            foreach ($floorDetails as $floorDetail) {
                $approvedFloor = $floorDetail->replicate();
                $approvedFloor->setTable('prop_rejected_safs_floors');
                $approvedFloor->id = $floorDetail->id;
                $approvedFloor->save();
                $floorDetail->delete();
            }
        }
    }

    /**
     * | Back to Citizen
     * | @param Request $req
     * | @var redis Establishing Redis Connection
     * | @var workflowId Workflow id of the SAF 
     * | Status-Closed
     * | Query Costing-401ms
     * | Rating-1 
     */
    public function backToCitizen(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|integer',
            'workflowId' => 'required|integer',
            'currentRoleId' => 'required|integer',
            'comment' => 'required|string'
        ]);

        try {
            $userId = authUser($req)->id;
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $safRefTableName = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $saf = PropActiveSaf::findOrFail($req->applicationId);
            $track = new WorkflowTrack();
            $mPropSmsLog = new PropSmsLog();
            $mWfActiveDocument = new WfActiveDocument();
            $senderRoleId = $saf->current_role;

            if ($saf->doc_verify_status == true) {
                // throw new Exception("Verification Done You Cannot Back to Citizen");
            }

            // Check capability for back to citizen
            $getDocReqs = [
                'activeId' => $saf->id,
                'workflowId' => $saf->workflow_id,
                'moduleId' => $moduleId
            ];
            $getRejectedDocument = $mWfActiveDocument->readRejectedDocuments($getDocReqs);
            if (collect($getRejectedDocument)->isEmpty()) {
                $getRejectedDocument = (new SecondaryDocVerification())->readRejectedDocuments($getDocReqs);
            }
            if (collect($getRejectedDocument)->isEmpty())
                throw new Exception("Document Not Rejected You Can't back to citizen this application");

            // if (is_null($saf->citizen_id)) {                // If the Application has been applied from Jsk or Ulb Employees
            //     $initiatorRoleId = $saf->initiator_role_id;
            //     $saf->current_role = $initiatorRoleId;
            //     $saf->parked = true;                        //<------ SAF Pending Status true
            // } else
            $saf->parked = true;                        // If the Application has been applied from Citizen

            DB::beginTransaction();
            DB::connection('pgsql_master');

            $saf->save();

            if ($saf->parked = true && $saf->citizen_id) {
                $metaReqs['receiverRoleId'] = null; // Send back to the citizen
            } else {
                $metaReqs['receiverRoleId'] = 11; // Send to role id 11 (fallback)
            }
            // $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            // $metaReqs['workflowId'] = $saf->workflow_id;
            // $metaReqs['refTableDotId'] = $safRefTableName;
            // $metaReqs['refTableIdValue'] = $req->applicationId;
            // $metaReqs['user_id'] = authUser($req)->id;
            // $metaReqs['verificationStatus'] = 2;
            // $metaReqs['senderRoleId'] = $senderRoleId;
            // $req->request->add($metaReqs);
            // $track->saveTrack($req);

            //changes by prity pandey
            $preWorkflowReq = [
                'workflowId' => $saf->workflow_id,
                'refTableDotId' => Config::get('PropertyConstaint.SAF_REF_TABLE'),
                'refTableIdValue' => $req->applicationId,
                'receiverRoleId' => $senderRoleId
            ];
            $previousWorkflowTrack = $track->getLastWfTrackByRefId($preWorkflowReq);
            if ($previousWorkflowTrack && !$previousWorkflowTrack->forward_date) {
                $previousWorkflowTrack->update([
                    'forward_date' => Carbon::parse($previousWorkflowTrack->created_at)->format('Y-m-d'),
                    'forward_time' => Carbon::parse($previousWorkflowTrack->created_at)->format('H:i:s')
                ]);
            }

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $saf->workflow_id;
            $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $metaReqs['refTableIdValue'] = $req->applicationId;
            $metaReqs['senderRoleId'] = $senderRoleId;
            $metaReqs['user_id'] = $userId;
            $metaReqs['forwardDate'] = $this->_todayDate->format('Y-m-d');
            $metaReqs['forwardTime'] = $this->_todayDate->format('H:i:s');
            $metaReqs['trackDate'] = $previousWorkflowTrack ? Carbon::parse($previousWorkflowTrack->forward_date." ".$previousWorkflowTrack->forward_time)->format('Y-m-d H:i:s') : $this->_todayDate->format('Y-m-d H:i:s');
            $req->request->add($metaReqs);
            $track->saveTrack($req);

            DB::commit();
            DB::connection('pgsql_master')->commit();

            $activeSafOwner = PropActiveSafsOwner::where('saf_id', $saf->id)
                ->orderBy('id')
                ->first();

            if ($activeSafOwner) {
                $mobileNo = $activeSafOwner->mobile_no;
                $ownerName = $activeSafOwner->owner_name;
                $applicationNo = $saf->saf_no;
                $holdingNo = $saf->holding_no;

                #_sms
                if (strlen($mobileNo) == 10) {
                    $sms      = "Dear " . $ownerName . ", your application Ref No. " . $applicationNo . " has been returned by AMC due to incomplete Documents/Information for the Property having Holding No. " . $holdingNo . ". For more details visit www.akolamc.org/call us at:18008907909 SWATI INDUSTRIES";
                    $response = send_sms($mobileNo, $sms, 1707169564208638633);

                    $smsReqs = [
                        "emp_id" => authUser($req)->id,
                        "ref_id" => $req->applicationId,
                        "ref_type" => 'SAF',
                        "mobile_no" => $mobileNo,
                        "purpose" => 'Back to Citizen',
                        "template_id" => 1707169564208638633,
                        "message" => $sms,
                        "response" => $response['status'],
                        "smgid" => $response['msg'],
                        "stampdate" => Carbon::now(),
                    ];
                    $mPropSmsLog->create($smsReqs);
                }
            }

            return responseMsgs(true, "Successfully Done", "", "010111", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Calculate SAF by Saf ID
     * | @param req request saf id
     * | @var array contains all the details for the saf id
     * | @var data contains the details of the saf id by the current object function
     * | @return safTaxes returns all the calculated demand
     * | Status-Closed
     * | Query Costing-417ms
     * | Rating-3 
     */
    public function calculateSafBySafId(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'id' => 'required|digits_between:1,9223372036854775807',
                'fYear' => 'nullable|max:9|min:9',
                'qtr' => 'nullable|regex:/^[1-4]+/'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mVerification = new PropSafVerification();
            $safDtls = PropActiveSaf::find($req->id);
            if (!$safDtls)
                $safDtls = PropSaf::find($req->id);

            if (collect($safDtls)->isEmpty())
                throw new Exception("Saf Not Available");

            $safVerification = $mVerification->where("saf_id", $safDtls->id)->where("status", 1)->orderBy("id", "DESC")->first();
            $fullSafDtls = $this->details($req);                    // for full details purpose
            if ($safVerification) {
                $safDtls = $this->addjustVerifySafDtls($safDtls, $safVerification);
                $safDtls = $this->addjustVerifySafDtlVal($safDtls);
                $fullSafDtls["property_type"] = $safDtls->property_type ?? $fullSafDtls["property_type"];
                $fullSafDtls["zone"]          = $safDtls->zone ?? $fullSafDtls["zone"];
                $fullSafDtls["old_ward_no"]   = $safDtls->old_ward_no ?? $fullSafDtls["old_ward_no"];
            }

            $calculateSafTaxById = new CalculateSafTaxById($safDtls);
            $demand = $calculateSafTaxById->_GRID;
            $demand['ulbWiseTax'] = [];

            if ($safDtls->saf_pending_status == 0) {                    // If Saf is Verified
                $propId = $safDtls->property_id;
                $calculateByPropId = new CalculatePropTaxByPropId($propId);
                $demand['ulbWiseTax'] = $calculateByPropId->_GRID;

                $safGrandTax = $demand['grandTaxes'];
                $ulbGrandTax = $demand['ulbWiseTax']['grandTaxes'];
                $demand['taxDiffs'] = [                                 // Differences in Tax
                    "alv" => roundFigure($ulbGrandTax['alv'] - $safGrandTax['alv']),
                    "generalTax" => roundFigure($ulbGrandTax['generalTax'] - $safGrandTax['generalTax']),
                    "roadTax" => roundFigure($ulbGrandTax['roadTax'] - $safGrandTax['roadTax']),
                    "firefightingTax" => roundFigure($ulbGrandTax['firefightingTax'] - $safGrandTax['firefightingTax']),
                    "educationTax" => roundFigure($ulbGrandTax['educationTax'] - $safGrandTax['educationTax']),
                    "waterTax" => roundFigure($ulbGrandTax['waterTax'] - $safGrandTax['waterTax']),
                    "cleanlinessTax" => roundFigure($ulbGrandTax['cleanlinessTax'] - $safGrandTax['cleanlinessTax']),
                    "sewerageTax" => roundFigure($ulbGrandTax['sewerageTax'] - $safGrandTax['sewerageTax']),
                    "treeTax" => roundFigure($ulbGrandTax['treeTax'] - $safGrandTax['treeTax']),
                    "stateEducationTax" => roundFigure($ulbGrandTax['stateEducationTax'] - $safGrandTax['stateEducationTax']),
                    "professionalTax" => roundFigure($ulbGrandTax['professionalTax'] - $safGrandTax['professionalTax']),
                    "totalTax" => roundFigure($ulbGrandTax['totalTax'] - $safGrandTax['totalTax'])
                ];
            }

            $demand['basicDetails'] = [
                "ulb_id" => $fullSafDtls['ulb_id'],
                "saf_no" => $fullSafDtls['saf_no'],
                "prop_address" => $fullSafDtls['prop_address'],
                "property_no" => $fullSafDtls['property_no'],
                "is_mobile_tower" => $fullSafDtls['is_mobile_tower'],
                "is_hoarding_board" => $fullSafDtls['is_hoarding_board'],
                "is_petrol_pump" => $fullSafDtls['is_petrol_pump'],
                "is_water_harvesting" => $fullSafDtls['is_water_harvesting'],
                "zone_mstr_id" => $fullSafDtls['zone_mstr_id'],
                "zone" => $fullSafDtls['zone'],
                "holding_no" => $fullSafDtls['new_holding_no'] ?? $fullSafDtls['holding_no'],
                "ward_no" => $fullSafDtls['old_ward_no'],
                "property_type" => $fullSafDtls['property_type'],
                "holding_type" => $fullSafDtls['holding_type'],
                "doc_upload_status" => $fullSafDtls['doc_upload_status'],
                "ownership_type" => $fullSafDtls['ownership_type'],
                "payment_status" => $fullSafDtls['payment_status'],
                "categoryType"  => $fullSafDtls['category'] ?? "",
                "category_description"  => $fullSafDtls['category_description'] ?? "",
            ];

            return responseMsgs(true, "Demand Details", remove_null($demand), "", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    #create By Sandeep Bara
    # Date 14-10-2023
    #========Fam Reciept Data========
    public function AkolaFam(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'safId' => 'required|digits_between:1,9223372036854775807'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $request->merge(["id" => $request->safId, "applicationId" => $request->safId]);
            $response = $this->calculateSafBySafId($request);
            if (!$response->original["status"]) {
                throw new Exception($response->original["message"]);
            }
            $mProperties = new PropProperty();
            $data = $response->original["data"];
            $fullSafDtls = $this->details($request);
            $property = (array)$mProperties->getPropDtls()
                ->where('prop_properties.id', $fullSafDtls["property_id"] ?? 0)
                ->first();
            $tax = $data["fyearWiseTaxes"];
            $correntTax = collect($tax)->where("fyear", '=', getFy());
            $arrearTax = collect($tax)->where("fyear", '<', getFy());
            $data["floorsTaxes"]->map(function ($val) {
                $val["quaterly"] = roundFigure(($val["taxValue"] ?? 0) / 4);
                return $val;
            });
            $data["ownersDtls"] = [
                "ownerName"         => $fullSafDtls["owners"]->implode("owner_name", ","),
                "guardianName"      => $fullSafDtls["owners"]->implode("guardian_name", ","),
                "mobileNo"          => $fullSafDtls["owners"]->implode("mobile_no", ","),
                "ownerNameMarathi"  => $fullSafDtls["owners"]->implode("owner_name_marathi", ","),
                "guardianNameMarathi" => $fullSafDtls["owners"]->implode("guardian_name_marathi", ","),
            ];
            $data["currentTax"] = [
                "alv"                   => roundFigure($correntTax->sum("alv")),
                "maintancePerc"         => roundFigure($correntTax->sum("maintancePerc")),
                "maintantance10Perc"    => roundFigure($correntTax->sum("maintantance10Perc")),
                "valueAfterMaintance"   => roundFigure($correntTax->sum("valueAfterMaintance")),
                "agingPerc"             => roundFigure($correntTax->sum("agingPerc")),
                "agingAmt"              => roundFigure($correntTax->sum("agingAmt")),
                "taxValue"              => roundFigure($correntTax->sum("taxValue")),
                "generalTax"            => roundFigure($correntTax->sum("generalTax")),
                "roadTax"               => roundFigure($correntTax->sum("roadTax")),
                "firefightingTax"       => roundFigure($correntTax->sum("firefightingTax")),
                "educationTax"          => roundFigure($correntTax->sum("educationTax")),
                "waterTax"              => roundFigure($correntTax->sum("waterTax")),
                "cleanlinessTax"        => roundFigure($correntTax->sum("cleanlinessTax")),
                "sewerageTax"           => roundFigure($correntTax->sum("sewerageTax")),
                "treeTax"               => roundFigure($correntTax->sum("treeTax")),
                "stateEducationTaxPerc" => roundFigure($correntTax->sum("stateEducationTaxPerc")),
                "stateEducationTax"     => roundFigure($correntTax->sum("stateEducationTax")),
                "professionalTaxPerc"   => roundFigure($correntTax->sum("professionalTaxPerc")),
                "professionalTax"       => roundFigure($correntTax->sum("professionalTax")),
                "totalTax"              => roundFigure($correntTax->sum("totalTax")),
                "openPloatTax"          => roundFigure($correntTax->sum("openPloatTax")),
                "plotArea"              => roundFigure($fullSafDtls["area_of_plot"] ?? "0"),
                "plotAreaSQTM"          => roundFigure(sqFtToSqMt($fullSafDtls["area_of_plot"] ?? "0")),
                "floorsCount"           => ($fullSafDtls["floors"]->count() ?? "0"),
                "wardNo"                => $fullSafDtls["old_ward_no"] ?? "",
                "propertyNo"            => $property["property_no"] ?? "",
                "toilet"                => 0,
                "partNo"                => $fullSafDtls["part_no"] ?? "",
                "propertyType"          => $fullSafDtls["INDEPENDENT BUILDING"] ?? "",
                "holdingType"           => $fullSafDtls["PURE COMMERCIAL"] ?? "",
            ];
            $data["arrearTax"] = [
                "alv"                   => roundFigure($arrearTax->sum("alv")),
                "maintancePerc"         => roundFigure($arrearTax->sum("maintancePerc")),
                "maintantance10Perc"    => roundFigure($arrearTax->sum("maintantance10Perc")),
                "valueAfterMaintance"   => roundFigure($arrearTax->sum("valueAfterMaintance")),
                "agingPerc"             => roundFigure($arrearTax->sum("agingPerc")),
                "agingAmt"              => roundFigure($arrearTax->sum("agingAmt")),
                "taxValue"              => roundFigure($arrearTax->sum("taxValue")),
                "generalTax"            => roundFigure($arrearTax->sum("generalTax")),
                "roadTax"               => roundFigure($arrearTax->sum("roadTax")),
                "firefightingTax"       => roundFigure($arrearTax->sum("firefightingTax")),
                "educationTax"          => roundFigure($arrearTax->sum("educationTax")),
                "waterTax"              => roundFigure($arrearTax->sum("waterTax")),
                "cleanlinessTax"        => roundFigure($arrearTax->sum("cleanlinessTax")),
                "sewerageTax"           => roundFigure($arrearTax->sum("sewerageTax")),
                "treeTax"               => roundFigure($arrearTax->sum("treeTax")),
                "stateEducationTaxPerc" => roundFigure($arrearTax->sum("stateEducationTaxPerc")),
                "stateEducationTax"     => roundFigure($arrearTax->sum("stateEducationTax")),
                "professionalTaxPerc"   => roundFigure($arrearTax->sum("professionalTaxPerc")),
                "professionalTax"       => roundFigure($arrearTax->sum("professionalTax")),
                "totalTax"              => roundFigure($arrearTax->sum("totalTax")),
                "openPloatTax"          => roundFigure($arrearTax->sum("openPloatTax")),
                "plotArea"              => roundFigure($fullSafDtls["area_of_plot"] ?? ""),
                "plotAreaSQTM"          => roundFigure(sqFtToSqMt($fullSafDtls["area_of_plot"] ?? "0")),
                "floorsCount"           => ($fullSafDtls["floors"]->count() ?? "0"),
                "wardNo"                => $fullSafDtls["old_ward_no"] ?? "",
                "propertyNo"            => $property["property_no"] ?? "",
                "toilet"                => 0,
                "partNo"                => $fullSafDtls["part_no"] ?? "",
                "propertyType"          => $fullSafDtls["INDEPENDENT BUILDING"] ?? "",
                "holdingType"           => $fullSafDtls["PURE COMMERCIAL"] ?? "",
            ];
            $geoTagging = PropSafGeotagUpload::where("saf_id", $request->safId)->orderBy("id", "ASC")->get()->map(function ($val) {
                $val->paths = (config('app.url') . "/" . $val->relative_path . "/" . $val->image_path);
                return $val;
            });
            $safDocController = App::makeWith(SafDocController::class);
            $response = $safDocController->getUploadDocuments($request);
            $map = collect();
            if ($response->original["status"]) {
                $map = collect($response->original["data"])->whereIn("doc_category", ["Layout sanction Map", "Layout sanction Mape"])->first();
                if ($map)
                    $map["ext"] = strtolower(collect(explode(".", $map["doc_path"]))->last());
            }

            $data["geoTagging"] = $geoTagging;
            $data["images"] = [
                "photograph" => collect($data["geoTagging"])->where("direction_type", "Front") ? (collect($data["geoTagging"])->where("direction_type", "Front"))->pluck("paths")->first() ?? "" : (collect($data["geoTagging"])->where("direction_type", "<>", "naksha")->first() ? (collect($data["geoTagging"])->where("direction_type", "<>", "naksha")->first())->pluck("paths") : ""),
                "naksha"    => $map ? $map["doc_path"] : (collect($data["geoTagging"])->where("direction_type", "naksha")->first() ? (collect($data["geoTagging"])->where("direction_type", "naksha")->first())->pluck("paths") : ""),
            ];
            $floorsTaxes = collect($data["floorsTaxes"] ?? []);
            $data["grandFloorsTaxes"] = [
                "totalFloors"       => $floorsTaxes->count("floorNo") ?? 0,
                "dateFrom"          => $floorsTaxes->min("dateFrom") ?? "",
                "appliedFrom"       => $floorsTaxes->min("appliedFrom") ?? "",
                "rate"              => roundFigure($floorsTaxes->sum("rate") ?? 0),
                "floorKey"          => $floorsTaxes->max("floorKey") ?? "",
                "floorNo"           => $floorsTaxes->max("floorNo") ?? "",
                "buildupAreaInSqmt" => roundFigure($floorsTaxes->sum("buildupAreaInSqmt") ?? "0"),
                "alv"               => roundFigure($floorsTaxes->sum("alv") ?? "0"),
                "maintancePerc"     => roundFigure($floorsTaxes->sum("maintancePerc") ?? "0"),
                "maintantance10Perc" => roundFigure($floorsTaxes->sum("maintantance10Perc") ?? "0"),
                "valueAfterMaintance" => roundFigure($floorsTaxes->sum("valueAfterMaintance") ?? "0"),
                "agingPerc"         => roundFigure($floorsTaxes->sum("agingPerc") ?? "0"),
                "agingAmt"          => roundFigure($floorsTaxes->sum("agingAmt") ?? "0"),
                "taxValue"          => roundFigure($floorsTaxes->sum("taxValue"), 2) ?? "0",
                "openPloatTax"      => roundFigure($floorsTaxes->sum("openPloatTax") ?? "0"),
                "generalTax"        => roundFigure($floorsTaxes->sum("generalTax") ?? "0"),
                "roadTax"           => roundFigure($floorsTaxes->sum("roadTax") ?? "0"),
                "firefightingTax"   => roundFigure($floorsTaxes->sum("firefightingTax") ?? "0"),
                "educationTax"      => roundFigure($floorsTaxes->sum("educationTax") ?? "0"),
                "waterTax"          => roundFigure($floorsTaxes->sum("waterTax") ?? "0"),
                "cleanlinessTax"    => roundFigure($floorsTaxes->sum("cleanlinessTax") ?? "0"),
                "sewerageTax"       => roundFigure($floorsTaxes->sum("sewerageTax") ?? "0"),
                "treeTax"           => roundFigure($floorsTaxes->sum("treeTax") ?? "0"),
                "isCommercial"      => ($floorsTaxes->where("isCommercial", true)->count() > 1 ? true : false) ?? false,
                "stateEducationTaxPerc" => roundFigure($floorsTaxes->sum("stateEducationTaxPerc") ?? "0"),
                "stateEducationTax" => roundFigure($floorsTaxes->sum("stateEducationTax") ?? "0"),
                "professionalTaxPerc" => roundFigure($floorsTaxes->sum("professionalTaxPerc") ?? "0"),
                "professionalTax"   => roundFigure($floorsTaxes->sum("professionalTax") ?? "0"),
                "totalTax"          => roundFigure(
                    $floorsTaxes->sum("generalTax") + $floorsTaxes->sum("roadTax") + $floorsTaxes->sum("firefightingTax") +
                        $floorsTaxes->sum("educationTax") + $floorsTaxes->sum("waterTax") + $floorsTaxes->sum("cleanlinessTax") +
                        $floorsTaxes->sum("sewerageTax") + $floorsTaxes->sum("treeTax") + $floorsTaxes->sum("stateEducationTax") +
                        $floorsTaxes->sum("professionalTax") + $floorsTaxes->sum("openPloatTax")
                ),
            ];
            $residentFloor = $floorsTaxes->whereIN("usageType", [45, 25]);
            $nonResidentFloor = $floorsTaxes->whereNOTIN("usageType", [45, 25]);
            $data["usageTypeTax"] = [
                "new" => [
                    "residence" => [
                        "taxValue" => roundFigure($residentFloor->sum("taxValue") ?? 0),
                        "totalTax" => roundFigure(
                            $residentFloor->sum("generalTax") + $residentFloor->sum("roadTax") + $residentFloor->sum("firefightingTax") +
                                $residentFloor->sum("educationTax") + $residentFloor->sum("waterTax") +
                                $residentFloor->sum("cleanlinessTax") + $residentFloor->sum("sewerageTax") + $residentFloor->sum("treeTax") +
                                $residentFloor->sum("stateEducationTax") +  $residentFloor->sum("professionalTax") +
                                $residentFloor->sum("openPloatTax")
                        ),
                    ],
                    "nonResidence" => [
                        "taxValue" => roundFigure($nonResidentFloor->sum("taxValue") ?? 0),
                        "totalTax" => roundFigure(
                            $nonResidentFloor->sum("generalTax") + $nonResidentFloor->sum("roadTax") +
                                $nonResidentFloor->sum("firefightingTax") + $nonResidentFloor->sum("educationTax") + $nonResidentFloor->sum("waterTax") +
                                $nonResidentFloor->sum("cleanlinessTax") + $nonResidentFloor->sum("sewerageTax") + $nonResidentFloor->sum("treeTax") +
                                $nonResidentFloor->sum("stateEducationTax") +  $nonResidentFloor->sum("professionalTax") +
                                $nonResidentFloor->sum("openPloatTax")
                        ),
                    ]
                ],
                "old" => [
                    "residence" => [
                        "taxValue" => 0,
                        "totalTax" => 0,
                    ],
                    "nonResidence" => [
                        "taxValue" => 0,
                        "totalTax" => 0,
                    ]
                ],
            ];
            $data["usageTypeTaxBifur"] = [
                "residence" => [
                    "alv"               => roundFigure($residentFloor->sum("alv") ?? "0"),
                    "maintancePerc"     => roundFigure($residentFloor->sum("maintancePerc") ?? "0"),
                    "maintantance10Perc" => roundFigure($residentFloor->sum("maintantance10Perc") ?? "0"),
                    "valueAfterMaintance" => roundFigure($residentFloor->sum("valueAfterMaintance") ?? "0"),
                    "agingPerc"         => roundFigure($residentFloor->sum("agingPerc") ?? "0"),
                    "agingAmt"          => roundFigure($residentFloor->sum("agingAmt") ?? "0"),
                    "taxValue"          => roundFigure($residentFloor->sum("taxValue") ?? "0"),
                    "generalTax"        => roundFigure($residentFloor->sum("generalTax") ?? "0"),
                    "roadTax"           => roundFigure($residentFloor->sum("roadTax") ?? "0"),
                    "firefightingTax"   => roundFigure($residentFloor->sum("firefightingTax") ?? "0"),
                    "educationTax"      => roundFigure($residentFloor->sum("educationTax") ?? "0"),
                    "waterTax"          => roundFigure($residentFloor->sum("waterTax") ?? "0"),
                    "cleanlinessTax"    => roundFigure($residentFloor->sum("cleanlinessTax") ?? "0"),
                    "sewerageTax"       => roundFigure($residentFloor->sum("sewerageTax") ?? "0"),
                    "treeTax"           => roundFigure($residentFloor->sum("treeTax") ?? "0"),
                    "openPloatTax"      => roundFigure($residentFloor->sum("openPloatTax") ?? "0"),
                    "isCommercial"      => ($residentFloor->where("isCommercial", true)->count() > 1 ? true : false) ?? false,
                    "stateEducationTaxPerc" => roundFigure($residentFloor->sum("stateEducationTaxPerc") ?? "0"),
                    "stateEducationTax" => roundFigure($residentFloor->sum("stateEducationTax") ?? "0"),
                    "professionalTaxPerc" => roundFigure($residentFloor->sum("professionalTaxPerc") ?? "0"),
                    "professionalTax"   => roundFigure($residentFloor->sum("professionalTax") ?? "0"),
                    "totalTax"          => roundFigure(
                        $residentFloor->sum("generalTax") + $residentFloor->sum("roadTax") + $residentFloor->sum("firefightingTax") +
                            $residentFloor->sum("educationTax") + $residentFloor->sum("waterTax") + $residentFloor->sum("cleanlinessTax") +
                            $residentFloor->sum("sewerageTax") + $residentFloor->sum("treeTax") + $residentFloor->sum("stateEducationTax") +
                            $residentFloor->sum("professionalTax") + $residentFloor->sum("openPloatTax")
                    ),
                ],
                "nonResidence" => [
                    "alv"               => roundFigure($nonResidentFloor->sum("alv") ?? "0"),
                    "maintancePerc"     => roundFigure($nonResidentFloor->sum("maintancePerc") ?? "0"),
                    "maintantance10Perc" => roundFigure($nonResidentFloor->sum("maintantance10Perc") ?? "0"),
                    "valueAfterMaintance" => roundFigure($nonResidentFloor->sum("valueAfterMaintance") ?? "0"),
                    "agingPerc"         => roundFigure($nonResidentFloor->sum("agingPerc") ?? "0"),
                    "agingAmt"          => roundFigure($nonResidentFloor->sum("agingAmt") ?? "0"),
                    "taxValue"          => roundFigure($nonResidentFloor->sum("taxValue") ?? "0"),
                    "generalTax"        => roundFigure($nonResidentFloor->sum("generalTax") ?? "0"),
                    "roadTax"           => roundFigure($nonResidentFloor->sum("roadTax") ?? "0"),
                    "firefightingTax"   => roundFigure($nonResidentFloor->sum("firefightingTax") ?? "0"),
                    "educationTax"      => roundFigure($nonResidentFloor->sum("educationTax") ?? "0"),
                    "waterTax"          => roundFigure($nonResidentFloor->sum("waterTax") ?? "0"),
                    "cleanlinessTax"    => roundFigure($nonResidentFloor->sum("cleanlinessTax") ?? "0"),
                    "sewerageTax"       => roundFigure($nonResidentFloor->sum("sewerageTax") ?? "0"),
                    "treeTax"           => roundFigure($nonResidentFloor->sum("treeTax") ?? "0"),
                    "openPloatTax"      => roundFigure($nonResidentFloor->sum("openPloatTax") ?? "0"),
                    "isCommercial"      => ($nonResidentFloor->where("isCommercial", true)->count() > 1 ? true : false) ?? false,
                    "stateEducationTaxPerc" => roundFigure($nonResidentFloor->sum("stateEducationTaxPerc") ?? "0"),
                    "stateEducationTax" => roundFigure($nonResidentFloor->sum("stateEducationTax") ?? "0"),
                    "professionalTaxPerc" => roundFigure($nonResidentFloor->sum("professionalTaxPerc") ?? "0"),
                    "professionalTax"   => roundFigure($nonResidentFloor->sum("professionalTax") ?? "0"),
                    "totalTax"          => roundFigure(
                        $nonResidentFloor->sum("generalTax") + $nonResidentFloor->sum("roadTax") + $nonResidentFloor->sum("firefightingTax") +
                            $nonResidentFloor->sum("educationTax") + $nonResidentFloor->sum("waterTax") + $nonResidentFloor->sum("cleanlinessTax") +
                            $nonResidentFloor->sum("sewerageTax") + $nonResidentFloor->sum("treeTax") + $nonResidentFloor->sum("stateEducationTax") +
                            $nonResidentFloor->sum("professionalTax") + $nonResidentFloor->sum("openPloatTax")
                    ),
                ]
            ];
            return responseMsgs(true, "Demand Details", remove_null($data), "", "1.1", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "1.1", responseTime(), "POST", $request->deviceId);
        }
    }

    /**
     * | One Percent Penalty Calculation(13.1)
     */
    public function calcOnePercPenalty($item)
    {
        $penaltyRebateCalc = new PenaltyRebateCalculation;
        $onePercPenalty = $penaltyRebateCalc->calcOnePercPenalty($item->due_date);                  // Calculation One Percent Penalty
        $item['onePercPenalty'] = $onePercPenalty;
        $onePercPenaltyTax = ($item['balance'] * $onePercPenalty) / 100;
        $item['onePercPenaltyTax'] = roundFigure($onePercPenaltyTax);
        return $item;
    }

    /**
     * | Generate Order ID (14)
     * | @param req requested Data
     * | @var auth authenticated users credentials
     * | @var calculateSafById calculated SAF amounts and details by request SAF ID
     * | @var totalAmount filtered total amount from the collection
     * | Status-closed
     * | Query Costing-1.41s
     * | Rating - 5
     * */
    public function generateOrderId(Request $req)
    {
        $req->validate([
            'id' => 'required|integer',
        ]);

        try {
            $ipAddress = getClientIpAddress();
            $mPropRazorPayRequest = new PropRazorpayRequest();
            $postRazorPayPenaltyRebate = new PostRazorPayPenaltyRebate;
            $url            = Config::get('razorpay.PAYMENT_GATEWAY_URL');
            $endPoint       = Config::get('razorpay.PAYMENT_GATEWAY_END_POINT');
            $authUser      = authUser($req);
            $req->merge(['departmentId' => 1]);
            $safDetails = PropActiveSaf::findOrFail($req->id);
            if ($safDetails->payment_status == 1)
                throw new Exception("Payment already done");
            $calculateSafById = $this->calculateSafBySafId($req);
            $demands = $calculateSafById->original['data']['demand'];
            $details = $calculateSafById->original['data']['details'];
            $totalAmount = $demands['payableAmount'];
            $req->request->add(['workflowId' => $safDetails->workflow_id, 'ghostUserId' => 0, 'amount' => $totalAmount, 'auth' => $authUser]);
            DB::beginTransaction();

            $orderDetails = $this->saveGenerateOrderid($req);
            // $orderDetails = Http::withHeaders([])
            //     ->post($url . $endPoint, $req->toArray());

            // $orderDetails = collect(json_decode($orderDetails));

            // $status = isset($orderDetails['status']) ? $orderDetails['status'] : true;                                      //<---------- Generate Order ID Trait

            // if ($status == false)
            //     return $orderDetails;
            $demands = array_merge($demands->toArray(), [
                'orderId' => $orderDetails['orderId']
            ]);
            // Store Razor pay Request
            $razorPayRequest = [
                'order_id' => $demands['orderId'],
                'saf_id' => $req->id,
                'from_fyear' => $demands['dueFromFyear'],
                'from_qtr' => $demands['dueFromQtr'],
                'to_fyear' => $demands['dueToFyear'],
                'to_qtr' => $demands['dueToQtr'],
                'demand_amt' => $demands['totalTax'],
                'ulb_id' => $safDetails->ulb_id,
                'ip_address' => $ipAddress,
                'demand_list' => json_encode($details, true),
                'amount' => $totalAmount,
            ];
            $storedRazorPayReqs = $mPropRazorPayRequest->store($razorPayRequest);
            // Store Razor pay penalty Rebates
            $postRazorPayPenaltyRebate->_safId = $req->id;
            $postRazorPayPenaltyRebate->_razorPayRequestId = $storedRazorPayReqs['razorPayReqId'];
            $postRazorPayPenaltyRebate->postRazorPayPenaltyRebates($demands);
            DB::commit();
            return responseMsgs(true, "Order ID Generated", remove_null($orderDetails), "010114", "1.0", "1s", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Post Penalty Rebates (14.2)
     */
    public function postPenaltyRebates($calculateSafById, $safId, $tranId, $clusterId = null)
    {
        $mPaymentRebatePanelties = new PropPenaltyrebate();
        $calculatedRebates = collect($calculateSafById->original['data']['demand']['rebates']);
        $rebateList = array();
        $rebatePenalList = collect(Config::get('PropertyConstaint.REBATE_PENAL_MASTERS'));

        foreach ($calculatedRebates as $item) {
            $rebate = [
                'keyString' => $item['keyString'],
                'value' => $item['rebateAmount'],
                'isRebate' => true
            ];
            array_push($rebateList, $rebate);
        }
        $headNames = [
            [
                'keyString' => $rebatePenalList->where('id', 1)->first()['value'],
                'value' => $calculateSafById->original['data']['demand']['totalOnePercPenalty'],
                'isRebate' => false
            ],
            [
                'keyString' => $rebatePenalList->where('id', 5)->first()['value'],
                'value' => $calculateSafById->original['data']['demand']['lateAssessmentPenalty'],
                'isRebate' => false
            ]
        ];
        $headNames = array_merge($headNames, $rebateList);
        collect($headNames)->map(function ($headName) use ($mPaymentRebatePanelties, $safId, $tranId, $clusterId) {
            if ($headName['value'] > 0) {
                $reqs = [
                    'tran_id' => $tranId,
                    'saf_id' => $safId,
                    'cluster_id' => $clusterId,
                    'head_name' => $headName['keyString'],
                    'amount' => $headName['value'],
                    'is_rebate' => $headName['isRebate'],
                    'tran_date' => Carbon::now()->format('Y-m-d')
                ];

                $mPaymentRebatePanelties->postRebatePenalty($reqs);
            }
        });
    }

    /**
     * | SAF Payment
     * | @param req  
     * | @var workflowId SAF workflow ID
     * | Status-Closed
     * | Query Consting-374ms
     * | Rating-3
     */
    public function paymentSaf(ReqPayment $req)
    {
        try {
            $req->validate([
                'paymentId' => "required",
                "transactionNo" => "required"
            ]);
            // Variable Assignments
            $mPropTransactions = new PropTransaction();
            $mPropSafsDemands = new PropSafsDemand();
            $mPropRazorPayRequest = new PropRazorpayRequest();
            $mPropRazorpayPenalRebates = new PropRazorpayPenalrebate();
            $mPropPenaltyRebates = new PropPenaltyrebate();
            $mPropRazorpayResponse = new PropRazorpayResponse();
            $mPropTranDtl = new PropTranDtl();
            $previousHoldingDeactivation = new PreviousHoldingDeactivation;
            $postSafPropTaxes = new PostSafPropTaxes;

            $activeSaf = PropActiveSaf::findOrFail($req['id']);
            if ($activeSaf->payment_status == 1)
                throw new Exception("Payment Already Done");
            $userId = $req['userId'];
            $safId = $req['id'];
            $orderId = $req['orderId'];
            $paymentId = $req['paymentId'];

            if ($activeSaf->payment_status == 1)
                throw new Exception("Payment Already Done");
            $req['ulbId'] = $activeSaf->ulb_id;
            $razorPayReqs = new Request([
                'orderId' => $orderId,
                'key' => 'saf_id',
                'keyId' => $req['id']
            ]);
            $propRazorPayRequest = $mPropRazorPayRequest->getRazorPayRequests($razorPayReqs);
            if (collect($propRazorPayRequest)->isEmpty())
                throw new Exception("No Order Request Found");

            if (!$userId)
                $userId = 0;                                                        // For Ghost User in case of online payment

            $tranNo = $req['transactionNo'];
            // Derivative Assignments
            $demands = json_decode($propRazorPayRequest['demand_list']);
            $amount = $propRazorPayRequest['amount'];

            if (!$demands || collect($demands)->isEmpty())
                throw new Exception("Demand Not Available for Payment");
            // Property Transactions
            $activeSaf->payment_status = 1;             // Paid for Online
            DB::beginTransaction();
            $activeSaf->save();
            // Replication of Prop Transactions
            $tranReqs = [
                'saf_id' => $req['id'],
                'tran_date' => $this->_todayDate->format('Y-m-d'),
                'tran_no' => $tranNo,
                'payment_mode' => 'ONLINE',
                'amount' => $amount,
                'tran_date' => $this->_todayDate->format('Y-m-d'),
                'verify_date' => $this->_todayDate->format('Y-m-d'),
                'citizen_id' => $userId,
                'is_citizen' => true,
                'from_fyear' => $propRazorPayRequest->from_fyear,
                'to_fyear' => $propRazorPayRequest->to_fyear,
                'from_qtr' => $propRazorPayRequest->from_qtr,
                'to_qtr' => $propRazorPayRequest->to_qtr,
                'demand_amt' => $propRazorPayRequest->demand_amt,
                'ulb_id' => $propRazorPayRequest->ulb_id,
            ];

            $storedTransaction = $mPropTransactions->storeTrans($tranReqs);
            $tranId = $storedTransaction['id'];
            $razorpayPenalRebates = $mPropRazorpayPenalRebates->getPenalRebatesByReqId($propRazorPayRequest->id);
            // Replication of Razorpay Penalty Rebates to Prop Penal Rebates
            foreach ($razorpayPenalRebates as $item) {
                $propPenaltyRebateReqs = [
                    'tran_id' => $tranId,
                    'head_name' => $item['head_name'],
                    'amount' => $item['amount'],
                    'is_rebate' => $item['is_rebate'],
                    'tran_date' => $this->_todayDate->format('Y-m-d'),
                    'saf_id' => $safId,
                ];
                $mPropPenaltyRebates->postRebatePenalty($propPenaltyRebateReqs);
            }

            // Updation of Prop Razor pay Request
            $propRazorPayRequest->status = 1;
            $propRazorPayRequest->payment_id = $paymentId;
            $propRazorPayRequest->save();

            // Update Prop Razorpay Response
            $razorpayResponseReq = [
                'razorpay_request_id' => $propRazorPayRequest->id,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'saf_id' => $req['id'],
                'from_fyear' => $propRazorPayRequest->from_fyear,
                'from_qtr' => $propRazorPayRequest->from_qtr,
                'to_fyear' => $propRazorPayRequest->to_fyear,
                'to_qtr' => $propRazorPayRequest->to_qtr,
                'demand_amt' => $propRazorPayRequest->demand_amt,
                'ulb_id' => $activeSaf->ulb_id,
                'ip_address' => getClientIpAddress(),
            ];
            $mPropRazorpayResponse->store($razorpayResponseReq);

            foreach ($demands as $demand) {
                $demand = (array)$demand;
                unset($demand['ruleSet'], $demand['rwhPenalty'], $demand['onePercPenalty'], $demand['onePercPenaltyTax']);
                if (isset($demand['status']))
                    unset($demand['status']);
                $demand['paid_status'] = 1;
                $demand['saf_id'] = $safId;
                $demand['balance'] = 0;
                $storedSafDemand = $mPropSafsDemands->postDemands($demand);

                $tranReq = [
                    'tran_id' => $tranId,
                    'saf_demand_id' => $storedSafDemand['demandId'],
                    'total_demand' => $demand['amount'],
                    'ulb_id' => $req['ulbId'],
                ];
                $mPropTranDtl->store($tranReq);
            }
            $previousHoldingDeactivation->deactivateHoldingDemands($activeSaf);  // Deactivate Property Holding
            $this->sendToWorkflow($activeSaf);                                   // Send to Workflow(15.2)
            $demands = collect($demands)->toArray();
            $postSafPropTaxes->postSafTaxes($safId, $demands, $activeSaf->ulb_id);                  // Save Taxes
            DB::commit();
            return responseMsgs(true, "Payment Successfully Done",  ['TransactionNo' => $tranNo, 'tranId' => $tranId], "010115", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Offline Saf Payment  
     */
    public function offlinePaymentSaf(ReqPayment $req)
    {
        try {
            // Variable Assignments
            $offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
            $todayDate = Carbon::now();
            $idGeneration = new IdGeneration;
            $propTrans = new PropTransaction();
            $verifyPaymentModes = Config::get('payment-constants.VERIFICATION_PAYMENT_MODES');
            $propSaf = PropSaf::findOrFail($req['id']);
            $mPropSafsDemands = new PropSafsDemand();
            $mPropTranDtl = new PropTranDtl();
            $mPropPenaltyRebates = new PropPenaltyrebate();

            if ($propSaf->payment_status == 1)
                throw new Exception("Payment Already Done");

            $calculatePropTaxByPropId = new CalculatePropTaxByPropId($propSaf->property_id);

            $userId = authUser($req)->id;                                      // Authenticated user or Ghost User
            $tranBy = authUser($req)->user_type;

            $tranNo = $req['transactionNo'];
            // Derivative Assignments
            if (!$tranNo)
                $tranNo = $idGeneration->generateTransactionNo($propSaf->ulb_id);

            $calulatedTaxes = $calculatePropTaxByPropId->_GRID;
            $demands = $calulatedTaxes['fyearWiseTaxes'];
            $amount = $calulatedTaxes['payableAmt'];

            if (!$demands || collect($demands)->isEmpty())
                throw new Exception("Demand Not Available for Payment");

            $demandAmt = $calulatedTaxes['grandTaxes']['totalTax'];
            // Property Transactions
            $req->merge([
                'userId' => $userId,
                'is_citizen' => false,
                'todayDate' => $todayDate->format('Y-m-d'),
                'tranNo' => $tranNo,
                'workflowId' => $propSaf->workflow_id,
                'amount' => $amount,
                'tranBy' => $tranBy,
                'ulbId' => $propSaf->ulb_id,
                'demandAmt' => $demandAmt
            ]);
            $propSaf->payment_status = 1; // Paid for Online or Cash
            if (in_array($req['paymentMode'], $verifyPaymentModes)) {
                $req->merge([
                    'verifyStatus' => 2
                ]);
                $propSaf->payment_status = 2;         // Under Verification for Cheque, Cash, DD
            }
            DB::beginTransaction();
            $propTrans = $propTrans->postSafTransaction($req, $demands);
            // 🔴🔴🔴🔴🔴 💀💀 Demand insertion and tran details 🔴🔴🔴🔴🔴
            foreach ($demands as $demand) {
                $demand = (object)$demand;
                $demandReq = [
                    "saf_id" => $req['id'],
                    "property_id" => $propSaf->property_id,
                    "alv" => $demand->alv,
                    "maintanance_amt" => $demand->maintantance10Perc,
                    "aging_amt" => $demand->agingAmt,
                    "general_tax" => $demand->generalTax,
                    "road_tax" => $demand->roadTax,
                    "firefighting_tax" => $demand->firefightingTax,
                    "education_tax" => $demand->educationTax,
                    "water_tax" => $demand->waterTax,
                    "cleanliness_tax" => $demand->cleanlinessTax,
                    "sewarage_tax" => $demand->sewerageTax,
                    "tree_tax" => $demand->treeTax,
                    "professional_tax" => $demand->professionalTax,
                    "state_education_tax" => $demand->stateEducationTax,
                    "total_tax" => $demand->totalTax,
                    "balance" => $demand->totalTax,
                    "paid_status" => 1,
                    "fyear" => $demand->fyear,
                    "adjust_amt" => $demand->adjustAmt ?? 0,
                    "user_id" => $userId,
                    "ulb_id" => $propSaf->ulb_id,
                ];
                $insertedDemand = $mPropSafsDemands->create($demandReq);

                // ✅✅✅✅✅ Tran details insertion
                $tranDtlReq = [
                    "tran_id" => $propTrans['id'],
                    "saf_demand_id" => $insertedDemand->id,
                    "total_demand" => $insertedDemand->balance,
                    "ulb_id" => $insertedDemand->ulb_id,
                ];
                $mPropTranDtl->create($tranDtlReq);
            }

            // 🔴🔴🔴🔴🔴🔴🔴🔴🔴🔴 Pending Works
            // 🔴🔴🔴🔴🔴 💀💀 Type of Rebates and Penalty should be defined  🔴🔴🔴🔴🔴
            if ($calulatedTaxes['isRebateApplied']) {
                $penalRebateReq = [
                    'tran_id' => $propTrans['id'],
                    'head_name' => 'Rebate',
                    'amount' => $calulatedTaxes['rebateAmt'],
                    'is_rebate' => true,
                    'tran_date' => $todayDate->format('Y-m-d'),
                    'saf_id' => $propSaf->id,
                    'prop_id' => $propSaf->property_id,
                    'app_type' => 'SAF'
                ];
                $mPropPenaltyRebates->create($penalRebateReq);
            }

            if (in_array($req['paymentMode'], $offlinePaymentModes)) {
                $req->merge([
                    'chequeDate' => $req['chequeDate'],
                    'tranId' => $propTrans['id'],
                    "applicationNo" => $propSaf->saf_no,

                ]);
                $this->postOtherPaymentModes($req);
            }

            // Update SAF Payment Status
            $propSaf->save();
            DB::commit();
            return responseMsgs(true, "Payment Successfully Done",  ['TransactionNo' => $tranNo], "010115", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Post Other Payment Modes for Cheque,DD,Neft
     */
    public function postOtherPaymentModes($req, $clusterId = null)
    {
        $cash = Config::get('payment-constants.PAYMENT_MODE.3');
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $mTempTransaction = new TempTransaction();
        if ($req['paymentMode'] != $cash) {
            $mPropChequeDtl = new PropChequeDtl();
            $chequeReqs = [
                'user_id' => $req['userId'],
                'prop_id' => (isset($req["tranType"]) && $req["tranType"] == 'Property') ? ($req['id'] ?? null) : null,
                'saf_id' => $req['saf_id'] ?? null,
                'transaction_id' => $req['tranId'],
                'cheque_date' => $req['chequeDate'],
                'bank_name' => $req['bankName'],
                'branch_name' => $req['branchName'],
                'cheque_no' => $req['chequeNo'],
                'cluster_id' => $clusterId
            ];

            $mPropChequeDtl->postChequeDtl($chequeReqs);
        }

        $tranReqs = [
            'transaction_id' => $req['tranId'],
            'application_id' => $req['id'],
            'module_id' => $moduleId,
            'workflow_id' => $req['workflowId'],
            'transaction_no' => $req['tranNo'],
            'application_no' => $req['applicationNo'],
            'amount' => $req['amount'],
            'payment_mode' => $req['paymentMode'],
            'cheque_dd_no' => $req['chequeNo'],
            'bank_name' => $req['bankName'],
            'tran_date' => $req['todayDate'],
            'user_id' => $req['userId'],
            'ulb_id' => $req['ulbId'],
            'ward_no' => $req['wardNo'] ?? "",
            'cluster_id' => $clusterId
        ];
        $mTempTransaction->tempTransaction($tranReqs);
    }

    /**
     * | Send to Workflow Level after payment(15.2)
     */
    public function sendToWorkflow($activeSaf)
    {
        $mWorkflowTrack = new WorkflowTrack();
        $todayDate = $this->_todayDate;
        $refTable = Config::get('PropertyConstaint.SAF_REF_TABLE');
        $reqWorkflow = [
            'workflow_id' => $activeSaf->workflow_id,
            'ref_table_dot_id' => $refTable,
            'ref_table_id_value' => $activeSaf->id,
            'track_date' => $todayDate->format('Y-m-d h:i:s'),
            'module_id' => $this->_moduleId,
            'user_id' => null,
            'receiver_role_id' => $activeSaf->current_role,
            'ulb_id' => $activeSaf->ulb_id,
        ];
        $mWorkflowTrack->store($reqWorkflow);
    }

    /**
     * | Generate Payment Receipt(1)
     * | @param request req
     * | Status-Closed
     * | Query Cost-3  (Not Used)
     */
    public function generatePaymentReceipt(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['tranNo' => 'required|string']
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $propSafsDemand = new PropSafsDemand();
            $transaction = new PropTransaction();
            $propPenalties = new PropPenaltyrebate();
            $paymentReceiptHelper = new PaymentReceiptHelper;
            $mUlbMasters = new UlbMaster();

            $mTowards = Config::get('PropertyConstaint.SAF_TOWARDS');
            $mAccDescription = Config::get('PropertyConstaint.ACCOUNT_DESCRIPTION');
            $mDepartmentSection = Config::get('PropertyConstaint.DEPARTMENT_SECTION');
            $rebatePenalMstrs = collect(Config::get('PropertyConstaint.REBATE_PENAL_MASTERS'));

            $onePercKey = $rebatePenalMstrs->where('id', 1)->first()['value'];
            $specialRebateKey = $rebatePenalMstrs->where('id', 6)->first()['value'];
            $firstQtrKey = $rebatePenalMstrs->where('id', 2)->first()['value'];
            $lateAssessKey = $rebatePenalMstrs->where('id', 5)->first()['value'];
            $onlineRebate = $rebatePenalMstrs->where('id', 3)->first()['value'];

            $safTrans = $transaction->getPropByTranPropId($req->tranNo);
            if (collect($safTrans)->isEmpty())
                throw new Exception("Transaction Not Found");
            // Saf Payment
            $safId = $safTrans->saf_id;
            $reqSafId = new Request(['id' => $safId]);
            $activeSafDetails = $this->details($reqSafId);
            $calDemandAmt = $safTrans->demand_amt;
            $checkOtherTaxes =  $propSafsDemand->getFirstDemandBySafId($safId);

            $mDescriptions = $paymentReceiptHelper->readDescriptions($checkOtherTaxes);      // Check the Taxes are Only Holding or Not

            $fromFinYear = $safTrans->from_fyear;
            $fromFinQtr = $safTrans->from_qtr;
            $upToFinYear = $safTrans->to_fyear;
            $upToFinQtr = $safTrans->to_qtr;

            // Get Property Penalties against property transaction
            $penalRebates = $propPenalties->getPropPenalRebateByTranId($safTrans->id);
            $onePercPanalAmt = $penalRebates->where('head_name', $onePercKey)->first()['amount'] ?? "";
            $rebateAmt = $penalRebates->where('head_name', 'Rebate')->first()['amount'] ?? "";
            $specialRebateAmt = $penalRebates->where('head_name', $specialRebateKey)->first()['amount'] ?? "";
            $firstQtrRebate = $penalRebates->where('head_name', $firstQtrKey)->first()['amount'] ?? "";
            $lateAssessPenalty = $penalRebates->where('head_name', $lateAssessKey)->first()['amount'] ?? "";
            $jskOrOnlineRebate = collect($penalRebates)->where('head_name', $onlineRebate)->first()->amount ?? 0;

            $taxDetails = $paymentReceiptHelper->readPenalyPmtAmts($lateAssessPenalty, $onePercPanalAmt, $rebateAmt,  $specialRebateAmt, $firstQtrRebate, $safTrans->amount, $jskOrOnlineRebate);   // Get Holding Tax Dtls
            $totalRebatePenals = $paymentReceiptHelper->calculateTotalRebatePenals($taxDetails);
            // Get Ulb Details
            $ulbDetails = $mUlbMasters->getUlbDetails($activeSafDetails['ulb_id']);
            // Response Return Data
            $responseData = [
                "departmentSection" => $mDepartmentSection,
                "accountDescription" => $mAccDescription,
                "transactionDate" => Carbon::parse($safTrans->tran_date)->format('d-m-Y'),
                "transactionNo" => $safTrans->tran_no,
                "transactionTime" => $safTrans->created_at->format('H:i:s'),
                "applicationNo" => $activeSafDetails['saf_no'],
                "customerName" => $activeSafDetails['applicant_name'],
                "receiptWard" => $activeSafDetails['new_ward_no'],
                "address" => $activeSafDetails['prop_address'],
                "paidFrom" => $fromFinYear,
                "paidFromQtr" => $fromFinQtr,
                "paidUpto" => $upToFinYear,
                "paidUptoQtr" => $upToFinQtr,
                "paymentMode" => $safTrans->payment_mode,
                "bankName" => $safTrans->bank_name,
                "branchName" => $safTrans->branch_name,
                "chequeNo" => $safTrans->cheque_no,
                "chequeDate" => ymdToDmyDate($safTrans->cheque_date),
                "demandAmount" => roundFigure((float)$calDemandAmt),
                "taxDetails" => $taxDetails,
                "totalRebate" => $totalRebatePenals['totalRebate'],
                "totalPenalty" => $totalRebatePenals['totalPenalty'],
                "ulbId" => $activeSafDetails['ulb_id'],
                "oldWardNo" => $activeSafDetails['old_ward_no'],
                "newWardNo" => $activeSafDetails['new_ward_no'],
                "towards" => $mTowards,
                "description" => $mDescriptions,
                "totalPaidAmount" => $safTrans->amount,
                "paidAmtInWords" => getIndianCurrency($safTrans->amount),
                "tcName" => $safTrans->tc_name,
                "tcMobile" => $safTrans->tc_mobile,
                "ulbDetails" => $ulbDetails
            ];
            return responseMsgs(true, "Payment Receipt", remove_null($responseData), "010116", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "", "010116", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Get Property Transactions
     * | @param req requested parameters
     * | @var userId authenticated user id
     * | @var propTrans Property Transaction details of the Logged In User
     * | @return responseMsg
     * | Status-Closed
     * | Run time Complexity-346ms
     * | Rating - 3
     */
    public function getPropTransactions(Request $req)
    {
        try {
            $auth = authUser($req);
            $userId = $auth->id;
            if ($auth->user_type == 'Citizen')
                $propTrans = $this->Repository->getPropTransByCitizenUserId($userId, 'citizen_id');
            else
                $propTrans = $this->Repository->getPropTransByCitizenUserId($userId, 'user_id');

            return responseMsgs(true, "Transactions History", remove_null($propTrans), "010117", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010117", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Get Transactions by Property id or SAF id
     * | @param Request $req
     */
    public function getTransactionBySafPropId(Request $req)
    {
        try {
            $propTransaction = new PropTransaction();
            if ($req->safId)                                                // Get By SAF Id
                $propTrans = $propTransaction->getPropTransBySafId($req->safId);
            if ($req->propertyId)                                           // Get by Property Id
                $propTrans = $propTransaction->getPropTransByPropId($req->propertyId);

            return responseMsg(true, "Property Transactions", remove_null($propTrans));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get Property Details by Property Holding No
     * | Rating - 2
     * | Run Time Complexity-500 ms
     */
    public function getPropByHoldingNo(Request $req)
    {
        $req->validate(
            isset($req->holdingNo) ? ['holdingNo' => 'required'] : ['propertyId' => 'required|integer']
        );
        try {
            $mProperties = new PropProperty();
            $mPropFloors = new PropFloor();
            $mPropOwners = new PropOwner();
            $mPropSafs = new PropSaf();
            $safAllDtl = "";
            $propertyDtl = [];
            if ($req->holdingNo) {
                $properties = $mProperties->getPropDtls()
                    ->where('prop_properties.holding_no', $req->holdingNo)
                    ->first();
            }

            if ($req->propertyId) {
                $properties = $mProperties->getPropDtls()
                    ->where('prop_properties.id', $req->propertyId)
                    ->first();
            }

            if ($req->propertyId) {
                $safAllDtl = $mPropSafs->safDtl($req->propertyId);
            }
            if (!$properties) {
                throw new Exception("Property Not Found");
            }

            $floors = $mPropFloors->getPropFloors($properties->id);        // Model function to get Property Floors
            $owners = $mPropOwners->getOwnersByPropId($properties->id);    // Model function to get Property Owners

            if (!$properties->holding_type)
                $properties->holding_type = $this->propHoldingType($floors);

            $propertyDtl = collect($properties);
            $propertyDtl['floors'] = $floors;
            $propertyDtl['owners'] = $owners;
            $propertyDtl['Safs'] = $safAllDtl ?? [];

            return responseMsgs(true, "Property Details", remove_null($propertyDtl), "010112", "1.0", "", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Site Verification
     * | @param req requested parameter
     * | Status-Closed
     */
    public function siteVerification(ReqSiteVerification $req)
    {
        try {
            $taxCollectorRole = Config::get('PropertyConstaint.SAF-LABEL.TC');
            $ulbTaxCollectorRole = Config::get('PropertyConstaint.SAF-LABEL.UTC');
            $propertyType = collect(Config::get('PropertyConstaint.PROPERTY-TYPE'))->flip();
            $propActiveSaf = new PropActiveSaf();
            $verification = new PropSafVerification();
            $mWfRoleUsermap = new WfRoleusermap();
            $verificationDtl = new PropSafVerificationDtl();
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $vacantLand = $propertyType['VACANT LAND'];

            $safDtls = $propActiveSaf->getSafNo($req->safId);
            $workflowId = $safDtls->workflow_id;
            $roadWidthType = $this->readRoadWidthType($req->roadWidth);                                 // Read Road Width Type by Trait
            $getRoleReq = new Request([                                                                 // make request to get role id of the user
                'userId' => $userId,
                'workflowId' => $workflowId
            ]);

            $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfId($getRoleReq);
            if (!$readRoleDtls) {
                $readRoleDtls = $mWfRoleUsermap->getRoleByUserWfIdV2($getRoleReq);
            }
            $roleId = $readRoleDtls->wf_role_id;

            DB::beginTransaction();
            switch ($roleId) {
                case $taxCollectorRole:                                                                  // In Case of Agency TAX Collector
                    // $req->agencyVerification = true;
                    // $req->ulbVerification = false;
                    $req->merge([
                        "agencyVerification" => true,
                        "ulbVerification" => false
                    ]);
                    $msg = "Site Successfully Verified";
                    $propActiveSaf->verifyAgencyFieldStatus($req->safId);                                         // Enable Fields Verify Status
                    $this->checkBifurcationCondition($safDtls, $req, $vacantLand);
                    break;
                case $ulbTaxCollectorRole:                                                                // In Case of Ulb Tax Collector
                    // $req->agencyVerification = false;
                    // $req->ulbVerification = true;
                    $req->merge([
                        "agencyVerification" => false,
                        "ulbVerification" => true
                    ]);
                    $msg = "Site Successfully Verified";
                    $propActiveSaf->verifyFieldStatus($req->safId);                                         // Enable Fields Verify Status
                    break;

                default:
                    return responseMsg(false, "Forbidden Access", "");
            }

            $req->merge(['userId' => $userId, 'ulbId' => $ulbId]);
            // Verification Store
            $verificationId = $verification->store($req);                            // Model function to store verification and get the id
            // Verification Dtl Table Update                                         // For Tax Collector
            if ($req->propertyType != $vacantLand) {
                foreach ($req->floor as $floorDetail) {
                    $floorReq = [
                        'saf_id'               => $req->safId,
                        'verification_id'      => $verificationId,
                        'saf_floor_id'         => $floorDetail['floorId'] ?? null,
                        'floor_mstr_id'        => $floorDetail['floorNo'],
                        'usage_type_id'        => $floorDetail['useType'],
                        'construction_type_id' => $floorDetail['constructionType'],
                        'occupancy_type_id'    => $floorDetail['occupancyType'],
                        'builtup_area'         => $floorDetail['buildupArea'],
                        'date_from'            => $floorDetail['dateFrom'],
                        'date_to'              => $floorDetail['dateUpto'],
                        'rent_amount'          => $floorDetail['rentAmount'],
                        'rent_agreement_date'  => $floorDetail['rentAgreementDate'],
                        'carpet_area'          => null,
                        'user_id'              => $userId,
                        'ulb_id'               => $ulbId,
                    ];

                    $verificationDtl->store($floorReq);
                }
            }

            DB::commit();
            return responseMsgs(true, $msg, "", "010118", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Check Bifurcation Condition
     */
    public function checkBifurcationCondition($safDtls, $req, $vacantLand)
    {
        if ($safDtls->assessment_type == 'Bifurcation') {
            $mPropProperties = new PropProperty();
            $propertyId = $safDtls->previous_holding_id;
            $propertyDtls = $mPropProperties::find($propertyId);
            $propertyPlotArea = $propertyDtls->area_of_plot;
            $safPlotArea = $req->areaOfPlot;
            if ($safPlotArea > $propertyPlotArea)
                throw new Exception("You have excedeed the plot area. Please insert plot area below " . $propertyPlotArea);

            if ($req->propertyType != $vacantLand) {
                $requestFloors = $req->floor;
                $mPropFloors = new PropFLoor();

                foreach ($requestFloors as $requestFloor) {
                    if (isset($requestFloor['floorId']))
                        $safFloorDtls  = PropActiveSafsFloor::find($requestFloor['floorId']);

                    if (isset($safFloorDtls))
                        $propFloorDtls = $mPropFloors::find($safFloorDtls->prop_floor_details_id);

                    if (isset($propFloorDtls)) {
                        if ($requestFloor['buildupArea'] > $propFloorDtls->builtup_area)
                            throw new Exception("Verification Floor Area Excedded the Property Floor. Please enter area below " . $propFloorDtls->builtup_area . ". You have entered " . $requestFloor['buildupArea']);
                    }
                }
            }
        }
    }

    /**
     * | Geo Tagging Photo Uploads
     * | @param request req
     * | @var relativePath Geo Tagging Document Ralative path
     * | @var array images- request image path
     * | @var array directionTypes- request direction types
     */
    public function geoTagging(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "safId" => "required|numeric",
                "imagePath" => "required|array|min:3|max:4",
                "imagePath.*" => "required|image|mimes:jpeg,jpg,png,gif",
                "directionType" => "required|array|min:3|max:4",
                "directionType.*" => "required|In:Left,Right,Front,waterHarvesting",
                "longitude" => "required|array|min:3|max:4",
                "longitude.*" => "required|numeric",
                "latitude" => "required|array|min:3|max:4",
                "latitude.*" => "required|numeric"
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $mWfRoleUser = new WfRoleusermap();
            $mWfWorkflowRoleMaps = new WfWorkflowrolemap();
            $docUpload = new DocUpload;
            $geoTagging = new PropSafGeotagUpload();
            $relativePath = Config::get('PropertyConstaint.GEOTAGGING_RELATIVE_PATH');
            $safDtls = PropActiveSaf::findOrFail($req->safId);
            $images = $req->imagePath;
            $directionTypes = $req->directionType;
            $longitude = $req->longitude;
            $latitude = $req->latitude;

            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');
            $workflowIds = collect(collect($mWfWorkflowRoleMaps->getWfByRoleId($roleIds))->where("workflow_id", $safDtls->workflow_id))->values();
            $req->merge(["applicationId" => $safDtls->id, "action" => "forward", "receiverRoleId" => $workflowIds[0]["forward_role_id"] ?? "", "comment" => $req->comment ?? "Geo Taging Done"]);

            DB::beginTransaction();
            // $response = $this->postNextLevel($req);
            // if (!$response->original["status"]) {
            //     return $response;
            // }
            collect($images)->map(function ($image, $key) use ($directionTypes, $relativePath, $req, $docUpload, $longitude, $latitude, $geoTagging) {
                $refImageName = 'saf-geotagging-' . $directionTypes[$key] . '-' . $req->safId;
                $docExistReqs = new Request([
                    'safId' => $req->safId,
                    'directionType' => $directionTypes[$key]
                ]);
                $imageName = $docUpload->upload($refImageName, $image, $relativePath);         // <------- Get uploaded image name and move the image in folder
                $isDocExist = $geoTagging->getGeoTagBySafIdDirectionType($docExistReqs);

                $docReqs = [
                    'saf_id' => $req->safId,
                    'image_path' => $imageName,
                    'direction_type' => $directionTypes[$key],
                    'longitude' => $longitude[$key],
                    'latitude' => $latitude[$key],
                    'relative_path' => $relativePath,
                    'user_id' => authUser($req)->id
                ];
                if ($isDocExist)
                    $geoTagging->edit($isDocExist, $docReqs);
                else
                    $geoTagging->store($docReqs);
            });

            $safDtls->is_geo_tagged = true;
            $safDtls->save();

            DB::commit();
            return responseMsgs(true, "Geo Tagging Done Successfully", "", "010119", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ======================upload the Naksha===================
     * ||                   created By : sandeep Bara
     * ||                   Date       : 06-12-2023
     * ||                   
     */
    public function uploadNaksha(Request $req)
    {
        try {
            $extention = ($req->document) instanceof UploadedFile ? $req->document->getClientOriginalExtension() : "";
            $rules = [
                "applicationId" => "required|numeric",
                "document" => "required|mimes:pdf,jpeg,png,jpg|" . (strtolower($extention) == 'pdf' ? 'max:10240' : 'max:5120'),

            ];
            $validated = Validator::make(
                $req->all(),
                $rules
            );
            if ($validated->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validated->errors()
                ]);
            }
            $req->merge([
                "directionType" => "naksha",
                "docCategory" => "naksha",
                "docCode" => "Measurment Sheet",
                "safId" => $req->applicationId,
                "imagePath" => $req->document,
            ]);
            $relativePath = Config::get('PropertyConstaint.SAF_RELATIVE_PATH');
            $propModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs = array();
            $docUpload = new DocUpload;
            $mWfActiveDocument = new WfActiveDocument();
            $geoTagging = new PropSafGeotagUpload();
            $relativePath = Config::get('PropertyConstaint.GEOTAGGING_RELATIVE_PATH');
            $safDtls = PropActiveSaf::find($req->safId);
            if (!$safDtls) {
                $safDtls = PropSaf::find($req->safId);
            }
            if (!$safDtls) {
                throw new Exception("Data Not Found");
            }

            $images = $req->imagePath;
            $directionTypes = $req->directionType;
            $longitude = $req->longitude;
            $latitude = $req->latitude;

            $user = Auth()->user();
            $userId = $user->id ?? 0;
            $req->merge(["applicationId" => $safDtls->id, "action" => "forward", "receiverRoleId" => $workflowIds[0]["forward_role_id"] ?? "", "comment" => $req->comment ?? "Geo Taging Done"]);

            $refImageName = 'saf-geotagging-' . $directionTypes . '-' . $req->safId;
            $docExistReqs = new Request([
                'safId' => $req->safId,
                'directionType' => $directionTypes
            ]);
            $imageName = $docUpload->upload($refImageName, $images, $relativePath);         // <------- Get uploaded image name and move the image in folder
            $isDocExist = $geoTagging->getGeoTagBySafIdDirectionType($docExistReqs);

            $docReqs = [
                'saf_id' => $req->safId,
                'image_path' => $imageName,
                'direction_type' => $directionTypes,
                'longitude' => $longitude,
                'latitude' => $latitude,
                'relative_path' => $relativePath,
                'user_id' => $userId
            ];
            $metaReqs['module_id'] = $propModuleId;
            $metaReqs['active_id'] = $safDtls->id;
            $metaReqs['workflow_id'] = $safDtls->workflow_id;
            $metaReqs['ulb_id'] = $safDtls->ulb_id;
            $metaReqs['relative_path'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['doc_code'] = $req->docCode;
            $metaReqs['doc_category'] = $req->docCategory;
            $metaReqs['verify_status'] = 1;

            $documents = $mWfActiveDocument->isDocCategoryExists($safDtls->id, $safDtls->workflow_id, $propModuleId, $req->docCategory, $req->ownerId)
                ->orderBy("id", "DESC")
                ->first();

            DB::beginTransaction();
            $sms = "Naksha Uploaded Successfully";
            if ($documents) {
                $sms =  "Naksha Update Successfully";
                // $geoTagging->edit($isDocExist, $docReqs);
                $mWfActiveDocument->edit($documents, $metaReqs);
            } else {
                // $geoTagging->store($docReqs);
                $mWfActiveDocument->create($metaReqs);
            }
            DB::commit();
            return responseMsgs(true, $sms, "", "010119.1", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ===================== property no entery by da(lipik)==========================================
     * ||                   created By : sandeep Bara
     * ||                   Date       : 22-12-2023
     */

    public function chequePropertyNo(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
                "wardId"       => "required|digits_between:1,9223372036854775807",
                "propertyNo"   => "required|required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\.,-_ \s]+$/",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $saf = PropActiveSaf::find($request->applicationId);
            if (!$saf) {
                throw new Exception("Data Not Find");
            }
            $test = PropActiveSaf::where("ward_mstr_id", $request->wardId)
                ->where("property_no", $request->propertyNo)
                ->where("id", "<>", $request->applicationId)
                ->count("id");
            if (!$test) {
                $test = DB::table("prop_rejected_safs")
                    ->where("ward_mstr_id", $request->wardId)
                    ->where("property_no", $request->propertyNo)
                    ->count("id");
            }
            if (!$test && !in_array($saf->assessment_type, ['Reassessment', 'Mutation'])) {
                $test = DB::table("prop_properties")
                    ->where("ward_mstr_id", $request->wardId)
                    ->where("property_no", $request->propertyNo)
                    ->count("id");
            }
            if ($test) {
                throw new Exception("Already Exists");
            }
            return responseMsg(true, "Available", $request->propertyNo);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function updatePropertyNo(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
                "wardId"       => "required|digits_between:1,9223372036854775807",
                "propertyNo"   => "required|required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\.,-_ \s]+$/",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $saf = PropActiveSaf::find($request->applicationId);
            if (!$saf) {
                throw new Exception("Data Not Find");
            }
            $check = $this->chequePropertyNo($request);
            if (!$check->original["status"]) {
                return $check;
            }
            DB::beginTransaction();
            $saf->property_no = $request->propertyNo;
            $saf->save();
            DB::commit();
            return responseMsg(true, "property no assigned", "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get The Verification done by Agency Tc
     */
    public function getTcVerifications(Request $req)
    {
        $req->validate([
            'safId' => 'required|numeric'
        ]);
        try {
            $data = array();
            $safVerifications = new PropSafVerification();
            $safVerificationDtls = new PropSafVerificationDtl();
            $mSafGeoTag = new PropSafGeotagUpload();

            $data = $safVerifications->getVerificationsData($req->safId);                       // <--------- Prop Saf Verification Model Function to Get Prop Saf Verifications Data 
            if (collect($data)->isEmpty())
                throw new Exception("Tc Verification Not Done");

            $data = json_decode(json_encode($data), true);

            $verificationDtls = $safVerificationDtls->getFullVerificationDtls($data['id']);     // <----- Prop Saf Verification Model Function to Get Verification Floor Dtls
            $existingFloors = $verificationDtls->where('saf_floor_id', '!=', NULL);
            $newFloors = $verificationDtls->where('saf_floor_id', NULL);
            $data['newFloors'] = $newFloors->values();
            $data['existingFloors'] = $existingFloors->values();
            $geoTags = $mSafGeoTag->getGeoTags($req->safId);
            $data['geoTagging'] = $geoTags;
            return responseMsgs(true, "TC Verification Details", remove_null($data), "010120", "1.0", "258ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get the Demandable Amount By SAF ID
     * | @param $req
     * | Query Run time -272ms 
     * | Rating-2
     */
    public function getDemandBySafId(Request $req)
    {
        $req->validate([
            'id' => 'required|numeric'
        ]);
        try {
            $mWfRoleusermap = new WfRoleusermap();
            $mPropTransactions = new PropTransaction();
            $jskRole = Config::get('PropertyConstaint.JSK_ROLE');
            $tcRole = 5;
            $user = authUser($req);
            $userId = $user->id;
            $safDetails = $this->details($req);
            if ($safDetails['payment_status'] == 1) {       // Get Transaction no if the payment is done
                $transaction = $mPropTransactions->getLastTranByKeyId('saf_id', $req->id);
                $demand['tran_no'] = $transaction->tran_no;
            }
            $workflowId = $safDetails['workflow_id'];
            $mreqs = new Request([
                "workflowId" => $workflowId,
                "userId" => $userId
            ]);
            // $role = $mWfRoleusermap->getRoleByUserWfId($mreqs);
            $role = $mWfRoleusermap->getRoleByUserId($mreqs);

            if (isset($role) && in_array($role->wf_role_id, [$jskRole, $tcRole]))
                $demand['can_pay'] = true;
            else
                $demand['can_pay'] = false;

            $safTaxes = $this->calculateSafBySafId($req);
            if ($safTaxes->original['status'] == false)
                throw new Exception($safTaxes->original['message']);
            $req = $safDetails;
            $demand['basicDetails'] = [
                "ulb_id" => $req['ulb_id'],
                "saf_no" => $req['saf_no'],
                "prop_address" => $req['prop_address'],
                "is_mobile_tower" => $req['is_mobile_tower'],
                "is_hoarding_board" => $req['is_hoarding_board'],
                "is_petrol_pump" => $req['is_petrol_pump'],
                "is_water_harvesting" => $req['is_water_harvesting'],
                "zone_mstr_id" => $req['zone_mstr_id'],
                "holding_no" => $req['new_holding_no'] ?? $req['holding_no'],
                "old_ward_no" => $req['old_ward_no'],
                "new_ward_no" => $req['new_ward_no'],
                "property_type" => $req['property_type'],
                "holding_type" => $req['holding_type'],
                "doc_upload_status" => $req['doc_upload_status'],
                "ownership_type" => $req['ownership_type']
            ];
            $demand['amounts'] = $safTaxes->original['data']['demand'] ?? [];
            $demand['details'] = collect($safTaxes->original['data']['details'])->values();
            $demand['taxDetails'] = collect($safTaxes->original['data']['taxDetails']) ?? [];
            $demand['paymentStatus'] = $safDetails['payment_status'];
            $demand['applicationNo'] = $safDetails['saf_no'];
            return responseMsgs(true, "Demand Details", remove_null($demand), "", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), []);
        }
    }


    public function getUploadedGeoTagging(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            ['applicationId' => 'required']
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 200);
        }
        try {
            $safId = $request->applicationId;
            $data = array();
            $geoTagging = PropSafGeotagUpload::where("saf_id", $safId)->get()->map(function ($val) {
                $val->paths = Config::get('module-constants.DOC_URL') . "/" . $val->relative_path . "/" . $val->image_path;
                return $val;
            });
            $data["geoTagging"] = $geoTagging;
            return responseMsg(true, "GeoTaging Dtls", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # code by sandeep bara 
    # date 31-01-2023
    // ----------start------------
    public function getVerifications(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            ['verificationId' => 'required']
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 200);
        }

        try {
            $data = array();
            $verifications = PropSafVerification::select(
                'prop_saf_verifications.*',
                'p.property_type',
                'u.ward_name as ward_no',
                "users.name as user_name"
            )
                ->leftjoin('ref_prop_types as p', 'p.id', '=', 'prop_saf_verifications.prop_type_id')
                ->leftjoin('ulb_ward_masters as u', 'u.id', '=', 'prop_saf_verifications.ward_id')
                ->leftjoin('users', 'users.id', '=', 'prop_saf_verifications.user_id')
                ->where("prop_saf_verifications.id", $request->verificationId)
                ->first();
            if (!$verifications) {
                throw new Exception("verification Data NOt Found");
            }
            $saf = PropActiveSaf::select(
                'prop_active_safs.*',
                'p.property_type',
                'u.ward_name as ward_no',
                'u1.ward_name as new_ward_no',
                "ownership_types.ownership_type"
            )
                ->leftjoin('ref_prop_types as p', 'p.id', '=', 'prop_active_safs.prop_type_mstr_id')
                ->leftjoin('ulb_ward_masters as u', 'u.id', '=', 'prop_active_safs.ward_mstr_id')
                ->leftjoin('ulb_ward_masters as u1', 'u.id', '=', 'prop_active_safs.new_ward_mstr_id')
                ->leftjoin('ref_prop_ownership_types as ownership_types', 'ownership_types.id', '=', 'prop_active_safs.ownership_type_mstr_id')
                ->where("prop_active_safs.id", $verifications->saf_id)
                ->first();
            $tbl = "prop_active_safs";
            if (!$saf) {
                $saf = DB::table("prop_rejected_safs")
                    ->select(
                        'prop_rejected_safs.*',
                        'p.property_type',
                        'u.ward_name as ward_no',
                        'u1.ward_name as new_ward_no',
                        "ownership_types.ownership_type"
                    )
                    ->leftjoin('ref_prop_types as p', 'p.id', '=', 'prop_rejected_safs.prop_type_mstr_id')
                    ->leftjoin('ulb_ward_masters as u', 'u.id', '=', 'prop_rejected_safs.ward_mstr_id')
                    ->leftjoin('ref_prop_ownership_types as ownership_types', 'ownership_types.id', '=', 'prop_rejected_safs.ownership_type_mstr_id')
                    ->leftJoin('ulb_ward_masters as u1', 'u1.id', '=', 'prop_rejected_safs.new_ward_mstr_id')
                    ->where("prop_rejected_safs.id", $verifications->saf_id)
                    ->first();
                $tbl = "prop_rejected_safs";
            }
            if (!$saf) {
                $saf = DB::table("prop_safs")
                    ->select(
                        'prop_safs.*',
                        'p.property_type',
                        'u.ward_name as ward_no',
                        'u1.ward_name as new_ward_no',
                        "ownership_types.ownership_type"
                    )
                    ->leftjoin('ref_prop_types as p', 'p.id', '=', 'prop_safs.prop_type_mstr_id')
                    ->leftjoin('ulb_ward_masters as u', 'u.id', '=', 'prop_safs.ward_mstr_id')
                    ->leftjoin('ref_prop_ownership_types as ownership_types', 'ownership_types.id', '=', 'prop_safs.ownership_type_mstr_id')
                    ->leftJoin('ulb_ward_masters as u1', 'u1.id', '=', 'prop_safs.new_ward_mstr_id')
                    ->where("prop_safs.id", $verifications->saf_id)
                    ->first();
                $tbl = "prop_safs";
            }
            if (!$saf) {
                throw new Exception("Saf Data Not Found");
            }
            $floars = DB::table($tbl . "_floors")
                ->select($tbl . "_floors.*", 'f.floor_name', 'u.usage_type', 'o.occupancy_type', 'c.construction_type')
                ->leftjoin('ref_prop_floors as f', 'f.id', '=', $tbl . "_floors.floor_mstr_id")
                ->leftjoin('ref_prop_usage_types as u', 'u.id', '=', $tbl . "_floors.usage_type_mstr_id")
                ->leftjoin('ref_prop_occupancy_types as o', 'o.id', '=', $tbl . "_floors.occupancy_type_mstr_id")
                ->leftjoin('ref_prop_construction_types as c', 'c.id', '=', $tbl . "_floors.const_type_mstr_id")
                ->where($tbl . "_floors.saf_id", $saf->id)
                ->get();
            $verifications_detals = PropSafVerificationDtl::select('prop_saf_verification_dtls.*', 'f.floor_name', 'u.usage_type', 'o.occupancy_type', 'c.construction_type')
                ->leftjoin('ref_prop_floors as f', 'f.id', '=', 'prop_saf_verification_dtls.floor_mstr_id')
                ->leftjoin('ref_prop_usage_types as u', 'u.id', '=', 'prop_saf_verification_dtls.usage_type_id')
                ->leftjoin('ref_prop_occupancy_types as o', 'o.id', '=', 'prop_saf_verification_dtls.occupancy_type_id')
                ->leftjoin('ref_prop_construction_types as c', 'c.id', '=', 'prop_saf_verification_dtls.construction_type_id')
                ->where("verification_id", $verifications->id)
                ->get();

            $prop_compairs = [
                [
                    "key" => "Ward No",
                    "values" => $saf->ward_mstr_id == $verifications->ward_id,
                    "according_application" => $saf->ward_no,
                    "according_verification" => $verifications->ward_no,
                ],
                [
                    "key" => "Property Type",
                    "values" => $saf->prop_type_mstr_id == $verifications->prop_type_id,
                    "according_application" => $saf->property_type,
                    "according_verification" => $verifications->property_type,
                ],
                [
                    "key" => "Plot Area",
                    "values" => $saf->area_of_plot == $verifications->area_of_plot,
                    "according_application" => $saf->area_of_plot,
                    "according_verification" => $verifications->area_of_plot,
                ],
                // [
                //     "key" => "Road Type",
                //     "values" => $saf->road_type_mstr_id == $verifications->road_type_id,
                //     "according_application" => $saf->road_type,
                //     "according_verification" => $verifications->road_type,
                // ],
                [
                    "key" => "Mobile Tower",
                    "values" => $saf->is_mobile_tower == $verifications->has_mobile_tower,
                    "according_application" => $saf->is_mobile_tower ? "Yes" : "No",
                    "according_verification" => $verifications->has_mobile_tower ? "Yes" : "No",
                ],
                [
                    "key" => "Hoarding Board",
                    "values" => $saf->is_hoarding_board == $verifications->has_hoarding,
                    "according_application" => $saf->is_hoarding_board ? "Yes" : "No",
                    "according_verification" => $verifications->has_hoarding ? "Yes" : "No",
                ],
                [
                    "key" => "Petrol Pump",
                    "values" => $saf->is_petrol_pump == $verifications->is_petrol_pump,
                    "according_application" => $saf->is_petrol_pump ? "Yes" : "No",
                    "according_verification" => $verifications->is_petrol_pump ? "Yes" : "No",
                ],
                [
                    "key" => "Water Harvesting",
                    "values" => $saf->is_water_harvesting == $verifications->has_water_harvesting,
                    "according_application" => $saf->is_water_harvesting ? "Yes" : "No",
                    "according_verification" => $verifications->has_water_harvesting ? "Yes" : "No",
                ],
            ];
            $size = sizeOf($floars) >= sizeOf($verifications_detals) ? $floars : $verifications_detals;
            $keys = sizeOf($floars) >= sizeOf($verifications_detals) ? "floars" : "detals";
            $floors_compais = array();
            $floors_compais = $size->map(function ($val, $key) use ($floars, $verifications_detals, $keys) {
                if (sizeOf($floars) == sizeOf($verifications_detals)) {
                    $saf_data = collect(array_values(objToArray(($floars)->values())))->all();
                    $verification = collect(array_values(objToArray(($verifications_detals)->values())))->all();
                }
                if ($keys == "floars") {
                    // $saf_data=($floars->where("id",$val->id))->values();
                    // $verification=($verifications_detals->where("saf_floor_id",$val->id))->values();
                    $saf_data = collect(array_values(objToArray(($floars->where("id", $val->id))->values())))->all();
                    $verification = collect(array_values(objToArray(($verifications_detals->where("saf_floor_id", $val->id))->values())))->all();
                } else {
                    // $saf_data=($floars->where("id",$val->saf_floor_id))->values();
                    // $verification=($verifications_detals->where("id",$val->id))->values();
                    $saf_data = collect(array_values(objToArray(($floars->where("id", $val->saf_floor_id))->values())))->all();
                    $verification = collect(array_values(objToArray(($verifications_detals->where("id", $val->id))->values())))->all();
                }
                return [
                    "floar_name" => $val->floor_name,
                    "values" => [
                        [
                            "key" => "Usage Type",
                            "values" => ($saf_data[0]->usage_type_mstr_id ?? "") == ($verification[0]['usage_type_id'] ?? ""),
                            "according_application" => $saf_data[0]->usage_type ?? "",
                            "according_verification" => $verification[0]['usage_type'] ?? "",
                        ],
                        [
                            "key" => "Occupancy Type",
                            "values" => ($saf_data[0]->occupancy_type_mstr_id ?? "") == ($verification[0]['occupancy_type_id'] ?? ""),
                            "according_application" => $saf_data[0]->occupancy_type ?? "",
                            "according_verification" => $verification[0]['occupancy_type'] ?? "",
                        ],
                        [
                            "key" => "Construction Type",
                            "values" => ($saf_data[0]->const_type_mstr_id ?? "") == ($verification[0]['construction_type_id'] ?? ""),
                            "according_application" => $saf_data[0]->construction_type ?? "",
                            "according_verification" => $verification[0]['construction_type'] ?? "",
                        ],
                        [
                            "key" => "Built Up Area (in Sq. Ft.)",
                            "values" => ($saf_data[0]->builtup_area ?? "") == ($verification[0]['builtup_area'] ?? ""),
                            "according_application" => $saf_data[0]->builtup_area ?? "",
                            "according_verification" => $verification[0]['builtup_area'] ?? "",
                        ],
                        [
                            "key" => "Date of Completion",
                            "values" => ($saf_data[0]->date_from ?? "") == ($verification[0]['date_from'] ?? ""),
                            "according_application" => $saf_data[0]->date_from ?? "",
                            "according_verification" => $verification[0]['date_from'] ?? "",
                        ]
                    ]
                ];
            });
            $message = "ULB TC Verification Details";
            if ($verifications->agency_verification) {
                $PropertyDeactivate = new \App\Repository\Property\Concrete\PropertyDeactivate();
                $geoTagging = PropSafGeotagUpload::where("saf_id", $saf->id)->get()->map(function ($val) use ($PropertyDeactivate) {
                    $val->paths = Config::get('module-constants.DOC_URL') . "/" . $val->relative_path . "/" . $val->image_path;
                    // $val->paths = $PropertyDeactivate->readDocumentPath($val->relative_path . "/" . $val->image_path);
                    return $val;
                });
                $message = "TC Verification Details";
                $data["geoTagging"] = $geoTagging;
            } else {
                $owners = DB::table($tbl . "_owners")
                    ->select($tbl . "_owners.*")
                    ->where($tbl . "_owners.saf_id", $saf->id)
                    ->get();

                $safDetails = $saf;
                $safDetails = json_decode(json_encode($safDetails), true);
                $safDetails['floors'] = $floars;
                $safDetails['owners'] = $owners;

                #===============
                $req = $safDetails;
                $array = $this->generateSafRequest($req);
                $calculater = new \App\Http\Controllers\Property\Akola\AkolaCalculationController();
                $safTaxes = $calculater->calculate(new \App\Http\Requests\Property\Akola\ApplySafReq($array));
                #===============
                $safDetails2 = json_decode(json_encode($verifications), true);

                $safDetails2["ward_mstr_id"] = $safDetails2["ward_id"];
                $safDetails2["prop_type_mstr_id"] = $safDetails2["prop_type_id"];
                $safDetails2["land_occupation_date"] = $saf->land_occupation_date;
                $safDetails2["ownership_type_mstr_id"] = $saf->ownership_type_mstr_id;
                $safDetails2["zone_mstr_id"] = $saf->zone_mstr_id;
                $safDetails2["road_type_mstr_id"] = $saf->road_type_mstr_id;
                $safDetails2["road_width"] = $saf->road_width;
                $safDetails2["is_gb_saf"] = $saf->is_gb_saf;
                $safDetails2["is_trust"] = $saf->is_trust;
                $safDetails2["trust_type"] = $saf->trust_type;


                $safDetails2["is_mobile_tower"] = $safDetails2["has_mobile_tower"];
                $safDetails2["tower_area"] = $safDetails2["tower_area"];
                $safDetails2["tower_installation_date"] = $safDetails2["tower_installation_date"];

                $safDetails2["is_hoarding_board"] = $safDetails2["has_hoarding"];
                $safDetails2["hoarding_area"] = $safDetails2["hoarding_area"];
                $safDetails2["hoarding_installation_date"] = $safDetails2["hoarding_installation_date"];

                $safDetails2["is_petrol_pump"] = $safDetails2["is_petrol_pump"];
                $safDetails2["under_ground_area"] = $safDetails2["underground_area"];
                $safDetails2["petrol_pump_completion_date"] = $safDetails2["petrol_pump_completion_date"];

                $safDetails2["is_water_harvesting"] = $safDetails2["has_water_harvesting"];

                $safDetails2['floors'] = $verifications_detals;
                $safDetails2['floors'] = $safDetails2['floors']->map(function ($val) {
                    $val->usage_type_mstr_id    = $val->usage_type_id;
                    $val->const_type_mstr_id    = $val->construction_type_id;
                    $val->occupancy_type_mstr_id = $val->occupancy_type_id;
                    $val->builtup_area          = $val->builtup_area;
                    $val->date_from             = $val->date_from;
                    $val->date_upto             = $val->date_to;
                    return $val;
                });


                $safDetails2['owners'] = $owners;

                #======================================

                $array2 = $this->generateSafRequest($safDetails2);
                // dd($array);
                $request2 = new Request($array2);
                $calculater2 = new \App\Http\Controllers\Property\Akola\AkolaCalculationController();
                $safTaxes2 = $calculater2->calculate(new \App\Http\Requests\Property\Akola\ApplySafReq($array2));
                // $taxCalculator = new \App\BLL\Property\Akola\TaxCalculator($request2);
                // $taxCalculator->calculateTax();
                // $safTaxes2 = $taxCalculator->_GRID;
                // dd($safTaxes,$array);
                if (!$safTaxes->original["status"]) {
                    throw new Exception($safTaxes->original["message"]);
                }
                if (!$safTaxes2->original["status"]) {
                    throw new Exception($safTaxes2->original["message"]);
                }
                $safTaxes3 = $this->reviewTaxCalculationV2($safTaxes);
                $safTaxes4 = $this->reviewTaxCalculationV2($safTaxes2);
                // dd(json_decode(json_encode($safTaxes), true),json_decode(json_encode($safTaxes2), true));
                $compairTax = $this->reviewTaxCalculationComV2($safTaxes, $safTaxes2);

                $safTaxes2 = json_decode(json_encode($safTaxes4), true);
                $safTaxes = json_decode(json_encode($safTaxes3), true);
                $compairTax = json_decode(json_encode($compairTax), true);

                $data["Tax"]["according_application"] = $safTaxes["original"]["data"];
                $data["Tax"]["according_verification"] = $safTaxes2["original"]["data"];
                $data["Tax"]["compairTax"] = $compairTax["original"]["data"];

                #======================================
            }
            $data["saf_details"] = $saf;
            $data["employee_details"] = ["user_name" => $verifications->user_name, "date" => ymdToDmyDate($verifications->created_at)];
            $data["property_comparison"] = $prop_compairs;
            $data["floor_comparison"] = $floors_compais;
            return responseMsgs(true, $message, remove_null($data), "010121", "1.0", "258ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function getSafVerificationList(Request $request)
    {
        $request->validate([
            'applicationId' => 'required|digits_between:1,9223372036854775807',
        ]);
        try {
            $data = array();
            $verifications = PropSafVerification::select(
                'id',
                // DB::raw("(created_at,'dd-mm-YYYY') as created_at"),
                'agency_verification',
                "ulb_verification",
                "created_at",
            )
                // ->where("prop_saf_verifications.status", 1)     #_removed beacuse not showing data after approval
                ->where("prop_saf_verifications.saf_id", $request->applicationId)
                ->get();

            $data = $verifications->map(function ($val) {
                $val->veryfied_by = $val->agency_verification ? "AGENCY TC" : "ULB TC";
                return $val;
            });
            return responseMsgs(true, "Data Fetched", remove_null($data), "010122", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    private function reviewTaxCalculation(object $response)
    {
        try {
            $finalResponse['demand'] = $response->original['data']['demand'];
            $reviewDetails = collect($response->original['data']['details'])->groupBy(['ruleSet', 'mFloorNo', 'mUsageType']);
            $finalTaxReview = collect();
            $review = collect($reviewDetails)->map(function ($reviewDetail) use ($finalTaxReview) {
                $table = collect($reviewDetail)->map(function ($floors) use ($finalTaxReview) {
                    $usageType = collect($floors)->map(function ($floor) use ($finalTaxReview) {
                        $first = $floor->first();
                        $response = $first->only([
                            'mFloorNo',
                            'mUsageType',
                            'arv',
                            'buildupArea',
                            'dateFrom',
                            'quarterYear',
                            'qtr',
                            'ruleSet',
                            'holdingTax',
                            'waterTax',
                            'latrineTax',
                            'educationTax',
                            'healthTax',
                            'totalTax',
                            'rwhPenalty',
                            'rentalValue',
                            'carpetArea',
                            'calculationPercFactor',
                            'multiFactor',
                            'rentalRate',
                            'occupancyFactor',
                            'circleRate',
                            'taxPerc',
                            'calculationFactor',
                            'matrixFactor'
                        ]);
                        $finalTaxReview->push($response);
                        return $response;
                    });
                    return $usageType;
                });
                return $table;
            });
            $ruleSetCollections = collect($finalTaxReview)->groupBy(['ruleSet']);
            $reviewCalculation = collect($ruleSetCollections)->map(function ($collection) {
                return collect($collection)->pipe(function ($collect) {
                    $quaters['floors'] = $collect;
                    $groupByFloors = $collect->groupBy(['quarterYear', 'qtr']);
                    $quaterlyTaxes = collect();
                    collect($groupByFloors)->map(function ($qtrYear) use ($quaterlyTaxes) {
                        return collect($qtrYear)->map(function ($qtr, $key) use ($quaterlyTaxes) {
                            return collect($qtr)->pipe(function ($floors) use ($quaterlyTaxes, $key) {
                                $taxes = [
                                    'key' => $key,
                                    'effectingFrom' => $floors->first()['dateFrom'],
                                    'qtr' => $floors->first()['qtr'],
                                    'arv' => roundFigure($floors->sum('arv')),
                                    'holdingTax' => roundFigure($floors->sum('holdingTax')),
                                    'waterTax' => roundFigure($floors->sum('waterTax')),
                                    'latrineTax' => roundFigure($floors->sum('latrineTax')),
                                    'educationTax' => roundFigure($floors->sum('educationTax')),
                                    'healthTax' => roundFigure($floors->sum('healthTax')),
                                    'rwhPenalty' => roundFigure($floors->sum('rwhPenalty')),
                                    'quaterlyTax' => roundFigure($floors->sum('totalTax')),
                                ];
                                $quaterlyTaxes->push($taxes);
                            });
                        });
                    });
                    $quaters['totalQtrTaxes'] = $quaterlyTaxes;
                    return $quaters;
                });
            });
            $finalResponse['details'] = $reviewCalculation;
            return responseMsg(true, "", $finalResponse);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    private function reviewTaxCalculationCom(object $response, object $response2)
    {

        try {
            $finalResponse['demand'] = $response->original['data']['demand'];
            $finalResponse2['demand'] = $response2->original['data']['demand'];
            // dd( $response->original['data'],  $response2->original['data']);
            $reviewDetails = collect($response->original['data']['details'])->groupBy(['ruleSet', 'mFloorNo', 'mUsageType']);
            $reviewDetails2 = collect($response2->original['data']['details'])->groupBy(['ruleSet', 'mFloorNo', 'mUsageType']);

            $finalTaxReview = collect();
            $finalTaxReview2 = collect();

            $review = collect($reviewDetails)->map(function ($reviewDetail) use ($finalTaxReview) {

                $table = collect($reviewDetail)->map(function ($floors) use ($finalTaxReview) {

                    $usageType = collect($floors)->map(function ($floor) use ($finalTaxReview) {

                        $first = $floor->first();

                        $response = $first->only([
                            'mFloorNo',
                            'mUsageType',
                            'arv',
                            'buildupArea',
                            'dateFrom',
                            'quarterYear',
                            'qtr',
                            'ruleSet',
                            'holdingTax',
                            'waterTax',
                            'latrineTax',
                            'educationTax',
                            'healthTax',
                            'totalTax',
                            'rwhPenalty',
                            'rentalValue',
                            'carpetArea',
                            'calculationPercFactor',
                            'multiFactor',
                            'rentalRate',
                            'occupancyFactor',
                            'circleRate',
                            'taxPerc',
                            'calculationFactor',
                            'matrixFactor'
                        ]);
                        $finalTaxReview->push($response);
                        return $response;
                    });
                    return $usageType;
                });
                return $table;
            });

            $review2 = collect($reviewDetails2)->map(function ($reviewDetail) use ($finalTaxReview2) {

                $table = collect($reviewDetail)->map(function ($floors) use ($finalTaxReview2) {

                    $usageType = collect($floors)->map(function ($floor) use ($finalTaxReview2) {

                        $first = $floor->first();

                        $response = $first->only([
                            'mFloorNo',
                            'mUsageType',
                            'arv',
                            'buildupArea',
                            'dateFrom',
                            'quarterYear',
                            'qtr',
                            'ruleSet',
                            'holdingTax',
                            'waterTax',
                            'latrineTax',
                            'educationTax',
                            'healthTax',
                            'totalTax',
                            'rwhPenalty',
                            'rentalValue',
                            'carpetArea',
                            'calculationPercFactor',
                            'multiFactor',
                            'rentalRate',
                            'occupancyFactor',
                            'circleRate',
                            'taxPerc',
                            'calculationFactor',
                            'matrixFactor'
                        ]);
                        $finalTaxReview2->push($response);
                        return $response;
                    });
                    return $usageType;
                });
                return $table;
            });

            $ruleSetCollections = collect($finalTaxReview)->groupBy(['ruleSet']);
            $ruleSetCollections2 = collect($finalTaxReview2)->groupBy(['ruleSet']);

            $reviewCalculation = collect($ruleSetCollections2)->map(function ($collection, $key) use ($ruleSetCollections) {
                $collection2 = collect($ruleSetCollections[$key] ?? []);
                // dd($key);
                return collect($collection)->pipe(function ($collect) use ($collection2) {

                    $quaters['floors'] = $collect;
                    $quaters2['floors'] = $collection2;

                    $groupByFloors = $collect->groupBy(['quarterYear', 'qtr']);
                    $groupByFloors2 = $collection2->groupBy(['quarterYear', 'qtr']) ?? [];

                    $quaterlyTaxes = collect();

                    collect($groupByFloors)->map(function ($qtrYear, $key1) use ($quaterlyTaxes, $groupByFloors2) {

                        $qtrYear2 = collect($groupByFloors2[$key1] ?? []);

                        return collect($qtrYear)->map(function ($qtr, $key) use ($quaterlyTaxes, $qtrYear2) {

                            $qtr2 = $qtrYear2[$key] ?? collect([]);

                            return collect($qtr)->pipe(function ($floors) use ($quaterlyTaxes, $key, $qtr2) {

                                $taxes = [
                                    'key' => $key,
                                    'effectingFrom' => $floors->first()['dateFrom'],
                                    'qtr' => $floors->first()['qtr'],
                                    'arv' => roundFigure(($floors->sum('arv')) - ($qtr2->sum('arv'))),
                                    'holdingTax' => roundFigure(($floors->sum('holdingTax')) - ($qtr2->sum('holdingTax'))),
                                    'waterTax' => roundFigure(($floors->sum('waterTax')) - ($qtr2->sum('waterTax'))),
                                    'latrineTax' => roundFigure(($floors->sum('latrineTax')) - ($qtr2->sum('latrineTax'))),
                                    'educationTax' => roundFigure(($floors->sum('educationTax')) - ($qtr2->sum('educationTax'))),
                                    'healthTax' => roundFigure(($floors->sum('healthTax')) - ($qtr2->sum('healthTax'))),
                                    'rwhPenalty' => roundFigure(($floors->sum('rwhPenalty')) - ($qtr2->sum('rwhPenalty'))),
                                    'quaterlyTax' => roundFigure(($floors->sum('totalTax')) - ($qtr2->sum('totalTax'))),
                                ];
                                $quaterlyTaxes->push($taxes);
                            });
                        });
                    });

                    $quaters['totalQtrTaxes'] = $quaterlyTaxes;
                    return $quaters;
                });
            });
            $finalResponse2['details'] = $reviewCalculation;
            return responseMsg(true, "", $finalResponse2);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    #========= for Akola Tax Compair=============

    private function reviewTaxCalculationV2(object $response)
    {
        try {
            $finalResponse['demand'] = $response->original['data']['grandTaxes'];
            $reviewDetails = collect($response->original['data']['fyearWiseTaxes'])->groupBy(['fyear']);
            $finalTaxReview = collect();
            $review = collect($reviewDetails)->map(function ($reviewDetail) use ($finalTaxReview) {
                $table = collect($reviewDetail)->map(function ($floors) use ($finalTaxReview) {
                    $first = collect($floors);
                    $response = $first->only([
                        'alv',
                        'maintancePerc',
                        'maintantance10Perc',
                        'valueAfterMaintance',
                        'agingPerc',
                        'agingAmt',
                        'taxValue',
                        'generalTax',
                        'roadTax',
                        'firefightingTax',
                        'educationTax',
                        'waterTax',
                        'cleanlinessTax',
                        'sewerageTax',
                        'treeTax',
                        'stateEducationTaxPerc',
                        'stateEducationTax',
                        'professionalTaxPerc',
                        'professionalTax',
                        'totalTax',
                        'fyear',
                    ]);
                    $finalTaxReview->push($response);
                    return $response;
                    // });
                    // return $usageType;
                });
                return $table;
            });
            $ruleSetCollections = collect($finalTaxReview)->groupBy(['fyear']);
            $reviewCalculation = collect($ruleSetCollections)->map(function ($collection) {
                $first = $collection->first();
                return collect([
                    'key' => $first['fyear'],
                    'alv'               => roundFigure($collection->sum('alv')),
                    'maintancePerc'     => roundFigure($collection->sum('maintancePerc')),
                    'maintantance10Perc' => roundFigure($collection->sum('maintantance10Perc')),
                    'valueAfterMaintance' => roundFigure($collection->sum('valueAfterMaintance')),
                    'agingPerc'         => roundFigure($collection->sum('agingPerc')),
                    'agingAmt'          => roundFigure($collection->sum('agingAmt')),
                    'taxValue'          => roundFigure($collection->sum('taxValue')),
                    'generalTax'        => roundFigure($collection->sum('generalTax')),
                    'roadTax'           => roundFigure($collection->sum('roadTax')),
                    'firefightingTax'   => roundFigure($collection->sum('firefightingTax')),
                    'educationTax'      => roundFigure($collection->sum('educationTax')),
                    'waterTax'          => roundFigure($collection->sum('waterTax')),
                    'cleanlinessTax'    => roundFigure($collection->sum('cleanlinessTax')),
                    'sewerageTax'       => roundFigure($collection->sum('sewerageTax')),
                    'treeTax'           => roundFigure($collection->sum('treeTax')),
                    'stateEducationTaxPerc' => roundFigure($collection->sum('stateEducationTaxPerc')),
                    'stateEducationTax' => roundFigure($collection->sum('stateEducationTax')),
                    'professionalTaxPerc' => roundFigure($collection->sum('professionalTaxPerc')),
                    'professionalTax'   => roundFigure($collection->sum('professionalTax')),
                    'totalTax'          => roundFigure($collection->sum('totalTax')),
                ]);
            });
            $finalResponse['details'] = $reviewCalculation->values();
            return responseMsg(true, "", $finalResponse);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    private function reviewTaxCalculationComV2(object $response, object $response2)
    {

        try {
            $finalResponse['demand'] = $response->original['data']['grandTaxes'];
            $finalResponse2['demand'] = $response2->original['data']['grandTaxes'];
            $reviewDetails = collect($response->original['data']['fyearWiseTaxes'])->groupBy(['fyear']);
            $reviewDetails2 = collect($response2->original['data']['fyearWiseTaxes'])->groupBy(['fyear']);

            $finalTaxReview = collect();
            $finalTaxReview2 = collect();

            $review = collect($reviewDetails)->map(function ($reviewDetail) use ($finalTaxReview) {
                $table = collect($reviewDetail)->map(function ($floors) use ($finalTaxReview) {
                    $first = collect($floors);
                    $response = $first->only([
                        'alv',
                        'maintancePerc',
                        'maintantance10Perc',
                        'valueAfterMaintance',
                        'agingPerc',
                        'agingAmt',
                        'taxValue',
                        'generalTax',
                        'roadTax',
                        'firefightingTax',
                        'educationTax',
                        'waterTax',
                        'cleanlinessTax',
                        'sewerageTax',
                        'treeTax',
                        'stateEducationTaxPerc',
                        'stateEducationTax',
                        'professionalTaxPerc',
                        'professionalTax',
                        'totalTax',
                        'fyear',
                    ]);
                    $finalTaxReview->push($response);
                    return $response;
                });
                return $table;
            });

            $review2 = collect($reviewDetails2)->map(function ($reviewDetail) use ($finalTaxReview2) {
                $table = collect($reviewDetail)->map(function ($floors) use ($finalTaxReview2) {
                    $first = collect($floors);
                    $response = $first->only([
                        'alv',
                        'maintancePerc',
                        'maintantance10Perc',
                        'valueAfterMaintance',
                        'agingPerc',
                        'agingAmt',
                        'taxValue',
                        'generalTax',
                        'roadTax',
                        'firefightingTax',
                        'educationTax',
                        'waterTax',
                        'cleanlinessTax',
                        'sewerageTax',
                        'treeTax',
                        'stateEducationTaxPerc',
                        'stateEducationTax',
                        'professionalTaxPerc',
                        'professionalTax',
                        'totalTax',
                        'fyear',
                    ]);
                    $finalTaxReview2->push($response);
                    return $response;
                });
                return $table;
            });
            $safDemand = $finalResponse['demand'];
            $demand = collect($finalResponse2['demand'])->map(function ($val, $key) use ($safDemand) {
                return (roundFigure($val - $safDemand[$key]));
            });
            $ruleSetCollections = collect($finalTaxReview)->groupBy(['fyear']);
            $ruleSetCollections2 = collect($finalTaxReview2)->groupBy(['fyear']);

            $reviewCalculation = collect($ruleSetCollections2)->map(function ($collection, $key) use ($ruleSetCollections) {
                $first = $collection->first();
                $tax2 = $ruleSetCollections[$key];
                return collect([
                    'key' => $first['fyear'],
                    'alv'               => roundFigure($collection->sum('alv') - $tax2->sum('alv')),
                    'maintancePerc'     => roundFigure($collection->sum('maintancePerc') - $tax2->sum('maintancePerc')),
                    'maintantance10Perc' => roundFigure($collection->sum('maintantance10Perc') - $tax2->sum('maintantance10Perc')),
                    'valueAfterMaintance' => roundFigure($collection->sum('valueAfterMaintance') - $tax2->sum('valueAfterMaintance')),
                    'agingPerc'         => roundFigure($collection->sum('agingPerc') - $tax2->sum('agingPerc')),
                    'agingAmt'          => roundFigure($collection->sum('agingAmt') - $tax2->sum('agingAmt')),
                    'taxValue'          => roundFigure($collection->sum('taxValue') - $tax2->sum('taxValue')),
                    'generalTax'        => roundFigure($collection->sum('generalTax') - $tax2->sum('generalTax')),
                    'roadTax'           => roundFigure($collection->sum('roadTax') - $tax2->sum('roadTax')),
                    'firefightingTax'   => roundFigure($collection->sum('firefightingTax') - $tax2->sum('firefightingTax')),
                    'educationTax'      => roundFigure($collection->sum('educationTax') - $tax2->sum('educationTax')),
                    'waterTax'          => roundFigure($collection->sum('waterTax') - $tax2->sum('waterTax')),
                    'cleanlinessTax'    => roundFigure($collection->sum('cleanlinessTax'))  - roundFigure($tax2->sum('cleanlinessTax')),
                    'sewerageTax'       => roundFigure($collection->sum('sewerageTax') - $tax2->sum('sewerageTax')),
                    'treeTax'           => roundFigure($collection->sum('treeTax'))  - roundFigure($tax2->sum('treeTax')),
                    'stateEducationTaxPerc' => roundFigure($collection->sum('stateEducationTaxPerc') - $tax2->sum('stateEducationTaxPerc')),
                    'stateEducationTax' => roundFigure($collection->sum('stateEducationTax') - $tax2->sum('stateEducationTax')),
                    'professionalTaxPerc' => roundFigure($collection->sum('professionalTaxPerc') - $tax2->sum('professionalTaxPerc')),
                    'professionalTax'   => roundFigure($collection->sum('professionalTax') - $tax2->sum('professionalTax')),
                    'totalTax'          => roundFigure($collection->sum('totalTax') - $tax2->sum('totalTax')),
                ]);
            });
            $finalResponse2['demand'] = $demand;
            $finalResponse2['details'] = $reviewCalculation->values();
            return responseMsg(true, "", $finalResponse2);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    // ---------end----------------

    /**
     * ========== Akola Proccess Fee Payment ==============
     *            Created by: Sandeep Bara
     *            Date      : 30-11-2023
     *  
     */

    public function getPendingProccessFeePtmLs(Request $request)
    {
        try {

            $perPage = $request->perPage ? $request->perPage : 10;
            $select = [
                "p.payment_status",
                "p.doc_upload_status",
                "p.saf_no",
                "p.id",
                "p.workflow_id",
                "p.ward_mstr_id",
                "p.is_agency_verified",
                "p.is_field_verified",
                "p.is_geo_tagged",
                "ward.ward_name as ward_no",
                "p.prop_type_mstr_id",
                "p.holding_no",
                "p.appartment_name",
                "o.owner_name as owner_name",
                "o.mobile_no as mobile_no",
                "rpt.property_type",
                "p.assessment_type as assessment",
                DB::raw("TO_CHAR(p.application_date, 'DD-MM-YYYY') as apply_date"),
                "p.parked",
                "p.prop_address",
                "p.applicant_name",
                "p.citizen_id"
            ];
            $data = DB::table("prop_safs as p")
                ->select($select)
                ->join("ref_prop_types as rpt", "rpt.id", "p.prop_type_mstr_id")
                ->leftJoin(DB::raw("
                        (
                            select prop_safs_owners.saf_id, 
                                    string_agg(prop_safs_owners.owner_name,', ') as owner_name ,
                                    string_agg(prop_safs_owners.guardian_name,', ') as guardian_name,
                                    string_agg(prop_safs_owners.mobile_no::text,', ') as mobile_no
                            from prop_safs_owners
                            join prop_safs on prop_safs.id = prop_safs_owners.saf_id
                            where prop_safs_owners.status =1 and prop_safs.proccess_fee_paid = 0 and prop_safs.proccess_fee>0
                            group by prop_safs_owners.saf_id
                        )o
                    "), "o.saf_id", "p.id")
                ->leftJoin("ulb_ward_masters as ward", "ward.id", "p.ward_mstr_id")
                ->where("p.proccess_fee_paid", 0)
                ->where("p.proccess_fee", ">", 0);

            if ($request->assessmentType)
                $data = $data->where("p.assessment_type", $request->assessmentType);

            switch ($request->searchBy) {
                case "applicationNo":
                    $data = $data->where("p.saf_no", $request->value);
                    break;
                case "ptn":
                    $data = $data->where("p.ptn", $request->value);
                    break;
                case "holding":
                    $data = $data->where("p.holding_no", $request->value);
                    break;
                case "name":
                    $data = $data->where("o.owner_name", "ILIKE", "%" . $request->value . "%");
                    break;
                case "mobileNo":
                    $data = $data->where("o.mobile_no", "ILIKE", "%" . $request->value . "%");
                    break;
            }
            switch ($request->filteredBy) {
                case "gbsaf":
                    $data = $data->where("p.is_gb_saf", true);
                    break;
                default:
                    $data = $data->where("p.is_gb_saf", false);
            }

            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),

            ];
            return responseMsgs(true, "Data Fetched", $list, "011604", "1.0.1", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011604", "1.0.1", "", "POST", $request->deviceId ?? "");
        }
    }
    public function proccessFeePayment(ReqPayment $request)
    {
        $rules = $this->getMutationFeeReqRules($request);
        $rules["paidAmount"] = 'required|numeric|min:1';
        $rules["saleValue.*.owner"] = is_array($request->saleValue) && sizeOf($request->saleValue) > 1 ? 'required' : "nullable";
        $rules["saleValue.*.deed"] = is_array($request->saleValue) && sizeOf($request->saleValue) > 1 ? 'required|mimes:pdf,jpeg,png,jpg' : "nullable";
        $validated = Validator::make(
            $request->all(),
            $rules
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }

        try {
            $user = Auth()->user();
            $verifyPaymentModes = Config::get('payment-constants.VERIFICATION_PAYMENT_MODES');
            $offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
            $verifyStatus = 1;
            $saf = PropActiveSaf::find($request->id);
            if (!$saf) {
                $saf = PropSaf::find($request->id);
            }
            if (!$saf) {
                throw new Exception("Data Not Found");
            }
            if ($saf->proccess_fee_paid) {
                throw new Exception("Proccessing Fee Already Pay");
            }
            if ($saf->getTable() == "prop_active_safs") {
                throw new Exception("Please Wait For Approval");
            }
            $newSaleValue = $saf->proccess_fee;
            $proccessFeePayment = $this->getProccessFeePayment($request);
            if ($proccessFeePayment->original["status"]) {
                $newSaleValue = $proccessFeePayment->original["data"]["proccess_fee"];
            }

            $proccessFee = $newSaleValue;
            if ($proccessFee != $request->paidAmount) {
                throw new Exception("Demand Amount And Paied Amount Missmatched");
            }
            $deedArr = [];
            $docUpload = new DocUpload;
            $relativePath = Config::get('PropertyConstaint.PROCCESS_RELATIVE_PATH');
            $propModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            foreach ($request->saleValue as $key => $deedDoc) {
                $deedArr[$key] = $deedDoc;
                $document = $deedDoc["deed"];
                $refImageName =  $saf->id . "-" . $key + 1;
                unset($deedArr[$key]["deed"]);
                $deedArr[$key]["upload"] = $document ? ($relativePath . "/" . $docUpload->upload($refImageName, $document, $relativePath)) : "";
            }
            $request->merge(["saleValueNew" => $deedArr]);
            if (in_array($request->paymentMode, $verifyPaymentModes)) {
                $verifyStatus = 2;
            }
            $request->merge(["verifyStatus" => $verifyStatus]);

            $idGeneration = new IdGeneration;
            $tranNo = $idGeneration->generateTransactionNo($saf->ulb_id);
            $paymentReceiptNo = $this->generatePaymentReceiptNoSV2($saf);
            $tranBy = auth()->user()->user_type ??  $request->userType;
            $request->merge($paymentReceiptNo);
            $request->merge([
                "demandAmt" => $proccessFee,
                "workflowId" => $saf->workflow_id,
                "tranType" => "Saf",
                "saf_id" => $saf->id,
                "applicationNo" => $saf->saf_no,
                "ulbId"   => $saf->ulb_id,
                "userId" => $request['userId'] ?? ($user->id ?? 0),
                "tranNo" => $tranNo,
                "amount" => $request->paidAmount,
                'tranBy' => $tranBy,
                'todayDate' => Carbon::now()->format('Y-m-d'),
            ]);

            $saf->proccess_fee_paid = 1;
            $saf->deed_json = preg_replace('/\//', '', json_encode($request->saleValueNew, JSON_UNESCAPED_UNICODE));
            $safTrans = new PropTransaction();
            $safTrans->saf_id = $saf->id;
            $safTrans->amount = $request['amount'];
            $safTrans->tran_type = 'Saf Proccess Fee';
            $safTrans->tran_date = $request['todayDate'];
            $safTrans->tran_no = $request['tranNo'];
            $safTrans->payment_mode = $request['paymentMode'];
            $safTrans->user_id = $request['userId'] ?? $user->id;
            $safTrans->ulb_id = $request['ulbId'];
            $safTrans->demand_amt = $request['demandAmt'];
            $safTrans->tran_by_type = $request['tranBy'];
            $safTrans->verify_status = $request['verifyStatus'];
            $safTrans->book_no = $request['bookNo'] ?? null;

            $property = PropProperty::where("saf_id", $saf->id)->first();
            $Oldproperty = PropProperty::find($saf->previous_holding_id ?? 0);

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            $safTrans->save();
            $saf->update();
            # Activate new Property
            if ($property) {
                $property->status = 1;
                $property->update();
            }
            if ($saf->assessment_type == 'Mutation') {
                # Deactivate Old Property
                if ($Oldproperty) {
                    $Oldproperty->status = 4;
                    $Oldproperty->update();
                }
            }

            if (in_array($request['paymentMode'], $offlinePaymentModes)) {
                $request->merge(["tranId" => $safTrans->id]);
                $this->postOtherPaymentModes($request);
            }
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Payment Successfully Done", ['TransactionNo' => $safTrans->tran_no, 'transactionId' => $safTrans->id], "011604", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "011604", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    public function getProccessFeePayment(Request $request)
    {
        try {
            $rules = $this->getMutationFeeReqRules($request);
            $rules['id'] = "required|digits_between:1,9223372036854775807";


            $validated = Validator::make(
                $request->all(),
                $rules
            );
            if ($validated->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validated->errors()
                ]);
            }
            $saf = PropActiveSaf::find($request->id);
            if (!$saf) {
                $saf = PropSaf::find($request->id);
            }
            if (!$saf) {
                throw new Exception("Data Not Found");
            }
            $request->merge(
                [
                    "assessmentType" => $saf->assessment_type,
                    "propertyType" => $saf->prop_type_mstr_id,
                    "transferModeId" => $saf->transfer_mode_mstr_id,
                ]
            );
            $saleVaues = 0;
            if (is_array($request->saleValue)) {
                foreach ($request->saleValue as $val) {
                    $saleVal = $val["value"];
                    $saleVaues += $this->readProccessFee($request->assessmentType, $saleVal, $request->propertyType, $request->transferModeId);
                }
            } else {
                $saleVaues = $this->readProccessFee($request->assessmentType, $request->saleValue, $request->propertyType, $request->transferModeId);
            }
            $data["proccess_fee"] = $saleVaues;
            return responseMsgs(true, "ProccessFee Fetched", $data, "011604", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {

            return responseMsgs(false, $e->getMessage(), "", "011604.1", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    public function proccessFeePaymentRecipte(Request $request)
    {
        $rules['tranId'] = "required|digits_between:1,9223372036854775807";
        $validated = Validator::make(
            $request->all(),
            $rules
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $mVerification = new PropSafVerification();
            $verificationDtl = collect();
            $mVerificationDtls = new PropSafVerificationDtl();
            $trans = (new PropTransaction())->getPropByTranId($request->tranId);
            if (collect($trans)->isEmpty()) {
                throw new Exception("Transaction Not Available for this Transaction No");
            }
            if ($trans->tran_type !== "Saf Proccess Fee") {
                throw new Exception("Invalid Transection");
            }

            $saf = (new PropSaf())->getBasicDetailsV2($trans->saf_id);                       // Get Details from saf table
            if (collect($saf)->isEmpty()) {
                throw new Exception("Saf Details not available");
            }
            $safVerification = $mVerification->getLastVerification($saf->id);
            if ($safVerification) {
                $saf->ward_no = $safVerification->ward_name ? $safVerification->ward_name : $saf->ward_no;
                // $saf->category= $safVerification->category ? $safVerification->category : $saf->category;
                // $verificationDtl = $mVerificationDtls->getVerificationDtls($safVerification->id);
            }
            $ulbDetails = (new UlbMaster())->getUlbDetails($saf->ulb_id);

            $data["transactionNo"] = $trans->tran_no;
            $data["receiptDtls"] = [
                "departmentSection" => Config::get('PropertyConstaint.DEPARTMENT_SECTION'),
                "accountDescription" => Config::get('PropertyConstaint.ACCOUNT_DESCRIPTION'),
                "transactionDate" => Carbon::parse($trans->tran_date)->format('d-m-Y'),
                "transactionNo" => $trans->tran_no,
                "transactionTime" => $trans->created_at->format('g:i A'),
                "chequeStatus" => $trans->cheque_status ?? 1,
                "verifyStatus" => $trans->verify_status,                     // (0-Not Verified,1-Verified,2-Under Verification,3-Bounce)
                "applicationNo" => $saf->application_no ?? "",
                "customerName" => $saf->applicant_marathi ?? "",
                "ownerName" => $saf->owner_name_marathi ?? "",
                "guardianName"        => trim($saf->guardian_name ?? "") ? $saf->guardian_name : $saf->guardian_name ?? "",
                "guardianNameMarathi" => trim($saf->guardian_name_marathi ?? "") ? $saf->guardian_name_marathi : $saf->guardian_name_marathi ?? "",
                "mobileNo" => $saf->mobile_no ?? "",
                "address" => $saf->prop_address ?? "",
                "zone_name" => $saf->zone_name ?? "",
                "paidFrom" => $trans->from_fyear,
                "paidUpto" => $trans->to_fyear,
                "paymentMode" => $trans->payment_mode,
                "bankName" => $trans->bank_name,
                "branchName" => $trans->branch_name,
                "chequeNo" => $trans->cheque_no,
                "chequeDate" => ymdToDmyDate($trans->cheque_date),
                "demandAmount" => $trans->demand_amt,
                "arrearSettled" => $trans->arrear_settled_amt,
                "ulbId" => $saf->ulb_id ?? "",
                "wardNo" => $saf->ward_no ?? "",
                "propertyNo" => $saf->property_no ?? "",
                "towards" => $trans->tran_type,
                "description" => [
                    "keyString" => "Holding Tax"
                ],
                "totalPaidAmount" => $trans->amount,
                "advancePaidAmount" => 0,
                "adjustAmount" => 0,
                "netAdvance" => 0,
                "paidAmtInWords" => getIndianCurrency($trans->amount),
                "tcName" => $trans->tc_name,
                "tcMobile" => $trans->tc_mobile,
                "ulbDetails" => $ulbDetails,
                "isArrearReceipt" => false,
                "bookNo" => $trans->book_no ?? "",
                "plot_no" => $saf->plot_no ?? "",
                "area_of_plot" => $saf->area_of_plot,

                "receiptNo" => isset($trans->book_no) ? (explode('-', $trans->book_no)[1] ?? "0") : ""
            ];
            return responseMsgs(true, "Payment Receipt", remove_null($data), "011604.2", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011604.2", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    private function getMutationFeeReqRules(Request $request)
    {
        $rules = [
            'saleValue' => 'required|array',
            "saleValue.*.value" => "required|digits_between:1,9223372036854775807",

        ];
        return  $rules;
    }

    private function generatePaymentReceiptNoSV2($saf): array
    {
        $wardDetails = UlbWardMaster::find($saf->ward_mstr_id);
        if (collect($wardDetails)->isEmpty())
            throw new Exception("Ward Details Not Available");

        $fyear = getFy();

        $wardNo = $wardDetails->ward_name;
        $counter = (new UlbWardMaster)->getTranCounter($wardDetails->id)->counter ?? null;
        $user = Auth()->user();
        $mUserType = $user->user_type ?? "";
        $type = "O";
        if ($mUserType == "TC") {
            $type = "T";
        } elseif ((new \App\Repository\Common\CommonFunction())->checkUsersWithtocken("users")) {
            $type = "C";
        }
        if (!$counter) {
            throw new Exception("Unable To Find Counter");
        }
        return [
            'bookNo' => substr($fyear, 7, 2) . $type . $wardNo . "-" . $counter,
            'receiptNo' => $counter,
            'wardNo' => $wardNo,
        ];
    }


    /**
     * ===============generate saf jahirnama===============
     *                create by : Sandeep  Bara
     *                Date      : 2024-0-24
     */
    public function genrateJahirnamaPDF(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $jahirnamaDoc = new PropSafJahirnamaDoc();
            $request->merge(['id' => $request->applicationId, "safId" => $request->applicationId]);

            $user = Auth()->user();
            $safData = $this->getStaticSafDetails($request);
            $filename = $request->applicationId . "-jahirnama" . '.' . 'pdf';
            $url = "Property/Saf/Jhirnama/" . $filename;
            if (!$safData->original["status"]) {
                throw new Exception($safData->original["message"]);
            }
            $safData = $safData->original["data"];
            $safData["previousOwnerName"] = $safData["previous_owners"]->implode("owner_name", ",");
            $safData["newOwnerName"] = $safData["owners"]->implode("owner_name", ",");
            $safData["jahirnamaDate"] = Carbon::now()->format("d/m/Y");
            $safData["jahirnamaNo"] = $safData["saf_no"];
            $pdf = PDF::loadView('prop_jahirnama', ["safData" => $safData]);
            $file = $pdf->download($filename . '.' . 'pdf');
            // $docUpload = (new DocUpload())->upload($filename,$file,"Uploads/Property/Saf/Jhirnama");
            $pdf = Storage::put('public' . '/' . $url, $file);
            $docUpload = move_uploaded_file("../storage/Uploads/Property/Saf/Jhirnama", "../public/Uploads/Property/Saf/Jhirnama");
            dd($docUpload);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function getJahirnamaDocList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $jahirnamaDoc = new PropSafJahirnamaDoc();
            $activeSaf = PropActiveSaf::find($request->applicationId);
            if (!$activeSaf) {
                throw new Exception("data not found");
            }

            $jahirnama = $jahirnamaDoc->getjahirnamaDoc($request->applicationId);

            $data = [
                "listDocs" => [
                    [
                        "docType" => "R",
                        "docName" => "Jahirnama",
                        "uploadedDoc" => $jahirnama,
                        "masters" => [[
                            "documentCode" => "Jahirnama",
                            "docVal"    => "Jahirnama",
                            "uploadedDoc" => $jahirnama->doc_path ?? "",
                            "uploadedDocId" => $jahirnama->id ?? "",
                            "verifyStatus" => $jahirnama->verify_status ?? "",
                            "remarks"   => $jahirnama->remarks ?? "",
                        ]]

                    ]
                ],
            ];
            return responseMsgs(true, "Jahirnama Doc list", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function uploadJahirnama(Request $request)
    {
        $extention = ($request->document) instanceof UploadedFile ? $request->document->getClientOriginalExtension() : "";
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
                "document" => "required|mimes:pdf,jpeg,png,jpg|" . (strtolower($extention) == 'pdf' ? 'max:10240' : 'max:5120'),
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $request->merge(['id' => $request->applicationId, "safId" => $request->applicationId]);

            $jahirnamaDoc = new PropSafJahirnamaDoc();
            $docUpload = new DocUpload;
            $user = Auth()->user();
            $document = $request->document;

            $filename = $request->applicationId . "-jahirnama";
            $relativePath = Config::get("PropertyConstaint.SAF_JARINAMA_RELATIVE_PATH");

            $safData = $this->getStaticSafDetails($request);
            $activeSaf = PropActiveSaf::find($request->applicationId);
            if (!$safData->original["status"]) {
                throw new Exception($safData->original["message"]);
            }
            if (!$activeSaf) {
                throw new Exception("data not found");
            }
            if (!in_array($activeSaf->workflow_id, $this->_alowJahirnamaWorckflows)) {
                throw new Exception("can not allow generate jahirnama for this type of property");
            }
            $oldjahirnamaDoc = $jahirnamaDoc->getJahirnamaBysafIdOrm($activeSaf->id)->first();
            if ($activeSaf->is_jahirnama_genrated && $oldjahirnamaDoc) {
                // throw new Exception("jahirnama already genrated on ".Carbon::parse($oldjahirnamaDoc->generation_date)->format("d-m-Y"));
            }
            $safData = $safData->original["data"];
            $imageName = $docUpload->upload($filename, $document, $relativePath);
            $request->merge([
                "docName"       => $imageName,
                "relativePath"  => $relativePath,
                "userId"        => $user->id ?? null,
            ]);


            DB::beginTransaction();
            $id = $jahirnamaDoc->store($request);
            $activeSaf->is_jahirnama_genrated = true;
            $activeSaf->save();
            DB::commit();
            $jahirnamaDocList = $this->getJahirnamaDoc($request);
            $data = $jahirnamaDocList->original["data"];
            return responseMsg(true, "jahirnama genrated", $data);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function getJahirnamaDoc(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $jahirnamaDoc = new PropSafJahirnamaDoc();
            $jahirnama = $jahirnamaDoc->getjahirnamaDoc($request->applicationId);
            if (!$jahirnama) {
                throw new Exception("jahirnama not genrated");
            }
            $jahirnama->doc_category = $jahirnama->doc_code;
            $jahirnama->verify_status = $jahirnama->status;
            $jahirnama->remarks       = "";
            $jahirnama->owner_name    =  "";
            $listDocs = collect();
            $listDocs->push($jahirnama->toArray());
            if ($jahirnama->is_update_objection) {
                $jhirnamaObjection = [
                    "owner_name" => $jahirnama->owner_name,
                    "doc_path" => $jahirnama->objection_doc_path,
                    "doc_code" => $jahirnama->doc_code . "-Objection",
                    "doc_category" => $jahirnama->doc_code . "-Objection",
                    "verify_status" => $jahirnama->status,
                    "remarks" => $jahirnama->objection_comment,
                ];
                $listDocs->push($jhirnamaObjection);
            }
            return responseMsg(true, "jahirnama doc", $listDocs);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function updateJahirnama(Request $request)
    {
        $extention = ($request->document) instanceof UploadedFile ? $request->document->getClientOriginalExtension() : "";
        $validated = Validator::make(
            $request->all(),
            [
                "applicationId" => "required|digits_between:1,9223372036854775807",
                "hasAnyObjection" => "required|bool",
                "document" => "nullable|required_if:hasAnyObjection,==,true,1|mimes:pdf,jpeg,png,jpg|" . (strtolower($extention) == 'pdf' ? 'max:10240' : 'max:5120'),
                "comment"  => "nullable|required_if:hasAnyObjection,==,true,1",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        try {
            $safId          = $request->applicationId;
            $jahirnamaDoc   = new PropSafJahirnamaDoc();
            $docUpload      = new DocUpload();
            $relativePath   = Config::get("PropertyConstaint.SAF_JARINAMA_RELATIVE_PATH");
            $user = Auth()->user();

            $jahirnama = $jahirnamaDoc->getJahirnamaBysafIdOrm($safId)->first();
            if (!$jahirnama) {
                throw new Exception("Jahirnama Not Uploaded");
            }
            $updateJahirnama = [
                "hasAnyObjection" => $request->hasAnyObjection,
                "objectionComment" => $request->comment,
                "objectionUserId" => $user->id ?? null,
                "isUpdateObjection" => true,
            ];
            $filename = $jahirnama->id . "-objection";
            $document = $request->document;
            if ($document) {
                $imageName = $docUpload->upload($filename, $document, $relativePath);
                $updateJahirnama["objectionDocName"] = $imageName;
                $updateJahirnama["objectionRelativePath"] = $relativePath;
            }
            $updateJahirnama = (object)$updateJahirnama;
            DB::beginTransaction();
            $t = $jahirnama->edit($jahirnama->id, $updateJahirnama);
            DB::commit();
            return responseMsg(true, "jahirnama objection updated", $t);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function getUploadDoc(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|numeric'
        ]);
        try {
            $refUser = Auth()->user();
            $refUserId = $refUser->id ?? 0;
            $mWfActiveDocument = new WfActiveDocument();
            $mActiveSafs = new PropActiveSaf();
            $mActiveSafsOwners = new PropActiveSafsOwner();
            $mPropSaf = new PropSaf();
            $mSafsOwners = new PropSafsOwner();
            $mCOMMON_FUNCTION = new CommonFunction();
            $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');              // 1

            $safDetails = $mActiveSafs->getSafNo($req->applicationId);
            if (!$safDetails)
                $safDetails = $mPropSaf->find($req->applicationId);
            if (!$safDetails)
                throw new Exception("Application Not Found for this application Id");

            $workflowId = $safDetails->workflow_id;
            $documents = $mWfActiveDocument->getDocByRefIds($req->applicationId, $workflowId, $moduleId);
            $refUlbId = $safDetails->ulb_id;
            $userRole = $mCOMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $workflowId);
            $sameWorkRoles = $mCOMMON_FUNCTION->getReactionActionTakenRole($refUserId, $refUlbId, $workflowId, "doc_verify");
            $owners = $mActiveSafs->getTable() == "prop_active_safs" ? $mActiveSafsOwners->getOwnersBySafId($safDetails->id) : $mSafsOwners->getOwnersBySafId($safDetails->id);
            $documents = $documents->map(function ($val) use ($sameWorkRoles, $userRole, $owners) {
                $seconderyData = (new SecondaryDocVerification())->SeconderyWfActiveDocumentById($val->id);
                $val->verify_status_secondery = $seconderyData ? $seconderyData->verify_status : 0;
                $val->remarks_secondery = $seconderyData ? $seconderyData->remarks :  "";
                $val->owner_name = (collect($owners)->where("id", $val->owner_dtl_id)->first())->owner_name ?? "";
                if (count($sameWorkRoles) > 1 && $userRole && $userRole->role_id != ($sameWorkRoles->first())["id"] && $userRole->can_verify_document) {
                    $val->verify_status = $seconderyData ? $val->verify_status_secondery : $val->verify_status;
                    $val->remarks = $seconderyData ? $val->remarks_secondery : $val->remarks;
                }
                if ($val->doc_code  == 'PHOTOGRAPH') {
                    $val->verify_status = 1;
                    $val->remarks = "";
                }
                return $val;
            });
            #======getjahinama Doc=======#
            $ActiveSafController = App::makeWith(ActiveSafController::class, ["iSafRepository" => iSafRepository::class]);
            $jahirnamaDoc = $ActiveSafController->getJahirnamaDoc($req);
            if ($jahirnamaDoc->original["status"]) {
                $jahirnamaDoc = collect($jahirnamaDoc->original["data"])->sortByDesc("id");
                foreach ($jahirnamaDoc as $val) {
                    $documents->push($val);
                }
            }
            $documents = $documents->map(function ($val) {
                $uploadeUser = isset($val["uploaded_by_type"]) && $val["uploaded_by_type"] != "Citizen" ? User::find($val["uploaded_by"] ?? 0) : ActiveCitizen::find($val["uploaded_by"] ?? 0);
                $val["uploadedBy"] = ($uploadeUser->name ?? ($uploadeUser->user_name ?? "")) . " (" . ($val["uploaded_by_type"] ?? "") . ")";
                return $val;
            });
            return remove_null($documents);
            // return responseMsgs(true, ["docVerifyStatus" => $safDetails->doc_verify_status], remove_null($documents), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }
}
