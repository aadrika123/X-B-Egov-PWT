<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WaterChequeDtl extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get check details by id
     */
    public function chequeDtlById($request)
    {
        return WaterChequeDtl::select('*')
            ->where('id', $request->chequeId)
            ->where('status', 2)
            ->first();
    }

    /**
     * | Get Details for the payment receipt
     * | Onlyin case of connection
     */
    public function getChequeDtlsByTransId($transId)
    {
        return WaterChequeDtl::where('transaction_id', $transId)
            ->where('status', '!=', 0);
    }

    /**
     * | Save the cheque details 
     */
    public function postChequeDtl($req)
    {
        $mPropChequeDtl = new WaterChequeDtl();
        $mPropChequeDtl->consumer_id        =  $req['consumer_id'] ?? null;
        $mPropChequeDtl->application_id     =  $req['application_id'] ?? null;
        $mPropChequeDtl->consumer_req_id    =  $req['consumer_req_id'] ?? null;
        $mPropChequeDtl->transaction_id     =  $req['transaction_id'];
        $mPropChequeDtl->cheque_date        =  $req['cheque_date'];
        $mPropChequeDtl->bank_name          =  $req['bank_name'];
        $mPropChequeDtl->branch_name        =  $req['branch_name'];
        $mPropChequeDtl->cheque_no          =  $req['cheque_no'];
        $mPropChequeDtl->user_id            =  $req['user_id'];
        $mPropChequeDtl->save();
    }
    /* deactivate transaction
    */
    public function updatedeactivateChequeDtls($transactionId, $updateDetails)
    {
        return WaterChequeDtl::where('transaction_id', $transactionId)
            ->update($updateDetails);
    }
}
