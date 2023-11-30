<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterIciciResponse extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_water';

    /**
     * | Save the data for water payament resposne
     */
    public function savePaymentResponse($iciciPayRequest, $webhookData)
    {
    }
}
