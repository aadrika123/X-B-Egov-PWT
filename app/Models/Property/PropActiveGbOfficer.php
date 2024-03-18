<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropActiveGbOfficer extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'prop_active_safgbofficers';

    /**
     * | Created By-Anshu Kumar
     * | Created for-13/03/2023 
     * | Model for The Officers Details for GB Saf
     */
    public function store($req)
    {
        PropActiveGbOfficer::create($req);
    }

    /**
     * | Get Officer by SAF Id
     */
    public function getOfficerBySafId($safId)
    {
        return PropActiveGbOfficer::select(
            'officer_name',
            'designation',
            'mobile_no'
        )
            ->where('saf_id', $safId)
            ->first();
    }
}
