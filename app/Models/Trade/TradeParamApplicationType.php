<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeParamApplicationType extends TradeParamModel    #Model
{
    use HasFactory;
    protected $connection;
    public $timestamps=false;

    public function __construct($DB=null)
    {
        parent::__construct($DB);
    }


    public function activeApplication()
    {
        return $this->hasMany(ActiveTradeLicence::class,'application_type_id',"id")->where("is_active",true);
    }
    public function rejectedApplication()
    {
        return $this->hasMany(RejectedTradeLicence::class,'application_type_id',"id")->where("is_active",true);
    }
    public function approvedApplication()
    {
        return $this->hasMany(TradeLicence::class,'application_type_id',"id");
    }
    public function renewalApplication()
    {
        return $this->hasMany(TradeRenewal::class,'application_type_id',"id");
    }
}
