<?php

namespace App\Http\Controllers\Water;

use App\BLL\Water\WaterMonthelyCall;
use App\Http\Controllers\Controller;
use App\Http\Requests\Water\reqDeactivate;
use App\Http\Requests\Water\reqMeterEntry;
use App\Http\Requests\Water\newWaterRequest;
use App\MicroServices\DocUpload;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Masters\RefRequiredDocument;
use App\MicroServices\IdGeneration;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Payment\TempTransaction;
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
use Illuminate\Support\Facades\Validator;
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
                throw new Exception("consumer detail not found ");
            }

            # Get demand details 
            $refConsumerDemand = $mWaterConsumerDemand->getConsumerDemandV3($refConsumerId);
            if (!($refConsumerDemand->first())) {
                $consumerDemand['demandStatus'] = 0;                                    // Static / to represent existence of demand
                return responseMsgs(false, "consumer demands not found!", $consumerDemand, "", "01", responseTime(), $request->getMethod(), $request->deviceId);
            }

            $totalAdvanceAmt = $mWaterAdvance->getAdvanceAmt($refConsumerId);
            $totalAdjustmentAmt = $mWaterAdjustment->getAdjustmentAmt($refConsumerId);
            $remainAdvance = $totalAdvanceAmt->sum("amount") - $totalAdjustmentAmt->sum("amount");

            $refConsumerDemand = collect($refConsumerDemand)->sortBy('id')->values();
            $consumerDemand['consumerDemands'] = $refConsumerDemand;
            $checkParam = collect($consumerDemand['consumerDemands'])->first();

            # Check the details 
            if (isset($checkParam)) {
                $sumDemandAmount = collect($consumerDemand['consumerDemands'])->sum('due_balance_amount');
                $totalPenalty = collect($consumerDemand['consumerDemands'])->sum('due_penalty');
                $consumerDemand['totalSumDemand'] = round($sumDemandAmount, 2);
                $consumerDemand['totalPenalty'] = round($totalPenalty, 2);
                $consumerDemand['remainAdvance'] = round($remainAdvance ?? 0);
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
            $this->checkDemandGeneration($request, $consumerDetails);                                       // unfinished function

            # Calling BLL for call
            $returnData = new WaterMonthelyCall($request->consumerId, $request->demandUpto, $request->finalRading); #WaterSecondConsumer::get();
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
                return responseMsgs(true, "Demand Generated! for" . " " . $request->consumerId, "", "", "02", ".ms", "POST", "");
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

        $lastDemand = $mWaterConsumerDemand->akolaCheckConsumerDemand($refConsumerId)->first();
        if ($lastDemand) {

            // here we check the demand from date is present then do this or that
            if (!$lastDemand->demand_upto) {
                $startDate  = Carbon::parse($lastDemand->generation_date);
            } else {
                $refDemandUpto = Carbon::parse($lastDemand->demand_upto);
            }

            if ($refDemandUpto > $today) {
                throw new Exception("the demand is generated till" . "" . $lastDemand->demand_upto);
            }

            $startDate  = Carbon::parse($refDemandUpto);
            $uptoMonth  = $startDate;
            $todayMonth = $today;
            if ($uptoMonth->greaterThan($todayMonth)) {
                throw new Exception("demand should be generated generate in next month!");
            }
            $diffMonth = $startDate->diffInMonths($today);
            if ($diffMonth < 4) {
                throw new Exception("there should be a difference of 4  month!");
            }
        } else {
            // $uptoMonth  = $startDate;
            // $todayMonth = $today;
            // $diffMonth = $startDate->diffInMonths($today);
            // if ($diffMonth < 4) {
            //     throw new Exception("there should be a difference of 4  month!");
            // }
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
                        throw new Exception("Rading Should be Greater Than last Reading!");
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
                    throw new Exception("Please pay the old Demand Amount! as per rule to change fixed connection to meter!");
                }
                throw new Exception("Please apply for regularization as per rule 16 your connection shoul be in meter!");
            }
            # If there is previous meter detail exist
            $reqConnectionDate = $request->connectionDate;
            if (strtotime($consumerMeterDetails->connection_date) > strtotime($reqConnectionDate)) {
                throw new Exception("Connection Date should be grater than previous Connection date!");
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
                    throw new Exception("Can not update Connection Date, Demand already generated upto that month!");
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
                throw new Exception("invalid Meter Connection!");
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
                throw new Exception("You Can Meter/Fixed The Connection On Priviuse Meter");
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
    public function saveDocument($request, $refImageName)
    {
        $document       = $request->document;
        $docUpload      = new DocUpload;
        $relativePath   = Config::get('waterConstaint.WATER_RELATIVE_PATH');

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
    public function applyDeactivation(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'    => "required|digits_between:1,9223372036854775807",
                'ulbId'         => "nullable|",
                'reason'        => "required",
                'remarks'       => "required"
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $user                           = authUser($request);
            $refRequest                     = array();
            $ulbWorkflowObj                 = new WfWorkflow();
            $mWorkflowTrack                 = new WorkflowTrack();
            $mWaterSecondConsumer           = new waterSecondConsumer();
            $mWaterConsumerCharge           = new WaterConsumerCharge();
            $mWaterConsumerChargeCategory   = new WaterConsumerChargeCategory();
            $mWaterConsumerActiveRequest    = new WaterConsumerActiveRequest();
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
            $ulbWorkflowId = $ulbWorkflowObj->getulbWorkflowId($refWorkflow, $ulbId);
            if (!$ulbWorkflowId) {
                throw new Exception("Respective Ulb is not maped to Water Workflow!");
            }
            $refInitiatorRoleId = $this->getInitiatorId($ulbWorkflowId->id);
            $refFinisherRoleId  = $this->getFinisherId($ulbWorkflowId->id);
            $finisherRoleId     = DB::select($refFinisherRoleId);
            $initiatorRoleId    = DB::select($refInitiatorRoleId);
            if (!$finisherRoleId || !$initiatorRoleId) {
                throw new Exception("initiatorRoleId or finisherRoleId not found for respective Workflow!");
            }

            # If the user is not citizen
            if ($user->user_type != $refUserType['1']) {
                $request->request->add(['workflowId' => $refWorkflow]);
                $roleDetails = $this->getRole($request);
                if (!$roleDetails) {
                    throw new Exception("Role detail Not found!");
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
            $chargeAmount = $mWaterConsumerChargeCategory->getChargesByid($refConsumerCharges['WATER_DISCONNECTION']);
            $refChargeList = collect($refConsumerCharges)->flip();

            $refRequest["initiatorRoleId"]   = collect($initiatorRoleId)->first()->role_id;
            $refRequest["finisherRoleId"]    = collect($finisherRoleId)->first()->role_id;
            $refRequest["roleId"]            = $roleId ?? null;
            $refRequest["ulbWorkflowId"]     = $ulbWorkflowId->id;
            $refRequest["chargeCategoryId"]  = $refConsumerCharges['WATER_DISCONNECTION'];
            $refRequest["amount"]            = $chargeAmount->amount;
            $refRequest['userType']          = $user->user_type;

            $this->begin();
            $idGeneration       = new PrefixIdGenerator($refConParamId['WCD'], $ulbId);
            $applicationNo      = $idGeneration->generate();
            $applicationNo      = str_replace('/', '-', $applicationNo);
            $deactivatedDetails = $mWaterConsumerActiveRequest->saveRequestDetails($request, $refDetails['consumerDetails'], $refRequest, $applicationNo);
            $metaRequest = [
                'chargeAmount'      => $chargeAmount->amount,
                'amount'            => $chargeAmount->amount,
                'ruleSet'           => null,
                'chargeCategoryId'  => $refConsumerCharges['WATER_DISCONNECTION'],
                'relatedId'         => $deactivatedDetails['id'],
                'status'            => 2                                                    // Static
            ];
            $mWaterConsumerCharge->saveConsumerCharges($metaRequest, $request->consumerId, $refChargeList['2']);
            $mWaterSecondConsumer->dissconnetConsumer($request->consumerId, $metaRequest['status']);

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

    /**
     * | Check the condition before appling for deactivation
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
        $pendingDemand      = $mWaterConsumerDemand->getConsumerDemand($consumerId);
        $firstPendingDemand = collect($pendingDemand)->first();

        if (isset($firstPendingDemand)) {
            throw new Exception("There are unpaid pending demand!");
        }
        if (isset($request->ulbId) && $request->ulbId != $refConsumerDetails->ulb_id) {
            throw new Exception("ulb not matched according to consumer connection!");
        }
        // if ($refConsumerDetails->user_type == $refUserType['1'] && $user->id != $refConsumerDetails->user_id) {
        //     throw new Exception("You are not the autherised user who filled before the connection!");
        // }
        $activeReq = $mWaterConsumerActiveRequest->getRequestByConId($consumerId)->first();
        if ($activeReq) {
            throw new Exception("There are other request applied for respective consumer connection!");
        }
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
            $mWaterWaterConsumer        = new WaterWaterConsumer();
            $mActiveCitizenUndercare    = new ActiveCitizenUndercare();

            $connectionDetails = $mActiveCitizenUndercare->getDetailsByCitizenId();
            $checkDemand = collect($connectionDetails)->first();
            if (is_null($checkDemand))
                throw new Exception("Under taken data not found!");

            $consumerIds = collect($connectionDetails)->pluck('consumer_id');
            $consumerDetails = $mWaterWaterConsumer->getConsumerByIds($consumerIds)->get();
            $checkConsumer = collect($consumerDetails)->first();
            if (is_null($checkConsumer)) {
                throw new Exception("Consuemr Details Not Found!");
            }
            return responseMsgs(true, 'list of undertaken water connections!', remove_null($consumerDetails), "", "01", ".ms", "POST", $request->deviceId);
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
            throw new Exception("Consumer meter details not found maybe meter is not installed!");

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
                throw new Exception("uptoDate should not be grater than" . " " . $todayDate);
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
                throw new Exception("current uptoData should be grater than the previous uptoDate! and should have a month difference!");
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
            throw new Exception("please generate the demand on $formatEndDate or after it!");
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

    /**
     * update consumer details
     */
    public function updateConsumerDetails(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'consumerId'         => 'required|integer',
                'mobile_no'          => 'nullable|',
                'email'              => 'nullable|',
                'applicant_name'     => 'nullable|',
                'guardian_name'      => 'nullable|',
                'zoneId'             => 'nullable|',
                'wardId_number'      => 'nullable|integer',
                'address'            => 'nullable|',
                'property_no'        => 'nullable',
                'dtcCode'            => 'nullble',
                'oldConsumerNo'      => 'nullable'
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $now            = Carbon::now();
            $user           = authUser($request);
            $userId         = $user->id;
            $consumerId     = $request->consumerId;

            $mWaterSecondConsumer = new waterSecondConsumer();
            $mWaterConsumerOwners = new WaterConsumerOwner();
            $consumerDtls = $mWaterSecondConsumer->consumerDetails($consumerId)->first();
            if (!$consumerDtls) {
                throw new Exception("consumer details not found!");
            }

            $this->begin();
            $mWaterSecondConsumer->editConsumerdtls($request, $userId);
            $mWaterConsumerOwners->editConsumerOwnerDtls($request, $userId);
            $this->commit();
            return responseMsgs(true, "update consumer details succesfull!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            $this->rollback();
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
}
