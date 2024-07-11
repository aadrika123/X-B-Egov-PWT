<?php

namespace App\Models\Water;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterApplicant extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';


    /**
     * |--------------------------- save new water applicants details ------------------------------|
     * | @param
     * |
        | Apply City and District
     */
    public function saveWaterApplicant($meta, $owners)
    {
        $applicant = new WaterApplicant();
        $applicant->application_id  = $meta['applicationId'];
        $applicant->applicant_name  = $owners['ownerName'];
        $applicant->guardian_name   = $owners['guardianName'] ?? null;
        $applicant->mobile_no       = $owners['mobileNo'];
        $applicant->email           = $owners['email'] ?? null;
        $applicant->save();
        return $applicant;
    }

    /**
     * |----------------------------------- Owner Detail By ApplicationId / active applications ----------------------------|
     * | @param request
     */
    public function ownerByApplication($request)
    {
        return WaterApplicant::select(
            'water_applicants.applicant_name as owner_name',
            'water_applicants.guardian_name',
            'water_applicants.mobile_no',
            'water_applicants.email',
            'water_applicants.city',
            'water_applicants.district'
        )
            ->join('water_applications', 'water_applications.id', '=', 'water_applicants.application_id')
            ->where('water_applications.id', $request->applicationId)
            ->where('water_applications.status', 1)
            ->where('water_applicants.status', 1);
    }

    /**
     * |
     */
    public function getOwnerList($applicationId)
    {
        return WaterApplicant::select(
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
     * |-------------- Delete the applicant -------------|
     */
    public function deleteWaterApplicant($id)
    {
        WaterApplicant::where('application_id', $id)
            ->delete();
    }

    /**
     * |---------- Edit the water owner Details ----------|
     */
    public function editWaterOwners($req, $refWaterApplications)
    {
        $owner = WaterApplicant::find($req->ownerId);
        $reqs = [
            'applicant_name'  => $req->applicant_name ?? $refWaterApplications->applicant_name,
            'guardian_name'   => $req->guardian_name  ?? $refWaterApplications->guardian_name,
            'mobile_no'       => $req->mobile_no      ?? $refWaterApplications->mobile_no,
            'email'           => $req->email          ?? $refWaterApplications->email,
        ];
        $owner->update($reqs);
    }

    /**
     * | final approval and the replication of the application details 
     */
    public function finalApplicantApproval($request, $consumerId)
    {
        try {
            // Fetch all water applicants with the specified application ID
            $approvedWaterApplicants = WaterApplicant::query()
                ->where('application_id', $request->applicationId)
                ->get();

            // Process each applicant
            collect($approvedWaterApplicants)->map(function ($value) use ($consumerId) {
                // Duplicate the applicant and save to water_approval_applicants
                $approvedWaterOwners = $value->replicate();
                $approvedWaterOwners->setTable('water_approval_applicants');
                $approvedWaterOwners->save();

                // Duplicate the applicant and save to water_consumer_owners
                $approvedWaterOwners = $value->replicate();
                $approvedWaterOwners->setTable('water_consumer_owners');
                $approvedWaterOwners->consumer_id = $consumerId;
                $approvedWaterOwners->save();
            });

            // Delete original applicants
            WaterApplicant::where('application_id', $request->applicationId)
                ->delete();
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            throw new Exception("An error occurred while processing the applicants: " . $e->getMessage());
        }
    }



    /**
     * | Rejection Application 
     * | transfer the rejected application to the rejected table
     */
    public function finalOwnerRejection($request)
    {
        $approvedWaterApplicant = WaterApplicant::query()
            ->where('application_id', $request->applicationId)
            ->get();

        collect($approvedWaterApplicant)->map(function ($value) {
            $approvedWaterOwners = $value->replicate();
            $approvedWaterOwners->setTable('water_rejection_applicants');
            $approvedWaterOwners->id = $value->id;
            $approvedWaterOwners->save();
        });
        WaterApplicant::where('application_id', $request->applicationId)
            ->delete();
    }

    /**
     * | Deactive the Applicant In the Process of Application Edit
     * | @param applicationId
     */
    public function deactivateApplicant($applicationId)
    {
        WaterApplicant::where('application_id', $applicationId)
            ->update([
                'status' => false
            ]);
    }

    /**
     * 
     */
    public function saveWaterApplicants($meta, $req)
    {
        $applicant = new WaterApplicant();
        $applicant->application_id  = $meta['applicationId'];
        $applicant->applicant_name  = $req['OwnerName'];
        $applicant->guardian_name   = $req['GuardianName'] ?? null;
        $applicant->mobile_no       = $req['MobileNo'];
        $applicant->email           = $req['Email'] ?? null;
        $applicant->save();
        return $applicant;
    }
}
