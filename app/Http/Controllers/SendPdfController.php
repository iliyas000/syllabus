<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\DB;

class SendPdfController extends Controller
{
    public function SendPdf($category, $file_id)
    {
        if($category == 'syllabus'){
            $file_url = DB::table('document_to_log')
                ->where('file_name', 'syllabus_'.$file_id)
                ->orderByDesc('id')
                ->select('file_url')
                ->first();
        }
        $b64Doc = base64_encode(file_get_contents($file_url->file_url));
        return $b64Doc;
    }


}
