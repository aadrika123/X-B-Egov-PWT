<?php

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefRequiredDocument extends Model
{
    use HasFactory;

    /**
     * | Get All Documents by Document Code
     */
    public function getDocsByDocCode($moduldId, $docCode)
    {
        return RefRequiredDocument::select('requirements')
            ->where('module_id', $moduldId)
            ->where('code', $docCode)
            ->first();
    }

    /**
     * | Get Documents where module Id
     */
    public function getDocsByModuleId($moduleId)
    {
        return RefRequiredDocument::select('code', 'requirements')
            ->where('module_id', $moduleId)
            ->get();
    }

    /**
     * | Get  All Document Collictively For Array Of DocCode
     */
    public function getCollectiveDocByCode($moduldId, $docCodes)
    {
        return RefRequiredDocument::select(
            'requirements',
            'code'
        )
            ->where('module_id', $moduldId)
            ->whereIn('code', $docCodes)
            ->get();
    }

    /**
     * | Get Total no of document for upload
     */
    public function totalNoOfDocs($moduleId,$docCode)
    {
        $noOfDocs = RefRequiredDocument::select('requirements')
            // ->where('code', $docCode)
            ->where('module_id',$moduleId)
            ->where('code',$docCode)
            ->first();
        $totalNoOfDocs = explode("#", $noOfDocs);
        return count($totalNoOfDocs);
    }
}
