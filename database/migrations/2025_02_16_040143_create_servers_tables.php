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
            $table->integer('sort_order')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default('');
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('allocation_id');
            $table->unsignedBigInteger('node');
            $table->boolean('start_on_completion')->default(true);
            $table->string('docker_image');

            $table->unsignedBigInteger('user');
            $table->unsignedBigInteger('egg');

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
