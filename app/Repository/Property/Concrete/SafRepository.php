<?php

namespace App\Repository\Property\Concrete;

use App\Http\Controllers\Property\SafDocController;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropSaf;
use App\Models\Workflows\WfRole;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\iSafRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | Repository for SAF
 */
class SafRepository implements iSafRepository
{
    protected $_COMMON_FUNCTION;
    protected $_MODULE_ID;
    protected $_TRADE_CONSTAINT;
    protected $_SafDocController;
    public function __construct()
    {
        $this->_COMMON_FUNCTION = new CommonFunction();
        $this->_MODULE_ID = Config::get('module-constants.PROPERTY_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_SafDocController = new SafDocController();
    }
    /**
     * | Meta Saf Details To Be Used in Various Common Functions
     */
    public function metaSafDtls($workflowIds)
    {
        return DB::table('prop_active_safs')
            ->leftJoin('prop_active_safs_owners as o', 'o.saf_id', '=', 'prop_active_safs.id')
            ->join('ref_prop_types as p', 'p.id', '=', 'prop_active_safs.prop_type_mstr_id')
            ->leftJoin('ulb_ward_masters as ward', 'ward.id', '=', 'prop_active_safs.ward_mstr_id')
            ->select(
                'prop_active_safs.payment_status',
                'prop_active_safs.doc_upload_status',
                'prop_active_safs.saf_no',
                'prop_active_safs.id',
                'prop_active_safs.workflow_id',
                'prop_active_safs.ward_mstr_id',
                'prop_active_safs.is_agency_verified',
                'prop_active_safs.is_field_verified',
                'prop_active_safs.is_geo_tagged',
                'ward.ward_name as ward_no',
                'prop_active_safs.prop_type_mstr_id',
                'prop_active_safs.appartment_name',
                DB::raw("string_agg(o.id::VARCHAR,',') as owner_id"),
                DB::raw("string_agg(o.owner_name,',') as owner_name"),
                DB::raw("string_agg(o.mobile_no,',') as mobile_no"),
                'p.property_type',
                'prop_active_safs.assessment_type as assessment',
                DB::raw("TO_CHAR(prop_active_safs.application_date, 'DD-MM-YYYY') as apply_date"),
                'prop_active_safs.parked',
                'prop_active_safs.prop_address',
                'prop_active_safs.applicant_name',
                'prop_active_safs.citizen_id',
                'prop_active_safs.is_field_verified',
                'prop_active_safs.is_agency_verified',
            )
            ->whereIn('workflow_id', $workflowIds)
            ->where('is_gb_saf', false)
            ->where(function ($query) {
                $query->whereNull('citizen_id')
                    ->orWhere('doc_upload_status', 1);
            });
    }

    /**
     * | Get Saf Details
     */
    public function getSaf($workflowIds)
    {
        $data = $this->metaSafDtls($workflowIds)
            ->where('payment_status', 0);
        return $data;
    }

    /**
     * | Get Property and Saf Transaction
     */
    public function getPropTransByCitizenUserId($userId, $userType)
    {
        $query = "SELECT 
                        prop_transactions.*,
                        TO_CHAR(prop_transactions.tran_date,'dd-mm-YYYY') as tran_date,
                        s.saf_no,
                        p.holding_no,
                        CASE 
                            WHEN (prop_transactions.saf_id IS NULL) THEN 'PROPERTY'
                            WHEN (prop_transactions.property_id IS NULL) THEN 'SAF'
                        END AS application_type
                        
                        FROM 
                        prop_transactions 
                        LEFT JOIN (
                        SELECT 
                            pas.id, 
                            pas.saf_no 
                        FROM 
                            prop_active_safs as pas
                            JOIN prop_transactions
                            ON (pas.id=prop_transactions.saf_id)
                            WHERE prop_transactions.$userType=$userId
                        UNION 
                        SELECT 
                            ps.id, 
                            ps.saf_no 
                        FROM 
                            prop_safs as ps
                            JOIN prop_transactions
                            ON (ps.id=prop_transactions.saf_id)
                            WHERE prop_transactions.$userType=$userId
                        ) AS s ON s.id = prop_transactions.saf_id 
                        LEFT JOIN prop_properties AS p ON p.id=prop_transactions.property_id
                        WHERE 
                        prop_transactions.$userType = $userId
                    ORDER BY prop_transactions.id DESC";
        $result = DB::select($query);
        return $result;
    }

    # this function are added by sandeep 
    /**
     * date : 26-10-2023
     * determin the application status
     */
    public function applicationStatus($applicationId,$docChequ2=true)
    {
        $refUser        = Auth()->user();
        $refUserId      = $refUser->id ?? 0;
        $refUlbId       = $refUser->ulb_id ?? 0;
        // $refWorkflowId  = $this->_WF_MASTER_Id;
        
        $application = PropSaf::find($applicationId);
        if (!$application) {
            $application = PropActiveSaf::find($applicationId);
        }
        if (!$application) {
            $application = DB::table("prop_rejected_safs")->find($applicationId);
        }
        $refWorkflowId = $application->workflow_id??0;
        $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId, $refUlbId);
        $rols  = WfRole::find($application->current_role??0);
        $status = "";
        
        if($application->gettable()==(new PropSaf)->gettable())
        {
            $status="Application is Approved";
        }
        elseif($application->gettable()==('prop_rejected_safs'))
        {
            $status="Application is Rejected";
        }
        elseif($application->parked && $application->current_role!=$application->initiator_role_id)
        {
            $status = "Application back to citizen by ". ($rols->role_name??"");
        }
        elseif ($application->saf_pending_status==1 && (($application->current_role != $application->finisher_role_id) || ($application->current_role == $application->finisher_role_id))) {            
            $status = "Application pending at " . ($rols->role_name??"");
        }
        elseif ($docChequ2 && strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""] && $application->citizen_id == $refUserId && $application->document_upload_status == 0) {
            $request = new Request(["applicationId" => $applicationId, "ulb_id" => $refUlbId, "user_id" => $refUserId]);
            $doc_status = $this->checkWorckFlowForwardBackord($request);
            if ($doc_status ) {#&& $application->payment_status == 0
                $status = "All Required Documents Are Uploaded";# But Payment is Pending 
            } elseif ($doc_status && $application->payment_status == 1) {
                $status = "Pending At Counter";
            } elseif (!$doc_status && $application->payment_status == 1) {
                $status = "Payment is Done But Document Not Uploaded";
            } elseif (!$doc_status) {# && $application->payment_status == 0
                $status = "Document Not Uploaded";#Payment is Pending And 
            }
        } 
        elseif($docChequ2 && $application->payment_status==0 && $application->document_upload_status == 0 ){
            $request = new Request(["applicationId" => $applicationId, "ulb_id" => $refUlbId, "user_id" => $refUserId,"workFlowId"=>$refWorkflowId]);
            $doc_status = $this->checkWorckFlowForwardBackord($request);            
            if ($doc_status) {
                $status = "All Required Documents Are Uploaded";
            }
            else{
                $status = "All Required Documents Are Not Uploaded";
            }
        } 
        return $status;
    }

    #=== check All Requird Document Are Uploaded Or Not ======

    public function checkWorckFlowForwardBackord(Request $request)
    {
        $user = Auth()->user();
        $user_id = $user->id ?? $request->user_id;
        $ulb_id = $user->ulb_id ?? $request->ulb_id;
        $refWorkflowId = $request->workFlowId;
        $allRolse = collect($this->_COMMON_FUNCTION->getAllRoles($user_id, $ulb_id, $refWorkflowId, 0, true));
        // $init_finish = $this->_COMMON_FUNCTION->iniatorFinisher($user_id, $ulb_id, $refWorkflowId);
        $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId, $ulb_id);
        $fromRole = [];
        if (!empty($allRolse)) {
            $fromRole = array_values(objToArray($allRolse->where("id", $request->senderRoleId)))[0] ?? [];
            $getUserRoll = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id,$refWorkflowId);
            ($fromRole && strtoupper($mUserType) != $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""] ) ? "" : $fromRole = $getUserRoll;
        }        
        if (strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""] || ($fromRole["can_upload_document"] ?? false) ||  ($fromRole["can_verify_document"] ?? false)) 
        {
            $documents = $this->_SafDocController->getDocList($request);
            if (!$documents->original["status"]) 
            {
                return false;
            }
            $applicationDoc = $documents->original["data"]["listDocs"];
            $ownerDoc = $documents->original["data"]["ownerDocs"];
            $appMandetoryDoc = $applicationDoc->whereIn("docType", ["R", "OR"]);
            $appUploadedDoc = $applicationDoc->whereNotNull("uploadedDoc");
            
            $appUploadedDocVerified = collect();
            $appUploadedDocRejected = collect();
            $appMadetoryDocRejected  = collect(); 
            $appUploadedDoc->map(function ($val) use ($appUploadedDocVerified,$appUploadedDocRejected,$appMadetoryDocRejected) {
                
                $appUploadedDocVerified->push(["is_docVerify" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"]) ? true : false) : true)]);
                $appUploadedDocRejected->push(["is_docRejected" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"]==2) ? true : false) : false)]);
                if(in_array($val["docType"],["R", "OR"]))
                {
                    $appMadetoryDocRejected->push(["is_docRejected" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"]==2) ? true : false) : false)]);
                }
            });
            $is_appUploadedDocVerified          = $appUploadedDocVerified->where("is_docVerify", false);
            $is_appUploadedDocRejected          = $appUploadedDocRejected->where("is_docRejected", true);
            $is_appUploadedMadetoryDocRejected  = $appMadetoryDocRejected->where("is_docRejected", true);
            
            $is_appMandUploadedDoc = $appMandetoryDoc->filter(function($val){
                return ($val["uploadedDoc"]=="" || $val["uploadedDoc"]==null);
            });
            
            $Wdocuments = collect();
            $ownerDoc->map(function ($val) use ($Wdocuments) {
                $ownerId = $val["ownerDetails"]["ownerId"] ?? "";
                $val["documents"]->map(function ($val1) use ($Wdocuments, $ownerId) {
                    $val1["ownerId"] = $ownerId;
                    $val1["is_uploded"] = (in_array($val1["docType"], ["R", "OR"]))  ? ((!empty($val1["uploadedDoc"])) ? true : false) : true;
                    $val1["is_docVerify"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"]) ? true : false) : true;
                    $val1["is_docRejected"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"]==2) ? true : false) : false;
                    $val1["is_madetory_docRejected"] = (!empty($val1["uploadedDoc"]) && in_array($val1["docType"],["R", "OR"]))?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"]==2) ? true : false) : false;
                    $Wdocuments->push($val1);
                });
            });
            $ownerMandetoryDoc              = $Wdocuments->whereIn("docType", ["R", "OR"]);
            $is_ownerUploadedDoc            = $Wdocuments->where("is_uploded", false);
            $is_ownerDocVerify              = $Wdocuments->where("is_docVerify", false);
            $is_ownerDocRejected            = $Wdocuments->where("is_docRejected", true);
            $is_ownerMadetoryDocRejected    = $Wdocuments->where("is_madetory_docRejected", true);
            
            if (($fromRole["can_upload_document"] ?? false) || strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]) 
            {
                return (empty($is_ownerUploadedDoc->all()) && empty($is_ownerDocRejected->all()) && empty($is_appMandUploadedDoc->all()) && empty($is_appUploadedDocRejected->all()));
            }
            if ($fromRole["can_verify_document"] ?? false) 
            {
                return (empty($is_ownerDocVerify->all()) && empty($is_appUploadedDocVerified->all()) && empty($is_ownerMadetoryDocRejected->all()) && empty($is_appUploadedMadetoryDocRejected->all()));
            }
        }
        return true;
    }
}
