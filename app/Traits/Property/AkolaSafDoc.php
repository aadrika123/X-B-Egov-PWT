<?php

namespace App\Traits\Property;

use App\Models\ActiveCitizen;
use App\Models\Masters\RefRequiredDocument;
use App\Models\Property\SecondaryDocVerification;
use App\Models\User;
use App\Models\Workflows\WfActiveDocument;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;

/**
 * | Trait Used for Gettting the Document Lists By Property Types and Owner Details
 * | Created On-19-10-2023 
 * | Created By-Sandeep Bara
 */

trait AkolaSafDoc
{
    use SafDoc;

    // public function getPropTypeDocList($refSafs)
    // {
    //     $propTypes = Config::get('PropertyConstaint.PROPERTY-TYPE');
    //     $transferTypes = Config::get('PropertyConstaint.TRANSFER_MODES');
    //     $flippedTransferMode = flipConstants($transferTypes);
    //     $propType = $refSafs->prop_type_mstr_id;
    //     $transferType = $refSafs->transfer_mode_mstr_id;
    //     $flip = flipConstants($propTypes);
    //     $this->_refSafs = $refSafs;
    //     $this->_documentLists = "";
    //     $this->_documentLists = collect($this->_propDocList)->where('code', 'AKOLA_APP_DOCS')->first()->requirements;
    //     // switch ($propType) {
    //     //     case $flip['FLATS / UNIT IN MULTI STORIED BUILDING']:
    //     //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
    //     //         break;
    //     //     case $flip['INDEPENDENT BUILDING']:
    //     //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
    //     //         break;
    //     //     case $flip['SUPER STRUCTURE']:
    //     //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
    //     //         break;
    //     //     case $flip['VACANT LAND']:
    //     //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_SALE')->first()->requirements;
    //     //         break;
    //     //     case $flip['OCCUPIED PROPERTY']:
    //     //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
    //     //         break;
    //     // }
    //     // if ($refSafs->assessment_type == 'Mutation' && $propType!=$flip['VACANT LAND'])
    //     {
    //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_SALE')->first()->requirements;
    //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
    //     }
    //     if ($flippedTransferMode['Imla'] == $transferType){
    //         $this->_documentLists = collect($this->_propDocList)->where('code', 'AKOLA_APP_DOCS')->first()->requirements;
    //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_IMLA')->first()->requirements;
    //     }


    //     return $this->_documentLists;
    // }

    # Added measuremrnt form for back office by prity pandey
    public function getPropTypeDocList($refSafs)
    {
        $propTypes = Config::get('PropertyConstaint.PROPERTY-TYPE');
        $transferTypes = Config::get('PropertyConstaint.TRANSFER_MODES');
        $flippedTransferMode = flipConstants($transferTypes);
        $propType = $refSafs->prop_type_mstr_id;
        $transferType = $refSafs->transfer_mode_mstr_id;
        $flip = flipConstants($propTypes);
        $user = Auth()->user();
        $userRole = (new \App\Repository\Common\CommonFunction())->getUserRoll($user->id,$user->ulb_id??0,$refSafs->workflow_id);
        // dd($userRole);
        $this->_refSafs = $refSafs;
        $this->_documentLists = "";
        $this->_documentLists = collect($this->_propDocList)->where('code', 'AKOLA_APP_DOCS')->first()->requirements;
        // switch ($propType) {
        //     case $flip['FLATS / UNIT IN MULTI STORIED BUILDING']:
        //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
        //         break;
        //     case $flip['INDEPENDENT BUILDING']:
        //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
        //         break;
        //     case $flip['SUPER STRUCTURE']:
        //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
        //         break;
        //     case $flip['VACANT LAND']:
        //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_SALE')->first()->requirements;
        //         break;
        //     case $flip['OCCUPIED PROPERTY']:
        //         $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
        //         break;
        // }
        // if ($refSafs->assessment_type == 'Mutation' && $propType!=$flip['VACANT LAND'])
        {
            $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_SALE')->first()->requirements;
            if($userRole && $userRole->role_id==11 && strtoupper($this->_refSafs->applied_by)=="TC"){
                $this->_documentLists .= collect($this->_propDocList)->where('code', 'MEASUREMENT_FORM')->first()->requirements;
            }
            $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
        }
        if ($flippedTransferMode['Imla'] == $transferType){
            $this->_documentLists = collect($this->_propDocList)->where('code', 'AKOLA_APP_DOCS')->first()->requirements;
            $this->_documentLists .= collect($this->_propDocList)->where('code', 'AKOLA_IMLA')->first()->requirements;
        }
        return $this->_documentLists;
    }

    public function filterDocumentV2($documentList, $refApplication, $ownerId = null)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $applicationId = $refApplication->id;
        $workflowId = $refApplication->workflow_id;
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $uploadedDocs = $mWfActiveDocument->getDocByRefIds($applicationId, $workflowId, $moduleId);
        $explodeDocs = collect(explode('#', $documentList))->filter();

        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs, $ownerId) {
            $document = explode(',', $explodeDoc);
            $key = array_shift($document);
            $docName =  array_shift($document);
            $docName = str_replace("{", "", str_replace("}", "", $docName));
            $documents = collect();
            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId, $docName) {

                $uploadedDoc = $uploadedDocs->where('doc_code', $docName)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                if (!$uploadedDoc) {
                    $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                        ->where('owner_dtl_id', $ownerId)
                        ->first();
                }
                if ($uploadedDoc) {
                    $seconderyData = (new SecondaryDocVerification())->SeconderyWfActiveDocumentById($uploadedDoc->id ?? 0);
                    $uploadeUser = $uploadedDoc->uploaded_by_type!="Citizen" ? User::find($uploadedDoc->uploaded_by??0) : ActiveCitizen::find($uploadedDoc->uploaded_by??0);
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode" => $item,
                        "ownerId" => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath" => $uploadedDoc->doc_path ?? "",
                        "verifyStatus" => ($uploadedDoc->verify_status ?? ""),
                        "remarks" => ($uploadedDoc->remarks ?? ""),
                        "uploadedBy" => ($uploadeUser->name ?? ($uploadeUser->user_name??"")) ." (".$uploadedDoc->uploaded_by_type.")",
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType'] = $key;
            $reqDoc['docName'] = $docName;
            $reqDoc['uploadedDoc'] = $documents->first();

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                $arr = [
                    "documentCode" => $doc,
                    "docVal" => ucwords($strReplace),
                    "uploadedDoc" => $uploadedDoc->doc_path ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $uploadedDoc->verify_status ?? "",
                    "remarks" => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });

        return collect($filteredDocs)->values() ?? [];
    }
    public function check($documentsList)
    {
        $applicationDoc = $documentsList["listDocs"];
        $ownerDoc = $documentsList["ownerDocs"];
        $appMandetoryDoc = $applicationDoc->whereIn("docType", ["R", "OR"]);
        $appUploadedDoc = $applicationDoc->whereNotNull("uploadedDoc");
        $appUploadedDocVerified = collect();
        $appUploadedDoc->map(function ($val) use ($appUploadedDocVerified) {
            $appUploadedDocVerified->push(["is_docVerify" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"] != 0) ? true : false) : true)]);
            $appUploadedDocVerified->push(["is_docRejected" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"] == 2) ? true : false) : false)]);
        });
        $is_appUploadedDocVerified = $appUploadedDocVerified->where("is_docVerify", false);
        $is_appUploadedDocRejected = $appUploadedDocVerified->where("is_docRejected", true);
        $is_appMandUploadedDoc  = $appMandetoryDoc->whereNull("uploadedDoc");
        $Wdocuments = collect();
        $ownerDoc->map(function ($val) use ($Wdocuments) {
            $ownerId = $val["ownerDetails"]["ownerId"] ?? "";
            $val["documents"]->map(function ($val1) use ($Wdocuments, $ownerId) {
                $val1["ownerId"] = $ownerId;
                $val1["is_uploded"] = (in_array($val1["docType"], ["R", "OR"]))  ? ((!empty($val1["uploadedDoc"])) ? true : false) : true;
                $val1["is_docVerify"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"] != 0) ? true : false) : true;
                $val1["is_docRejected"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"] == 2) ? true : false) : false;
                $Wdocuments->push($val1);
            });
        });
        $ownerMandetoryDoc = $Wdocuments->whereIn("docType", ["R", "OR"]);
        $is_ownerUploadedDoc = $Wdocuments->where("is_uploded", false);
        $is_ownerDocVerify = $Wdocuments->where("is_docVerify", false);
        $is_ownerDocRejected = $Wdocuments->where("is_docRejected", true);
        $data = [
            "docUploadStatus" => 0,
            "docVerifyStatus" => 0,
        ];
        $data["docUploadStatus"] = (empty($is_ownerUploadedDoc->all()) && empty($is_appMandUploadedDoc->all()) && empty($is_ownerDocRejected->all()) && empty($is_appUploadedDocRejected->all()));
        $data["docVerifyStatus"] =  (empty($is_ownerDocVerify->all()) && empty($is_appUploadedDocVerified->all()));
        return ($data);
    }
}
