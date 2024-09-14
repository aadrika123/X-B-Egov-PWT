<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropSaf extends PropParamModel #Model
{
    use HasFactory;

    /**
     * | 
     */
    public function getSafDtlsBySafNo($safNo)
    {
        return DB::table('prop_safs as s')
            ->where('s.saf_no', strtoupper($safNo))
            ->select(
                's.id',
                DB::raw("'approved' as status"),
                's.saf_no',
                's.ward_mstr_id',
                's.new_ward_mstr_id',
                's.elect_consumer_no',
                's.elect_acc_no',
                's.elect_bind_book_no',
                's.elect_cons_category',
                's.prop_address',
                's.corr_address',
                's.prop_pin_code',
                's.corr_pin_code',
                's.assessment_type',
                's.applicant_name',
                's.application_date',
                's.area_of_plot as total_area_in_decimal',
                's.prop_type_mstr_id',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
                'p.property_type',
                'doc_upload_status',
                'payment_status',
                DB::raw(
                    "case when payment_status!=1 then 'Payment Not Done'
                          else role_name end
                          as current_role
                    "
                ),
                'role_name as approvedBy',
                's.user_id',
                's.citizen_id',
                DB::raw(
                    "case when s.user_id is not null then 'TC/TL/JSK' when 
                    s.citizen_id is not null then 'Citizen' end as appliedBy
                "
                ),
            )
            ->leftjoin('wf_roles', 'wf_roles.id', 's.current_role')
            ->join('ulb_ward_masters as u', 's.ward_mstr_id', '=', 'u.id')
            ->join('ref_prop_types as p', 'p.id', '=', 's.prop_type_mstr_id')
            ->leftJoin('ulb_ward_masters as u1', 's.new_ward_mstr_id', '=', 'u1.id')
            ->first();
    }

    /**
     * | Get GB SAf details by saf No
     */
    public function getGbSafDtlsBySafNo($safNo)
    {
        return DB::table('prop_safs as s')
            ->where('s.saf_no', strtoupper($safNo))
            ->select(
                's.id',
                DB::raw("'approved' as status"),
                's.saf_no',
                's.ward_mstr_id',
                's.new_ward_mstr_id',
                's.prop_address',
                's.prop_pin_code',
                's.assessment_type',
                's.applicant_name',
                's.application_date',
                's.area_of_plot as total_area_in_decimal',
                'u.ward_name as old_ward_no',
                'u1.ward_name as new_ward_no',
                'doc_upload_status',
                'payment_status',
                DB::raw(
                    "case when payment_status!=1 then 'Payment Not Done'
                          else role_name end
                          as current_role
                    "
                ),
                'role_name as approvedBy',
                's.user_id',
                's.citizen_id',
                'gb_office_name',
                'building_type',
                DB::raw(
                    "case when s.user_id is not null then 'TC/TL/JSK' when 
                    s.citizen_id is not null then 'Citizen' end as appliedBy
                "
                ),
            )
            ->join('wf_roles', 'wf_roles.id', 's.current_role')
            ->leftjoin('ref_prop_gbpropusagetypes as p', 'p.id', '=', 's.gb_usage_types')
            ->leftjoin('ref_prop_gbbuildingusagetypes as q', 'q.id', '=', 's.gb_prop_usage_types')
            ->join('ulb_ward_masters as u', 's.ward_mstr_id', '=', 'u.id')
            ->leftJoin('ulb_ward_masters as u1', 's.new_ward_mstr_id', '=', 'u1.id')
            ->first();
    }

    /**
     * | Search safs
     */
    // public function searchSafs()
    // {
    //     return PropSaf::select(
    //         'prop_safs.id',"prop_safs.proccess_fee_paid",
    //         DB::raw("'approved' as status"),
    //         'prop_safs.saf_no',
    //         'prop_safs.assessment_type',
    //         DB::raw(
    //             "case when prop_safs.payment_status = 0 then 'Payment Not Done'
    //                   when prop_safs.payment_status = 2 then 'Cheque Payment Verification Pending'
    //                   else role_name end
    //                   as current_role
    //             "
    //         ),
    //         // 'role_name as currentRole',
    //         DB::raw(
    //             "case when prop_safs.parked = true then CONCAT('BTC BY ',role_name)
    //                 else CONCAT('At ',role_name) end
    //             as currentRole
    //             "
    //         ),
    //         'u.ward_name as old_ward_no',
    //         'uu.ward_name as new_ward_no',
    //         'prop_address',
    //         // DB::raw(
    //         //     "case when prop_safs.user_id is not null then 'TC/TL/JSK' when 
    //         //     prop_safs.citizen_id is not null then 'Citizen' end as appliedBy"
    //         // ),
    //         "users.name as appliedBy",
    //         DB::raw("string_agg(so.mobile_no::VARCHAR,',') as mobile_no"),
    //         DB::raw("string_agg(so.owner_name,',') as owner_name"),
    //     )
    //         ->leftjoin('users', 'users.id', 'prop_safs.user_id')
    //         ->leftjoin('wf_roles', 'wf_roles.id', 'prop_safs.current_role')
    //         ->join('ulb_ward_masters as u', 'u.id', 'prop_safs.ward_mstr_id')
    //         ->leftjoin('ulb_ward_masters as uu', 'uu.id', 'prop_safs.new_ward_mstr_id')
    //         ->join('prop_safs_owners as so', function($join){
    //             $join->on('so.saf_id', 'prop_safs.id')
    //             ->where("so.status",1);
    //         })
    //         ->groupBy('users.name');
    // }
    # modified by prity pandey
    public function searchSafs()
    {
        return PropSaf::select(
            'prop_safs.id',
            "prop_safs.proccess_fee_paid",
            DB::raw("'approved' as status"),
            'prop_safs.saf_no',
            'prop_safs.assessment_type',
             'prop_safs.current_role as current_role_id',
             DB::raw("to_char(prop_safs.application_date, 'DD-MM-YYYY') as application_date"),
            DB::raw(
                "case when prop_safs.payment_status = 0 then 'Payment Not Done'
                      when prop_safs.payment_status = 2 then 'Cheque Payment Verification Pending'
                      else role_name end
                      as current_role
                "
            ),
            // 'role_name as currentRole',
            DB::raw(
                "case when prop_safs.parked = true then CONCAT('BTC BY ',role_name)
                    else CONCAT('At ',role_name) end
                as currentRole
                "
            ),
            'u.ward_name as old_ward_no',
            'uu.ward_name as new_ward_no',
            'prop_address',
            // DB::raw(
            //     "case when prop_safs.user_id is not null then 'TC/TL/JSK' when 
            //     prop_safs.citizen_id is not null then 'Citizen' end as appliedBy"
            // ),
            //"users.name as appliedBy",
            DB::raw(
                "case when prop_safs.citizen_id is not null then 'Citizen'
                      else users.name end as appliedBy"
            ),
            DB::raw("string_agg(so.mobile_no::VARCHAR,',') as mobile_no"),
            DB::raw("string_agg(so.owner_name,',') as owner_name"),
        )
            ->leftjoin('users', 'users.id', 'prop_safs.user_id')
            ->leftjoin('wf_roles', 'wf_roles.id', 'prop_safs.current_role')
            ->join('ulb_ward_masters as u', 'u.id', 'prop_safs.ward_mstr_id')
            ->leftjoin('ulb_ward_masters as uu', 'uu.id', 'prop_safs.new_ward_mstr_id')
            ->join('prop_safs_owners as so', function ($join) {
                $join->on('so.saf_id', 'prop_safs.id')
                    ->where("so.status", 1);
            })
            ->groupBy('users.name');
    }

    /**
     * | Search Gb Saf
     */
    public function searchGbSafs()
    {
        return PropSaf::select(
            'prop_safs.id',
            "prop_safs.proccess_fee_paid",
            DB::raw("'approved' as status"),
            'prop_safs.saf_no',
            'prop_safs.assessment_type',
            DB::raw(
                "case when prop_safs.payment_status!=1 then 'Payment Not Done'
                      else role_name end
                      as current_role
                "
            ),
            'role_name as currentRole',
            'ward_name as old_ward_no',
            'prop_address',
            'gbo.officer_name',
            'gbo.mobile_no'
        )
            ->leftjoin('wf_roles', 'wf_roles.id', 'prop_safs.current_role')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_safs.ward_mstr_id')
            ->join('prop_gbofficers as gbo', 'gbo.saf_id', 'prop_safs.id');
    }

    /**
     * | Get Saf Details
     */
    public function getSafDtls()
    {
        return DB::table('prop_safs')
            ->select(
                'prop_safs.*',
                DB::raw(
                    "case when prop_safs.workflow_id=202 then 'Direct Mutation'
                          else assessment_type end
                          as assessment_type"
                ),
                DB::raw("REPLACE(prop_safs.holding_type, '_', ' ') AS holding_type"),
                'w.ward_name as old_ward_no',
                'nw.ward_name as new_ward_no',
                'o.ownership_type',
                'p.property_type',
                'r.road_type as road_type_master',
                'wr.role_name as current_role_name',
                't.transfer_mode',
                'a.apt_code as apartment_code',
                'a.apartment_address',
                'a.no_of_block',
                'a.apartment_name',
                'building_type',
                'prop_usage_type',
                'zone_masters.zone_name as zone',
                'cat.category',
                'cat.description as category_description',
                'bifurcated_from_plot_area'
            )
            ->leftJoin('ulb_ward_masters as w', 'w.id', '=', 'prop_safs.ward_mstr_id')
            ->leftJoin('wf_roles as wr', 'wr.id', '=', 'prop_safs.current_role')
            ->leftJoin('ulb_ward_masters as nw', 'nw.id', '=', 'prop_safs.new_ward_mstr_id')
            ->leftJoin('ref_prop_ownership_types as o', 'o.id', '=', 'prop_safs.ownership_type_mstr_id')
            ->leftJoin('ref_prop_types as p', 'p.id', '=', 'prop_safs.prop_type_mstr_id')
            ->leftJoin('ref_prop_road_types as r', 'r.id', '=', 'prop_safs.road_type_mstr_id')
            ->leftJoin('ref_prop_transfer_modes as t', 't.id', '=', 'prop_safs.transfer_mode_mstr_id')
            ->leftJoin('prop_apartment_dtls as a', 'a.id', '=', 'prop_safs.apartment_details_id')
            ->leftJoin('zone_masters', 'zone_masters.id', 'prop_safs.zone_mstr_id')
            ->leftJoin('ref_prop_gbbuildingusagetypes as gbu', 'gbu.id', 'prop_safs.gb_usage_types')
            ->leftJoin('ref_prop_gbpropusagetypes as gbp', 'gbp.id', 'prop_safs.gb_prop_usage_types')
            ->join('ref_prop_categories as cat', 'cat.id', '=', 'prop_safs.category_id');
    }

    /**
     * | get Safs details from prop id
     */
    public function getSafbyPropId($propId)
    {
        return PropSaf::where('property_id', $propId)
            ->first();
    }

    /**
     * | Count Previous Holdings
     */
    public function countPreviousHoldings($previousHoldingId)
    {
        return PropSaf::where('previous_holding_id', $previousHoldingId)
            ->count();
    }


    /**
     * | Get Property Applications for Payment details Purpose
     */
    public function getBasicDetails($safId)
    {
        return DB::table('prop_safs as p')
            ->select(
                'p.holding_no as application_no',
                'p.prop_address',
                'p.previous_holding_id',
                'p.assessment_type',
                'p.ulb_id',
                'o.owner_name',
                'o.guardian_name',
                'o.mobile_no',
                'u.ward_name as ward_no'
            )
            ->leftJoin('prop_safs_owners as o', 'o.saf_id', '=', 'p.id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'p.ulb_id')
            ->where('p.id', $safId)
            ->orderBy('o.id')
            ->first();
    }

    public function getBasicDetailsV2($safId)
    {
        $select = [
            'p.id as id',
            'p.holding_no as holding_no',
            'p.previous_holding_id',
            'p.prop_address',
            'p.ulb_id',
            'o.owner_name',
            'o.guardian_name',
            'o.mobile_no',
            'u.ward_name as ward_no',

            'p.saf_no as application_no',
            'p.applicant_name as eng_applicant_name',
            'p.applicant_name as applicant_marathi',

            'p.plot_no',
            'p.property_no',
            'p.area_of_plot',

            'z.zone_name',
            'o.owner_name as owner_name',
            'o.owner_name_marathi',
            'o.guardian_name',
            'o.guardian_name_marathi',
            'o.mobile_no',
            'u.ward_name as ward_no'
        ];
        $data =  DB::table('prop_safs as p')
            ->select(
                $select

            )
            // ->leftJoin('prop_safs_owners as o', 'o.saf_id', '=', 'p.id')
            ->leftjoin(DB::raw("(
                select string_agg(mobile_no::text,',') as mobile_no,
                        string_agg(owner_name,',') as owner_name,
                        string_agg(guardian_name,',') as guardian_name,   
                        string_agg(case when owner_name_marathi <>'' then owner_name_marathi else owner_name end ,',') as owner_name_marathi,
                        string_agg(case when guardian_name_marathi <>'' then guardian_name_marathi else guardian_name end ,',') as guardian_name_marathi, 
                        saf_id
                from prop_safs_owners
                where status  =1 AND saf_id = $safId
                group by saf_id
            )as o"), 'o.saf_id', 'p.id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'p.ward_mstr_id')
            ->join('zone_masters as z', 'z.id', '=', 'p.zone_mstr_id')
            ->where('p.id', $safId)
            ->first();
        if (!$data) {
            $data =  DB::table('prop_active_safs as p')
                ->select(
                    $select
                )
                // ->leftJoin('prop_safs_owners as o', 'o.saf_id', '=', 'p.id')
                ->leftjoin(DB::raw("(
                    select string_agg(mobile_no::text,',') as mobile_no,
                            string_agg(owner_name,',') as owner_name,
                            string_agg(guardian_name,',') as guardian_name,   
                            string_agg(case when owner_name_marathi <>'' then owner_name_marathi else owner_name end ,',') as owner_name_marathi,
                            string_agg(case when guardian_name_marathi <>'' then guardian_name_marathi else guardian_name end ,',') as guardian_name_marathi, 
                            saf_id
                    from prop_active_safs_owners
                    where status  =1 AND saf_id = $safId
                    group by saf_id
                )as o"), 'o.saf_id', 'p.id')
                ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'p.ulb_id')
                ->join('zone_masters as z', 'z.id', '=', 'p.zone_mstr_id')
                ->where('p.id', $safId)
                ->first();
        }

        if (!$data) {
            $data =  DB::table('prop_rejected_safs as p')
                ->select(
                    $select
                )
                // ->leftJoin('prop_safs_owners as o', 'o.saf_id', '=', 'p.id')
                ->leftjoin(DB::raw("(
                    select string_agg(mobile_no::text,',') as mobile_no,
                            string_agg(owner_name,',') as owner_name,
                            string_agg(guardian_name,',') as guardian_name,   
                            string_agg(case when owner_name_marathi <>'' then owner_name_marathi else owner_name end ,',') as owner_name_marathi,
                            string_agg(case when guardian_name_marathi <>'' then guardian_name_marathi else guardian_name end ,',') as guardian_name_marathi, 
                            saf_id
                    from prop_rejected_safs_owners
                    where status  =1 AND saf_id = $safId
                    group by saf_id
                )as o"), 'o.saf_id', 'p.id')
                ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'p.ulb_id')
                ->join('zone_masters as z', 'z.id', '=', 'p.zone_mstr_id')
                ->where('p.id', $safId)
                ->first();
        }
        return $data;
    }

    public function safDtl($propId)
    {
        $prop = (string)$propId;
        $data =  PropSaf::select(
            'prop_safs.id',
            'prop_safs.saf_no',
            'prop_safs.assessment_type',
            'prop_safs_owners.owner_name',
            'prop_safs_owners.guardian_name',
            'prop_safs.vacant_land_type'
        )
            ->leftJoin('prop_safs_owners', 'prop_safs_owners.saf_id', '=', 'prop_safs.id')
            ->where('prop_safs.previous_holding_id', $prop)
            ->get();
        return $data;
    }
}
