<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropFloor;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\RefPropConstructionType;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * | Author - Anshu Kumar
 * | Created On-12-08-2023
 * | Status-Closed
 */
class TaxCalculator
{
    private $_REQUEST;
    private array $_calculatorParams;
    private array $_floorsTaxes;
    public array $_GRID;
    private $_pendingYrs;
    private $_carbonToday;
    private $_propFyearFrom;
    private $_maintancePerc;
    private $_refPropConstTypes;
    private $_mRefPropConsTypes;
    public $_calculationDateFrom;
    public $_updateCalculationDateFrom;
    private $_agingPercs;
    private $_currentFyear;
    private $_residentialUsageType;
    private $_govResidentialUsageType;
    private $_newForm;
    public $_oldUnpayedAmount;
    public $_lastDemand;
    public $_oldDemand;
    public $_isSingleManArmedForce = false;
    public $_mangalTowerId;
    private $_LESS_PERSENTAGE_APPLY_WARD_IDS;

    public $_OldFloors = [];
    public $_newFloors = [];

    /**
     * | Initialization of global variables
     */
    public function __construct(Request $req)
    {
        $this->_REQUEST = $req;
        $this->_carbonToday = Carbon::now();
        $this->_mRefPropConsTypes = new RefPropConstructionType();
        $this->_agingPercs = Config::get('PropertyConstaint.AGING_PERC');
        $this->_residentialUsageType = Config::get('akola-property-constant.RESIDENTIAL_USAGE_TYPE');
        $this->_govResidentialUsageType = Config::get('akola-property-constant.GOV_USAGE_TYPE');
        $this->_LESS_PERSENTAGE_APPLY_WARD_IDS = Config::get('akola-property-constant.LESS_PERSENTAGE_APPLY_WARD_IDS');
        $this->_mangalTowerId  = Config::get('akola-property-constant.MANGAL_TOWER_ID');
    }

    /**
     * | Calculate Tax
     */
    public function calculateTax()
    {
        $this->setOldFloors();
        $this->setNewFloors();
        $this->readCalculatorParams();      // 1

        $this->generateFloorWiseTax();      // 2

        $this->generateVacantWiseTax();     // 3

        $this->generateFyearWiseTaxes();    // 4

        //Written by prity pandey
        $this->generateTaxAccOldPropDemand();    // 4.1

        //Written by prity pandey  16-09-24
        $this->generateBifurcationTaxAccOldPropDemand(); //4.2



        $this->generatePayableAmount();     // 5
    }

    /**
     * | Read Params(1)
     */
    public function readCalculatorParams()
    {
        $this->_refPropConstTypes = $this->_mRefPropConsTypes->propConstructionType();

        if ($this->_REQUEST->propertyType == 4)                                                // If the property is not vacand land it means the property is a independent building
            $this->_propFyearFrom = Carbon::parse($this->_REQUEST->dateOfPurchase)->format('Y');                // For Vacant Land Only

        if ($this->_REQUEST->propertyType != 4) {                                               // If the property is not vacand land it means the property is a independent building
            $oldestFloor = collect($this->_REQUEST->floor)->sortBy('dateFrom')->first();
            $this->_propFyearFrom = Carbon::parse($oldestFloor['dateFrom'])->format('Y');
        }
        $armedForceOwners = collect($this->_REQUEST->owner)->where("isArmedForce", 1);
        if ($armedForceOwners->isNotEmpty() && collect($this->_REQUEST->owner)->count() == 1) {
            $this->_isSingleManArmedForce = true;
        }

        if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() != 'New Assessment') && ($this->getAssestmentTypeStr() != 'Amalgamation')) {
            $mPropFloors = new PropFloor();
            $mPropOwners = new PropOwner();
            $priProperty = PropProperty::find($this->_REQUEST->previousHoldingId);
            // $floors = $mPropFloors->getPropFloors($priProperty->id);
            if ($priProperty) {
                $unPaidDemand = $priProperty->PropArrearDueDemands()->get();
                $this->_oldUnpayedAmount = collect($unPaidDemand)->sum("due_total_tax") ?? 0;
            }

            // if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() == 'Bifurcation')) {
            //     $unPaidDemand = $priProperty->PropDueDemands()->get();
            //     $this->_oldUnpayedAmount = round(collect($unPaidDemand)->sum("due_total_tax") ?? 0);
            // }

            if ($this->_oldUnpayedAmount > 0) {
                // throw new Exception("Old Demand Not Cleard");
            }
            $this->_lastDemand = $priProperty->PropLastDemands();
            $this->_oldDemand = $priProperty->getAllDemands()->get();
            $paydUptoDemand = $priProperty->PropLastPaidDemands()->get();
            $test = $unPaidDemand->toArray();
            list($fromYear, $lastYear) = explode("-", $this->_lastDemand->fyear ?? getFY());
            $this->_newForm = $lastYear . "-04-01";
            // $this->_propFyearFrom = Carbon::parse($this->_newForm)->format('Y');

            //prity pandey 16-09-24
            if (($this->getAssestmentTypeStr() == 'Bifurcation')) {
                list($fromYear, $lastYear) = explode("-", $unPaidDemand->min("fyear") ?? getFY());
                $this->_newForm = $fromYear . "-04-01";
            }
            //end of change
            list($fromYear, $lastYear) = explode("-", $this->_oldDemand->min("fyear") ?? getFY());
            if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() == 'Reassessment')) {
                $this->_newForm = $fromYear . "-04-01";
                //changes by prity pandey
                //list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->approvedDate));
                //$this->_newForm = ($fromYear - 2) . "-04-01";
                // $this->_newForm = ($fromYear) . "-04-01";
                //end of changes

                //code for reassessment calculation according to completion and building approval date
                //added by prity pandey 12-09-2024
                if ($this->_REQUEST->buildingPlanCompletionDate) {
                    list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->buildingPlanCompletionDate));
                    $this->_newForm = ($fromYear) . "-04-01";
                    if (getFY($this->_REQUEST->buildingPlanCompletionDate) < "2022-2023") {
                        $this->_newForm = "2022-04-01";
                    }
                } elseif ($this->_REQUEST->buildingPlanApprovalDate) {
                    list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->buildingPlanApprovalDate));
                    $this->_newForm = ($lastYear) . "-04-01";
                    if (getFY($this->_REQUEST->buildingPlanApprovalDate) < "2022-2023") {
                        $this->_newForm = "2022-04-01";
                    }
                }
                // added by prity pandey 17-09-24
                // else {
                //     list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->approvedDate));
                //     $this->_newForm = ($fromYear - 2) . "-04-01";
                //     $this->_newForm = ($fromYear) . "-04-01";
                // }
            }



            //changes by prity pandey
            // if ($this->_REQUEST->buildingPlanCompletionDate) {
            //     $this->_newForm = $this->_REQUEST->buildingPlanCompletionDate >= $this->_newForm ? explode("-", getFY($this->_REQUEST->buildingPlanCompletionDate))[0] . "-04-01" : $this->_newForm;
            // } elseif ($this->_REQUEST->buildingPlanApprovalDate) {
            //     $this->_newForm = $this->_REQUEST->buildingPlanApprovalDate >= $this->_newForm ? explode("-", getFY($this->_REQUEST->buildingPlanApprovalDate))[1] . "-04-01" : $this->_newForm;
            // }
            //end of changes

            //changes by prity pandey
            $this->_propFyearFrom = Carbon::parse($this->_newForm)->format('Y');
            //if ($this->getAssestmentTypeStr() == 'Bifurcation') {
            // list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->approvedDate));
            // $this->_newForm = ($fromYear - 2) . "-04-01";
            // Calculate area percentage and distribute the arrear amount
            // $totalArea = $this->_REQUEST->areaOfPlot * 0.092903; // Square meter conversion
            // $bifurcatedArea = $this->_REQUEST->bifurcatedPlot * 0.092903; // Bifurcated area
            // $areaPercentage = $bifurcatedArea / $totalArea;

            // $this->_oldUnpayedAmount = round($this->_oldUnpayedAmount * $areaPercentage);
            //}
        }
        //end of change

        if (($this->getAssestmentTypeStr() == 'Amalgamation'))
            $this->_propFyearFrom = Carbon::parse($this->_REQUEST->dateOfPurchase)->format('Y');

        $currentFYear = $this->_carbonToday->format('Y');
        $this->_pendingYrs = ($currentFYear - $this->_propFyearFrom) + 1;                      // Read Total FYears
        $propMonth = Carbon::parse($this->_REQUEST->dateOfPurchase)->format('m');

        if ($propMonth > 3) {                                                           // Adjustment of Pending Years by Financial Years
            $this->_GRID['pendingYrs'] = $this->_pendingYrs;
        }

        if ($propMonth < 4) {
            $this->_propFyearFrom = $this->_propFyearFrom - 1;
            $this->_pendingYrs = ($currentFYear - $this->_propFyearFrom) + 1;
            $this->_GRID['pendingYrs'] =  $this->_pendingYrs;                               // Calculate Total Fyears
        }

        $this->_calculatorParams = [
            'areaOfPlot' => $this->getAssestmentTypeStr() == 'Bifurcation' ? $this->_REQUEST->bifurcatedPlot * 0.092903 : $this->_REQUEST->areaOfPlot * 0.092903,                         // Square feet to square meter conversion
            'category' => $this->_REQUEST->category,
            'dateOfPurchase' => $this->_REQUEST->dateOfPurchase,
            'floors' => $this->_REQUEST->floor
        ];

        $this->_maintancePerc = 10;

        if ($this->_REQUEST->propertyType != 4)                                            // i.e for building case
            $this->_calculationDateFrom = collect($this->_REQUEST->floor)->sortBy('dateFrom')->first()['dateFrom'];
        else
            $this->_calculationDateFrom = $this->_REQUEST->dateOfPurchase;

        # For Amalgamation
        if ($this->getAssestmentTypeStr() == 'Amalgamation')
            $this->_calculationDateFrom = $this->_REQUEST->dateOfPurchase;

        // if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() != 'New Assessment') && ($this->getAssestmentTypeStr() != 'Amalgamation')) {
        //     $this->_calculationDateFrom = $this->_newForm ? $this->_newForm : $this->_calculationDateFrom;
        //     // $this->_calculationDateFrom =  Carbon::now()->format('Y-m-d');
        // }

        if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() != 'New Assessment') && ($this->getAssestmentTypeStr() != 'Amalgamation')) {
            $this->_calculationDateFrom = $this->_newForm ? $this->_newForm : $this->_calculationDateFrom;
            // $this->_calculationDateFrom =  Carbon::now()->format('Y-m-d');
        }
        if (isset($this->_REQUEST->assessmentType) && (in_array($this->getAssestmentTypeStr(), ['Reassessment', 'Bifurcation']))) {
            // $this->_calculationDateFrom =  Carbon::now()->format('Y-m-d');
        }

        #========condition for new assessment==Written by prity pandey ====== 25-09-2024====#
        if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() == 'New Assessment') && ($this->_REQUEST->propertyType != 4)) {
            $this->_calculationDateFrom = Carbon::now()->addYears(-5)->format('Y-m-d');
            if ($this->_REQUEST->buildingPlanCompletionDate) {
                list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->buildingPlanCompletionDate));
                $this->_calculationDateFrom = ($fromYear) . "-04-01";
            } elseif ($this->_REQUEST->buildingPlanApprovalDate) {
                list($fromYear, $lastYear) = explode("-", getFY($this->_REQUEST->buildingPlanApprovalDate));
                $this->_calculationDateFrom = ($lastYear) . "-04-01";
            }
        }


        if (isset($this->_REQUEST->assessmentType) && ($this->getAssestmentTypeStr() == 'New Assessment') && ($this->_REQUEST->propertyType == 4)) {
            $this->_calculationDateFrom = Carbon::now()->addYears(-5)->format('Y-m-d');
        }
        #=====End of changes==========#
        $this->_currentFyear = calculateFYear(Carbon::now()->format('Y-m-d'));
    }

    public function setOldFloors()
    {
        if ($this->getAssestmentTypeStr() != 'New Assessment') {
            $this->_OldFloors = collect($this->_REQUEST->floor)->whereNotNull("propFloorDetailId");
        }
    }
    // public function setNewFloors()
    // {
    //     $this->_newFloors = collect($this->_REQUEST->floor)->whereNull("propFloorDetailId");
    //     if ($this->getAssestmentTypeStr() == 'New Assessment') {
    //         $this->_newFloors = collect($this->_REQUEST->floor);
    //     }
    // }

    public function setNewFloors()
    {
        $this->_newFloors = collect($this->_REQUEST->floor)->whereNull("propFloorDetailId");
        if ($this->getAssestmentTypeStr() == 'New Assessment') {
            $this->_newFloors = collect($this->_REQUEST->floor)->map(function ($val) {
                $val["dateFrom"] = $val["dateFrom"] > Carbon::now()->addYears(-5)->format('Y-m-d') ? Carbon::now()->addYears(-5)->format('Y-m-d') : $val["dateFrom"];
                return $val;
            });
        }
    }

    public function generateFloorWiseTax()
    {
        if ($this->_REQUEST->propertyType != 4) {
            $totalFloor = collect($this->_REQUEST->floor)->count();
            //$totalBuildupArea = collect($this->_REQUEST->floor)->sum("buildupArea");
            $totalBuildupArea = collect($this->_REQUEST->floor)->whereNotIn('usage_type_mstr_id', Config::get('akola-property-constant.MANGAL_TOWER_ID'))
                    ->sum("builtup_area");
            if ($this->getAssestmentTypeStr() == 'Bifurcation') {
                $bifurcatedFloorArea = collect($this->_REQUEST->floor)->whereNotNull('propFloorDetailId')->sum("biBuildupArea");
                $newFloorArea = collect($this->_REQUEST->floor)->whereNull('propFloorDetailId')->sum("buildupArea");
                $totalBuildupArea =  $bifurcatedFloorArea + $newFloorArea;
            }
            $AllDiffArrea = ($totalBuildupArea - $this->_REQUEST->nakshaAreaOfPlot) > 0 ? $totalBuildupArea - $this->_REQUEST->nakshaAreaOfPlot : 0;

            // if ($this->getAssestmentTypeStr() == 'Reassessment') {
            //     $newFloorArea = collect($this->_REQUEST->floor)->whereNull('propFloorDetailId')->sum("buildupArea");
            //     $AllDiffArrea = ($newFloorArea - $this->_REQUEST->nakshaAreaOfPlot) > 0 ? $newFloorArea - $this->_REQUEST->nakshaAreaOfPlot : 0;
            // }

            if ($this->getAssestmentTypeStr() == 'Reassessment') {
                $mPropFloors = new PropFloor();
                $oldProperty = PropProperty::find($this->_REQUEST->previousHoldingId);
                $floors = $mPropFloors->getPropFloors($oldProperty->id);
                $totalFloorArea = collect($floors)->sum("buildupArea");
                $totalBuildupArea = collect($this->_REQUEST->floor)->sum("buildupArea");
                $newFloorArea = $totalBuildupArea - $totalFloorArea;
                $AllDiffArrea = ($newFloorArea - $this->_REQUEST->nakshaAreaOfPlot) > 0 ? $newFloorArea - $this->_REQUEST->nakshaAreaOfPlot : 0;
            }

            foreach ($this->_OldFloors as $key => $item) {
                $item = (object)$item;
                $rate = $this->readRateByFloor($item);                // (2.1)
                //if ($item->usageType == $this->_mangalTowerId) {
                //$this->mangalTower($item);
                if (in_array($item->usageType, $this->_mangalTowerId)) {
                    $this->_floorsTaxes[$key] = $this->mangalTower($item);
                    continue;
                }
                $agingPerc = $this->readAgingByFloor($item);           // (2.2)

                $floorBuildupArea = roundFigure(isset($item->biBuildupArea) && $this->getAssestmentTypeStr() == 'Bifurcation' ? $item->biBuildupArea * 0.092903 :  $item->buildupArea  * 0.092903);
                $alv = ($item->occupancyType == 2 && isset($item->rentAmount)) ? roundFigure($item->rentAmount * 12) : roundFigure($floorBuildupArea * $rate);

                //chnage prity

                $alv = ($item->occupancyType == 2 && !($item->agreementAvailable ?? false))
                    ? roundFigure($floorBuildupArea * $rate * 2.5)
                    : $alv;
                //
                $maintance10Perc = roundFigure(($alv * $this->_maintancePerc) / 100);
                $valueAfterMaintanance = roundFigure($alv - $maintance10Perc);
                $aging = roundFigure(($valueAfterMaintanance * $agingPerc) / 100);
                $taxValue = roundFigure($valueAfterMaintanance - $aging);               // Tax value is the amount in which all the tax will be calculated

                // Municipal Taxes
                // $generalTax = $this->_isSingleManArmedForce ? 0 : roundFigure($taxValue * 0.30);
                $generalTax = roundFigure($taxValue * 0.30);
                $roadTax = roundFigure($taxValue * 0.03);
                $firefightingTax = roundFigure($taxValue * 0.02);
                $educationTax = roundFigure($taxValue * 0.02);
                $waterTax = roundFigure($taxValue * 0.02);
                $cleanlinessTax = roundFigure($taxValue * 0.02);
                $sewerageTax = roundFigure($taxValue * 0.02);
                $treeTax = roundFigure($taxValue * 0.01);

                $isCommercial = (in_array($item->usageType, $this->_residentialUsageType)) ? false : true;                    // Residential usage type id

                $stateTaxes = $this->readStateTaxes($floorBuildupArea, $isCommercial, $alv);                   // Read State Taxes(2.3)
                if (in_array($item->usageType, $this->_govResidentialUsageType)) {
                    $educationTax  = 0;
                }

                $stateTaxes = $this->readStateTaxes($floorBuildupArea, $isCommercial, $alv);                   // Read State Taxes(2.3)

                $tax1 = 0;
                $diffArrea = 0;
                $doubleTax1 = ($generalTax + $roadTax + $firefightingTax + $educationTax
                    + $waterTax + $cleanlinessTax + $sewerageTax
                    + $treeTax + $stateTaxes['educationTax'] + $stateTaxes['professionalTax']
                    + ($openPloatTax ?? 0));
                if ($this->_REQUEST->nakshaAreaOfPlot) {
                    // $diffArrea = ($this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot)>0 ? $this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot :0;
                    $diffArrea = $AllDiffArrea / ($totalBuildupArea > 0 ? $totalBuildupArea : 1);
                }


                # double tax apply
                if ($this->_REQUEST->isAllowDoubleTax) {
                    $tax1 = $doubleTax1;
                }
                # 100% penalty apply on diff arrea
                elseif ($diffArrea > 0) {
                    // $tax1 = $doubleTax1 * ($diffArrea / ($this->_REQUEST->areaOfPlot>0?$this->_REQUEST->areaOfPlot:1));
                    $tax1 = $doubleTax1 * ($diffArrea);
                }
                if ($diffArrea > 0 && $this->getAssestmentTypeStr() == 'Reassessment') {
                    $tax1 = 0;
                }
                $this->_floorsTaxes[$key] = [
                    'usageType' => $item->usageType,
                    'usageTypeName' => $item->usageTypeName ?? "",
                    'constructionType' => $item->constructionType ?? "",
                    'constructionTypeVal' => Config::get("PropertyConstaint.CONSTRUCTION-TYPE." . $item->constructionType ?? ""),
                    'occupancyType' => $item->occupancyType ?? "",
                    'occupancyTypeVal' => Config::get("PropertyConstaint.OCCUPANCY-TYPE." . $item->occupancyType ?? ""),
                    'dateFrom' => $item->dateFrom,
                    'dateUpto' => $item->dateUpto,
                    'appliedFrom' => getFY($item->dateFrom),
                    'appliedUpto' => getFY($item->dateUpto),
                    'rate' => $rate,
                    'floorKey' => $key,
                    'floorNo' => $item->floorNo,
                    'floorName' => $item->floorName ?? "",
                    'buildupAreaInSqmt' => $floorBuildupArea,
                    'rentAmount' => $item->rentAmount ?? 0,
                    'alv' => $alv,
                    'maintancePerc' => $this->_maintancePerc,
                    'maintantance10Perc' => $maintance10Perc,
                    'valueAfterMaintance' => $valueAfterMaintanance,
                    'agingPerc' => $agingPerc,
                    'agingAmt' => $aging,
                    'taxValue' => $taxValue,
                    'generalTax' => $generalTax,
                    'roadTax' => $roadTax,
                    'firefightingTax' => $firefightingTax,
                    'educationTax' => $educationTax,
                    'waterTax' => $waterTax,
                    'cleanlinessTax' => $cleanlinessTax,
                    'sewerageTax' => $sewerageTax,
                    'treeTax' => $treeTax,
                    "tax1" => $tax1,
                    'isCommercial' => $isCommercial,
                    'stateEducationTaxPerc' => $stateTaxes['educationTaxPerc'],
                    'stateEducationTax' => $stateTaxes['educationTax'],
                    'professionalTaxPerc' => $stateTaxes['professionalTaxPerc'],
                    'professionalTax' => $stateTaxes['professionalTax'],
                ];
            }


            foreach ($this->_newFloors as $key => $item) {
                $item = (object)$item;
                $rate = $this->readRateByFloor($item);                 // (2.1)
                //if ($item->usageType == $this->_mangalTowerId) {
                //$this->mangalTower($item);
                if (in_array($item->usageType, $this->_mangalTowerId)) {
                    $this->_floorsTaxes[$key] = $this->mangalTower($item);
                    continue;
                }
                $applyArrea = 0;
                if ($this->getAssestmentTypeStr() == 'Reassessment') {
                    $AllDiffArrea = 0;
                    $safFloor = PropActiveSafsFloor::find($item->floorID ?? 0);
                    if (!$safFloor) {
                        $safFloor = PropSafsFloor::find($item->floorID ?? 0);
                    }
                    $applyArrea = $safFloor->builtup_area ?? 0;
                    $AllDiffArrea =  ($safFloor &&  $safFloor->builtup_area > $item->buildupArea) ?  ($safFloor->builtup_area - $item->buildupArea) : 0;
                }
                $agingPerc = $this->readAgingByFloor($item);           // (2.2)

                $floorBuildupArea = roundFigure(isset($item->biBuildupArea) && $this->getAssestmentTypeStr() == 'Bifurcation' ? $item->biBuildupArea * 0.092903 :  $item->buildupArea  * 0.092903);
                $alv = ($item->occupancyType == 2 && isset($item->rentAmount)) ? roundFigure($item->rentAmount * 12) : roundFigure($floorBuildupArea * $rate);

                //chnage prity
                $alv = ($item->occupancyType == 2 && !($item->agreementAvailable ?? false))
                    ? roundFigure($floorBuildupArea * $rate * 2.5)
                    : $alv;

                //
                //    dd($item,$alv,!($item->agreementAvailable??false));
                $maintance10Perc = roundFigure(($alv * $this->_maintancePerc) / 100);
                $valueAfterMaintanance = roundFigure($alv - $maintance10Perc);
                $aging = roundFigure(($valueAfterMaintanance * $agingPerc) / 100);
                $taxValue = roundFigure($valueAfterMaintanance - $aging);               // Tax value is the amount in which all the tax will be calculated

                // Municipal Taxes
                // $generalTax = $this->_isSingleManArmedForce ? 0 : roundFigure($taxValue * 0.30);
                $generalTax = roundFigure($taxValue * 0.30);
                $roadTax = roundFigure($taxValue * 0.03);
                $firefightingTax = roundFigure($taxValue * 0.02);
                $educationTax = roundFigure($taxValue * 0.02);
                $waterTax = roundFigure($taxValue * 0.02);
                $cleanlinessTax = roundFigure($taxValue * 0.02);
                $sewerageTax = roundFigure($taxValue * 0.02);
                $treeTax = roundFigure($taxValue * 0.01);

                $isCommercial = (in_array($item->usageType, $this->_residentialUsageType)) ? false : true;                    // Residential usage type id

                $stateTaxes = $this->readStateTaxes($floorBuildupArea, $isCommercial, $alv);                   // Read State Taxes(2.3)
                if (in_array($item->usageType, $this->_govResidentialUsageType)) {
                    $educationTax  = 0;
                }

                $stateTaxes = $this->readStateTaxes($floorBuildupArea, $isCommercial, $alv);                   // Read State Taxes(2.3)

                $tax1 = 0;
                $diffArrea = 0;
                $doubleTax1 = ($generalTax + $roadTax + $firefightingTax + $educationTax
                    + $waterTax + $cleanlinessTax + $sewerageTax
                    + $treeTax + $stateTaxes['educationTax'] + $stateTaxes['professionalTax']
                    + ($openPloatTax ?? 0));
                if ($this->_REQUEST->nakshaAreaOfPlot) {
                    // $diffArrea = ($this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot)>0 ? $this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot :0;
                    $diffArrea = $AllDiffArrea / ($totalBuildupArea > 0 ? $totalBuildupArea : 1);
                }

                if ($this->_REQUEST->nakshaAreaOfPlot && $this->getAssestmentTypeStr() == 'Reassessment') {
                    // $diffArrea = ($this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot)>0 ? $this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot :0;
                    $diffArrea = $AllDiffArrea / ($newFloorArea > 0 ? $newFloorArea : 1);
                }
                if ($this->getAssestmentTypeStr() == 'Reassessment') {
                    // $diffArrea = ($this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot)>0 ? $this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot :0;
                    $diffArrea = $AllDiffArrea / ($applyArrea > 0 ? $applyArrea : 1);
                }
                # double tax apply
                if ($this->_REQUEST->isAllowDoubleTax) {
                    $tax1 = $doubleTax1;
                }
                # 100% penalty apply on diff arrea
                elseif ($diffArrea > 0) {
                    // $tax1 = $doubleTax1 * ($diffArrea / ($this->_REQUEST->areaOfPlot>0?$this->_REQUEST->areaOfPlot:1));
                    $tax1 = $doubleTax1 * ($diffArrea);
                }
                $this->_floorsTaxes[$key] = [
                    'usageType' => $item->usageType,
                    'usageTypeName' => $item->usageTypeName ?? "",
                    'constructionType' => $item->constructionType ?? "",
                    'constructionTypeVal' => Config::get("PropertyConstaint.CONSTRUCTION-TYPE." . $item->constructionType ?? ""),
                    'occupancyType' => $item->occupancyType ?? "",
                    'occupancyTypeVal' => Config::get("PropertyConstaint.OCCUPANCY-TYPE." . $item->occupancyType ?? ""),
                    'dateFrom' => $item->dateFrom,
                    'dateUpto' => $item->dateUpto,
                    'appliedFrom' => getFY($item->dateFrom),
                    'appliedUpto' => getFY($item->dateUpto),
                    'rate' => $rate,
                    'floorKey' => $key,
                    'floorNo' => $item->floorNo,
                    'floorName' => $item->floorName ?? "",
                    'buildupAreaInSqmt' => $floorBuildupArea,
                    'rentAmount' => $item->rentAmount ?? 0,
                    'alv' => $alv,
                    'maintancePerc' => $this->_maintancePerc,
                    'maintantance10Perc' => $maintance10Perc,
                    'valueAfterMaintance' => $valueAfterMaintanance,
                    'agingPerc' => $agingPerc,
                    'agingAmt' => $aging,
                    'taxValue' => $taxValue,
                    'generalTax' => $generalTax,
                    'roadTax' => $roadTax,
                    'firefightingTax' => $firefightingTax,
                    'educationTax' => $educationTax,
                    'waterTax' => $waterTax,
                    'cleanlinessTax' => $cleanlinessTax,
                    'sewerageTax' => $sewerageTax,
                    'treeTax' => $treeTax,
                    "tax1" => $tax1,
                    'isCommercial' => $isCommercial,
                    'stateEducationTaxPerc' => $stateTaxes['educationTaxPerc'],
                    'stateEducationTax' => $stateTaxes['educationTax'],
                    'professionalTaxPerc' => $stateTaxes['professionalTaxPerc'],
                    'professionalTax' => $stateTaxes['professionalTax'],
                ];
            }

            $this->_GRID['floorsTaxes'] = $this->_floorsTaxes;
        }
    }

    /**
     * | Calculate Floor Wise Calculation including Vacant also (2)
     */
    public function generateFloorWiseTax_old()
    {
        if ($this->_REQUEST->propertyType != 4) {
            $totalFloor = collect($this->_REQUEST->floor)->count();
            $totalBuildupArea = collect($this->_REQUEST->floor)->sum("buildupArea");
            if ($this->getAssestmentTypeStr() == 'Bifurcation') {
                $bifurcatedFloorArea = collect($this->_REQUEST->floor)->whereNotNull('propFloorDetailId')->sum("biBuildupArea");
                $newFloorArea = collect($this->_REQUEST->floor)->whereNull('propFloorDetailId')->sum("buildupArea");
                $totalBuildupArea =  $bifurcatedFloorArea + $newFloorArea;
            }
            $AllDiffArrea = ($totalBuildupArea - $this->_REQUEST->nakshaAreaOfPlot) > 0 ? $totalBuildupArea - $this->_REQUEST->nakshaAreaOfPlot : 0;

            foreach ($this->_REQUEST->floor as $key => $item) {
                $item = (object)$item;
                $rate = $this->readRateByFloor($item);                 // (2.1)
                $agingPerc = $this->readAgingByFloor($item);           // (2.2)

                $floorBuildupArea = roundFigure(isset($item->biBuildupArea) && $this->getAssestmentTypeStr() == 'Bifurcation' ? $item->biBuildupArea * 0.092903 :  $item->buildupArea  * 0.092903);
                $alv = roundFigure($floorBuildupArea * $rate);
                $maintance10Perc = roundFigure(($alv * $this->_maintancePerc) / 100);
                $valueAfterMaintanance = roundFigure($alv - $maintance10Perc);
                $aging = roundFigure(($valueAfterMaintanance * $agingPerc) / 100);
                $taxValue = roundFigure($valueAfterMaintanance - $aging);               // Tax value is the amount in which all the tax will be calculated

                // Municipal Taxes
                // $generalTax = $this->_isSingleManArmedForce ? 0 : roundFigure($taxValue * 0.30);
                $generalTax = roundFigure($taxValue * 0.30);
                $roadTax = roundFigure($taxValue * 0.03);
                $firefightingTax = roundFigure($taxValue * 0.02);
                $educationTax = roundFigure($taxValue * 0.02);
                $waterTax = roundFigure($taxValue * 0.02);
                $cleanlinessTax = roundFigure($taxValue * 0.02);
                $sewerageTax = roundFigure($taxValue * 0.02);
                $treeTax = roundFigure($taxValue * 0.01);

                $isCommercial = (in_array($item->usageType, $this->_residentialUsageType)) ? false : true;                    // Residential usage type id

                $stateTaxes = $this->readStateTaxes($floorBuildupArea, $isCommercial, $alv);                   // Read State Taxes(2.3)
                if (in_array($item->usageType, $this->_govResidentialUsageType)) {
                    $educationTax  = 0;
                }
                $tax1 = 0;
                $diffArrea = 0;
                $doubleTax1 = ($generalTax + $roadTax + $firefightingTax + $educationTax
                    + $waterTax + $cleanlinessTax + $sewerageTax
                    + $treeTax + $stateTaxes['educationTax'] + $stateTaxes['professionalTax']
                    + ($openPloatTax ?? 0));
                if ($this->_REQUEST->nakshaAreaOfPlot) {
                    // $diffArrea = ($this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot)>0 ? $this->_REQUEST->nakshaAreaOfPlot - $this->_REQUEST->areaOfPlot :0;
                    $diffArrea = $AllDiffArrea / ($totalBuildupArea > 0 ? $totalBuildupArea : 1);
                }
                # double tax apply
                if ($this->_REQUEST->isAllowDoubleTax) {
                    $tax1 = $doubleTax1;
                }
                # 100% penalty apply on diff arrea
                elseif ($diffArrea > 0) {
                    // $tax1 = $doubleTax1 * ($diffArrea / ($this->_REQUEST->areaOfPlot>0?$this->_REQUEST->areaOfPlot:1));
                    $tax1 = $doubleTax1 * ($diffArrea);
                }
                $this->_floorsTaxes[$key] = [
                    'usageType' => $item->usageType,
                    'usageTypeName' => $item->usageTypeName ?? "",
                    'constructionType' => $item->constructionType ?? "",
                    'constructionTypeVal' => Config::get("PropertyConstaint.CONSTRUCTION-TYPE." . $item->constructionType ?? ""),
                    'occupancyType' => $item->occupancyType ?? "",
                    'occupancyTypeVal' => Config::get("PropertyConstaint.OCCUPANCY-TYPE." . $item->occupancyType ?? ""),
                    'dateFrom' => $item->dateFrom,
                    'dateUpto' => $item->dateUpto,
                    'appliedFrom' => getFY($item->dateFrom),
                    'appliedUpto' => getFY($item->dateUpto),
                    'rate' => $rate,
                    'floorKey' => $key,
                    'floorNo' => $item->floorNo,
                    'floorName' => $item->floorName ?? "",
                    'buildupAreaInSqmt' => $floorBuildupArea,
                    'alv' => $alv,
                    'maintancePerc' => $this->_maintancePerc,
                    'maintantance10Perc' => $maintance10Perc,
                    'valueAfterMaintance' => $valueAfterMaintanance,
                    'agingPerc' => $agingPerc,
                    'agingAmt' => $aging,
                    'taxValue' => $taxValue,
                    'generalTax' => $generalTax,
                    'roadTax' => $roadTax,
                    'firefightingTax' => $firefightingTax,
                    'educationTax' => $educationTax,
                    'waterTax' => $waterTax,
                    'cleanlinessTax' => $cleanlinessTax,
                    'sewerageTax' => $sewerageTax,
                    'treeTax' => $treeTax,
                    "tax1" => $tax1,
                    'isCommercial' => $isCommercial,
                    'stateEducationTaxPerc' => $stateTaxes['educationTaxPerc'],
                    'stateEducationTax' => $stateTaxes['educationTax'],
                    'professionalTaxPerc' => $stateTaxes['professionalTaxPerc'],
                    'professionalTax' => $stateTaxes['professionalTax'],
                ];
            }

            $this->_GRID['floorsTaxes'] = $this->_floorsTaxes;
        }
    }

    /**
     * | Read Rate to Calculate ALV of the floor (2.1)
     */
    public function readRateByFloor($item)
    {
        $constType = $this->_refPropConstTypes->where('id', $item->constructionType);
        if ($constType->isEmpty())
            throw new Exception("Construction type not Available");
        $category = $this->_REQUEST->category;
        if ($category == 1)
            $rate = $constType->first()->category1_rate;
        elseif ($category == 2)
            $rate = $constType->first()->category2_rate;
        else
            $rate = $constType->first()->category3_rate;

        return $rate;
    }

    /**
     *
     * | Read aging of the floor(2.2)
     */
    public function readAgingByFloor($item)
    {
        $agings = $this->_agingPercs;
        $constYear = Carbon::parse($item->dateFrom)->diffInYears(Carbon::now());
        $perc = 0;
        if ($constYear > 10) {
            $perc = collect($agings)->where('const_id', $item->constructionType)
                ->where('range_from', '<=', $constYear)
                ->sortByDesc('range_from')
                ->first();
            $perc = $perc['aging_perc'];
        }
        return $perc;
    }

    /**
     * | Calculate Vacant wise Tax (3)
     */
    public function generateVacantWiseTax()
    {
        if ($this->_REQUEST->propertyType == 4) {
            $agingPerc = 0;                         // No Aging Percent for Vacant Land
            if ($this->_REQUEST->category == 1)
                $rate = 11;
            elseif ($this->_REQUEST->category == 2)
                $rate = 10;
            else
                $rate = 8;

            $alv = roundFigure($this->_calculatorParams['areaOfPlot'] * $rate);
            $maintance10Perc = 0; #roundFigure(($alv * $this->_maintancePerc) / 100);
            $valueAfterMaintanance = roundFigure($alv - ($alv * 0.1)); # 10% minuse on ALV
            $aging = 0; #roundFigure(($valueAfterMaintanance * $agingPerc) / 100);
            $taxValue = roundFigure($valueAfterMaintanance - $aging);

            // Municipal Taxes
            $generalTax = 0; #roundFigure($taxValue * 0.30);
            $roadTax = 0; #roundFigure($taxValue * 0.03);
            $firefightingTax = 0; #roundFigure($taxValue * 0.02);
            $educationTax = 0; #roundFigure($taxValue * 0.02);
            $waterTax = 0; #roundFigure($taxValue * 0.02);
            $cleanlinessTax = 0; #roundFigure($taxValue * 0.02);
            $sewerageTax = 0; #roundFigure($taxValue * 0.02);
            $treeTax = roundFigure($taxValue * 0.01);
            $openPloatTax = roundFigure($taxValue * 0.43);

            $isCommercial = false;

            $stateTaxes = $this->readStateTaxes($this->_calculatorParams['areaOfPlot'], $isCommercial, $alv);                   // Read State Taxes(3.1)

            $tax1 = 0;
            $diffArrea = 0;
            $doubleTax1 = ($generalTax + $roadTax + $firefightingTax + $educationTax
                + $waterTax + $cleanlinessTax + $sewerageTax
                + $treeTax + $stateTaxes['educationTax'] + $stateTaxes['professionalTax']
                + ($openPloatTax ?? 0));
            if ($this->_REQUEST->nakshaAreaOfPlot) {
                $diffArrea = ($this->_REQUEST->areaOfPlot - $this->_REQUEST->nakshaAreaOfPlot) > 0 ? $this->_REQUEST->areaOfPlot -  $this->_REQUEST->nakshaAreaOfPlot : 0;
            }
            # double tax apply
            if ($this->_REQUEST->isAllowDoubleTax) {
                $tax1 = $doubleTax1;
            }
            # 100% penalty apply on diff arrea
            elseif ($diffArrea > 0) {
                $tax1 = $doubleTax1 * ($diffArrea / ($this->_REQUEST->areaOfPlot > 0 ? $this->_REQUEST->areaOfPlot : 1));
            }

            $this->_floorsTaxes[0] = [
                'dateFrom' => $this->_REQUEST->approvedDate ? Carbon::parse($this->_REQUEST->approvedDate)->addYears(-5)->format('Y-m-d') : Carbon::now()->addYears(-5)->format('Y-m-d'),
                'dateUpto' => null,
                'appliedFrom' => getFY($this->_REQUEST->approvedDate ? Carbon::parse($this->_REQUEST->approvedDate)->addYears(-5)->format('Y-m-d') : Carbon::now()->addYears(-5)->format('Y-m-d')),
                'appliedUpto' => getFY(),
                'rate' => $rate,
                'floorKey' => "Vacant Land",
                'floorNo' => "Vacant Land",
                'alv' => $alv,
                'maintancePerc' => $this->_maintancePerc,
                'maintantance10Perc' => $maintance10Perc,
                'valueAfterMaintance' => $valueAfterMaintanance,
                'agingPerc' => $agingPerc,
                'agingAmt' => $aging,
                'taxValue' => $taxValue,
                'generalTax' => $generalTax,
                'roadTax' => $roadTax,
                'firefightingTax' => $firefightingTax,
                'educationTax' => $educationTax,
                'waterTax' => $waterTax,
                'cleanlinessTax' => $cleanlinessTax,
                'sewerageTax' => $sewerageTax,
                'treeTax' => $treeTax,
                "tax1" => $tax1,
                "openPloatTax" => $openPloatTax,
                'isCommercial' => $isCommercial,
                'stateEducationTaxPerc' => $stateTaxes['educationTaxPerc'],
                'stateEducationTax' => $stateTaxes['educationTax'],
                'professionalTaxPerc' => $stateTaxes['professionalTaxPerc'],
                'professionalTax' => $stateTaxes['professionalTax'],
            ];
        }

        $this->_GRID['floorsTaxes'] = $this->_floorsTaxes;
    }

    /**
     * | read State Taxes (2.3) && (3.1)
     */
    public function readStateTaxes($floorBuildupArea, $isCommercial, $alv = 0)
    {
        // State Taxes
        if (is_between(round($alv), 0, 150)) {
            $stateEducationTaxPerc = $isCommercial ? 4 : 2;
            // $stateEducationTax = roundFigure(($floorBuildupArea * $stateEducationTaxPerc) / 100);
            $stateEducationTax = roundFigure(($alv * $stateEducationTaxPerc) / 100);
            $professionalTaxPerc = $isCommercial ? 1 : 0;
            // $professionalTax = roundFigure(($floorBuildupArea * $professionalTaxPerc) / 100);
            $professionalTax = roundFigure(($alv * $professionalTaxPerc) / 100);
        }

        if (is_between(round($alv), 151, 300)) {
            $stateEducationTaxPerc = $isCommercial ? 6 : 3;
            // $stateEducationTax = roundFigure(($floorBuildupArea * $stateEducationTaxPerc) / 100);
            $stateEducationTax = roundFigure(($alv * $stateEducationTaxPerc) / 100);
            $professionalTaxPerc = $isCommercial ? 1.5 : 0;
            // $professionalTax = roundFigure(($floorBuildupArea * $professionalTaxPerc) / 100);
            $professionalTax = roundFigure(($alv * $professionalTaxPerc) / 100);
        }

        if (is_between(round($alv), 301, 3000)) {
            $stateEducationTaxPerc = $isCommercial ? 8 : 4;
            // $stateEducationTax = roundFigure(($floorBuildupArea * $stateEducationTaxPerc) / 100);
            $stateEducationTax = roundFigure(($alv * $stateEducationTaxPerc) / 100);
            $professionalTaxPerc = $isCommercial ? 2 : 0;
            // $professionalTax = roundFigure(($floorBuildupArea * $professionalTaxPerc) / 100);
            $professionalTax = roundFigure(($alv * $professionalTaxPerc) / 100);
        }

        if (is_between(round($alv), 3001, 6000)) {
            $stateEducationTaxPerc = $isCommercial ? 10 : 5;
            // $stateEducationTax = roundFigure(($floorBuildupArea * $stateEducationTaxPerc) / 100);
            $stateEducationTax = roundFigure(($alv * $stateEducationTaxPerc) / 100);
            $professionalTaxPerc = $isCommercial ? 2.5 : 0;
            // $professionalTax = roundFigure(($floorBuildupArea * $professionalTaxPerc) / 100);
            $professionalTax = roundFigure(($alv * $professionalTaxPerc) / 100);
        }

        if (round($alv) > 6000) {
            $stateEducationTaxPerc = $isCommercial ? 12 : 6;
            // $stateEducationTax = roundFigure(($floorBuildupArea * $stateEducationTaxPerc) / 100);
            $stateEducationTax = roundFigure(($alv * $stateEducationTaxPerc) / 100);
            $professionalTaxPerc = $isCommercial ? 3 : 0;
            // $professionalTax = roundFigure(($floorBuildupArea * $professionalTaxPerc) / 100);
            $professionalTax = roundFigure(($alv * $professionalTaxPerc) / 100);
        }

        return [
            'educationTaxPerc' => $stateEducationTaxPerc,
            'educationTax' => $stateEducationTax,
            'professionalTaxPerc' => $professionalTaxPerc,
            'professionalTax' => $professionalTax,
        ];
    }


    /**
     * | Summation of Taxes(4.1) && (5.1)
     */
    // public function sumTaxes($floorTaxes)
    // {
    //     $annualTaxes = $floorTaxes->pipe(function (Collection $taxes) {
    //         $totalKeys = $taxes->count();
    //         $totalKeys = $totalKeys ? $totalKeys : 1;
    //         return [
    //             "alv" => (float)roundFigure($taxes->sum('alv')),
    //             "maintancePerc" => $taxes->first()['maintancePerc'] ?? 0,
    //             "maintantance10Perc" => roundFigure($taxes->sum('maintantance10Perc')),
    //             "valueAfterMaintance" => roundFigure($taxes->sum('valueAfterMaintance')),
    //             "agingPerc" => roundFigure($taxes->sum('agingPerc') / $totalKeys),
    //             "agingAmt" => roundFigure($taxes->sum('agingAmt')),
    //             "taxValue" => roundFigure($taxes->sum('taxValue')),
    //             "generalTax" => roundFigure($taxes->sum('generalTax')),
    //             "roadTax" => roundFigure($taxes->sum('roadTax')),
    //             "firefightingTax" => roundFigure($taxes->sum('firefightingTax')),
    //             "educationTax" => roundFigure($taxes->sum('educationTax')),
    //             "waterTax" => roundFigure($taxes->sum('waterTax')),
    //             "cleanlinessTax" => roundFigure($taxes->sum('cleanlinessTax')),
    //             "sewerageTax" => roundFigure($taxes->sum('sewerageTax')),
    //             "treeTax" => roundFigure($taxes->sum('treeTax')),
    //             "stateEducationTaxPerc" => roundFigure($taxes->sum('stateEducationTaxPerc') / $totalKeys),
    //             "stateEducationTax" => roundFigure($taxes->sum('stateEducationTax')),
    //             "professionalTaxPerc" => roundFigure($taxes->sum('professionalTaxPerc') / $totalKeys),
    //             "professionalTax" => roundFigure($taxes->sum('professionalTax')),
    //             "openPloatTax" => roundFigure($taxes->sum('openPloatTax')),
    //             "tax1" => roundFigure($taxes->sum('tax1')),
    //             "adjustAmount" => roundFigure($taxes->sum("adjustAmount")),
    //             "dueAmount" => roundFigure($taxes->sum("dueAmount")),
    //         ];
    //     });
    //     $annualTaxes['totalTax'] = roundFigure(
    //         $annualTaxes['generalTax'] + $annualTaxes['roadTax'] + $annualTaxes['firefightingTax'] + $annualTaxes['educationTax']
    //             + $annualTaxes['waterTax'] + $annualTaxes['cleanlinessTax'] + $annualTaxes['sewerageTax']
    //             + $annualTaxes['treeTax'] + $annualTaxes['stateEducationTax'] + $annualTaxes['professionalTax']
    //             + ($annualTaxes['openPloatTax'] ?? 0) + ($annualTaxes['tax1'] ?? 0)
    //     );
    //     $annualTaxes['totalTax2'] = roundFigure(
    //         $annualTaxes['generalTax'] + $annualTaxes['roadTax'] + $annualTaxes['firefightingTax'] + $annualTaxes['educationTax']
    //             + $annualTaxes['waterTax'] + $annualTaxes['cleanlinessTax'] + $annualTaxes['sewerageTax']
    //             + $annualTaxes['treeTax'] + $annualTaxes['stateEducationTax'] + $annualTaxes['professionalTax']
    //             + ($annualTaxes['openPloatTax'] ?? 0)
    //     );
    //     return $annualTaxes;
    // }

    //change by prity pandey
    public function sumTaxes($floorTaxes)
    {
        $annualTaxes = $floorTaxes->pipe(function (Collection $taxes) {
            $totalKeys = $taxes->count();
            $totalKeys = $totalKeys ? $totalKeys : 1;
            return [
                "alv" => (float)roundFigure($taxes->sum('alv')),
                "maintancePerc" => $taxes->first()['maintancePerc'] ?? 0,
                "maintantance10Perc" => roundFigure($taxes->sum('maintantance10Perc')),
                "valueAfterMaintance" => roundFigure($taxes->sum('valueAfterMaintance')),
                "agingPerc" => roundFigure($taxes->sum('agingPerc') / $totalKeys),
                "agingAmt" => roundFigure($taxes->sum('agingAmt')),
                "taxValue" => roundFigure($taxes->sum('taxValue')),
                "generalTax" => roundFigure($taxes->sum('generalTax')),
                "roadTax" => roundFigure($taxes->sum('roadTax')),
                "firefightingTax" => roundFigure($taxes->sum('firefightingTax')),
                "educationTax" => roundFigure($taxes->sum('educationTax')),
                "waterTax" => roundFigure($taxes->sum('waterTax')),
                "cleanlinessTax" => roundFigure($taxes->sum('cleanlinessTax')),
                "sewerageTax" => roundFigure($taxes->sum('sewerageTax')),
                "treeTax" => roundFigure($taxes->sum('treeTax')),
                "stateEducationTaxPerc" => roundFigure($taxes->sum('stateEducationTaxPerc') / $totalKeys),
                "stateEducationTax" => roundFigure($taxes->sum('stateEducationTax')),
                "professionalTaxPerc" => roundFigure($taxes->sum('professionalTaxPerc') / $totalKeys),
                "professionalTax" => roundFigure($taxes->sum('professionalTax')),
                "openPloatTax" => roundFigure($taxes->sum('openPloatTax')),
                "tax1" => roundFigure($taxes->sum('tax1')),
                "tax2" => roundFigure($taxes->sum('tax2')),
                "tax3" => roundFigure($taxes->sum('tax3')),

                "waterBenefitTax" => roundFigure($taxes->sum("waterBenefitTax")),
                "waterBillTax" => roundFigure($taxes->sum("waterBillTax")),
                "spWaterCessTax" => roundFigure($taxes->sum("spWaterCessTax")),
                "drainCessTax" => roundFigure($taxes->sum("drainCessTax")),
                "lightCessTax" => roundFigure($taxes->sum("lightCessTax")),
                "majorBuildingTax" => roundFigure($taxes->sum("majorBuildingTax")),

                "adjustAmount" => roundFigure($taxes->sum("adjustAmount")),
                "dueAmount" => roundFigure($taxes->sum("dueAmount")),
            ];
        });
        $annualTaxes['totalTax'] = roundFigure(
            $annualTaxes['generalTax'] + $annualTaxes['roadTax'] + $annualTaxes['firefightingTax'] + $annualTaxes['educationTax']
                + $annualTaxes['waterTax'] + $annualTaxes['cleanlinessTax'] + $annualTaxes['sewerageTax']
                + $annualTaxes['treeTax'] + $annualTaxes['stateEducationTax'] + $annualTaxes['professionalTax']
                + ($annualTaxes['openPloatTax'] ?? 0) + ($annualTaxes['waterBenefitTax'] ?? 0) + ($annualTaxes['waterBillTax'] ?? 0)
                + ($annualTaxes['spWaterCessTax'] ?? 0) + ($annualTaxes['drainCessTax'] ?? 0) + ($annualTaxes['lightCessTax'] ?? 0) + ($annualTaxes['majorBuildingTax'] ?? 0)
                + ($annualTaxes['tax2'] ?? 0) + ($annualTaxes['tax3'] ?? 0) + ($annualTaxes['tax1'] ?? 0)


        );
        $annualTaxes['totalTax2'] = roundFigure(
            $annualTaxes['generalTax'] + $annualTaxes['roadTax'] + $annualTaxes['firefightingTax'] + $annualTaxes['educationTax']
                + $annualTaxes['waterTax'] + $annualTaxes['cleanlinessTax'] + $annualTaxes['sewerageTax']
                + $annualTaxes['treeTax'] + $annualTaxes['stateEducationTax'] + $annualTaxes['professionalTax']
                + ($annualTaxes['openPloatTax'] ?? 0) + ($annualTaxes['waterBenefitTax'] ?? 0) + ($annualTaxes['waterBillTax'] ?? 0)
                + ($annualTaxes['spWaterCessTax'] ?? 0) + ($annualTaxes['drainCessTax'] ?? 0) + ($annualTaxes['lightCessTax'] ?? 0) + ($annualTaxes['majorBuildingTax'] ?? 0)
                + ($annualTaxes['tax2'] ?? 0) + ($annualTaxes['tax3'] ?? 0)
        );
        return $annualTaxes;
    }


    /**
     * | Grand Taxes (4)
     */
    public function generateFyearWiseTaxes()
    {
        $currentFyearEndDate = Carbon::now()->endOfYear()->addMonths(3)->format('Y-m-d');
        list($From, $upto) =  explode("-", getFY());
        if ($upto) {
            $currentFyearEndDate = ($upto . "-03-31");
        }
        $fyearWiseTaxes = collect();
        // Act Of limitation
        $yearDiffs = Carbon::parse($this->_calculationDateFrom)->diffInYears(Carbon::now());                // year differences
        if ($yearDiffs >= 6) {                                                                              // Act of limitation 6 Years
            $this->_GRID['demandPendingYrs'] = 6;
            $this->_calculationDateFrom = Carbon::now()->addYears(-5)->format('Y-m-d');
        }
        #======added by Sandeep Bara========
        /**
         * for if vacand land apply New Assessment then teck tax from privisus 6 year
         */
        $privFiveYear = Carbon::now()->addYears(-5)->format('Y-m-d');
        // if ($this->_REQUEST->applyDate) {
        //     $privFiveYear = Carbon::parse($this->_REQUEST->applyDate)->addYears(-5)->format('Y-m-d');
        // }
        //changes by prity pandey

        if ($this->_REQUEST->approvedDate) {
            $privFiveYear = Carbon::parse($this->_REQUEST->approvedDate)->addYears(-5)->format('Y-m-d');
        }

        // New conditions for buildingPlanCompletionDate and buildingPlanApprovalDate
        // if ($this->_REQUEST->buildingPlanCompletionDate) {
        //     $privFiveYear = Carbon::parse($this->_REQUEST->buildingPlanCompletionDate)->addYears(-5)->format('Y-m-d');
        // } elseif ($this->_REQUEST->buildingPlanApprovalDate) {
        //     $privFiveYear = Carbon::parse($this->_REQUEST->buildingPlanApprovalDate)->addYear()->subYears(5)->format('Y-m-d');
        // }


        //end of changes

        // if ((Config::get("PropertyConstaint.ASSESSMENT-TYPE." . $this->_REQUEST->assessmentType) == 'New Assessment' || $this->_REQUEST->assessmentType == 'New Assessment') && $privFiveYear < $this->_calculationDateFrom) {
        //     $this->_GRID['demandPendingYrs'] = 6;
        //     $this->_calculationDateFrom = $privFiveYear;
        // }
        if ((Config::get("PropertyConstaint.ASSESSMENT-TYPE." . $this->_REQUEST->assessmentType) == 'New Assessment' || $this->_REQUEST->assessmentType == 'New Assessment')  && $this->_calculationDateFrom < $privFiveYear) {
            $this->_GRID['demandPendingYrs'] = 6;
            $this->_calculationDateFrom = $privFiveYear;
        }
        #=======end========
        // Act Of Limitations end
        while ($this->_calculationDateFrom <= $currentFyearEndDate) {
            // $annualTaxes = collect($this->_floorsTaxes)->where('dateFrom', '<=', $this->_calculationDateFrom)->where("appliedUpto",">=",getFY($privFiveYear));
            $annualTaxes = collect($this->_floorsTaxes)->where('appliedFrom', '<=', getFY($this->_calculationDateFrom))->where("appliedUpto", ">=", getFY($privFiveYear))->filter(function ($val) {
                return $val["appliedFrom"] <= $val["appliedUpto"] && $val["appliedUpto"] >= getFY($this->_calculationDateFrom);
            });
            // dd($this->_REQUEST->propertyType,$this->_REQUEST["ward"],$this->_REQUEST->all());
            if ($this->_REQUEST->propertyType == 4 && in_array($this->_REQUEST["ward"], $this->_LESS_PERSENTAGE_APPLY_WARD_IDS) && getFy($this->_calculationDateFrom) <= '2021-2023') {

                $annualTaxes = $annualTaxes->map(function ($vals) {
                    $prcent = Config::get('akola-property-constant.LESS_PERSENTAGE_APPLY_FYEARS.' . getFy($this->_calculationDateFrom));
                    if ($prcent) {
                        $vals["agingAmt"] = $vals["agingAmt"] * $prcent;
                        $vals["taxValue"] = $vals["taxValue"] * $prcent;
                        $vals["generalTax"] = $vals["generalTax"] * $prcent;
                        $vals["roadTax"] = $vals["roadTax"] * $prcent;
                        $vals["firefightingTax"] = $vals["firefightingTax"] * $prcent;
                        $vals["educationTax"] = $vals["educationTax"] * $prcent;


                        $vals["waterTax"] = $vals["waterTax"] * $prcent;
                        $vals["cleanlinessTax"] = $vals["cleanlinessTax"] * $prcent;
                        $vals["sewerageTax"] = $vals["sewerageTax"] * $prcent;
                        $vals["treeTax"] = $vals["treeTax"] * $prcent;
                        $vals["openPloatTax"] = $vals["openPloatTax"] * $prcent;
                        // $vals["stateEducationTax"] = $vals["stateEducationTax"] * $prcent;
                        // $vals["professionalTax"] = $vals["professionalTax"] * $prcent;
                    }
                    return $vals;
                });
            }

            $fyear = getFY($this->_calculationDateFrom);
            if (!$annualTaxes->isEmpty()) {
                $yearTax = $this->sumTaxes($annualTaxes);                       // 4.1
                switch ($yearDiffs) {
                    case 0:
                        $priveTotalTax = $this->_lastDemand ? $this->_lastDemand->total_tax : 0;
                        $diffAmount = (($yearTax["totalTax"] - $priveTotalTax) > 0) ? ($yearTax["totalTax"] - $priveTotalTax) : 0;
                        if ($diffAmount > 0) {
                            $diffPercent = 1; #($diffAmount / ($yearTax["totalTax"] > 0 ? $yearTax["totalTax"] : 1));
                            // $yearTax2 = $yearTax;
                            $yearTax["agingAmt"]    = roundFigure($yearTax["agingAmt"] * $diffPercent);
                            $yearTax["generalTax"]  = roundFigure($yearTax["generalTax"] * $diffPercent);
                            $yearTax["roadTax"]     = roundFigure($yearTax["roadTax"] * $diffPercent);
                            $yearTax["firefightingTax"] = roundFigure($yearTax["firefightingTax"] * $diffPercent);
                            $yearTax["educationTax"] = roundFigure($yearTax["educationTax"] * $diffPercent);
                            $yearTax["waterTax"]    = roundFigure($yearTax["waterTax"] * $diffPercent);
                            $yearTax["cleanlinessTax"] = roundFigure($yearTax["cleanlinessTax"] * $diffPercent);
                            $yearTax["sewerageTax"] = roundFigure($yearTax["sewerageTax"] * $diffPercent);
                            $yearTax["treeTax"]     = roundFigure($yearTax["treeTax"] * $diffPercent);
                            $yearTax["stateEducationTax"]  = roundFigure($yearTax["stateEducationTax"] * $diffPercent);
                            $yearTax["professionalTax"]  = roundFigure($yearTax["professionalTax"] * $diffPercent);
                            $yearTax["openPloatTax"]    = roundFigure($yearTax["openPloatTax"] * $diffPercent);
                            $yearTax["tax1"]        = roundFigure($yearTax["tax1"] * $diffPercent);
                            $yearTax["totalTax"]    = roundFigure($yearTax["totalTax"] * $diffPercent);
                            $yearTax["diffPercent"]    = roundFigure($diffPercent);
                            $yearTax["priveTotalTax"]    = roundFigure($priveTotalTax);
                        }
                        break;
                }
                $yearTax["adjustAmount"] = 0;
                $yearTax["dueAmount"] = $yearTax["totalTax"];
                if ($this->_oldDemand) {
                    $oldDemand = $this->_oldDemand->where("fyear", $fyear);
                    $paidTax = collect($oldDemand)->sum("total_tax") - collect($oldDemand)->sum("balance");
                    $yearTax["adjustAmount"] = roundFigure($paidTax);
                    $yearTax["dueAmount"] = $yearTax["totalTax"] - $yearTax["adjustAmount"];
                    // dd($oldDemand,$fyear,$paidTax);
                }
                $yearTax["totalFloar"] = $annualTaxes->count();
                $fyearWiseTaxes->put($fyear, array_merge($yearTax, ['fyear' => $fyear]));
            }

            $this->_calculationDateFrom = Carbon::parse($this->_calculationDateFrom)->addYear()->format('Y-m-d');
        }
        $this->_GRID['fyearWiseTaxes'] = $fyearWiseTaxes;
        $this->_GRID['demandPendingYrs'] = $fyearWiseTaxes->count();                // Update demand payment years
    }


    // public function generateTaxAccOldPropDemand()
    // {
    //     //changes by prity pandey
    //     $fyear = null;
    //     if ($this->_REQUEST->buildingPlanCompletionDate) {
    //         $fyear = getFY($this->_REQUEST->buildingPlanCompletionDate);
    //     } elseif ($this->_REQUEST->buildingPlanApprovalDate) {
    //         $fyear = getFY($this->_REQUEST->buildingPlanApprovalDate);
    //     }
    //     //end of changes
    //     // if (in_array($this->getAssestmentTypeStr(), ['Reassessment', 'Mutation', 'Amalgamation']) && $fyear) {
    //         if (in_array($this->getAssestmentTypeStr(), ['Reassessment']) && $fyear) {
    //         $propProperties = PropProperty::find($this->_REQUEST->previousHoldingId);
    //         DB::enableQueryLog();
    //         $unPaidDemand = $propProperties->PropDueDemands()->where("fyear", "<", $fyear)->get();
    //         foreach ($unPaidDemand as $val) {
    //             $fyear = $val["fyear"];
    //             $arr = [
    //                 "alv" => roundFigure($val["alv"]),
    //                 "maintancePerc" => "0",
    //                 "maintantance10Perc" => roundFigure($val["due_maintanance_amt"]),
    //                 "valueAfterMaintance" => "0",
    //                 "agingPerc" => "0",
    //                 "agingAmt" => roundFigure($val["due_aging_amt"]),
    //                 "taxValue" => "0",
    //                 "generalTax" => roundFigure($val["due_general_tax"]),
    //                 "roadTax" => roundFigure($val["due_road_tax"]),
    //                 "firefightingTax" => roundFigure($val["due_firefighting_tax"]),
    //                 "educationTax" => roundFigure($val["due_education_tax"]),
    //                 "waterTax" => roundFigure($val["due_water_tax"]),
    //                 "cleanlinessTax" => roundFigure($val["due_cleanliness_tax"]),
    //                 "sewerageTax" => roundFigure($val["due_sewarage_tax"]),
    //                 "treeTax" => roundFigure($val["due_tree_tax"]),
    //                 "stateEducationTaxPerc" => "0",
    //                 "stateEducationTax" => roundFigure($val["due_sp_education_tax"]),
    //                 "professionalTaxPerc" => "0",
    //                 "professionalTax" => roundFigure($val["due_professional_tax"]),
    //                 "openPloatTax" => roundFigure($val["due_open_ploat_tax"]),
    //                 "tax1" => roundFigure($val["due_tax1"]),
    //                 "adjustAmount" => "0",
    //                 "dueAmount" => roundFigure($val["due_total_tax"]),
    //                 "totalTax" => roundFigure($val["due_total_tax"]),
    //                 "totalTax2" => roundFigure($val["due_total_tax"]),
    //                 "totalFloar" => "0",
    //                 "fyear" => $val["fyear"]
    //             ];
    //             $this->_GRID['fyearWiseTaxes'][$fyear] = $arr;
    //         }

    //         $this->_GRID['fyearWiseTaxes'] = collect($this->_GRID['fyearWiseTaxes'])->sortBy("fyear");
    //     }
    // }


    //change by prity pandey
    public function generateTaxAccOldPropDemand()
    {
        //changes by prity pandey
        $fyear = null;
        if ($this->_REQUEST->buildingPlanCompletionDate) {
            $fyear = getFY($this->_REQUEST->buildingPlanCompletionDate);
        } elseif ($this->_REQUEST->buildingPlanApprovalDate) {
            $fyear = getFY($this->_REQUEST->buildingPlanApprovalDate);
        }
        //end of changes
        // if (in_array($this->getAssestmentTypeStr(), ['Reassessment', 'Mutation', 'Amalgamation']) && $fyear) {
        if (in_array($this->getAssestmentTypeStr(), ['Reassessment']) && $fyear) {
            $propProperties = PropProperty::find($this->_REQUEST->previousHoldingId);
            DB::enableQueryLog();
            $unPaidDemand = $propProperties->PropDueDemands()->where("fyear", "<", $fyear)->get();
            foreach ($unPaidDemand as $val) {
                $fyear = $val["fyear"];
                $arr = [
                    "alv" => roundFigure($val["alv"]),
                    "maintancePerc" => "0",
                    "maintantance10Perc" => roundFigure($val["due_maintanance_amt"]),
                    "valueAfterMaintance" => "0",
                    "agingPerc" => "0",
                    "agingAmt" => roundFigure($val["due_aging_amt"]),
                    "taxValue" => "0",
                    "generalTax" => roundFigure($val["due_general_tax"]),
                    "roadTax" => roundFigure($val["due_road_tax"]),
                    "firefightingTax" => roundFigure($val["due_firefighting_tax"]),
                    "educationTax" => roundFigure($val["due_education_tax"]),
                    "waterTax" => roundFigure($val["due_water_tax"]),
                    "cleanlinessTax" => roundFigure($val["due_cleanliness_tax"]),
                    "sewerageTax" => roundFigure($val["due_sewarage_tax"]),
                    "treeTax" => roundFigure($val["due_tree_tax"]),
                    "stateEducationTaxPerc" => "0",
                    "stateEducationTax" => roundFigure($val["due_sp_education_tax"]),
                    "professionalTaxPerc" => "0",
                    "professionalTax" => roundFigure($val["due_professional_tax"]),
                    "openPloatTax" => roundFigure($val["due_open_ploat_tax"]),
                    "tax1" => roundFigure($val["due_tax1"]),
                    "tax2" => roundFigure($val["due_tax2"]),
                    "tax3" => roundFigure($val["due_tax3"]),
                    "waterBenefitTax" => roundFigure($val["due_water_benefit"]),
                    "waterBillTax" => roundFigure($val["due_water_bill"]),
                    "spWaterCessTax" => roundFigure($val["due_sp_water_cess"]),
                    "drainCessTax" => roundFigure($val["due_drain_cess"]),
                    "lightCessTax" => roundFigure($val["due_light_cess"]),
                    "majorBuildingTax" => roundFigure($val["due_major_building"]),
                    "adjustAmount" => "0",
                    "dueAmount" => roundFigure($val["due_total_tax"]),
                    "totalTax" => roundFigure($val["due_total_tax"]),
                    "totalTax2" => roundFigure($val["due_total_tax"]),
                    "totalFloar" => "0",
                    "fyear" => $val["fyear"]
                ];
                $this->_GRID['fyearWiseTaxes'][$fyear] = $arr;
            }

            $this->_GRID['fyearWiseTaxes'] = collect($this->_GRID['fyearWiseTaxes'])->sortBy("fyear");
        }
    }

    //prity pandey bifurcation tax calculation 16-09-24
    public function generateBifurcationTaxAccOldPropDemand()
    {
        $propProperties = PropProperty::find($this->_REQUEST->previousHoldingId);
        if (in_array($this->getAssestmentTypeStr(), ['Bifurcation']) && $propProperties) {
            $totalArea = $propProperties->area_of_plot;
            $bifurcatedArea = $this->_REQUEST->bifurcatedPlot;
            if ($this->_REQUEST->propertyType != 4) {
                $propFloor = PropFloor::where("property_id", $propProperties->id)
                    ->where('status', 1)
                    ->orderby('id')
                    ->get();
                $totalArea = collect($propFloor)->sum("builtup_area");
                $bifurcatedArea = collect($this->_REQUEST->floor)->sum("biBuildupArea");
            }
            $onePercOfArea = $totalArea / 100;
            $percOfBifurcatedArea = round(($bifurcatedArea / $onePercOfArea), 2);
            $unPaidDemand = $propProperties->PropDueDemands()->where("fyear", "<", getFY())->get();
            $fyearWiseTaxes = collect();
            foreach ($unPaidDemand as $val) {
                $fyear = $val["fyear"];
                $arr = [
                    "percOfBifurcatedArea" => $percOfBifurcatedArea,
                    "alv" => roundFigure(($val["alv"] / 100) * $percOfBifurcatedArea),
                    "maintancePerc" => "0",
                    "maintantance10Perc" => roundFigure(($val["due_maintanance_amt"] / 100) * $percOfBifurcatedArea),
                    "valueAfterMaintance" => "0",
                    "agingPerc" => "0",
                    "agingAmt" => roundFigure(($val["due_aging_amt"] / 100) * $percOfBifurcatedArea),
                    "taxValue" => "0",
                    "generalTax" => roundFigure(($val["due_general_tax"] / 100) * $percOfBifurcatedArea),
                    "roadTax" => roundFigure(($val["due_road_tax"] / 100) * $percOfBifurcatedArea),
                    "firefightingTax" => roundFigure(($val["due_firefighting_tax"] / 100) * $percOfBifurcatedArea),
                    "educationTax" => roundFigure(($val["due_education_tax"] / 100) * $percOfBifurcatedArea),
                    "waterTax" => roundFigure(($val["due_water_tax"] / 100) * $percOfBifurcatedArea),
                    "cleanlinessTax" => roundFigure(($val["due_cleanliness_tax"] / 100) * $percOfBifurcatedArea),
                    "sewerageTax" => roundFigure(($val["due_sewarage_tax"] / 100) * $percOfBifurcatedArea),
                    "treeTax" => roundFigure(($val["due_tree_tax"] / 100) * $percOfBifurcatedArea),
                    "stateEducationTaxPerc" => "0",
                    "stateEducationTax" => roundFigure(($val["due_sp_education_tax"] / 100) * $percOfBifurcatedArea),
                    "professionalTaxPerc" => "0",
                    "professionalTax" => roundFigure(($val["due_professional_tax"] / 100) * $percOfBifurcatedArea),
                    "openPloatTax" => roundFigure(($val["due_open_ploat_tax"] / 100) * $percOfBifurcatedArea),
                    "tax1" => roundFigure(($val["due_tax1"] / 100) * $percOfBifurcatedArea),
                    "tax2" => roundFigure(($val["due_tax2"] / 100) * $percOfBifurcatedArea),
                    "tax3" => roundFigure(($val["due_tax3"] / 100) * $percOfBifurcatedArea),
                    "waterBenefitTax" => roundFigure(($val["due_water_benefit"] / 100) * $percOfBifurcatedArea),
                    "waterBillTax" => roundFigure(($val["due_water_bill"] / 100) * $percOfBifurcatedArea),
                    "spWaterCessTax" => roundFigure(($val["due_sp_water_cess"] / 100) * $percOfBifurcatedArea),
                    "drainCessTax" => roundFigure(($val["due_drain_cess"] / 100) * $percOfBifurcatedArea),
                    "lightCessTax" => roundFigure(($val["due_light_cess"] / 100) * $percOfBifurcatedArea),
                    "majorBuildingTax" => roundFigure(($val["due_major_building"] / 100) * $percOfBifurcatedArea),
                    "adjustAmount" => "0",
                    "dueAmount" => roundFigure(($val["due_total_tax"] / 100) * $percOfBifurcatedArea),
                    "totalTax" => roundFigure(($val["due_total_tax"] / 100) * $percOfBifurcatedArea),
                    "totalTax2" => roundFigure(($val["due_total_tax"] / 100) * $percOfBifurcatedArea),
                    "totalFloar" => "0",
                    "fyear" => $val["fyear"]
                ];
                $this->_GRID['fyearWiseTaxes'][$fyear] = $arr;
            }
            $this->_GRID['fyearWiseTaxes'] = collect($this->_GRID['fyearWiseTaxes'])->sortBy("fyear");
        }
    }

    /**
     * | Generate Payable Amount (5)
     */
    public function generatePayableAmount()
    {
        $this->_GRID['grandTaxes'] = $this->sumTaxes($this->_GRID['fyearWiseTaxes']);               // 5.1

        $this->_GRID['isRebateApplied'] = false;
        $this->_GRID['rebateAmt'] = 0;
        // Read Rebates
        $firstOwner = collect($this->_REQUEST->owner)->first();
        if (isset($firstOwner))                     // If first Owner is found
            $isArmedForce = $firstOwner['isArmedForce'];
        else
            $isArmedForce = false;

        if ($isArmedForce) {
            $currentYearTax = $this->_GRID['fyearWiseTaxes']->where('fyear', $this->_currentFyear)->first();       // General Tax of current fyear will be our rebate

            if (collect($currentYearTax)->isEmpty() && $this->_REQUEST->assessmentType == 1)
                throw new Exception("Current Year Taxes Not Available");
            if (collect($currentYearTax)->isEmpty() && $this->_REQUEST->assessmentType != 1) {
                $currentYearTax['generalTax'] = [];
            }

            $cyGeneralTax = $currentYearTax['generalTax'];
            $this->_GRID['isRebateApplied'] = true;
            $this->_GRID['rebateAmt'] = $cyGeneralTax ? $cyGeneralTax : 0;
        }

        // Calculation of Payable Amount
        $this->_GRID['payableAmt'] = round($this->_GRID['grandTaxes']['dueAmount'] - ($this->_GRID['rebateAmt'] ?? 0));
    }

    public function getAssestmentTypeStr()
    {
        $assessmentType = $this->_REQUEST->assessmentType ?? "";
        if (is_numeric($this->_REQUEST->assessmentType)) {
            $assessmentType = Config::get("PropertyConstaint.ASSESSMENT-TYPE." . $this->_REQUEST->assessmentType);
        }
        return $assessmentType;
    }


    /**
     * | Managal Tower
     */
    public function mangalTower($item)
    {
        // if ($this->_REQUEST->propertyType == 4) {
        $agingPerc = 0;                         // No Aging Percent for Vacant Land
        // if ($this->_REQUEST->category == 1)
        //     $rate = 11*5;
        // elseif ($this->_REQUEST->category == 2)
        //     $rate = 10;
        // else
        //     $rate = 8;

        $rate = $this->readRateByFloor($item);                 // (2.1)
        $rate = $rate * 5;

        $alv = roundFigure($item->buildupArea * 0.092903  * $rate);
        $maintance10Perc = 0; #roundFigure(($alv * $this->_maintancePerc) / 100);
        $valueAfterMaintanance = roundFigure($alv - ($alv * 0.1)); # 10% minuse on ALV
        $aging = 0; #roundFigure(($valueAfterMaintanance * $agingPerc) / 100);
        $taxValue = roundFigure($valueAfterMaintanance - $aging);

        // Municipal Taxes
        $generalTax = 0; #roundFigure($taxValue * 0.30);
        $roadTax = 0; #roundFigure($taxValue * 0.03);
        $firefightingTax = 0; #roundFigure($taxValue * 0.02);
        $educationTax = 0; #roundFigure($taxValue * 0.02);
        $waterTax = 0; #roundFigure($taxValue * 0.02);
        $cleanlinessTax = 0; #roundFigure($taxValue * 0.02);
        $sewerageTax = 0; #roundFigure($taxValue * 0.02);
        $treeTax = roundFigure($taxValue * 0.01);
        $openPloatTax = roundFigure($taxValue * 0.43);

        $isCommercial = true;

        $stateTaxes = $this->readStateTaxes($this->_calculatorParams['areaOfPlot'], $isCommercial, $alv);                   // Read State Taxes(3.1)

        $tax1 = 0;
        $diffArrea = 0;
        $doubleTax1 = ($generalTax + $roadTax + $firefightingTax + $educationTax
            + $waterTax + $cleanlinessTax + $sewerageTax
            + $treeTax + $stateTaxes['educationTax'] + $stateTaxes['professionalTax']
            + ($openPloatTax ?? 0));
        if ($this->_REQUEST->nakshaAreaOfPlot) {
            $diffArrea = ($this->_REQUEST->areaOfPlot - $this->_REQUEST->nakshaAreaOfPlot) > 0 ? $this->_REQUEST->areaOfPlot -  $this->_REQUEST->nakshaAreaOfPlot : 0;
        }
        # double tax apply
        if ($this->_REQUEST->isAllowDoubleTax) {
            //$tax1 = $doubleTax1;
            $tax1 = 0;
        }
        # 100% penalty apply on diff arrea
        elseif ($diffArrea > 0) {
            // $tax1 = $doubleTax1 * ($diffArrea / ($this->_REQUEST->areaOfPlot > 0 ? $this->_REQUEST->areaOfPlot : 1));
            $tax1 = 0;
        }
        //commented by prity pandey
        //$this->_floorsTaxes[0] = [

        //end of change
        // 'dateFrom' => $this->_REQUEST->approvedDate ? Carbon::parse($this->_REQUEST->approvedDate)->addYears(-5)->format('Y-m-d') : Carbon::now()->addYears(-5)->format('Y-m-d'),
        // 'dateUpto' => null,
        // 'appliedFrom' => getFY($this->_REQUEST->approvedDate ? Carbon::parse($this->_REQUEST->approvedDate)->addYears(-5)->format('Y-m-d') : Carbon::now()->addYears(-5)->format('Y-m-d')),
        // 'appliedUpto' => getFY(),
        return [
            'dateFrom' => $item->dateFrom,
            'dateUpto' => $item->dateUpto,
            'appliedFrom' => getFY($item->dateFrom),
            'appliedUpto' => getFY($item->dateUpto),
            'rate' => $rate,
            'floorKey' => "Vacant Land",
            'floorNo' => "Vacant Land",
            'alv' => $alv,
            'maintancePerc' => $this->_maintancePerc,
            'maintantance10Perc' => $maintance10Perc,
            'valueAfterMaintance' => $valueAfterMaintanance,
            'agingPerc' => $agingPerc,
            'agingAmt' => $aging,
            'taxValue' => $taxValue,
            'generalTax' => $generalTax,
            'roadTax' => $roadTax,
            'firefightingTax' => $firefightingTax,
            'educationTax' => $educationTax,
            'waterTax' => $waterTax,
            'cleanlinessTax' => $cleanlinessTax,
            'sewerageTax' => $sewerageTax,
            'treeTax' => $treeTax,
            "tax1" => $tax1,
            "openPloatTax" => $openPloatTax,
            'isCommercial' => $isCommercial,
            'stateEducationTaxPerc' => $stateTaxes['educationTaxPerc'],
            'stateEducationTax' => $stateTaxes['educationTax'],
            'professionalTaxPerc' => $stateTaxes['professionalTaxPerc'],
            'professionalTax' => $stateTaxes['professionalTax'],
        ];
        // }

        // $this->_GRID['floorsTaxes'] = $this->_floorsTaxes;
    }
}
