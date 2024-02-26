<?php

namespace App\Observers\Property;

use App\Models\Masters\IdGenerationParam;
use App\Models\Property\PropSafJahirnamaDoc;
use App\Models\UlbWardMaster;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;

class JahirnamaObserver
{
    private $_mIdGenerationParams;
    private $_currentYear;
    public function __construct()
    {
        $this->_currentYear = Carbon::now()->format("Y");
        $this->_mIdGenerationParams = new IdGenerationParam();
    }

    public function created(PropSafJahirnamaDoc $jahirnama)
    {
        if (!$jahirnama->jhirnama_no) {
            $jhirnama_no = $this->generateJahirnamaNo($jahirnama->is_full_update);
            $jahirnama->jhirnama_no = $jhirnama_no;
            $jahirnama->update();
        }
    }

    // ╔═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╗
    // ║                                                ✅ Prop Update No Generation ✅                                                   ║ 
    // ╚═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╝ 

    /**
     * | Generate Prop Update No
     * | created By Sandeep
     */
    public function generateJahirnamaNo($fyear=null): string
    {
        $fyear = $fyear?$fyear:getFY();
        $paramId = Config::get('PropertyConstaint.JAHIRNAMA_ID') ;
        $counter = $this->_mIdGenerationParams->where('id', $paramId)->first();
        if (collect($counter)->isEmpty())
            throw new Exception("Counter Not Available");
      
        $updateNo = str_pad($counter->int_val, 4, "0", STR_PAD_LEFT);
        $counter->int_val += 1; 

        $counter->save();
        $memoNo = trim($counter->string_val . '/' . $updateNo. '/' . $this->_currentYear,"/");
        return $memoNo;
    }
}
