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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique()->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default('');
            $table->string('external_id')->nullable()->unique();
            $table->unsignedInteger('allocation_id');
            $table->unsignedInteger('node');
            $table->boolean('start_on_completion')->default(true);
            $table->string('docker_image');

            $table->unsignedInteger('user');
            $table->unsignedInteger('egg');

            $table->json('limits');
            $table->json('feature_limits');
            $table->json('egg_variables');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
