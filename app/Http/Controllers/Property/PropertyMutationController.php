<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Property\reqApplySaf;
use Illuminate\Support\Facades\Validator;
use App\Models\Property\PropActiveMutation;
use App\Models\Property\PropActiveApp;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropActiveSafsOwner;
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
            $request->merge(["no_calculater"=>true]);
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
    public function approve(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'applicationId' => 'required|digits_between:1,9223372036854775807',
                'status' => 'required|integer|in:1,0'

            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 200);
            }

            $newSafData = PropActiveSaf::find($request->applicationId);
            if (!$newSafData) {
                throw new Exception("Data Not Found");
            }
            $request->merge(["assessmentType"=>"6",
                            "previousHoldingId"=>$newSafData->previous_holding_id,
            ]);
            $ApplySafContoller = new ApplySafController();
            $ulb_id= $newSafData->ulb_id;
            $ulbWorkflowId = $ApplySafContoller->readAssessUlbWfId($request, $ulb_id);
            if ($ulbWorkflowId->id != $newSafData->workflow_id) {
                throw new Exception("This Data not Able To Approved From Hear");
            }

            DB::beginTransaction();

            $propProperties = $newSafData->toBePropertyBySafId($newSafData->id);
            $propProperties->saf_id = $newSafData->id;
            $propProperties->holding_no = $newSafData->holding_no;
            $propProperties->new_holding_no = $newSafData->holding_no; 
            $propProperties = PropProperty::create($propProperties->toArray());

            $oldProp = PropProperty::find($newSafData->previous_holding_id);
            $oldProp->status =0;
            $oldProp->update();
            // foreach($oldOwners = $oldProp->Owneres()->get() as $val)
            // {
            //     $val->status =0;
            //     $val->update();
            // }
            // foreach($oldFloor = $oldProp->Owneres()->get() as $val)
            // {
            //     $val->status =0;
            //     $val->update();
            // }

            $prop_saf = $newSafData->replicate();
            $prop_saf->setTable('prop_safs');
            $prop_saf->id = $newSafData->id;
            $prop_saf->save();
            $newSafData->delete();

            $new_saf_owners = PropActiveSafsOwner::select("*")->where("saf_id",$newSafData->id)->orderBy("id","ASC")->get(); 
            foreach ($new_saf_owners as $ownerDetail) {
                $propOwner = $approvedOwner = $ownerDetail->replicate();
                $approvedOwner->setTable('prop_safs_owners'); 
                $approvedOwner->id = $ownerDetail->id;
                $approvedOwner->save();

                $propOwner->setTable('prop_owners');
                $propOwner->property_id = $propProperties->id;
                $propOwner->save();
                
                $ownerDetail->delete();
            }

            if ($newSafData->prop_type_mstr_id != 4) { 
                $new_saf_floors = PropActiveSafsFloor::select("*")->where("saf_id",$newSafData->id)->OrderBy("id","ASC")->get(); 
                foreach ($new_saf_floors as $floorDetail) {
                    $propFloor = $approvedFloor = $floorDetail->replicate();
                    $approvedFloor->setTable('prop_safs_floors');                    
                    $approvedFloor->id = $floorDetail->id;                    
                    $approvedFloor->save();

                    $propFloor->setTable('prop_floors');
                    $propFloor->property_id = $propProperties->id;
                    $propFloor->save();
                    $floorDetail->delete();
                }
            }
            DB::commit();
            $responseFields = [
                'holdingNo' => $propProperties->holding_no,
                'ptNo' => $propProperties->pt_no,
                'famNo' => "",
                'famId' => "0",
                'propId' => $propProperties->id
            ];
            
            return responseMsgs(true, "mutation Approved successfully", $responseFields, '010801', '01', '623ms', 'Post', '');
            }

        
        catch (Exception $e) {
            DB::rollBack();
            return responseMsg(false, $e->getMessage(), "");

        }
    }
}
