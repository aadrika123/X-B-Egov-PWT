<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterRejectionApplicant extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];
    /**
     * |
     */
    public function getOwnerList($applicationId)
    {
        return self::select(
            'id',
            'applicant_name',
            'guardian_name',
            'mobile_no',
            'email'
        )
            ->where('application_id', $applicationId)
            ->where('status', true);
    }
}
