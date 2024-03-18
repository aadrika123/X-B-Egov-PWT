<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefPropTransferMode extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | Get Transfer Modes
     */
    public function getTransferModes()
    {
        return RefPropTransferMode::select(
            'id',
            DB::raw('INITCAP(transfer_mode) as transfer_mode')
        )
            ->where('status', 1)
            ->get();
    }
}
