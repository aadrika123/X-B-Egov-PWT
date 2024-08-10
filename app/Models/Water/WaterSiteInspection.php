<?php

namespace App\Models\Water;

use App\Models\Workflows\WfRole;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterSiteInspection extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * |-------------------- save site inspecton -----------------------\
     * | @param req
     */
    public function storeInspectionDetails($req, $waterDetails, $refRoleDetails)
    {
        $role = WfRole::where('id', $refRoleDetails)
            ->where('is_suspended', false)
            ->first();

        $saveSiteVerify = new WaterSiteInspection();
        $saveSiteVerify->apply_connection_id    =   $req->applicationId;
        $saveSiteVerify->property_type_id       =   $req->propertyTypeId;
        $saveSiteVerify->pipeline_type_id       =   $req->pipelineTypeId;
        $saveSiteVerify->connection_through     =   $waterDetails['connection_through'];
        $saveSiteVerify->category               =   $req->category;
        $saveSiteVerify->connection_type_id     =   $req->connectionTypeId;
        $saveSiteVerify->flat_count             =   $req->flatCount ?? null;
        $saveSiteVerify->ward_id                =   $waterDetails['ward_id'];
        $saveSiteVerify->area_sqft              =   $req->areaSqft;
        $saveSiteVerify->rate_id                =   $req->rateId ?? null;                    // what is rate Id
        $saveSiteVerify->emp_details_id         =   authUser($req)->id;
        $saveSiteVerify->pipeline_size          =   $req->pipelineSize;
        $saveSiteVerify->pipeline_size_type     =   $req->pipelineSizeType;
        $saveSiteVerify->pipe_size              =   $req->diameter;
        $saveSiteVerify->ferrule_type           =   $req->feruleSize;                       // what is ferrule
        $saveSiteVerify->road_type              =   $req->roadType;
        $saveSiteVerify->inspection_date        =   Carbon::now();
        $saveSiteVerify->verified_by            =   $role['role_name'];                     // here role 
        $saveSiteVerify->inspection_time        =   Carbon::now();
        $saveSiteVerify->ts_map                 =   $req->tsMap;
        $saveSiteVerify->order_officer          =   $refRoleDetails;
        $saveSiteVerify->pipe_type              =   $req->pipeQuality;
        $saveSiteVerify->pressure_supply        =   $req->pressurewaterSupply;
        $saveSiteVerify->meter_no               =   $req->meterNo;
        $saveSiteVerify->initial_reading        =   $req->intialreading;
        $saveSiteVerify->connection_place       =   $req->connectionPlace;
        $saveSiteVerify->public_tap             =   $req->noofPublicTap;
        $saveSiteVerify->time_water_supply      =   $req->timeWaterSupply;
        $saveSiteVerify->road_width             =   $req->roadWidth;
        $saveSiteVerify->amc_limit              =   $req->amcLimit;
        $saveSiteVerify->dimension              =   $req->dimension;
        // $saveSiteVerify->payment_status         =   $paymentstatus;
        $saveSiteVerify->latitude               =   $req->latitude ?? null;
        $saveSiteVerify->longitude              =   $req->longitude ?? null;
        $saveSiteVerify->save();
    }


    /**
     * | Get Site inspection Details by ApplicationId
     * | According to verification status false
     * | @param applicationId
        | Not Used 
     */
    public function getInspectionById($applicationId)
    {
        return WaterSiteInspection::select(
            'water_site_inspections.*',
            'id as site_inspection_id',
            'property_type_id as site_inspection_property_type_id',
            'area_sqft as site_inspection_area_sqft'
        )
            ->where('apply_connection_id', $applicationId)
            ->where('status', true)
            ->where('payment_status', 0)
            ->orderByDesc('water_site_inspections.id');
    }


    /**
     * | Save the Sheduled Date and Time of the Site Inspection
     * | Create a record for further Edit in site inspection
     * | @param request
     | Not used 
     */
    public function saveSiteDateTime($request)
    {
        $inspectionDate = date('Y-m-d', strtotime($request->inspectionDate));
        $mWaterSiteInspection = new WaterSiteInspection();
        $mWaterSiteInspection->apply_connection_id    =   $request->applicationId;
        $mWaterSiteInspection->inspection_date        =   $inspectionDate;
        $mWaterSiteInspection->inspection_time        =   $request->inspectionTime;
        $mWaterSiteInspection->save();
    }

    /**
     * | Get Site inspection Details 
     * | Site inspection details with payment true 
     * | @param applicationId
     */
    public function getSiteDetails($applicationId)
    {
        return WaterSiteInspection::select(
            'water_site_inspections.*',
            'id as site_inspection_id',
            'property_type_id as site_inspection_property_type_id',
            'area_sqft as site_inspection_area_sqft'
        )
            ->where('apply_connection_id', $applicationId)
            ->where('status', 1)
            ->orderByDesc('water_site_inspections.id');
    }


    /**
     * | Update the Online Site Inspection details by AE
     * | @param request
     */
    public function saveOnlineSiteDetails($req)
    {
        $roleName = WfRole::where('id', $req->roleId)
            ->where('is_suspended', false)
            ->first();

        $mWaterSiteInspection = new WaterSiteInspection();
        $mWaterSiteInspection->water_lock_arng      =   $req->waterLockArng;
        $mWaterSiteInspection->gate_valve           =   $req->gateValve;
        $mWaterSiteInspection->pipeline_size        =   $req->pipelineSize;
        $mWaterSiteInspection->pipe_size            =   $req->pipeSize;
        $mWaterSiteInspection->ferrule_type         =   $req->ferruleType;

        $mWaterSiteInspection->ward_id              =   $req->wardId;
        $mWaterSiteInspection->emp_details_id       =   $req->userId;
        $mWaterSiteInspection->apply_connection_id  =   $req->applicationId;
        $mWaterSiteInspection->verified_by          =   $roleName->role_name;
        $mWaterSiteInspection->order_officer        =   $req->roleId;
        $mWaterSiteInspection->inspection_date      =   $req->inspectionDate;
        $mWaterSiteInspection->inspection_time      =   $req->inspectionTime;
        $mWaterSiteInspection->save();
    }


    /**
     * | Save Payment Status after payment 
     * | updating payment status true or false
     * | @param applicationId
     */
    public function saveSitePaymentStatus($applicationId)
    {
        $siteDetails = WaterSiteInspection::where('apply_connection_id', $applicationId)
            ->where('status', 1)
            ->where('payment_status', 0)
            ->orderByDesc('id')
            ->first();
        $siteDetails->payment_status = 1;
        $siteDetails->save();
    }


    /**
     * | Deactivate the site inspection details
     * | @param id
     */
    public function deactivateSiteDetails($id)
    {
        WaterSiteInspection::where('id', $id)
            ->where('status', 1)
            ->update([
                'status' => 0
            ]);
    }
}
