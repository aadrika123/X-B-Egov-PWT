<?php

namespace App\Repository\Trade;

use Illuminate\Http\Request;

/**
 * | Created On-22-12-2022 
 * | Created By-Sandeep Bara
 * ------------------------------------------------------------------------------------------
 * | Interface for Eloquent Trade Citizen Repository
 */

interface ITradeCitizen
{
    public function addRecord(Request $request);
    public function citizenApplication(Request $request);
    public function readCitizenLicenceDtl(Request $request);
    public function readCitizenTemplicenseDtl(Request $request);
    public function citizenApplicationByCitizenId(Request $request);
}
