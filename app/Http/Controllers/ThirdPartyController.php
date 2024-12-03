<?php

namespace App\Http\Controllers;

use App\MicroServices\IdGeneration;
use App\Models\ActiveCitizen;
use App\Models\OtpMaster;
use App\Models\OtpRequest;
use App\Models\TblSmsLog;
use App\Models\User;
use App\Traits\Ward;
use App\Traits\Water\WaterTrait;
use App\Traits\Workflow\Workflow;
use Carbon\Carbon;
use Seshac\Otp\Otp;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use WpOrg\Requests\Auth;

class ThirdPartyController extends Controller
{

    use Ward;
    use Workflow;
    use WaterTrait;

    private $_waterRoles;
    private $_waterModuleId;
    protected $_DB_NAME;
    protected $_DB;
    protected $_DB_MASTER;

    public function __construct()
    {
        $this->_waterRoles      = Config::get('waterConstaint.ROLE-LABEL');
        $this->_waterModuleId   = Config::get('module-constants.WATER_MODULE_ID');
        $this->_DB_NAME         = "pgsql_water";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_DB_MASTER           = DB::connection("pgsql_master");
    }

    /**
     * | Database transaction
     */
    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB_MASTER->getDatabaseName();
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
        $db3 = $this->_DB_MASTER->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB_MASTER->rollBack();
    }
    /**
     * | Database transaction
     */
    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_DB_MASTER->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_DB_MASTER->commit();
    }
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
                'type' => "nullable|in:Register,Forgot,Attach Holding,Update Mobile,Attached Connection",
            ]
        );
        if ($validated->fails())
            return validationError($validated);
        try {
            $refIdGeneration = new IdGeneration();
            $mOtpRequest = new OtpRequest();
            $mTblSmsLog = new TblSmsLog();
            $mobileNo   =  $request->mobileNo;
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

            switch ($request->type) {
                case ('Register'):
                    $otpType = 'Citizen Registration';
                    break;

                case ('Forgot'):
                    $otpType = 'Forgot Password';
                    break;

                case ('Attach Holding'):
                    $otpType = 'Attach Holding';
                    break;

                case ('Update Mobile'):
                    $otpType = 'Update Mobile';
                    break;
                case ('Attached Connection'):
                    $otpType = 'Attached Connection';
                    break;
                default:
                    throw new Exception("Invalid type provided");
            }

            $generateOtp = $this->generateOtp();
            $sms         = "OTP for " . $otpType . " at Akola Municipal Corporation's portal is " . $generateOtp . ". This OTP is valid for 10 minutes.";

            $response = send_sms($mobileNo, $sms, 1707170367857263583);
            $mOtpRequest->saveOtp($request, $generateOtp);

            $smsReqs = [
                "emp_id" => $request->userId ?? (auth()->user()->id) ?? 0,
                // "emp_id" => $request->userId ?? authUser($request)->id ?? 0,
                "ref_id" => isset($userDetails) ? $userDetails->id : 0,
                "ref_type" => 'Active Citizen',
                "mobile_no" => $mobileNo,
                "purpose" => "OTP for " . $otpType,
                "template_id" => 1707170367857263583,
                "message" => $sms,
                "response" => $response['status'],
                "smgid" => $response['msg'],
                "stampdate" => Carbon::now(),
            ];
            $mTblSmsLog->create($smsReqs);

            return responseMsgs(true, "OTP send to your mobile No!", "", "", "01", ".ms", "POST", "");
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

        // $validated = Validator::make(
        //     $request->all(),
        //     [
        //         'otp' => "required|digits:6",
        //         'mobileNo' => "required|digits:10|regex:/[0-9]{10}/|exists:otp_requests,mobile_no"
        //         ]
        //     );
        //     if ($validated->fails())
        //     return validationError($validated);
        try {
            # model
            $mOtpMaster     = new OtpRequest();
            $mActiveCitizen = new ActiveCitizen();
            # logi 
            $this->begin();
            $checkOtp = $mOtpMaster->checkOtp($request);
            if (!$checkOtp) {
                $msg = "OTP not match!";
                return responseMsgs(false, $msg, "", "", "01", ".ms", "POST", "");
            }
            // return $request;
            // $token = $mActiveCitizen->changeToken($request);
            $checkOtp->delete();
            $this->commit();
            return responseMsgs(true, "OTP Validated!",  "", "01", ".ms", "POST", "");   //   return responseMsgs(true, "OTP Validated!", remove_null($token), "", "01", ".ms", "POST", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", "");
        }
    }

    /**
     * | Generate Random OTP 
     */
    public function generateOtp()
    {
        $otp = str_pad(Carbon::createFromDate()->milli . random_int(100, 999), 6, 0); ///   

        // $otp = 123123;
        return $otp;
    }
}
