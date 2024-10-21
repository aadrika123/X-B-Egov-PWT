<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property\PropActiveConcession;
use App\Models\Property\PropActiveDeactivationRequest;
use App\Models\Property\PropActiveGbOfficer;
use App\Models\Property\PropActiveHarvesting;
use App\Models\Property\PropActiveObjection;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropConcession;
use App\Models\Property\PropDeactivationRequest;
use App\Models\Property\PropDemand;
use App\Models\Property\PropGbofficer;
use App\Models\Property\PropHarvesting;
use App\Models\Property\PropObjection;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsOwner;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Repository\Property\Interfaces\iPropertyDetailsRepo;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyDetailsController extends Controller
{
    /**
     * | Created On-26-11-2022 
     * | Modified by-Anshu Kumar On-(17/01/2023)
     * --------------------------------------------------------------------------------------
     * | Controller regarding with Propery Module (Property Details)
     * | Status-Open
     */

    // Construction 
    private $propertyDetails;
    public function __construct(iPropertyDetailsRepo $propertyDetails)
    {
        $this->propertyDetails = $propertyDetails;
    }

    // get details of the property filtering with the provided details
    public function applicationsListByKey(Request $request)
    {
        $request->validate([
            'searchBy' => 'required',
            'filteredBy' => 'required',
            'value' => 'required',
        ]);
        try {

            $mPropActiveSaf = new PropActiveSaf();
            $mPropActiveConcessions = new PropActiveConcession();
            $mPropActiveObjection = new PropActiveObjection();
            $mPropActiveHarvesting = new PropActiveHarvesting();
            $mPropActiveDeactivationRequest = new PropActiveDeactivationRequest();
            $mPropSafs = new PropSaf();
            $mPropConcessions = new PropConcession();
            $mPropObjection = new PropObjection();
            $mPropHarvesting = new PropHarvesting();
            $mPropDeactivationRequest = new PropDeactivationRequest();
            $searchBy = $request->searchBy;
            $key = $request->filteredBy;
            $perPage = $request->perPage ?? 10;

            //search by application no.
            if ($searchBy == 'applicationNo') {
                $applicationNo = $request->value;
                switch ($key) {

                    case ("saf"):
                        $approved  = $mPropSafs->searchSafs()
                            ->where('prop_safs.saf_no', strtoupper($applicationNo))
                            ->groupby('prop_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        $active = $mPropActiveSaf->searchSafs()
                            ->where('prop_active_safs.saf_no', strtoupper($applicationNo))
                            //->where('prop_active_safs.citizen_id', null)
                            ->groupby('prop_active_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        // $details = $approved->union($active)->get();
                        break;

                    case ("gbsaf"):
                        $approved =  $mPropSafs->searchGbSafs()
                            ->where('prop_safs.saf_no', strtoupper($applicationNo));

                        $active =  $mPropActiveSaf->searchGbSafs()
                            ->where('prop_active_safs.saf_no', strtoupper($applicationNo));

                        // $details = $approved->union($active)->get();
                        break;

                    case ("concession"):
                        $approved = $mPropConcessions->searchConcessions()
                            ->where('prop_concessions.application_no', strtoupper($applicationNo));

                        $active = $mPropActiveConcessions->searchConcessions()
                            ->where('prop_active_concessions.application_no', strtoupper($applicationNo));

                        // $details = $approved->union($active)->get();
                        break;

                    case ("objection"):
                        $approved = $mPropObjection->searchObjections()
                            ->where('prop_objections.objection_no', strtoupper($applicationNo));

                        $active = $mPropActiveObjection->searchObjections()
                            ->where('prop_active_objections.objection_no', strtoupper($applicationNo));

                        // $details = $approved->union($active)->get();
                        break;

                    case ("harvesting"):
                        $approved = $mPropHarvesting->searchHarvesting()
                            ->where('application_no', strtoupper($applicationNo));

                        $active = $mPropActiveHarvesting->searchHarvesting()
                            ->where('application_no', strtoupper($applicationNo));

                        // $details = $approved->union($active)->get();
                        break;

                    case ('holdingDeactivation'):
                        $approved = $mPropDeactivationRequest->getDeactivationApplication()
                            ->where('prop_deactivation_requests.application_no', strtoupper($applicationNo));

                        $active = $mPropActiveDeactivationRequest->getDeactivationApplication()
                            ->where('prop_active_deactivation_requests.application_no', strtoupper($applicationNo));

                        // $details = $approved->union($active)->get();
                        break;
                }
            }

            // search by name
            if ($searchBy == 'name') {
                $ownerName = $request->value;
                switch ($key) {
                    case ("saf"):
                        $approved  = $mPropSafs->searchSafs()
                            ->where('so.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%')
                            ->groupby('prop_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        $active = $mPropActiveSaf->searchSafs()
                            ->where('so.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%')
                            ->groupby('prop_active_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        // $details = $approved->union($active)->get();
                        break;

                    case ("gbsaf"):
                        $approved =  $mPropSafs->searchGbSafs()
                            ->where('gbo.officer_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        $active =  $mPropActiveSaf->searchGbSafs()
                            ->where('gbo.officer_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("concession"):
                        $approved = $mPropConcessions->searchConcessions()
                            ->where('prop_owners.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        $active = $mPropActiveConcessions->searchConcessions()
                            ->where('prop_owners.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("objection"):
                        $approved = $mPropObjection->searchObjections()
                            ->where('prop_owners.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        $active = $mPropActiveObjection->searchObjections()
                            ->where('prop_owners.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("harvesting"):
                        $approved = $mPropHarvesting->searchHarvesting()
                            ->where('prop_owners.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        $active = $mPropActiveHarvesting->searchHarvesting()
                            ->where('prop_owners.owner_name', 'LIKE', '%' . strtoupper($ownerName) . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ('holdingDeactivation'):
                        $details = 'No Data Found';
                        break;
                }
            }

            // search by mobileNo
            if ($searchBy == 'mobileNo') {
                $mobileNo = $request->value;
                switch ($key) {
                    case ("saf"):
                        $approved  = $mPropSafs->searchSafs()
                            ->where('so.mobile_no', 'LIKE', '%' . $mobileNo . '%')
                            ->groupby('prop_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        $active = $mPropActiveSaf->searchSafs()
                            ->where('so.mobile_no', 'LIKE', '%' . $mobileNo . '%')
                            ->groupby('prop_active_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        // $details = $approved->union($active)->get();
                        // $details = (object)$details;
                        break;
                    case ("gbsaf"):
                        $approved =  $mPropSafs->searchGbSafs()
                            ->where('gbo.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        $active = $mPropActiveSaf->searchGbSafs()
                            ->where('gbo.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("concession"):
                        $approved = $mPropConcessions->searchConcessions()
                            ->where('prop_owners.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        $active = $mPropActiveConcessions->searchConcessions()
                            ->where('prop_owners.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("objection"):
                        $approved = $mPropObjection->searchObjections()
                            ->where('prop_owners.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        $active = $mPropActiveObjection->searchObjections()
                            ->where('prop_owners.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("harvesting"):
                        $approved = $mPropHarvesting->searchHarvesting()
                            ->where('prop_owners.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        $active = $mPropActiveHarvesting->searchHarvesting()
                            ->where('prop_owners.mobile_no', 'LIKE', '%' . $mobileNo . '%');

                        // $details = $approved->union($active)->get();
                        break;
                    case ('holdingDeactivation'):
                        $details = 'No Data Found';
                        break;
                }
            }

            // search by ptn
            if ($searchBy == 'ptn') {
                $ptn = $request->value;
                switch ($key) {
                    case ("saf"):
                        $approved = $mPropSafs->searchSafs()
                            ->where('prop_safs.pt_no', $ptn)
                            ->groupby('prop_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        $active = $mPropActiveSaf->searchSafs()
                            ->where('prop_active_safs.pt_no', $ptn)
                            ->groupby('prop_active_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("gbsaf"):
                        $approved = $mPropSafs->searchGbSafs()
                            ->where('prop_safs.pt_no',  $ptn);

                        $active = $mPropActiveSaf->searchGbSafs()
                            ->where('prop_active_safs.pt_no',  $ptn);

                        // $details = $approved->union($active)->get();
                        break;
                    case ("concession"):
                        $approved =  $mPropConcessions->searchConcessions()
                            ->where('pp.pt_no', $ptn);

                        $active =  $mPropActiveConcessions->searchConcessions()
                            ->where('pp.pt_no', $ptn);

                        // $details = $approved->union($active)->get();
                        break;
                    case ("objection"):
                        $approved = $mPropObjection->searchObjections()
                            ->where('pp.pt_no', $ptn);

                        $active = $mPropActiveObjection->searchObjections()
                            ->where('pp.pt_no', $ptn);

                        // $details = $approved->union($active)->get();
                        break;
                    case ("harvesting"):
                        $approved = $mPropHarvesting->searchHarvesting()
                            ->where('pp.pt_no', $ptn);

                        $active = $mPropActiveHarvesting->searchHarvesting()
                            ->where('pp.pt_no', $ptn);

                        // $details = $approved->union($active)->get();
                        break;
                    case ('holdingDeactivation'):
                        $details = 'No Data Found';
                        break;
                }
            }

            // search with holding no
            if ($searchBy == 'holding') {
                $holding = $request->value;
                switch ($key) {
                    case ("saf"):
                        $approved  = $mPropSafs->searchSafs()
                            ->where('prop_safs.holding_no', $holding)
                            ->groupby('prop_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        $active = $mPropActiveSaf->searchSafs()
                            ->where('prop_active_safs.holding_no', $holding)
                            ->groupby('prop_active_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');

                        // $details = $approved->union($active)->get();
                        break;
                    case ("gbsaf"):
                        $approved = $mPropSafs->searchGbSafs()
                            ->where('prop_safs.holding_no', $holding);

                        $active = $mPropActiveSaf->searchGbSafs()
                            ->where('prop_active_safs.holding_no', $holding);

                        // $details = $approved->union($active)->get();
                        break;
                    case ("concession"):
                        $approved = $mPropConcessions->searchConcessions()
                            ->where('pp.holding_no',  $holding)
                            ->orWhere('pp.new_holding_no',  $holding);

                        $active = $mPropActiveConcessions->searchConcessions()
                            ->where('pp.holding_no',  $holding)
                            ->orWhere('pp.new_holding_no',  $holding);

                        // $details = $approved->union($active)->get();
                        break;
                    case ("objection"):
                        $approved =  $mPropObjection->searchObjections()
                            ->where('pp.holding_no',  $holding)
                            ->orWhere('pp.new_holding_no',  $holding);

                        $active =  $mPropActiveObjection->searchObjections()
                            ->where('pp.holding_no',  $holding)
                            ->orWhere('pp.new_holding_no',  $holding);

                        // $details = $approved->union($active)->get();
                        break;
                    case ("harvesting"):
                        $approved = $mPropHarvesting->searchHarvesting()
                            ->where('pp.holding_no',  $holding)
                            ->orWhere('pp.new_holding_no',  $holding);

                        $active = $mPropActiveHarvesting->searchHarvesting()
                            ->where('pp.holding_no',  $holding)
                            ->orWhere('pp.new_holding_no',  $holding);

                        // $details = $approved->union($active)->get();
                        break;
                    case ('holdingDeactivation'):
                        $details = 'No Data Found';
                        break;
                }
            }
            $details = $approved->union($active)->paginate($perPage);
            $transFeeWorkflowType  = (new \App\BLL\Property\Akola\SafApprovalBll())->_SkipFiledWorkWfMstrId;
            (collect($details->items())->map(function ($val) use ($transFeeWorkflowType) {
                $val->current_role = in_array($val->assessment_type, $transFeeWorkflowType) ? ($val->proccess_fee_paid == 0 ? "Transfer Fee Not Paid" : "Transfer Fee Paid") : "Payment Not Required";
                return $val;
            }));

            return responseMsgs(true, "Application Details", remove_null($details), "010501", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010501", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    public function applicationsListByKeySafOnly(Request $request)
    {
        $request->validate([
            'searchBy' => 'nullable',
            'value' => 'nullable',
            'pendingAt' => 'nullable',
        ]);

        try {
            $mPropActiveSaf = new PropActiveSaf();
            $mPropSafs = new PropSaf();
            $searchBy = $request->searchBy;
            $value = $request->value;
            $pendingAt = $request->pendingAt;
            $perPage = $request->perPage ?? 10;
            $approved = $mPropSafs->searchSafs();
            $active = $mPropActiveSaf->searchSafs()->where('prop_active_safs.citizen_id', null);
            if ($searchBy == 'applicationNo') {
                $approved->where('prop_safs.saf_no', strtoupper($value));
                $active->where('prop_active_safs.saf_no', strtoupper($value));
            } elseif ($searchBy == 'name') {
                $approved->where('so.owner_name', 'LIKE', '%' . strtoupper($value) . '%');
                $active->where('so.owner_name', 'LIKE', '%' . strtoupper($value) . '%');
            } elseif ($searchBy == 'mobileNo') {
                $approved->where('so.mobile_no', 'LIKE', '%' . $value . '%');
                $active->where('so.mobile_no', 'LIKE', '%' . $value . '%');
            } elseif ($searchBy == 'ptn') {
                $approved->where('prop_safs.pt_no', $value);
                $active->where('prop_active_safs.pt_no', $value);
            } elseif ($searchBy == 'holding') {
                $approved->where('prop_safs.holding_no', $value);
                $active->where('prop_active_safs.holding_no', $value);
            }
            if ($pendingAt && $pendingAt !== 'All') {
                $active->where('prop_active_safs.current_role', $pendingAt);
            }
            $approved->groupBy('prop_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');
            $active->groupBy('prop_active_safs.id', 'u.ward_name', 'uu.ward_name', 'wf_roles.role_name');
            if ($pendingAt) {
                $details = $active->paginate($perPage);
            } else {
                $details = $approved->union($active)->paginate($perPage);
            }

            $transFeeWorkflowType = (new \App\BLL\Property\Akola\SafApprovalBll())->_SkipFiledWorkWfMstrId;
            (collect($details->items())->map(function ($val) use ($transFeeWorkflowType) {
                $val->current_role = in_array($val->assessment_type, $transFeeWorkflowType)
                    ? ($val->proccess_fee_paid == 0 ? "Transfer Fee Not Paid" : "Transfer Fee Paid")
                    : "Payment Not Required";
                return $val;
            }));
            $paginatedData = [
                'current_page' => $details->currentPage(),
                'data' => $details->items(),
                'last_page' => $details->lastPage(),
                'total' => $details->total()
            ];
            return responseMsgs(true, "Application Details", remove_null($paginatedData), "010501", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010501", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }



    // get details of the diff operation in property
    public function propertyListByKey(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filteredBy' => "required",
                'parameter' => "nullable",
                'zoneId' => "nullable|digits_between:1,9223372036854775807",
                'wardId' => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }

        try {
            $mWfWardUser = new WfWardUser();
            $mPropProperty = new PropProperty();
            $mWfRoleUser = new WfRoleusermap();
            $user = authUser($request);
            $userId = $user->id;
            $userType = $user->user_type;
            $ulbId = $user->ulb_id ?? $request->ulbId;
            $roleIds = $mWfRoleUser->getRoleIdByUserId($userId)->pluck('wf_role_id');                      // Model to () get Role By User Id
            $role = $roleIds->first();
            $key = $request->filteredBy;
            $parameter = $request->parameter;
            $isLegacy = $request->isLegacy;
            $perPage = $request->perPage ?? 5;
            $occupiedWards = $mWfWardUser->getWardsByUserId($userId)->pluck('ward_id');
            switch ($key) {
                case ("holdingNo"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where(function ($where) use ($parameter) {
                            $where->ORwhere('prop_properties.holding_no', 'ILIKE',  strtoupper($parameter))
                                ->orWhere('prop_properties.new_holding_no', 'ILIKE',  strtoupper($parameter));
                        })
                        ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);
                    break;

                case ("ptn"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        // ->where('prop_properties.pt_no', 'LIKE', '%' . $parameter . '%');
                        ->where('prop_properties.property_no', 'ILIKE', '%' . $parameter . '%')
                        ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);
                    break;

                case ("ownerName"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where(function ($where) use ($parameter) {
                            $where->where('o.owner_name', 'ILIKE', '%' . strtoupper($parameter) . '%')
                                ->orwhere('o.owner_name_marathi', 'ILIKE', '%' . strtoupper($parameter) . '%');
                        })
                        ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);

                    break;

                case ("address"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where('prop_properties.prop_address', 'ILIKE', '%' . strtoupper($parameter) . '%')
                        ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);
                    break;

                case ("mobileNo"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where('o.mobile_no', 'LIKE', '%' . $parameter . '%')
                        ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);
                    break;

                case ("khataNo"):
                    if ($request->khataNo)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.khata_no', $request->khataNo);

                    if ($request->plotNo)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.plot_no',  $request->plotNo);

                    if ($request->maujaName)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.village_mauja_name',  $request->maujaName);

                    if ($request->khataNo && $request->plotNo)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.khata_no',  $request->khataNo)
                            ->where('prop_properties.plot_no',  $request->plotNo);

                    if ($request->khataNo && $request->maujaName)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.khata_no',  $request->khataNo)
                            ->where('prop_properties.village_mauja_name',  $request->maujaName);

                    if ($request->plotNo && $request->maujaName)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.plot_no',  $request->plotNo)
                            ->where('prop_properties.village_mauja_name',  $request->maujaName);

                    if ($request->khataNo && $request->plotNo && $request->maujaName)
                        $data = $mPropProperty->searchPropertyV2($ulbId)
                            ->where('prop_properties.khata_no',  $request->khataNo)
                            ->where('prop_properties.plot_no',  $request->plotNo)
                            ->where('prop_properties.village_mauja_name',  $request->maujaName);
                    break;

                case ("propertyNo"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where('prop_properties.property_no', 'LIKE', $parameter)
                        ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);
                    break;
                default:
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                    ->whereIn('prop_properties.ward_mstr_id',$occupiedWards);
            }
            $data = $data->whereIn('prop_properties.status', [1, 3]);

            if ($request->zoneId) {
                $data = $data->where("prop_properties.zone_mstr_id", $request->zoneId);
            }
            if ($request->wardId) {
                $data = $data->where("prop_properties.ward_mstr_id", $request->wardId);
            }
            if ($userType != 'Citizen')
                $data = $data->where('prop_properties.ulb_id', $ulbId);

            if ($isLegacy == false) {
                if ($key == 'ptn') {
                    $paginator =
                        $data
                        // ->groupby('prop_properties.id', 'ulb_ward_masters.ward_name', 'latitude', 'longitude', 'zone_name', 'd.paid_status', 'o.owner_name','o.owner_name_marathi', 'o.mobile_no')
                        ->paginate($perPage);
                } else {
                    $paginator = $data
                        // ->groupby('prop_properties.id', 'ulb_ward_masters.ward_name', 'latitude', 'longitude', 'zone_name', 'd.paid_status', 'o.owner_name','o.owner_name_marathi', 'o.mobile_no')
                        ->paginate($perPage);
                }
            }

            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];

            return responseMsgs(true, "Application Details", remove_null($list), "010501", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010502", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    // All saf no from Active Saf no
    /**
     | ----------flag
     */
    public function getListOfSaf()
    {
        $getSaf = new PropActiveSaf();
        return $getSaf->allNonHoldingSaf();
    }

    // All the listing of the Details of Applications According to the respective Id
    public function getUserDetails(Request $request)
    {
        return $this->propertyDetails->getUserDetails($request);
    }
}
