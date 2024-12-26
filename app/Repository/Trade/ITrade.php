<?php

namespace App\Repository\Trade;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * | Created On-01-10-2022 
 * | Created By-Sandeep Bara
 * ------------------------------------------------------------------------------------------
 * | Interface for Eloquent Property Repository
 */

interface ITrade
{
    public function __construct();
    public function addRecord(Request $request);
    public function addRecordTemp(Request $request);
    public function paymentCounter(Request $request);
    public function isvalidateHolding(Request $request);
    public function searchLicenceByNo(Request $request);
    public function searchLicence(string $licence_no, $ulb_id);
    public function readApplication(Request $request);
    public function readApplicationv1(Request $request);
    public function postEscalate(Request $request);
    public function updateLicenseBo(Request $request);
    public function updateBasicDtl(Request $request);
    public function getUploadDocuments(Request $request);
    public function documentVirify(Request $request);
    public function readLicenceDtl($id);
    public function readLicenceDtlv1($id);
    public function readDenialdtlbyNoticno(Request $request);
    public function getPaybleAmount(Request $request);
    public function readPaymentReceipt($id, $transectionId);
    public function getAllApplicationType();
    public function specialInbox(Request $request);
    public function btcInbox(Request $request);
    public function inbox(Request $request);
    public function outbox(Request $request);
    public function approvedButNotPayment(Request $request);
    public function addIndependentComment(Request $request);
    public function readIndipendentComment(Request $request);
    public function provisionalCertificate($id);
    public function licenceCertificate($id);
    
    public function approvedApplication(Request $request);
    public function getLicenceDocuments($id, $tbl = "active_");
    public function getLicenceById($id);
    public function updateLicenseCounter($id);
    public function searchTempTradeLicense(Request $request);
}
