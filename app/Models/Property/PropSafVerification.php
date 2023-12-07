<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropSafVerification extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function getVerificationsData($safId)
    {
        return DB::table('prop_saf_verifications as v')
            ->select(
                'v.id',
                'v.saf_id',
                'v.agency_verification',
                'v.prop_type_id as prop_type_mstr_id',
                'v.area_of_plot',
                'v.verified_by',
                'v.ward_id as ward_mstr_id',
                'v.has_mobile_tower as is_mobile_tower',
                'v.tower_area',
                'v.tower_installation_date',
                'v.has_hoarding as is_hoarding_board',
                'v.hoarding_area',
                'v.hoarding_installation_date',
                'v.is_petrol_pump',
                'v.underground_area as under_ground_area',
                'v.petrol_pump_completion_date',
                'v.has_water_harvesting as is_water_harvesting',
                'v.created_at',
                'v.updated_at',
                'v.status',
                'v.user_id',
                'v.ulb_id',
                'p.property_type',
                'u.ward_name as ward_no',
                'c.category',
                'c.id as category_id'
            )
            ->join('ulb_ward_masters as u', 'u.id', '=', 'v.ward_id')
            ->leftjoin('ref_prop_types as p', 'p.id', '=', 'v.prop_type_id')
            ->join('ref_prop_categories as c', 'c.id', '=', 'v.category_id')
            ->where('v.saf_id', $safId)
            ->where('v.agency_verification', true)
            ->orderByDesc('id')
            ->first();
    }

    // Store
    public function store($req)
    {
        $metaReqs = [
            'saf_id' => $req->safId,
            'agency_verification' => $req->agencyVerification ?? null,
            'ulb_verification' => $req->ulbVerification ?? null,
            'prop_type_id' => $req->propertyType,
            'area_of_plot' => $req->areaOfPlot,
            'ward_id' => $req->wardId,
            'has_mobile_tower' => $req->isMobileTower,
            'tower_area' => $req->mobileTower['area'],
            'tower_installation_date' => $req->mobileTower['dateFrom'],
            'has_hoarding' => $req->isHoardingBoard,
            'hoarding_area' => $req->hoardingBoard['area'],
            'hoarding_installation_date' => $req->hoardingBoard['dateFrom'],
            'is_petrol_pump' => $req->isPetrolPump,
            'underground_area' => $req->petrolPump['area'],
            'petrol_pump_completion_date' => $req->petrolPump['dateFrom'],
            'has_water_harvesting' => $req->isWaterHarvesting,
            'user_id' => $req->userId,
            'ulb_id' => $req->ulbId,
            'category_id' => $req->category
        ];

        return PropSafVerification::create($metaReqs)->id;
    }

    /**
     * | Deactivate Verifications
     */
    public function deactivateVerifications($safId)
    {
        $safVerifications = PropSafVerification::where('saf_id', $safId)
            ->get();

        collect($safVerifications)->map(function ($safVerification) {
            $safVerification->status = 0;
            $safVerification->save();
        });
    }

    /**
     * | Get Ulb Field Verification by SafId
     */
    public function getVerificationsBySafId($safId)
    {
        $query = "SELECT *,v.id as id FROM prop_saf_verification_dtls AS v
                    JOIN (SELECT * FROM prop_saf_verifications WHERE saf_id=$safId AND ulb_verification=TRUE ORDER BY id DESC LIMIT 1) AS p ON p.id=v.verification_id
                    WHERE v.saf_id=$safId";
        return DB::select($query);
    }

    /**
     * | Get Verifications
     */
    public function getVerifications($safId)
    {
        $query = "SELECT * FROM prop_saf_verifications WHERE saf_id=$safId AND ulb_verification=TRUE ORDER BY id DESC LIMIT 1";
        return DB::select($query);
    }

    public function getVerifications2($safId)
    {
        $query = "SELECT * FROM prop_saf_verifications WHERE saf_id=$safId AND ulb_verification!=TRUE ORDER BY id DESC LIMIT 1";
        return DB::select($query);
    }
}
