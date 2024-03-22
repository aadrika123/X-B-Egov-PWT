<?php

namespace App\Http\Controllers;

use App\Models\MPropBuildingRentalconst;
use App\Models\MPropBuildingRentalRate;
use App\Models\MPropBuildingRentalrate as ModelsMPropBuildingRentalrate;
use App\Models\MPropForgeryType;
use App\Models\MPropRentalValue;
use App\Models\MPropVacanatRentalrate;
use App\Models\MPropVacantRentalrate;
use App\Models\PropApartmentdtl;
use App\Models\Property\RefPropCategory;
use App\Models\Property\RefPropTransferMode;
use App\Models\RefPropBuildingRenatlRate;
use App\Models\RefPropConstructionType;
use App\Models\RefPropFloor;
use App\Models\RefPropGbbuildingusagetype;
use App\Models\RefPropGbpropusagetype;
use App\Models\RefPropObjectionType;
use App\Models\RefPropOccupancyFactor;
use App\Models\RefPropOccupancyType;
use App\Models\RefPropOwnershipType;
use App\Models\RefPropPenaltyType;
use App\Models\RefPropRebateType;
use App\Models\RefPropRoadType;
use App\Models\RefPropType;
use App\Models\RefPropUsageType;
use Illuminate\Http\Request;

/**
 * | Creation of Reference APIs
 * | Created By- Tannu Verma
 * | Created On- 24-05-2023 
 * | Serial No. - 21
 * | Status-Open
 */

 /**
  * | Functions for creation of Reference APIs
    * 1. listBuildingRentalconst()
    * 2. listPropForgeryType()
    * 3. listPropRentalValue()
    * 4. listPropApartmentdtl()
    * 5. listBropBuildingRentalrate()
    * 6. listPropVacantRentalrate()
    * 7. listPropConstructiontype()
    * 8. listPropFloor()
    * 9. listPropgbBuildingUsagetype()
    * 10. listPropgbPropUsagetype()
    * 11. listPropObjectiontype()
    * 12. listPropOccupancyFactor()
    * 13. listPropOccupancytype()
    * 14. listPropOwnershiptype()
    * 15. listPropPenaltytype()
    * 16. listPropRebatetype()
    * 17. listPropRoadtype()
    * 18. listPropTransfermode()
    * 19. listPropType()
    * 20. listPropUsagetype()
*/


class ReferenceController extends Controller
{ 
    /** 
     * 1. listBuildingRentalconst()
     *    Display List for Building Rental Const
    */
    public function listBuildingRentalconst(Request $request)
   {
    try {
        $m_buildingRentalconst = MPropBuildingRentalconst::where('status', 1)
            ->get();

        if (!$m_buildingRentalconst) {
            return responseMsgs(false, "", 'Building Rental Const Not Found', "", "");
        }
        return responseMsgs(true, $m_buildingRentalconst, 'Building Rental Const Retrieved Successfully', "012101", "");
        
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
        
    }
   }
   
   /** 
     * 2. listPropForgeryType()
     *    Display List for Property Forgery type
    */

   public function listPropForgeryType(Request $request)
   {
    try {
        $m_propforgerytype = MPropForgeryType::where('status', 1)
            ->get();
        if (!$m_propforgerytype) {
            return responseMsgs(false, "", 'Forgery type Not Found', "", "");  
        }
        return responseMsgs(true, $m_propforgerytype, 'Forgery type Retrieved Successfully', "012102", "");
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
    }
   }


   /** 
     * 3. listPropRentalValue()
     *    Display List for Property rental Value
    */

    public function listPropRentalValue(Request $request)
    {
     try {
         $m_proprentalvalue = MPropRentalValue::where('status', 1)
             ->get();
         if (!$m_proprentalvalue) {
             return responseMsgs(false, "", 'Rental Value Not Found', "", "");
         }
         return responseMsgs(true, $m_proprentalvalue, 'Rental Value Retrieved Successfully', "012103", "");
     } catch (\Exception $e) {
         return responseMsgs(false, $e->getMessage(), "");
     }
    }


    /** 
     * 4. listPropApartmentdtl()
     *    Display List for Apartment Detail
    */

    public function listPropApartmentdtl(Request $request)
    {
     try {
         $m_propapartmentdtl = PropApartmentDtl::where('status', 1)
             ->get();
 
         if (!$m_propapartmentdtl) {
             return responseMsgs(false, "", 'Apartment details Not Found', "", "");
         }
         return responseMsgs(true, $m_propapartmentdtl, 'Apartment details Retrieved Successfully', "012104", "");
         
     } catch (\Exception $e) {
         return responseMsgs(false, $e->getMessage(), "");
     }
    }

    
    /** 
     * 5. listPropBuildingRentalrate()
     *    Display List for Building Rental Rate
    */

    public function listPropBuildingRentalrate(Request $request)
    {
     try {
         $m_propbuildingrentalrate = MPropBuildingRentalrate::where('status', 1)
             ->get();
 
         if (!$m_propbuildingrentalrate) {
             return responseMsgs(false, "", 'Building Rental Rate Not Found', "", "");
         }
         return responseMsgs(true, $m_propbuildingrentalrate, 'Building Rental Rate Retrieved Successfully', "012105", "");

     } catch (\Exception $e) {
         return responseMsgs(false, $e->getMessage(), "");  
     }
    }

    
    /** 
     * 6. listPropVacantRentalrate()
     *    Display List for Vacant Rental Rate
    */

    public function listPropVacantRentalrate(Request $request)
   {
    try {        
        $status = $request->input('status', 1); // Status filter, default is 1
        
        $m_propvacantrentalrate = MPropVacantRentalrate::where('status', $status)
            ->get();

        if (!$m_propvacantrentalrate->count()) {
            return responseMsgs(false, "", 'Vacant Rental Rate Not Found', "", "");
        }
        return responseMsgs(true, $m_propvacantrentalrate, 'Vacant Rental Rate Retrieved Successfully', "012106", "");   
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
        
    }
  }


  /** 
     * 7. listPropConstructiontype()
     *    Display List for Property Construction Type
    */

    public function listPropConstructiontype(Request $request)
   {
    try {
        $m_propconstructiontype = RefPropConstructionType::where('status', 1)
             ->get();

        if (!$m_propconstructiontype) {
            return responseMsgs(false, "", 'Construction Type Not Found', "", "");   
        }
        return responseMsgs(true, $m_propconstructiontype, 'Construction Type Retrieved Successfully', "012107", "");
        
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
    }
  }


  /** 
     * 8. listPropFloor()
     *    Display List for Property Floor
    */

    public function listPropFloor(Request $request)
   {
    try {
        $status = $request->input('status', 1); // Status filter, default is 1

        $m_propfloor = RefPropFloor::where('status', $status)
            ->get();

        if (!$m_propfloor->count()) {
            return responseMsgs(false, "", 'Floor Type Not Found', "", "");
        }
        return responseMsgs(true, $m_propfloor, 'Floor Type Retrieved Successfully', "012108", "");

    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
    }
  }


   /** 
     * 9. listPropgbBuildingUsagetype()
     *    Display List for Property GB Building Usage Type
    */

    public function listPropgbBuildingUsagetype(Request $request)
   {
    try {
        
        $m_propgbbuildingusagetype = RefPropGbbuildingusagetype::where('status',1)
        ->get();

        if (!$m_propgbbuildingusagetype) {
            return responseMsgs(false, "", 'GB Building Usage Type Not Found', "", "");
        }
        return responseMsgs(true, $m_propgbbuildingusagetype, 'GB Building Usage Type Retrieved Successfully', "012109", "");
        
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
    }
  }


  /** 
     * 10. listPropgbPropUsagetype()
     *    Display List for Property Usage Type
    */

    public function listPropgbPropUsagetype(Request $request)
   {
    try {
        
        $m_propgbpropusagetype = RefPropGbpropusagetype::where('status',1)
        ->get();

        if (!$m_propgbpropusagetype) {
            return responseMsgs(false, "", 'GB Property Usage Type Not Found', "", "");
        }
        return responseMsgs(true, $m_propgbpropusagetype, 'GB Property Usage Type Retrieved Successfully', "012110", "");
       
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");       
    }
  }


  /** 
     * 11. listPropObjectiontype()
     *    Display List for Property Objection Type
    */

    public function listpropobjectiontype(Request $request)
   {
    try {
        $m_propobjectiontype = RefPropObjectionType::where('status', 1)
        ->get();

        if (!$m_propobjectiontype) {
            return responseMsgs(false, "", 'Property Objection Type Not Found', "", "");
            
        }
        return responseMsgs(true, $m_propobjectiontype, 'Property Objection Type Retrieved Successfully', "012111", "");
        
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
    }
  }


  /** 
     * 12. listPropOccupancyFactor()
     *    Display List for Property Occupancy Factor
    */

    public function listPropOccupancyFactor(Request $request)
   {
    try {
        $m_propoccupancyfactor = RefPropOccupancyFactor::where('status', 1)
        ->get();

        if (!$m_propoccupancyfactor) {
            return responseMsgs(false, "", 'Property Occupancy Factor Not Found', "", "");
        }
        return responseMsgs(true, $m_propoccupancyfactor, 'Property Occupancy Factor Retrieved Successfully', "012112", "");
  
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");
        
    }
  }


  /** 
     * 13. listPropOccupancytype()
     *    Display List for Property Occupancy Type
    */

    public function listPropOccupancytype(Request $request)
   {
    try {
        $m_propoccupancytype = RefPropOccupancyType::where('status', 1)
        ->get();

        if (!$m_propoccupancytype) {
            return responseMsgs(false, "", 'Property Occupancy Type Not Found', "", "");        
        }
        return responseMsgs(true, $m_propoccupancytype, 'Property Occupancy Type Retrieved Successfully', "012113", "");      
    } catch (\Exception $e) {
        return responseMsgs(false, $e->getMessage(), "");       
    }
  }


   /** 
     * 14. listPropOwnershiptype()
     *    Display List for Property Ownership Type
    */
    
    public function listPropOwnershiptype(Request $request)
    {
      try {
         $m_propownershiptype = RefPropOwnershipType::where('status', 1)
         ->get();

         if(!$m_propownershiptype){
         return responseMsgs(false, "", 'Property Ownership Type not found', "", "");
         }
         return responseMsgs(true, $m_propownershiptype, 'Property Ownership Type Retrieved Successfully', "012114", "");
        
        }catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");
        }

      }

    /** 
     * 15. listPropPenaltytype()
     *    Display List for Property Penalty Type
    */
    
    public function listPropPenaltytype(Request $request)
    {
        try {
            $m_proppenaltytype = RefPropPenaltyType::where('status', 1)
            ->get();
            
            if(!$m_proppenaltytype){
            return responseMsgs(false, "", 'Property Penalty Type not find', "", "");
            }
            return responseMsgs(true, $m_proppenaltytype, 'Property Penalty Type Retrieved Successfully', "012115", "");

         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");
            
        }
    }

    /** 
     * 16. listPropRebatetype()
     *    Display List for Property Rebate Type
    */
    
    public function listPropRebatetype(Request $request)
    {
        try {
            $m_proprebatetype = RefPropRebateType::where('status', 1)
            ->get();
            
            if(!$m_proprebatetype){
            return responseMsgs(false, "", 'Property Rebate Type not find', "", "");
            }
            return responseMsgs(true, $m_proprebatetype, 'Property Rebate Type Retrieved Successfully', "012116", "");           

         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");

        }
    }
    
    
    /** 
     * 17. listPropRoadtype()
     *    Display List for Property Road Type
    */
    
    public function listPropRoadtype(Request $request)
    {
        try {
            $m_proproadtype = RefPropRoadType::where('status', 1)
            ->get();
            
            if(!$m_proproadtype){
            return responseMsgs(false, "", 'Property Road Type not find', "", "");
            }
           return responseMsgs(true, $m_proproadtype, 'Property Road Type Retrieved Successfully', "012117", "");

         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");
            
        }
    }



    /** 
     * 18. listPropTransfermode()
     *    Display List for Property Transfer Mode
    */
    
    public function listPropTransfermode(Request $request)
    {
        try {
            $m_proptransfermode = RefPropTransferMode::where('status', 1)
            ->get();
            
            if(!$m_proptransfermode){
            return responseMsgs(false, "", 'Property Transfer Mode not find', "", "");
            }
            return responseMsgs(true, $m_proptransfermode, 'Property Transfer Mode Retrieved Successfully', "012118", "");
            
         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");          
        }
    }


    /** 
     * 19. listProptype()
     *    Display List for Property Type
    */
    
    public function listProptype(Request $request)
    {
        try {
            $m_proptype = RefPropType::where('status', 1)
            ->get();
            
            if(!$m_proptype){
            return responseMsgs(false, 'Property Type not find',  "", "");
            }
            return responseMsgs(true,  'Property Type Retrieved Successfully', $m_proptype,"012119", "");

         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");
       }

    }


    /** 
     * 20. listPropUsagetype()
     *    Display List for Property Usage Type
    */
    
    public function listPropUsagetype(Request $request)
    {
        try {
            $m_propusagetype = RefPropUsageType::where('status', 1)
            ->get();
            
            if(!$m_propusagetype){
            return responseMsgs(false, "Property Usage Type not find", '');
            }

            return responseMsgs(true, 'Property Usage Type Retrieved Successfully',  $m_propusagetype,  "012120", "");

         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");
        }
    }

    /**
     * | categoryType
     */
    public function categoryType(Request $request)
    {
        try {
            $mCategory = RefPropCategory::select('id','category','status','description')
                                ->where('status', 1)
                                ->get();
            
            return responseMsgs(true, 'Category Type',  $mCategory,  "012121", "");

         } catch(\Exception $e){
            return responseMsgs(false, $e->getMessage(), "");
        }
    }


}

    
     

    




  
  

  





  









