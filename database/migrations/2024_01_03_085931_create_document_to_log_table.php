<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentToLogTable extends Migration
{
    public function up()
    {
        Schema::create('document_to_log', function (Blueprint $table) {
            $table->id();
            $table->string('file_url');
            $table->string('file_name');
            $table->timestamps();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_to_log');
    }
}
