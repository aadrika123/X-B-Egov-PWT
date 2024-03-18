<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropSafMemoDtl extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Post SAF Memo Dtls
     */
    public function postSafMemoDtls($req)
    {
        $metaReqs = [
            'saf_id' => $req->saf_id,
            'from_qtr' => $req->qtr,
            'from_fyear' => $req->fyear,
            'arv' => $req->arv,
            'quarterly_tax' => $req->amount,
            'user_id' => $req->userId,
            'memo_no' => $req->memo_no,
            'memo_type' => $req->memo_type,
            'holding_no' => $req->holding_no,
            'prop_id' => $req->prop_id ?? null,
            'ward_mstr_id' => $req->ward_id,
            'pt_no' => $req->pt_no ?? null
        ];
        PropSafMemoDtl::create($metaReqs);
    }

    /**
     * | Get memo list by Safid
     */
    public function memoLists($safId)
    {
        return DB::table('prop_saf_memo_dtls as m')
            ->select(
                'm.id',
                'm.saf_id',
                'm.from_fyear',
                'm.alv',
                'm.annual_tax',
                'm.user_id',
                'm.memo_no',
                'm.memo_type',
                'm.holding_no',
                'm.prop_id',
                'm.pt_no',
                DB::raw("(to_char(m.created_at::timestamp,'dd-mm-yyyy HH:MI')) as memo_date"),
                'u.name as generated_by'
            )
            ->where('saf_id', $safId)
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('status', 1)
            ->get();
    }

    /**
     * | Memo Details by memo id
     */
    public function getMemoDtlsByMemoId($memoId)
    {
        $query = "SELECT d.*,
                    TO_CHAR(d.created_at,'DD-MM-YYYY') AS memo_date,
                                pd.holding_tax,
                                pd.water_tax,
                                pd.latrine_tax,
                                pd.education_cess,
                                pd.health_cess,
                                pd.additional_tax AS rwh_penalty,
                                o.owner_name,
                                o.guardian_name,
                                o.relation_type,
                                s.prop_address,
                                u.ward_name AS ward_no,
                                nw.ward_name as new_ward_no
                            
                            FROM prop_saf_memo_dtls d
                            LEFT JOIN prop_safs_demands pd ON pd.fyear=d.from_fyear AND pd.qtr=d.from_qtr AND pd.saf_id=d.saf_id AND pd.status=1
                            LEFT JOIN (SELECT owner_name,guardian_name,saf_id,relation_type FROM prop_active_safs_owners ORDER BY id) AS o ON o.saf_id=d.saf_id
                            JOIN prop_active_safs AS s ON s.id=d.saf_id
                            JOIN ulb_ward_masters AS u ON u.id=s.ward_mstr_id
                            LEFT JOIN ulb_ward_masters AS nw ON nw.id=s.new_ward_mstr_id
                            WHERE d.id=$memoId AND d.status=1
                    LIMIT 1";
        return DB::select($query);
    }

    /**
     * | Memo Details by memo id
     */
    public function getPropMemoDtlsByMemoId($memoId)
    {
        $query = "SELECT d.*,
                    TO_CHAR(d.created_at,'DD-MM-YYYY') AS memo_date,
                                pd.holding_tax,
                                pd.water_tax,
                                pd.latrine_tax,
                                pd.education_cess,
                                pd.health_cess,
                                pd.additional_tax AS rwh_penalty,
                                o.owner_name,
                                o.guardian_name,
                                o.relation_type,
                                s.prop_address,
                                u.ward_name AS ward_no,
                                nw.ward_name as new_ward_no
                            
                            FROM prop_saf_memo_dtls d
                            LEFT JOIN prop_safs_demands pd ON pd.fyear=d.from_fyear AND pd.qtr=d.from_qtr AND pd.saf_id=d.saf_id AND pd.status=1
                            LEFT JOIN (SELECT owner_name,guardian_name,saf_id,relation_type FROM prop_safs_owners ORDER BY id) AS o ON o.saf_id=d.saf_id
                            JOIN prop_safs AS s ON s.id=d.saf_id
                            JOIN ulb_ward_masters AS u ON u.id=s.ward_mstr_id
                            LEFT JOIN ulb_ward_masters AS nw ON nw.id=s.new_ward_mstr_id
                            WHERE d.id=$memoId AND d.status=1
                    LIMIT 1";
        return DB::select($query);
    }
}
