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
use App\Models\Property\PropSaf;
use App\Models\WorkflowTrack;
use App\Traits\Property\Property;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
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
            $test = PropActiveSaf::select("*")->where("previous_holding_id",$request->propertyId)->first();
            if($test)
            {
                throw new Exception("Application Already Apply For ".$test->assessment_type." On ".$test->application_date ." Please Wait For Approvale");
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
            $holdingNo =  "";
            $ptNo = "";
            $famNo ="";
            $famId =0;
            $propId =0;
            $msg = "Application Rejected Successfully";
            $userId = authUser($request)->id;
            $track = new WorkflowTrack();
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
            $new_saf_owners = PropActiveSafsOwner::select("*")->where("saf_id",$newSafData->id)->orderBy("id","ASC")->get();
            $new_saf_floors = PropActiveSafsFloor::select("*")->where("saf_id",$newSafData->id)->OrderBy("id","ASC")->get(); 
            $oldProp = PropProperty::find($newSafData->previous_holding_id);
            DB::beginTransaction();
            DB::connection('pgsql_master')->beginTransaction();
            $prop_saf = $newSafData->replicate();          
            $prop_saf->id = $newSafData->id;
            if($request->status==1)
            {

                $propProperties = $newSafData->toBePropertyBySafId($newSafData->id);
                $propProperties->saf_id = $newSafData->id;
                $propProperties->holding_no = $newSafData->holding_no;
                $propProperties->new_holding_no = $newSafData->holding_no; 
                $propProperties->property_no = $oldProp->property_no;
                $propProperties = PropProperty::create($propProperties->toArray());
    
                
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
    
                $prop_saf->setTable('prop_safs');
    
                 
                foreach ($new_saf_owners as $ownerDetail) {
                    $approvedOwner = $ownerDetail->replicate();
                    $propOwner = $ownerDetail->replicate();
                    $approvedOwner->setTable('prop_safs_owners'); 
                    $approvedOwner->id = $ownerDetail->id;
                    $approvedOwner->save();
    
                    $propOwner->setTable('prop_owners');
                    $propOwner->property_id = $propProperties->id;
                    $propOwner->save();
                    
                    $ownerDetail->delete();
                }
    
                if ($newSafData->prop_type_mstr_id != 4) { 
                   
                    foreach ($new_saf_floors as $floorDetail) {
                        $approvedFloor = $floorDetail->replicate();
                        $propFloor = $floorDetail->replicate();
                        $approvedFloor->setTable('prop_safs_floors');                    
                        $approvedFloor->id = $floorDetail->id;                    
                        $approvedFloor->save();
    
                        $propFloor->setTable('prop_floors');
                        $propFloor->property_id = $propProperties->id;
                        $propFloor->save();
                        $floorDetail->delete();
                    }
                }
                $msg = "Mutation Application Approved Successfully";
                $metaReqs['verificationStatus'] = 1;
                $holdingNo =  $propProperties->holding_no;
                $ptNo = $propProperties->pt_no;
                $famNo ="";
                $famId =0;
                $propId =$propProperties->id;
            }
            else
            {
               
                $prop_saf->setTable('prop_rejected_safs');
                foreach ($new_saf_owners as $ownerDetail) {
                    $approvedOwner = $ownerDetail->replicate();
                    $approvedOwner->setTable('prop_rejected_safs_owners'); 
                    $approvedOwner->id = $ownerDetail->id;
                    $approvedOwner->save();
                    
                    $ownerDetail->delete();
                }
    
                if ($newSafData->prop_type_mstr_id != 4) { 
                   
                    foreach ($new_saf_floors as $floorDetail) {
                        $approvedFloor = $floorDetail->replicate();
                        $approvedFloor->setTable('prop_rejected_safs_floors');                    
                        $approvedFloor->id = $floorDetail->id;                    
                        $approvedFloor->save();
    
                        $floorDetail->delete();
                    }
                }
                $msg = "Mutation Application Rejected Successfully";
                $metaReqs['verificationStatus'] = 0;
                
            }
            
            $prop_saf->save();
            $newSafData->delete();

            $metaReqs['moduleId'] = Config::get('module-constants.PROPERTY_MODULE_ID');
            $metaReqs['workflowId'] = $newSafData->workflow_id;
            $metaReqs['refTableDotId'] = Config::get('PropertyConstaint.SAF_REF_TABLE');
            $metaReqs['refTableIdValue'] = $newSafData->id;
            $metaReqs['senderRoleId'] = $newSafData->current_role;
            $metaReqs['verificationStatus'] = 1;
            $metaReqs['user_id'] = $userId;
            $metaReqs['trackDate'] = Carbon::now()->format('Y-m-d H:i:s');
            $request->merge($metaReqs);
            $track->saveTrack($request);
            DB::commit();
            DB::connection('pgsql_master')->commit();
           
            $responseFields = [
                'holdingNo' => $holdingNo,
                'ptNo' => $ptNo,
                'famNo' => $famNo,
                'famId' => $famId,
                'propId' => $propId,
            ];
            
            return responseMsgs(true, $msg, $responseFields, '010801', '01', '623ms', 'Post', '');
            }

        
        catch (Exception $e) {
            DB::rollBack();
            DB::connection('pgsql_master')->rollBack();
            return responseMsg(false, $e->getMessage(), "");

        }
    }

    public function approveList(Request $request)
    {
    
        try{
            $validator = Validator::make($request->all(), [
                'applicationNo' => 'nullable',
                "fromDate" => "nullable|date|date_format:Y-m-d",
                "uptoDate" => "nullable|date|date_format:Y-m-d",
                'zoneId' => "nullable",
                'wardId' => "nullable",

            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validator->errors()
                ], 200);
            }
            $properties = PropSaf::select(
                'prop_properties.id as property_id',
                "prop_properties.saf_id",
                'prop_safs.holding_no',
                'prop_properties.property_no',
                'prop_safs.prop_address',
                'prop_safs.assessment_type',
                'prop_safs.saf_no as application_no',
                'owner.owner_name',
                'owner.guardian_name',
                'owner.mobile_no',
                'prop_active_apps.application_date',
                'zone_masters.zone_name',
                'ulb_ward_masters.ward_name'
            )
            ->join(DB::raw('(SELECT
                                STRING_AGG(prop_owners.owner_name, \',\') AS owner_name,
                                STRING_AGG(prop_owners.guardian_name, \',\') AS guardian_name,
                                STRING_AGG(prop_owners.mobile_no::TEXT, \',\') AS mobile_no,
                                saf_id
                            FROM prop_owners
                            JOIN prop_safs ON prop_safs.id = prop_owners.saf_id
                            WHERE prop_owners.status = 1 AND prop_safs.workflow_id = 202
                            GROUP BY prop_owners.saf_id) AS owner'), function ($join) {
                    $join->on('owner.saf_id', '=', 'prop_safs.id');
                })
            ->join('prop_active_apps', 'prop_safs.app_id', '=', 'prop_active_apps.id')
            ->join('prop_properties', 'prop_properties.saf_id', '=', 'prop_safs.id')
            ->join('ulb_ward_masters', 'ulb_ward_masters.id', '=', 'prop_safs.ward_mstr_id')
            ->join('zone_masters', 'zone_masters.id', '=', 'ulb_ward_masters.zone')
            ->where('prop_safs.workflow_id', 202);

        if ($request->applicationNo) {
            $properties->where('prop_safs.saf_no', $request->applicationNo);
        }

        if ($request->fromDate && $request->uptoDate) {
            $properties->whereBetween('prop_active_apps.application_date', [$request->fromDate, $request->uptoDate]);
        }

        if ($request->zoneId) {
            
            $properties->where('zone_masters.id', $request->zoneId);
        }

        if ($request->wardId) {
           
            $properties->where('ulb_ward_masters.id', $request->wardId);
        }
            $perPage = $request->perPage ;
            $paginator = $properties->paginate($perPage);             
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ]; 
            
            return responseMsg(true,"Eo Approved List" ,$list, "010501", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
        
    }

}
