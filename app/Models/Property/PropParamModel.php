<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropParamModel extends Model
{
    use HasFactory;
    // protected $connection;
    protected $guarded = [];
    // public function __construct($DB=null)
    // {
        protected $connection = "pgsql";
    // }
    // public function readConnection()
    // {
    //     return $this->setConnection($this->connection."::read");
    // }

    public static function readConnection()
    {
        $self = new static; //OBJECT INSTANTIATION
        return $self->setConnection($self->connection."::read");
    }
}
