<?php

namespace App\Http\Controllers\Water;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Water\NewConnectionController;
use App\Models\Water\WaterApplication as WaterWaterApplication;
use App\Models\Water\WaterTran;
use App\Models\Workflows\WfRole;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Repository\Water\Interfaces\iNewConnection;
use App\Repository\Water\Interfaces\IWaterNewConnection;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class WaterApplication extends Controller
{
    use Workflow;
    private $Repository;
    private $_NewConnectionController;
    public function __construct(IWaterNewConnection $Repository, iNewConnection $newConnection)
    {
        $this->Repository = $Repository;
        $this->_NewConnectionController = new NewConnectionController($newConnection);
    }

    public function getCitizenApplication(Request $request)
    {
        try {
            $returnValue = $this->Repository->getCitizenApplication($request);
            return responseMsg(true, "", remove_null($returnValue));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function handeRazorPay(Request $request)
    {
        return $this->Repository->handeRazorPay($request);
    }
    public function readTransectionAndApl(Request $request)
    {
        return $this->Repository->readTransectionAndApl($request);
    }
    public function documentUpload(Request $request)
    {
        return $this->Repository->documentUpload($request);
    }
    public function getUploadDocuments(Request $request)
    {
        return $this->Repository->getUploadDocuments($request);
    }
    public function calWaterConCharge(Request $request)
    {
        return $this->Repository->calWaterConCharge($request);
    }

    #--------------------------------------------------------Dashbording----------------------------------------------------------#

    /**
     * | Get All application Applied from jsk
     * | @param request
     * | @var mWaterApplication
     * | @return returnData
        | Serial No : 01
        | Not Working
     */
    public function getJskAppliedApplication(Request $request)
    {
        try {
            $user = authUser($request);
            if (!in_array($user->user_type, ['JSK', 'TC'])) {
                $canView = false;
            }
            $mWaterApplication  = new WaterWaterApplication();
            $mWaterTran         = new WaterTran();
            $mWfWorkflow        = new WfWorkflow();
            $refConnectionType  = Config::get("waterConstaint.CONNECTION_TYPE");
            $wfMstId            = Config::get("workflow-constants.WATER_MASTER_ID");
            $rfTransMode        = Config::get("payment-constants.PAYMENT_OFFLINE_MODE.5");
            $currentDate        = Carbon::now()->format('Y-m-d');
            $userType           = $user->user_type;

            $applicationDetails = $mWaterApplication->getJskAppliedApplications($request)->get();
            $transactionDetails = $mWaterTran->tranDetailByDate($currentDate, $userType, $rfTransMode);
            $workflow = $mWfWorkflow->getulbWorkflowId($wfMstId, $user->ulb_id);
            $request->merge([
                'workflowId'    => $workflow->id,
            ]);
            $roleDetails = $this->getRole($request);
            if (!collect($roleDetails)->first()) {
                $returnData['canView'] = $canView ?? false;
                return responseMsgs(false, "Daccess Denied! No Role ", $returnData, "", "01", ".ms", "POST", "");
            }
            $roleData = WfRole::findOrFail($roleDetails['wf_role_id']);
            $returnApplicationDetails = collect($applicationDetails)->map(function ($value) {
                return [
                    'id'                    => $value['id'],
                    'application_no'        => $value['application_no'],
                    'apply_date'            => $value['apply_date'],
                    'connection_type_id'    => $value['connection_type_id'],
                    'applicantname'         => $value['applicantname'],
                ];
            });

            $returnTransactionDetails = collect($transactionDetails)->map(function ($value) {
                return [
                    'id'            => $value['id'],
                    'tran_no'       => $value['tran_no'],
                    'payment_mode'  => $value['payment_mode'],
                    'amount'        => $value['amount'],
                    'tran_date'     => $value['tran_date']
                ];
            });

            $applicationData = [
                'applicationCount'  => collect($applicationDetails)->count(),
                'newConnectionList' => collect($applicationDetails)->where('connection_type_id', $refConnectionType['NEW_CONNECTION'])->count(),
                'RegulizationList'  => collect($applicationDetails)->where('connection_type_id', $refConnectionType['REGULAIZATION'])->count()
            ];

            $amountData = [
                'totalCollection'  => collect($transactionDetails)->pluck('amount')->sum(),
                'chequeAmount'     => collect($transactionDetails)->where('payment_mode', 'Cheque')->pluck('amount')->sum(),
                'onlineAmount'     => collect($transactionDetails)->where('payment_mode', 'Online')->pluck('amount')->sum(),
                'cashAmount'       => collect($transactionDetails)->where('payment_mode', 'Cash')->pluck('amount')->sum(),
                'ddAmount'         => collect($transactionDetails)->where('payment_mode', 'DD')->pluck('amount')->sum(),
                'neftAmount'       => collect($transactionDetails)->where('payment_mode', 'Neft')->pluck('amount')->sum()
            ];

            $paymentModeCount = [
                'totalCollectionCount' => collect($transactionDetails)->count(),
                'chequeCollection'     => collect($transactionDetails)->where('payment_mode', 'Cheque')->count(),
                'onlineCollection'     => collect($transactionDetails)->where('payment_mode', 'Online')->count(),
                'cashCollection'       => collect($transactionDetails)->where('payment_mode', 'Cash')->count(),
                'ddCollection'         => collect($transactionDetails)->where('payment_mode', 'DD')->count(),
            ];

            $returnData = [
                'refNumber'             => 1,                               // Static
                'canView'               => $canView ?? true,
                'userDetails'           => $user,
                'roleId'                => $roleDetails['wf_role_id'],
                'roleName'              => $roleData['role_name'],
                'applicationDetails'    => $applicationData,
                'amountData'            => $amountData,
                'transactionCount'      => $paymentModeCount,
                'applicationList'       => $returnApplicationDetails,
                'transactionDetails'    => $returnTransactionDetails
            ];
            return responseMsgs(true, "dashbord Data!", remove_null($returnData), "", "02", ".ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | Workflow dasboarding details
     * | @param request 
        | Serial No : 02
        | Working
     */
    public function workflowDashordDetails(Request $request)
    {
        try {
            $canView = true;
            $user = authUser($request);
            if (in_array($user->user_type, ['JSK', 'TC'])) {
                $canView = false;
            }
            $ulbId                  = $user->ulb_id;
            $wfMstId                = Config::get("workflow-constants.WATER_MASTER_ID");
            $moduleId               = Config::get("module-constants.WATER_MODULE_ID");
            $WorkflowTrack          = new WorkflowTrack();
            $mWfWorkflow            = new WfWorkflow();
            $mWaterWaterApplication = new WaterWaterApplication();
            $mWfWorkflowRoleMaps    = new WfWorkflowrolemap();

            $workflow = $mWfWorkflow->getulbWorkflowId($wfMstId, $ulbId);
            $request->merge([
                'workflowId'    => $workflow->id,
                'ulbId'         => $ulbId,
                'moduleId'      => $moduleId
            ]);
            $roleDetails = $this->getRole($request);
            if (!collect($roleDetails)->first()) {
                $returnData['canView'] = $canView;
                return responseMsgs(false, "Access Denied! No Role", $returnData, "", "01", ".ms", "POST", "");
            }
            $roleId = $roleDetails['wf_role_id'];
            $occupiedWards = $this->getWardByUserId($user->id)->pluck('ward_id');

            $dateWiseData = $WorkflowTrack->getWfDashbordData($request)->get();
            $applicationCount = $mWaterWaterApplication->getApplicationByRole($roleId)
                ->whereIn('ward_id', $occupiedWards)
                ->where('ulb_id', $ulbId)
                ->where('workflow_id', $workflow->id)
                ->count('id');
            $roleData = WfRole::findOrFail($roleId);
            $returnData = [
                'canView'               => $canView ?? true,
                'userDetails'           => $user,
                'roleId'                => $roleId,
                'roleName'              => $roleData['role_name'],
                'todayForwardCount'     => collect($dateWiseData)->where('sender_role_id', $roleId)->count(),
                'todayReceivedCount'    => collect($dateWiseData)->where('receiver_role_id', $roleId)->count(),
                'pendingApplication'    => $applicationCount
            ];

            return responseMsgs(true, "", remove_null($returnData), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }
}
