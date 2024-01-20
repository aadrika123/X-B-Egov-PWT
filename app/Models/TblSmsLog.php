<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TblSmsLog extends Model
{
    use HasFactory;
    protected $connection = "pgsql_master";
    protected $guarded = [];
}
