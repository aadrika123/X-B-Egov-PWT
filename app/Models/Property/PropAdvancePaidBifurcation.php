<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropAdvancePaidBifurcation extends Model
{
    use HasFactory;
    protected $guarded = [];
    /**
     * | Store Function
     */
    public function store($req)
    {
        return PropAdvancePaidBifurcation::create($req)->id;
    }

    public function advanceAdjustment($fromDate, $toDate)
    {
        return self::selectRaw("
        SUM(general_tax) as general_tax_sum,
        SUM(exempted_general_tax) as exempted_general_tax_sum,
        SUM(road_tax) as road_tax_sum,
        SUM(firefighting_tax) as firefighting_tax_sum,
        SUM(education_tax) as education_tax_sum,
        SUM(water_tax) as water_tax_sum,
        SUM(cleanliness_tax) as cleanliness_tax_sum,
        SUM(sewarage_tax) as sewarage_tax_sum,
        SUM(tree_tax) as tree_tax_sum,
        SUM(professional_tax) as professional_tax_sum,
        SUM(tax1) as tax1_sum,
        SUM(tax2) as tax2_sum,
        SUM(tax3) as tax3_sum,
        SUM(state_education_tax) as state_education_tax_sum,
        SUM(water_benefit) as water_benefit_sum,
        SUM(water_bill) as water_bill_sum,
        SUM(sp_water_cess) as sp_water_cess_sum,
        SUM(drain_cess) as drain_cess_sum,
        SUM(light_cess) as light_cess_sum,
        SUM(major_building) as major_building_sum,
        SUM(open_ploat_tax) as open_ploat_tax_sum,
        SUM(total_tax) as total_tax_sum
    ")
            ->where('tran_date', '>=', $fromDate)
            ->where('tran_date', '<=', $toDate)
            ->first();
    }
}
