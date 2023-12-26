<?php

namespace App\Repository\Common;

use App\Models\Ward\WardUser;
use App\Models\Workflows\WfMaster;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfRole;
use App\Models\Workflows\WfRoleusermap;
use App\Traits\Auth;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CommonFunction implements ICommonFunction
{
    use Auth;
    public function WardPermission($user_id)
    {
        $redis = Redis::connection();
        $ward_permission = ""; //json_decode(Redis::get('WardPermission:' . $user_id),true)??null; 
        if (!$ward_permission) {
            Redis::del('WardPermission:' . $user_id);
            $ward_permission = WfWardUser::select(
                "ward_id",
                "ulb_ward_masters.id",
                DB::raw("ulb_ward_masters.ward_name as ward_no")
            )
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "=", "wf_ward_users.ward_id")
                ->where('user_id', $user_id)
                ->orderBy('ward_id')
                ->get();
            $ward_permission = objToArray($ward_permission);
            $this->WardPermissionSet($redis, $user_id, $ward_permission);
        }
        return $ward_permission;
    }
    public function oldWardPermission($user_id)
    {
        $redis = Redis::connection();
        $ward_permission = ""; //json_decode(Redis::get('WardPermission:' . $user_id),true)??null; 
        if (!$ward_permission) {
            Redis::del('WardPermission:' . $user_id);
            $ward_permission = WfWardUser::select(
                DB::raw("min(ward_id) as ward_id,
                min(ulb_ward_masters.id) as id,
                ulb_ward_masters.ward_name as ward_no")
            )
                ->join("ulb_ward_masters", "ulb_ward_masters.id", "=", "wf_ward_users.ward_id")
                ->where('user_id', $user_id)
                ->groupBy("ward_name")
                ->orderBy('ward_name')
                ->get();
            $ward_permission = objToArray($ward_permission);
            $this->WardPermissionSet($redis, $user_id, $ward_permission);
        }
        return $ward_permission;
    }
    public function getWorkFlowRoles($user_id, int $ulb_id, int $work_flow_id)
    {
        $redis = Redis::connection();
        $workflow_rolse = ""; //json_decode(Redis::get('WorkFlowRoles:' . $user_id.":".$work_flow_id),true)??null;
        if (!$workflow_rolse) {
            // DB::enableQueryLog();
            $workflow_rolse = WfMaster::select(
                DB::raw(
                    "wf_roles.id ,wf_roles.role_name,
                    forward_role_id as forward_role_id,
                    backward_role_id as backward_role_id,
                    is_initiator,is_finisher,
                    wf_masters.workflow_name,
                    wf_masters.id as workflow_id,
                    wf_workflows.ulb_id,
                    wf_workflowrolemaps.can_escalate,
                    wf_workflowrolemaps.serial_no,wf_workflowrolemaps.is_btc,
                    wf_workflowrolemaps.can_edit,
                    wf_workflowrolemaps.can_upload_document,
                    wf_workflowrolemaps.can_verify_document"
                    // "*"
                )
            )
                ->join("wf_workflows", function ($join) {
                    $join->on("wf_workflows.wf_master_id", "wf_masters.id")
                        ->where("wf_workflows.is_suspended", FALSE);
                })
                ->join(
                    DB::raw("(SELECT distinct(wf_role_id) as wf_role_id,
                                                workflow_id , forward_role_id , backward_role_id,
                                                is_initiator,is_finisher,
                                                wf_workflowrolemaps.allow_full_list,wf_workflowrolemaps.can_escalate,
                                                wf_workflowrolemaps.serial_no,wf_workflowrolemaps.is_btc,
                                                wf_workflowrolemaps.allow_full_list,
                                                wf_workflowrolemaps.can_verify_document,
                                                wf_workflowrolemaps.can_upload_document,
                                                wf_workflowrolemaps.can_edit
                                            FROM wf_workflowrolemaps 
                                            WHERE  wf_workflowrolemaps.is_suspended = false 
                                            GROUP BY workflow_id,wf_role_id , forward_role_id , backward_role_id, is_initiator, is_finisher,
                                                wf_workflowrolemaps.allow_full_list,wf_workflowrolemaps.can_escalate,
                                                wf_workflowrolemaps.serial_no,wf_workflowrolemaps.is_btc,
                                                wf_workflowrolemaps.can_verify_document,
                                                wf_workflowrolemaps.can_upload_document,
                                                wf_workflowrolemaps.can_edit
                                            ) wf_workflowrolemaps"),
                    function ($join) use ($ulb_id) {
                        $join->on("wf_workflowrolemaps.workflow_id", "wf_workflows.id");
                    }
                )
                ->join("wf_roles", "wf_roles.id", "wf_workflowrolemaps.wf_role_id")
                ->where("wf_roles.is_suspended", false)
                ->where("wf_workflows.ulb_id", $ulb_id)
                ->where("wf_workflows.wf_master_id", $work_flow_id)
                ->where("wf_workflows.is_suspended", false)
                ->orderBy("wf_roles.id")
                ->get();
            // dd(DB::getQueryLog());
            $workflow_rolse = objToArray($workflow_rolse);
            $this->WorkFlowRolesSet($redis, $user_id, $workflow_rolse, $work_flow_id);
        }
        return $workflow_rolse;
    }

    public function getForwordBackwordRoll($user_id, int $ulb_id, int $work_flow_id, int $role_id, $finisher = null)
    {
        $retuns = [];
        $workflow_rolse = $this->getWorkFlowRoles($user_id, $ulb_id, $work_flow_id);
        $backwordForword = array_filter($workflow_rolse, function ($val) use ($role_id) {
            return $val['id'] == $role_id;
        });
        $backwordForword = array_values($backwordForword)[0] ?? [];
        if ($backwordForword) {
            $data = array_map(function ($val) use ($backwordForword) {
                if ($val['id'] == $backwordForword['forward_role_id']) {
                    return ['forward' => ['id' => $val['id'], 'role_name' => $val['role_name']]];
                }
                if ($val['id'] == $backwordForword['backward_role_id']) {
                    return ['backward' => ['id' => $val['id'], 'role_name' => $val['role_name']]];
                }
            }, $workflow_rolse);
            $data = array_filter($data, function ($val) {
                return is_array($val);
            });
            $data = array_values($data);

            $forward = array_map(function ($val) {
                return $val['forward'] ?? false;
            }, $data);

            $forward = array_filter($forward, function ($val) {
                return is_array($val);
            });
            $forward = array_values($forward)[0] ?? [];

            $backward = array_map(function ($val) {
                return $val['backward'] ?? false;
            }, $data);

            $backward = array_filter($backward, function ($val) {
                return is_array($val);
            });
            $backward = array_values($backward)[0] ?? [];
            // dd($backward);
            $retuns["backward"] = $backward;
            $retuns["forward"] = $forward;
        }
        return $retuns;
    }

    public function getAllRoles($user_id, int $ulb_id, int $work_flow_id, int $role_id, $all = false)
    {
        try {
            $data = $this->getWorkFlowRoles($user_id, $ulb_id, $work_flow_id, $role_id);
            // dd($data);
            $curentUser = array_filter($data, function ($val) use ($role_id) {
                return $val['id'] == $role_id;
            });
            $curentUser = array_values($curentUser)[0] ?? []; //dd($curentUser);
            if ($curentUser) {
                $data = array_filter($data, function ($val) use ($curentUser, $all) { //dd();
                    if ($all) {
                        return (!in_array($val['id'], [$curentUser['forward_role_id'], $curentUser['backward_role_id']]) && $val['id'] != $curentUser['id'] && ($val['forward_id'] || $val['backward_id']));
                    }
                    return (!in_array($val['id'], [$curentUser['forward_role_id'], $curentUser['backward_role_id']]) && $val['id'] != $curentUser['id']);
                });
            }
            return (array_values($data));
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getUserRoll($user_id, $ulb_id, $workflow_id)
    {
        try {
            DB::enableQueryLog();
            $data = WfRole::select(
                DB::raw(
                    "wf_roles.id as role_id,wf_roles.role_name,
                    wf_workflowrolemaps.is_initiator, wf_workflowrolemaps.is_finisher,
                    wf_workflowrolemaps.forward_role_id,forword.role_name as forword_name,
                    wf_workflowrolemaps.backward_role_id,backword.role_name as backword_name,
                    wf_workflowrolemaps.allow_full_list,wf_workflowrolemaps.can_escalate,
                    wf_workflowrolemaps.serial_no,wf_workflowrolemaps.is_btc,
                    wf_workflowrolemaps.can_upload_document,
                    wf_workflowrolemaps.can_verify_document,
                    wf_workflowrolemaps.can_backward,
                    wf_workflowrolemaps.can_edit,
                    wf_workflows.id as workflow_id,wf_masters.workflow_name,
                    ulb_masters.id as ulb_id, ulb_masters.ulb_name,
                    ulb_masters.ulb_type"
                )
            )
                ->join("wf_roleusermaps", function ($join) {
                    $join->on("wf_roleusermaps.wf_role_id", "=", "wf_roles.id")
                        ->where("wf_roleusermaps.is_suspended", "=", FALSE);
                })
                ->join("users", "users.id", "=", "wf_roleusermaps.user_id")
                ->join("wf_workflowrolemaps", function ($join) {
                    $join->on("wf_workflowrolemaps.wf_role_id", "=", "wf_roleusermaps.wf_role_id")
                        ->where("wf_workflowrolemaps.is_suspended", "=", FALSE);
                })
                ->leftjoin("wf_roles AS forword", "forword.id", "=", "wf_workflowrolemaps.forward_role_id")
                ->leftjoin("wf_roles AS backword", "backword.id", "=", "wf_workflowrolemaps.backward_role_id")
                ->join("wf_workflows", function ($join) {
                    $join->on("wf_workflows.id", "=", "wf_workflowrolemaps.workflow_id")
                        ->where("wf_workflows.is_suspended", "=", FALSE);
                })
                ->join("wf_masters", function ($join) {
                    $join->on("wf_masters.id", "=", "wf_workflows.wf_master_id")
                        ->where("wf_masters.is_suspended", "=", FALSE);
                })
                ->join("ulb_masters", "ulb_masters.id", "=", "wf_workflows.ulb_id")
                ->where("wf_roles.is_suspended", false)
                ->where("wf_roleusermaps.user_id", $user_id)
                ->where("wf_workflows.ulb_id", $ulb_id)
                ->where("wf_workflows.wf_master_id", $workflow_id)
                ->orderBy("wf_roleusermaps.id", "desc")
                ->first();
            // dd(DB::getQueryLog());
            return $data;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function iniatorFinisher($user_id, $ulb_id, $refWorkflowId) //array
    {
        try {
            $getWorkFlowRoles = $this->getWorkFlowRoles($user_id, $ulb_id, $refWorkflowId);
            $initater = array_filter($getWorkFlowRoles, function ($val) {
                return $val['is_initiator'] == true;
            });
            $initater = (array_values($initater)[0]) ?? array(null);
            $finisher = array_filter($getWorkFlowRoles, function ($val) {
                return $val['is_finisher'] == true;
            });
            $finisher = (array_values($finisher)[0]) ?? array(null);
            return ["initiator" => $initater, "finisher" => $finisher];
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function userType($refWorkflowId, $ulb_id = null): string
    {
        $user = Auth()->user();
        $tableName = $user ? $user->gettable() : null;
        $user_id = $user->id ?? 0;
        $ulb_id = ($ulb_id ? $ulb_id : ($user->ulb_id ?? 0));
        $user_data = $this->getUserRoll($user_id, $ulb_id, $refWorkflowId);
        $roll_id =  $user_data->role_id ?? -1;
        if ($roll_id != -1 && ($tableName == "users")) {
            $user_type_sort = Config::get('TradeConstant.USER-TYPE-SHORT-NAME.' . strtoupper($user_data->role_name));
            if (!$user_type_sort) {
                return "Online";
            }
            return $user_type_sort;
        } else
            return "Online";
    }

    public function getWorkFlowAllRoles($user_id, int $ulb_id, int $work_flow_id, $all = false)
    {
        try {
            $data = $this->getWorkFlowRoles($user_id, $ulb_id, $work_flow_id);
            if ($all) {
                $data = array_filter($data, function ($val) {
                    if (($val['forward_role_id']) || $val['backward_role_id']) {
                        return true;
                    }
                });
                $data = array_values($data);
            }
            return ($data);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function sortsWorkflowRols($roles)
    {
        return (collect($roles)->sortBy('serial_no')->values()->all());
    }

    public function checkUsersWithtocken($tbl = "users")
    {
        $refUser            = Auth()->user();
        $tableName = $refUser ? $refUser->gettable() : "";
        if ($tableName != $tbl) {
            return false;
        }
        return true;
    }

    public function getReactionActionTakenRole($user_id, int $ulb_id, int $work_flow_id,$action="doc_verify")
    {
        $roles = ((collect($this->getWorkFlowRoles($user_id, $ulb_id, $work_flow_id))->sortBy("serial_no"))->values());
        switch($action)
        {
            case "doc_verify": $roles = $roles->where("can_verify_document",true);
                                break;
            case "doc_upload": $roles = $roles->where("can_upload_document",true);
                                break;
            case "can_edit": $roles = $roles->where("can_edit",true);
                                break;
        }
        return($roles);
    }

    public function getUserAllRoles()
    {
        $user = Auth()->user();
        $constaint = Config::get("TradeConstant.USER-TYPE-SHORT-NAME");
        return WfRoleusermap::select(
            DB::raw(
                "distinct wf_roles.id as role_id, 
                wf_roles.role_name,user_id"
                )
            )
            ->join("wf_roles","wf_roles.id","wf_roleusermaps.wf_role_id")
            ->where("wf_roleusermaps.user_id",$user->id??0)
            ->where("wf_roles.is_suspended",FALSE)
            ->where("wf_roleusermaps.is_suspended",FALSE)
            ->get()
            ->map(function($val) use($constaint){
                $val->sort_name = $constaint[strtoupper($val->role_name)]??"";
                return $val;
            });
            
    }
}
