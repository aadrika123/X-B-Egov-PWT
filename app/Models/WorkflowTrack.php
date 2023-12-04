<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class WorkflowTrack extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];
    protected $connection = "pgsql_master";

    /**
     * | Store Track
     */
    public function store($req)
    {
        WorkflowTrack::create($req);
    }

    public function saveTrack($request)
    {
        $track      = new WorkflowTrack;
        $userId     = $request->user_id;
        $ulbId      = $request->ulb_id ?? authUser($request)->ulb_id;
        $mTrackDate = $request->trackDate ?? Carbon::now()->format('Y-m-d H:i:s');

        $track->workflow_id         = $request->workflowId;
        $track->citizen_id          = $request->citizenId;
        $track->module_id           = $request->moduleId;
        $track->ref_table_dot_id    = $request->refTableDotId;
        $track->ref_table_id_value  = $request->refTableIdValue;
        $track->track_date          = $mTrackDate;
        $track->message             = $request->comment;
        $track->forward_date        = $request->forwardDate ?? null;
        $track->forward_time        = $request->forwardTime ?? null;
        $track->sender_role_id      = $request->senderRoleId ?? null;
        $track->receiver_role_id    = $request->receiverRoleId ?? null;
        $track->verification_status = $request->verificationStatus ?? 0;
        $track->user_id             = $userId;
        $track->ulb_id              = $ulbId;
        $track->status              = true;
        return  $track->save();
    }

    public function details()
    {
        return  DB::table('workflow_tracks')
            ->select(
                'workflow_tracks.id',
                'workflow_tracks.user_id',
                'workflow_tracks.citizen_id',
                'workflow_tracks.module_id',
                'module_masters.module_name',
                'workflow_tracks.ref_table_dot_id',
                'workflow_tracks.ref_table_id_value',
                'workflow_tracks.message',
                'workflow_tracks.track_date',
                'users.user_name'
            )
            ->join('users', 'users.id', 'workflow_tracks.user_id')
            ->join('module_masters', 'module_masters.id', 'workflow_tracks.module_id');
    }

    /**
     * | Get Tracks by Ref Table Id
     */
    public function getTracksByRefId($mRefTable, $tableId)
    {
        return DB::table('workflow_tracks')
            ->select(
                'workflow_tracks.ref_table_dot_id AS referenceTable',
                'workflow_tracks.ref_table_id_value AS applicationId',
                'workflow_tracks.message',
                'workflow_tracks.track_date',
                'workflow_tracks.forward_date',
                'workflow_tracks.forward_time',
                'w.role_name as commentedBy',
                'wr.role_name as forwarded_to',
                'users.name',
                'users.user_code',
            )
            ->where('ref_table_dot_id', $mRefTable)
            ->where('ref_table_id_value', $tableId)
            ->join('wf_roles as w', 'w.id', '=', 'workflow_tracks.sender_role_id')
            ->join('users', 'users.id', '=', 'workflow_tracks.user_id')
            ->leftJoin('wf_roles as wr', 'wr.id', '=', 'workflow_tracks.receiver_role_id')
            ->where('citizen_id', null)
            ->orderByDesc('workflow_tracks.id')
            ->get();
    }

    /**
     * | Get Citizen Comment
     */
    public function getCitizenTracks($mRefTable, $tableId, $citizenId)
    {
        return DB::table('workflow_tracks')
            ->select(
                'workflow_tracks.ref_table_dot_id AS referenceTable',
                'workflow_tracks.ref_table_id_value AS applicationId',
                'workflow_tracks.message',
                'workflow_tracks.track_date',
                'workflow_tracks.forward_date',
                'workflow_tracks.forward_time',
                'u.user_name as commentedBy'
            )
            ->where('ref_table_dot_id', $mRefTable)
            ->where('ref_table_id_value', $tableId)
            ->where('citizen_id', $citizenId)
            ->Join('active_citizens as u', 'u.id', '=', 'workflow_tracks.citizen_id')
            ->get();
    }

    /**
     * |total forwaded application
     */
    public function todayForwadedApplication($currentRole, $ulbId, $propertyWorflows)
    {
        $date = Carbon::now();
        return WorkflowTrack::where('sender_role_id', $currentRole)
            ->where('forward_date', $date)
            ->where('ulb_id', $ulbId)
            ->whereIn('workflow_id', $propertyWorflows)
            ->get();
    }


    /**
     * |Total Approved application
     */
    public function todayApprovedApplication($currentRole, $ulbId, $propertyWorflows)
    {
        $date = Carbon::now();
        return WorkflowTrack::where('receiver_role_id', $currentRole)
            ->where('forward_date', $date)
            ->where('ulb_id', $ulbId)
            ->where('verification_status', 1)
            ->whereIn('workflow_id', $propertyWorflows)
            ->get();
    }

    /**
     * |Total Rejected application
     */
    public function todayRejectedApplication($currentRole, $ulbId, $propertyWorflows)
    {
        $date = Carbon::now();
        return WorkflowTrack::where('receiver_role_id', $currentRole)
            ->where('forward_date', $date)
            ->where('ulb_id', $ulbId)
            ->where('verification_status', 2)
            ->whereIn('workflow_id', $propertyWorflows)
            ->get();
    }


    /**
     * | Get workflow track
     */
    public function getWfDashbordData($request)
    {
        $currentDate = Carbon::now()->format('Y-m-d');
        return WorkflowTrack::where('workflow_id', $request->workflowId)
            ->where('ulb_id', $request->ulbId)
            ->where('forward_date', $currentDate)
            ->where('module_id', $request->moduleId);
    }

    /**
     * | Get Workflow Track by Ref Table, Workflow, and ref table Value and Receiver RoleId
     */
    public function getWfTrackByRefId(array $req)
    {
        return WorkflowTrack::where('workflow_id', $req['workflowId'])
            ->where('ref_table_dot_id', $req['refTableDotId'])
            ->where('ref_table_id_value', $req['refTableIdValue'])
            ->where('receiver_role_id', $req['receiverRoleId'])
            ->where('status', true)
            ->first();
    }
}
