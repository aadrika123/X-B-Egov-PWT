<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropPaynimoPayRequest extends Model
{
    use HasFactory;
    protected $connection;
    protected $guarded = [];
    // public function readConnection()
    // {
    //     return $this->setConnection($this->connection."::read");
    // }

    public static function readConnection()
    {
        $self = new static; //OBJECT INSTANTIATION
        return $self->setConnection($self->connection . "::read");
    }
    public function updateTrans($id)
    {
        return self::where('id', $id)
            ->update([
                'merchant_id' => '0123',
            ]);
    }
}
