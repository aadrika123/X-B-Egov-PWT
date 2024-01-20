<?php

namespace App\Http\Controllers;

use App\Models\ActiveCitizen;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Trade\TradeLicence;
use App\Models\Water\WaterApprovalApplicant;
use App\Models\Water\WaterConsumer;
use App\Pipelines\CareTakers\CareTakeProperty;
use App\Pipelines\CareTakers\CareTakerTrade;
use App\Pipelines\CareTakers\TagProperty;
use App\Pipelines\CareTakers\TagTrade;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * | Created On-19-04-2022 
 * | Created By-Mrinal Kumar
 */

class CaretakerController extends Controller
{
    /**
     * | Send otp for caretaker property
     */
    public function waterCaretakerOtp(Request $req)
    {
        try {
            $user                       = authUser($req);
            $userId                     = $user->id;
            $mWaterApprovalApplicant    = new WaterApprovalApplicant();
            $ThirdPartyController       = new ThirdPartyController();
            $mActiveCitizenUndercare    = new ActiveCitizenUndercare();
            $mWaterConsumer             = new WaterConsumer();

            $waterDtl = $mWaterConsumer->getConsumerByNo($req->consumerNo);
            if (!isset($waterDtl))
                throw new Exception('Water Connection Not Found!');

            if (!$waterDtl->apply_connection_id)
                throw new Exception("application details not found!");

            if ($waterDtl->user_id == $user->id && $waterDtl->user_type == 1)
                throw new Exception("cannote undertake your own connection!");

            $existingData = $mActiveCitizenUndercare->getDetailsForUnderCare($userId, $waterDtl->id);
            if (!is_null($existingData))
                throw new Exception("ConsumerNo caretaker already exist!");

            $approveApplicant = $mWaterApprovalApplicant->getOwnerDtlById($waterDtl->apply_connection_id);
            if (is_null($approveApplicant->mobile_no) || is_null($approveApplicant))
                throw new Exception("Mobile no for respective consumer not found! or application details not found!");

            $applicantMobile = $approveApplicant->mobile_no;
            $myRequest = new \Illuminate\Http\Request();
            $myRequest->setMethod('POST');
            $myRequest->request->add([
                'mobileNo' => $applicantMobile,
                "userId" => $userId
            ]);
            $otpResponse = $ThirdPartyController->sendOtp($myRequest);

            $response = collect($otpResponse)->toArray();
            $data = [
                'otp' => $response['original']['data'],
                'mobileNo' => $applicantMobile
            ];
            return responseMsgs(true, "OTP send successfully", $data, '', '01', '623ms', 'Post', '');
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Care taker property tag
     */
    public function caretakerConsumerTag(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'consumerNo' => 'required|max:255',
                'otp' => 'required|digits:6'
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $userId                     = authUser($req)->id;
            $mWaterApprovalApplicant    = new WaterApprovalApplicant();
            $mActiveCitizenUndercare    = new ActiveCitizenUndercare();
            $mWaterConsumer             = new WaterConsumer();
            $ThirdPartyController       = new ThirdPartyController();

            DB::beginTransaction();
            $waterDtl = $mWaterConsumer->getConsumerByNo($req->consumerNo);
            if (!isset($waterDtl))
                throw new Exception('Water Connection Not Found!');

            if (!$waterDtl->apply_connection_id)
                throw new Exception('Application details not found!');

            $approveApplicant = $mWaterApprovalApplicant->getOwnerDtlById($waterDtl->apply_connection_id);
            $myRequest = new \Illuminate\Http\Request();
            $myRequest->setMethod('POST');
            $myRequest->request->add(['mobileNo' => $approveApplicant->mobile_no]);
            $myRequest->request->add(['otp' => $req->otp]);
            $otpReturnData = $ThirdPartyController->verifyOtp($myRequest);
            $verificationStatus = collect($otpReturnData)['original']['status'];
            if ($verificationStatus == false)
                throw new Exception("otp Not Validated!");

            $existingData = $mActiveCitizenUndercare->getDetailsForUnderCare($userId, $waterDtl->id);
            if (!is_null($existingData))
                throw new Exception("ConsumerNo caretaker already exist!");

            $mActiveCitizenUndercare->saveCaretakeDetails($waterDtl->id, $approveApplicant->mobile_no, $userId);
            DB::commit();
            return responseMsgs(true, "Cosumer Succesfully Attached!", '', '', '01', '623ms', 'Post', '');
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }


    /**
     * | Master CareTaking for all modules
     */
    public function careTakeModules(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                'moduleId' => 'required|integer',
                'referenceNo' => 'required'
            ]
        );
        if ($validated->fails()) {
            return validationError($validated);
        }

        try {
            $data = array();
            $response = app(Pipeline::class)
                ->send($data)
                ->through([
                    TagProperty::class,
                    TagTrade::class
                ])
                ->thenReturn();
            return responseMsgs(true, $response, [], '1001', '1.0', "", 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '1001', '1.0', "", 'POST', $req->deviceId);
        }
    }

    /**
     * | Master caretaking for all module
     */
    public function careTakeOtp(Request $req)
    {
        try {
            $req->validate([
                'moduleId' => 'required|integer',
                'referenceNo' => 'required'
            ]);
            $data = array();
            $response = app(Pipeline::class)
                ->send($data)
                ->through([
                    CareTakeProperty::class,
                    CareTakerTrade::class
                ])
                ->thenReturn();
            return responseMsgs(true, $response, [], '01', '1.0', "", 'POST', $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], '01', '1.0', "", 'POST', $req->deviceId);
        }
    }
}
