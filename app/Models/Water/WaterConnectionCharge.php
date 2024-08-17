<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class WaterConnectionCharge extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * |---------------------------------- Save water connection charges ---------------------------------------------|
     * | Saving the water Connection charges
     * | @param applicationId
     * | @param req
     * | @param newConnectionCharges
     * | @var chargeCatagory
     */
    public function saveWaterCharge($applicationId, $req, $newConnectionCharges)
    {
        $chargeCatagory = Config::get('waterConstaint.CHARGE_CATAGORY');

        $saveCharges = new WaterConnectionCharge();
        $saveCharges->application_id    = $applicationId;
        $saveCharges->paid_status       = 0;
        $saveCharges->status            = 1;
        $saveCharges->penalty           = $newConnectionCharges['conn_fee_charge']['penalty'];
        $saveCharges->conn_fee          = $newConnectionCharges['conn_fee_charge']['conn_fee'];
        $saveCharges->amount            = $newConnectionCharges['conn_fee_charge']['amount'];
        $saveCharges->rule_set          = $newConnectionCharges['ruleSete'];
        switch ($req->connectionTypeId) {
            case (1):                                                                       // Static
                $saveCharges->charge_category = $chargeCatagory['NEW_CONNECTION'];
                break;
            case (2):                                                                       // Static
                $saveCharges->charge_category = $chargeCatagory['REGULAIZATION'];
                break;
        }
        if ($req->chargeCatagory) {
            $saveCharges->charge_category = $chargeCatagory['SITE_INSPECTON'];
        }
        if ($newConnectionCharges['conn_fee_charge']['amount'] == 0) {
            $saveCharges->paid_status = 1;
        }
        $saveCharges->save();
        return $saveCharges->id;
    }

    /**
     * |----------------------------------- Get Water Charges By ApplicationId ------------------------------|
     * | @param request
     */
    public function getWaterchargesById($applicationId)
    {
        return WaterConnectionCharge::select(
            'id',
            'amount',
            'charge_category',
            'penalty',
            'conn_fee',
            'rule_set',
            'paid_status'
        )
            ->where('application_id', $applicationId)
            ->where('status', 1);
    }
    /**
     * |----------------------------------- Get Water Charges By ApplicationId ------------------------------|
     * | @param request
     */
    public function getWaterchargesByIdv1($applicationId)
    {
        return WaterConnectionCharge::select(
            'id',
            'amount',
            'charge_category',
            'penalty',
            'conn_fee',
            'rule_set',
            'paid_status'
        )
            ->where('application_id', $applicationId)
            ->where('charge_category','WATER RECONNECTION')
            ->where('status', 1);
    }

    /**
     * |-------------- Delete the Water Application Connection Charges -------------|
        | Recheck
     */
    public function deleteWaterConnectionCharges($applicationId)
    {
        WaterConnectionCharge::where('application_id', $applicationId)
            ->delete();
    }

    /**
     * | Deactivate the application In the process of editing
     */
    public function deactivateCharges($applicationId)
    {
        WaterConnectionCharge::where('application_id', $applicationId)
            ->update([
                'status' => false
            ]);
    }

    /**
     * | Get the Connection charges By Id
     * | @param id
     */
    public function getChargesById($id)
    {
        return WaterConnectionCharge::where('id', $id)
            ->where('status', 1);
    }


    /**
     * | Get the Connection charges By array of ids
     * | @param id
     */
    public function getChargesByIds($id)
    {
        return WaterConnectionCharge::whereIn('id', $id)
            ->where('status', 1);
    }


    /**
     * | Deactivate Site Inspection Demand 
     * | @param req
     */
    public function deactivateSiteCharges($applicationId, $siteInspection)
    {
        WaterConnectionCharge::where('application_id', $applicationId)
            ->where('charge_category', $siteInspection)
            ->update([
                'status' => 0
            ]);
    }

    /**
     * 
     */

    public function saveWaterCharges($meta)
    {
        $saveCharges = new WaterConnectionCharge();
        $saveCharges->application_id    = $meta['applicationId'];
        $saveCharges->amount            = $meta['amount'];
        $saveCharges->charge_category   = $meta['chargeCategory'];
        $saveCharges->save();
        return $saveCharges;
    }
}
