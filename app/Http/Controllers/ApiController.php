<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\PropertyDetail;
use App\Models\Seller;
use App\Models\Purchaser;
use App\Models\UserDetail;
use Exception;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    //
    public function pushIGRData(Request $request)
    {
        try {
            $requestData = $request->all();
            DB::beginTransaction();
            $user = new UserDetail();
            $user->username = $requestData['username'];
            $user->password = $requestData['password'];
            $user->save();
            $userId = $user->id;
            foreach ($requestData['ObjGetData']['PropertyDetails'] as $propertyData) {
                $property = new PropertyDetail();
                $property->user_id = $userId;
                $property->uniquedocnumber = $propertyData['UniqueDocumentNumber'];
                $property->haddaname_eng = $propertyData['haddaname_eng'];
                $property->registration_date = $propertyData['registration_date'];
                $property->sroename = $propertyData['sroename'];
                $property->docnumber = $propertyData['docnumber'];
                $property->am_areaname_engtrans = $propertyData['am_areaname_engtrans'];
                $property->area = $propertyData['area'];
                $property->eri_attribute_name = $propertyData['eri_attribute_name'];
                $property->property_number = $propertyData['property_number'];
                $property->typename = $propertyData['typename'];
                $property->pui = $propertyData['pui'];
                $property->other_desc = $propertyData['other_desc'];
                $property->boundriesofproperty = $propertyData['boundaries_of_property'];
                $property->considerationamt = $propertyData['considerationamt'];
                $property->save();
                $propertyId = $property->id;
            }
            foreach ($requestData['ObjGetData']['BuyerDetails'] as $buyerData) {
                $buyer = new Purchaser();
                $buyer->property_details_id = $propertyId;
                $buyer->uniquedocnumber = $buyerData['UniqueDocumentNumber'];
                $buyer->fname = $buyerData['fname'];
                $buyer->mname = $buyerData['mname'];
                $buyer->lname = $buyerData['lname'];
                $buyer->efname = $buyerData['efname'];
                $buyer->emname = $buyerData['emname'];
                $buyer->elname = $buyerData['elname'];
                $buyer->email = $buyerData['email'];
                $buyer->eaddress = $buyerData['eaddress'];
                $buyer->partymobilenumber = $buyerData['partymobilenumber'];
                $buyer->save();
            }
            foreach ($requestData['ObjGetData']['SellerDetails'] as $sellerData) {
                $seller = new Seller();
                $seller->property_details_id = $propertyId;
                $seller->uniquedocnumber = $sellerData['UniqueDocumentNumber'];
                $seller->fname = $sellerData['fname'];
                $seller->mname = $sellerData['mname'];
                $seller->lname = $sellerData['lname'];
                $seller->efname = $sellerData['efname'];
                $seller->emname = $sellerData['emname'];
                $seller->elname = $sellerData['elname'];
                $seller->email = $sellerData['email'];
                $seller->eaddress = $sellerData['eaddress'];
                $seller->partymobilenumber = $sellerData['partymobilenumber'];
                $seller->save();
            }
            DB::commit();
            return responseMsgs(true, "Application Saved successfully", "", "010501", "1.0", "", "POST", $request->deviceId ?? "");
        } catch (Exception $e) {
            DB::rollBack();
            return responseMsgs(false, $e->getMessage(), "", "010502", "1.0", "", "POST", $request->deviceId ?? "");
        }
    }
}
