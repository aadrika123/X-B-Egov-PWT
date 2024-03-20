<?php

namespace App\Observers\Property;

use App\Models\Property\PropTransaction;
use Carbon\Carbon;
use Exception;

class PropTransactionObserver
{
    public function created(PropTransaction $propTransaction)
    {
        $test = PropTransaction::where("id","<>",$propTransaction->id)->where("property_id",$propTransaction->property_id)->orderBy("id","DESC")->first();
        if ($test && (Carbon::parse($test->created_at)->diffInMinutes(Carbon::parse()) < 2)) {
            throw new Exception("Please Wait at least 2 minutes after first transaction");
        }
    }
}
