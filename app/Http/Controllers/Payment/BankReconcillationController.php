<?php

namespace App\Http\Controllers\Payment;

use App\BLL\Property\DeactivateTran;
use App\BLL\Water\WaterTranDeactivate;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Water\WaterPaymentController;
use App\MicroServices\DocUpload;
use App\Models\Payment\PaymentReconciliation;
use App\Models\Payment\TempTransaction;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropChequeDtl;
use App\Models\Property\PropDemand;
use App\Models\Property\PropProperty;
use App\Models\Property\PropSafsDemand;
use App\Models\Property\PropTranDtl;
use App\Models\Property\PropTransaction;
use App\Models\Property\PropTransactionDeactivateDtl;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\TradeChequeDtl;
use App\Models\Trade\TradeLicence;
use App\Models\Trade\TradeTransaction;
use App\Models\Trade\TradeTransactionDeactivateDtl;
use App\Models\Water\WaterApplication;
use App\Models\Water\WaterChequeDtl;
use App\Models\Water\WaterConnectionCharge;
use App\Models\Water\WaterConsumer;
use App\Models\Water\WaterConsumerCollection;
use App\Models\Water\WaterConsumerDemand;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterTran;
use App\Models\Water\WaterTranDetail;
use App\Models\Water\WaterTransactionDeactivateDtl;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Predis\Response\Status;

/**
 * | Created On-14-02-2023 
 * | Created by-Mrinal Kumar
 * | Bank Reconcillation
 */

class BankReconcillationController extends Controller
{
    /**
     * | 1
     */
    public function searchTransaction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fromDate' => 'required',
                'toDate' => 'required',
                'moduleId' => 'required'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ]);
            }
            $ulbId = authUser($request)->ulb_id;
            $moduleId = $request->moduleId;
            $paymentMode = $request->paymentMode;
            $verifyStatus = $request->verificationType;
            $fromDate = Carbon::create($request->fromDate)->format('Y-m-d');
            $toDate = Carbon::create($request->toDate)->format('Y-m-d');
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $mPropTransaction = new PropTransaction();
            $mTradeTransaction = new TradeTransaction();
            $mWaterTran = new WaterTran();

            if ($moduleId == $propertyModuleId) {
                $chequeTranDtl  = $mPropTransaction->chequeTranDtl($ulbId);
                if ($request->verificationType != "bounce") {
                    $chequeTranDtl = $chequeTranDtl->where("prop_transactions.status", 1);
                }
                if ($request->chequeNo) {
                    $data =  $chequeTranDtl
                        ->where('cheque_no', $request->chequeNo)
                        ->get();
                }
                if (!isset($data)) {
                    $data = $chequeTranDtl
                        ->whereBetween('tran_date', [$fromDate, $toDate])
                        ->get();
                }
            }

            if ($moduleId == $waterModuleId) {

                $chequeTranDtl  = $mWaterTran->chequeTranDtl($ulbId);
                if ($request->verificationType != "bounce") {
                    $chequeTranDtl = $chequeTranDtl->where("water_trans.status", 1);
                }

                if ($request->chequeNo) {
                    $data =  $chequeTranDtl
                        ->where('cheque_no', $request->chequeNo)
                        ->get();
                }
                if (!isset($data)) {
                    $data = $chequeTranDtl
                        ->whereBetween('tran_date', [$fromDate, $toDate])
                        ->get();
                }
            }

            if ($moduleId == $tradeModuleId) {
                $chequeTranDtl  = $mTradeTransaction->chequeTranDtl($ulbId);

                if ($request->chequeNo) {
                    $data =  $chequeTranDtl
                        ->where('cheque_no', $request->chequeNo)
                        ->get();
                }
                if (!isset($data)) {
                    $data = $chequeTranDtl
                        ->whereBetween('tran_date', [$fromDate, $toDate])
                        ->get();
                }
            }

            if ($paymentMode == 'DD') {
                $a =  collect($data)->where('payment_mode', 'DD');
                $data = (array_values(objtoarray($a)));
            }

            if ($paymentMode == 'CHEQUE') {
                $a =  collect($data)->where('payment_mode', 'CHEQUE');
                $data = (array_values(objtoarray($a)));
            }

            if ($paymentMode == 'NEFT') {
                $a =  collect($data)->where('payment_mode', 'NEFT');
                $data = (array_values(objtoarray($a)));
            }

            //search with verification status
            if ($verifyStatus == 'pending') {
                $a =  collect($data)->where('status', '2');
                $data = (array_values(objtoarray($a)));
            }

            if ($verifyStatus == 'clear') {
                $a =  collect($data)->where('status', '1');
                $data = (array_values(objtoarray($a)));
            }

            if ($verifyStatus == 'bounce') {
                $a =  collect($data)->where('status', '3');
                $data = (array_values(objtoarray($a)));
            }

            if (collect($data)->isNotEmpty()) {
                return responseMsgs(true, "Data Acording to request!", $data, '010801', '01', '382ms-547ms', 'Post', '');
            }
            return responseMsg(false, "data not found!", "");
        } catch (Exception $error) {
            return responseMsg(false, "ERROR!", $error->getMessage());
        }
    }


    /**
     * | 2
     */
    public function chequeDtlById(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'moduleId' => 'required',
                'chequeId' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => False, 'msg' => $validator()->errors()]);
            }

            $moduleId = $request->moduleId;
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $mPropChequeDtl = new PropChequeDtl();
            $mTradeChequeDtl = new TradeChequeDtl();
            $mWaterChequeDtl = new WaterChequeDtl();


            switch ($moduleId) {
                    //Property
                case ($propertyModuleId):
                    $data = $mPropChequeDtl->chequeDtlById($request);
                    break;

                    //Water
                case ($waterModuleId):
                    $data = $mWaterChequeDtl->chequeDtlById($request);
                    break;

                    //Trade
                case ($tradeModuleId):
                    $data = $mTradeChequeDtl->chequeDtlById($request);
                    break;
            }

            if ($data)
                return responseMsg(true, "data found", $data);
            else
                return responseMsg(false, "data not found!", "");
        } catch (Exception $error) {
            return responseMsg(false, "ERROR!", $error->getMessage());
        }
    }

    /**
     * | 3
     */
    public function chequeClearance(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'moduleId'      => 'required',
                'chequeId'      => 'required',
                'status'        => 'required|in:clear,bounce',
                'clearanceDate' => 'required'
            ]);

            if ($validator->fails()) {
                return validationError($validator);
            }
            $user = authUser($request);
            $ulbId = $user->ulb_id;
            $userId = $user->id;
            $moduleId = $request->moduleId;
            $paymentStatus = 1;
            $applicationPaymentStatus = 1;
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $mPaymentReconciliation = new PaymentReconciliation();

            if ($request->status == 'clear') {
                $applicationPaymentStatus = $paymentStatus = 1;
            }
            if ($request->status == 'bounce') {
                $paymentStatus = 3;
                $applicationPaymentStatus = 0;
            }

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            DB::connection('pgsql_water')->beginTransaction();
            // DB::connection('pgsql_trade')->beginTransaction();

            if ($moduleId == $propertyModuleId) {
                $mChequeDtl =  PropChequeDtl::find($request->chequeId);

                $mChequeDtl->status = $paymentStatus;
                $mChequeDtl->clear_bounce_date = $request->clearanceDate;
                $mChequeDtl->bounce_amount = $request->cancellationCharge;
                $mChequeDtl->remarks = $request->remarks;
                $mChequeDtl->save();

                $transaction = PropTransaction::where('id', $mChequeDtl->transaction_id)
                    ->first();
                $propId = $transaction->property_id;
                $safId = $transaction->saf_id;

                if ($propId)
                    $wardId = PropProperty::findorFail($propId)->ward_mstr_id;

                if ($safId)
                    $wardId = PropActiveSaf::findorFail($safId)->ward_mstr_id;

                PropTransaction::where('id', $mChequeDtl->transaction_id)
                    ->update(
                        [
                            'verify_status' => $paymentStatus,
                            'verify_date' => Carbon::now(),
                            'verified_by' => $userId
                        ]
                    );
                if ($safId)
                    PropActiveSaf::where('id', $safId)
                        ->update(
                            ['payment_status' => $applicationPaymentStatus]
                        );

                if ($applicationPaymentStatus == 0) {
                    $propDeactivateTran = new DeactivateTran($transaction->id);
                    $propDeactivateTran->deactivate();                              // Deactive Property Transaction by property id
                    // if ($transaction->is_arrear_settled == false) {
                    //     $propTranDtls = PropTranDtl::where('tran_id', $transaction->id)->get();
                    //     foreach ($propTranDtls as $propTranDtl) {
                    //         $propDemandId = $propTranDtl->prop_demand_id;
                    //         $safDemandId = $propTranDtl->saf_demand_id;

                    //         if ($safDemandId) {
                    //             $safDemandDtl =  PropSafsDemand::where('id', $safDemandId)->first();
                    //             PropSafsDemand::where('id', $safDemandId)
                    //                 ->update(
                    //                     [
                    //                         'paid_status' => $applicationPaymentStatus,
                    //                         'balance' => $safDemandDtl->total_tax - $safDemandDtl->adjust_amt,
                    //                     ]
                    //                 );
                    //         }

                    //         if ($propDemandId) {
                    //             $propDemandDtl =  PropDemand::where('id', $propDemandId)->first();
                    //             PropDemand::where('id', $propDemandId)
                    //                 ->update(
                    //                     [
                    //                         'paid_status' => $applicationPaymentStatus,
                    //                         'balance' => $propDemandDtl->total_tax - $propDemandDtl->adjust_amt,
                    //                     ]
                    //                 );
                    //             $property = PropProperty::find($transaction->property_id);
                    //             if (collect($property)->isEmpty())
                    //                 throw new Exception("Property Not Available");
                    //             $property->balance = $transaction->arrear_settled_amt;
                    //             $property->save();
                    //         }
                    //     }
                    // }

                    // if ($transaction->is_arrear_settled) {
                    //     if ($transaction->tran_type == 'Property') {
                    //         $property = PropProperty::find($transaction->property_id);
                    //         if (collect($property)->isEmpty())
                    //             throw new Exception("Property Not Found");
                    //         $property->balance = $transaction->arrear_settled_amt;
                    //         $property->save();
                    //     }
                    // }
                }

                $request->merge([
                    'id' => $mChequeDtl->id,
                    'paymentMode' => $transaction->payment_mode,
                    'transactionNo' => $transaction->tran_no,
                    'transactionAmount' => $transaction->amount,
                    'transactionDate' => $transaction->tran_date,
                    'wardId' => $wardId,
                    'chequeNo' => $mChequeDtl->cheque_no,
                    'branchName' => $mChequeDtl->branch_name,
                    'bankName' => $mChequeDtl->bank_name,
                    'clearanceDate' => $mChequeDtl->clear_bounce_date,
                    'bounceReason' => $mChequeDtl->remarks,
                    'chequeDate' => $mChequeDtl->cheque_date,
                    'moduleId' => $propertyModuleId,
                    'ulbId' => $ulbId,
                    'userId' => $userId,
                ]);

                // return $request;
                $mPaymentReconciliation->addReconcilation($request);
            }

            # For Water module 
            if ($moduleId == $waterModuleId) {

                # Find Cheque details 
                $mChequeDtl = WaterChequeDtl::find($request->chequeId);
                $mChequeDtl->status             = $paymentStatus;
                $mChequeDtl->clear_bounce_date  = $request->clearanceDate;
                $mChequeDtl->bounce_amount      = $request->cancellationCharge;
                $mChequeDtl->remarks            = $request->remarks;
                $mChequeDtl->save();

                $transaction = WaterTran::where('id', $mChequeDtl->transaction_id)
                    ->first();
                WaterTran::where('id', $mChequeDtl->transaction_id)
                    ->update(
                        [
                            'verify_status' => $paymentStatus,
                            'verified_date' => Carbon::now(),
                            'verified_by'   => $userId
                        ]
                    );

                # If the transaction bounce
                if ($paymentStatus == 3) {
                    // $waterDeactivateTran = new WaterTranDeactivate($transaction->id);
                    // $waterDeactivateTran->deactivate(); 
                    
                    $waterTranDtls = WaterTranDetail::where('tran_id', $transaction->id)
                        ->where('status', '<>', 0)
                        ->get();
                    $demandIds = $waterTranDtls->pluck('demand_id');

                    # For demand payment 
                    if ($transaction->tran_type == 'Demand Collection') {
                        # Map every demand data 
                        $waterTranDtls->map(function ($values, $key)
                        use ($applicationPaymentStatus, $transaction) {
                            $conumserDemand = WaterConsumerDemand::where('id', $values->demand_id)->first();
                            $conumserDemand->update(
                                [
                                    'paid_status'           => $applicationPaymentStatus,
                                    'is_full_paid'          => false,
                                    'due_balance_amount'    => (($conumserDemand->due_balance_amount ?? 0) + ($values->paid_amount ?? 0))
                                ]
                            );

                            # Update the transaction details 
                            $values->update([
                                'status'     => $applicationPaymentStatus,
                                'updated_at' => Carbon::now()
                            ]);
                        });

                        # Update water consumer collection details 
                        WaterConsumerCollection::where('transaction_id', $transaction->id)
                            ->update([
                                "status" => $applicationPaymentStatus
                            ]);
                        // $wardId = WaterConsumer::find($transaction->related_id)->ward_mstr_id;
                    }

                    # ❗❗❗ Unfinished code For application payment ❗❗❗
                    if ($transaction->tran_type != 'Demand Collection') {
                        WaterApplication::where('id', $mChequeDtl->application_id)
                            ->update(
                                [
                                    'payment_status' => $applicationPaymentStatus
                                ]
                            );
                        $connectionChargeDtl =  WaterConnectionCharge::find($demandIds);
                        WaterConnectionCharge::whereIn('id', $demandIds)
                            ->update(
                                [
                                    'paid_status' => $applicationPaymentStatus
                                ]
                            );

                        WaterApplication::where('id', $connectionChargeDtl->application_id)
                            ->update(
                                [
                                    'payment_status' => $applicationPaymentStatus,

                                ]
                            );

                        //after penalty resolved
                        WaterPenaltyInstallment::where('related_demand_id', $demandIds)
                            ->update(
                                [
                                    'paid_status' => $applicationPaymentStatus
                                ]
                            );
                        // $wardId = WaterApplication::find($transaction->related_id)->ward_id;
                    }
                }
 
                # If the payment got clear
                if ($paymentStatus == 1) {
                    # For demand payment 
                    if ($transaction->tran_type == 'Demand Collection') {
                        # Update consumer demand
                        $waterTranDtls = WaterTranDetail::where('tran_id', $transaction->id)
                            ->where('status', '<>', 0)
                            ->get();
                        $demandIds = $waterTranDtls->pluck('demand_id');
                        WaterConsumerDemand::whereIn('id', $demandIds)
                            ->update([
                                "paid_status" => $paymentStatus
                            ]);
                    }
                    # ❗❗❗ Unfinished section
                    if ($transaction->tran_type != 'Demand Collection') {
                    }
                }

                $request->merge([
                    'id' => $mChequeDtl->id,
                    'paymentMode' => $transaction->payment_mode,
                    'transactionNo' => $transaction->tran_no,
                    'transactionAmount' => $transaction->amount,
                    'transactionDate' => $transaction->tran_date,
                    // 'wardId' => $wardId,
                    'chequeNo' => $mChequeDtl->cheque_no,
                    'branchName' => $mChequeDtl->branch_name,
                    'bankName' => $mChequeDtl->bank_name,
                    'clearanceDate' => $mChequeDtl->clear_bounce_date,
                    'bounceReason' => $mChequeDtl->remarks,
                    'chequeDate' => $mChequeDtl->cheque_date,
                    'moduleId' => $waterModuleId,
                    'ulbId' => $ulbId,
                    'userId' => $userId,
                ]);

                // return $request;
                $mPaymentReconciliation->addReconcilation($request);
            }

            if ($moduleId == $tradeModuleId) {
                $mChequeDtl =  TradeChequeDtl::find($request->chequeId);

                $mChequeDtl->status = $paymentStatus;
                $mChequeDtl->clear_bounce_date = $request->clearanceDate;
                $mChequeDtl->bounce_amount = $request->cancellationCharge;
                $mChequeDtl->remarks = $request->remarks;
                $mChequeDtl->save();

                $transaction = TradeTransaction::where('id', $mChequeDtl->tran_id)
                    ->first();

                TradeTransaction::where('id', $mChequeDtl->tran_id)
                    ->update(
                        [
                            'is_verified' => 1,
                            'verify_date' => Carbon::now(),
                            // 'verify_by' => $userId,
                            'status' => $paymentStatus,
                        ]
                    );


                //  Update in trade applications
                $application = ActiveTradeLicence::find($mChequeDtl->temp_id);
                if (!$application) {
                    $application = TradeLicence::find($mChequeDtl->temp_id);
                }
                if (!$application) {
                    throw new Exception("Application Not Found");
                }
                $application->payment_status = $applicationPaymentStatus;
                $application->update();
                $wardId = $application->ward_id;
                // ActiveTradeLicence::where('id', $transaction->temp_id)
                //     ->update(
                //         ['payment_status' => $applicationPaymentStatus]
                //     );

                // $wardId = ActiveTradeLicence::find($mChequeDtl->temp_id)->ward_id;

                $request->merge([
                    'id' => $mChequeDtl->id,
                    'paymentMode' => $transaction->payment_mode,
                    'transactionNo' => $transaction->tran_no,
                    'transactionAmount' => $transaction->paid_amount,
                    'transactionDate' => $transaction->tran_date,
                    'wardId' => $wardId,
                    'chequeNo' => $mChequeDtl->cheque_no,
                    'branchName' => $mChequeDtl->branch_name,
                    'bankName' => $mChequeDtl->bank_name,
                    'clearanceDate' => $mChequeDtl->clear_bounce_date,
                    'chequeDate' => $mChequeDtl->cheque_date,
                    'moduleId' => $tradeModuleId,
                    // 'ulbId' => $ulbId,
                    // 'userId' => $userId,
                ]);

                // return $request;
                $mPaymentReconciliation->addReconcilation($request);
            }
            DB::commit();
            DB::connection('pgsql_master')->commit();
            DB::connection('pgsql_water')->commit();
            DB::connection('pgsql_trade')->commit();
            return responseMsg(true, "Data Updated!", '');
        } catch (Exception $error) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            DB::connection('pgsql_water')->rollBack();
            DB::connection('pgsql_trade')->rollBack();
            return responseMsg(false, "ERROR!", $error->getMessage());
        }
    }

    /**
     * | tran deactive search
     */
    public function searchTransactionNo(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "transactionNo" => "required",
            "tranType" => "required|In:Property,Water,Trade"
        ]);

        if ($validator->fails())
            return validationError($validator);
        try {
            if ($req->tranType == "Property") {
                $mPropTransaction = new PropTransaction();
                $transactionDtl = $mPropTransaction->getTransByTranNo($req->transactionNo);
            }
            if ($req->tranType == "Water") {
                $mWaterTransaction = new WaterTran();
                $transactionDtl = $mWaterTransaction->getTransByTranNO($req->transactionNo);
            }

            return responseMsgs(true, "Transaction No is", $transactionDtl, "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }

    /**
     * | Required @param Request
     * | Required @return 
     */
    public function deactivateTransaction(Request $req)
    {
        $validator = Validator::make($req->all(), [
            "id" => "required|integer",                         // Transaction ID
            "moduleId" => "required|integer"

        ]);
        if ($validator->fails())
            return validationError($validator);

        try {
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $docUpload = new DocUpload;
            $document = $req->document;
            $refImageName = $req->id . "_" . $req->moduleId . "_" . (Carbon::now()->format("Y-m-d"));
            $relativePath = $req->moduleId == $propertyModuleId ? "Property/TranDeactivate" : ($req->moduleId == $waterModuleId ? "Water/TranDeactivate" : ($req->moduleId == $tradeModuleId ? "Trade/TranDeactivate" : "Others/TranDeactivate"));
            $user = Auth()->user();
            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            DB::connection('pgsql_water')->beginTransaction();
            DB::connection('pgsql_trade')->beginTransaction();

            $imageName = $req->document ? $relativePath . "/" . $docUpload->upload($refImageName, $document, $relativePath) : "";
            $deactivationArr = [
                "tran_id" => $req->id,
                "deactivated_by" => $user->id,
                "reason" => $req->remarks,
                "file_path" => $imageName,
                "deactive_date" => $req->deactiveDate ?? Carbon::now()->format("Y-m-d"),
            ];
            #_For Property Transaction Deactivation
            if ($req->moduleId == $propertyModuleId) {
                $deactivateTran = new DeactivateTran($req->id);                 // Property or Saf Deactivate Transaction
                $deactivateTran->deactivate();
                $propTranDeativetion = new PropTransactionDeactivateDtl();
                $propTranDeativetion->create($deactivationArr);
            }

            #_For Water Transaction Deactivation
            if ($req->moduleId == $waterModuleId) {
                // $waterDeactivateTran = new WaterTranDeactivate($req->id);
                // $waterDeactivateTran->deactivate();
                $waterReq = new Request([
                    "transactionId" => $req->id
                ]);
                $cWaterPaymentController = new WaterPaymentController();
                $cWaterPaymentController->transactionDeactivation($waterReq);
                $waterTranDeativetion = new WaterTransactionDeactivateDtl();
                $waterTranDeativetion->create($deactivationArr);
            }

            #_For Trade Transaction Deactivation
            if ($req->moduleId == $tradeModuleId) {
                $tradeTrans = TradeTransaction::find($req->id);
                $tradeTranDeativetion = new TradeTransactionDeactivateDtl();
                $tradeTranDeativetion->create($deactivationArr);
                if (!$tradeTrans) {
                    throw new Exception("Trade Transaction Not Available");
                }
                if (!$tradeTrans->is_verified) {
                    throw new Exception("Transaction Verified");
                }
                $application = ActiveTradeLicence::find($tradeTrans->temp_id);
                if (!$application) {
                    $application = TradeLicence::find($tradeTrans->temp_id);
                }
                if (!$application) {
                    throw new Exception("Application Not Found");
                }
                if (!in_array(Str::upper($tradeTrans->payment_mode), ['ONLINE', 'ONL', 'CASH'])) {
                    $propChequeDtl = TradeChequeDtl::where('tran_id', $tradeTrans->id)->first();
                    $propChequeDtl->status = 0;
                    $propChequeDtl->update();
                }
                $application->payment_status = 0;
                $tradeTrans->status = 0;
                $tradeTrans->update();
                $application->update();
            }

            DB::commit();
            DB::connection('pgsql_master')->commit();
            DB::connection('pgsql_water')->commit();
            DB::connection('pgsql_trade')->commit();
            return responseMsgs(true, "Transaction Deactivated", "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            DB::connection('pgsql_water')->rollBack();
            DB::connection('pgsql_trade')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "", 01, responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}
