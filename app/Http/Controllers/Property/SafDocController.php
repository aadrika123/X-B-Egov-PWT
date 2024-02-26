<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\MicroServices\DocUpload;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\PropSafsOwner;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropSafVerificationDtl;
use App\Models\Property\SecondaryDocVerification;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWorkflow;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Traits\Property\AkolaSafDoc;
use App\Traits\Property\SAF;
use App\Traits\Property\SafDoc;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config as FacadesConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On=01-02-2023 
 * | Created By=Anshu Kumar
 * | Created for=Document Upload 
 * | Status-Open
 */
class SafDocController extends Controller
{
    // use SafDoc;
    use AkolaSafDoc;
    use SAF;
    /**
     * | Get Document Lists
     */
    public function getDocList(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|numeric'
        ]);

        try {
            $mActiveSafs = new PropActiveSaf();
            $safsOwners = new PropActiveSafsOwner();
            $refSafs = $mActiveSafs->getSafNo($req->applicationId);                      // Get Saf Details
            if (!$refSafs)
                throw new Exception("Application Not Found for this id");
            $refSafOwners = $safsOwners->getOwnersBySafId($req->applicationId);
            $propTypeDocs['listDocs'] = $this->getSafDocLists($refSafs);                // Current Object(Saf Docuement List)

            $safOwnerDocs['ownerDocs'] = collect($refSafOwners)->map(function ($owner) use ($refSafs) {
                return $this->getOwnerDocLists($owner, $refSafs);
            }); 
            
            $totalDocLists = collect($propTypeDocs)->merge($safOwnerDocs);
            $status =  $this->check($totalDocLists);
            if($refSafs->doc_upload_status && isset($status["docUploadStatus"]))
            {
                $refSafs->doc_upload_status = (!$status["docUploadStatus"]) ? 0 : $refSafs->doc_upload_status;
            }            
            $totalDocLists['docUploadStatus'] = $refSafs->doc_upload_status;
            $totalDocLists['docVerifyStatus'] = $refSafs->doc_verify_status;
            $totalDocLists['paymentStatus'] = $refSafs->payment_status;
            $totalDocLists['isCitizen'] = ($refSafs->citizen_id == null) ? false : true; 
            $totalDocLists['citizenCanSendOfficer'] = ($refSafs->doc_upload_status && ($refSafs->initiator_role_id == $refSafs->current_role || $refSafs->parked)) ? true  : false;

            return responseMsgs(true, "", remove_null($totalDocLists), "010203", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    /**
     * | Gettting Document List (1)
     * | Transer type initial mode 0 for other Case
     */
    public function getSafDocLists($refSafs)
    {
        $documentList = $this->getPropTypeDocList($refSafs);
        $filteredDocs = $this->filterDocumentV2($documentList, $refSafs);                            // function(1.2)
        return $filteredDocs;
    }

    /**
     * | Get Owner Document Lists
     */
    public function getOwnerDocLists($refOwners, $refSafs)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $moduleId = FacadesConfig::get('module-constants.PROPERTY_MODULE_ID');
        $documentList = $this->getOwnerDocs($refOwners);

        if (!empty($documentList)) {
            $ownerPhoto = $mWfActiveDocument->getOwnerPhotograph($refSafs['id'], $refSafs->workflow_id, $moduleId, $refOwners['id']);
            $filteredDocs['ownerDetails'] = [
                'ownerId' => $refOwners['id'],
                'name' => $refOwners['owner_name'],
                'mobile' => $refOwners['mobile_no'],
                'guardian' => $refOwners['guardian_name'],
                'uploadedDoc' => $ownerPhoto->doc_path ?? "",
                'docId' => $ownerPhoto->doc_id ?? "",
                'verifyStatus' => ($refSafs->payment_status == 1) ? ($ownerPhoto->verify_status ?? "") : 0
            ];
            $filteredDocs['documents'] = $this->filterDocument($documentList, $refSafs, $refOwners['id']);                                     // function(1.2)
        } else
            $filteredDocs = [];

        $filteredDocs['ownerDetails']['reqDocCount'] = $filteredDocs['documents']->count();
        $filteredDocs['ownerDetails']['uploadedDocCount'] = $filteredDocs['documents']->whereNotNull('uploadedDoc')->count();
        return $filteredDocs;
    }

    /**
     * | Filter Document(1.2)
     */
    public function filterDocument($documentList, $refSafs, $ownerId = null)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $safId = $refSafs->id;
        $workflowId = $refSafs->workflow_id;
        $moduleId = FacadesConfig::get('module-constants.PROPERTY_MODULE_ID');
        $docUrl = FacadesConfig::get('module-constants.DOC_URL');
        $uploadedDocs = $mWfActiveDocument->getDocByRefIds($safId, $workflowId, $moduleId);
        $explodeDocs = collect(explode('#', $documentList));

        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs, $ownerId, $refSafs, $docUrl) {
            $document = explode(',', $explodeDoc);
            $key = array_shift($document);
            $label = array_shift($document);
            $documents = collect();

            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId, $refSafs, $docUrl) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();

                if ($uploadedDoc) {
                    $seconderyData = (new SecondaryDocVerification())->SeconderyWfActiveDocumentById($uploadedDoc->id??0);
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode" => $item,
                        "ownerId" => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath" =>  $uploadedDoc->doc_path ?? "",
                        // "verifyStatus" => $refSafs->payment_status == 1 ? ($uploadedDoc->verify_status ?? "") : 0,
                        "verifyStatus" =>  ($uploadedDoc->verify_status ?? 0),
                        "remarks" => ($uploadedDoc->remarks ?? ""),
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType'] = $key;
            $docName = substr($label, 1, -1);
            $reqDoc['docName'] = $docName;

            // Check back to citizen status
            $uploadedDocument = $documents->sortByDesc('uploadedDocId')->first();
            if (collect($uploadedDocument)->isNotEmpty() && $uploadedDocument['verifyStatus'] == 2) {
                $reqDoc['btcStatus'] = true;
            } else
                $reqDoc['btcStatus'] = false;
            $reqDoc['uploadedDoc'] = $uploadedDocument;                      // Get Last Uploaded Document

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs, $refSafs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                $arr = [
                    "documentCode" => $doc,
                    "docVal" => ucwords($strReplace),
                    "uploadedDoc" => $uploadedDoc->doc_path ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $refSafs->payment_status == 1 ? ($uploadedDoc->verify_status ?? "") : 0,
                    "remarks" => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });
        return $filteredDocs;
    }

    /**
     * | Created for Document Upload for SAFs(2)
     */
    public function docUpload(Request $req)
    {
        $extention = $req->document->getClientOriginalExtension();
        $rules = [
            "applicationId" => "required|numeric",
            "document" => "required|mimes:pdf,jpeg,png,jpg|" . (strtolower($extention) == 'pdf' ? 'max:10240' : 'max:5120'),
            "docCode" => "required",
            "docCategory" => "required|string",
            "ownerId" => "nullable|numeric"
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

        $req->validate([
            "applicationId" => "required|numeric",
            "document" => "required|mimes:pdf,jpeg,png,jpg",
            "docCode" => "required",
            "docCategory" => "required|string",
            "ownerId" => "nullable|numeric"
        ]);
        $extention = $req->document->getClientOriginalExtension();
        $req->validate([
            'document' => $extention == 'pdf' ? 'max:10240' : 'max:5120',
        ]);

        try {
            // Variable Assignments
            $metaReqs = array();
            $docUpload = new DocUpload;
            $mWfActiveDocument = new WfActiveDocument();
            $mActiveSafs = new PropActiveSaf();
            $relativePath = FacadesConfig::get('PropertyConstaint.SAF_RELATIVE_PATH');
            $propModuleId = FacadesConfig::get('module-constants.PROPERTY_MODULE_ID');

            // Derivative Assignments
            $getSafDtls = $mActiveSafs->getSafNo($req->applicationId);
            $refImageName = $req->docCode;
            $refImageName = str_replace('/', '-', $refImageName);
            $refImageName = $getSafDtls->id . '-' . $refImageName;
            $document = $req->document;
            $imageName = $docUpload->upload($refImageName, $document, $relativePath);

            $metaReqs['module_id'] = $propModuleId;
            $metaReqs['active_id'] = $getSafDtls->id;
            $metaReqs['workflow_id'] = $getSafDtls->workflow_id;
            $metaReqs['ulb_id'] = $getSafDtls->ulb_id;
            $metaReqs['relative_path'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['doc_code'] = $req->docCode;
            $metaReqs['doc_category'] = $req->docCategory;

            if ($req->docCode == 'PHOTOGRAPH') {
                $metaReqs['verify_status'] = 1;
            }

            $metaReqs['owner_dtl_id'] = $req->ownerId;
            $documents = $mWfActiveDocument->isDocCategoryExists($getSafDtls->id, $getSafDtls->workflow_id, $propModuleId, $req->docCategory, $req->ownerId)->get();
            $ifDocCategoryExist = collect();
            if ($req->docCode == 'PHOTOGRAPH')
                $ifDocCategoryExist = collect($documents)->where('verify_status', 1)->first();   // Checking if the document is already existing or not
            else
                $ifDocCategoryExist = collect($documents)->where('verify_status', 0)->first();   // Checking if the document is already existing or not

            DB::beginTransaction();
            if (collect($ifDocCategoryExist)->isEmpty()) {
                // Check if the New Uploaded Document is Rejected Or Not
                $isDocRejected = collect($documents)->where('verify_status', 2)->first();
                if ($isDocRejected)
                    $isDocRejected->update(['status' => 0]);
                $mWfActiveDocument->create($metaReqs);           // Store New Document
            }

            if (collect($ifDocCategoryExist)->isNotEmpty())
                $mWfActiveDocument->edit($ifDocCategoryExist, $metaReqs);       // Update Existing Document

            $docUploadStatus = $this->checkFullDocUpload($req->applicationId);
            if ($docUploadStatus == 1) {                                        // Doc Upload Status Update
                $getSafDtls->doc_upload_status = 1;
                // if ($getSafDtls->parked == true)                                // Case of Back to Citizen
                    // $getSafDtls->parked = false;

                $getSafDtls->save();
            }
            DB::commit();
            return responseMsgs(true, "Document Uploadation Successful", "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | View Saf Uploaded Documents 
     */
    public function getUploadDocuments(Request $req)
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
            $moduleId = FacadesConfig::get('module-constants.PROPERTY_MODULE_ID');              // 1

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
            $owners = $mActiveSafs->getTable()=="prop_active_safs" ? $mActiveSafsOwners->getOwnersBySafId($safDetails->id) : $mSafsOwners->getOwnersBySafId($safDetails->id) ;            
            $documents = $documents->map(function ($val) use ($sameWorkRoles, $userRole,$owners) {
                $seconderyData = (new SecondaryDocVerification())->SeconderyWfActiveDocumentById($val->id);
                $val->verify_status_secondery = $seconderyData ? $seconderyData->verify_status : 0;
                $val->remarks_secondery = $seconderyData ? $seconderyData->remarks :  "";
                $val->owner_name = (collect($owners)->where("id",$val->owner_dtl_id)->first())->owner_name?? "";
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

            return responseMsgs(true, ["docVerifyStatus" => $safDetails->doc_verify_status], remove_null($documents), "010102", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * get uploaded naksha
     */
    public function getUploadDocumentNaksha(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|numeric'
        ]);
        try{ 
            $safController = App::makeWith(ActiveSafController::class,["iSafRepository",iSafRepository::class]); 
            $safDetailsResponce = $safController->getStaticSafDetails($req); 
            if(!$safDetailsResponce->original["status"])
            {
                return $safDetailsResponce;
            }   
            $saf = $safDetailsResponce->original["data"]->all();
            $response = $this->getUploadDocuments($req);
            if(!$response->original["status"])
            {
                return $response;
            }            
            $map = collect($response->original["data"])->where("doc_category","Layout sanction Map")->first(); 
            $roughMap = collect($response->original["data"])->where("doc_category","naksha")->first();           
            if($map)
            {
                $map["ext"] = strtolower(collect(explode(".",$map["doc_path"]))->last());
            }
            if($roughMap)
            {
                $roughMap["ext"] = strtolower(collect(explode(".",$roughMap["doc_path"]))->last());
            }
            $saf["is_naksha_uploaded"] = $map? true: false;
            $saf["is_measurement_sheet_uploaded"] = $roughMap? true: false;
            $saf["naksha"] = $map;
            $saf["measurement_sheet"] = $roughMap;
            return responseMsgs(true, "date fetched", $saf, "010202", "1.1", "", "POST", $req->deviceId ?? "");
        }
        catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010202", "1.1", "", "POST", $req->deviceId ?? "");
        }

    }

    public function nakshaVerifyReject(Request $req)
    {
        {
            $req->validate([
                'id' => 'required|digits_between:1,9223372036854775807',
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                'docRemarks' =>  $req->docStatus == "Rejected" ? 'required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/' : "nullable",
                'docStatus' => 'required|in:Verified,Rejected'
            ]);
    
            try {
                // Variable Assignments
                $mWfDocument = new WfActiveDocument();
                $mActiveSafs = new PropActiveSaf();
                $mWfRoleusermap = new WfRoleusermap();
                $mSecondaryDocVerification = new SecondaryDocVerification();
                $wfDocId = $req->id;
                $userId = authUser($req)->id;
                $applicationId = $req->applicationId;
                $wfLevel = FacadesConfig::get('PropertyConstaint.SAF-LABEL');
                $trustDocCode = FacadesConfig::get('PropertyConstaint.TRUST_DOC_CODE');
                // Derivative Assigments
                $activeDocument = $mWfDocument::findOrFail($wfDocId);
                $safDtls = $mActiveSafs->getSafNo($applicationId);
                $safReq = new Request([
                    'userId' => $userId,
                    'workflowId' => $safDtls->workflow_id
                ]);
                $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfId($safReq);
                if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                    throw new Exception("Role Not Available");
    
                $senderRoleId = $senderRoleDtls->wf_role_id;                
                if ($senderRoleId != $wfLevel['UTC'])                                // Authorization for Dealing Assistant Only
                    throw new Exception("You are not Authorized");
    
                if (!$safDtls || collect($safDtls)->isEmpty())
                    throw new Exception("Saf Details Not Found");
    
                $ifFullDocVerified = $this->ifFullDocVerified($applicationId);       // (Current Object Derivative Function 4.1)
                // if ($ifFullDocVerified == 1)
                //     throw new Exception("Document Fully Verified");
    
                DB::beginTransaction();
                if ($req->docStatus == "Verified") {
                    $status = 1;
                    // trust verification in case of trust upload
                    if ($activeDocument->doc_code == $trustDocCode)
                        $safDtls->is_trust_verified = true;
                }
                if ($req->docStatus == "Rejected") {
                    $status = 2;
                    // For Rejection Doc Upload Status and Verify Status will disabled
                    $safDtls->doc_upload_status = 0;
                    $safDtls->doc_verify_status = 0;
    
                    if ($activeDocument->doc_code == $trustDocCode)
                        $safDtls->is_trust_verified = false;
    
                    $safDtls->save();
                }
    
                $reqs = [
                    'remarks' => $req->docRemarks,
                    'verify_status' => $status,
                    'action_taken_by' => $userId
                ];
                $mWfDocument->docVerifyReject($wfDocId, $reqs);
                if ($req->docStatus == 'Verified')
                    $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId, $req->docStatus);
                else
                    $ifFullDocVerifiedV1 = 0;                                       // In Case of Rejection the Document Verification Status will always remain false
    
                // dd($ifFullDocVerifiedV1);
                if ($ifFullDocVerifiedV1 == 1) {                                     // If The Document Fully Verified Update Verify Status
                    $safDtls->doc_verify_status = 1;
                    $safDtls->save();
                }
                #=====secondery Document Verification Entery=======
                $seconderyId = $mSecondaryDocVerification->insertSecondryDoc($wfDocId);
                $seconderyDoc = SecondaryDocVerification::find($seconderyId);
                $seconderyDoc->verify_status = $status??1;
                $seconderyDoc->save();
                DB::commit();
                return responseMsgs(true, $req->docStatus . " Successfully", "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
            } catch (Exception $e) {
                DB::rollBack();
                return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
            }
        }
    }

    public function nakshaAreaOfPloteUpdate(Request $req)
    { 
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|numeric',
                "IsDouble"=>"required|boolean",
                "areaOfPlot"=>"nullable|required_if:IsDouble,in,0,false|numeric|min:0",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status'    => false,
                'message'   => 'validation error',
                'errors'    => $validated->errors()
            ]);
        } 
        try{
            $mActiveSafs = new PropActiveSaf();
            $mActiveSafsFloors = new PropActiveSafsFloor();
            $mVerification = new PropSafVerification();
            $verificationDtl = collect();
            $mVerificationDtls = new PropSafVerificationDtl();
            $mPropSafsFloors = new PropSafsFloor();
            
            $refSafs = $mActiveSafs->getSafNo($req->applicationId);                      // Get Saf Details
            if (!$refSafs){
                throw new Exception("Application Not Found for this id");
            }
            $refSafsUpdate = $mActiveSafs->getSafNo($req->applicationId);
            
            $safVerification = $mVerification->where("saf_id", $refSafs->id)->where("status", 1)->orderBy("id", "DESC")->first();
            $verificationDtl = $mVerificationDtls->getVerificationDtls($safVerification->id ?? 0);
            if ($safVerification) {
                $refSafs = $this->addjustVerifySafDtls($refSafs, $safVerification);
                $refSafs = $this->addjustVerifySafDtlVal($refSafs);
            }

            $getFloorDtls = $mActiveSafsFloors->getFloorsBySafId($refSafs->id);      // Model Function to Get Floor Details
            if (collect($getFloorDtls)->isEmpty())
                $getFloorDtls = $mPropSafsFloors->getFloorsBySafId($refSafs->id);


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
            $totalArea = $refSafs->area_of_plot;
            if($refSafs->prop_type_mstr_id !=4)
            {
                $totalArea = $getFloorDtls->sum("builtup_area");
            }

            $diffArea = ($totalArea - $req->areaOfPlot) > 0 ? ($totalArea - $req->areaOfPlot) : 0;
            $sms = $req->IsDouble ? "Double Taxation Apply" : ("100 % Penalty Apply On " .($diffArea));
            DB::beginTransaction();
            $refSafsUpdate->is_allow_double_tax = $req->IsDouble;
            $refSafsUpdate->naksha_area_of_plot = $req->areaOfPlot;
            $refSafsUpdate->update();
            DB::commit();
            
            return responseMsgs(true, $sms, "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
        catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check Full Upload Doc Status
     */
    public function checkFullDocUpload($applicationId)
    {
        $mActiveSafs = new PropActiveSaf();
        $mWfActiveDocument = new WfActiveDocument();
        $refSafs = $mActiveSafs->getSafNo($applicationId);                      // Get Saf Details
        $refReq = [
            'activeId' => $applicationId,
            'workflowId' => $refSafs->workflow_id,
            'moduleId' => 1
        ];
        $req = new Request($refReq);
        $refDocList = $mWfActiveDocument->getDocsByActiveId($req);
        return $this->isAllDocs($applicationId, $refDocList, $refSafs);
    }

    /**
     * | Document Verify Reject (04)
     */
    public function docVerifyReject(Request $req)
    {
        $req->validate([
            'id' => 'required|digits_between:1,9223372036854775807',
            'applicationId' => 'required|digits_between:1,9223372036854775807',
            'docRemarks' =>  $req->docStatus == "Rejected" ? 'required|regex:/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/' : "nullable",
            'docStatus' => 'required|in:Verified,Rejected'
        ]);

        try {
            // Variable Assignments
            $mWfDocument = new WfActiveDocument();
            $mActiveSafs = new PropActiveSaf();
            $mWfRoleusermap = new WfRoleusermap();
            $mSecondaryDocVerification = new SecondaryDocVerification();
            $wfDocId = $req->id;
            $userId = authUser($req)->id;
            $applicationId = $req->applicationId;
            $wfLevel = FacadesConfig::get('PropertyConstaint.SAF-LABEL');
            $trustDocCode = FacadesConfig::get('PropertyConstaint.TRUST_DOC_CODE');
            // Derivative Assigments
            $activeDocument = $mWfDocument::findOrFail($wfDocId);
            $safDtls = $mActiveSafs->getSafNo($applicationId);
            $safReq = new Request([
                'userId' => $userId,
                'workflowId' => $safDtls->workflow_id
            ]);
            $senderRoleDtls = $mWfRoleusermap->getRoleByUserWfId($safReq);
            if (!$senderRoleDtls || collect($senderRoleDtls)->isEmpty())
                throw new Exception("Role Not Available");

            $senderRoleId = $senderRoleDtls->wf_role_id;
            #secondry Document Verification
            if ($safDtls->doc_verify_status && ($senderRoleId != $wfLevel['DA'])) {
                return $this->seconderyDocverify($req);
            }
            if ($senderRoleId != $wfLevel['DA'])                                // Authorization for Dealing Assistant Only
                throw new Exception("You are not Authorized");

            if (!$safDtls || collect($safDtls)->isEmpty())
                throw new Exception("Saf Details Not Found");

            $ifFullDocVerified = $this->ifFullDocVerified($applicationId);       // (Current Object Derivative Function 4.1)
            // if ($ifFullDocVerified == 1)
            //     throw new Exception("Document Fully Verified");

            DB::beginTransaction();
            if ($req->docStatus == "Verified") {
                $status = 1;
                // trust verification in case of trust upload
                if ($activeDocument->doc_code == $trustDocCode)
                    $safDtls->is_trust_verified = true;
            }
            if ($req->docStatus == "Rejected") {
                $status = 2;
                // For Rejection Doc Upload Status and Verify Status will disabled
                $safDtls->doc_upload_status = 0;
                $safDtls->doc_verify_status = 0;

                if ($activeDocument->doc_code == $trustDocCode)
                    $safDtls->is_trust_verified = false;

                $safDtls->save();
            }

            $reqs = [
                'remarks' => $req->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $userId
            ];
            $mWfDocument->docVerifyReject($wfDocId, $reqs);
            if ($req->docStatus == 'Verified')
                $ifFullDocVerifiedV1 = $this->ifFullDocVerified($applicationId, $req->docStatus);
            else
                $ifFullDocVerifiedV1 = 0;                                       // In Case of Rejection the Document Verification Status will always remain false

            // dd($ifFullDocVerifiedV1);
            if ($ifFullDocVerifiedV1 == 1) {                                     // If The Document Fully Verified Update Verify Status
                $safDtls->doc_verify_status = 1;
                $safDtls->save();
            }
            #=====secondery Document Verification Entery=======
            $mSecondaryDocVerification->insertSecondryDoc($wfDocId);
            DB::commit();
            return responseMsgs(true, $req->docStatus . " Successfully", "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
    }

    private function seconderyDocverify(Request $req)
    {
        try {

            $mWfDocument = new WfActiveDocument();
            $mActiveSafs = new PropActiveSaf();
            $mCOMMON_FUNCTION = new CommonFunction();
            $mSecondaryDocVerification = new SecondaryDocVerification();
            $wfDocId = $req->id;
            $user = Auth()->user();
            $refUserId = $user->id ?? 0;
            $applicationId = $req->applicationId;
            $activeDocument = $mWfDocument::findOrFail($wfDocId);
            $safDtls = $mActiveSafs->getSafNo($applicationId);
            if (!$safDtls || collect($safDtls)->isEmpty()) {
                throw new Exception("Saf Details Not Found");
            }
            # trust verification in case of trust upload
            if ($req->docStatus == "Verified") {
                $status = 1;
            }
            # For Rejection Doc Upload Status and Verify Status will disabled
            if ($req->docStatus == "Rejected") {
                $status = 2;
                $safDtls->doc_verify_status = 0;
                $safDtls->doc_verify_status = 0;
            }$reqs = [
                'remarks' => $req->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $refUserId
            ];
            $mWfDocument->docVerifyReject($wfDocId, $reqs);
            $refUlbId = $safDtls->ulb_id;
            $workflowId = $safDtls->workflow_id;
            $userRole = $mCOMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $workflowId);
            $reqs = [
                'remarks' => $req->docRemarks,
                'verify_status' => $status,
                'action_taken_by' => $refUserId,
                'role_id' => $userRole->role_id ?? 0,
            ];

            $sameWorkRoles = $mCOMMON_FUNCTION->getReactionActionTakenRole($refUserId, $refUlbId, $workflowId, "doc_verify");

            if (count($sameWorkRoles) <= 1 || !$userRole || $userRole->role_id == ($sameWorkRoles->first())["id"] || !$userRole->can_verify_document) {
                // throw new Exception("Forbidan For Take Action");

            }

            DB::beginTransaction();
            $mSecondaryDocVerification->docVerifyReject($wfDocId, $reqs);
            DB::commit();
            return responseMsgs(true, $req->docStatus . " Successfully", "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010204", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Check if the Document is Fully Verified or Not (4.1)
     */
    public function ifFullDocVerified($applicationId)
    {
        $mActiveSafs = new PropActiveSaf();
        $mWfActiveDocument = new WfActiveDocument();
        $refSafs = $mActiveSafs->getSafNo($applicationId);                      // Get Saf Details
        $refReq = [
            'activeId' => $applicationId,
            'workflowId' => $refSafs->workflow_id,
            'moduleId' => 1
        ];
        $refDocList = $mWfActiveDocument->getVerifiedDocsByActiveId($refReq);
        return $this->isAllDocs($applicationId, $refDocList, $refSafs);
    }

    /**
     * | Checks the Document Upload Or Verify Status
     * | @param activeApplicationId
     * | @param refDocList list of Verified and Uploaded Documents
     * | @param refSafs saf Details
     */
    public function isAllDocs($applicationId, $refDocList, $refSafs)
    {
        $docList = array();
        $verifiedDocList = array();
        $mSafsOwners = new PropActiveSafsOwner();
        $refSafOwners = $mSafsOwners->getOwnersBySafId($applicationId);
        $propListDocs = $this->getPropTypeDocList($refSafs);
        $docList['propDocs'] = explode('#', $propListDocs);
        $ownerDocList = collect($refSafOwners)->map(function ($owner) {
            return [
                'ownerId' => $owner->id,
                'docs'  => explode('#', $this->getOwnerDocs($owner))
            ];
        });
        $docList['ownerDocs'] = $ownerDocList;

        $verifiedDocList['ownerDocs'] = $refDocList->where('owner_dtl_id', '!=', null)->values();
        $verifiedDocList['propDocs'] = $refDocList->where('owner_dtl_id', null)->values();
        $collectUploadDocList = collect();
        collect($verifiedDocList['propDocs'])->map(function ($item) use ($collectUploadDocList) {
            return $collectUploadDocList->push($item['doc_code']);
        });
        $mPropDocs = collect($docList['propDocs']);

        // Property List Documents
        $flag = 1;
        foreach ($mPropDocs as $item) {
            if (!$item) {
                continue;
            }
            $explodeDocs = explode(',', $item);
            $type = $explodeDocs[0] ?? "O";
            array_shift($explodeDocs);
            foreach ($explodeDocs as $explodeDoc) {
                $changeStatus = 0;
                if (in_array($explodeDoc, $collectUploadDocList->toArray())) {
                    $changeStatus = 1;
                    break;
                }
            }
            if ($changeStatus == 0 && $type == "R") {
                $flag = 0;
                break;
            }
        }

        if ($flag == 0)
            return 0;

        // Owner Documents
        $ownerFlags = 1;
        foreach ($ownerDocList as $item) {
            $ownerUploadedDocLists = $verifiedDocList['ownerDocs']->where('owner_dtl_id', $item['ownerId']);
            $arrayOwners = array();
            foreach ($ownerUploadedDocLists as $list) {
                array_push($arrayOwners, $list->doc_code);
            }

            foreach ($item['docs'] as $doc) {
                $explodeDocs = explode(',', $doc);
                $type = $explodeDocs[0] ?? "O";
                array_shift($explodeDocs);
                foreach ($explodeDocs as $explodeDoc) {
                    $changeStatusV1 = 0;
                    if (in_array($explodeDoc, $arrayOwners)) {
                        $changeStatusV1 = 1;
                        break;
                    }
                }
                if ($changeStatusV1 == 0 && $type == "R") {
                    $ownerFlags = 0;
                    break;
                }
            }
            if ($changeStatusV1 == 0)
                break;
        }
        if ($ownerFlags == 0)
            return 0;
        else
            return 1;
    }


}
