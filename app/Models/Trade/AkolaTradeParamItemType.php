<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AkolaTradeParamItemType extends TradeParamModel    #Model
{
    use HasFactory;
    protected $connection;
    public $timestamps=false;

    public function __construct($DB=null)
    {
        parent::__construct($DB);
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
