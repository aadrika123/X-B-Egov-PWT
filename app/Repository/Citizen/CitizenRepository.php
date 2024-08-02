<?php

namespace App\Repository\Citizen;

use App\Http\Controllers\Property\HoldingTaxController;
use Illuminate\Http\Request;
use App\Repository\Citizen\iCitizenRepository;
use App\Models\ActiveCitizen;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Payment\PaymentRequest;
use App\Models\Property\PropLevelPending;
use App\Models\Property\PropProperty;
use App\Models\Trade\ActiveLicence;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\TradeLicence;
use App\Models\User;
use App\Models\Water\WaterApplication;
use App\Models\WorkflowTrack;
use App\Traits\Auth;
use App\Traits\Workflow\Workflow;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * | Created On-08-08-2022 
 * | Created By-Anshu Kumar.
 * -------------------------------------------------------------------------------------------
 * | Eloquent For Citizen Registration and Approval
 */

class CitizenRepository implements iCitizenRepository
{
    private $_appliedApplications;
    private $_redis;

    use Auth, Workflow;

    public function __construct()
    {
        $this->_redis = Redis::connection();
    }



    /**
     * | Get Citizens by ID
     * | @param Citizen-id $id
     * | join with ulb_masters
     */
    public function getCitizenByID($id)
    {
        $citizen = ActiveCitizen::select('active_citizens.id', 'user_name', 'mobile', 'email', 'user_type', 'ulb_id', 'is_approved', 'ulb_name')
            ->where('active_citizens.id', $id)
            ->leftJoin('ulb_masters', 'active_citizens.ulb_id', '=', 'ulb_masters.id')
            ->first();
        return $citizen;
    }

    /**
     * | Get All Citizens
     * | Join With ulb_masters
     */
    public function getAllCitizens()
    {
        $citizen = ActiveCitizen::select('active_citizens.id', 'user_name', 'mobile', 'email', 'user_type', 'ulb_id', 'is_approved', 'ulb_name')
            ->where('is_approved', null)
            ->leftJoin('ulb_masters', 'active_citizens.ulb_id', '=', 'ulb_masters.id')
            ->get();
        return $citizen;
    }


    /**
     * | Get All Applied Applications of All Modules
     */
    public function getAllAppliedApplications($req)
    {
        try {
            $userId = authUser($req)->id ?? $req->userId;
            $applications = array();

            if ($req->getMethod() == 'GET') {                                                       // For All Applications
                $property = $this->appliedSafApplications($userId);
                $applications['Property'] = $property;
                $applications['Property']['totalApplications'] = $this->countTotalApplications($property);

                $waters = $this->appliedWaterApplications($userId);
                $applications['Water'] = $waters;

                $applications['Trade'] = $this->appliedTradeApplications($userId);
                $applications['CareTaker'] = $this->getCaretakerProperty($userId);
                $applications['Holding'] = $this->getProperties($userId, $applications['CareTaker']);
            }

            if ($req->getMethod() == 'POST') {                                                      // Get Applications By Module
                if ($req->module == 'Property') {
                    $applications['Property'] = $this->appliedSafApplications($userId);
                }

                if ($req->module == 'Water') {
                    $applications['Water'] = $this->appliedWaterApplications($userId);
                }

                if ($req->module == 'Trade') {
                    $applications['Trade'] = $this->appliedTradeApplications($userId);
                }

                if ($req->module == 'Holding') {
                    $applications['Holding'] = $this->getCitizenProperty($userId);
                }

                if ($req->module == 'careTaker') {
                    $applications['CareTaker'] = $this->getCaretakerProperty($userId);
                }

                if ($req->module == 'careTakeTrade')
                    $applications['CareTakerTrade'] = $this->getCareTakerTrade($userId);
            }

            return responseMsg(true, "All Applied Applications", remove_null($applications));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Count Total
     */
    public function countTotalApplications($applications)
    {
        $counters = collect();
        foreach ($applications as $application) {
            $numberOfApplications = collect($application)->count();
            $counters->push($numberOfApplications);
        }
        $totalCounter = $counters->sum();
        return $totalCounter;
    }

    /**
     * | Applied Saf Applications
     * | Redis Should Be delete on SAF Apply, Trade License Apply and Water Apply
     * | Status - Closed
     */
    public function appliedSafApplications($userId)
    {
        $applications = array();
        $propertyApplications = DB::table('prop_active_safs')
            ->leftJoin('wf_roles as r', 'r.id', '=', 'prop_active_safs.current_role')
            ->leftJoin('prop_transactions as t', 't.saf_id', '=', 'prop_active_safs.id')
            ->select(
                'prop_active_safs.id as application_id',
                'saf_no',
                'pt_no',
                'holding_no',
                'assessment_type',
                'current_role',
                'r.role_name as current_level',
                DB::raw("TO_CHAR(application_date, 'DD-MM-YYYY') as application_date,
                    CASE WHEN prop_active_safs.current_role = prop_active_safs.initiator_role_id THEN TRUE
                        WHEN prop_active_safs.current_role IS NULL THEN TRUE
                        ELSE FALSE
                    END AS citizen_can_edit
                "),
                'applicant_name',
                'payment_status',
                'doc_upload_status',
                'saf_pending_status',
                'parked as backToCitizen',
                'workflow_id',
                'prop_active_safs.created_at',
                'prop_active_safs.updated_at',
                't.tran_no as transaction_no',
                't.tran_date as transaction_date',
                'prop_active_safs.is_agency_verified',
                'prop_active_safs.is_field_verified as is_ulb_verified',
            )
            ->where('prop_active_safs.citizen_id', $userId)
            ->where('prop_active_safs.status', 1)
            ->orderByDesc('prop_active_safs.id')
            ->get();
        $applications['SAF'] = collect($propertyApplications)->values();

        $concessionApplications = DB::table('prop_active_concessions')
            ->join('wf_roles as r', 'r.id', '=', 'prop_active_concessions.current_role')
            ->join('prop_properties as p', 'p.id', '=', 'prop_active_concessions.property_id')
            ->select(
                'prop_active_concessions.id as application_id',
                'prop_active_concessions.application_no',
                'prop_active_concessions.applicant_name',
                DB::raw("TO_CHAR(prop_active_concessions.date, 'DD-MM-YYYY') as apply_date"),
                'p.holding_no',
                'p.new_holding_no',
                'p.pt_no',
                'r.role_name as pending_at',
                'prop_active_concessions.workflow_id'
            )
            ->where('prop_active_concessions.citizen_id', $userId)
            ->get();
        $applications['concessions'] = $concessionApplications;

        $objectionApplications = DB::table('prop_property_update_requests')
            ->select('prop_property_update_requests.*','prop_owner_update_requests.owner_id','prop_owner_update_requests.owner_name','prop_owner_update_requests.guardian_name','prop_owner_update_requests.mobile_no','prop_owner_update_requests.email','prop_owner_update_requests.dob','prop_floors_update_requests.floor_id','prop_floors_update_requests.builtup_area','prop_floors_update_requests.carpet_area','prop_floors_update_requests.date_from','prop_floors_update_requests.date_upto')
            ->leftjoin('prop_owner_update_requests','prop_owner_update_requests.request_id','=','prop_property_update_requests.id')
            ->leftjoin('prop_floors_update_requests','prop_floors_update_requests.request_id','=','prop_property_update_requests.id')
            ->where('prop_property_update_requests.citizen_id', $userId)
            ->where('prop_property_update_requests.pending_status',1)
            ->get();
        $applications['objections'] = $objectionApplications;

        $harvestingApplications = DB::table('prop_active_harvestings')
            ->join('wf_roles as r', 'r.id', '=', 'prop_active_harvestings.current_role')
            ->join('prop_properties as p', 'p.id', '=', 'prop_active_harvestings.property_id')
            ->join('prop_owners', 'prop_owners.property_id', '=', 'p.id')
            ->select(
                'prop_active_harvestings.id as application_id',
                'prop_active_harvestings.application_no',
                DB::raw("TO_CHAR(prop_active_harvestings.date, 'DD-MM-YYYY') as apply_date"),
                DB::raw("string_agg(prop_owners.owner_name,',') as applicant_name"),
                'p.holding_no',
                'p.new_holding_no',
                'p.pt_no',
                'r.role_name as pending_at',
                'prop_active_harvestings.workflow_id'
            )
            ->where('prop_active_harvestings.citizen_id', $userId)
            ->groupBy('prop_active_harvestings.id', 'p.id', 'r.role_name')
            ->get();
        $applications['harvestings'] = $harvestingApplications;

        $pendingProcessFeeApplications = DB::table('prop_safs')
            ->leftJoin('wf_roles as r', 'r.id', '=', 'prop_safs.current_role')
            ->select(
                'prop_safs.id as application_id',
                'saf_no',
                'pt_no',
                'holding_no',
                'assessment_type',
                'r.role_name as current_level',
                DB::raw("TO_CHAR(application_date, 'DD-MM-YYYY') as application_date,
                    CASE WHEN prop_safs.current_role = prop_safs.initiator_role_id THEN TRUE
                        WHEN prop_safs.current_role IS NULL THEN TRUE
                        ELSE FALSE
                    END AS citizen_can_edit
                "),
                'applicant_name',
                'payment_status',
                'doc_upload_status',
                'saf_pending_status',
                'parked as backToCitizen',
                'workflow_id',
                'prop_safs.created_at',
                'prop_safs.updated_at',
                'prop_safs.is_agency_verified',
                'prop_safs.is_field_verified as is_ulb_verified',
                'prop_safs.proccess_fee_paid'
            )
            ->where('prop_safs.citizen_id', $userId)
            ->where('prop_safs.status', 1)
            ->where('prop_safs.proccess_fee_paid', 0)
            ->orderByDesc('prop_safs.id')
            ->get();
        $applications['pendingProcessFeeSaf'] = collect($pendingProcessFeeApplications)->values();

        return collect($applications);
    }

    /**
     * | Applied Water Applications
     * | Status-Closed
     */
    public function appliedWaterApplications($userId)
    {
        $applications = array();
        $waterApplications = WaterApplication::select('id as application_id', 'category', 'application_no', 'holding_no', 'workflow_id', 'created_at', 'updated_at')
            ->where('user_id', $userId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->get();
        $applications['applications'] = $waterApplications;
        $applications['totalApplications'] = $waterApplications->count();
        return collect($applications)->reverse();
    }

    /**
     * | Applied Trade Applications
     * | Status- Closed
     */
    public function appliedTradeApplications($userId)
    {
        $applications = array();
        $tradeApplications = ActiveTradeLicence::select('id as application_id', 'application_no', 'holding_no', 'workflow_id', 'created_at', 'updated_at')
            ->where('citizen_id', $userId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->get();
        $applications['applications'] = $tradeApplications;
        $applications['totalApplications'] = $tradeApplications->count();
        return collect($applications)->reverse();
    }

    /**
     * | Get User Property List by UserID
     */
    public function getCitizenProperty($userId)
    {
        try {
            $application = array();
            $query = "SELECT  p.id AS prop_id,
                                p.pt_no,
                                p.holding_no,
                                p.new_holding_no,
                                p.application_date AS apply_date,
                                o.owner_name,
                                p.balance AS leftAmount,
                              
                                t.tran_date AS lastPaidDate,
                                p.status    AS active_status,
                                p.prop_address,
                                zone_masters.zone_name,
                                ulb_ward_masters.ward_name,
                                t.payment_mode,
                              

                COALESCE(t.demand_amt, 0) AS totalTax,
                COALESCE(t.amount, 0) AS lastPaidAmount,
                (COALESCE(t.demand_amt, 0) - COALESCE(t.amount, 0)) AS balanceCalculate
                
    
                                FROM prop_properties p
                                LEFT JOIN (
                                    SELECT property_id,amount,tran_date,payment_mode,demand_amt,
                                        ROW_NUMBER() OVER(
                                            PARTITION BY property_id
                                            ORDER BY id desc
                                        ) AS row_num
                                    FROM prop_transactions 
                                    ORDER BY id DESC
                                ) AS t ON t.property_id=p.id AND row_num =1
                                   JOIN zone_masters ON zone_masters.id = p.zone_mstr_id
                                  join ulb_ward_masters on  ulb_ward_masters.id =  p.ward_mstr_id
                                LEFT JOIN (
                                    SELECT property_id,owner_name,
                                    row_number() over(
                                        partition BY property_id
                                        ORDER BY id ASC
                                    ) AS ROW1
                                    FROM prop_owners 
                                    ORDER BY id ASC 
                                    ) AS o ON o.property_id=p.id AND ROW1=1
                                    WHERE p.citizen_id=$userId   
                                    AND   p.status = 1";
            $properties = DB::select($query);
            $application['applications'] = $properties;
            $application['totalApplications'] = collect($properties)->count();
            return collect($application);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    //
    public function getCaretakerProperty($userId)
    {
        $data = array();
        $mActiveCitizenCareTaker = new ActiveCitizenUndercare();
        $propertiesByCitizen = $mActiveCitizenCareTaker->getTaggedPropsByCitizenId($userId);
        $propIds =  ($propertiesByCitizen->pluck('property_id'));
        if ($propIds->isEmpty())
            return $data;
        // throw new Exception('No Caretaker');

        foreach ($propIds as $propId) {
            if (!$propId)
                continue;
            $propdtl =  PropProperty::where('prop_properties.id', $propId)
                ->select(
                    'prop_properties.id',
                    'prop_properties.holding_no',
                    'prop_properties.new_holding_no',
                    DB::raw("TO_CHAR(application_date, 'DD-MM-YYYY') as application_date"),
                    'prop_owners.owner_name',
                    'prop_properties.balance',
                    'prop_transactions.amount',
                    DB::raw("TO_CHAR(prop_transactions.tran_date, 'DD-MM-YYYY') as tran_date"),

                )
                ->join('prop_owners', 'prop_owners.property_id', 'prop_properties.id')
                ->leftjoin('prop_transactions', 'prop_transactions.property_id', 'prop_properties.id')
                ->orderBydesc('prop_transactions.id')
                ->first();

            $propDtls = [
                'prop_id' => $propdtl->id,
                'holding_no' => $propdtl->holding_no,
                'new_holding_no' => $propdtl->new_holding_no,
                'apply_date' => $propdtl->application_date,
                'owner_name' => $propdtl->owner_name,
                'leftamount' => $propdtl->balance,
                'lastpaidamount' => $propdtl->amount,
                'lastpaiddate' => $propdtl->tran_date,
            ];

            array_push($data, $propDtls);
        }
        return collect($data);
    }

    /**
     * | Get care taker trade
     */
    public function getCareTakerTrade($userId)
    {
        $data = collect();
        $mTradeLicense = new TradeLicence;
        $mActiveCitizenCareTaker = new ActiveCitizenUndercare();
        $details = $mActiveCitizenCareTaker->getDetailsByCitizenId($userId);
        $licenses = $details->pluck('license_id');

        foreach ($licenses as $license) {
            if (!$license)
                continue;
            $license = $mTradeLicense->getTradeDtlsByLicenseNo($license);
            $data->push($license);
        }

        return $data;
    }


    /**
     * | Total Pet Registrations
     */
    public function getPetRegistrations($userId)
    {
        $applications = array();

        $registrations = DB::table('pet_active_registrations')
            ->where('citizen_id', $userId)
            ->where('status', 1)
            ->get();
        $applications['applications'] = $registrations;
        $applications['totalApplications'] = $registrations->count();
        return $applications;
    }


    /**
     * | Get Total Properties
     */
    public function getProperties($userId, $careTakerProperties)
    {
        $applications = array();
        $properties = PropProperty::select('holding_no', 'applicant_name', 'application_date')
            ->where('citizen_id', $userId)
            ->get();

        $applications['applications'] = $properties;
        $totalCareTakers = collect($careTakerProperties)->count();
        $applications['totalApplications'] = $properties->count() + $totalCareTakers;
        return $applications;
    }

    /**
     * | Independent Comment for the Citizen on their applications
     * | @param req requested parameters
     * | Status-Closed
     */
    public function commentIndependent($req)
    {
        $path = storage_path() . "/json/workflow.json";
        $json = json_decode(file_get_contents($path), true);                                                    // get Data from the storage path workflow
        $collection = collect($json['workflowId']);
        $refTable = collect($collection)->where('id', 4)->first();

        $array = array();
        $array['workflowId'] = $req->workflowId;
        $array['citizenId'] = authUser($req)->id;
        $array['refTableId'] = $refTable['workflow_name'] . '.id';
        $array['applicationId'] = $req->applicationId;
        $array['message'] = $req->message;

        $workflowTrack = new WorkflowTrack();
        $this->workflowTrack($workflowTrack, $array);                                                            // Trait For Workflow Track
        $workflowTrack->save();
        return responseMsg(true, "Successfully Given the Message", "");
    }

    /**
     * | Get Transaction History
     */
    public function getTransactionHistory($req)
    {
        try {
            $userId = authUser($req)->id;
            $trans = PaymentRequest::where('citizen_id', $userId)
                ->get();
            return responseMsg(true, "Data Fetched", remove_null($trans));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
}
