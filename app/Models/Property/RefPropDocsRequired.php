<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefPropDocsRequired extends PropParamModel #Model
{
    use HasFactory;
    protected $table = 'ref_prop_docs_required';

    /**
     * | Get Document Name by Document Type
     */
    public function getDocByDocType($docType)
    {
        return RefPropDocsRequired::select('id', 'doc_name')
            ->where('doc_type', $docType)
            ->get();
    }
}
