<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePassRequest;
use App\Http\Requests\Auth\OtpChangePass;
use App\MicroServices\DocUpload;
use App\Models\ActiveCitizen;
use Illuminate\Http\Request;
use App\Repository\Citizen\iCitizenRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

/**
 * | Created On-08-08-2022 
 * | Created By-Anshu Kumar
 * --------------------------------------------------------------------------------------------
 * | Citizens Operations for Save, approve,Reject
 */
class CitizenController extends Controller
{
    // Initializing Repository
    protected $repository;
    protected $Repository;

    public function __construct(iCitizenRepository $repository)
    {
        $this->Repository = $repository;
    }

    // Citizen Registrations
    public function citizenRegister(Request $request)
    {
        $request->validate([
            'name'     => 'required',
            'mobile'   => 'required|numeric|digits:10',
            'password' => [
                'required',
                'min:6',
                'max:255',
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:/[@$!%*#?&]/'  // must contain a special character
            ],
        ]);

        try {

            DB::beginTransaction();
            $mCitizen = new ActiveCitizen();
            $citizens = $mCitizen->getCitizenByMobile($request->mobile);
            if (isset($citizens))
                return responseMsgs(false, "This Mobile No is Already Existing", "");

            $id = $mCitizen->citizenRegister($mCitizen, $request);        //Citizen save in model

            $this->docUpload($request, $id);

            DB::commit();

            return responseMsg(true, "Succesfully Registered", "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
            DB::rollBack();
        }
    }

    /**
     * | Doc upload
     */
    public function docUpload($request, $id)
    {
        $docUpload = new DocUpload;
        $imageRelativePath = 'Uploads/Citizen/' . $id;
        ActiveCitizen::where('id', $id)
            ->update([
                'relative_path' => $imageRelativePath . '/',
            ]);

        if ($request->photo) {
            $filename = 'photo';
            $document = $request->photo;
            $imageName = $docUpload->upload($filename, $document, $imageRelativePath);

            ActiveCitizen::where('id', $id)
                ->update([
                    'profile_photo' => $imageName,
                ]);
        }

        if ($request->aadharDoc) {
            $filename = 'aadharDoc';
            $document = $request->aadharDoc;
            $imageName = $docUpload->upload($filename, $document, $imageRelativePath);

            ActiveCitizen::where('id', $id)
                ->update([
                    'aadhar_doc' => $imageName,
                ]);
        }

        if ($request->speciallyAbledDoc) {
            $filename = 'speciallyAbled';
            $document = $request->speciallyAbledDoc;
            $imageName = $docUpload->upload($filename, $document, $imageRelativePath);

            ActiveCitizen::where('id', $id)
                ->update([
                    'specially_abled_doc' => $imageName,
                ]);
        }

        if ($request->armedForceDoc) {
            $filename = 'armedForce';
            $document = $request->armedForceDoc;
            $imageName = $docUpload->upload($filename, $document, $imageRelativePath);

            ActiveCitizen::where('id', $id)
                ->update([
                    'armed_force_doc' => $imageName,
                ]);
        }
    }


    /**
     *  Citizen Login
     */
    public function citizenLogin(Request $req)
    {
        try {
            $req->validate([
                'mobile' => "required",
                'password' => [
                    'required',
                    'min:6',
                    'max:255',
                    'regex:/[a-z]/',      // must contain at least one lowercase letter
                    'regex:/[A-Z]/',      // must contain at least one uppercase letter
                    'regex:/[0-9]/',      // must contain at least one digit
                    'regex:/[@$!%*#?&]/'  // must contain a special character
                ],
            ]);
            $citizenInfo = ActiveCitizen::where('mobile', $req->mobile)
                ->first();
            if (!$citizenInfo) {
                $msg = "Oops! Given mobile no does not exist";
                return responseMsg(false, $msg, "");
            }

            $userDetails['userName'] = $citizenInfo->user_name;
            $userDetails['mobile'] = $citizenInfo->mobile;
            $userDetails['userType'] = $citizenInfo->user_type;

            if ($citizenInfo) {
                if (Hash::check($req->password, $citizenInfo->password)) {
                    $token = $citizenInfo->createToken('my-app-token')->plainTextToken;
                    $citizenInfo->remember_token = $token;
                    $citizenInfo->save();
                    $userDetails['token'] = $token;
                    $key = 'last_activity_citizen_' . $citizenInfo->id;               // Set last activity key 
                    Redis::set($key, Carbon::now());
                    return responseMsgs(true, 'You r logged in now', $userDetails, '', "1.0", "494ms", "POST", "");
                } else {
                    $msg = "Incorrect Password";
                    return responseMsg(false, $msg, '');
                }
            }
        }
        // Authentication Using Sql Database
        catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    /**
     * | Citizen Logout 
     */
    public function citizenLogout(Request $req)
    {
        // token();
        $id =  authUser($req)->id;

        $user = ActiveCitizen::where('id', $id)->first();
        $user->remember_token = null;
        $user->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }


    public function citizenEditProfile(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'id'     => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(
                $validator->errors(),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $citizen = ActiveCitizen::find($request->id);
            $citizen->user_name = $request->name;
            $citizen->email = $request->email;
            $citizen->mobile = $request->mobile;
            $citizen->gender = $request->gender;
            $citizen->dob    = $request->dob;
            $citizen->aadhar = $request->aadhar;
            $citizen->is_specially_abled = $request->isSpeciallyAbled;
            $citizen->is_armed_force = $request->isArmedForce;
            $citizen->save();

            $this->docUpload($request, $citizen->id);

            return responseMsg(true, 'Successful Updated', "");
        } catch (Exception $e) {
            return response()->json('Something Went Wrong', 400);
        }
    }

    /** 
     * 
     */
    public function profileDetails()
    {
        try {
            $userId = auth()->user()->id;
            $redis = Redis::get('active_citizen:' . $userId);
            if ($redis) {
                $data = json_decode($redis);
                $collection = [
                    'id' => $data->id,
                    'name' => $data->user_name,
                    'mobile' => $data->mobile,
                    'email' => $data->email,
                    'gender' => $data->gender,
                    'dob' => $data->dob,
                    'aadhar' => $data->aadhar,
                    'aadhar_doc' => $data->relative_path . $data->aadhar_doc,
                    'is_specially_abled' => $data->is_specially_abled,
                    'specially_abled_doc' => $data->relative_path . $data->specially_abled_doc,
                    'is_armed_force' => $data->is_armed_force,
                    'armed_force_doc' => $data->relative_path . $data->armed_force_doc,
                    'relative_path' => $data->relative_path,
                    'user_type' => $data->user_type,
                    'profile_photo' => $data->relative_path . $data->profile_photo,
                ];
                $filtered = collect($collection);
                $message = ["status" => true, "message" => "Data Fetched", "data" => remove_null($filtered)];
                return $message;                                    // Filteration using Collection
            }
            if (!$redis) {
                // $details = DB::select($this->query($user_id));
                $details = ActiveCitizen::select(
                    'id',
                    'user_name as name',
                    'mobile',
                    'email',
                    'gender',
                    'dob',
                    'aadhar',
                    'aadhar_doc',
                    'is_specially_abled',
                    'specially_abled_doc',
                    'is_armed_force',
                    'armed_force_doc',
                    'relative_path',
                    'user_type',
                    'profile_photo',
                )
                    ->where('id', $userId)
                    ->first();
                if (collect($details)->isEmpty())
                    throw new Exception("Citizen Not Found");

                $details->aadhar_doc = ($details->relative_path . $details->aadhar_doc);
                $details->specially_abled_doc = ($details->relative_path . $details->specially_abled_doc);
                $details->armed_force_doc = ($details->relative_path . $details->armed_force_doc);
                $details->profile_photo = ($details->relative_path . $details->profile_photo);

                $message = ["status" => true, "message" => "Data Fetched", "data" => remove_null($details)];
                return $message;
            }
        } catch (Exception $e) {
            return ["status" => false, "message" => $e->getMessage(), "data" => []];
        }
    }



    // Get Citizen By ID
    public function getCitizenByID($id)
    {
        return $this->Repository->getCitizenByID($id);
    }

    // Get All Citizens
    public function getAllCitizens()
    {
        return $this->Repository->getAllCitizens();
    }

    // Get all applications
    public function getAllAppliedApplications(Request $req)
    {
        return $this->Repository->getAllAppliedApplications($req);
    }

    // Independent Comment
    public function commentIndependent(Request $req)
    {
        $req->validate([
            'message' => 'required|string'
        ]);

        return $this->Repository->commentIndependent($req);
    }

    // Citizen Transaction History
    public function getTransactionHistory(Request $req)
    {
        return $this->Repository->getTransactionHistory($req);
    }

    /**
     * -----------------------------------------------
     * Parent @Controller- function changePass()
     * -----------------------------------------------
     * @param App\Http\Requests\Request 
     * @param App\Http\Requests\Request $request 
     * 
     * 
     */
    public function changeCitizenPass(ChangePassRequest $request)
    {
        try {
            $id = authUser($request)->id;
            $citizen = ActiveCitizen::where('id', $id)->firstOrFail();
            $validPassword = Hash::check($request->password, $citizen->password);
            if ($validPassword) {

                $citizen->password = Hash::make($request->newPassword);
                $citizen->save();

                Redis::del('user:' . authUser($request)->id);   //DELETING REDIS KEY
                return responseMsgs(true, 'Successfully Changed the Password', "", "", "02", ".ms", "POST", $request->deviceId);
            }
            throw new Exception("Old Password dosen't Match!");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "02", ".ms", "POST", $request->deviceId);
        }
    }


    /**
     * |
     */
    public function changeCitizenPassByOtp(OtpChangePass $request)
    {
        try {
            $id = authUser($request)->id;
            $citizen = ActiveCitizen::where('id', $id)->firstOrFail();
            $citizen->password = Hash::make($request->password);
            $citizen->save();

            Redis::del('user:' . authUser($request)->id);   //DELETING REDIS KEY
            return responseMsgs(true, "Password changed!", "", "", "01", ".ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "", "01", ".ms", "POST", $request->deviceId);
        }
    }
}
