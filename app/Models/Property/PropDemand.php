<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropDemand extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Create Demands
     */
    public function store($req)
    {
        PropDemand::insert($req);
    }

    /**
     * | Get the Last Demand Date by Property Id
     */
    public function readLastDemandDateByPropId($propId)
    {
        $propDemand = PropDemand::where('property_id', $propId)
            ->orderByDesc('id')
            ->first();
        return $propDemand;
    }

    /**
     * | Get Property Dues Demand by Property Id
     */
    public function getDueDemandByPropId($propId)
    {
        return PropDemand::select(
            'id',
            'property_id',
            "alv",
            "maintanance_amt",
            "aging_amt",
            "general_tax",
            "road_tax",
            "firefighting_tax",
            "education_tax",
            "water_tax",
            "cleanliness_tax",
            "sewarage_tax",
            "tree_tax",
            "professional_tax",
            "total_tax",
            "balance",
            "paid_status",
            "fyear",
            "adjust_type",
            "adjust_amt",
            "tax1",
            "tax2",
            "tax3",
            "sp_education_tax as state_education_tax",
            "water_benefit",
            "water_bill",
            "sp_water_cess",
            "drain_cess",
            "light_cess",
            "major_building",
        )
            ->where('property_id', $propId)
            ->where('paid_status', 0)
            ->where('status', 1)
            ->orderByDesc('fyear')
            ->get();
    }


    /**
     * | Get Property Dues Demand by Property Id
     */
    public function getDueDemandByPropIdV2($propId)
    {
        return PropDemand::select(
            'id',
            'property_id',
            "alv",
            "maintanance_amt",
            "aging_amt",
            "general_tax",
            "road_tax",
            "firefighting_tax",
            "education_tax",
            "water_tax",
            "cleanliness_tax",
            "sewarage_tax",
            "tree_tax",
            "professional_tax",
            "total_tax",
            "balance",
            "paid_status",
            "fyear",
            "adjust_type",
            "adjust_amt",
            "tax1",
            "tax2",
            "tax3",
            "sp_education_tax as state_education_tax",
            "water_benefit",
            "water_bill",
            "sp_water_cess",
            "drain_cess",
            "light_cess",
            "major_building",
            "is_full_paid",
            "has_partwise_paid",
            "open_ploat_tax",
            "due_open_ploat_tax",

            "due_general_tax",
            "due_road_tax",
            "due_firefighting_tax",
            "due_education_tax",
            "due_water_tax",
            "due_cleanliness_tax",
            "due_sewarage_tax",
            "due_tree_tax",
            "due_professional_tax",
            "due_total_tax",
            "due_balance",
            "due_tax1",
            "due_tax2",
            "due_tax3",
            "due_sp_education_tax as due_state_education_tax",
            "due_water_benefit",
            "due_water_bill",
            "due_sp_water_cess",
            "due_drain_cess",
            "due_light_cess",
            "due_major_building",
            "is_old",
            "created_at",
        )
            ->where('status', 1)
            ->where('property_id', $propId)

            ->whereIn('paid_status', [0, 1])

            ->where(function ($query) {                         // conditional case when paid status is 1 then get the result of part paid amount only
                $query->where(function ($query) {
                    $query->where('paid_status', 1)->where('is_full_paid', false);
                })->orWhere(function ($query) {
                    $query->where('paid_status', 0)->where('is_full_paid', true);
                });
            })

            ->orderByDesc('fyear')
            ->get();
    }

    /**
     * | Get Property Demand by Property ID
     */
    public function getDemandByPropId($propId)
    {
        return PropDemand::where('property_id', $propId)
            ->where('paid_status', 0)
            ->where('status', 1)
            ->orderByDesc('fyear')
            ->get();
    }


    /**
     * | Get Property Demand by Property ID
     */
    public function getDemandByPropIdV2($propId)
    {
        return PropDemand::where('property_id', $propId)
            ->where('status', 1)
            ->orderByDesc('fyear')
            ->get();
    }

    /**
     * | Get First Prop Demand by propID
     */
    public function getEffectFromDemandByPropId($propId)
    {
        return PropDemand::where('property_id', $propId)
            ->where('paid_status', 0)
            ->where('status', 1)
            ->orderByDesc('due_date')
            ->first();
    }

    /**
     * | Get Demands by Financial year
     */
    public function getDemandByFyear($fYear, $propId)
    {
        $propDemand = PropDemand::where('fyear', $fYear)
            ->where('property_id', $propId)
            ->where('status', 1)
            ->orderBy('due_date')
            ->get();
        return $propDemand;
    }

    /**
     * | Get Full Demands By Property ID
     * | Used in Generating Fam Receipt
     */
    public function getFullDemandsByPropId($propId)
    {
        $propDemand = PropDemand::where('property_id', $propId)
            ->where('status', 1)
            ->orderBy('due_date')
            ->get();
        return $propDemand;
    }

    /**
     * | Get Paid Demand By PropId
     */
    public function getPaidDemandByPropId($propId)
    {
        $propDemand = PropDemand::where('property_id', $propId)
            ->where('paid_status', 1)
            ->where('status', 1)
            ->orderBy('due_date')
            ->get();
        return $propDemand;
    }

    // Get Saf First Demand
    public function getFirstDemandByFyearPropId($propId, $fyear)
    {
        return PropDemand::where('property_id', $propId)
            ->where('fyear', $fyear)
            ->orderBy('id')
            ->first();
    }

    /**
     * Get Demand By Demand Id
     */
    public function getDemandById($id)
    {
        return PropDemand::find($id);
    }


    /**
     * | Get Demands By Cluster Id
     */
    public function getDemandsByClusterId($clusterId)
    {
        return PropDemand::where('cluster_id', $clusterId)
            ->where('paid_status', 0)
            ->get();
    }

    /**
     * | 
     */
    public function wardWiseHolding($req)
    {
        return PropDemand::select(
            'holding_no',
            'new_holding_no',
            'owner_name',
            'mobile_no',
            'pt_no',
            'prop_address',
            'prop_demands.balance',
            'prop_demands.ward_mstr_id',
            'ward_name as ward_no',
            DB::raw("CONCAT (MIN(qtr),'/',fyear) AS fyear"),
        )
            ->join('prop_properties', 'prop_properties.id', 'prop_demands.property_id')
            ->join('prop_owners', 'prop_owners.property_id', 'prop_demands.property_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_demands.ward_mstr_id')
            ->where('paid_status', 0)
            ->whereBetween('due_date', [$req->fromDate, $req->toDate])
            ->where('prop_demands.ulb_id', $req->ulbId)
            ->where('prop_demands.ward_mstr_id', $req->wardMstrId)
            ->groupby(
                'prop_demands.property_id',
                'holding_no',
                'new_holding_no',
                'pt_no',
                'prop_demands.balance',
                'prop_demands.ward_mstr_id',
                'fyear',
                'prop_address',
                'owner_name',
                'mobile_no',
                'ward_name',
                // 'qtr'
            )
            ->paginate($req->perPage);
        // ->get();
    }

    /**
     * | Save cluster Id in prop Demand
     */
    public function saveClusterInDemand($refPropId, $clusterId)
    {
        PropDemand::whereIn('property_id', $refPropId)
            ->update([
                'cluster_id' => $clusterId
            ]);
    }

    /**
     * | Demand Deactivation
     */
    public function deactivateDemand($propId)
    {
        PropDemand::where('property_id', $propId)
            ->where('paid_status', 0)
            ->where('status', 1)
            ->update([
                'status' => 2
            ]);
    }

    /**
     * | Get Demands list by Demand ids
     */
    public function getDemandsListByIds(array $demandIds)
    {
        return PropDemand::whereIn('id', $demandIds)
            ->where('status', 1)
            ->get();
    }
}
