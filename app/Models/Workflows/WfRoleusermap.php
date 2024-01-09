<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WfRoleusermap extends Model
{
    use HasFactory;

    /**
     * | get Role By User Id
     */
    public function getRoleIdByUserId($userId)
    {
        return WfRoleusermap::select('id', 'wf_role_id', 'user_id')
            ->where('user_id', $userId)
            ->where('is_suspended', false)
            ->get();
    }

    /**
     * | Get Role details by User Id
     */
    public function getRoleDetailsByUserId($userId)
    {
        return WfRoleusermap::select(
            'wf_roles.role_name AS roles',
            'wf_roles.id AS roleId'
        )
            ->join('wf_roles', 'wf_roles.id', '=', 'wf_roleusermaps.wf_role_id')
            ->where('wf_roleusermaps.user_id', $userId)
            ->where('wf_roleusermaps.is_suspended', false)
            ->orderByDesc('wf_roles.id')
            ->get();
    }

    /**
     * | Get role by User and Workflow Id
     */
    public function getRoleByUserWfId($req)
    {
        return DB::table('wf_roleusermaps as r')
            ->select(
                'r.wf_role_id',
                'w.forward_role_id',
                'w.backward_role_id'
            )
            ->join('wf_workflowrolemaps as w', 'w.wf_role_id', '=', 'r.wf_role_id')
            ->where('r.user_id', $req->userId)
            ->where('w.workflow_id', $req->workflowId)
            ->where('w.is_suspended', false)
            ->first();
    }

    public function getRoleByUserWfIdV2($req)
    {
        return DB::table('wf_roleusermaps as r')
            ->select(
                'r.wf_role_id',
                'w.forward_role_id',
                'w.backward_role_id'
            )
            ->join('wf_workflowrolemaps as w', 'w.wf_role_id', '=', 'r.wf_role_id')
            ->where('r.user_id', $req->userId)
            ->where('w.workflow_id', $req->workflowId)
            ->where('w.is_suspended', TRUE)
            ->first();
    }

    /**
     * | Get role by User Id
     */
    public function getRoleByUserId($req)
    {
        return DB::table('wf_roleusermaps as r')
            ->select(
                'id',
                'wf_role_id',
                'user_id'
            )
            ->where('r.user_id', $req->userId)
            ->where('r.is_suspended', false)
            ->first();
    }

    /**
     * | Get Tc list
     */
    public function getTcList($ulbId)
    {
        return WfRoleusermap::select(
            'users.id',
            'name',
            'user_type',
            'role_name',
            DB::raw("CONCAT(name,'(',role_name,')') AS user_name"),
        )
            ->join('users', 'users.id', 'wf_roleusermaps.user_id')
            ->join('wf_roles', 'wf_roles.id', 'wf_roleusermaps.wf_role_id')
            ->where('users.ulb_id', $ulbId)
            ->orderby('id');
    }
}
