<?php

namespace App\Repository\Trade;

use App\BLL\Property\Akola\GetHoldingDuesV2;
use App\BLL\Property\Akola\TaxCalculator;
use Illuminate\Support\Facades\Storage;
use App\EloquentModels\Common\ModelWard;
use App\Models\CustomDetail;
use App\Models\Payment\TempTransaction;
use App\Models\Property\ActiveSafsOwnerDtl;
use App\Models\Property\ActiveSaf;
use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsOwner;
use App\Models\Property\PropOwner;
use App\Models\Property\PropProperty;
use App\Models\Property\ZoneMaster;
use App\Models\Trade\TradeLicence;
use App\Models\Trade\TradeDocument;
use App\Models\Trade\ActiveTradeDocument;
use App\Models\Trade\ActiveTradeLicence;
use App\Models\Trade\ActiveTradeNoticeConsumerDtl;
use App\Models\Trade\ActiveTradeOwner;
use App\Models\Trade\AkolaTradeParamItemType;
use App\Models\Trade\AkolaTradeParamLicenceRate;
use App\Models\Trade\RejectedTradeLicence;
use App\Models\Trade\RejectedTradeOwner;
use App\Models\Trade\RejectedTradeDocument;
use App\Models\Trade\TradeChequeDtl;
use App\Models\Trade\TradeFineRebete;
use App\Models\Trade\TradeNoticeConsumerDtl;
use App\Models\Trade\TradeOwner;
use App\Models\Trade\TradeParamApplicationType;
use App\Models\Trade\TradeParamCategoryType;
use App\Models\Trade\TradeParamDocumentType;
use App\Models\Trade\TradeParamFirmType;
use App\Models\Trade\TradeParamItemType;
use App\Models\Trade\TradeParamLicenceRate;
use App\Models\Trade\TradeParamOwnershipType;
use App\Models\Trade\TradeRenewal;
use App\Models\Trade\TradeSmsLog;
use App\Models\Trade\TradeTransaction;
use App\Models\UlbMaster;
use App\Models\UlbWardMaster;
use App\Models\Workflows\WfActiveDocument;
use App\Models\Workflows\WfWorkflow;
use App\Models\WorkflowTrack;
use App\Models\Workflows\WfRole;
use App\Repository\Common\CommonFunction;
use App\Repository\WorkflowMaster\Concrete\WorkflowMap;
use Illuminate\Http\Request;
use App\Repository\Notice\Notice;

use App\Traits\Auth;
use App\Traits\Property\WardPermission;
use App\Traits\Payment\Razorpay;
use App\Traits\Trade\TradeTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class Trade implements ITrade
{
    use Auth;               // Trait Used added by sandeep bara date 17-09-2022
    use WardPermission;
    use Razorpay;
    use TradeTrait;

    /**
     * | Created On-01-10-2022 
     * | Created By-Sandeep Bara
     * | Status (open)
     * |
     * |----------------------
     * | Applying For Trade License
     * | Proper Validation will be applied 
     * | @param Illuminate\Http\Request
     * | @param Request $request
     * | @param response
     */
    protected $_DB;
    protected $_DB_MASTER;
    protected $_DB_NAME;
    protected $_NOTICE_DB;
    protected $_NOTICE_DB_NAME;
    protected $_wardNo;
    protected $_licenceId;
    protected $_shortUlbName;
    protected $_MODEL_WARD;
    protected $_COMMON_FUNCTION;
    protected $_WARD_NO;
    protected $_LICENCE_ID;
    protected $_SHORT_ULB_NAME;
    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;
    protected $_NOTICE;
    protected $_DOC_URL;

    protected $_MODEL_TradeParamFirmType;
    protected $_MODEL_TradeParamOwnershipType;
    protected $_MODEL_TradeParamCategoryType;
    protected $_MODEL_TradeParamItemType;
    protected $_MODEL_ActiveTradeLicence;
    protected $_MODEL_ActiveTradeOwner;
    protected $_MODEL_AkolaTradeParamItemType;
    protected $_MODEL_AkolaTradeParamLicenceRate;


    public function __construct()
    {
        $this->_DB_NAME = "pgsql_trade";
        $this->_NOTICE_DB = "pgsql_notice";
        $this->_DB = DB::connection($this->_DB_NAME);
        $this->_DB_MASTER = DB::connection("pgsql_master");
        $this->_NOTICE_DB = DB::connection($this->_NOTICE_DB);
        DB::enableQueryLog();
        // $this->_DB->enableQueryLog();
        // $this->_NOTICE_DB->enableQueryLog();

        $this->_MODEL_WARD = new ModelWard();
        $this->_COMMON_FUNCTION = new CommonFunction();
        $this->_NOTICE = new Notice();
        $this->_WARD_NO = NULL;
        $this->_LICENCE_ID = NULL;
        $this->_SHORT_ULB_NAME = NULL;
        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];
        $this->_DOC_URL = Config::get("module-constants.DOC_URL");

        $this->_MODEL_TradeParamFirmType = new TradeParamFirmType($this->_DB_NAME);
        $this->_MODEL_TradeParamOwnershipType = new TradeParamOwnershipType($this->_DB_NAME);
        $this->_MODEL_TradeParamCategoryType = new TradeParamCategoryType($this->_DB_NAME);
        // $this->_MODEL_TradeParamItemType = new TradeParamItemType($this->_DB_NAME );
        $this->_MODEL_ActiveTradeLicence = new ActiveTradeLicence($this->_DB_NAME);
        $this->_MODEL_ActiveTradeOwner  = new ActiveTradeOwner($this->_DB_NAME);

        $this->_MODEL_AkolaTradeParamItemType = new AkolaTradeParamItemType($this->_DB_NAME);
        $this->_MODEL_AkolaTradeParamLicenceRate = new AkolaTradeParamLicenceRate($this->_DB_NAME);
    }

    public function begin()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_NOTICE_DB->getDatabaseName();
        $db4 = $this->_DB_MASTER->getDatabaseName();
        DB::beginTransaction();
        if ($db1 != $db2)
            $this->_DB->beginTransaction();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_NOTICE_DB->beginTransaction();
        if ($db1 != $db4 && $db2 != $db4 && $db3 != $db4)
            $this->_DB_MASTER->beginTransaction();
    }

    public function rollback()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_NOTICE_DB->getDatabaseName();
        $db4 = $this->_DB_MASTER->getDatabaseName();
        DB::rollBack();
        if ($db1 != $db2)
            $this->_DB->rollBack();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_NOTICE_DB->rollBack();
        if ($db1 != $db4 && $db2 != $db4 && $db3 != $db4)
            $this->_DB_MASTER->rollBack();
    }

    public function commit()
    {
        $db1 = DB::connection()->getDatabaseName();
        $db2 = $this->_DB->getDatabaseName();
        $db3 = $this->_NOTICE_DB->getDatabaseName();
        $db4 = $this->_DB_MASTER->getDatabaseName();
        DB::commit();
        if ($db1 != $db2)
            $this->_DB->commit();
        if ($db1 != $db3 && $db2 != $db3)
            $this->_NOTICE_DB->commit();
        if ($db1 != $db4 && $db2 != $db4 && $db3 != $db4)
            $this->_DB_MASTER->commit();
    }
    # Serial No : 01
    /**
     * | Apply Of Application
     * | --------------------descriptin------------------------------------
     * | add records only ["ONLINE","JSK","UTC","TC","SUPER ADMIN","TL"]
     * |----------------------------------------------
     * | @var mDenialAmount      = 0
     * | @var refUser            = Auth()->user()
     * | @var refUserId          = refUser->id      | loging user Id
     * | @var refUlbId           = refUser->ulb_id  | loging user ulb Id
     * | @var refUlbDtl          = UlbMaster::find(refUlbId) | 
     * | @var refUlbName         = explode(' ',refUlbDtl->ulb_name)
     * | @var refNoticeDetails   = null
     * | @var refWorkflowId      = $this->_WF_MASTER_Id
     * | @var refWorkflows       = $this->_COMMON_FUNCTION->iniatorFinisher(refUserId,refUlbId,refWorkflowId)
     * | @var redis              = new Redis
     * | @var mUserData          = json_decode(redis::get('user:' . refUserId), true)
     * | @var mUserType          = $this->_COMMON_FUNCTION->userType()    | loging user Role Name
     * | @var mShortUlbName      = ""           | first charecter of each word
     * | @var mApplicationTypeId = null         | 1-> NEW LICENCE, 2-> RENEWAL, 3-> AMENDMENT, 4-> SURENDER
     * | @var mNowdate           = Carbon::now()->format('Y-m-d')   | curent date
     * | @var mTimstamp          = Carbon::now()->format('Y-m-d H:i:s') | curent timestamp
     * | @var mNoticeDate        = null
     * | @var mProprtyId         = null  | integer In Case of Holding No is Supply
     * | @var mnaturOfBusiness   = null  | Trade Item Ids
     * | @var mAppNo application number 12 charectes
     * | @var mProvNo provisinal license number  
     * |-------------------------------------------------------------------
     * | @var licence model object(active_licences)
     * | @var mOldLicenceId = request->id   |priviuse licence id only for applicationType(2,3,4) 
     * | @var nextMonth = Carbon::now()->addMonths(1)->format('Y-m-d')  | on month priviuse date
     * | @var refOldLicece = ActiveLicence::find(mOldLicenceId) | priviuse licence data only for applicationType(2,3,4) 
     * | @var mWardNo      ward no | ulb_ward_masters->ward_name 
     * | @var refOldowners  priviuse licence owner data only for applicationType(2,3,4) 
     * | @var owner model object(active_licence_owners)
     * | @var Tradetransaction model object(trade_transactions)
     * | @var TradeFineRebet model object(trade_fine_rebet_details)
     * | @var TradeFineRebet2 model object(trade_fine_rebet_details)
     * | @var tradeChq model object(trade_cheque_dtls)
     * |
     * |----------------function-------------------------------------------
     * |    this->getFirmTypeList()                               |   read FirmType
     * |    this->getOwnershipTypeList()                          |   read OwnerShipType
     * |    this->getCategoryList()                               |   read Category Type                   
     * |    this->getItemsList(true)                               |    read Trade Item List without tomacco item
     * |    this->getLicenceById(request->id)                           |    only for applicationType(2,3,4)  |  read Old Licence Dtl
     * |    ActiveLicenceOwner::owneresByLId($request->id)                       |    only for applicationType(2,3,4)  |  read Old Licence Owner Dtl
     * |    TradeParamItemType::itemsById(refOldLicece->nature_of_bussiness)|    only for applicationType(2,3,4)  |  read Old Licence Trade Items
     * 
     * |    this->transferExpire(mOldLicenceId,licenceId)               |    only for applicationType(2,3,4)  | transfer Aclive Licence To Expire Licence
     * |    this->createApplicationNo(mWardNo,licenceId)                |    Create the Application no
     * |    this->createProvisinalNo(mShortUlbName,mWardNo,licenceId)   |    Create Provisinal Certification No (provisinal certificati valide 20 day from apply date)
     * |    this->getDenialFirmDetails($refUlbId,strtoupper(trim($noticeNo))) |  Get the Notice Data 
     * |    this->updateStatusFine(refDenialId, mChargeData['notice_amount'], licence) | update The Notice Ammount And Licence Id
     * |
     * |    * | add records only ["JSK","UTC","TC","SUPER ADMIN","TL"] Authorize User For Cute The Mement In Ofline Mode
     * |    this->cltCharge(args)       | Calculation Of Charges
     * |
     * |----------------Basic Logic-------------------------------------------
     * |    case 1) mApplicationTypeId !=1  -> Transfer Old Licece into Expire and create New Records According To Old Licence Dtl
     * |         2)  mApplicationTypeId ==1 ->  create New Records According To Old Licence Dtl
     * |    
     * |
     */
    public function addRecord(Request $request)
    {
        try {
            #------------------------ Declaration-----------------------           
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id ?? $request->ulbId;
            $refUlbDtl          = UlbMaster::find($refUlbId);
            $refUlbName         = explode(' ', $refUlbDtl->ulb_name);
            $refNoticeDetails   = null;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }

            $refWorkflows       = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mShortUlbName      = "";
            $mApplicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType];
            $mNowdate           = Carbon::now()->format('Y-m-d');
            $mNoticeDate        = null;
            $mProprtyId         = null;
            $mnaturOfBusiness   = null;
            $mOldLicenceId      = null;
            $data               = array();
            foreach ($refUlbName as $mval) {
                $mShortUlbName .= $mval[0];
            }
            #------------------------End Declaration-----------------------
            if (in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {
                $data['wardList'] = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $data['wardList'] = objToArray($data['wardList']);
            } else {
                $data['wardList'] = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            }
            if ($request->getMethod() == "POST") {
                if ($request->firmDetails['holdingNo']) {
                    $property = $this->propertyDetailsfortradebyHoldingNo($request->firmDetails['holdingNo'], $refUlbId);
                    if ($property['status'])
                        $mProprtyId = $property['property']['id'];
                    else
                        throw new Exception("Property Details Not Found");
                }
                if ($mApplicationTypeId != 1) {
                    $mOldLicenceId = $request->licenseId;
                    $nextMonth = Carbon::now()->addMonths(3)->format('Y-m-d');
                    $refOldLicece = TradeLicence::find($mOldLicenceId);
                    if (!$refOldLicece) {
                        throw new Exception("Old Licence Not Found");
                    }
                    if (!$refOldLicece->is_active) {
                        $newLicense = ActiveTradeLicence::where("license_no", $refOldLicece->license_no)
                            ->orderBy("id")
                            ->first();
                        throw new Exception("Application Aready Apply Please Track  " . $newLicense->application_no);
                    }
                    if (!$refOldLicece->application_type_id == 4) {
                        throw new Exception("Surender License Can Not Apply Renewal Or Amendment ");
                    }
                    if ($refOldLicece->valid_upto > $nextMonth && !in_array($mApplicationTypeId, [3, 4])) {
                        throw new Exception("Licence Valice Upto " . $refOldLicece->valid_upto);
                    }
                    if ($refOldLicece->valid_upto < (Carbon::now()->format('Y-m-d')) && in_array($mApplicationTypeId, [3, 4])) {
                        throw new Exception("Licence Was Expired Please Renewal First");
                    }
                    if ($refOldLicece->pending_status != 5) {
                        throw new Exception("Application Aready Apply Please Track  " . $refOldLicece->application_no);
                    }
                    if (in_array($mApplicationTypeId, [3, 4]) && $refOldLicece->valid_upto < Carbon::now()->format('Y-m-d')) {
                        throw new Exception("Application was Expired.You Can't Apply " . $request->applicationType . ". Please Renew First.");
                    }
                    if ($refOldLicece->payment_status != 1) {
                        throw new Exception("Application payment not clear Please pay application charge first " . $refOldLicece->application_no);
                    }

                    $mnaturOfBusiness = $refOldLicece->nature_of_bussiness;
                    if (!$mnaturOfBusiness); {
                        $mnaturOfBusiness = array_map(function ($val) {
                            return $val['id'];
                        }, $request->firmDetails['natureOfBusiness']);
                        $mnaturOfBusiness = implode(',', $mnaturOfBusiness);
                    }
                    $wardId = $refOldLicece->ward_mstr_id;
                    $mWardNo = array_filter($data['wardList'], function ($val) use ($wardId) {
                        return $val['id'] == $wardId;
                    });
                    $mWardNo = array_values($mWardNo)[0]['ward_no'] ?? "";
                    $refOldowners = TradeOwner::where('temp_id', $mOldLicenceId)
                        ->get();
                }
                if (in_array($mApplicationTypeId, [1, 3])) {
                    $mnaturOfBusiness = array_map(function ($val) {
                        return $val['id'];
                    }, $request->firmDetails['natureOfBusiness']);
                    $mnaturOfBusiness = implode(',', $mnaturOfBusiness);
                }
                #
                $this->begin();
                $licence = new ActiveTradeLicence();
                $licence->application_type_id = $mApplicationTypeId;
                $licence->ulb_id              = $refUlbId;
                $licence->trade_id            = $mOldLicenceId;
                $licence->property_id         = $mProprtyId;
                $licence->user_id             = $refUserId;
                $licence->application_date    = $mNowdate;
                $licence->apply_from          = $mUserType;
                $licence->current_role        = $refWorkflows['initiator']['id'];
                $licence->initiator_role      = $refWorkflows['initiator']['id'];
                $licence->finisher_role       = $refWorkflows['finisher']['id'];
                $licence->workflow_id         = $refWfWorkflow->id;

                if (strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]) {
                    $licence->citizen_id      = $refUserId;
                }
                #----------------Crate Application--------------------
                if (in_array($mApplicationTypeId, ["2", "4"])) # code for Renewal,Surender respectively
                {
                    $licence->licence_for_years   = $mApplicationTypeId == 2 ? $request->licenseDetails['licenseFor'] : $refOldLicece->licence_for_years;
                    $this->renewalAndSurenderLicense($licence, $refOldLicece, $request);
                    $licence->valid_from    = Carbon::parse($refOldLicece->valid_upto)->addDay()->format("Y-m-d");
                    $licence->save();
                    $licenceId = $licence->id;
                    foreach ($refOldowners as $owners) {
                        $owner = new ActiveTradeOwner();
                        $owner->temp_id      = $licenceId;
                        $this->transerOldOwneres($owner, $owners, $request);
                        $owner->user_id  = $refUserId;
                        $owner->save();
                    }
                } elseif ($mApplicationTypeId == 3) # code for Amendment
                {
                    $this->amedmentLicense($licence, $refOldLicece, $request);
                    $licence->valid_from    = Carbon::now()->format('Y-m-d');
                    $licence->save();
                    $licenceId = $licence->id;
                    foreach ($refOldowners as $owners) {
                        $owner = new ActiveTradeOwner();
                        $owner->temp_id      = $licenceId;
                        $this->transerOldOwneres($owner, $owners, $request);
                        $owner->user_id  = $refUserId;
                        $owner->save();
                    }
                    foreach ($request->ownerDetails as $owners) {
                        $owner = new ActiveTradeOwner();
                        $owner->temp_id      = $licenceId;
                        $this->addNewOwners($owner, $owners);
                        $owner->user_id  = $refUserId;
                        $owner->save();
                    }
                } elseif ($mApplicationTypeId == 1) # code for New License
                {
                    $wardId = $request->firmDetails['wardNo'];
                    $mWardNo = (collect($data['wardList'])->where("id", $wardId)->pluck("ward_no"));

                    $mWardNo =  $mWardNo[0] ?? "";
                    $this->newLicense($licence, $request);
                    $licence->valid_from    = $licence->application_date;
                    $licence->save();
                    $licenceId = $licence->id;
                    foreach ($request->ownerDetails as $owners) {
                        $owner = new ActiveTradeOwner();
                        $owner->temp_id      = $licenceId;
                        $this->addNewOwners($owner, $owners);
                        $owner->user_id      = $refUserId;
                        $owner->save();
                    }
                }
                $licence->nature_of_bussiness = $mnaturOfBusiness;
                $mAppNo = $this->createApplicationNo($mWardNo, $licenceId, $mShortUlbName);
                $licence->application_no = $mAppNo;
                $licence->update();
                #----------------End Crate Application--------------------
                #---------------- transaction of payment-------------------------------
                if ($mApplicationTypeId == 1 && $request->initialBusinessDetails['applyWith'] == 1) {
                    $noticeNo = trim($request->initialBusinessDetails['noticeNo']);
                    $firm_date = $request->firmDetails['firmEstdDate'];
                    // $refNoticeDetails = $this->getDenialFirmDetails($refUlbId, strtoupper(trim($noticeNo)));
                    $refNoticeDetails = $this->_NOTICE->getDtlByNoticeNo(strtoupper(trim($noticeNo)), $refUlbId);
                    if ($refNoticeDetails) {
                        $refDenialId = $refNoticeDetails->id;
                        $licence->denial_id = $refDenialId;
                        $licence->update();
                        $mNoticeDate = date("Y-m-d", strtotime($refNoticeDetails['notice_date'])); //notice date  

                        if ($firm_date > $mNoticeDate) {
                            throw new Exception("Firm Establishment Date Can Not Be Greater Than Notice Date ");
                        }
                    }
                }
                if (in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-CUTE-PAYMENT"]) && $mApplicationTypeId != 4) {
                    // $myRequest = new \Illuminate\Http\Request();
                    // $myRequest->setMethod('POST');
                    // $myRequest->request->add(['paymentMode' => $request->licenseDetails['paymentMode']]);
                    // $myRequest->request->add(['licenceId'   => $licence->id]);
                    // $myRequest->request->add(['licenseFor' => $licence->licence_for_years]);
                    // $myRequest->request->add(['totalCharge' => $request->licenseDetails["totalCharge"]]);
                    // if ($request->licenseDetails['paymentMode'] != "CASH") 
                    // {
                    //     $myRequest->request->add(['chequeNo' => $request->licenseDetails["chequeNo"]]);
                    //     $myRequest->request->add(['chequeDate' => $request->licenseDetails["chequeDate"]]);
                    //     $myRequest->request->add(['bankName' => $request->licenseDetails["bankName"]]);
                    //     $myRequest->request->add(['branchName' => $request->licenseDetails["branchName"]]);
                    // }
                    // $temp = $this->paymentCounter($myRequest);
                    // if (!$temp->original["status"]) 
                    // {
                    //     throw new Exception($temp->original["message"]);
                    // }
                    // $res['transactionId']   = $temp->original["data"]["transactionId"];
                    // $res['paymentReceipt']   = $temp->original["data"]["paymentReceipt"];
                } elseif ($refNoticeDetails) {
                    $licence->denial_id = $refDenialId;
                    $licence->update();
                    $this->_NOTICE->noticeClose($licence->denial_id);
                    // $this->updateStatusFine($refDenialId, 0, $licenceId, 1); //update status and fineAmount                     
                }
                #---------------- End transaction of payment----------------------------
                if ($mApplicationTypeId == 4) {
                    $mProvno                         = $this->createProvisinalNo($mShortUlbName, $mWardNo, $licenceId);
                    $licence->provisional_license_no = $mProvno;
                    $licence->payment_status         = 1;
                    $licence->update();
                }
                #frize Priviuse License
                if ($mApplicationTypeId != 1 && !$this->transferExpire($mOldLicenceId)) {
                    throw new Exception("Some Error Ocures!....");
                }
                $this->commit();

                $mTradeSmsLog      = new TradeSmsLog();
                $firstOwnerDetails = collect($request->ownerDetails)->first();
                $firstOwnerName    = $firstOwnerDetails['businessOwnerName'];
                $firstOwnerMobile  = $firstOwnerDetails['mobileNo'];

                if (strlen($firstOwnerMobile) == 10) {
                    $sms      = "Dear " . $firstOwnerName . ", congratulations on submitting your License application. Your Ref No. is " . $mAppNo . ". For more details visit www.akolamc.org/call us at:18008907909 SWATI INDUSTRIES";
                    $response = send_sms($firstOwnerMobile, $sms, 1707171938021689821);

                    $smsReqs = [
                        "emp_id"      => (auth()->user() ? auth()->user()->id : null),
                        "ref_id"      => $licenceId,
                        "ref_type"    => 'TRADE',
                        "mobile_no"   => $firstOwnerMobile,
                        "purpose"     => $request->applicationType,
                        "template_id" => 1707171938021689821,
                        "message"     => $sms,
                        "response"    => $response['status'],
                        "smgid"       => $response['msg'],
                        "stampdate"   => Carbon::now(),
                    ];
                    $mTradeSmsLog->create($smsReqs);
                }

                $res['applicationNo'] = $mAppNo;
                $res['applyLicenseId'] = $licenceId;
                return responseMsg(true, $mAppNo, $res);
            }
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 01.01
    public function newLicense($refActiveLicense, $request)
    {
        $refActiveLicense->firm_type_id        = $request->initialBusinessDetails['firmType'];
        $refActiveLicense->firm_description    = $request->initialBusinessDetails['otherFirmType'] ?? null;
        $refActiveLicense->category_type_id    = $request->initialBusinessDetails['categoryTypeId'] ?? null;
        $refActiveLicense->ownership_type_id   = $request->initialBusinessDetails['ownershipType'];
        $refActiveLicense->zone_id             = $request->firmDetails['zoneId'];
        $refActiveLicense->ward_id             = $request->firmDetails['wardNo'];
        $refActiveLicense->new_ward_id         = $request->firmDetails['newWardNo'] ?? null;
        $refActiveLicense->holding_no          = $request->firmDetails['holdingNo'];
        $refActiveLicense->firm_name           = $request->firmDetails['firmName'];
        $refActiveLicense->firm_name_marathi   = $request->firmDetails['firmNameMarathi'] ?? null;
        $refActiveLicense->premises_owner_name = $request->firmDetails['premisesOwner'] ?? null;
        $refActiveLicense->brief_firm_desc     = $request->firmDetails['businessDescription'];
        $refActiveLicense->area_in_sqft        = $request->firmDetails['areaSqft'];

        $refActiveLicense->k_no                = $request->firmDetails['kNo'] ?? null;
        $refActiveLicense->bind_book_no        = $request->firmDetails['bindBookNo'] ?? null;
        $refActiveLicense->account_no          = $request->firmDetails['accountNo'] ?? null;
        $refActiveLicense->pan_no              = $request->firmDetails['panNo'] ?? null;
        $refActiveLicense->tin_no              = $request->firmDetails['tinNo'] ?? null;
        $refActiveLicense->salestax_no         = $request->firmDetails['salestaxNo'] ?? null;
        $refActiveLicense->establishment_date  = $request->firmDetails['firmEstdDate'];

        $refActiveLicense->licence_for_years   = $request->licenseDetails['licenseFor'];
        $refActiveLicense->address             = $request->firmDetails['businessAddress'];
        $refActiveLicense->landmark            = $request->firmDetails['landmark'] ?? null;
        $refActiveLicense->pin_code            = $request->firmDetails['pincode'] ?? null;
        $refActiveLicense->street_name         = $request->firmDetails['streetName'] ?? null;
        $refActiveLicense->property_type       = "Property";
        $refActiveLicense->is_tobacco      = $request->firmDetails['tocStatus'];
    }
    # Serial No : 01.02
    public function amedmentLicense($refActiveLicense, $refOldLicece, $request)
    {
        $refActiveLicense->parent_ids          = trim(($refActiveLicense->trade_id . "," . $refOldLicece->parent_ids), ',');
        $refActiveLicense->parent_ids          = trim(',', ($refOldLicece->parent_ids . "," . $refActiveLicense->trade_id));
        $refActiveLicense->firm_type_id        = $request->initialBusinessDetails['firmType'];
        $refActiveLicense->firm_description    = $request->initialBusinessDetails['otherFirmType'] ?? null;
        $refActiveLicense->category_type_id    = $refOldLicece->category_type_id;
        $refActiveLicense->ownership_type_id   = $request->initialBusinessDetails['ownershipType']; //$refOldLicece->ownership_type_id;
        $refActiveLicense->zone_id             = $refOldLicece->zone_id;
        $refActiveLicense->ward_id             = $refOldLicece->ward_id;
        $refActiveLicense->new_ward_id         = $refOldLicece->new_ward_id;
        $refActiveLicense->holding_no          = $request->firmDetails['holdingNo'];
        $refActiveLicense->nature_of_bussiness = $refOldLicece->nature_of_bussiness;
        $refActiveLicense->firm_name           = $refOldLicece->firm_name;
        $refActiveLicense->firm_name_marathi   = $refOldLicece->firm_name_marathi ? $refOldLicece->firm_name_marathi : ($request->firmDetails['firmNameMarathi'] ?? null);
        $refActiveLicense->premises_owner_name = $refOldLicece->premises_owner_name;
        $refActiveLicense->brief_firm_desc     = $request->firmDetails['businessDescription']; //$refOldLicece->brife_desp_firm;
        $refActiveLicense->area_in_sqft        = $request->firmDetails['areaSqft']; //$refOldLicece->area_in_sqft;

        $refActiveLicense->k_no                = $refOldLicece->k_no;
        $refActiveLicense->bind_book_no        = $refOldLicece->bind_book_no;
        $refActiveLicense->account_no          = $refOldLicece->account_no;
        $refActiveLicense->pan_no              = $refOldLicece->pan_no;
        $refActiveLicense->tin_no              = $refOldLicece->tin_no;
        $refActiveLicense->salestax_no         = $refOldLicece->salestax_no;
        $refActiveLicense->establishment_date  = $refOldLicece->establishment_date;

        $refActiveLicense->licence_for_years   = $request->licenseDetails['licenseFor'];
        $refActiveLicense->address             = $refOldLicece->address;
        $refActiveLicense->landmark            = $refOldLicece->landmark;
        $refActiveLicense->pin_code            = $refOldLicece->pin_code;
        $refActiveLicense->street_name         = $refOldLicece->street_name;
        $refActiveLicense->property_type       = $refOldLicece->property_type;
        $refActiveLicense->valid_from          = $refOldLicece->valid_upto;
        $refActiveLicense->license_no          = $refOldLicece->license_no;
        $refActiveLicense->is_tobacco          = $refOldLicece->is_tobacco;

        if ($refOldLicece->citizen_id) {
            $refActiveLicense->citizen_id          = $refOldLicece->citizen_id;
        }
    }

    # Serial No : 01.03
    public function renewalAndSurenderLicense($refActiveLicense, $refOldLicece, $request)
    {
        $refActiveLicense->parent_ids          = trim(($refActiveLicense->trade_id . "," . $refOldLicece->parent_ids), ',');

        $refActiveLicense->firm_type_id        = $refOldLicece->firm_type_id ? $refOldLicece->firm_type_id : ($request->initialBusinessDetails['firmType'] ?? null);
        $refActiveLicense->firm_description    = $refOldLicece->firm_description ? $refOldLicece->firm_description : ($request->initialBusinessDetails['otherFirmType'] ?? null);
        $refActiveLicense->category_type_id    = $refOldLicece->category_type_id ? $refOldLicece->category_type_id : ($request->initialBusinessDetails['categoryTypeId'] ?? null);
        $refActiveLicense->ownership_type_id   = $refOldLicece->ownership_type_id ? $refOldLicece->ownership_type_id : ($request->initialBusinessDetails['ownershipType'] ?? 1);
        $refActiveLicense->zone_id             = $refOldLicece->zone_id == $request->firmDetails['zoneId'] ? $refOldLicece->zone_id : $request->firmDetails['zoneId'];
        $refActiveLicense->ward_id             = $refOldLicece->ward_id ? $refOldLicece->ward_id : $request->firmDetails['wardNo'];
        $refActiveLicense->new_ward_id         = $refOldLicece->new_ward_id;
        $refActiveLicense->holding_no          = $request->firmDetails['holdingNo'];
        $refActiveLicense->nature_of_bussiness = $refOldLicece->nature_of_bussiness;
        $refActiveLicense->firm_name           = $refOldLicece->firm_name;
        $refActiveLicense->firm_name_marathi   = $refOldLicece->firm_name_marathi ? $refOldLicece->firm_name_marathi : ($request->firmDetails['firmNameMarathi'] ?? null);
        $refActiveLicense->premises_owner_name = $refOldLicece->premises_owner_name ? $refOldLicece->premises_owner_name : $request->firmDetails['premisesOwner'] ?? null;
        $refActiveLicense->brief_firm_desc     = $refOldLicece->brief_firm_desc ? $refOldLicece->brief_firm_desc : $request->firmDetails['businessDescription'];
        $refActiveLicense->area_in_sqft        = $request->firmDetails['areaSqft'] ? $request->firmDetails['areaSqft'] : $refOldLicece->area_in_sqft;

        $refActiveLicense->k_no                = $refOldLicece->k_no;
        $refActiveLicense->bind_book_no        = $refOldLicece->bind_book_no;
        $refActiveLicense->account_no          = $refOldLicece->account_no;
        $refActiveLicense->pan_no              = $refOldLicece->pan_no;
        $refActiveLicense->tin_no              = $refOldLicece->tin_no;
        $refActiveLicense->salestax_no         = $refOldLicece->salestax_no;
        $refActiveLicense->establishment_date  = $refOldLicece->establishment_date ? $refOldLicece->establishment_date : $request->firmDetails['firmEstdDate'];
        $refActiveLicense->address             = $refOldLicece->address;
        $refActiveLicense->landmark            = $refOldLicece->landmark ? $refOldLicece->landmark : $request->firmDetails['landmark'] ?? null;
        $refActiveLicense->pin_code            = $refOldLicece->pin_code ? $refOldLicece->pin_code : $request->firmDetails['pincode'] ?? null;
        $refActiveLicense->street_name         = $refOldLicece->street_name;
        $refActiveLicense->property_type       = $refOldLicece->property_type;
        $refActiveLicense->valid_from          = $refOldLicece->valid_upto;
        $refActiveLicense->license_no          = $refOldLicece->license_no;
        $refActiveLicense->is_tobacco          = $refOldLicece->is_tobacco;
        if ($refOldLicece->citizen_id) {
            $refActiveLicense->citizen_id          = $refOldLicece->citizen_id;
        }
    }

    # Serial No : 01.04
    public function addNewOwners($refOwner, $owners)
    {
        $refOwner->owner_name      = $owners['businessOwnerName'];
        $refOwner->guardian_name   = $owners['guardianName'] ?? null;
        $refOwner->owner_name_marathi      = $owners['ownerNameMarathi'] ?? null;
        $refOwner->guardian_name_marathi   = $owners['guardianNameMarathi'] ?? null;
        $refOwner->address         = $owners['address'] ?? null;
        $refOwner->mobile_no          = $owners['mobileNo'];
        $refOwner->city            = $owners['city'] ?? null;
        $refOwner->district        = $owners['district'] ?? null;
        $refOwner->state           = $owners['state'] ?? null;
        $refOwner->email_id         = $owners['email'] ?? null;
    }

    # Serial No : 01.05
    public function transerOldOwneres($refOwner, $owners, $request)
    {
        $newData = collect($request->ownerDetails)->where("ownerId", $owners->id)->first();
        if ($newData) {
            return ($this->addNewOwners($refOwner, $newData));
        }
        $refOwner->owner_name      = $owners->owner_name;
        $refOwner->guardian_name   = $owners->guardian_name;
        $refOwner->owner_name_marathi      = $owners->owner_name_marathi;
        $refOwner->guardian_name_marathi   =  $owners->guardian_name_marathi;

        $refOwner->address         = $owners->address;
        $refOwner->mobile_no          = $owners->mobile_no;
        $refOwner->city            = $owners->city;
        $refOwner->district        = $owners->district;
        $refOwner->state           = $owners->state;
        $refOwner->email_id         = $owners->email_id;
    }

    # Serial No : 01.06
    public function transferExpire(int $licenceId)
    {
        try {
            $licence = TradeLicence::find($licenceId);
            if ($licence->id) {
                $licence->is_active = FALSE;
                $licence->update();
                return $licence->id;
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    /**
     * | Trade Licence Charge Payment (offline Mode)
     * | --------------------descriptin------------------------------------
     * | make Payment only ["JSK","UTC","TC","SUPER ADMIN","TL"]
     * |-------------------------------------------------------------------
     * | @var refUser        = Auth()->user()
     * | @var refUserId      = refUser->id          | loging user Id
     * | @var refUlbId       = refUser->ulb_id      | loging user Ulb Id
     * | @var refWorkflowId  = $this->_WF_MASTER_Id
     * | @var refWorkflows   = $this->_COMMON_FUNCTION->iniatorFinisher(refUserId,refUlbId,refWorkflowId)
     * | @var refNoticeDetails= null
     * | @var refDenialId    = null
     * | @var refUlbDtl      = UlbMaster::find(refUlbId)
     * | @var refUlbName     = explode(' ',refUlbDtl->ulb_name)
     * | @var refLecenceData  = model object(active_licences)
     * | @var licenceId   = request->licenceId
     * | @var refLevelData = TradeLevelPending::getLevelData(licenceId)
     * |   
     * | @var mUserData      = $this->_COMMON_FUNCTION->getUserRoll(refUserId, refUlbId,refWorkflowId)
     * | @var mUserType      = $this->_COMMON_FUNCTION->userType()
     * | @var mNowDate       = Carbon::now()->format('Y-m-d')   | curent date
     * | @var mTimstamp      = Carbon::now()->format('Y-m-d H:i:s')     | curent timestamp
     * | @var mDenialAmount  = 0
     * | @var mPaymentStatus = 1
     * | @var mNoticeDate = null
     * | @var mShortUlbName = ""        | first charecter of each word
     * | @var mWardNo        = ""
     * |
     * |-------------------functions-------------------------------------
     * |
     * |  mUserData      = $this->_COMMON_FUNCTION->getUserRoll(refUserId, refUlbId,refWorkflowId)
     * |  mUserType      = $this->_COMMON_FUNCTION->userType(refWorkflowId)
     * |  refLevelData   = TradeLevelPending::getLevelData(licenceId)
     * |  refNoticeDetails = this->readNotisDtl(refLecenceData->id)
     * |  chargeData    = this->cltCharge(args)
     * |  this->updateStatusFine(refDenialId, chargeData['notice_amount'], licenceId,1)
     * |
     * |------------------------------------------------------------------
     * | *********************validation**********************************
     * | case 1) refLecenceData==null                                           |(data Not Available)
     * |      2) refLecenceData->application_type_id==4                         |(surender license Not Pay any amount)
     * |      3) refLecenceData->payment_status in [1,2]                        |(1->CHASE, 2->CHEQUE Payment mode, 0->not payment)
     * |      4) refLecenceData->tobacco_status==1 && request->licenseFor >1    |(tobacco licence issue only one years)
     * |      5) chargeData['response']==false || chargeData['total_charge']!=request->totalCharge      | (Charge not Calculate Or Total Charge Missmatch)
     * |
     * |----------------------basic logic---------------------------------
     * | @var totalCharge = chargeData['total_charge'] 
     * | @var mDenialAmount = chargeData['notice_amount']
     * | @var transactionType = $this->_TRADE_CONSTAINT['APPLICATION-TYPE-ID][refLecenceData->application_type_id]    | NEW LICENSE, RENEWAL, AMENDMENT
     * | @var Tradetransaction model(Tradetransaction)
     * | Tradetransaction->transaction_type = transactionType
     * | Tradetransaction->transaction_date = mNowDate      |   curent date
     * | Tradetransaction->payment_mode     = request->paymentMode
     * | Tradetransaction->paid_amount      = totalCharge        |  total paid amount
     * | Tradetransaction->penalty          = chargeData['penalty'] + mDenialAmount + chargeData['arear_amount'] (total penalty)
     * | Tradetransaction->status =         |   when request->paymentMode != CASH then 2 else 1 (default)
     * |
     * |   **************************transaction on Fin-Rebet model(TradeFineRebetDetail)*******************
     * | ++++++ first row
     * | TradeFineRebet->transaction_id = transaction_id (Tradetransaction->id)
     * | TradeFineRebet->head_name      = 'Delay Apply License'
     * | TradeFineRebet->amount         = chargeData['penalty']  (leat penalty)
     * |
     * | ++++++ second row (not neccesary)
     * | mDenialAmount = mDenialAmount + $chargeData['arear_amount']       | update priviuse value (Other Penalty)
     * | ---TradeFineRebet2  model(TradeFineRebetDetail)
     * | TradeFineRebet2->transaction_id = transaction_id (Tradetransaction->id)
     * | TradeFineRebet2->head_name      = 'Denial Apply'
     * | TradeFineRebet2->amount         = mDenialAmount
     * 
     * |    *************************transaction on Cheque Details model(TradeChequeDtl) *****************************************
     * | ++++++ only on Case Of request->paymentMode != 'CASH'
     * | tradeChq->transaction_id = transaction_id (Tradetransaction->id)
     * | tradeChq->cheque_no      = request->chequeNo
     * | tradeChq->cheque_date    = request->chequeDate
     * | tradeChq->bank_name      = request->bankName
     * | tradeChq->branch_name    = request->branchName
     * | tradeChq->emp_details_id = refUserId
     * | tradeChq->created_on     = mTimstamp
     * | 
     * | mPaymentStatus           = 2
     * |
     * |    *********************** transaction on level model(TradeLevelPending)******************************
     * | ++++++ only on Case Of (mPaymentStatus==1 && refLecenceData->document_upload_status =1 && refLecenceData->pending_status=0 && !refLevelData)
     * | level_insert->licence_id            = licenceId
     * | level_insert->sender_user_type_id   = refWorkflows['initiator']['id']
     * | level_insert->receiver_user_type_id = refWorkflows['initiator']['forward_id']
     * | level_insert->sender_user_id        = refUserId
     * |
     * |    ***************finally**************************
     * | provNo = $this->createProvisinalNo($mShortUlbName,$mWardNo,$licenceId)
     * | refLecenceData->provisional_license_no = provNo
     * | refLecenceData->payment_status         = mPaymentStatus
     * |
     * |-----------------------------------------------------------------------------
     * |
     */
    public function paymentCounter(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $refWorkflows   = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $refNoticeDetails = null;
            $refDenialId    = null;
            $refUlbDtl      = UlbMaster::find($refUlbId);
            $refUlbName     = explode(' ', $refUlbDtl->ulb_name);

            $mUserData      = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mNowDate       = Carbon::now()->format('Y-m-d');
            $mTimstamp      = Carbon::now()->format('Y-m-d H:i:s');
            $mDenialAmount  = 0;
            $mPaymentStatus = 1;
            $mNoticeDate    = null;
            $mShortUlbName  =  $refUlbDtl->short_name ?? null;
            $mWardNo        = "";
            if (!$mShortUlbName) {
                foreach ($refUlbName as $val) {
                    $mShortUlbName .= $val[0];
                }
            }

            #-----------valication-------------------                            
            // if (!in_array($mUserType, $this->_TRADE_CONSTAINT["CANE-CUTE-PAYMENT"])) {
            //     throw new Exception("You Are Not Authorized For Payment Cut");
            // }
            // $refLecenceData = ActiveTradeLicence::find($request->licenceId);
            $refLecenceData = TradeLicence::find($request->licenceId);
            $licenceId = $request->licenceId;

            // $refLevelData = $this->getWorkflowTrack($licenceId); //TradeLevelPending::getLevelData($licenceId);
            if (!$refLecenceData) {
                $refLecenceData = ActiveTradeLicence::find($request->licenceId);
                if ($refLecenceData->payment_status != 5) {
                    throw new Exception("Application Not Approved Pleas Wait For Aproval!!!");
                }
                throw new Exception("Licence Data Not Found !!!!!");
            } elseif ($refLecenceData->application_type_id == 4) {
                throw new Exception("Surender Application Not Pay Anny Amount");
            } elseif (in_array($refLecenceData->payment_status, [1, 2])) {
                throw new Exception("Payment Already Done Of This Application");
            }
            if (!$request->licenseFor) {
                $request->merge(["licenseFor" => $refLecenceData->licence_for_years]);
            }
            if (!$refLecenceData->holding_no && $request->licenseFor > 1) {
                throw new Exception("Without Holding Map You Can Not Take Licence More Than One Year");
            }
            if ($refLecenceData->tobacco_status == 1 && $request->licenseFor > 1) {
                throw new Exception("Tobaco Application Not Take Licence More Than One Year");
            }
            if ($refLecenceData->licence_for_years != $request->licenseFor) {
                throw new Exception("After Application Approved you can not change the priode");
            }
            if ($refLecenceData->denial_id && $refNoticeDetails = $this->_NOTICE->getNoticDtlById($refLecenceData->denial_id)) {
                $refDenialId = $refNoticeDetails->id;
                $mNoticeDate = date("Y-m-d", strtotime($refNoticeDetails['notice_date'])); //notice date 
            }

            $ward_no = UlbWardMaster::select("ward_name")
                ->where("id", $refLecenceData->ward_id)
                ->first();
            $mWardNo = $ward_no['ward_name'] ?? "";

            #-----------End valication-------------------

            #-------------Calculation-----------------------------                
            $args['areaSqft']            = (float)$refLecenceData->area_in_sqft;
            $args['application_type_id'] = $refLecenceData->application_type_id;
            $args['firmEstdDate'] = !empty(trim($refLecenceData->valid_from)) ? $refLecenceData->valid_from : $refLecenceData->apply_date;
            $args['curdate'] = Carbon::parse($refLecenceData->application_date)->format("Y-m-d");
            if ($refLecenceData->application_type_id == 1) {
                $args['firmEstdDate'] = $refLecenceData->establishment_date;
            }
            $args['tobacco_status']      = $refLecenceData->is_tobacco;
            $args['licenseFor']          = $request->licenseFor;
            $args['nature_of_business']  = $refLecenceData->nature_of_bussiness;
            $args['noticeDate']          = $mNoticeDate;
            $chargeData = $this->AkolaCltCharge($args);
            // $chargeData = $this->cltCharge($args);
            if ($chargeData['response'] == false || $chargeData['total_charge'] != $request->totalCharge) {
                throw new Exception("Payble Amount Missmatch!!!");
            }

            $transactionType = $this->_TRADE_CONSTAINT['APPLICATION-TYPE-BY-ID'][$refLecenceData->application_type_id];
            $rate_id = $chargeData["rate_id"];
            $totalCharge = $chargeData['total_charge'];
            $mDenialAmount = $chargeData['notice_amount'];
            #-------------End Calculation-----------------------------
            #-------- Transection section-------------------
            $this->begin();
            $Tradetransaction = new TradeTransaction;
            $Tradetransaction->temp_id          = $licenceId;
            $Tradetransaction->ward_id          = $refLecenceData->ward_id;
            $Tradetransaction->tran_type        = $transactionType;
            $Tradetransaction->tran_date        = $mNowDate;
            $Tradetransaction->payment_mode     = $request->paymentMode;
            $Tradetransaction->rate_id          = $rate_id;
            $Tradetransaction->paid_amount      = $totalCharge;
            $Tradetransaction->penalty          = $chargeData['penalty'] + $mDenialAmount + $chargeData['arear_amount'];
            if ($request->paymentMode != 'CASH') {
                $Tradetransaction->status = 2;
            }
            $Tradetransaction->emp_dtl_id       = $refUserId;
            $Tradetransaction->created_at       = $mTimstamp;
            $Tradetransaction->ip_address       = '';
            $Tradetransaction->ulb_id           = $refUlbId;
            $Tradetransaction->save();
            $transaction_id                     = $Tradetransaction->id;
            $Tradetransaction->tran_no   = $this->createTransactionNo($transaction_id, $mShortUlbName); //"TRANML" . date('d') . $transaction_id . date('Y') . date('m') . date('s');
            $Tradetransaction->update();

            $TradeFineRebet = new TradeFineRebete;
            $TradeFineRebet->tran_id  = $transaction_id;
            $TradeFineRebet->type      = 'Delay Apply License';
            $TradeFineRebet->amount         = $chargeData['penalty'];
            $TradeFineRebet->created_at    = $mTimstamp;
            $TradeFineRebet->save();

            $mDenialAmount = $mDenialAmount + $chargeData['arear_amount'];
            if ($mDenialAmount > 0) {
                $TradeFineRebet2 = new TradeFineRebete;
                $TradeFineRebet2->tran_id = $transaction_id;
                $TradeFineRebet2->type      = 'Denial Apply';
                $TradeFineRebet2->amount         = $mDenialAmount;
                $TradeFineRebet2->created_at     = $mTimstamp;
                $TradeFineRebet2->save();
            }

            if ($request->paymentMode != 'CASH') {
                $tradeChq = new TradeChequeDtl;
                $tradeChq->tran_id = $transaction_id;
                $tradeChq->temp_id = $licenceId;
                $tradeChq->cheque_no      = $request->chequeNo;
                $tradeChq->cheque_date    = $request->chequeDate;
                $tradeChq->bank_name      = $request->bankName;
                $tradeChq->branch_name    = $request->branchName;
                $tradeChq->emp_dtl_id     = $refUserId;
                $tradeChq->created_at     = $mTimstamp;
                $tradeChq->save();
                $mPaymentStatus = 2;
            }
            // if ($mPaymentStatus == 1 && $refLecenceData->document_upload_status == 1 && $refLecenceData->pending_status == 0 && !$refLevelData) {
            //     $refLecenceData->current_role = $refWorkflows['initiator']['forward_id'];
            //     $refLecenceData->pending_status  = 2;
            //     $args["sender_role_id"] = $refWorkflows['initiator']['id'];
            //     $args["receiver_role_id"] = $refWorkflows['initiator']['forward_id'];
            //     $args["citizen_id"] = $refUserId;;
            //     $args["ref_table_dot_id"] = "active_trade_licences";
            //     $args["ref_table_id_value"] = $licenceId;
            //     $args["workflow_id"] = $refLecenceData->workflow_id??"";//$refWorkflowId;
            //     $args["module_id"] = $this->_MODULE_ID;

            //     $tem =  $this->insertWorkflowTrack($args);
            // }

            if (!$refLecenceData->provisional_license_no) {
                $provNo = $this->createProvisinalNo($mShortUlbName, $mWardNo, $licenceId);
                $refLecenceData->provisional_license_no = $provNo;
            }
            $refLecenceData->payment_status         = $mPaymentStatus;
            if ($refLecenceData->application_type_id != 3) {
                $refLecenceData->licence_for_years = $request->licenseFor;
            }
            $refLecenceData->save();

            if ($refNoticeDetails) {
                $refLecenceData->denial_id = $refDenialId;
                $refLecenceData->update();
                $this->_NOTICE->noticeClose($refLecenceData->denial_id);
            }
            $this->postTempTransection($Tradetransaction, $refLecenceData, $mWardNo);
            $this->commit();
            #----------End transaction------------------------
            #----------Response------------------------------
            $res['transactionId'] = $transaction_id; #config('app.url') . 
            $res['paymentReceipt'] = "/api/trade/application/payment-receipt/" . $licenceId . "/" . $transaction_id;
            return responseMsg(true, "", $res);
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function postTempTransection(TradeTransaction $refTransection, TradeLicence $refApplication, $mWardNo = null)
    {
        $module_id = $this->_MODULE_ID;
        $mTempTransaction = new TempTransaction();
        $tranReqs = [
            'transaction_id' => $refTransection->id,
            'application_id' => $refTransection->temp_id,
            'module_id' => $module_id,
            'workflow_id' => $refApplication->workflow_id,
            'transaction_no' => $refTransection->tran_no,
            'application_no' => $refApplication->application_no,
            'amount' => $refTransection->paid_amount,
            'payment_mode' => $refTransection->payment_mode,
            'tran_date' => $refTransection->tran_date,
            'user_id' => $refTransection->emp_dtl_id,
            'ulb_id' => $refTransection->ulb_id,
            'cheque_dd_no' => null,
            'bank_name' => null,
            "ward_no" => $mWardNo,
        ];
        if ($refTransection->payment_mode != 'CASH') {
            $mChequeDtl = TradeChequeDtl::select("*")
                ->where("tran_id", $refTransection->id)
                ->orderBy("id", "DESC")
                ->first();

            $tranReqs['cheque_dd_no'] = $mChequeDtl->cheque_no ?? null;
            $tranReqs['bank_name'] = $mChequeDtl->bank_name ?? null;
        }
        $mTempTransaction->tempTransaction($tranReqs);
        $sms = trade(["ammount" => $refTransection->paid_amount, "application_no" => $refApplication->application_no, "ref_no" => $refTransection->tran_no], "Payment done");

        if ($sms["status"]) {
            $owners = $this->getAllOwnereDtlByLId($refApplication->id);
            foreach ($owners as $val) {
                // $respons = send_sms($val["mobile_no"], $sms["sms"], $sms["temp_id"]);
            }
        }
    }
    # Serial No : 02
    public function updateLicenseBo(Request $request)
    {
        try {
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id;
            $refWorkflowId = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            if (in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {
                $data['wardList'] = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $data['wardList'] = objToArray($data['wardList']);
            } else {
                $data['wardList'] = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            }
            $mLicenceId         = $request->initialBusinessDetails['id'];
            $refOldLicece       = $this->getActiveLicenseById($mLicenceId);
            if (!$refOldLicece) {
                throw new Exception("No Licence Found");
            }
            $refOldOwneres = ActiveTradeOwner::owneresByLId($mLicenceId);
            $mnaturOfBusiness = !empty(trim($refOldLicece->nature_of_bussiness)) ? $this->_MODEL_AkolaTradeParamItemType->itemsById($refOldLicece->nature_of_bussiness) : [];
            $natur = array();
            foreach ($mnaturOfBusiness as $val) {
                $natur[] = [
                    "id" => $val->id,
                    "trade_item" => "(" . $val->trade_code . ") " . $val->trade_item
                ];
            }
            $refOldLicece->nature_of_bussiness = $natur;

            $data["licenceDtl"]         =  $refOldLicece;
            $data["ownerDtl"]           = $refOldOwneres;
            $data['userType']           = $mUserType;
            $data["zone"]               = (new ZoneMaster())->getZone($refUlbId);
            $data["firmTypeList"]       = TradeParamFirmType::List();
            $data["ownershipTypeList"]  = TradeParamOwnershipType::List();
            $data["categoryTypeList"]   = TradeParamCategoryType::List();
            $data["natureOfBusiness"]   = AkolaTradeParamItemType::List(true);
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    public function updateBasicDtl(Request $request)
    {
        try {
            $user       = Auth()->user();
            $refUserId  = $user->id;
            $refUlbId   = $user->ulb_id ?? 0;
            $refWorkflowId = $this->_WF_MASTER_Id;

            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mProprtyId = null;
            $mNoticeDate    = null;
            $mLicenceId         = $request->initialBusinessDetails['id'];
            $refOldLicece       = ActiveTradeLicence::find($mLicenceId);
            if (!$refOldLicece) {
                throw new Exception("No Licence Found");
            }

            $role = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            $rollId     =  ($role->role_id ?? -1);
            if (($rollId == -1 || !$role->can_edit ?? false) &&  $this->_COMMON_FUNCTION->checkUsersWithtocken('users')) {
                throw new Exception("You Are Not Authorized");
            }

            $mnaturOfBusiness = array_map(function ($val) {
                return $val['id'];
            }, $request->firmDetails['natureOfBusiness']);
            $mnaturOfBusiness = implode(',', $mnaturOfBusiness);
            if ($request->firmDetails['holdingNo']) {
                $property = $this->propertyDetailsfortradebyHoldingNo($request->firmDetails['holdingNo'], $refUlbId);
                if ($property['status'])
                    $mProprtyId = $property['property']['id'];
                else
                    throw new Exception("Property Details Not Found");
            }
            $this->begin();

            // if ($refOldLicece->payment_status == 0) 
            {
                $refOldLicece->area_in_sqft        = $request->firmDetails['areaSqft'];
                $refOldLicece->establishment_date  = $request->firmDetails['firmEstdDate'];
                $refOldLicece->nature_of_bussiness = $mnaturOfBusiness;
                $refOldLicece->is_tobacco      = $request->firmDetails['tocStatus'];
            }
            $refOldLicece->firm_type_id        = $request->initialBusinessDetails['firmType'];
            $refOldLicece->firm_description    = $request->initialBusinessDetails['otherFirmType'] ?? null;
            $refOldLicece->category_type_id    = $request->initialBusinessDetails['categoryTypeId'] ?? null;
            $refOldLicece->ownership_type_id   = $request->initialBusinessDetails['ownershipType'];
            $refOldLicece->zone_id             = $request->firmDetails['zoneId'];
            $refOldLicece->ward_id             = $request->firmDetails['wardNo'];
            $refOldLicece->new_ward_id         = $request->firmDetails['newWardNo'] ?? null;

            $refOldLicece->property_id         = $mProprtyId;
            $refOldLicece->holding_no          = $request->firmDetails['holdingNo'];
            $refOldLicece->firm_name           = $request->firmDetails['firmName'];
            $refOldLicece->premises_owner_name = $request->firmDetails['premisesOwner'] ?? null;
            $refOldLicece->brief_firm_desc     = $request->firmDetails['businessDescription'];

            $refOldLicece->k_no                = $request->firmDetails['kNo'] ?? null;
            $refOldLicece->bind_book_no        = $request->firmDetails['bindBookNo'] ?? null;
            $refOldLicece->account_no          = $request->firmDetails['accountNo'] ?? null;
            $refOldLicece->pan_no              = $request->firmDetails['panNo'] ?? null;
            $refOldLicece->tin_no              = $request->firmDetails['tinNo'] ?? null;
            $refOldLicece->salestax_no         = $request->firmDetails['salestaxNo'] ?? null;
            $refOldLicece->address             = $request->firmDetails['businessAddress'];
            $refOldLicece->landmark            = $request->firmDetails['landmark'] ?? null;
            $refOldLicece->pin_code            = $request->firmDetails['pincode'] ?? null;
            $refOldLicece->street_name         = $request->firmDetails['streetName'] ?? null;
            $refOldLicece->update();
            foreach ($request->ownerDetails as $owner) {
                if (isset($owner['id']) && trim($owner['id'])) {
                    $refOldOwner = ActiveTradeOwner::find($owner['id']);
                    if (!$refOldOwner || $refOldOwner->temp_id != $mLicenceId) {
                        throw new Exception("Invalid Owner Id Supply!!!");
                    }
                    $refOldOwner->owner_name       = $owner['businessOwnerName'];
                    $refOldOwner->guardian_name    = $owner['guardianName'] ?? null;
                    $refOldOwner->address          = $owner['address'] ?? null;
                    $refOldOwner->mobile_no           = $owner['mobileNo'];
                    $refOldOwner->city             = $owner['city'] ?? null;
                    $refOldOwner->district         = $owner['district'] ?? null;
                    $refOldOwner->state            = $owner['state'] ?? null;
                    $refOldOwner->email_id          = $owner['email'] ?? null;
                    $refOldOwner->update();
                } elseif (!$refOldLicece->is_doc_verified) {
                    $newOwner = new ActiveTradeOwner();
                    $newOwner->temp_id      = $mLicenceId;
                    $newOwner->owner_name      = $owner['businessOwnerName'];
                    $newOwner->guardian_name   = $owner['guardianName'] ?? null;
                    $newOwner->address         = $owner['address'] ?? null;
                    $newOwner->mobile_no       = $owner['mobileNo'];
                    $newOwner->city            = $owner['city'] ?? null;
                    $newOwner->district        = $owner['district'] ?? null;
                    $newOwner->state           = $owner['state'] ?? null;
                    $newOwner->email_id        = $owner['email'] ?? null;
                    $newOwner->user_id         = $refUserId;
                    $newOwner->save();
                } else {
                    throw new Exception("You Can Not Update Owner Document is VeriFy On " . $refOldLicece->doc_verify_date . " !!!");
                }
            }

            if (in_array($refOldLicece->payment_status, [1, 2]) && $refOldLicece->application_type_id != 4) {
                $tranDtl =    TradeTransaction::select("*")->where("temp_id", $refOldLicece->id)->whereIn("status", [1, 2])->get();
                $args["curdate"]     =  $tranDtl->max("tran_date") ?? Carbon::now()->format("Y-m-d");
                $totalPaidCharge = $tranDtl->sum("paid_amount") ?? 0;
                if ($refOldLicece->denial_id && $refNoticeDetails = $this->_NOTICE->getNoticDtlById($refOldLicece->denial_id)) {
                    $refDenialId = $refNoticeDetails->id;
                    $mNoticeDate = date("Y-m-d", strtotime($refNoticeDetails['notice_date'])); //notice date 
                }
                $args['areaSqft']            = (float)$refOldLicece->area_in_sqft;
                $args['application_type_id'] = $refOldLicece->application_type_id;
                $args['firmEstdDate'] = !empty(trim($refOldLicece->valid_from)) ? $refOldLicece->valid_from : $refOldLicece->apply_date;
                if ($refOldLicece->application_type_id == 1) {
                    $args['firmEstdDate'] = $refOldLicece->establishment_date;
                }
                $args['tobacco_status']      = $refOldLicece->is_tobacco;
                $args['licenseFor']          = $request->licenseFor;
                $args['nature_of_business']  = $refOldLicece->nature_of_bussiness;
                $args['noticeDate']          = $mNoticeDate;
                $chargeData = $this->AkolaCltCharge($args);
                if ($chargeData['response'] == false || $chargeData['total_charge'] > $totalPaidCharge) {
                    throw new Exception("Paid Amount And New Payble Amount Missmatch!!!");
                }
            }

            $this->commit();
            return responseMsg(true, "Application Update SuccessFully!", "");
        } catch (Exception $e) {
            $this->rollback();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 06
    public function getUploadDocuments(Request $request)
    {
        try {
            $licenceId = $request->id;
            if (!$licenceId) {
                throw new Exception("Licence Id Required");
            }
            $refLicence = $this->getAllLicenceById($licenceId);
            if (!$refLicence) {
                throw new Exception("Data Not Found");
            }
            $tbl = $refLicence->tbl == "active_trade_licences" ? "active_" : ($refLicence->tbl == "rejected_trade_licences" ? "rejected_" : "");
            $mUploadDocument = $this->getLicenceDocuments($licenceId, $tbl)->map(function ($val) {
                if (isset($val["document_path"])) {
                    $path = $this->readDocumentPath($val["document_path"]);
                    $val["document_path"] = !empty(trim($val["document_path"])) ? $path : null;
                }
                return $val;
            });
            $data["uploadDocument"] = $mUploadDocument;
            return responseMsg(true, "", $data);
        } catch (Exception $e) {

            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    # Serial No : 07
    public function documentVirify(Request $request)
    {
        $user = Auth()->user();
        $user_id = $user->id;
        $ulb_id = $user->ulb_id;
        $workflow_id = $this->_WF_MASTER_Id;
        $workflows = $this->_COMMON_FUNCTION->iniatorFinisher($user_id, $ulb_id, $workflow_id);
        $rolles = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id, $workflow_id);
        $roll_id =  $rolles->role_id ?? null;
        $mUserType = $this->_COMMON_FUNCTION->userType($workflow_id);

        $licenceId = $request->licenceId;
        $rules = [];
        $message = [];
        try {
            if (!$roll_id || !$rolles->can_verify_document) {
                throw new Exception("You are Not Authorized For Document Verify");
            }
            if (!$licenceId) {
                throw new Exception("Data Not Found");
            }
            $licence = $this->getActiveLicenseById($licenceId);
            if (!$licence) {
                throw new Exception("Data Not Found2");
            }
            $item_name = "";
            $cods = "";
            if ($licence->nature_of_bussiness) {
                $items = AkolaTradeParamItemType::itemsById($licence->nature_of_bussiness);
                foreach ($items as $val) {
                    $item_name .= $val->trade_item . ",";
                    $cods .= $val->trade_code . ",";
                }
                $item_name = trim($item_name, ',');
                $cods = trim($cods, ',');
            }
            $licence->items = $item_name;
            $licence->items_code = $cods;
            $owneres = $this->getAllOwnereDtlByLId($licenceId);
            $mUploadDocument = $this->getLicenceDocuments($licenceId)->map(function ($val) {
                if (isset($val["document_path"])) {
                    $path = $this->readDocumentPath($val["document_path"]);
                    $val["document_path"] = !empty(trim($val["document_path"])) ? $path : null;
                }
                return $val;
            });
            foreach ($owneres as $key => $val) {
                $owneres[$key]["Identity Proof"] = $this->check_doc_exist_owner($licenceId, $val->id);
                if (isset($refOwneres[$key]["Identity Proof"]["document_path"])) {
                    $path = $this->readDocumentPath($owneres[$key]["Identity Proof"]["document_path"]);
                    // $refOwneres[$key]["Identity Proof"]["document_path"] = !empty(trim($refOwneres[$key]["Identity Proof"]["document_path"]))?storage_path('app/public/' . $refOwneres[$key]["Identity Proof"]["document_path"]):null;
                    $owneres[$key]["Identity Proof"]["document_path"] = !empty(trim($owneres[$key]["Identity Proof"]["document_path"])) ? $path : null;
                }
                $owneres[$key]["image"] = $this->check_doc_exist_owner($licenceId, $val->id, 0);
                if (isset($owneres[$key]["image"]["document_path"])) {
                    $path = $this->readDocumentPath($owneres[$key]["image"]["document_path"]);
                    $owneres[$key]["image"]["document_path"] = !empty(trim($owneres[$key]["image"]["document_path"])) ? $path : null;
                }
            }
            $data["uploadDocs"] = $mUploadDocument;
            $data["licence"] = $licence;
            $data["owneres"] = $owneres;
            if ($request->getMethod() == "GET") {
                return responseMsg(true, "", remove_null($data));
            } elseif ($request->getMethod() == "POST") {
                $rules = [];
                $message = [];
                $nowdate = Carbon::now()->format('Y-m-d');
                $timstamp = Carbon::now()->format('Y-m-d H:i:s');
                $regex = '/^[a-zA-Z1-9][a-zA-Z1-9\.\s]+$/';
                $rules = [
                    'btn' => 'required|in:verify,reject',
                    'id' => 'required',
                ];
                $status = 1;
                if ($request->btn == "reject") {
                    $status = 2;
                    $rules["comment"] = "required|regex:$regex|min:10";
                }
                $validator = Validator::make($request->all(), $rules, $message);
                if ($validator->fails()) {
                    return responseMsg(false, $validator->errors(), $request->all());
                }
                $level_data = $this->getWorkflowTrack($request->licenceId); //TradeLevelPending::getLevelData($licenceId);
                if (!$level_data || $level_data->receiver_role_id != $roll_id) {
                    throw new Exception("You Are Not Authorized For This Action");
                }

                $this->begin();
                $tradeDoc = ActiveTradeDocument::find($request->id);
                $tradeDoc->verify_status = $status;
                $tradeDoc->remarks = ($status == 2 ? $request->comment : null);
                $tradeDoc->verified_by_emp_id = $user_id;
                $tradeDoc->lvl_pending_id = $level_data->id;
                $tradeDoc->update();
                $this->commit();
                $sms = $tradeDoc->doc_for . " " . strtoupper($request->btn);
                return responseMsg(true, $sms, "");
            }
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    # Serial No : 08 
    /**
     * | Get License All Dtl
     * |-------------------------------------------------------------------------
     * | @var mUserType      = $this->_COMMON_FUNCTION->userType() | login user Role Name
     * | @var refApplication = this->getLicenceById(id)  | read application dtl
     * | @var items          = TradeParamItemType::itemsById(refApplication->nature_of_bussiness) | read trade licence Items
     * | @var refOwnerDtl    = ActiveLicenceOwner::owneresByLId(id)  | read owner dtl
     * | @var refTransactionDtl  = TradeTransaction::listByLicId(id)    | read Transaction Dtl
     * | @var refTimeLine    = this->getTimelin(id)      | read Level remarks
     * | @var refUploadDocuments = this->getLicenceDocuments(id)    | read upload Documents
     */
    public function readLicenceDtl($request)
    {

        try {
            $mWorkflowTracks = new WorkflowTrack();
            $mCustomDetails = new CustomDetail();
            $forwardBackward = new WorkflowMap;
            $id = $request->applicationId;
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $mRefTable = $this->_REF_TABLE;

            $init_finish = $this->_COMMON_FUNCTION->iniatorFinisher($refUserId, $refUlbId, $refWorkflowId);
            $finisher = $init_finish['finisher'];
            $role = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            $finisher['short_user_name'] = $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][strtoupper($init_finish['finisher']['role_name'])];
            $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $refApplication = $this->getAllLicenceById($id);
            if (!$refApplication) {
                throw new Exception("Data Not Found");
            }
            $refApplication->application_date = $refApplication->application_date ? Carbon::parse($refApplication->application_date)->format("d-m-Y") : "";
            $refApplication->license_date = $refApplication->license_date ? Carbon::parse($refApplication->license_date)->format("d-m-Y") : "";
            $refApplication->valid_from = $refApplication->valid_from ? Carbon::parse($refApplication->valid_from)->format("d-m-Y") : "";
            $refApplication->valid_upto = $refApplication->valid_upto ? Carbon::parse($refApplication->valid_upto)->format("d-m-Y") : "";
            $refApplication->establishment_date = $refApplication->establishment_date ? Carbon::parse($refApplication->establishment_date)->format("d-m-Y") : "";

            $mStatus = $this->applicationStatus($id);
            $mItemName      = "";
            $mCods          = "";
            if (trim($refApplication->nature_of_bussiness)) {
                $items = AkolaTradeParamItemType::itemsById($refApplication->nature_of_bussiness);
                foreach ($items as $val) {
                    $mItemName  .= $val->trade_item . ",";
                    $mCods      .= $val->trade_code . ",";
                }
                $mItemName = trim($mItemName, ',');
                $mCods = trim($mCods, ',');
            }
            $refApplication->items      = $mItemName;
            $refApplication->items_code = $mCods;
            $refOwnerDtl                = $this->getAllOwnereDtlByLId($id);
            $refTransactionDtl          = TradeTransaction::listByLicId($id)->map(function ($val) {
                $val->tran_date = $val->tran_date ? Carbon::parse($val->tran_date)->format("d-m-Y") : "";
                return $val;
            });
            $refTimeLine                = $this->getTimelin($id);

            $mworkflowRoles = $this->_COMMON_FUNCTION->getWorkFlowAllRoles($refUserId, $refUlbId, $refWorkflowId, true);
            $mileSton = $this->_COMMON_FUNCTION->sortsWorkflowRols($mworkflowRoles);


            $licenseDetail =  $refApplication;
            $ownerDetails  = $refOwnerDtl;
            $transactionDtl = $refTransactionDtl;
            $data['pendingStatus']  = $mStatus;
            $data['remarks']        = $refTimeLine;
            $data["userType"]       = $mUserType;
            $data["roles"]          = $mileSton;


            $newData = array();
            $fullDetailsData = array();
            $basicDetails = $this->generateBasicDetails($licenseDetail);      // Trait function to get Basic Details
            $basicElement = [
                'headerTitle' => "Basic Details",
                "data" => $basicDetails
            ];


            $paymentDetail = sizeOf($transactionDtl) > 0 ? $this->generatepaymentDetails($transactionDtl) : (array) null;      // Trait function to get payment Details
            $paymentElement = [
                'headerTitle' => "Transaction Details",
                'tableHead' => ["#", "Payment For", "Tran No", "Payment Mode", "Date"],
                'tableData' => $paymentDetail,

            ];

            $ownerDetailsTable = $this->generateOwnerDetails($ownerDetails);
            $ownerElement = [
                'headerTitle' => 'Owner Details',
                // 'tableHead' => ["#", "Owner Name", "Gender", "DOB", "Guardian Name", "Relation", "Mobile No", "Aadhar", "PAN", "Email", "Address"],
                'tableHead' => ["#", "Owner Name",  "Guardian Name",  "Mobile No",  "Email",],
                'tableData' => $ownerDetailsTable
            ];

            $cardDetails = $this->generateCardDetails($licenseDetail, $ownerDetails);
            $cardElement = [
                'headerTitle' => "About Trade",
                'data' => $cardDetails
            ];
            $fullDetailsData["propId"]         = $licenseDetail->property_id;
            $fullDetailsData["workflowId"]     = $licenseDetail->workflow_id;
            $fullDetailsData['application_no'] = $licenseDetail->application_no;
            $fullDetailsData['apply_date'] = $licenseDetail->application_date;
            $fullDetailsData['fullDetailsData']['dataArray'] = new Collection([$basicElement]);
            $fullDetailsData['fullDetailsData']['tableArray'] = new Collection([$ownerElement, $paymentElement]);
            $fullDetailsData['fullDetailsData']['cardArray'] = $cardElement;

            $metaReqs['customFor'] = 'Trade';
            $metaReqs['wfRoleId'] = ($role && $role->is_initiator && $licenseDetail->is_parked) ? $role->role_id : $licenseDetail->current_role;
            $metaReqs['workflowId'] = $licenseDetail->workflow_id;
            $metaReqs['lastRoleId'] = $licenseDetail->last_role_id;
            $levelComment = $mWorkflowTracks->getTracksByRefId($mRefTable, $licenseDetail->id)->map(function ($val) {
                $val->forward_date = $val->forward_date ? Carbon::parse($val->forward_date)->format("d-m-Y") : "";
                $val->track_date = $val->track_date ? Carbon::parse($val->track_date)->format("d-m-Y") : "";
                $val->duration = (Carbon::parse($val->forward_date)->diffInDays(Carbon::parse($val->track_date))) . " Days";
                return $val;
            });
            $fullDetailsData['levelComment'] = $levelComment;

            $citizenComment = $mWorkflowTracks->getCitizenTracks($mRefTable, $licenseDetail->id, $licenseDetail->user_id);
            $fullDetailsData['citizenComment'] = $citizenComment;

            $request->request->add($metaReqs);
            $forwardBackward = $forwardBackward->getRoleDetails($request);
            $fullDetailsData['roleDetails'] = collect($forwardBackward)['original']['data'];

            $fullDetailsData['timelineData'] = collect($request);

            $custom = $mCustomDetails->getCustomDetails($request);
            $fullDetailsData['departmentalPost'] = collect($custom)['original']['data'];

            return responseMsgs(true, 'Data Fetched', remove_null($fullDetailsData), "010104", "1.0", "303ms", "POST", $request->deviceId);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), '');
        }
    }

    # Serial No : 09 
    /**
     * | Get Notice Data
     */
    public function readDenialdtlbyNoticno(Request $request)
    {
        $data = (array)null;
        $refUser = Auth()->user();
        $refUlbId = $refUser->ulb_id ?? $request->ulbId;
        $mNoticeNo = null;
        $mNowDate = Carbon::now()->format('Y-m-d'); // todays date
        try {
            $rules = [
                "noticeNo" => "required|string",
                "ulbId" => $refUlbId ? "nullable" : "required",
            ];
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), "");
            }
            $mNoticeNo = $request->noticeNo;
            $refDenialDetails = $this->_NOTICE->getDtlByNoticeNo(trim($mNoticeNo), $refUlbId);
            if ($refDenialDetails) {
                $notice_date = Carbon::parse($refDenialDetails->notice_date)->format('Y-m-d'); //notice date
                $denialAmount = $this->getDenialAmountTrade($notice_date, $mNowDate);
                $data['denialDetails'] = $refDenialDetails;
                $data['denialAmount'] = $denialAmount;
                return responseMsg(true, "", $data);
            } else {
                $response = "no Data";
                return responseMsg(false, $response, "");
            }
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 10 
    /**
     * | @var data = this->cltCharge(data) | get the calculated Charge
     */
    public function getPaybleAmount(Request $request)
    {
        try {
            $mNoticeDate = null;
            $data['curdate']        = Carbon::now()->format('Y-m-d');
            $data['application_type_id'] = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType];
            if (!$data['application_type_id']) {
                throw new Exception("Invalide Application Type");
            }
            $mNatureOfBussiness = array_map(function ($val) {
                return $val['id'];
            }, $request->natureOfBusiness);

            $mNatureOfBussiness = implode(',', $mNatureOfBussiness);
            if (
                $request->licenceId && ($refLecenceData = TradeLicence::find($request->licenceId))
                && ($refNoticeDetails = $this->_NOTICE->getNoticDtlById($refLecenceData->denial_id))
            ) {
                $refDenialId = $refNoticeDetails->id;
                $data['curdate']        = Carbon::parse($refLecenceData->application_date)->format('Y-m-d');
                $mNoticeDate = date("Y-m-d", strtotime($refNoticeDetails['notice_date'])); //notice date 
            }

            $data["areaSqft"]       = $request->areaSqft;
            $data["firmEstdDate"]   = $request->firmEstdDate;
            $data["tobacco_status"] = $request->tocStatus;
            $data['noticeDate']     = $request->noticeDate ? $request->noticeDate : $mNoticeDate;
            $data["licenseFor"]     = $request->licenseFor;
            $data["apply_licence_id"]  = $request->licenceId ?? null;
            $data["nature_of_business"] = $mNatureOfBussiness;
            // $data = $this->cltCharge($data);
            $data = $this->AkolaCltCharge($data);
            // dd($data1,$data);
            if ($data['response'])
                return responseMsg(true, "", $data);
            else
                throw new Exception("some Errors on Calculation");
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 11 
    public function isvalidateSaf(Request $request)
    {
        $ferUser = Auth()->user();
        $ferUlbId = $ferUser->ulb_id ?? $request->ulbId;
        if ($request->getMethod() == "POST") {
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $rules = [
                "safNo" => "required|string",
            ];
            if (strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]) {
                $rules["ulbId"] = "required|digits_between:1,92";
            }
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $inputs = $request->all();
            $saf_no = $inputs['safNo'] ?? null;
            $safdet = $this->getSafDtlBySafno($saf_no, $ferUlbId);
            if ($safdet['status']) {
                $response = ['response' => true, $safdet];
            } else {
                $response = ['response' => false];
            }
        } else {
            $response = ['response' => false];
        }
        return json_encode($response);
    }

    # Serial No : 12 
    public function isvalidateHolding(Request $request)
    {
        $refUser = Auth()->user();
        $refUserId = $refUser->id;
        $refUlbId = $refUser->ulb_id ?? $request->ulbId;
        if ($request->getMethod() == "POST") {
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $rules = [
                "holdingNo" => "required|string",
            ];
            if (strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""] && !$refUlbId) {
                $rules["ulbId"] = "required|digits_between:1,92";
            }
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $inputs = $request->all();

            $propdet = $this->propertyDetailsfortradebyHoldingNo($inputs['holdingNo'], $refUlbId);

            if ($propdet['status']) {

                $request->merge(["propId" => $propdet['property']['id']]);
                $getHoldingDues = new GetHoldingDuesV2;
                $demand = $getHoldingDues->getDues($request);

                $response = ['status' => true, "data" => ["property" => $propdet['property'], "owner" => $propdet['owner']], "message" => ""];
                
                if (($demand['previousInterest']) > 0 || ($demand['arrear']) > 0) {
                    $response = ['status' => false, "data" => '', "message" => "Please Clear The Previous Arrear Amount Of ₹" . $demand['arrearPayableAmt'] . " Before Applying The Application."];
                }
            } else {
                $response = ['status' => false, "data" => '', "message" => 'No Property Found'];
            }
        } else {
            $response = ['status' => false, "data" => '', "message" => 'Onlly Post Allowed'];
        }
        return responseMsg($response['status'], $response["message"], remove_null($response["data"]));
    }

    # Serial No : 13 
    /**
     * | Validate The Licence No Befor Apply(reniwal/surrend/amendment)
        query cost(***)
     * |----------------------------------------------------------------
     * |-------------------Request--------------------------------------
     * |    1. licenceNo
     * |-----------------------------------------------------------------
     * | @var refUser    = Auth()->user()
     * | @var refUserId  = refUser->id
     * | @var refUlbId   = refUser->ulb_id
     * | @var mNextMonth = Carbon::now()->addMonths(1)->format('Y-m-d')
     * | @var mApplicationTypeId = request->applicationType
     * | @var mLicenceNo = $request->licenceNo
     * | @var data       std class object 
     * |------------------------- validation ----------------------------
     * | case 1) empty(data)                                                    -> data not Existing on given license No
     * |      2) (data->valid_upto > mNextMonth && mApplicationTypeId!=4 )      -> Applicant Can Apply  reniwal/amendment Only whene Existing License Validation Remains 1 Months
     * |      3) (data->pending_status!=5)                                      -> Current Application Not Approved And It Is On Owrkflow
     * |      4) (mApplicationTypeId==4 && data->valid_upto < Carbon::now()->format('Y-m-d'))  -> Applicant Can Apply surrend When Current Application Is Valide 
     * |           
     */
    public function searchLicenceByNo(Request $request) # reniwal/surrend/amendment
    {
        try {
            $refUser    = Auth()->user();
            $refUlbId   = $refUser->ulb_id ?? $request->ulbId;
            $mNextMonth = Carbon::now()->addMonths(3)->format('Y-m-d');
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $request->request->add(['ulbId' => $refUlbId]);
            if (in_array(strtoupper($mUserType), [$this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]])) {
                $rules["ulbId"]     = "required|digits_between:1,92";
            }

            $rules["licenceNo"]     = "required";
            $message["licenceNo.required"] = "Licence No Required";
            $rules["applicationType"] = "required:int";
            $message["applicationType.required"] = "Application Type Id Is Required";

            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), "");
            }

            $mApplicationTypeId = $request->applicationType;    //if application type is for new, renewal, amendment or surrender btw 1,2,3,4
            $mLicenceNo = $request->licenceNo;
            if (!in_array($mApplicationTypeId, [1, 2, 3, 4])) {
                throw new Exception("Invalid Application Type Supplied");
            } elseif ($mApplicationTypeId == 1) {
                throw new Exception("You Can Not Apply New Licence");
            }
            DB::enableQueryLog();

            $data = TradeLicence::select(
                "trade_licences.*",
                "owner.*",
                DB::raw("ulb_ward_masters.ward_name as ward_no,'trade_licences' AS tbl")
            )
                ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "=", "trade_licences.ward_id")
                ->leftjoin(
                    DB::raw("(SELECT temp_id,
                                    string_agg(owner_name,',') as owner_name,
                                    string_agg(guardian_name,',') as guardian_name,
                                    string_agg(mobile_no,',') as mobile
                                    FROM trade_owners
                                    WHERE is_active =true
                                    GROUP BY temp_id
                                    ) owner
                                    "),
                    function ($join) {
                        $join->on("owner.temp_id", "=",  "trade_licences.id");
                    }
                )
                ->where('trade_licences.is_active', TRUE)
                ->where('trade_licences.license_no', $mLicenceNo)
                ->where("trade_licences.ulb_id", $refUlbId)
                ->first();

            if (!$data) {
                $data = ActiveTradeLicence::select(
                    "active_trade_licences.*",
                    "owner.*",
                    DB::raw("ulb_ward_masters.ward_name as ward_no,'active_trade_licences' AS tbl")
                )
                    ->leftjoin("ulb_ward_masters", "ulb_ward_masters.id", "=", "active_trade_licences.ward_id")
                    ->leftjoin(
                        DB::raw("(SELECT temp_id,
                                string_agg(owner_name,',') as owner_name,
                                string_agg(guardian_name,',') as guardian_name,
                                string_agg(mobile_no,',') as mobile
                                FROM active_trade_owners
                                WHERE is_active =true
                                GROUP BY temp_id
                                ) owner
                                "),
                        function ($join) {
                            $join->on("owner.temp_id", "=",  "active_trade_licences.id");
                        }
                    )
                    ->where('active_trade_licences.is_active', TRUE)
                    ->where('active_trade_licences.license_no', $mLicenceNo)
                    ->where("active_trade_licences.ulb_id", $refUlbId)
                    ->first();
            }
            if (!$data) {
                $data = RejectedTradeLicence::select(
                    "rejected_trade_licences.*",
                    "owner.*",
                    DB::raw("ulb_ward_masters.ward_name as ward_no,'rejected_trade_licences' AS tbl")
                )
                    ->join("ulb_ward_masters", "ulb_ward_masters.id", "=", "rejected_trade_licences.ward_id")
                    ->leftjoin(
                        DB::raw("(SELECT temp_id,
                                string_agg(owner_name,',') as owner_name,
                                string_agg(guardian_name,',') as guardian_name,
                                string_agg(mobile_no,',') as mobile
                                FROM rejected_trade_owners
                                WHERE is_active =true
                                GROUP BY temp_id
                                ) owner
                                "),
                        function ($join) {
                            $join->on("owner.temp_id", "=",  "rejected_trade_licences.id");
                        }
                    )
                    ->where('rejected_trade_licences.is_active', TRUE)
                    ->where('rejected_trade_licences.license_no', $mLicenceNo)
                    ->where("rejected_trade_licences.ulb_id", $refUlbId)

                    ->first();
            }
            if (!$data) {
                throw new Exception("No Data Found");
            } elseif ($data->application_type_id == 4) {
                throw new Exception("You Can Not Performed Anny Action On This Application Becouse You Are Surender The License By Application No: " . $data->application_no . " Of Licence No: " . $data->license_no . ".");
            } elseif ($data->valid_upto > $mNextMonth && !in_array($mApplicationTypeId, [4, 3]) && $data->tbl == "trade_licences") {
                throw new Exception("Licence Valid Upto " . $data->valid_upto);
            } elseif ($data->tbl == "active_trade_licences") {
                throw new Exception("Application Already Applied. Please Track  " . $data->application_no);
            }
            if ($mApplicationTypeId == 4 && $data->valid_upto < Carbon::now()->format('Y-m-d') && $data->tbl == "trade_licences") {
                throw new Exception("You Can Not Apply Surrender. Application No: " . $data->application_no . " Of Licence No: " . $data->license_no . " Expired On " . $data->valid_upto . ".");
            }
            if ($mApplicationTypeId == 3 && $data->valid_upto < Carbon::now()->format('Y-m-d') && $data->tbl == "trade_licences") {
                throw new Exception("You Can Not Apply Amendment. Application No: " . $data->application_no . " Of Licence No: " . $data->license_no . " Expired On " . $data->valid_upto . ".");
            }

            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    # Serial No : 14
    /**
     * | Get 10 Application List Only
         query cost(**)
     * |----------------------------------------------------------------------------
     * |---------------------------Request------------------------------------------
     * |    1. entityValue
     * |    2. entityName
     * |
     * |----------------------------------------------------------------------------
     * | @var refUser   = Auth()->user()
     * | @var refUlbId  = refUser->ulb_id
     * | @var mInputs   = request->all() 
     */
    public function readApplication(Request $request)
    {
        try {
            $refUser    = Auth()->user();
            $refUlbId   = $refUser->ulb_id;
            $mInputs    = $request->all();
            DB::enableQueryLog();
            $licence = ActiveTradeLicence::select(
                "active_trade_licences.id",
                "active_trade_licences.application_no",
                "active_trade_licences.provisional_license_no",
                "active_trade_licences.license_no",
                "active_trade_licences.firm_name",
                "active_trade_licences.application_date",
                "active_trade_licences.apply_from",
                "active_trade_licences.valid_upto",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                'trade_param_application_types.application_type',
                "active_trade_licences.payment_status",
                DB::raw("'pending' as type"),
            )
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        temp_id
                                    FROM active_trade_owners 
                                    WHERE is_active  =TRUE
                                    GROUP BY temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "active_trade_licences.id");
                })
                ->leftjoin('trade_param_application_types', 'trade_param_application_types.id', '=', 'active_trade_licences.application_type_id');
            // ->where("active_trade_licences.status",1) 

            $aropved = TradeLicence::select(
                "trade_licences.id",
                "trade_licences.application_no",
                "trade_licences.provisional_license_no",
                "trade_licences.license_no",
                "trade_licences.firm_name",
                "trade_licences.application_date",
                "trade_licences.apply_from",
                "trade_licences.valid_upto",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                'trade_param_application_types.application_type',
                "trade_licences.payment_status",
                DB::raw("'Approved' as type"),
            )
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        temp_id
                                    FROM trade_owners 
                                    WHERE is_active  =TRUE
                                    GROUP BY temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "trade_licences.id");
                })
                ->leftjoin('trade_param_application_types', 'trade_param_application_types.id', '=', 'trade_licences.application_type_id');

            $old = tradeRenewal::select(
                "trade_renewals.id",
                "trade_renewals.application_no",
                "trade_renewals.provisional_license_no",
                "trade_renewals.license_no",
                "trade_renewals.firm_name",
                "trade_renewals.application_date",
                "trade_renewals.apply_from",
                "trade_renewals.valid_upto",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                'trade_param_application_types.application_type',
                "trade_renewals.payment_status",
                DB::raw("'Old' as type"),
            )
                ->leftjoin(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                        STRING_AGG(guardian_name,',') AS guardian_name,
                                        STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                        STRING_AGG(email_id,',') AS email_id,
                                        temp_id
                                    FROM trade_owners 
                                    WHERE is_active  =TRUE
                                    GROUP BY temp_id
                                    )owner"), function ($join) {
                    $join->on("owner.temp_id", "trade_renewals.id");
                })
                ->leftjoin('trade_param_application_types', 'trade_param_application_types.id', '=', 'trade_renewals.application_type_id');


            $licence = $licence->where("active_trade_licences.ulb_id", $refUlbId);
            $aropved = $aropved->where("trade_licences.ulb_id", $refUlbId);
            $old = $old->where("trade_renewals.ulb_id", $refUlbId);

            if (isset($mInputs['entityValue']) && trim($mInputs['entityValue']) && isset($mInputs['entityName']) && trim($mInputs['entityName'])) {
                $key = trim($mInputs['entityValue']);
                $column = strtoupper(trim($mInputs['entityName']));
                $licence = $licence->where(function ($query) use ($key, $column) {
                    if ($column == "FIRM") {
                        $query->orwhere('active_trade_licences.firm_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "APPLICATION") {
                        $query->orwhere('active_trade_licences.application_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "LICENSE") {
                        $query->orwhere('active_trade_licences.license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "PROVISIONAL") {
                        $query->orwhere('active_trade_licences.provisional_license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "OWNER") {
                        $query->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "GUARDIAN") {
                        $query->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "MOBILE") {
                        $query->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                    } else {
                        $query->orwhere('active_trade_licences.application_no', 'ILIKE', '%' . $key . '%');
                    }
                });

                $aropved = $aropved->where(function ($query) use ($key, $column) {
                    if ($column == "FIRM") {
                        $query->orwhere('trade_licences.firm_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "APPLICATION") {
                        $query->orwhere('trade_licences.application_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "LICENSE") {
                        $query->orwhere('trade_licences.license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "PROVISIONAL") {
                        $query->orwhere('trade_licences.provisional_license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "OWNER") {
                        $query->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "GUARDIAN") {
                        $query->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "MOBILE") {
                        $query->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                    } else {
                        $query->orwhere('trade_licences.application_no', 'ILIKE', '%' . $key . '%');
                    }
                });

                $old = $old->where(function ($query) use ($key, $column) {
                    if ($column == "FIRM") {
                        $query->orwhere('trade_renewals.firm_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "APPLICATION") {
                        $query->orwhere('trade_renewals.application_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "LICENSE") {
                        $query->orwhere('trade_renewals.license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "PROVISIONAL") {
                        $query->orwhere('trade_renewals.provisional_license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "OWNER") {
                        $query->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "GUARDIAN") {
                        $query->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "MOBILE") {
                        $query->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                    } else {
                        $query->orwhere('trade_renewals.application_no', 'ILIKE', '%' . $key . '%');
                    }
                });
            }
            $licence = $licence->union($aropved)->union($old)
                ->orderBy("id", "DESC")
                ->limit(10)
                ->get();
            // dd(DB::getQueryLog());
            if ($licence->isEmpty()) {
                throw new Exception("Application Not Found");
            }
            $data = [
                "licence" => $licence->map(function ($val) {
                    $val->application_date = $val->application_date ? Carbon::parse($val->application_date)->format("d-m-Y") : null;
                    $val->valid_upto = $val->valid_upto ? Carbon::parse($val->valid_upto)->format("d-m-Y") : null;
                    return $val;
                }),
            ];
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    # Serial No : 15
    public function postEscalate(Request $request)
    {
        try {
            $userId = auth()->user()->id;
            // Validation Rule
            $rules = [
                "escalateStatus" => "required|int",
                "applicationId" => "required",
            ];
            // Validation Message
            $message = [
                "escalateStatus.required" => "Escalate Status Is Required",
                // "id.required" => "Application Id Is Required",
            ];
            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $this->begin();
            $licenceId = $request->applicationId;
            $data = ActiveTradeLicence::find($licenceId);
            $data->is_escalate = $request->escalateStatus;
            $data->escalate_by = $userId;
            $data->save();
            $this->commit();
            return responseMsg(true, $request->escalateStatus == 1 ? 'Application is Escalated' : "Application is removed from Escalated", '');
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    public function WorkFlowMetaList()
    {
        return $this->_DB->table("active_trade_licences")
            ->JOIN("trade_param_application_types", "trade_param_application_types.id", "active_trade_licences.application_type_id")
            ->join(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                                    STRING_AGG(guardian_name,',') AS guardian_name,
                                                    STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                                    STRING_AGG(email_id,',') AS email_id,
                                                    temp_id
                                                FROM active_trade_owners 
                                                WHERE is_active = TRUE
                                                GROUP BY temp_id
                                                )owner"), function ($join) {
                $join->on("owner.temp_id", "active_trade_licences.id");
            })
            ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "active_trade_licences.firm_type_id")
            ->leftjoin("ulb_ward_masters", function ($join) {
                $join->on("ulb_ward_masters.id", "=", "active_trade_licences.ward_id");
            })
            ->select(
                "active_trade_licences.id",
                "active_trade_licences.application_no",
                "active_trade_licences.provisional_license_no",
                "active_trade_licences.license_no",
                "active_trade_licences.document_upload_status",
                "active_trade_licences.payment_status",
                "active_trade_licences.firm_name",
                "active_trade_licences.apply_from",
                "active_trade_licences.workflow_id",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                "active_trade_licences.application_type_id",
                "trade_param_application_types.application_type",
                "trade_param_firm_types.firm_type",
                "active_trade_licences.firm_type_id",
                DB::raw("TO_CHAR(CAST(active_trade_licences.application_date AS DATE), 'DD-MM-YYYY') as application_date"),
                ('ulb_ward_masters.id as ward_id'),
                DB::RAW("CASE WHEN old_ward_name IS NULL THEN CAST(ward_name AS TEXT) 
                        ELSE old_ward_name 
                        END AS ward_no")
            )
            ->WHERE("active_trade_licences.is_active", TRUE);
    }
    # Serial No : 16
    /**
     * | Trade module WorkFlow Inbox 
     * |
     * |----------------------------------------------------------------------------------
     * |----------------Request-----------------------------------------------------------
     * |    1.  key     -> optinal
     * |    2.  wardNo  -> optinal
     * |    3.  formDate-> optinal
     * |    4.  toDate  -> optinal 
     * |
     * |----------------------------------------------------------------------------------
     * | @var refUser        = Auth()->user()
     * | @var refUserId      = refUser->id
     * | @var refUlbId       = refUser->ulb_id
     * | @var refWorkflowId  = $this->_WF_MASTER_Id
     * | @var refWorkflowMstrId = WfWorkflow  | (model)
     * |
     * | @var mUserType       = $this->_COMMON_FUNCTION->userType()
     * | @var mWardPermission = $this->_COMMON_FUNCTION->WardPermission(refUserId)
     * | @var mRole           = $this->_COMMON_FUNCTION->getUserRoll(refUserId, refUlbId, refWorkflowMstrId->wf_master_id)
     * | @var mJoins          = ""
     * | @var mRoleId         = mRole->role_id
     * | @var mWardIds                      | permited ward ids
     */
    public function inbox(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mRole = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);

            if (!$mRole) {
                throw new Exception("You Are Not Authorized For This Action");
            }
            if ($mRole->is_initiator) {
                $mWardPermission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);

            $mRoleId = $mRole->role_id;

            $license = $this->WorkFlowMetaList()
                ->where("active_trade_licences.is_parked", FALSE)
                // ->where("active_trade_licences.payment_status", 1)
                ->where("active_trade_licences.current_role", $mRoleId)
                ->where("active_trade_licences.ulb_id", $refUlbId);
            if ($request->applicationType) {
                $mApplicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType] ?? 0;
                $license = $license->where('active_trade_licences.application_type_id', $mApplicationTypeId);
            }
            if ($request->todayReceived) {
                $metaRequest = new Request([
                    'workflowId'    => $this->_WF_MASTER_Id,
                    'ulbId'         => $refUlbId,
                    'moduleId'      => $this->_MODULE_ID
                ]);
                $track = new WorkflowTrack();
                $dateWiseData = $track->getWfDashbordData($metaRequest)->get();

                $appId = (collect($dateWiseData)->where('receiver_role_id', $mRole->role_id)->unique("ref_table_id_value"))->pluck("ref_table_id_value");
                if ($appId->isEmpty()) {
                    $appId->push(0);
                }

                if ($mRole->is_initiator == true) {
                    $license = $license->where(function ($query) use ($appId) {
                        $query->orWhere('active_trade_licences.application_date', Carbon::now()->format('Y-m-d'))
                            ->orWhereIn('active_trade_licences.id', $appId);
                    });
                } else {
                    $license = $license->whereIn('active_trade_licences.id', $appId);
                }
            }
            if (trim($request->key)) {
                $key = trim($request->key);
                $license = $license->where(function ($query) use ($key) {
                    $query->orwhere('active_trade_licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('active_trade_licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            if ($request->formDate && $request->toDate) {
                $license = $license
                    ->whereBetween('active_trade_licences.application_date', [$request->formDate, $request->toDate]);
            }
            $license = $license
                ->whereIn('active_trade_licences.ward_id', $mWardIds)
                ->orderBy("active_trade_licences.application_date", "DESC");
            if ($request->all) {
                $license = $license->get();
                return responseMsg(true, "", $license);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $license->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    # Serial No : 17
    public function outbox(Request $request)
    {
        try {
            $user = Auth()->user();
            $user_id = $user->id;
            $ulb_id = $user->ulb_id;
            $refWorkflowId = $this->_WF_MASTER_Id;
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $ulb_id)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $ward_permission = $this->_COMMON_FUNCTION->WardPermission($user_id);
            $role = $this->_COMMON_FUNCTION->getUserRoll($user_id, $ulb_id, $refWorkflowId);
            if (!$role) {
                throw new Exception("You Are Not Authorized");
            }
            if ($role->is_initiator || in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {

                $ward_permission = $this->_MODEL_WARD->getAllWard($ulb_id)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $ward_permission = objToArray($ward_permission);
            }
            $role_id = $role->role_id;

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $ward_permission);

            $license = $this->WorkFlowMetaList()
                ->where("active_trade_licences.is_parked", FALSE)
                ->where("active_trade_licences.current_role", "<>", $role_id)
                ->where("active_trade_licences.ulb_id", $ulb_id);

            if ($request->applicationType) {
                $mApplicationTypeId = $this->_TRADE_CONSTAINT["APPLICATION-TYPE"][$request->applicationType] ?? 0;
                $license = $license->where('active_trade_licences.application_type_id', $mApplicationTypeId);
            }
            if ($request->todayForward) {
                $metaRequest = new Request([
                    'workflowId'    => $this->_WF_MASTER_Id,
                    'ulbId'         => $ulb_id,
                    'moduleId'      => $this->_MODULE_ID
                ]);
                $track = new WorkflowTrack();
                $dateWiseData = $track->getWfDashbordData($metaRequest)->get();

                $appId = (collect($dateWiseData)->where('sender_role_id', $role->role_id)->unique("ref_table_id_value"))->pluck("ref_table_id_value");
                if ($appId->isEmpty()) {
                    $appId->push(0);
                }

                $license = $license->whereIn('active_trade_licences.id', $appId);
            }

            if (trim($request->key)) {
                $key = trim($request->key);
                $license = $license->where(function ($query) use ($key) {
                    $query->orwhere('active_trade_licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('active_trade_licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }

            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            if ($request->formDate && $request->toDate) {
                $license = $license
                    ->whereBetween('active_trade_licences.application_date', [$request->formDate, $request->toDate]);
            }
            $license = $license
                ->whereIn('active_trade_licences.ward_id', $mWardIds)
                ->orderBy("active_trade_licences.application_date", "DESC");
            if ($request->all) {
                $license = $license->get();
                return responseMsg(true, "", $license);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $license->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    public function specialInbox(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $ward_permission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $role = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);

            if ($role->is_initiator || in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {

                $ward_permission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $ward_permission = objToArray($ward_permission);
            }

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $ward_permission);

            $license = $this->WorkFlowMetaList()
                ->where("active_trade_licences.is_escalate", TRUE)
                ->where("active_trade_licences.ulb_id", $refUlbId);

            if (trim($request->key)) {
                $key = trim($request->key);
                $license = $license->where(function ($query) use ($key) {
                    $query->orwhere('active_trade_licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('active_trade_licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            if ($request->formDate && $request->toDate) {
                $license = $license
                    ->whereBetween('active_trade_licences.application_date', [$request->formDate, $request->toDate]);
            }
            $license = $license
                ->whereIn('active_trade_licences.ward_id', $mWardIds)
                ->orderBy("active_trade_licences.application_date", "DESC");
            if ($request->all) {
                $license = $license->get();
                return responseMsg(true, "", $license);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $license->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }
    public function btcInbox(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId  = $this->_WF_MASTER_Id;
            $refWfWorkflow     = WfWorkflow::where('wf_master_id', $refWorkflowId)
                ->where('ulb_id', $refUlbId)
                ->first();
            if (!$refWfWorkflow) {
                throw new Exception("Workflow Not Available");
            }
            $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            $mRole = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);

            if (!$mRole->is_initiator) {
                throw new Exception("You Are Not Authorized For This Action");
            }
            if ($mRole->is_initiator) {
                $mWardPermission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            }

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $mWardPermission);

            $license = $this->WorkFlowMetaList()
                ->where("active_trade_licences.is_parked", TRUE)
                ->where("active_trade_licences.ulb_id", $refUlbId);

            if (trim($request->key)) {
                $key = trim($request->key);
                $license = $license->where(function ($query) use ($key) {
                    $query->orwhere('active_trade_licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('active_trade_licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("active_trade_licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            if ($request->formDate && $request->toDate) {
                $license = $license
                    ->whereBetween('active_trade_licences.application_date', [$request->formDate, $request->toDate]);
            }
            $license = $license
                ->whereIn('active_trade_licences.ward_id', $mWardIds)
                ->orderBy("active_trade_licences.application_date", "DESC");
            if ($request->all) {
                $license = $license->get();
                return responseMsg(true, "", $license);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $license->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    public function approvedButNotPayment(Request $request)
    {
        try {
            $refUser        = Auth()->user();
            $refUserId      = $refUser->id;
            $refUlbId       = $refUser->ulb_id;
            $refWorkflowId = $this->_WF_MASTER_Id;
            $mUserType = $this->_COMMON_FUNCTION->userType($refWorkflowId);
            $ward_permission = $this->_COMMON_FUNCTION->WardPermission($refUlbId);
            $role = $this->_COMMON_FUNCTION->getUserRoll($refUserId, $refUlbId, $refWorkflowId);
            if (!$role) {
                throw new Exception("You Are Not Authorized");
            }

            if ($role->is_initiator || in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {

                $ward_permission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $ward_permission = objToArray($ward_permission);
            }
            $role_id = $role->role_id;

            $mWardIds = array_map(function ($val) {
                return $val['id'];
            }, $ward_permission);

            $select = [
                "licences.id",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.license_no",
                "licences.document_upload_status",
                "licences.payment_status",
                "licences.firm_name",
                "licences.apply_from",
                "licences.workflow_id",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                "licences.application_type_id",
                "trade_param_application_types.application_type",
                "trade_param_firm_types.firm_type",
                "licences.firm_type_id",
                DB::raw("TO_CHAR(CAST(licences.application_date AS DATE), 'DD-MM-YYYY') as application_date"),
            ];
            $ApprovedLicence  = TradeLicence::from("trade_licences as licences")
                ->JOIN("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->join(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                                STRING_AGG(guardian_name,',') AS guardian_name,
                                                STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                                STRING_AGG(email_id,',') AS email_id,
                                                temp_id
                                            FROM trade_owners 
                                            WHERE is_active = TRUE
                                            GROUP BY temp_id
                                            )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "licences.firm_type_id")
                ->select($select)
                ->where("licences.pending_status", 5)
                ->where("licences.payment_status", 0)
                ->where("licences.ulb_id", $refUlbId);
            $ActiveLicence  = ActiveTradeLicence::from("trade_licences as licences")
                ->JOIN("trade_param_application_types", "trade_param_application_types.id", "licences.application_type_id")
                ->join(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                                STRING_AGG(guardian_name,',') AS guardian_name,
                                                STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                                STRING_AGG(email_id,',') AS email_id,
                                                temp_id
                                            FROM active_trade_owners 
                                            WHERE is_active = TRUE
                                            GROUP BY temp_id
                                            )owner"), function ($join) {
                    $join->on("owner.temp_id", "licences.id");
                })
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "licences.firm_type_id")
                ->select($select)
                ->where("licences.pending_status", 5)
                ->where("licences.payment_status", 0)
                ->where("licences.ulb_id", $refUlbId);

            if (trim($request->key)) {
                $key = trim($request->key);
                $ApprovedLicence = $ApprovedLicence->where(function ($query) use ($key) {
                    $query->orwhere('licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
                $ActiveLicence = $ApprovedLicence->where(function ($query) use ($key) {
                    $query->orwhere('licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }

            if (isset($request->entityValue) && trim($request->entityValue) && isset($request->entityName) && trim($request->entityName)) {
                $key = trim($request->entityValue);
                $column = strtoupper(trim($request->entityName));
                $ApprovedLicence = $ApprovedLicence->where(function ($query) use ($key, $column) {
                    if ($column == "FIRM") {
                        $query->orwhere('licences.firm_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "APPLICATION") {
                        $query->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "LICENSE") {
                        $query->orwhere('licences.license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "PROVISIONAL") {
                        $query->orwhere('licences.provisional_license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "OWNER") {
                        $query->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "GUARDIAN") {
                        $query->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "MOBILE") {
                        $query->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                    } else {
                        $query->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%');
                    }
                });

                $ActiveLicence = $ActiveLicence->where(function ($query) use ($key, $column) {
                    if ($column == "FIRM") {
                        $query->orwhere('licences.firm_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "APPLICATION") {
                        $query->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "LICENSE") {
                        $query->orwhere('licences.license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "PROVISIONAL") {
                        $query->orwhere('licences.provisional_license_no', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "OWNER") {
                        $query->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "GUARDIAN") {
                        $query->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%');
                    } elseif ($column == "MOBILE") {
                        $query->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                    } else {
                        $query->orwhere('licences.application_no', 'ILIKE', '%' . $key . '%');
                    }
                });
            }

            if ($request->wardNo && $request->wardNo != "ALL") {
                $mWardIds = [$request->wardNo];
            }
            $ApprovedLicence = $ApprovedLicence->whereIn('licences.ward_id', $mWardIds)
                ->orderBy("licences.license_date", "ASC");
            $ActiveLicence = $ActiveLicence->whereIn('licences.ward_id', $mWardIds)
                ->orderBy("licences.license_date", "ASC");
            if ($request->formDate && $request->toDate) {
                $ApprovedLicence = $ApprovedLicence->whereBetween('licences.license_date', [$request->formDate, $request->toDate]);
                $ActiveLicence = $ApprovedLicence->whereBetween('licences.license_date', [$request->formDate, $request->toDate]);
            }

            $license = $ApprovedLicence->union($ActiveLicence);
            if ($request->all) {
                $license = $license->get();
                return responseMsg(true, "", $license);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $page = $request->page && $request->page > 0 ? $request->page : 1;

            $paginator = $license->paginate($perPage);
            $list = [
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 26
    public function approvedApplication(Request $request)
    {
        try {
            $refUser            = Auth()->user();
            $refUserId          = $refUser->id;
            $refUlbId           = $refUser->ulb_id ?? 0;
            $refWorkflowId      = $this->_WF_MASTER_Id;
            $mUserType          = $this->_COMMON_FUNCTION->userType($refWorkflowId);

            if (in_array(strtoupper($mUserType), $this->_TRADE_CONSTAINT["CANE-NO-HAVE-WARD"])) {
                $mWardPermission = $this->_MODEL_WARD->getAllWard($refUlbId)->map(function ($val) {
                    $val->ward_no = $val->ward_name;
                    return $val;
                });
                $mWardPermission = objToArray($mWardPermission);
            } else {
                $mWardPermission = $this->_COMMON_FUNCTION->WardPermission($refUserId);
            }

            $fromDate = $uptoDate = Carbon::now()->format("Y-m-d");

            $key = null;
            $wardNo = null;
            if ($request->fromDate) {
                $fromDate = $request->fromDate;
            }
            if ($request->uptoDate) {
                $uptoDate = $request->uptoDate;
            }
            if (in_array(strtoupper($mUserType), [$this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]])) {
                $fromDate = $uptoDate = null;
            }
            if ($request->key) {
                $key = trim($request->key);
            }
            if ($request->wardNo) {
                $wardNo = [$request->wardNo];
            }
            $license = TradeLicence::select(
                "trade_licences.id",
                "trade_licences.application_no",
                "trade_licences.application_type_id",
                "application_type",
                "trade_licences.provisional_license_no",
                "trade_licences.license_no",
                "trade_licences.document_upload_status",
                "trade_licences.payment_status",
                "trade_licences.firm_name",
                "trade_licences.licence_for_years",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile_no",
                "owner.email_id",
                DB::raw("
                        TO_CHAR(trade_licences.valid_from, 'DD-MM-YYYY') as valid_from ,
                        TO_CHAR(trade_licences.valid_upto, 'DD-MM-YYYY') as valid_upto,
                        TO_CHAR(trade_licences.application_date, 'DD-MM-YYYY') as application_date,
                        TO_CHAR(trade_licences.license_date, 'DD-MM-YYYY') as license_date
                    "),
                "ulb_ward_masters.id as ward_id",
                DB::RAW("CASE WHEN old_ward_name IS NULL THEN CAST(ward_name AS TEXT) 
                    ELSE old_ward_name 
                    END AS ward_no")

            )
                ->join(DB::raw("(select STRING_AGG(owner_name,',') AS owner_name,
                                STRING_AGG(guardian_name,',') AS guardian_name,
                                STRING_AGG(mobile_no::TEXT,',') AS mobile_no,
                                STRING_AGG(email_id,',') AS email_id,
                                temp_id
                                FROM trade_owners 
                                WHERE is_active =TRUE
                                GROUP BY temp_id
                                )owner"), function ($join) {
                    $join->on("owner.temp_id", "trade_licences.id");
                })
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "trade_licences.ward_id");
                })
                ->join("trade_param_application_types", "trade_param_application_types.id", "trade_licences.application_type_id")
                ->where("trade_licences.is_active", TRUE)
                ->where("trade_licences.pending_status", 5);
            if ($refUlbId) {

                $license = $license->where("trade_licences.ulb_id", $refUlbId);
            }
            if ($key) {
                $license = $license->where(function ($query) use ($key) {
                    $query->orwhere('trade_licences.holding_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere('trade_licences.application_no', 'ILIKE', '%' . $key . '%')
                        ->orwhere("trade_licences.license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere("trade_licences.provisional_license_no", 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.owner_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.guardian_name', 'ILIKE', '%' . $key . '%')
                        ->orwhere('owner.mobile_no', 'ILIKE', '%' . $key . '%');
                });
            }
            if ($wardNo) {
                $mWardIds = $wardNo;
                $license = $license
                    ->whereIn('trade_licences.ward_id', $mWardIds);
            }
            if ($fromDate && $uptoDate) {

                $license = $license
                    ->whereBetween('trade_licences.license_date', [$fromDate, $uptoDate]);
            }

            if (in_array(strtoupper($mUserType), [$this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]])) {
                $license = $license
                    ->where("citizen_id", $refUserId);
            }
            $perPage = $request->perPage ? $request->perPage :  10;
            $paginator = $license->paginate($perPage);
            $list = [
                "wardList" => $mWardPermission,
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "data" => $paginator->items(),
                "total" => $paginator->total(),
            ];
            if (in_array(strtoupper($mUserType), [$this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]])) {
                $license = $license
                    ->get();
                $list = [
                    "wardList" => $mWardPermission,
                    "licence" => $license,
                ];
            }
            return responseMsg(true, "", remove_null($list));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $request->all());
        }
    }

    # Serial No : 04
    public function readPaymentReceipt($id, $transectionId) # unauthorised  function
    {
        try {
            $select = [
                "licences.*",
                "licences.application_no",
                "licences.provisional_license_no",
                "licences.trade_id",
                "licences.licence_for_years",
                "licences.application_type_id",
                "licences.license_no",
                "licences.firm_name",
                "licences.holding_no",
                "licences.address",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, 
                        ulb_masters.id as ulb_id, ulb_masters.ulb_name,ulb_masters.ulb_type,
                        ulb_masters.logo as ulb_logo,
                        ulb_masters.short_name as ulb_short_name,
                        ulb_masters.hindi_name as ulb_hindi_name,
                        ulb_masters.current_website as ulb_current_website,
                        ulb_masters.parent_website as ulb_parent_website,
                        ulb_masters.toll_free_no as ulb_toll_free_no,
                        ulb_masters.mobile_no AS ulb_mobile_no,
                        ulb_masters.association_with as association_with,
                        zone_masters.zone_name,
                        TO_CHAR(cast(licences.application_date as date), 'DD-MM-YYYY') AS application_date,
                        TO_CHAR(cast(licences.valid_from as date), 'DD-MM-YYYY') AS valid_from,
                        TO_CHAR(cast(licences.valid_upto as date), 'DD-MM-YYYY') AS valid_upto
                        ")
            ];
            $application = $this->_DB->table("active_trade_licences AS licences")->select($select)
                ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                ->join("zone_masters", "zone_masters.id", "licences.zone_id")
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                                STRING_AGG(guardian_name,',') as guardian_name,
                                                STRING_AGG(mobile_no::text,',') as mobile,
                                                temp_id
                                            FROM active_trade_owners 
                                            WHERE temp_id = $id
                                                AND is_active  = TRUE
                                            GROUP BY temp_id
                                            ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "licences.id");
                })
                ->where('licences.id', $id)
                ->first();
            if (!$application) {
                $application = $this->_DB->table("trade_licences AS licences")->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "licences.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                    })
                    ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                                STRING_AGG(guardian_name,',') as guardian_name,
                                                STRING_AGG(mobile_no,',') as mobile,
                                                temp_id
                                            FROM trade_owners 
                                            WHERE temp_id = $id
                                                AND is_active = TRUE
                                            GROUP BY temp_id
                                            ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "licences.id");
                    })
                    ->where('licences.id', $id)
                    ->first();
            }
            if (!$application) {
                $application = $this->_DB->table("rejected_trade_licences AS licences")->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "licences.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                    })
                    ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                                STRING_AGG(guardian_name,',') as guardian_name,
                                                STRING_AGG(mobile_no,',') as mobile,
                                                temp_id
                                            FROM rejected_trade_owners 
                                            WHERE temp_id = $id
                                                AND is_active = TRUE
                                            GROUP BY temp_id
                                            ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "licences.id");
                    })
                    ->where('licences.id', $id)
                    ->first();
            }
            if (!$application) {
                $application = $this->_DB->table("trade_renewals AS licences")->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "licences.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "licences.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "licences.ward_id");
                    })
                    ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                                STRING_AGG(guardian_name,',') as guardian_name,
                                                STRING_AGG(mobile_no,',') as mobile,
                                                temp_id
                                            FROM trade_owners 
                                            WHERE temp_id = $id
                                                AND is_active = TRUE
                                            GROUP BY temp_id
                                            ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "licences.id");
                    })
                    ->where('licences.id', $id)
                    ->first();
            }
            if (!$application) {
                throw new Exception("Application Not Found");
            }
            $transaction = TradeTransaction::select(
                "tran_no",
                "tran_type",
                "payment_mode",
                "paid_amount",
                "penalty",
                "trade_cheque_dtls.cheque_no",
                "trade_cheque_dtls.bank_name",
                "trade_cheque_dtls.branch_name",
                DB::raw("TO_CHAR(cast(tran_date as date), 'DD-MM-YYYY') AS tran_date,
                             TO_CHAR(cast(cheque_date as date), 'DD-MM-YYYY') AS cheque_date
                            ")
            )
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->where("trade_transactions.id", $transectionId)
                ->where("trade_transactions.temp_id", $id)
                ->whereIn("trade_transactions.status", [1, 2])
                ->first();
            if (!$transaction) {
                throw new Exception("Transaction Not Faound");
            }
            if (!$application->licence_for_years || !$application->valid_from || !$application->valid_upto) {
                $this->temCalValidity($application);
            }
            $penalty = TradeFineRebete::select("type", "amount")
                ->where('tran_id', $transectionId)
                ->where("status", 1)
                ->orderBy("id")
                ->get();
            $pen = 0;
            $delay_fee = 0;
            $denial_fee = 0;
            foreach ($penalty as $val) {
                if (strtoupper($val->type) == strtoupper("Delay Apply License")) {
                    $delay_fee = $val->amount;
                } elseif (strtoupper($val->type) == strtoupper("Denial Apply")) {
                    $denial_fee = $val->amount;
                }
                $pen += $val->amount;
            }
            if (!$transaction) {
                $transaction = new TradeTransaction;
                $transaction->paid_amount = 0;
                $transaction->id = 0;
            }
            $transaction->rate = number_format(($transaction->paid_amount - $pen), 2);
            $transaction->delay_fee = number_format($delay_fee, 2);
            $transaction->denial_fee = number_format($denial_fee, 2);
            $transaction->paid_amount_in_words = getIndianCurrency($transaction->paid_amount);
            $application->ulb_logo = $application->ulb_logo ? ($this->_DOC_URL . "/" . $application->ulb_logo) : "";
            $data = [
                "application" => $application,
                "transaction" => $transaction,
                "penalty"    => $penalty
            ];
            #config('app.url') .
            $data['paymentReceipt'] =  "/api/trade/payment-receipt/" . $id . "/" . $transectionId;
            $data = remove_null($data);
            return responseMsg(true, "", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), '');
        }
    }

    # Serial No : 19
    public function provisionalCertificate($id) # unauthorised  function
    {
        try {

            $data = (array)null;
            $data['provisionalCertificate'] = config('app.url') . "/api/trade/provisional-certificate/" . $id;
            $select = [
                "license.*",
                "license.application_no",
                "license.provisional_license_no",
                "license.license_no",
                "license.firm_name",
                "license.holding_no",
                "license.address",
                "license.payment_status",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, 
                        ulb_masters.id as ulb_id, ulb_masters.ulb_name,ulb_masters.ulb_type,
                        ulb_masters.logo as ulb_logo,
                        ulb_masters.short_name as ulb_short_name,
                        ulb_masters.hindi_name as ulb_hindi_name,
                        ulb_masters.current_website as ulb_current_website,
                        ulb_masters.parent_website as ulb_parent_website,
                        ulb_masters.toll_free_no as ulb_toll_free_no,
                        ulb_masters.mobile_no AS ulb_mobile_no,
                        ulb_masters.association_with as association_with,
                        zone_masters.zone_name ,
                        TO_CHAR(CAST(license.application_date AS DATE), 'DD-MM-YYYY') as application_date,
                        TO_CHAR(CAST(license.establishment_date AS DATE), 'DD-MM-YYYY') as establishment_date,
                        TO_CHAR(CAST(license.license_date AS DATE), 'DD-MM-YYYY') as license_date,
                        TO_CHAR(CAST(license.valid_from AS DATE), 'DD-MM-YYYY') as valid_from,
                        TO_CHAR(CAST(license.valid_upto AS DATE), 'DD-MM-YYYY') as valid_upto
                        ")
            ];
            $application = $this->_DB->table("active_trade_licences as license")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "license.ulb_id")
                ->join("zone_masters", "zone_masters.id", "license.zone_id")
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "license.ward_id");
                })
                ->join(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                            STRING_AGG(guardian_name,',') as guardian_name,
                                            STRING_AGG(mobile_no::text,',') as mobile,
                                            temp_id
                                        FROM active_trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                        GROUP BY temp_id
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "license.id");
                })
                ->where('license.id', $id)
                ->first();
            if (!$application) {
                $application = $this->_DB->table("trade_licences as license")
                    ->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "license.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "license.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "license.ward_id");
                    })
                    ->join(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                            STRING_AGG(guardian_name,',') as guardian_name,
                                            STRING_AGG(mobile_no,',') as mobile,
                                            temp_id
                                        FROM trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                        GROUP BY temp_id
                                        ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "license.id");
                    })
                    ->where('license.id', $id)
                    ->first();
            }
            if (!$application) {
                $application = $this->_DB->table("rejected_trade_licences as license")
                    ->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "license.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "license.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "license.ward_id");
                    })
                    ->join(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                            STRING_AGG(guardian_name,',') as guardian_name,
                                            STRING_AGG(mobile_no,',') as mobile,
                                            temp_id
                                        FROM rejected_trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                        GROUP BY temp_id
                                        ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "license.id");
                    })
                    ->where('license.id', $id)
                    ->first();
            }
            if (!$application) {
                $application = $this->_DB->table("trade_renewals as license")
                    ->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "license.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "license.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "license.ward_id");
                    })
                    ->join(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                            STRING_AGG(guardian_name,',') as guardian_name,
                                            STRING_AGG(mobile_no,',') as mobile,
                                            temp_id
                                        FROM trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                        GROUP BY temp_id
                                        ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "license.id");
                    })
                    ->where('license.id', $id)
                    ->first();
            }
            if (!$application) {
                throw new Exception("Application Not Found");
            }
            if ($application->payment_status == 0) {
                throw new Exception("Please Payment Of This Application");
            }
            $vUpto = $application->application_date;
            $application->valid_upto = date('d-m-Y', strtotime(date("$vUpto", mktime(time())) . " + 22 day"));
            $transaction = TradeTransaction::select(
                "trade_transactions.id",
                "tran_no",
                "tran_type",
                "tran_date",
                "payment_mode",
                "paid_amount",
                "penalty",
                "trade_cheque_dtls.cheque_no",
                "trade_cheque_dtls.cheque_date",
                "trade_cheque_dtls.bank_name",
                "trade_cheque_dtls.branch_name",
                DB::raw("TO_CHAR(CAST(tran_date AS DATE), 'DD-MM-YYYY') as tran_date,
                            TO_CHAR(CAST(cheque_date AS DATE), 'DD-MM-YYYY') as cheque_date
                            ")
            )
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                ->where("trade_transactions.temp_id", $id)
                ->whereIn("trade_transactions.status", [1, 2])
                ->first();
            if (!$transaction) {
                throw new Exception("Transaction Not Faound");
            }
            $penalty = TradeFineRebete::select("type", "amount")
                ->where('tran_id', $transaction->id)
                ->where("status", 1)
                ->orderBy("id")
                ->get();
            $pen = 0;
            foreach ($penalty as $val) {
                $pen += $val->amount;
            }
            if (!$transaction) {
                $transaction = new TradeTransaction;
                $transaction->paid_amount = 0;
                $transaction->id = 0;
            }
            $transaction->rate = $transaction->paid_amount - $pen;
            $application->ulb_logo = $application->ulb_logo ? ($this->_DOC_URL . "/" . $application->ulb_logo) : "";
            $data["application"] = $application;
            $data["transaction"] = $transaction;
            $data["penalty"]    = $penalty;

            $data = remove_null($data);
            return  responseMsg(true, "", $data);
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $id);
        }
    }

    # Serial No : 20
    public function licenceCertificate($id) # unauthorised  function
    {
        try {
            $docUrl = Config::get('module-constants.DOC_URL');
            $data = (array)null;
            $data['licenceCertificate'] = config('app.url') . "/api/trade/license-certificate/" . $id;
            $select = [
                "license.*",
                "license.application_no",
                "license.provisional_license_no",
                "license.license_no",
                "license.firm_name",
                "license.holding_no",
                "license.address",
                "license.licence_for_years",
                "license.nature_of_bussiness",
                "license.firm_description",
                "license.brief_firm_desc",
                "license.premises_owner_name",
                "license.pending_status",
                "license.payment_status",
                "owner.owner_name",
                "owner.guardian_name",
                "owner.mobile",
                "owner.licensee_id",
                "owner_additionl.additionl_owner_name",
                "owner_additionl.additionl_guardian_name",
                "owner_additionl.additionl_mobile",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, 
                        ulb_masters.id as ulb_id, ulb_masters.ulb_name,ulb_masters.ulb_type,
                        ulb_masters.logo as ulb_logo,
                        ulb_masters.short_name as ulb_short_name,
                        ulb_masters.hindi_name as ulb_hindi_name,
                        ulb_masters.current_website as ulb_current_website,
                        ulb_masters.parent_website as ulb_parent_website,
                        ulb_masters.toll_free_no as ulb_toll_free_no,
                        ulb_masters.mobile_no AS ulb_mobile_no,
                        ulb_masters.association_with as association_with,
                        zone_masters.zone_name,
                        TO_CHAR(CAST(license.application_date AS DATE), 'DD-MM-YYYY') as application_date,
                        TO_CHAR(CAST(license.establishment_date AS DATE), 'DD-MM-YYYY') as establishment_date,
                        TO_CHAR(CAST(license.license_date AS DATE), 'DD-MM-YYYY') as license_date,
                        TO_CHAR(CAST(license.valid_from AS DATE), 'DD-MM-YYYY') as valid_from,
                        TO_CHAR(CAST(license.valid_upto AS DATE), 'DD-MM-YYYY') as valid_upto
                        ")
            ];
            $application = $this->_DB->table("trade_licences AS license")
                ->select($select)
                ->join("ulb_masters", "ulb_masters.id", "license.ulb_id")
                ->join("zone_masters", "zone_masters.id", "license.zone_id")
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "license.ward_id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                            STRING_AGG(guardian_name,',') as guardian_name,
                                            STRING_AGG(mobile_no::text,',') as mobile,
                                            temp_id, id as licensee_id
                                        FROM trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                        GROUP BY temp_id,id
                                        ORDER BY id ASC
                                        limit 1
                                        ) owner"), function ($join) {
                    $join->on("owner.temp_id", "=", "license.id");
                })
                ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as additionl_owner_name,
                                            STRING_AGG(guardian_name,',') as additionl_guardian_name,
                                            STRING_AGG(mobile_no::text,',') as additionl_mobile,
                                            temp_id
                                        FROM trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                            AND id NOT IN(
                                                SELECT id
                                                FROM trade_owners 
                                                WHERE temp_id = $id
                                                    AND is_active =TRUE
                                                ORDER BY id ASC
                                                limit 1
                                            )
                                        GROUP BY temp_id
                                        ) owner_additionl"), function ($join) {
                    $join->on("owner_additionl.temp_id", "=", "license.id");
                })
                ->where('license.id', $id)
                ->first();
            if (!$application) {
                $application = $this->_DB->table("trade_renewals AS license")
                    ->select($select)
                    ->join("ulb_masters", "ulb_masters.id", "license.ulb_id")
                    ->join("zone_masters", "zone_masters.id", "license.zone_id")
                    ->leftjoin("ulb_ward_masters", function ($join) {
                        $join->on("ulb_ward_masters.id", "=", "license.ward_id");
                    })
                    ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name,
                                            STRING_AGG(guardian_name,',') as guardian_name,
                                            STRING_AGG(mobile_no::text,',') as mobile,
                                            temp_id , id as licensee_id
                                        FROM trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                        GROUP BY temp_id,id
                                        ORDER BY id ASC
                                        limit 1
                                        ) owner"), function ($join) {
                        $join->on("owner.temp_id", "=", "license.id");
                    })
                    ->leftjoin(DB::raw("(SELECT STRING_AGG(owner_name,',') as additionl_owner_name,
                                            STRING_AGG(guardian_name,',') as additionl_guardian_name,
                                            STRING_AGG(mobile_no::text,',') as additionl_mobile,
                                            temp_id
                                        FROM trade_owners 
                                        WHERE temp_id = $id
                                            AND is_active =TRUE
                                            AND id NOT IN(
                                                SELECT id
                                                FROM trade_owners 
                                                WHERE temp_id = $id
                                                    AND is_active =TRUE
                                                ORDER BY id ASC
                                                limit 1
                                            )
                                        GROUP BY temp_id
                                        ) owner_additionl"), function ($join) {
                        $join->on("owner_additionl.temp_id", "=", "license.id");
                    })
                    ->where('license.id', $id)
                    ->first();
                if (!$application) {
                    throw new Exception("Application Not Found");
                }
            }
            if (!$application) {
                throw new Exception("Data Not Faound");
            }
            if ($application->payment_status == 2) {
                throw new Exception("Payment Not Clear From Accontend");
            }
            if ($application->payment_status != 1) {
                throw new Exception("Payment Not Done");
            }
            $oldLicense = TradeRenewal::find($application->trade_id);
            if ($application->pending_status != 5) {
                throw new Exception("Application Not Approved");
            }
            $item_name = "";
            $cods = "";
            if ($application->nature_of_bussiness) {
                $items = AkolaTradeParamItemType::itemsById($application->nature_of_bussiness);
                foreach ($items as $val) {
                    $item_name .= $val->trade_item . ",";
                    $cods .= $val->trade_code . ",";
                }
                $item_name = trim($item_name, ',');
                $cods = trim($cods, ',');
            }
            $application->firm_name_marathi = trim($application->firm_name_marathi) ? $application->firm_name_marathi : $application->firm_name;
            $application->items = $item_name;
            $application->items_code = $cods;
            if (!$application->licence_for_years || !$application->valid_from || !$application->valid_upto) {
                $this->temCalValidity($application);
            }
            $application->old_license_no = $oldLicense->license_no ?? "";
            $application->old_license_date = $oldLicense ? Carbon::parse($oldLicense->license_date)->format("d-m-Y") : "";
            $application->ulb_logo = $application->ulb_logo ? ($this->_DOC_URL . "/" . $application->ulb_logo) : "";

            $transaction = TradeTransaction::select(
                "tran_no",
                "tran_type",
                "payment_mode",
                "paid_amount",
                "penalty",
                "rate_id",
                "trade_transactions.id",
                "trade_cheque_dtls.cheque_no",
                "trade_cheque_dtls.bank_name",
                "trade_cheque_dtls.branch_name",
                DB::raw("TO_CHAR(cast(tran_date as date), 'DD-MM-YYYY') AS tran_date,
                            TO_CHAR(cast(cheque_date as date), 'DD-MM-YYYY') AS cheque_date
                            ")
            )
                ->leftjoin("trade_cheque_dtls", "trade_cheque_dtls.tran_id", "trade_transactions.id")
                // ->where("trade_transactions.id", $transectionId)
                ->where("trade_transactions.temp_id", $application->id)
                ->whereIn("trade_transactions.status", [1, 2])
                ->orderBy("trade_transactions.id", "DESC")
                ->first();

            if (!$application->licence_for_years || !$application->valid_from || !$application->valid_upto) {
                $this->temCalValidity($application);
            }
            $rate_ids = explode(",", ($transaction->rate_id ?? "0"));
            $rates = $this->_MODEL_AkolaTradeParamLicenceRate->whereIn("id", $rate_ids)->sum("rate");
            $penalty = TradeFineRebete::select("type", "amount")
                ->where('tran_id', $transaction->id ?? 0)
                ->where("status", 1)
                ->orderBy("id")
                ->get();
            $pen = 0;
            $delay_fee = 0;
            $denial_fee = 0;
            foreach ($penalty as $val) {
                if (strtoupper($val->type) == strtoupper("Delay Apply License")) {
                    $delay_fee = $val->amount;
                } elseif (strtoupper($val->type) == strtoupper("Denial Apply")) {
                    $denial_fee = $val->amount;
                }
                $pen += $val->amount;
            }
            if (!$transaction) {
                $transaction = new TradeTransaction;
                $transaction->paid_amount = 0;
                $transaction->id = 0;
            }
            // $transaction->rate = number_format(($transaction->paid_amount - $pen), 2);
            $transaction->rate = number_format(($rates), 2);
            $transaction->delay_fee = number_format($delay_fee, 2);
            $transaction->denial_fee = number_format($denial_fee, 2);
            $transaction->paid_amount_in_words = getIndianCurrency($transaction->paid_amount);
            $application->parent_ids = trim($application->parent_ids . "," . $application->id, ",");
            $parentIds =   explode(",", $application->parent_ids ? $application->parent_ids : $application->id);
            $application->licenceHistory = TradeRenewal::whereIn("id", $parentIds)->orderBy("valid_upto", "ASC")->get()->map(function ($history) {
                $Oltransaction = TradeTransaction::where("trade_transactions.temp_id", $history->id)
                    ->whereIn("trade_transactions.status", [1, 2])
                    ->orderBy("trade_transactions.id", "DESC")
                    ->first();
                $penalty = TradeFineRebete::select("type", "amount")
                    ->where('tran_id', $Oltransaction->id ?? 0)
                    ->where("status", 1)
                    ->orderBy("id")
                    ->get();
                $pen = 0;
                $delay_fee = 0;
                $denial_fee = 0;
                foreach ($penalty as $val) {
                    if (strtoupper($val->type) == strtoupper("Delay Apply License")) {
                        $delay_fee = $val->amount;
                    } elseif (strtoupper($val->type) == strtoupper("Denial Apply")) {
                        $denial_fee = $val->amount;
                    }
                    $pen += $val->amount;
                }

                $hrate_ids = explode(",", ($Oltransaction->rate_id ?? "0"));
                $hrates = $this->_MODEL_AkolaTradeParamLicenceRate->whereIn("id", $hrate_ids)->sum("rate");

                $history->tran_no = $Oltransaction ? $Oltransaction->tran_no : null;
                $history->application_type = ($history->applicationType()->first())->application_type ?? null;
                $history->tran_date = $Oltransaction ? Carbon::parse($Oltransaction->tran_date)->format("d-m-Y") : null;
                // $history->rate = $Oltransaction ? number_format(($Oltransaction->paid_amount - $pen), 2): 0;
                $history->rate = number_format(($hrates), 2);
                $history->paid_amount = $Oltransaction ? number_format(($Oltransaction->paid_amount), 2) : 0;
                $history->delay_fee = $delay_fee;
                $history->denial_fee = $denial_fee;
                $history->valid_from = $history->valid_from ? Carbon::parse($history->valid_from)->format("d-m-Y") : $history->valid_from;
                $history->valid_upto = $history->valid_upto ? Carbon::parse($history->valid_upto)->format("d-m-Y") : $history->valid_upto;
                $history->license_date = $history->license_date ? Carbon::parse($history->license_date)->format("d-m-Y") : $history->license_date;
                $history->application_date = $history->application_date ? Carbon::parse($history->application_date)->format("d-m-Y") : $history->application_date;
                return $history->only(["tran_date", "tran_no", "paid_amount", "rate", "delay_fee", "denial_fee", "id", "application_type", "application_no", "valid_from", "valid_upto", "license_date", "application_date"]);
            });

            $oldOwnersId = TradeOwner::whereIN("temp_id", $parentIds)
                ->where("is_active", true)
                ->orderby("id", "ASC")
                ->first();
            $owner_doc = WfActiveDocument::whereIn("active_id", $parentIds)
                ->where("workflow_id", $application->workflow_id)
                // ->where("owner_dtl_id",$oldOwnersId->id)
                ->where("doc_code", "Owner Image")
                ->where("status", "<>", 0)
                ->first();
            $application->owner_image = $owner_doc ? ($docUrl . "/" . $owner_doc->relative_path . "/" . $owner_doc->document) : "";
            $data = [
                "application" => $application,
                "transaction" => $transaction,
                "penalty"    => $penalty,
            ];
            $data = remove_null($data);
            return  responseMsg(true, "", $data);
        } catch (Exception $e) {
            return responseMsg(false, [$e->getMessage(), $e->getFile(), $e->getLine()], $id);
        }
    }


    # Serial No : 22
    public function addIndependentComment(Request $request)
    {
        try {
            $mRegex     = '/^[a-zA-Z1-9][a-zA-Z1-9\. \s]+$/';
            $userId = auth()->user()->id;
            $ulbId  = auth()->user()->ulb_id;
            $role_id = 0;
            $refWorkflowId = $this->_WF_MASTER_Id;
            $role = $this->_COMMON_FUNCTION->getUserRoll($userId, $ulbId, $refWorkflowId);
            if ($role && $this->_COMMON_FUNCTION->checkUsersWithtocken()) {
                $role_id = $role->role_id ?? 0;
            }

            $rules["comment"] = "required|min:10|regex:$mRegex";
            $rules["applicationId"] = "required|digits_between:1,9223372036854775807";
            $validator = Validator::make($request->all(), $rules,);

            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), "");
            }

            $refLicense = ActiveTradeLicence::find($request->applicationId);
            if (!$refLicense) {
                throw new Exception("Comments for invalide application !....");
            }

            // Save On Workflow Track
            $this->begin();
            $metaReqs['moduleId'] = $this->_MODULE_ID;
            $metaReqs['workflowId'] = $refLicense->workflow_id;
            $metaReqs['refTableDotId'] = 'active_trade_licences';
            $metaReqs['refTableIdValue'] = $refLicense->id;
            $metaReqs['senderRoleId'] = $role_id;
            $metaReqs['trackDate'] = Carbon::now()->format('Y-m-d H:i:s');
            if ($this->_COMMON_FUNCTION->checkUsersWithtocken()) {
                $metaReqs['user_id'] = $userId;
            } else {
                $metaReqs['citizenId'] = $userId;
            }
            $metaReqs['ulb_id'] = $refLicense->ulb_id;
            $request->merge($metaReqs);

            $track = new WorkflowTrack();
            $track->saveTrack($request);
            $this->commit();
            return responseMsg(true, "You Have Commented Successfully!!", ['Comment' => $request->comment]);
        } catch (Exception $e) {
            $this->rollBack();
            return responseMsg(false, $e->getMessage(), "");
        }
    }

    # Serial No : 23
    public function readIndipendentComment(Request $request)
    {
        try {
            $rules["licenceId"] = "required|digits_between:1,9223372036854775807";
            $validator = Validator::make($request->all(), $rules,);
            if ($validator->fails()) {
                return responseMsg(false, $validator->errors(), $request->all());
            }
            $comments = $this->getIndipendentComment($request->licenceId);
            return $comments;
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), "");
        }
    }
    # Serial No : 23.01
    public function getIndipendentComment($licenceId)
    {
        try {
            $data = WorkflowTrack::select(
                "workflow_tracks.*",
                "users.user_name",
                DB::raw("CASE WHEN wf_roles.id ISNULL THEN 'Citizen' ELSE wf_roles.role_name END AS role_name")
            )
                ->join("users", "users.id", "workflow_tracks.citizen_id")
                ->leftjoin("wf_roles", "wf_roles.id", "workflow_tracks.commented_by")
                ->where("workflow_tracks.ref_table_dot_id", "active_licences")
                ->where("workflow_tracks.ref_table_id_value", $licenceId)
                ->where("workflow_tracks.module_id", $this->_MODULE_ID)
                ->orderBy("workflow_tracks.track_date")
                ->get();
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }




    #---------- core function for trade Application--------

    /**
     * |----------------------------------------------------
     * |    APN     |      2A      |     0000001            |
     * |  const(3)  | ward no(2)   | unique No id(7)        |
     * |____________________________________________________|
     * 
     */
    public function createApplicationNo($wardNo, $licenceId, $mShortUlbName = null)
    {
        return ($mShortUlbName && strtoupper($mShortUlbName) != "AMC" ? strtoupper($mShortUlbName) . "-" : "APN") . str_pad($wardNo, 2, '0', STR_PAD_LEFT) . str_pad($licenceId, 7, '0', STR_PAD_LEFT);
    }

    /**
     * |----------------------------------------------------------------------------------
     * |     RMC                  |    2A         | 01152022           |      1           |
     * | @var shortUlbName(3)     |  ward no(2)   | Month Date Year(8) |  unique No id    |
     * |___________________________________________________________________________________
     */
    public function createProvisinalNo($shortUlbName, $wardNo, $licenceId)
    {
        return $shortUlbName . $wardNo . date('mdy') . $licenceId;
    }

    /**
     * |-----------------------------------------------------------------------------------
     * |  TRANML      |    14        |  1234         | 2022       | 01        |  53        |
     * |    (3)       |   date('d')  | transactionId | date('Y')  | date('m') | date('s')  |
     * |____________________________________________________________________________________
     */
    public function createTransactionNo($transactionId, $shortUlbName)
    {
        return "TR" . $shortUlbName . date('d') . $transactionId . date('Y') . date('m') . date('s');
    }
    public function cltCharge(array $args)
    {
        $response = ['response' => false];
        try {
            $data = array();
            $inputs = $args;
            $data['area_in_sqft'] = (float)$inputs['areaSqft'];
            $data['application_type_id'] = $inputs['application_type_id'];
            $data['firm_date'] = $inputs['firmEstdDate'];
            $data['firm_date'] = date('Y-m-d', strtotime($data['firm_date']));

            $data['tobacco_status'] = $inputs['tobacco_status'] == True ? 1 : 0;
            $data['timeforlicense'] = $inputs['licenseFor'];
            $data['curdate'] = $inputs['curdate'] ?? date("Y-m-d");

            $denial_amount_month = 0;
            $count = $this->getrate($data);
            $rate = $count->rate * $data['timeforlicense'];
            $notice_amount = 0;
            if (isset($inputs['noticeDate']) && $inputs['noticeDate']) {
                $notice_amount = $this->getDenialAmountTrade($inputs['noticeDate']);
            }
            $pre_app_amount = 0;
            if (isset($data['application_type_id']) && in_array($data['application_type_id'], [1, 2])) {
                $nob = array();
                $data['nature_of_business'] = null;
                if (isset($inputs['nature_of_business']))
                    $nob = explode(',', $inputs['nature_of_business']);
                if (sizeof($nob) == 1) {
                    $data['nature_of_business'] = $nob[0];
                }

                $temp = $data['firm_date'];
                $temp2 = $data['firm_date'];
                if ($data['nature_of_business'] == 198 && strtotime($temp) <= strtotime('2021-10-30')) {
                    $temp = '2021-10-30';
                    $temp2 = $temp;
                } elseif ($data['nature_of_business'] != 198 && strtotime($temp) <= strtotime('2020-01-01')) {
                    $temp = '2020-01-01';
                }
                $data['firm_date'] = $temp;
                $diff_year = date_diff(date_create($temp2), date_create($data['curdate']))->format('%R%y');
                $pre_app_amount = ($diff_year > 0 ? $diff_year : 0) * $count->rate;
            }

            $vDiff = abs(strtotime($data['curdate']) - strtotime($data['firm_date'])); // here abs in case theres a mix in the dates
            $vMonths = ceil($vDiff / (30 * 60 * 60 * 24)); // number of seconds in a month of 30 days

            if ($vMonths > 0 && strtotime($data['firm_date']) < strtotime($data['curdate'])) {
                $denial_amount_month = 100 + (($vMonths) * 20);
            }
            # In case of ammendment no denial amount
            if ($data['application_type_id'] == 3) {
                $denial_amount_month = 0;
            }
            $total_denial_amount = $denial_amount_month + $rate + $pre_app_amount + $notice_amount;

            # Check If Any cheque bounce charges
            if (isset($inputs['apply_licence_id'], $inputs['apply_licence_id'])) {
                $penalty = $this->getChequeBouncePenalty($inputs['apply_licence_id']);
                $denial_amount_month += $penalty;
                $total_denial_amount += $penalty;
            }

            if ($count) {
                $response = ['response' => true, 'rate' => $rate, 'penalty' => $denial_amount_month, 'total_charge' => $total_denial_amount, 'rate_id' => $count['id'], 'arear_amount' => $pre_app_amount, "notice_amount" => $notice_amount, "months" => $vMonths, "year" => $diff_year ?? 0];
            } else {
                $response = ['response' => false];
            }
            return $response;
        } catch (Exception $e) {
            return $response;
        }
    }

    public function AkolaCltCharge(array $args)
    {
        $response = ['response' => false];
        try {
            $data = array();
            $inputs = $args;
            $data['area_in_sqft'] = (float)$inputs['areaSqft'];
            $data['application_type_id'] = $inputs['application_type_id'];
            $data['firm_date'] = $inputs['firmEstdDate'];
            $data['firm_date'] = date('Y-m-d', strtotime($data['firm_date']));

            $data['tobacco_status'] = $inputs['tobacco_status'] == True ? 1 : 0;
            $data['timeforlicense'] = $inputs['licenseFor'];
            $data['curdate'] = $inputs['curdate'] ?? date("Y-m-d");
            $data["nature_of_business"] = $inputs['nature_of_business'] ?? 0;
            $denial_amount_month = 0;
            $count = $this->AkolaGetRate($data);
            $rate = $count->rate * $data['timeforlicense'];
            $notice_amount = 0;
            if (isset($inputs['noticeDate']) && $inputs['noticeDate']) {
                $notice_amount = 0; #$this->getDenialAmountTrade($inputs['noticeDate']);
            }
            $pre_app_amount = 0;
            if (isset($data['application_type_id']) && in_array($data['application_type_id'], [1, 2])) {
                $nob = array();
                $data['nature_of_business'] = null;
                if (isset($inputs['nature_of_business']))
                    $nob = explode(',', $inputs['nature_of_business']);
                if (sizeof($nob) == 1) {
                    $data['nature_of_business'] = $nob[0];
                }

                $temp = $data['firm_date'];
                $temp2 = $data['firm_date'];
                // if ($data['nature_of_business'] == 198 && strtotime($temp) <= strtotime('2021-10-30')) 
                // {
                //     $temp = '2021-10-30';
                //     $temp2 = $temp;
                // } 
                // elseif ($data['nature_of_business'] != 198 && strtotime($temp) <= strtotime('2020-01-01')) 
                // {
                //     $temp = '2020-01-01';
                // }
                $data['firm_date'] = $temp;
                $diff_year = date_diff(date_create($temp2), date_create($data['curdate']))->format('%R%y');
                $pre_app_amount = ($diff_year > 0 ? $diff_year : 0) * $count->rate;
            }

            $vDiff = abs(strtotime($data['curdate']) - strtotime($data['firm_date'])); // here abs in case theres a mix in the dates
            $vMonths = ceil($vDiff / (30 * 60 * 60 * 24)); // number of seconds in a month of 30 days

            if ($vMonths > 0 && strtotime($data['firm_date']) < strtotime($data['curdate'])) {
                // $denial_amount_month = 100 + (($vMonths) * 20);
            }
            # In case of ammendment no denial amount
            if ($data['application_type_id'] == 3) {
                $denial_amount_month = 0;
            }
            $total_denial_amount = $denial_amount_month + $rate + $pre_app_amount + $notice_amount;

            # Check If Any cheque bounce charges
            if (isset($inputs['apply_licence_id'], $inputs['apply_licence_id'])) {
                $penalty = $this->getChequeBouncePenalty($inputs['apply_licence_id']);
                $denial_amount_month += $penalty;
                $total_denial_amount += $penalty;
            }

            if ($count) {
                $response = ['response' => true, 'rate' => $rate, 'penalty' => $denial_amount_month, 'total_charge' => $total_denial_amount, 'rate_id' => $count->ids, 'arear_amount' => $pre_app_amount, "curdate" => $data['curdate'], "notice_amount" => $notice_amount, "months" => $vMonths, "year" => $diff_year ?? 0, "Dtl" => $count,];
            } else {
                $response = ['response' => false];
            }
            return $response;
        } catch (Exception $e) {
            return $response;
        }
    }

    public function getDenialAmountTrade($notice_date = null, $current_date = null)
    {
        $notice_date = $notice_date ? Carbon::createFromFormat("Y-m-d", $notice_date)->format("Y-m-d") : Carbon::now()->format('Y-m-d');
        $current_date = $current_date ? Carbon::createFromFormat("Y-m-d", $current_date)->format("Y-m-d") : Carbon::now()->format('Y-m-d');

        $datediff = strtotime($current_date) - strtotime($notice_date); //days difference in second
        $totalDays =   abs(ceil($datediff / (60 * 60 * 24))); // total no. of days
        $denialAmount = 100 + (($totalDays) * 10);

        return $denialAmount;
    }
    public function getAllApplicationType()
    {
        try {
            $data = TradeParamApplicationType::select("id", "application_type")
                ->where('status', '1')
                ->get();
            return $data;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getrate(array $input) #stdcl object array
    {
        try {
            DB::enableQueryLog();
            $builder = TradeParamLicenceRate::select('id', 'rate')
                ->where('application_type_id', $input['application_type_id'])
                ->where('range_from', '<=', ceil($input['area_in_sqft']))
                ->where('range_to', '>=', ceil($input['area_in_sqft']))
                ->where('effective_date', '<', $input['curdate'])
                ->where('status', 1)
                ->where('tobacco_status', $input['tobacco_status'])
                ->orderBy('effective_date', 'Desc')
                ->first();
            return $builder;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function AkolaGetRate(array $input) #stdcl object array
    {
        try {
            DB::enableQueryLog();
            // $builder = AkolaTradeParamLicenceRate::select('id', 'rate')
            $builder = AkolaTradeParamLicenceRate::select(DB::raw("string_agg(cast(id as text),',') as ids, sum(rate) as rate"))
                ->where('application_type_id', $input['application_type_id'])
                ->whereIn('item_type_id', explode(",", $input['nature_of_business']))
                ->where('effective_date', '<', $input['curdate'])
                ->where('status', 1)
                // ->orderBy('effective_date', 'Desc')
                ->first();
            // return($this->_DB->getqueryLog());
            return $builder;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getChequeBouncePenalty(int $apply_licence_id): float
    {
        try {

            $result = TradeChequeDtl::select(DB::raw("coalesce(sum(amount), 0) as penalty"))
                ->where("temp_id", $apply_licence_id)
                ->where("status", 3)
                ->first();
            return $result->penalty;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function propertyDetailsfortradebyHoldingNo(string $holdingNo, int $ulb_id): array
    {
        // DB::enableQueryLog();
        $property = PropProperty::select("*")
            ->leftjoin(
                DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name ,property_id
                                        FROM Prop_OwnerS 
                                        WHERE status = 1
                                        GROUP BY property_id
                                        ) owners
                                        "),
                function ($join) {
                    $join->on("owners.property_id", "=", "prop_properties.id");
                }
            )
            ->where("status", 1)
            ->where("new_holding_no", "<>", "")
            ->where("new_holding_no", "ILIKE", $holdingNo)
            ->where("ulb_id", $ulb_id)
            ->first();
        // dd(DB::getQueryLog());
        if (!$property) {
            $property = PropProperty::select("*")
                ->leftjoin(
                    DB::raw("(SELECT STRING_AGG(owner_name,',') as owner_name ,property_id
                                        FROM Prop_OwnerS 
                                        WHERE status = 1
                                        GROUP BY property_id
                                        ) owners
                                        "),
                    function ($join) {
                        $join->on("owners.property_id", "=", "prop_properties.id");
                    }
                )
                ->where("status", 1)
                // ->where("new_holding_no", "<>", "")
                ->where("holding_no", "ILIKE", $holdingNo)
                ->where("ulb_id", $ulb_id)
                ->orderBy("id", 'DESC')
                ->first();
        }

        if ($property) {
            $owner = PropOwner::where("property_id", $property->id)->where("status", 1)->get();
            return ["status" => true, 'property' => objToArray($property), "owner" => $owner];
        }
        return ["status" => false, 'property' => ''];
    }
    public function getSafDtlBySafno(string $safNo, int $ulb_id): array
    {
        $saf = PropActiveSaf::select("*")
            ->where('status', 1)
            ->where('saf_no', $safNo)
            ->where('ulb_id', $ulb_id)
            ->first();
        if ($saf->id) {
            $owneres = PropActiveSafsOwner::select("*")
                ->where("saf_dtl_id", $saf->id)
                ->where('status', 1)
                ->get();
            return ["status" => true, 'saf' => objToArray($saf), 'owneres' => objToArray($owneres)];
        }
        return ["status" => false, 'property' => '', 'owneres' => ''];
    }

    public function getLicenceById($id)
    {
        try {
            $application = TradeLicence::select(
                "trade_licences.*",
                "trade_param_application_types.application_type",
                "trade_param_category_types.category_type",
                "trade_param_firm_types.firm_type",
                "trade_param_ownership_types.ownership_type",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, new_ward.ward_name as new_ward_no, zone_masters.zone_name ")
            )
                ->leftjoin("trade_param_application_types", "trade_param_application_types.id", "trade_licences.application_type_id")
                ->leftjoin("zone_masters", "zone_masters.id", "trade_licences.zone_id")
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "trade_licences.ward_id");
                })
                ->leftjoin("ulb_ward_masters AS new_ward", function ($join) {
                    $join->on("new_ward.id", "=", "trade_licences.new_ward_id");
                })
                ->leftjoin("trade_param_category_types", "trade_param_category_types.id", "trade_licences.category_type_id")
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "trade_licences.firm_type_id")
                ->leftjoin("trade_param_ownership_types", "trade_param_ownership_types.id", "trade_licences.ownership_type_id")
                ->where('trade_licences.id', $id)
                ->first();
            return $application;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getActiveLicenseById($id)
    {
        try {
            $application = ActiveTradeLicence::select(
                "active_trade_licences.*",
                "trade_param_application_types.application_type",
                "trade_param_category_types.category_type",
                "trade_param_firm_types.firm_type",
                "trade_param_ownership_types.ownership_type",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, new_ward.ward_name as new_ward_no, zone_masters.zone_name ")
            )
                ->leftjoin("ulb_ward_masters", function ($join) {
                    $join->on("ulb_ward_masters.id", "=", "active_trade_licences.ward_id");
                })
                ->leftjoin("ulb_ward_masters AS new_ward", function ($join) {
                    $join->on("new_ward.id", "=", "active_trade_licences.new_ward_id");
                })
                ->join("zone_masters", "zone_masters.id", "active_trade_licences.zone_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", "active_trade_licences.application_type_id")
                ->leftjoin("trade_param_category_types", "trade_param_category_types.id", "active_trade_licences.category_type_id")
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", "active_trade_licences.firm_type_id")
                ->leftjoin("trade_param_ownership_types", "trade_param_ownership_types.id", "active_trade_licences.ownership_type_id")
                ->where('active_trade_licences.id', $id)
                ->first();
            return $application;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getAllLicenceById($id)
    {
        try {
            $test = TradeLicence::select("id")->find($id);
            $table = "trade_licences";
            $application = TradeLicence::select(
                "trade_licences.*",
                "trade_param_application_types.application_type",
                "trade_param_category_types.category_type",
                "trade_param_firm_types.firm_type",
                "trade_param_ownership_types.ownership_type",
                DB::raw("ulb_ward_masters.ward_name AS ward_no, new_ward.ward_name as new_ward_no,ulb_masters.ulb_name, zone_masters.zone_name , '$table' AS tbl")
            );
            if (!$test) {
                $test = RejectedTradeLicence::select("id")->find($id);
                $table = "rejected_trade_licences";
                $application = RejectedTradeLicence::select(
                    "rejected_trade_licences.*",
                    "trade_param_application_types.application_type",
                    "trade_param_category_types.category_type",
                    "trade_param_firm_types.firm_type",
                    "trade_param_ownership_types.ownership_type",
                    DB::raw("ulb_ward_masters.ward_name AS ward_no, new_ward.ward_name as new_ward_no,ulb_masters.ulb_name, zone_masters.zone_name , '$table' AS tbl")
                );
            }
            if (!$test) {
                $test = ActiveTradeLicence::select("id")->find($id);
                $table = "active_trade_licences";
                $application = ActiveTradeLicence::select(
                    "active_trade_licences.*",
                    "trade_param_application_types.application_type",
                    "trade_param_category_types.category_type",
                    "trade_param_firm_types.firm_type",
                    "trade_param_ownership_types.ownership_type",
                    DB::raw("ulb_ward_masters.ward_name AS ward_no, 
                    new_ward.ward_name as new_ward_no,ulb_masters.ulb_name, zone_masters.zone_name , '$table' AS tbl")
                );
            }
            if (!$test) {
                $table = "trade_renewals";
                $application = TradeRenewal::select(
                    "trade_renewals.*",
                    "trade_param_application_types.application_type",
                    "trade_param_category_types.category_type",
                    "trade_param_firm_types.firm_type",
                    "trade_param_ownership_types.ownership_type",
                    DB::raw("ulb_ward_masters.ward_name AS ward_no, 
                    new_ward.ward_name as new_ward_no,ulb_masters.ulb_name, zone_masters.zone_name , '$table' AS tbl")
                );
            }

            $application = $application
                ->leftjoin("ulb_ward_masters", function ($join) use ($table) {
                    $join->on("ulb_ward_masters.id", "=", $table . ".ward_id");
                })
                ->leftjoin("ulb_ward_masters AS new_ward", function ($join) use ($table) {
                    $join->on("new_ward.id", "=", $table . ".new_ward_id");
                })
                ->join("ulb_masters", "ulb_masters.id", $table . ".ulb_id")
                ->join("zone_masters", "zone_masters.id", $table . ".zone_id")
                ->join("trade_param_application_types", "trade_param_application_types.id", $table . ".application_type_id")
                ->leftjoin("trade_param_category_types", "trade_param_category_types.id", $table . ".category_type_id")
                ->leftjoin("trade_param_firm_types", "trade_param_firm_types.id", $table . ".firm_type_id")
                ->leftjoin("trade_param_ownership_types", "trade_param_ownership_types.id", $table . ".ownership_type_id")
                ->where($table . '.id', $id)
                ->first();
            return $application;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getAllOwnereDtlByLId($id)
    {
        try {
            $ownerDtl   = ActiveTradeOwner::select("*")
                ->where("temp_id", $id)
                ->where("is_active", 1)
                ->get();
            if (sizeOf($ownerDtl) < 1) {
                $ownerDtl   = RejectedTradeOwner::select("*")
                    ->where("temp_id", $id)
                    ->where("is_active", 1)
                    ->get();
            }
            if (sizeOf($ownerDtl) < 1) {
                $ownerDtl   = TradeOwner::select("*")
                    ->where("temp_id", $id)
                    ->where("is_active", 1)
                    ->get();
            }
            return $ownerDtl;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getTimelin($id)
    {
        try {
            //    DB::enableQueryLog();
            $time_line =  workflowTrack::select(
                "workflow_tracks.message",
                "workflow_tracks.forward_date",
                "workflow_tracks.forward_time",
                "workflow_tracks.receiver_role_id",
                "role_name",
                DB::raw("workflow_tracks.created_at as receiving_date")
            )
                ->leftjoin('wf_roles', "wf_roles.id", "workflow_tracks.receiver_role_id")
                ->where('workflow_tracks.ref_table_id_value', $id)
                ->where('workflow_tracks.ref_table_dot_id', $this->_REF_TABLE)
                ->whereNotNull('workflow_tracks.sender_role_id')
                ->where('workflow_tracks.status', true)
                ->groupBy(
                    'workflow_tracks.receiver_role_id',
                    'workflow_tracks.message',
                    'workflow_tracks.forward_date',
                    'workflow_tracks.forward_time',
                    'wf_roles.role_name',
                    'workflow_tracks.created_at'
                )
                ->orderBy('workflow_tracks.created_at', 'desc')
                ->get();
            // dd(DB::getQueryLog());
            return $time_line;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function getLicenceDocuments($id, $tbl = "active_")
    {
        try {
            $doc =  TradeDocument::select(
                $tbl . "trade_documents.*",
                DB::raw("CASE WHEN " . $tbl . "trade_owners.id" . " NOTNULL 
                                    THEN CONCAT(" . $tbl . "trade_owners.owner_name,'( '," . $tbl . "trade_documents.doc_type_code,' )')
                                    ELSE " . $tbl . "trade_documents.doc_type_code END doc_type_code")
            );
            if ($tbl = "active_") {
                $doc =  ActiveTradeDocument::select(
                    $tbl . "trade_documents.*",
                    DB::raw("CASE WHEN " . $tbl . "trade_owners.id" . " NOTNULL 
                                        THEN CONCAT(" . $tbl . "trade_owners.owner_name,'( '," . $tbl . "trade_documents.doc_type_code,' )')
                                        ELSE " . $tbl . "trade_documents.doc_type_code END doc_type_code")
                );
            }
            if ($tbl = "rejected_") {
                $doc =  RejectedTradeDocument::select(
                    $tbl . "trade_documents.*",
                    DB::raw("CASE WHEN " . $tbl . "trade_owners.id" . " NOTNULL 
                                        THEN CONCAT(" . $tbl . "trade_owners.owner_name,'( '," . $tbl . "trade_documents.doc_type_code,' )')
                                        ELSE " . $tbl . "trade_documents.doc_type_code END doc_type_code")
                );
            }
            $doc =  $doc->leftjoin($tbl . "trade_owners", function ($join) use ($tbl) {
                $join->on($tbl . "trade_owners.id", $tbl . "trade_documents.temp_owner_id");
            })
                ->where($tbl . "trade_documents.temp_id", $id)
                ->orderBy($tbl . "trade_documents.id", 'desc')
                ->get();
            return $doc;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    public function searchLicence(string $licence_no, $ulb_id)
    {
        try {
            $data = TradeLicence::select("*")
                ->join(
                    DB::raw("(SELECT licence_id,
                                    string_agg(owner_name,',') as owner_name,
                                    string_agg(guardian_name,',') as guardian_name,
                                    string_agg(mobile,',') as mobile
                                    FROM active_licence_owners
                                    WHERE status =1
                                    GROUP BY licence_id
                                    ) owner
                                    "),
                    function ($join) {
                        $join->on("owner.licence_id", "=",  "active_licences.id");
                    }
                )
                ->where('is_active', true)
                ->where("ulb_id", $ulb_id)
                ->where('license_no', $licence_no)
                ->first();
            return responseMsg(true, "", remove_null($data));
        } catch (Exception $e) {
            return responseMsg(false, $e->getMessage(), $licence_no);
        }
    }

    public function uplodeFile($file, $custumFileName)
    {
        $filePath = $file->storeAs('uploads/Trade', $custumFileName, 'public');
        return  $filePath;
    }

    public function getDocumentTypeList($refLicense)
    {
        try {
            $show = '1';
            if ($refLicense->application_type_id == 1) {
                if ($refLicense->ownership_type_id == 1) {
                    $show .= ',' . '2';
                } else {
                    $show .= ',' . '3';
                }

                if ($refLicense->firm_type_id == 2) {
                    $show .= ',' . '4';
                } elseif ($refLicense->firm_type_id == 3 || $refLicense->firm_type_id == 4) {
                    $show .= ',' . '5';
                }
                if ($refLicense->category_type_id == 2) {
                    $show .= ',' . '6';
                }
            }
            $show = explode(",", $show);
            // DB::enableQueryLog();
            $data = TradeParamDocumentType::select("doc_for", "is_mandatory", "show")
                ->where("application_type_id", $refLicense->application_type_id)
                ->where("status", 1)
                ->whereIn("show", $show)
                ->groupBy("doc_for", "is_mandatory", "show");
            if (strtoupper($refLicense->apply_from) == "ONLINE") {
                $data = $data->where("doc_for", "<>", "Application Form");
            }
            $data =    $data->get();
            // dd(DB::getQueryLog());
            return $data;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    public function getDocumentList($doc_for, $application_type_id, $show)
    {
        try {
            $data = TradeParamDocumentType::select("id", "doc_name")
                ->where("status", 1)
                ->where("doc_for", $doc_for)
                ->where("application_type_id", $application_type_id)
                ->where("show", $show)
                ->get();
            return $data;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    public function check_doc_exist($licenceId, $doc_for, $doc_mstr_id = null, $woner_id = null)
    {
        try {

            $doc = ActiveTradeDocument::select("id", "doc_type_code", "is_verified", "remarks", "document_id")
                ->where('temp_id', $licenceId)
                ->where('doc_type_code', $doc_for);
            if ($doc_mstr_id) {
                $doc = $doc->where('document_id', $doc_mstr_id);
            }
            if ($woner_id) {
                $doc = $doc->where('temp_owner_id', $woner_id);
            }
            $doc = $doc->orderBy('id', 'DESC')
                ->first();
            return $doc;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function check_doc_exist_owner($licenceId, $owner_id, $document_id = null, $doc_type_code = null)
    {
        try {
            $doc = ActiveTradeDocument::select("id", "doc_type_code", "is_verified", "remarks",  "document_id")
                ->where('temp_id', $licenceId)
                ->where('temp_owner_id', $owner_id);
            if ($doc_type_code) {
                $doc = $doc->where('doc_type_code', $doc_type_code);
            }
            if ($document_id !== null) {
                $document_id = (int)$document_id;
                $doc = $doc->where('document_id', $document_id);
            } else {
                $doc = $doc->where("document_id", "<>", 0);
            }
            $doc = $doc->orderBy('id', 'DESC')
                ->first();
            return $doc;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function readDocumentPath($path)
    {
        $path = (config('app.url') . '/api/getImageLink?path=' . $path);
        return $path;
    }
    public function applicationStatus($licenceId, $docChequ2 = true)
    {
        $refUser        = Auth()->user();
        $refUserId      = $refUser->id ?? 0;
        $refUlbId       = $refUser->ulb_id ?? 0;
        $refWorkflowId  = $this->_WF_MASTER_Id;
        $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId, $refUlbId);
        $application = TradeLicence::find($licenceId);
        if (!$application) {
            $application = ActiveTradeLicence::find($licenceId);
        }
        if (!$application) {
            $application = RejectedTradeLicence::find($licenceId);
        }
        if (!$application) {
            $application = TradeRenewal::find($licenceId);
        }
        $status = "";
        if ($application->pending_status == 5 && $application->payment_status == 1) {
            $status = "License Created Successfully";
            if ($application->application_type_id == 4) {
                $status = "License Surrenderd Successfully";
            }
            if ($application->valid_upto < Carbon::now()->format("Y-m-d")) {
                $status = "License Expired On " . $application->valid_upto;
            }
        } elseif ($application->pending_status == 5 && $application->payment_status != 1) {
            $status = "Application is Approved But Payment Not Clear";
            if ($application->application_type_id == 4) {
                $status = "License Surrenderd Successfully";
            }
        } elseif ($application->gettable() == (new RejectedTradeLicence)->gettable()) {
            $rols  = WfRole::find($application->current_role);
            $status = "Application Is Rejected By " . ($rols->role_name ?? "");
        } elseif ($application->is_parked) {
            $rols  = WfRole::find($application->current_role);
            $status = "Application back to citizen by " . ($rols->role_name ?? "");
        } elseif ($application->pending_status != 0 && (($application->current_role != $application->finisher_role) || ($application->current_role == $application->finisher_role))) {
            $rols  = WfRole::find($application->current_role);
            $status = "Application pending at " . ($rols->role_name ?? "");
        } elseif (!$application->is_active) {
            $status = "Application rejected ";
        } # && $application->payment_status == 0
        elseif ($docChequ2 && strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""] && $application->citizen_id == $refUserId && $application->document_upload_status == 0) {
            $request = new Request(["applicationId" => $licenceId, "ulb_id" => $refUlbId, "user_id" => $refUserId]);
            $doc_status = $this->checkWorckFlowForwardBackord($request);
            if ($doc_status) { #&& $application->payment_status == 0
                $status = "All Required Documents Are Uploaded"; # But Payment is Pending 
            } elseif ($doc_status && $application->payment_status == 1) {
                $status = "Pending At Counter";
            } elseif (!$doc_status && $application->payment_status == 1) {
                $status = "Payment is Done But Document Not Uploaded";
            } elseif (!$doc_status) { # && $application->payment_status == 0
                $status = "Document Not Uploaded"; #Payment is Pending And 
            }
        } elseif ($docChequ2 && $application->payment_status == 1 && $application->document_upload_status == 0 && $application->application_type_id == 4) {
            $request = new Request(["applicationId" => $licenceId, "ulb_id" => $refUlbId, "user_id" => $refUserId]);
            $doc_status = $this->checkWorckFlowForwardBackord($request);
            if ($doc_status) {
                $status = "All Required Documents Are Uploaded ";
            } else {
                $status = "All Required Documents Are Not Uploaded ";
            }
        } elseif ($application->payment_status == 0 && $application->document_upload_status == 0) {
            $status = "Payment is pending and document not uploaded ";
        } elseif ($application->payment_status == 1 && $application->document_upload_status == 0) {
            $status = "Payment is done but document not uploaded ";
        } elseif ($application->payment_status == 0 && $application->document_upload_status == 1) {
            $status = "Payment is pending but document is uploaded ";
        } elseif ($application->payment_status == 1 && $application->document_upload_status == 1) {
            $status = "Payment is done and document is uploaded ";
        } elseif ($application->payment_status == 2 && $application->document_upload_status == 1) {
            $status = "Document is uploaded but Payment is not clear";
        } elseif ($application->payment_status == 2 && $application->document_upload_status == 0) {
            $status = "Payment is not clear and document not uploaded ";
        } else {
            $status = "Applilcation Not Appoved";
        }


        return $status;
    }

    public function getWorkflowTrack($licenseId)
    {
        try {
            $data = WorkflowTrack::select("*")
                ->where("ref_table_dot_id", "active_trade_licences")
                ->where("ref_table_id_value", "$licenseId")
                ->where("status", 1)
                ->whereNotNull("sender_role_id")
                ->where("verification_status", 0)
                ->orderBy("id", "DESC")
                ->first();
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    public function insertWorkflowTrack(array $arg)
    {
        try {
            $WorkflowTrack = new WorkflowTrack;
            $WorkflowTrack->workflow_id      = $arg["workflow_id"] ?? null;
            $WorkflowTrack->citizen_id       = $arg["citizen_id"] ?? null;
            $WorkflowTrack->ref_table_dot_id = $arg["ref_table_dot_id"] ?? null;
            $WorkflowTrack->ref_table_id_value = $arg["ref_table_id_value"] ?? null;
            $WorkflowTrack->message         = $arg["message"] ?? null;
            $WorkflowTrack->track_date      = $arg["track_date"] ?? null;
            $WorkflowTrack->forward_date    = $arg["forward_date"] ?? null;
            $WorkflowTrack->module_id       = $arg["module_id"] ?? null;
            $WorkflowTrack->user_id         = $arg["user_id"] ?? null;
            $WorkflowTrack->sender_role_id  = $arg["sender_role_id"] ?? null;
            $WorkflowTrack->receiver_role_id = $arg["receiver_role_id"] ?? null;
            $WorkflowTrack->verification_status = $arg["verification_status"] ?? 0;
            $WorkflowTrack->forward_time    = $arg["forward_time"] ?? null;
            $WorkflowTrack->save();
            $i = $WorkflowTrack->id;
            return $i;
        } catch (Exception $e) {
        }
    }

    public function checkWorckFlowForwardBackord(Request $request)
    {
        $user = Auth()->user();
        $user_id = $user->id ?? $request->user_id;
        $ulb_id = $user->ulb_id ?? $request->ulb_id;
        $refWorkflowId = $this->_WF_MASTER_Id;
        $allRolse = collect($this->_COMMON_FUNCTION->getAllRoles($user_id, $ulb_id, $refWorkflowId, 0, true));
        // $init_finish = $this->_COMMON_FUNCTION->iniatorFinisher($user_id, $ulb_id, $refWorkflowId);
        $mUserType      = $this->_COMMON_FUNCTION->userType($refWorkflowId, $ulb_id);
        $fromRole = [];
        if (!empty($allRolse)) {
            $fromRole = array_values(objToArray($allRolse->where("id", $request->senderRoleId)))[0] ?? [];
        }
        if (strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""] || ($fromRole["can_upload_document"] ?? false) ||  ($fromRole["can_verify_document"] ?? false)) {
            $documents = $this->getLicenseDocLists($request);
            if (!$documents->original["status"]) {
                return false;
            }
            $applicationDoc = $documents->original["data"]["listDocs"];
            $ownerDoc = $documents->original["data"]["ownerDocs"];
            $appMandetoryDoc = $applicationDoc->whereIn("docType", ["R", "OR"]);
            $appUploadedDoc = $applicationDoc->whereNotNull("uploadedDoc");
            // dd($ownerDoc,$applicationDoc,$fromRole["can_upload_document"]);
            $appUploadedDocVerified = collect();
            $appUploadedDocRejected = collect();
            $appMadetoryDocRejected  = collect();
            $appUploadedDoc->map(function ($val) use ($appUploadedDocVerified, $appUploadedDocRejected, $appMadetoryDocRejected) {

                $appUploadedDocVerified->push(["is_docVerify" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"]) ? true : false) : true)]);
                $appUploadedDocRejected->push(["is_docRejected" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"] == 2) ? true : false) : false)]);
                if (in_array($val["docType"], ["R", "OR"])) {
                    $appMadetoryDocRejected->push(["is_docRejected" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"] == 2) ? true : false) : false)]);
                }
            });
            $is_appUploadedDocVerified          = $appUploadedDocVerified->where("is_docVerify", false);
            $is_appUploadedDocRejected          = $appUploadedDocRejected->where("is_docRejected", true);
            $is_appUploadedMadetoryDocRejected  = $appMadetoryDocRejected->where("is_docRejected", true);
            // $is_appMandUploadedDoc              = $appMandetoryDoc->whereNull("uploadedDoc");
            $is_appMandUploadedDoc = $appMandetoryDoc->filter(function ($val) {
                return ($val["uploadedDoc"] == "" || $val["uploadedDoc"] == null);
            });

            $Wdocuments = collect();
            $ownerDoc->map(function ($val) use ($Wdocuments) {
                $ownerId = $val["ownerDetails"]["ownerId"] ?? "";
                $val["documents"]->map(function ($val1) use ($Wdocuments, $ownerId) {
                    $val1["ownerId"] = $ownerId;
                    $val1["is_uploded"] = (in_array($val1["docType"], ["R", "OR"]))  ? ((!empty($val1["uploadedDoc"])) ? true : false) : true;
                    $val1["is_docVerify"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"]) ? true : false) : true;
                    $val1["is_docRejected"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"] == 2) ? true : false) : false;
                    $val1["is_madetory_docRejected"] = (!empty($val1["uploadedDoc"]) && in_array($val1["docType"], ["R", "OR"])) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"] == 2) ? true : false) : false;
                    $Wdocuments->push($val1);
                });
            });
            $ownerMandetoryDoc              = $Wdocuments->whereIn("docType", ["R", "OR"]);
            $is_ownerUploadedDoc            = $Wdocuments->where("is_uploded", false);
            $is_ownerDocVerify              = $Wdocuments->where("is_docVerify", false);
            $is_ownerDocRejected            = $Wdocuments->where("is_docRejected", true);
            $is_ownerMadetoryDocRejected    = $Wdocuments->where("is_madetory_docRejected", true);
            if (($fromRole["can_upload_document"] ?? false) || strtoupper($mUserType) == $this->_TRADE_CONSTAINT["USER-TYPE-SHORT-NAME"][""]) {
                return (empty($is_ownerUploadedDoc->all()) && empty($is_ownerDocRejected->all()) && empty($is_appMandUploadedDoc->all()) && empty($is_appUploadedDocRejected->all()));
            }
            if ($fromRole["can_verify_document"] ?? false) {
                return (empty($is_ownerDocVerify->all()) && empty($is_appUploadedDocVerified->all()) && empty($is_ownerMadetoryDocRejected->all()) && empty($is_appUploadedMadetoryDocRejected->all()));
            }
        }
        return true;
    }
    #-------------------- End core function of core function --------------

    public function getLicenseDocLists(Request $request)
    {
        $request->validate([
            'applicationId' => 'required|numeric'
        ]);
        try {
            $refApplication = ActiveTradeLicence::find($request->applicationId);
            if (!$refApplication) {
                throw new Exception("Application Not Found for this id");
            }
            $refOwners = ActiveTradeOwner::owneresByLId($request->applicationId);
            $DocsType['listDocs'] = $this->getApplTypeDocList($refApplication);
            $DocsType['ownerDocs'] = collect($refOwners)->map(function ($owner) use ($refApplication) {
                return $this->getOwnerDocLists($owner, $refApplication);
            });
            $status =  $this->check($DocsType);
            $DocsType['docUploadStatus'] = ($status["docUploadStatus"] ?? false) ? 1 : 0;
            $DocsType['docVerifyStatus'] = ($status["docVerifyStatus"] ?? false) ? 1 : 0;
            $DocsType["citizenCanSendOfficer"] = ($DocsType['docUploadStatus'] && ($refApplication->initiator_role == $refApplication->current_role || $refApplication->is_parked)) ? true  : false;
            return responseMsgs(true, "Documents Fetched", remove_null($DocsType), "010203", "1.0", "", 'POST', "");
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), "", "010203", "1.0", "", 'POST', "");
        }
    }

    public function check($documentsList)
    {
        $applicationDoc = $documentsList["listDocs"];
        $ownerDoc = $documentsList["ownerDocs"];
        $appMandetoryDoc = $applicationDoc->whereIn("docType", ["R", "OR"]);
        $appUploadedDoc = $applicationDoc->whereNotNull("uploadedDoc");
        $appUploadedDocVerified = collect();
        $appUploadedDoc->map(function ($val) use ($appUploadedDocVerified) {
            $appUploadedDocVerified->push(["is_docVerify" => (!empty($val["uploadedDoc"]) ?  (((collect($val["uploadedDoc"])->all())["verifyStatus"] != 0) ? true : false) : true)]);
        });
        $is_appUploadedDocVerified = $appUploadedDocVerified->where("is_docVerify", false);
        $is_appMandUploadedDoc  = $appMandetoryDoc->whereNull("uploadedDoc");
        $Wdocuments = collect();
        $ownerDoc->map(function ($val) use ($Wdocuments) {
            $ownerId = $val["ownerDetails"]["ownerId"] ?? "";
            $val["documents"]->map(function ($val1) use ($Wdocuments, $ownerId) {
                $val1["ownerId"] = $ownerId;
                $val1["is_uploded"] = (in_array($val1["docType"], ["R", "OR"]))  ? ((!empty($val1["uploadedDoc"])) ? true : false) : true;
                $val1["is_docVerify"] = !empty($val1["uploadedDoc"]) ?  (((collect($val1["uploadedDoc"])->all())["verifyStatus"] != 0) ? true : false) : true;
                $Wdocuments->push($val1);
            });
        });
        $ownerMandetoryDoc = $Wdocuments->whereIn("docType", ["R", "OR"]);
        $is_ownerUploadedDoc = $Wdocuments->where("is_uploded", false);
        $is_ownerDocVerify = $Wdocuments->where("is_docVerify", false);
        $data = [
            "docUploadStatus" => 0,
            "docVerifyStatus" => 0,
        ];
        $data["docUploadStatus"] = (empty($is_ownerUploadedDoc->all()) && empty($is_appMandUploadedDoc->all()));
        $data["docVerifyStatus"] =  (empty($is_ownerDocVerify->all()) && empty($is_appUploadedDocVerified->all()));
        return ($data);
    }
}
