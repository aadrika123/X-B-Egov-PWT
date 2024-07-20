<?php

namespace App\Traits\Property;

use Illuminate\Database\Eloquent\Collection;

/**
 * | Trait Created for Gettting Dynamic Saf Details
 */
trait SafDetailsTrait
{
    /**
     * | Get Basic Details
     */
    public function generateBasicDetails($data)
    {
        return new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $data->old_ward_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            // ['displayString' => 'New Ward No', 'key' => 'newWardNo', 'value' => $data->new_ward_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $data->ownership_type, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $data->property_type, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Zone', 'key' => 'zone', 'value' => ($data->zone_mstr_id == 1) ? 'Zone 1' : 'Zone 2', 'canBtc' => 'true', 'canEdit' => 'false'],
            ['displayString' => 'Property has Mobile Tower(s) ?', 'key' => 'isMobileTower', 'value' => ($data->is_mobile_tower == false) ? 'No' : 'Yes', 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property has Hoarding Board(s) ?', 'key' => 'isHoardingBoard', 'value' => ($data->is_hoarding_board == false) ? 'No' : 'Yes', 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property has Rain Water Harvesting ?', 'key' => 'isWaterHarvesting', 'value' => ($data->is_water_harvesting == false) ? 'No' : 'Yes', 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'AppliedBy', 'key' => 'AppliedBy', 'value' => $data->applied_by]
        ]);
    }

    /**
     * | Generating Property Details
     */
    public function generatePropertyDetails($data)
    {
        return new Collection([
            ['displayString' => 'Khata No.', 'key' => 'khataNo', 'value' => $data->khata_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Plot No.', 'key' => 'plotNo', 'value' => $data->plot_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Village/Mauja Name', 'key' => 'villageMaujaName', 'value' => $data->village_mauja_name, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Area of Plot', 'key' => 'areaOfPlot', 'value' => $data->area_of_plot, 'canBtc' => 'true', 'canEdit' => 'true'],
            // ['displayString' => 'Road Width', 'key' => 'roadWidth', 'value' => $data->road_width ?? "", 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Category', 'key' => 'category', 'value' => $data->category ?? "", 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'City', 'key' => 'city', 'value' => $data->prop_city, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'District', 'key' => 'district', 'value' => $data->prop_dist, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'State', 'key' => 'state', 'value' => $data->prop_state, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Pin', 'key' => 'pin', 'value' => $data->prop_pin_code, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Locality', 'key' => 'locality', 'value' => $data->prop_address, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Apartment Code', 'key' => 'apartmentCode', 'value' => $data->apartment_code, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Apartment Address', 'key' => 'apartmentAddress', 'value' => $data->apartment_address, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'No Of Blocks', 'key' => 'noOfBlocks', 'value' => $data->no_of_block, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Apartment Name', 'key' => 'apartmentName', 'value' => $data->apartment_name, 'canBtc' => 'true', 'canEdit' => 'true'],
        ]);
    }

    /**
     * /Generating doc detail
     */

     public function generateDocDetails($data)
    {
        return new Collection([
            ['displayString' => 'isApplicationFormDoc', 'key' => 'isApplicationFormDoc', 'value' => $data->is_application_form_doc],
            ['displayString' => 'isSaleDeedDoc', 'key' => 'isSaleDeedDoc', 'value' => $data->is_sale_deed_doc],
            ['displayString' => 'isLayoutSactionMapDoc', 'key' => 'isLayoutSactionMapDoc', 'value' => $data->is_layout_section_map_doc],
            ['displayString' => 'isNaOrderDoc', 'key' => 'isNaOrderDoc', 'value' => $data->is_na_order_doc],
            ['displayString' => 'isNamunaDDoc', 'key' => 'isNamunaDDoc', 'value' => $data->is_namuna_doc],
            ['displayString' => 'isOthersDoc', 'key' => 'isOthersDoc', 'value' => $data->is_other_doc],
            ['displayString' => 'isMeasurementDoc', 'key' => 'isMeasurementDoc', 'value' => $data->is_measurement_doc],
            ['displayString' => 'isPhotoDoc', 'key' => 'isPhotoDoc', 'value' => $data->is_photo_doc],
            ['displayString' => 'isIdProofDoc', 'key' => 'isIdProofDoc', 'value' => $data->is_id_proof_doc],
            
        ]);
    }
    /**
     * | Generate Corresponding Details
     */
    public function generateCorrDtls($data)
    {
        return new Collection([
            ['displayString' => 'City', 'key' => 'corrCity', 'value' => $data->corr_city, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'District', 'key' => 'corrDistrict', 'value' => $data->corr_dist, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'State', 'key' => 'corrState', 'value' => $data->corr_state, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Pin', 'key' => 'corrPin', 'value' => $data->corr_pin_code, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Locality', 'key' => 'corrLocality', 'value' => $data->corr_address, 'canBtc' => 'true', 'canEdit' => 'true'],
        ]);
    }

    /**
     * | Generate Electricity Details
     */
    public function generateElectDtls($data)
    {
        return new Collection([
            ['displayString' => 'Electricity K. No', 'key' => 'electKNo', 'value' => $data->elect_consumer_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'ACC No.', 'key' => 'accNo', 'value' => $data->elect_acc_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'BIND/BOOK No.', 'key' => 'bindBookNo', 'value' => $data->elect_bind_book_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Electricity Consumer Category', 'key' => 'electConsumerCategory', 'value' => $data->elect_cons_category, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Building Plan Approval No.', 'key' => 'buildingApprovalNo', 'value' => $data->building_plan_approval_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Building Plan Approval Date', 'key' => 'buildingApprovalDate', 'value' => $data->building_plan_approval_date, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Water Consumer No.', 'key' => 'waterConsumerNo', 'value' => $data->water_conn_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Water Connection Date', 'key' => 'waterConnectionDate', 'value' => $data->water_conn_date, 'canBtc' => 'true', 'canEdit' => 'true']
        ]);
    }

    /**
     * | Generate Owner Details
     */
    public function generateOwnerDetails($ownerDetails)
    {
        return collect($ownerDetails)->map(function ($ownerDetail, $key) {
            return [
                $key + 1,
                $ownerDetail['owner_name'],
                $ownerDetail['gender'],
                $ownerDetail['dob'],
                $ownerDetail['guardian_name'],
                // $ownerDetail['relation_type'],
                $ownerDetail['mobile_no'],
                $ownerDetail['aadhar_no'],
                $ownerDetail['pan_no'],
                $ownerDetail['email'],
                ($ownerDetail['is_armed_force'] == true ? 'Yes' : 'No'),
                ($ownerDetail['is_specially_abled'] == true ? 'Yes' : 'No'),
            ];
        });
    }

    /**
     * | Generate Floor Details
     */
    public function generateFloorDetails($floorDetails)
    {
        return collect($floorDetails)->map(function ($floorDetail, $key) {
            return [
                $key + 1,
                $floorDetail->floor_name,
                $floorDetail->usage_type,
                $floorDetail->occupancy_type,
                $floorDetail->construction_type,
                $floorDetail->builtup_area,
                $floorDetail->date_from,
                $floorDetail->date_upto
            ];
        });
    }

    /**
     * | Generate Bi Floor Details
     */
    public function generateBiFloorDetails($floorDetails)
    {
        return collect($floorDetails)->map(function ($floorDetail, $key) {
            return [
                $key + 1,
                $floorDetail->floor_name,
                $floorDetail->usage_type,
                $floorDetail->occupancy_type,
                $floorDetail->construction_type,
                $floorDetail->builtup_area,
                $floorDetail->bifurcated_from_buildup_area,
                $floorDetail->date_from,
                $floorDetail->date_upto
            ];
        });
    }

    /**
     * | Generate Card Details
     */
    public function generateCardDetails($req, $ownerDetails)
    {
        $owners = collect($ownerDetails)->implode('owner_name', ',');
        $data = new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->old_ward_no],
            ['displayString' => 'SAF No.', 'key' => 'safNo', 'value' => $req->saf_no],
            ['displayString' => 'Owners', 'key' => 'ownerName', 'value' => $owners],
            ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $req->property_type],
            ['displayString' => 'Assessment Type', 'key' => 'assessmentType', 'value' => $req->assessment_type],
            ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $req->ownership_type],
            ['displayString' => 'Apply-Date', 'key' => 'applyDate', 'value' => $req->application_date],
            ['displayString' => 'Plot-Area(In Sqft)', 'key' => 'plotArea', 'value' => $req->area_of_plot],
            ['displayString' => 'Is-Water-Harvesting', 'key' => 'isWaterHarvesting', 'value' => ($req->is_water_harvesting == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is-Hoarding-Board', 'key' => 'isHoardingBoard', 'value' => ($req->is_hoarding_board == true) ? 'Yes' : 'No'],
            ['displayString' => 'Category', 'key' => 'category', 'value' => $req->category ]
        ]);
        if(trim($req->property_no))
        {
            $data->push(
                ['displayString' => 'Property No', 'key' => 'properyNo', 'value' => $req->property_no]
            );
        }
        return $data;
    }

    /**
     * | Generate Bifurcated Card Details
     */
    public function generateBiCardDetails($req, $ownerDetails)
    {
        $owners = collect($ownerDetails)->implode('owner_name', ',');
        $data = new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->old_ward_no],
            ['displayString' => 'SAF No.', 'key' => 'safNo', 'value' => $req->saf_no],
            ['displayString' => 'Owners', 'key' => 'ownerName', 'value' => $owners],
            ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $req->property_type],
            ['displayString' => 'Assessment Type', 'key' => 'assessmentType', 'value' => $req->assessment_type],
            ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $req->ownership_type],
            ['displayString' => 'Plot-Area(In Sqft)', 'key' => 'plotArea', 'value' => $req->area_of_plot],
            ['displayString' => 'Bifurcated From Plot Area(In Sqft)', 'key' => 'biPlotArea', 'value' => $req->bifurcated_from_plot_area],
            ['displayString' => 'Is-Water-Harvesting', 'key' => 'isWaterHarvesting', 'value' => ($req->is_water_harvesting == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is-Hoarding-Board', 'key' => 'isHoardingBoard', 'value' => ($req->is_hoarding_board == true) ? 'Yes' : 'No'],
            ['displayString' => 'Category', 'key' => 'category', 'value' => $req->category ]
        ]);
        if(trim($req->property_no))
        {
            $data->push(
                ['displayString' => 'Property No', 'key' => 'properyNo', 'value' => $req->property_no]
            );
        }
        return $data;
    }

    /**
     * | Generate Card Details for Concession
     */
    public function generateConcessionCardDtls($req, $ownerDetails)
    {
        $owners = collect($ownerDetails)->implode('owner_name', ',');

        $propertyDetails = new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->old_ward_no],
            ['displayString' => 'Holding No', 'key' => 'safNo', 'value' => $req->holding_no],
            ['displayString' => 'DOB', 'key' => 'dob', 'value' => $req->dob],
            ['displayString' => 'Gender', 'key' => 'gender', 'value' => $req->gender],
            ['displayString' => 'Is Armed Force', 'key' => 'isArmedForce', 'value' => ($req->is_armed_force == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is Specially Abled', 'key' => 'isSpeciallyAbled', 'value' => ($req->is_specially_abled == true) ? 'Yes' : 'No'],
            ['displayString' => 'Owner', 'key' => 'ownerName', 'value' => $req->owner_name],
            ['displayString' => 'Concession Applied For', 'key' => 'appliedFor', 'value' => $req->applied_for],
        ]);

        $cardElement = [
            'headerTitle' => "Concession Details",
            'data' => $propertyDetails
        ];
        return $cardElement;
    }


    /**
     * | Generate Card Details for Objections
     */
    public function generateObjCardDtls($req, $ownerDetails)
    {
        $owners = collect($ownerDetails)->implode('owner_name', ',');

        $propertyDetails = new Collection([
            ['displayString' => 'Holding No', 'key' => 'holdingNo', 'value' => $req->holding_no],
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->old_ward_no],
            ['displayString' => 'Objection No.', 'key' => 'objectionNo', 'value' => $req->objection_no],
            ['displayString' => 'Owners', 'key' => 'ownerName', 'value' => $owners],
            ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $req->property_type],
            ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $req->ownership_type],
            ['displayString' => 'Plot-Area(sqft)', 'key' => 'plotArea', 'value' => $req->area_of_plot],
            ['displayString' => 'Is Hoarding Board', 'key' => 'isHoardingBoard', 'value' => ($req->is_hoarding_board == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is Petrol Pump', 'key' => 'isPetrolPump', 'value' => ($req->is_petrol_pump == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is Water Harvesting', 'key' => 'isWaterHarvesting', 'value' => ($req->is_water_harvesting == true) ? 'Yes' : 'No'],
        ]);

        $cardElement = [
            'headerTitle' => "Objection Details",
            'data' => $propertyDetails
        ];
        return $cardElement;
    }

    /**
     * | Generate Card Details for Harvesting
     */
    public function generateHarvestingCardDtls($req, $ownerDetails)
    {
        $owners = collect($ownerDetails)->implode('owner_name', ',');
        $propertyDetails = new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->old_ward_no],
            ['displayString' => 'Holding No', 'key' => 'holdingNo', 'value' => $req->holding_no],
            ['displayString' => 'Harvesting No.', 'key' => 'harvestingNo', 'value' => $req->application_no],
            // ['displayString' => 'Owners', 'key' => 'ownerName', 'value' => $owners],
            // ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $req->property_type],
            // ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $req->ownership_type],
            ['displayString' => 'Plot-Area(decimal)', 'key' => 'plotArea', 'value' => $req->area_of_plot],
            ['displayString' => 'Is Hoarding Board', 'key' => 'isHoardingBoard', 'value' => ($req->is_hoarding_board == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is Petrol Pump', 'key' => 'isPetrolPump', 'value' => ($req->is_petrol_pump == true) ? 'Yes' : 'No'],
            ['displayString' => 'Previous Water Harvesting Status', 'key' => 'isWaterHarvesting', 'value' => ($req->is_water_harvesting == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is Harvesting done before 31-03-2017?', 'key' => 'harvestingBefore2017', 'value' => ($req->harvesting_status == true) ? 'Yes' : 'No'],
            ['displayString' => 'Date of Completion', 'key' => 'dateOfCompletion', 'value' => $req->date_of_completion],
            ['displayString' => 'Pending At', 'key' => 'pendingStatus', 'value' => $req->current_role],
        ]);

        $cardElement = [
            'headerTitle' => "Harvesting Details",
            'data' => $propertyDetails
        ];
        return $cardElement;
    }

    /**
     * | Objection Owner Details
     */
    public function objectionOwnerDetails($ownerDetails)
    {
        return collect($ownerDetails)->map(function ($ownerDetail) {
            return [
                1,
                $ownerDetail['owner_name'],
                $ownerDetail['gender'],
                $ownerDetail['dob'],
                $ownerDetail['guardian_name'],
                $ownerDetail['relation'],
                $ownerDetail['owner_mobile'],
                $ownerDetail['aadhar'],
                $ownerDetail['pan'],
                $ownerDetail['email'],
                ($ownerDetail['is_armed_force'] == true ? 'Yes' : 'No'),
                ($ownerDetail['is_specially_abled'] == true ? 'Yes' : 'No'),
            ];
        });
    }

    /**
     * | Objection Details
     */
    public function objectionDetails($objectionLists)
    {
        return collect($objectionLists)->map(function ($objectionList, $key) {
            return [
                $key + 1,
                $objectionList['type'],
                $objectionList['asses_valu'],
                $objectionList['obj_valu'],
            ];
        });
    }

    /**
     * | Objection floor details
     */

    public function generateObjectionFloorDetails($objectionFlooorDtl)
    {
        return
            [
                $objectionFlooorDtl['floor_name'],
                $objectionFlooorDtl['usage_type'],
                $objectionFlooorDtl['occupancy_type'],
                $objectionFlooorDtl['construction_type'],
                $objectionFlooorDtl['builtup_area'],
                $objectionFlooorDtl['date_from'],
                $objectionFlooorDtl['date_upto'],
            ];
    }

    /**
     * | Get GB Basic Details
     */
    public function generateGbBasicDetails($data)
    {
        return new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $data->old_ward_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'New Ward No', 'key' => 'newWardNo', 'value' => $data->new_ward_no, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Building Type', 'key' => 'buildingType', 'value' => $data->building_type, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property Usage Type', 'key' => 'propertyUsageType', 'value' => $data->prop_usage_type, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Zone', 'key' => 'zone', 'value' => ($data->zone_mstr_id == 1) ? 'Zone 1' : 'Zone 2', 'canBtc' => 'true', 'canEdit' => 'false'],
            ['displayString' => 'Property has Mobile Tower(s) ?', 'key' => 'isMobileTower', 'value' => ($data->is_mobile_tower == false) ? 'No' : 'Yes', 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property has Hoarding Board(s) ?', 'key' => 'isHoardingBoard', 'value' => ($data->is_hoarding_board == false) ? 'No' : 'Yes', 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property has Rain Water Harvesting ?', 'key' => 'isWaterHarvesting', 'value' => ($data->is_water_harvesting == false) ? 'No' : 'Yes', 'canBtc' => 'true', 'canEdit' => 'true']
        ]);
    }

    /**
     * | Generating GB Property Details
     */
    public function generateGbPropertyDetails($data)
    {
        return new Collection([
            ['displayString' => 'Building Name', 'key' => 'buildingName', 'value' => $data->building_name, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Street Name', 'key' => 'streetName', 'value' => $data->street_name, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Location', 'key' => 'location', 'value' => $data->location, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Landmark', 'key' => 'landmark', 'value' => $data->landmark, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Road Width', 'key' => 'roadWidth', 'value' => $data->road_width ?? "", 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'City', 'key' => 'city', 'value' => $data->prop_city, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'District', 'key' => 'district', 'value' => $data->prop_dist, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'State', 'key' => 'state', 'value' => $data->prop_state, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Pin', 'key' => 'pin', 'value' => $data->prop_pin_code, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Locality', 'key' => 'locality', 'value' => $data->prop_address, 'canBtc' => 'true', 'canEdit' => 'true'],
        ]);
    }

    /**
     * | Officer Details
     */
    public function generateOfficerDetails($officerDetails)
    {
        return [
            1,
            $officerDetails['officer_name'],
            $officerDetails['designation'],
            $officerDetails['mobile_no'],
            $officerDetails['email'],
            $officerDetails['address'],
        ];
    }

    /**
     * | Generate Card Details
     */
    public function generateGbCardDetails($req, $officerDetails)
    {
        return new Collection([
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->old_ward_no],
            ['displayString' => 'SAF No.', 'key' => 'safNo', 'value' => $req->saf_no],
            ['displayString' => 'Officer', 'key' => 'officerName', 'value' => $officerDetails->officer_name],
            ['displayString' => 'Assessment Type', 'key' => 'assessmentType', 'value' => $req->assessment_type],
            ['displayString' => 'Building Type', 'key' => 'buildingType', 'value' => $req->building_type, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Property Usage Type', 'key' => 'propertyUsageType', 'value' => $req->prop_usage_type, 'canBtc' => 'true', 'canEdit' => 'true'],
            ['displayString' => 'Apply-Date', 'key' => 'applyDate', 'value' => $req->application_date],
            ['displayString' => 'Plot-Area(sqt)', 'key' => 'plotArea', 'value' => $req->area_of_plot],
            ['displayString' => 'Is-Water-Harvesting', 'key' => 'isWaterHarvesting', 'value' => ($req->is_water_harvesting == true) ? 'Yes' : 'No'],
            ['displayString' => 'Is-Hoarding-Board', 'key' => 'isHoardingBoard', 'value' => ($req->is_hoarding_board == true) ? 'Yes' : 'No']
        ]);
    }

    /**
     * | Get Basic Details
     */
    public function generateForgeryType($data)
    {
        return new Collection([
            ['displayString' => 'Reason Of Forgery', 'key' => 'forgeryReason', 'value' => $data->type],
            ['displayString' => 'Additional Details', 'key' => 'remarks', 'value' => $data->remarks],
        ]);
    }



    /**
     * | Generate Demand Detail
     */
    public function generateDemandDues($demandDues)
    {
        // return $demandDues;
        // return collect($demandDues)->map(function ($ownerDetail) {
        return [
            1,
            $demandDues['duesFrom'],
            $demandDues['duesTo'],
            $demandDues['totalDues'],
            $demandDues['onePercPenalty'],
            $demandDues['rebateAmt'],
            $demandDues['payableAmount'],
        ];
        // });
    }
}
