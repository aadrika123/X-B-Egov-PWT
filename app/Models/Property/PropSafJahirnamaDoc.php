<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PropSafJahirnamaDoc extends Model
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

    public function getJahirnamaBysafIdOrm($safId)
    {
        return self::where("saf_id",$safId)->where("status",1)->orderBy("id","DESC");
    }

    public function getjahirnamaDoc($safId)
    {
        return $this->getJahirnamaBysafIdOrm($safId)->select(DB::raw("concat('".$this->_docUrl."/',relative_path,'/',doc_name) as doc_path"),"*")->first();
    }
}
