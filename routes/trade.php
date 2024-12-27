<?php

use App\Http\Controllers\Mdm\TradeController;
use App\Http\Controllers\Trade\ReportControlle;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Trade\TradeApplication;
use App\Http\Controllers\Trade\TradeCitizenController;
use App\Http\Controllers\Trade\TradeNoticeController;
use App\Http\Controllers\Trade\ReportController;

/**
 * | Created On-06-10-2022 
 * | Created For-The Routes defined for the Water Usage Charge Management System Module
 * | Created By-SandeepBara  
 */

Route::group(['middleware' => ['json.response', "auth_maker"]], function () {
    /**
     *  -----------------------------------------------------------------
     * |                TRADE MODULE                                      |
     *  ------------------------------------------------------------------  
     * Created on- 06-10-2022
     * Created By- Sandeep Bara
     *  
     */
    Route::controller(TradeApplication::class)->group(function () {
        Route::post("getApplyData", "getApplyData");
        // Route::post("application/new-license", "getMstrForNewLicense");
        // Route::post("application/renewal", "getMstrForRenewal");
        // Route::post("application/amendment", "getMstrForAmendment");
        // Route::post("application/surrender", "getMstrForSurender");
        Route::post('application/add', 'applyApplication');



        Route::post('application/get-demand', 'paybleAmount');

        Route::post('property/by-holding', 'validateHoldingNo');

        Route::post('application/edit-by-id', 'updateLicenseBo');

        Route::post('application/edit', 'updateBasicDtl');

        Route::post('application/doc-list', 'getDocList');

        Route::post('application/upload-document', 'uploadDocument');

        Route::post('appliction/documents', 'getUploadDocuments');

        Route::post('application/document-verify', 'documentVerify');

        Route::post('application/dtl-by-id', 'getLicenceDtl');

        Route::post('notice/details', "getDenialDetails");

        Route::post('application/search-for-renew', 'searchLicence');

        Route::post('application/list', 'readApplication');

        Route::post('application/escalate', 'postEscalate');

        Route::post('application/btc', 'backToCitizen');

        Route::post('workflow/dashboard-data', 'workflowDashordDetails');

        Route::post('application/escalate-inbox', 'specialInbox');

        Route::post('application/btc-inbox', 'btcInbox');

        Route::post('application/inbox', 'inbox');

        Route::post('application/outbox', 'outbox');
        Route::post('application/pyment-pending', 'approvedButNotPayment');

        Route::post('application/post-next', 'postNextLevel');

        Route::post('application/approve-reject', 'approveReject');

        Route::post('application/independent-comment', 'addIndependentComment');

        Route::post('application/pay-charge', 'PaymentCounter');

        Route::post('application/approved-list', 'approvedApplication');

        Route::post('application/get-independent-comment', 'readIndipendentComment');

        Route::post('application/renewal-surrender-amend-list', 'ReAmentSurrenderList');

        Route::post('application/counter-renewable-list', 'CRenewalList');                  #id = c8

        Route::post('application/counter-amendable-list', 'CAmendmentList');                 #id = c9

        Route::post('application/counter-surrenderable-list', 'CSurrenderList');             #id = c10

        Route::post('application/old-app-edit', 'editOldApp');
        Route::post("license/counter", "updateLicenseCounter");

        # ==== written by Arshad Temporary license=========#
        Route::post('application/add-temporary', 'applyTempApplication');

        Route::post('application/get-trade-temp-docs', 'getTempUploadDocuments');

        Route::post('application/search-temp-application', 'searchTempTrade');

        Route::post('application/inbox-temp-license', 'inboxTemplice');

        Route::post('application/outbox-temp-license', 'outboxTempLicense');

        Route::post('application/special-temp-license', 'specialTempInbox');

        Route::post('application/temp-dtl-by-id', 'readLicenceDtlTempLicense');
    });

    Route::controller(TradeNoticeController::class)->group(function () {
        Route::post('notice/add', 'applyDenail');

        Route::post('notice/inbox', 'inbox');

        Route::post('notice/outbox', 'outbox');

        Route::post('notice/btc-inbox', 'btcInbox');

        Route::post('notice/post-next', 'postNextLevel');

        Route::post('notice/approve-reject', 'approveReject');

        Route::post('notice/view', 'denialview');
    });

    #------------citizenApplication--------------------- 
    Route::controller(TradeCitizenController::class)->group(function () {

        Route::post('application/citizen-ward-list', "getWardList");               #id = c1

        Route::post('application/citizen-add', 'applyApplication');                #id = c2        

        Route::post('notice/citizen-details', "getDenialDetails");                 #id = c3 

        Route::post('application/send-to-officer', 'sendToLevel');

        Route::post('application/pay-razorpay-charge', 'handeRazorPay');           #id = c4
        Route::post('application/pay-pinelab-charge', 'handelPinelab');           #id = c4.1

        Route::post('application/conform-razorpay-tran', 'conformRazorPayTran');   #id = c5

        Route::post('application/citizen-application', 'citizenApplication');      #id = c6

        Route::post('application/citizen-by-id', 'readCitizenLicenceDtl');         #id = c7



        Route::post('application/renewable-list', 'renewalList');                  #id = c8

        Route::post('application/amendable-list', 'amendmentList');                 #id = c9

        Route::post('application/surrenderable-list', 'surrenderList');             #id = c10

        Route::post('application/attached-list', "readAtachedLicenseDtl");
        Route::post('application/attached-list-citizen', "readAtachedLicenseDtlV1");

        Route::post('application/citizen-application-list', "citizenApplicationByCitizenId");
        Route::post('application/citizen-history', "citizenHistory");
        Route::post('application/status', "citizenApplicationStatus");

        Route::post('application/init-payment', "initPayment");
        Route::post('application/easebuzz-handel-response', "easebuzzHandelResponse");
        Route::post('application/license-init-payment', "initPaymentReceipt");

        Route::post('application-temp/citizen-by-id', 'readCitizenTemplicenseDtl');
    });

    #-----------report----------------------------------
    Route::controller(ReportController::class)->group(function () {
        Route::post("dashboard", "tradeDaseboard");
        Route::post("dashboard-application-collection", "applicationTypeCollection");
        Route::post("dashboard-applied-application", "userAppliedApplication");
        Route::post("dashboard-collection-perfomance", "collectionPerfomance");
        Route::post("application/collection-reports", "CollectionReports");
        Route::post("application/team-summary", "teamSummary");
        Route::post("application/valid-expire-list", "valideAndExpired");
        Route::post("application/collection-summary", "CollectionSummary");
        Route::post("application/track-status", "ApplicantionTrackStatus");
        Route::post("application/application-agent-notice", "applicationAgentNotice");
        Route::post("application/notice-summary", "noticeSummary");
        Route::post("application/levelwisependingform", "levelwisependingform");
        Route::post("application/leveluserpending", "levelUserPending");
        Route::post('application/userWiseWardWiseLevelPending', 'userWiseWardWiseLevelPending');
        Route::post('application/levelformdetail', 'levelformdetail');
        Route::post('application/userwiselevelpending', 'userWiseLevelPending');
        Route::post('application/bulk-payment-recipt', 'bulkPaymentRecipt');
        Route::post('application/application-status', 'applicationStatus');
        Route::post('application/today', 'todaysApplication');
        Route::post('ward-list', 'WardList');
        Route::post('tc-list', 'TcList');
    });

    #-----------mdm-------------------------------------
    Route::controller(TradeController::class)->group(function () {
        // Route::post('firm-type-add', 'addFirmType');
        Route::post('firm-type-list', 'firmTypeList');
        Route::post('firm-type', 'firmType');
        // Route::post('firm-type-update', 'updateFirmType');

        // Route::post('application-type-add', 'addApplicationType');
        Route::post('application-type-list', 'applicationTypeList');
        Route::post('application-type', 'applicationType');
        // Route::post('application-type-update', 'updateApplicationType');

        // Route::post('category-type-add', 'addCategoryType');
        Route::post('category-type-list', 'categoryTypeList');
        Route::post('category-type', 'categoryType');
        // Route::post('category-type-update', 'updateCategoryType');

        // Route::post('item-type-add', 'addItemType');
        Route::post('item-type-list', 'itemTypeList');
        Route::post('item-type', 'itemType');
        // Route::post('item-type-update', 'updateItemType');        

        // Route::post('rate-add', 'addRate');
        Route::post('rate-list', 'rateList');
        Route::post('rate', 'rate');
        // Route::post('rate-update', 'updateRate');

        // Route::post('ownership-type-add', 'addOwnershipType');
        Route::post('ownership-type-list', 'ownershipTypeList');
        Route::post('ownership-type', 'ownershipType');
        // Route::post('ownership-type-update', 'updateOwnershipType');
        Route::post('update-mobile', 'updateMobile');
    });
});

Route::controller(TradeApplication::class)->group(function () {
    Route::get('payment-receipt/{id}/{transectionId}', 'paymentReceipt');
    Route::get('provisional-certificate/{id}', 'provisionalCertificate');
    Route::get('license-certificate/{id}', 'licenceCertificate');
    Route::post('application/list-direct', 'readApplicationv1');
    Route::post('application/dtl-by-id-direct', 'getLicenceDtlv1');
    Route::post('application/send-sms', 'sendSms');
});

Route::controller(TradeCitizenController::class)->group(function () {
    Route::post('application/citizen-by-id-direct', 'readCitizenLicenceDtl');
});
