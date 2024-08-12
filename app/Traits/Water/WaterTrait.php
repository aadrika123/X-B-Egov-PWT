<?php

namespace App\Traits\Water;

use App\Models\Water\WaterApplication;
use App\Models\Water\WaterConsumerActiveRequest;
use App\Models\Water\WaterPenaltyInstallment;
use App\Models\Water\WaterTranFineRebate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Created By-Sam kerketta
 * Created On- 27-06-2022
 * Creation Purpose- For Common function or water module
 */

trait WaterTrait
{

    /**
     * |----------------------------- Get Water Application List For the Workflow ---------------------------|
     * | Rating : 
     * | Opertation : serch the application for the respective ulb/workflow
        | Serial No : 01
        | Working
     */
    public function getWaterApplicatioList($workflowIds, $ulbId)
    {
        return WaterApplication::select(
            'water_applications.id',
            'water_applications.application_no',
            'water_applicants.id as owner_id',
            'water_applicants.applicant_name as owner_name',
            // 'water_applications.ward_id',
            'water_connection_through_mstrs.connection_through',
            'water_connection_type_mstrs.connection_type',
            // 'u.ward_name as ward_no',
            'water_applications.workflow_id',
            'water_applications.current_role as role_id',
            'water_applications.apply_date',
            'water_applications.parked',
            'ulb_ward_masters.ward_name'
        )
            // ->join('ulb_ward_masters as u', 'u.id', '=', 'water_applications.ward_id')
            ->join('water_applicants', 'water_applicants.application_id', '=', 'water_applications.id')
            ->leftjoin('water_connection_through_mstrs', 'water_connection_through_mstrs.id', '=', 'water_applications.connection_through')
            ->leftjoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_applications.ward_id')
            ->leftjoin('water_connection_type_mstrs', 'water_connection_type_mstrs.id', '=', 'water_applications.connection_type_id')
            ->where('water_applications.status', 1)
            ->where('water_applications.ulb_id', $ulbId)
            ->whereIn('water_applications.workflow_id', $workflowIds)
            ->orderByDesc('water_applicants.id');
    }

    /**
     * | Details for the dcb according to the perticular year
     * | @param
     * | @param
        | Serial No : 02
        | Working
     */
    public function demandByFyear($fyear, $fromDate, $uptoDate, $ulbId)
    {
        $raw = "SELECT     
                    '$fyear' as fyear,    
                    SUM (amount) AS totalDemand,
                    SUM(CASE WHEN paid_status = 1 THEN amount ELSE 0 END )AS totalCollection,
                    sum (amount - CASE WHEN paid_status = 1 THEN amount ELSE 0 END) as totalBalance
                FROM water_consumer_demands 
                WHERE water_consumer_demands.status = true
                AND water_consumer_demands.demand_from >= '$fromDate' 
                AND water_consumer_demands.demand_upto <= '$uptoDate'
                AND  ulb_id = '$ulbId'";
        return $raw;
    }

    /**
     * | Save Rebate details for water connection
     * | only in terms of regulisation
     * | @param req
     * | @param charges
     * | @param waterTrans
        | Serial No : 03
        | Not Tested
        | Check the code 
        | Common function
     */
    public function saveRebateForTran($req, $charges, $waterTrans)
    {
        $transactionId              = $waterTrans['id'];
        $mWaterTranFineRebate       = new WaterTranFineRebate();
        $mWaterPenaltyInstallment   = new WaterPenaltyInstallment();
        $refWaterHeadName           = Config::get("waterConstaint.WATER_HEAD_NAME");

        $connectionChargeId = collect($charges)->pluck('id')->first();
        $penaltyDetails = $mWaterPenaltyInstallment->getPenaltyByChargeId($connectionChargeId)->get();
        $checkPenalty = collect($penaltyDetails)->first();
        if (!$checkPenalty) {
            throw new Exception("penalty details not found or there is false data!");
        }

        collect($penaltyDetails)->map(function ($value)
        use ($mWaterTranFineRebate, $req, $transactionId) {
            $metaRequest = new Request([
                "headName"      => $value['penalty_head'],
                "amount"        => $value['balance_amount'],
                "applicationId" => $req->applicationId,
                "valueAddMinus" => "+"                                                          // Static
            ]);
            $mWaterTranFineRebate->saveRebateDetails($metaRequest, $transactionId);
        });
        $refPenalty = collect($charges)->pluck('penalty')->first();
        $actualPenaltyAmountRebate = (10 / 100 * $refPenalty);
        $metaRequest = new Request([
            "headName"      => $refWaterHeadName['1'],
            "amount"        => $actualPenaltyAmountRebate,
            "applicationId" => $req->applicationId,
            "valueAddMinus" => "-"                                                              // Static
        ]);
        $mWaterTranFineRebate->saveRebateDetails($metaRequest, $transactionId);
    }

    /**
     * | common function for workflow
     * | Get consumer active application details 
        | Serial No : 04
        | Working
     */
    public function getConsumerWfBaseQuerry($workflowIds, $ulbId)
    {
        return WaterConsumerActiveRequest::select(
            'water_consumer_active_requests.id',
            'wco.id as owner_id',
            'water_consumer_active_requests.application_no',
            'water_consumer_active_requests.apply_date',
            'water_consumer_active_requests.charge_catagory_id',
            'wco.applicant_name as owner_name',
            'water_second_consumers.consumer_no',
            'water_consumer_active_requests.workflow_id',
            'uwm.ward_name'
        )
            ->leftjoin('water_consumer_owners AS wco', 'wco.consumer_id', 'water_consumer_active_requests.consumer_id')
            ->leftjoin('ulb_ward_masters AS uwm', 'uwm.id', 'water_consumer_active_requests.ward_mstr_id')
            ->join('water_second_consumers', 'water_second_consumers.id', 'water_consumer_active_requests.consumer_id')
            // ->join('ulb_ward_masters', 'ulb_ward_maters.id', 'water_second_consumers.ward_mstr_id')
            // ->leftjoin('ulb_masters AS um', 'um.id', 'water_consumer_active_requests.ulb_id')
            ->where('water_consumer_active_requests.status', 1)
            // ->where('water_consumer_active_requests.payment_status', 1)
            // ->where('water_consumer_active_requests.ulb_id', $ulbId)
            ->whereIn('water_consumer_active_requests.workflow_id', $workflowIds);
    }

    /**
     * | Get the Consumer Request active appliation details for view 
        | Serial No :
        | Under Con
     */
    public function getConActiveAppDetails($applicationId)
    {
        return WaterConsumerActiveRequest::select(
            'water_consumer_active_requests.*',
            'wc.holding_no',
            'wc.saf_no',
            'uwm.ward_name',
            'um.ulb_name',
            'wcc.amount AS total_amount',
            'wcc.penalty',
            'wcc.charge_amount',
            'wcc.paid_status AS charges_paid_status',
            DB::raw("
            CONCAT(
                UPPER(SUBSTRING(wcc.charge_category, 1, 1)),
                LOWER(REPLACE(SUBSTRING(wcc.charge_category, 2), '_', ' '))
            ) AS formatted_column
            ")
        )
            ->join('water_second_consumers AS wc', 'wc.id', 'water_consumer_active_requests.consumer_id')
            ->leftjoin('ulb_ward_masters AS uwm', 'uwm.id', 'water_consumer_active_requests.ward_mstr_id')
            ->leftjoin('ulb_masters AS um', 'um.id', 'water_consumer_active_requests.ulb_id')
            ->join('water_consumer_charges AS wcc', 'wcc.related_id', 'water_consumer_active_requests.id')
            ->where('water_consumer_active_requests.id', $applicationId)
            ->where('water_consumer_active_requests.status', 1);
    }
}
