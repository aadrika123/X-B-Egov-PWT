<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PropActiveSafsFloor extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Get Safs Floors By Saf Id
     */
    public function getSafFloorsBySafId($safId)
    {
        return PropActiveSafsFloor::where('saf_id', $safId)
            ->where('status', 1)
            ->get();
    }

    public function getSafFloorsAsFieldVrfDtl($safId)
    {
        return self::select(DB::raw("
                        prop_active_safs_floors.id,
                        0 as verification_id,
                        prop_active_safs_floors.saf_id as saf_id,
                        prop_active_safs_floors.id as saf_floor_id,
                        prop_active_safs_floors.floor_mstr_id as floor_mstr_id,	 	
                        prop_active_safs_floors.usage_type_mstr_id	as usage_type_id, 	
                        prop_active_safs_floors.const_type_mstr_id as 	 construction_type_id, 	 	
                        prop_active_safs_floors.occupancy_type_mstr_id	as occupancy_type_id,	
                        prop_active_safs_floors.builtup_area as builtup_area, 	 	
                        prop_active_safs_floors.date_from as date_from,	 	
                        prop_active_safs_floors.date_upto as date_to,	 	
                        prop_active_safs_floors.status,		
                        prop_active_safs_floors.carpet_area	as carpet_area, 
                        0 as verified_by,	 	
                        prop_active_safs_floors.created_at,	 	
                        prop_active_safs_floors.updated_at,	 	
                        prop_active_safs_floors.user_id	, 
                        prop_active_safs.ulb_id ,	
                        prop_active_safs_floors.no_of_rooms	, 	
                        prop_active_safs_floors.no_of_toilets,
                        prop_active_safs_floors.bifurcated_buildup_area
                "))
            ->join("prop_active_safs", "prop_active_safs.id", "prop_active_safs_floors.saf_id")
            ->where("prop_active_safs_floors.saf_id", $safId)->get();
    }

    /**
     * | Get Saf Floor Details by SAF id
     */
    public function getFloorsBySafId($safId)
    {
        return DB::table('prop_active_safs_floors')
            ->select(
                'prop_active_safs_floors.*',
                'f.floor_name',
                'u.usage_type',
                'o.occupancy_type',
                'c.construction_type'
            )
            ->join('ref_prop_floors as f', 'f.id', '=', 'prop_active_safs_floors.floor_mstr_id')
            ->join('ref_prop_usage_types as u', 'u.id', '=', 'prop_active_safs_floors.usage_type_mstr_id')
            ->join('ref_prop_occupancy_types as o', 'o.id', '=', 'prop_active_safs_floors.occupancy_type_mstr_id')
            ->join('ref_prop_construction_types as c', 'c.id', '=', 'prop_active_safs_floors.const_type_mstr_id')
            ->where('saf_id', $safId)
            ->where('prop_active_safs_floors.status', 1)
            ->get();
    }

    /**
     * | Get occupancy type according to Saf id
     */
    public function getOccupancyType($safId, $refTenanted)
    {
        $occupency = PropActiveSafsFloor::where('saf_id', $safId)
            ->where('occupancy_type_mstr_id', $refTenanted)
            ->get();
        $check = collect($occupency)->first();
        if ($check) {
            $metaData = [
                'tenanted' => true
            ];
            return $metaData;
        }
        return  $metaData = [
            'tenanted' => false
        ];
        return $metaData;
    }

    /**
     * | Get usage type according to Saf NO
     */
    public function getSafUsageCatagory($safId)
    {
        return PropActiveSafsFloor::select(
            'ref_prop_usage_types.usage_code'
        )
            ->join('ref_prop_usage_types', 'ref_prop_usage_types.id', '=', 'prop_active_safs_floors.usage_type_mstr_id')
            ->where('saf_id', $safId)
            // ->where('ref_prop_usage_types.status', 1)
            ->orderByDesc('ref_prop_usage_types.id')
            ->get();
    }

    /**
     * | Floor Edit
     */
    public function editFloor($req, $citizenId)
    {
        $req = new Request($req);
        $floor = PropActiveSafsFloor::find($req->safFloorId);
        if ($req->useType == 1)
            $carpetArea =  $req->buildupArea * 0.70;
        else
            $carpetArea =  $req->buildupArea * 0.80;

        $reqs = [
            'floor_mstr_id' => $req->floorNo,
            'usage_type_mstr_id' => $req->useType,
            'const_type_mstr_id' => $req->constructionType,
            'occupancy_type_mstr_id' => $req->occupancyType,
            'builtup_area' => $req->buildupArea,
            'carpet_area' => $carpetArea,
            'date_from' => $req->dateFrom,
            'date_upto' => $req->dateUpto,
            'prop_floor_details_id' => $req->propFloorDetailId,
            'user_id' => $citizenId,

        ];
        $floor->update($reqs);
    }

    public function addfloor($req, $safId, $userId, $assessmentType, $biDateOfPurchase)
    {
        if ($req['usageType'] == 1)
            $carpetArea =  $req['buildupArea'] * 0.70;
        else
            $carpetArea =  $req['buildupArea'] * 0.80;

        $floor = new  PropActiveSafsFloor();
        $floor->saf_id = $safId;
        $floor->floor_mstr_id = $req['floorNo'] ?? null;
        $floor->usage_type_mstr_id = $req['usageType'] ?? null;
        $floor->const_type_mstr_id = $req['constructionType'] ?? null;
        $floor->occupancy_type_mstr_id = $req['occupancyType'] ??  null;
        $floor->builtup_area = $req['buildupArea'] ?? null;
        $floor->carpet_area = $carpetArea;
        $floor->date_from = $req['dateFrom'] ?? null;
        if ($assessmentType == "Bifurcation")
            $floor->date_from = $biDateOfPurchase;

        $floor->date_upto = $req['dateUpto'] ?? null;
        $floor->prop_floor_details_id = $req['propFloorDetailId'] ?? null;
        $floor->user_id = $userId;
        $floor->no_of_rooms = $req['noOfRooms'] ?? null;
        $floor->no_of_toilets = $req['noOfToilet'] ?? null;
        $floor->bifurcated_buildup_area = $req['biBuildupArea'] ?? null;
        $floor->save();
    }

    /**
     * | 
     */
    public function getSafAppartmentFloor($safIds)
    {
        return PropActiveSafsFloor::select('prop_active_safs_floors.*')
            ->whereIn('prop_active_safs_floors.saf_id', $safIds)
            ->where('prop_active_safs_floors.status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get Saf floors by Saf Id
     */
    public function getQSafFloorsBySafId($applicationId)
    {
        return PropActiveSafsFloor::query()
            ->where('saf_id', $applicationId)
            ->where('status', 1)
            ->get();
    }
}
