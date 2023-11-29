<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PropProperty extends Model
{
    use HasFactory;
    protected $guarded = [];

    // Get Property Of the Citizen
    public function getUserProperties($userId)
    {
        return PropProperty::where('user_id', $userId)
            ->get();
    }

    // get Prpoperty id
    public function getPropertyId($holdingNo)
    {
        return PropProperty::where('holding_no', $holdingNo)
            ->orWhere('new_holding_no', $holdingNo)
            ->select('id')
            ->first();
    }

    // Get Property by propID
    public function getPropById($id)
    {
        return PropProperty::where('id', $id)
            ->where('status', 1)
            ->first();
    }

    // Get SAf id by Prop Id
    public function getSafByPropId($propId)
    {
        return PropProperty::select('saf_id')
            ->where('id', $propId)
            ->first();
    }

    // Get SAf Id by Holding No
    public function getSafIdByHoldingNo($holdingNo)
    {
        return PropProperty::select('saf_id', 'id')
            ->where('holding_no', $holdingNo)
            ->orWhere('new_holding_no', $holdingNo)
            ->first();
    }

    /**
     * | Get Property Details
     */
    public function getPropDtls()
    {
        return DB::table('prop_properties')
            ->select(
                'prop_properties.*',
                DB::raw("REPLACE(prop_properties.holding_type, '_', ' ') AS holding_type"),
                DB::raw(" CASE WHEN TRIM(applicant_name) <>'' THEN applicant_name ELSE applicant_marathi END AS ref_applicant_name"),
                'applicant_marathi as applicant_name',
                'prop_properties.status as active_status',
                'prop_properties.assessment_type as assessment',
                'w.ward_name as ward_no',
                'o.ownership_type',
                'ref_prop_types.property_type',
                'r.road_type',
                'a.apartment_name',
                'a.apt_code as apartment_code',
                'z.zone_name',
                'cat.category AS categoryType',
            )
            ->join('ulb_ward_masters as w', 'w.id', '=', 'prop_properties.ward_mstr_id')
            ->leftJoin('ref_prop_ownership_types as o', 'o.id', '=', 'prop_properties.ownership_type_mstr_id')
            ->leftJoin('ref_prop_types', 'ref_prop_types.id', '=', 'prop_properties.prop_type_mstr_id')
            ->leftJoin('ref_prop_road_types as r', 'r.id', '=', 'prop_properties.road_type_mstr_id')
            ->leftJoin('prop_apartment_dtls as a', 'a.id', '=', 'prop_properties.apartment_details_id')
            ->leftJoin('zone_masters as z', 'z.id', '=', 'prop_properties.zone_mstr_id')
            ->leftjoin('ref_prop_categories as cat', 'cat.id', '=', 'prop_properties.category_id');
    }

    /**
     * | Get Property Basic Dtls
     */
    public function getPropBasicDtls($propId)
    {
        return $this->getPropDtls()
            ->where('prop_properties.id', $propId)
            ->first();
    }

    /**
     * | Get Property Full Details
     * | Used for Calculation Parameter
     * | @param propId Property Id
     */
    public function getPropFullDtls($propId)
    {
        $mPropOwners = new PropOwner();
        $mPropFloors = new PropFloor();
        $details = array();
        $details = PropProperty::find($propId);
        $owners = $mPropOwners->getOwnersByPropId($propId);
        $details['owners'] = $owners;
        $floors = $mPropFloors->getFloorsByPropId($propId);
        $details['floors'] = $floors;
        return $details;
    }

    /**
     * | Get Property Details
     */
    public function getPropByHoldingNo($holdingNo)
    {
        return PropProperty::select(
            'prop_properties.id',
            'prop_properties.holding_no',
            'ward_name',
            'prop_address'
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_properties.ward_mstr_id')
            ->where('prop_properties.holding_no', 'LIKE', '%' . $holdingNo . '%')
            ->orWhere('prop_properties.new_holding_no', 'LIKE', '%' . $holdingNo . '%')
            ->get();
    }

    /**
     * | Get Proprty Details By Holding No
     */
    public function getPropByHolding($holdingNo, $ulbId)
    {
        $oldHolding = PropProperty::select(
            'prop_properties.id',
            'prop_properties.holding_no',
            'prop_properties.new_holding_no',
            'prop_properties.ward_mstr_id',
            'prop_properties.new_ward_mstr_id',
            'prop_properties.elect_consumer_no',
            'prop_properties.elect_acc_no',
            'prop_properties.elect_bind_book_no',
            'prop_properties.elect_cons_category',
            'prop_properties.prop_pin_code',
            'prop_properties.corr_pin_code',
            'prop_properties.prop_address',
            'prop_properties.corr_address',
            'prop_properties.apartment_details_id',
            'prop_properties.area_of_plot as total_area_in_desimal',
            'prop_properties.prop_type_mstr_id',
            'ulb_ward_masters.ward_name as old_ward_no',
            'u.ward_name as new_ward_no',
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'prop_properties.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'prop_properties.new_ward_mstr_id')
            ->where('prop_properties.holding_no', $holdingNo)
            ->where('prop_properties.ulb_id', $ulbId)
            ->where('prop_properties.status', 1)
            ->first();

        if ($oldHolding) {
            return $oldHolding;
        }

        $newHolding = PropProperty::select(
            'prop_properties.id',
            'prop_properties.holding_no',
            'prop_properties.new_holding_no',
            'prop_properties.ward_mstr_id',
            'prop_properties.new_ward_mstr_id',
            'prop_properties.elect_consumer_no',
            'prop_properties.elect_acc_no',
            'prop_properties.elect_bind_book_no',
            'prop_properties.elect_cons_category',
            'prop_properties.prop_pin_code',
            'prop_properties.corr_pin_code',
            'prop_properties.prop_address',
            'prop_properties.corr_address',
            'prop_properties.apartment_details_id',
            'prop_properties.area_of_plot as total_area_in_desimal',
            'prop_properties.prop_type_mstr_id',
            'ulb_ward_masters.ward_name as old_ward_no',
            'u.ward_name as new_ward_no',
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'prop_properties.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'prop_properties.new_ward_mstr_id')
            ->where('prop_properties.new_holding_no', $holdingNo)
            ->where('prop_properties.ulb_id', $ulbId)
            ->first();
        return $newHolding;
    }

    /**
     * | get property details by userId and ulbId
     */
    public function getpropByUserUlb($request)
    {
        return PropProperty::select(
            'new_holding_no',
            'holding_no'
        )
            ->where('user_id', auth()->user()->id)
            ->where('ulb_id', $request->ulbId)
            ->get();
    }

    /**
     * | Search holding
     */
    public function searchHolding($ulbId)
    {
        return PropProperty::select(
            'prop_properties.id',
            'prop_properties.new_ward_mstr_id AS wardId',
            'prop_properties.prop_address AS address',
            'ref_prop_types.property_type AS propertyType',
            'prop_properties.new_holding_no as holding_no',
            DB::raw("string_agg(prop_owners.owner_name,',') as ownerName"),
            DB::raw("string_agg(prop_owners.mobile_no::VARCHAR,',') as mobileNo"),
            'prop_properties.holding_no as holdingNo'
        )
            ->join('prop_owners', 'prop_owners.saf_id', '=', 'prop_properties.saf_id')
            ->join('ref_prop_types', 'ref_prop_types.id', '=', 'prop_properties.prop_type_mstr_id')
            ->where('prop_properties.status', 1)
            ->where('ulb_id', $ulbId)
            ->groupBy('prop_properties.id', 'ref_prop_types.property_type');
    }

    /**
     * | Search prop Details by Cluster Id
     */
    public function searchPropByCluster($clusterId)
    {
        return  PropProperty::leftjoin('prop_owners', 'prop_owners.property_id', '=', 'prop_properties.id')
            ->join('ref_prop_types', 'ref_prop_types.id', '=', 'prop_properties.prop_type_mstr_id')
            ->select(
                'prop_properties.id',
                'prop_properties.new_ward_mstr_id AS new_ward_id',
                DB::raw("string_agg(prop_owners.owner_name,',') as ownerName"),
                DB::raw("string_agg(prop_owners.mobile_no::VARCHAR,',') as mobileNo"),
                'prop_properties.prop_address AS address',
                'ref_prop_types.property_type AS propertyType',
                'prop_properties.cluster_id',
                'prop_properties.holding_no as holdingNo',
                'prop_properties.ulb_id',
                'prop_properties.ward_mstr_id as ward_id'
            )
            ->where('prop_properties.cluster_id', $clusterId)
            ->where('prop_properties.status', 1)
            ->where('ref_prop_types.status', 1)
            ->groupBy('prop_properties.id', 'ref_prop_types.property_type')
            ->get();
    }

    /**
     * | Collective holding search
     */
    public function searchCollectiveHolding($holdingArray)
    {
        return PropProperty::whereIn('new_holding_no', $holdingArray)
            ->where('status', 1)
            ->get();
    }

    /**
     * | Get Property id by saf id
     * | Function Used On Approval Rejection Saf
     */
    public function getPropIdBySafId($safId)
    {
        return PropProperty::select('id')
            ->where('saf_id', $safId)
            ->firstOrFail();
    }

    /**
     * | Get Property By Saf id
     */
    public function readPropBySafId($safId)
    {
        return PropProperty::where('saf_id', $safId)
            ->first();
    }

    /**
     * | Replicate Saf 
     */
    public function replicateVerifiedSaf($propId, $fieldVerifiedSaf)
    {
        $property = PropProperty::find($propId);
        $reqs = [
            'prop_type_mstr_id' => $fieldVerifiedSaf->prop_type_id,
            'road_type_mstr_id' => $fieldVerifiedSaf->road_type_id,
            'area_of_plot' => $fieldVerifiedSaf->area_of_plot,
            'ward_mstr_id' => $fieldVerifiedSaf->ward_id,
            'is_mobile_tower' => $fieldVerifiedSaf->has_mobile_tower,
            'tower_area' => $fieldVerifiedSaf->tower_area,
            'tower_installation_date' => $fieldVerifiedSaf->tower_installation_date,
            'is_hoarding_board' => $fieldVerifiedSaf->has_hoarding,
            'hoarding_area' => $fieldVerifiedSaf->hoarding_area,
            'hoarding_installation_date' => $fieldVerifiedSaf->hoarding_installation_date,
            'is_petrol_pump' => $fieldVerifiedSaf->is_petrol_pump,
            'under_ground_area' => $fieldVerifiedSaf->underground_area,
            'petrol_pump_completion_date' => $fieldVerifiedSaf->petrol_pump_completion_date,
            'is_water_harvesting' => $fieldVerifiedSaf->has_water_harvesting,
            'new_ward_mstr_id' => $fieldVerifiedSaf->new_ward_id,
            'ulb_id' => $fieldVerifiedSaf->ulb_id,
            'road_width' => $fieldVerifiedSaf->road_width,
            'gb_usage_types' => $fieldVerifiedSaf->gb_usage_types,
            'gb_prop_usage_types' => $fieldVerifiedSaf->gb_prop_usage_types,
            'rwh_date_from' => $fieldVerifiedSaf->rwh_date_from,
        ];
        $property->update($reqs);
    }


    /**
     * | Request made to save or replicate property
     */
    public function reqProp($req)
    {
        $reqs = [
            'ulb_id' => $req->ulb_id,
            'cluster_id' => $req->cluster_id,
            'saf_id' => $req->id,
            'applicant_name' => $req->applicant_name,
            'application_date' => $req->application_date,
            'ward_mstr_id' => $req->ward_mstr_id,
            'ownership_type_mstr_id' => $req->ownership_type_mstr_id,
            'prop_type_mstr_id' => $req->prop_type_mstr_id,
            'appartment_name' => $req->appartment_name,
            'no_electric_connection' => $req->no_electric_connection,
            'elect_consumer_no' => $req->elect_consumer_no,
            'elect_acc_no' => $req->elect_acc_no,
            'elect_bind_book_no' => $req->elect_bind_book_no,
            'elect_cons_category' => $req->elect_cons_category,
            'building_plan_approval_no' => $req->building_plan_approval_no,
            'building_plan_approval_date' => $req->building_plan_approval_date,
            'water_conn_no' => $req->water_conn_no,
            'water_conn_date' => $req->water_conn_date,
            'khata_no' => $req->khata_no,
            'plot_no' => $req->plot_no,
            'village_mauja_name' => $req->village_mauja_name,
            'road_type_mstr_id' => $req->road_type_mstr_id,
            'area_of_plot' => $req->area_of_plot,
            'prop_address' => $req->prop_address,
            'prop_city' => $req->prop_city,
            'prop_dist' => $req->prop_dist,
            'prop_pin_code' => $req->prop_pin_code,
            'prop_state' => $req->prop_state,
            'corr_address' => $req->corr_address,
            'corr_city' => $req->corr_city,
            'corr_dist' => $req->corr_dist,
            'corr_pin_code' => $req->corr_pin_code,
            'corr_state' => $req->corr_state,
            'is_mobile_tower' => $req->is_mobile_tower,
            'tower_area' => $req->tower_area,
            'tower_installation_date' => $req->tower_installation_date,
            'is_hoarding_board' => $req->is_hoarding_board,
            'hoarding_area' => $req->hoarding_area,
            'hoarding_installation_date' => $req->hoarding_installation_date,
            'is_petrol_pump' => $req->is_petrol_pump,
            'under_ground_area' => $req->under_ground_area,
            'petrol_pump_completion_date' => $req->petrol_pump_completion_date,
            'is_water_harvesting' => $req->is_water_harvesting,
            'land_occupation_date' => $req->land_occupation_date,
            'new_ward_mstr_id' => $req->new_ward_mstr_id,
            'entry_type' => $req->entry_type,
            'zone_mstr_id' => $req->zone_mstr_id,
            'flat_registry_date' => $req->flat_registry_date,
            'assessment_type' => $req->assessment_type,
            'holding_type' => $req->holding_type,
            'is_old' => $req->is_old,
            'apartment_details_id' => $req->apartment_details_id,
            'ip_address' => $req->ip_address,
            'user_id' => $req->user_id,
            'road_width' => $req->road_width,
            'old_prop_id' => $req->old_prop_id,
            'citizen_id' => $req->citizen_id,
            'saf_no' => $req->saf_no,
            'pt_no' => $req->pt_no,
            'building_name' => $req->building_name,
            'street_name' => $req->street_name,
            'location' => $req->location,
            'landmark' => $req->landmark,
            'is_gb_saf' => $req->is_gb_saf,
            'gb_office_name' => $req->gb_office_name,
            'gb_usage_types' => $req->gb_usage_types,
            'gb_prop_usage_types' => $req->gb_prop_usage_types,
            'is_trust' => $req->is_trust,
            'trust_type' => $req->trust_type,
            'is_trust_verified' => $req->is_trust_verified,
            'rwh_date_from' => $req->rwh_date_from
        ];
        return $reqs;
    }

    /**
     * | Edit Property By Saf
     * | Used To Edit Prop Dtls While Reassessment and Mutation Case
     * | Functions Used replicateSaf()
     */
    public function editPropBySaf($propId, $safDtls)
    {
        $property = PropProperty::findOrFail($propId);
        $reqs = $this->reqProp($safDtls);
        $property->update($reqs);
    }

    /**
     * | verify holding No
     */
    public function verifyHolding($req)
    {
        return PropProperty::select('*')
            ->where('holding_no', $req->holdingNo)
            ->orWhere('new_holding_no', $req->holdingNo)
            ->orWhere('pt_no', $req->holdingNo)
            ->where('ulb_id', $req->ulbId)
            ->first();
    }

    /**
     * | Get Comparative Demand Details
     */
    public function getComparativeBasicDtls($propId)
    {
        return DB::table('prop_properties as p')
            ->select(
                'p.holding_no',
                'p.new_holding_no',
                'p.prop_address',
                'p.road_width',
                'p.ulb_id',
                'p.prop_type_mstr_id',
                'p.is_mobile_tower',
                'p.tower_area',
                'p.tower_installation_date',
                'p.is_hoarding_board',
                'p.hoarding_area',
                'p.hoarding_installation_date',
                'p.is_petrol_pump',
                'p.under_ground_area',
                'p.petrol_pump_completion_date',
                'w.ward_name as old_ward_no',
                'nw.ward_name as new_ward_no',
                DB::raw("(SELECT owner_name FROM prop_owners WHERE property_id=$propId order by id LIMIT 1)"),
                DB::raw("(SELECT guardian_name FROM prop_owners WHERE property_id=$propId order by id LIMIT 1)"),
                'f.id as floor_id',
                'f.builtup_area',
                'f.floor_mstr_id',
                'f.usage_type_mstr_id',
                'f.const_type_mstr_id',
                'f.occupancy_type_mstr_id',
                'f.date_from',
                'f.date_upto',
                'f.carpet_area',
            )
            ->join('ulb_ward_masters as w', 'w.id', '=', 'p.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as nw', 'nw.id', '=', 'p.new_ward_mstr_id')
            ->leftJoin('prop_floors as f', 'f.property_id', '=', 'p.id')
            ->where('p.id', $propId)
            ->where('p.status', 1)
            ->where('f.status', 1)
            ->get();
    }

    /**
     * | Get citizen holdings
     */
    public function getCitizenHoldings($citizenId, $ulbId)
    {
        return PropProperty::select('id', 'new_holding_no', 'citizen_id')
            ->where('ulb_id', $ulbId)
            ->where('citizen_id', $citizenId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * | Get citizen Ptn
     */
    public function getCitizenPtn($citizenId, $ulbId)
    {
        return PropProperty::select('id', 'pt_no', 'citizen_id')
            ->where('ulb_id', $ulbId)
            ->where('citizen_id', $citizenId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * | Get Properties By Cluster Id
     */
    public function getPropsByClusterId($clusterId)
    {
        return PropProperty::where('cluster_id', $clusterId)
            ->get();
    }

    /**
     * | Property Basic Edit
     */
    public function editProp($propId, $propDtl)
    {
        $property = PropProperty::find($propId);
        $reqs = [
            "new_ward_mstr_id" => $propDtl->new_ward_mstr_id,
            "khata_no" => $propDtl->khata_no,
            "plot_no" => $propDtl->plot_no,
            "village_mauja_name" => $propDtl->village_mauja_name,
            "prop_pin_code" => $propDtl->prop_pin_code,
            "building_name" => $propDtl->building_name,
            "street_name" => $propDtl->street_name,
            "location" => $propDtl->location,
            "landmark" => $propDtl->landmark,
            "prop_address" => $propDtl->prop_address,
            "corr_pin_code" => $propDtl->corr_pin_code,
            "corr_address" => $propDtl->corr_address
        ];
        $property->update($reqs);
    }

    /**
     * | Save Cluster in property
     */
    public function saveClusterInProperty($holdingList, $clusterId)
    {
        PropProperty::whereIn('new_holding_no', $holdingList)
            ->update([
                'cluster_id' => $clusterId
            ]);
    }

    /**
     * | Independent Edit
     */
    public function edit($propId, $req)
    {
        $property = PropProperty::findOrFail($propId);
        return $property->update($req);
    }

    /**
     * | Search Property
               select  
                        p.id,
                        COALESCE(d.fyear,'2023-2024') AS current_fyear,
                        COALESCE(d.paid_status,1) AS current_paid_status,
                        string_agg(pd.fyear::text,',') AS previous_fyears,
                        string_to_array(string_agg(pd.paid_status::TEXT,','),',') AS previous_paid_status,
                        '0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')) AS previous_unpaid_status,
                        
                        CASE 
                            WHEN 
                                    -- Current Year paid and previous year paid 
                                        COALESCE(d.paid_status,1)=1
                                    AND ('0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')))=FALSE 
                                    AND p.balance<1
                                        THEN 1 --'all_paid'
                                        -- Current Year unpaid but arrear paid
--                                 WHEN 
--                                         COALESCE(d.paid_status,1)=0 
--                                     AND ('0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')))=FALSE 
--                                     AND p.balance<1
--                                         THEN 2 -- 'arrear_paid_but_current_due'
                                        
--                                     WHEN 
--                                         -- Current Year unpaid and arrear unpaid
--                                         COALESCE(d.paid_status,1)=0 
--                                     AND ('0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')))=TRUE 
--                                     AND balance>0
--                                         THEN 3 -- 'arrear_and_current_due'
                                        
--                                 WHEN 
--                                         -- Current Year unpaid and arrear unpaid
--                                         COALESCE(d.paid_status,1)=0 
--                                     AND ('0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')))=FALSE 
--                                     AND balance>0
--                                         THEN 3 -- 'arrear_and_current_due'
                                        
--                                 WHEN 
--                                         -- arrear_overdue
--                                         COALESCE(d.paid_status,1)=1
--                                     AND ('0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')))=TRUE 
--                                     OR balance>0
--                                         THEN 4 -- 'arrear_overdue'

--                                 WHEN 
--                                 -- Current Payment done arrear not available
--                                 COALESCE(d.paid_status,1)=1
--                                 AND string_to_array(string_agg(pd.paid_status::TEXT,','),',') IS NULL
--                                 THEN 1 -- 'current_payment_done'
                                        
                            
                                ELSE 
                                    -- only arrear overdue
                                    2 --'arrear_overdue'
                                        
                        END AS paid_status
                        
                        FROM prop_properties as p
                        
                        LEFT JOIN (
                                SELECT 
                                paid_status,
                                fyear,
                                property_id,
                                status
                                FROM prop_demands
                                WHERE fyear='2023-2024' AND status=1		
                        ) AS d ON d.property_id=p.id
                        
                        LEFT JOIN (
                                SELECT 
                                paid_status,
                                fyear,
                                property_id,
                                status
                                FROM prop_demands
                                WHERE fyear<'2023-2024' AND status=1		
                        ) AS pd ON pd.property_id=p.id
                        
                        
                        WHERE p.id=39681
                        GROUP BY p.id,d.fyear,d.paid_status
                        --LIMIT 100
     * 
     */
    public function searchProperty($ulbId)
    {
        return PropProperty::select(
            'prop_properties.id',
            'prop_properties.ulb_id',
            'prop_properties.holding_no',
            'zone_name',
            'latitude',
            'longitude',
            'prop_properties.new_holding_no',
            'prop_properties.pt_no',
            'prop_properties.khata_no',
            'prop_properties.plot_no',
            'prop_properties.property_no',
            'ward_name',
            'prop_address',
            'prop_properties.status as active_status',
            'o.mobile_no',
            'o.owner_name',
            DB::raw("CASE WHEN TRIM(o.owner_name)<>'' THEN o.owner_name ELSE o.owner_name_marathi END AS owner_name"),
            DB::raw("CASE 
                        WHEN 
                                -- Current Year paid and previous year paid 
                                COALESCE(d.paid_status,1)=1
                                AND ('0' = ANY(string_to_array(string_agg(pd.paid_status::TEXT,','),',')))=FALSE 
                                AND prop_properties.balance<1
                                THEN 1 --'all_paid'
                                
                        ELSE 
                            -- Payment Pending
                            2 --'Pending'
                                
                    END AS paid_status")
        )
            ->join('zone_masters', 'zone_masters.id', 'prop_properties.zone_mstr_id')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_properties.ward_mstr_id')
            ->leftjoin(DB::raw("(select latitude, longitude,  prop_saf_geotag_uploads.saf_id
                                from prop_saf_geotag_uploads 
                                JOIN prop_properties ON prop_properties.saf_id = prop_saf_geotag_uploads.saf_id 
                                where direction_type ILIKE('%front%')                               
                                GROUP BY prop_saf_geotag_uploads.saf_id,latitude, longitude
                           ) as geotag"), function ($join) {
                $join->on("geotag.saf_id", "=", "prop_properties.saf_id");
            })
            // ->leftjoin('prop_owners as o', 'o.property_id', 'prop_properties.id')
            ->leftjoin(DB::raw("(
                select string_agg(mobile_no::text,',') as mobile_no,
                        string_agg(owner_name,',') as owner_name,
                        string_agg(case when owner_name_marathi <>'' then owner_name_marathi else owner_name end ,',') as owner_name_marathi,
                        property_id
                from prop_owners
                where status  =1 
                group by property_id
            )as o"), 'o.property_id', 'prop_properties.id')
            ->leftJoin(DB::raw(
                "(SELECT 
                    paid_status,
                    fyear,
                    property_id,
                    status
                    FROM prop_demands
                WHERE fyear='2023-2024' AND status=1) as d"
            ), function ($join) {
                $join->on("d.property_id", "=", "prop_properties.id");
            })
            ->leftJoin(DB::raw(
                "(SELECT 
                    paid_status,
                    fyear,
                    property_id,
                    status
                    FROM prop_demands
                WHERE fyear<'2023-2024' AND status=1) as pd"
            ), function ($join) {
                $join->on("pd.property_id", "=", "prop_properties.id");
            });
    }

    /**
     * | Property Basic Edit the water connection
     */
    public function updateWaterConnection($propIds, $consumerNo)
    {
        $property = PropProperty::whereIn('id', $propIds);
        $reqs = [
            "water_conn_no" => $consumerNo,
            "water_conn_date" => Carbon::now(),
        ];
        $property->update($reqs);
    }

    /**
     * | deactivate holding by ids
     */
    public function deactivateHoldingByIds($propertyIds)
    {
        PropProperty::whereIn('id', $propertyIds)
            ->update([
                'status' => 0
            ]);
    }

    /**
     * | Get property details under the respective appartment id
     * | @param apartmentId
     */
    public function getPropByApartmentId($apartmentId)
    {
        return PropProperty::select(
            'prop_properties.*',
            'ulb_ward_masters.ward_name AS old_ward_no',
            'u.ward_name AS new_ward_no'
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'prop_properties.ward_mstr_id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'prop_properties.new_ward_mstr_id')
            ->where('prop_properties.apartment_details_id', $apartmentId)
            ->where('prop_properties.status', 1)
            ->orderByDesc('id');
    }

    /**
     * | Get property list by apartmentid
     * | @param apartmentId
     */
    public function getPropertyByApartmentId($apartmentId)
    {
        return PropProperty::where('prop_properties.apartment_details_id', $apartmentId)
            ->where('prop_properties.status', 1)
            ->orderByDesc('id');
    }



    /**
     * | Search with holding No for first owner
     */
    public function searchHoldingNo($ulbId)
    {
        return PropProperty::select(
            'prop_properties.id',
            'ulb_name as ulb',
            'prop_properties.holding_no',
            'prop_properties.new_holding_no',
            'prop_properties.pt_no',
            'ward_name',
            'prop_address',
            'prop_properties.status',
            // 'prop_owners.mobile_no',
            // 'prop_owners.owner_name'
        )
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_properties.ward_mstr_id')
            ->join('ulb_masters', 'ulb_masters.id', 'prop_properties.ulb_id')
            // ->join('prop_owners', 'prop_owners.property_id', 'prop_properties.id')
            ->where('prop_properties.ulb_id', $ulbId)
            ->where('prop_properties.status', 1);
    }
    /**
     * | Get Property id by Ptn or HoldingNo
     * | @param reference Application No
     */
    public function getPropByPtnOrHolding($refrenceNo)
    {
        return PropProperty::select('id')
            ->where('holding_no', $refrenceNo)
            ->orWhere('new_holding_no', $refrenceNo)
            ->orWhere('pt_no', $refrenceNo)
            ->firstOrFail();
    }


    /**
     * | Get details according to ward for heat map
     */
    public function getPropLatlong($wardId)
    {
        return PropProperty::select(
            'prop_properties.id as property_id',
            'prop_saf_geotag_uploads.id as geo_id',
            'prop_properties.holding_no',
            'prop_properties.new_holding_no',
            'prop_properties.pt_no',
            'prop_properties.prop_address',
            'prop_saf_geotag_uploads.latitude',
            'prop_owners.mobile_no',
            'prop_saf_geotag_uploads.longitude',
            'prop_saf_geotag_uploads.created_at',
            DB::raw("concat(relative_path,'/',image_path) as doc_path"),
        )
            ->join('prop_saf_geotag_uploads', 'prop_saf_geotag_uploads.saf_id', '=', 'prop_properties.saf_id')
            ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_properties.id')
            ->where('prop_properties.ward_mstr_id', $wardId)
            ->where('prop_properties.holding_no', '!=', null)
            ->orderByDesc('prop_properties.id')
            ->skip(0)
            ->take(100)
            ->get();
    }

    /**
     * | get New Holding
     */
    public function getNewholding($propertyId)
    {
        return PropProperty::select('id', 'new_holding_no', 'citizen_id')
            ->whereIn('id', $propertyId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * | get New Holding
     */
    public function getPtn($propertyId)
    {
        return PropProperty::select('id', 'pt_no', 'citizen_id')
            ->whereIn('id', $propertyId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * | Get Property Applications for Payment details Purpose
     */
    public function getBasicDetails($propId)
    {
        return DB::table('prop_properties as p')
            ->select(
                'p.holding_no as application_no',
                'p.applicant_name as eng_applicant_name',
                'p.applicant_marathi as applicant_name ',
                'p.applicant_marathi',
                'p.prop_address',
                'p.ulb_id',
                'p.property_no',
                'z.zone_name',
                'o.owner_name as eng_owner_name',
                'o.owner_name_marathi as owner_name',
                'o.owner_name_marathi',
                'o.guardian_name',
                'o.guardian_name_marathi',
                'o.mobile_no',
                'u.ward_name as ward_no'
            )
            ->leftJoin('prop_owners as o', 'o.property_id', '=', 'p.id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'p.ward_mstr_id')
            ->join('zone_masters as z', 'z.id', '=', 'p.zone_mstr_id')
            ->where('p.id', $propId)
            ->orderBy('o.id')
            ->first();
    }

    public function getBasicDetailsV2($propId)
    {
        return DB::table('prop_properties as p')
            ->select(
                'p.holding_no as application_no',
                'p.applicant_name as eng_applicant_name',
                'p.applicant_marathi as applicant_name ',
                'p.applicant_marathi',
                'p.prop_address',
                'p.ulb_id',
                'p.property_no',
                'z.zone_name',
                'o.owner_name as eng_owner_name',
                'o.owner_name_marathi as owner_name',
                'o.owner_name_marathi',
                'o.guardian_name',
                'o.guardian_name_marathi',
                'o.mobile_no',
                'u.ward_name as ward_no'
            )
            // ->leftJoin('prop_owners as o', 'o.property_id', '=', 'p.id')
            ->leftjoin(DB::raw("(
                select string_agg(mobile_no::text,',') as mobile_no,
                        string_agg(owner_name,',') as owner_name,
                        string_agg(guardian_name,',') as guardian_name,   
                        string_agg(case when owner_name_marathi <>'' then owner_name_marathi else owner_name end ,',') as owner_name_marathi,
                        string_agg(case when guardian_name_marathi <>'' then guardian_name_marathi else guardian_name end ,',') as guardian_name_marathi, 
                        property_id
                from prop_owners
                where status  =1 AND property_id = $propId
                group by property_id
            )as o"), 'o.property_id', 'p.id')
            ->leftJoin('ulb_ward_masters as u', 'u.id', '=', 'p.ward_mstr_id')
            ->join('zone_masters as z', 'z.id', '=', 'p.zone_mstr_id')
            ->where('p.id', $propId)
            ->first();
    }

    // get Prpoperty 
    public function getPropert($holdingNo)
    {
        $propDetails = PropProperty::select(
            'ulb_ward_masters.*',
            'prop_properties.*'
        )
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_properties.ward_mstr_id')
            ->where('holding_no', $holdingNo)
            ->where('prop_properties.status', 1)
            ->first();
        if (!$propDetails) {
            $propDetails = PropProperty::where('new_holding_no', $holdingNo)
                ->where('prop_properties.status', 1)
                ->first();
        }
        return $propDetails;
    }

    // Get property detials according to citizen id
    public function getPropDetailsByCitizenId($citizenId)
    {
        return PropProperty::where('citizen_id', $citizenId)
            ->where('prop_properties.status', 1)
            ->orderByDesc('id');
    }

    // Get property details according to property id with owner details 
    public function getPropByPropId($propertyIds)
    {
        return PropProperty::select(
            DB::raw("string_agg(prop_owners.owner_name,',') as owner_name"),
            DB::raw("string_agg(prop_owners.mobile_no::VARCHAR,',') as mobile_no"),
        )
            ->join('prop_owners', 'prop_owners.property_id', 'prop_properties.id')
            ->whereIn('prop_properties', $propertyIds)
            ->where('prop_properties.status', 1)
            ->where('prop_owners.status', 1);
    }

    public function getUpdateRqu()
    {
        return $this->hasMany(PropPropertyUpdateRequest::class,"prop_id","id")
        ->where("prop_property_update_requests.status",1)
        ->orderBy("id","ASC");
    }
    public function getUpdatePendingRqu()
    {
        return $this->hasOne(PropPropertyUpdateRequest::class,"prop_id","id")
        ->where("prop_property_update_requests.status",1)
        ->where("prop_property_update_requests.pending_status",1)
        ->orderBy("id","ASC");
    }

    public function Owneres()
    {
        return $this->hasMany(PropOwner::class,"property_id","id")
                ->where("status",1)
                ->orderBy("id","ASC");        
    }
    public function floars()
    {
        return $this->hasMany(PropFloor::class,"property_id","id")
                ->where(function($where){
                    $where->whereNull("date_upto")
                    ->OrWhere("date_upto",">=",Carbon::now()->format("Y-m-d"));
                })
                ->orderBy("id","ASC");
    }

    public function PropDueDemands()
    {
        return $this->hasMany(PropDemand::class,"property_id","id")
            ->where("status",1)
            ->where("due_total_tax",">",0)
            ->orderBy("fyear","ASC");
    }
    public function PropLastPaidDemands()
    {
        return $this->hasOne(PropDemand::class,"property_id","id")
            ->where("status",1)
            ->where("due_total_tax","=",0)
            ->orderBy("fyear","DESC");
    }
    public function PropLastDemands()
    {
        return $this->hasOne(PropDemand::class,"property_id","id")
            ->where("status",1)
            ->orderBy("fyear","DESC")->first();
    }
}
