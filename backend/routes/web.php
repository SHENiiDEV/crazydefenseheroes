<?php

use App\Http\Controllers\LegalDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/legal/terms', [LegalDocumentController::class, 'page'])->defaults('document', 'terms')->name('legal.terms');
Route::get('/legal/privacy', [LegalDocumentController::class, 'page'])->defaults('document', 'privacy')->name('legal.privacy');
Route::get('/legal/refunds', [LegalDocumentController::class, 'page'])->defaults('document', 'refunds')->name('legal.refunds');
Route::get('/legal/security', [LegalDocumentController::class, 'page'])->defaults('document', 'security')->name('legal.security');
