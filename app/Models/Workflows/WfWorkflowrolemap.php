<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WfWorkflowrolemap extends Model
{
    use HasFactory;

    /**
     * Create Role Map
     */
    public function addRoleMap($req)
    {
        $createdBy = Auth()->user()->id;
        $data = new WfWorkflowrolemap;
        $data->workflow_id = $req->workflowId;
        $data->wf_role_id = $req->wfRoleId;
        $data->forward_role_id = $req->forwardRoleId;
        $data->backward_role_id = $req->backwardRoleId;
        $data->is_initiator = $req->isInitiator;
        $data->is_finisher = $req->isFinisher;
        $data->allow_full_list = $req->allowFullList;
        $data->can_escalate = $req->canEscalate;
        $data->serial_no = $req->serialNo;
        $data->is_btc = $req->isBtc;
        $data->is_enabled = $req->isEnabled;
        $data->can_view_document = $req->canViewDocument;
        $data->can_upload_document = $req->canUploadDocument;
        $data->can_verify_document = $req->canVerifyDocument;
        $data->allow_free_communication = $req->allowFreeCommunication;
        $data->can_forward = $req->canForward;
        $data->can_backward = $req->canBackward;
        $data->is_pseudo = $req->isPseudo;
        $data->show_field_verification = $req->showFieldVerification;
        $data->created_by = $createdBy;
        $data->stamp_date_time = Carbon::now();
        $data->created_at = Carbon::now();
        $data->save();
    }

    /**
     * Update Role Map
     */
    public function updateRoleMap($req)
    {
        $data = WfWorkflowrolemap::find($req->id);
        $data->workflow_id = $req->workflowId;
        $data->wf_role_id = $req->wfRoleId;
        $data->forward_role_id = $req->forwardRoleId;
        $data->backward_role_id = $req->backwardRoleId;
        $data->is_initiator = $req->isInitiator;
        $data->is_finisher = $req->isFinisher;
        $data->allow_full_list = $req->allowFullList;
        $data->can_escalate = $req->canEscalate;
        $data->serial_no = $req->serialNo;
        $data->is_btc = $req->isBtc;
        $data->is_enabled = $req->isEnabled;
        $data->can_view_document = $req->canViewDocument;
        $data->can_upload_document = $req->canUploadDocument;
        $data->can_verify_document = $req->canVerifyDocument;
        $data->allow_free_communication = $req->allowFreeCommunication;
        $data->can_forward = $req->canForward;
        $data->can_backward = $req->canBackward;
        $data->is_pseudo = $req->isPseudo;
        $data->show_field_verification = $req->showFieldVerification;
        $data->save();
    }

    /**
     * Role Map List by id
     */
    public function listbyId($req)
    {
        $data = WfWorkflowrolemap::select('*')
            ->join('wf_workflows', 'wf_workflows.id', 'wf_workflowrolemaps.workflow_id')
            ->join('wf_masters', 'wf_masters.id', 'wf_workflows.wf_master_id')
            ->join('wf_roles', 'wf_roles.id', 'wf_workflowrolemaps.wf_role_id')
            ->where('wf_workflowrolemaps.is_suspended', false)
            ->where('wf_workflowrolemaps.id', $req->id)
            ->first();
        return $data;
    }

    /**
     * All Role Map list
     */
    public function roleMaps()
    {
        $data = DB::table('wf_workflowrolemaps')
            ->select(
                'wf_workflowrolemaps.*',
                'r.role_name as forward_role_name',
                'rr.role_name as backward_role_name',
                'wf_roles.role_name',
                'wf_masters.workflow_name',
                'ulb_name'
            )
            ->join('wf_workflows', 'wf_workflows.id', 'wf_workflowrolemaps.workflow_id')
            ->join('wf_masters', 'wf_masters.id', 'wf_workflows.wf_master_id')
            ->leftJoin('wf_roles as r', 'wf_workflowrolemaps.forward_role_id', '=', 'r.id')
            ->leftJoin('wf_roles as rr', 'wf_workflowrolemaps.backward_role_id', '=', 'rr.id')
            ->join('wf_roles', 'wf_roles.id', 'wf_workflowrolemaps.wf_role_id')
            ->join('ulb_masters', 'ulb_masters.id', 'wf_workflows.ulb_id')
            ->where('wf_workflowrolemaps.is_suspended', false)
            ->orderBy('workflow_id')
            ->get();
        return $data;
    }

    /**
     * Delete Role Map
     */
    public function deleteRoleMap($req)
    {
        $data = WfWorkflowrolemap::find($req->id);
        $data->is_suspended = 'true';
        $data->save();
    }

    /**
     * | Get Workflow Forward and Backward Ids
     */
    public function getWfBackForwardIds($req)
    {
        return WfWorkflowrolemap::select('forward_role_id', 'backward_role_id')
            ->where('workflow_id', $req->workflowId)
            ->where('wf_role_id', $req->roleId)
            ->where('is_suspended', false)
            ->first();
    }

    public function getWfBackForwardIdsV2($req)
    {
        return WfWorkflowrolemap::select('forward_role_id', 'backward_role_id')
            ->where('workflow_id', $req->workflowId)
            ->where('wf_role_id', $req->roleId)
            ->first();
    }

    /**
     * | Get Ulb Workflows By Role Ids
     */
    public function getWfByRoleId($roleIds)
    {
        return WfWorkflowrolemap::select('workflow_id',"*")
            ->whereIn('wf_role_id', $roleIds)
            ->get();
    }
}
