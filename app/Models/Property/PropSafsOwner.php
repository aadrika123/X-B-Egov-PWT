<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropSafsOwner extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Owner by Saf No
     */
    public function getOwnerDtlsBySafId1($safId)
    {
        return PropSafsOwner::where('saf_id', $safId)
            ->select(
                'owner_name',
                'mobile_no',
                'dob',
                'guardian_name',
                'email',
                'is_armed_force',
                'is_specially_abled'
            )
            ->orderBy('id')
            ->first();
    }


    public function getOwnersBySafId($safId)
    {
        return PropSafsOwner::select(
            'prop_safs_owners.*'
        )
            ->where('saf_id', $safId)
            ->where('status', 1)
            ->get();
    }


    /**
     * | Get the prop details according to mobile no 
     */
    public function getPropByMobile($mobileNo)
    {
        return PropSafsOwner::select(
            'prop_active_safs.id AS safId',
            'prop_active_safs.saf_no',
            'prop_active_safs.citizen_id',
            'prop_active_safs_owners.*'
        )
            ->join('prop_active_safs', 'prop_active_safs.id', 'prop_active_safs_owners.saf_id')
            ->where('prop_active_safs_owners.mobile_no', $mobileNo)
            ->where('prop_active_safs.status', 1)
            ->where('prop_active_safs_owners.status', 1)
            ->orderByDesc('prop_active_safs.id');
    }
}
