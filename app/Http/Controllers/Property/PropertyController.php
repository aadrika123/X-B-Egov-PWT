<?php

namespace App\Http\Controllers\Property;

use App\EloquentModels\Common\ModelWard;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ThirdPartyController;
use App\MicroServices\DocUpload;
use App\Models\ActiveCitizen;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Property\PropActiveConcession;
use App\Models\Property\PropActiveHarvesting;
use App\Models\Property\PropActiveObjection;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropDemand;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropOwnerUpdateRequest;
use App\Models\Property\PropProperty;
use App\Models\Property\PropPropertyUpdateRequest;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsDemand;
use App\Models\Property\PropSafsOwner;
use App\Models\Property\PropTransaction;
use App\Models\User;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Pipelines\SearchHolding;
use App\Pipelines\SearchPtn;
use App\Repository\Common\CommonFunction;
use App\Traits\Property\Property;
use App\Traits\Property\SAF;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On - 11-03-2023
 * | Created By - Mrinal Kumar
 * | Status - Open
 */

class PropertyController extends Controller
{
    use SAF;
    use Property;
    /**
     * | Send otp for caretaker property
     */
    public function caretakerOtp(Request $req)
    {
        try {
            $mPropOwner = new PropOwner();
            $ThirdPartyController = new ThirdPartyController();
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            if ($req->moduleId == $propertyModuleId) {
            }
            $propDtl = app(Pipeline::class)
                ->send(PropProperty::query()->where('status', 1))
                ->through([
                    SearchHolding::class,
                    SearchPtn::class
                ])
                ->thenReturn()
                ->first();

            if (!isset($propDtl))
                throw new Exception('Property Not Found');
            $propOwners = $mPropOwner->getOwnerByPropId($propDtl->id);
            $firstOwner = collect($propOwners)->first();
            if (!$firstOwner)
                throw new Exception('Owner Not Found');
            $ownerMobile = $firstOwner->mobileNo;

            $myRequest = new \Illuminate\Http\Request();
            $myRequest->setMethod('POST');
            $myRequest->request->add(['mobileNo' => $ownerMobile]);
            $response = $ThirdPartyController->sendOtp($myRequest);

            $response = collect($response)->toArray();
            $data['otp'] = $response['original']['data'];
            $data['mobileNo'] = $ownerMobile;

            return responseMsgs(true, "OTP send successfully", $data, '010801', '01', '623ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Care taker property tag
     */
    public function caretakerPropertyTag(Request $req)
    {
        $req->validate([
            'holdingNo' => 'required_without:ptNo|max:255',
            'ptNo' => 'required_without:holdingNo|numeric',
        ]);
        try {
            $userId = authUser($req)->id;
            $activeCitizen = ActiveCitizen::findOrFail($userId);

            $propDtl = app(Pipeline::class)
                ->send(PropProperty::query()->where('status', 1))
                ->through([
                    SearchHolding::class,
                    SearchPtn::class
                ])
                ->thenReturn()
                ->first();

            if (!isset($propDtl))
                throw new Exception('Property Not Found');

            $allPropIds = $this->ifPropertyExists($propDtl->id, $activeCitizen);
            $activeCitizen->caretaker = $allPropIds;
            $activeCitizen->save();

            return responseMsgs(true, "Property Tagged!", '', '010801', '01', '623ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Function if Property Exists
     */
    public function ifPropertyExists($propId, $activeCitizen)
    {
        $propIds = collect(explode(',', $activeCitizen->caretaker));
        $propIds->push($propId);
        return $propIds->implode(',');
    }

    /**
     * | Logged in citizen Holding & Saf
     */
    public function citizenHoldingSaf(Request $req)
    {
        $req->validate([
            'type' => 'required|In:holding,saf,ptn',
            'ulbId' => 'required|numeric'
        ]);
        try {
            $citizenId = authUser($req)->id;
            $ulbId = $req->ulbId;
            $type = $req->type;
            $mPropSafs = new PropSaf();
            $mPropActiveSafs = new PropActiveSaf();
            $mPropProperty = new PropProperty();
            $mActiveCitizenUndercare = new ActiveCitizenUndercare();
            $caretakerProperty =  $mActiveCitizenUndercare->getTaggedPropsByCitizenId($citizenId);

            if ($type == 'saf') {
                $data = $mPropActiveSafs->getCitizenSafs($citizenId, $ulbId);
                $msg = 'Citizen Safs';
            }

            if ($type == 'holding') {
                $data = $mPropProperty->getCitizenHoldings($citizenId, $ulbId);
                if ($caretakerProperty->isNotEmpty()) {
                    $propertyId = collect($caretakerProperty)->pluck('property_id');
                    $data2 = $mPropProperty->getNewholding($propertyId);
                    $data = $data->merge($data2);
                }
                $data = collect($data)->map(function ($value) {
                    if (isset($value['new_holding_no'])) {
                        return $value;
                    }
                })->filter()->values();
                $msg = 'Citizen Holdings';
            }

            if ($type == 'ptn') {
                $data = $mPropProperty->getCitizenPtn($citizenId, $ulbId);
                $msg = 'Citizen Ptn';

                if ($caretakerProperty->isNotEmpty()) {
                    $propertyId = collect($caretakerProperty)->pluck('property_id');
                    $data2 = $mPropProperty->getPtn($propertyId);
                    $data = $data->merge($data2);
                }
                $data = collect($data)->map(function ($value) {
                    if (isset($value['pt_no'])) {
                        return $value;
                    }
                })->filter()->values();
            }

            if ($data->isEmpty())
                throw new Exception('No Data Found');

            return responseMsgs(true, $msg, remove_null($data), '010801', '01', '623ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Property Basic Edit
     */
    public function basicPropertyEdit(Request $req)
    {
        try {
            $mPropProperty = new PropProperty();
            $mPropOwners = new PropOwner();
            $propId = $req->propertyId;
            $mOwners = $req->owner;

            $mreq = new Request(
                [
                    "new_ward_mstr_id" => $req->newWardMstrId,
                    "khata_no" => $req->khataNo,
                    "plot_no" => $req->plotNo,
                    "village_mauja_name" => $req->villageMauja,
                    "prop_pin_code" => $req->pinCode,
                    "building_name" => $req->buildingName,
                    "street_name" => $req->streetName,
                    "location" => $req->location,
                    "landmark" => $req->landmark,
                    "prop_address" => $req->address,
                    "corr_pin_code" => $req->corrPin,
                    "corr_address" => $req->corrAddress
                ]
            );
            $mPropProperty->editProp($propId, $mreq);

            collect($mOwners)->map(function ($owner) use ($mPropOwners) {            // Updation of Owner Basic Details
                if (isset($owner['ownerId'])) {

                    $req = new Request([
                        'id' =>  $owner['ownerId'],
                        'owner_name' => $owner['ownerName'],
                        'guardian_name' => $owner['guardianName'],
                        'relation_type' => $owner['relation'],
                        'mobile_no' => $owner['mobileNo'],
                        'aadhar_no' => $owner['aadhar'],
                        'pan_no' => $owner['pan'],
                        'email' => $owner['email'],
                    ]);
                    $mPropOwners->editPropOwner($req);
                }
            });

            return responseMsgs(true, 'Data Updated', '', '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ================📝 Submit Basic Dtl Update Request 📝======================================
     * ||                 Created by :  Sandeep Bara
     * ||                 Date       :  01-11-2023
     * ||                 Status     :  Open
     * ||                 
     * ============================================================================================
     * 
     */
    public function basicPropertyEditV1(Request $req)
    {
        $controller = App::makeWith(ActiveSafController::class, ["iSafRepository" => app(\App\Repository\Property\Interfaces\iSafRepository::class)]);
        $response = $controller->masterSaf(new Request);
        if (!$response->original["status"]) {
            return $response;
        }
        $data = $response->original["data"];
        $categories = $data["categories"];
        $categoriesIds = collect($categories)->implode("id", ",");

        $construction_type = $data["construction_type"];
        $construction_typeIds = collect($construction_type)->implode("id", ",");

        $floor_type = $data["floor_type"];
        $floor_typeIds = collect($floor_type)->implode("id", ",");

        $occupancy_type = $data["occupancy_type"];
        $occupancy_typeIds = collect($occupancy_type)->implode("id", ",");

        $ownership_types = $data["ownership_types"];
        $ownership_typesIds = collect($ownership_types)->implode("id", ",");

        $property_type = $data["property_type"];
        $property_typeIds = collect($property_type)->implode("id", ",");

        $transfer_mode = $data["transfer_mode"];
        $transfer_modeIds = collect($transfer_mode)->implode("id", ",");

        $usage_type = $data["usage_type"];
        $usage_typeIds = collect($usage_type)->implode("id", ",");

        $ward_master = $data["ward_master"];
        $ward_masterIds = collect($ward_master)->implode("id", ",");
        $zoneWiseWardIds = collect($ward_master)->where("zone", $req->zone)->implode("id", ",");
        if (!$zoneWiseWardIds) {
            $zoneWiseWardIds = "0";
        }


        $zone = $data["zone"];
        $zoneIds = collect($zone)->implode("id", ",");


        $rules = [
            "propertyId" => "required|digits_between:1,9223372036854775807",
            "document" => "required|mimes:pdf,jpeg,png,jpg,gif",
            // "applicantName" => "required|regex:/^[A-Za-z.\s]+$/i",
            "applicantName" => "required",
            "applicantMarathi" => "required|string",

            "appartmentName"   => "nullable|string",
            "electricityConnection" => "nullable|string",
            "electricityCustNo" => "nullable|string",
            "electricityAccNo" => "nullable|string",
            "electricityBindBookNo" => "nullable|string",
            "electricityConsCategory" => "nullable|string",
            "buildingPlanApprovalNo" => "nullable|string",
            "buildingPlanApprovalDate" => "nullable|date|",

            "ownershipType" => "required|In:$ownership_typesIds",
            "zone" => "required|In:$zoneIds",
            "ward" => "required|In:$zoneWiseWardIds",

            "owner"      => "required|array",
            "owner.*.ownerId"      => "required|digits_between:1,9223372036854775807",
            // "owner.*.ownerName"      => "required|regex:/^[A-Za-z.\s]+$/i",
            "owner.*.ownerName"      => "required",
            "owner.*.ownerNameMarathi"  => "required|string",
            // "owner.*.guardianName"      => "required|regex:/^[A-Za-z.\s]+$/i",
            // "owner.*.guardianNameMarathi" => "required|string",
            "owner.*.relation" => "nullable|string|in:S/O,W/O,D/O,C/O",
            "owner.*.mobileNo" => "nullable|digits:10|regex:/[0-9]{10}/",
            "owner.*.aadhar" => "digits:12|regex:/[0-9]{12}/|nullable",
            "owner.*.pan" => "string|nullable",
            "owner.*.email" => "email|nullable",
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
        try {
            $refUser            = Auth()->user();
            if (!$refUser) {
                throw new Exception("Access denied");
            }
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id;
            $relativePath = Config::get('PropertyConstaint.PROP_UPDATE_RELATIVE_PATH');
            $docUpload = new DocUpload;
            $mPropProperty = new PropProperty();
            $mPropOwners = new PropOwner();
            $rPropProerty = new PropPropertyUpdateRequest();
            $rPropOwners = new PropOwnerUpdateRequest();
            $mCommonFunction = new CommonFunction();
            $propId = $req->propertyId;
            $prop = $mPropProperty->find($propId);
            if (!$prop) {
                throw new Exception("Data Not Found");
            }
            $refWorkflowId      = Config::get("workflow-constants.PROPERTY_UPDATE_ID");
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $pendingRequest = $prop->getUpdatePendingRqu()->first();
            if ($pendingRequest) {
                throw new Exception("Already Update Request Apply Which is Pending");
            }

            $refWorkflows       = $mCommonFunction->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $mUserType          = $mCommonFunction->userType($refWorkflowId);
            $document = $req->document;
            $refImageName = $req->propertyId . "-" . (strtotime(Carbon::now()->format('Y-m-dH:s:i')));
            $imageName = $docUpload->upload($refImageName, $document, $relativePath);
            $metaReqs["supportingDocument"] = ($relativePath . "/" . $imageName);

            $roadWidthType = $this->readRoadWidthType($req->roadType);


            $metaReqs['roadWidthType'] = $roadWidthType;

            $metaReqs['workflowId'] = $refWfWorkflow->id;       // inserting workflow id
            $metaReqs['initiatorRoleId'] = $refWorkflows['initiator']['id'];
            $metaReqs['finisherRoleId'] = $refWorkflows['finisher']['id'];
            $metaReqs['currentRole'] = $refWorkflows['initiator']['id'];
            $metaReqs['userId'] = $refUserId;
            $metaReqs['pendingStatus'] = 1;
            $req->merge($metaReqs);
            $req->merge(["isFullUpdate" => false]);
            $propRequest = $this->generatePropUpdateRequest($req, $prop, $req->isFullUpdate);
            $req->merge($propRequest);

            DB::beginTransaction();
            $updetReq = $rPropProerty->store($req);
            foreach ($req->owner as $val) {
                $testOwner = $mPropOwners->select("*")->where("id", $val["ownerId"])->where("property_id", $propId)->first();
                if (!$testOwner) {
                    throw new Exception("Invalid Owner Id Pass");
                }
                $newOwnerArr = $this->generatePropOwnerUpdateRequest($val, $testOwner, $req->isFullUpdate);
                $newOwnerArr["requestId"] = $updetReq["id"];
                $newOwnerArr["userId"] = $refUlbId;
                $rPropOwners->store($newOwnerArr);
            }
            $rules = [
                "applicationId" => $updetReq["id"],
                "status" => 1,
                "comment" => "Approved",
            ];
            // $newRequest = new Request($rules);
            // $approveResponse = $this->approvedRejectRequest($newRequest);
            // if(!$approveResponse->original["status"]) 
            // {
            //     return $approveResponse;
            // }
            DB::commit();
            return responseMsgs(true, 'Update Request Submited', $updetReq, '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ======================📖 Update Request InBox 📖==========================================
     * ||                     Created By : Sandeep Bara
     * ||                     Date       : 01-11-2023
     * ||                     Status     : Open
     * ===========================================================================================
     */
    public function updateRequestInbox(Request $request)
    {
        try {
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id;
            $mCommonFunction = new CommonFunction();
            $ModelWard = new ModelWard();
            $refWorkflowId  = Config::get("workflow-constants.PROPERTY_UPDATE_ID");
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $mCommonFunction->userType($refWorkflowId);
            $mWardPermission = $mCommonFunction->WardPermission($refUserId);
            $mRole = $mCommonFunction->getUserRoll($refUserId, $refUlbId, $refWorkflowId);

            if (!$mRole) {
                throw new Exception("You Are Not Authorized For This Action");
            }
            if ($mRole->is_initiator) {
                $mWardPermission = $ModelWard->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);

            $mRoleId = $mRole->role_id;

            $data = (new PropPropertyUpdateRequest)->WorkFlowMetaList()
                ->where("current_role_id", $mRoleId)
                ->where("prop_properties.ulb_id", $refUlbId);
            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            if ($request->formDate && $request->toDate) {
                $data = $data
                    ->whereBetween(DB::raw('prop_property_update_requests.created_at::date'), [$request->formDate, $request->toDate]);
            }
            if (trim($request->key)) {
                $key = trim($request->key);
                $data = $data->where(function ($query) use ($key) {
                    $query->orwhere('prop_properties.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('prop_property_update_requests.request_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('prop_properties.property_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name_marathi', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name_marathi', 'ILIKE', '%' . $key . '%');
                });
            }
            $data = $data
                ->whereIn('prop_properties.ward_mstr_id', $mWardIds)
                ->orderBy("prop_property_update_requests.created_at", "DESC");
            if ($request->all) {
                $data = $data->get();
                return responseMsg(true, "", $data);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ======================📖 Update Request OutBox 📖==========================================
     * ||                     Created By : Sandeep Bara
     * ||                     Date       : 01-11-2023
     * ||                     Status     : Open
     * ===========================================================================================
     */
    public function updateRequestOutbox(Request $request)
    {
        try {
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id;
            $mCommonFunction = new CommonFunction();
            $ModelWard = new ModelWard();
            $refWorkflowId  = Config::get("workflow-constants.PROPERTY_UPDATE_ID");
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $mCommonFunction->userType($refWorkflowId);
            $mWardPermission = $mCommonFunction->WardPermission($refUserId);
            $mRole = $mCommonFunction->getUserRoll($refUserId, $refUlbId, $refWorkflowId);

            if (!$mRole) {
                throw new Exception("You Are Not Authorized For This Action");
            }
            if ($mRole->is_initiator) {
                $mWardPermission = $ModelWard->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);

            $mRoleId = $mRole->role_id;

            $data = (new PropPropertyUpdateRequest)->WorkFlowMetaList()
                ->where("current_role_id", "<>", $mRoleId)
                ->where("prop_properties.ulb_id", $refUlbId);
            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            if ($request->formDate && $request->toDate) {
                $data = $data
                    ->whereBetween(DB::raw('prop_property_update_requests.created_at::date'), [$request->formDate, $request->toDate]);
            }
            if (trim($request->key)) {
                $key = trim($request->key);
                $data = $data->where(function ($query) use ($key) {
                    $query->orwhere('prop_properties.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('prop_property_update_requests.request_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('prop_properties.property_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name_marathi', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name_marathi', 'ILIKE', '%' . $key . '%');
                });
            }
            $data = $data
                ->whereIn('prop_properties.ward_mstr_id', $mWardIds)
                ->orderBy("prop_property_update_requests.created_at", "DESC");
            if ($request->all) {
                $data = $data->get();
                return responseMsg(true, "", $data);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function updateRequestView(Request $request)
    {
        try {
            $validated = Validator::make(
                $request->all(),
                [
                    'applicationId' => 'required|digits_between:1,9223372036854775807',
                ]
            );
            if ($validated->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validated->errors()
                ]);
            }
            $application = PropPropertyUpdateRequest::find($request->applicationId);
            if (!$application) {
                throw new Exception("Data Not Found");
            }
            $users = User::select("*")->where("id", $application->user_id)->first();
            $docUrl = Config::get('module-constants.DOC_URL');
            $data["userDtl"] = [
                "employeeName" => $users->name,
                "mobile" => $users->mobile,
                "document" => $application->supporting_doc ? ($docUrl . "/" . $application->supporting_doc) : "",
                "applicationDate" => $application->created_at ? Carbon::parse($application->created_at)->format("m-d-Y H:s:i A") : null,
                "requestNo" => $application->request_no,
                "updationType" => $application->is_full_update ? "Full Update" : "Basice Update",
            ];
            $data["propCom"] = $this->PropUpdateCom($application);
            $data["ownerCom"] = $this->OwerUpdateCom($application);

            return responseMsgs(true, "data fetched", remove_null($data), "010109", "1.0", "286ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ======================📝 Update Request Forward Next User Or Reject 📝====================
     * ||                     Created By : Sandeep Bara
     * ||                     Date       : 01-11-2023
     * ||                     Status     : Open
     * ===========================================================================================
     */
    public function postNextUpdateRequest(Request $request)
    {
        try {
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $mCommonFunction = new CommonFunction();
            $refWorkflowId  = Config::get("workflow-constants.PROPERTY_UPDATE_ID");
            $mModuleId = config::get("module-constants.PROPERTY_MODULE_ID");
            $_TRADE_CONSTAINT = config::get("TradeConstant");

            $role = $mCommonFunction->getUserRoll($user_id, $ulb_id, $refWorkflowId);
            $rules = [
                "action"        => 'required|in:forward,backward',
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                'senderRoleId' => 'nullable|integer',
                'receiverRoleId' => 'nullable|integer',
                'comment' => ($role->is_initiator ?? false) ? "nullable" : 'required',
            ];
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
            if (!$request->senderRoleId) {
                $request->merge(["senderRoleId" => $role->role_id ?? 0]);
            }
            if (!$request->receiverRoleId) {
                if ($request->action == 'forward') {
                    $request->merge(["receiverRoleId" => $role->forward_role_id ?? 0]);
                }
                if ($request->action == 'backward') {
                    $request->merge(["receiverRoleId" => $role->backward_role_id ?? 0]);
                }
            }
            #if finisher forward then
            if (($role->is_finisher ?? 0) && $request->action == 'forward') {
                $request->merge(["status" => 1]);
                return $this->approvedRejectRequest($request);
            }
            if ($request->action != 'forward') {
                $request->merge(["status" => 0]);
                return $this->approvedRejectRequest($request);
            }
            if (!$mCommonFunction->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }
            $workflowId = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $ulb_id)
                ->first();
            if (!$workflowId) {
                throw new Exception("Workflow Not Available");
            }
            $application = PropPropertyUpdateRequest::find($request->applicationId);
            if (!$application) {
                throw new Exception("Data Not Found");
            }
            $allRolse     = collect($mCommonFunction->getAllRoles($user_id, $ulb_id, $refWorkflowId, 0, true));

            $initFinish   = $mCommonFunction->iniatorFinisher($user_id, $ulb_id, $refWorkflowId);
            $receiverRole = array_values(objToArray($allRolse->where("id", $request->receiverRoleId)))[0] ?? [];
            $senderRole   = array_values(objToArray($allRolse->where("id", $request->senderRoleId)))[0] ?? [];

            if ($application->current_role_id != $role->role_id) {
                throw new Exception("You Have Not Pending This Application");
            }

            $sms = "Application Rejected By " . $receiverRole["role_name"] ?? "";
            if ($role->serial_no  < $receiverRole["serial_no"] ?? 0) {
                $sms = "Application Forward To " . $receiverRole["role_name"] ?? "";
            }
            DB::beginTransaction();
            DB::connection("pgsql_master")->beginTransaction();
            $application->max_level_attained = ($application->max_level_attained < ($receiverRole["serial_no"] ?? 0)) ? ($receiverRole["serial_no"] ?? 0) : $application->max_level_attained;
            $application->current_role_id = $request->receiverRoleId;
            $application->update();

            $track = new WorkflowTrack();
            $lastworkflowtrack = $track->select("*")
                ->where('ref_table_id_value', $request->applicationId)
                ->where('module_id', $mModuleId)
                ->where('ref_table_dot_id', "prop_properties")
                ->whereNotNull('sender_role_id')
                ->orderBy("track_date", 'DESC')
                ->first();


            $metaReqs['moduleId'] = $mModuleId;
            $metaReqs['workflowId'] = $application->workflow_id;
            $metaReqs['refTableDotId'] = "prop_properties";
            $metaReqs['refTableIdValue'] = $request->applicationId;
            $metaReqs['user_id'] = $user_id;
            $metaReqs['ulb_id'] = $ulb_id;
            $metaReqs['trackDate'] = $lastworkflowtrack && $lastworkflowtrack->forward_date ? ($lastworkflowtrack->forward_date . " " . $lastworkflowtrack->forward_time) : Carbon::now()->format('Y-m-d H:i:s');
            $metaReqs['forwardDate'] = Carbon::now()->format('Y-m-d');
            $metaReqs['forwardTime'] = Carbon::now()->format('H:i:s');
            $metaReqs['verificationStatus'] = ($request->action == 'forward') ? $_TRADE_CONSTAINT["VERIFICATION-STATUS"]["VERIFY"] : $_TRADE_CONSTAINT["VERIFICATION-STATUS"]["BACKWARD"];

            $request->merge($metaReqs);
            $track->saveTrack($request);
            DB::commit();
            DB::connection("pgsql_master")->commit();
            return responseMsgs(true, $sms, "", "010109", "1.0", "286ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection("pgsql_master")->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * ======================📝 Update Request Approved Or Reject By Finisher📝==================
     * ||                     Created By : Sandeep Bara
     * ||                     Date       : 01-11-2023
     * ||                     Status     : Open
     * ===========================================================================================
     */
    public function approvedRejectRequest(Request $request)
    {
        try {
            $rules = [
                "applicationId" => "required",
                "status" => "required",
                "comment" => $request->status == 0 ? "required" : "nullable",
            ];
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
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $mCommonFunction = new CommonFunction();
            $refWorkflowId  = Config::get("workflow-constants.PROPERTY_UPDATE_ID");
            $mModuleId = config::get("module-constants.PROPERTY_MODULE_ID");
            $_TRADE_CONSTAINT = config::get("TradeConstant");

            if (!$mCommonFunction->checkUsersWithtocken("users")) {
                throw new Exception("Citizen Not Allowed");
            }

            $application = PropPropertyUpdateRequest::find($request->applicationId);

            $role = $mCommonFunction->getUserRoll($user_id, $ulb_id, $refWorkflowId);

            if (!$application) {
                throw new Exception("Data Not Found!");
            }
            if ($application->pending_status == 5) {
                throw new Exception("Application Already Approved On " . $application->approval_date);
            }
            if (!$role || ($application->finisher_role_id != $role->role_id ?? 0)) {
                throw new Exception("Forbidden Access");
            }
            if (!$request->senderRoleId) {
                $request->merge(["senderRoleId" => $role->role_id ?? 0]);
            }
            $owneres = $application->getOwnersUpdateReq()->get();
            if (!$request->receiverRoleId) {
                if ($request->status == '1') {
                    $request->merge(["receiverRoleId" => $role->forward_role_id ?? 0]);
                }
                if ($request->status == '0') {
                    $request->merge(["receiverRoleId" => $role->backward_role_id ?? 0]);
                }
            }
            $track = new WorkflowTrack();
            $lastworkflowtrack = $track->select("*")
                ->where('ref_table_id_value', $request->applicationId)
                ->where('module_id', $mModuleId)
                ->where('ref_table_dot_id', "prop_properties")
                ->whereNotNull('sender_role_id')
                ->orderBy("track_date", 'DESC')
                ->first();
            $metaReqs['moduleId'] = $mModuleId;
            $metaReqs['workflowId'] = $application->workflow_id;
            $metaReqs['refTableDotId'] = 'prop_properties';
            $metaReqs['refTableIdValue'] = $request->applicationId;
            $metaReqs['user_id'] = $user_id;
            $metaReqs['ulb_id'] = $ulb_id;
            $metaReqs['trackDate'] = $lastworkflowtrack && $lastworkflowtrack->forward_date ? ($lastworkflowtrack->forward_date . " " . $lastworkflowtrack->forward_time) : Carbon::now()->format('Y-m-d H:i:s');
            $metaReqs['forwardDate'] = Carbon::now()->format('Y-m-d');
            $metaReqs['forwardTime'] = Carbon::now()->format('H:i:s');
            $metaReqs['verificationStatus'] = ($request->status == 1) ? $_TRADE_CONSTAINT["VERIFICATION-STATUS"]["APROVE"] : $_TRADE_CONSTAINT["VERIFICATION-STATUS"]["REJECT"];
            $request->merge($metaReqs);

            DB::beginTransaction();
            DB::connection("pgsql_master")->beginTransaction();
            $track->saveTrack($request);

            // Approval
            if ($request->status == 1) {
                $propArr = $this->updateProperty($application);
                $propUpdate = (new PropProperty)->edit($application->prop_id, $propArr);
                foreach ($owneres as $val) {
                    $ownerArr = $this->updatePropOwner($val);
                    $ownerUpdate = (new PropOwner)->edit($val->owner_id, $ownerArr);
                }
                $application->pending_status = 5;
                $msg =  $application->holding_no . " Updated Successfull";
            }

            // Rejection
            if ($request->status == 0) {
                // Objection Application replication
                $application->pending_status = 4;
                $msg = $application->request_no . " Of Holding No " . $application->holding_no . " Rejected";
            }

            $application->approval_date = Carbon::now()->format('Y-m-d');
            $application->approved_by = $user_id;
            $application->update();
            DB::commit();
            DB::connection("pgsql_master")->commit();
            return responseMsgs(true, $msg, "", '010811', '01', '474ms-573', 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection("pgsql_master")->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Check if the property id exist in the workflow
     */
    public function CheckProperty(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'type' => 'required|in:Reassesment,Mutation,Concession,Objection,Harvesting,Bifurcation',
                'propertyId' => 'required|numeric',
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $type = $req->type;
            $propertyId = $req->propertyId;

            switch ($type) {
                case 'Reassesment':
                    $data = PropActiveSaf::select('prop_active_safs.id', 'role_name', 'saf_no as application_no')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                        ->where('previous_holding_id', $propertyId)
                        ->where('prop_active_safs.status', 1)
                        ->first();
                    break;
                case 'Mutation':
                    $data = PropActiveSaf::select('prop_active_safs.id', 'role_name', 'saf_no as application_no')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                        ->where('previous_holding_id', $propertyId)
                        ->where('prop_active_safs.status', 1)
                        ->first();
                    break;
                case 'Bifurcation':
                    $data = PropActiveSaf::select('prop_active_safs.id', 'role_name', 'saf_no as application_no')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                        ->where('previous_holding_id', $propertyId)
                        ->where('prop_active_safs.status', 1)
                        ->first();
                    break;
                case 'Concession':
                    $data = PropActiveConcession::select('prop_active_concessions.id', 'role_name', 'application_no')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_concessions.current_role')
                        ->where('property_id', $propertyId)
                        ->where('prop_active_concessions.status', 1)
                        ->first();
                    break;
                case 'Objection':
                    $data = PropActiveObjection::select('prop_active_objections.id', 'role_name', 'objection_no as application_no')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_objections.current_role')
                        ->where('property_id', $propertyId)
                        ->where('prop_active_objections.status', 1)
                        ->first();
                    break;
                case 'Harvesting':
                    $data = PropActiveHarvesting::select('prop_active_harvestings.id', 'role_name', 'application_no')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_harvestings.current_role')
                        ->where('property_id', $propertyId)
                        ->where('prop_active_harvestings.status', 1)
                        ->first();
                    break;
            }
            if ($data) {
                $msg['id'] = $data->id;
                $msg['inWorkflow'] = true;
                $msg['currentRole'] = $data->role_name;
                $msg['message'] = "The application is still in workflow and pending at " . $data->role_name . ". Please Track your application with " . $data->application_no;
            } else
                $msg['inWorkflow'] = false;

            return responseMsgs(true, 'Data Updated', $msg, '010801', '01', '', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Get the Property LatLong for Heat map
     * | Using wardId used in dashboard data 
     * | @param req
        | For MVP testing
     */
    public function getpropLatLong(Request $req)
    {
        $req->validate([
            'wardId' => 'required|integer',
        ]);
        try {
            $mPropProperty = new PropProperty();
            $propDetails = $mPropProperty->getPropLatlong($req->wardId);
            $propDetails = collect($propDetails)->map(function ($value) {

                $currentDate = Carbon::now()->format('Y-04-01');
                $refCurrentDate = Carbon::createFromFormat('Y-m-d', $currentDate);
                $mPropDemand = new PropDemand();

                $geoDate = strtotime($value['created_at']);
                $geoDate = date('Y-m-d', $geoDate);
                $ref2023 = Carbon::createFromFormat('Y-m-d', "2023-01-01")->toDateString();

                $path = $this->readDocumentPath($value['doc_path']);
                # arrrer,current,paid
                $refUnpaidPropDemands = $mPropDemand->getDueDemandByPropId($value['property_id']);
                $checkPropDemand = collect($refUnpaidPropDemands)->last();
                if (is_null($checkPropDemand)) {
                    $currentStatus = 3;                                                             // Static
                    $statusName = "No Dues";                                                         // Static
                }
                if ($checkPropDemand) {
                    $lastDemand = collect($refUnpaidPropDemands)->last();
                    if (is_null($lastDemand->due_date)) {
                        $currentStatus = 3;                                                         // Static
                        $statusName = "No Dues";                                                     // Static
                    }
                    $refDate = Carbon::createFromFormat('Y-m-d', $lastDemand->due_date);
                    if ($refDate < $refCurrentDate) {
                        $currentStatus = 1;                                                         // Static
                        $statusName = "Arrear";                                                    // Static
                    } else {
                        $currentStatus = 2;                                                         // Static
                        $statusName = "Current Dues";                                               // Static
                    }
                }
                $value['statusName'] = $statusName;
                $value['currentStatus'] = $currentStatus;
                if ($geoDate < $ref2023) {
                    $path = $this->readRefDocumentPath($value['doc_path']);
                    $value['full_doc'] = !empty(trim($value['doc_path'])) ? $path : null;
                    return $value;
                }
                $value['full_doc'] = !empty(trim($value['doc_path'])) ? $path : null;
                return $value;
            })->filter(function ($refValues) {
                return $refValues['new_holding_no'] != null;
            });
            return responseMsgs(true, "latLong Details", remove_null($propDetails), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", $req->deviceId);
        }
    }
    public function readRefDocumentPath($path)
    {
        $path = ("https://smartulb.co.in/RMCDMC/getImageLink.php?path=" . "/" . $path);                      // Static
        return $path;
    }
    public function readDocumentPath($path)
    {
        $path = (config('app.url') . "/" . $path);
        return $path;
    }


    /**
     * | Get porperty transaction by user id 
     * | List the transaction detial of all transaction by the user
        | Serial No :
        | Under Con 
     */
    public function getUserPropTransactions(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "citizenId" => "required|int"
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $transactionDetails = array();
            $citizenId          = $request->citizenId;
            $mPropProperty      = new PropProperty();
            $mPropActiveSaf     = new PropActiveSaf();
            $propTransaction    = new PropTransaction();

            $refPropertyIds = $mPropProperty->getPropDetailsByCitizenId($citizenId)->selectRaw('id')->get();
            $refSafIds = $mPropActiveSaf->getSafDetailsByCitizenId($citizenId)->selectRaw('id')->get();

            if ($refPropertyIds->first()) {
                $safIds = ($refSafIds->pluck('id'))->toArray();
                $safTranDetails = $propTransaction->getPropTransBySafIdV2($safIds)->get();
            }
            if ($refSafIds->first()) {
                $propertyIds = ($refPropertyIds->pluck('id'))->toArray();
                $proptranDetails = $propTransaction->getPropTransByPropIdV2($propertyIds)->get();
            }
            $transactionDetails = [
                "propTransaction" => $proptranDetails ?? [],
                "safTransaction" => $safTranDetails ?? []
            ];
            return responseMsgs(true, "Transactions History", remove_null($transactionDetails), "", "1.0", responseTime(), "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    /**
     * | Get application detials according to citizen id
        | Serial no :
        | Under Con
     */
    public function getActiveApplications(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "citizenId" => "required|int"
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "1.0", responseTime(), "POST", $request->deviceId);
        }
    }

    /**
     * | Get property detials according to mobile no 
        | Serial No :
        | Under Con
        | PRIOR
     */
    public function getPropDetialByMobileNo(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "mobileNo" => "required",
                "filterBy"  => "required"
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $mPropOwner                 = new PropOwner();
            $mPropSafsOwner             = new PropSafsOwner();
            $mPropProperty              = new PropProperty();
            $mActiveCitizenUndercare    = new ActiveCitizenUndercare();
            $filterBy                   = $request->filterBy;
            $mobileNo                   = $request->mobileNo;

            # For Active Saf
            if ($filterBy == 'saf') {                                                   // Static
                $returnData = $mPropSafsOwner->getPropByMobile($mobileNo)->get();
                $msg = 'Citizen Safs';
            }

            # For Porperty
            if ($filterBy == 'holding') {                                               // Static
                $data                   = $mPropOwner->getOwnerDetailV2($mobileNo)->get();
                $citizenId              = collect($data)->pluck('citizen_id')->filter();
                $caretakerProperty      = $mActiveCitizenUndercare->getTaggedPropsByCitizenIdV2(($citizenId)->toArray());
                $caretakerPropertyIds   = $caretakerProperty->pluck('property_id');
                $data3                  = $mPropProperty->getPropByPropId($caretakerPropertyIds)->get();

                # If caretaker property exist
                if (($data3->first())->isNotEmpty()) {
                    $propertyId = collect($caretakerProperty)->pluck('property_id');
                    $data2      = $mPropProperty->getNewholding($propertyId);
                    $data       = $data->merge($data2);
                }

                # Format the data for returning
                $data = collect($data)->map(function ($value) {
                    if (isset($value['new_holding_no'])) {
                        return $value;
                    }
                })->filter()->values();
                $msg = 'Citizen Holdings';
            }
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "1.0", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | Get The property copy report
     */
    public function getHoldingCopy(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "propId" => "required|integer",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        $mPropProperty = new PropProperty();
        $mPropFloors = new PropFloor();
        $mPropOwner = new PropOwner();
        $mPropDemands = new PropDemand();

        try {
            $propDetails = $mPropProperty->getPropBasicDtls($req->propId);
            $propFloors = $mPropFloors->getPropFloors($req->propId);
            $propOwner = $mPropOwner->getfirstOwner($req->propId);

            $floorTypes = $propFloors->implode('floor_name', ',');
            $floorCode = $propFloors->implode('floor_code', '-');
            $usageTypes = $propFloors->implode('usage_type', ',');
            $constTypes = $propFloors->implode('construction_type', ',');
            $constCode = $propFloors->implode('construction_code', '-');
            $totalBuildupArea = $propFloors->pluck('builtup_area')->sum();
            $minFloorFromDate = $propFloors->min('date_from');
            $propUsageTypes = ($this->propHoldingType($propFloors) == 'PURE_RESIDENTIAL') ? 'निवासी' : 'अनिवासी';
            $propDemand = $mPropDemands->getDemandByPropIdV2($req->propId)->first();

            if (collect($propDemand)->isNotEmpty()) {
                $propDemand->maintanance_amt = roundFigure($propDemand->alv * 0.10);
                $propDemand->tax_value = roundFigure($propDemand->alv - ($propDemand->maintanance_amt + $propDemand->aging_amt));
            }
            $propUsageTypes = collect($propDemand)->isNotEmpty() && $propDemand->professional_tax == 0 ? 'निवासी' : 'अनिवासी';

            $responseDetails = [
                'zone_no' => $propDetails->zone_name,
                'survey_no' => "",
                'ward_no' => $propDetails->ward_no,
                'plot_no' => $propDetails->plot_no,
                'old_property_no' => $propDetails->property_no,
                'partition_no' => explode('-', $propDetails->property_no)[1] ?? "",
                'old_ward_no' => "",
                'property_usage_type' => $propUsageTypes,
                'floor_types' => $floorTypes,
                'floor_code' => $floorCode,
                'floor_usage_types' => $usageTypes,
                'floor_const_types' => $constTypes,
                'floor_const_code' => $constCode,
                'total_buildup_area' => $totalBuildupArea,
                'area_of_plot' => $propDetails->area_of_plot,
                'primary_owner_name' => $propOwner->owner_name_marathi,
                'applicant_name' => $propDetails->applicant_marathi,
                'property_from' => $minFloorFromDate,
                'demands' => $propDemand
            ];
            return responseMsgs(true, "Property Details", remove_null($responseDetails));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }
}
