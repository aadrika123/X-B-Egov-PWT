<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class PropSafGeotagUpload extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Get GeoTag Uploaded Images by Saf id
     */
    public function getGeoTags($safId)
    {
        $docUrl = Config::get('module-constants.DOC_URL');
        return DB::table('prop_saf_geotag_uploads as g')
            ->select(
                'g.*',
                'u.user_name as geo_tagged_by',
                'u.mobile as geo_tagged_by_mobile',
                DB::raw("concat('$docUrl/',relative_path,'/',image_path) as image_path"),
            )
            ->join('users as u', 'u.id', '=', 'g.user_id')
            ->where('g.saf_id', $safId)
            ->where('g.status', 1)
            ->get();
    }

    /**
     * | Get Geo Tag Done By Saf id and Direction Type
     */
    public function getGeoTagBySafIdDirectionType($req)
    {
        return PropSafGeotagUpload::where('saf_id', $req->safId)
            ->where('direction_type', $req->directionType)
            ->where('status', 1)
            ->first();
    }
    /**
     * | Get Geo Tag Done By Saf id and Direction Type
     */
    public function getDetails($req)
    {
        return PropSafGeotagUpload::where('saf_id', $req->safId)
            ->where('status', 1);
    }

    /**
     * | Store New Images
     */
    public function store($req)
    {
        PropSafGeotagUpload::create($req);
    }

    /**
     * | Edit Existing Image
     */
    public function edit($geoTags, $req)
    {
        $geoTags->update($req);
    }
}
