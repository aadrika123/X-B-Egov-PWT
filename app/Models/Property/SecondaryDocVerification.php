<?php

namespace App\Models\Property;

use App\Models\Advertisements\WfActiveDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecondaryDocVerification extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];
    public $timestamps=false;

    public function SeconderyWfActiveDocumentById($wfActDocId)
    {
        return self::where("wf_active_documents_id",$wfActDocId)
                    ->where("status",1)
                    ->orderBy("id","DESC")
                    ->first();
    }

    public function metaReqs(WfActiveDocument $req)
    {
        return [
            "wf_active_documents_id" => $req->id,
            "active_id"         => $req->active_id,
            "owner_dtl_id"       => $req->owner_dtl_id
        ];
    }


    public function insertSecondryDoc($wfActDocId)
    {
        $WfActiveDocument = WfActiveDocument::find($wfActDocId);
        if($old = $this->SeconderyWfActiveDocumentById($wfActDocId))
        {
            $updat["verify_status"]=$WfActiveDocument->verify_status==0?0: $old->verify_status;
            $updat["status"]=$WfActiveDocument->status;
            $old->update($updat);
            return  $old->id;
        }        
        $metaReqs = $this->metaReqs($WfActiveDocument);        
        return SecondaryDocVerification::create($metaReqs)->id;
    }

    public function docVerifyReject($wfActDocId, $req)
    {
        $document = $this->SeconderyWfActiveDocumentById($wfActDocId);
        if($document)
                $document->update($req);
    }

    public function readRejectedDocuments(array $metaReqs)
    {
        return self::select("wf_active_documents.*","secondary_doc_verifications.verify_status")
            ->join("wf_active_documents","wf_active_documents.id","secondary_doc_verifications.wf_active_documents_id")
            ->where('secondary_doc_verifications.active_id', $metaReqs['activeId'])
            ->where('secondary_doc_verifications.verify_status', 2)
            ->where('secondary_doc_verifications.status', 1)
            ->get();
    }



}
