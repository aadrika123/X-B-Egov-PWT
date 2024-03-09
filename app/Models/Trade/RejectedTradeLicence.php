<?php

namespace App\Models\Trade;

use App\Models\Workflows\WfActiveDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectedTradeLicence extends Model
{
    use HasFactory;
    protected $connection;
    public function __construct($DB=null)
    {
       $this->connection = $DB ? $DB:"pgsql_trade";
    }


    # one to one
    public function cotegoryType()
    {
        return $this->hasOne(TradeParamCategoryType::class,'id',"category_type_id");
    }

    public function FirmType()
    {
        return $this->hasOne(TradeParamFirmType::class,'id',"firm_type_id");
    }

    public function applicationType()
    {
        return $this->hasOne(TradeParamApplicationType::class,'id',"application_type_id");
    }

    public function ownershipeType()
    {
        return $this->hasOne(TradeParamOwnershipType::class,'id',"ownership_type_id");
    }

    public function noticeDtl()
    {
        return $this->hasOne(TradeNoticeConsumerDtl::class,'id',"denial_id");
    }
    # end one to one
    # one to many
    public function owneres()
    {
        return $this->hasMany(RejectedTradeOwner::class,'temp_id',"id");
    }
    // public function itemType()
    // {
    //     return $this->hasMany(TradeParamItemType::class,'id',"nature_of_bussiness");
    // }

    public function transactionDtl()
    {
        return $this->hasMany(TradeTransaction::class,'temp_id',"id")->whereNotIn("status",[0,3]);
    }

    public function chequenDtl()
    {
        return $this->hasMany(TradeChequeDtl::class,'temp_id',"id");
    }

    public function docDtl()
    {
        return $this->hasMany(WfActiveDocument::class,'active_id',"id")
                ->where("wf_active_documents.workflow_id",$this->workflow_id)
                ->where("wf_active_documents.status",1);
    }    

    public function razorPayRequest()
    {
        return $this->hasMany(TradeRazorPayRequest::class,'temp_id',"id");
    }

    public function razorPayResonse()
    {
        return $this->hasMany(TradeRazorPayResponse::class,'temp_id',"id");
    }
    # end one to many
    # one to many through
    public function tranChequenDtl()
    {
        return $this->hasManyThrough(TradeChequeDtl::class,TradeTransaction::class,'temp_id',"tran_id","id");
    }

    public function fineRebateDtl()
    {
        return $this->hasManyThrough(TradeFineRebete::class,TradeTransaction::class,'temp_id',"tran_id","id")
        ->where("trade_fine_rebetes.status",1);
    }

    # end one to many through
}
