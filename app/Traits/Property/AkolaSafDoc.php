<?php

namespace App\Traits\Property;

use App\Models\Masters\RefRequiredDocument;
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

    public function getPropTypeDocList($refSafs)
    {
        $propTypes = Config::get('PropertyConstaint.PROPERTY-TYPE');
        $propType = $refSafs->prop_type_mstr_id;
        $flip = flipConstants($propTypes);
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
            $this->_documentLists.= collect($this->_propDocList)->where('code', 'AKOLA_SALE')->first()->requirements;
            $this->_documentLists.= collect($this->_propDocList)->where('code', 'AKOLA_BULDING')->first()->requirements;
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
            $docName = str_replace("{","",str_replace("}","",$docName));
            $documents = collect();
            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId,$docName) {
                
                $uploadedDoc = $uploadedDocs->where('doc_code', $docName)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                if(!$uploadedDoc)
                {
                    $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                }
                if ($uploadedDoc) {
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode" => $item,
                        "ownerId" => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath" => $uploadedDoc->doc_path ?? "",
                        "verifyStatus" => $uploadedDoc->verify_status ?? "",
                        "remarks" => $uploadedDoc->remarks ?? "",
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
        
        return collect($filteredDocs)->values()??[];
    }
 }

