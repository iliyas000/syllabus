<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentToLog extends Model
{
    protected $table = 'document_to_log';

    protected $fillable = [
        'file_url',
        'file_name',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
