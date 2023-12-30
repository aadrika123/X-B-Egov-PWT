<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropFloor extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Get Property Floors
     */
    public function getPropFloors($propertyId)
    {
        return DB::table('prop_floors')
            ->select(
                'prop_floors.*',
                'f.floor_name',
                'u.usage_type',
                'o.occupancy_type',
                'c.construction_type',
                DB::raw('c.code AS construction_code , f.code AS floor_code'),
            )
            ->leftjoin('ref_prop_floors as f', 'f.id', '=', 'prop_floors.floor_mstr_id')
            ->leftjoin('ref_prop_usage_types as u', 'u.id', '=', 'prop_floors.usage_type_mstr_id')
            ->leftjoin('ref_prop_occupancy_types as o', 'o.id', '=', 'prop_floors.occupancy_type_mstr_id')
            ->leftjoin('ref_prop_construction_types as c', 'c.id', '=', 'prop_floors.const_type_mstr_id')
            ->where('property_id', $propertyId)
            ->where('prop_floors.status', 1)
            ->get();
    }


    /**
     * | Used for Calculation Parameter
     * | Get Property Details
     */
    public function getFloorsByPropId($propertyId)
    {
        return DB::table('prop_floors')
            ->where('property_id', $propertyId)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Deactivate Floors By Prop ID
     */
    public function deactivateFloorsByPropId($propId)
    {
        DB::table('prop_floors')
            ->where('property_id', $propId)
            ->where('status', 1)
            ->update([
                'status' => 0
            ]);
    }

    /**
     * | Get occupancy type according to holding id
     */
    public function getOccupancyType($propertyId, $refTenanted)
    {
        $occupency = PropFloor::where('property_id', $propertyId)
            ->where('occupancy_type_mstr_id', $refTenanted)
            ->get();
        $check = collect($occupency)->first();
        if ($check) {
            $metaData = [
                'tenanted' => true,
            ];
            return $metaData;
        }
        return  $metaData = [
            'tenanted' => false
        ];

        return $metaData;
    }

    /**
     * | Get usage type according to holding
     */
    public function getPropUsageCatagory($propertyId)
    {
        return PropFloor::select(
            'ref_prop_usage_types.usage_code'
        )
            ->join('ref_prop_usage_types', 'ref_prop_usage_types.id', '=', 'prop_floors.usage_type_mstr_id')
            ->where('property_id', $propertyId)
            // ->where('ref_prop_usage_types.status', 1)
            ->orderByDesc('ref_prop_usage_types.id')
            ->get();
    }

    /**
     * | Get Prop Floor by saf floor id and saf_id
     */
    public function getFloorBySafFloorIdSafId($safId, $safFloorId)
    {
        return PropFloor::where('saf_id', $safId)
            ->where('id', $safFloorId)
            ->first();
    }

    /**
     * | Get Floor by Saf Floor Id
     */
    public function getFloorBySafFloorId($safId, $safFloorId)
    {
        return PropFloor::where('saf_id', $safId)
            ->where('saf_floor_id', $safFloorId)
            ->first();
    }

    /***
     * | Get Floor By Floor Id
     */
    public function getFloorByFloorId($floorId)
    {
        return PropFloor::find($floorId);
    }

    /**
     * | Meta Floor Requests
     */
    public function metaFloorReqs($req)
    {
        return [
            'floor_mstr_id' => $req->floor_mstr_id,
            'usage_type_mstr_id' => $req->usage_type_mstr_id,
            'const_type_mstr_id' => $req->const_type_mstr_id,
            'occupancy_type_mstr_id' => $req->occupancy_type_mstr_id,
            'builtup_area' => $req->builtup_area,
            'date_from' => $req->date_from,
            'date_upto' => $req->date_upto,
            'carpet_area' => $req->carpet_area,
            'property_id' => $req->property_id,
            'saf_id' => $req->saf_id,
            'saf_floor_id' => $req->saf_floor_id,
            'prop_floor_details_id' => $req->prop_floor_details_id
        ];
    }

    /**
     * | Edit Existing Floor
     */
    public function editFloor($floor, $req)
    {
        $metaReqs = $this->metaFloorReqs($req);
        $floor->update($metaReqs);
    }

    /**
     * | Independent Edit
     */
    public function edit($floorId, $req)
    {
        $property = PropFloor::findOrFail($floorId);
        return $property->update($req);
    }

    /**
     * | Add new Floor
     */
    public function postFloor($req)
    {
        $metaReqs = $this->metaFloorReqs($req);
        PropFloor::create($metaReqs);
    }

    /**
     * |get flloor by floor mstr id
     */
    public function getFloorByFloorMstrId($floorId)
    {
        return PropFloor::where('prop_floors.id', $floorId)
            ->join('ref_prop_usage_types', 'ref_prop_usage_types.id', 'prop_floors.usage_type_mstr_id')
            ->join('ref_prop_floors', 'ref_prop_floors.id', 'prop_floors.floor_mstr_id')
            ->join('ref_prop_occupancy_types', 'ref_prop_occupancy_types.id', 'prop_floors.occupancy_type_mstr_id')
            ->join('ref_prop_construction_types', 'ref_prop_construction_types.id', 'prop_floors.const_type_mstr_id')
            ->get();
    }

    /**
     * | Get details of 
     */
    public function getAppartmentFloor($propIds)
    {
        return PropFloor::select('prop_floors.*')
            ->whereIn('prop_floors.property_id', $propIds)
            ->where('prop_floors.status', 1)
            ->orderByDesc('id');
    }
}
