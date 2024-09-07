<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropPaynimoPayResponse extends Model
{
    use HasFactory;
    protected $connection;
    protected $guarded = [];

    public static function readConnection()
    {
        $self = new static; //OBJECT INSTANTIATION
        return $self->setConnection($self->connection . "::read");
    }
}
