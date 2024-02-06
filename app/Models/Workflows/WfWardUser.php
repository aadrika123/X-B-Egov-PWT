<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WfWardUser extends Model
{
    use HasFactory;

    /**
     * | Get Wards by user id
     * | @var userId
     */
    public function getWardsByUserId($userId)
    {
        return WfWardUser::select('id', 'ward_id')
            ->where('user_id', $userId)
            ->where("is_suspended" ,false)
            ->orderBy('ward_id')
            ->get();
    }


    //create warduser
    public function addWardUser($req)
    {
        $createdBy = Auth()->user()->id;
        $device = new WfWardUser;
        $device->user_id = $req->userId;
        $device->ward_id = $req->wardId;
        $device->is_admin = $req->isAdmin;
        $device->created_by = $createdBy;
        $device->stamp_date_time = Carbon::now();
        $device->created_at = Carbon::now();
        $device->save();
    }

    //update ward user
    public function updateWardUser($req)
    {
        $device = WfWardUser::find($req->id);
        $device->user_id = $req->userId;
        $device->ward_id = $req->wardId;
        $device->is_admin = $req->isAdmin;
        $device->save();
    }


    //list ward user by id
    public function listbyId($req)
    {
        $data = WfWardUser::select(
            'wf_ward_users.id',
            'user_id',
            'ward_id',
            'is_admin',
            'user_name',
            'ward_name',
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'wf_ward_users.ward_id')
            ->join('users', 'users.id', 'wf_ward_users.user_id')
            ->where('wf_ward_users.id', $req->id)
            ->where('is_suspended', 'false')
            ->get();
        return $data;
    }

    //list ward user
    public function listWardUser()
    {
        $data = WfWardUser::select(
            'wf_ward_users.id',
            'user_id',
            'ward_id',
            'is_admin',
            'user_name',
            'ward_name'
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'wf_ward_users.ward_id')
            ->join('users', 'users.id', 'wf_ward_users.user_id')
            ->where('is_suspended', 'false')
            ->orderByDesc('wf_ward_users.id')
            ->get();
        return $data;
    }

    //delete ward user
    public function deleteWardUser($req)
    {
        $data = WfWardUser::find($req->id);
        $data->is_suspended = "true";
        $data->save();
    }
}
