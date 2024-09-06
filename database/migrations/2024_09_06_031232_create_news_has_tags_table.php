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
        Schema::create('news_has_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->references('id')->on('news')->cascadeOnDelete();
            $table->foreignId('tag_id')->references('id')->on('news_tags')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_has_tags');
    }
};
