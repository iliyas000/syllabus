<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PDFController extends Controller
{
    public function downloadPDF($filename)
    {
        $filePath = '/documents/syllabus/' . $filename;
        if (file_exists(storage_path($filePath))) {
            return response()->file(storage_path($filePath));
        } else {
            abort(404, 'File not found');
        }
    }
}
