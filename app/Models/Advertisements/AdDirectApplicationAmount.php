<?php

namespace App\Models\Advertisements;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdDirectApplicationAmount extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = 'pgsql_advertisements';

    /**
     * |
     */
    # update status 
    public function updateStatus($applicationId)
    {
        return self::where('id', $applicationId)
            ->update([
                'paid_status' => 0
            ]);
    }
}
