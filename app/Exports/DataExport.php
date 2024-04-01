<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class DataExport implements FromCollection
{
    /**
     * ========================================
     *          Created By : Sandeep Bara
     *          Date       : 2024-03-30
     */
    private $_data = [];
    public function __construct(array $data)
    {
        $this->_data = $data;
        foreach($data as $key=>$val)
        {
            if(!is_array($val))
            {
                $this->_data = [];
                $this->_data[] = $data; 
            }
            break;
        }
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return new Collection($this->_data);
    }
}
