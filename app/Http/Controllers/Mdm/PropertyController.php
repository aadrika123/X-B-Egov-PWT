<?php

namespace App\Http\Controllers\Mdm;

use App\Http\Controllers\Controller;
use App\MicroServices\DocUpload;
use App\Models\Property\PropApartmentDtl;
use App\Repository\Common\CommonFunction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

            $validated = Validator::make(
                $request->all(),
                [
                    'apartmentCode'         => 'required',
                    'apartmentName'         => 'required',
                    'apartmentAddress'      => 'required',
                    'waterHarvestingStatus' => 'required|boolean',
                    'waterHarvestingImage'  => 'nullable|mimes:jpeg,png,jpg|max:2048',
                    'aptImage'              => 'nullable|mimes:jpeg,png,jpg|max:2048',
                    'ward'                  => 'required|digits_between:1,9223372036854775807',
                    'category'              => 'required|digits_between:1,9223372036854775807',
                    'blocks'                => 'nullable',
                ]
            );
            if ($validated->fails())
                return validationError($validated);

            $docUpload = new DocUpload;
            $user   = authUser($request);
            $userId = $user->id ?? 0;
            $ulbId  = $user->ulb_id ?? 0;


            if ($request->file('waterHarvestingImage')) {
                $refImageName = 'Har-' . Str::random(5) . '-' . date('His');
                $file = $request->file('waterHarvestingImage');
                $imageName = $docUpload->upload($refImageName, $file, 'Uploads/Property');
                $request->request->add(['harvestingImage' => 'Uploads/Property/' . $imageName]);
            }

            if ($request->file('aptImage')) {
                $refImageName = 'Apt-' . Str::random(5) . '-' . date('His');
                $file = $request->file('aptImage');
                $imageName = $docUpload->upload($refImageName, $file, 'Uploads/Property');
                $request->request->add(['apartmentImage' => 'Uploads/Property/' . $imageName]);
            }

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
            $docUrl = Config::get('module-constants.DOC_URL');
            $perPage = $request->perPage ?? 10;
            $data    = $this->_APARTMENT_DTL::select(
                'prop_apartment_dtls.id',
                'apt_code',
                'apartment_name',
                'apartment_address',
                DB::raw("case when water_harvesting_status =0 then 'No'
                                else 'Yes' end
                                as water_harvesting_status,
                    concat('$docUrl/',wtr_hrvs_image_file_name) as wtr_hrvs_image_file_name
                "),
                'zone_name as zone',
                'ward_name as ward_no'
            )
                ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_apartment_dtls.id')
                ->join('zone_masters', 'zone_masters.id', 'ulb_ward_masters.zone')
                ->orderBy('apartment_name')
                ->paginate($perPage);

            return responseMsg(true, "Apartment List", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
}
