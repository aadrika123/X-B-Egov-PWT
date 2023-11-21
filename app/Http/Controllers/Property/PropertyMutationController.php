<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Property\reqApplySaf;
use Illuminate\Support\Facades\Validator;
use App\Models\Property\PropActiveMutation;
use App\Models\Property\PropActiveApp;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropProperty;
use App\Traits\Property\Property;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class PropertyMutationController extends Controller
{
    /**
     * create by prity pandey
     * date : 20-11-2023
     * for old mution data entery
     */
    use Property;
    public function addMutationApplication(Request $request)
    { 
        
        try{
            $todayDate = Carbon::now()->format('Y-m-d');
            $validator = Validator::make($request->all(), [
                "propertyId" => "required|digits_between:1,9223372036854775807",
                'applicationDate' => 'required|date',
                "owner" => "required|array",
                'owner.*.ownerName' => 'required|string',
                'owner.*.ownerNameMarathi' => 'nullable',
                'owner.*.guardianNameMarathi' => 'nullable',
                "owner.*areaOfPlot"    => "required|numeric|not_in:0",
                "owner.*.gender" => "nullable|In:Male,Female,Transgender",
                "owner.*.dob" => "nullable|date|date_format:Y-m-d|before_or_equal:$todayDate",
                "owner.*.mobileNo" => "nullable|digits:10|regex:/[0-9]{10}/",
                "owner.*.aadhar" => "digits:12|regex:/[0-9]{12}/|nullable",
                "owner.*.pan" => "string|nullable",
                "owner.*.email" => "email|nullable",
                "owner.*.isArmedForce" => "nullable|bool",
                "owner.*.isSpeciallyAbled" => "nullable|bool"

            ]);
            $request->merge(["assessmentType"=>"6",
                            "previousHoldingId"=>$request->propertyId,
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 200);
            }
            $propProperty = PropProperty::find($request->propertyId);         
            if(!$propProperty)
            {
                throw new Exception("Data Not found");
            }
            $request->merge($this->generatePropUpdateRequest($request,$propProperty,true));
            
            if(!$propProperty->status)
            {
                throw new Exception("Property Is Deacatived");
            }
            $dueDemands = $propProperty->PropDueDemands()->get();
            if(collect($dueDemands)->sum("due_total_tax")>0)
            {
                //  throw new Exception("Please clear Due Demand First");
            }
            $owners = $propProperty->Owneres()->get();
            if(!$request->owner)
            {   $newOwners = [];
                foreach($owners as $val)
                {
                    $newOwners[]=$this->generatePropOwnerUpdateRequest([],$val,true);
                }
                $request->merge(["owner"=>$newOwners]);
            }
            $propFloars = $propProperty->floars()->get();
            $newFloars["floor"] =[];
            foreach($propFloars as $key=>$val)
            {
                $newFloars["floor"][]=($this->generatePropFloar($request,$val));

            }
            $request->merge($newFloars);
            $ApplySafContoller = new ApplySafController();
            $applySafRequet = new reqApplySaf();
            $applySafRequet->merge($request->all());
            DB::beginTransaction();
            $newSafRes = ($ApplySafContoller->applySaf($applySafRequet));
            if(!$newSafRes->original["status"])
            {
                return $newSafRes;
            }
            $safData = $newSafRes->original["data"];
            $safId = $safData["safId"];
            
            
            $app = PropActiveApp::create([
                'citizen_id' => $request->citizenId,
                'application_type' => $request->applicationType,
                'application_date' => $request->applicationDate
            ]);            
            $appId = $app->id;
            $newSafData = PropActiveSaf::find($safId);
            $newSafData->app_id = $appId; 
            $newSafData->update();
          DB::commit();
  
          return responseMsgs(true, "mutation applied successfully", $safData, '010801', '01', '623ms', 'Post', '');
        
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");

        }
    }
}
