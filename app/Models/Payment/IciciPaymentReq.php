<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciPaymentReq extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_master';
    protected $guarded = [];

    /**
     * | find reqRefNo
     */
    public function findByReqRefNo($reqRefNo)
    {
        return self::where('req_ref_no', $reqRefNo)
            ->first();
    }

    /**
     * | find reqRefNo
     */
    public function findByReqRefNoV2($reqRefNo)
    {
        return self::where('req_ref_no', $reqRefNo)
            ->where('status', 1)
            ->first();
    }

    /**
     * | find reqRefNo
     */
    public function findByReqRefNoV3($reqRefNo)
    {
        return self::where('req_ref_no', $reqRefNo)
            ->where('status', 1)
            ->where('payment_status', 0);
    }

    /**
     * | Find with help of Refe No 
     */
    public function getByReqRefNo($reqRefNo)
    {
        return self::where('req_ref_no', $reqRefNo);
    }


    /**
     * | Save the request details
     */
    public function updateRequestDetails($id, $updReqs)
    {
        return self::where('id', $id)
            ->where('status', 1)
            ->update($updReqs);
    }
}
