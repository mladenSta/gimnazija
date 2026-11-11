<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two optional YouTube links per post/page, shown below the text.
     */
    public function up(): void
    {
        foreach (['posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('video_url_1', 255)->nullable()->after('content');
                $t->string('video_url_2', 255)->nullable()->after('video_url_1');
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['video_url_1', 'video_url_2']);
            });
        }
    }
};
