<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldChequeTranEntery extends PropParamModel #Model
{
    use HasFactory;
    public $timestamps = false;
    protected $guarded = [];

    public function store($req)
    {
        $data = [
            "prop_id" =>$req->propId,
            "tran_id" =>$req->tranId,
            "demand_id" =>$req->demandId,
            "book_no" =>$req->bookNo,
            "receipt_no" =>$req->ReceiptNo,
            "cheque_no" =>$req->chequeNo,
            "demand_log"=>$req->demand ? json_encode($req->demand->toArray(), JSON_UNESCAPED_UNICODE): null,
            "request_body"=>json_encode($req->toArray(), JSON_UNESCAPED_UNICODE),
            "user_id"=>$req->userId,
        ];
        $recodes = OldChequeTranEntery::create($data);
        return $recodes->id;
    }
    
}
