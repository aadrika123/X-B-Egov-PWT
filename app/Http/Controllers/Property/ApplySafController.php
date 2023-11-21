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
use App\Http\Requests\Property\reqApplySaf;
use App\Http\Requests\ReqGBSaf;
use App\Models\Property\PropActiveGbOfficer;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropDemand;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSafsDemand;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Auth\EloquentAuthRepository;
use App\Traits\Property\SAF;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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
            $taxCalculator = new TaxCalculator($request);
            // Derivative Assignments
            $ulbWorkflowId = $this->readAssessUlbWfId($request, $ulb_id);           // (2.1)
            $roadWidthType = $this->readRoadWidthType($request->roadType);          // Read Road Width Type

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
                $metaReqs['initiatorRoleId'] = collect($initiatorRoleId)['forward_role_id'];         // Send to DA in Case of Citizen
                $metaReqs['userId'] = null;
                $metaReqs['citizenId'] = $user_id;
            }
            $metaReqs['finisherRoleId'] = collect($finisherRoleId)['role_id'];
            $metaReqs['holdingType'] = $this->holdingType($request['floor']);
            $request->merge($metaReqs);
            $this->_REQUEST = $request;
            $this->mergeAssessedExtraFields();                                          // Merge Extra Fields for Property Reassessment,Mutation,Bifurcation & Amalgamation(2.2)
            // Generate Calculation
            if(!$request->no_calculater)
            $taxCalculator->calculateTax();
            if(($taxCalculator->_oldUnpayedAmount??0)>0)
            {
                throw new Exception("Old Demand Amount Of ".$taxCalculator->_oldUnpayedAmount." Not Cleard");
            }
            DB::beginTransaction();
            $createSaf = $saf->store($request);                                         // Store SAF Using Model function 
            $safId = $createSaf->original['safId'];
            $safNo = $createSaf->original['safNo'];

            // SAF Owner Details
            if ($request['owner']) {
                $ownerDetail = $request['owner'];
                if ($request->assessmentType == 'Mutation')                             // In Case of Mutation Avert Existing Owner Detail
                    $ownerDetail = collect($ownerDetail)->where('propOwnerDetailId', null);
                foreach ($ownerDetail as $ownerDetails) {
                    $mOwner->addOwner($ownerDetails, $safId, $user_id);
                }
            }

            // Floor Details
            if ($request->propertyType != 4) {
                if ($request['floor']) {
                    $floorDetail = $request['floor'];
                    foreach ($floorDetail as $floorDetails) {
                        $floor = new PropActiveSafsFloor();
                        $floor->addfloor($floorDetails, $safId, $user_id);
                    }
                }
            }
            $this->sendToWorkflow($createSaf, $user_id);
            DB::commit();
            return responseMsgs(true, "Successfully Submitted Your Application Your SAF No. $safNo", [
                "safNo" => $safNo,
                "applyDate" => ymdToDmyDate($mApplyDate),
                "safId" => $safId,
                "calculatedTaxes" => (!$request->no_calculater ? $taxCalculator->_GRID: []),
            ], "010102", "1.0", "1s", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010102", "1.0", "1s", "POST", $request->deviceId);
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
                'hasPreviousHoldingNo' => true,
                'previousHoldingId' => implode(",", $previousHoldingIds),
                'holdingNo' => implode(",", $req->holdingNoLists)
            ]);
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
