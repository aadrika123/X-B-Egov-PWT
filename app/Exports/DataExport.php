<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataExport implements FromCollection , WithHeadings
{
    /**
     * ========================================
     *          Created By : Sandeep Bara
     *          Date       : 2024-03-30
     */
    private $_data = [];
    private $_headings = [];
    public function __construct(array $data, array $headings = [])
    {
        $this->_headings = $headings;
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
    public function headings(): array
    {
        return (!$this->_headings ? collect(collect($this->_data)->first())->keys() : collect($this->_headings))->toArray();
    }
}
