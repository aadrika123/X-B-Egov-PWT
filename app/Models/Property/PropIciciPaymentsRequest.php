<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropIciciPaymentsRequest extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($requst)
    {
        $data = [
            "req_ref_no"    => $requst->reqRefNo,
            "encrypt_url"   => $requst->encryptUrl,
            "res_ref_no"    => $requst->resRefNo,
            "saf_id"        => $requst->safId,
            "prop_id"       => $requst->propId,
            "tran_type"     => $requst->paymentType,
            "from_fyear"    => $requst->fromFyear,
            "to_fyear"      => $requst->toFyear,
            "demand_amt"    => $requst->demandAmt,
            "payable_amount" => $requst->paidAmount,
            "arrear_settled" => $requst->arrearSettled,
            "demand_list"   => json_encode($requst->demandList->toArray()) ?? null,
            "request"       => json_encode($requst->all()) ?? null,
            "ulb_id"        => $requst->ulbId,
            "ip_address"    => $requst->ipAddress ?? getClientIpAddress(),
            "rebate_amount"  => $requst->rebate ,
            // "user_id"       => $requst->userId,
            // "user_type"     => $requst->userType,
            // "auth"          => $requst->auth
        ];
        return PropIciciPaymentsRequest::create($data)->id;
    }


    public function store1($requst)
    {
        $data = [
            "req_ref_no"    => $requst->reqRefNo,
            "encrypt_url"   => $requst->encryptUrl,
            "res_ref_no"    => $requst->resRefNo,
            "saf_id"        => $requst->safId,
            "prop_id"       => $requst->propId,
            "tran_type"     => $requst->tran_type,
            "from_fyear"    => $requst->from_fyear,
            "to_fyear"      => $requst->to_fyear,
            "demand_amt"    => $requst->amount ,
            "payable_amount" => $requst->paidAmount,
            "arrear_settled" => $requst->arrearSettled ??null,
            "demand_list"   =>  null,
            "request"       => json_encode($requst->all()) ?? null,
            "ulb_id"        => $requst->ulbId,
            "ip_address"    => $requst->ipAddress ?? getClientIpAddress(),
            "rebate_amount"  => $requst->rebate ,
            // "user_id"       => $requst->userId,
            // "user_type"     => $requst->userType,
            // "auth"          => $requst->auth
        ];
        return PropIciciPaymentsRequest::create($data)->id;
    }
}
