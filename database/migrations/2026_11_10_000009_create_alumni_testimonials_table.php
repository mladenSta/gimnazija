<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('generation')->nullable(); // graduation year
            $table->string('profession')->nullable();
            $table->text('quote');
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_testimonials');
    }
};
