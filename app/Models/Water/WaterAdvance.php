<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterAdvance extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';
    protected $guarded = [];

    public function store($req)
    {
        WaterAdvance::create($req);
    }
    
    /**
     * | Get Advance respective for consumer id
     * | list all the advance toward consumer
     * | @param consumerId
     * | @var
     * | @return 
     */
    public function getAdvanceByRespectiveId($consumerId, $advanceFor)
    {
        return WaterAdvance::where('related_id', $consumerId)
            ->where('status', 1);
    }

    /**
     * | Save advance details 
     */
    public function saveAdvanceDetails($req, $advanceFor, $docDetails)
    {
        $mWaterAdvance = new WaterAdvance();
        $mWaterAdvance->related_id      = $req->relatedId;
        $mWaterAdvance->reason          = $req->reason;
        $mWaterAdvance->amount          = $req->amount;
        $mWaterAdvance->remarks         = $req->remarks;
        $mWaterAdvance->document        = $docDetails['document'];
        $mWaterAdvance->user_id         = $req->userId;
        $mWaterAdvance->user_type       = $req->userType;
        $mWaterAdvance->role_id         = $req->roleId;
        $mWaterAdvance->advance_for     = $advanceFor;
        $mWaterAdvance->relative_path   = $docDetails['relaivePath'];
        $mWaterAdvance->save();
    }
}
