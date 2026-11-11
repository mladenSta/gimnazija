<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value settings entered in the admin (values are stored encrypted).
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Log of "Objavi na FB / IG / LinkedIn" attempts.
        Schema::create('social_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('network', 20);
            $table->boolean('success');
            $table->string('external_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->text('message');
            $table->timestamps();

            $table->index(['post_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_shares');
        Schema::dropIfExists('settings');
    }
};
