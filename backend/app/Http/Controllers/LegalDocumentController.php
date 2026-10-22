<?php

namespace App\Http\Controllers;

use App\Services\LegalDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class LegalDocumentController extends Controller
{
    public function show(string $document, LegalDocumentService $documents): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $documents->document($document)]);
    }

    public function page(string $document, LegalDocumentService $documents): View
    {
        return view('legal.document', ['document' => $documents->document($document)]);
    }
}
