<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropTranDtl extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    // Store Prop Tran Dtls
    public function store(array $req)
    {
        PropTranDtl::create($req);
    }

    /**
     * | Get Tran Demands by TranId
     */
    public function getTranDemandsByTranId($tranId)
    {
        return PropTranDtl::where('tran_id', $tranId)
            ->where('status', 1)
            ->get();
    }
}
