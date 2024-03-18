<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropRazorpayRequest extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    // Store 
    public function store($req)
    {
        $stored = PropRazorpayRequest::create($req);
        return [
            'razorPayReqId' => $stored->id
        ];
    }

    /**
     * | Get Razor pay request by order id and saf id
     * | @param Request $req
     */
    public function getRazorPayRequests($req)
    {
        return PropRazorpayRequest::where('order_id', $req->orderId)
            ->where("$req->key", $req->keyId)
            ->orderByDesc('id')
            ->first();
    }
}
