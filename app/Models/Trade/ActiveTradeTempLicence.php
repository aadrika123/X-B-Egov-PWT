<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActiveTradeTempLicence extends TradeParamModel
{
    use HasFactory;
    protected $connection;
    public function __construct($DB = null)
    {
        parent::__construct($DB);
    }
}
