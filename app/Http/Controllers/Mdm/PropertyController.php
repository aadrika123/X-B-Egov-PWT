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
     * 
     */
    public function editApartment(Request $request)
    {
        try {
            $validated = Validator::make(
                $request->all(),
                [
                    'id'                    => 'required|digits_between:1,9223372036854775807',
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
            $apartment = $this->_APARTMENT_DTL::find($request->id);
            if (!$apartment)
                throw new Exception("No Apartment Found");

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

            $newRequest = [
                'apt_code'                  => $request->apartmentCode ?? $apartment->apt_code,
                'apartment_name'            => $request->apartmentName ?? $apartment->apartment_name,
                'apartment_address'         => $request->apartmentAddress ?? $apartment->apartment_address,
                'water_harvesting_status'   => $request->waterHarvestingStatus ?? $apartment->water_harvesting_status,
                'wtr_hrvs_image_file_name'  => $request->harvestingImage ?? $apartment->wtr_hrvs_image_file_name,
                'apt_image_file_name'       => $request->apartmentImage ?? $apartment->apt_image_file_name,
                'ward_mstr_id'              => $request->ward ?? $apartment->ward_mstr_id,
                'is_blocks'                 => $request->isBlocks ?? $apartment->is_blocks,
                'no_of_block'               => $request->blocks ?? $apartment->no_of_block,
                'category_type_mstr_id'     => $request->category ?? $apartment->category_type_mstr_id,
            ];

            $apartment->update($newRequest);

            return responseMsg(true, "Record Updated", []);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), []);
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
                'no_of_block',
                DB::raw("case when water_harvesting_status =0 then 'No'
                                else 'Yes' end
                                as water_harvesting_status,
                        case when  water_harvesting_status =0 then '' else 
                    concat('$docUrl/',wtr_hrvs_image_file_name) end as wtr_hrvs_image_file_name,
                    concat('$docUrl/',apt_image_file_name) as apt_image_file_name
                "),
                'zone_name as zone',
                'ward_name as ward_no',
                'category'
            )
                ->join('ulb_ward_masters', 'ulb_ward_masters.id', 'prop_apartment_dtls.ward_mstr_id')
                ->join('zone_masters', 'zone_masters.id', 'ulb_ward_masters.zone')
                ->join('ref_prop_categories', 'ref_prop_categories.id', 'prop_apartment_dtls.category_type_mstr_id')
                ->orderBy('apartment_name')
                ->paginate($perPage);

            return responseMsg(true, "Apartment List", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
}
