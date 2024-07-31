<?php

namespace App\Http\Controllers\Water;

use App\BLL\Water\WaterConsumerDemandReceipt;
use App\BLL\Water\WaterMonthelyCall;
use App\Exports\DataExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Water\reqDeactivate;
use App\Http\Requests\Water\reqMeterEntry;
use App\Http\Requests\Water\newWaterRequest;
use App\MicroServices\DocumentUpload;
use App\MicroServices\DocUpload;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Masters\RefRequiredDocument;
use App\MicroServices\IdGeneration;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Payment\TempTransaction;
use App\Models\Property\ZoneMaster;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Models\User;
use App\Models\Water\Log\WaterConsumerOwnerUpdatingLog;
use App\Models\Water\Log\WaterConsumersUpdatingLog;
use App\Models\Water\WaterAdjustment;
use App\Models\Water\WaterAdvance;
use App\Models\Water\WaterApplication;
use App\Models\Water\WaterApprovalApplicationDetail;
use App\Models\Water\WaterChequeDtl;
use App\Models\Water\WaterConnectionCharge;
use App\Models\Water\WaterConnectionTypeMstr;
use App\Models\Water\WaterConsumer as WaterWaterConsumer;
use App\Models\Water\WaterConsumerActiveRequest;
use App\Models\Water\WaterConsumerOwner;
use App\Models\Water\WaterConsumerCharge;
use App\Models\Water\WaterConsumerChargeCategory;
use App\Models\Water\WaterConsumerComplain;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterConsumerDemandRecord;
use App\Models\Water\WaterConsumerDisconnection;
use App\Models\Water\WaterConsumerInitialMeter;
use App\Models\Water\WaterConsumerMeter;
use App\Models\Water\WaterConsumerTax;
use App\Models\Water\WaterDisconnection;
use App\Models\Water\WaterMeterReadingDoc;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterSecondConsumer;
use App\Models\Water\WaterSiteInspection;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterSecondConnectionCharge;
use App\Models\Water\WaterTranDetail;
use App\Models\Workflows\WfRoleusermap;
use App\Models\Workflows\WfWorkflow;
use App\Models\Workflows\WfWorkflowrolemap;
use App\Models\WorkflowTrack;
use App\Repository\Water\Concrete\WaterNewConnection;
use App\Repository\Water\Interfaces\IConsumer;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Excel as ExcelExcel;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\CssSelector\Node\FunctionNode;

class WaterConsumer extends Controller
{
    use Workflow;

    private $Repository;
    private $_docUrl;
    protected $_DB_NAME;
    protected $_DB;

    public function __construct(IConsumer $Repository)
    {
        $this->Repository = $Repository;
        $this->_DB_NAME = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_docUrl = Config::get("waterConstaint.DOC_URL");
    }
    /**
     * | Database transaction
     */
    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        DB::beginTransaction();
        if ($db1 != $db2)
            $this->_DB->beginTransaction();
    }
    /**
     * | Database transaction
     */
    public function rollback()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
    }
    /**
     * | Database transaction
     */
    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
    }


    /**
     * | Calcullate the Consumer demand 
     * | @param request
     * | @return Repository
        | Serial No : 01
        | Working
     */
    public function calConsumerDemand(Request $request)
    {
        return $this->Repository->calConsumerDemand($request);
    }


    /**
     * | List Consumer Active Demand
     * | Show the Demand With payed-status false
     * | @param request consumerId
     * | @var WaterConsumerDemand  model
     * | @var consumerDemand  
     * | @var refConsumerId
     * | @var refMeterData
     * | @var connectionName
     * | @return consumerDemand  Consumer Demand List
        | Serial no : 02
        | Working
     */
    public function listConsumerDemand(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'ConsumerId' => 'required|',
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWaterConsumerDemand   = new WaterConsumerDemand();
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            $mWaterSecondConsumer   = new WaterSecondConsumer();
            $mWaterAdvance          = new WaterAdvance();
            $mWaterAdjustment       = new WaterAdjustment();
            $mNowDate               = carbon::now()->format('Y-m-d');
            $refConnectionName      = Config::get('waterConstaint.METER_CONN_TYPE');
            $refConsumerId          = $request->ConsumerId;

            # Basic consumer details
            $WaterBasicDetails = $mWaterSecondConsumer->fullWaterDetails($refConsumerId)->first();
            if (!$WaterBasicDetails) {
                throw new Exception("Consumer detail not found ");
            }

            # Get demand details 
            $refConsumerDemand = $mWaterConsumerDemand->getConsumerDemandV3($refConsumerId);
            if (!($refConsumerDemand->first())) {
                $consumerDemand['demandStatus'] = 0;                                    // Static / to represent existence of demand
                return responseMsgs(false, "Consumer demands not found!", $consumerDemand, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
            }

            $totalAdvanceAmt = $mWaterAdvance->getAdvanceAmt($refConsumerId);
            $totalAdjustmentAmt = $mWaterAdjustment->getAdjustmentAmt($refConsumerId);
            $remainAdvance = $totalAdvanceAmt->sum("amount") - $totalAdjustmentAmt->sum("amount");

            $refConsumerDemand = collect($refConsumerDemand)->sortBy('demand_upto')->values();
            $consumerDemand['consumerDemands'] = $refConsumerDemand;
            $checkParam = collect($consumerDemand['consumerDemands'])->first();

            # Check the details 
            if (isset($checkParam)) {
                $sumDemandAmount = collect($consumerDemand['consumerDemands'])->sum('due_balance_amount');
                $totalPenalty = collect($consumerDemand['consumerDemands'])->sum('due_penalty');
                $consumerDemand['fromDate'] = Carbon::parse(collect($consumerDemand['consumerDemands'])->min("demand_from"))->format("d-m-Y");
                $consumerDemand['uptoDate'] = Carbon::parse(collect($consumerDemand['consumerDemands'])->max("demand_upto"))->format("d-m-Y");
                $consumerDemand['totalSumDemand'] = round($sumDemandAmount, 2);
                $consumerDemand['totalPenalty'] = round($totalPenalty, 2);
                $consumerDemand['remainAdvance'] = round($remainAdvance ?? 0);
                if ($consumerDemand['totalSumDemand'] == 0)
                    unset($consumerDemand['consumerDemands']);

                # Meter Details 
                $refMeterData = $mWaterConsumerMeter->getMeterDetailsByConsumerIdV2($refConsumerId)->first();
                $refMeterData->ref_initial_reading = (float)($refMeterData->ref_initial_reading);
                switch ($refMeterData['connection_type']) {
                    case (1):
                        $connectionName = $refConnectionName['1'];
                        break;
                    case (3):
                        $connectionName = $refConnectionName['3'];
                        break;
                }
                if ($checkParam['demand_from'] == null && $checkParam['paid_status'] == 0 && $checkParam['demand_upto'] == null) {
                    // return('last demand is not available');
                    $checkParam['demand_from'] = $checkParam['generation_date'];
                    $checkParam['demand_upto'] = $mNowDate;
                }

                $refMeterData['connectionName']     = $connectionName;
                $refMeterData['ConnectionTypeName'] = $connectionName;
                $refMeterData['basicDetails']       = $WaterBasicDetails;
                $consumerDemand['meterDetails']     = $refMeterData;
                $consumerDemand['demandStatus']     = 1;                                // Static / to represent existence of demand
                return responseMsgs(true, "List of Consumer Demand!", ($request->original ? $consumerDemand : remove_null($consumerDemand)), "", "01", "ms", "POST", "");
            }
            throw new Exception("There is no demand!");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "01", "ms", "POST", "");
        }
    }


    /**
     * | Save the consumer demand 
     * | Also generate demand 
     * | @param request
     * | @var mWaterConsumerInitialMeter
     * | @var mWaterConsumerMeter
     * | @var refMeterConnectionType
     * | @var consumerDetails
     * | @var calculatedDemand
     * | @var demandDetails
     * | @var meterId
     * | @return 
        | Serial No : 03
        | Not Tested
        | Work on the valuidation and the saving of the meter details document
     */
    public function saveGenerateConsumerDemand(Request $request)
    {
        $mNowDate = carbon::now()->format('Y-m-d');
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'       => "required|digits_between:1,9223372036854775807",
                "demandUpto"       => "nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate",
                'finalRading'      => "nullable|numeric",
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        // return $request->all();

        try {
            $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
            $mWaterConsumerMeter        = new WaterConsumerMeter();
            $mWaterMeterReadingDoc      = new WaterMeterReadingDoc();
            $mWaterSecondConsumer       = new WaterSecondConsumer();
            $refMeterConnectionType     = Config::get('waterConstaint.METER_CONN_TYPE');
            $meterRefImageName          = config::get('waterConstaint.WATER_METER_CODE');
            $demandIds = array();

            # Check and calculate Demand                  
            $consumerDetails = $mWaterSecondConsumer->getConsumerDetails($request->consumerId)->first();
            if (!$consumerDetails) {
                throw new Exception("Consumer detail not found!");
            }
            // $this->checkDemandGeneration($request, $consumerDetails);                                       // unfinished function

            # Calling BLL for call
            $returnData = new WaterMonthelyCall($request->consumerId, $request->demandUpto, $request->finalRading); #WaterSecondConsumer::get();
            if (!$request->isNotstrickChek) {
                $returnData->checkDemandGenerationCondition();
            }
            $calculatedDemand = $returnData->parentFunction($request);
            if ($calculatedDemand['status'] == false) {
                throw new Exception($calculatedDemand['errors']);
            }
            # Save demand details 
            $this->begin();
            $userDetails = $this->checkUserType($request);
            if (isset($calculatedDemand)) {
                $demandDetails = collect($calculatedDemand['consumer_tax']['0']);
                switch ($demandDetails['charge_type']) {
                        # For Meter Connection
                    case ($refMeterConnectionType['1']):
                        $validated = Validator::make(
                            $request->all(),
                            [
                                'document' => "required|mimes:pdf,jpeg,png,jpg",
                            ]
                        );
                        if ($validated->fails())
                            return validationError($validated);
                        $meterDetails = $mWaterConsumerMeter->saveMeterReading($request);
                        $mWaterConsumerInitialMeter->saveConsumerReading($request, $meterDetails, $userDetails);
                        $demandIds = $this->savingDemand($calculatedDemand, $request, $consumerDetails, $demandDetails['charge_type'], $refMeterConnectionType, $userDetails);
                        # save the chages doc
                        $documentPath = $this->saveDocument($request, $meterRefImageName);
                        collect($demandIds)->map(function ($value)
                        use ($mWaterMeterReadingDoc, $meterDetails, $documentPath) {
                            $mWaterMeterReadingDoc->saveDemandDocs($meterDetails, $documentPath, $value);
                        });
                        break;

                        # For Fixed connection
                    case ($refMeterConnectionType['3']):
                        $this->savingDemand($calculatedDemand, $request, $consumerDetails, $demandDetails['charge_type'], $refMeterConnectionType, $userDetails);
                        break;
                }
                // $sms = AkolaProperty(["owner_name" => $request['arshad'], "saf_no" => $request['tranNo']], "New Assessment");
                // if (($sms["status"] !== false)) {
                //     $respons = SMSAKGOVT(6206998554, $sms["sms"], $sms["temp_id"]);
                // }
                $this->commit();
                $respons = $documentPath ?? [];
                $respons["consumerId"]  =   $request->consumerId;
                return responseMsgs(true, "Demand Generated! for" . " " . $request->consumerId, $respons, "", "02", ".ms", "POST", "");
            }
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), [], "", "01", "ms", "POST", "");
        }
    }

    /**
     * | Save the Details for the Connection Type Meter 
     * | In Case Of Connection Type is meter OR Gallon 
     * | @param Request  
     * | @var mWaterConsumerDemand
     * | @var mWaterConsumerTax
     * | @var generatedDemand
     * | @var taxId
     * | @var meterDetails
     * | @var refDemands
        | Serial No : 03.01
        | Not Tested
     */
    public function savingDemand($calculatedDemand, $request, $consumerDetails, $demandType, $refMeterConnectionType, $userDetails)
    {
        $mWaterConsumerTax      = new WaterConsumerTax();
        $mWaterConsumerDemand   = new WaterConsumerDemand();
        $generatedDemand        = $calculatedDemand['consumer_tax'];

        $returnDemandIds = collect($generatedDemand)->map(function ($firstValue)
        use ($mWaterConsumerDemand, $consumerDetails, $request, $mWaterConsumerTax, $demandType, $refMeterConnectionType, $userDetails) {
            $taxId = $mWaterConsumerTax->saveConsumerTax($firstValue, $consumerDetails, $userDetails);
            // $refDemandIds = array();
            # User for meter details entry
            $meterDetails = [
                "charge_type"       => $firstValue['charge_type'],
                "amount"            => $firstValue['charge_type'],
                "effective_from"    => $firstValue['effective_from'],
                "initial_reading"   => $firstValue['initial_reading'],
                "final_reading"     => $firstValue['final_reading'],
                "rate_id"           => $firstValue['rate_id'],
            ];
            switch ($demandType) {
                case ($refMeterConnectionType['1']):
                    $refDemands = $firstValue['consumer_demand'];
                    $check = collect($refDemands)->first();
                    if (is_array($check)) {
                        $refDemandIds = collect($refDemands)->map(function ($secondValue)
                        use ($mWaterConsumerDemand, $consumerDetails, $request, $taxId, $userDetails) {
                            $refDemandId = $mWaterConsumerDemand->saveConsumerDemand($secondValue, $consumerDetails, $request, $taxId, $userDetails);
                            return $refDemandId;
                        });
                        break;
                    }
                    $refDemandIds = $mWaterConsumerDemand->saveConsumerDemand($refDemands, $consumerDetails, $request, $taxId, $userDetails);
                    break;
                case ($refMeterConnectionType['3']):
                    $refDemands = $firstValue['consumer_demand'];
                    $check = collect($refDemands)->first();
                    if (is_array($check)) {
                        $refDemandIds = collect($refDemands)->map(function ($secondValue)
                        use ($mWaterConsumerDemand, $consumerDetails, $request, $taxId, $userDetails) {
                            $refDemandId = $mWaterConsumerDemand->saveConsumerDemand($secondValue,  $consumerDetails, $request, $taxId, $userDetails);
                            return $refDemandId;
                        });
                        break;
                    }
                    $refDemandIds = $mWaterConsumerDemand->saveConsumerDemand($refDemands, $consumerDetails, $request, $taxId, $userDetails);
                    break;
            }
            return $refDemandIds;
        });
        return $returnDemandIds->first();
    }

    /**
     * | Validate the user and other criteria for the Genereating demand
     * | @param request
        | Serial No : 03.02
        | Not Used 
     */
    public function checkDemandGeneration($request, $consumerDetails)
    {
        $today                  = Carbon::now();
        $refConsumerId          = $request->consumerId;
        $mWaterConsumerDemand   = new WaterConsumerDemand();
        $mWaterSecondConsumer   = new WaterSecondConsumer();
        $mWaterConsumerMeter    = new WaterConsumerMeter();

        $lastDemand = $mWaterConsumerDemand->akolaCheckConsumerDemand($refConsumerId)->first();
        if ($lastDemand) {
            // here we check the demand from date is present then do this or that
            if (!$lastDemand->demand_upto) {
                $startDate  = Carbon::parse($lastDemand->generation_date);
                $refDemandUpto =  $lastDemand->generation_date ? $startDate : $lastDemand->generation_date;
            } else {
                $refDemandUpto = $lastDemand->demand_upto ? Carbon::parse($lastDemand->demand_upto) : $lastDemand->demand_upto;
            }

            if ($refDemandUpto && $refDemandUpto > $today) {
                throw new Exception("The demand is generated till" . "" . $lastDemand->demand_upto);
            }

            $startDate  = Carbon::parse($refDemandUpto);
            $uptoMonth  = $startDate;
            $todayMonth = $today;
            if ($refDemandUpto && $uptoMonth->greaterThan($todayMonth)) {
                throw new Exception("Demand should be generated in the next month!");
            }
            $diffMonth = $startDate->diffInMonths($today);
            if ($refDemandUpto && $diffMonth < 3) {
                throw new Exception("There should be a difference of 3 month!");
            }
        }
        # write the code to check the first meter reading exist and the other 
    }



    /**
     * | Save the Meter details 
     * | @param request
        | Serial No : 04
        | Working  
        | Check the parameter for the autherised person
        | Chack the Demand for the fixed rate 
        | Re discuss
     */
    public function saveUpdateMeterDetails(reqMeterEntry $request)
    {
        try {
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            // $mWaterConsumerInitial  = new WaterConsumerInitialMeter();
            $meterRefImageName      = config::get('waterConstaint.WATER_METER_CODE');
            $param                  = $this->checkParamForMeterEntry($request);

            $this->begin();
            $metaRequest = new Request([
                "consumerId"    => $request->consumerId,
                "finalRading"   => $request->oldMeterFinalReading,
                "demandUpto"    => $request->connectionDate,
                "document"      => $request->document,
            ]);
            if ($param['meterStatus'] != false) {
                $this->saveGenerateConsumerDemand($metaRequest);
            }
            $documentPath = $this->saveDocument($request, $meterRefImageName);
            $mWaterConsumerMeter->saveMeterDetails($request, $documentPath, $fixedRate = null);
            // $userDetails =[
            //     'emp_id' =>$mWaterConsumerMeter->emp_details_id
            // ];
            // $mWaterConsumerInitial->saveConsumerReading($request,$metaRequest,$userDetails);             # when initial meter data save  
            $this->commit();
            return responseMsgs(true, "Meter Detail Entry Success !", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Chech the parameter before Meter entry
     * | Validate the Admin For entring the meter details
     * | @param request
        | Serial No : 04.01
        | Working
        | Look for the meter status true condition while returning data
        | Recheck the process for meter and non meter 
        | validation for the respective meter conversion and verify the new consumer.
     */
    public function checkParamForMeterEntry($request)
    {
        $refConsumerId  = $request->consumerId;
        $todayDate      = Carbon::now();

        $mWaterWaterSecondConsumer    = new waterSecondConsumer();
        $mWaterConsumerMeter    = new WaterConsumerMeter();
        $mWaterConsumerDemand   = new WaterConsumerDemand();
        $refMeterConnType       = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');

        $refConsumerDetails     = $mWaterWaterSecondConsumer->getConsumerDetailById($refConsumerId);
        if (!$refConsumerDetails) {
            throw new Exception("Consumer Details Not Found!");
        }
        $consumerMeterDetails   = $mWaterConsumerMeter->getMeterDetailsByConsumerId($refConsumerId)->first();
        $consumerDemand         = $mWaterConsumerDemand->getFirstConsumerDemand($refConsumerId)->first();

        # Check the meter/fixed case 
        $this->checkForMeterFixedCase($request, $consumerMeterDetails, $refMeterConnType);

        switch ($request) {
            case (strtotime($request->connectionDate) > strtotime($todayDate)):
                throw new Exception("Connection Date can not be greater than Current Date!");
                break;
            case ($request->connectionType != $refMeterConnType['Meter/Fixed']):
                if (!is_null($consumerMeterDetails)) {
                    if ($consumerMeterDetails->final_meter_reading >= $request->oldMeterFinalReading) {
                        throw new Exception("Reading Should be Greater Than last Reading!");
                    }
                }
                break;
            case ($request->connectionType != $refMeterConnType['Meter']):
                if (!is_null($consumerMeterDetails)) {
                    if ($consumerMeterDetails->connection_type == $request->connectionType) {
                        throw new Exception("You can not update same connection type as before!");
                    }
                }
                break;
        }

        # If Previous meter details exist
        if ($consumerMeterDetails) {
            # If fixed meter connection is changing to meter connection as per rule every connection should be in meter
            if ($request->connectionType != $refMeterConnType['Fixed'] && $consumerMeterDetails->connection_type == $refMeterConnType['Fixed']) {
                if ($consumerDemand) {
                    throw new Exception("Please pay the old demand Amount! as per rule to change fixed connection to meter!");
                }
                throw new Exception("Please apply for regularization as per rule 16 your connection should be in meter!");
            }
            # If there is previous meter detail exist
            $reqConnectionDate = $request->connectionDate;
            if (strtotime($consumerMeterDetails->connection_date) > strtotime($reqConnectionDate)) {
                throw new Exception("Connection date should be greater than previous connection date!");
            }
            # Check the Conversion of the Connection
            $this->checkConnectionTypeUpdate($request, $consumerMeterDetails, $refMeterConnType);
        }

        # If the consumer demand exist
        if (isset($consumerDemand)) {
            $reqConnectionDate = $request->connectionDate;
            $reqConnectionDate = Carbon::parse($reqConnectionDate)->format('m');
            $consumerDmandDate = Carbon::parse($consumerDemand->demand_upto)->format('m');
            switch ($consumerDemand) {
                case ($consumerDmandDate >= $reqConnectionDate):
                    throw new Exception("Cannot update connection Date, Demand already generated upto that month!");
                    break;
            }
        }
        # If the meter detail do not exist 
        if (is_null($consumerMeterDetails)) {
            if (!in_array($request->connectionType, [$refMeterConnType['Meter'], $refMeterConnType['Gallon']])) {
                throw new Exception("New meter connection should be in meter and gallon!");
            }
            $returnData['meterStatus'] = false;
        }
        return $returnData;
    }


    /**
     * | Check the meter connection type in the case of meter updation 
     * | If the meter details exist check the connection type 
        | Serial No :
        | Under Con
     */
    public function checkConnectionTypeUpdate($request, $consumerMeterDetails, $refMeterConnType)
    {
        $currentConnectionType      = $consumerMeterDetails->connection_type;
        $requestedConnectionType    = $request->connectionType;

        switch ($currentConnectionType) {
                # For Fixed Connection
            case ($refMeterConnType['Fixed']):
                if ($requestedConnectionType != $refMeterConnType['Meter'] || $requestedConnectionType != $refMeterConnType['Gallon']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # For Fixed Meter Connection
            case ($refMeterConnType['Meter']):
                if ($requestedConnectionType != $refMeterConnType['Meter'] || $requestedConnectionType != $refMeterConnType['Gallon'] || $requestedConnectionType != $refMeterConnType['Meter/Fixed']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # For Fixed Gallon Connection
            case ($refMeterConnType['Gallon']):
                if ($requestedConnectionType != $refMeterConnType['Meter']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # For Fixed Meter/Fixed Connection
            case ($refMeterConnType['Meter/Fixed']):
                if ($requestedConnectionType != $refMeterConnType['Meter']) {
                    throw new Exception("Invalid connection type update for Fixed!");
                }
                break;
                # Default
            default:
                throw new Exception("Invalid Meter Connection!");
                break;
        }
    }



    /**
     * | Check for the Meter/Fixed 
     * | @param request
     * | @param consumerMeterDetails
        | Serial No : 04.01.01
        | Not Working
     */
    public function checkForMeterFixedCase($request, $consumerMeterDetails, $refMeterConnType)
    {
        if ($request->connectionType == $refMeterConnType['Meter/Fixed']) {
            $refConnectionType = 1;
            if ($consumerMeterDetails->connection_type == $refConnectionType && $consumerMeterDetails->meter_status == 0) {
                throw new Exception("You can not update same connection type as before!");
            }
            if ($request->meterNo != $consumerMeterDetails->meter_no) {
                throw new Exception("You Can Meter/Fixed The Connection On Previous Meter");
            }
        }
    }

    /**
     * | Save the Document for the Meter Entry 
     * | Return the Document Path
     * | @param request
        | Serial No : 04.02 / 06.02
        | Working
        | Common function
     */
    public function saveDocument($request, $refImageName, $folder = null)
    {
        $document       = $request->document;
        $docUpload      = new DocUpload;
        $relativePath   = trim(Config::get('waterConstaint.WATER_RELATIVE_PATH') . "/" . $folder, "/");

        $imageName = $docUpload->upload($refImageName, $document, $relativePath);
        $doc = [
            "document"      => $imageName,
            "relaivePath"   => $relativePath
        ];
        return $doc;
    }


    /**
     * | Get all the meter details According to the consumer Id
     * | @param request
     * | @var 
     * | @return 
        | Serial No : 05
        | Not Working
     */
    public function getMeterList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => "required|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $meterConnectionType    = null;
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            $refWaterNewConnection  = new WaterNewConnection();
            $refMeterConnType       = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');

            $meterList = $mWaterConsumerMeter->getMeterDetailsByConsumerId($request->consumerId)->get();
            $returnData = collect($meterList)->map(function ($value)
            use ($refMeterConnType, $meterConnectionType, $refWaterNewConnection) {
                switch ($value['connection_type']) {
                    case ($refMeterConnType['Meter']):
                        if ($value['meter_status'] == 0) {
                            $meterConnectionType = "Metre/Fixed";                               // Static
                        }
                        $meterConnectionType = "Meter";                                         // Static
                        break;

                    case ($refMeterConnType['Gallon']):
                        $meterConnectionType = "Gallon";                                        // Static
                        break;
                    case ($refMeterConnType['Fixed']):
                        $meterConnectionType = "Fixed";                                         // Static
                        break;
                }
                $value['meter_connection_type'] = $meterConnectionType;
                $path = $refWaterNewConnection->readDocumentPath($value['doc_path']);
                $value['doc_path'] = !empty(trim($value['doc_path'])) ? $path : null;
                return $value;
            });
            return responseMsgs(true, "Meter List!", remove_null($returnData), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Apply For Deactivation
     * | Save the details for Deactivation
     * | @param request
     * | @var 
        | Not Working
        | Serial No : 06
        | Differenciate btw citizen and user 
        | check if the ulb is same as the consumer details 
     */
    public function applyDeactivation(reqDeactivate $request)
    {

        try {
            $user                           = authUser($request);
            $refRequest                     = array();
            $mDocuments                     = $request->documents;
            $ulbWorkflowObj                 = new WfWorkflow();
            $mWorkflowTrack                 = new WorkflowTrack();
            $mWaterSecondConsumer           = new waterSecondConsumer();
            $mWaterConsumerCharge           = new WaterConsumerCharge();
            $mWaterConsumerChargeCategory   = new WaterConsumerChargeCategory();
            $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
            $mWaterConsumerComplain         = new WaterConsumerComplain();
            $refUserType                    = Config::get('waterConstaint.REF_USER_TYPE');
            $refConsumerCharges             = Config::get('waterConstaint.CONSUMER_CHARGE_CATAGORY');
            $refApplyFrom                   = Config::get('waterConstaint.APP_APPLY_FROM');
            $refWorkflow                    = Config::get('workflow-constants.WATER_DISCONNECTION');
            $refConParamId                  = Config::get('waterConstaint.PARAM_IDS');
            $confModuleId                   = Config::get('module-constants.WATER_MODULE_ID');

            # Check the condition for deactivation
            $refDetails = $this->PreConsumerDeactivationCheck($request, $user);
            $ulbId      = $request->ulbId ?? $refDetails['consumerDetails']['ulb_id'];

            # Get initiater and finisher
            if ($request->requestType == 10 || $request->requestType == 11) {                // static for water Complain workflow
                $refWorkflow = 41; 
            }

            $ulbWorkflowId = $ulbWorkflowObj->getulbWorkflowId($refWorkflow, $ulbId);
            if (!$ulbWorkflowId) {
                throw new Exception("Respective Ulb is not maped to Water Workflow!");
            }
            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);
            $refFinisherRoleId  = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId     = DB::select($refFinisherRoleId);
            $initiatorRoleId    = DB::select($refInitiatorRoleId);
            if (!$finisherRoleId || !$initiatorRoleId) {
                throw new Exception("Initiator Role or finisher Role not found for respective Workflow!");
            }

            # If the user is not citizen
            if ($user->user_type != $refUserType['1']) {
                $request->request->add(['workflowId' => $refWorkflow]);
                $roleDetails = $this->getRole($request);
                if (!$roleDetails) {
                    throw new Exception("Role not found!");
                }
                $roleId = $roleDetails['wf_role_id'];
                $refRequest = [
                    "applyFrom" => $user->user_type,
                    "empId"     => $user->id
                ];
            } else {
                $refRequest = [
                    "applyFrom" => $refApplyFrom['1'],
                    "citizenId" => $user->id
                ];
            }

            # Get chrages for deactivation
            $chargeDetails = $mWaterConsumerChargeCategory->getChargesByid($request->requestType);
            $refChargeList = collect($refConsumerCharges)->flip();

            $refRequest["initiatorRoleId"]   = collect($initiatorRoleId)->first()->role_id;
            $refRequest["finisherRoleId"]    = collect($finisherRoleId)->first()->role_id;
            $refRequest["roleId"]            = $roleId ?? null;
            $refRequest["ulbWorkflowId"]     = $ulbWorkflowId->id;
            $refRequest["chargeCategoryId"]  = $chargeDetails->id;
            // $refRequest["amount"]            = $chargeAmount->amount;
            $refRequest['userType']          = $user->user_type;

            $this->begin();
            $idGeneration       = new PrefixIdGenerator($refConParamId['WCD'], $ulbId);
            $applicationNo      = $idGeneration->generate();
            $applicationNo      = str_replace('/', '-', $applicationNo);
            $deactivatedDetails = $mWaterConsumerActiveRequest->saveRequestDetails($request, $refDetails['consumerDetails'], $refRequest, $applicationNo);
            # Complain Data 
            if ($request->requestType == 10) {
                $mWaterConsumerComplain->AddConsumerComplain($request, $deactivatedDetails);
            }

            $metaRequest = [
                // 'chargeAmount'      => $chargeAmount->amount,
                // 'amount'            => $chargeAmount->amount,
                'ruleSet'           => null,
                'chargeCategoryId'  => $refConsumerCharges['WATER_DISCONNECTION'],
                'relatedId'         => $deactivatedDetails['id'],
                'status'            => 2                                                    // Static
            ];
            // $mWaterConsumerCharge->saveConsumerCharges($metaRequest, $request->consumerId, $refChargeList['2']);
            #save Document
            $this->uploadHoardDocument($deactivatedDetails['id'], $mDocuments, $request->auth);
            $mWaterConsumerActiveRequest->updateUploadStatus($deactivatedDetails, true);

            # Save data in track
            $metaReqs = new Request(
                [
                    'citizenId'         => $refRequest['citizenId'] ?? null,
                    'moduleId'          => $confModuleId,
                    'workflowId'        => $ulbWorkflowId->id,
                    'refTableDotId'     => 'water_consumer_active_requests.id',             // Static                          // Static                              // Static
                    'refTableIdValue'   => $deactivatedDetails['id'],
                    'user_id'           => $refRequest['empId'] ?? null,
                    'ulb_id'            => $ulbId,
                    'senderRoleId'      => $refRequest['empId'] ?? null,
                    'receiverRoleId'    => collect($initiatorRoleId)->first()->role_id,
                ]
            );
            $mWorkflowTrack->saveTrack($metaReqs);
            $returnData = [
                'applicationNo'         => $applicationNo,
                "Id"                    => $metaRequest['relatedId'],
                'applicationDetails'    => $metaRequest,
            ];
            $this->commit();
            return responseMsgs(true, "Respective Consumer Deactivated!", $returnData, "", "02", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }

    /*
     * upload Document By agency At the time of Registration
     * @param Request $req
     * @return \Illuminate\Http\JsonResponse
     */

    public function uploadHoardDocument($ActiveRequestId, $documents, $auth)
    {
        $docUpload = new DocumentUpload;
        $mWfActiveDocument = new WfActiveDocument();
        $mActiveRequest = new WaterConsumerActiveRequest();
        $relativePath       = Config::get('waterConstaint.WATER_RELATIVE_PATH');
        $documents;


        collect($documents)->map(function ($doc) use ($ActiveRequestId, $docUpload, $mWfActiveDocument, $mActiveRequest, $relativePath, $auth) {
            $metaReqs = array();
            $getApplicationDtls = $mActiveRequest->getApplicationDtls($ActiveRequestId);
            $refImageName = $doc['docCode'];
            $refImageName = $getApplicationDtls->id . '-' . $refImageName;
            $documentImg = $doc['image'];
            $imageName = $docUpload->upload($refImageName, $documentImg, $relativePath);
            $metaReqs['moduleId'] = Config::get('module-constants.WATER_MODULE_ID');
            $metaReqs['activeId'] = $getApplicationDtls->id;
            $metaReqs['workflowId'] = $getApplicationDtls->workflow_id;
            $metaReqs['ulbId'] = $getApplicationDtls->ulb_id ?? 2;
            $metaReqs['relativePath'] = $relativePath;
            $metaReqs['document'] = $imageName;
            $metaReqs['docCode'] = $doc['docCode'];
            $metaReqs['ownerDtlId'] = $doc['ownerDtlId'];

            $a = new Request($metaReqs);
            // $mWfActiveDocument->postDocuments($a, $auth);
            $metaReqs =  $mWfActiveDocument->metaRequest($metaReqs);
            $mWfActiveDocument->create($metaReqs);
            // foreach ($metaReqs as $key => $val) {
            //     $mWfActiveDocument->$key = $val;
            // }
            // $mWfActiveDocument->save();
        });
    }

    /**
     * | Check the condition before applying for citizen Request
     * | @param
     * | @var 
        | Not Working
        | Serial No : 06.01
        | Recheck the amount and the order from weaver committee 
        | Check if the consumer applied for other requests
        | Ceheck the date of the consumer demand
     */
    public function PreConsumerDeactivationCheck($request, $user)
    {
        $consumerId                     = $request->consumerId;
        $mWaterSecondConsumer           = new waterSecondConsumer();
        $mWaterConsumerDemand           = new WaterConsumerDemand();
        $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
        $refUserType                    = Config::get('waterConstaint.REF_USER_TYPE');

        $refConsumerDetails = $mWaterSecondConsumer->getConsumerDetails($consumerId)->first();
        if($refConsumerDetails->status == 4){
            throw new Exception ('Please paid Your Connection Fee First');
        }
        if ($request->requestType == 2) {
            $pendingDemand      = $mWaterConsumerDemand->getConsumerDemand($consumerId);
            $firstPendingDemand = collect($pendingDemand)->first();

            if (isset($firstPendingDemand)) {
                throw new Exception("There are unpaid pending demand!");
            }
        }

        if (isset($request->ulbId) && $request->ulbId != $refConsumerDetails->ulb_id) {
            throw new Exception("Ulb not matched according to consumer connection!");
        }
        // if ($refConsumerDetails->user_type == $refUserType['1'] && $user->id != $refConsumerDetails->user_id) {
        //     throw new Exception("You are not the autherised user who filled before the connection!");
        // }
        // $activeReq = $mWaterConsumerActiveRequest->getRequestByConId($consumerId)->first();
        // if ($activeReq) {
        //     // throw new Exception("There are other request applied for respective consumer connection!");
        //     throw new Exception("Already $activeReq->charge_category Applied");
        // }
        return [
            "consumerDetails" => $refConsumerDetails
        ];
    }



    /**
     * | Post Other Payment Modes for Cheque,DD,Neft
     * | @param req
        | Serial No : 06.03.01
        | Not Working
     */
    public function postOtherPaymentModes($req)
    {
        $cash = Config::get('payment-constants.PAYMENT_MODE.3');
        $moduleId = Config::get('module-constants.WATER_MODULE_ID');
        $mTempTransaction = new TempTransaction();

        if ($req['paymentMode'] != $cash) {
            $mPropChequeDtl = new WaterChequeDtl();
            $chequeReqs = [
                'user_id'           => $req['userId'],
                'consumer_id'       => $req['id'],
                'transaction_id'    => $req['tranId'],
                'cheque_date'       => $req['chequeDate'],
                'bank_name'         => $req['bankName'],
                'branch_name'       => $req['branchName'],
                'cheque_no'         => $req['chequeNo']
            ];

            $mPropChequeDtl->postChequeDtl($chequeReqs);
        }

        $tranReqs = [
            'transaction_id'    => $req['tranId'],
            'application_id'    => $req['id'],
            'module_id'         => $moduleId,
            'workflow_id'       => $req['workflowId'] ?? 0,
            'transaction_no'    => $req['tranNo'],
            'application_no'    => $req['applicationNo'],
            'amount'            => $req['amount'],
            'payment_mode'      => strtoupper($req['paymentMode']),
            'cheque_dd_no'      => $req['chequeNo'],
            'bank_name'         => $req['bankName'],
            'tran_date'         => $req['todayDate'],
            'user_id'           => $req['userId'],
            'ulb_id'            => $req['ulbId'],
            'ward_no'           => $req['ward_no']
        ];
        $mTempTransaction->tempTransaction($tranReqs);
    }


    #---------------------------------------------------------------------------------------------------------#

    /**
     * | Demand deactivation process
     * | @param 
     * | @var 
     * | @return 
        | Not Working
        | Serial No :
        | Not Build
     */
    public function consumerDemandDeactivation(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'    => "required|digits_between:1,9223372036854775807",
                'demandId'      => "required|array|unique:water_consumer_demands,id'",
                'paymentMode'   => "required|in:Cash,Cheque,DD",
                'amount'        => "required",
                'reason'        => "required"
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $mWaterWaterConsumer = new WaterWaterConsumer();
            $mWaterConsumerDemand = new WaterConsumerDemand();

            $this->checkDeactivationDemand($request);
            $this->checkForPayment($request);
        } catch (Exception $e) {
            return responseMsgs(true, $e->getMessage(), "", "", "01", ".ms", "POST", $request->deviceId);
        }
    }

    /**
     * | check if the following conditon if fullfilled for demand deactivation
     * | check for valid user
     * | @param request
     * | @var 
     * | @return 
        | Not Working
        | Serial No: 
        | Not Build
        | Get Concept for deactivation demand
     */
    public function checkDeactivationDemand($request)
    {
        return true;
    }

    /**
     * | Check the concept for payment and amount
     * | @param request
     * | @var 
     * | @return 
        | Not Working
        | Serial No:
        | Get Concept Notes for demand deactivation 
     */
    public function checkForPayment($request)
    {
        $mWaterTran = new WaterTran();
    }

    #---------------------------------------------------------------------------------------------------------#


    /**
     * | View details of the caretaken water connection
     * | using user id
     * | @param request
        | Working
        | Serial No : 07
     */
    public function viewCaretakenConnection(Request $request)
    {
        try {
            $mWaterWaterConsumer        = new WaterSecondConsumer();
            $mActiveCitizenUndercare    = new ActiveCitizenUndercare();

            $connectionDetails = $mActiveCitizenUndercare->getDetailsByCitizenId();
            $checkDemand = collect($connectionDetails)->first();
            if (is_null($checkDemand))
                throw new Exception("Under taken data not found!");

            $consumerIds = collect($connectionDetails)->pluck('consumer_id');
            $consumerDetails = $mWaterWaterConsumer->getConsumerByIds($consumerIds)->get();
            $checkConsumer = collect($consumerDetails)->first();
            if (is_null($checkConsumer)) {
                throw new Exception("Consumer Details Not Found!");
            }
            return responseMsgs(true, 'List of undertaken water connections!', remove_null($consumerDetails), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | Add Fixed Rate for the Meter connection is under Fixed
     * | Admin Entered Data
        | Serial No : 08
        | Use It
        | Recheck 
     */
    public function addFixedRate(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'    => "required|digits_between:1,9223372036854775807",
                'document'      => "required|mimes:pdf,jpg,jpeg,png",
                'ratePerMonth'  => "required|numeric"
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $consumerId             = $request->consumerId;
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            $fixedMeterCode         = Config::get("waterConstaint.WATER_FIXED_CODE");

            $relatedDetails = $this->checkParamForFixedEntry($consumerId);
            $metaRequest = new Request([
                'consumerId'                => $consumerId,
                'connectionDate'            => $relatedDetails['meterDetails']['connection_date'],
                'connectionType'            => $relatedDetails['meterDetails']['connection_type'],
                'newMeterInitialReading'    => $relatedDetails['meterDetails']['initial_reading']
            ]);

            $this->begin();
            $refDocument = $this->saveDocument($request, $fixedMeterCode);
            $document = [
                'relaivePath'   => $refDocument['relaivePath'],
                'document'      => $refDocument['document']
            ];
            $mWaterConsumerMeter->saveMeterDetails($metaRequest, $document, $request->ratePerMonth);
            $this->commit();
            return responseMsgs(true, "Fixed rate entered successfully!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), [""], "", "01", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | Check the parameter for Fixed meter entry
     * | @param consumerId
        | Seriel No : 08.01
        | Not used
     */
    public function checkParamForFixedEntry($consumerId)
    {
        $mWaterConsumerMeter    = new WaterConsumerMeter();
        $mWaterWaterConsumer    = new WaterWaterConsumer();
        $refPropertyType        = Config::get('waterConstaint.PROPERTY_TYPE');
        $refConnectionType      = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');

        // $consumerDetails = $mWaterWaterConsumer->getConsumerDetailById($consumerId);
        // if ($consumerDetails->property_type_id != $refPropertyType['Government'])
        // throw new Exception("Consumer's property type is not under Government!");

        $meterConnectionDetails = $mWaterConsumerMeter->getMeterDetailsByConsumerId($consumerId)->first();
        if (!$meterConnectionDetails)
            throw new Exception("Consumer meter detail's not found maybe meter is not installed!");

        if ($meterConnectionDetails->connection_type != $refConnectionType['Fixed'])
            throw new Exception("Consumer meter's connection type is not fixed!");

        return [
            "meterDetails" => $meterConnectionDetails
        ];
    }


    /**
     * | Calculate Final meter reading according to demand upto date and previous upto data 
     * | @param request
        | Serial No : 09
        | Working
     */
    public function calculateMeterFixedReading(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'  => "required|",
                'uptoData'    => "required|date",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $todayDate                  = Carbon::now();
            $refConsumerId              = $request->consumerId;
            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();

            if ($request->uptoData > $todayDate) {
                throw new Exception("Upto date should not be grater than" . " " . $todayDate);
            }
            $refConsumerDemand = $mWaterConsumerDemand->consumerDemandByConsumerId($refConsumerId);
            if (is_null($refConsumerDemand)) {
                throw new Exception("There should be last data regarding meter!");
            }

            $refOldDemandUpto   = $refConsumerDemand->demand_upto;
            $privdayDiff        = Carbon::parse($refConsumerDemand->demand_upto)->diffInDays(Carbon::parse($refConsumerDemand->demand_from));
            $endDate            = Carbon::parse($request->uptoData);
            $startDate          = Carbon::parse($refOldDemandUpto);

            $difference = $endDate->diffInMonths($startDate);
            if ($difference < 1 || $startDate > $endDate) {
                throw new Exception("Current uptoDate should be greater than the previous uptoDate! and should have a month difference!");
            }
            $diffInDays = $endDate->diffInDays($startDate);
            $finalMeterReading = $mWaterConsumerInitialMeter->getmeterReadingAndDetails($refConsumerId)
                ->orderByDesc('id')
                ->first();
            $finalSecondLastReading = $mWaterConsumerInitialMeter->getSecondLastReading($refConsumerId, $finalMeterReading->id);
            if (is_null($refConsumerDemand)) {
                throw new Exception("There should be demand for the previous meter entry!");
            }

            $refTaxUnitConsumed = ($finalMeterReading['initial_reading'] ?? 0) - ($finalSecondLastReading['initial_reading'] ?? 0);
            $avgReading         = $privdayDiff > 0 ? $refTaxUnitConsumed / $privdayDiff : 1;
            $lastMeterReading   = $finalMeterReading->initial_reading;
            $ActualReading      = ($diffInDays * $avgReading) + $lastMeterReading;

            $returnData['finalMeterReading']    = round($ActualReading, 2);
            $returnData['diffInDays']           = $diffInDays;
            $returnData['previousConsumed']     = $refTaxUnitConsumed;

            return responseMsgs(true, "calculated date difference!", $returnData, "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * | Get Details for memo
     * | Get all details for the consumer application and consumer both details 
     * | @param request
        | Serial No 
        | Use
        | Not Finished
        | Get the card details 
     */
    public function generateMemo(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerNo'  => "required",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $refConsumerNo          = $request->consumerNo;
            $mWaterWaterConsumer    = new WaterWaterConsumer();
            $mWaterTran             = new WaterTran();

            $dbKey = "consumer_no";
            $consumerDetails = $mWaterWaterConsumer->getRefDetailByConsumerNo($dbKey, $refConsumerNo)->first();
            if (is_null($consumerDetails)) {
                throw new Exception("consumer Details not found!");
            }
            $applicationDetails = $this->Repository->getconsumerRelatedData($consumerDetails->id);
            if (is_null($applicationDetails)) {
                throw new Exception("Application Details not found!");
            }
            $transactionDetails = $mWaterTran->getTransNo($consumerDetails->apply_connection_id, null)->get();
            $checkTransaction = collect($transactionDetails)->first();
            if ($checkTransaction) {
                throw new Exception("transactions not found!");
            }

            $consumerDetails;           // consumer related details 
            $applicationDetails;        // application / owners / siteinspection related details 
            $transactionDetails;        // all transactions details 
            $var = null;

            $returnValues = [
                "consumerNo"            => $var,
                "applicationNo"         => $var,
                "year"                  => $var,
                "receivingDate"         => $var,
                "ApprovalDate"          => $var,
                "receiptNo"             => $var,
                "paymentDate"           => $var,
                "wardNo"                => $var,
                "applicantName"         => $var,
                "guardianName"          => $var,
                "correspondingAddress"  => $var,
                "mobileNo"              => $var,
                "email"                 => $var,
                "holdingNo"             => $var,
                "safNo"                 => $var,
                "builUpArea"            => $var,
                "connectionThrough"     => $var,
                "AppliedFrom"           => $var,
                "ownersDetails"         => $var,
                "siteInspectionDetails" => $var,


            ];
            return responseMsgs(true, "successfully fetched memo details!", remove_null($returnValues), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $request->deviceId);
        }
    }

    //Start///////////////////////////////////////////////////////////////////////
    /**
     * | Search the governmental prop water commention 
     * | Search only the Gov water connections 
        | Serial No :
        | use
        | Not finished
     */
    public function searchFixedConsumers(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'filterBy'  => 'required',
                'parameter' => 'required'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {

            return $waterReturnDetails = $this->getDetailByConsumerNo($request, 'consumer_no', '2016000500');
            return false;

            $mWaterConsumer = new WaterWaterConsumer();
            $key            = $request->filterBy;
            $paramenter     = $request->parameter;
            $string         = preg_replace("/([A-Z])/", "_$1", $key);
            $refstring      = strtolower($string);

            switch ($key) {
                case ("consumerNo"):                                                                        // Static
                    $waterReturnDetails = $this->getDetailByConsumerNo($request, $refstring, $paramenter);
                    $checkVal = collect($waterReturnDetails)->first();
                    if (!$checkVal)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("holdingNo"):                                                                         // Static
                    $waterReturnDetails = $mWaterConsumer->getDetailByConsumerNo($request, $refstring, $paramenter)->get();
                    $checkVal = collect($waterReturnDetails)->first();
                    if (!$checkVal)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("safNo"):                                                                             // Static
                    $waterReturnDetails = $mWaterConsumer->getDetailByConsumerNo($request, $refstring, $paramenter)->get();
                    $checkVal = collect($waterReturnDetails)->first();
                    if (!$checkVal)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ("applicantName"):                                                                     // Static
                    $paramenter = strtoupper($paramenter);
                    $waterReturnDetails = $mWaterConsumer->getDetailByOwnerDetails($refstring, $paramenter)->get();
                    $checkVal = collect($waterReturnDetails)->first();
                    if (!$checkVal)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                case ('mobileNo'):                                                                          // Static
                    $paramenter = strtoupper($paramenter);
                    $waterReturnDetails = $mWaterConsumer->getDetailByOwnerDetails($refstring, $paramenter)->get();
                    $checkVal = collect($waterReturnDetails)->first();
                    if (!$checkVal)
                        throw new Exception("Data according to " . $key . " not Found!");
                    break;
                default:
                    throw new Exception("Data provided in filterBy is not valid!");
            }
            return responseMsgs(true, "Water Consumer Data According To Parameter!", remove_null($waterReturnDetails), "", "01", "652 ms", "POST", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    // Calling function
    public function getDetailByConsumerNo($req, $key, $refNo)
    {
        $refConnectionType = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');
        return WaterWaterConsumer::select(
            'water_consumers.id',
            'water_consumers.consumer_no',
            'water_consumers.ward_mstr_id',
            'water_consumers.address',
            'water_consumers.holding_no',
            'water_consumers.saf_no',
            'water_consumers.ulb_id',
            'ulb_ward_masters.ward_name',
            DB::raw("string_agg(water_consumer_owners.applicant_name,',') as applicant_name"),
            DB::raw("string_agg(water_consumer_owners.mobile_no::VARCHAR,',') as mobile_no"),
            DB::raw("string_agg(water_consumer_owners.guardian_name,',') as guardian_name"),
        )
            ->join('water_consumer_owners', 'water_consumer_owners.consumer_id', '=', 'water_consumers.id')
            ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_consumers.ward_mstr_id')
            ->leftjoin('water_consumer_meters', 'water_consumer_meters.consumer_id', 'water_consumers.id')
            ->where('water_consumers.' . $key, 'LIKE', '%' . $refNo . '%')
            ->where('water_consumers.status', 1)
            ->where('water_consumers.ulb_id', authUser($req)->ulb_id)
            ->where('water_consumer_meters.connection_type', $refConnectionType['Fixed'])
            ->groupBy(
                'water_consumers.saf_no',
                'water_consumers.holding_no',
                'water_consumers.address',
                'water_consumers.id',
                'water_consumers.ulb_id',
                'water_consumer_owners.consumer_id',
                'water_consumers.consumer_no',
                'water_consumers.ward_mstr_id',
                'ulb_ward_masters.ward_name'
            )->first();
    }
    ///////////////////////////////////////////////////////////////////////End//


    /**
     * | Citizen self generation of demand 
     * | generate demand only the last day of the month
        | Serial No :
        | Working
     */
    public function selfGenerateDemand(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'id'            => 'required',
                'finalReading'  => 'required',
                'document'      => 'required|file|'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $today                  = Carbon::now();
            $consumerId             = $req->id;
            $mWaterWaterConsumer    = new WaterWaterConsumer();
            $refConsumerDetails     = $mWaterWaterConsumer->getConsumerDetailById($consumerId);
            $refDetails             = $this->checkUser($req, $refConsumerDetails);
            $metaReq = new Request([
                "consumerId" => $consumerId
            ]);

            $this->checkDemandGeneration($metaReq, $refConsumerDetails);
            $metaRequest = new Request([
                "consumerId"    => $consumerId,
                "finalRading"   => $req->finalReading,                          // if the demand is generated for the first time
                "demandUpto"    => $today->format('Y-m-d'),
                "document"      => $req->document,
            ]);
            $returnDetails = $this->saveGenerateConsumerDemand($metaRequest);
            if ($returnDetails->original['status'] == false) {
                throw new Exception($returnDetails->original['message']);
            }
            return responseMsgs(true, "Self Demand Generated!", [], "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }

    /**
     * | Check the user details for self demand generation
     * | check the consumer details with user details
        | Serial No :
     */
    public function checkUser($req, $refConsumerDetails)
    {
        $user                       = authUser($req);
        $todayDate                  = Carbon::now();
        $endDate                    = Carbon::now()->endOfMonth();
        $formatEndDate              = $endDate->format('d-m-Y');
        $refUserType                = Config::get("waterConstaint.REF_USER_TYPE");
        $mActiveCitizenUndercare    = new ActiveCitizenUndercare();

        if ($endDate > $todayDate) {
            throw new Exception("Please generate the demand on $formatEndDate or after it!");
        }
        $careTakerDetails   = $mActiveCitizenUndercare->getWaterUnderCare($user->id)->get();
        $consumerIds        = collect($careTakerDetails)->pluck('consumer_id');
        if (!in_array($req->id, ($consumerIds->toArray()))) {
            if ($refConsumerDetails->user_type != $refUserType['1']) {
                throw new Exception("you are not the citizen whose consumer is assigned!");
            }
            if ($refConsumerDetails->user_id != $user->id) {
                throw new Exception("you are not the authorized user!");
            }
        }
    }

    /**
     * | Check the user type and return its id
        | Serial No :
        | Working
     */
    public function checkUserType($req)
    {
        $user = authUser($req);
        $confUserType = Config::get("waterConstaint.REF_USER_TYPE");
        $userType = $user->user_type;

        if ($userType == $confUserType['1']) {
            return [
                "citizen_id"    => $user->id,
                "user_type"     => $userType
            ];
        } else {
            return [
                "emp_id"    => $user->id,
                "user_type" => $userType
            ];
        }
    }


    /**
     * | Add the advance amount for consumer 
     * | If advance amount is present it should be added by a certain official
        | Serial No :
        | Under Con
     */
    public function addAdvance(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'consumerId'    => 'required|int',
                'amount'        => 'required|int',
                'document'      => 'required|file|',
                'remarks'       => 'required',
                'reason'        => 'nullable'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $user           = authUser($req);
            $docAdvanceCode = Config::get('waterConstaint.WATER_ADVANCE_CODE');
            $refAdvanceFor  = Config::get('waterConstaint.ADVANCE_FOR');
            $refWorkflow    = Config::get('workflow-constants.WATER_MASTER_ID');
            $mWaterAdvance  = new WaterAdvance();

            $refDetails = $this->checkParamForAdvanceEntry($req, $user);
            $req->request->add(['workflowId' => $refWorkflow]);
            $roleDetails = $this->getRole($req);
            $roleId = $roleDetails['wf_role_id'];
            $req->request->add(['roleId' => $roleId]);

            $this->begin();
            $docDetails = $this->saveDocument($req, $docAdvanceCode);
            $req->merge([
                "relatedId" => $req->consumerId,
                "userId"    => $user->id,
                "userType"  => $user->user_type,
            ]);
            $mWaterAdvance->saveAdvanceDetails($req, $refAdvanceFor['1'], $docDetails);
            $this->commit();
            return responseMsgs(true, "Advance Details saved successfully!", [], "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), [], "", "01", ".ms", "POST", $req->deviceId);
        }
    }


    /**
     * | Chech the params for adding advance 
        | Serial No :
        | Under Con
        | Check the autherised user is entring the advance amount
     */
    public function checkParamForAdvanceEntry($req, $user)
    {
        $consumerId = $req->consumerId;
        $refUserType = Config::get("waterConstaint.REF_USER_TYPE");
        $mWaterWaterConsumer = new WaterWaterConsumer();

        $consumerDetails = $mWaterWaterConsumer->getConsumerDetailById($consumerId);
        if ($user->user_type == $refUserType['1']) {
            throw new Exception("You are not a verified Use!");
        }
    }


    /**
     * | Get meter list for display in the process of meter entry
        | Serial No 
        | Working  
     */
    public function getConnectionList(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => "required|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $consumerid             = $request->consumerId;
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            $refMeterConnType       = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');
            $consumerMeterDetails   = $mWaterConsumerMeter->getMeterDetailsByConsumerId($consumerid)->first();

            # If consumer details are null, set an indicator key and default values
            if (!$consumerMeterDetails) {
                $status = false;
                $defaultTypes = ['Meter', 'Gallon'];
            }
            # Consumer details are not null, check connection_type 
            else {
                $status = true;
                $connectionType = $consumerMeterDetails->connection_type;
                switch ($connectionType) {
                    case ("1"):                                 // Static
                        $defaultTypes = ['Meter', 'Gallon', 'Meter/Fixed'];
                        break;
                    case ("2"):                                 // Static
                        $defaultTypes = ['Meter'];
                        break;
                    case ("3"):                                 // Static
                        $defaultTypes = ['Meter'];
                        break;
                    case ("4"):                                 // Static
                        $defaultTypes = ['Meter'];
                        break;
                }
            }
            foreach ($defaultTypes as $type) {
                $responseArray['displayData'][] = [
                    'id'    => $refMeterConnType[$type],
                    'name'  => strtoupper($type)
                ];
            }
            $responseArray['status'] = $status;
            return responseMsgs(true, "Meter List!", $responseArray, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | Apply for Ferule cleaning and Pipe shifting
        | Serial No :
        | Working
     */
    public function applyConsumerRequest(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "consumerId"  => 'required',
                "remarks"     => 'required',
                "mobileNo"    => 'required|numeric',            // Corresponding Mobile no
                "requestType" => 'required|in:4,5'              // Charge Catagory Id
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user       = authUser($request);
            $penalty    = 0;                                  // Static
            $consumerId = $request->consumerId;

            $mWfWorkflow                    = new WfWorkflow();
            $mWorkflowTrack                 = new WorkflowTrack();
            $mWaterWaterConsumer            = new WaterWaterConsumer();
            $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
            $mwaterConsumerCharge           = new WaterConsumerCharge();
            $mWaterConsumerChargeCategory   = new WaterConsumerChargeCategory();

            $refChargeCatagory  = Config::get("waterConstaint.CONSUMER_CHARGE_CATAGORY");
            $refUserType        = Config::get('waterConstaint.REF_USER_TYPE');
            $refApplyFrom       = Config::get('waterConstaint.APP_APPLY_FROM');
            $refModuleId        = Config::get('module-constants.WATER_MODULE_ID');

            $waterConsumerDetails   = $mWaterWaterConsumer->getConsumerDetailById($consumerId);
            $ulbId                  = $waterConsumerDetails['ulb_id'];
            $request->merge(["ulbId" => $ulbId]);

            # Check param for appliying for requests
            $refRelatedDetails = $this->checkParamForFeruleAndPipe($request, $waterConsumerDetails);
            $consumerCharges = $mWaterConsumerChargeCategory->getChargesByid($request->requestType);
            if (!$consumerCharges || !in_array($request->requestType, [$refChargeCatagory['FERRULE_CLEANING_CHECKING'], $refChargeCatagory['PIPE_SHIFTING_ALTERATION']])) {
                throw new Exception("Consumer charges not found");
            }

            # Get wf details
            $ulbWorkflowId  = $mWfWorkflow->getulbWorkflowId($refRelatedDetails['workflowMasterId'], $ulbId);
            if (!$ulbWorkflowId) {
                throw new Exception("Respective ULB IS NOT MAPED TO WORKFLOW!");
            }

            # If the user is not citizen
            if ($user->user_type != $refUserType['1']) {
                $request->request->add(['workflowId' => $ulbWorkflowId->id]);
                $roleDetails = $this->getRole($request);
                if (!$roleDetails) {
                    throw new exception('role not found');
                }
                $roleId = $roleDetails['wf_role_id'];
                $refRequest = [
                    "applyFrom" => $user->user_type,
                    "empId"     => $user->id
                ];
            } else {
                $refRequest = [
                    "applyFrom" => $refApplyFrom['1'],
                    "citizenId" => $user->id
                ];
            }

            # Get Initiater and finisher role 
            $refInitiaterRoleId  = $this->getInitiatorId($ulbWorkflowId->id);
            $refFinisherRoleId   = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId      = DB::select($refFinisherRoleId);
            $initiatorRoleId     = DB::select($refInitiaterRoleId);
            if (!$finisherRoleId || !$initiatorRoleId) {
                throw new Exception('initiator or finisher not found ');
            }
            $refRequest["initiatorRoleId"]  = collect($initiatorRoleId)->first()->role_id;
            $refRequest["finisherRoleId"]   = collect($finisherRoleId)->first()->role_id;
            $refRequest["roleId"]           = $roleId ?? null;
            $refRequest["userType"]         = $user->user_type;
            $refRequest["amount"]           = $consumerCharges->amount + $penalty;
            $refRequest["ulbWorkflowId"]    = $ulbWorkflowId->id;
            $refRequest["chargeCategory"]   = $consumerCharges->charge_category;
            $refRequest["chargeAmount"]     = $consumerCharges->amount;
            $refRequest["ruleSet"]          = null;
            $refRequest["chargeCategoryId"] = $consumerCharges->id;

            $this->begin();
            $idGeneration   = new PrefixIdGenerator($refRelatedDetails['idGenParam'], $ulbId);
            $applicationNo  = $idGeneration->generate();
            $applicationNo  = str_replace('/', '-', $applicationNo);

            $consumerRequestDetails = $mWaterConsumerActiveRequest->saveRequestDetails($request, $waterConsumerDetails, $refRequest, $applicationNo);
            $refRequest["relatedId"] = $consumerRequestDetails['id'];
            $mwaterConsumerCharge->saveConsumerCharges($refRequest, $consumerId, $consumerCharges->charge_category);

            # Save data in track
            $metaReqs = new Request(
                [
                    'citizenId'         => $refRequest['citizenId'] ?? null,
                    'moduleId'          => $refModuleId,
                    'workflowId'        => $ulbWorkflowId->id,
                    'refTableDotId'     => 'water_consumer_active_requests.id',             // Static    
                    'refTableIdValue'   => $consumerRequestDetails['id'],
                    'user_id'           => $user->id ?? null,
                    'ulb_id'            => $ulbId,
                    'senderRoleId'      => $refRequest['empId'] ?? null,
                    'receiverRoleId'    => collect($initiatorRoleId)->first()->role_id,
                ]
            );
            $mWorkflowTrack->saveTrack($metaReqs);
            $this->commit();
            $returnData = [
                "ApplicationNo" => $applicationNo
            ];
            return responseMsgs(true, "Successfully applied for the Request!", $returnData, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), [], "", "01", responseTime(), $request->getMethod(), $request->deviceId);
        }
    }


    /**
     * | Check param before appliying for pipe shifting and ferrul cleaning
        | Serial No :
        | Under Con
     */
    public function checkParamForFeruleAndPipe($request, $waterConsumerDetails)
    {
        $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
        $refWorkflow                    = Config::get('workflow-constants.WATER_CONSUMER_WF');
        $refChargeCatagory              = Config::get('waterConstaint.CONSUMER_CHARGE_CATAGORY');
        $refConParamId                  = Config::get('waterConstaint.PARAM_IDS');

        if (!$waterConsumerDetails) {
            throw new Exception("Water Consumer not found for given consumer Id!");
        }
        if ($request->requestType == $refChargeCatagory['FERRULE_CLEANING_CHECKING']) {
            $workflowId = $refWorkflow['FERRULE_CLEANING_CHECKING'];
            $refConParamId = $refConParamId['WFC'];
        } else {
            $workflowId = $refWorkflow['PIPE_SHIFTING_ALTERATION'];
            $refConParamId = $refConParamId['WPS'];
        }

        # Check if the request is already running 
        $isPerReq = $mWaterConsumerActiveRequest->getRequestByConId($request->consumerId)
            ->where('charge_catagory_id', $request->requestType)
            ->first();
        if ($isPerReq) {
            throw new Exception("Pre Request are in process!");
        }
        return [
            "workflowMasterId"  => $workflowId,
            "idGenParam"        => $refConParamId
        ];
    }


    /**
     * this function for apply disconnection water.
        | Change the process
        | Remove
     */
    public function applyWaterDisconnection(Request $request)
    {
        $request->validate([
            "consumerId"    => 'required',
            "remarks"       => 'required',
            "reason"        => 'required',
            "mobileNo"      => 'required|numeric',
            "address"       => 'required',

        ]);
        try {
            $user                         = authUser($request);
            $penalty                      = 0;
            $consumerId                   = $request->consumerId;
            $applydate                    = Carbon::now();
            $currentDate                  = $applydate->format('Y-m-d H:i:s');
            $mWaterConsumer               = new WaterWaterConsumer();
            $ulbWorkflowObj               = new WfWorkflow();
            $mwaterConsumerDemand         = new WaterConsumerDemand();
            $mWaterConsumerActive         = new WaterConsumerActiveRequest();
            $mwaterConsumerCharge         = new WaterConsumerCharge();
            $mWaterConsumerChargeCategory = new WaterConsumerChargeCategory();
            $waterTrack                   = new WorkflowTrack();
            $refUserType                  = Config::get('waterConstaint.REF_USER_TYPE');
            $refApplyFrom                 = Config::get('waterConstaint.APP_APPLY_FROM');
            $watercharges                 = Config::get("waterConstaint.CONSUMER_CHARGE_CATAGORY");
            $waterRole                    = Config::get("waterConstaint.ROLE-LABEL");
            $refWorkflow                  = config::get('workflow-constants.WATER_DISCONNECTION');
            $refConParamId                = Config::get("waterConstaint.PARAM_IDS");
            $waterConsumer                = WaterWaterConsumer::where('id', $consumerId)->first(); // Get the consumer ID from the database based on the given consumer Id
            if (!$waterConsumer) {
                throw new Exception("Water Consumer not found on the given consumer Id");
            }
            // $this->checkprecondition($request);
            $ulbId      = $request->ulbId ?? $waterConsumer['ulb_id'];
            $ulbWorkflowId  = $ulbWorkflowObj->getulbWorkflowId($refWorkflow, $ulbId);
            if (!$ulbWorkflowId) {
                throw new Exception("Respective ULB IS NOT MAPED TO WATER WORKFLOW");
            }
            $refInitiaterRoleId  = $this->getInitiatorId($ulbWorkflowId->id);
            $refFinisherRoleId   = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId      = DB::select($refFinisherRoleId);
            $initiatorRoleId     = DB::select($refInitiaterRoleId);
            if (!$finisherRoleId || !$initiatorRoleId) {
                throw new Exception('initiator or finisher not found ');
            }


            $consumerCharges = $mWaterConsumerChargeCategory->getChargesByid($watercharges['WATER_DISCONNECTION']);
            if ($consumerCharges == null) {
                throw new Exception("Consumer charges not found");
            }
            $meteReq = [
                "chargeAmount"      => $consumerCharges->amount,
                "chargeCategory"    => $consumerCharges->charge_category,
                "penalty"           => $penalty,
                "amount"            => $consumerCharges->amount + $penalty,
                "ruleSet"           => "test",
                "ulbId"             => $waterConsumer->ulb_id,
                "applydate"         => $currentDate,
                "wardmstrId"        => $waterConsumer->ward_mstr_id,
                "empDetailsId"      => $waterConsumer->emp_details_id,
                "chargeCategoryID"  => $consumerCharges->id,
                "ulbWorkflowId"     => $ulbWorkflowId->id

            ];
            # If the user is not citizen
            if ($user->user_type != $refUserType['1']) {
                $request->request->add(['workflowId' => $refWorkflow]);
                $roleDetails = $this->getRole($request);
                $roleId = $roleDetails['wf_role_id'];
                $refRequest = [
                    "applyFrom" => $user->user_type,
                    "empId"     => $user->id
                ];
            } else {
                $refRequest = [
                    "applyFrom" => $refApplyFrom['1'],
                    "citizenId" => $user->id
                ];
            }

            $refRequest["initiatorRoleId"]   = collect($initiatorRoleId)->first()->role_id;
            $refRequest["finisherRoleId"]    = collect($finisherRoleId)->first()->role_id;
            $refRequest['roleId']            = $roleId ?? null;
            $refRequest['userType']          = $user->user_type;
            $this->begin();
            // Save water disconnection charge using the saveConsumerCharges function
            $idGeneration            =  new PrefixIdGenerator($refConParamId['WCD'], $ulbId);
            $applicationNo           =  $idGeneration->generate();
            $applicationNo           = str_replace('/', '-', $applicationNo);
            $savewaterDisconnection = $mWaterConsumerActive->saveWaterConsumerActive($request, $consumerId, $meteReq, $refRequest, $applicationNo); // Call the storeActive method of WaterConsumerActiveRequest and pass the consumerId
            $var = [
                'relatedId' => $savewaterDisconnection->id,
                "Status"    => 2,

            ];

            $savewaterDisconnection = $mwaterConsumerCharge->saveConsumerChargesDiactivation($consumerId, $meteReq, $var);
            # save for  work flow track
            if ($user->user_type == "Citizen") {                                                        // Static
                $receiverRoleId = $waterRole['DA'];
            }
            if ($user->user_type != "Citizen") {                                                        // Static
                $receiverRoleId = collect($initiatorRoleId)->first()->role_id;
            }
            $metaReqs = new Request(
                [
                    'citizenId'         => $refRequest['citizenId'] ?? null,
                    'moduleId'          => 2,
                    'workflowId'        => $ulbWorkflowId['id'],
                    'refTableDotId'     => 'water_consumer_active_request.id',                                     // Static
                    'refTableIdValue'   => $var['relatedId'],
                    'user_id'           => $user->id,
                    'ulb_id'            => $ulbId,
                    'senderRoleId'      => $senderRoleId ?? null,
                    'receiverRoleId'    => $receiverRoleId ?? null
                ]
            );
            $waterTrack->saveTrack($metaReqs);
            $mWaterConsumer->dissconnetConsumer($consumerId, $var['Status']);
            $returnData = [
                'applicationDetails'    => $meteReq,
                'applicationNo'         => $applicationNo,
                'Id'                    => $var['relatedId'],

            ];
            $this->commit();
            return responseMsgs(true, "Successfully apply disconnection ", remove_null($returnData), "1.0", "350ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", $e->getCode(), "1.0", "", 'POST', "");
        }
    }

    public function updateConnectionType(reqMeterEntry $request)
    {
        try {
            $userDetails = Auth()->user();
            $userDetails ? $userDetails["emp_id"] = $userDetails->id : null;
            $m_consumer = new WaterSecondConsumer();
            $m_meter = new WaterConsumerMeter();
            $merterReading = new WaterConsumerInitialMeter();
            $m_demand = new WaterConsumerDemand();
            $meterRefImageName      = config::get('waterConstaint.WATER_METER_CODE');
            $consumerDtl = $m_consumer->find($request->consumerId);
            $connectinDtl = $consumerDtl->getLastConnectionDtl();
            $lastMeterreading = $consumerDtl->getLastReading();
            $lastDemand = $consumerDtl->getLastDemand();
            $lastPaidDemand = $consumerDtl->getLastPaidDemand();
            $allUnpaidDemand = $consumerDtl->getAllUnpaidDemand();
            if ($lastPaidDemand && $lastPaidDemand->demand_upto > $request->connectionDate) {
                throw new Exception("Demand Already paid Upto " . Carbon::parse($lastPaidDemand->demand_upto)->format("d-m-Y") . ". Connection date can not befor or equal to last demand paid upto date");
            }
            if ($connectinDtl && $connectinDtl->connection_type != 3 && (($lastMeterreading->initial_reading ?? 0) >= $request->oldMeterFinalReading)) {
                throw new Exception("old meter reading can not less than previouse reading");
            }
            $removeDemand = collect($allUnpaidDemand)->where("demand_upto", ">=", $request->connectionDate); #->where("generation_date",">=",$request->connectionDate);
            $demandRquest = new Request(
                [
                    "consumerId" => $consumerDtl->id,
                    "demandUpto"       => $request->connectionDate,
                    'finalRading'      => $request->oldMeterFinalReading,
                    "isNotstrickChek"  => true,
                    "document"         => $request->document,
                    "auth"             => $request->auth,
                ]
            );

            $this->begin();
            foreach ($removeDemand as $val) {
                $val->status = 0;
                $val->save();
            }
            $endOfPrivMonthEnd = Carbon::parse($request->connectionDate)->subMonth()->endOfMonth()->format("Y-m-d");
            $lastDemand = $m_demand->akolaCheckConsumerDemand($consumerDtl->id)->get()->sortByDesc("demand_upto")->first();
            $currentDemandUpdotoDate = Carbon::parse($lastDemand->demand_upto)->format("Y-m-d");
            $demandGenrationRes = $this->saveGenerateConsumerDemand($demandRquest);
            if (!$demandGenrationRes->original["status"] && ($endOfPrivMonthEnd > $currentDemandUpdotoDate)) {
                throw new Exception($demandGenrationRes->original["message"]);
            }
            $request->merge(['finalRading'      => $request->newMeterInitialReading]);
            $documentPath = $demandGenrationRes->original["data"];
            $oldFile = public_path() . "/" . ($documentPath["relaivePath"] ?? "") . "/" . ($documentPath["document"] ?? "");
            $neFileName =  public_path() . "/" . ($documentPath["relaivePath"] ?? "") . "/meter_doc" . "/" . ($documentPath["document"] ?? "");
            $meterDocPath = public_path() . "/" . ($documentPath["relaivePath"] ?? "") . "/meter_doc2";
            if (!file_exists($meterDocPath)) {
                mkdir($meterDocPath, 0755);
            }
            if (($documentPath["relaivePath"] ?? false) && copy($oldFile, $neFileName)) {
                $documentPath["relaivePath"] = $documentPath["relaivePath"] . "/meter_doc";
            }
            $meterDetails["meterId"] = $m_meter->saveMeterDetails($request, $documentPath, 0);
            $merterReading->saveConsumerReading($request, $meterDetails, $userDetails);
            $this->commit();
            return responseMsgs(true, "connection type changed", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, [$e->getMessage(), $e->getFile(), $e->getLine()], "", $e->getCode(), "1.0", "", 'POST', "");
        }
    }

    ####################################################################################################

    /**
     * | Doc upload through document upload service 
        | Type test
     */
    public function checkDoc(Request $request)
    {
        try {
            // $contentType = (collect(($request->headers->all())['content-type'] ?? "")->first());
            $file = $request->document;
            $filePath = $file->getPathname();
            $hashedFile = hash_file('sha256', $filePath);
            $filename = ($request->document)->getClientOriginalName();
            $api = "http://192.168.0.92:888/backend/document/upload";
            $transfer = [
                "file" => $request->document,
                "tags" => "good",
                // "reference" => 425
            ];
            $returnData = Http::withHeaders([
                "x-digest"      => "$hashedFile",
                "token"         => "8Ufn6Jio6Obv9V7VXeP7gbzHSyRJcKluQOGorAD58qA1IQKYE0",
                "folderPathId"  => 1
            ])->attach([
                [
                    'file',
                    file_get_contents($request->file('document')->getRealPath()),
                    $filename
                ]
            ])->post("$api", $transfer);

            if ($returnData->successful()) {
                $statusCode = $returnData->status();
                $responseBody = $returnData->body();
                return $returnData;
            } else {
                $statusCode = $returnData->status();
                $responseBody = $returnData->body();
                return $responseBody;
            }
            return false;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }


    /**
     * |apply water connection for akola
     * |made by :- Arshad 
     |in process
     
     */
    public function applyWaterConnection(newWaterRequest $req)
    {
        try {
            $ulbId = $req->ulbId;
            $mWaterSecondConsumer  = new WaterSecondConsumer();
            $mwaterConnection      = new WaterSecondConnectionCharge(); // Corrected class name
            $mwaterConsumerMeter   = new WaterConsumerMeter();
            $mwaterConsumerInitial = new WaterConsumerInitialMeter();
            $mwaterConsumerOwner   = new WaterConsumerOwner();
            $mwaterConsumerDemand  = new WaterConsumerDemand();
            $refConParamId         = Config::get("waterConstaint.PARAM_IDS");
            $refPropertyType       = Config::get("waterConstaint.PAYMENT_FOR_CONSUMER");
            $userDetails           = $this->checkUserType($req);

            if ($req->connectionType == 1) {
                $connectionType = 1;
                $connectionTypeString = 'Meter';
            } else {
                $connectionType = 3;
                $connectionTypeString = 'Fixed';
            }
            if ($req->Category == 'Slum' && $req->tapSize != 15) {
                throw new Exception('Tab size must be 15 for Slum');
            }
            if ($req->PropertyType == '2' && $req->Category == 'Slum') {
                throw new Exception('slum is not under the commercial');
            }
            // save consumer details 
            $this->begin();
            $idGeneration = new PrefixIdGenerator($refConParamId['WCD'], $ulbId);
            $applicationNo = $idGeneration->generate();
            $applicationNo = str_replace('/', '-', $applicationNo);
            $meta = [
                'status' => '1',
                'wardmstrId' => "3",
                "meterNo"   => $req['meterNo'],
                "connectionType" => $connectionType
            ];
            $water = $mWaterSecondConsumer->saveConsumer($req, $meta, $applicationNo);  // save active consumer
            $refRequest = [
                "consumerId"     => $water->id,
                "chargeCategory" => $refPropertyType['1'],
                "InitialMeter"   => $water->meter_reading,
                "ConnectionType" => $connectionType,
                "connectionType" => $connectionTypeString,
                "amount"         => $req->amount,
                "ward"           => $req->ward,
                "ConnectionDate" => $req->connectionDate
            ];
            $water = $mwaterConnection->saveCharges($refRequest);                                    // save connection charges 
            $water = $mwaterConsumerOwner->saveConsumerOwner($req, $refRequest);                     // save owner detail
            $water = $mwaterConsumerInitial->saveConsumerReadings($refRequest);                      // meter reading
            $water = $mwaterConsumerMeter->saveInitialMeter($refRequest, $meta);                     // initail or final reading
            $water = $mwaterConsumerDemand->saveNewConnectionDemand($req, $refRequest, $userDetails);
            $returnData = [
                'consumerNo' => $applicationNo,
                "consumerId" => $refRequest['consumerId']
            ];
            $this->commit();
            return responseMsgs(true, "save consumer!", remove_null($returnData), "", "02", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            $this->rollback(); // Assuming this is part of your transaction handling
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", ".ms", "POST", "");
        }
    }



    /**
     * | 
     */
    public function test(Request $req)
    {
        try {
            $mNowDate             = Carbon::now()->format('Y-m-d');
            $rules = [
                'consumerId'       => "required|digits_between:1,9223372036854775807",
                "demandUpto"       => "nullable|date|date_format:Y-m-d|before_or_equal:$mNowDate",
                'finalRading'      => "nullable|numeric",
            ];
            $validator = Validator::make($req->all(), $rules,);
            if ($validator->fails()) {
                throw new Exception($validator->errors());
            }
            $returnData = new WaterMonthelyCall($req->consumerId, $req->demandUpto, $req->finalRading); #WaterSecondConsumer::get();
            return $returnData->parentFunction($req);
            return responseMsgs(true, "Successfully apply disconnection ", $returnData, "1.0", "350ms", "POST", "");
        } catch (Exception $e) {
            $response["status"] = false;
            $response["errors"] = json_decode($e->getMessage());
            return collect($response);
        }
    }

    /**
     * get master  data for consumer
     */
    public function getMasterData()
    {
        try {
            $waterAkolaMaster = Config::get('waterConstaint.WATER_CONSUMER_MASTSER_DATA');
            $returnValues = [
                "PROPERTY_TYPE"    => $waterAkolaMaster['PROPERTY_TYPE'],
                "pipe_diameter"    => $waterAkolaMaster['PIPE_DIAMETER'],
                "CATEGORY"         => $waterAkolaMaster['CATEGORY'],

            ];
            // $returnValues = collect($masterValues)->merge($configMasterValues);
            return responseMsgs(true, "list of Water Consumer Master Data!", remove_null($returnValues), "", "01", "ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), $e->getFile(), "", "01", "ms", "POST", "");
        }
    }

    /**
     * get consumer and meter details 
     * 
     | change the validation key  
     */

    public function WaterConsumerDetails(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'applicationId' => 'required|integer',
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $mwaterConsumer         = new WaterSecondConsumer();
            $mWaterConsumerMeter    = new WaterConsumerMeter();
            $refConnectionName      = Config::get('waterConstaint.METER_CONN_TYPE');
            $refConsumerId          = $req->applicationId;
            #consumer dettails 
            $consumerDetails = $mwaterConsumer->fullWaterDetails($refConsumerId)->first();
            if (!$consumerDetails) {
                throw new Exception("consumer basic details not found!");
            }

            # meter Details 
            $refMeterData = $mWaterConsumerMeter->getMeterDetailsByConsumerIdV2($refConsumerId)->first();
            if ($refMeterData) {
                $refMeterData->ref_initial_reading = (float)($refMeterData->ref_initial_reading);
                switch ($refMeterData['connection_type']) {
                    case (1):
                        $connectionName = $refConnectionName['1'];                                      // Meter 
                        break;
                    case (3):
                        $connectionName = $refConnectionName['3'];                                      // Fixed - Non Meter
                        break;
                }
            }
            $refMeterData['connectionName'] = $connectionName ?? "";
            $refMeterData['ConnectionTypeName'] = $connectionName ?? "";
            $consumerDemand['meterDetails'] = $refMeterData;
            $returnValues = collect($consumerDetails)->merge($consumerDemand);
            return responseMsgs(true, "Consumer Details!", remove_null($returnValues), "", "01", ".ms", "POST", $req->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    /***
     * get deactivation Doc list 
     */
    public function getdeactivationList(Request $req)
    {
        $req->validate([
            'applicationId' => 'required|numeric'
        ]);
        try {
            $applicationId                  = $req->applicationId;
            $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();

            $refWaterApplication = $mWaterConsumerActiveRequest->getActiveRequest($applicationId)->first();                      // Get Saf Details
            if (!$refWaterApplication) {
                throw new Exception("Application Not Found for this id");
            }
            $documentList = $this->getdiactivationDocLists();                                                      // this funstion is for doc list 
            $waterTypeDocs['listDocs'] = collect($documentList)->map(function ($value, $key) use ($refWaterApplication) {
                return $this->filterdeactivationDocument($value, $refWaterApplication)->first();
            });
            $totalDocLists = collect($waterTypeDocs);
            $totalDocLists['docUploadStatus'] = $refWaterApplication->doc_upload_status;
            $totalDocLists['docVerifyStatus'] = $refWaterApplication->doc_status;
            return responseMsgs(true, "", remove_null($totalDocLists), "010203", "", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }
    /**
     * 
     */

    public function getdiactivationDocLists()
    {
        $mRefReqDocs    = new RefRequiredDocument();
        $moduleId       = Config::get('module-constants.WATER_MODULE_ID');
        $type           = ["METER_BILL"];                                                 // Static
        return $mRefReqDocs->getCollectiveDocByCode($moduleId, $type);
    }
    /**
     * filter the doc name 
     */
    public function filterdeactivationDocument($documentList, $refWaterApplication, $ownerId = null)
    {
        $mWfActiveDocument  = new WfActiveDocument();
        $applicationId      = $refWaterApplication->id;
        $workflowId         = $refWaterApplication->workflow_id;
        $moduleId           = Config::get('module-constants.WATER_MODULE_ID');
        $uploadedDocs       = $mWfActiveDocument->getDocByRefIds($applicationId, $workflowId, $moduleId);

        $explodeDocs = collect(explode('#', $documentList->requirements));
        $filteredDocs = $explodeDocs->map(function ($explodeDoc) use ($uploadedDocs, $ownerId, $documentList) {

            # var defining
            $document   = explode(',', $explodeDoc);
            $key        = array_shift($document);
            $label      = array_shift($document);
            $documents  = collect();

            collect($document)->map(function ($item) use ($uploadedDocs, $documents, $ownerId, $documentList) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $item)
                    ->where('owner_dtl_id', $ownerId)
                    ->first();
                if ($uploadedDoc) {
                    $path = $this->readDocumentPath($uploadedDoc->doc_path);
                    $fullDocPath = !empty(trim($uploadedDoc->doc_path)) ? $path : null;
                    $response = [
                        "uploadedDocId" => $uploadedDoc->id ?? "",
                        "documentCode"  => $item,
                        "ownerId"       => $uploadedDoc->owner_dtl_id ?? "",
                        "docPath"       => $fullDocPath ?? "",
                        "verifyStatus"  => $uploadedDoc->verify_status ?? "",
                        "remarks"       => $uploadedDoc->remarks ?? "",
                    ];
                    $documents->push($response);
                }
            });
            $reqDoc['docType']      = $key;
            $reqDoc['uploadedDoc']  = $documents->last();
            $reqDoc['docName']      = substr($label, 1, -1);
            // $reqDoc['refDocName'] = substr($label, 1, -1);

            $reqDoc['masters'] = collect($document)->map(function ($doc) use ($uploadedDocs) {
                $uploadedDoc = $uploadedDocs->where('doc_code', $doc)->first();
                $strLower = strtolower($doc);
                $strReplace = str_replace('_', ' ', $strLower);
                if (isset($uploadedDoc)) {
                    $path =  $this->readDocumentPath($uploadedDoc->doc_path);
                    $fullDocPath = !empty(trim($uploadedDoc->doc_path)) ? $path : null;
                }
                $arr = [
                    "documentCode"  => $doc,
                    "docVal"        => ucwords($strReplace),
                    "uploadedDoc"   => $fullDocPath ?? "",
                    "uploadedDocId" => $uploadedDoc->id ?? "",
                    "verifyStatus'" => $uploadedDoc->verify_status ?? "",
                    "remarks"       => $uploadedDoc->remarks ?? "",
                ];
                return $arr;
            });
            return $reqDoc;
        });
        return $filteredDocs;
    }

    /**
     * function for demands details  
     * 
     view bill 
     */
    public function getConsumerDemands(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => 'required'
            ]
        );

        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $docUrl                     = $this->_docUrl;
            $mWaterDemands              = new WaterConsumerDemand();
            $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
            $mWaterMeterReadingDoc      = new WaterMeterReadingDoc();
            $NowDate                    = Carbon::now()->format('Y-m-d');
            $bilDueDate                 = Carbon::now()->addDays(15)->format('Y-m-d');
            $ConsumerId                 = $request->consumerId;
            // $this->_DB->enableQueryLog();
            $demandDetails              = $mWaterDemands->getDemandBydemandIds($ConsumerId); // get demands detai
            if (!$demandDetails) {
                throw new Exception('demands not found ');
            }
            $allDemandGenerated = $mWaterDemands->getConsumerDemandV3($ConsumerId);           // get all demands of consumer generated 
            # sum of amount
            $sumAmount = collect($allDemandGenerated)->sum('due_balance_amount');
            $roundedSumAmount = round($sumAmount);
            $ConsumerInitial = $mWaterConsumerInitialMeter->calculateUnitsConsumed($ConsumerId);  # unit consumed
            $finalReading = $ConsumerInitial->first()->initial_reading;
            $initialReading = $ConsumerInitial->last()->initial_reading ?? 0;
            $documents = $mWaterMeterReadingDoc->getDocByDemandId($demandDetails->ref_demand_id);
            $demands =  [
                'billDate'          => $NowDate,
                'bilDueDate'        => $bilDueDate,
                'unitConsumed'      => ($finalReading - $initialReading),
                'initialReading'    => (int)$initialReading,
                'finalReading'      => (int)$finalReading,
                'initialDate'       => "",
                'due_balance_amount' =>  $roundedSumAmount,
                "meterImg"          => ($documents ? $docUrl . "/" . $documents->relative_path . "/" . $documents->file_name : 0)
            ];
            $returnValues = collect($demandDetails)->merge($demands);
            return responseMsgs(true, "Consumer Details!", remove_null($returnValues), "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    public function getConsumerDemandsV2(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => 'required'
            ]
        );

        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
            $waterConsumerDemandReceipt = new WaterConsumerDemandReceipt($request->consumerId);
            $waterConsumerDemandReceipt->generateDemandReceipts();
            $demandReciept = $waterConsumerDemandReceipt->_GRID;
            return responseMsgs(true, "Demand Recipt", remove_null($demandReciept));
        } catch (Exception $e) {
            responseMsgs(false, $e->getMessage(), "");
        }
    }

    /**
     * update consumer details
     */
    public function updateConsumerDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'         => 'required|integer',
                'mobile_no'          => 'nullable|',            // 'nullable|digits:10|regex:/[0-9]{10}/',
                'email'              => 'nullable|',
                'applicant_name'     => 'nullable|',
                'guardian_name'      => 'nullable|',
                'zoneId'             => 'nullable|',
                'wardId'             => 'nullable|',
                'address'            => 'nullable|',
                'property_no'        => 'nullable',
                'dtcCode'            => 'nullable',
                'oldConsumerNo'      => 'nullable',
                "category"           => "nullable|in:General,Slum",
                "propertytype"       =>  "nullable|in:1,2",
                "tapsize"            =>  "nullable",
                "landmark"           =>  "nullable",
                "document"           =>  "nullable|mimes:pdf,jpeg,png,jpg,gif",
                "remarks"            =>  "nullable",
                "meterNo"            =>  "nullable",
            ]
        );
        if ($validated->fails())
            return validationErrorV2($validated);
        try {
            $request->merge([
                "propertyNo" => $request->property_no,
                "mobileNo" => $request->mobile_no,
                "applicantName" => $request->applicant_name,
                "guardianName" => $request->guardian_name,
            ]);
            $now            = Carbon::now();
            $user           = Auth()->user();
            $userId         = $user->id;
            $consumerId     = $request->consumerId;
            $relativePath   = Config::get("waterConstaint.WATER_UPDATE_RELATIVE_PATH");
            $refImageName = $request->consumerId;
            $imageName = null;

            $docUpload = new DocumentUpload();
            $mWaterSecondConsumer = new waterSecondConsumer();
            $mWaterConsumerOwners = new WaterConsumerOwner();
            $mWaterConsumerLog = new WaterConsumersUpdatingLog();
            $mWaterConsumerOwnersLog = new WaterConsumerOwnerUpdatingLog();
            $consumerDtls = $mWaterSecondConsumer->find($consumerId);
            if (!$consumerDtls) {
                throw new Exception("consumer details not found!");
            }
            $owner = $mWaterConsumerOwners->where("consumer_id", $request->consumerId)->orderBy("id", "ASC")->first();

            $conUpdaleLog = $consumerDtls->replicate();
            $conUpdaleLog->setTable($mWaterConsumerLog->getTable());
            $conUpdaleLog->purpose      =   "Consumer Update";
            $conUpdaleLog->consumer_id  =   $consumerDtls->id;
            $conUpdaleLog->up_user_id   = $user->id;
            $conUpdaleLog->up_user_type = $user->user_type;
            $conUpdaleLog->remarks       = $request->remarks;

            $ownerUdatesLog = $owner->replicate();
            $ownerUdatesLog->setTable($mWaterConsumerOwnersLog->getTable());
            $ownerUdatesLog->woner_id  =   $owner->id;

            #=========consumer updates=================
            $consumerDtls->ward_mstr_id         =  $request->wardId         ? $request->wardId : $consumerDtls->ward_mstr_id;
            $consumerDtls->zone_mstr_id         =  $request->zoneId         ? $request->zoneId : $consumerDtls->zone_mstr_id;
            $consumerDtls->mobile_no            =  $request->mobileNo       ? $request->mobileNo : $consumerDtls->mobile_no;
            $consumerDtls->old_consumer_no      =  $request->oldConsumerNo  ? $request->oldConsumerNo : $consumerDtls->old_consumer_no;
            $consumerDtls->property_no          =  $request->propertyNo     ? $request->propertyNo : $consumerDtls->property_no;
            $consumerDtls->dtc_code             =  $request->dtcCode        ? $request->dtcCode : $consumerDtls->dtc_code;
            $consumerDtls->category             =  $request->category       ? $request->category : $consumerDtls->category;
            $consumerDtls->property_type_id     =  $request->propertytype   ? $request->propertytype : $consumerDtls->property_type_id;
            $consumerDtls->tab_size             =  $request->tapsize        ? $request->tapsize : $consumerDtls->tab_size;
            $consumerDtls->landmark             =  $request->landmark       ? $request->landmark : $consumerDtls->landmark;
            $consumerDtls->address              =  $request->address       ? $request->address : $consumerDtls->address;

            #=========consumer updates=================
            if ($owner) {
                $owner->applicant_name       =  $request->applicant_name      ? $request->applicant_name : $owner->applicant_name;
                $owner->guardian_name        =  $request->guardian_name       ? $request->guardian_name  : $owner->guardian_name;
                $owner->email                =  $request->email               ? $request->email          : $owner->email;
                $owner->mobile_no            =  $request->mobile_no           ? $request->mobile_no      : $owner->mobile_no;
            }

            $this->begin();

            $conUpdaleLog->save();
            if ($request->document) {
                $imageName = $docUpload->upload($conUpdaleLog->id, $request->document, $relativePath);
            }

            $ownerUdatesLog->consumers_updating_log_id = $conUpdaleLog->id;

            $consumerDtls->update();
            $owner ? $owner->update() : "";

            $conUpdaleLog->relative_path = $imageName ? $relativePath : null;
            $conUpdaleLog->document      = $imageName;
            $conUpdaleLog->new_data_json = json_encode($consumerDtls->toArray(), JSON_UNESCAPED_UNICODE);
            $conUpdaleLog->update();
            $owner ? $ownerUdatesLog->new_data_json = json_encode($owner->toArray(), JSON_UNESCAPED_UNICODE) : "";
            $owner ? $ownerUdatesLog->save() : "";


            $this->commit();
            return responseMsgs(true, "update consumer details succesfull!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }


    public function searchUpdateConsumerLog(Request $request)
    {
        $now = Carbon::now()->format("Y-m-d");
        $validated = Validator::make(
            $request->all(),
            [
                "fromDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "uptoDate" => "nullable|date|before_or_equal:$now|date_format:Y-m-d",
                "userId" => "nullable|digits_between:1,9223372036854775807",
                "wardId" => "nullable|digits_between:1,9223372036854775807",
                "zoneId"    => "nullable|digits_between:1,9223372036854775807",
                "page" => "nullable|digits_between:1,9223372036854775807",
                "perPage" => "nullable|digits_between:1,9223372036854775807",
            ]
        );
        if ($validated->fails())
            return validationErrorV2($validated);
        try {
            $fromDate = $uptoDate = $now;
            $userId = $wardId = $zoneId = null;
            $key = $request->key;
            if ($key) {
                $fromDate = $uptoDate = null;
            }
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if ($request->wardId) {
                $wardId = $request->wardId;
            }
            if ($request->zoneId) {
                $zoneId = $request->zoneId;
            }
            if ($request->userId) {
                $userId = $request->userId;
            }
            $data = WaterConsumersUpdatingLog::select(
                "water_consumers_updating_logs.id",
                "water_consumers_updating_logs.consumer_id",
                "water_consumers_updating_logs.consumer_no",
                "water_consumers_updating_logs.remarks",
                "water_consumers_updating_logs.purpose",
                "water_consumers_updating_logs.remarks",
                "water_consumers_updating_logs.address",
                "water_consumers_updating_logs.up_created_at AS created_at",
                "water_consumers_updating_logs.folio_no as property_no",
                "zone_masters.zone_name",
                "ulb_ward_masters.ward_name",
                "owners.applicant_name",
                "owners.guardian_name",
                "owners.mobile_no",
                "users.name AS user_name",
            )
                ->leftJoin("users", "users.id", "water_consumers_updating_logs.up_user_id")
                ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'water_consumers_updating_logs.ward_mstr_id')
                ->leftjoin('zone_masters', 'zone_masters.id', 'water_consumers_updating_logs.zone_mstr_id')
                ->leftJoin(DB::raw("(
                        SELECT water_consumer_owner_updating_logs.consumers_updating_log_id,
                            string_agg(water_consumer_owner_updating_logs.applicant_name,',') as applicant_name,
                            string_agg(water_consumer_owner_updating_logs.guardian_name,',') as guardian_name,
                            string_agg(water_consumer_owner_updating_logs.mobile_no,',') as mobile_no
                        FROM water_consumer_owner_updating_logs
                        JOIN water_consumers_updating_logs ON water_consumers_updating_logs.id = water_consumer_owner_updating_logs.consumers_updating_log_id
                        WHERE CAST(water_consumers_updating_logs.up_created_at AS DATE) BETWEEN '$fromDate' AND '$uptoDate' 
                        " . ($userId ? " AND water_consumers_updating_logs.up_user_id = $userId" : "") . "
                        " . ($wardId ? " AND water_consumers_updating_logs.ward_mstr_id = $wardId" : "") . "
                        " . ($zoneId ? " AND water_consumers_updating_logs.zone_mstr_id = $zoneId" : "") . "
                        GROUP BY water_consumer_owner_updating_logs.consumers_updating_log_id
                    )owners"), "owners.consumers_updating_log_id", "water_consumers_updating_logs.id");
            if ($fromDate && $uptoDate) {
                $data->whereBetween(DB::raw("CAST(water_consumers_updating_logs.up_created_at AS DATE)"), [$fromDate, $uptoDate]);
            }
            if ($userId) {
                $data->where("water_consumers_updating_logs.up_user_id", $userId);
            }
            if ($wardId) {
                $data->where("water_consumers_updating_logs.ward_mstr_id", $wardId);
            }
            if ($zoneId) {
                $data->where("water_consumers_updating_logs.zone_mstr_id", $zoneId);
            }
            if ($key) {
                $data->where(function ($where) use ($key) {
                    $where->orWhere("water_consumers_updating_logs.consumer_no", "ILIKE", "%$key%")
                        ->orWhere("water_consumers_updating_logs.old_consumer_no", "ILIKE", "%$key%")
                        ->orWhere("water_consumers_updating_logs.address", "ILIKE", "%$key%")
                        ->orWhere("owners.applicant_name", "ILIKE", "%$key%")
                        ->orWhere("owners.guardian_name", "ILIKE", "%$key%")
                        ->orWhere("owners.mobile_no", "ILIKE", "%$key%");
                });
            }
            $perPage = $request->perPage ? $request->perPage : 10;
            $paginator = $data->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            $queryRunTime = (collect(DB::getQueryLog())->sum("time"));
            return responseMsgs(true, "", $list);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    public function consumerUpdateDetailLogs(Request $request)
    {
        $logs = new  WaterConsumersUpdatingLog();
        $validated = Validator::make(
            $request->all(),
            [
                'applicationId'         => "required|integer|exists:" . $logs->getConnectionName() . "." . $logs->getTable() . ",id",
            ]
        );
        if ($validated->fails())
            return validationErrorV2($validated);
        try {
            $newConsumerData = new WaterSecondConsumer();
            $consumerLog = $logs->find($request->applicationId);
            $users = User::find($consumerLog->up_user_id);
            $consumerLog->property_type = $consumerLog->getProperty()->property_type ?? "";
            $consumerLog->zone = ZoneMaster::find($consumerLog->zone_mstr_id)->zone_name ?? "";
            $consumerLog->ward_name = UlbWardMaster::find($consumerLog->ward_mstr_id)->ward_name ?? "";
            $ownres      = $consumerLog->getOwners();
            foreach (json_decode($consumerLog->new_data_json, true) as $key => $val) {
                $newConsumerData->$key = $val;
            }
            $newConsumerData->property_type = $newConsumerData->getProperty()->property_type ?? "";
            $newConsumerData->zone = ZoneMaster::find($newConsumerData->zone_mstr_id)->zone_name ?? "";
            $newConsumerData->ward_name = UlbWardMaster::find($newConsumerData->ward_mstr_id)->ward_name ?? "";
            $newOwnresData = $ownres->map(function ($val) {
                $owner = new WaterConsumerOwner();

                foreach (json_decode($val->new_data_json, true) as $key => $val1) {
                    $owner->$key = $val1;
                }
                return $owner;
            });
            $commonFunction = new \App\Repository\Common\CommonFunction();
            $rols = ($commonFunction->getUserAllRoles($users->id)->first());
            $docUrl = Config::get('module-constants.DOC_URL');
            $header = [
                "user_name" => $users->name ?? "",
                "role" => $rols->role_name ?? "",
                "remarks" => $consumerLog->remarks ?? "",
                "upate_date" => $consumerLog->up_created_at ? Carbon::parse($consumerLog->up_created_at)->format("d-m-Y h:i:s A") : "",
                "document" => $consumerLog->document ? trim($docUrl . "/" . $consumerLog->relative_path . "/" . $consumerLog->document, "/") : "",
            ];
            $data = [
                "userDtls" => $header,
                "oldConsumer" => $consumerLog,
                "newConsumer" => $newConsumerData,
                "oldOwnere" => $ownres,
                "newOwnere" => $newOwnresData,
            ];
            return responseMsgs(true, "Log Details", remove_null($data), "", "010203", "1.0", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }


    /**
     * | Send whatsapp
     */
    public function sendSms(Request $request)
    {
        try {
            $whatsapp2 = (Whatsapp_Send(
                9031248170,
                "test_file_v3",
                [
                    "content_type" => "pdf",
                    [
                        [
                            "link" => "https://egov.modernulb.com/Uploads/Icon/Water%20_%20Akola%20Municipal%20Corportation%202.pdf",
                            "filename" => "TEST_PDF" . ".pdf"
                        ],
                    ],
                    "text" => [
                        "17",
                        "CON-100345",
                        "https://modernulb.com/water/waterViewDemand/28"
                    ]
                ]
            ));

            // $whatsapp2 = (Whatsapp_Send(
            //     7319867430,
            //     "test_file_v4",
            //     [
            //         "content_type" => "text",
            //         [
            //             "https://www.smartulb.co.in/RMCDMC/getImageLink.php?path=RANCHI/water_consumer_deactivation/26dd0dbc6e3f4c8043749885523d6a25.pdf",
            //             "notice.pdf"
            //         ]
            //     ]
            // ));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * update consumer details
        | Clear the concept of meter reding then save the meter reading
     */
    public function updateConsumerDemands(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'        => 'required|integer',
                'demandId'          => 'required|',
                'amount'            => 'required|min:1',
                'meterNo'           => 'nullable',
                'meterReading'      => 'nullable',
                'document'          => 'required|mimes:pdf,jpg,jpeg,png|',
                'demandFrom'        => 'nullable|date',
                'demandUpto'        => 'nullable|date'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $now            = Carbon::now();
            $user           = authUser($request);
            $userId         = $user->id;
            $usertype       = $user->user_type;
            $consumerId     = $request->consumerId;
            $demandId       = $request->demandId;
            $refdemandFrom  = $request->demandFrom;
            $refdemandUpto    = $request->demandUpto;
            $fullPaid       = false;
            $advanceAmount  = 0;                                                                        // Static

            $mWaterConsumerDemand       = new WaterConsumerDemand();
            $mWaterConsumerMeter        = new WaterConsumerMeter();
            $mWaterSecondConsumer       = new WaterSecondConsumer();
            $mWaterConsumerInitialMeter = new WaterConsumerInitialMeter();
            $docUpload                  = new DocUpload;

            # Check the params for demand updation
            $consumerDetaills = $mWaterSecondConsumer->getConsumerDetails($consumerId)->first();
            if (!$consumerDetaills) {
                throw new Exception('Consumer details not found');
            }
            $demandDetails = $mWaterConsumerDemand->consumerDemandId($demandId)->first();
            if (!$demandDetails) {
                throw new Exception("consumer details not found!");
            }

            $this->begin();
            $approvedWaterOwners = $demandDetails->replicate();
            $approvedWaterOwners->setTable('water_consumer_demand_records');

            # Callculation details 
            $dueAmount = $request->amount;
            $balanceAmount = $request->amount;
            if ($demandDetails->due_balance_amount && $demandDetails->due_balance_amount < $request->amount) {
                if ($demandDetails->paid_status == 1 && $demandDetails->is_full_paid == false) {
                    $dueAmount = ($request->amount - $demandDetails->due_balance_amount) ?? 0;
                }
            }
            if ($dueAmount < 0 || $dueAmount == 0) {
                $advanceAmount = abs($dueAmount);
                $dueAmount = 0;
                $fullPaid = true;
            }

            $updateReq = [
                "due_balance_amount"    => $dueAmount,
                "is_full_paid"          => $fullPaid,
                "balance_amount"        => $balanceAmount,
                "demand_from"           => $refdemandFrom,
                "demand_upto"           => $refdemandUpto,
                "current_demand"        => $dueAmount,
                "arrear_demand"         => $dueAmount
            ];
            $mWaterConsumerDemand->updateDemand($updateReq, $demandId);

            if ($request->meterNo) {
                $meterReq = [
                    "meter_no" => $request->meterNo
                ];
                $mWaterConsumerMeter->updateMeterDetails($consumerId, $meterReq);
            }

            # Save Document 
            if (isset($_FILES['document'])) {
                $relativePath = "Uploads/Water/DemandUpdation";
                $refImageName = "DemandUpdation";
                $refImageName = $request->consumerId . '-' . str_replace(' ', '_', $refImageName);
                $document     = $request->document;
                $imageName    = $docUpload->upload($refImageName, $document, $relativePath);
            }

            // # Save the consumer replicate details 
            $approvedWaterOwners->emp_details_id        = $userId;
            $approvedWaterOwners->user_type             = $usertype;
            $approvedWaterOwners->relative_path         = $relativePath;
            $approvedWaterOwners->document              = $imageName;
            $approvedWaterOwners->advance_amount        = $advanceAmount;
            $approvedWaterOwners->new_amount            = $request->amount;
            $approvedWaterOwners->new_reading           = $request->meterReading;
            $approvedWaterOwners->save();

            # Save Details in advance table
            $initialReq = [
                "initial_reading" => $request->meterReading,
            ];
            $mWaterConsumerInitialMeter->updateInitialMeter($consumerId, $initialReq);

            $this->commit();
            return responseMsgs(true, "Update Consumer Demand Succesfull!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    public function AutoCorrectDemand()
    {
        try {
            $dataSql = "
                        select water_consumer_taxes.*,demands.*
                        from water_consumer_taxes
                        join(
                            select consumer_tax_id,
                                min(demand_from) as demand_from,
                                max(demand_upto) as demand_upto,
                                sum(amount) as amount,
                                sum(due_balance_amount) as due_balance_amount,
                                sum(paid_total_tax) as paid_total_tax,
                                string_agg(id::text,',') as demand_ids --,
                            /*(
                                json_agg(
                                        json_build_object('id',id,
                                                        'consumer_id',consumer_id,
                                                        'ward_id',ward_id,
                                                        'ulb_id',ulb_id,
                                                        'consumer_tax_id',consumer_tax_id,
                                                        'generation_date',generation_date,
                                                        'amount',amount,
                                                        'emp_details_id',emp_details_id,
                                                        'paid_status',paid_status,
                                                        'status',status,
                                                        'demand_from',demand_from,
                                                        'demand_upto',demand_upto,
                                                        'penalty',penalty,
                                                        'current_meter_reading',current_meter_reading,
                                                        'adv_amount',adv_amount,
                                                        'last_payment_id',last_payment_id,
                                                        'spr_last_payment_id',spr_last_payment_id,
                                                        'unit_amount',unit_amount,
                                                        'connection_type',connection_type,
                                                        'demand_no',demand_no,
                                                        'balance_amount',balance_amount,
                                                        'panelty_updated_on',panelty_updated_on,
                                                        'created_at',created_at,
                                                        'updated_at',updated_at,
                                                        'old_id',old_id,
                                                        'citizen_id',citizen_id,
                                                        'user_id',user_id,
                                                        'due_amount',due_amount,
                                                        'due_penalty',due_penalty,
                                                        'due_balance_amount',due_balance_amount,
                                                        'due_adv_amount',due_adv_amount,
                                                        'is_full_paid',is_full_paid,
                                                        'arrear_demand',arrear_demand,
                                                        'current_demand',current_demand,
                                                        'arrear_demand_date',arrear_demand_date,
                                                        'current_demand_date',current_demand_date,
                                                        'outstanding_demand',outstanding_demand,
                                -- 						  'paid_arrear_demands',paid_arrear_demands,
                                -- 						  'arrear_amount',arrear_amount,
                                                        'due_arrear_demand',due_arrear_demand,
                                                        'due_current_demand',due_current_demand,
                                                        'paid_total_tax',paid_total_tax
                        
                                                        )
                                            ) 
                                )as demand_json*/
                            from water_consumer_demands 
                            where water_consumer_demands.status =true
                            group by consumer_tax_id
                            Order by demand_upto ASC
                        )demands on demands.consumer_tax_id = water_consumer_taxes.id
                        left join demand_currection_logs on demand_currection_logs.tax_id =  water_consumer_taxes.id
                        where demand_currection_logs.id is null AND water_consumer_taxes.created_on::date <='2024-02-19' 
                            --and water_consumer_taxes.charge_type !='Fixed'
                            --AND water_consumer_taxes.id = 179158
                            --AND water_consumer_taxes.consumer_id = 196
                        order by water_consumer_taxes.id ASC
                        --limit 2
            ";
            $data = $this->_DB->select($dataSql);
            print_var($this->_DB->select("select count(*) 
                                        from water_consumer_taxes 
                                        left join demand_currection_logs on demand_currection_logs.tax_id =  water_consumer_taxes.id
                                        where demand_currection_logs.id is null AND water_consumer_taxes.created_on::date <='2024-02-19'
                                        "));
            foreach ($data as $key => $val) {
                print_var("==============INDEX=>$key====================\n");
                print_var($val);

                $taxId =  $val->id;
                $consumerId = $val->consumer_id;
                $paidTotalAmount = $this->_DB->select("SELECT COALESCE(SUM(amount),0) AS paid_tax FROM water_trans WHERE water_trans.related_id = $consumerId AND status =1");
                $paidTotalAmount = $paidTotalAmount[0]->paid_tax ?? -1;
                $lastTran = WaterTran::where("related_id", $consumerId)->where("status", 1)->orderBy("id", 'DESC')->first();
                $demandUpto = $val->demand_upto;
                $finalRading = $val->final_reading;
                $demandIds = explode(',', $val->demand_ids);
                $oldTax = WaterConsumerTax::find($taxId);
                $consumerDetails = WaterSecondConsumer::find($consumerId);
                $userDetails  = User::find($val->emp_details_id);
                $lastMeterReading = (new WaterConsumerInitialMeter())->getmeterReadingAndDetails($consumerId)->orderByDesc('id')->first();
                $demand_currection_logs["tax_log"] = json_encode($oldTax->toArray(), JSON_UNESCAPED_UNICODE);
                $newDemands = [];
                $oldDemand  = [];
                $newCreatedDemand = [];
                $newRequst = new Request();
                $newRequst->merge(["consumerId" => $consumerId]);

                try {
                    $this->begin();
                    if ($val->charge_type != "Fixed" && $lastMeterReading) {
                        $lastMeterReading->status = 0;
                        $lastMeterReading->save();
                    }
                    $oldDemands = WaterConsumerDemand::whereIn("id", $demandIds)->orderby("demand_upto", 'ASC')->get();

                    $demand_currection_logs["demand_log"] = json_encode($oldDemands->toArray(), JSON_UNESCAPED_UNICODE);

                    $updates  = WaterConsumerDemand::whereIn("id", $demandIds)->update(['status' => false]);

                    $returnData = new WaterMonthelyCall($consumerId, $demandUpto, $finalRading);

                    $calculatedDemand = $returnData->parentFunction();

                    if ($calculatedDemand['status'] == false) {
                        throw new Exception($calculatedDemand['errors']);
                    }

                    $newTax = $calculatedDemand["consumer_tax"][0];

                    $oldTax->charge_type = $newTax["charge_type"];
                    $oldTax->effective_from = $newTax["effective_from"];
                    $oldTax->amount         = $newTax["amount"];
                    $oldTax->save();
                    $advance = 0;
                    $newTotalTax = 0;
                    print_var($newTax);
                    $n = collect();
                    foreach ($newTax["consumer_demand"] as $newDemands) {
                        $demands = WaterConsumerDemand::where("consumer_tax_id", $taxId)
                            ->where("consumer_id", $consumerId)
                            ->where("demand_from", $newDemands["demand_from"])
                            ->where("demand_upto", $newDemands["demand_upto"])
                            ->orderBy("id", "DESC")
                            ->first();
                        $newTotalTax +=  $newDemands["amount"];
                        if (!$demands) {
                            $mWaterConsumerDemand = new WaterConsumerDemand();
                            $refDemands = $newDemands;
                            $newDid = $mWaterConsumerDemand->saveConsumerDemand($refDemands, $consumerDetails, $newRequst, $taxId, $userDetails);
                            $f = ($mWaterConsumerDemand->find($newDid));
                            $f->generation_date = Carbon::parse($val->created_on)->format("Y-m-d");
                            $f->save();
                            $f->gen_type = "NEW";
                            $n->push($f->toArray());
                            $meterImag = new WaterMeterReadingDoc();
                            $OldmeterImag = WaterMeterReadingDoc::whereIn("demand_id", $demandIds)->orderBy("id", "DESC")->first();
                            if ($val->charge_type != "Fixed" && $OldmeterImag) {
                                $meterImag->demand_id = $newDid;
                                $meterImag->file_name = $OldmeterImag->file_name;
                                $meterImag->meter_no = $OldmeterImag->meter_no;
                                $meterImag->relative_path = $OldmeterImag->relative_path;
                                $meterImag->created_at = $OldmeterImag->created_at;
                                $meterImag->save();
                            }
                        } else {
                            $paidTotalTax = 0;
                            if ($demands->paid_status != 0) {
                                $paidTotalTax = $demands->amount - ($demands->due_balance_amount >= 0 ? $demands->due_balance_amount : 0);
                            }
                            if ($demands->amount > $newDemands["amount"]) {
                                $newAmount = $newDemands["amount"];
                                $newDue = $demands->paid_status == 0 ? $newDemands["amount"] : $newAmount - $demands->paid_total_tax;
                            } else {
                                $oldAmount = $demands->amount;
                                $diffAmount = $newDemands["amount"] - $oldAmount;

                                $newAmount = $newDemands["amount"];
                                $newDue = $demands->paid_status == 0 ? $newDemands["amount"] : $demands->due_balance_amount + $diffAmount;
                            }
                            if ($newDue < 0) {
                                $newDue = 0;
                            }

                            if ($newDue > 1) {
                                $demands->is_full_paid = false;
                            }
                            if ($paidTotalTax > $newAmount) {
                                $demands->is_full_paid = true;
                                $demands->paid_status = 1;
                                $newDue = 0;
                                $advance = $advance + $paidTotalTax;
                            }

                            $demands->amount = $newAmount;
                            $demands->balance_amount = $demands->amount;
                            $demands->current_demand = $newAmount;
                            $demands->due_balance_amount = $newDue;
                            $demands->due_current_demand = $newDue;
                            $demands->connection_type = $newDemands["connection_type"];
                            $demands->current_meter_reading = $newDemands["current_reading"] ?? null;
                            $demands->status = true;
                            $demands->save();
                            $demands->gen_type = "OLD";
                            $n->push($demands->toArray());
                        }
                    }
                    if ($val->charge_type != "Fixed" && $lastMeterReading) {
                        $lastMeterReading->status = 1;
                        $lastMeterReading->save();
                    }
                    if ($advance > 0) {
                        print_var("Advand=====>" . $advance);
                    }
                    $newUpdDemand = WaterConsumerDemand::where("consumer_id", $consumerId)->where("consumer_tax_id", $taxId)->where("status", true)->orderby("demand_upto", 'ASC')->get();
                    $allActiveDemands = WaterConsumerDemand::where("consumer_id", $consumerId)->where("status", true)->orderby("demand_upto", 'ASC')->get();
                    print_var($key);
                    print_var($val);
                    $excelData[$key]["status"] = "Success";
                    $demand_currection_logs["new_tax_log"] = json_encode($oldTax->toArray(), JSON_UNESCAPED_UNICODE);
                    $demand_currection_logs["new_demand_log"] = json_encode($n->toArray(), JSON_UNESCAPED_UNICODE);
                    $demand_currection_logs["tax_calculation_log"] = json_encode($calculatedDemand, JSON_UNESCAPED_UNICODE);
                    $newAdvand = $paidTotalAmount - collect($allActiveDemands)->sum("amount");
                    $is_full_paid = $newAdvand < 0 ? FALSE : TRUE;
                    $newAdvand = $newAdvand > 0 ? $newAdvand : 0;
                    print_var("newAdv=====>" . $newAdvand . "  %%%%%%%%% " . $paidTotalAmount - collect($allActiveDemands)->sum("amount"));
                    print_var("new Total tax=======>" . $newTotalTax . "++++++ full Tax=====>" . collect($allActiveDemands)->sum("amount"));
                    print_var("Paid Total tax=======>" . $paidTotalAmount);

                    if ($lastTran) {
                        $lastTran->due_amount = (collect($allActiveDemands)->sum("amount") - $paidTotalAmount) > 0 ?  (collect($allActiveDemands)->sum("amount") - $paidTotalAmount) : 0;
                        $lastTran->save();
                    }
                    print_var($n);
                    $new = collect($n->where("gen_type", "NEW"));
                    $newIds = (collect($new)->implode("id", ","));
                    $old = collect($n->where("gen_type", "<>", "NEW"));
                    $oldIds = (collect($old)->implode("id", ","));
                    if ($new->isNotEmpty() && $lastTran && $is_full_paid) {
                        foreach ($new as $newD) {
                            WaterConsumerDemand::where("id", $newD["id"])->update(["paid_status" => 1, "due_balance_amount" => 0, "is_full_paid" => true, "due_current_demand" => 0]);
                            $newTranDtls = new WaterTranDetail();
                            $newTranDtls->tran_id = $lastTran->id;
                            $newTranDtls->application_id = $newD["consumer_id"];
                            $newTranDtls->demand_id = $newD["id"];
                            $newTranDtls->total_demand = $newD["amount"];
                            $newTranDtls->paid_amount = $newD["amount"];
                            $newTranDtls->save();
                        }
                    }
                    $insertSql = "insert Into demand_currection_logs (tax_id ,consumer_id,old_demand_ids,new_demand_added_ids 
                                                                        " . ($is_full_paid ? (",is_full_paid") : "") . ",
                                                                        tax_log,new_tax_log,
                                                                        demand_log,new_demand_log,advance_amt,tax_calculation_log) 
                                values( $taxId,$consumerId,'$oldIds','$newIds' " . ($is_full_paid ? (",true") : "") . ",'" . $demand_currection_logs["tax_log"] . "','" . $demand_currection_logs["new_tax_log"] . "',
                                        '" . $demand_currection_logs["demand_log"] . "','" . $demand_currection_logs["new_demand_log"] . "',
                                        " . $newAdvand . ",'" . $demand_currection_logs["tax_calculation_log"] . "')";

                    $insertId = $this->_DB->select($insertSql);
                    $seql = "select count(*) from demand_currection_logs";
                    print_var("=============inserData======$newAdvand==========");
                    print_Var($this->_DB->select($seql));
                    $this->commit();
                    // dd($newTax);
                    print_var("Success");
                } catch (Exception $e) {
                    $this->rollback();
                    print_var("Fail");
                    //dd($e->getMessage(),$e->getFile(),$e->getLine());
                }
            }
        } catch (Exception $e) {
            $this->rollback();
            dd($e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    public function getConsumerDemandsHistory(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId' => 'required'
            ]
        );

        if ($validated->fails()) {
            return validationError($validated);
        }
        try {
        } catch (Exception $e) {
        }
    }

    public function gerateAutoFixedDemand(Request $request)
    {
        $excelData[] = [
            "consumer_id", "consumer No", "status", "error",
        ];
        try {
            $newRequest = new Request([
                "auth" => [
                    "id" => 18,
                    "user_name" => null,
                    "mobile" => null,
                    "email" => "stateadmin@gmail.com",
                    "email_verified_at" => null,
                    "user_type" => "Admin",
                    "ulb_id" => 2,
                    "suspended" => false,
                    "super_user" => null,
                    "description" => "asdf",
                    "workflow_participant" => false,
                    "photo_relative_path" => null,
                    "photo" => null,
                    "sign_relative_path" => null,
                    "signature" => null,
                    "old_ids" => null,
                    "name" => "STATE ADMIN",
                    "old_password" => null,
                    "user_code" => null,
                    "alternate_mobile" => null,
                    "address" => null,
                    "max_login_allow" => 10,
                ]
            ]);
            $sql = "
                    with consumers as (
                        select id ,consumer_no
                        from water_second_consumers
                        where status =1
                    ),
                    fixed_consumer as (
                        select water_consumer_meters.consumer_id,connection_type
                        from water_consumer_meters
                        where id in(
                            select max(water_consumer_meters.id) as max_id
                            from water_consumer_meters
                            join consumers on consumers.id = water_consumer_meters.consumer_id
                            where water_consumer_meters.status =1
                            group by consumers.id
                        )
                        AND connection_type =3
                    ),
                    last_demands as (
                        select water_consumer_demands.consumer_id,max(demand_upto) as demand_upto
                        from water_consumer_demands
                        left join fixed_consumer on fixed_consumer.consumer_id = water_consumer_demands.consumer_id
                        where status =true
                        group by water_consumer_demands.consumer_id
                    )
                    select *
                    from consumers
                    join fixed_consumer on fixed_consumer.consumer_id = consumers.id
                    left join last_demands on last_demands.consumer_id = consumers.id
                    where (last_demands.consumer_id is null 
                        or last_demands.demand_upto < (date_trunc('month',(date_trunc('month', now())- interval '1 month'))+ interval '1 month - 1 day')::date
                        )
                    order by demand_upto DESC
                    --limit 10
            ";
            $data = $this->_DB->select($sql);
            foreach ($data as $key => $val) {
                $consumerId = $val->id;
                $excelData[$key + 1] = [
                    "consumer_id" => $consumerId,
                    "consumer_no" => $val->consumer_no,
                    "status" => "Succes",
                    "error" => "",
                ];
                echo ("\n\n=============index($key [$consumerId])=========\n\n");
                $newRequest->merge(["consumerId" => $consumerId]);
                $this->begin();
                $respons = $this->saveGenerateConsumerDemand($newRequest);
                $respons = $respons->original;
                if (!$respons["status"]) {
                    $excelData[$key + 1]["status"] = "Fail";
                    $excelData[$key + 1]["error"] = $respons["message"];
                    $this->rollback();
                    continue;
                }
                $this->commit();
                echo ("\n================status(" . $excelData[$key + 1]["status"] . ")===================\n");
            }
            $fileName =  Carbon::now()->format("Y-m-d_H_i_s_A_") . "Fixed-consumer-Demand.xlsx";
            Excel::store(new DataExport($excelData), $fileName, "public");
            echo ("demand genrated=====>file====>" . $fileName);
        } catch (Exception $e) {
            $this->rollback();
            dd($e->getMessage());
        }
    }
}
