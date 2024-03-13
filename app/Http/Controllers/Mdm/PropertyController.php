<?php

namespace App\Http\Controllers\Mdm;

use App\Http\Controllers\Controller;
use App\Models\Property\PropApartmentDtl;
use App\Repository\Common\CommonFunction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    protected $_ALLOW_ROLE_ID;
    protected $_USER_TYPE;
    protected $_USER_ID;
    protected $_ULB_ID;
    protected $_ROLE_ID;
    protected $_APARTMENT_DTL;
    protected $_COMMON_FUNCTION;

    public function __construct()
    {
        DB::enableQueryLog();
        $this->_COMMON_FUNCTION     = new CommonFunction();
        $this->_APARTMENT_DTL           = new PropApartmentDtl();

        #roleId = 1 -> SUPER ADMIN,
        #         2 -> ADMIN
        $this->_ALLOW_ROLE_ID       = [1,2];
        
    }

    #============= Apartment Dtl Crud =================
    public function addApartment(Request $request)
    {
        
        try{
            $user = Auth()->user();
            $userId = $user->id??0;
            $ulbId = $user->ulb_id??0;
            DB::enableQueryLog();
            $role = $this->_COMMON_FUNCTION->getUserAllRoles()->whereIn("role_id",$this->_ALLOW_ROLE_ID)->first();         
            #roleId = 1 -> SUPER ADMIN,
            #         2 -> ADMIN
            if(!$role || !in_array($role->role_id,$this->_ALLOW_ROLE_ID))
            {
                throw new Exception(($role?$role->role_name:"You Are")." Not Authoried For This Action");
            }
            DB::beginTransaction();
            $data["apartmentId"] = $this->_APARTMENT_DTL->store($request);
            DB::commit();
            return responseMsg(true,"New Recode Added",$data);
        }
        catch(Exception $e)
        {
            return responseMsg(false,$e->getMessage(),$request->all());
        }
    }
}
