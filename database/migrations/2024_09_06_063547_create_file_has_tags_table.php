<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('file_has_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->foreignId('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_has_tags');
    }
};
