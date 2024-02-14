<?php

namespace App\Repository\Property\Concrete;

use App\EloquentModels\Common\ModelWard;
use App\Http\Controllers\Service\IdGeneratorController;
use App\Models\Property\PropActiveDeactivationRequest;
// use App\Models\Property\PropDeactivationReqInbox;
use App\Models\Property\PropDeactivationRequest;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\IPropertyDeactivate;
use App\Traits\Property\SafDetailsTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyDeactivate implements IPropertyDeactivate
{
    use SafDetailsTrait;
    /**
     * | Created On -19-11-2022
     * | Created By - Sandeep Bara
     * -----------------------------------------------------------------------------------------
     * | Property Deactivation WorkFlow 
     * | status (Open)
     */

    private $_COMMON_FUNCTION;
    private $_PROPERTY_CONSTAINT;
    private $_MODEL_WARD;
    private $_WF_MASTER_ID;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_DOC_PATH;
    protected $_MODULE_CONSTAINT;
    protected $_WORKFLOW_TRACK;
    protected $_APPLICATION_NO_CONST;
    protected $_ID_GENERATOR;
    public function __construct()
    {
        $this->_COMMON_FUNCTION     =   new CommonFunction();
        $this->_MODEL_WARD          =   new ModelWard();
        $this->_WORKFLOW_TRACK      =   new WorkflowTrack();
        $this->_ID_GENERATOR        =   new IdGeneratorController();
        $this->_WF_MASTER_ID        =   Config::get('workflow-constants.PROPERTY_DEACTIVATION_MASTER_ID');
        $this->_MODULE_CONSTAINT    =   Config::get('module-constants');
        $this->_MODULE_ID           =   Config::get('module-constants.PROPERTY_MODULE_ID');
        $this->_PROPERTY_CONSTAINT  =   Config::get("PropertyConstaint");
        $this->_APPLICATION_NO_CONST =  $this->_PROPERTY_CONSTAINT["DEACTIV_PARAM_ID"] ?? 0;
        $this->_REF_TABLE           = null;
        $this->_DOC_PATH            = null;
    }
    /**
     * | Searching the valide Property With New Holding No
         query cost(**)
     * |
     * |-----------------------------------------------------------------------
     * | @var refUser    = Auth()->user()       | loging user Data
     * | @var refUserId  = refUser->id          | loging user Id
     * | @var refUlbId   = refUser->ulb_id      | loging user Ulb Id
     * |
     * | @var mHoldingNo = strtoupper(request->holdingNo) | request data
     * | @var property   
     */
    public function readHoldigbyNo(Request $request)
    {
        try {
            $refUser    = Auth()->user();
            $refUserId  = $refUser->id;
            $refUlbId   = $refUser->ulb_id;
            $rules["holdingNo"] = "required|string";
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $mHoldingNo = strtoupper($request->holdingNo);
            // DB::enableQueryLog();
            $property = PropProperty::select(
                "id",
                "new_holding_no",
                "holding_no",
                "prop_address",
                DB::raw("owners.owner_name, owners.guardian_name, owners.mobile_no")
            )
                ->leftjoin(
                    DB::raw("(SELECT DISTINCT(property_id) AS property_id,
                                                        STRING_AGG(owner_name, ',') AS owner_name,
                                                        STRING_AGG(guardian_name, ',') AS guardian_name,
                                                        STRING_AGG(mobile_no::text, ',') AS mobile_no
                                                    FROM prop_owners 
                                                    JOIN prop_properties ON prop_properties.id = prop_owners.property_id
                                                        AND  prop_properties.status =1 and upper(prop_properties.new_holding_no) = '$mHoldingNo'
                                                        AND prop_properties.ulb_id = $refUlbId
                                                    WHERE prop_owners.status =1 
                                                    GROUP BY property_id 
                                                    )owners"),
                    function ($join) {
                        $join->on("owners.property_id", "prop_properties.id");
                    }
                )
                ->whereRaw("UPPER(prop_properties.new_holding_no) = ?", [$mHoldingNo])
                ->where("prop_properties.ulb_id", $refUlbId)
                ->get();
            // dd(DB::getQueryLog());
            if (sizeOf($property) < 1) {
                throw new Exception("Holding Not Found");
            }
            $data['property'] = $property;
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    /**
     * | Apply The Property Deactivation Request With Proper Comment And Document
        query(***)
     * |
     * |------------------------------------------------------------------------------
     * | @var refUser    = Auth()->user()           | loging user data
     * | @var refUserId  = refUser->id              | loging user Id
     * | @var refUlbId   = refUser->ulb_id          | loging user Ulb Id
     * | @var mRegex     = '/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/'  | rejex
     * | @var mNowDate   = Carbon::now()->format("Y-m-d")       | current date
     * | @var refWorkflowId = $this->_WF_MASTER_ID  | workflowId
     * | @var mUserType  = this->_common->userType(refWorkflowId) | loging user short Role Name On Currert Workflow
     * | @var workflowId  (model)WfWorkflow->id
     * |
     * | @var mProperty  = this->getPropertyById(propId)    | property data
     * | @var mRole      = this->_common->getUserRoll(refUserId, refUlbId, workflowId->wf_master_id)    | current user role Dtl
     * | @var init_finish = this->_common->iniatorFinisher(refUserId,refUlbId,refWorkflowId)            | determin the Initiator And Finisher Of The Workflow
     * | @var mOwrners = $this->getPropOwnerByProId(mProperty->id)      | request Property Owners Dtls
     * | 
     * | @var PropDeactivationRequest    = PropDeactivationRequest (model)
     * |
     * |---------------------fuctions---------------------------------------------------------------
     * | this->_common->userType(refWorkflowId)
     * | this->getPropertyById(propId)
     * | this->_common->getUserRoll(refUserId, refUlbId, workflowId->wf_master_id)
     * | this->_common->iniatorFinisher(refUserId, refUlbId, refWorkflowId)
     * | this->getPropOwnerByProId(mProperty->id)
     */
    public function deactivatProperty(Request $request)
    {
        try {
            $propId = $request->propertyId;
            $refUser    = Auth()->user();
            $refUserId  = $refUser->id;
            $refUlbId   = $refUser->ulb_id;
            $refWorkflowId = $this->_WF_MASTER_ID;

            $mUserType  = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            if (!in_array(strtoupper($mUserType), ['BO', "SUPER ADMIN", "ONLINE"])) {
                throw new Exception("You Are Not Authorized For Deactivate Property!");
            }
            if (!$refWorkflowId) {
                throw new Exception("Workflow Not Available");
            }

            $mProperty  = PropProperty::where("status", 1)->find($propId);
            if (!$mProperty) {
                throw new Exception("Property Not Found");
            }
            if (!$refUlbId) {
                $refUlbId = $mProperty->ulb_id;
            }
            $init_finish = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            if (!$init_finish) {
                throw new Exception("Full Work Flow Not Desigen Properly. Please Contact Admin !!!...");
            } elseif (!$init_finish["initiator"]) {
                throw new Exception("Initiar Not Available. Please Contact Admin !!!...");
            }
            $PropDeactivationRequest    = PropActiveDeactivationRequest::select("*")
                ->where("property_id", $propId)
                ->where("status", 1)
                ->orderBy("id", "DESC")
                ->first();
            if ($PropDeactivationRequest) {
                throw new Exception("Request is already submited. Please check request status with APPN - $PropDeactivationRequest->application_no !....");
            }
            if ($request->getMethod() == "POST") {

                DB::beginTransaction();

                $PropDeactivationRequest    = new PropActiveDeactivationRequest;
                $PropDeactivationRequest->ulb_id         = $mProperty->ulb_id;
                $PropDeactivationRequest->property_id    = $mProperty->id;
                $PropDeactivationRequest->emp_detail_id  = $refUserId;
                $PropDeactivationRequest->remarks        = $request->comments;
                $PropDeactivationRequest->workflow_id    = $refWorkflowId;
                $PropDeactivationRequest->max_level_attained = $init_finish["initiator"]["serial_no"] ?? null;
                $PropDeactivationRequest->current_role       = $init_finish["initiator"]["id"] ?? null;
                $PropDeactivationRequest->initiator_role     = $init_finish["initiator"]["id"] ?? null;
                $PropDeactivationRequest->finisher_role      = $init_finish["finisher"]["id"] ?? null;

                $id_request = new Request(["ulbId" => $refUlbId, "paramId" => $this->_APPLICATION_NO_CONST]);
                $id_respons = $this->_ID_GENERATOR->idGenerator($id_request);
                $PropDeactivationRequest->application_no  = $id_respons->original["data"];

                $PropDeactivationRequest->save();
                $DeactivationReqId = $PropDeactivationRequest->id;
                if ($DeactivationReqId) {
                    $file = $request->file("document");
                    $file_ext = $data["exten"] = $file->getClientOriginalExtension();
                    $fileName = "Deactive/$DeactivationReqId.$file_ext";
                    $filePath = $this->uplodeFile($file, $fileName);

                    $PropDeactivationRequest->documents = $filePath;
                    $PropDeactivationRequest->update();
                }
                DB::commit();

                return  responseMsgs(true, "APN: $PropDeactivationRequest->application_no", [], "00002", "1.0", "", "POST", $request->deviceId);
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $request->all(), "00002", "1.0", "", "POST", $request->deviceId);
        }
    }

    public function inbox(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_ID;
            $refWorkflowMstrId     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWorkflowMstrId) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mRole = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            if (!$mRole) {
                throw new Exception("You Are Not Authorized For This Action");
            }

            if ($mRole->is_initiator) {
                $mWardPermission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }
            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);
            $mWardIds = implode(',', $mWardIds);
            $mRoleId = $mRole->role_id;
            $inputs = $request->all();
            if (isset($inputs['wardNo']) && trim($inputs['wardNo']) && $inputs['wardNo'] != "ALL") {
                $mWardIds = $inputs['wardNo'];
            }
            // DB::enableQueryLog();          
            $mProperty = PropActiveDeactivationRequest::select(
                "prop_active_deactivation_requests.id",
                "prop_active_deactivation_requests.application_no",
                "prop_active_deactivation_requests.workflow_id",
                "properties.holding_no",
                "properties.new_holding_no",
                "properties.owner_name",
                "properties.guardian_name",
                "properties.mobile_no",
                "properties.email_id",
            )
                ->join(
                    DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                            STRING_AGG(guardian_name,',') AS guardian_name,
                                            STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                            STRING_AGG(email,',') AS email_id,
                                            prop_properties.id,holding_no,new_holding_no
                                        FROM prop_properties  
                                        LEFT JOIN prop_owners ON prop_properties.id = prop_owners.property_id AND prop_owners.status=1
                                        WHERE prop_properties.status =1 AND prop_properties.ulb_id=$refUlbId
                                        AND prop_properties.ward_mstr_id IN ($mWardIds)
                                        GROUP BY prop_properties.id,holding_no,new_holding_no
                                        )properties"),
                    function ($join) use ($inputs) {
                        $join = $join->on("properties.id", "prop_active_deactivation_requests.property_id");
                        if (isset($inputs['key']) && trim($inputs['key'])) {
                            $key = trim($inputs['key']);
                            $join = $join->where(function ($query) use ($key) {
                                $query->orwhere('properties.holding_no', 'ILIKE', '%' . $key . '%')
                                    ->orwhere("prop_active_deactivation_requests.application_no", '%' . $key . '%')
                                    ->orwhere('properties.new_holding_no', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.owner_name', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.guardian_name', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.mobile_no', 'ILIKE', '%' . $key . '%');
                            });
                        }
                    }
                )
                ->where("prop_active_deactivation_requests.ulb_id", $refUlbId)
                ->where('prop_active_deactivation_requests.is_parked', false)
                ->where('prop_active_deactivation_requests.status', 1)
                ->where('prop_active_deactivation_requests.current_role', $mRoleId);
            if (isset($inputs['formDate']) && isset($inputs['toDate']) && trim($inputs['formDate']) && $inputs['toDate']) {
                $mProperty = $mProperty
                    ->whereBetween('prop_active_deactivation_requests.created_at::date', [$inputs['formDate'], $inputs['formDate']]);
            }
            $mProperty = $mProperty
                ->get();
            // dd(DB::getQueryLog());
            $data = $mProperty;
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
    public function outbox(Request $request)
    {
        try {
            $user = Auth()->user();
            $refUserId = $user->id;
            $refUlbId = $user->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_ID;
            $refWorkflowMstrId     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWorkflowMstrId) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mRole = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            if (!$mRole) {
                throw new Exception("You Are Not Authorized");
            }
            if ($mRole->is_initiator || in_array(strtoupper($mUserType), ["JSK", "SUPER ADMIN", "ADMIN", "TL", "PMU", "PM"])) {
                $mWardPermission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);
            $mWardIds = implode(',', $mWardIds);
            $mRoleId = $mRole->role_id;
            $inputs = $request->all();
            // DB::enableQueryLog();
            $mProperty = PropActiveDeactivationRequest::select(
                "prop_active_deactivation_requests.id",
                "prop_active_deactivation_requests.application_no",
                "properties.holding_no",
                "properties.new_holding_no",
                "properties.owner_name",
                "properties.guardian_name",
                "properties.mobile_no",
                "properties.email_id",
            )
                ->join(
                    DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                            STRING_AGG(guardian_name,',') AS guardian_name,
                                            STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                            STRING_AGG(email,',') AS email_id,
                                            prop_properties.id,holding_no,new_holding_no
                                        FROM prop_properties  
                                        LEFT JOIN prop_owners ON prop_properties.id = prop_owners.property_id AND prop_owners.status=1
                                        WHERE prop_properties.status =1 AND prop_properties.ulb_id=$refUlbId
                                        AND prop_properties.ward_mstr_id IN ($mWardIds)
                                        GROUP BY prop_properties.id,holding_no,new_holding_no
                                        )properties"),
                    function ($join) use ($inputs) {
                        $join = $join->on("properties.id", "prop_active_deactivation_requests.property_id");
                        if (isset($inputs['key']) && trim($inputs['key'])) {
                            $key = trim($inputs['key']);
                            $join = $join->where(function ($query) use ($key) {
                                $query->orwhere('properties.holding_no', 'ILIKE', '%' . $key . '%')
                                    ->orwhere("prop_active_deactivation_requests.application_no", '%' . $key . '%')
                                    ->orwhere('properties.new_holding_no', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.owner_name', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.guardian_name', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.mobile_no', 'ILIKE', '%' . $key . '%');
                            });
                        }
                    }
                )
                ->where("prop_active_deactivation_requests.ulb_id", $refUlbId)
                ->where('prop_active_deactivation_requests.is_parked', false)
                ->where('prop_active_deactivation_requests.status', 1)
                ->where('prop_active_deactivation_requests.current_role', "<>", $mRoleId);
            if (isset($inputs['formDate']) && isset($inputs['toDate']) && trim($inputs['formDate']) && $inputs['toDate']) {
                $mProperty = $mProperty
                    ->whereBetween('prop_active_deactivation_requests.created_at::date', [$inputs['formDate'], $inputs['formDate']]);
            }
            $mProperty = $mProperty
                ->get();
            // dd(DB::getQueryLog());
            $data = $mProperty;
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
    public function readDeactivationReq(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_ID;
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $rules = [
                "applicationId" => "required|int",
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $refRequestData =  PropActiveDeactivationRequest::find($request->applicationId);
            if (!$refRequestData) {
                throw new Exception("Data Not Found!");
            }

            $pendingAt  = $refRequestData->current_role;
            $mworkflowRoles = $this->_COMMON_FUNCTION->getWorkFlowAllRoles($refUserId, $refUlbId, $refWorkflowId, true);
            $mileSton = $this->_COMMON_FUNCTION->sortsWorkflowRols($mworkflowRoles);

            $refProperty = $this->getPropertyById($refRequestData->property_id);
            $refProperty->old_ward_no = $refProperty->ward_no;
            $refOwners   = $this->getPropOwnerByProId($refRequestData->property_id);
            $refTimeLine =  $this->_WORKFLOW_TRACK->getTracksByRefId("prop_active_deactivation_requests", $refRequestData->id);

            // Trait function to get Basic Details
            $basicElement = [
                'headerTitle' => "Basic Details",
                "data" => $this->generateBasicDetails($refProperty)
            ];
            // Trait function to get Property Details
            $propertyElement = [
                'headerTitle' => "Property Details & Address",
                'data' =>  $this->generatePropertyDetails($refProperty)
            ];
            // Trait function to generate corresponding address details
            $corrElement = [
                'headerTitle' => 'Corresponding Address',
                'data' => $this->generateCorrDtls($refProperty),
            ];
            // Trait function to generate Electricity Details
            $electElement = [
                'headerTitle' => 'Electricity & Water Details',
                'data' =>  $this->generateElectDtls($refProperty),
            ];
            $fullDetailsData['application_no'] = $refProperty->application_no;
            $fullDetailsData['apply_date'] = $refRequestData->apply_date;
            $fullDetailsData['fullDetailsData']['dataArray'] = collect([$basicElement, $propertyElement, $corrElement, $electElement]);
            // Table Array
            // Owner Details
            $ownerElement = [
                'headerTitle' => 'Owner Details',
                'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Mobile No", "Aadhar", "PAN", "Email", "IsArmedForce", "isSpeciallyAbled"],
                'tableData' => $this->generateOwnerDetails($refOwners)
            ];
            // Floor Details
            $getFloorDtls = (new PropFloor())->getPropFloors($refProperty->id);      // Model Function to Get Floor Details

            $floorElement = [
                'headerTitle' => 'Floor Details',
                'tableHead' => ["#", "Floor", "Usage Type", "Occupancy Type", "Construction Type", "Build Up Area", "From Date", "Upto Date"],
                'tableData' => $this->generateFloorDetails($getFloorDtls)
            ];
            $fullDetailsData['fullDetailsData']['tableArray'] = Collect([$ownerElement, $floorElement]);
            // Card Detail Format
            $cardElement = [
                'headerTitle' => "About Property",
                'data' => $this->generateCardDetails($refProperty, $refOwners)
            ];
            $fullDetailsData['fullDetailsData']['cardArray'] = Collect($cardElement);
            $fullDetailsData['levelComment'] = $refTimeLine;
            $fullDetailsData['citizenComment'] = $this->citizenComments("prop_active_deactivation_requests", $refRequestData->id);
            $fullDetailsData['roleDetails'] = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);

            $metaReqs['customFor'] = 'PROPERTY DEACTIVATION';
            $metaReqs['wfRoleId'] = $fullDetailsData['roleDetails']['role_id'];
            $metaReqs['workflowId'] = $refWorkflowId;
            $metaReqs['lastRoleId'] = $refRequestData->max_level_attained;


            $request->request->add($metaReqs);

            $fullDetailsData['timelineData'] = collect($request);

            $custom = (new \App\Models\CustomDetail())->getCustomDetails($request);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];
            // $data=[
            //     "requestData"=> $refRequestData,
            //     "property"   => $refProperty,
            //     "owners"     => $refOwners,
            //     'remarks'    => $refTimeLine,
            //     "userType"   => $mUserType,
            //     "roles"      => $mileSton,
            //     "pendingAt"  => $pendingAt,
            // ];
            return responseMsg(true, "", remove_null($fullDetailsData));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    public function specialInbox(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_ID;
            $refWorkflowMstrId     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWorkflowMstrId) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mRole = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            if (!$mRole) {
                throw new Exception("You Are Not Authorized For This Action");
            }

            if ($mRole->is_initiator) {
                $mWardPermission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }
            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);
            $mWardIds = implode(',', $mWardIds);
            $mRoleId = $mRole->role_id;
            $inputs = $request->all();
            if (isset($inputs['wardNo']) && trim($inputs['wardNo']) && $inputs['wardNo'] != "ALL") {
                $mWardIds = $inputs['wardNo'];
            }
            // DB::enableQueryLog();          
            $mProperty = PropActiveDeactivationRequest::select(
                "prop_active_deactivation_requests.id",
                "prop_active_deactivation_requests.application_no",
                "properties.holding_no",
                "properties.new_holding_no",
                "properties.owner_name",
                "properties.guardian_name",
                "properties.mobile_no",
                "properties.email_id",
            )
                ->join(
                    DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                            STRING_AGG(guardian_name,',') AS guardian_name,
                                            STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                            STRING_AGG(email,',') AS email_id,
                                            prop_properties.id,holding_no,new_holding_no
                                        FROM prop_properties  
                                        LEFT JOIN prop_owners ON prop_properties.id = prop_owners.property_id AND prop_owners.status=1
                                        WHERE prop_properties.status =1 AND prop_properties.ulb_id=$refUlbId
                                        AND prop_properties.ward_mstr_id IN ($mWardIds)
                                        GROUP BY prop_properties.id,holding_no,new_holding_no
                                        )properties"),
                    function ($join) use ($inputs) {
                        $join = $join->on("properties.id", "prop_active_deactivation_requests.property_id");
                        if (isset($inputs['key']) && trim($inputs['key'])) {
                            $key = trim($inputs['key']);
                            $join = $join->where(function ($query) use ($key) {
                                $query->orwhere('properties.holding_no', 'ILIKE', '%' . $key . '%')
                                    ->orwhere("prop_active_deactivation_requests.application_no", '%' . $key . '%')
                                    ->orwhere('properties.new_holding_no', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.owner_name', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.guardian_name', 'ILIKE', '%' . $key . '%')
                                    ->orwhere('properties.mobile_no', 'ILIKE', '%' . $key . '%');
                            });
                        }
                    }
                )
                ->where("prop_active_deactivation_requests.ulb_id", $refUlbId)
                ->where('prop_active_deactivation_requests.is_parked', FALSE)
                ->where('prop_active_deactivation_requests.status', 1)
                // ->where('prop_active_deactivation_requests.current_role',$mRoleId)
                ->where('prop_active_deactivation_requests.is_escalate', TRUE);
            if (isset($inputs['formDate']) && isset($inputs['toDate']) && trim($inputs['formDate']) && $inputs['toDate']) {
                $mProperty = $mProperty
                    ->whereBetween('prop_active_deactivation_requests.created_at::date', [$inputs['formDate'], $inputs['formDate']]);
            }
            $mProperty = $mProperty
                ->get();
            // dd(DB::getQueryLog());
            $data = $mProperty;
            return responseMsgs(true, "Data Fetched", remove_null($data), "00008", "1.0", "251ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    #---------------------Core Function--------------------------------------------------------
    public function getPropDtlByHoldingNo(string $holdingNo, $ulbId)
    {
        try {
            $mProperty = PropProperty::select("*")
                ->where("new_holding_no", $holdingNo)
                ->where("status", 1)
                ->where("ulb_id", $ulbId)
                ->first();
            return $mProperty;
        } catch (Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }
    public function getPropertyById($id)
    {
        try {
            // DB::enableQueryLog();
            $application = PropProperty::select(
                "prop_properties.*",
                "ref_prop_ownership_types.ownership_type",
                "ref_prop_types.property_type",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, new_ward.ward_name as new_ward_no")
            )
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "prop_properties.ward_mstr_id");
                })
                ->leftjoin("ulb_ward_masters AS new_ward", function ($join) {
                    $join->on("new_ward.id", "=", "prop_properties.new_ward_mstr_id");
                })
                ->leftjoin("ref_prop_ownership_types", "ref_prop_ownership_types.id", "prop_properties.ownership_type_mstr_id")
                ->leftjoin("ref_prop_types", "ref_prop_types.id", "prop_properties.prop_type_mstr_id")
                ->where('prop_properties.id', $id)
                ->first();
            // dd(DB::getQueryLog());
            return $application;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getPropOwnerByProId($propId)
    {
        try {
            $mOwrners = PropOwner::select("*")
                ->where("property_id", $propId)
                ->where("status", 1)
                ->get();
            return $mOwrners;
        } catch (Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }
    public function uplodeFile($file, $custumFileName)
    {
        $filePath = $file->storeAs('uploads/Property', $custumFileName, 'public');
        return  $filePath;
    }
    public function readDocumentPath($path)
    {
        $path = (config('app.url') . '/api/getImageLink?path=' . $path);
        return $path;
    }

    public function citizenComments($mRefTable, $tableId)
    {
        try {
            $data = WorkflowTrack::select(
                'workflow_tracks.ref_table_dot_id AS referenceTable',
                'workflow_tracks.ref_table_id_value AS applicationId',
                'workflow_tracks.message',
                'workflow_tracks.track_date',
                'workflow_tracks.forward_date',
                'workflow_tracks.forward_time',
                'u.user_name as commentedBy'
            )
                ->where('ref_table_dot_id', $mRefTable)
                ->where('ref_table_id_value', $tableId)
                ->whereNOtNull('citizen_id')
                ->Join('users as u', 'u.id', '=', 'workflow_tracks.citizen_id')
                ->get();
            return $data;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
