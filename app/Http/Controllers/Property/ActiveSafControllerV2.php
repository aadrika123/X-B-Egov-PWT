<?php

namespace App\Http\Controllers\Property;

use App\BLL\Property\CalculationByUlbTc;
use App\BLL\Property\EditSafBll;
use App\EloquentClass\Property\PenaltyRebateCalculation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\reqApplySaf;
use App\Http\Requests\Property\ReqPayment;
use App\MicroServices\IdGeneration;
use App\Models\Cluster\Cluster;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropApartmentDtl;
use App\Models\Property\PropDemand;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafGeotagUpload;
use App\Models\Property\PropSafMemoDtl;
use App\Models\Property\PropSafsDemand;
use App\Models\Property\PropSafTax;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropTax;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\UlbMaster;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWardUser;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Pipelines\SafInbox\SearchByApplicationNo;
use App\Pipelines\SafInbox\SearchByMobileNo;
use App\Pipelines\SafInbox\SearchByName;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Traits\Property\SAF;
use Carbon\Carbon;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On-10-02-2023 
 * | Created By-Mrinal Kumar
 * */

class ActiveSafControllerV2 extends Controller
{
    use SAF;
    /**
     * | Edit Applied Saf by SAF Id for BackOffice
     * | @param request $req
     * | Serial 01
     */
    public function editCitizenSaf(reqApplySaf $req)
    {
        $req->validate([
            'id' => 'required|numeric'
        ]);
        try {
            $id = $req->id;
            $mPropActiveSaf = PropActiveSaf::find($id);
            $citizenId = authUser($req)->id;
            $mPropSafOwners = new PropActiveSafsOwner();
            $mPropSafFloors = new PropActiveSafsFloor();
            $mActiveSaf = new PropActiveSaf();
            $reqOwners = $req->owner;
            $reqFloors = $req->floor;

            $refSafFloors = $mPropSafFloors->getSafFloorsBySafId($id);
            $refSafOwners = $mPropSafOwners->getOwnersBySafId($id);

            if ($mPropActiveSaf->payment_status == 1)
                throw new Exception("You cannot edit the application");

            if ($mPropActiveSaf->payment_status == 0) {
                // Floors
                $newFloors = collect($reqFloors)->whereNull('safFloorId')->values();
                $existingFloors = collect($reqFloors)->whereNotNull('safFloorId')->values();
                $existingFloorIds = $existingFloors->pluck('safFloorId');
                $toDeleteFloors = $refSafFloors->whereNotIn('id', $existingFloorIds)->values();
                $toDeleteFloorIds = $toDeleteFloors->pluck('id');
                // Owners
                $newOwners = collect($reqOwners)->whereNull('safOwnerId')->values();
                $existingOwners = collect($reqOwners)->whereNotNull('safOwnerId')->values();
                $existingOwnerIds = $existingOwners->pluck('safOwnerId');
                $toDeleteOwners = $refSafOwners->whereNotIn('id', $existingOwnerIds)->values();
                $toDeleteOwnerIds = $toDeleteOwners->pluck('id');

                $roadWidthType = $this->readRoadWidthType($req->roadType);          // Read Road Width Type
                $req = $req->merge(['road_type_mstr_id' => $roadWidthType]);

                DB::beginTransaction();
                // Edit Active Saf
                $mActiveSaf->safEdit($req, $mPropActiveSaf, $citizenId);
                // Delete No Existing floors
                PropActiveSafsFloor::destroy($toDeleteFloorIds);
                // Update Existing floors
                foreach ($existingFloors as $existingFloor) {
                    $mPropSafFloors->editFloor($existingFloor, $citizenId);
                }
                // Add New Floors
                foreach ($newFloors as $newFloor) {
                    $mPropSafFloors->addfloor($newFloor, $id, $citizenId);
                }

                // Delete No Existing Owners
                PropActiveSafsOwner::destroy($toDeleteOwnerIds);
                // Update Existing Owners
                foreach ($existingOwners as $existingOwner) {
                    $mPropSafOwners->edit($existingOwner);
                }

                // Add New Owners
                foreach ($newOwners as $newOwner) {
                    $mPropSafOwners->addOwner($newOwner, $id, $citizenId);
                }
            }
            DB::commit();
            return responseMsgs(true, "Successfully Updated the Data", "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Delete Citizen Saf
     * | Serial 02
     */
    public function deleteCitizenSaf(Request $req)
    {
        try {
            $id = $req->id;
            $mPropActiveSaf = PropActiveSaf::find($id);
            $mPropSafOwner = PropActiveSafsOwner::where('saf_id', $id)->get();
            $mPropSafFloor =  PropActiveSafsFloor::where('saf_id', $id)->get();

            if ($mPropActiveSaf->payment_status == 1)
                throw new Exception("Payment Done Saf Cannot be deleted");

            if ($mPropActiveSaf->payment_status == 0) {
                $mPropActiveSaf->status = 0;
                $mPropActiveSaf->update();

                foreach ($mPropSafOwner as $mPropSafOwners) {
                    $mPropSafOwners->status = 0;
                    $mPropSafOwners->save();
                }
                foreach ($mPropSafFloor as $mPropSafFloors) {
                    $mPropSafFloors->status = 0;
                    $mPropSafFloors->save();
                }
            }
            return responseMsgs(true, "Saf Deleted", "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Generate memo receipt
     * | Serial 03
     */
    public function memoReceipt(Request $req)
    {
        $req->validate([
            'memoId' => 'required|numeric'
        ]);
        try {
            $mPropSafMemoDtl = new PropSafMemoDtl();
            $propSafTax = new PropSafTax();
            $propTax = new PropTax();
            $mPropProperty = new PropProperty();
            $mUlbMaster = new UlbMaster();
            $mPropSafMemoDtl = new PropSafMemoDtl();
            $famDtls = array();

            $details = $mPropSafMemoDtl::find($req->memoId);
            $saf = PropSaf::find($details->saf_id);
            $famDtls = [
                'fam' => $details,
                'paid_status' => $saf->payment_status
            ];
            // if (collect($details)->isEmpty())
            //     $details = $mPropSafMemoDtl->getPropMemoDtlsByMemoId($req->memoId);

            // if (collect($details)->isEmpty())
            //     throw new Exception("Memo Details Not Available");
            // $details = collect($details)->first();
            // $taxTable = collect($details)->only(['holding_tax', 'water_tax', 'latrine_tax', 'education_cess', 'health_cess', 'rwh_penalty']);
            // $details->taxTable = $this->generateTaxTable($taxTable);
            // // Fam Receipt
            // if ($details->memo_type == 'FAM') {
            //     $propId = $details->prop_id;
            //     $safId = $details->saf_id;

            //     $safTaxes = $propSafTax->getSafTaxesBySafId($safId);
            //     if ($safTaxes->isEmpty())
            //         throw new Exception("Saf Taxes Not Available");
            //     $propTaxes = $propTax->getPropTaxesByPropId($propId);
            //     if ($propTaxes->isEmpty())
            //         throw new Exception("Prop Taxes Not Available");
            //     $holdingTaxes = $propTaxes->map(function ($propTax) use ($safTaxes) {
            //         $ulbTax = $propTax;
            //         $selfAssessTaxes = $safTaxes->where('fyear', $propTax->fyear)     // Holding Tax Amount without penalty
            //             ->where('qtr', $propTax->qtr)
            //             ->first();
            //         if (is_null($selfAssessTaxes))
            //             $selfAssessQuaterlyTax = 0;
            //         else
            //             $selfAssessQuaterlyTax = $selfAssessTaxes->quarterly_tax * 4;

            //         $ulbVerifiedQuarterlyTaxes = $ulbTax->quarterly_tax * 4;

            //         $diffAmt = $ulbVerifiedQuarterlyTaxes - $selfAssessQuaterlyTax;
            //         if (substr($ulbTax->fyear, 5) >= 2023 && $ulbTax->qtr >= 1)
            //             $particulars = "Holding Tax @ 0.075% or 0.15% or 0.2%";         // Ruleset1
            //         elseif (substr($ulbTax->fyear, 5) >= 2017 && $ulbTax->qtr >= 1)
            //             $particulars = "Holding Tax @ 2%";                              // Ruleset2
            //         else
            //             $particulars = "Holding Tax @ 43.75% or 38.75%";                // Ruleset3

            //         $response = [
            //             'Particulars' => $particulars,
            //             'quarterFinancialYear' => 'Quarter' . $ulbTax->qtr . '/' . $ulbTax->fyear,
            //             'basedOnSelfAssess' => roundFigure($selfAssessQuaterlyTax),
            //             'basedOnUlbCalc' => roundFigure($ulbVerifiedQuarterlyTaxes),
            //             'diffAmt' => roundFigure($diffAmt)
            //         ];
            //         return $response;
            //     });

            //     $holdingTaxes = $holdingTaxes->values();
            //     $total = collect([
            //         'Particulars' => 'Total Amount',
            //         'quarterFinancialYear' => "",
            //         'basedOnSelfAssess' => roundFigure($holdingTaxes->sum('basedOnSelfAssess')),
            //         'basedOnUlbCalc' => roundFigure($holdingTaxes->sum('basedOnUlbCalc')),
            //         'diffAmt' => roundFigure($holdingTaxes->sum('diffAmt')),
            //     ]);
            //     $details->from_qtr = $safTaxes->first()->qtr;
            //     $details->from_fyear = $safTaxes->first()->fyear;
            //     $details->arv = $safTaxes->first()->arv;
            //     $details->quarterly_tax = $safTaxes->first()->quarterly_tax;
            //     $details->rule = substr($details->from_fyear, 5) >= 2023 ? "Capital Value Rule, property tax" : "Annual Rent Value Rule, annual rent value";
            //     $details->taxTable = $holdingTaxes->merge([$total])->values();
            // }
            // // Get Ulb Details
            // $details->ulbDetails = $mUlbMaster->getUlbDetails($properties->ulb_id ?? 2);
            return responseMsgs(true, "", remove_null($famDtls), "011803", 1.0, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011803", 1.0, responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Generate Tax Table
     */
    public function generateTaxTable($taxDetails)
    {
        $taxes = collect(
            [
                'Holding Tax' => $taxDetails['holding_tax'],
                'Water Tax' => $taxDetails['water_tax'],
                'Latrine Tax' => $taxDetails['latrine_tax'],
                'Education Cess' => $taxDetails['education_cess'],
                'Health Tax' => $taxDetails['health_cess'],
                'RWH Penalty' => $taxDetails['rwh_penalty'],
            ]
        );
        return $taxes->filter(function ($value, $key) {
            return $value != 0;
        });
    }

    /**
     * | Search Holding of user not logged in
     * | Serial 04
     */
    public function searchHolding(Request $req)
    {
        $req->validate([
            "holdingNo" => "required",
            "ulbId" => "required"
        ]);
        try {
            $holdingNo = $req->holdingNo;
            $ulbId = $req->ulbId;
            $mPropProperty = new PropProperty();
            $mPropOwners = new PropOwner();

            $prop = $mPropProperty->searchHoldingNo($ulbId)
                ->where('prop_properties.holding_no', $holdingNo)
                ->first();

            if (!$prop) {
                $prop = $mPropProperty->searchHoldingNo($ulbId)
                    ->where('prop_properties.new_holding_no', $holdingNo)
                    ->first();
            }

            if (!$prop) {
                $prop = $mPropProperty->searchHoldingNo($ulbId)
                    ->where('prop_properties.pt_no', $holdingNo)
                    ->first();
            }

            if (!$prop)
                throw new Exception("Enter Valid Holding No.");

            $owner = $mPropOwners->firstOwner($prop['id']);
            $owner = [
                "owner_name" => $owner->owner_name,
                "mobile_no" => $owner->mobile_no
            ];
            $data[] = collect($prop)->merge($owner);


            return responseMsgs(true, "Holding Details", $data, 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    /**
     * created by Prity Pandey
     * date : 18-11-2023
     * unAuthicated Serching of Holding For Citizen
     * 
     */
    public function searchHoldingDirect(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filteredBy' => "required",
                'parameter' => "nullable",
                'propId' => "nullable|digits_between:1,9223372036854775807",
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
            $mPropProperty = new PropProperty();
            $ulbId = $request->ublId ?? 2;
            $key = $request->filteredBy;
            $parameter = $request->parameter;
            $isLegacy = $request->isLegacy;
            $perPage = $request->perPage ?? 5;

            switch ($key) {
                case ("holdingNo"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where(function ($where) use ($parameter) {
                            $where->ORwhere('prop_properties.holding_no', 'LIKE',  strtoupper($parameter))
                                ->orWhere('prop_properties.new_holding_no', 'LIKE', strtoupper($parameter));
                        });
                    break;

                case ("ptn"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where('prop_properties.property_no', 'LIKE', '%' . $parameter . '%');
                    break;

                case ("ownerName"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where(function ($where) use ($parameter) {
                            $where->where('o.owner_name', 'ILIKE', '%' . strtoupper($parameter) . '%')
                                ->orwhere('o.owner_name_marathi', 'ILIKE', '%' . strtoupper($parameter) . '%');
                        });

                    break;

                case ("address"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where('prop_properties.prop_address', 'ILIKE', '%' . strtoupper($parameter) . '%');
                    break;

                case ("mobileNo"):
                    $data = $mPropProperty->searchPropertyV2($ulbId)
                        ->where('o.mobile_no', 'LIKE', '%' . $parameter . '%');
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
                        ->where('prop_properties.property_no', 'LIKE',  $parameter . '-');
                    break;
                default:
                    $data = $mPropProperty->searchPropertyV2($ulbId);
            }

            if ($request->zoneId) {
                $data = $data->where("prop_properties.zone_mstr_id", $request->zoneId);
            }
            if ($request->wardId) {
                $data = $data->where("prop_properties.ward_mstr_id", $request->wardId);
            }
            if ($request->propId) {
                $data = $data->where("prop_properties.id", $request->propId);
            }
            $data = $data->where("prop_properties.status", 1);

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

    /**
     * | Serial 05
     */
    public function verifyHoldingNo(Request $req)
    {
        try {
            $req->validate([
                'holdingNo' => 'required',
                'ulbId' => 'required',
            ]);
            $mPropProperty = new PropProperty();
            $data = $mPropProperty->verifyHolding($req);

            if (!isset($data)) {
                throw new Exception("Enter Valid Holding No.");
            }
            $datas['id'] = $data->id;

            return responseMsgs(true, "Holding Exist", $datas, 010124, 1.0, "308ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", 010124, 1.0, "308ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Get Apartment List by Ward Id
     */
    public function getAptList(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'wardMstrId' => 'required',
                'ulbId' => 'nullable',
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $mPropApartmentDtl = new PropApartmentDtl();
            $ulbId = $req->ulbId ?? authUser($req)->ulb_id;
            $req->request->add(['ulbId' => $ulbId,]);

            $data = $mPropApartmentDtl->apartmentList($req);

            if (($data->isEmpty())) {
                throw new Exception("Apartment List Not Available");
            }

            return responseMsgs(true, "Apartment List", $data, 010124, 1.0, responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], 010124, 1.0, responseTime(), "POST", $req->deviceId);
        }
    }

    /**
     * | Get Pending GeoTaggings
     */
    public function pendingGeoTaggingList(Request $req, iSafRepository $iSafRepo)
    {
        try {
            $agencyTcRole = Config::get('PropertyConstaint.SAF-LABEL.TC');
            $mWfWardUser = new WfWardUser();
            $mWorkflowRoleMap = new WfWorkflowrolemap();
            $userId = authUser($req)->id;
            $ulbId = authUser($req)->ulb_id;
            $perPage = $req->perPage ?? 10;

            $workflowIds = $mWorkflowRoleMap->getWfByRoleId([$agencyTcRole])->pluck('workflow_id');
            $readWards = $mWfWardUser->getWardsByUserId($userId);                       // Model () to get Occupied Wards of Current User
            $occupiedWards = collect($readWards)->pluck('ward_id');

            $safDtl = $iSafRepo->getSaf($workflowIds)                                 // Repository function to get SAF Details
                ->where('parked', false)
                ->where('is_geo_tagged', false)
                ->where('prop_active_safs.ulb_id', $ulbId)
                ->where('prop_active_safs.status', 1)
                ->where('current_role', $agencyTcRole)
                ->whereIn('ward_mstr_id', $occupiedWards)
                ->orderByDesc('id')
                ->groupBy('prop_active_safs.id', 'p.property_type', 'ward.ward_name');

            $safInbox = app(Pipeline::class)
                ->send(
                    $safDtl
                )
                ->through([
                    SearchByApplicationNo::class,
                    SearchByMobileNo::class,
                    SearchByName::class
                ])
                ->thenReturn()
                ->paginate($perPage);

            return responseMsgs(true, "Data Fetched", remove_null($safInbox), "011806", "1.0", "", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011806", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }


    /**
     * | Cluster Demand for Saf
     */
    public function getClusterSafDues(Request $req, iSafRepository $iSafRepository)
    {
        $req->validate([
            'clusterId' => 'required|integer'
        ]);

        try {
            $todayDate = Carbon::now();
            $clusterId = $req->clusterId;
            $mPropActiveSaf = new PropActiveSaf();
            $penaltyRebateCal = new PenaltyRebateCalculation;
            $activeSafController = new ActiveSafController($iSafRepository);
            $mClusters = new Cluster();
            $clusterDtls = $mClusters::findOrFail($clusterId);

            $clusterDemands = array();
            $finalClusterDemand = array();
            $clusterDemandList = array();
            $currentQuarter = calculateQtr($todayDate->format('Y-m-d'));
            $loggedInUserType = authUser($req)->user_type;
            $currentFYear = getFY();

            $clusterSafs = $mPropActiveSaf->getSafsByClusterId($clusterId);
            if ($clusterSafs->isEmpty())
                throw new Exception("Safs Not Available");

            foreach ($clusterSafs as $item) {
                $propIdReq = new Request([
                    'id' => $item['id']
                ]);
                $demandList = $activeSafController->calculateSafBySafId($propIdReq)->original['data'];
                $safDues['demand'] = $demandList['demand'] ?? [];
                $safDues['details'] = $demandList['details'] ?? [];
                array_push($clusterDemandList, $safDues['details']);
                array_push($clusterDemands, $safDues);
            }

            $collapsedDemand = collect($clusterDemandList)->collapse();                       // Clusters Demands Collapsed into One

            if ($collapsedDemand->isEmpty())
                throw new Exception("Demand Not Available for this Cluster");

            $totalLateAssessmentPenalty = collect($clusterDemands)->map(function ($item) {      // Total Collective Late Assessment Penalty
                return $item['demand']['lateAssessmentPenalty'] ?? 0;
            })->sum();

            $groupedByYear = $collapsedDemand->groupBy('due_date');                           // Grouped By Financial Year and Quarter for the Separation of Demand  
            $summedDemand = $groupedByYear->map(function ($item) use ($penaltyRebateCal) {    // Sum of all the Demands of Quarter and Financial Year
                $quarterDueDate = $item->first()['due_date'];
                $onePercPenaltyPerc = $penaltyRebateCal->calcOnePercPenalty($quarterDueDate);
                $balance = roundFigure($item->sum('balance'));

                $onePercPenaltyTax = ($balance * $onePercPenaltyPerc) / 100;
                $onePercPenaltyTax = roundFigure($onePercPenaltyTax);

                return [
                    'quarterYear' => $item->first()['qtr']  . "/" . $item->first()['fyear'],
                    'arv' => roundFigure($item->sum('arv')),
                    'qtr' => $item->first()['qtr'],
                    'holding_tax' => roundFigure($item->sum('holding_tax')),
                    'water_tax' => roundFigure($item->sum('water_tax')),
                    'education_cess' => roundFigure($item->sum('education_cess')),
                    'health_cess' => roundFigure($item->sum('health_cess')),
                    'latrine_tax' => roundFigure($item->sum('latrine_tax')),
                    'additional_tax' => roundFigure($item->sum('additional_tax')),
                    'amount' => roundFigure($item->sum('amount')),
                    'balance' => $balance,
                    'fyear' => $item->first()['fyear'],
                    'adjust_amount' => roundFigure($item->sum('adjust_amt')),
                    'due_date' => $quarterDueDate,
                    'onePercPenalty' => $onePercPenaltyPerc,
                    'onePercPenaltyTax' => $onePercPenaltyTax,
                ];
            })->values();
            $finalDues = collect($summedDemand)->sum('balance');
            $finalDues = roundFigure($finalDues);

            $finalOnePerc = collect($summedDemand)->sum('onePercPenaltyTax');
            $finalOnePerc = roundFigure($finalOnePerc);
            $finalAmt = $finalDues + $finalOnePerc + $totalLateAssessmentPenalty;
            $finalAmt = roundFigure($finalAmt);
            $duesFrom = collect($clusterDemands)->first()['demand']['duesFrom'] ?? collect($clusterDemands)->last()['demand']['duesFrom'] ?? [];
            $duesTo = collect($clusterDemands)->first()['demand']['duesTo'] ?? collect($clusterDemands)->last()['demand']['duesTo'] ?? [];

            $finalClusterDemand['demand'] = [
                'duesFrom' => $duesFrom,
                'duesTo' => $duesTo,
                'totalTax' => $finalDues,
                'totalDues' => $finalDues,
                'totalOnePercPenalty' => $finalOnePerc,
                'lateAssessmentPenalty' => $totalLateAssessmentPenalty,
                'finalAmt' => $finalAmt,
                'totalDemand' => $finalAmt,
            ];
            $mLastQuarterDemand = collect($summedDemand)->where('fyear', $currentFYear)->sum('balance');
            $finalClusterDemand['demand'] = $penaltyRebateCal->readRebates($currentQuarter, $loggedInUserType, $mLastQuarterDemand, null, $finalAmt, $finalClusterDemand['demand']);
            $payableAmount = $finalAmt - ($finalClusterDemand['demand']['rebateAmt'] + $finalClusterDemand['demand']['specialRebateAmt']);
            $finalClusterDemand['demand']['payableAmount'] = round($payableAmount);

            $finalClusterDemand['details'] = $summedDemand;
            $finalClusterDemand['basicDetails'] = $clusterDtls;
            return responseMsgs(true, "Cluster Demands", remove_null($finalClusterDemand), "011807", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), ['basicDetails' => $clusterDtls], "011807", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Cluster Payment
     */
    public function clusterSafPayment(ReqPayment $req, iSafRepository $iSafRepository)
    {
        try {
            $dueReq = new Request([
                'clusterId' => $req->id
            ]);
            $clusterId = $req->id;
            $todayDate = Carbon::now();
            $idGeneration = new IdGeneration;
            $mPropTrans = new PropTransaction();
            $mPropSafsDemand = new PropSafsDemand();
            $activeSafController = new ActiveSafController($iSafRepository);
            $offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
            $mPropTranDtl = new PropTranDtl();

            $dues1 = $this->getClusterSafDues($dueReq, $iSafRepository);

            if ($dues1->original['status'] == false)
                throw new Exception($dues1->original['message']);

            $dues = $dues1->original['data'];

            $demands = $dues['details'];
            $tranNo = $idGeneration->generateTransactionNo($req['ulbId']);
            $payableAmount = $dues['demand']['payableAmount'];
            // Property Transactions
            if (in_array($req['paymentMode'], $offlinePaymentModes)) {
                $userId = authUser($req)->id ?? null;
                if (!$userId)
                    throw new Exception("User Should Be Logged In");
                $tranBy = authUser($req)->user_type;
            }
            $req->merge([
                'userId' => $userId,
                'todayDate' => $todayDate->format('Y-m-d'),
                'tranNo' => $tranNo,
                'amount' => $payableAmount,
                'tranBy' => $tranBy,
                'clusterType' => "Saf"
            ]);

            DB::beginTransaction();
            $propTrans = $mPropTrans->postClusterTransactions($req, $demands, 'Saf');
            if (in_array($req['paymentMode'], $offlinePaymentModes)) {
                $req->merge([
                    'chequeDate' => $req['chequeDate'],
                    'tranId' => $propTrans['id'],
                    'id' => null
                ]);
                $activeSafController->postOtherPaymentModes($req, $clusterId);
            }
            // Reflect on Prop Tran Details
            foreach ($demands as $demand) {
                $demand = $demand->toArray();
                unset($demand['ruleSet'], $demand['rwhPenalty'], $demand['onePercPenalty'], $demand['onePercPenaltyTax'], $demand['quarterYear']);
                if (isset($demand['status']))
                    unset($demand['status']);
                $demand['paid_status'] = 1;
                $demand['cluster_id'] = $clusterId;
                $demand['balance'] = 0;
                $storedSafDemand = $mPropSafsDemand->postDemands($demand);

                $tranReq = [
                    'tran_id' => $propTrans['id'],
                    'saf_cluster_demand_id' => $storedSafDemand['demandId'],
                    'total_demand' => $demand['amount'],
                    'ulb_id' => $req['ulbId'],
                ];
                $mPropTranDtl->store($tranReq);
            }
            // Replication Prop Rebates Penalties
            $activeSafController->postPenaltyRebates($dues1, null, $propTrans['id'], $clusterId);
            DB::commit();
            return responseMsgs(true, "Payment Successfully Done", ["tranNo" => $tranNo], "011612", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "011612", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Edit Active Saf
     */
    public function editActiveSaf(reqApplySaf $req)
    {
        $req->validate([
            "id" => 'required|integer'
        ]);

        try {
            $editSafBll = new EditSafBll;
            $editSafBll->_reqs = $req;
            $editSafBll->edit();
            DB::commit();
            return responseMsgs(true, "Application Edited Successfully", [], "011809", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {

            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "011809", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
    }
    /**
     * | Edit Active Saf
     */
    public function getSafLatLanById(Request $req)
    {
        $req->validate([
            "safId" => 'required|integer'
        ]);

        try {
            $safDetails = new PropSafGeotagUpload();
            $details = $safDetails->getDetails($req)->first();
            return responseMsgs(true, "Details", remove_null($details), "011809", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), [], "011809", "1.0", responseTime(), "POST", $req->deviceId ?? "");
        }
    }
}
