<?php

namespace App\Models\Water;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WaterConsumerActiveRequest extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Save request details 
     */
    public function saveRequestDetails($req, $consumerDetails, $refRequest, $applicationNo)
    {
        $mWaterConsumerActiveRequest = new WaterConsumerActiveRequest();
        $mWaterConsumerActiveRequest->consumer_id               = $consumerDetails->id;
        $mWaterConsumerActiveRequest->apply_date                = Carbon::now();
        $mWaterConsumerActiveRequest->citizen_id                = $refRequest['citizenId'] ?? null;
        $mWaterConsumerActiveRequest->created_at                = Carbon::now();
        $mWaterConsumerActiveRequest->emp_details_id            = $refRequest['empId'] ?? null;
        $mWaterConsumerActiveRequest->ward_mstr_id              = $consumerDetails->ward_mstr_id;
        $mWaterConsumerActiveRequest->reason                    = $req['reason'] ?? null;
        // $mWaterConsumerActiveRequest->amount                    = $refRequest['amount'];
        $mWaterConsumerActiveRequest->remarks                   = $req['remarks'];
        $mWaterConsumerActiveRequest->apply_from                = $refRequest['applyFrom'];
        $mWaterConsumerActiveRequest->current_role              = $refRequest['initiatorRoleId'];
        $mWaterConsumerActiveRequest->initiator                 = $refRequest['initiatorRoleId'];
        $mWaterConsumerActiveRequest->workflow_id               = $refRequest['ulbWorkflowId'];
        $mWaterConsumerActiveRequest->ulb_id                    = $req['ulbId'] ?? 2;
        $mWaterConsumerActiveRequest->finisher                  = $refRequest['finisherRoleId'];
        $mWaterConsumerActiveRequest->user_type                 = $refRequest['userType'];
        $mWaterConsumerActiveRequest->application_no            = $applicationNo;
        $mWaterConsumerActiveRequest->charge_catagory_id        = $refRequest['chargeCategoryId'];
        $mWaterConsumerActiveRequest->corresponding_mobile_no   = $req->mobileNo ?? null;
        $mWaterConsumerActiveRequest->save();
        return [
            "id" => $mWaterConsumerActiveRequest->id
        ];
    }

    /**
     * | Get Active appication by consumer Id
     */
    public function getRequestByConId($consumerId)
    {
        return WaterConsumerActiveRequest::where('consumer_id', $consumerId)
            ->where('status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get application by ID
     */
    public function getRequestById($id)
    {
        return WaterConsumerActiveRequest::select(
            'water_consumer_active_requests.*',
            'water_consumer_charge_categories.amount',
            'water_consumer_charge_categories.charge_category AS charge_category_name'
        )
            ->join('water_consumer_charge_categories', 'water_consumer_charge_categories.id', 'water_consumer_active_requests.charge_catagory_id')
            ->where('water_consumer_active_requests.status', 1)
            ->where('water_consumer_active_requests.id', $id);
    }

    /**
     * | Get active request by request id 
     */
    public function getActiveReqById($id)
    {
        return WaterConsumerActiveRequest::where('id', $id)
            ->where('status', 1);
    }


    /**
     * | Update the payment status and the current role for payment 
     * | After the payment is done the data are update in active table
     */
    public function updateDataForPayment($applicationId, $req)
    {
        WaterConsumerActiveRequest::where('id', $applicationId)
            ->where('status', 1)
            ->update($req);
    }

    /**
     * | Get the Application according to user details 
     */
    public function getApplicationByUser($userId)
    {
        return WaterConsumerActiveRequest::select(
            'water_consumer_active_requests.id',
            'water_consumer_active_requests.reason',
            'water_consumer_active_requests.remarks',
            'water_consumer_active_requests.amount',
            'water_consumer_active_requests.application_no',
            // DB::raw('REPLACE(water_consumer_charges.charge_category, \'_\', \' \') as charge_category'),
            'water_consumer_active_requests.verify_status',
            "water_consumer_active_requests.corresponding_address",
            "water_consumer_active_requests.corresponding_mobile_no",
            "water_second_consumers.consumer_no",
            "water_consumer_active_requests.ward_mstr_id",
            "water_consumer_active_requests.apply_date",
            "water_consumer_active_requests.payment_status",
            "ulb_ward_masters.ward_name",
            "water_consumer_charge_categories.charge_category"
        )
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_consumer_active_requests.ward_mstr_id')
            ->leftjoin('water_consumer_charges', 'water_consumer_charges.related_id', 'water_consumer_active_requests.id')
            ->join('water_consumer_charge_categories', 'water_consumer_charge_categories.id', 'water_consumer_active_requests.charge_catagory_id')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_active_requests.consumer_id')
            ->where('water_consumer_active_requests.citizen_id', $userId)
            ->where('water_consumer_active_requests.status', 1)
            ->orderByDesc('water_consumer_active_requests.id');
    }








    ///////////////////////////////////////////////////////////////////////////////
    public function saveWaterConsumerActive($req, $consumerId, $meteReq, $refRequest, $applicationNo)
    {
        $mWaterConsumeActive = new WaterConsumerActiveRequest();
        $mWaterConsumeActive->id;
        $mWaterConsumeActive->ulb_id                   = $meteReq['ulbId'];
        $mWaterConsumeActive->application_no           = $applicationNo;
        $mWaterConsumeActive->consumer_id              = $consumerId;
        $mWaterConsumeActive->emp_details_id           = $refRequest['empId'] ?? null;
        $mWaterConsumeActive->citizen_id               = $refRequest["citizenId"] ?? null;
        $mWaterConsumeActive->apply_from               = $refRequest['applyFrom'];
        $mWaterConsumeActive->apply_date               = $meteReq['applydate'];
        $mWaterConsumeActive->amount                   = $meteReq['amount'];
        $mWaterConsumeActive->reason                   = $req->reason;
        $mWaterConsumeActive->remarks                  = $req->remarks;
        $mWaterConsumeActive->doc_verify_status        = $req->doc_verify_status;
        // $mWaterConsumeActive->payment_status           = $req->payment_status;
        $mWaterConsumeActive->charge_catagory_id       = $meteReq['chargeCategoryID'];
        $mWaterConsumeActive->corresponding_mobile_no  = $req->mobileNo;
        $mWaterConsumeActive->corresponding_address    = $req->address;
        $mWaterConsumeActive->ward_mstr_id             = $meteReq['wardmstrId'];
        $mWaterConsumeActive->initiator                = $refRequest['initiatorRoleId'];
        $mWaterConsumeActive->finisher                 = $refRequest['finisherRoleId'];
        $mWaterConsumeActive->user_type                = $refRequest['userType'];
        $mWaterConsumeActive->workflow_id              = $meteReq['ulbWorkflowId'];
        $mWaterConsumeActive->current_role             = $refRequest['initiatorRoleId'];
        $mWaterConsumeActive->save();
        return $mWaterConsumeActive;
    }
    /**
     * | Get active request
     */
    public function getActiveRequest($applicationId)
    {
        return WaterConsumerActiveRequest::where('id', $applicationId)
            ->where('status', 1);
    }

    /**
     * | Update the application Doc Verify status
     * | @param applicationId
     */
    public function updateAppliVerifyStatus($applicationId)
    {
        WaterConsumerActiveRequest::where('id', $applicationId)
            ->update([
                'doc_verify_status' => true
            ]);
    }

    /**
     * | Deactivate the doc Upload Status 
     */
    public function updateUploadStatus($applicationId, $status)
    {
        return  WaterConsumerActiveRequest::where('id', $applicationId)
            ->where('status', true)
            ->update([
                "doc_upload_status" => $status
            ]);
    }
    /**
     * | Get application details by Id
     */
    public function getApplicationDtls($appId)
    {
        return self::select('*')
            ->where('id', $appId)
            ->first();
    }

    public function fullWaterDetails($request)
    {
        return  WaterConsumerActiveRequest::select(
            'water_consumer_active_requests.id',
            'water_consumer_active_requests.consumer_id',
            'water_consumer_active_requests.id as applicationId',
            // 'water_consumer_active_requests.mobile_no',
            'water_second_consumers.tab_size',
            'water_second_consumers.property_no',
            'water_consumer_active_requests.status',
            'water_second_consumers.payment_status',
            'water_second_consumers.user_type',
            'water_consumer_active_requests.apply_date',
            'water_second_consumers.address',
            'water_second_consumers.category',
            'water_consumer_active_requests.application_no',
            'water_second_consumers.pin',
            'water_consumer_active_requests.current_role',
            'water_consumer_active_requests.workflow_id',
            'water_consumer_active_requests.last_role_id',
            'water_consumer_active_requests.doc_upload_status',
            'water_property_type_mstrs.property_type',
            // 'water_param_pipeline_types.pipeline_type',
            'zone_masters.zone_name',
            'ulb_masters.ulb_name',
            'water_connection_type_mstrs.connection_type',
            'wf_roles.role_name AS current_role_name',
            'water_connection_type_mstrs.connection_type',
            'ulb_ward_masters.ward_name',
            'water_consumer_charge_categories.charge_category'
        )
            ->leftjoin('wf_roles', 'wf_roles.id', '=', 'water_consumer_active_requests.current_role')
            ->leftjoin('ulb_masters', 'ulb_masters.id', '=', 'water_consumer_active_requests.ulb_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_consumer_active_requests.ward_mstr_id')
            ->join('water_second_consumers', 'water_second_consumers.id', '=', 'water_consumer_active_requests.consumer_id')
            ->join('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_second_consumers.connection_type_id')
            ->join('water_property_type_mstrs', 'water_property_type_mstrs.id', 'water_second_consumers.property_type_id')
            ->join('water_consumer_charge_categories', 'water_consumer_charge_categories.id', 'water_consumer_active_requests.charge_catagory_id')
            ->leftjoin('zone_masters', 'zone_masters.id', 'water_second_consumers.zone_mstr_id')
            ->where('water_consumer_active_requests.id', $request->applicationId)
            ->where('water_consumer_active_requests.status', true);
    }

    /**
     * | Get approved appliaction using the id 
     */
    public function getApproveApplication($applicationId)
    {
        return WaterConsumerActiveRequest::where('id', $applicationId)
            ->where('status', 1)
            ->where('verify_status', 0)
            ->orderByDesc('id')
            ->first();
    }
    /**
     * | Deactivate the doc Upload Status 
     */
    public function updateVerifystatus($applicationId, $status)
    {
        return  WaterConsumerActiveRequest::where('id', $applicationId)
            ->where('status', true)
            ->update([
                "verify_status" => $status
            ]);
    }

    /**
     * | Get the Application according to user details 
     */
    public function getApplicationsById($applicationId)
    {
        return WaterConsumerActiveRequest::select(
            'water_consumer_active_requests.id',
            'water_consumer_active_requests.reason',
            'water_consumer_active_requests.remarks',
            'water_consumer_active_requests.amount',
            'water_consumer_active_requests.application_no',
            // DB::raw('REPLACE(water_consumer_charges.charge_category, \'_\', \' \') as charge_category'),
            'water_consumer_active_requests.verify_status',
            "water_consumer_active_requests.corresponding_address",
            "water_consumer_active_requests.corresponding_mobile_no",
            "water_second_consumers.consumer_no",
            "water_second_consumers.category",
            "water_second_consumers.address",
            "water_second_consumers.tab_size",
            "water_consumer_active_requests.ward_mstr_id",
            "water_consumer_active_requests.apply_date",
            "water_consumer_active_requests.payment_status",
            "ulb_ward_masters.ward_name",
            "water_consumer_owners.applicant_name as ownerName",
            "water_consumer_charge_categories.charge_category"
        )
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_consumer_active_requests.ward_mstr_id')
            ->leftjoin('water_consumer_charges', 'water_consumer_charges.related_id', 'water_consumer_active_requests.id')
            ->join('water_consumer_charge_categories', 'water_consumer_charge_categories.id', 'water_consumer_active_requests.charge_catagory_id')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_active_requests.consumer_id')
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', 'water_consumer_active_requests.consumer_id')
            ->where('water_consumer_active_requests.id', $applicationId)
            ->where('water_consumer_active_requests.status', 1)
            ->where('water_consumer_active_requests.verify_status', 1);
    }
}
