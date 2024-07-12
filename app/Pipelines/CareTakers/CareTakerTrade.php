<?php

namespace App\Pipelines\CareTakers;

use App\Http\Controllers\ThirdPartyController;
use App\Models\Citizen\ActiveCitizenUndercare;
use App\Models\Trade\TradeLicence;
use App\Models\Trade\TradeOwner;
use Closure;
use Exception;

/**
 * | Created On-21-04-2023 
 * | Created By-Sam kerketta
 * | Status - Closed
 * | ------------------------------
 * | PipeLine Class to Tag Trade 
 */
class CareTakerTrade
{
    private $_mTradeLicenses;
    private $_mTradeOwners;
    private $_tradeId;
    private $_mActiveCitizenUnderCares;
    private $_licenseNo;
    private $_ThirdPartyController;

    /**
     * | Initialing Values
     */
    public function __construct()
    {
        $this->_mTradeLicenses = new TradeLicence();
        $this->_mTradeOwners = new TradeOwner();
        $this->_mActiveCitizenUnderCares = new ActiveCitizenUndercare();
        $this->_ThirdPartyController = new ThirdPartyController();
    }


    public function handle($request, Closure $next)
    {

        if (request()->input('moduleId') != 3) {
            return $next($request);
        }

        $referenceNo = request()->input('referenceNo');
        $trade = $this->_mTradeLicenses->getTradeIdByLicenseNo($referenceNo);
        if (!$trade)
            throw new Exception('Enter Valid License No.');
        $this->_tradeId = $trade->id;
        $this->_licenseNo = $referenceNo;
        $this->isTradeAlreadyTagged();                                                      // function (1.1)
        $tradeOwner = $this->_mTradeOwners->getFirstOwner($this->_tradeId);
        if (is_null($tradeOwner->mobile_no)) {
            throw new Exception('This license does not have an associated mobile number.');
        }
        $myRequest = new \Illuminate\Http\Request();
        $myRequest->setMethod('POST');
        $myRequest->request->add(['mobileNo' => $tradeOwner->mobile_no]);
        $myRequest->request->add(['type' => "Attached Connection"]);
        $otpResponse = $this->_ThirdPartyController->sendOtp($myRequest);
        $verificationStatus = collect($otpResponse)['original']['status'];
        if ($verificationStatus == false)
            throw new Exception(collect($otpResponse)['original']['message']);

        $response = collect($otpResponse)->toArray();
        $data = [
            'otp' => $response['original']['data'],
            'mobileNo' => $tradeOwner->mobile_no
        ];
        return $data;
    }

    /**
     * | Is The Property Already Tagged
     */
    public function isTradeAlreadyTagged()
    {
        $taggedTradeList = $this->_mActiveCitizenUnderCares->getTaggedTrades($this->_licenseNo);
        $totalTrades = $taggedTradeList->count('license_no');
        if ($totalTrades > 3)                                               // Check if the Property is already tagged 3 times of not
            throw new Exception("License has already tagged 3 Times");

        $citizens = $taggedTradeList->pluck('citizen_id');

        if ($citizens->contains(auth()->user()->id))                                // Check Is the Property already tagged by the citizen 
            throw new Exception("License Already Tagged");
    }
}
