<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\DB;

class SendPdfController extends Controller
{
    public function SendPdf($category, $file_id)
    {
        if ($category == 'syllabus') {
            $file_record = DB::table('document_to_log')
                ->where('file_name', 'syllabus_' . $file_id)
                ->orderByDesc('id')
                ->select('file_url')
                ->first();
            if(!$file_record){
                return null;
            }
            $file_url = $file_record->file_url;
        }

        $b64Doc = base64_encode(file_get_contents($file_url));
        $file_url = 'http://192.168.12.52:8000/api/download-pdf/syllabus_' . $file_id . '.pdf';

        return [
            'base64' => $b64Doc,
            'file_url' => $file_url
        ];
    }

}
