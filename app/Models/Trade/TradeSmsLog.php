<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeSmsLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = "pgsql_trade";
}
