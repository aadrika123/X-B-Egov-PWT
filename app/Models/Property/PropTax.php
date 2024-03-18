<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropTax extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Add Taxes
     */
    public function postTaxes(array $req)
    {
        PropTax::create($req);
    }

    /**
     * | Get Prop Taxes by PropID
     */
    public function getPropTaxesByPropId($propId)
    {
        return PropTax::where('prop_id', $propId)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Deactivate Property Taxes
     */
    public function deactivatePropTax($propId)
    {
        PropTax::where('prop_id', $propId)
            ->where('status', 1)
            ->update(['status' => 0]);
    }
    /**
     * | Replicate Saf Taxes On prop Taxes
     */
    public function replicateSafTaxes($propId, array $taxes)
    {
        foreach ($taxes as $tax) {
            $reqs = [
                'prop_id' => $propId,
                'qtr' => $tax['qtr'],
                'arv' => $tax['arv'],
                'holding_tax' => $tax['holding_tax'],
                'water_tax' => $tax['water_tax'],
                'education_cess' => $tax['education_cess'],
                'health_cess' => $tax['health_cess'],
                'latrine_tax' => $tax['latrine_tax'],
                'additional_tax' => $tax['additional_tax'],
                'fyear' => $tax['fyear'],
                'quarterly_tax' => $tax['quarterly_tax'],
            ];
            PropTax::create($reqs);
        }
    }
}
