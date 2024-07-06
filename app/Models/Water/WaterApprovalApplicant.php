<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterApprovalApplicant extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    public function getOwnerDtlById($applicationId)
    {
        return WaterApprovalApplicant::where('application_id', $applicationId)
            ->first();
    }

    public function getOwnerList($applicationId)
    {
        return WaterApprovalApplicant::select(
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
