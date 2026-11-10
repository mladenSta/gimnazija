<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('short_description', 500)->nullable();
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('location')->nullable();
            $table->string('label', 50)->nullable(); // e.g. "Takmičenje"
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
