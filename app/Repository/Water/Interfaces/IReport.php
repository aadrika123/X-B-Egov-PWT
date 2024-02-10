<?php

namespace App\Repository\Water\Interfaces;

use Illuminate\Http\Request;

interface IReport
{
    public function tranDeactivatedList(Request $request);
}