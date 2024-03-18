<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefPropSpecialRebateType extends PropParamModel #Model
{
    use HasFactory;

    public function specialRebate($from = null,$upto = null)
    {
        $from = $from?$from:Carbon::now()->format('Y-m-d');
        $upto = $upto?$upto:Carbon::now()->format('Y-m-d');

        return self::where("status",1)
                ->where("effective_from","<=",$from)
                ->where("effective_upto",">=",$upto)
                ->orderBy("id","ASC")
                ->get();

    }
}
