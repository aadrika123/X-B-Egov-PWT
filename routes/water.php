<?php

use App\Http\Controllers\Water\NewConnectionController;
use App\Http\Controllers\Water\WaterApplication;
use App\Http\Controllers\Water\WaterConsumer;
use App\Http\Controllers\Water\WaterConsumerWfController;
use App\Http\Controllers\Water\WaterPaymentController;
use App\Http\Controllers\Water\WaterReportController;
use App\Repository\Water\Concrete\WaterNewConnection;
use Illuminate\Support\Facades\Route;

/**
 * | ----------------------------------------------------------------------------------
 * | Water Module Routes |
 * |-----------------------------------------------------------------------------------
 * | Created On-06-10-2022 
 * | Created For-The Routes defined for the Water Usage Charge Management System Module
 * | Created By-Anshu Kumar
 */

Route::post('/apply-new-connection', function () {
    dd('Welcome to simple Water route file');
});

Route::group(['middleware' => ['json.response', 'auth_maker']], function () { //'auth_maker'
    /**
     * | Created On-08-10-2022 
     * | Updated by-Sam kerketta
     * | ------------------- Apply New Water Connection ------------------------ |
     */
    // Route::resource('application/apply-new-connection', NewConnectionController::class);                //01
    /**
     * | Created On:08-11-2022 
     * | Created by:Sam Kerketta
     * | ------------------- Water Connection / mobile ------------------------ |
     */
    Route::controller(NewConnectionController::class)->group(function () {
        # Workflow
        Route::post('inbox', 'waterInbox');                                                             // Workflow
        Route::post('outbox', 'waterOutbox');                                                           // Workflow
        Route::post('post-next-level', 'postNextLevel');                                                // Workflow
        Route::post('workflow/application/get-by-id', 'getApplicationsDetails');                        // Workflow
        Route::post('special-inbox', 'waterSpecialInbox');                                              // Workflow
        Route::post('escalate', 'postEscalate');                                                        // Workflow                     
        Route::post('application/approval-rejection', 'approvalRejectionWater');                        // Workflow
        Route::post('comment-independent', 'commentIndependent');                                       // Workflow
        Route::post('field-verified-inbox', 'fieldVerifiedInbox');                                      // Workflow
        Route::post('back-to-citizen', 'backToCitizen');                                                // Workflow
        Route::post('btc-inbox', 'btcInbox');                                                           // Workflow
        Route::post('workflow/get-doc-list', 'getDocList');                                             // Workflow
        Route::post('doc-verify-reject', 'docVerifyRejects');                                           // Workflow
        Route::post('upload-document', 'uploadWaterDoc');                                               // Workflow/Citizen
        Route::post('get-upload-documents', 'getUploadDocuments');                                      // Workflow/Citizen  

        # Admin / Citizen view
        Route::post('application/delete', 'deleteWaterApplication');                                    // Citizen     
        Route::post('application/get-by-id', 'getApplicationDetails');                                  // Citizen
        Route::post('application/rejected-get-by-id', 'getRejectedApplicationDetails');                 // Citizen
        Route::post('approve-application/get-by-id', 'getApproveApplicationsDetails');                  // Admin
        Route::post('citizen/get-doc-list', 'getDocToUpload');                                          // Citizen  
        Route::post('application/edit', 'editWaterAppliction');                                         // Citizen/Admin
        Route::post('search-holding-saf', 'getSafHoldingDetail');                                       // Admin
        Route::post('application/search', 'getAprroveApplictaions');                                    // Admin
        Route::post('application/applied-application-search', 'getAppliedApplictaions');                // Applied Apllication Admin Side (jsk)
        Route::post('admin/application/get-details-by-id', 'getApplicationDetailById');                 // Admin
        Route::post('admin/application/list-details-by-date', 'listApplicationBydate');                 // Admin
        Route::post('search-consumer', 'searchWaterConsumer');                                          // Admin/Consumer   
        Route::post('consumer/get-listed-details', 'approvedWaterApplications');                        // Consumer 

        # Site Inspection
        Route::post('admin/search-application', 'searchApplicationByParameter');                        // Admin
        Route::post('admin/application/save-inspection-date', 'saveInspectionDateTime');                // Workflow/Admin
        Route::post('admin/application/site-inspection-details', 'getSiteInspectionDetails');           // Workflow/Admin
        Route::post('admin/application/cancel-inspection-scheduling', 'cancelSiteInspection');          // Workflow/Admin
        Route::post('admin/application/je-site-details', 'getJeSiteDetails');                           // Workflow/Admin
        Route::post('admin/application/je-site-details', 'getJeSiteDetails');                           // Workflow/Admin
        Route::post('admin/application/online-technical-inspection', 'onlineSiteInspection');           // Workflow
        Route::post('admin/application/technical-inspection-details', 'getTechnicalInsDetails');        // Workflow
        // Route::post('search-holding-saf', 'searchHoldingsaf');
        Route::post('get-all-details', 'getdetailsbyId');                                               // akola
        # new connection for akoal water 
        Route::post('application/apply-new-connection', 'applyWaterNew');
        Route::post('application/holding', 'searchHolding');
        //check
        Route::post('check-test', 'check');
        Route::post('citizen/approve-application', 'getCitizenApproveApplication');
        Route::post('application/jsk-get-by-id', 'getApproveAplications');                                  // Admin
        Route::post('applications/send-to-officer', 'sendToLevel');
        Route::post('approve/application-list', 'waterApproveApplication');                          // Approve Application List

    });

    /**
     * | Created on : 10-02-2023
     * | Created By : Sam kerketta
     * |-------------- Water transaction and Payment related ---------------|
     */
    Route::controller(WaterPaymentController::class)->group(function () {
        # Consumer And Citizen Transaction Operation

        Route::post('master/get-listed-details', 'getWaterMasterData');                                 // Admin/ Citizen


        Route::post('consumer/calculate-month-demand', 'callDemandByMonth');                            // Admin/Consumer
        Route::post('application/payment/get-payment-history', 'getApplicationPaymentHistory');         // Admin/Consumer
        Route::post('consumer/offline-demand-payment', 'offlineDemandPayment');                         // Consumer
        Route::post('application/payment/offline/pay-connection-charge', 'offlineConnectionPayment');   // Admin

        Route::post('consumer/online-demand-payment', 'initiateOnlineDemandPayment');                   // Citizen
        Route::post('citizen/payment-history', 'paymentHistory');                                       // Citizen  
        Route::post('consumer/water-user-charges', 'getWaterUserCharges');                              // Admin / Citizen

        # Site inspection 
        Route::post('site-verification/save-site-details', 'saveSitedetails');                          // Admin
        Route::post('consumer/offline-payment', 'Consumerofflinepayment');

        # FOR AKOLA 
        Route::post('consumer/offline-request-payment', 'offlineConPayment');
        Route::post('consumer/reconnect-offline-request-payment', 'offlineReconPayment');
        Route::post('consumer/part-payment', 'partPayment');                                             //status working
        Route::post('consumer/online-payment', 'getReferalUrl');
        Route::post('consumer/demand-payment', 'partPaymentV2');                                                                     // use in consumer part
        Route::post('consumer/payment-receipt', 'generateDemandPaymentReceiptV2');
        Route::post('consumer/transaction-deactivation', 'transactionDeactivation');
        # for deactivation  consumer
        Route::post('admin/consumer/offline-request-payment', 'offlineConReqPayment');

        # for grievance
        Route::post('grievance/get-user-transactions', 'getUserTransactions');
        Route::post('application/citizen-application-list', 'getCitizenApplicationList');
        Route::post('consumer/cheque-detail-update', 'chequeUpdetails');
        Route::post('old-payment-entry', 'oldPaymentEntry');

        # Paynimo Online payment Integration 
        Route::post('paynimo/payment-initiate', 'initiatePayment');
        Route::post('worldline/payment-Response', 'WorldlineHandelResponse');
        # worldline Online payment For Demand Collection


        # test api
        Route::post('testv2', 'testv2');
        Route::post('/water-payment/checkout', 'waterPaymentResponse');
    });

    /**
     * | Created On : 11-02-2023
     * | Created By : Sam kerketta
     * |------------- Water Consumer and Related -------------|
     */
    Route::controller(WaterConsumer::class)->group(function () {
        Route::post('consumer/list-demand', 'listConsumerDemand');                                      // Consumer
        Route::post('admin/consumer/generate-demand', 'saveGenerateConsumerDemand');                    // Admin   Akola
        Route::post('admin/consumer/save-connection-meter', 'saveUpdateMeterDetails');                  // Admin
        Route::post('consumer/update-con-type', 'updateConnectionType');
        Route::post('admin/consumer/get-meter-list', 'getMeterList');                                   // Admin
        Route::post('consumer/caretaken-connections', 'viewCaretakenConnection');                       // Citiizen
        Route::post('consumer/calculate/meter-fixed-reading', 'calculateMeterFixedReading');            // Admin
        Route::post('consumer/self-generate-demand', 'selfGenerateDemand');                             // Citizen

        # Unfinished API
        Route::post('admin/consumer/add-fixed-rate', 'addFixedRate');               // Here             // Admin / Not used
        Route::post('consumer/generate-memo', 'generateMemo');                      // Here             // Admin / Citizen
        Route::post('consumer/search-fixed-connections', 'searchFixedConsumers');   // Here             // Admin / Not used
        Route::post('consumer/add-advance', 'addAdvance');                                              // Admin
        Route::post('check-doc', 'checkDoc');                                       // testing document service

        # Deactivation
        Route::post('apply-water-disconnection', 'applyDeactivation');                           // Admin / Not Used
        Route::post('admin/consumer/demand-deactivation', 'consumerDemandDeactivation');  // Here       // Admin / Not used
        Route::post('get-listed-fee', 'test');

        # for akola 
        Route::post('consumer/apply-offline-newconnection', 'applyWaterConnection');     // for akola consumer 
        Route::post('consumer/get-master-data', 'getMasterData');
        Route::post('consumer/get-consumer-demand', 'getConsumerDemands');
        Route::post('consumer/update-consumer-details', 'updateConsumerDetails');
        Route::post('consumer/update-consumer-log-search', 'searchUpdateConsumerLog');
        Route::post('consumer/update-consumer-log-dtl', 'consumerUpdateDetailLogs');
        Route::post('consumer/get-details', 'WaterConsumerDetails');
        Route::post('consumer/get-connection-list', 'getConnectionList');
        Route::post('consumer/demands-correction', 'updateConsumerDemands');
        Route::post("consumer/demands-correct", "AutoCorrectDemand");

        Route::post("consumer/get-demand-history", "getConsumerDemandsHistory");
        Route::post("consumer/auto-generate-fixed-demand", "gerateAutoFixedDemand");
        Route::post('consumer/update-mobile-no', 'updateMobile');
        Route::post('consumer/tc-visit-records', 'tcVisitRecordUpdate');
        Route::post('consumer/search-records-tc-visit', 'searchTcVisitRecords');
    });


    /**
     * | Created On : 15-07-2023
     * | Created By : Sam kerketta
     * |------------ Water Consumer Workflow -------------|
     */
    Route::controller(WaterConsumerWfController::class)->group(function () {
        Route::post('consumer/req/inbox', 'consumerInbox');                                                  // Workflow
        Route::post('consumer/req/outbox', 'consumerOutbox');                                               // Workflow
        Route::post('consumer/req/get-details-by', 'getWorkflow');                                          // Workflow
        Route::post('consumer/req/post-next-level', 'consumerPostNextLevel');                               // Here
        Route::post('consumer/req/list-req-docs', 'listDocToUpload');                                       // Here
        Route::post('consumer/req/doc-verify-reject', 'consumerDocVerifyReject');                           // Here
        Route::post('consumer/req/get-upload-documents', 'getDiscUploadDocuments');                         // Here
        // Route::post('consumer/req/get-upload-documents', 'getConsumerDocs');                              // Here
        Route::post('consumer/req/approval-rejection', 'consumerApprovalRejection');                         // Here
        Route::post('get-details-applications', 'getConApplicationDetails');
        Route::post('get-details-disconnections', 'getRequestedApplication');                                // Citizen / Changes the route name
        Route::post('consumer/req/get-disconnected-details', 'getDetailsDisconnections');
        Route::post('consumer/reqs/reupload-document', 'reuploadDocument');                                   // 21 ( Reupload Document for Pending Documents)
        Route::post('workflow/get-doc-list-je', 'getDocListForJe');                                             // Workflow
        Route::post('workflow/upload-doc-je', 'uploadWaterDocJe');                                             // Workflow
        Route::post('workflow/unauthorized-tap-status-update', 'unauthorizedTapUpdateStatus');                                             // Workflow  JE
        Route::post('consumer/req/back-to-citizen', 'backToCitizen');                                                // Workflow
        Route::post('consumer/req/btc-inbox', 'btcInbox');                                                           // Workflow
        Route::post('consumer/req/special-inbox', 'waterSpecialInbox');                                              // Workflow
        Route::post('consumer/req/escalate', 'postEscalate');                                                        // Workflow           

        # Route for Water Reconnect Process
        Route::post("consumer/reconnect-consumer", "reconnectConsumerFun");                                          // Reconnect Consumer Citizen Side 
        Route::post("consumer/reconnect-inbox", "reconnectInbox");                                                 // Reconnect Consumer Citizen Side 
        Route::post("consumer/reconnect-special-box", "waterSpecialInboxRec");                                                 // Reconnect Consumer Citizen Side 
        Route::post("consumer/reconnect-outbox", "reconnectOutbox");                                                 // Reconnect Consumer Citizen Side 
        Route::post('consumer/req/reconnect-get-details-by', 'getRecWorkflow');                                         // Workflow
        Route::post('workflow/reconnect/get-doc-list-je', 'getDocListForJeReconnect');
        Route::post('workflow/reconnect/upload-doc-list-je', 'uploadWaterDocJeReconnect');
        Route::post('workflow/reconnect/post-next-level', 'consumerPostNextLevelReconnect');
        Route::post('workflow/reconnect/get-upload-docs', 'getDiscUploadDocumentReconnect');
        Route::post('workflow/reconnect/doc-verify-reject', 'reconnectDocVerifyReject');
        Route::post('workflow/reconnect/approval-rejection', 'consumerApprovalRejectionReconnect');
        Route::post('workflow/reconnect/get-consumer-details', 'getApplicationDetailById');
        Route::post('workflow/reconnect/escalate', 'postEscalateForReconnect');
    });

    /**
     * | Created On : 17-04-2023
     * | Created By : Sam kerketta
     * |------------- Water Reports -------------|
     */
    Route::controller(WaterReportController::class)->group(function () {
        Route::post('consumer/report/list-ward-dcb', 'wardWiseDCB');                                    //01
        Route::post('consumer/report/dcb-pie-chart', 'dcbPieChart');                                    //02
        Route::post('report-cosumer', 'consumerReport');                                                //03
        Route::post('connection-collection', 'connectionCollection');                                   //04
        #akola
        Route::post('report/water-ward-wise-dcb', 'WaterWardWiseDCB');
        Route::post('report/water-dcb', 'WaterdcbReport');
        Route::post('report/water-transaction', 'getTransactionDetail');
        Route::post('report/tc-visit-report', 'tCvisitReport');
        Route::post('report/total-water-consumer', 'totalConsumerType');
        Route::post('report/billing-summary', 'billingSummary');
        Route::post('ward-list', 'WardList');
        Route::post('report/tc-collection', 'WaterCollectionReport');                                         // tc collection report
        Route::post('reports/user-wise/coll-summary', 'userWiseCollectionSummary');                           // tc over all report
        Route::post('report/water-demands-reports', 'WaterDemandsReport');
        Route::post('consumer/demands/bulk-receipt', 'bulkReceipt');
        Route::post('report/water-collection-report', 'WaterCollectionConsumerReport');
        Route::post('report/ward-wise-demand', 'wardWiseConsumerReport');
        Route::post('report/billing-summary', 'billingSummary');
        // Route::post('bill/demand-bill', 'waterBulkdemand');                                                   // demand bulk view bill
        Route::post('bill/demand-bill', 'waterBulkdemandV2');
        Route::post('consumer/demand-bill', 'waterBulkdemandV3');
        Route::post('receipt/bulk-receipt', 'bulkReceipt');
        Route::post('report/date-wise-collection', 'dateCollectuionReport');
        Route::post('report/tc-collection-report', 'tcCollectionReport');
        Route::post('report/tc-visit-records', 'tcvisitRecords');
        Route::post('report/tc-visit', 'tcvisitRecordsv2');
        Route::post('reports/tran-deactived-list', 'tranDeactivatedList');
        Route::post('reports/device-type/collection', 'deviceTypeCollection');
        Route::post('reports/demand-updated', 'searchUpdateConsumerDemand');
        Route::post('reports/demand-updated-dtls', 'consumerUpdateDemandLogs');

        //written by prity pandey
        #Created On:10-08-2024
        Route::post('reports/demand-dues-report', 'consumeDemandDuesReport');
        Route::post('reports/generate-notice', 'generateNotice');
        Route::post('reports/generated-notice-list', 'generateNoticeList');
        Route::post('reports/generated-3rd-notice-list', 'generateNoticeListFinal');
        Route::post('reports/send-to-JE', 'sendToJe');
        Route::post('JeInbox', 'jeInbox');
        Route::post('JeOutbox', 'jeOutbox');
        Route::post('JeViewApplicationDetail', 'viewDetail');
        Route::post('JeGetDocList', 'DocToUpload');
        Route::post('JeUploadDocument', 'DocUpload');
        Route::post('farwardApplication', 'consumerPostNextLevel');
        Route::post('uploadedDocumentList', 'getDiscUploadDocuments');
        Route::post('finalApproval', 'consumerApprovalRejection');
        // Route::post('noticeDtlConsumer', 'consumerNotice');
    });

    /**
     * | Created On:09-12-2022 
     * | Created by:Sandeep Bara
     * | Modified by: Arshad Hussain
     * | Modified on: 12-08-2024
     * | ------------------- Water Connection / mobile ------------------------ |
     */
    Route::controller(WaterApplication::class)->group(function () {
        Route::post('citizenApplications', 'getCitizenApplication');                                    //10
        Route::post('Razorpay-Orderid', 'handeRazorPay');                                               //11
        Route::post('getTranNo', 'readTransectionAndApl');                                              //12
        Route::post('update-applications', 'updateWaterApplication');       // Here 


        # Dashbording Api
        Route::post('admin/application/dashboard-data', 'getJskAppliedApplication');                    //13
        Route::post('admin/workflow/dashboard-data', 'workflowDashordDetails');                         //14
        #citizen DashBoard Application
        Route::post('applications/reject-list', 'rejectedApplication');                                 //15
    });
});
Route::controller(WaterApplication::class)->group(function () {
    Route::post('cargeCal', 'calWaterConCharge');                                                       //16
    Route::post('consumerChargeCal', 'calConsumerDemand');                                              //17
});
Route::controller(WaterConsumer::class)->group(function () {
    Route::post('consumerChargeCal', 'calConsumerDemand');                                              //18        
    Route::post('sendSms', 'sendSms');
    Route::post("consumer/get-consumer-demandV2", "getConsumerDemandsV2");
});
Route::controller(NewConnectionController::class)->group(function () {
    Route::post('citizen/consumer-search', 'searchWaterConsumer');                                                                              // citizen consumer search

});
Route::controller(WaterConsumer::class)->group(function () {
    Route::post('consumer/details', 'WaterConsumerDetails');                                        // details of consumer 
    Route::post('consumer/get-consumer-bill', 'getConsumerDemands');                                // consumer bill
});
Route::controller(WaterPaymentController::class)->group(function () {
    Route::post('citizen/icici-payment', 'initiateOnlineDemandPayment');
    Route::post('citizen/demand/generate-payment-receipt', 'generateDemandPaymentReceipt');
    Route::post('citizen/get-payment-history', 'getConsumerPaymentHistory');
    Route::post('consumer/demand/generate-payment-receipt', 'generateDemandPaymentReceipt');        // Admin/ Citizen          // use in consumer part
    Route::post('admin/application/generate-payment-receipt', 'generateOfflinePaymentReceipt');     // Citizen / Admin
    Route::post('worldline/payment-initiate-demand', 'initiatePaymentDemand');
    Route::post('worldline/payment-response-demand', 'WorldlineHandelResponseDemand');
    Route::post('consumer/get-payment-history', 'getConsumerPaymentHistory');                       // Consumer               // use in consumer part
    // Route::post('')
});

Route::controller(WaterNewConnection::class)->group(function () {
    Route::post('test-icici-respons', 'testIciciResposnse');
});

Route::controller(WaterReportController::class)->group(function () {
    Route::post('noticeDtlConsumer', 'consumerNotice');
    Route::post('bulkNoticeOne', 'bulkNotice1');
});
