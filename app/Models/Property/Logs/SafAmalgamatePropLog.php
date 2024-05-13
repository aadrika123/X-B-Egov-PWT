<?php

namespace App\Models\Property\Logs;

use App\Models\Property\PropParamModel;
use App\Models\Property\PropProperty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafAmalgamatePropLog extends PropParamModel
{
    use HasFactory;
    protected $guarded = [];

    public function store($request)
    {
        foreach($request->amalgamatHoldingId as $key =>$val)
        {
            if($val ==$request->previousHoldingId)
            {
                continue;
            }
            $propPerty = PropProperty::find($val);
            $owners    = $propPerty->Owneres()->get();
            $floors    = $propPerty->floars()->get();
            $demands    = $propPerty->getAllDemands()->get();
            $transactions = $propPerty->getAllTransection()->get();
            $array = [
                "saf_id" =>$request->safId,
                "property_id"=>$propPerty->id,
                "holding_no"=>$propPerty->holding_no,
                "is_master"=>false,
                "property_json"=>json_encode($propPerty->toArray(),JSON_UNESCAPED_UNICODE),
                "floors_ids"=>json_encode($floors->pluck("id")),
                "floors_json"=>json_encode($floors->toArray(),JSON_UNESCAPED_UNICODE),
                "owners_ids"=>json_encode($owners->pluck("id")),
                "owners_json"=>json_encode($owners->toArray(),JSON_UNESCAPED_UNICODE),
                "demand_ids"=>json_encode($demands->pluck("id")),
                "demand_json"=>json_encode($demands->toArray(),JSON_UNESCAPED_UNICODE),
                "tran_ids"=>json_encode($transactions->pluck("id")),
                "tran_json"=>json_encode($transactions->toArray(),JSON_UNESCAPED_UNICODE),
            ];
            self::create($array);
        }
        $propPerty = PropProperty::find($request->previousHoldingId);
        $owners    = $propPerty->Owneres()->get();
        $floors    = $propPerty->floars()->get();
        $demands    = $propPerty->getAllDemands()->get();
        $transactions = $propPerty->getAllTransection()->get();
        $array = [
            "saf_id" =>$request->safId,
            "property_id"=>$propPerty->id,
            "holding_no"=>$propPerty->holding_no,
            "is_master"=>true,
            "property_json"=>json_encode($propPerty->toArray(),JSON_UNESCAPED_UNICODE),
            "floors_ids"=>json_encode($floors->pluck("id")),
            "floors_json"=>json_encode($floors->toArray(),JSON_UNESCAPED_UNICODE),
            "owners_ids"=>json_encode($owners->pluck("id")),
            "owners_json"=>json_encode($owners->toArray(),JSON_UNESCAPED_UNICODE),
            "demand_ids"=>json_encode($demands->pluck("id")),
            "demand_json"=>json_encode($demands->toArray(),JSON_UNESCAPED_UNICODE),
            "tran_ids"=>json_encode($transactions->pluck("id")),
            "tran_json"=>json_encode($transactions->toArray(),JSON_UNESCAPED_UNICODE),
        ];
        self::create($array);
    }

    public function getProperty()
    {
        return $this->belongsTo(PropProperty::class,"id","property_id")->first();
    }
}
