<?php

namespace App\Models\Advertisements;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdTransactionDeactivateDtl extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = 'pgsql_advertisements';
}
