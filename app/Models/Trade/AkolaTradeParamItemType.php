<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AkolaTradeParamItemType extends Model
{
    use HasFactory;

    public $timestamps=false;
    protected $connection;
    public function __construct($DB=null)
    {
       $this->connection = $DB ? $DB:"pgsql_trade";
    }
    public static function List($all=false)
    {
        return self::select("id","trade_item","trade_item_marathi","trade_code")
                ->where("status",1)
                // ->where("id","<>",187)
                ->orderBy("id","ASC")
                ->get();
    }
    public static function itemsById($id)
    {        
        if(!$id)
        {
            $id="0";
        }
        $id = explode(",",$id);
        $items = self::select("*")
            ->whereIn("id",$id)
            ->get();
        return $items;
               
    }
}
