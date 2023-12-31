<?php

use App\Http\Controllers\PDFController;
use App\Http\Controllers\SendPdfController;
use App\Http\Controllers\SyllabusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/send-pdf/{category}/{file_id}', [SendPdfController::class, 'sendPdf']);

Route::get('/download-pdf/{filePath}', [PDFController::class, 'downloadPDF']);

Route::post('/save-syllabus', [SyllabusController::class, 'saveSyllabus']);
Route::get('/test', [SyllabusController::class, 'test']);
