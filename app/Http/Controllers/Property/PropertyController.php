<?php

namespace App\Http\Controllers\Property;

use App\EloquentModels\Common\ModelWard;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ThirdPartyController;
use App\MicroServices\DocUpload;
use App\Models\ActiveCitizen;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Property\Location;
use App\Models\Property\PropActiveConcession;
use App\Models\Property\PropActiveHarvesting;
use App\Models\Property\PropActiveObjection;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropDemand;
use App\Models\Property\PropFloor;
use App\Models\Property\PropFloorsUpdateRequest;
use App\Models\Property\PropOwner;
use App\Models\Property\PropOwnerUpdateRequest;
use App\Models\Property\PropPenaltyrebate;
use App\Models\Property\PropProperty;
use App\Models\Property\PropPropertyUpdateRequest;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafGeotagUpload;
use App\Models\Property\PropSafsDemand;
use App\Models\Property\PropSafsOwner;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\User;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Pipelines\SearchHolding;
use App\Pipelines\SearchPtn;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\iSafRepository;
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
        $validated = Validator::make(
            $req->all(),
            ["holdingNo" => "required"]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
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
                    // SearchPtn::class
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
            if (strlen($ownerMobile) <> 10)
                throw new Exception('Mobile No. Does Not Exist or Invalid');

            $myRequest = new \Illuminate\Http\Request();
            $myRequest->setMethod('POST');
            $myRequest->request->add([
                'mobileNo' => $ownerMobile,
                'type'     => "Attach Holding",
                'userId'   => authUser($req)->id ?? null,
            ]);
            $response = $ThirdPartyController->sendOtp($myRequest);

            $response = collect($response)->toArray();
            // $data['otp'] = $response['original']['data'];
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
        $todayDate = Carbon::now()->format('Y-m-d');

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
            "propertyId"                => "required|digits_between:1,9223372036854775807",
            "document"                  => "required|mimes:pdf,jpeg,png,jpg,gif",
            "applicantName"             => "required",
            "applicantMarathi"          => "required|string",
            "appartmentName"            => "nullable|string",
            "electricityConnection"     => "nullable|string",
            "electricityCustNo"         => "nullable|string",
            "electricityAccNo"          => "nullable|string",
            "electricityBindBookNo"     => "nullable|string",
            "electricityConsCategory"   => "nullable|string",
            "buildingPlanApprovalNo"    => "nullable|string",
            "buildingPlanApprovalDate"  => "nullable|date|",
            // "applicantName" => "required|regex:/^[A-Za-z.\s]+$/i",

            "ownershipType"             => "required|In:$ownership_typesIds",
            "zone"                      => "required|In:$zoneIds",
            "ward"                      => "required|In:$zoneWiseWardIds",
            "owner"                     => "required|array",

            "owner.*.ownerId"           => "nullable|digits_between:1,9223372036854775807",
            "owner.*.ownerName"         => "required",
            "owner.*.ownerNameMarathi"  => "required|string",
            "owner.*.mobileNo"          => "nullable|digits:10|regex:/[0-9]{10}/",
            "owner.*.aadhar"            => "digits:12|regex:/[0-9]{12}/|nullable",
            "owner.*.pan"               => "string|nullable",
        ];
        if ($req->isFullUpdate) {
            $rules["propertyType"] = "required|integer";
            $rules["areaOfPlot"] = "required|numeric";
            $rules["category"] = "required|integer";
            $rules["dateOfPurchase"] = "required|date|date_format:Y-m-d|before_or_equal:$todayDate";
            $rules["propertyType"] = "required|integer";
        }
        if ($req->has('propertyType') && $req->propertyType != 4) {
            $rules["floor"] = "required|array";
            $rules["floor.*.floorId"] = "nullable|digits_between:1,9223372036854775807";
            $rules["floor.*.floorNo"] = "required|integer";
            $rules["floor.*.constructionType"] = "required|integer";
            $rules["floor.*.usageType"] = "required|integer";
            $rules["floor.*.buildupArea"] = "required|numeric";
            $rules["floor.*.dateFrom"] = "required|date|date_format:Y-m-d|before_or_equal:$req->todayDate" . ($req->assessmentType == 3 ? "" : "|after_or_equal:$req->dateOfPurchase");
        }
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
            $refUser = Auth()->user();
            if (!$refUser) {
                throw new Exception("Access denied");
            }
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id;
            $propId             = $req->propertyId;

            $relativePath       = Config::get('PropertyConstaint.PROP_UPDATE_RELATIVE_PATH');
            $docUpload          = new DocUpload;
            $mPropProperty      = new PropProperty();
            $mPropOwners        = new PropOwner();
            $mPropFloors        = new PropFloor();
            $rPropProerty       = new PropPropertyUpdateRequest();
            $rPropOwners        = new PropOwnerUpdateRequest();
            $rPropFloors        = new PropFloorsUpdateRequest();
            $mCommonFunction    = new CommonFunction();

            $prop = $mPropProperty->find($propId);
            if (!$prop) {
                throw new Exception("Data Not Found");
            }
            $refWorkflowId = Config::get("workflow-constants.PROPERTY_UPDATE_ID");
            $refWfWorkflow = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $pendingRequest = $prop->getUpdatePendingRqu()->first();
            if ($pendingRequest) {
                throw new Exception("Already Update Request Apply Which is Pending");
            }

            $refWorkflows   = $mCommonFunction->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $mUserType      = $mCommonFunction->userType($refWorkflowId);
            $document       = $req->document;
            $refImageName   = $req->propertyId . "-" . (strtotime(Carbon::now()->format('Y-m-dH:s:i')));
            $imageName      = $docUpload->upload($refImageName, $document, $relativePath);

            $roadWidthType = $this->readRoadWidthType($req->roadType);

            $metaReqs["supportingDocument"] = ($relativePath . "/" . $imageName);
            $metaReqs['roadWidthType']      = $roadWidthType;
            $metaReqs['workflowId']         = $refWfWorkflow->id;       // inserting workflow id
            $metaReqs['initiatorRoleId']    = $refWorkflows['initiator']['id'];
            $metaReqs['finisherRoleId']     = $refWorkflows['finisher']['id'];
            $metaReqs['currentRole']        = $refWorkflows['initiator']['id'];
            $metaReqs['userId']             = $refUserId;
            $metaReqs['pendingStatus']      = 1;
            $req->merge($metaReqs);
            if (!$req->has('isFullUpdate')) {
                $req->merge(["isFullUpdate" => false]);
            }

            $propRequest = $this->generatePropUpdateRequest($req, $prop, $req->isFullUpdate);
            $propRequest["dateOfPurchase"] = $req->landOccupationDate;
            $req->merge($propRequest);

            DB::beginTransaction();
            $updetReq = $rPropProerty->store($req);
            foreach ($req->owner as $val) {
                $testOwner = $mPropOwners->select("*")->where("id", ($val["ownerId"] ?? 0))->where("property_id", $propId)->first();
                if (!$testOwner && ($val["ownerId"] ?? 0)) {
                    throw new Exception("Invalid Owner Id Pass");
                }
                if (!$testOwner) {
                    $testOwner = new PropOwner();
                    $testOwner->property_id = $propId;
                }
                $newOwnerArr = $this->generatePropOwnerUpdateRequest($val, $testOwner, true);
                $newOwnerArr["requestId"] = $updetReq["id"];
                $newOwnerArr["userId"] = $refUlbId;
                $rPropOwners->store($newOwnerArr);
            }
            if ($req->isFullUpdate && $req->propertyType != 4) {
                foreach ($req->floor as $val) {
                    $testFloor = $mPropFloors->select("*")->where("id", ($val["floorId"] ?? 0))->where("property_id", $propId)->first();
                    if (!$testFloor && ($val["floorId"] ?? 0)) {
                        throw new Exception("Invalid Owner Id Pass");
                    }
                    if (!$testFloor) {
                        $testFloor = new PropFloor();
                        $testFloor->property_id = $propId;
                    }
                    $newFloorArr = $this->generatePropFloarUpdateRequest($val, $testFloor, true);
                    $newFloorArr["requestId"] = $updetReq["id"];
                    $newFloorArr["userId"] = $refUlbId;
                    $rPropFloors->store($newFloorArr);
                }
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
            $data["floorCom"] = $this->FloorUpdateCom($application);

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
            $floors = $application->getFloorsUpdateReq()->get();
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
                    if ($val->owner_id)
                        $ownerUpdate = (new PropOwner)->edit($val->owner_id, $ownerArr);
                    else {
                        $ownerArr["property_id"] = $application->prop_id;
                        $ownerArr["status"] = 1;
                        $ownerArr["saf_id"] = null;
                        $ownerArr["user_id"] = $user_id;
                        $ownerArr["id"] = null;
                        $ownerUpdate = (new PropOwner)->postOwner((object)$ownerArr);
                    }
                }
                if ($application->is_full_update) {
                    foreach ($floors as $val) {
                        $floorArr = $this->updatePropFloorPrimary($val);
                        if ($val->floor_id)
                            $floorUpdate = (new PropFloor())->edit($val->floor_id, $floorArr);
                        else {
                            $floorArr["property_id"] = $application->prop_id;
                            $floorArr["status"] = 1;
                            $floorArr["saf_id"] = null;
                            $floorArr["saf_floor_id"] = null;
                            $floorArr["prop_floor_details_id"] = null;
                            $floorArr["user_id"] = $user_id;
                            $floorArr["id"] = null;
                            $floorUpdate = (new PropFloor)->postFloor((object)$floorArr);
                        }
                    }
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
            $data = null;

            switch ($type) {
                case 'Reassesment':
                    $data = PropActiveSaf::select('prop_active_safs.id', 'role_name', 'saf_no as application_no', 'assessment_type')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                        ->where('previous_holding_id', $propertyId)
                        ->where('prop_active_safs.status', 1)
                        ->first();
                    break;
                case 'Mutation':
                    $data = PropActiveSaf::select('prop_active_safs.id', 'role_name', 'saf_no as application_no', 'assessment_type')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                        ->where('previous_holding_id', $propertyId)
                        ->where('prop_active_safs.status', 1)
                        ->first();
                    break;
                case 'Bifurcation':
                    $data = PropActiveSaf::select('prop_active_safs.id', 'role_name', 'saf_no as application_no', 'assessment_type')
                        ->join('wf_roles', 'wf_roles.id', 'prop_active_safs.current_role')
                        ->where('assessment_type', 'Mutation')
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
            $prop = PropProperty::find($propertyId);
            if ($prop && $prop->status == 0) {
                switch ($prop->status) {
                    case 0:
                        throw new Exception("Property Is Already Deactivated");
                        break;
                    case 2:
                        throw new Exception("Property Is Deactivated By Deactivated Request");
                        break;
                    case 3:
                        throw new Exception("Property Is Deactivated After Mutaion Fee Payment");
                        break;
                    case 4:
                        throw new Exception("Property Is Deactivated By Mutaion");
                        break;
                }
            }
            if ($data) {
                $msg['id'] = $data->id;
                $msg['inWorkflow'] = true;
                $msg['currentRole'] = $data->role_name;
                $msg['message'] = "Your " . $data->assessment_type . " application is still in workflow and pending at " . $data->role_name . ". Please Track your application with " . $data->application_no;
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
            $mPropProperty->id = $req->propId;
            $propOwnerAll = $mPropProperty->Owneres()->get();
            $propOwnerAll = collect($propOwnerAll)->map(function ($val) {
                $val->owner_name_marathi = trim($val->owner_name_marathi) ? trim($val->owner_name_marathi) : trim($val->owner_name);
                return $val;
            });
            $propOwnerAllMarathi = $propOwnerAll->implode("owner_name_marathi", " ,");
            $floorTypes = $propFloors->implode('floor_name', ',');
            $floorCode = $propFloors->implode('floor_code', '-');
            $usageTypes = $propFloors->implode('usage_type', ',');
            $constTypes = $propFloors->implode('construction_type', ',');
            $constCode = $propFloors->implode('construction_code', '-');
            $totalBuildupArea = $propFloors->pluck('builtup_area')->sum();
            $minFloorFromDate = $propFloors->min('date_from');
            $propUsageTypes = ($this->propHoldingType($propFloors) == 'PURE_RESIDENTIAL') ? 'निवासी' : 'अनिवासी';
            if (collect($propFloors)->isEmpty())
                $propUsageTypes = 'प्लॉट';

            $propDemand = $mPropDemands->getDemandByPropIdV2($req->propId)->first();

            if (collect($propDemand)->isNotEmpty()) {
                $propDemand->maintanance_amt = roundFigure($propDemand->alv * 0.10);
                $propDemand->tax_value = roundFigure($propDemand->alv - ($propDemand->maintanance_amt + $propDemand->aging_amt));
            }
            // $propUsageTypes = collect($propDemand)->isNotEmpty() && $propDemand->professional_tax == 0 ? 'निवासी' : 'अनिवासी';

            $safId = $propDetails->saf_id;
            $geotaggedImg = null;
            if ($safId) {
                $geoTagging = PropSafGeotagUpload::where("saf_id", $safId)->orderBy("id", "ASC")->get()->map(function ($val) {
                    $val->paths = (config('app.url') . "/" . $val->relative_path . "/" . $val->image_path);
                    return $val;
                });

                $geotaggedImg = collect($geoTagging)->where("direction_type", "Front") ? (collect($geoTagging)->where("direction_type", "Front"))->pluck("paths")->first() ?? "" : (collect($geoTagging)->where("direction_type", "<>", "naksha")->first() ? (collect($geoTagging)->where("direction_type", "<>", "naksha")->first())->pluck("paths") : "");
            }

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
                'primary_owner_name' => $propOwnerAllMarathi ? $propOwnerAllMarathi : ($propOwner->owner_name_marathi ?? null),
                'applicant_name' => $propDetails->applicant_marathi,
                'property_from' => $minFloorFromDate,
                'geo_tag_image' =>  $geotaggedImg,
                'demands' => $propDemand
            ];
            return responseMsgs(true, "Property Details", remove_null($responseDetails));
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }

    public function Entery(Request $request)
    {
        try {
            $sql = '
                select
                prop_transactions.id as tran_id ,
                prop_transactions.created_at as created_at ,
                prop_transactions.ulb_id as ulb_id ,
                prop_transactions.demand_amt as tran_demand_amt,
                from_fyears,upto_fyears,prop_transactions.property_id,
                prop_transactions.tran_date,
                null as prop_demand_id,
                "Sheet2"."TaxTotal" as total_demand,
                0 as paid_maintanance_amt,
                0 paid_aging_amt,
                "Sheet2"."PropertyTax" as paid_general_tax,
                "Sheet2"."RoadCess" as paid_road_tax,
                "Sheet2"."FireCess" as paid_firefighting_tax,
                "Sheet2"."EducationTax" as paid_education_tax,
                0 as paid_water_tax,
                "Sheet2"."Sanitation" as paid_cleanliness_tax,
                "Sheet2"."SewageDisposalCess" as paid_sewarage_tax,
                "Sheet2"."TreeCess" as paid_tree_tax,
                "Sheet2"."EmploymentTax" as paid_professional_tax,
                "Sheet2"."TaxTotal" as paid_total_tax,
                "Sheet2"."TaxTotal" as paid_balance,
                0 paid_adjust_amt,
                "Sheet2"."Tax1" as paid_tax1,
                "Sheet2"."Tax2" as paid_tax2,
                ("Sheet2"."Tax3"+ "Sheet2"."Tax4"+ "Sheet2"."Tax5") as paid_tax3,
                "Sheet2"."SpEducationTax" as paid_sp_education_tax,
                "Sheet2"."WaterBenefit" as paid_water_benefit,
                "Sheet2"."WaterBill" as paid_water_bill,
                "Sheet2"."SpWaterCess" as paid_sp_water_cess,
                "Sheet2"."DrainCess" as paid_drain_cess,
                "Sheet2"."LightCess" as paid_light_cess,
                "Sheet2"."MajorBuilding" as paid_major_building,
                0 as paid_open_ploat_tax,
                ("Sheet2"."Interest"::numeric) as penalty
                
            from "Sheet2"
            join(
                select *
                from(
                    select "Sheet2"."ID" ,concat(("Sheet2"."FinanceYear"),' . "'-'" . ',("Sheet2"."FinanceYear"+1) )as from_fyears, 
                            concat(("Sheet2"."PendingYear"),' . "'-'" . ',("Sheet2"."PendingYear"+1) )as upto_fyears,
                        (
                            "Sheet2"."PropertyTax" +
                            "Sheet2"."RoadCess" +
                            "Sheet2"."FireCess" +
                            "Sheet2"."EducationTax" +
                            0 +
                            "Sheet2"."Sanitation" +
                            "Sheet2"."SewageDisposalCess" +
                            "Sheet2"."TreeCess" +
                            "Sheet2"."EmploymentTax" +
                            0 +
                            "Sheet2"."Tax1" +
                            "Sheet2"."Tax2" +
                            "Sheet2"."Tax3"+"Sheet2"."Tax4"+"Sheet2"."Tax5" +
                            "Sheet2"."SpEducationTax" +
                            "Sheet2"."WaterBenefit" +
                            "Sheet2"."WaterBill"+
                            "Sheet2"."SpWaterCess"+
                            "Sheet2"."DrainCess"+
                            "Sheet2"."LightCess"+
                            "Sheet2"."MajorBuilding"+
                            0
            
                    ) as sums,
                    ("Sheet2"."TaxTotal") as taxTotal,
                        ("Sheet2"."NetTotal"::numeric) as net_total,
                    ("Sheet2"."Interest"::numeric) as penalty
                    from "Sheet1"
                    join "Sheet2" on "Sheet2"."ID" = "Sheet1"."ID"
                
                    )t
            )te on te."ID"= "Sheet2"."ID"
            join prop_transactions on "Sheet2"."ID" = prop_transactions.old_transaction_id
            left join (
                select tran_id
                from old_trans_demand_logs
                group by tran_id
            )old_trans_demand_logs on old_trans_demand_logs.tran_id = prop_transactions.id
            where round(te.sums)=round(te.taxTotal)  and old_trans_demand_logs.tran_id is null --and prop_transactions.id = 552818
            order by prop_transactions.id
            ;
            ';

            $data1 = DB::select($sql);
            $data = (collect($data1)->take(20))->toArray();


            foreach ($data as $val) {
                DB::beginTransaction();
                $fyearList = [];
                $diff = 1;
                if ($val->from_fyears != $val->upto_fyears && $val->from_fyears > $val->upto_fyears) {
                    $from = $val->from_fyears;
                    $val->from_fyears = $val->upto_fyears;
                    $val->upto_fyears = $from;
                    list($fFrom, $fUpto) = explode("-", $val->from_fyears);
                    list($uFrom, $uUpto) = explode("-", $val->upto_fyears);
                }

                $fyearList[] = $fromYear = $val->from_fyears;

                while ($fromYear < $val->upto_fyears) {
                    $diff += 1;
                    list($From, $Upto) = explode("-", $fromYear);
                    $fyearList[] = $fromYear = ($From + 1) . "-" . ($Upto + 1);
                }

                foreach ($fyearList as $fyear) {
                    $type = "old";
                    $demand = PropDemand::where("fyear", $fyear)
                        ->where("property_id", $val->property_id)
                        ->OrderBy("id", "DESC")
                        ->first();
                    $demand2 = PropDemand::where("fyear", $fyear)
                        ->where("property_id", $val->property_id)
                        ->OrderBy("id", "DESC")
                        ->first();
                    $logs = json_encode($demand->toArray());
                    #===== new tax Insert =============
                    if (!$demand) {
                        $type = "new";
                        $demand = new PropDemand();
                        $demand->alv = 0;
                        $demand->due_maintanance_amt     = $demand->maintanance_amt = $val->paid_maintanance_amt / $diff;
                        $demand->due_aging_amt           = $demand->aging_amt       = $val->paid_aging_amt / $diff;
                        $demand->due_general_tax         = $demand->general_tax     = $val->paid_general_tax / $diff;
                        $demand->due_road_tax            = $demand->road_tax        = $val->paid_road_tax / $diff;
                        $demand->due_firefighting_tax    = $demand->firefighting_tax = $val->paid_firefighting_tax / $diff;
                        $demand->due_education_tax       = $demand->education_tax   = $val->paid_education_tax / $diff;
                        $demand->due_water_tax           = $demand->water_tax       = $val->paid_water_tax / $diff;
                        $demand->due_cleanliness_tax     = $demand->cleanliness_tax = $val->paid_cleanliness_tax / $diff;
                        $demand->due_sewarage_tax        = $demand->sewarage_tax    = $val->paid_sewarage_tax / $diff;
                        $demand->due_tree_tax            = $demand->tree_tax        = $val->paid_tree_tax / $diff;
                        $demand->due_professional_tax    = $demand->professional_tax = $val->paid_professional_tax / $diff;
                        $demand->due_total_tax           = $demand->total_tax       = $val->paid_total_tax / $diff;
                        $demand->due_balance             = $demand->balance         = $val->paid_total_tax / $diff;
                        $demand->due_tax1                = $demand->tax1            = $val->paid_tax1 / $diff;
                        $demand->due_tax2                = $demand->tax2            = $val->paid_tax2 / $diff;
                        $demand->due_tax3                = $demand->tax3            = $val->paid_tax3 / $diff;
                        $demand->due_sp_education_tax    = $demand->sp_education_tax = $val->paid_sp_education_tax / $diff;
                        $demand->due_water_benefit       = $demand->water_benefit   = $val->paid_water_benefit / $diff;
                        $demand->due_water_bill          =  $demand->water_bill     = $val->paid_water_bill / $diff;
                        $demand->due_sp_water_cess       =  $demand->sp_water_cess  = $val->paid_sp_water_cess / $diff;
                        $demand->due_drain_cess          = $demand->drain_cess      = $val->paid_drain_cess / $diff;
                        $demand->due_light_cess          = $demand->light_cess      = $val->paid_light_cess / $diff;
                        $demand->due_major_building      = $demand->major_building  = $val->paid_major_building / $diff;
                        $demand->due_open_ploat_tax      = $demand->open_ploat_tax  = $val->paid_open_ploat_tax / $diff;

                        $demand->is_arrear = getFY() < $fyear ? true : false;
                        $demand->paid_status = 1;
                        $demand->fyear = $fyear;
                        $demand->created_at = $val->created_at;
                        $demand->ulb_id = $val->ulb_id;
                        $demand->save();
                    }
                    #===== end =============
                    #===== tax update =============
                    $demand->maintanance_amt = $demand->maintanance_amt > 0 ? $demand->maintanance_amt : ($val->paid_maintanance_amt   / $diff);
                    $demand->aging_amt       = $demand->aging_amt     > 0  ? $demand->aging_amt       : ($val->paid_aging_amt    /    $diff);
                    $demand->general_tax     = $demand->general_tax   > 0  ? $demand->general_tax     : ($val->paid_general_tax / $diff);
                    $demand->road_tax        = $demand->road_tax      > 0  ? $demand->road_tax        : ($val->paid_road_tax / $diff);
                    $demand->firefighting_tax = $demand->firefighting_tax > 0 ? $demand->firefighting_tax : ($val->paid_firefighting_tax / $diff);
                    $demand->education_tax   = $demand->education_tax > 0  ? $demand->education_tax   : ($val->paid_education_tax / $diff);
                    $demand->water_tax       = $demand->water_tax     > 0  ? $demand->water_tax       : ($val->paid_water_tax / $diff);
                    $demand->cleanliness_tax = $demand->cleanliness_tax > 0 ? $demand->cleanliness_tax : ($val->paid_cleanliness_tax / $diff);
                    $demand->sewarage_tax    = $demand->sewarage_tax  > 0  ? $demand->sewarage_tax    : ($val->paid_sewarage_tax / $diff);
                    $demand->tree_tax        = $demand->tree_tax      > 0  ? $demand->tree_tax        : ($val->paid_tree_tax / $diff);
                    $demand->professional_tax = $demand->professional_tax ? $demand->professional_tax : ($val->paid_professional_tax / $diff);
                    $demand->tax1            = $demand->tax1          > 0  ? $demand->tax1            : ($val->paid_tax1 / $diff);
                    $demand->tax2            = $demand->tax2          > 0  ? $demand->tax2            : ($val->paid_tax2 / $diff);
                    $demand->tax3            = $demand->tax3          > 0  ? $demand->tax3            : ($val->paid_tax3 / $diff);
                    $demand->sp_education_tax = $demand->sp_education_tax > 0 ? $demand->sp_education_tax : ($val->paid_sp_education_tax / $diff);
                    $demand->water_benefit   = $demand->water_benefit > 0  ? $demand->water_benefit   : ($val->paid_water_benefit / $diff);
                    $demand->water_bill      = $demand->water_bill    > 0  ? $demand->water_bill      : ($val->paid_water_bill / $diff);
                    $demand->sp_water_cess   = $demand->sp_water_cess > 0  ? $demand->sp_water_cess   : ($val->paid_sp_water_cess / $diff);
                    $demand->drain_cess      = $demand->drain_cess    > 0  ? $demand->drain_cess      : ($val->paid_drain_cess / $diff);
                    $demand->light_cess      = $demand->light_cess    > 0  ? $demand->light_cess      : ($val->paid_light_cess / $diff);
                    $demand->major_building  = $demand->major_building > 0  ? $demand->major_building  : ($val->paid_major_building / $diff);
                    $demand->open_ploat_tax  = $demand->open_ploat_tax > 0  ? $demand->open_ploat_tax  : ($val->paid_open_ploat_tax / $diff);

                    $demand->balance = $demand->total_tax       = (
                        $demand->maintanance_amt    +   $demand->aging_amt      +   $demand->general_tax    +   $demand->road_tax +
                        $demand->firefighting_tax   +   $demand->education_tax  +   $demand->water_tax      +   $demand->cleanliness_tax +
                        $demand->sewarage_tax       +   $demand->tree_tax       +   $demand->professional_tax +  $demand->tax1 +
                        $demand->tax2               +   $demand->tax3           +   $demand->sp_education_tax +  $demand->water_benefit +
                        $demand->water_bill         +   $demand->sp_water_cess  +   $demand->drain_cess      +  $demand->light_cess +
                        $demand->major_building     +   $demand->open_ploat_tax
                    );

                    #===== end =============
                    #===== due tax update =============

                    $demand->due_maintanance_amt = $demand->due_maintanance_amt > 0 ? ($demand->due_maintanance_amt - ($val->paid_maintanance_amt  / $diff)) : $demand->due_maintanance_amt;
                    $demand->due_aging_amt       = $demand->due_aging_amt       > 0 ? ($demand->due_aging_amt       - ($val->paid_aging_amt        / $diff)) : $demand->due_aging_amt;
                    $demand->due_general_tax     = $demand->due_general_tax     > 0 ? ($demand->due_general_tax     - ($val->paid_general_tax      / $diff)) : $demand->due_general_tax;
                    $demand->due_road_tax        = $demand->due_road_tax        > 0 ? ($demand->due_road_tax        - ($val->paid_road_tax         / $diff)) : $demand->due_road_tax;
                    $demand->due_firefighting_tax = $demand->due_firefighting_tax > 0 ? ($demand->due_firefighting_tax - ($val->paid_firefighting_tax  / $diff)) : $demand->due_firefighting_tax;
                    $demand->due_education_tax   = $demand->due_education_tax   > 0 ? ($demand->due_education_tax   - ($val->paid_education_tax     / $diff)) : $demand->due_education_tax;
                    $demand->due_water_tax       = $demand->due_water_tax       > 0 ? ($demand->due_water_tax       - ($val->paid_water_tax         / $diff)) : $demand->due_water_tax;
                    $demand->due_cleanliness_tax = $demand->due_cleanliness_tax > 0 ? ($demand->due_cleanliness_tax - ($val->paid_cleanliness_tax   / $diff)) : $demand->due_cleanliness_tax;
                    $demand->due_sewarage_tax    = $demand->due_sewarage_tax    > 0 ? ($demand->due_sewarage_tax    - ($val->paid_sewarage_tax      / $diff)) : $demand->due_sewarage_tax;
                    $demand->due_tree_tax        = $demand->due_tree_tax        > 0 ? ($demand->due_tree_tax        - ($val->paid_tree_tax          / $diff)) : $demand->due_tree_tax;
                    $demand->due_professional_tax = $demand->due_professional_tax > 0 ? ($demand->due_professional_tax - ($val->paid_professional_tax   / $diff)) : $demand->due_professional_tax;
                    $demand->due_tax1            = $demand->due_tax1            > 0 ? ($demand->due_tax1            - ($val->paid_tax1  / $diff))           :    $demand->due_tax1;
                    $demand->due_tax2            = $demand->due_tax2            > 0 ? ($demand->due_tax2            - ($val->paid_tax2  / $diff))           :    $demand->due_tax2;
                    $demand->due_tax3            = $demand->due_tax3            > 0 ? ($demand->due_tax3            - ($val->paid_tax3  / $diff))           :    $demand->due_tax3;
                    $demand->due_sp_education_tax = $demand->due_sp_education_tax > 0 ? ($demand->due_sp_education_tax - ($val->paid_sp_education_tax  / $diff)) :    $demand->due_sp_education_tax;
                    $demand->due_water_benefit   = $demand->due_water_benefit   > 0 ? ($demand->due_water_benefit   - ($val->paid_water_benefit  / $diff))  :   $demand->due_water_benefit;
                    $demand->due_water_bill      = $demand->due_water_bill      > 0 ? ($demand->due_water_bill      - ($val->paid_water_bill  / $diff))     :    $demand->due_water_bill;
                    $demand->due_sp_water_cess   = $demand->due_sp_water_cess   > 0 ? ($demand->due_sp_water_cess   - ($val->paid_sp_water_cess  / $diff))  :    $demand->due_sp_water_cess;
                    $demand->due_drain_cess      = $demand->due_drain_cess      > 0 ? ($demand->due_drain_cess      - ($val->paid_drain_cess  / $diff))     :    $demand->due_drain_cess;
                    $demand->due_light_cess      = $demand->due_light_cess      > 0 ? ($demand->due_light_cess      - ($val->paid_light_cess  / $diff))     :    $demand->due_light_cess;
                    $demand->due_major_building  = $demand->due_major_building  > 0 ? ($demand->due_major_building  - ($val->paid_major_building  / $diff)) :    $demand->due_major_building;
                    $demand->due_open_ploat_tax  = $demand->due_open_ploat_tax  > 0 ? ($demand->due_open_ploat_tax  - ($val->paid_open_ploat_tax  / $diff)) :    $demand->due_open_ploat_tax;
                    $demand->due_balance = $demand->due_total_tax       = (
                        $demand->due_maintanance_amt    +   $demand->due_aging_amt      +   $demand->due_general_tax    +   $demand->due_road_tax +
                        $demand->due_firefighting_tax   +   $demand->due_education_tax  +   $demand->due_water_tax      +   $demand->due_cleanliness_tax +
                        $demand->due_sewarage_tax       +   $demand->due_tree_tax       +   $demand->due_professional_tax +  $demand->due_tax1 +
                        $demand->due_tax2               +   $demand->due_tax3           +   $demand->due_sp_education_tax +  $demand->due_water_benefit +
                        $demand->due_water_bill         +   $demand->due_sp_water_cess  +   $demand->due_drain_cess      +  $demand->due_light_cess +
                        $demand->due_major_building     +   $demand->due_open_ploat_tax
                    );
                    #===== end =============

                    $demand->update();
                    $collection = new PropTranDtl();
                    #===== inset Collection Dtls =============
                    $collection->tran_id            =    $val->tran_id;
                    $collection->prop_demand_id        =    $demand->id;
                    $collection->total_demand        =    $val->tran_demand_amt / $diff;
                    $collection->created_at            =    $val->created_at;
                    $collection->ulb_id                =    $val->ulb_id;
                    $collection->paid_maintanance_amt =    $val->paid_maintanance_amt / $diff;
                    $collection->paid_aging_amt        =    $val->paid_aging_amt / $diff;
                    $collection->paid_general_tax    =    $val->paid_general_tax / $diff;
                    $collection->paid_road_tax        =    $val->paid_road_tax / $diff;
                    $collection->paid_firefighting_tax =    $val->paid_firefighting_tax / $diff;
                    $collection->paid_education_tax    =    $val->paid_education_tax / $diff;
                    $collection->paid_water_tax        =    $val->paid_water_tax / $diff;
                    $collection->paid_cleanliness_tax =    $val->paid_cleanliness_tax / $diff;
                    $collection->paid_sewarage_tax    =    $val->paid_sewarage_tax / $diff;
                    $collection->paid_tree_tax        =    $val->paid_tree_tax / $diff;
                    $collection->paid_professional_tax =    $val->paid_professional_tax / $diff;
                    $collection->paid_total_tax        =    $val->paid_total_tax / $diff;
                    $collection->paid_balance        =    $val->paid_balance / $diff;
                    $collection->paid_tax1            =    $val->paid_tax1 / $diff;
                    $collection->paid_tax2            =    $val->paid_tax2 / $diff;
                    $collection->paid_tax3            =    $val->paid_tax3 / $diff;
                    $collection->paid_sp_education_tax =    $val->paid_sp_education_tax / $diff;
                    $collection->paid_water_benefit    =    $val->paid_water_benefit / $diff;
                    $collection->paid_water_bill    =    $val->paid_water_bill / $diff;
                    $collection->paid_sp_water_cess    =    $val->paid_sp_water_cess / $diff;
                    $collection->paid_drain_cess    =    $val->paid_drain_cess / $diff;
                    $collection->paid_light_cess    =    $val->paid_light_cess / $diff;
                    $collection->paid_major_building =    $val->paid_major_building / $diff;
                    $collection->paid_open_ploat_tax =    $val->paid_open_ploat_tax / $diff;
                    #===== end =============
                    $collection->save();
                    #===== insert =========
                    DB::enableQueryLog();
                    $insertLog = "insert into old_trans_demand_logs (
                    tran_id, fyear,creation_type, demand_id, logs 
                )
                values(" .
                        $val->tran_id . "," .
                        "'" . $demand->fyear . "'," .
                        "'" . $type . "'," .
                        $demand->id . "," .
                        ($logs ? "'" . $logs . "'" : null)
                        . ");";
                    DB::select($insertLog);
                    // print_Var($collection->toArray());
                }
                $penalty = [
                    "tran_id" => $val->tran_id,
                    "head_name" => "Monthly Penalty",
                    "amount" => $val->penalty,
                    "is_rebate" => false,
                    "tran_date" => $val->tran_date,
                    "prop_id" => $val->property_id,
                ];
                PropPenaltyrebate::create($penalty);

                DB::commit();
            }
            print_var(DB::select("SELECT count(DISTINCT tran_id) FROM old_trans_demand_logs"));
        } catch (Exception $e) {
            DB::rollback();
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    /**
     * =====================📝 update Worng Tax Generated Of Vacand Land 📝==========================
     * ||                       created By : sandeep Bara
     * ||                       Date       : 05-12-2023
     * ||                       purpus     : Worng Demand Genrated Of Open Land in Ward id (10,11,2,3,16,17,18,28,29,49,39,40,41)
     * ||
     * ||==============================================================================================
     */
    public function TaxCorrection(Request $request)
    {
        try {
            $sql = "
            select prop_safs.id,prop_safs.saf_no,
                prop_properties.id as prop_id,
                prop_properties.holding_no
            from prop_safs
            join prop_properties on prop_properties.saf_id = prop_safs.id
            left join (
                select distinct property_id
                from tax_currectins
                where status =1
            )tax_currectins on tax_currectins.property_id = prop_properties.id
            where workflow_id in(1,2,3)
                and tax_currectins.property_id is null
            and prop_properties.prop_type_mstr_id = 4
            and prop_properties.ward_mstr_id in(10,11,2,3,16,17,18,28,29,49,39,40,41)
            limit 2;
            ";
            $property = DB::select($sql);
            $new = new Request();
            $new->merge($request->all());
            $controller = App::makeWith(ActiveSafController::class, ["iSafRepository", iSafRepository::class]);
            foreach ($property as $val) {
                DB::beginTransaction();
                $safDtls = PropSaf::find($val->id);
                $calculateSafTaxById = new \App\BLL\Property\Akola\CalculateSafTaxById($safDtls);
                $demand = $calculateSafTaxById->_GRID;
                foreach ($demand["fyearWiseTaxes"] as $cdemand) {
                    $oldDemands = PropDemand::where("fyear", $cdemand["fyear"])
                        ->where("property_id", $val->prop_id)
                        ->where("paid_status", 0)
                        ->first();
                    $insertSql = "insert into tax_currectins( property_id, demand_id, logs, user_id)
                        values(" .
                        $val->prop_id . "," .
                        ($oldDemands->id ?? 'null') . "," .
                        "'" . json_encode($oldDemands ?? 'null') . "'," .
                        (Auth()->user()->id ?? 0)
                        . ") ";
                    DB::select($insertSql);
                    if ($oldDemands) {
                        $oldDemands->tree_tax = $cdemand["treeTax"];
                        $oldDemands->total_tax = $cdemand["totalTax"];
                        $oldDemands->balance = $cdemand["totalTax"];
                        $oldDemands->sp_education_tax = $cdemand["stateEducationTax"];
                        $oldDemands->open_ploat_tax = $cdemand["openPloatTax"];
                        $oldDemands->due_open_ploat_tax = $cdemand["openPloatTax"];
                        $oldDemands->save();
                    }
                    // dd($demand["fyearWiseTaxes"],$oldDemands,$insertSql,$cdemand,$val);
                }
                DB::commit();
            }
        } catch (Exception $e) {
            DB::rollback();
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "");
        }
    }

    /**
     * | Update Mobile18
     */
    public function updateMobile(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "propertyId" => "required|integer",
                "ownerId"    => "required|integer",
                "mobileNo"   => "required|digits:10|regex:/[0-9]{10}/",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $ownerDetails = PropOwner::where('id', $req->ownerId)
                ->where('property_id', $req->propertyId)
                ->first();

            if (!$ownerDetails)
                throw new Exception("No Data Found Against this Owner");

            $ownerDetails->old_mobile_no = $ownerDetails->mobile_no;
            $ownerDetails->mobile_no = $req->mobileNo;
            $ownerDetails->save();

            return responseMsgs(true, "Mobile No Updated", [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    # save tc locations

    public function saveLocations(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "tcId"       => "required",
                "latitude"   => "required",
                "longitude"  => "required",
                "altitude"   => "nullable",
            ]
        );
        if ($validated->fails()) {
            return responseMsgs(false, $validated->errors(), "", "011610", "1.0", "", "POST", $req->deviceId ?? "");
        }

        try {
            $mlocations = new Location();
            $nowTime    = Carbon::now()->format('h:i:s A');
            $mlocations->tc_id     = $req->tcId;
            $mlocations->latitude  = $req->latitude;
            $mlocations->longitude = $req->longitude;
            $mlocations->altitude  = $req->altitude;
            $mlocations->time      = $nowTime;
            $mlocations->save();

            return responseMsgs(true, "tc location updated", [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    #get tc locations
    public function getTcLocations(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "tcId"       => "required",
                'perPage'     =>  'nullable',
                'perPage'     =>  'nullable',
            ]
        );
        if ($validated->fails())
            return responseMsgs(false, $validated->errors(), "", "011610", "1.0", "", "POST", $request->deviceId ?? "");
        try {
            $mLocation  = new Location();
            $tcId       = $request->tcId;
            $perPage = $request->perPage ? $request->perPage : 10;
            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");
            $wardId = $zoneId = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            $data  = $mLocation->getTcDetails($tcId)
                ->whereBetween(DB::raw("Cast(locations.created_at As date)"), [$fromDate, $uptoDate])
                ->orderBy("locations.id", "DESC");

            $paginator = $data->paginate($perPage);

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "get tc loacations ", remove_null($list), "011918", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "011918", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * ||==================Functions for Tc Location Save =======================
     *          created By : sandeep Bara
     *          Date       : 13-01-2024
     */

    public function saveLocationsV2(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "tcId"       => "required",
                "latitude"   => "required",
                "longitude"  => "required",
                "altitude"   => "nullable",
            ]
        );
        if ($validated->fails()) {
            return responseMsgs(false, $validated->errors(), "", "011610", "1.0", "", "POST", $req->deviceId ?? "");
        }

        try {
            $mlocations = new Location();
            if (!$mlocations->store($req)) {
                throw new Exception("tc location updated error");
            }
            return responseMsgs(true, "tc location Save", '', "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
