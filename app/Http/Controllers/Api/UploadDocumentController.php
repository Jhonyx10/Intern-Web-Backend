<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DocumentService;


class UploadDocumentController extends Controller
{
    protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }
    
}
