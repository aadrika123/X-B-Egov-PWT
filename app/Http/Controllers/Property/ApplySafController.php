<?php

namespace App\Http\Controllers\Property;

use App\BLL\Property\Akola\TaxCalculator;
use App\BLL\Property\CalculateSafById;
use App\BLL\Property\GenerateSafApplyDemandResponse;
use App\BLL\Property\PostSafPropTaxes;
use App\EloquentClass\Property\InsertTax;
use App\EloquentClass\Property\PenaltyRebateCalculation;
use App\EloquentClass\Property\SafCalculation;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Water\WaterConsumer;
use App\Http\Requests\Property\reqApplySaf;
use App\Http\Requests\ReqGBSaf;
use App\Models\Property\Logs\PropSmsLog;
use App\Models\Property\Logs\SafAmalgamatePropLog;
use App\Models\Property\PropActiveGbOfficer;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropDemand;
use App\Models\Property\PropFloor;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsDemand;
use App\Models\TradeLicence;
use App\Models\Water\WaterSecondConsumer;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Auth\EloquentAuthRepository;
use App\Traits\Property\SAF;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * | Created On-16-03-2023 
 * | Created By-Anshu Kumar
 * | Created For
 *      - Apply Saf 
 *      - Apply GB Saf
 * | Status-Closed
 */

class ApplySafController extends Controller
{
    use SAF;
    use Workflow;

    protected $_todayDate;
    protected $_REQUEST;
    protected $_safDemand;
    public $_generatedDemand;
    protected $_propProperty;
    public $_holdingNo;
    protected $_citizenUserType;
    protected $_currentFYear;
    protected $_penaltyRebateCalc;
    protected $_currentQuarter;
    private $_demandAdjustAssessmentTypes;

    public function __construct()
    {
        $this->_todayDate = Carbon::now();
        $this->_safDemand = new PropSafsDemand();
        $this->_propProperty = new PropProperty();
        $this->_citizenUserType = Config::get('workflow-constants.USER_TYPES.1');
        $this->_currentFYear = getFY();
        $this->_penaltyRebateCalc = new PenaltyRebateCalculation;
        $this->_currentQuarter = calculateQtr($this->_todayDate->format('Y-m-d'));
        $this->_demandAdjustAssessmentTypes = Config::get('PropertyConstaint.REASSESSMENT_TYPES');
    }
    /**
     * | Created On-17-02-2022 
     * | Created By-Anshu Kumar
     * | --------------------------- Workflow Parameters ---------------------------------------
     * |                                 # SAF New Assessment
     * | wf_master id=4 
     * | wf_workflow_id=4
     * |                                 # SAF Reassessment 
     * | wf_mstr_id=5
     * | wf_workflow_id=3
     * |                                 # SAF Mutation
     * | wf_mstr_id=9
     * | wf_workflow_id=5
     * |                                 # SAF Bifurcation
     * | wf_mstr_id=25
     * | wf_workflow_id=182
     * |                                 # SAF Amalgamation
     * | wf_mstr_id=373
     * | wf_workflow_id=381
     * | Created For- Apply for New Assessment, Reassessment, Mutation, Bifurcation and Amalgamation
     * | Status-Open
     */
    /**
     * | Apply for New Application(2)
     * | Status-Closed
     * | Query Costing-500 ms
     * | Rating-5
     */
    public function applySaf(reqApplySaf $request)
    {
        try {
            // Variable Assignments
            $mApplyDate = Carbon::now()->format("Y-m-d");
            $user = authUser($request);
            $user_id = $user->id;
            $ulb_id = 2;                                // ulb id for akola municipal
            $userType = $user->user_type;
            $metaReqs = array();
            $saf = new PropActiveSaf();
            $mOwner = new PropActiveSafsOwner();
            $mPropSmsLog = new PropSmsLog();
            $prop   = new PropProperty();
            $taxCalculator = new TaxCalculator($request);
            if ($prop = PropProperty::find($request->previousHoldingId)) {
                $request->merge(["propertyNo" => $prop->property_no ?? null]);
            }
            if ($request->assessmentType == 4 || $request->assessmentType == "Bifurcation") {
                $request->merge(["propertyNo" => null]);
            }
            // Derivative Assignments
            $ulbWorkflowId = $this->readAssessUlbWfId($request, $ulb_id);           // (2.1)
            $roadWidthType = $this->readRoadWidthType($request->roadType);          // Read Road Width Type
            $mutationProccessFee = $this->readProccessFee($request->assessmentType, $request->saleValue, $request->propertyType, $request->transferModeId);
            // if ($request->assessmentType == 'Bifurcation')
            //     $request->areaOfPlot = $this->checkBifurcationCondition($saf, $prop, $request);

            $request->request->add(['road_type_mstr_id' => $roadWidthType]);

            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);                                // Get Current Initiator ID
            $initiatorRoleId = collect(DB::select($refInitiatorRoleId))->first();
            if (is_null($initiatorRoleId))
                throw new Exception("Initiator Role Not Available");

            $refFinisherRoleId = $this->getFinisherId($ulbWorkflowId->id);

            $finisherRoleId = collect(DB::select($refFinisherRoleId))->first();
            if (is_null($finisherRoleId))
                throw new Exception("Finisher Role Not Available");


            $metaReqs['roadWidthType'] = $roadWidthType;
            $metaReqs['workflowId'] = $ulbWorkflowId->id;       // inserting workflow id
            $metaReqs['ulbId'] = $ulb_id;
            $metaReqs['userId'] = $user_id;
            $metaReqs['initiatorRoleId'] = collect($initiatorRoleId)['role_id'];

            if ($userType == $this->_citizenUserType) {
                //     $metaReqs['initiatorRoleId'] = collect($initiatorRoleId)['forward_role_id'];         // Send to DA in Case of Citizen
                $metaReqs['userId'] = null;
                $metaReqs['citizenId'] = $user_id;
            }
            $metaReqs['finisherRoleId'] = collect($finisherRoleId)['role_id'];
            $metaReqs['holdingType'] = $this->holdingType($request['floor']);
            $request->merge($metaReqs);
            $request->merge(["proccessFee" => $mutationProccessFee]);
            if ($request->workflowId == Config::get('workflow-constants.ULB_WORKFLOW_ID_OLD_MUTATION')) {
                $request->merge(["saleValue" => 0, "proccessFee" => 0]);
            }
            $this->_REQUEST = $request;
            $this->mergeAssessedExtraFields();                                          // Merge Extra Fields for Property Reassessment,Mutation,Bifurcation & Amalgamation(2.2)
            // Generate Calculation
            if (!$request->no_calculater)
                $taxCalculator->calculateTax();
            // if (($taxCalculator->_oldUnpayedAmount ?? 0) > 0 && ($request->assessmentType == 3 || $request->assessmentType == 4 || $request->assessmentType == "Mutation" || $request->assessmentType == "Bifurcation")) {
            //     throw new Exception("Old Demand Amount Of " . $taxCalculator->_oldUnpayedAmount . " Not Cleard");
            // }
            DB::beginTransaction();
            $createSaf = $saf->store($request);                                         // Store SAF Using Model function 
            if ($request->assessmentType == 5 || $request->assessmentType == "Amalgamation") {
                $request->merge(["safId" => $createSaf->original['safId']]);
                $SafAmalgamatePropLog = new SafAmalgamatePropLog();
                $SafAmalgamatePropLog->store($request);
            }
            $safId = $createSaf->original['safId'];
            $safNo = $createSaf->original['safNo'];

            // SAF Owner Details
            if ($request['owner']) {
                $ownerDetail = $request['owner'];
                if ($request->assessmentType == 'Mutation')                             // In Case of Mutation Abort Existing Owner Detail
                    $ownerDetail = collect($ownerDetail)->where('propOwnerDetailId', null);
                foreach ($ownerDetail as $ownerDetails) {
                    $mOwner->addOwner($ownerDetails, $safId, $user_id);
                }
            }

            // Floor Details
            if ($request->propertyType != 4) {
                if ($request['floor']) {
                    $floorDetail = $request['floor'];
                    $this->checkBifurcationFloorCondition($floorDetail);
                    foreach ($floorDetail as $floorDetails) {
                        $floor = new PropActiveSafsFloor();
                        $floor->addfloor($floorDetails, $safId, $user_id, $request->assessmentType, $request['biDateOfPurchase'] ?? null);
                    }
                }
            }
            $this->sendToWorkflow($createSaf, $user_id);
            DB::commit();

            $ownerName = Str::limit(trim($ownerDetail[0]['ownerName']), 30);
            $ownerMobile = $ownerDetail[0]['mobileNo'];

            $sms = AkolaProperty(["owner_name" => $ownerName, "saf_no" => $safNo, "assessment_type" => $request->assessmentType, "holding_no" => $request->propertyNo ?? ""], ($request->assessmentType == "New Assessment" ? "New Assessment" : "Reassessment"));
            if (($sms["status"] !== false)) {
                $response = send_sms($ownerMobile, $sms["sms"], $sms["temp_id"]);
                $smsReqs = [
                    "emp_id" => $user_id,
                    "ref_id" => $safId,
                    "ref_type" => 'SAF',
                    "mobile_no" => $ownerMobile,
                    "purpose" => $request->assessmentType . ' Apply',
                    "template_id" => $sms["temp_id"],
                    "message" => $sms["sms"],
                    "response" => $response['status'],
                    "smgid" => $response['msg'],
                    "stampdate" => Carbon::now(),
                ];
                $mPropSmsLog->create($smsReqs);
            }

            return responseMsgs(true, "Successfully Submitted Your Application Your SAF No. $safNo", [
                "safNo" => $safNo,
                "applyDate" => ymdToDmyDate($mApplyDate),
                "safId" => $safId,
                "calculatedTaxes" => (!$request->no_calculater ? $taxCalculator->_GRID : []),
            ], "010102", "1.0", "1s", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010102", "1.0", "1s", "POST", $request->deviceId);
        }
    }

    public function applySafTc(Request $request)
    {
        $request->validate([
            'propAddress' => 'required',
            'mobileNo' => 'required|digits:10|regex:/[0-9]{10}/',
            'ownerName' => 'required|string|max:255',
            'ward' => 'required',
            'zone' => 'required',
            'consumerNo' => 'nullable',
            'licenseNo' => 'nullable',
            'propertyType' => 'required|int',
            'isWaterHarvesting' => 'nullable|bool',
            'harvestingDate' => 'nullable|date',
            'isApplicationFormDoc'=>'nullable|boolean',
            'isSaleDeedDoc'=>'nullable|boolean',
            'isLayoutSactionMapDoc'=>'nullable|boolean',
            'isNaOrderDoc'=>'nullable|boolean',
            'isNamunaDDoc'=>'nullable|boolean',
            'isOthersDoc'=>'nullable|boolean',
            'isMeasurementDoc'=>'nullable|boolean',
            'isPhotoDoc'=>'nullable|boolean',
            'isIdProofDoc'=>'nullable|boolean'
        ]);

        try {
            // Fetch current date
            $mApplyDate = Carbon::now()->format("Y-m-d");

            // Authenticate user
            $user = authUser($request);
            $user_id = $user->id;
            $ulb_id = 2;
            $userType = $user->user_type;
            $metaReqs = array();

            // Initialize models
            $saf = new PropActiveSaf();
            $mOwner = new PropActiveSafsOwner();

            // Fetch workflow details
            $workflow_id = Config::get('workflow-constants.SAF_WORKFLOW_ID');
            $ulbWorkflowId = WfWorkflow::where('wf_master_id', $workflow_id)
                ->where('ulb_id', $ulb_id)
                ->first();

            // Fetch initiator role ID
            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);
            $initiatorRoleId = collect(DB::select($refInitiatorRoleId))->first();
            if (is_null($initiatorRoleId))
                throw new Exception("Initiator Role Not Available");

            // Fetch finisher role ID
            $refFinisherRoleId = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId = collect(DB::select($refFinisherRoleId))->first();
            if (is_null($finisherRoleId))
                throw new Exception("Finisher Role Not Available");

            // Prepare meta request data
            $metaReqs['workflowId'] = $ulbWorkflowId->id;
            $metaReqs['assessmentType'] = "New Assessment";
            $metaReqs['appliedBy'] = "TC";
            $metaReqs['ulbId'] = $ulb_id;
            $metaReqs['userId'] = $user_id;
            $metaReqs['initiatorRoleId'] = collect($initiatorRoleId)['role_id'];

            if ($userType == $this->_citizenUserType) {
                $metaReqs['userId'] = null;
                $metaReqs['citizenId'] = $user_id;
            }
            $metaReqs['finisherRoleId'] = collect($finisherRoleId)['role_id'];

            $request->merge($metaReqs);
            if ($request->consumerNo) {
                $water = new WaterSecondConsumer();
                $waterDetail = $water->getDetailByConsumerNoforProperty($request->consumerNo);
                if (!$waterDetail) {
                    throw new Exception("Invalid consumer_no");
                }
            }
            if ($request->licenseNo) {
                $mTrade = new TradeLicence();
                $tradeDetail = $mTrade->getDetailsByLicenceNov2($request->licenseNo);
                if (!$tradeDetail) {
                    throw new Exception("Invalid license No");
                }
            }
            DB::beginTransaction();
            // Store SAF data
            $createSaf = $saf->storeV1($request);
            $safId = $createSaf->original['safId'];
            $safNo = $createSaf->original['safNo'];
            $mOwner->owner_name = strtoupper($request->ownerName);
            $mOwner->mobile_no = $request->mobileNo ?? null;
            $mOwner->saf_id = $safId;
            $mOwner->save();
            // Send data to workflow
            $this->sendToWorkflow($createSaf, $user_id);
            DB::commit();
            return responseMsgs(true, "Successfully Submitted Your Application. Your SAF No. $safNo", [
                "safNo" => $safNo,
                "applyDate" => ymdToDmyDate($mApplyDate),
                "safId" => $safId,
                "waterDetails" => $waterDetail ?? null,
                "tradeDetails" => $tradeDetail ?? null
            ], "010102", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010102", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }


    public function applySafTcDetail(Request $request)
    {
        $request->validate([
            'safId' => 'required'
        ]);

        try {
            $user = authUser($request);
            $user_id = $user->id;
            $ulb_id = 2;

            $saf = new PropActiveSaf();
            $basicDetail = $saf->getSafDetail($request->safId);

            $waterDetail = null;
            $tradeDetail = null;

            if ($basicDetail->water_conn_no != null) {
                $water = new WaterSecondConsumer();
                $waterDetail = $water->getDetailByConsumerNoforProperty($basicDetail->water_conn_no);
            }

            if ($basicDetail->trade_license_no != null) {
                $mTrade = new TradeLicence();
                $tradeDetail = $mTrade->getDetailsByLicenceNov2($basicDetail->trade_license_no);
            }

            $list = [
                'BasicDetail' => $basicDetail,
                'WaterDetail' => $waterDetail,
                'TradeDetail' => $tradeDetail,
            ];

            return responseMsgs(true, "Saf Details", $list, "010102", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010102", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    public function verifyConsumer(Request $request)
    {
        $request->validate([
            'consumerNo' => 'nullable',
        ]);

        try {
            $waterDetail = null;

            if ($request->consumerNo) {
                $water = new WaterSecondConsumer();
                $waterDetail = $water->getDetailByConsumerNoforProperty($request->consumerNo);
                if (!$waterDetail) {
                    throw new Exception("Consumer number does not exist.");
                }
            } 
            return responseMsgs(true, "Consumer details retrieved successfully.", $waterDetail, "010102", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010102", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    public function verifyLicense(Request $request)
    {
        $request->validate([
            'licenseNo' => 'nullable',
        ]);

        try {
            $tradeDetail = null;

            if ($request->licenseNo) {
                $mTrade = new TradeLicence();
                $tradeDetail = $mTrade->getDetailsByLicenceNov2($request->licenseNo);
                if (!$tradeDetail) {
                    throw new Exception("License number does not exist.");
                }
            }
            
            return responseMsgs(true, "License details retrieved successfully.", $tradeDetail, "010102", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010102", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }





    /**
     * | Read Assessment Type and Ulb Workflow Id(2.1)
     */
    public function readAssessUlbWfId($request, $ulb_id)
    {
        if ($request->assessmentType == 1) {                                                    // New Assessment 
            $workflow_id = Config::get('workflow-constants.SAF_WORKFLOW_ID');
            $request->assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.1');
        }

        if ($request->assessmentType == 2) {                                                    // Reassessment
            $workflow_id = Config::get('workflow-constants.SAF_REASSESSMENT_ID');
            $request->assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.2');
        }

        if ($request->assessmentType == 3) {                                                    // Mutation
            $workflow_id = Config::get('workflow-constants.SAF_MUTATION_ID');
            $request->assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.3');
        }

        if ($request->assessmentType == 4) {                                                    // Bifurcation
            $workflow_id = Config::get('workflow-constants.SAF_BIFURCATION_ID');
            $request->assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.4');
        }

        if ($request->assessmentType == 5) {                                                    // Amalgamation
            $workflow_id = Config::get('workflow-constants.SAF_AMALGAMATION_ID');
            $request->assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.5');
        }
        if ($request->assessmentType == 6) {                                                    // Amalgamation
            $workflow_id = Config::get('workflow-constants.SAF_OLD_MUTATION_ID');
            $request->assessmentType = Config::get('PropertyConstaint.ASSESSMENT-TYPE.3');
        }

        return WfWorkflow::where('wf_master_id', $workflow_id)
            ->where('ulb_id', $ulb_id)
            ->first();
    }

    /**
     * | Merge Extra Fields in request for Reassessment,Mutation,Etc
     */
    public function mergeAssessedExtraFields()
    {
        $mPropProperty = new PropProperty();
        $req = $this->_REQUEST;
        $assessmentType = $req->assessmentType;

        if (in_array($assessmentType, $this->_demandAdjustAssessmentTypes)) {           // Reassessment,Mutation and Others
            $property = $mPropProperty->getPropById($req->previousHoldingId);
            if (collect($property)->isEmpty())
                throw new Exception("Property Not Found For This Holding");
            $req->holdingNo = $property->new_holding_no ?? $property->holding_no;
            $propId = $property->id;
            $req->merge([
                'hasPreviousHoldingNo' => true,
                'previousHoldingId' => $propId
            ]);
            switch ($assessmentType) {
                case "Reassessment":                                 // Bifurcation
                    $req->merge([
                        'propDtl' => $propId
                    ]);
                    break;
                case "Bifurcation":                                 // Bifurcation
                    $req->dateOfPurchase = $req->biDateOfPurchase;
                    $req->areaOfPlot     = $this->checkBifurcationCondition($property, $req);
                    break;
            }
        }

        // Amalgamation
        if (in_array($assessmentType, ["Amalgamation"])) {
            $previousHoldingIds = array();
            $previousHoldingLists = array();

            foreach ($req->holdingNoLists as $holdingNoList) {
                $propDtls = $mPropProperty->getPropertyId($holdingNoList);
                if (!$propDtls)
                    throw new Exception("Property Not Found For the holding");
                $propId = $propDtls->id;
                array_push($previousHoldingIds, $propId);
                array_push($previousHoldingLists, $holdingNoList);
            }

            $req->merge([
                'amalgamatHoldingId' => $previousHoldingIds,
                'amalgamatHoldingNo' => $req->holdingNoLists
            ]);
        }
    }

    /**
     * | Check Bifurcation Condition
     */
    public function checkBifurcationCondition($propDtls, $activeSafReqs)
    {
        $mPropActiveSaf = new PropActiveSaf();
        $propertyId = $propDtls->id;
        $propertyPlotArea = $propDtls->area_of_plot;
        $currentSafPlotArea = $activeSafReqs->bifurcatedPlot;
        $activeSafDetail = $mPropActiveSaf->where('previous_holding_id', $propertyId)->where('assessment_type', 'Bifurcation')->where('status', 1)->get();
        $activeSafPlotArea = collect($activeSafDetail)->sum('area_of_plot');
        $newAreaOfPlot = $propertyPlotArea - $activeSafPlotArea;
        if (($activeSafPlotArea + $currentSafPlotArea) > $propertyPlotArea)
            throw new Exception("You have excedeed the plot area. Please insert plot area below " . $newAreaOfPlot);
        if (($activeSafPlotArea + $currentSafPlotArea) == $propertyPlotArea)
            throw new Exception("You Can't apply for Bifurcation. Please Apply Mutation.");
        return $newAreaOfPlot;
    }

    /**
     * | Check Bifurcation Floor Condition
     */
    public function checkBifurcationFloorCondition($floorDetail)
    {
        $req = $this->_REQUEST;
        $mPropFloors = new PropFloor();
        $assessmentType = $req->assessmentType;
        if ($assessmentType == 'Bifurcation') {
            $floorDetail = collect($floorDetail)->whereNotNull('propFloorDetailId');

            foreach ($floorDetail as $index => $requestFloor) {
                $propFloorDtls = $mPropFloors::find($requestFloor['propFloorDetailId']);
                $safFloorDtls  = PropActiveSafsFloor::where('prop_floor_details_id', $requestFloor['propFloorDetailId'])->where('status', 1)->get();
                $currentFloorArea  = $requestFloor['biBuildupArea'];
                $propFloorArea  = $propFloorDtls->builtup_area;
                $safFloorArea   = $safFloorDtls->sum('builtup_area');
                $newAreaOfPlot  = $propFloorArea - $safFloorArea;

                if (($safFloorArea + $currentFloorArea) > $propFloorArea)
                    throw new Exception("You have excedeed the floor area. Please insert plot area below " . $newAreaOfPlot . " of floor " . $index + 1);
            }
        }
    }


    /**
     * | Send to Workflow Level
     */
    public function sendToWorkflow($activeSaf, $userId)
    {
        $mWorkflowTrack = new WorkflowTrack();
        $todayDate = $this->_todayDate;
        $refTable = Config::get('PropertyConstaint.SAF_REF_TABLE');
        $reqWorkflow = [
            'workflow_id' => $activeSaf->original['workflow_id'],
            'ref_table_dot_id' => $refTable,
            'ref_table_id_value' => $activeSaf->original['safId'],
            'track_date' => $todayDate->format('Y-m-d h:i:s'),
            'module_id' => Config::get('module-constants.PROPERTY_MODULE_ID'),
            'user_id' => $userId,
            'receiver_role_id' => $activeSaf->original['current_role'],
            'ulb_id' => $activeSaf->original['ulb_id'],
            'status' => true
        ];
        $mWorkflowTrack->store($reqWorkflow);
    }
}
