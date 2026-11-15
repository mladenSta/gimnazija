<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per day: the whole visit history.
        Schema::create('daily_visits', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('page_views')->default(0);
        });

        // Today's anonymous visitor codes (hash of IP + browser + a daily salt),
        // only to count each visitor once per day. Older days are deleted.
        Schema::create('visit_hashes', function (Blueprint $table) {
            $table->date('date');
            $table->char('hash', 64);
            $table->primary(['date', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_hashes');
        Schema::dropIfExists('daily_visits');
    }
};
