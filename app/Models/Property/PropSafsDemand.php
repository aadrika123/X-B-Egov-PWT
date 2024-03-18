<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use KitLoong\MigrationsGenerator\Migration\Blueprint\Property;

class PropSafsDemand extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    // Get Demand By SAF id
    public function getDemandBySafId($safId)
    {
        return PropSafsDemand::where('saf_id', $safId)
            ->where('paid_status', 0)
            ->orderByDesc('due_date')
            ->get();
    }

    /**
     * | 1.Used on Saf Payment Receipt
     */
    public function getFirstDemandBySafId($safId)
    {
        return PropSafsDemand::where('saf_id', $safId)
            ->orderBy('id')
            ->first();
    }

    // Get Saf First Demand by saf id and financial year
    public function getFirstDemandByFyearSafId($safId, $fyear)
    {
        return PropSafsDemand::where('saf_id', $safId)
            ->where('fyear', $fyear)
            ->orderBy('id')
            ->first();
    }

    // Get Demand by Saf Id
    public function getDemandsBySafId($safId)
    {
        return PropSafsDemand::where('saf_id', $safId)
            ->orderByDesc('due_date')
            ->get();
    }

    // Get Demand By ID
    public function getDemandById($id)
    {
        return PropSafsDemand::find($id);
    }

    // Get Existing Prop SAF Demand by financial quarter and safid
    public function getPropSafDemands($quarterYear, $qtr, $safId)
    {
        return PropSafsDemand::where('fyear', $quarterYear)
            ->where('qtr', $qtr)
            ->where('saf_id', $safId)
            ->first();
    }

    /**
     * | Save SAF Demand
     */
    public function postDemands(array $req)
    {
        $stored = PropSafsDemand::create($req);
        return [
            'demandId' => $stored->id
        ];
    }

    /**
     * | Update Demands
     */
    public function editDemands($demandId, $req)
    {
        $demands = PropSafsDemand::find($demandId);
        $demands->update($req);
    }

    /**
     * | Get Last Demand Date by Saf Id
     */
    public function readLastDemandDateBySafId($safId)
    {
        $safDemand = PropSafsDemand::where('saf_id', $safId)
            ->orderByDesc('id')
            ->first();
        return $safDemand;
    }

    /**
     * | Get Demands by Financial year
     */
    public function getDemandByFyear($fYear, $safId)
    {
        $propDemand = PropSafsDemand::where('fyear', $fYear)
            ->where('saf_id', $safId)
            ->where('status', 1)
            ->orderBy('due_date')
            ->get();
        return $propDemand;
    }

    /**
     * | Get Full Demands By Property ID
     */
    public function getFullDemandsBySafId($safId)
    {
        $safDemand = PropSafsDemand::where('saf_id', $safId)
            ->where('status', 1)
            ->orderBy('due_date')
            ->get();
        return $safDemand;
    }

    /**
     * | Get Paid Demand by Saf Id
     */
    public function getPaidDemandBySafId($safId)
    {
        $safDemand = PropSafsDemand::where('saf_id', $safId)
            ->where('status', 1)
            ->where('paid_status', 1)
            ->orderBy('due_date')
            ->get();
        return $safDemand;
    }

    /**
     * | Get Demands by Cluster ID
     */
    public function getDemandsByClusterId($clusterId)
    {
        return PropSafsDemand::where('cluster_id', $clusterId)
            ->where('paid_status', 0)
            ->get();
    }

    /**
     * | Save cluster in Saf demand
     */
    public function saveClusterinSafDemand($safIds, $clusterId)
    {
        PropSafsDemand::whereIn('saf_id', $safIds)
            ->update([
                'cluster_id' => $clusterId
            ]);
    }
}
