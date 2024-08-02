<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\MicroServices\IdGenerator\PrefixIdGenerator;
use App\Models\Payment\RevDailycollection;
use App\Models\Payment\RevDailycollectiondetail;
use App\Models\Payment\TempTransaction;
use App\Models\Property\PropChequeDtl;
use App\Models\Property\PropTransaction;
use App\Models\Trade\TradeChequeDtl;
use App\Models\Trade\TradeTransaction;
use App\Models\UlbMaster;
use App\Models\Water\WaterChequeDtl;
use App\Models\Water\WaterTran;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On-31-01-2023 
 * | Created by-Mrinal Kumar
 * | Payment Cash Verification
 */

class CashVerificationController extends Controller
{
    /**
     * | Unverified Cash Verification List
     * | Serial : 1
     */
    public function cashVerificationList(Request $request)
    {
        try {
            $ulbId =  authUser($request)->ulb_id;
            $userId =  $request->id;
            $date = date('Y-m-d', strtotime($request->date));
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $mTempTransaction =  new TempTransaction();
            $zoneId = $request->zone;
            $wardId = $request->wardId;

            $data = $mTempTransaction->transactionDtl($date, $ulbId);
            if ($userId) {
                $data = $data->where('user_id', $userId);
            }
            if ($zoneId) {
                $data = $data->where('ulb_ward_masters.zone', $zoneId);
            }
            if ($wardId) {
                $data = $data->where('ulb_ward_masters.id', $wardId);
            }
            $data = $data->get();

            $collection = collect($data->groupBy("id")->all());

            $data = $collection->map(function ($val) use ($date, $propertyModuleId, $waterModuleId, $tradeModuleId) {
                $total =  $val->sum('amount');
                $prop  = $val->where("module_id", $propertyModuleId)->sum('amount');
                $water = $val->where("module_id", $waterModuleId)->sum('amount');
                $trade = $val->where("module_id", $tradeModuleId)->sum('amount');
                return [
                    "id" => $val[0]['id'],
                    "user_name" => $val[0]['name'],
                    "property" => $prop,
                    "water" => $water,
                    "trade" => $trade,
                    "total" => $total,
                    "date" => Carbon::parse($date)->format('d-m-Y'),
                    // "verified_amount" => 0,
                ];
            });

            $data = (array_values(objtoarray($data)));

            // $property = collect($data)->where('module_id', $propertyModuleId);
            // $water = collect($data)->where('module_id', $waterModuleId);
            // $trade = collect($data)->where('module_id', $tradeModuleId);

            // $total['property'] =  collect($property)->map(function ($value) {
            //     return $value['amount'];
            // })->sum();

            // $total['water'] =  collect($water)->map(function ($value) {
            //     return $value['amount'];
            // })->sum();

            // $total['trade'] =  collect($trade)->map(function ($value) {
            //     return $value['amount'];
            // })->sum();

            // $total['total'] = collect($total)->sum();
            // $total['date'] = $date;

            return responseMsgs(true, "List cash Verification", $data, "010201", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    /**
     * | Verified Cash Verification List
     * | Serial : 2
     */
    public function verifiedCashVerificationList(Request $req)
    {
        try {
            $date = date('Y-m-d', strtotime($req->date));
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');

            $revDailycollection =  RevDailycollection::select('users.id', 'collection_id', 'name', 'deposit_amount', 'module_id', 'tran_no', 'rev_dailycollections.status')
                ->join('rev_dailycollectiondetails as rdc', 'rdc.collection_id', 'rev_dailycollections.id')
                ->join('users', 'users.id', 'rev_dailycollections.tc_id')
                ->groupBy('users.id', 'collection_id', 'name', 'rdc.deposit_amount', 'module_id', 'tran_no', 'rev_dailycollections.status')
                ->where('rev_dailycollections.status', 1)
                ->where('deposit_date', $date)
                ->get();
            $collection = collect($revDailycollection->groupBy("collection_id")->all());

            $data = $collection->map(function ($val) use ($date, $propertyModuleId, $waterModuleId, $tradeModuleId) {
                $total =  $val->sum('deposit_amount');
                $prop = $val->where("module_id", $propertyModuleId)->sum('deposit_amount');
                $water = $val->where("module_id", $waterModuleId)->sum('deposit_amount');
                $trade = $val->where("module_id", $tradeModuleId)->sum('deposit_amount');
                return [
                    "id" => $val[0]['id'],
                    "user_name" => $val[0]['name'],
                    "property" => $prop,
                    "water" => $water,
                    "trade" => $trade,
                    "total" => $total,
                    "date" => Carbon::parse($date)->format('d-m-Y'),
                    "verifyStatus" => true,
                    "tranNo" => $val[0]['tran_no'],
                ];
            });

            $data = (array_values(objtoarray($data)));

            return responseMsgs(true, "TC Collection", remove_null($data), "010201", "1.0", "", "POST", $req->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $req->deviceId ?? "");
        }
    }

    /**
     * | Tc Collection Dtl
     * | Serial : 3
     */
    public function tcCollectionDtl(Request $request)
    {
        try {
            $request->validate([
                "date" => "required|date",
                "userId" => "required|numeric",

            ]);
            $userId =  $request->userId;
            $ulbId =  authUser($request)->ulb_id;
            $date = date('Y-m-d', strtotime($request->date));
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $mTempTransaction = new TempTransaction();
            $details = $mTempTransaction->transactionList($date, $userId, $ulbId);
            if ($details->isEmpty())
                throw new Exception("No Application Found for this id");

            $data['property'] = collect($details)->where('module_id', $propertyModuleId)->values();
            $data['water'] = collect($details)->where('module_id', $waterModuleId)->values();
            $data['trade'] = collect($details)->where('module_id', $tradeModuleId)->values();
            $data['Cash'] = collect($details)->where('payment_mode', '=', 'CASH')->sum('amount');
            $data['Cheque'] = collect($details)->where('payment_mode', '=', 'CHEQUE')->sum('amount');
            $data['DD'] = collect($details)->where('payment_mode', '=', 'DD')->sum('amount');
            $data['Neft'] = collect($details)->where('payment_mode', '=', 'NEFT')->sum('amount');
            // $data['RTGS'] = collect($details)->where('payment_mode', '=', 'RTGS')->first()->amount;
            $data['totalAmount'] =  $details->sum('amount');
            $data['numberOfTransaction'] =  $details->count();
            $data['collectorName'] =  collect($details)[0]->user_name;
            $data['date'] = Carbon::parse($date)->format('d-m-Y');
            $data['verifyStatus'] = false;

            return responseMsgs(true, "TC Collection", remove_null($data), "010201", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    /**
     * | Verified tc collection
     * | Serial : 4
     */
    public function verifiedTcCollectionDtl(Request $request)
    {
        try {
            $validated = Validator::make(
                $request->all(),
                ['tranNo' => 'required|string']
            );
            if ($validated->fails()) {
                return validationError($validated);
            }

            $userId =  $request->userId;
            $ulbId =  authUser($request)->ulb_id;
            $date = date('Y-m-d', strtotime($request->date));
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');

            $mRevDailycollection = new RevDailycollection();
            $details = $mRevDailycollection->collectionDetails($ulbId)
                // ->where('deposit_date', $date)
                // ->where('tc_id', $userId)
                ->where('tran_no', $request->tranNo)
                ->get();

            if ($details->isEmpty())
                throw new Exception("No Application Found for this id");

            $data['property'] = collect($details)->where('module_id', $propertyModuleId)->values();
            $data['water'] = collect($details)->where('module_id', $waterModuleId)->values();
            $data['trade'] = collect($details)->where('module_id', $tradeModuleId)->values();

            $data['Cash'] = collect($details)->where('payment_mode', 'CASH')->sum('amount');
            $data['Cheque'] = collect($details)->where('payment_mode', 'CHEQUE')->sum('amount');
            $data['DD'] = collect($details)->where('payment_mode', 'DD')->sum('amount');

            $data['totalAmount'] =  $details->sum('amount');
            $data['numberOfTransaction'] =  $details->count();
            $data['collectorName']   =  collect($details)[0]->tc_name;
            $data['collectorMobile'] =  collect($details)[0]->tc_mobile;
            $data['verifierName']    =  collect($details)[0]->verifier_name;
            $data['verifierMobile']  =  collect($details)[0]->verifier_mobile;
            $data['tranNo']  =  collect($details)[0]->tran_no;
            $data['verifyStatus']    =  true;
            $data['date'] = Carbon::parse($date)->format('d-m-Y');

            return responseMsgs(true, "TC Collection", remove_null($data), "010201", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }

    /**
     * | For Verification of cash
     * | serial : 5
     */
    public function cashVerify(Request $request)
    {
        try {
            $user = authUser($request);
            $userId = $user->id;
            $ulbId = $user->ulb_id;
            $property =  $request->property;
            $water    =  $request->water;
            $trade    =  $request->trade;
            $mRevDailycollection = new RevDailycollection();
            $cashParamId = Config::get('PropertyConstaint.CASH_VERIFICATION_PARAM_ID');

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            DB::connection('pgsql_water')->beginTransaction();
            DB::connection('pgsql_trade')->beginTransaction();
            $idGeneration = new PrefixIdGenerator($cashParamId, $ulbId);
            $tranNo = $idGeneration->generate();

            if ($property) {
                $tempTranDtl = TempTransaction::find($property[0]);
                $tranDate = $tempTranDtl['tran_date'];
                $tcId = $tempTranDtl['user_id'];
                $mReqs = new Request([
                    "tran_no" => $tranNo,
                    "user_id" => $userId,
                    "demand_date" => $tranDate,
                    "deposit_date" => Carbon::now(),
                    "ulb_id" => $ulbId,
                    "tc_id" => $tcId,
                ]);
                $collectionId =  $mRevDailycollection->store($mReqs);

                foreach ($property as $item) {

                    $tempDtl = TempTransaction::find($item);
                    $tranId =  $tempDtl->transaction_id;

                    PropTransaction::where('id', $tranId)
                        ->update(
                            [
                                'verify_status' => 1,
                                'verify_date' => Carbon::now(),
                                'verified_by' => $userId
                            ]
                        );
                    $this->dailyCollectionDtl($tempDtl, $collectionId);
                    if (!$tempDtl)
                        throw new Exception("No Transaction Found for this id");

                    $logTrans = $tempDtl->replicate();
                    $logTrans->setTable('log_temp_transactions');
                    $logTrans->id = $tempDtl->id;
                    $logTrans->save();
                    $tempDtl->delete();
                }
            }

            if ($water) {
                $tempTranDtl = TempTransaction::find($water[0]);
                $tranDate = $tempTranDtl['tran_date'];
                $tcId = $tempTranDtl['user_id'];
                $mReqs = new Request([
                    "tran_no" => $tranNo,
                    "user_id" => $userId,
                    "demand_date" => $tranDate,
                    "deposit_date" => Carbon::now(),
                    "ulb_id" => $ulbId,
                    "tc_id" => $tcId,
                ]);
                $collectionId =  $mRevDailycollection->store($mReqs);

                foreach ($water as $item) {

                    $tempDtl = TempTransaction::find($item);
                    $tranId =  $tempDtl->transaction_id;

                    WaterTran::where('id', $tranId)
                        ->update(
                            [
                                'verify_status' => 1,
                                'verified_date' => Carbon::now(),
                                'verified_by' => $userId
                            ]
                        );
                    $this->dailyCollectionDtl($tempDtl, $collectionId);
                    if (!$tempDtl)
                        throw new Exception("No Transaction Found for this id");

                    $logTrans = $tempDtl->replicate();
                    $logTrans->setTable('log_temp_transactions');
                    $logTrans->id = $tempDtl->id;
                    $logTrans->save();
                    $tempDtl->delete();
                }
            }

            if ($trade) {
                $tempTranDtl = TempTransaction::find($trade[0]);
                $tranDate = $tempTranDtl['tran_date'];
                $tcId = $tempTranDtl['user_id'];
                $mReqs = new Request([
                    "tran_no" => $tranNo,
                    "user_id" => $userId,
                    "demand_date" => $tranDate,
                    "deposit_date" => Carbon::now(),
                    "ulb_id" => $ulbId,
                    "tc_id" => $tcId,
                ]);
                $collectionId =  $mRevDailycollection->store($mReqs);

                foreach ($trade as $item) {

                    $tempDtl = TempTransaction::find($item);
                    $tranId =  $tempDtl->transaction_id;

                    TradeTransaction::where('id', $tranId)
                        ->update(
                            [
                                'is_verified' => 1,
                                'verify_date' => Carbon::now(),
                                'verify_by' => $userId
                            ]
                        );
                    $this->dailyCollectionDtl($tempDtl, $collectionId);
                    if (!$tempDtl)
                        throw new Exception("No Transaction Found for this id");

                    $logTrans = $tempDtl->replicate();
                    $logTrans->setTable('log_temp_transactions');
                    $logTrans->id = $tempDtl->id;
                    $logTrans->save();
                    $tempDtl->delete();
                }
            }
            DB::commit();
            DB::connection('pgsql_master')->commit();
            DB::connection('pgsql_water')->commit();
            DB::connection('pgsql_trade')->commit();
            return responseMsgs(true, "Cash Verified", '', "010201", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            DB::connection('pgsql_water')->rollBack();
            DB::connection('pgsql_trade')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }


    /**
     * | serial : 5.1
     */
    public function dailyCollectionDtl($tranDtl, $collectionId)
    {
        $RevDailycollectiondetail = new RevDailycollectiondetail();
        $mReqs = new Request([
            "collection_id" => $collectionId,
            "module_id" => $tranDtl['module_id'],
            "demand" => $tranDtl['amount'],
            "deposit_amount" => $tranDtl['amount'],
            "cheq_dd_no" => $tranDtl['cheque_dd_no'],
            "bank_name" => $tranDtl['bank_name'],
            "deposit_mode" => strtoupper($tranDtl['payment_mode']),
            "application_no" => $tranDtl['application_no'],
            "transaction_id" => $tranDtl['id']
        ]);
        $RevDailycollectiondetail->store($mReqs);
    }

    /**
     * | Cash Verification Receipt
     */
    public function cashReceipt(Request $request)
    {
        $request->validate([
            'receiptNo' => 'required'
        ]);
        try {
            $ulbId = authUser($request)->ulb_id;
            $mUlbMasters = new UlbMaster();
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');

            $mRevDailycollection = new RevDailycollection();
            $details = $mRevDailycollection->collectionDetails($ulbId)
                ->where('rev_dailycollections.tran_no', $request->receiptNo)
                ->get();

            if ($details->isEmpty())
                throw new Exception("No Application Found for this id");

            $data['property'] = collect($details)->where('module_id', $propertyModuleId)->values();
            $data['water']    = collect($details)->where('module_id', $waterModuleId)->values();
            $data['trade']    = collect($details)->where('module_id', $tradeModuleId)->values();

            $data['Cash']   = collect($details)->where('payment_mode', 'CASH')->sum('amount');
            $data['Cheque'] = collect($details)->where('payment_mode', 'CHEQUE')->sum('amount');
            $data['DD']     = collect($details)->where('payment_mode', 'DD')->sum('amount');

            $data['totalAmount'] =  $details->sum('amount');
            $data['numberOfTransaction'] =  $details->count();
            $data['collectorName']       =  collect($details)[0]->tc_name;
            $data['collectorMobile']     =  collect($details)[0]->tc_mobile;
            $data['verifierName']        =  collect($details)[0]->verifier_name;
            $data['verifierMobile']      =  collect($details)[0]->verifier_mobile;
            $data['receiptNo']           =  collect($details)[0]->tran_no;
            $data['verificationDate']    =  collect($details)[0]->verification_date;
            $data['ulb']                 =  collect($details)[0]->ulb_name;
            $data['ulbDetails']          = $mUlbMasters->getUlbDetails($ulbId);

            return responseMsgs(true, "Cash Receipt", $data, "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        }
    }

    /**
     * | Edit Cheque No
       | Currently not in use
     */
    public function editChequeNo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'moduleId' => 'required|numeric',
            'chequeNo' => 'required',
            'bankName' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 401);
        }
        try {

            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $tranDtl = TempTransaction::find($request->id);
            $tranId = $tranDtl->transaction_id;

            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            DB::connection('pgsql_water')->beginTransaction();
            DB::connection('pgsql_trade')->beginTransaction();
            $tranDtl
                ->update(
                    [
                        'cheque_dd_no' => $request->chequeNo,
                        'bank_name' => $request->bankName,
                    ]
                );

            if ($request->moduleId == $propertyModuleId) {
                PropChequeDtl::where('transaction_id', $tranId)
                    ->update(
                        [
                            'cheque_no' => $request->chequeNo,
                            'bank_name' => $request->bankName,
                        ]
                    );
            }

            if ($request->moduleId == $waterModuleId) {
                WaterChequeDtl::where('transaction_id', $tranId)
                    ->update(
                        [
                            'cheque_no' => $request->chequeNo,
                            'bank_name' => $request->bankName
                        ]
                    );
            }

            if ($request->moduleId == $tradeModuleId) {
                TradeChequeDtl::where('tran_id', $tranId)
                    ->update(
                        [
                            'cheque_no' => $request->chequeNo,
                            'bank_name' => $request->bankName
                        ]
                    );
            }

            DB::commit();
            DB::connection('pgsql_master')->commit();
            DB::connection('pgsql_water')->commit();
            DB::connection('pgsql_trade')->commit();
            return responseMsgs(true, "Edit Successful", "", "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            DB::connection('pgsql_water')->rollBack();
            DB::connection('pgsql_trade')->rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        }
    }

    public function tranDeactivatedList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fromDate' => 'required|date',
            'uptoDate' => 'required|date',
            'moduleId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return validationError($validator);
        }
        try {
            $mUser = Auth()->user();
            $userId = $mUser->id ?? 2;
            $ulbId = $mUser->ulb_id ?? 2;
            $perPage = $request->perPage ?? 10;
            $moduleId = $request->moduleId;
            $fromDate = $request->fromDate . ' 00:00:00';
            $uptoDate = $request->uptoDate . ' 23:59:59';
            $propertyModuleId = Config::get('module-constants.PROPERTY_MODULE_ID');
            $waterModuleId = Config::get('module-constants.WATER_MODULE_ID');
            $tradeModuleId = Config::get('module-constants.TRADE_MODULE_ID');
            $mPropTransaction = new PropTransaction();

            switch ($moduleId) {
                case $propertyModuleId:
                    $data = PropTransaction::whereBetween('updated_at', [$fromDate, $uptoDate])
                        ->get();
                    break;

                case $waterModuleId:
                    # code...
                    break;

                case $tradeModuleId:
                    # code...
                    break;
            }

            return responseMsgs(true, "Deactivated Tran Detail", $data, "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010201", "1.0", responseTime(), "POST", $request->deviceId ?? "");
        }
    }
}
