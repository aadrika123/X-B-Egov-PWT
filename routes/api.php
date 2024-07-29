<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApiMasterController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\CustomController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\Service\IdGeneratorController;
use App\Http\Controllers\ThirdPartyController;
use App\Http\Controllers\UlbController;
use App\Http\Controllers\UlbWorkflowController;
use App\Http\Controllers\Workflows\WorkflowController;
use App\Http\Controllers\Workflows\WorkflowTrackController;
use App\Http\Controllers\Ward\WardController;
use App\Http\Controllers\WcController;
use App\Http\Controllers\WorkflowMaster\RoleController;
use App\Http\Controllers\Workflows\UlbWorkflowRolesController;
use App\Http\Controllers\WorkflowMaster\WorkflowRoleController;
use App\Http\Controllers\WorkflowMaster\WorkflowRoleUserMapController;
use App\Http\Controllers\CaretakerController;
use App\Http\Controllers\ReferenceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/**
 * Creation Date:-24-06-2022
 * Created By:- Anshu Kumar
 * -------------------------------------------------------------------------
 * Code Test
 * -------------------------------------------------------------------------
 * Code Tested By-Anil Mishra Sir
 * Code Testing Date:-25-06-2022
 * -------------------------------------------------------------------------
 */

// Route Used for Login and Register the User
/**
 * | Updated By- Sam kerketta
 */
Route::controller(UserController::class)->group(function () {
    Route::post('login', 'loginAuth')->middleware('request_logger');
    Route::post('register', 'store');
});

/**
 * Routes for API masters, api searchable and api edit
 * CreatedOn-29-06-2022
 */

Route::controller(ApiMasterController::class)->group(function () {
    Route::post('save-api', 'store');
    Route::put('edit-api/{id}', 'update');
    Route::get('get-api-by-id/{id}', 'getApiByID');
    Route::get('get-all-apis', 'getAllApis');
    Route::post('search-api', 'search');
    Route::get('search-api-by-tag', 'searchApiByTag');
});

/**
 * | Citizen Registration
 * | Created On-08-08-2022 
 */
Route::controller(CitizenController::class)->group(function () {
    Route::post('citizen-register', 'citizenRegister');         // Citizen Registration
    Route::post('citizen-login', 'citizenLogin')->middleware('request_logger');
    Route::post('citizen-logout', 'citizenLogout')->middleware('auth:sanctum');
});

/**
 * | Created On-14-08-2022 
 * | Created By-Anshu Kumar
 * | Get all Ulbs by Ulb ID
 */
Route::controller(UlbController::class)->group(function () {
    Route::get('get-all-ulb', 'getAllUlb');
    Route::post('city/state/ulb-id', 'getCityStateByUlb');
});

/**
 * | Id Generator
 */
Route::controller(IdGeneratorController::class)->group(function () {
    Route::post('id-generator', 'idGenerator');
});


// Inside Middleware Routes with API Authenticate 
Route::group(['middleware' => ['request_logger', 'expireBearerToken']], function () {

    /**
     * | Api to Check if the User is authenticated or not
     */
    Route::post('/heartbeat', function () {                 // Heartbeat Api
        return response()->json([
            'status' => true,
            'authenticated' => auth()->check()
        ]);
    });

    /**
     * Routes for User 
     * Created By-Anshu Kumar
     * Updated By-Sam Kerketta
     * Created On-20-06-2022 
     * Modified On-27-06-2022 
     */

    Route::controller(UlbController::class)->group(function () {
        Route::post('city/state/auth/ulb-id', 'getCityStateByUlb');
        Route::post('list-ulb-by-district', 'districtWiseUlb');
        Route::post('list-district', 'districtList');
    });

    Route::controller(UserController::class)->group(function () {
        Route::post('authorised-register', 'authorizeStore');               // authorised user adding user // 
        Route::get('test', 'testing');
        Route::post('logout', 'logOut');
        Route::post('change-password', 'changePass');                       // Change password with login
        Route::post('otp/change-password', 'changePasswordByOtp');           // Change Password With OTP   

        // User Profile APIs
        Route::get('my-profile-details', 'myProfileDetails');   // For get My profile Details
        Route::post('edit-my-profile', 'editMyProfile');        // For Edit My profile Details ---->>edited by mrinal method changed from put to post

        Route::post('edit-user', 'update');
        Route::post('delete-user', 'deleteUser');
        Route::get('get-user/{id}', 'getUser');
        Route::get('get-all-users', 'getAllUsers');
        Route::post('list-employees', 'employeeList');
        Route::post('get-user-notifications', 'userNotification');
        Route::post('add-user-notification', 'addNotification');
        Route::post('delete-user-notification', 'deactivateNotification');
        Route::post('hash-password', 'hashPassword');

        // Route are authorized for super admin only using Middleware 
        Route::group(['middleware' => ['can:isSuperAdmin']], function () {
            // Route::put('edit-user/{id}', 'update');
            // Route::delete('delete-user', 'deleteUser');
            // Route::get('get-user/{id}', 'getUser');
            // Route::get('get-all-users', 'getAllUsers');
        });
    });


    /**
     * Routes for Ulbs
     * Created By-Anshu Kumar
     * Creation Date-02-07-2022 
     * Modified On-
     */
    Route::controller(UlbController::class)->group(function () {
        Route::post('save-ulb', 'store');
        Route::put('edit-ulb/{id}', 'edit');
        Route::get('get-ulb/{id}', 'view');
        Route::delete('delete-ulb/{id}', 'deleteUlb');
    });

    /**
     * Routes for Workflows
     * Created By-Anshu Kumar
     * Creation Date-06-07-2022 
     * Modified On-
     */
    Route::controller(WorkflowController::class)->group(function () {
        Route::post('add-workflow', 'storeWorkflow');
        Route::get('view-workflow/{id}', 'viewWorkflow');
        Route::put('edit-workflow/{id}', 'updateWorkflow');
        Route::delete('delete-workflow/{id}', 'deleteWorkflow');
        Route::get('all-workflows', 'getAllWorkflows');

        Route::post('workflow-candidate', 'storeWorkflowCandidate');
        Route::get('view-workflow-candidates/{id}', 'viewWorkflowCandidates');
        Route::get('all-workflow-candidates', 'allWorkflowCandidates');
        Route::put('edit-workflow-candidates/{id}', 'editWorkflowCandidates');
        Route::delete('delete-workflow-candidates/{id}', 'deleteWorkflowCandidates');
        Route::get('gen/workflow/workflow-candidates/{ulbworkflowid}', 'getWorkflowCandidatesByUlbWorkflowID');  // Get Workflow Candidates by ulb-workflow-id
    });

    // Workflow Roles Rest Apis
    Route::resource('workflow/workflow-roles', UlbWorkflowRolesController::class);

    /**
     * APIs for Module Master
     * Created By-Anshu Kumar
     * Creation Date-14-07-2022
     * Modified By-
     */
    Route::resource('crud/module-masters', ModuleController::class);

    /**
     * Api route for Ulb Module Master
     * CreatedBy-Anshu Kumar
     * Creation Date-14-07-2022 
     * Modified By-
     */
    Route::resource('crud/ulb-workflow-masters', UlbWorkflowController::class);

    // Get Ulb Workflow details by Ulb Ids
    Route::get('admin/workflows/{ulb_id}', [UlbWorkflowController::class, 'getUlbWorkflowByUlbID']);

    // Workflow Track
    Route::controller(WorkflowTrackController::class)->group(function () {
        Route::post('save-workflow-track', 'store');                                                                         // Save Workflow Track Messages
        Route::post('get-workflow-track', 'getWorkflowTrackByID');                                                       // Get Workflow Track Message By TrackID
        Route::post('gen/workflow-track', 'getWorkflowTrackByTableIDValue');                     // Get WorkflowTrack By TableRefID and RefTableValue

        //changes by mrinal
        Route::post('workflow-track/getNotificationByCitizenId', 'getNotificationByCitizenId');
    });

    // Citizen Register
    Route::controller(CitizenController::class)->group(function () {
        Route::get('get-citizen-by-id/{id}', 'getCitizenByID');                                                // Get Citizen By ID
        Route::get('get-all-citizens', 'getAllCitizens');                                                      // Get All Citizens
        Route::post('edit-citizen-profile', 'citizenEditProfile');                                             // Approve Or Reject Citizen by Id
        Route::match(['get', 'post'], 'property/citizens/applied-applications', 'getAllAppliedApplications');  // Get Applied Applications
        Route::post('citizens/independent-comment', 'commentIndependent');                                     // Independent Comment for the Citizen to be Tracked
        Route::get('citizens/get-transactions', 'getTransactionHistory');                                      // Get User Transaction History
        Route::post('change-citizen-pass', 'changeCitizenPass');                                               // Change the Password of The Citizen Using its Old Password 
        Route::post('otp/change-citizen-pass', 'changeCitizenPassByOtp');                                      // Change Password using OTP for Citizen
        Route::post('citizen-profile-details', 'profileDetails');
    });

    /**
     * | Created On-19-08-2022 
     * | Created by-Anshu Kumar
     * | Ulb Wards operations
     */
    Route::controller(WardController::class)->group(function () {
        Route::post('store-ulb-wards', 'storeUlbWard');          // Save Ulb Ward
        Route::put('edit-ulb-ward/{id}', 'editUlbWard');         // Edit Ulb Ward
        Route::get('get-ulb-ward/{id}', 'getUlbWardByID');       // Get Ulb Ward Details by ID
        Route::get('get-all-ulb-wards', 'getAllUlbWards'); //not for use      // Get All Ulb Wards
        Route::post('property/v1/get-ward-by-zone', 'getWardByZone');
        Route::post('property/v1/get_ward_name_by_zone', 'getWardByZone')->withoutMiddleware(['request_logger', 'expireBearerToken']);
    });
});


// Routes used where authentication not required
Route::group(['middleware' => ['json.response', 'request_logger', 'expireBearerToken']], function () {

    Route::controller(WardController::class)->group(function () {
        Route::post('get-newward-by-oldward', 'getNewWardByOldWard');
    });
});


//==============================================================================================
//========================                WORKFLOW MASTER           ============================ 
//==============================================================================================


// /**
//  * Creation Date: 06-10-2022
//  * Created By:-   Mrinal Kumar
//  * Modified On :- 17-12-2022
//  * Modified By :- Mrinal Kumar
//  */

Route::group(['middleware' => ['json.response', 'auth:sanctum', 'request_logger']], function () {


    /**
     * workflow roles CRUD operation
     */
    Route::controller(WorkflowRoleController::class)->group(function () {
        Route::post('crud/roles/save-role', 'create');                      #_Save Role
        Route::put('crud/roles/edit-role', 'editRole');                     #_Edit Role
        Route::post('crud/roles/get-role', 'getRole');                      #_Get Role By Id
        Route::get('crud/roles/get-all-roles', 'getAllRoles');              #_Get All Roles
        Route::delete('crud/roles/delete-role', 'deleteRole');              #_Delete Role
    });


    /**
     * ============== To be replaced with upper api  =================
     */
    Route::controller(RoleController::class)->group(function () {
        Route::post('workflow/roles/save', 'createRole');                   // Save Role
        Route::post('workflow/roles/edit', 'editRole');                     // edit Role
        Route::post('workflow/roles/get', 'getRole');                       // Get Role By Id
        Route::post('workflow/roles/list', 'getAllRoles');                  //Get All Roles          
        Route::post('workflow/roles/delete', 'deleteRole');                 // Delete Role
    });
    /**
     * ===================================================================
     */


    /**
     * Role User Map CRUD operation
     */

    Route::apiResource("roleusermap", WorkflowRoleUserMapController::class);


    /**
     * | Created On-14-12-2022 
     * | Created By-Mrinal Kumar
     * | Workflow Traits
     */
    Route::controller(WcController::class)->group(function () {
        Route::post('workflow-current-user', 'workflowCurrentUser');
        Route::post('workflow-initiator', 'workflowInitiatorData');
        Route::post('role-by-user', 'roleIdByUserId');
        Route::post('ward-by-user', 'wardByUserId');
        Route::post('role-by-workflow', 'getRole');
        Route::post('initiator', 'initiatorId');
        Route::post('finisher', 'finisherId');
    });

    /**
     * | for custom details
       | Serial No : 09
     */
    Route::controller(CustomController::class)->group(function () {
        Route::post('get-all-custom-tab-data', 'getCustomDetails');
        Route::post('post-custom-data', 'postCustomDetails');
        Route::post('get-dues-api', 'duesApi');
        Route::post('post-geo-location', 'tcGeoLocation');
        Route::post('list-location', 'locationList');
        Route::post('tc-collection-route', 'tcCollectionRoute');
        Route::post('list-quick-access', 'quickAccessList');
        Route::post('quick-access-byuserid', 'getQuickAccessListByUser');
        Route::post('add-update-quickaccess', 'addUpdateQuickAccess');
    });
});



/**
 * | Caretaker Property , Water , Trade (10)
 */
Route::group(['middleware' => ['json.response', 'auth_maker']], function () {
    Route::controller(CaretakerController::class)->group(function () {
        Route::post('water/caretaker-otp', 'waterCaretakerOtp');
        Route::post('water/caretaker-consumer-tagging', 'caretakerConsumerTag');

        Route::post('citizen/caretake-modules', 'careTakeModules');        // CareTake Modules (01)
        Route::post('citizen/caretake-otp', 'careTakeOtp');                  // Otp for caretaker
    });

    Route::controller(ThirdPartyController::class)->group(function () {
        Route::post('user/send-otp', 'sendOtp');
        Route::post('user/verify-otp', "verifyOtp");
    });
});


Route::group(['middleware' => ['json.response', 'auth:sanctum', 'request_logger', 'expireBearerToken']], function () {

    /**
     * | Created On-23-11-2022 
     * | Created By-Sam kerketta
     * | Menu Permissions
     */
    Route::controller(MenuController::class)->group(function () {
        Route::post('crud/menu/get-all-menues', 'getAllMenues');                    // Get All the Menu List
        Route::post('crud/menu/delete-menues', 'deleteMenuesDetails');              // Soft Delition of the menus
        Route::post('crud/menu/add-new-menues', 'addNewMenues');                    // adding the details of the menues in the menue table
        Route::post('crud/menu/update-menues', 'updateMenuMaster');                 // Update the menu master 

        Route::post('menu/get-menu-by-id', 'getMenuById');                          // Get menu bu menu Id

        Route::post('menu-roles/get-menu-by-roles', 'getMenuByroles');              // Get all the menu by roles
        Route::post('menu-roles/update-menu-by-role', 'updateMenuByRole');          // Update Menu Permission By Role
        Route::post('menu-roles/list-parent-serial', 'listParentSerial');           // Get the list of parent menues

        Route::post('sub-menu/tree-structure', 'getTreeStructureMenu');             // Generation of the menu tree Structure        
        Route::post('sub-menu/get-children-node', 'getChildrenNode');               // Get the children menues
        Route::post('menu/by-module', 'getMenuByModuleId');                         // Get menu by Module Id 

    });
});

/**
 * | Get OTP for the for Change Password
 * | Created By : Sam kerketta
 * | Created At : 06-03-2023
 */





/**
 * | Get Reference List and Ulb Master Crud Operation
 * | Created By : Tannu Verma
 * | Created At : 20-05-2023
 * | Status: Open
 */

// Route::controller(UlbMasterController::class)->group(function () {

//     Route::post('list-ulbmaster', 'listulbmaster');
//     Route::post('store-ulbmaster', 'storeUlbMaster');
//     Route::post('view-ulbmaster', 'showUlbMaster');
//     Route::post('edit-ulbmaster', 'updateUlbMaster');
//     Route::post('deactivate-ulbmaster', 'deactivateUlbMaster');
// });

// Route::controller(WardListController::class)->group(function () {

//     Route::post('store-wardlist', 'storeUlbWardMaster');
//     Route::post('view-wardlist', 'showUlbWardMaster');
//     Route::post('edit-wardlist', 'updateUlbWardMaster');
//     Route::post('update-wardlist', 'deactivateUlbWardMaster');
// });


Route::controller(ReferenceController::class)->group(function () {

    Route::post('property/building-rental-const', 'listBuildingRentalconst');
    Route::post('property/get-forgery-type', 'listpropForgeryType');
    Route::post('property/get-rental-value', 'listPropRentalValue');
    Route::post('property/building-rental-rate', 'listPropBuildingRentalrate');
    Route::post('property/vacant-rental-rate', 'listPropVacantRentalrate');
    Route::post('property/get-construction-list', 'listPropConstructiontype');
    Route::post('property/floor-type', 'listPropFloor');
    Route::post('property/gb-building-usage-type', 'listPropgbBuildingUsagetype');
    Route::post('property/gb-prop-usage-type', 'listPropgbPropUsagetype');
    Route::post('property/prop-objection-type', 'listPropObjectiontype');
    Route::post('property/prop-occupancy-factor', 'listPropOccupancyFactor');
    Route::post('property/prop-occupancy-type', 'listPropOccupancytype');
    Route::post('property/prop-ownership-type', 'listPropOwnershiptype');
    Route::post('property/prop-penalty-type', 'listPropPenaltytype');
    Route::post('property/prop-rebate-type', 'listPropRebatetype');
    Route::post('property/prop-road-type', 'listPropRoadtype');
    Route::post('property/prop-transfer-mode', 'listPropTransfermode');
    Route::post('property/get-prop-type', 'listProptype');
    Route::post('property/prop-usage-type', 'listPropUsagetype');
});


/**
 * This Route is for Demo Purpose
 */
Route::controller(DemoController::class)->group(function () {
    Route::post('water-connection', 'waterConnection');
    Route::post('whatsaap-test', 'testWhatsaap');
});
#-------------------------- document read ------------------------------
Route::get('/getImageLink', function () {
    return view('getImageLink');
});
