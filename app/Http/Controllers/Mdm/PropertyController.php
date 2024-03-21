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
        $this->_APARTMENT_DTL       = new PropApartmentDtl();

        #roleId = 1 -> SUPER ADMIN,
        #         2 -> ADMIN
        $this->_ALLOW_ROLE_ID       = [1, 2];
    }

    #============= Apartment Dtl Crud =================
    public function addApartment(Request $request)
    {
        try {
            $user   = authUser($request);
            $userId = $user->id ?? 0;
            $ulbId  = $user->ulb_id ?? 0;

            $request->request->add([
                'ulbId'  => $ulbId,
                'userId' => $userId,
            ]);

            $data["apartmentId"] = $this->_APARTMENT_DTL->store($request);
            return responseMsg(true, "New Record Added", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    /**
     * | 
     */
    public function apartmentList(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 10;
            $data    = $this->_APARTMENT_DTL::select(
                'prop_apartment_dtls.id',
                'apt_code',
                'apartment_name',
                'apartment_address',
                'water_harvesting_status',
                'wtr_hrvs_image_file_name',
                'zone_name',
                'ward_name' 
            )
                ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_apartment_dtls.id')
                ->join('zone_masters', 'zone_masters.id', 'ulb_ward_masters.zone')
                ->orderBy('apartment_name')->paginate($perPage);

            return responseMsg(true, "Apartment List", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
}
