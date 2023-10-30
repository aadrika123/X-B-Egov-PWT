<?php

namespace App\Http\Controllers\Property\Akola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\HoldingTaxController;
use App\Repository\Property\Interfaces\iSafRepository;
use Exception;
use Illuminate\Http\Request;

class WhatsappReceiptController extends Controller
{

    /**
     * | Author - Anshu Kumar
     * | Date - 06-10-2023 
     * | Created for the Send Whatsapp Receipt
     * | Status - Closed
     */

    protected $iSaf;
    private $_holdingTaxController;

    //
    public function __construct(iSafRepository $repo)
    {
        $this->_holdingTaxController = new HoldingTaxController($repo);
    }

    public function sendPaymentReceipt($tranId)
    {
        $receiptReq = new Request([
            'tranId' => $tranId
        ]);
        try {
            $tranDetails = $this->_holdingTaxController->propPaymentReceipt($receiptReq);
            // dd($tranDetails);
            if ($tranDetails->original['status'] == false)
                throw new Exception($tranDetails->original['message']);
            $data = $tranDetails->original['data'];

            return view('property_payment_reciept', ['data' => $data]);
        } catch (Exception $e) {
            return responseMsgs(false, $e->getMessage(), []);
        }
    }
}