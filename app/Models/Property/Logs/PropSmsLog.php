<?php

namespace App\Models\Property\Logs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropSmsLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($requst)
    {
        $data = [
            "emp_id"         => $requst->userId??(Auth()->user()->id??0),
            "ref_id"         => $requst->appId,
            "ref_type"       => $requst->refType,
            "mobile_no"      =>  $requst->mobileNo,
            "purpose"        =>  $requst->purpose,
            "template_id"    => $requst->templateId,
            "mesage"        => $requst->sms
        ];
        return PropSmsLog::create($data)->id;
    }
    public function updateResponse($requst)
    {
        $id = $requst->sms_id;
        $data =[
            "response"=>$requst->response,
            "smgid"=>$requst->smgid
        ];
        return self::where("id",$id)->update([$data]);
    }
}
