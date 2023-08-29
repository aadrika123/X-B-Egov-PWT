<?php

namespace App\BLL\Property\Akola;

use App\Models\Property\PropActiveSaf;
use App\Models\Property\PropActiveSafsFloor;
use App\Models\Property\PropSaf;
use App\Models\Property\PropSafsFloor;
use App\Models\Property\PropSafVerification;
use App\Models\Property\PropSafVerificationDtl;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * | ✅✅Created by-Anshu Kumar
 * | Created for-Calculation of the tax as per Ulb Verification details
 * | Status-Closed
 */
class CalculateTaxByUlb extends TaxCalculator
{
    private $_mPropSafVerifications;
    private $_mPropSafVerificationDtls;
    private $_verificationId;
    private $_propVerifications;
    private $_propVerificationDtls;
    private $_safs;
    private $_REQUEST;

    /**
     * | @var verificationId Required parameter Verification id
     */
    public function __construct($verificationId)
    {
        $this->_verificationId = $verificationId;
        $this->_mPropSafVerifications = new PropSafVerification();
        $this->_mPropSafVerificationDtls = new PropSafVerificationDtl();

        $this->readParams();                // Read all the parameter for calculation
        $this->generateRequests();
        parent::__construct($this->_REQUEST);                           // making parent constructor for tax calculator BLL
        $this->calculateTax();                                          // Calculate Tax with Tax Calculator
    }

    /**
     * | Read the master parameters
     */
    public function readParams()
    {
        $this->_propVerifications = $this->_mPropSafVerifications::find($this->_verificationId);
        if (collect($this->_propVerifications)->isEmpty())
            throw new Exception("Property Verification Details not available to generate FAM");

        $this->_safs = PropActiveSaf::find($this->_verificationId);                         // Get Saf details from active table
        if (collect($this->_safs)->isEmpty())
            $this->_safs = PropSaf::find($this->_verificationId);                           // Get Saf details from approved table
    }

    /**
     * | 🧮🧮 Requests requried for the calculation
     */
    public function generateRequests(): void
    {
        $calculationReq = [
            "propertyType" => $this->_propVerifications->prop_type_id,
            "areaOfPlot" => $this->_propVerifications->area_of_plot,
            "category" => $this->_propVerifications->category_id,
            "dateOfPurchase" => $this->_safs->date_of_purchase,
            "floor" => [],
            "owner" => []
        ];

        // Get Floors
        if ($this->_propVerifications->prop_type_id != 4) {
            $this->_propVerificationDtls = $this->_mPropSafVerificationDtls->getVerificationDetails($this->_verificationId);            // Get Verified floor details

            if (collect($this->_propVerificationDtls)->isEmpty())
                throw new Exception("Verification Floors not available for this property");

            foreach ($this->_propVerificationDtls as $floor) {
                $floorReq =  [
                    "floorNo" => $floor->floor_mstr_id,
                    "constructionType" =>  $floor->construction_type_id,
                    "usageType" => $floor->usage_type_id,
                    "buildupArea" =>  $floor->builtup_area,
                    "dateFrom" =>  $floor->date_from
                ];
                array_push($calculationReq['floor'], $floorReq);
            }
        }

        $this->_REQUEST = new Request($calculationReq);
    }
}
