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
        Schema::create('eggs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('egg_id')->unique();
            $table->string('uuid')->unique();
            $table->string('name')->default('');
            $table->text('description')->nullable();
            $table->string('egg_url')->nullable();
            $table->json('docker_images')->nullable();
            $table->json('egg_variables')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->boolean('public')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eggs');
    }
};
