<?php

namespace App\Http\Controllers\Property;

use App\BLL\Payment\ModuleRefUrl;
use App\BLL\Payment\PineLabPayment;
use App\BLL\Property\Akola\Calculate2PercPenalty;
use App\BLL\Property\Akola\GeneratePaymentReceipt;
use App\BLL\Property\Akola\GeneratePaymentReceiptV2;
use App\BLL\Property\Akola\GetHoldingDues;
use App\BLL\Property\Akola\GetHoldingDuesV2;
use App\BLL\Property\Akola\PostPropPayment;
use App\BLL\Property\Akola\PostPropPaymentV2;
use App\BLL\Property\Akola\RevCalculateByAmt;
use App\BLL\Property\PaymentReceiptHelper;
use App\BLL\Property\PostRazorPayPenaltyRebate;
use App\BLL\Property\YearlyDemandGeneration;
use App\EloquentClass\Property\PenaltyRebateCalculation;
use App\EloquentClass\Property\SafCalculation;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ThirdPartyController;
use App\Http\Requests\Property\ReqPayment;
use App\MicroServices\DocUpload;
use App\MicroServices\IdGeneration;
use App\Models\Cluster\Cluster;
use App\Models\Payment\TempTransaction;
use App\Models\Property\Logs\PropSmsLog;
use App\Models\Property\OldChequeTranEntery;
use App\Models\Property\PropAdjustment;
use App\Models\Property\PropAdvance;
use App\Models\Property\PropChequeDtl;
use App\Models\Property\PropDemand;
use App\Models\Property\PropIcicipayPayment;
use App\Models\Property\PropOwner;
use App\Models\Property\PropPenaltyrebate;
use App\Models\Property\PropPendingArrear;
use App\Models\Property\PropPinelabPayment;
use App\Models\Property\PropProperty;
use App\Models\Property\PropRazorpayPenalrebate;
use App\Models\Property\PropRazorpayRequest;
use App\Models\Property\PropRazorpayResponse;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsDemand;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\UlbMaster;
use App\Models\User;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfRoleusermap;
use App\Repository\Common\CommonFunction;
use App\Repository\Property\Interfaces\iSafRepository;
use App\Traits\Payment\Razorpay;
use App\Traits\Property\SAF;
use App\Traits\Property\SafDetailsTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HoldingTaxController extends Controller
{
    use SAF;
    use Razorpay;
    use SafDetailsTrait;
    protected $_propertyDetails;
    protected $_safRepo;
    protected $_holdingTaxInterest = 0;
    protected $_paramRentalRate;
    protected $_refParamRentalRate;
    protected $_carbon;
    protected $_COMMONFUNCTION;
    /**
     * | Created On-19/01/2023 
     * | Created By-Anshu Kumar
     * | Created for Holding Property Tax Demand and Receipt Generation
     * | Status-Closed
     */

    public function __construct(iSafRepository $safRepo)
    {
        $this->_safRepo = $safRepo;
        $this->_carbon = Carbon::now();
        $this->_COMMONFUNCTION = new CommonFunction();
    }
    /**
     * | Generate Holding Demand(1)
     */
    public function generateHoldingDemand(Request $req)
    {
        $req->validate([
            'propId' => 'required|numeric'
        ]);
        try {
            $yearlyDemandGeneration = new YearlyDemandGeneration;
            $responseDemand = $yearlyDemandGeneration->generateHoldingDemand($req);
            return responseMsgs(true, "Property Demand", remove_null($responseDemand), "011601", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), ['holdingNo' => $yearlyDemandGeneration->_propertyDetails['holding_no']], "011601", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Read the Calculation From Date (1.1)
     */
    public function generateCalculationParams($propertyId, $propDetails)
    {
        $mPropDemand = new PropDemand();
        $mSafDemand = new PropSafsDemand();
        $safId = $this->_propertyDetails->saf_id;
        $todayDate = Carbon::now();
        $propDemand = $mPropDemand->readLastDemandDateByPropId($propertyId);
        if (!$propDemand) {
            $propDemand = $mSafDemand->readLastDemandDateBySafId($safId);
            if (!$propDemand)
                throw new Exception("Last Demand is Not Available for this Property");
        }
        $lastPayDate = $propDemand->due_date;
        if (Carbon::parse($lastPayDate) > $todayDate)
            throw new Exception("No Dues For This Property");
        $payFrom = Carbon::parse($lastPayDate)->addDay(1);
        $payFrom = $payFrom->format('Y-m-d');

        $realFloor = collect($propDetails['floor'])->map(function ($floor) use ($payFrom) {
            $floor['dateFrom'] = $payFrom;
            return $floor;
        });

        $propDetails['floor'] = $realFloor->toArray();
        return $propDetails;
    }

    /**
     * | Get Holding Dues( 2) 
     */
    public function getHoldingDues(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['propId' => 'required']
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 200);
        }

        try {
            $user = Auth()->user();
            $usertype = $this->_COMMONFUNCTION->getUserAllRoles();
            $testRole = collect($usertype)->whereIn("sort_name", Config::get("TradeConstant.CANE-CUTE-PAYMENT"));
            // $getHoldingDues = new GetHoldingDues;
            $getHoldingDues = new GetHoldingDuesV2;
            $demand = $getHoldingDues->getDues($req);
            $demand["can_take_payment"] = collect($testRole)->isNotEmpty() ? true : false;
            if ($this->_COMMONFUNCTION->checkUsersWithtocken("active_citizens")) {
                $data["can_take_payment"] =  $demand["payableAmt"] > 0 ? true : false;
            }
            return responseMsgs(true, "Demand Details", remove_null($demand), "011602", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), ['basicDetails' => $basicDtls ?? []], "011602", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }



    /**
     * | Generate Referal Url
     */
    public function getReferalUrl(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['propId' => 'required|integer']
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }
        // Initializations
        $moduleRefUrl = new ModuleRefUrl;
        $mPropIciciPayPayments = new PropIcicipayPayment();
        try {
            $holdingDues = $this->getHoldingDues($req);

            if ($holdingDues->original['status'] == false)
                throw new Exception($holdingDues->original['message']);

            if ($holdingDues->original['data']['paymentStatus'])
                throw new Exception("Payment Already Done");

            $holdingDues = $holdingDues->original['data'];
            $payableAmount = $holdingDues['payableAmt'];
            $basicDetails = $holdingDues['basicDetails'];

            $refReq = new Request([
                'userId' => $basicDetails['citizen_id'] ?? $basicDetails['user_id'],
                'amount' => $payableAmount,
                'applicationId' => $req->propId,
                'moduleId' => 1                             // Property Module Id
            ]);

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            $moduleRefUrl->getReferalUrl($refReq);
            $refurl = $moduleRefUrl->_refUrl;
            // Table maintain for particular module 
            $propIciciReqs = [
                "req_ref_no" => $moduleRefUrl->_refNo,
                "prop_id" => $req->propId,
                "tran_type" => 'Property',
                "from_fyear" => collect($holdingDues['demandList'])->first()['fyear'],
                "to_fyear" => collect($holdingDues['demandList'])->last()['fyear'],
                "demand_amt" => $holdingDues['grandTaxes']['balance'],
                "ulb_id" => 2,
                "ip_address" => $req->ipAddress ?? getClientIpAddress(),
                "demand_list" => json_encode($holdingDues, true),
                "payable_amount" => $holdingDues['payableAmt'],
                "arrear_settled" => $holdingDues['arrear']
            ];

            $mPropIciciPayPayments->create($propIciciReqs);
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "", $refurl);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), []);
        }
    }


    /**
     * | Generate Bill Reference No
     */
    public function generateBillRefNo(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'propId' => 'required|integer',
                'paymentType' => 'required|In:isFullPayment,isArrearPayment,isPartPayment',
                'paidAmount' => 'nullable|required_if:paymentType,==,isPartPayment|integer',
                'paymentMode' => 'required|string'
            ]
        );

        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ]);
        }


        $pineLabPayment = new PineLabPayment;
        $mPropPinelabPayment = new PropPinelabPayment();

        try {

            if ($req->paymentType == 'isFullPayment')
                $req->merge(['isArrear' => false]);
            elseif ($req->paymentType == 'isArrearPayment')
                $req->merge(['isArrear' => true]);
            else
                $req->merge(['isArrear' => false]);

            $propCalReq = new Request([                                                 // Request from payment
                'propId' => $req['propId'],
                'isArrear' => $req['isArrear'] ?? null
            ]);

            $holdingDues = $this->getHoldingDues($propCalReq);
            if ($holdingDues->original['status'] == false)
                throw new Exception($holdingDues->original['message']);

            if ($holdingDues->original['data']['paymentStatus'])
                throw new Exception("Payment Already Done");

            $holdingDues = $holdingDues->original['data'];
            $payableAmount = ($req->paymentType == "isPartPayment") ? $req->paidAmount : $holdingDues['payableAmt'];

            $demands = $holdingDues['demandList'];
            $arrear = $holdingDues['arrear'];

            if (collect($demands)->isEmpty() && $arrear > 0) {
                $arrearDate = Carbon::now()->addYear(-1)->format('Y-m-d');
                $arrearFyear = getFY($arrearDate);
                $fromFyear = $arrearFyear;
                $uptoFyear = $arrearFyear;
            }

            $pineLabParams = (object)[
                "workflowId"    => 0,
                "amount"        => $payableAmount,
                "moduleId"      => 1,
                "applicationId" => $req->propId,
                "paymentType" => "Property"
            ];

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            $refNo = $pineLabPayment->initiatePayment($pineLabParams);
            // Table maintain for particular module 
            $pineReqs = [
                "bill_ref_no" => $refNo,
                "payment_mode" => $req->paymentMode,
                "prop_id" => $req->propId,
                "tran_type" => 'Property',
                "from_fyear" => collect($holdingDues['demandList'])->first()['fyear'] ?? $fromFyear,
                "to_fyear" => collect($holdingDues['demandList'])->last()['fyear'] ?? $uptoFyear,
                "demand_amt" => $holdingDues['grandTaxes']['balance'],
                "ulb_id" => 2,
                "ip_address" => $req->ipAddress ?? getClientIpAddress(),
                "demand_list" => json_encode($holdingDues, true),
                "payable_amount" => $holdingDues['payableAmt'],
                "arrear_settled" => $holdingDues['arrear'],
                "payment_type" => $req->paymentType,
                "paid_amount" => $req->paidAmount
            ];

            $mPropPinelabPayment->create($pineReqs);
            DB::commit();
            DB::connection('pgsql_master')->commit();
            return responseMsgs(true, "Bill id is", ['billRefNo' => $refNo], "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "1.0", responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Payment Holding (Case for Online Payment)
     */
    public function paymentHolding(Request $req)
    {
        try {
            $billRefNo = $req->billRefNo;
            $mPropPinelabPayment = new PropPinelabPayment();
            $paymentReqs = $mPropPinelabPayment->getPaymentByBillRefNo($billRefNo);
            if (collect($paymentReqs)->isEmpty())
                throw new Exception("Payment Request Not available");
            $req->merge(
                [
                    'paymentMode' => $paymentReqs->payment_mode,
                    'paymentType' => $paymentReqs->payment_type,
                    'paidAmount' => $paymentReqs->paid_amount
                ]
            );                                                              // Add Payment Mode by the table
            $postPropPayment = new PostPropPaymentV2($req);
            $demandList = json_decode($paymentReqs->demand_list, true);
            $demandList = responseMsgs(true, "Demand Details", $demandList);
            $demandList->original['data'] = (array)$demandList->original['data'];
            $demandList->original['data']['grandTaxes'] = (array)$demandList->original['data']['grandTaxes'];

            $postPropPayment->_propCalculation = $demandList;
            $postPropPayment->postPayment();
            DB::commit();
            return [
                'tran_id' => $postPropPayment->_tranId
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * | Offline Payment Holding for (Cheque, Cash, DD and Neft)
     */
    public function offlinePaymentHolding(ReqPayment $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['isArrear' => 'required|bool']
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 401);
        }
        // $postPropPayment = new PostPropPayment($req);
        $postPropPayment = new PostPropPaymentV2($req);

        if ($req->isArrear == false)
            $req->merge(['paymentType' => 'isArrearPayment']);
        else
            $req->merge(['paymentType' => 'isFullPayment']);

        $propCalReq = new Request([                                                 // Request from payment
            'propId' => $req['id'],
            'isArrear' => $req['isArrear'] ?? null
        ]);

        $propCalReq = new Request([                                                 // Request from payment
            'propId' => $req['id'],
            'isArrear' => $req['isArrear'] ?? null
        ]);

        try {
            $propCalculation = $this->getHoldingDues($propCalReq);                    // Calculation of Holding
            if ($propCalculation->original['status'] == false)
                throw new Exception($propCalculation->original['message']);

            $postPropPayment->_propCalculation = $propCalculation;
            // Transaction is beginning in Prop Payment Class
            $postPropPayment->postPayment();
            DB::commit();
            return responseMsgs(true, "Payment Successfully Done", ['TransactionNo' => $postPropPayment->_tranNo, 'transactionId' => $postPropPayment->_tranId], "011604", "1.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "011604", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }


    /**
     * | Offline Payment Saf Version 2
     */
    public function offlinePaymentHoldingV2(ReqPayment $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'paymentType' => 'required|In:isFullPayment,isArrearPayment,isPartPayment',
                'paidAmount' => 'nullable|required_if:paymentType,==,isPartPayment|numeric'
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 401);
        }
        $mPropOwner = new PropOwner();
        $ThirdPartyController = new ThirdPartyController();
        $postPropPayment = new PostPropPaymentV2($req);

        if ($req->paymentType == 'isFullPayment')
            $req->merge(['isArrear' => false]);
        elseif ($req->paymentType == 'isArrearPayment')
            $req->merge(['isArrear' => true]);
        else
            $req->merge(['isArrear' => false]);

        $propCalReq = new Request([                                                 // Request from payment
            'propId' => $req['id'],
            'isArrear' => $req['isArrear'] ?? null
        ]);

        try {
            $propCalculation = $this->getHoldingDues($propCalReq);                    // Calculation of Holding
            if ($propCalculation->original['status'] == false)
                throw new Exception($propCalculation->original['message']);

            $postPropPayment->_propCalculation = $propCalculation;
            // Transaction is beginning in Prop Payment Class
            $postPropPayment->postPayment();
            DB::commit();

            return responseMsgs(true, "Payment Successfully Done", ['TransactionNo' => $postPropPayment->_tranNo, 'transactionId' => $postPropPayment->_tranId], "011604", "2.0", responseTime(), "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "011604", "2.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Post Other Payment Modes for Cheque,DD,Neft
     */
    public function postOtherPaymentModes($req)
    {
        $cash = Config::get('payment-constants.PAYMENT_MODE.3');
        $moduleId = Config::get('module-constants.PROPERTY_MODULE_ID');
        $mTempTransaction = new TempTransaction();
        if ($req['paymentMode'] != $cash) {
            $mPropChequeDtl = new PropChequeDtl();
            $chequeReqs = [
                'user_id' => $req['userId'],
                'prop_id' => $req['id'],
                'transaction_id' => $req['tranId'],
                'cheque_date' => $req['chequeDate'],
                'bank_name' => $req['bankName'],
                'branch_name' => $req['branchName'],
                'cheque_no' => $req['chequeNo']
            ];
            $mPropChequeDtl->postChequeDtl($chequeReqs);
        }

        $tranReqs = [
            'transaction_id' => $req['tranId'],
            'application_id' => $req['id'],
            'module_id' => $moduleId,
            'workflow_id' => 0,
            'transaction_no' => $req['tranNo'],
            'application_no' => $req->applicationNo,
            'amount' => $req['amount'],
            'payment_mode' => $req['paymentMode'],
            'cheque_dd_no' => $req['chequeNo'],
            'bank_name' => $req['bankName'],
            'tran_date' => $req['todayDate'],
            'user_id' => $req['userId'],
            'ulb_id' => $req['ulbId'],
            // 'cluster_id' => $clusterId
        ];
        $mTempTransaction->tempTransaction($tranReqs);
    }

    /**
     * | Legacy Payment Holding
     */
    public function legacyPaymentHolding(ReqPayment $req)
    {
        $req->validate([
            'document' => 'required|mimes:pdf,jpeg,png,jpg'
        ]);
        try {
            $mPropDemand = new PropDemand();
            $mPropProperty = new PropProperty();
            $propWfId = Config::get('workflow-constants.PROPERTY_WORKFLOW_ID');
            $docUpload = new DocUpload;
            $refImageName = "LEGACY_PAYMENT";
            $propModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $relativePath = Config::get('PropertyConstaint.SAF_RELATIVE_PATH');
            $mWfActiveDocument = new WfActiveDocument();

            $propCalReq = new Request([
                'propId' => $req['id'],
                'fYear' => $req['fYear'],
                'qtr' => $req['qtr']
            ]);

            $properties = $mPropProperty::findOrFail($req['id']);
            $propCalculation = $this->getHoldingDues($propCalReq);
            if ($propCalculation->original['status'] == false)
                throw new Exception($propCalculation->original['message']);

            // Image Upload
            $imageName = $docUpload->upload($refImageName, $req->document, $relativePath);
            $demands = $propCalculation->original['data']['demandList'];

            $wfActiveDocReqs = [
                'active_id' => $req['id'],
                'workflow_id' => $propWfId,
                'ulb_id' => $properties->ulb_id,
                'module_id' => $propModuleId,
                'doc_code' => $refImageName,
                'relative_path' => $relativePath,
                'document' => $imageName,
                'uploaded_by' => authUser($req)->id ?? auth()->user()->id,
                'uploaded_by_type' => authUser($req)->user_type ?? auth()->user()->user_type,
                'doc_category' => $refImageName,
            ];
            DB::beginTransaction();
            $mWfActiveDocument->create($wfActiveDocReqs);
            foreach ($demands as $demand) {
                $tblDemand = $mPropDemand->getDemandById($demand['id']);
                $tblDemand->paid_status = 9;
                $tblDemand->adjust_type = "Legacy Payment Adjustment";
                $tblDemand->save();
            }
            DB::commit();
            return responseMsgs(true, "Payment Successfully Done", ['TransactionNo' => ""], "011604", "1.0", "", "POST", $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "011604", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Generate Payment Receipt(9.1)
     */
    public function propPaymentReceipt(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'tranNo' => 'nullable|string',
                'tranId' => 'nullable|integer'
            ]
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 401);
        }

        try {
            // $generatePaymentReceipt = new GeneratePaymentReceipt;
            $generatePaymentReceipt = new GeneratePaymentReceiptV2;                     // Version 2 Receipt
            $generatePaymentReceipt->generateReceipt($req->tranNo, $req->tranId);
            $receipt = $generatePaymentReceipt->_GRID;
            return responseMsgs(true, "Payment Receipt", remove_null($receipt), "011605", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011605", "1.0", "", "POST", $req->deviceId);
        }
    }

    /**
     * | Property Payment History
     */
    public function propPaymentHistory(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['propId' => 'required|digits_between:1,9223372036854775807']
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $mPropTrans = new PropTransaction();
            $mPropProperty = new PropProperty();

            $transactions = array();

            $propertyDtls = $mPropProperty->getSafByPropId($req->propId);
            if (!$propertyDtls)
                throw new Exception("Property Not Found");

            $propTrans = $mPropTrans->getPropTransactions($req->propId, 'property_id');         // Holding Payment History
            if (!$propTrans || $propTrans->isEmpty())
                throw new Exception("No Transaction Found");

            $propTrans->map(function ($propTran) {
                $propTran['tran_date'] = Carbon::parse($propTran->tran_date)->format('d-m-Y');
            });

            $propSafId = $propertyDtls->saf_id;

            if (is_null($propSafId))
                $safTrans = array();
            else {
                $safTrans = $mPropTrans->getPropTransactions($propSafId, 'saf_id');                 // Saf payment History
                $safTrans->map(function ($safTran) {
                    $safTran['tran_date'] = Carbon::parse($safTran->tran_date)->format('d-m-Y');
                });
            }

            $transactions['Holding'] = collect($propTrans)->sortByDesc('id')->values();
            $transactions['Saf'] = collect($safTrans)->sortByDesc('id')->values();

            return responseMsgs(true, "", remove_null($transactions), "011606", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011606", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Generate Ulb Payment Receipt
     */
    public function proUlbReceipt(Request $req)
    {
        $req->validate([
            'tranNo' => 'required'
        ]);

        try {
            $mTransaction = new PropTransaction();
            $propTrans = $mTransaction->getPropTransFullDtlsByTranNo($req->tranNo);
            $responseData = $this->propPaymentReceipt($req);
            if ($responseData->original['status'] == false)
                return $responseData;
            $responseData = $responseData->original['data'];                                              // Function propPaymentReceipt(9.1)
            $totalRebate = $responseData['totalRebate'];
            $holdingTaxDetails = $this->holdingTaxDetails($propTrans, $totalRebate);                    // (9.2)
            $holdingTaxDetails = collect($holdingTaxDetails)->where('amount', '>', 0)->values();
            $responseData['holdingTaxDetails'] = $holdingTaxDetails;
            return responseMsgs(true, "Payment Receipt", remove_null($responseData), "011609", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011609", "1.0", "", "POST", $req->deviceId);
        }
    }

    /**
     * | Get Holding Tax Details On RMC Receipt (9.2)
     */
    public function holdingTaxDetails($propTrans, $totalRebate)
    {
        $transactions = collect($propTrans);
        $tranDate = $transactions->first()->tran_date;
        $paidFinYear = calculateFYear($tranDate);
        $currentTaxes = $transactions->where('fyear', $paidFinYear)->values();
        $arrearTaxes = $transactions->where('fyear', '!=', $paidFinYear)->values();

        $arrearFromQtr = $arrearTaxes->first()->qtr ?? "";
        $arrearFromFyear = $arrearTaxes->first()->fyear ?? "";
        $arrearToQtr = $arrearTaxes->last()->qtr ?? "";
        $arrearToFyear = $arrearTaxes->last()->fyear ?? "";

        $currentFromQtr = $currentTaxes->first()->qtr ?? "";
        $currentFromFyear = $currentTaxes->first()->fyear ?? "";
        $currentToQtr = $currentTaxes->last()->qtr ?? "";
        $currentToFyear = $currentTaxes->last()->fyear ?? "";

        $arrearPeriod = $arrearFromQtr . '/' . $arrearFromFyear . '-' . $arrearToQtr . '/' . $arrearToFyear;
        $currentPeriod = $currentFromQtr . '/' . $currentFromFyear . '-' . $currentToQtr . '/' . $currentToFyear;
        return [
            [
                // 'codeOfAmount' => '1100100A',
                'description' => 'Holding Tax Arrear',
                'period' =>  $arrearPeriod,
                'amount' => roundFigure($arrearTaxes->sum('holding_tax')),
            ],
            [
                // 'codeOfAmount' => '1100100C',
                'description' => 'Holding Tax Current',
                'period' => $currentPeriod,
                'amount' => roundFigure($currentTaxes->sum('holding_tax')),
            ],
            [
                // 'codeOfAmount' => '1100200A',
                'description' => 'Water Tax Arrear',
                'period' =>  $arrearPeriod,
                'amount' =>  roundFigure($arrearTaxes->sum('water_tax')),
            ],
            [
                // 'codeOfAmount' => '1100200C',
                'description' => 'Water Tax Current',
                'period' => $currentPeriod,
                'amount' => roundFigure($currentTaxes->sum('water_tax')),
            ],
            [
                // 'codeOfAmount' => '1100400A',
                'description' => 'Conservancy Tax Arrear',
                'period' =>  $arrearPeriod,
                'amount' =>  roundFigure($arrearTaxes->sum('latrine_tax')),
            ],
            [
                // 'codeOfAmount' => '1100400C',
                'description' => 'Conservancy Tax Current',
                'period' => $currentPeriod,
                'amount' => roundFigure($currentTaxes->sum('latrine_tax')),
            ],
            [
                // 'codeOfAmount' => '1105201A',
                'description' => 'Education Cess Arrear',
                'period' =>  $arrearPeriod,
                'amount' =>  roundFigure($arrearTaxes->sum('education_cess')),
            ],
            [
                // 'codeOfAmount' => '1105201A',
                'description' => 'Education Cess Current',
                'period' => $currentPeriod,
                'amount' => roundFigure($currentTaxes->sum('education_cess')),
            ],
            [
                // 'codeOfAmount' => '1105203A',
                'description' => 'Health Cess Arrear',
                'period' =>   $arrearPeriod,
                'amount' => roundFigure($arrearTaxes->sum('health_cess')),
            ],
            [
                // 'codeOfAmount' => '1105203C',
                'description' => 'Health Cess Current',
                'period' => $currentPeriod,
                'amount' => roundFigure($currentTaxes->sum('health_cess')),
            ]
        ];
    }

    /**
     * | Property Comparative Demand(16)
     */
    public function comparativeDemand(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'propId' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return responseMsgs(false, $validator->errors(), "", "011610", "1.0", "", "POST", $req->deviceId ?? "");
        }

        try {
            // Variable Assignments
            $comparativeDemand = array();
            $comparativeDemand['arvRule'] = array();
            $comparativeDemand['cvRule'] = array();
            $propId = $req->propId;
            $safCalculation = new SafCalculation;
            $mPropProperty = new PropProperty();
            $floorTypes = Config::get('PropertyConstaint.FLOOR-TYPE');
            $effectDateRuleset2 = Config::get('PropertyConstaint.EFFECTIVE_DATE_RULE2');
            $effectDateRuleset3 = Config::get('PropertyConstaint.EFFECTIVE_DATE_RULE3');
            $mUlbMasters = new UlbMaster();
            // Derivative Assignments
            $fullDetails = $mPropProperty->getComparativeBasicDtls($propId);             // Full Details of the Floor
            $ulbId = $fullDetails[0]->ulb_id;
            if (collect($fullDetails)->isEmpty())
                throw new Exception("No Property Found");
            $basicDetails = collect($fullDetails)->first();
            $safCalculation->_redis = Redis::connection();
            $safCalculation->_rentalRates = $safCalculation->calculateRentalRates();
            $safCalculation->_effectiveDateRule2 = $effectDateRuleset2;
            $safCalculation->_effectiveDateRule3 = $effectDateRuleset3;
            $safCalculation->_multiFactors = $safCalculation->readMultiFactor();        // Get Multi Factors List
            $safCalculation->_propertyDetails['roadType'] = $basicDetails->road_width;
            $safCalculation->_propertyDetails['propertyType'] = $basicDetails->prop_type_mstr_id;
            $safCalculation->_readRoadType[$effectDateRuleset2] = $safCalculation->readRoadType($effectDateRuleset2);
            $safCalculation->_readRoadType[$effectDateRuleset3] = $safCalculation->readRoadType($effectDateRuleset3);
            $safCalculation->_ulbId = $basicDetails->ulb_id;
            $safCalculation->_wardNo = $basicDetails->old_ward_no;
            $safCalculation->readParamRentalRate();
            $safCalculation->_point20TaxedUsageTypes = Config::get('PropertyConstaint.POINT20-TAXED-COMM-USAGE-TYPES'); // The Type of Commercial Usage Types which have taxes 0.20 Perc


            if (!is_null($basicDetails->floor_id))                                          // If The Property Have Floors
            {
                $floors = array();
                foreach ($fullDetails as $detail) {
                    array_push($floors, [
                        'floorMstrId' => $detail->floor_mstr_id,
                        'buildupArea' => $detail->builtup_area,
                        'useType' => $detail->usage_type_mstr_id,
                        'constructionType' => $detail->const_type_mstr_id,
                        'carpetArea' => $detail->carpet_area,
                        'occupancyType' => $detail->occupancy_type_mstr_id,
                    ]);
                }
                $safCalculation->_floors = $floors;
                $capitalvalueRates = $safCalculation->readCapitalValueRate();
                foreach ($fullDetails as $key => $detail) {
                    $floorMstrId = $detail->floor_mstr_id;
                    $floorBuiltupArea = $detail->builtup_area;
                    $floorUsageType = $detail->usage_type_mstr_id;
                    $floorConstType = $detail->const_type_mstr_id;
                    $floorCarpetArea = $detail->carpet_area;
                    $floorFromDate = $detail->date_from;
                    $floorOccupancyType = $detail->occupancy_type_mstr_id;
                    $safCalculation->_floors[$floorMstrId]['useType'] = $floorUsageType;
                    $safCalculation->_floors[$floorMstrId]['buildupArea'] = $floorBuiltupArea;
                    $safCalculation->_floors[$floorMstrId]['carpetArea'] = $floorCarpetArea;
                    $safCalculation->_floors[$floorMstrId]['occupancyType'] = $floorOccupancyType;
                    $safCalculation->_floors[$floorMstrId]['constructionType'] = $floorConstType;
                    $safCalculation->_capitalValueRate[$floorMstrId] = $capitalvalueRates[$key];
                    $rules = $this->generateFloorComparativeDemand($floorFromDate, $floorTypes, $floorMstrId, $safCalculation);  // 16.1
                    array_push($comparativeDemand['arvRule'], $rules['arvRule']);
                    array_push($comparativeDemand['cvRule'], $rules['cvRule']);
                }
            }

            // Check Other Demands
            $otherDemands = $this->generateOtherDemands($basicDetails, $safCalculation);
            // Include other Demands
            $comparativeDemand['arvRule'] = array_merge($comparativeDemand['arvRule'], $otherDemands['arvRule']);
            $comparativeDemand['cvRule'] = array_merge($comparativeDemand['cvRule'], $otherDemands['cvRule']);

            $arvRule = $comparativeDemand['arvRule'];
            $cvRule = $comparativeDemand['cvRule'];
            $comparativeDemand['total'] = [
                'arvTotalPropTax' => roundFigure((float)collect($arvRule)->sum('arvTotalPropTax') ?? 0 + (float)collect($cvRule)->sum('arvTotalPropTax') ?? 0),
                'cvTotalPropTax' => roundFigure((float)collect($arvRule)->sum('cvArvPropTax') + (float)collect($cvRule)->sum('cvArvPropTax') ?? 0),
            ];
            $comparativeDemand['basicDetails'] = array_merge((array)$basicDetails, [
                'todayDate' => $this->_carbon->format('d-m-Y')
            ]);
            // Ulb Details
            $comparativeDemand['ulbDetails'] = $mUlbMasters->getUlbDetails($ulbId);
            return responseMsgs(true, "Comparative Demand", remove_null($comparativeDemand), "011610", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011610", "1.0", "", "POST", $req->deviceId);
        }
    }

    /**
     * | Generate Comparative Demand(16.1)
     */
    public function generateFloorComparativeDemand($floorFromDate, $floorTypes, $floorMstrId, $safCalculation, $onePercPenalty = 0)
    {
        if ($floorFromDate < $safCalculation->_effectiveDateRule3) {
            $rule2 = $safCalculation->calculateRuleSet2($floorMstrId, $onePercPenalty, $floorFromDate);
            $rule2 = array_merge(
                $rule2,
                ['circleRate' => ""],
                ['taxPerc' => ""],
                ['calculationFactor' => ""],
                ['matrixFactor' => $rule2['rentalRate']],
                ['cvArvPropTax' => 0],
                ['arvPsf' => $rule2['arv']],
                ['floorMstr' => $floorMstrId],
                ['floor' => $floorTypes[$floorMstrId]],
                ['ruleApplied' => 'Arv Rule']
            );
            $setRule2 = $this->responseDemand($rule2);          // Function (16.1)
        }

        $rule3 = $safCalculation->calculateRuleSet3($floorMstrId, $onePercPenalty, $floorFromDate);
        $rule3 = array_merge(
            $rule3,
            ['arvTotalPropTax' => 0],
            ['multiFactor' => ""],
            ['carpetArea' => ""],
            ['cvArvPropTax' => $rule3['arv']],
            ['arvPsf' => ""],
            ['floorMstr' => $floorMstrId],
            ['floor' => $floorTypes[$floorMstrId]],
            ['ruleApplied' => 'CV Rule'],
            ['rentalRate' => $rule3['matrixFactor']],       // Function (16.1)
        );
        $setRule3 = $this->responseDemand($rule3);
        return [
            'arvRule' => $setRule2 ?? [],
            'cvRule' => $setRule3 ?? []
        ];
    }

    /**
     * | response demands(16.1)
     */
    public function responseDemand($rule)
    {
        return [
            "floor" => $rule['floor'],
            "buildupArea" => $rule['buildupArea'],
            "usageFactor" => $rule['usageFactor'] ?? null,
            "occupancyFactor" => $rule['occupancyFactor'],
            "carpetArea" => $rule['carpetArea'] ?? null,
            "rentalRate" => $rule['rentalRate'],
            "taxPerc" => $rule['taxPerc'] ?? null,
            "calculationFactor" => $rule['calculationFactor'] ?? null,
            "arvPsf" => $rule['arvPsf'] ?? null,
            "circleRate" => $rule['circleRate'] ?? "",
            "arvTotalPropTax" => roundFigure($rule['arvTotalPropTax'] ?? 0),
            "cvArvPropTax" => roundFigure($rule['cvArvPropTax'] ?? 0)
        ];
    }

    /**
     * | Get Floor Demand (16.2)
     */
    public function generateOtherDemands($basicDetails, $safCalculation)
    {
        $onePercPenalty = 0;
        $array['arvRule'] = array();
        $array['cvRule'] = array();
        $safCalculation->_capitalValueRateMPH = $safCalculation->readCapitalValueRateMHP();
        // Mobile Tower
        if ($basicDetails->is_mobile_tower == true) {
            $safCalculation->_mobileTowerArea = $basicDetails->tower_area;
            if ($basicDetails->tower_installation_date < $safCalculation->_effectiveDateRule2) {
                $rule2 = $safCalculation->calculateRuleSet2("mobileTower", $onePercPenalty);
                $rule2['floor'] = "mobileTower";
                $rule2['usageFactor'] = $rule2['multiFactor'];
                $rule2['arvPsf'] = $rule2['arv'];
                array_push($array['arvRule'], $this->responseDemand($rule2)); // (16.1)
            }

            $rule3 = $safCalculation->calculateRuleSet3("mobileTower", $onePercPenalty);
            $rule3['floor'] = "mobileTower";
            $rule3['rentalRate'] = $rule3['matrixFactor'];
            $rule3['cvArvPropTax'] = $rule3['arv'];
            array_push($array['cvRule'], $this->responseDemand($rule3));
        }
        // Hoarding Board
        if ($basicDetails->is_hoarding_board == true) {
            $safCalculation->_hoardingBoard['area'] = $basicDetails->hoarding_area;
            if ($basicDetails->hoarding_installation_date < $safCalculation->_effectiveDateRule2) {
                $rule2 = $safCalculation->calculateRuleSet2("hoardingBoard", $onePercPenalty);
                $rule2['floor'] = "hoardingBoard";
                $rule2['usageFactor'] = $rule2['multiFactor'];
                $rule2['arvPsf'] = $rule2['arv'];
                array_push($array['arvRule'], $this->responseDemand($rule2)); // (16.1)
            }

            $rule3 = $safCalculation->calculateRuleSet3("hoardingBoard", $onePercPenalty);
            $rule3['floor'] = "hoardingBoard";
            $rule3['rentalRate'] = $rule3['matrixFactor'];
            $rule3['cvArvPropTax'] = $rule3['arv'];
            array_push($array['cvRule'], $this->responseDemand($rule3)); // (16.1)
        }
        // Petrol Pump
        if ($basicDetails->is_petrol_pump == true) {
            $safCalculation->_petrolPump['area'] = $basicDetails->under_ground_area;
            if ($basicDetails->petrol_pump_completion_date < $safCalculation->_effectiveDateRule2) {
                $rule2 = $safCalculation->calculateRuleSet2("petrolPump", $onePercPenalty);
                $rule2['floor'] = "petrolPump";
                $rule2['usageFactor'] = $rule2['multiFactor'];
                $rule2['arvPsf'] = $rule2['arv'];
                array_push($array['arvRule'], $this->responseDemand($rule2)); // (16.1)
            }

            $rule3 = $safCalculation->calculateRuleSet3("petrolPump", $onePercPenalty);
            $rule3['floor'] = "petrolPump";
            $rule3['rentalRate'] = $rule3['matrixFactor'];
            $rule3['cvArvPropTax'] = $rule3['arv'];
            array_push($array['cvRule'], $this->responseDemand($rule3)); // (16.1)
        }
        return $array;
    }

    /**
     * | Cluster Holding Dues
     */
    public function getClusterHoldingDues(Request $req)
    {
        $req->validate([
            'clusterId' => 'required|integer'
        ]);
        try {
            $todayDate = Carbon::now();
            $clusterId = $req->clusterId;
            $mPropProperty = new PropProperty();
            $mClusters = new Cluster();
            $penaltyRebateCalc = new PenaltyRebateCalculation;
            $mPropAdvance = new PropAdvance();
            $properties = $mPropProperty->getPropsByClusterId($clusterId);
            $clusterDemands = array();
            $finalClusterDemand = array();
            $clusterDemandList = array();
            $currentQuarter = calculateQtr($todayDate->format('Y-m-d'));
            $loggedInUserType = authUser($req)->user_type;
            $currentFYear = getFY();

            $clusterDtls = $mClusters::findOrFail($clusterId);

            if ($properties->isEmpty())
                throw new Exception("Properties Not Available");

            $arrear = $properties->sum('balance');
            foreach ($properties as $item) {
                $propIdReq = new Request([
                    'propId' => $item['id']
                ]);
                $demandList = $this->getHoldingDues($propIdReq)->original['data'];
                $propDues['duesList'] = $demandList['duesList'] ?? [];
                $propDues['demandList'] = $demandList['demandList'] ?? [];
                array_push($clusterDemandList, $propDues['demandList']);
                array_push($clusterDemands, $propDues);
            }
            $collapsedDemand = collect($clusterDemandList)->collapse();                       // Clusters Demands Collapsed into One

            if (collect($collapsedDemand)->isEmpty())
                throw new Exception("Demand Not Available For This Cluster");

            $groupedByYear = $collapsedDemand->groupBy('quarteryear');                        // Grouped By Financial Year and Quarter for the Separation of Demand  

            $summedDemand = $groupedByYear->map(function ($item) use ($penaltyRebateCalc) {                            // Sum of all the Demands of Quarter and Financial Year
                $quarterDueDate = $item->first()['due_date'];
                $onePercPenaltyPerc = $penaltyRebateCalc->calcOnePercPenalty($quarterDueDate);
                $balance = roundFigure($item->sum('balance'));

                $onePercPenaltyTax = ($balance * $onePercPenaltyPerc) / 100;
                $onePercPenaltyTax = roundFigure($onePercPenaltyTax);

                return [
                    'quarterYear' => $item->first()['quarteryear'],
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
                    'adjust_amt' => roundFigure($item->sum('adjust_amt')),
                    'due_date' => $quarterDueDate,
                    'onePercPenalty' => $onePercPenaltyPerc,
                    'onePercPenaltyTax' => $onePercPenaltyTax,
                ];
            })->values();

            $finalDues = collect($summedDemand)->sum('balance');
            $finalDues = roundFigure($finalDues);

            $finalOnePerc = collect($summedDemand)->sum('onePercPenaltyTax');
            $finalOnePerc = roundFigure($finalOnePerc);

            $finalAmt = $finalDues + $finalOnePerc + $arrear;
            $duesFrom = collect($clusterDemands)->first()['duesList']['duesFrom'] ?? collect($clusterDemands)->last()['duesList']['duesFrom'];
            $duesTo = collect($clusterDemands)->first()['duesList']['duesTo'] ?? collect($clusterDemands)->last()['duesList']['duesTo'];
            $paymentUptoYrs = collect($clusterDemands)->first()['duesList']['paymentUptoYrs'] ?? collect($clusterDemands)->last()['duesList']['paymentUptoYrs'];
            $paymentUptoQtrs = collect($clusterDemands)->first()['duesList']['paymentUptoQtrs'] ?? collect($clusterDemands)->last()['duesList']['paymentUptoQtrs'];

            $advanceAdjustments = $mPropAdvance->getClusterAdvanceAdjustAmt($clusterId);

            if (collect($advanceAdjustments)->isEmpty())
                $advanceAmt = 0;
            else
                $advanceAmt = $advanceAdjustments->advance - $advanceAdjustments->adjustment_amt;

            $advanceAmt = roundFigure($advanceAmt);
            $finalClusterDemand['duesList'] = [
                'paymentUptoYrs' => $paymentUptoYrs,
                'paymentUptoQtrs' => $paymentUptoQtrs,
                'duesFrom' => $duesFrom,
                'duesTo' => $duesTo,
                'totalDues' => $finalDues,
                'onePercPenalty' => $finalOnePerc,
                'finalAmt' => $finalAmt,
                'arrear' => $arrear,
                'advanceAmt' => $advanceAmt
            ];
            $mLastQuarterDemand = collect($summedDemand)->where('fyear', $currentFYear)->sum('balance');
            $finalClusterDemand['duesList'] = $penaltyRebateCalc->readRebates($currentQuarter, $loggedInUserType, $mLastQuarterDemand, null, $finalAmt, $finalClusterDemand['duesList']);
            $payableAmount = $finalAmt - ($finalClusterDemand['duesList']['rebateAmt'] + $finalClusterDemand['duesList']['specialRebateAmt']);
            $finalClusterDemand['duesList']['payableAmount'] = round($payableAmount - $advanceAmt);

            $finalClusterDemand['demandList'] = $summedDemand;
            $finalClusterDemand['basicDetails'] = $clusterDtls;
            return responseMsgs(true, "Generated Demand of the Cluster", remove_null($finalClusterDemand), "011611", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), ['basicDetails' => $clusterDtls], "011611", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }


    /**
     * | Cluster Property Payments
     */
    public function clusterPayment(ReqPayment $req)
    {
        try {
            $dueReq = new Request([
                'clusterId' => $req->id
            ]);
            $clusterId = $req->id;
            $todayDate = Carbon::now();
            $idGeneration = new IdGeneration;
            $mPropTrans = new PropTransaction();
            $mPropDemand = new PropDemand();
            $offlinePaymentModes = Config::get('payment-constants.PAYMENT_MODE_OFFLINE');
            $mPropAdjustment = new PropAdjustment();

            $dues = $this->getClusterHoldingDues($dueReq);

            if ($dues->original['status'] == false)
                throw new Exception($dues->original['message']);

            $dues = $dues->original['data'];
            $demands = $dues['demandList'];
            $tranNo = $idGeneration->generateTransactionNo($req['ulbId']);
            $payableAmount = $dues['duesList']['payableAmount'];
            $advanceAmt = $dues['duesList']['advanceAmt'];
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
                'clusterType' => "Property"
            ]);

            DB::beginTransaction();
            $propTrans = $mPropTrans->postClusterTransactions($req, $demands);

            if (in_array($req['paymentMode'], $offlinePaymentModes)) {
                $req->merge([
                    'chequeDate' => $req['chequeDate'],
                    'tranId' => $propTrans['id']
                ]);
                $this->postOtherPaymentModes($req);
            }

            $clusterDemand = $mPropDemand->getDemandsByClusterId($clusterId);
            if ($clusterDemand->isEmpty())
                throw new Exception("Demand Not Available");
            // Reflect on Prop Tran Details
            foreach ($clusterDemand as $demand) {
                $propDemand = $mPropDemand->getDemandById($demand['id']);
                $propDemand->balance = 0;
                $propDemand->paid_status = 1;           // <-------- Update Demand Paid Status 
                $propDemand->save();

                $propTranDtl = new PropTranDtl();
                $propTranDtl->tran_id = $propTrans['id'];
                $propTranDtl->prop_demand_id = $demand['id'];
                $propTranDtl->total_demand = $demand['amount'];
                $propTranDtl->ulb_id = $req['ulbId'];
                $propTranDtl->save();
            }
            // Replication Prop Rebates Penalties
            $this->postPaymentPenaltyRebate($dues['duesList'], null, $propTrans['id'], $clusterId);

            if ($advanceAmt > 0) {
                $adjustReq = [
                    'cluster_id' => $clusterId,
                    'tran_id' => $propTrans['id'],
                    'amount' => $advanceAmt
                ];
                if ($tranBy == 'Citizen')
                    $adjustReq = array_merge($adjustReq, ['citizen_id' => $userId ?? 0]);
                else
                    $adjustReq = array_merge($adjustReq, ['user_id' => $userId ?? 0]);

                $mPropAdjustment->store($adjustReq);
            }
            DB::commit();
            return responseMsgs(true, "Payment Successfully Done", ["tranNo" => $tranNo], "011612", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "011612", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Cluster Payment History
     */
    public function clusterPaymentHistory(Request $req)
    {
        $req->validate([
            'clusterId' => "required|numeric"
        ]);

        try {
            $clusterId = $req->clusterId;
            $mPropTrans = new PropTransaction();
            $transactions = $mPropTrans->getPropTransactions($clusterId, "cluster_id");
            if ($transactions->isEmpty())
                throw new Exception("No Transaction Found for this Cluster");
            $transactions = $transactions->groupBy('tran_type');
            return responseMsgs(true, "Cluster Transactions", remove_null($transactions), "011613", "1.0", "", "", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011613", "1.0", "", "", $req->deviceId ?? "");
        }
    }

    /**
     * | Generate Cluster Payment Receipt For cluster saf and Holding (011613)
     */
    public function clusterPaymentReceipt(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['tranNo' => 'required']
        );
        if ($validated->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validated->errors()
            ], 401);
        }
        try {
            $mTransaction = new PropTransaction();
            $mPropPenalties = new PropPenaltyrebate();
            $mClusters = new Cluster();
            $paymentReceiptHelper = new PaymentReceiptHelper;

            $mTowards = Config::get('PropertyConstaint.SAF_TOWARDS');
            $mAccDescription = Config::get('PropertyConstaint.ACCOUNT_DESCRIPTION');
            $mDepartmentSection = Config::get('PropertyConstaint.DEPARTMENT_SECTION');

            $rebatePenalMstrs = collect(Config::get('PropertyConstaint.REBATE_PENAL_MASTERS'));
            $onePercKey = $rebatePenalMstrs->where('id', 1)->first()['value'];
            $specialRebateKey = $rebatePenalMstrs->where('id', 6)->first()['value'];
            $firstQtrKey = $rebatePenalMstrs->where('id', 2)->first()['value'];
            $onlineRebate = $rebatePenalMstrs->where('id', 3)->first()['value'];

            $propTrans = $mTransaction->getPropByTranPropId($req->tranNo);
            $clusterId = $propTrans->cluster_id;

            $propCluster = $mClusters->getClusterDtlsById($clusterId);

            // Get Property Penalty and Rebates
            $penalRebates = $mPropPenalties->getPropPenalRebateByTranId($propTrans->id);

            $onePercPenalty = collect($penalRebates)->where('head_name', $onePercKey)->first()->amount ?? 0;
            $rebate = collect($penalRebates)->where('head_name', 'Rebate')->first()->amount ?? "";
            $specialRebate = collect($penalRebates)->where('head_name', $specialRebateKey)->first()->amount ?? 0;
            $firstQtrRebate = collect($penalRebates)->where('head_name', $firstQtrKey)->first()->amount ?? 0;
            $jskOrOnlineRebate = collect($penalRebates)->where('head_name', $onlineRebate)->first()->amount ?? 0;
            $lateAssessmentPenalty = 0;

            $taxDetails = $paymentReceiptHelper->readPenalyPmtAmts($lateAssessmentPenalty, $onePercPenalty, $rebate, $specialRebate, $firstQtrRebate, $propTrans->amount, $jskOrOnlineRebate);
            $responseData = [
                "departmentSection" => $mDepartmentSection,
                "accountDescription" => $mAccDescription,
                "transactionDate" => $propTrans->tran_date,
                "transactionNo" => $propTrans->tran_no,
                "transactionTime" => $propTrans->created_at->format('H:i:s'),
                "applicationNo" => "",
                "customerName" => $propCluster->authorized_person_name,
                "mobileNo" => $propCluster->mobile_no,
                "receiptWard" => $propCluster->old_ward,
                "address" => $propCluster->address,
                "paidFrom" => $propTrans->from_fyear,
                "paidFromQtr" => $propTrans->from_qtr,
                "paidUpto" => $propTrans->to_fyear,
                "paidUptoQtr" => $propTrans->to_qtr,
                "paymentMode" => $propTrans->payment_mode,
                "bankName" => $propTrans->bank_name,
                "branchName" => $propTrans->branch_name,
                "chequeNo" => $propTrans->cheque_no,
                "chequeDate" => $propTrans->cheque_date,
                "demandAmount" => $propTrans->demand_amt,
                "taxDetails" => $taxDetails,
                "ulbId" => $propCluster->ulb_id,
                "oldWardNo" => $propCluster->old_ward,
                "newWardNo" => $propCluster->new_ward,
                "towards" => $mTowards,
                "description" => [
                    "keyString" => "Holding Tax"
                ],
                "totalPaidAmount" => $propTrans->amount,
                "paidAmtInWords" => getIndianCurrency($propTrans->amount),
            ];
            return responseMsgs(true, "Cluster Payment Receipt", remove_null($responseData), "011613", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "011613", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Get Property Dues
     */
    public function propertyDues(Request $req)
    {
        $validator = Validator::make(
            $req->all(),
            ['propId' => 'required']
        );
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'validation error',
                'errors'  => $validator->errors()
            ]);
        }
        $demandDues = $this->getHoldingDues($req);
        if ($demandDues->original['status'] == false)
            return responseMsgs(false, "No Dues Available for this Property", "");
        $demandDues = $demandDues->original['data']['duesList'];
        $demandDetails = $this->generateDemandDues($demandDues);
        $dataRow['dataRow'] = $demandDetails;
        $dataRow['btnUrl'] = "/viewDemandHoldingProperty/" . $req->propId;
        $data['tableTop'] =  [
            'headerTitle' => 'Property Dues',
            'tableHead' => ["#", "Dues From", "Dues To", "Total Dues", "1 % Penalty", "Rebate Amt", "Payable Amount"],
            'tableData' => [$dataRow]
        ];
        return responseMsgs(true, "Demand Dues", remove_null($data), "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    }

    public function oldChequeEntery(Request $request)
    {
        try {
            $user = Auth()->user();
            $userId = $user->id;
            $ulbId = $user->ulbId;
            $tran = new PropTransaction();
            $tranDtl = new PropTranDtl();
            $cheqeDtl = new PropChequeDtl();
            $penaltyrebates = new PropPenaltyrebate();
            $log = new OldChequeTranEntery();
            $now = Carbon::now()->format("Y-m-d");
            $mRegex         = '/^[a-zA-Z1-9][a-zA-Z1-9\.\s\/\-\_\,]+$/i';
            $validator = Validator::make(
                $request->all(),
                [
                    "propId"        =>  "required|digits_between:1,9223372036854775807",
                    "demandId"      =>  "required|digits_between:1,9223372036854775807",
                    "bookNo"        =>  "required|regex:$mRegex",
                    "ReceiptNo"     =>  "required|digits_between:1,922337",
                    "tranDate"      =>  "required|date|before_or_equal:$now",
                    "paymentMode"   =>  "required|in:CASH,CHEQUE,DD,NEFT",
                    "chequeNo"      => ($request->paymentMode && $request->paymentMode != "CASH" ? "required" : "nullable"),
                    "bankName"      => ($request->paymentMode && $request->paymentMode != "CASH" ? "required|regex:$mRegex" : "nullable"),
                    "branchName"    =>  "nullable|regex:$mRegex",
                    "clearStatus"   =>  "required|in:pending,clear",
                    "amount"        =>  "required|numeric|min:0|max:9999999",
                    "penalty"       =>  "required|numeric|min:0|max:9999999",
                    "maintananceAmt" =>  "required|numeric|min:0|max:922337",
                    "agingAmt"      =>  "required|numeric|min:0|max:922337",
                    "generalTax"    =>  "required|numeric|min:0|max:922337",
                    "roadTax"       =>  "required|numeric|min:0|max:922337",
                    "firefightingTax"  =>  "required|numeric|min:0|max:922337",
                    "educationTax"  =>  "required|numeric|min:0|max:922337",
                    "waterTax"      =>  "required|numeric|min:0|max:922337",
                    "cleanlinessTax" =>  "required|numeric|min:0|max:922337",
                    "sewarageTax"   =>  "required|numeric|min:0|max:922337",
                    "treeTax"       =>  "required|numeric|min:0|max:922337",
                    "professionalTax" => "required|numeric|min:0|max:922337",
                    "totalTax"      => "required|numeric|min:0|max:922337",
                    "tax1"          => "required|numeric|min:0|max:922337",
                    "tax2"          => "required|numeric|min:0|max:922337",
                    "tax3"          => "required|numeric|min:0|max:922337",
                    "spEducationTax" => "required|numeric|min:0|max:922337",
                    "waterBenefit" => "required|numeric|min:0|max:922337",
                    "waterBill" => "required|numeric|min:0|max:922337",
                    "spWaterCess" => "required|numeric|min:0|max:922337",
                    "drainCess" => "required|numeric|min:0|max:922337",
                    "lightCess" => "required|numeric|min:0|max:922337",
                    "majorBuilding" => "required|numeric|min:0|max:922337",
                    "openPloatTax" => "required|numeric|min:0|max:922337",
                ]
            );
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), "");
            }
            $penalty = $request->penalty;
            $totalTax = $request->totalTax - $penalty;
            $testTotal = ($request->maintananceAmt + $request->agingAmt + $request->generalTax +
                $request->roadTax + $request->educationTax +
                $request->waterTax + $request->firefightingTax +
                $request->cleanlinessTax + $request->sewarageTax + $request->treeTax +
                $request->professionalTax + $request->tax1 + $request->tax2 +
                $request->tax3 + $request->spEducationTax + $request->waterBenefit +
                $request->waterBill + $request->spWaterCess + $request->drainCess +
                $request->lightCess + $request->majorBuilding + $request->openPloatTax
            );

            if (round($testTotal) != round($totalTax)) {
                throw new Exception("total Tax Missmatched " . round($testTotal) . "-------" . round($totalTax));
            }
            if (round($request->amount) != round($totalTax + $penalty)) {
                throw new Exception("total payble Amount Missmatched");
            }
            $enterChek = OldChequeTranEntery::select("*")
                ->where("prop_id", $request->propId)
                ->where("book_no", trim($request->bookNo))
                ->where("receipt_no", trim($request->ReceiptNo))
                ->where("cheque_no", trim($request->chequeNo))
                ->where("status", 1)
                ->first();
            if ($enterChek) {
                throw new Exception("This Transection Already Inserted on : " . $enterChek->created_at);
            }
            $prop = PropProperty::find($request->propId);
            if (!$prop) {
                throw new Exception("Property Not Found");
            }
            $olddemand = PropDemand::where("id", $request->demandId)->where("property_id", $request->propId)->first();
            $demand = PropDemand::where("id", $request->demandId)->where("property_id", $request->propId)->first();
            if (!$demand) {
                throw new Exception("Demand Not Found");
            }
            $meta_request = [
                "totalTax" => $totalTax,
                "demand" => $demand,
                "userId" => $userId,
            ];
            $request->merge($meta_request);
            DB::beginTransaction();

            $this->demandUpdate($request, $demand);

            $demand->update();

            $this->tranInsert($request, $tran, $demand, $prop);
            $tran->save();
            $request->merge(["tranId" => $tran->id]);
            $interId = $log->store($request);
            $this->tranDtlInsert($request, $tranDtl, $tran, $demand, $prop);
            $tranDtl->save();
            if ($request->paymentMode != "CASH") {
                $this->chequDtlInsert($request, $cheqeDtl, $tran, $prop);
                $cheqeDtl->save();
            }
            $this->penaltyRebateInsert($request, $penaltyrebates, $tran);
            $penaltyrebates->save();

            // dd($olddemand,$totalTax,$interId,$demand,$tran,$tranDtl,$cheqeDtl);
            DB::commit();
            $returnData = ['TransactionNo' => $tran->tran_no, 'transactionId' => $tran->id];
            return responseMsgs(true, "Transection Inserted", $returnData, "1", "1.0", "", "", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "1", "1.0", "", "", $request->deviceId ?? "");
        }
    }

    private function demandUpdate(Request $request, PropDemand $demand)
    {
        $demand->maintanance_amt = $demand->maintanance_amt + $request->maintananceAmt;
        $demand->aging_amt       = $demand->aging_amt       + $request->agingAmt;
        $demand->general_tax     = $demand->general_tax     + $request->generalTax;
        $demand->road_tax        = $demand->road_tax        + $request->roadTax;
        $demand->firefighting_tax = $demand->firefighting_tax + $request->firefightingTax;
        $demand->education_tax   = $demand->education_tax   + $request->educationTax;
        $demand->water_tax       = $demand->water_tax       + $request->waterTax;
        $demand->cleanliness_tax = $demand->cleanliness_tax + $request->cleanlinessTax;
        $demand->sewarage_tax    = $demand->sewarage_tax    + $request->sewarageTax;
        $demand->tree_tax        = $demand->tree_tax        + $request->treeTax;
        $demand->professional_tax = $demand->professional_tax + $request->professionalTax;
        $demand->total_tax       = $demand->total_tax       + $request->totalTax;
        $demand->tax1            = $demand->tax1            + $request->tax1;
        $demand->tax2            = $demand->tax2            + $request->tax2;
        $demand->tax3            = $demand->tax3            + $request->tax3;
        $demand->sp_education_tax = $demand->sp_education_tax + $request->sp_educationTax;
        $demand->water_benefit   = $demand->water_benefit   + $request->waterBenefit;
        $demand->water_bill      = $demand->water_bill   + $request->waterBill;
        $demand->sp_water_cess   = $demand->sp_water_cess   + $request->spWaterCess;
        $demand->drain_cess      = $demand->drain_cess   + $request->drainCess;
        $demand->light_cess      = $demand->light_cess   + $request->lightCess;
        $demand->major_building  = $demand->major_building   + $request->majorBuilding;
        $demand->open_ploat_tax  = $demand->open_ploat_tax   + $request->openPloatTax;
        if (!$demand->paid_status && $demand->due_total_tax > 0) {
            $demand->is_full_paid = false;
            $demand->paid_status = 1;
        }
    }
    private function tranInsert(Request $request, PropTransaction $tran, PropDemand $demand, PropProperty $prop)
    {
        $tran->property_id = $request->propId;
        $tran->tran_date   = Carbon::parse($request->tranDate)->format("Y-m-d");
        $tran->tran_no     = $request->bookNo . "-" . $request->ReceiptNo;
        $tran->tran_no     = $tran->tran_no;
        $tran->payment_mode = $request->paymentMode;
        $tran->amount       = $request->amount;
        $tran->tran_type    = "Property";
        $tran->verify_status = $request->clearStatus == "clear" ? 1 : 2;
        $tran->user_id      =     18;
        $tran->from_fyear   =     $demand->fyear;
        $tran->to_fyear     =     $demand->fyear;
        $tran->ulb_id       =     $prop->ulb_id;
    }

    private function tranDtlInsert(Request $request, PropTranDtl $tranDtl, PropTransaction $tran, PropDemand $demand, PropProperty $prop)
    {
        $tranDtl->tran_id        = $tran->id;
        $tranDtl->prop_demand_id = $demand->id;
        $tranDtl->ulb_id         = $prop->ulb_id;
        $tranDtl->paid_maintanance_amt  = $request->maintananceAmt;
        $tranDtl->paid_aging_amt  = $request->agingAmt;
        $tranDtl->paid_general_tax  = $request->generalTax;
        $tranDtl->paid_road_tax  = $request->roadTax;
        $tranDtl->paid_firefighting_tax  = $request->firefightingTax;
        $tranDtl->paid_education_tax  = $request->educationTax;
        $tranDtl->paid_water_tax  = $request->waterTax;
        $tranDtl->paid_cleanliness_tax  = $request->cleanlinessTax;
        $tranDtl->paid_sewarage_tax  = $request->sewarageTax;
        $tranDtl->paid_tree_tax  = $request->treeTax;
        $tranDtl->paid_professional_tax  = $request->professionalTax;
        $tranDtl->paid_total_tax  = $request->totalTax;
        $tranDtl->paid_balance  = $request->totalTax;
        $tranDtl->paid_tax1  = $request->tax1;
        $tranDtl->paid_tax2  = $request->tax2;
        $tranDtl->paid_tax3  = $request->tax3;
        $tranDtl->paid_sp_education_tax  = $request->sp_educationTax;
        $tranDtl->paid_water_benefit  = $request->waterBenefit;
        $tranDtl->paid_water_bill  = $request->waterBill;
        $tranDtl->paid_sp_water_cess  = $request->spWaterCess;
        $tranDtl->paid_drain_cess  = $request->drainCess;
        $tranDtl->paid_light_cess  = $request->lightCess;
        $tranDtl->paid_major_building  = $request->majorBuilding;
        $tranDtl->paid_open_ploat_tax  = $request->openPloatTax;
    }
    private function chequDtlInsert(Request $request, PropChequeDtl $cheqeDtl, PropTransaction $tran, PropProperty $prop)
    {
        $cheqeDtl->prop_id = $prop->id;
        $cheqeDtl->transaction_id = $tran->id;
        $cheqeDtl->cheque_date = $tran->tran_date;
        $cheqeDtl->bank_name = $request->bankName;
        $cheqeDtl->branch_name = $request->branchName;
        $cheqeDtl->status = $tran->verify_status;
        $cheqeDtl->cheque_no = $request->chequeNo;
        if ($cheqeDtl->status == 1) {
            $cheqeDtl->clear_bounce_date = $tran->tran_date;
        }
    }
    private function penaltyRebateInsert(Request $request, PropPenaltyrebate $penaltyrebates, PropTransaction $tran)
    {
        $penaltyrebates->tran_id =  $tran->id;
        $penaltyrebates->head_name =  'Monthly Penalty';
        $penaltyrebates->amount =  $request->penalty;
        $penaltyrebates->is_rebate =  false;
        $penaltyrebates->tran_date =  $tran->tran_date;
        $penaltyrebates->prop_id =  $tran->property_id;
        $penaltyrebates->app_type =  $tran->tran_type;
    }

    /**
     * | Send Bulk List
     */
    public function propertyBulkSmsList(Request $req)
    {


        $zoneId = $wardId = $amount = NULL;
        if ($req->zoneId)
            $zoneId = $req->zoneId;

        if ($req->wardId)
            $wardId = $req->wardId;

        if ($req->amount)
            $amount = $req->amount;

        $fromDate = Carbon::now()->today();
        $uptoDate = Carbon::now()->addWeek(-1);
        $perPage = $req->perPage ?? 10;
        $currentFYear = getFY();
        DB::enableQueryLog();
        $propDetails = PropDemand::select(
            'prop_sms_logs.id as sms_log_id',
            'prop_properties.id as property_id',
            DB::raw('SUM(due_total_tax) as total_tax, MAX(fyear) as max_fyear'),
            'prop_owners.owner_name',
            'prop_owners.mobile_no',
            'holding_no',
            'prop_address',
            'property_no',
            'zone_name',
            'ward_name',
            // 'prop_sms_logs.created_at'
        )
            ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_demands.property_id')
            ->join('prop_properties', 'prop_properties.id', '=', 'prop_demands.property_id')
            ->leftjoin('zone_masters', 'zone_masters.id', '=', 'prop_properties.zone_mstr_id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'prop_properties.ward_mstr_id')

            ->leftJoin('prop_sms_logs', function ($join) use ($fromDate, $uptoDate) {
                $join->on('prop_sms_logs.ref_id', '=', 'prop_properties.id')
                    ->where('prop_sms_logs.ref_type', 'PROPERTY')
                    ->where('prop_sms_logs.purpose', 'Demand Reminder')
                    ->whereBetween('prop_sms_logs.created_at', [$fromDate, $uptoDate]);
            })
            ->whereNull('prop_sms_logs.id')
            ->where('due_total_tax', '>', 0.9)
            ->where('paid_status', 0)
            ->where(DB::raw('LENGTH(prop_owners.mobile_no)'), '=', 10)
            ->groupBy(
                'prop_demands.property_id',
                'prop_owners.owner_name',
                'prop_owners.mobile_no',
                'prop_properties.id',
                'zone_name',
                'ward_name',
                'prop_sms_logs.id'
            )
            ->orderBy('prop_demands.property_id');

        if ($zoneId)
            $propDetails = $propDetails->where("prop_properties.zone_mstr_id", $zoneId);

        if ($wardId)
            $propDetails = $propDetails->where("prop_properties.ward_mstr_id", $wardId);

        if ($amount)
            $propDetails = $propDetails->where("due_total_tax", '>', $amount);

        $propDetails =  $propDetails->paginate($perPage);
        // dd(DB::getQueryLog());

        return responseMsgs(true, "Bulk SMS List", $propDetails, "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    }


    /**
     * | Send Bulk Sms
     */
    public function propertyBulkSms(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            ['propertyIds' => 'required|array']
        );
        if ($validated->fails())
            return validationError($validated);

        $userId = authUser($req)->id;
        $mPropSmsLog = new PropSmsLog();
        $getHoldingDues = new GetHoldingDuesV2;
        $propDetails = PropDemand::select(
            'prop_demands.property_id',
            DB::raw('SUM(due_total_tax) as total_tax, MAX(fyear) as max_fyear'),
            'owner_name',
            'mobile_no',
        )
            ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_demands.property_id')
            ->where('due_total_tax', '>', 0.9)
            ->where('paid_status', 0)
            ->where(DB::raw('LENGTH(mobile_no)'), '=', 10)
            ->whereIn('prop_demands.property_id', $req->propertyIds)
            ->groupBy('prop_demands.property_id', 'owner_name', 'mobile_no')
            ->orderByDesc('prop_demands.property_id')
            ->get();

        foreach ($propDetails as $propDetail) {
            $newReq = new Request(['propId' => $propDetail->property_id]);
            $demand = $getHoldingDues->getDues($newReq);

            $ownerName       = Str::limit(trim($propDetail->owner_name), 30);
            $ownerMobile     = $propDetail->mobile_no;
            $totalTax        = $demand['payableAmt'];
            $fyear           = $propDetail->max_fyear;
            $propertyId      = $propDetail->property_id;
            $propertyNo      = $demand['basicDetails']['property_no'];
            $akolaContactNo  = "08069493299";

            $sms      = "Dear " . $ownerName . ", your Property Tax of amount Rs " . $totalTax . " for property no. " . $propertyNo . " is due. Please pay your tax on time to avoid penalties and interest. Please ignore if already paid. For details visit amcakola.in or call us at:" . $akolaContactNo . ". SWATI INDUSTRIES";
            $response = send_sms($ownerMobile, $sms, 1707170858405361648);

            $smsReqs = [
                "emp_id" => $userId,
                "ref_id" => $propertyId,
                "ref_type" => 'PROPERTY',
                "mobile_no" => $ownerMobile,
                "purpose" => 'Demand Reminder',
                "template_id" => 1707170858405361648,
                "message" => $sms,
                "response" => $response['status'],
                "smgid" => $response['msg'],
                "stampdate" => Carbon::now(),
            ];
            $mPropSmsLog->create($smsReqs);
        }

        return responseMsgs(true, "SMS Send Successfully of " . $propDetails->count() . " Property", [], "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    }

    /**
     * | Bulk Sms Report
     */
    public function bulkSmsReport(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "fromDate" => "required|date",
                "uptoDate" => "required|date"
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        $mPropSmsLog = new PropSmsLog();
        $fromDate = $req->fromDate;
        $uptoDate = $req->uptoDate;
        $perPage = $req->perPage ?? 10;
        $propDetails = $mPropSmsLog::
            // select(
            //     'prop_demands.property_id',
            //     DB::raw('SUM(due_total_tax) as total_tax, MAX(fyear) as max_fyear'),
            //     'owner_name',
            //     'mobile_no',
            // )
            where('purpose', '=', 'Demand Reminder')
            // ->join('prop_properties', 'prop_properties.id', '=', 'prop_sms_logs.ref_id')
            // ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_properties.id')
            // ->join('users', 'users.id', 'prop_sms_logs.emp_id')
            ->whereBetween('prop_sms_logs.created_at', [$fromDate . ' 00:00:01', $uptoDate . ' 23:59:59'])
            ->paginate($perPage);

        return responseMsgs(true, "Sent SMS List", $propDetails, "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    }


    public function testSms(Request $req)
    {
        $mPropSmsLog = new PropSmsLog();

        $sms      = "Dear {#var#}, your Property Tax of amount Rs {#var#} is due. Please pay your tax on time to avoid penalties and interest. Please ignore if already paid. For details visit www.amcakola.in or call us at:18008907909. SWATI INDUSTRIES";
        $response = send_sms("Ram Kumar", $sms, 1707170832840649671);

        $smsReqs = [
            "emp_id" => 1,
            "ref_id" => 1,
            "ref_type" => 'PROPERTY',
            "mobile_no" => 8797770238,
            "purpose" => 'Demand Reminder',
            "template_id" => 1707169564203481769,
            "message" => $sms,
            "response" => $response['status'],
            "smgid" => $response['msg'],
            "stampdate" => Carbon::now(),
        ];
        $mPropSmsLog->create($smsReqs);


        return responseMsgs(true, "SMS Send Successfully", [], "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    }

    /**
     * | Send Bulk Sms
     */
    public function propertyBulkSms2(Request $req)
    {
        $mPropSmsLog = new PropSmsLog();
        $propDetails = PropDemand::select(
            'prop_demands.property_id',
            DB::raw('SUM(due_total_tax) as total_tax, MAX(fyear) as max_fyear'),
            'owner_name',
            'mobile_no',
        )
            ->join('prop_owners', 'prop_owners.property_id', '=', 'prop_demands.property_id')
            ->where('due_total_tax', '>', 0.9)
            ->where('fyear', '2023-2024')
            ->where('paid_status', 0)
            ->where(DB::raw('LENGTH(mobile_no)'), '=', 10)
            ->groupBy('prop_demands.property_id', 'owner_name', 'mobile_no')
            ->orderBy('prop_demands.property_id')
            ->limit(2)
            ->get();

        foreach ($propDetails as $propDetail) {

            $ownerName   = Str::limit(trim($propDetail->owner_name), 30);
            $ownerMobile = $propDetail->mobile_no;
            $totalTax    = $propDetail->total_tax;
            $fyear       = $propDetail->max_fyear;
            $propertyId  = $propDetail->property_id;

            $sms      = "Dear " . $ownerName . ",  your Property Tax Demand of Rs " . $totalTax . " has been generated upto FY 23-24. Please pay on time to avoid any late fine. Please ignore if already paid. For more details visit www.akolamc.org/call us at:18008907909 SWATI INDUSTRIES";
            $response = send_sms($ownerMobile, $sms, 1707169564203481769);

            $smsReqs = [
                "emp_id" => 1,
                "ref_id" => $propertyId,
                "ref_type" => 'PROPERTY',
                "mobile_no" => $ownerMobile,
                "purpose" => 'Demand Reminder',
                "template_id" => 1707169564203481769,
                "message" => $sms,
                "response" => $response['status'],
                "smgid" => $response['msg'],
                "stampdate" => Carbon::now(),
            ];
            $mPropSmsLog->create($smsReqs);
        }

        return responseMsgs(true, "SMS Send Successfully of " . $propDetails->count() . " Property", [], "", "1.0", responseTime(), "POST", $req->deviceId ?? "");
    }
}
