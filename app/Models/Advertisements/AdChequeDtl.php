<?php

namespace App\Models\Advertisements;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdChequeDtl extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = 'pgsql_advertisements';
    public function chequeDtlById($request)
    {
        return AdChequeDtl::select('*')
            ->where('id', $request->chequeId)
            ->where('status', 2)
            ->first();
    }
}
