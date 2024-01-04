<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\SendPdfController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SyllabusController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/send-pdf/{category}/{file_id}', [SendPdfController::class, 'sendPdf']);

Route::get('/save-syllabus/{syllabus_id}', [SyllabusController::class, 'saveSyllabus']);
Route::get('/test', [SyllabusController::class, 'test']);


