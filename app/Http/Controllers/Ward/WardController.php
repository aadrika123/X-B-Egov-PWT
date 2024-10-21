<?php

namespace App\Http\Controllers\Ward;

use App\Http\Requests\Ward\UlbWardRequest;
use App\Http\Controllers\Controller;
use App\Models\Property\ZoneMaster;
use App\Models\Ulb\UlbNewWardmap;
use App\Models\UlbWardMaster;
use App\Models\Workflows\WfWardUser;
use App\Repository\Ward\EloquentWardRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On-19-08-2022 
 * | Created By-Anshu Kumar
 * | Ulb Wards Operations
 */

class WardController extends Controller
{
    // Initializing Construct Function 
    protected $eloquent_repository;
    public function __construct(EloquentWardRepository $eloquent_repository)
    {
        $this->Repository = $eloquent_repository;
    }
    // Save Ulb Ward
    public function storeUlbWard(UlbWardRequest $request)
    {
        return $this->Repository->storeUlbWard($request);
    }

    // Edit Ulb Ward
    public function editUlbWard(UlbWardRequest $request, $id)
    {
        return $this->Repository->editUlbWard($request, $id);
    }

    // Get Ulb Ward by Ulb ID
    public function getUlbWardByID($id)
    {
        return $this->Repository->getUlbWardByID($id);
    }

    // Get All Ulb Wards
    public function getAllUlbWards()
    {
        return $this->Repository->getAllUlbWards();
    }

    // Get All Ulb Wards
    public function getNewWardByOldWard(Request $req)
    {
        $req->validate([
            'oldWardMstrId' => 'required',
        ]);
        $mulbNewWardMap = new UlbNewWardmap();
        $newWard =  UlbNewWardmap::select(
            'ulb_new_wardmaps.id',
            'ulb_new_wardmaps.new_ward_mstr_id',
            'ward_name'
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'ulb_new_wardmaps.new_ward_mstr_id')
            ->where('old_ward_mstr_id', $req->oldWardMstrId)
            ->orderBy('new_ward_mstr_id')
            ->get();;

        return responseMsg(true, "Data Retrived", remove_null($newWard));
    }

    // Get Ward by Zone
    public function getWardByZone(Request $req)
    {
        $validate = Validator::make($req->all(), [
            'zoneId' => 'required|integer'
        ]);

        if ($validate->fails())
            return validationError($validate);

        try {
            $mUlbWardMstr = new UlbWardMaster();
            $wardsByZone = json_decode(Redis::get('ward-by-zone-' . $req->zoneId));
            if (collect($wardsByZone)->isEmpty()) {
                $wardsByZone = $mUlbWardMstr->getWardsByZone($req->zoneId);
            }
            $wardsByZone = collect($wardsByZone)->sortBy(function ($item) {
                // Extract the numeric part from the "ward_name"
                preg_match('/\d+/', $item->ward_name, $matches);
                return (int) ($matches[0] ?? "");
            })->values();
            return responseMsgs(true, "Ward List", remove_null($wardsByZone), "", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }

    public function getWardZoneByUser(Request $req)
    {
        $validate = Validator::make($req->all(), [
            'zoneId' => 'required|integer'
        ]);

        if ($validate->fails()) {
            return validationError($validate);
        }

        try {
            $mWfWardUser = new WfWardUser();
            $mUlbWardMstr = new UlbWardMaster();
            $mUserId = authUser($req)->id;
            $occupiedWards = $mWfWardUser->getWardsByUserId($mUserId)->pluck('ward_id');
            $wardsByZone = $mUlbWardMstr->getWardsByZone($req->zoneId)->pluck('id');
            $filteredWardIds = $occupiedWards->intersect($wardsByZone);
            $filteredWards = $mUlbWardMstr->whereIn('id', $filteredWardIds)->get();
            return responseMsgs(true, "Ward List", remove_null($filteredWards), "", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "1.0", responseTime(), "POST", $req->deviceId);
        }
    }
}
