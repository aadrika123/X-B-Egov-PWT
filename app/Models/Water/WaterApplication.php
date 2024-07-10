<?php

namespace App\Models\Water;

use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropProperty;
use App\Models\WorkflowTrack;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Predis\Response\Status;
use Razorpay\Api\Request;

class WaterApplication extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';


    /**
     * |------------------------------------------ Save new water applications -----------------------------------------|
     * | @param req
     * | @param ulbWorkflowId
     * | @param initiatorRoleId
     * | @param finisherRoleId
     * | @param ulbId
     * | @param applicationNo
     * | @param waterFeeId
     * | @param newConnectionCharges
     * | @return applicationId
     */
    public function saveWaterApplication($req, $ulbWorkflowId, $initiatorRoleId, $finisherRoleId, $ulbId, $applicationNo)
    {

        $saveNewApplication = new WaterApplication();
        $saveNewApplication->connection_type_id     = $req->connectionTypeId;
        $saveNewApplication->property_type_id       = $req->propertyTypeId;
        $saveNewApplication->owner_type             = $req->ownerType;
        $saveNewApplication->category               = $req->category;
        $saveNewApplication->pipeline_type_id       = $req->pipelineTypeId ?? 1;
        $saveNewApplication->ward_id                = $req->wardId;
        $saveNewApplication->area_sqft              = $req->areaSqft;
        $saveNewApplication->address                = $req->address;
        $saveNewApplication->landmark               = $req->landmark ?? null;
        $saveNewApplication->pin                    = $req->pin;
        $saveNewApplication->connection_through     = $req->connection_through;
        $saveNewApplication->workflow_id            = $ulbWorkflowId->id;
        // $saveNewApplication->connection_fee_id      = $waterFeeId;
        $saveNewApplication->initiator              = collect($initiatorRoleId)->first()->role_id;
        $saveNewApplication->finisher               = collect($finisherRoleId)->first()->role_id;
        $saveNewApplication->application_no         = $applicationNo;
        $saveNewApplication->ulb_id                 = $ulbId;
        $saveNewApplication->apply_date             = date('Y-m-d H:i:s');
        $saveNewApplication->user_id                = authUser($req)->id;    // <--------- here
        $saveNewApplication->user_type              = authUser($req)->user_type;
        $saveNewApplication->area_sqmt              = sqFtToSqMt($req->areaSqft);


        # condition entry 
        // if (!is_null($req->holdingNo) && $req->connection_through == 1) {
        //     $propertyId = new PropProperty();
        //     $propertyId = $propertyId->getPropertyId($req->holdingNo);
        //     $saveNewApplication->prop_id = $propertyId->id;
        //     $saveNewApplication->holding_no = $req->holdingNo;
        // }
        // if (!is_null($req->safNo) && $req->connection_through == 2) {
        //     $safId = new PropActiveSaf();
        //     $safId = $safId->getSafId($req->safNo);
        //     $saveNewApplication->saf_id = $safId->id;
        //     $saveNewApplication->saf_no = $req->safNo;
        // }

        // switch ($saveNewApplication->user_type) {
        //     case ('Citizen'):
        //         $saveNewApplication->apply_from = "Online";                                             // Static
        //         if ($newConnectionCharges['conn_fee_charge']['amount'] == 0) {
        //             $saveNewApplication->payment_status = 1;
        //         }
        //         break;
        //     case ('JSK'):
        //         $saveNewApplication->apply_from = "JSK";                                                // Static
        //         if ($newConnectionCharges['conn_fee_charge']['amount'] == 0) {
        //             $saveNewApplication->payment_status = 1;
        //         }
        //         break;
        //     default: # Check
        //         $saveNewApplication->apply_from = authUser($req)->user_type;
        //         $saveNewApplication->current_role = Config::get('waterConstaint.ROLE-LABEL.BO');
        //         break;
        // }

        $saveNewApplication->save();

        return $saveNewApplication->id;
    }


    /**
     * |----------------------- Get Water Application detals With all Relation ------------------|
     * | @param request
     * | @return 
     */
    public function fullWaterDetails($request)
    {
        return  WaterApplication::select(
            'water_applications.*',
            'water_applications.connection_through as connection_through_id',
            // 'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            // 'water_property_type_mstrs.property_type',
            // 'water_connection_through_mstrs.connection_through',
            'wf_roles.role_name AS current_role_name',
            // 'water_owner_type_mstrs.owner_type AS owner_char_type',
            // 'water_param_pipeline_types.pipeline_type'
        )
            ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_applications.current_role')
            // ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'water_applications.ward_id')
            // ->join('water_connection_through_mstrs', 'water_connection_through_mstrs.id', '=', 'water_applications.connection_through')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_applications.ulb_id')
            ->join('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_applications.connection_type_id')
            // ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_applications.property_type_id')
            // ->join('water_owner_type_mstrs', 'water_owner_type_mstrs.id', '=', 'water_applications.owner_type')
            // ->leftjoin('water_param_pipeline_types', 'water_param_pipeline_types.id', '=', 'water_applications.pipeline_type_id')
            ->where('water_applications.id', $request->applicationId)
            ->where('water_applications.status', 1);
    }


    /**
     * |----------------- is site is verified -------------------------|
     * | @param id
     */
    public function markSiteVerification($id)
    {
        $activeSaf = WaterApplication::find($id);
        $activeSaf->is_field_verified = true;
        $activeSaf->save();
    }

    /**
     * |------------------ Get Application details By Id ---------------|
     * | @param applicationId
     */
    public function getWaterApplicationsDetails($applicationId)
    {
        return WaterApplication::select(
            'water_applications.*',
            'water_applicants.id as ownerId',
            'water_applicants.applicant_name',
            'water_applicants.guardian_name',
            'water_applicants.city',
            'water_applicants.mobile_no',
            'water_applicants.email',
            'water_applicants.status',
            'water_applicants.district',
            'ulb_ward_masters.ward_name'

        )
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_applications.ward_id')
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->where('water_applications.id', $applicationId)
            ->firstOrFail();
    }

    /**
     * |------------------- Delete the Application Prmanentaly ----------------------|
     * | @param req
     */
    public function deleteWaterApplication($req)
    {
        WaterApplication::where('id', $req)
            ->delete();
    }

    /**
     * |------------------- Get Water Application By Id -------------------|
     * | @param applicationId
     */
    public function getApplicationById($applicationId)
    {
        return  WaterApplication::where('id', $applicationId)
            ->where('status', 1);
    }


    /**
     * |------------------- Get the Application details by applicationNo -------------------|
     * | @param applicationNo
     * | @param connectionTypes 
     * | @return 
     */
    public function getDetailsByApplicationNo($req, $connectionTypes, $applicationNo)
    {
        return WaterApplication::select(
            'water_applications.id',
            'water_applications.application_no',
            'water_applications.ward_id',
            'water_applications.address',
            'water_applications.holding_no',
            'water_applications.saf_no',
            'ulb_ward_masters.ward_name',
            DB::raw("string_agg(water_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_applicants.guardian_name,',') as guardianName"),
        )
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_applications.ward_id')
            ->where('water_applications.status', true)
            ->where('water_applications.connection_type_id', $connectionTypes)
            ->where('water_applications.application_no', 'LIKE', '%' . $applicationNo . '%')
            ->where('water_applications.ulb_id', authUser($req)->ulb_id)
            ->groupBy(
                'water_applications.saf_no',
                'water_applications.holding_no',
                'water_applications.address',
                'water_applications.id',
                'water_applicants.application_id',
                'water_applications.application_no',
                'water_applications.ward_id',
                'ulb_ward_masters.ward_name'
            );
    }

    /**
     * | Get Application details according to desired Parameter
     * | 
     */
    public function getDetailsByParameters($req)
    {
        return WaterApplication::select(
            'water_applications.id',
            'water_applications.application_no',
            'water_applications.ward_id',
            'water_applications.address',
            'water_applications.holding_no',
            'water_applications.saf_no',
            'ulb_ward_masters.ward_name',
            DB::raw("string_agg(water_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_applicants.guardian_name,',') as guardianName"),
        )
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_applications.ward_id')
            ->where('water_applications.status', true)
            ->where('water_applications.ulb_id', authUser($req)->ulb_id)
            ->groupBy(
                'water_applications.saf_no',
                'water_applications.holding_no',
                'water_applications.address',
                'water_applications.id',
                'water_applicants.application_id',
                'water_applications.application_no',
                'water_applications.ward_id',
                'ulb_ward_masters.ward_name'
            );
    }

    /**
     * |------------------- Final Approval of the water application -------------------|
     * | @param request
     * | @param consumerNo
     */
    public function finalApproval($request, $refJe, $consumerNo)
    {
        # object creation
        $mWaterApprovalApplicationDetail = new WaterApprovalApplicationDetail();
        $mWaterSiteInspection = new WaterSiteInspection();
        $mWaterConsumer = new WaterSecondConsumer();
        $waterTrack = new WorkflowTrack();

        # checking if consumer already exist 
        $approvedWater = WaterApplication::query()
            ->where('id', $request->applicationId)
            ->first();
        $checkExist = $mWaterApprovalApplicationDetail->getApproveApplication($approvedWater->id);
        if ($checkExist) {
            throw new Exception("Access Denied ! Consumer Already Exist!");
        }
        $checkconsumer = $mWaterConsumer->getConsumerByAppId($approvedWater->id);
        if ($checkconsumer) {
            throw new Exception("Access Denied ! Consumer Already Exist!");
        }

        # saving the data in the approved application table
        $approvedWaterRep = $approvedWater->replicate();
        $approvedWaterRep->setTable('water_approval_application_details');
        $approvedWaterRep->id = $approvedWater->id;
        $approvedWaterRep->save();

        # data formating for save the consumer details 
        $siteDetails = $mWaterSiteInspection->getSiteDetails($request->applicationId)
            // ->where('payment_status', 1)
            ->where('order_officer', $refJe)
            ->first();
        if (isset($siteDetails)) {
            $refData = [
                'connection_type_id'    => $siteDetails['connection_type_id'],
                'connection_through'    => $siteDetails['connection_through'],
                'pipeline_type_id'      => $siteDetails['pipeline_type_id'],
                'property_type_id'      => $siteDetails['property_type_id'],
                'category'              => $siteDetails['category'],
                'area_sqft'             => $siteDetails['area_sqft'],
                'area_asmt'             => sqFtToSqMt($siteDetails['area_sqft'])
            ];
            $approvedWaterRep = collect($approvedWater)->merge($refData);
        }
        $consumerId = $mWaterConsumer->saveWaterConsumer($approvedWaterRep, $consumerNo);

        # dend record in the track table 
        $metaReqs = [
            'moduleId'          => Config::get("module-constants.WATER_MODULE_ID"),
            'workflowId'        => $approvedWater->workflow_id,
            'refTableDotId'     => 'water_applications.id',
            'refTableIdValue'   => $approvedWater->id,
            'user_id'           => authUser($request)->id,
        ];
        $request->request->add($metaReqs);
        $waterTrack->saveTrack($request);

        # final delete
        $approvedWater->delete();
        return $consumerId;
    }

    /**
     * |------------------- Final rejection of the Application -------------------|
     * | Transfer the data to new table
     */
    public function finalRejectionOfAppication($request)
    {
        $rejectedWater = WaterApplication::query()
            ->where('id', $request->applicationId)
            ->first();

        # replication in the rejected application table 
        $rejectedWaterRep = $rejectedWater->replicate();
        $rejectedWaterRep->setTable('water_rejection_application_details');
        $rejectedWaterRep->id = $rejectedWater->id;
        $rejectedWaterRep->save();

        # save record in track table 
        $waterTrack = new WorkflowTrack();
        $metaReqs['moduleId'] =  Config::get("module-constants.WATER_MODULE_ID");
        $metaReqs['workflowId'] = $rejectedWater->workflow_id;
        $metaReqs['refTableDotId'] = 'water_applications.id';
        $metaReqs['refTableIdValue'] = $rejectedWater->id;
        $metaReqs['user_id'] = authUser($request)->id;
        $request->request->add($metaReqs);
        $waterTrack->saveTrack($request);

        # final delete 
        $rejectedWater->delete();
    }

    /**
     * |------------------- Edit the details of the application -------------------|
     * | Send the details of the apllication in the audit table
        | Not Finished
     */
    public function editWaterApplication($applicationId)
    {
    }

    /**
     * |------------------- Deactivate the Water Application In the Process of Aplication Editing -------------------|
     * | @param ApplicationId
     */
    public function deactivateApplication($applicationId)
    {
        WaterApplication::where('id', $applicationId)
            ->update([
                'status' => false
            ]);
    }

    /**
     * |------------------- Get Water Application Details According to the UserType and Date -------------------|
     * | @param request
     */
    public function getapplicationByDate($req)
    {
        return WaterApplication::select(
            'water_applications.id',
            'water_applications.*',
            'water_applications.connection_through as connection_through_id',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            'water_property_type_mstrs.property_type',
        )

            ->join('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_applications.connection_type_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_applications.property_type_id')
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_applications.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_applications.ward_id')
            ->where('water_applications.status', true)
            ->whereBetween('water_applications.apply_date', [$req['refStartTime'], $req['refEndTime']]);
    }


    /**
     * | Search Application Using the application NO and role 
     * | @param applicationNo
     */
    public function getApplicationByNo($applicationNo, $roleId)
    {
        return  WaterApplication::select(
            'water_applications.*',
            'water_applications.connection_through as connection_through_id',
            // 'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            // 'water_connection_type_mstrs.connection_type',
            // 'water_property_type_mstrs.property_type',
            // 'water_connection_through_mstrs.connection_through',
            // 'water_owner_type_mstrs.owner_type AS owner_char_type',
            // 'water_param_pipeline_types.pipeline_type'
        )
            // ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_applications.ward_id')
            // ->join('water_connection_through_mstrs', 'water_connection_through_mstrs.id', '=', 'water_applications.connection_through')
            ->leftjoin('ulb_masters', 'ulb_masters.id', '=', 'water_applications.ulb_id')
            // ->join('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_applications.connection_type_id')
            // ->leftjoin('water_property_type_mstrs', 'water_property_type_mstrs.id', '=', 'water_applications.property_type_id')
            // ->join('water_owner_type_mstrs', 'water_owner_type_mstrs.id', '=', 'water_applications.owner_type')
            // ->leftjoin('water_param_pipeline_types', 'water_param_pipeline_types.id', '=', 'water_applications.pipeline_type_id')
            ->where('water_applications.application_no', 'LIKE', '%' . $applicationNo . '%')
            ->where('water_applications.current_role', $roleId)
            ->where('water_applications.status', 1);
    }


    /**
     * | Update payment Status for the application
     * | Only used in the process of site inspection
     * | @param applicationNo
     * | @param action
     */
    public function updatePaymentStatus($applicationId, $action)
    {
        switch ($action) {
            case (false):
                WaterApplication::where('id', $applicationId)
                    ->update([
                        'payment_status' => 0,
                        // 'is_field_verified' => true
                    ]);
                break;

            case (true):
                WaterApplication::where('id', $applicationId)
                    ->update([
                        'payment_status' => 1,
                        // 'is_field_verified' => true
                    ]);
                break;
        }
    }


    /**
     * |------------------- Get the Application details by applicationNo -------------------|
     * | @param applicationNo
     * | @param connectionTypes 
     * | @return 
     */
    public function getDetailsByApplicationId($applicationId)
    {
        return WaterApplication::select(
            'water_applications.id',
            'water_applications.application_no',
            'water_applications.ward_id',
            'water_applications.address',
            'water_applications.holding_no',
            'water_applications.saf_no',
            "water_applications.category",
            'water_applications.mobile_no',
            'ulb_ward_masters.ward_name',
            'ulb_masters.ulb_name',
            'ulb_masters.logo',
            'ulb_masters.association_with',
            DB::raw("string_agg(water_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_applicants.guardian_name,',') as guardianName"),
        )
            ->join('ulb_masters', 'ulb_masters.id', '=', 'water_applications.ulb_id')
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_applications.ward_id')
            ->where('water_applications.status', true)
            ->where('water_applications.id', $applicationId)
            ->groupBy(
                'water_applications.saf_no',
                'water_applications.holding_no',
                'water_applications.address',
                'water_applications.id',
                'water_applicants.application_id',
                'water_applications.application_no',
                'water_applications.ward_id',
                'water_applications.ulb_id',
                'ulb_ward_masters.ward_name',
                'ulb_masters.id',
                'ulb_masters.ulb_name',
                'ulb_masters.logo',
                'ulb_masters.association_with',
                "water_applications.category"
            );
    }


    /**
     * | Deactivate the Doc Upload Status
     * | @param applicationId
     */
    public function deactivateUploadStatus($applicationId)
    {
        WaterApplication::where('id', $applicationId)
            ->update([
                'doc_upload_status' => false
            ]);
    }

    /**
     * | Activate the Doc Upload Status
     */
    public function activateUploadStatus($applicationId)
    {
        WaterApplication::where('id', $applicationId)
            ->update([
                'doc_upload_status' => true
            ]);
    }

    /**
     * | update the current role in case of online citizen apply
     */
    public function updateCurrentRoleForDa($applicationId, $waterRole)
    {
        WaterApplication::where('id', $applicationId)
            ->update([
                'current_role' => $waterRole
            ]);
    }

    /**
     * | Save The payment Status 
     * | @param ApplicationId
     */
    public function updateOnlyPaymentstatus($applicationId)
    {
        $activeSaf = WaterApplication::find($applicationId);
        $activeSaf->payment_status = 1;
        $activeSaf->save();
    }


    /**
     * | Update the payment Status ini case of pending
     * | in case of application is under verification 
     * | @param applicationId
     */
    public function updatePendingStatus($applicationId)
    {
        $activeSaf = WaterApplication::find($applicationId);
        $activeSaf->payment_status = 2;
        $activeSaf->save();
    }


    #--------------------------------------------------------------------------------------------------------------------#

    /**
     * | Dash bording 
     */
    public function getJskAppliedApplications($req)
    {
        $refUserType = authUser($req)->user_type;
        $currentDate = Carbon::now()->format('Y-m-d');

        return WaterApplication::select(
            'water_applications.*',
            DB::raw("string_agg(water_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_applicants.guardian_name,',') as guardianName"),
        )
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->where('apply_date', $currentDate)
            ->where('user_type', $refUserType)
            ->where('water_applications.status', true)
            ->where('water_applicants.status', true)
            ->groupBy(
                'water_applications.id',
                'water_applicants.application_id',
            );
    }

    /**
     * | Get application According to current role
     */
    public function getApplicationByRole($roleId)
    {
        return WaterApplication::where('current_role', $roleId)
            ->where('is_escalate', false)
            ->where('parked', false)
            ->where('status', 1);
    }


    /**
     * | Save the application current role as the bo when payament is done offline
     * | @param 
     */
    public function sendApplicationToRole($applicationId, $refRoleId)
    {
        WaterApplication::where('id', $applicationId)
            ->where('status', 1)
            ->update([
                "current_role" => $refRoleId
            ]);
    }


    /**
     * | Update the application Doc Verify status
     * | @param applicationId
     */
    public function updateAppliVerifyStatus($applicationId)
    {
        WaterApplication::where('id', $applicationId)
            ->update([
                'doc_status' => true
            ]);
    }

    /**
     * | Update the parked status false 
     */
    public function updateParkedstatus($status, $applicationId)
    {
        $mWaterApplication = WaterApplication::find($applicationId);
        switch ($status) {
            case (true):
                $mWaterApplication->parked = $status;
                break;

            case (false):
                $mWaterApplication->parked = $status;
                break;
        }
        $mWaterApplication->save();
    }
    public function saveWaterApplications($connectypeId, $req, $ulbWorkflowId, $initiatorRoleId, $finisherRoleId, $ulbId, $applicationNo)
    {

        $saveNewApplication = new WaterApplication();
        $saveNewApplication->connection_type_id     = $connectypeId;
        $saveNewApplication->property_type_id       = $req->buildingType;
        $saveNewApplication->owner_type             = $req->ownerType;
        $saveNewApplication->category               = $req->category;
        $saveNewApplication->pipeline_type_id       = $req->pipelineTypeId ?? 1;
        $saveNewApplication->ward_id                = $req->wardId;
        $saveNewApplication->area_sqft              = $req->areaSqft;
        $saveNewApplication->address                = $req->address;
        $saveNewApplication->landmark               = $req->landmark ?? null;
        $saveNewApplication->pin                    = $req->pin;
        $saveNewApplication->connection_through     = $req->connection_through;
        $saveNewApplication->workflow_id            = $ulbWorkflowId->id;
        // $saveNewApplication->connection_fee_id      = $waterFeeId;
        $saveNewApplication->initiator              = collect($initiatorRoleId)->first()->role_id;
        $saveNewApplication->current_role           = collect($initiatorRoleId)->first()->role_id;
        $saveNewApplication->finisher               = collect($finisherRoleId)->first()->role_id;
        $saveNewApplication->application_no         = $applicationNo;
        $saveNewApplication->ulb_id                 = $ulbId;
        $saveNewApplication->apply_date             = date('Y-m-d H:i:s');
        $saveNewApplication->user_id                = authUser($req)->id;    // <--------- here
        $saveNewApplication->user_type              = authUser($req)->user_type;
        // $saveNewApplication->area_sqmt              = sqFtToSqMt($req->areaSqft);
        $saveNewApplication->property_no_type       = $req->propertyNoType;
        $saveNewApplication->property_no            = $req->propertyNo;
        $saveNewApplication->tab_size               = $req->tabSize;
        $saveNewApplication->mobile_no              = $req->mobileNo;
        $saveNewApplication->zone_mstr_id           = $req->zoneId;
        $saveNewApplication->cycle                  = $req->cycle;
        $saveNewApplication->building_type          = $req->buildingType;
        $saveNewApplication->trade_license          = $req->tradelicenseNo;


        $saveNewApplication->save();
        return $saveNewApplication;
    }

    public function fullWaterDetail($applicationId)
    {
        return WaterApplication::select(
            'water_applications.*',
            'water_connection_charges.amount',
            "water_connection_charges.charge_category"

        )
            ->join('water_connection_charges', 'water_connection_charges.application_id', 'water_applications.id')
            ->where('water_applications.id', $applicationId);
    }

    /**
     * | get water application details 
     */
    public function getAppplicationByUserId($citizenId, $userType)
    {
        return  WaterApplication::select(
            'water_applications.*',
            DB::raw("string_agg(water_applicants.applicant_name,',') as applicantName"),
            DB::raw("string_agg(water_applicants.mobile_no::VARCHAR,',') as mobileNo"),
            DB::raw("string_agg(water_applicants.guardian_name,',') as guardianName"),
            'water_applicants.email'
        )
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->where('water_applications.user_type', $userType)
            ->where('water_applications.user_id', $citizenId)
            ->where('water_applications.status', true)
            ->groupBy(
                'water_applications.id',
                'water_applicants.application_id',
                'water_applicants.email'
            );
    }
}
