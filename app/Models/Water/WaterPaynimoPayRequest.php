<?php

namespace App\Models\Water;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterPaynimoPayRequest extends Model
{
    use HasFactory;
    protected $connection;
    protected $guarded = [];
    public function __construct($DB = null)
    {
        $this->connection = $DB ? $DB : "pgsql_water";
    }
    // public function readConnection()
    // {
    //     return $this->setConnection($this->connection."::read");
    // }

    public static function readConnection()
    {
        $self = new static; //OBJECT INSTANTIATION
        return $self->setConnection($self->connection . "::read");
    }
}
