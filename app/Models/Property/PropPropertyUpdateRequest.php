<?php

namespace App\Models\Property;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropPropertyUpdateRequest extends PropParamModel #Model
{
    use HasFactory;
    protected $guarded = [];

    public function store($req)
    {
        $reqs = [
            "prop_id"   =>  $req->propId,
            "holding_no" => $req->holdingNo,
            "new_holding_no" => $req->newHoldingNo,
            "pt_no" =>  $req->ptNo,
            "property_no" =>  $req->propertyNo,
            "logs" =>  $req->logs,
            "supporting_doc" => $req->supportingDocument,
            "unique_id" => $req->uniqueId,
            "reference_no" => $req->referenceNo,
            "is_full_update" => $req->isFullUpdate,

            'holding_no' => $req->holdingNo,
            'ward_mstr_id' => $req->ward,
            'ownership_type_mstr_id' => $req->ownershipType,
            'prop_type_mstr_id' => $req->propertyType,
            'appartment_name' => $req->appartmentName,
            'flat_registry_date' => $req->flatRegistryDate,
            'zone_mstr_id' => $req->zone,
            'no_electric_connection' => $req->electricityConnection,
            'elect_consumer_no' => $req->electricityCustNo,
            'elect_acc_no' => $req->electricityAccNo,
            'elect_bind_book_no' => $req->electricityBindBookNo,
            'elect_cons_category' => $req->electricityConsCategory,
            'building_plan_approval_no' => $req->buildingPlanApprovalNo,
            'building_plan_approval_date' => $req->buildingPlanApprovalDate,
            'water_conn_no' => $req->waterConnNo,
            'water_conn_date' => $req->waterConnDate,
            'khata_no' => $req->khataNo,
            'plot_no' => $req->plotNo,
            'village_mauja_name' => $req->villageMaujaName,
            'road_type_mstr_id' => $req->roadWidthType,
            'area_of_plot' => $req->areaOfPlot,
            'prop_address' => $req->propAddress,
            'prop_city' => $req->propCity,
            'prop_dist' => $req->propDist,
            'prop_pin_code' => $req->propPinCode,
            'corr_address' => $req->corrAddress,
            'corr_city' => $req->corrCity,
            'corr_dist' => $req->corrDist,
            'corr_pin_code' => $req->corrPinCode,
            'holding_type' => $req->holdingType,
            'is_mobile_tower' => $req->isMobileTower,
            'tower_area' => $req->mobileTower['area'] ?? null,
            'tower_installation_date' => $req->mobileTower['dateFrom'] ?? null,

            'is_hoarding_board' => $req->isHoardingBoard,
            'hoarding_area' => $req->hoardingBoard['area'] ?? null,
            'hoarding_installation_date' => $req->hoardingBoard['dateFrom'] ?? null,


            'is_petrol_pump' => $req->isPetrolPump,
            'under_ground_area' => $req->petrolPump['area'] ?? null,
            'petrol_pump_completion_date' => $req->petrolPump['dateFrom'] ?? null,

            'is_water_harvesting' => $req->isWaterHarvesting,
            'rwh_date_from' => ($req->isWaterHarvesting == 1) ? $req->rwhDateFrom : null,
            'land_occupation_date' => $req->landOccupationDate ? $req->landOccupationDate : $req->dateOfPurchase,
            'application_date' =>  $req->applicationDate,
            'assessment_type' => $req->assessmentType,
            'prop_state' => $req->propState,
            'corr_state' => $req->corrState,
            'holding_type' => $req->holdingType,
            'ip_address' => getClientIpAddress(),
            'new_ward_mstr_id' => $req->newWard,
            "entry_type"    =>  $req->entryType,
            'apartment_details_id' => $req->apartmentId,
            'applicant_name' => $req->applicantName,
            'applicant_marathi' => $req->applicantMarathi,
            'road_width' => $req->roadType,
            'user_id' => $req->userId,
            'workflow_id' => $req->workflowId,
            'ulb_id' => $req->ulbId,
            'pending_status' => $req->pendingStatus ?? 0,
            'current_role_id' => $req->finisherRoleId,
            'initiator_role_id' => $req->finisherRoleId,
            'finisher_role_id' => $req->finisherRoleId,
            'citizen_id' => $req->citizenId ?? null,

            'building_name' => $req->buildingName,
            'street_name' => $req->streetName,
            'location' => $req->location,
            'landmark' => $req->landmark,
            'is_gb_saf' => isset($req->isGBSaf) ? $req->isGBSaf : false,
            'gb_office_name' => isset($req->gbOfficeName) ? $req->gbOfficeName : null,
            'gb_usage_types' => isset($req->gbUsageTypes) ? $req->gbUsageTypes : null,
            'gb_prop_usage_types' => isset($req->gbPropUsageTypes) ? $req->gbPropUsageTypes : null,
            'is_trust' => $req->isTrust ?? false,
            'trust_type' => $req->trustType ?? null,
            'is_trust_verified' => $req->isTrustVerified ?? false,
            'category_id' => $req->category,
        ];
        $propUpdateReq = PropPropertyUpdateRequest::create($reqs);
        return ([
            'id' => $propUpdateReq->id,
            'application_no' => $propUpdateReq->request_no,
            'workflow_id' => $propUpdateReq->workflow_id,
            'current_role' => $propUpdateReq->current_role_id,
            'ulb_id' => $propUpdateReq->ulb_id
        ]);
    }

    public function WorkFlowMetaList()
    {
        return self::select(
            "prop_property_update_requests.id",
            "prop_property_update_requests.request_no",
            "prop_property_update_requests.prop_id",
            "prop_property_update_requests.workflow_id",
            "owner.owner_name",
            "owner.guardian_name",
            "owner.mobile_no",
            "owner.email",
            "owner.owner_name_marathi",
            "owner.guardian_name_marathi",
            "prop_property_update_requests.holding_no",
            "prop_property_update_requests.property_no",
            "prop_property_update_requests.current_role_id",
            "prop_property_update_requests.is_full_update",
            DB::raw("TO_CHAR(CAST(prop_property_update_requests.created_at AS DATE), 'DD-MM-YYYY') as application_date"),
            "ward_name"
        )
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_property_update_requests.ward_mstr_id')
            ->join("prop_properties", "prop_properties.id", "prop_property_update_requests.prop_id")
            ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                            STRING_AGG(guardian_name,',') AS guardian_name,
                            STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                            STRING_AGG(email,',') AS email,
                            STRING_AGG(owner_name_marathi,',') AS owner_name_marathi,
                            STRING_AGG(guardian_name_marathi,',') AS guardian_name_marathi,
                            property_id
                        FROM prop_owners 
                        WHERE status = 1
                        GROUP BY property_id
                        )owner"), function ($join) {
                $join->on("owner.property_id", "prop_properties.id");
            })
            ->where("prop_property_update_requests.status", 1)
            ->where("prop_property_update_requests.pending_status", 1);
    }

    public function getOwnersUpdateReq()
    {
        return $this->hasMany(PropOwnerUpdateRequest::class, "request_id", "id")->where("prop_owner_update_requests.status", 1);
    }

    public function getFloorsUpdateReq()
    {
        return $this->hasMany(PropFloorsUpdateRequest::class, "request_id", "id")->where("prop_floors_update_requests.status", 1);
    }

    /**
     * | Update Details
     */
    public function updateDetails()
    {
        return PropPropertyUpdateRequest::select(
            'prop_property_update_requests.id',
            'prop_property_update_requests.applicant_name as owner_name',
            'prop_property_update_requests.property_no',
            'holding_no',
            'zone_name',
            'ward_name as ward_no',
            'u.name as applied_by',
            'uu.name as approved_by',
            DB::raw("TO_CHAR(prop_property_update_requests.created_at, 'DD-MM-YYYY') as apply_date"),
            DB::raw("TO_CHAR(prop_property_update_requests.approval_date, 'DD-MM-YYYY') as approved_date"),
            DB::raw(
                "case when pending_status =1 then 'Pending'
                      when pending_status =4 then 'Rejected'
                      when pending_status =5 then 'Approved'
                    end
                      as status"
            ),
        )
            ->where('prop_property_update_requests.status', 1)
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_property_update_requests.ward_mstr_id')
            ->join('zone_masters', 'zone_masters.id', 'prop_property_update_requests.zone_mstr_id')
            ->join('users as u', 'u.id', 'prop_property_update_requests.user_id')
            ->leftjoin('users as uu', 'uu.id', 'prop_property_update_requests.approved_by');
    }

    public function getDetail($applicationId)
    {
        $data =  PropPropertyUpdateRequest::select('*')
            ->where('prop_property_update_requests.id', $applicationId)->get();
            return $data;
    }
}
