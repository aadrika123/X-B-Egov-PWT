<?php

namespace App\Http\Controllers\Mdm;

use App\Http\Controllers\Controller;
use App\Models\Trade\TradeParamApplicationType;
use App\Models\Trade\TradeParamCategoryType;
use App\Models\Trade\TradeParamFirmType;
use App\Models\Trade\TradeParamItemType;
use App\Models\Trade\TradeParamLicenceRate;
use App\Models\Trade\TradeParamOwnershipType;
use App\Repository\Common\CommonFunction;
use App\Repository\Trade\Trade;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TradeController extends Controller
{
    

    protected $_ALLOW_ROLE_ID;
    protected $_USER_TYPE;
    protected $_USER_ID;
    protected $_ULB_ID;
    protected $_ROLE_ID;
    protected $_FIRM_TYPE;
    protected $_APPLICATION_TYPE;
    protected $_CATEGORY_TYPE;
    protected $_ITEM_TYPE;
    protected $_RATES;
    protected $_OWNERSHIP_TYPE;
    protected $_COMMON_FUNCTION;
    protected $_REPOSITORY;
    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;
    protected $_REPOSITORY_TRADE;

    public function __construct()
    {
        DB::enableQueryLog();
        $this->_COMMON_FUNCTION     = new CommonFunction();
        $this->_FIRM_TYPE           = new TradeParamFirmType();
        $this->_APPLICATION_TYPE    = new TradeParamApplicationType();
        $this->_CATEGORY_TYPE       = new TradeParamCategoryType();
        $this->_ITEM_TYPE           = new TradeParamItemType();
        $this->_RATES               = new TradeParamLicenceRate();
        $this->_OWNERSHIP_TYPE      = new TradeParamOwnershipType();
        $this->_REPOSITORY_TRADE = new Trade();

        #roleId = 1 -> SUPER ADMIN,
        #         2 -> ADMIN
        $this->_ALLOW_ROLE_ID       = [1,2];


        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];

        
    }

    #============= Firm Type Crud =================
    public function addFirmType(Request $request)
    {
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(["firmType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i"]);
            
            $firmData = $this->_FIRM_TYPE->SELECT("*")
                        ->WHERE(DB::RAW("UPPER(firm_type)"),trim(strtoupper($request->firmType)))
                        ->ORDERBY("id")
                        ->FIRST();
            
            DB::beginTransaction();                        
            if(!$firmData)
            {
                #insert Data
                $sms="New Recode Added";  
                $newFirmType                =  $this->_FIRM_TYPE;
                $newFirmType->firm_type     =  trim(strtoupper($request->firmType));
                $newFirmType->status        =  1;
                $newFirmType->save();       
            }
            else
            {
                #update data
                $sms="Updated Recode";
                $firmData->status       = 1;
                $firmData->update();
            }
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }

    public function firmTypeList()
    {
        try{

            $list = $this->_FIRM_TYPE->select("*")
                    ->get();
            return responseMsg(true,["heard"=>"Firm Type List"],remove_null($list));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),[]);
        }
    }

    public function firmType(Request $request)
    {
        try{
            $request->validate(
                [
                    "id" => "required|digits_between:1,9223372036854775807",
                ]
            );
            $firmData = $this->_FIRM_TYPE->find($request->id);
            if(!$firmData)
            {
                  throw new Exception("Data Not Found");   
            }
            return responseMsg(true,["heard"=>"Firm Type Detail"],remove_null($firmData));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
        
    }

    public function updateFirmType(Request $request)
    {

        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                    "firmType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                    "id" => "required|digits_between:1,9223372036854775807",
                    "status"=>"nullable|in:0,1"
                ]
            );
            $firmData = $this->_FIRM_TYPE->find($request->id);
            
            DB::beginTransaction();                        
            if(!$firmData)
            {
                  throw new Exception("Data Not Found");   
            }
             #update data
            $sms="Updated Recode";
            $firmData->firm_type     =  trim(strtoupper($request->firmType));
            if(isset($request->status))
            {
                switch($request->status)
                {
                    case 0 : $firmData->status   = 0;
                            break;
                    case 1  : $firmData->status  = 1;
                            break;
                }

            }
            
            $firmData->update();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
    #============= End Firm Type Crud =================
    #============= Application Type Crud =================
    public function addApplicationType(Request $request)
    {
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(["applicationType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i"]);
            
            $appData = $this->_APPLICATION_TYPE->SELECT("*")
                        ->WHERE(DB::RAW("UPPER(application_type)"),trim(strtoupper($request->applicationType)))
                        ->ORDERBY("id")
                        ->FIRST();
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                #insert Data
                $sms="New Recode Added";  
                $newApplicationType                   =  $this->_APPLICATION_TYPE;
                $newApplicationType->application_type =  trim(strtoupper($request->firmType));
                $newApplicationType->status           =  1;
                $newApplicationType->save();       
            }
            else
            {
                #update data
                $sms="Updated Recode";
                $appData->status       = 1;
                $appData->update();
            }
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }

    public function applicationTypeList()
    {
        try{

            $list = $this->_APPLICATION_TYPE->select("*")
                    ->get();
            return responseMsg(true,["heard"=>"Application Type List"],remove_null($list));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),[]);
        }
    }

    public function applicationType(Request $request)
    {
        try{
            $request->validate(
                [
                    "id" => "required|digits_between:1,9223372036854775807",
                ]
            );
            $appData = $this->_APPLICATION_TYPE->find($request->id);
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
            return responseMsg(true,["heard"=>"Firm Type Detail"],remove_null($appData));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
        
    }

    public function updateApplicationType(Request $request)
    {

        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                    "applicationType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                    "id" => "required|digits_between:1,9223372036854775807",
                    "status"=>"nullable|in:0,1"
                ]
            );
            $appData = $this->_APPLICATION_TYPE->find($request->id);
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
             #update data
            $sms="Updated Recode";
            $appData->application_type     =  trim(strtoupper($request->applicationType));
            if(isset($request->status))
            {
                switch($request->status)
                {
                    case 0 : $appData->status   = 0;
                            break;
                    case 1  : $appData->status  = 1;
                            break;
                }

            }
            
            $appData->update();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
    #============= End Application Type Crud =================
    #============= Category Type Crud =================
    public function addCategoryType(Request $request)
    {
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(["categoryType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i"]);
            
            $appData = $this->_CATEGORY_TYPE->SELECT("*")
                        ->WHERE(DB::RAW("UPPER(category_type)"),trim(strtoupper($request->categoryType)))
                        ->ORDERBY("id")
                        ->FIRST();
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                #insert Data
                $sms="New Recode Added";  
                $newType                   =  $this->_CATEGORY_TYPE;
                $newType->category_type    =  trim(strtoupper($request->categoryType));
                $newType->status           =  1;
                $newType->save();       
            }
            else
            {
                #update data
                $sms="Updated Recode";
                $appData->status       = 1;
                $appData->update();
            }
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }

    public function categoryTypeList()
    {
        try{

            $list = $this->_CATEGORY_TYPE->select("*")
                    ->get();
            return responseMsg(true,["heard"=>"Category Type List"],remove_null($list));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),[]);
        }
    }

    public function categoryType(Request $request)
    {
        try{
            $request->validate(
                [
                    "id" => "required|digits_between:1,9223372036854775807",
                ]
            );
            $appData = $this->_CATEGORY_TYPE->find($request->id);
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
            return responseMsg(true,["heard"=>"Firm Type Detail"],remove_null($appData));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
        
    }

    public function updateCategoryType(Request $request)
    {

        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                    "categoryType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                    "id" => "required|digits_between:1,9223372036854775807",
                    "status"=>"nullable|in:0,1"
                ]
            );
            $appData = $this->_CATEGORY_TYPE->find($request->id);
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
             #update data
            $sms="Updated Recode";
            $appData->category_type     =  trim(strtoupper($request->categoryType));
            if(isset($request->status))
            {
                switch($request->status)
                {
                    case 0 : $appData->status   = 0;
                            break;
                    case 1  : $appData->status  = 1;
                            break;
                }

            }
            
            $appData->update();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
    #============= End Category Type Crud =================

    #============= Item Type Crud =================
    public function addItemType(Request $request)
    {
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                "itemType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                "tradeCode" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i"
                ]
            );
            
            $appData = $this->_ITEM_TYPE->SELECT("*")
                        ->WHERE(DB::RAW("UPPER(trade_item)"),trim(strtoupper($request->itemType)))
                        ->ORDERBY("id")
                        ->FIRST();
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                #insert Data
                $sms="New Recode Added";  
                $newType                   =  $this->_ITEM_TYPE;
                $newType->trade_item    =  trim(strtoupper($request->categoryType));
                $newType->trade_code    =  trim(strtoupper($request->tradeCode));
                $newType->status           =  1;
                $newType->save();       
            }
            else
            {
                #update data
                $sms="Updated Recode";
                $appData->status       = 1;
                $appData->update();
            }
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }

    public function itemTypeList()
    {
        try{

            $list = $this->_ITEM_TYPE->select("*")
                    ->get();
            return responseMsg(true,["heard"=>"Item Type List"],remove_null($list));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),[]);
        }
    }

    public function itemType(Request $request)
    {
        try{
            $request->validate(
                [
                    "id" => "required|digits_between:1,9223372036854775807",
                ]
            );
            $appData = $this->_ITEM_TYPE->find($request->id);
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
            return responseMsg(true,["heard"=>"Item Type Detail"],remove_null($appData));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
        
    }

    public function updateItemType(Request $request)
    {

        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                    "itemType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                    "tradeCode" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                    "id" => "required|digits_between:1,9223372036854775807",
                    "status"=>"nullable|in:0,1"
                ]
            );
            $appData = $this->_ITEM_TYPE->find($request->id);
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
             #update data
            $sms="Updated Recode";
            $appData->trade_item     =  trim(strtoupper($request->itemType));
            $appData->trade_code    =  trim(strtoupper($request->tradeCode));
            if(isset($request->status))
            {
                switch($request->status)
                {
                    case 0 : $appData->status   = 0;
                            break;
                    case 1  : $appData->status  = 1;
                            break;
                }

            }
            
            $appData->update();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
    #============= End Item Type Crud =================
    #============= Rate Crud =================
    public function addRate(Request $request)
    {
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $applicationType = $this->applicationTypeList();
            if(!$applicationType->original["status"])
            {
                throw new Exception("Some Error Occurs");
            }
            $applicationType = $applicationType->original["data"];
            $applicationTypeId = $applicationType->where("status",1)->implode("id",",");
            

            $sms = "";
            $request->validate(
                [
                "applicationTypeId" => "required|int|in:$applicationTypeId",
                "rangeFrom" => "required|int|min:1",
                "rangeTo" => "required|int|min:".(is_numeric($request->rangeFrom) && $request->rangeFrom>0 ? ($request->rangeFrom+1):1),
                "effectiveDate" => "required|date|date_format:Y-m-d",
                "rate" => "required|numeric|min:0",
                "tobaccoStatus" => "required|in:0,1",
                ]
            );
            
            $user = Auth()->user();
            $user_id = $user->id;
            
           
            #insert Data
            $sms="New Recode Added";  
            $newType                         =  $this->_RATES;
            $newType->application_type_id    =  $request->applicationTypeId;
            $newType->range_from             =  $request->rangeFrom;
            $newType->range_to               =  $request->rangeTo;
            $newType->effective_date         =  $request->effectiveDate;
            $newType->rate                   =  $request->rate;
            $newType->tobacco_status         =  $request->tobacco_status?1:0;
            $newType->emp_details_id         =  $user_id;
               
            DB::beginTransaction();
            $newType->save();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }

    public function rateList()
    {
        try{

            $list = $this->_RATES->select("trade_param_licence_rates.*","application_type")
                    ->join("trade_param_application_types","trade_param_application_types.id","trade_param_licence_rates.application_type_id")
                    ->get();
            return responseMsg(true,["heard"=>"Rate List"],remove_null($list));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),[]);
        }
    }

    public function rate(Request $request)
    {
        try{  
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id); 
            $request->validate(
                [
                    "id" => "required|digits_between:1,9223372036854775807",
                ]
            );
            $appData = $this->_RATES->select("trade_param_licence_rates.*","application_type")
                        ->join("trade_param_application_types","trade_param_application_types.id","trade_param_licence_rates.application_type_id")
                        ->find($request->id);
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
            return responseMsg(true,["heard"=>"Rate Detail"],remove_null($appData));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
        
    }

    public function updateRate(Request $request)
    {

        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                    "applicationTypeId" => "required|int",
                    "rangeFrom" => "required|int",
                    "rangeTo" => "required|int",
                    "effectiveDate" => "required|date|date_format:Y-m-d",
                    "rate" => "required|numeric|min:0",
                    "tobaccoStatus" => "required|bool",                        
                    "id" => "required|digits_between:1,9223372036854775807",
                    "status"=>"nullable|in:0,1"
                ]
            );
            $appData = $this->_RATES->find($request->id);
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
             #update data
            $sms="Updated Recode";
            $appData->application_type_id    =  $request->applicationTypeId;
            $appData->range_from             =  $request->rangeFrom;
            $appData->range_to               =  $request->rangeTo;
            $appData->effective_date         =  $request->effectiveDate;
            $appData->rate                   =  $request->rate;
            $appData->tobacco_status         =  $request->tobacco_status?1:0;
            $appData->emp_details_id         =  $userId;

            if(isset($request->status))
            {
                switch($request->status)
                {
                    case 0 : $appData->status   = 0;
                            break;
                    case 1  : $appData->status  = 1;
                            break;
                }

            }
            
            $appData->update();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
    #============= End Rate Crud =================
    #============= Ownership Type Crud =================
    public function addOwnershipType(Request $request)
    {
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                "ownershipType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",                
                ]
            );
            
            $user = Auth()->user();
            $user_id = $user->id;
            
           
            $appData = $this->_OWNERSHIP_TYPE->SELECT("*")
                        ->WHERE(DB::RAW("UPPER(ownership_type)"),trim(strtoupper($request->ownershipType)))
                        ->ORDERBY("id")
                        ->FIRST();
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                #insert Data
                $sms="New Recode Added";  
                $newType                   =  $this->_OWNERSHIP_TYPE;
                $newType->ownership_type    =  trim(strtoupper($request->ownershipType));
                $newType->status           =  1;
                $newType->save();       
            }
            else
            {
                #update data
                $sms="Updated Recode";
                $appData->status       = 1;
                $appData->update();
            }

            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }

    public function ownershipTypeList()
    {
        try{

            $list = $this->_OWNERSHIP_TYPE->select("*")
                    ->get();
            return responseMsg(true,["heard"=>"Ownership Type List"],remove_null($list));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),[]);
        }
    }

    public function ownershipType(Request $request)
    {
        try{
            $request->validate(
                [
                    "id" => "required|digits_between:1,9223372036854775807",
                ]
            );
            $appData = $this->_OWNERSHIP_TYPE->select("*")
                        ->find($request->id);
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
            return responseMsg(true,["heard"=>"Ownership Type Detail"],remove_null($appData));
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
        
    }

    public function updateOwnershipType(Request $request)
    {

        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $this->_WF_MASTER_Id);            
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            $sms = "";
            $request->validate(
                [
                    "ownershipType" => "required|regex:/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i",
                    "id" => "required|digits_between:1,9223372036854775807",                    
                    "status"=>"nullable|in:0,1"
                ]
            );
            $appData = $this->_OWNERSHIP_TYPE->find($request->id);
            
            DB::beginTransaction();                        
            if(!$appData)
            {
                  throw new Exception("Data Not Found");   
            }
             #update data
            $sms="Updated Recode";
            $appData->ownership_type     =  trim(strtoupper($request->ownershipType));
            if(isset($request->status))
            {
                switch($request->status)
                {
                    case 0 : $appData->status   = 0;
                            break;
                    case 1  : $appData->status  = 1;
                            break;
                }

            }
            
            $appData->update();
            DB::commit();
            return responseMsg(true,$sms,"");
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
    #============= End Ownership Type Crud =================


    public function updateMobile(Request $req)
    {
        $validated = Validator::make(
            $req->all(),
            [
                "applicationId" => "required|integer",
                "ownerId"    => "required|integer",
                "mobileNo"   => "required|digits:10|regex:/[0-9]{10}/",
            ]
        );
        if ($validated->fails())
            return validationError($validated);

        try {
            $trade = new Trade();
            $ownerDetails = $trade->getAllOwnereDtlByLId($req->applicationId)
                                ->where('id', $req->ownerId)
                                ->first();

            if (!$ownerDetails)
                throw new Exception("No Data Found Against this Owner");

            $ownerDetails->old_mobile_no = $ownerDetails->mobile_no;
            $ownerDetails->mobile_no = $req->mobileNo;
            $ownerDetails->save();

            return responseMsgs(true, "Mobile No Updated", [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), [], "011918", "01", responseTime(), $req->getMethod(), $req->deviceId);
        }
    }
}