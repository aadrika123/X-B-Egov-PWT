<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropSafVerificationDtl extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    // Get Floor Details by Verification Id
    public function getVerificationDetails($verificationId)
    {
        return PropSafVerificationDtl::where('verification_id', $verificationId)->get();
    }

    // Get Full Verification Details
    public function getFullVerificationDtls($verifyId)
    {
        return DB::table('prop_saf_verification_dtls as v')
            ->select(
                'v.id',
                'v.verification_id',
                'v.saf_id',
                'v.saf_floor_id',
                'v.floor_mstr_id',
                'v.usage_type_id as usage_type_mstr_id',
                'v.construction_type_id as const_type_mstr_id',
                'v.occupancy_type_id as occupancy_type_mstr_id',
                'v.builtup_area',
                'v.date_from',
                'v.date_to as date_upto',
                'v.carpet_area',
                'v.verified_by',
                'v.created_at',
                'v.updated_at',
                'v.status',
                'v.user_id',
                'v.ulb_id',
                'v.created_at',
                'f.floor_name',
                'u.usage_type',
                'o.occupancy_type',
                'c.construction_type'
            )
            ->join('ref_prop_floors as f', 'f.id', '=', 'v.floor_mstr_id')
            ->join('ref_prop_usage_types as u', 'u.id', '=', 'v.usage_type_id')
            ->join('ref_prop_occupancy_types as o', 'o.id', '=', 'v.occupancy_type_id')
            ->join('ref_prop_construction_types as c', 'c.id', '=', 'v.construction_type_id')
            ->where('verification_id', $verifyId)
            ->get();
    }

    /**
     * | Deactivate Verifications
     */
    public function deactivateVerifications($safId)
    {
        $verifications = PropSafVerificationDtl::where('saf_id', $safId)
            ->get();

        collect($verifications)->map(function ($verification) {
            $verification->status = 0;
            $verification->save();
        });
    }

    /**
     * | Store Verification Details
     */
    public function store($req)
    {
        return PropSafVerificationDtl::create($req);
    }

    public function getVerificationDtls($verificationId)
    {
        return self::select("prop_saf_verification_dtls.*","ref_prop_floors.floor_name","ref_prop_usage_types.usage_type",
                    "ref_prop_construction_types.construction_type","ref_prop_occupancy_types.occupancy_type"
                )
            ->join("ref_prop_floors","ref_prop_floors.id","prop_saf_verification_dtls.floor_mstr_id")
            ->join("ref_prop_usage_types","ref_prop_usage_types.id","prop_saf_verification_dtls.usage_type_id")
            ->join("ref_prop_construction_types","ref_prop_construction_types.id","prop_saf_verification_dtls.construction_type_id")
            ->join("ref_prop_occupancy_types","ref_prop_occupancy_types.id","prop_saf_verification_dtls.occupancy_type_id")
            ->where("prop_saf_verification_dtls.status",1)
            ->where("prop_saf_verification_dtls.verification_id",$verificationId)
            ->get();
    }
}
