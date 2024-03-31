<?php

namespace App\Http\Requests\Water;

use App\Models\Water\WaterConsumerMeter;
use App\Models\Water\WaterSecondConsumer;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class reqMeterEntry extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $currentDate = Carbon::now()->format("Y-m-d");
        $consumer = new WaterSecondConsumer();
        $WaterConsumerMeter = new WaterConsumerMeter();
        $ConsumerMeters = $WaterConsumerMeter->getConsumerMeterDetails($this->consumerId)
            ->where('status', 1)
            ->orderBy("id","DESC")                                                                            // Static
            ->first();
        $old_connection_type = $ConsumerMeters->connection_type??3;
        $tbl = $consumer->getTable();
        $con =$consumer->getConnectionName();
        $refMeterConnectionType = Config::get('waterConstaint.WATER_MASTER_DATA.METER_CONNECTION_TYPE');
        $rules['connectionType']        = 'required|int|in:1,2,3,4';
        $rules['consumerId']            = "required|digits_between:1,9223372036854775807|exists:$con.$tbl,id,status,1";
        $rules['connectionDate']        = "required|date|before_or_equal:$currentDate|date_format:Y-m-d";
        $rules['oldMeterFinalReading']  = "nullable";
        if($old_connection_type!=3)
        {
            $rules['oldMeterFinalReading']  = "required";
        }

        if (isset($this->connectionType) && $this->connectionType && in_array($this->connectionType, [$refMeterConnectionType['Meter'], $refMeterConnectionType['Gallon']])) {
            $rules['meterNo']                   = 'required';
            $rules['document']                  = 'required|mimes:pdf,jpeg,jpg,png|max:2048';
            $rules['newMeterInitialReading']    = 'required';
        }
        // if (isset($this->connectionType) && $this->connectionType && $this->connectionType == $refMeterConnectionType['Fixed']) {
        //     $rules['newMeterInitialReading'] = 'required';
        // }
        if (isset($this->connectionType) && $this->connectionType && $this->connectionType == $refMeterConnectionType['Meter/Fixed']) {
            $rules['meterNo']               = 'required';
            $rules['document']              = 'required|mimes:pdf,jpeg,jpg,png|max:2048';
        }
        return $rules;
    }


    // Validation Error Message
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'status'   => false,
                    'message'  => 'The given data was invalid',
                    'errors'   => $validator->errors()
                ],
                200
            )
        );
    }
}
