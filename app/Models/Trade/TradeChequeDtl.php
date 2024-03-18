<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeChequeDtl extends TradeParamModel    #Model
{
    use HasFactory;
    protected $connection;
    public $timestamps=false;

    public function __construct($DB=null)
    {
        parent::__construct($DB);
    }

    public function chequeDtlById($request)
    {
        return TradeChequeDtl::select('*')
            ->where('id', $request->chequeId)
            ->where('status', 2)
            ->first();
    }
}
