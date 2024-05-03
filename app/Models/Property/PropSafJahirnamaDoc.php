<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PropSafJahirnamaDoc extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];
    public $_minAppholdeDays ; 
    public $_docUrl;
    public function __construct()
    {
        $this->_minAppholdeDays = (Config::get("PropertyConstaint.SAF_JARINAMA_HOLD_DAYS"));
        $this->_docUrl = Config::get('module-constants.DOC_URL');
    }   

    public function store($req)
    {        
        $jahirnama = new self();
        $minDays  = $req->minimumHoldDays ? $req->minimumHoldDays :$this->_minAppholdeDays;


        $jahirnama->saf_id          = $req->safId;        
        $jahirnama->doc_name        = $req->docName;
        $jahirnama->relative_path   = $req->relativePath;
        $jahirnama->user_id         = $req->userId ?? (Auth()->user()->id??null); 
        $jahirnama->generation_date  = Carbon::parse($req->generationDate)->format("Y-m-d");       
        if($req->docCode)
            $jahirnama->doc_code      = $req->docCode;
        if($minDays)
        {
            $jahirnama->min_holds_period_in_days = $minDays;
        }
        $this->deactivateJahirnama($req->safId);

        $jahirnama->save();
        return $jahirnama->id;
    }

    public function deactivateJahirnama($safId)
    {
        return self::where("saf_id",$safId)->where("status",1)->update(["status"=>0]);
    } 

    public function edit($id,$data)
    {
        $data = (object)$data;
        $old = self::find($id);
        $update["saf_id"]               = isset($data->safId) ? $data->safId : $old->saf_id;
        $update["jhirnama_no"]          = isset($data->jhirnamaNo) ? $data->jhirnamaNo : $old->jhirnama_no;
        $update["doc_code"]             = isset($data->docCode) ? $data->docCode : $old->doc_code;
        $update["doc_name"]             = isset($data->docName) ? $data->docName : $old->doc_name;
        $update["relative_path"]        = isset($data->relativePath) ? $data->relativePath : $old->relative_path;
        $update["user_id"]              = isset($data->userId) ? $data->userId : $old->user_id;
        $update["min_holds_period_in_days"] = isset($data->minimumHoldDays) ? $data->minimumHoldDays : $old->min_holds_period_in_days;
        $update["generation_date"]      = isset($data->generationDate) ? $data->generationDate : $old->generation_date;
        $update["is_update_objection"]  = isset($data->isUpdateObjection) ? $data->isUpdateObjection : $old->is_update_objection;
        $update["objection_updated_by"]  = isset($data->objectionUserId) ? $data->objectionUserId : $old->objection_updated_by;
        $update["has_any_objection"]    = isset($data->hasAnyObjection) ? $data->hasAnyObjection : $old->has_any_objection;
        $update["objection_comment"]    = isset($data->objectionComment) ? $data->objectionComment : $old->objection_comment;
        $update["objection_doc"]        = isset($data->objectionDocName) ? $data->objectionDocName : $old->objection_doc;
        $update["objection_doc_relative_path"]    = isset($data->objectionRelativePath) ? $data->objectionRelativePath : $old->objection_doc_relative_path;
        $update["status"]               = isset($data->status) ? $data->status : $old->status;
        return $old->update($update);
        
    }

    public function getJahirnamaBysafIdOrm($safId)
    {
        return self::where("saf_id",$safId)->where("status",1)->orderBy("id","DESC");
    }

    public function getjahirnamaDoc($safId)
    {
        return $this->getJahirnamaBysafIdOrm($safId)
                ->select(
                    "*",
                    DB::raw("
                    user_id AS uploaded_by, 'TC' as uploaded_by_type,
                    concat('".$this->_docUrl."/',relative_path,'/',doc_name) as doc_path,
                    TRIM('/ ' FROM CASE WHEN TRIM(objection_doc_relative_path) <>'' OR TRIM(objection_doc) <> '' THEN(
                            concat('".$this->_docUrl."/',objection_doc_relative_path,'/',objection_doc)
                        )ELSE NULL END
                    ) AS objection_doc_path
                    ")
                )->first();
    }
}
