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

    /**
     * |----------------------------------- Owner Detail By ApplicationId / approve applications ----------------------------|
     * | @param request
     */
    public function ownerByApplication($request)
    {
        return WaterApprovalApplicant::select(
            'water_approval_applicants.applicant_name as owner_name',
            'water_approval_applicants.guardian_name',
            'water_approval_applicants.mobile_no',
            'water_approval_applicants.email',
            'water_approval_applicants.city',
            'water_approval_applicants.district'
        )
            ->join('water_applications', 'water_applications.id', '=', 'water_approval_applicants.application_id')
            ->where('water_applications.id', $request->applicationId)
            ->where('water_applications.status', 1)
            ->where('water_approval_applicants.status', 1);
    }
}
