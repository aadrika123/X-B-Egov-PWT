<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterSecondConsumer extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Get consumer by consumer Id
     */
    public function getConsumerDetailsById($consumerId)
    {
        return WaterSecondConsumer::where('id',$consumerId);
    }

    /**
     * apply for akola 
     */
    public function saveConsumer($req,$meta,$applicationNo){
        $waterSecondConsumer = new WaterSecondConsumer();
        
        $waterSecondConsumer->ulb_id                    = $req->ulbId;
        $waterSecondConsumer->zone                      = $req->zone;
        $waterSecondConsumer->cycle                     = $req->Cycle;
        $waterSecondConsumer->property_no               = $req->PropertyNo;
        $waterSecondConsumer->consumer_no               = $applicationNo;
        $waterSecondConsumer->mobile_no                 = $req->MoblieNo;
        $waterSecondConsumer->address                   = $req->Address;
        $waterSecondConsumer->landmark                  = $req->PoleLandmark;
        $waterSecondConsumer->dtc_code                  = $req->DtcCode;
        $waterSecondConsumer->meter_make                = $req->MeterMake;
        $waterSecondConsumer->meter_no                  = $req->MeterNo;
        $waterSecondConsumer->meter_digit               = $req->MeterDigit;
        $waterSecondConsumer->tab_size                  = $req->TabSize;
        $waterSecondConsumer->meter_state               = $req->MeterState;
        $waterSecondConsumer->reading_date              = $req->ReadingDate;
        $waterSecondConsumer->connection_date           = $req->ConectionDate;
        $waterSecondConsumer->disconnection_date        = $req->DisconnectionDate;
        $waterSecondConsumer->disconned_reading         = $req->DisconnedDate;
        $waterSecondConsumer->book_no                   = $req->BookNo;
        $waterSecondConsumer->folio_no                  = $req->FolioNo;
        $waterSecondConsumer->building_type             = $req->BuildingType;
        $waterSecondConsumer->no_of_connection          = $req->NoOfConnection;
        $waterSecondConsumer->is_meter_rented           = $req->IsMeterRented;
        $waterSecondConsumer->rent_amount               = $req->RentAmount;
        $waterSecondConsumer->total_installment         = $req->TotalInstallment;
        $waterSecondConsumer->nearest_consumer_no       = $req->NearestConsumerNo;
        $waterSecondConsumer->status                    = $meta['status'];
        $waterSecondConsumer->ward_mstr_id              = $meta['wardmstrId'];

        $waterSecondConsumer->save();
        return $waterSecondConsumer;
        
 }
 
    /**
     * get all details 
     */

     public function getallDetails($applicationId){       
         return  WaterSecondConsumer::select(
            'water_second_consumers.*'

         )
         ->where('water_second_consumers.id',$applicationId)
         ->get();

        }

            /**
     * | Get active request by request id 
     */
    public function getActiveReqById($id)
    {
        return WaterSecondConsumer ::where('id', $id)
            ->where('status', 4);
    }

    /**
     * | get the water consumer detaials by consumr No
     * | @param consumerNo
     * | @var 
     * | @return 
     */
    public function getDetailByConsumerNo($req, $key, $refNo)
    {
        return WaterSecondConsumer::select(
            'water_second_consumers.id',
            'water_second_consumers.consumer_no',
            'water_second_consumers.ward_mstr_id',
            'water_second_consumers.address',
            'water_second_consumers.ulb_id',
            // 'ulb_ward_masters.ward_name',
         
        )
            // ->leftJoin('ulb_ward_masters', 'ulb_ward_masters.id', 'water_second_consumers.ward_mstr_id')
            ->where('water_second_consumers.' . $key, 'LIKE', '%' . $refNo . '%')
            // ->where('water_second_consumers.status', 1)
            ->groupBy(
                'water_second_consumers.id',
                'water_second_consumers.ulb_id',
                'water_second_consumers.consumer_no',
                'water_second_consumers.ward_mstr_id',
                'water_second_consumers.address',
                // 'ulb_ward_masters.ward_name'
            );
    }
    
    /**
     * | Update the payment status and the current role for payment 
     * | After the payment is done the data are update in active table
     */
    public function updateDataForPayment($applicationId, $req)
    {
        WaterSecondConsumer::where('id', $applicationId)
            ->where('status', 4)
            ->update($req);
    }

    /**
     * |----------------------- Get Water Application detals With all Relation ------------------|
     * | @param request
     * | @return 
     */
    public function fullWaterDetails($request)
    {
        return  WaterSecondConsumer::select(
            'water_second_consumers.*',
            'water_second_consumers.consumer_no',
            // 'ulb_masters.ulb_name',
            "water_second_connection_charges.amount",
            'water_second_connection_charges.charge_category'
           
        )
            ->join('water_second_connection_charges','water_second_connection_charges.consumer_id','water_second_consumers.id')
            ->where('water_second_consumers.id', $request->applicationId)
            ->where('water_second_consumers.status', 4);
    }


}
