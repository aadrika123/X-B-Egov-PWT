<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropSafTax extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Read Saf Taxes by SafId
     */
    public function getSafTaxesBySafId($safId)
    {
        return PropSafTax::where('saf_id', $safId)
            ->orderBy('created_at')
            ->where('status', 1)
            ->get();
    }

    /**
     * | Post Taxes
     */
    public function postTaxes(array $tax)
    {
        PropSafTax::create($tax);
    }

    /**
     * | Deactivate Saf 
     */
    public function deactivateTaxes($safId)
    {
        PropSafTax::where('saf_id', $safId)
            ->where('status', 1)
            ->update(["status" => 0]);
    }
}
