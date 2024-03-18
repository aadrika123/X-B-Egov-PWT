<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropPinelabPaymentsResponse extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($requst)
    {
        $data = [
            "request_id"    => $requst->requestId,
            "bill_ref_no"    => $requst->reqRefNo,
            "saf_id"        => $requst->safId,
            "prop_id"       => $requst->propId,
            "tran_id"       =>$requst->tranId,
            "payment_mode"  =>$requst->paymentMode,
            "response_code" =>$requst->responseCode,
            "response_sms" =>$requst->responseSms,
            "tran_amount"  => $requst->paidAmount,
            "base_amount"  => $requst->baseAmount??null,
            "proc_fee"      => $requst->procFee??null,
            "response_json" => json_encode($requst->requst,JSON_UNESCAPED_UNICODE),
            "user_id"       => $requst->userId,
            "ip_address"    => $requst->ipAddress ?? getClientIpAddress(),
            
        ];
        return PropPinelabPaymentsResponse::create($data)->id;
    }   
}
