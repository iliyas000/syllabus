<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    protected $fillable = [
        'name',
        'directory_url',
    ];

    // Если у вас есть связь с документами, вы можете добавить её здесь
    public function documents()
    {
        return $this->hasMany(DocumentToLog::class);
    }
}
