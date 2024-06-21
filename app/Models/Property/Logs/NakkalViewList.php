<?php

namespace App\Models\Property\Logs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NakkalViewList extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * | Create 
     */
    public function store($reqs)
    {
        return NakkalViewList::create($reqs);
    }
}
