<?php

namespace App\Traits\Property;

use App\Http\Controllers\Property\ActiveSafController;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropOwnerUpdateRequest;
use App\Models\Property\PropProperty;
use App\Models\Property\PropPropertyUpdateRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * | Trait for Property and SAF reusable 
 * | Created On-08-12-2022 
 * | Created By-Anshu Kumar
 */

trait Property
{
    public function generatePropUpdateRequest(Request $request,PropProperty $property,bool $fullEdit=false)
    {
        $arr= [
            "propId"=>$property->id,
            "holdingNo" => $property->holding_no,
            "newHoldingNo" => $property->new_holding_no,
            "ptNo"=>  $property->pt_no,
            "propertyNo"=>  $property->property_no,
            "logs"=> json_encode($property->toArray(), JSON_UNESCAPED_UNICODE),
            "supportingDocument"=>$request->supportingDocument,
            "uniqueId"=>$request->uniqueId,
            "referenceNo" => $request->referenceNo,

            "ulbId"=> $request->UlbId ?? $property->ulb_id,
            "clusterId" => $request->clusterId ?? $property->cluster_id,
            "safId" => $request->safId ?? $property->saf_id,            
            "applicantName" => $request->applicantName ??  $property->applicant_name,
            "applicantMarathi" => $request->applicantMarathi ??  $property->applicant_marathi,
            "applicationDate" => $request->applicationDate ??  $property->application_date,
            "ward" => $request->ward ??  $property->ward_mstr_id,
            "ownershipType" => $request->ownershipType ??  $property->ownership_type_mstr_id,
            "electricityConnection" => $request->electricityConnection ??  $property->no_electric_connection,
            "electricityCustNo" => $request->electricityCustNo ??  $property->elect_consumer_no,
            "electricityAccNo" => $request->electricityAccNo ??  $property->elect_acc_no,
            "electricityBindBookNo" => $request->electricityBindBookNo ??  $property->elect_bind_book_no,
            "electricityConsCategory" => $request->electricityConsCategory ??  $property->elect_cons_category,            
            "waterConnNo" => $request->waterConnNo ??  $property->water_conn_no,  
            "waterConnDate" => $request->waterConnDate ??  $property->water_conn_date, 
            "khataNo" => $request->khataNo ??  $property->khata_no,
            "plotNo" => $request->plotNo ??  $property->plot_no,
            "villageMaujaName" => $request->villageMaujaName ??  $property->village_mauja_name,   
            "propAddress" => $request->propAddress ??  $property->prop_address, 
            "propCity" => $request->propCity ??  $property->prop_city,    
            "propDist" => $request->propDist ??  $property->prop_dist,  
            "propPinCode" => $request->propPinCode ??  $property->prop_pin_code,  
            "propState" => $request->propState ??  $property->prop_state,  
            "corrAddress" => $request->corrAddress ??  $property->corr_address, 
            "corrCity" => $request->corrCity ??  $property->corr_city,
            "corrDist" => $request->corrDist ??  $property->corr_dist,
            "corrPinCode" => $request->corrPinCode ??  $property->corr_pin_code,
            "corrState" => $request->corrState ??  $property->corr_state, 
            "newWard" => $request->newWard ??  $property->new_ward_mstr_id,   
            "entryType" => $request->entryType ??  $property->entry_type,  
            "zone" => $request->zone ??  $property->zone_mstr_id,  
            "buildingName" => $request->buildingName ??  $property->building_name, 
            "streetName" => $request->streetName ??  $property->street_name, 
            "location" => $request->location ??  $property->location, 
            "landmark" => $request->landmark ??  $property->landmark,
        ];
        $arr2 = [];
        if($fullEdit)
        {
            $arr2=[
            "appartmentName" => $request->appartmentName ?? $property->appartment_name,
            "propertyType" =>  $request->propertyType ?? $property->prop_type_mstr_id,
            "buildingPlanApprovalNo" =>  $request->buildingPlanApprovalNo ??  $property->building_plan_approval_no,
            "buildingPlanApprovalDate" =>  $request->buildingPlanApprovalDate ??  $property->building_plan_approval_date,
            "roadWidthType" =>   $request->roadWidthType ??  $property->road_type_mstr_id,
            "roadType" =>  $request->roadType ??  $property->road_width,
            "areaOfPlot" =>   $request->areaOfPlot ??  $property->area_of_plot,
            "isMobileTower" =>  $request->isMobileTower ??  $property->is_mobile_tower,
            "mobileTower" => ["area"=> $request->mobileTower['area'] ??  $property->tower_area,
                              "dateFrom"=> $request->mobileTower['dateFrom'] ??  $property->tower_installation_date
                            ],

            "isHoardingBoard" =>  $request->isHoardingBoard ??  $property->is_hoarding_board,
            "hoardingBoard" => ["area"=> $request->hoardingBoard['area'] ??  $property->hoarding_area,
                                "dateFrom"=> $request->hoardingBoard['dateFrom'] ??  $property->hoarding_installation_date
                               ],
            
            "isPetrolPump" =>  $request->isPetrolPump ??  $property->is_petrol_pump,
            "petrolPump" => ["area"=> $request->petrolPump['area'] ??  $property->under_ground_area,
                            "dateFrom"=> $request->petrolPump['dateFrom'] ??  $property->petrol_pump_completion_date
                            ],

            "isWaterHarvesting" =>  $request->isWaterHarvesting ??  $property->is_water_harvesting,            
            "rwhDateFrom" =>  $request->rwhDateFrom ??  $property->rwh_date_from,

            "dateOfPurchase" =>  $request->landOccupationDate ??  $property->land_occupation_date,
            "flatRegistryDate" =>  $request->flatRegistryDate ??  $property->flat_registry_date,
            "assessmentType" =>  $request->assessmentType ??  $property->assessment_type,
            "holdingType" =>  $request->holdingType ??  $property->holding_type,
            "apartmentId" =>  $request->apartmentId ??  $property->apartment_details_id,
            "isGBSaf" =>  $request->isGBSaf ??  $property->is_gb_saf,
            "gbOfficeName" =>  $request->gbOfficeName ??  $property->gb_office_name,
            "gbUsageTypes" =>  $request->gbUsageTypes ??  $property->gb_usage_types,
            "gbPropUsageTypes" =>  $request->gbPropUsageTypes ??  $property->gb_prop_usage_types,
            "isTrust" =>  $request->isTrust ??  $property->is_trust,
            "trustType" =>  $request->trustType ??  $property->trust_type,
            "isTrustVerified" =>  $request->isTrustVerified ??  $property->is_trust_verified,
            "category" =>  $request->category ??  $property->category_id,
            ];
            $arr =array_merge($arr,$arr2);
        }
        return $arr;
        
    }
    public function generatePropOwnerUpdateRequest(array $request,PropOwner $owners,bool $fullEdit=false)
    {
        $request=(object) $request;
        $arr= [
            "ownerId"=>$owners->id,
            "propId"=>$owners->property_id,
            "safId"=>$owners->saf_id,
            "logs"=>json_encode($owners->toArray(),JSON_UNESCAPED_UNICODE),
            "ownerName" => $request->ownerName ??  $owners->owner_name,  
            "ownerNameMarathi" => $request->ownerNameMarathi ??  $owners->owner_name_marathi, 
            "guardianNameMarathi" => $request->guardianNameMarathi ??  $owners->guardian_name_marathi,
            "guardianName" => $request->guardianName ??  $owners->guardian_name, 
            "relation" => $request->relation ??  $owners->relation_type, 
            "mobileNo" => $request->mobileNo ??  $owners->mobile_no,
            "email" => $request->email ??  $owners->email,
            "pan" => $request->pan ??  $owners->pan_no,
            "aadhar" => $request->aadhar ??  $owners->aadhar_no,
        ];
        $arr2 = [];
        if($fullEdit)
        {
            $arr2=[
            "gender" => $request->gender ?? $owners->gender,
            "dob" =>  $request->dob ?? $owners->dob,
            "isArmedForce" =>  $request->isArmedForce ??  $owners->is_armed_force,
            "isSpeciallyAbled" =>  $request->isSpeciallyAbled ??  $owners->is_specially_abled,
            ];
            $arr =array_merge($arr,$arr2);
        }
        return $arr;
    }

    public function updateProperty(PropPropertyUpdateRequest $UpdateRequest)
    {
        $prop = PropProperty::find($UpdateRequest->prop_id);
        if(!$prop)
        {
            throw new Exception("Property Not Found");
        }

        $arr = $this->updatePropBasic($UpdateRequest);
        if($UpdateRequest->is_full_update)
        {
            $arr =array_merge($arr,$this->updatePropPrimary($UpdateRequest)); 
        }
        return $arr;

    }

    public function updatePropBasic(PropPropertyUpdateRequest $UpdateRequest)
    {
        return $arr= [
            "ulb_id"=> $UpdateRequest->ulb_id,
            "cluster_id" => $UpdateRequest->cluster_id,        
            "applicant_name" =>  $UpdateRequest->applicant_name,
            "applicant_marathi" => $UpdateRequest->applicant_marathi,
            "application_date" =>  $UpdateRequest->application_date,
            "ward_mstr_id" => $UpdateRequest->ward_mstr_id,
            "ownership_type_mstr_id" => $UpdateRequest->ownership_type_mstr_id,
            "no_electric_connection" => $UpdateRequest->no_electric_connection,
            "elect_consumer_no" => $UpdateRequest->elect_consumer_no,
            "elect_acc_no" => $UpdateRequest->elect_acc_no,
            "elect_bind_book_no" =>  $UpdateRequest->elect_bind_book_no,
            "elect_cons_category" =>  $UpdateRequest->elect_cons_category,            
            "water_conn_no" => $UpdateRequest->water_conn_no,  
            "water_conn_date" => $UpdateRequest->water_conn_date, 
            "khata_no" => $UpdateRequest->khata_no,
            "plot_no" =>  $UpdateRequest->plot_no,
            "village_mauja_name" =>  $UpdateRequest->village_mauja_name,   
            "prop_address" => $UpdateRequest->prop_address, 
            "prop_city" =>  $UpdateRequest->prop_city,    
            "prop_dist" => $UpdateRequest->prop_dist,  
            "prop_pin_code" =>  $UpdateRequest->prop_pin_code,  
            "prop_state" => $UpdateRequest->prop_state,  
            "corr_address" =>  $UpdateRequest->corr_address, 
            "corr_city" =>  $UpdateRequest->corr_city,
            "corr_dist" =>  $UpdateRequest->corr_dist,
            "corr_pin_code" =>  $UpdateRequest->corr_pin_code,
            "corr_state" =>  $UpdateRequest->corr_state, 
            "new_ward_mstr_id" =>  $UpdateRequest->new_ward_mstr_id,   
            "entry_type" => $UpdateRequest->entry_type,  
            "zone_mstr_id" => $UpdateRequest->zone_mstr_id,  
            "building_name" =>  $UpdateRequest->building_name, 
            "street_name" => $UpdateRequest->street_name, 
            "location" =>  $UpdateRequest->location, 
            "landmark" =>  $UpdateRequest->landmark,
        ];
    }

    public function updatePropPrimary(PropPropertyUpdateRequest $UpdateRequest)
    {
        return$arr2=[
            "appartment_name" =>  $UpdateRequest->appartment_name,
            "prop_type_mstr_id" =>  $UpdateRequest->prop_type_mstr_id,
            "building_plan_approval_no" =>   $UpdateRequest->building_plan_approval_no,
            "building_plan_approval_date" =>  $UpdateRequest->building_plan_approval_date,
            "road_type_mstr_id" =>   $UpdateRequest->road_type_mstr_id,
            "road_width" =>  $UpdateRequest->road_width,
            "area_of_plot" =>   $UpdateRequest->area_of_plot,
            "is_mobile_tower" =>  $UpdateRequest->is_mobile_tower,
            "tower_area" => $UpdateRequest->tower_area,
            "tower_installation_date"=> $UpdateRequest->tower_installation_date,

            "isHoardingBoard" =>  $UpdateRequest->is_hoarding_board,
            "hoarding_area" => $UpdateRequest->hoarding_area,
            "hoarding_installation_date"=> $UpdateRequest->hoarding_installation_date,
            
            "is_petrol_pump" => $UpdateRequest->is_petrol_pump,
            "under_ground_area"=> $UpdateRequest->under_ground_area,
            "petrol_pump_completion_date"=> $UpdateRequest->petrol_pump_completion_date,

            "is_water_harvesting" =>  $UpdateRequest->is_water_harvesting,            
            "rwh_date_from" =>  $UpdateRequest->rwh_date_from,

            "land_occupation_date" =>  $UpdateRequest->land_occupation_date,
            "flat_registry_date" =>  $UpdateRequest->flat_registry_date,
            "assessment_type" =>  $UpdateRequest->assessment_type,
            "holding_type" =>  $UpdateRequest->holding_type,
            "apartment_details_id" =>  $UpdateRequest->apartment_details_id,
            "is_gb_saf" => $UpdateRequest->is_gb_saf,
            "gb_office_name" =>  $UpdateRequest->gb_office_name,
            "gb_usage_types" =>  $UpdateRequest->gb_usage_types,
            "gb_prop_usage_types" =>  $UpdateRequest->gb_prop_usage_types,
            "is_trust" =>  $UpdateRequest->is_trust,
            "trust_type" => $UpdateRequest->trust_type,
            "is_trust_verified" =>  $UpdateRequest->is_trust_verified,
            "category_id" =>  $UpdateRequest->category_id,
            ];
    }

    public function updatePropOwner(PropOwnerUpdateRequest $UpdateOwnerRequest)
    {
        $PropOwner = PropOwner::find($UpdateOwnerRequest->owner_id);
        $propUdateRequest = PropPropertyUpdateRequest::find($UpdateOwnerRequest->request_id);
        if(!$PropOwner)
        {
            throw new Exception("Owner Not Found");
        }
        $arr = $this->updatePropOwnerBasic($UpdateOwnerRequest);
        if($propUdateRequest->is_full_update)
        {
            $arr =array_merge($arr,$this->updatePropOwnerPrimary($UpdateOwnerRequest)); 
        }
        return $arr;
    }

    public function updatePropOwnerBasic(PropOwnerUpdateRequest $UpdateRequest)
    {
        return $arr= [
                "owner_name" => $UpdateRequest->owner_name,  
                "owner_name_marathi" => $UpdateRequest->owner_name_marathi, 
                "guardian_name_marathi" => $UpdateRequest->guardian_name_marathi,
                "guardian_name" => $UpdateRequest->guardian_name, 
                "relation_type" => $UpdateRequest->relation_type, 
                "mobile_no" => $UpdateRequest->mobile_no,
                "email" => $UpdateRequest->email,
                "pan_no" =>$UpdateRequest->pan_no,
                "aadhar_no" => $UpdateRequest->aadhar_no,
            ];
    }

    public function updatePropOwnerPrimary(PropOwnerUpdateRequest $UpdateRequest)
    {
        return $arr2=[
            "gender" =>  $UpdateRequest->gender,
            "dob" =>  $UpdateRequest->dob,
            "is_armed_force" =>  $UpdateRequest->is_armed_force,
            "is_specially_abled" =>   $UpdateRequest->is_specially_abled,
            ];
    }

    public function PropUpdateCom(PropPropertyUpdateRequest $application)
    {
        $propCom =[];
        $propLog = json_decode($application->logs);
        $controller = App::makeWith(ActiveSafController::class,["iSafRepository"=>app(\App\Repository\Property\Interfaces\iSafRepository::class)]);
        $response = $controller->masterSaf(new Request);
        if(!$response->original["status"]) 
        {
            throw new Exception("Master Data Not Found");
        }       
        $data = $response->original["data"];
        $categories = $data["categories"];        
        $categoriesIds = collect($categories)->implode("id",",");

        $construction_type = $data["construction_type"];
        $construction_typeIds = collect($construction_type)->implode("id",",");
        
        $floor_type = $data["floor_type"];
        $floor_typeIds = collect($floor_type)->implode("id",",");
        
        $occupancy_type = $data["occupancy_type"];
        $occupancy_typeIds = collect($occupancy_type)->implode("id",",");
        
        $ownership_types = $data["ownership_types"];
        $ownership_typesIds = collect($ownership_types)->implode("id",",");
        
        $property_type = $data["property_type"];
        $property_typeIds = collect($property_type)->implode("id",",");
        
        $transfer_mode = $data["transfer_mode"];
        $transfer_modeIds = collect($transfer_mode)->implode("id",",");
        
        $usage_type = $data["usage_type"];
        $usage_typeIds = collect($usage_type)->implode("id",",");

        $ward_master = $data["ward_master"];
        $ward_masterIds = collect($ward_master)->implode("id",",");        
        
        

        $zone = $data["zone"];
        $zoneIds = collect($zone)->implode("id",",");
        #basic        
        {
            $propCom["basic"]["values"]=[
                [
                "key"=>"applicant name",
                "values" => ($propLog->applicant_name ?? "") == ($application->applicant_name ?? ""),
                "according_verification" => $application->applicant_name ?? "",
                "according_application" => $propLog->applicant_name ?? "",
                ],
                [
                    "key"=>"applicant name in marathi",
                    "values" => ($propLog->applicant_marathi ?? "") == ($application->applicant_marathi ?? ""),
                    "according_verification" => $application->applicant_marathi ?? "",
                    "according_application" => $propLog->applicant_marathi ?? "",
                ],
                [
                    "key"=>"zone",
                    "values" => ($propLog->zone_mstr_id ?? "") == ($application->zone_mstr_id ?? ""),
                    "according_verification" => ((collect($zone)->where("id",$application->zone_mstr_id ?? "")->first())->zone_name??""),
                    "according_application" => ((collect($zone)->where("id",$propLog->zone_mstr_id ?? "")->first())->zone_name??""),
                ],
                [
                    "key"=>"ward no.",
                    "values" => ($propLog->ward_mstr_id ?? "") == ($application->ward_mstr_id ?? ""),
                    "according_verification" => ((collect($ward_master)->where("id",$application->ward_mstr_id ?? "")->first())->ward_name??""),
                    "according_application" => ((collect($ward_master)->where("id",$propLog->ward_mstr_id ?? "")->first())->ward_name??""),
                ],
                [
                    "key"=>"ownership type",
                    "values" => ($propLog->ownership_type_mstr_id ?? "") == ($application->ownership_type_mstr_id ?? ""),
                    "according_verification" => ((collect($ownership_types)->where("id",$application->ownership_type_mstr_id ?? "")->first())->ownership_type??""),
                    "according_application" => ((collect($ownership_types)->where("id",$propLog->ownership_type_mstr_id ?? "")->first())->ownership_type??""),
                ],
                [
                    "key"=>"electric connection no",
                    "values" => ($propLog->no_electric_connection ?? "") == ($application->no_electric_connection ?? ""),
                    "according_verification" => $application->no_electric_connection ?? "",
                    "according_application" => $propLog->no_electric_connection ?? "",
                ],
                [
                    "key"=>"electric consumer no",
                    "values" => ($propLog->elect_consumer_no ?? "") == ($application->elect_consumer_no ?? ""),
                    "according_verification" => $application->elect_consumer_no ?? "",
                    "according_application" => $propLog->elect_consumer_no ?? "",
                ],
                [
                    "key"=>"electric acc no",
                    "values" => ($propLog->elect_acc_no ?? "") == ($application->elect_acc_no ?? ""),
                    "according_verification" => $application->elect_acc_no ?? "",
                    "according_application" => $propLog->elect_acc_no ?? "",
                ],
                [
                    "key"=>"electric bind book no",
                    "values" => ($propLog->elect_bind_book_no ?? "") == ($application->elect_bind_book_no ?? ""),
                    "according_verification" => $application->elect_bind_book_no ?? "",
                    "according_application" => $propLog->elect_bind_book_no ?? "",
                ],                
                [
                    "key"=>"electric consumer category",
                    "values" => ($propLog->elect_cons_category ?? "") == ($application->elect_cons_category ?? ""),
                    "according_verification" => $application->elect_cons_category ?? "",
                    "according_application" => $propLog->elect_cons_category ?? "",
                ],               
                [
                    "key"=>"water consumer no",
                    "values" => ($propLog->water_conn_no ?? "") == ($application->water_conn_no ?? ""),
                    "according_verification" => $application->water_conn_no ?? "",
                    "according_application" => $propLog->water_conn_no ?? "",
                ],
                [
                    "key"=>"water conn date",
                    "values" => ($propLog->water_conn_date ?? "") == ($application->water_conn_date ?? ""),
                    "according_verification" => $application->water_conn_date ?Carbon::parse($application->water_conn_date)->format("d-m-Y"): null,
                    "according_application" => $propLog->water_conn_date ?Carbon::parse($propLog->water_conn_date)->format("d-m-Y"): null,
                ],                
                [
                    "key"=>"khata no",
                    "values" => ($propLog->khata_no ?? "") == ($application->khata_no ?? ""),
                    "according_verification" => $application->khata_no ?? "",
                    "according_application" => $propLog->khata_no ?? "",
                ],
                [
                    "key"=>"plot no",
                    "values" => ($propLog->plot_no ?? "") == ($application->plot_no ?? ""),
                    "according_verification" => $application->plot_no ?? "",
                    "according_application" => $propLog->plot_no ?? "",
                ],
                [
                    "key"=>"village mauja name",
                    "values" => ($propLog->village_mauja_name ?? "") == ($application->village_mauja_name ?? ""),
                    "according_verification" => $application->village_mauja_name ?? "",
                    "according_application" => $propLog->village_mauja_name ?? "",
                ],
                [
                    "key"=>"prop address",
                    "values" => ($propLog->prop_address ?? "") == ($application->prop_address ?? ""),
                    "according_verification" => $application->prop_address ?? "",
                    "according_application" => $propLog->prop_address ?? "",
                ],
                [
                    "key"=>"prop city",
                    "values" => ($propLog->prop_city ?? "") == ($application->prop_city ?? ""),
                    "according_verification" => $application->prop_city ?? "",
                    "according_application" => $propLog->prop_city ?? "",
                ],                
                [
                    "key"=>"prop dist",
                    "values" => ($propLog->prop_dist ?? "") == ($application->prop_dist ?? ""),
                    "according_verification" => $application->prop_dist ?? "",
                    "according_application" => $propLog->prop_dist ?? "",
                ],               
                [
                    "key"=>"prop pin code",
                    "values" => ($propLog->prop_pin_code ?? "") == ($application->prop_pin_code ?? ""),
                    "according_verification" => $application->prop_pin_code ?? "",
                    "according_application" => $propLog->prop_pin_code ?? "",
                ],                              
                [
                    "key"=>"prop state",
                    "values" => ($propLog->prop_state ?? "") == ($application->prop_state ?? ""),
                    "according_verification" => $application->prop_state ?? "",
                    "according_application" => $propLog->prop_state ?? "",
                ],                             
                [
                    "key"=>"corr address",
                    "values" => ($propLog->corr_address ?? "") == ($application->corr_address ?? ""),
                    "according_verification" => $application->corr_address ?? "",
                    "according_application" => $propLog->corr_address ?? "",
                ],                             
                [
                    "key"=>"corr city",
                    "values" => ($propLog->corr_city ?? "") == ($application->corr_city ?? ""),
                    "according_verification" => $application->corr_city ?? "",
                    "according_application" => $propLog->corr_city ?? "",
                ],                             
                [
                    "key"=>"corr dist",
                    "values" => ($propLog->corr_dist ?? "") == ($application->corr_dist ?? ""),
                    "according_verification" => $application->corr_dist ?? "",
                    "according_application" => $propLog->corr_dist ?? "",
                ],                           
                [
                    "key"=>"corr pin code",
                    "values" => ($propLog->corr_pin_code ?? "") == ($application->corr_pin_code ?? ""),
                    "according_verification" => $application->corr_pin_code ?? "",
                    "according_application" => $propLog->corr_pin_code ?? "",
                ],                           
                [
                    "key"=>"corr state",
                    "values" => ($propLog->corr_state ?? "") == ($application->corr_state ?? ""),
                    "according_verification" => $application->corr_state ?? "",
                    "according_application" => $propLog->corr_state ?? "",
                ],                           
                [
                    "key"=>"building name",
                    "values" => ($propLog->building_name ?? "") == ($application->building_name ?? ""),
                    "according_verification" => $application->building_name ?? "",
                    "according_application" => $propLog->building_name ?? "",
                ],                          
                [
                    "key"=>"street name",
                    "values" => ($propLog->street_name ?? "") == ($application->street_name ?? ""),
                    "according_verification" => $application->street_name ?? "",
                    "according_application" => $propLog->street_name ?? "",
                ],                          
                [
                    "key"=>"location",
                    "values" => ($propLog->location ?? "") == ($application->location ?? ""),
                    "according_verification" => $application->location ?? "",
                    "according_application" => $propLog->location ?? "",
                ],                          
                [
                    "key"=>"landmark",
                    "values" => ($propLog->landmark ?? "") == ($application->landmark ?? ""),
                    "according_verification" => $application->landmark ?? "",
                    "according_application" => $propLog->landmark ?? "",
                ],  
            ];
        }
        #primary
        {
            $propCom["primary"]["values"]=[
                [
                    "key"=>"appartment name",
                    "values" => ($propLog->appartment_name ?? "") == ($application->appartment_name ?? ""),
                    "according_verification" => $application->appartment_name ?? "",
                    "according_application" => $propLog->appartment_name ?? "",
                ],
                [
                    "key"=>"building plan approval no",
                    "values" => ($propLog->building_plan_approval_no ?? "") == ($application->building_plan_approval_no ?? ""),
                    "according_verification" => $application->building_plan_approval_no ?? "",
                    "according_application" => $propLog->building_plan_approval_no ?? "",
                ],
                [
                    "key"=>"building plan approval date",
                    "values" => ($propLog->building_plan_approval_date ?? "") == ($application->building_plan_approval_date ?? ""),
                    "according_verification" => $application->building_plan_approval_date ? Carbon::parse($application->building_plan_approval_date)->format("d-m-Y"): null,
                    "according_application" => $propLog->building_plan_approval_date ? Carbon::parse($propLog->building_plan_approval_date)->format("d-m-Y"): null,
                ],
                [
                    "key"=>"property type",
                    "values" => ($propLog->prop_type_mstr_id ?? "") == ($application->prop_type_mstr_id ?? ""),
                    "according_verification" => ((collect($property_type)->where("id",$application->prop_type_mstr_id ?? "")->first())->property_type??""),
                    "according_application" => ((collect($property_type)->where("id",$propLog->prop_type_mstr_id ?? "")->first())->property_type??""),
                ],
                [
                    "key"=>"road width",
                    "values" => ($propLog->road_width ?? "") == ($application->road_width ?? ""),
                    "according_verification" => ($application->road_width ?? ""),
                    "according_application" => ($propLog->road_width ?? ""),
                ],
                [
                    "key"=>"area of plot",
                    "values" => ($propLog->area_of_plot ?? "") == ($application->area_of_plot ?? ""),
                    "according_verification" => ($application->area_of_plot ?? ""),
                    "according_application" => ($propLog->area_of_plot ?? ""),
                ],
                [
                    "key"=>"has mobile tower",
                    "values" => ($propLog->is_mobile_tower ?? "") == ($application->is_mobile_tower ?? ""),
                    "according_verification" => ($application->is_mobile_tower ?? ""),
                    "according_application" => ($propLog->is_mobile_tower ?? ""),
                ],
                [
                    "key"=>"tower area",
                    "values" => ($propLog->tower_area ?? "") == ($application->tower_area ?? ""),
                    "according_verification" => ($application->tower_area ?? ""),
                    "according_application" => ($propLog->tower_area ?? ""),
                ],                
                [
                    "key"=>"tower installation date",
                    "values" => ($propLog->tower_installation_date ?? "") == ($application->tower_installation_date ?? ""),
                    "according_verification" => $application->tower_installation_date ? Carbon::parse($application->tower_installation_date)->format("d-m-Y"): null,
                    "according_application" => $propLog->tower_installation_date ? Carbon::parse($propLog->tower_installation_date)->format("d-m-Y"): null,
                ],              
                [
                    "key"=>"has Hoarding Board",
                    "values" => ($propLog->isHoardingBoard ?? "") == ($application->isHoardingBoard ?? ""),
                    "according_verification" => ($application->isHoardingBoard ?? ""),
                    "according_application" => ($propLog->isHoardingBoard ?? ""),
                ],
                [
                    "key"=>"Hoarding Board Area",
                    "values" => ($propLog->hoarding_area ?? "") == ($application->hoarding_area ?? ""),
                    "according_verification" => ($application->hoarding_area ?? ""),
                    "according_application" => ($propLog->hoarding_area ?? ""),
                ],                
                [
                    "key"=>"Hoarding Board installation date",
                    "values" => ($propLog->hoarding_installation_date ?? "") == ($application->hoarding_installation_date ?? ""),
                    "according_verification" => $application->hoarding_installation_date ? Carbon::parse($application->hoarding_installation_date)->format("d-m-Y"): null,
                    "according_application" => $propLog->hoarding_installation_date ? Carbon::parse($propLog->hoarding_installation_date)->format("d-m-Y"): null,
                ],                
                [
                    "key"=>"has petrol pump",
                    "values" => ($propLog->is_petrol_pump ?? "") == ($application->is_petrol_pump ?? ""),
                    "according_verification" => ($application->is_petrol_pump ?? ""),
                    "according_application" => ($propLog->is_petrol_pump ?? ""),
                ],                
                [
                    "key"=>"under ground area",
                    "values" => ($propLog->under_ground_area ?? "") == ($application->under_ground_area ?? ""),
                    "according_verification" => ($application->under_ground_area ?? ""),
                    "according_application" => ($propLog->under_ground_area ?? ""),
                ],                
                [
                    "key"=>"petrol pump completion date",
                    "values" => ($propLog->petrol_pump_completion_date ?? "") == ($application->petrol_pump_completion_date ?? ""),
                    "according_verification" => $application->petrol_pump_completion_date ? Carbon::parse($application->petrol_pump_completion_date)->format("d-m-Y"): null,
                    "according_application" => $propLog->petrol_pump_completion_date ? Carbon::parse($propLog->petrol_pump_completion_date)->format("d-m-Y"): null,
                ],                
                [
                    "key"=>"has water harvesting",
                    "values" => ($propLog->is_water_harvesting ?? "") == ($application->is_water_harvesting ?? ""),
                    "according_verification" => ($application->is_water_harvesting ?? ""),
                    "according_application" => ($propLog->is_water_harvesting ?? ""),
                ],                                
                [
                    "key"=>"water harvesting date from",
                    "values" => ($propLog->rwh_date_from ?? "") == ($application->rwh_date_from ?? ""),
                    "according_verification" => $application->rwh_date_from ? Carbon::parse($application->rwh_date_from)->format("d-m-Y"): null,
                    "according_application" => $propLog->rwh_date_from ? Carbon::parse($propLog->rwh_date_from)->format("d-m-Y"): null,
                ],                                
                [
                    "key"=>"land occupation date",
                    "values" => ($propLog->land_occupation_date ?? "") == ($application->land_occupation_date ?? ""),
                    "according_verification" => $application->land_occupation_date ? Carbon::parse($application->land_occupation_date)->format("d-m-Y"): null,
                    "according_application" => $propLog->land_occupation_date ? Carbon::parse($propLog->land_occupation_date)->format("d-m-Y"): null,
                ],                                               
                [
                    "key"=>"flat registry date",
                    "values" => ($propLog->flat_registry_date ?? "") == ($application->flat_registry_date ?? ""),
                    "according_verification" => ($application->flat_registry_date ?? ""),
                    "according_application" => ($propLog->flat_registry_date ?? ""),
                ],                                              
                [
                    "key"=>"is trust",
                    "values" => ($propLog->is_trust ?? "") == ($application->is_trust ?? ""),
                    "according_verification" => ($application->is_trust ?? ""),
                    "according_application" => ($propLog->is_trust ?? ""),
                ],                                                              
                [
                    "key"=>"trust type",
                    "values" => ($propLog->trust_type ?? "") == ($application->trust_type ?? ""),
                    "according_verification" => ($application->trust_type ?? ""),
                    "according_application" => ($propLog->trust_type ?? ""),
                ],                                                              
                [
                    "key"=>"category",
                    "values" => ($propLog->category_id ?? "") == ($application->category_id ?? ""),
                    "according_verification" => ((collect($categories)->where("id",$application->category_id ?? "")->first())->category??""),
                    "according_application" => ((collect($categories)->where("id",$propLog->category_id ?? "")->first())->category??""),
                ],
            ]; 
        }
        return $propCom;
    }

    public function OwerUpdateCom(PropPropertyUpdateRequest $application)
    {
        $ownerCom = [];
        $owners = $application->getOwnersUpdateReq()->get(); 
        
        foreach($owners as $key=>$val)
        {
            $ownerLog = json_decode($val->logs);
            $ownerCom[]["values"]=[
                [
                    "key"=>"owner name",
                    "values" => ($ownerLog->owner_name ?? "") == ($val->owner_name ?? ""),
                    "according_verification" => $val->owner_name ?? "",
                    "according_application" => $ownerLog->owner_name ?? "",
                ],
                [
                    "key"=>"owner name in marathi",
                    "values" => ($ownerLog->owner_name_marathi ?? "") == ($val->owner_name_marathi ?? ""),
                    "according_verification" => $val->owner_name_marathi ?? "",
                    "according_application" => $ownerLog->owner_name_marathi ?? "",
                ],
                [
                    "key"=>"guardian name",
                    "values" => ($ownerLog->guardian_name ?? "") == ($val->guardian_name ?? ""),
                    "according_verification" => $val->guardian_name ?? "",
                    "according_application" => $ownerLog->guardian_name ?? "",
                ],
                [
                    "key"=>"guardian name in marathi",
                    "values" => ($ownerLog->guardian_name_marathi ?? "") == ($val->guardian_name_marathi ?? ""),
                    "according_verification" => $val->guardian_name_marathi ?? "",
                    "according_application" => $ownerLog->guardian_name_marathi ?? "",
                ],
                [
                    "key"=>"relation type",
                    "values" => ($ownerLog->relation_type ?? "") == ($val->relation_type ?? ""),
                    "according_verification" => $val->relation_type ?? "",
                    "according_application" => $ownerLog->relation_type ?? "",
                ],
                [
                    "key"=>"mobile no",
                    "values" => ($ownerLog->mobile_no ?? "") == ($val->mobile_no ?? ""),
                    "according_verification" => $val->mobile_no ?? "",
                    "according_application" => $ownerLog->mobile_no ?? "",
                ],
                [
                    "key"=>"email",
                    "values" => ($ownerLog->email ?? "") == ($val->email ?? ""),
                    "according_verification" => $val->email ?? "",
                    "according_application" => $ownerLog->email ?? "",
                ],
                [
                    "key"=>"pan no",
                    "values" => ($ownerLog->pan_no ?? "") == ($val->pan_no ?? ""),
                    "according_verification" => $val->pan_no ?? "",
                    "according_application" => $ownerLog->pan_no ?? "",
                ],
                [
                    "key"=>"aadhar no",
                    "values" => ($ownerLog->aadhar_no ?? "") == ($val->aadhar_no ?? ""),
                    "according_verification" => $val->aadhar_no ?? "",
                    "according_application" => $ownerLog->aadhar_no ?? "",
                ],
                [
                    "key"=>"gender",
                    "values" => ($ownerLog->gender ?? "") == ($val->gender ?? ""),
                    "according_verification" => $val->gender ?? "",
                    "according_application" => $ownerLog->gender ?? "",
                ],
                [
                    "key"=>"DOB",
                    "values" => ($ownerLog->dob ?? "") == ($val->dob ?? ""),
                    "according_verification" => $val->dob ?Carbon::parse($val->dob)->format("d-m-Y"): null,
                    "according_application" => $ownerLog->dob ?Carbon::parse($ownerLog->dob)->format("d-m-Y"): null ,
                ],
                [
                    "key"=>"is armed force",
                    "values" => ($ownerLog->is_armed_force ?? "") == ($val->is_armed_force ?? ""),
                    "according_verification" => $val->is_armed_force ?? "",
                    "according_application" => $ownerLog->is_armed_force ?? "",
                ],
                [
                    "key"=>"is specially abled",
                    "values" => ($ownerLog->is_specially_abled ?? "") == ($val->is_specially_abled ?? ""),
                    "according_verification" => $val->is_specially_abled ?? "",
                    "according_application" => $ownerLog->is_specially_abled ?? "",
                ],
            ];
        }
        return $ownerCom;
    }

    public function generatePropFloar(Request $request,PropFloor $floors)
    {
         $arr=[
                    "propFloorDetailId"=>$floors->prop_floor_details_id,
                    "floorNo"=>$floors->floor_mstr_id,
                    "usageType"=>$floors->usage_type_mstr_id,
                    "constructionType"=>$floors->const_type_mstr_id,
                    "occupancyType"=>$floors->occupancy_type_mstr_id,
                    "buildupArea"=>$floors->builtup_area,
                    "dateFrom"=>$floors->date_from,
                    "dateUpto"=>$floors->date_upto
                ];
        return $arr;
    } 
}
