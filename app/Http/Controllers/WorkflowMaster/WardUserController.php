<?php

namespace App\Http\Controllers\WorkflowMaster;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workflows\WfRoleusermap;
use Illuminate\Http\Request;
use App\Models\Workflows\WfWardUser;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Svg\Tag\Rect;

/**
 * Controller for Add, Update, View , Delete of Wf Ward User Table
 * -------------------------------------------------------------------------------------------------
 * Created On-07-10-2022
 * Created By-Mrinal Kumar
 * Modification On: 19-12-2022
 * Status : Open
 * -------------------------------------------------------------------------------------------------
 */

class WardUserController extends Controller
{
    //create WardUser
    public function createWardUser(Request $req)
    {
        try {
            $req->validate([
                'userId' => 'required',
                'wardId' => 'required',
                'isAdmin' => 'required',
            ]);
            $checkExisting = WfWardUser::where('user_id', $req->userId)
                ->where('ward_id', $req->wardId)
                ->first();
            if ($checkExisting) {
                $checkExisting->user_id = $req->userId;
                $checkExisting->ward_id = $req->wardId;
                $checkExisting->save();
                return responseMsg(true, "User Exist", "");
            }

            $create = new WfWardUser();
            $create->addWardUser($req);

            return responseMsg(true, "Successfully Saved", "");
        } catch (Exception $e) {
            return response()->json(false, $e->getMessage());
        }
    }

    //update WardUser
    public function updateWardUser(Request $req)
    {
        try {
            $update = new WfWardUser();
            $list  = $update->updateWardUser($req);

            return responseMsg(true, "Successfully Updated", $list);
        } catch (Exception $e) {
            return response()->json(false, $e->getMessage());
        }
    }

    //WardUser list by id
    public function WardUserbyId(Request $req)
    {
        try {

            $listById = new WfWardUser();
            $list  = $listById->listbyId($req);

            return responseMsg(true, "WardUser List", $list);
        } catch (Exception $e) {
            return response()->json(false, $e->getMessage());
        }
    }

    //all WardUser list
    public function getAllWardUser()
    {
        try {

            $list = new WfWardUser();
            $WardUsers = $list->listWardUser();

            return responseMsg(true, "All WardUser List", $WardUsers);
        } catch (Exception $e) {
            return response()->json(false, $e->getMessage());
        }
    }


    //delete WardUser
    public function deleteWardUser(Request $req)
    {
        try {
            $delete = new WfWardUser();
            $delete->deleteWardUser($req);

            return responseMsg(true, "Data Deleted", '');
        } catch (Exception $e) {
            return response()->json($e, 400);
        }
    }

    public function tcList(Request $req)
    {
        $req->validate([
            'wardId' => 'nullable',
        ]);
        try {

            $user = authUser($req);
            $userId =  $user->id;
            $ulbId  =  $req->ulbId ?? $user->ulb_id;
            $mWfRoleusermap = new WfRoleusermap();
            if (!$ulbId)
                throw new Exception('Ulb Id Required');
            $tl = Config::get('role-constants.TEAM LEADER');
            $tc = Config::get('role-constants.TAX COLLECTOR');
            $jsk = Config::get('role-constants.JSK');
            $tcIds = [$tl, $tc, $jsk];
            $mreqs = new Request(['userId' => $userId]);

            $roleDtls = $mWfRoleusermap->getRoleByUserId($mreqs);

            if ($roleDtls->wf_role_id == $tl) {
                $tcList =  $mWfRoleusermap->getTcList($ulbId)
                    ->where('wf_role_id', $tc)
                    ->get();
            }

            $tcList =  $mWfRoleusermap->getTcList($ulbId)
                ->whereIn('wf_role_id', $tcIds)
                ->get();

            return responseMsgs(true, "TC List", remove_null($tcList), "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return response()->json($e, 400);
        }
    }
}
