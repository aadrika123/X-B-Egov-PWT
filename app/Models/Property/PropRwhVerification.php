<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropRwhVerification extends PropParamModel #Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'harvesting_id',
        'harvesting_status',
        'agency_verification',
        'ulb_verification',
        'date',
        'user_id',
        'ulb_id',
    ];

    /**
     * 
     */
    public function getVerificationsData($applicationId)
    {
        return DB::table('prop_rwh_verifications')
            ->select(
                '*',
                'prop_rwh_verifications.harvesting_status',
                'agency_verification'
                // 'p.property_type',
                // 'r.road_type',
                // 'u.ward_name as ward_no'
            )
            // ->join('ulb_ward_masters as u', 'u.id', '=', 'prop_saf_verifications.ward_id')
            // ->join('prop_properties', 'prop_properties.id', 'prop_rwh_verifications.property_id')
            ->where('prop_rwh_verifications.harvesting_id', $applicationId)
            ->where('prop_rwh_verifications.agency_verification', true)
            ->first();
    }

    /**
     * |
     */
    public function store($req)
    {
        $metaReqs = [
            'property_id' => $req->propertyId,
            'harvesting_id' => $req->harvestingId,
            'harvesting_status' => $req->harvestingStatus,
            'agency_verification' => $req->agencyVerification ?? null,
            'ulb_verification' => $req->ulbVerification ?? null,
            'date' => Carbon::now(),
            'user_id' => $req->userId,
            'ulb_id' => $req->ulbId
        ];

        return PropRwhVerification::create($metaReqs)->id;
    }
}
