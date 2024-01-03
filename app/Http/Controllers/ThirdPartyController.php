<?php

namespace App\Http\Controllers;

use App\MicroServices\IdGeneration;
use App\Models\ActiveCitizen;
use App\Models\OtpMaster;
use App\Models\OtpRequest;
use App\Models\User;
use Carbon\Carbon;
use Seshac\Otp\Otp;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use WpOrg\Requests\Auth;

class ThirdPartyController extends Controller
{
    // OTP related Operations


    /**
     * | Send OTP for Use
     * | OTP for Changing PassWord using the mobile no 
     * | @param request
     * | @var 
     * | @return 
        | Serial No : 01
        | Working
        | Dont share otp 
     */
    public function sendOtp(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'mobileNo' => "required|digits:10|regex:/[0-9]{10}/", #exists:active_citizens,mobile|
                'type' => "nullable|in:Register,Forgot,Attach Holding,Update Mobile",
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $refIdGeneration = new IdGeneration();
            $mOtpRequest = new OtpRequest();
            $mobileNo    =  $request->mobileNo;
            if ($request->type == "Register") {
                $userDetails = ActiveCitizen::where('mobile', $request->mobileNo)
                    ->first();
                if ($userDetails) {
                    throw new Exception("Mobile no $request->mobileNo is registered to An existing account!");
                }
            }
            if ($request->type == "Forgot") {
                $userDetails = ActiveCitizen::where('mobile', $request->mobileNo)
                    ->first();
                if (!$userDetails) {
                    throw new Exception("Please Check Your Mobile No!");
                }
            }
            $generateOtp = $this->generateOtp();
            $otpType     = $request->type == "Forgot" ? "Forgot Password" : $request->type;
            $sms         = "OTP for " . $otpType . " at Akola Municipal Corporation's portal is " . $generateOtp . ". This OTP is valid for 10 minutes.";

            $response = send_sms($mobileNo, $sms, 1707170367857263583);
            $mOtpRequest->saveOtp($request, $generateOtp);

            return responseMsgs(true, "OTP send to your mobile No!", $generateOtp, "", "01", ".ms", "POST", "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "0101", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Verify OTP 
     * | Check OTP and Create a Token
     * | @param request
        | Serial No : 02
        | Working
     */
    public function verifyOtp(Request $request)
    {
        $validated = Validator::make(
            $request->all(),
            [
                'otp' => "required|digits:6",
                'mobileNo' => "required|digits:10|regex:/[0-9]{10}/|exists:otp_requests,mobile_no"
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            # model
            $mOtpMaster     = new OtpRequest();
            $mActiveCitizen = new ActiveCitizen();

            # logi 
            DB::beginTransaction();
            $checkOtp = $mOtpMaster->checkOtp($request);
            if (!$checkOtp) {
                $msg = "OTP not match!";
                return responseMsgs(false, $msg, "", "", "01", ".ms", "POST", "");
            }
            $token = $mActiveCitizen->changeToken($request);
            $checkOtp->delete();
            DB::commit();
            return responseMsgs(true, "OTP Validated!", remove_null($token), "", "01", ".ms", "POST", "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Generate Random OTP 
     */
    public function generateOtp()
    {
        $otp = str_pad(Carbon::createFromDate()->milli . random_int(100, 999), 6, 0);
        // $otp = 123123;
        return $otp;
    }
}
