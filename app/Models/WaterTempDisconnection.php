<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterTempDisconnection extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $fillable = [
        'consumer_id',
        'demand_from',
        'demand_upto',
        'amount_notice_1',
        'amount_notice_2',
        'amount_notice_3',
        'demand_upto_1',
        'demand_upto_2',

        'current_role',
        'initiator',
        'finisher',
        'last_role_id',
        'workflow_id',
        'notice',
        'notice_no_1',
        'notice_no_2',
        'notice_no_3',
        'notice_2',
        'notice_3',
        'notice_1_generated_at',
        'notice_2_generated_at',
        'notice_3_generated_at',
    ];

    public function getActiveReqById($id)
    {
        return WaterTempDisconnection::where('consumer_id', $id)
            ->where('status', 1);
    }

    public function fullDetails($req)
    {
        return WaterTempDisconnection::where('consumer_id', $req->applicationId)
            ->where('status', 1);
    }
    /**
     * | Update the application Doc Verify status
     * | @param applicationId
     */
    public function updateDeactiveStatus($consumerId)
    {
        WaterTempDisconnection::where('consumer_id', $consumerId)
            ->update([
                'status' => true
            ]);
    }
}
