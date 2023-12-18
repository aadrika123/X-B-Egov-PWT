<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropPinelabPaymentsRequest extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($requst)
    {
        $data = [
            "saf_id"        => $requst->safId,
            "prop_id"       => $requst->propId,
            "bill_ref_no"   => $requst->resRefNo,
            "payment_type"  =>  $requst->paymentType,
            "payment_mode"  =>  $requst->paymentMode,
            "tran_type"     => $requst->tranType,
            "from_fyear"    => $requst->fromFyear,
            "to_fyear"      => $requst->toFyear,
            "demand_amt"    => $requst->demandAmt,
            "payable_amount" => $requst->paidAmount,
            "arrear_settled" => $requst->arrearSettled,
            "arrear_settled" => $requst->arrearSettled,
            "is_arrear_settled"=> $requst->isArrearSettled,
            "demand_list"   => json_encode($requst->demandList->toArray()) ?? null,
            "request"       => json_encode($requst->all()) ?? null,
            "ulb_id"        => $requst->ulbId,
            "ip_address"    => $requst->ipAddress ?? getClientIpAddress(),
            // "user_id"       => $requst->userId,
            // "user_type"     => $requst->userType,
            // "auth"          => $requst->auth
        ];
        return PropPinelabPaymentsRequest::create($data)->id;
    }
    
}
