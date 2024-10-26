<?php

namespace App\Models;

use App\Models\Property\PropParamModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropActiveSafsTransferDetail extends PropParamModel
{
    use HasFactory;
    protected $guarded = [];

    public function addTransferDetail(array $detail, int $safId)
    {
        $transfer = new PropActiveSafsTransferDetail();
        $transfer->saf_id = $safId;
        $transfer->name = $detail['name'];
        $transfer->transfer_mode = $detail['transferMode'];
        $transfer->sale_value = $detail['saleValue'];
        $transfer->save();
    
        return $transfer; // Return the saved model instance
    }
    

}
