<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images attached to posts/pages: the bottom gallery ("gallery")
     * and images used inside the content HTML ("inline").
     */
    public function up(): void
    {
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->morphs('mediable');
            $table->string('role', 20)->default('gallery');
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['media_id', 'mediable_type', 'mediable_id', 'role'], 'mediables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
