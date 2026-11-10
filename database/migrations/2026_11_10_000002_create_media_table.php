<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32)->default('uploads');
            $table->string('path', 500); // relative to disk, e.g. 2026/11/dan-otvorenih-vrata.webp
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->string('alt')->nullable();
            $table->string('caption')->nullable();
            $table->json('variants')->nullable(); // {"thumb": {"path": "...", "width": 400, "height": 225}, ...}
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
