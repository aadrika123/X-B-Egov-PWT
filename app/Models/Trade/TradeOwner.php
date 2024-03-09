<?php

namespace App\Models\Trade;

use App\Models\Workflows\WfActiveDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeOwner extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $connection;
    public function __construct($DB=null)
    {
       $this->connection = $DB ? $DB:"pgsql_trade";
    }

    public function application()
    {
        return $this->belongsTo(TradeLicence::class,'temp_id',"id");
    }

    public function renewalApplication()
    {
        return $this->belongsTo(TradeRenewal::class,'temp_id',"id");
    }

    public function docDtl()
    {
        return $this->hasManyThrough(WfActiveDocument::class,TradeLicence::class,'id',"active_id","temp_id","id")
                ->whereColumn("wf_active_documents.workflow_id","trade_licences.workflow_id")
                ->where("wf_active_documents.owner_dtl_id",$this->id)
                ->where("wf_active_documents.status",1);
    }

    public function renewalDocDtl()
    {
        return $this->hasManyThrough(WfActiveDocument::class,TradeRenewal::class,'id',"active_id","temp_id","id")
                ->whereColumn("wf_active_documents.workflow_id","trade_renewals.workflow_id")
                ->where("wf_active_documents.owner_dtl_id",$this->id)
                ->where("wf_active_documents.status",1);
    }

    public static function owneresByLId($licenseId)
    {
        return self::select("*")
            ->where("temp_id", $licenseId)
            ->where("is_active", True)
            ->get();
    }

    public function getFirstOwner($licenseId)
    {
        return self::select('owner_name', 'mobile_no')
            ->where('temp_id', $licenseId)
            ->where('is_active', true)
            ->first();
    }
}
