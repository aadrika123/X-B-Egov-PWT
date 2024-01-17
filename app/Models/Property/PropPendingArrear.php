<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropPendingArrear extends Model
{
    use HasFactory;

    /**
     * | Get Property Interest of Previous financial year
     */
    public function getInterestByPropId($propId)
    {
        return self::select(
            "id",
            "prop_id",
            "fyear",
            "total_interest",
            "due_total_interest",
            "status"
        )
            ->where('status', 1)
            ->where('paid_status', 0)
            ->where('prop_id', $propId)
            ->orderByDesc('fyear')
            ->first();
    }
}
