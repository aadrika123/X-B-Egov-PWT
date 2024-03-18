<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradePinelabPayRequest extends TradeParamModel    #Model
{
    use HasFactory;
    protected $connection;
    public $timestamps=false;

    public function __construct($DB=null)
    {
        parent::__construct($DB);
    }

    // public function insert(array $data)
    // {
    //     $reqs = [
    //         'temp_id'         => $data["applicationId"]??null,
    //         'module_id'       => $data["moduleId"]??null,
    //         'tran_type'       => $data["paymentType"]??null,
    //         'merchant_id'     => $data["merchantId"]??null,
    //         'amount'          => $data["amount"]??null,
    //         'order_id'        => $data["orderId"]??null,            
    //         'ip_address'      => $data["ipAddress"]??null,
    //         'department_id'   => $data["departmentId"]??null,
    //         'citizen_id'      => $data["citizenId"]??null,
    //         // 'request_data'    => $data["requestData"]??null,
    //     ];
    //     return TradePinelabPayRequest::setConnection($this->connection)->create($reqs)->id;  
    // }
    public function edit(array $data)
    {
        $requestData = self::find($data["id"]);
        $reqs = [
            'payment_id'      => $data["paymentId"]??null,
            'signature'       => $data["signature"]??null,
            'error_reason'    => $data["errorReason"]??null,
        ];
        if(isset($data['status']))
        {
            $reqs["status"]= $data['status']?1:3;
        }
        return $requestData->update($reqs);
    }
}
