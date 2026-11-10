<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('type', 20)->default('standard'); // slider | standard
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('focal_x')->default(50); // 0–100 %
            $table->unsignedTinyInteger('focal_y')->default(50);
            // Slider-only fields
            $table->string('badge')->nullable();
            $table->string('title')->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('link_label')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->timestamps();

            $table->index(['gallery_id', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('gallery_item_tag', function (Blueprint $table) {
            $table->foreignId('gallery_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['gallery_item_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_item_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('galleries');
    }
};
