<?php

namespace App\Traits\Trade;

use Illuminate\Support\Facades\Config;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Masters\RefRequiredDocument;
use App\Models\Trade\AkolaTradeParamItemType;
use App\Models\Trade\TradeLicence;
use App\Models\Trade\TradeOwner;
use App\Models\Trade\TradeRenewal;
use App\Models\Trade\TradeTransaction;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Repository\Common\CommonFunction;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/***
 * @Parent - App\Http\Request\AuthUserRequest
 * Author Name-Anshu Kumar
 * Created On- 27-06-2022
 * Creation Purpose- For Validating During User Registeration
 * Coding Tested By-
 */

trait TradeTrait
{
    public function generateBasicDetails($data)
    {
        return new Collection([
            ['displayString' => 'Application No', 'key' => 'applicationNo', 'value' => $data->application_no],
            ['displayString' => 'Licence For Years', 'key' => 'district', 'value' => $data->licence_for_years],
            ['displayString' => 'Holding No', 'key' => 'state', 'value' => $data->holding_no],
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $data->ward_no],
            ['displayString' => 'Zone Name', 'key' => 'zone_name', 'value' => $data->zone_name],
            ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $data->ownership_type],
            ['displayString' => 'Property Type', 'key' => 'propertyType', 'value' => $data->property_type],
            ['displayString' => 'Application Type', 'key' => 'applicationType', 'value' => $data->application_type],            
            ['displayString' => 'Firm Type', 'key' => 'firmType', 'value' => $data->firm_type],
            ['displayString' => 'Nature Of Business', 'key' => 'natureofBusiness', 'value' => $data->nature_of_bussiness],
            ['displayString' => 'K No.', 'key' => 'kNo', 'value' => $data->k_no],
            ['displayString' => 'Area In Sqft.', 'key' => 'area', 'value' => $data->area_in_sqft],
            ['displayString' => 'Account No', 'key' => 'accountNo', 'value' => $data->account_no],
            ['displayString' => 'Firm Name', 'key' => 'firmName', 'value' => $data->firm_name],
            // ['displayString' => 'Cateogry Type', 'key' => 'categoryType', 'value' => $data->category_type],
            ['displayString' => 'Firm Establishment Date', 'key' => 'establishmentDate', 'value' => $data->establishment_date],
            ['displayString' => 'Address', 'key' => 'address', 'value' => $data->address],
            ['displayString' => 'Landmark', 'key' => 'landmark', 'value' => $data->landmark],
            ['displayString' => 'Applied Date', 'key' => 'applicationDate', 'value' => $data->application_date],
            ['displayString' => 'Valid Upto', 'key' => 'validUpto', 'value' => $data->valid_upto],
        ]);
    }

    public function generatePropertyDetails($data)
    {
        return new Collection([            
            ['displayString' => 'Application No', 'key' => 'applicationNo', 'value' => $data->application_no],
            ['displayString' => 'Licence For Years', 'key' => 'district', 'value' => $data->licence_for_years],
            ['displayString' => 'Holding No', 'key' => 'state', 'value' => $data->holding_no],
            ['displayString' => 'Area', 'key' => 'area', 'value' => $data->area_in_sqft],
            ['displayString' => 'Account No', 'key' => 'accountNo', 'value' => $data->account_no],
            ['displayString' => 'Firm Name', 'key' => 'firmName', 'value' => $data->firm_name],
            ['displayString' => 'Street Name', 'key' => 'street_name', 'value' => $data->street_name],
        ]);
    }
    
    public function generatepaymentDetails($data)
    {
        return collect($data)->map(function ($val, $key) {
            return [
                $key + 1,
                $val['tran_type'],
                $val['tran_no'],
                $val['payment_mode'],
                $val['tran_date'],
                // $val['id'],

            ];
        });
    }
    public function generateOwnerDetails($ownerDetails)
    {
        return collect($ownerDetails)->map(function ($ownerDetail, $key) {
            return [
                $key + 1,
                $ownerDetail['owner_name'],
                // $ownerDetail['gender'],
                // $ownerDetail['dob'],
                $ownerDetail['guardian_name'],
                // $ownerDetail['relation_type'],
                $ownerDetail['mobile_no'],
                // $ownerDetail['aadhar_no'],
                // $ownerDetail['pan_no'],
                $ownerDetail['email'],
                // $ownerDetail['address'],

            ];
        });
    }

    public function generateCardDetails($req, $ownerDetails)
    {
        $owners = collect($ownerDetails)->implode('owner_name', ','); 
        $data = new Collection([
            ['displayString' => 'Zone No', 'key' => 'zoneNo', 'value' => $req->zone_name],
            ['displayString' => 'Ward No', 'key' => 'wardNo', 'value' => $req->ward_no],
            ['displayString' => 'Holding No', 'key' => 'holdingNo', 'value' => $req->holding_no],
            ['displayString' => 'Owner Name', 'key' => 'ownerName', 'value' => $owners],
            ['displayString' => 'Application Type', 'key' => 'applicationType', 'value' => $req->application_type],
            ['displayString' => 'Ownership Type', 'key' => 'ownershipType', 'value' => $req->ownership_type],
            ['displayString' => 'Apply-Date', 'key' => 'applyDate', 'value' => $req->application_date],
            ['displayString' => 'Area In Sqft.', 'key' => 'area', 'value' => $req->area_in_sqft],
        ]);
        if(trim($req->license_no))
        {
            $data->push(
                ['displayString' => 'License No', 'key' => 'LicenseNo', 'value' => $req->license_no]
            );
        }
        return $data;
    }


    public function getApplTypeDocList($refApplication)
    {
        $mRefReqDocs = new RefRequiredDocument();
        $applicationTypes = Config::get('TradeConstant.APPLICATION-TYPE-BY-ID');
        $moduleId = Config::get('module-constants.TRADE_MODULE_ID');
        $applicationTypeId = $refApplication->application_type_id;
        $ownershipTypeId = $refApplication->ownership_type_id;
        $firmTypeId = $refApplication->firm_type_id;
        $categoryTypeId = $refApplication->category_type_id;  
        $isHoldingAttached = trim($refApplication->holding_no) ? true : false;         
        $flip = flipConstants($applicationTypes);
        $documentList="" ;
        $mnaturOfBusiness = (new AkolaTradeParamItemType())->itemsById($refApplication->nature_of_bussiness);
        $tradeItems = !empty($mnaturOfBusiness->where("food_drug_license",true)->all() )? true : false ;      
        switch ($applicationTypeId) {
            case $flip['NEW LICENSE']:
                $documentList="" ;//= $mRefReqDocs->getDocsByDocCode($moduleId, "New_Licences")->requirements;
                $documentList= $mRefReqDocs->getDocsByDocCode($moduleId, "SHOP_ACT")->requirements;
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "NOC_A")->requirements;
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "FIRE_CLEARANCE")->requirements;
                if($tradeItems){
                    $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "FOOD&BRUG_LICENSE")->requirements;
                }
                break;
            case $flip['RENEWAL']:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "Reniwal_Licences")->requirements;
                break;
            case $flip['AMENDMENT']:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "Amendment_Licences")->requirements;
                break;
            case $flip['SURRENDER']:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "Surenderd_Licences")->requirements;    // Function (1.1)
                break;
        }
        if($applicationTypeId == $flip['NEW LICENSE'])
        {   
            if($isHoldingAttached)
            {
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "Holding_Tax")->requirements;
            }      
            // switch ($ownershipTypeId) 
            // {
            //     case 1: # OWN PROPERTY
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "Owner_Premises")->requirements;
            //         break;
            //     case 2: #ON RENT
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "On_Rent")->requirements;
            //         break;
            //     case 3:# ON LEASE
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "On_Rent")->requirements;
            //         break;
            // }
            // switch ($firmTypeId) 
            // {
            //     case 1: # PROPRIETORSHIP
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "NOC_Individual")->requirements;
            //         break;
            //     case 2: # PARTNERSHIP
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "NOC_Parter")->requirements;
            //         break;
            //     case 3:# PVT. LTD.
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "NOC_Pvt_Ltd_Com")->requirements;
            //         break;
            //     case 4: #PUBLIC LTD.
            //         $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "NOC_Pvt_Ltd_Com")->requirements;
            //         break;
                    
            // } 
            // switch ($categoryTypeId) 
            // {
            //     case 2: # Dangerous Trade
            //         $documentList .= "";//$mRefReqDocs->getDocsByDocCode($moduleId, "NOC")->requirements;
            //         break;
            // }
        }  
        $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "OTHER")->requirements??""; 
        $documentList = $this->filterDocument($documentList,$refApplication);
        return $documentList;
    }
    /**
     * | Filter Document(1.2)
     */
    public function filterDocument($documentList, $refApplication, $ownerId = null)
    {
        $mWfActiveDocument = new WfActiveDocument();
        $applicationId = $refApplication->id;
        $workflowId = $refApplication->workflow_id;
        $moduleId = Config::get('module-constants.TRADE_MODULE_ID');
        $uploadedDocs = $mWfActiveDocument->getDocByRefIds($applicationId, $workflowId, $moduleId);
        $explodeDocs = collect(explode('#', $documentList))->filter();

        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs, $ownerId) {
            $document = explode(',', $explodeDoc);
            $key = array_shift($document);
            $docName =  array_shift($document);
            $docName = str_replace("{","",str_replace("}","",$docName));
            $documents = collect();
            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId,$docName) {

                $uploadedDoc = $uploadedDocs->where('doc_code', $docName)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                if ($uploadedDoc) {
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode" => $item,
                        "ownerId" => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath" => $uploadedDoc->doc_path ?? "",
                        "verifyStatus" => $uploadedDoc->verify_status ?? "",
                        "remarks" => $uploadedDoc->remarks ?? "",
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType'] = $key;
            $reqDoc['docName'] = $docName;
            $reqDoc['uploadedDoc'] = $documents->first();

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                $arr = [
                    "documentCode" => $doc,
                    "docVal" => ucwords($strReplace),
                    "uploadedDoc" => $uploadedDoc->doc_path ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $uploadedDoc->verify_status ?? "",
                    "remarks" => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });
        return collect($filteredDocs)->values()??[];
    }
    public function getOwnerDocLists($refOwners, $refApplication)
    {
        $WfActiveDocument = new WfActiveDocument();
        $moduleId = Config::get('module-constants.TRADE_MODULE_ID');
        $documentList = $this->getOwnerDocs($refApplication);
        if (!empty($documentList)) 
        {
            $filteredDocs['ownerDetails'] = [
                'ownerId' => $refOwners['id'],
                'name' => $refOwners['owner_name'],
                'mobile' => $refOwners['mobile_no'],
                'guardian' => $refOwners['guardian_name'],
            ];
            $filteredDocs['documents']= $this->filterDocument($documentList, $refApplication, $refOwners['id']); 
                                               // function(1.2)
            $OwnerImage = ((($filteredDocs['documents']->where("docName","PHOTO WITH A DEPICTION OF THE SHOP")->first())["uploadedDoc"])??[]);
            $filteredDocs['ownerDetails']["uploadedDoc"]= $OwnerImage["docPath"]??null;
            $filteredDocs['ownerDetails']["verifyStatus"]= $OwnerImage["verifyStatus"]??null;

        } 
        else
        {
            $filteredDocs = [];
        }
        return $filteredDocs;
    }

    public function getOwnerDocs($refApplication)
    {
        $mRefReqDocs = new RefRequiredDocument();
        $applicationTypes = Config::get('TradeConstant.APPLICATION-TYPE-BY-ID');
        $moduleId = Config::get('module-constants.TRADE_MODULE_ID');
        $applicationTypeId = $refApplication->application_type_id;
        $flip = flipConstants($applicationTypes);

        $parentIds = trim($refApplication->parent_ids.",".$refApplication->id,",");
        $parentIds = explode(",",$parentIds?$parentIds:$refApplication->id); 
        $oldOwnersId = TradeOwner::whereIN("temp_id",$parentIds)
                        ->where("is_active",true)
                        ->orderby("id","ASC")
                        ->first();
        $owner_doc = WfActiveDocument::whereIn("active_id",$parentIds)
                    ->where("workflow_id",$refApplication->workflow_id)
                    ->where("owner_dtl_id",$oldOwnersId->id??0)
                    ->where("doc_code","Owner Image")
                    ->where("status","<>",0)
                    ->first();

        switch ($applicationTypeId) {
            case $flip['NEW LICENSE']:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "PHOTO")->requirements;
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "New_Licences_Owneres")->requirements;
                break;
            case $flip['RENEWAL'] && !$owner_doc:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "PHOTO")->requirements;
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "New_Licences_Owneres")->requirements;
                break;
            case $flip['AMENDMENT']:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "PHOTO")->requirements;
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "New_Licences_Owneres")->requirements;
                break;
            case $flip['SURRENDER']:
                $documentList = $mRefReqDocs->getDocsByDocCode($moduleId, "PHOTO")->requirements;
                $documentList .= $mRefReqDocs->getDocsByDocCode($moduleId, "New_Licences_Owneres")->requirements;
                break;
            default :  $documentList = collect([]);
        }
        return $documentList;
    }

    public function giveValidity($refLicenc)
    {
        try{
            $commonFuction = new CommonFunction();
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $refWorkflowId = Config::get('workflow-constants.TRADE_MASTER_ID');
            $role = $commonFuction->getUserRoll($user_id, $ulb_id,$refWorkflowId);
            $init_finish = $commonFuction->iniatorFinisher($user_id, $ulb_id, $refWorkflowId);
            $licence_for_years = $refLicenc->licence_for_years?$refLicenc->licence_for_years:1;
            # 1	NEW LICENSE
            if ($refLicenc->application_type_id == 1) 
            {
                $ulbDtl = UlbMaster::find($refLicenc->ulb_id);
                $ulb_name = explode(' ', $ulbDtl->ulb_name);
                $short_ulb_name = "";
                foreach ($ulb_name as $val) 
                {
                    $short_ulb_name .= $val[0];
                }
                $ward_no = UlbWardMaster::select("ward_name")
                    ->where("id", $refLicenc->ward_id)
                    ->first();
                $ward_no = $ward_no['ward_name'];
                $license_no = $short_ulb_name . $ward_no .  $refLicenc->id;
                // $valid_from = $refLicenc->application_date;
                // $valid_upto = date("Y-m-d", strtotime("+$licence_for_years years", strtotime("-1 days",strtotime($refLicenc->application_date))));
                list($valid_from,$valid_upto) = FyearFromUptoDate();
                // $valid_from = getFY();
                // $valid_upto = date("Y-m-d", strtotime("+$licence_for_years years", strtotime("-1 days",strtotime($refLicenc->application_date))));
            }
            # 2 RENEWAL
            if ($refLicenc->application_type_id == 2) 
            {
                $prive_licence = TradeLicence::find($refLicenc->trade_id);
                if (!empty($prive_licence)) 
                {
                    $prive_licence_id = $prive_licence->id;
                    $license_no = $prive_licence->license_no;
                    $valid_from = $prive_licence->valid_upto; 
                    $datef = date('Y-m-d', strtotime($valid_from));
                    $datefrom = date_create($datef);
                    $datea = date('Y-m-d', strtotime($refLicenc->application_date));
                    $dateapply = date_create($datea);
                    $year_diff = date_diff($datefrom, $dateapply);
                    $year_diff =  $year_diff->format('%y');

                    $priv_m_d = date('m-d', strtotime($valid_from));
                    $date = date('Y', strtotime($valid_from)) . '-' . $priv_m_d;
                    $licence_for_years2 = $licence_for_years + $year_diff;
                    // list($valid_from,$valid_upto) = FyearFromUptoDate();
                    $valid_upto = date('Y-m-d', strtotime("-1 days",strtotime($date . "+" . $licence_for_years2 . " years")));
                    $data['valid_upto'] = $valid_upto;
                    $this->addReniwalLicense($prive_licence);
                } 
                else 
                {
                    throw new Exception('licence', 'Some Error Occurred Please Contact to Admin!!!');
                }
            }

            # 3	AMENDMENT
            if ($refLicenc->application_type_id == 3) 
            {
                $prive_licence = TradeLicence::find($refLicenc->trade_id);
                $license_no = $prive_licence->license_no;
                $oneYear_validity = date("Y-m-d", strtotime("-1 days",strtotime("+1 years", strtotime('now'))));
                $previous_validity = $prive_licence->valid_upto;
                if ($previous_validity > $oneYear_validity)
                    $valid_upto = $previous_validity;
                else
                    $valid_upto = $oneYear_validity;
                $valid_from = date('Y-m-d');
                // list($valid_from,$valid_upto) = FyearFromUptoDate();
                $this->addReniwalLicense($prive_licence);
            }

            # 4 SURRENDER
            if ($refLicenc->application_type_id == 4) 
            {
                # Incase of surrender valid upto is previous license validity
                $prive_licence = TradeLicence::find($refLicenc->trade_id);
                $license_no = $prive_licence->license_no;
                $valid_from = $prive_licence->valid_from;
                $valid_upto = $prive_licence->valid_upto;
                $this->addReniwalLicense($prive_licence);
            }
            $refLicenc->license_date = Carbon::now()->format("Y-m-d");
            $refLicenc->valid_from = $valid_from;
            $refLicenc->valid_upto = $valid_upto;
            $refLicenc->license_no = $license_no;
            $refLicenc->current_role = $role->role_id;
           return true;
        }
        catch(Exception $e)
        {
            return false;
        }
    }
    public function addReniwalLicense(TradeLicence $refTradeLicense)
    {
        $ReniwalLicence = $refTradeLicense->replicate();
        $ReniwalLicence->setTable('trade_renewals');
        $ReniwalLicence->id = $refTradeLicense->id;
        $ReniwalLicence->save();
        $transection = TradeTransaction::select("*")
                    ->where("temp_id",$refTradeLicense->id)
                    ->orderBy("tran_date","DESC")
                    ->first();
        $ReniwalLicence->is_active      = true;
        $ReniwalLicence->tran_id        = $transection->id??null;
        $ReniwalLicence->rate_id        = $transection->rate_id??null;
        $ReniwalLicence->demand_amount  = $transection->paid_amount??null;
        $ReniwalLicence->fine_amount    = $transection->penalty??null;
        $ReniwalLicence->penalty_amount = $transection->penalty??null;
        $ReniwalLicence->rebate_amount  = $transection->rebate??null;
        $ReniwalLicence->tax_percent    = $transection->tax_percent??null;
        $ReniwalLicence->tax_amount	    = (($transection->paid_amount??0) - ($transection->penalty??0));
        $ReniwalLicence->total_taxable_amount = null;
        $ReniwalLicence->payable_amount = null;
        $ReniwalLicence->pmt_amount     = null;
        $ReniwalLicence->update();
        $refTradeLicense->forceDelete();

    }

    #this code use only printable data
    public function tempCalLicenseForYear($refLicenc)
    {
        $licence_for_years = $refLicenc->licence_for_years;
        
        if((!$refLicenc->valid_from || !$refLicenc->valid_upto) &&  !$licence_for_years)
        {
            $transection = TradeTransaction::select("*")
                        ->where("temp_id",$refLicenc->id)
                        ->orderBy("tran_date","DESC")
                        ->first();
            $licenseCharge = ($transection->paid_amount??0)-($transection->penalty??0);
            $area_in_sqft  = $refLicenc->area_in_sqft??0;
            $data["application_type_id"] = $refLicenc->application_type_id;
            $data["area_in_sqft"] = $area_in_sqft;
            $data["curdate"] = $refLicenc->application_date;
            $data["tobacco_status"] = $refLicenc->is_tobacco?1:0;
            $char = $this->getrate($data);
            $testCharge = 0;
            $testYear = 0;
            while($licenseCharge>=$testCharge && $testYear <=10 && $char)
            {
                $testCharge+=$char->rate??0;
                $testYear++;
            }
            $licence_for_years=$testYear-1;

        }        
        if(($refLicenc->valid_from && $refLicenc->valid_upto) &&  !$licence_for_years)
        {
            $formDate = Carbon::parse($refLicenc->valid_from);
            $endDate = Carbon::parse($refLicenc->valid_upto);
            $monthDiff = $endDate->diffInMonths($formDate);
            if(($monthDiff%12)>=10)
            {
                $year_diff = ceil($monthDiff/12);
            }
            else
            {
                $year_diff = floor($monthDiff/12);
            }
            $licence_for_years=$year_diff;
        }
        return abs($licence_for_years);
    }

    #this code use only printable data
    public function temCalValidity($refLicenc)
    {
        $valid_from ="";
        $valid_upto = "";
        if(!$refLicenc->licence_for_years)
        {
            $refLicenc->licence_for_years=($this->tempCalLicenseForYear($refLicenc));
        }
        $licence_for_years = $refLicenc->licence_for_years;
        
        # 1	NEW LICENSE
        if ($refLicenc->application_type_id == 1) 
        {
            $valid_from = $refLicenc->application_date;
            $valid_upto = date("Y-m-d", strtotime("+$licence_for_years years", strtotime("-1 days",strtotime($refLicenc->application_date))));
        }
        # 2 RENEWAL
        if ($refLicenc->application_type_id == 2) 
        {
            $prive_licence = TradeLicence::find($refLicenc->trade_id);
            if (!empty($prive_licence)) 
            {
                $valid_from = $prive_licence->valid_upto??Carbon::parse(); 
                $datef = date('Y-m-d', strtotime($valid_from));
                $datefrom = date_create($datef);
                $datea = date('Y-m-d', strtotime($refLicenc->application_date));
                $dateapply = date_create($datea);
                $year_diff = date_diff($datefrom, $dateapply);
                $year_diff =  $year_diff->format('%y');

                $priv_m_d = date('m-d', strtotime($valid_from));
                $date = date('Y', strtotime($valid_from)) . '-' . $priv_m_d;
                $licence_for_years2 = $licence_for_years + $year_diff;
                $valid_upto = date('Y-m-d', strtotime("-1 days",strtotime($date . "+" . $licence_for_years2 . " years")));
                $data['valid_upto'] = $valid_upto;
            }
        }

        # 3	AMENDMENT
        if ($refLicenc->application_type_id == 3) 
        {
            $prive_licence = TradeLicence::find($refLicenc->trade_id);
            $oneYear_validity = date("Y-m-d", strtotime("-1 days",strtotime("+1 years", strtotime('now'))));
            $previous_validity = $prive_licence->valid_upto??"";
            if ($previous_validity > $oneYear_validity)
                $valid_upto = $previous_validity;
            else
                $valid_upto = $oneYear_validity;
            $valid_from = date('Y-m-d');
        }

        # 4 SURRENDER
        if ($refLicenc->application_type_id == 4) 
        {
            # Incase of surrender valid upto is previous license validity
            $prive_licence = TradeLicence::find($refLicenc->trade_id);
            $valid_from = $prive_licence->valid_from??"";
            $valid_upto = $prive_licence->valid_upto??"";
        }
        list($valid_from,$valid_upto) = FyearFromUptoDate();
        if(!$refLicenc->valid_from)
            $refLicenc->valid_from = $valid_from?date("d-m-Y",strtotime($valid_from)):"";
        if(!$refLicenc->valid_upto)
            $refLicenc->valid_upto =  $valid_upto?date("d-m-Y",strtotime($valid_upto)):"";
    }
    
}
