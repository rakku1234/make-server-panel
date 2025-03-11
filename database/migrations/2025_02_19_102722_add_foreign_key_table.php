<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->foreign('node_id')
                  ->references('node_id')->on('nodes');
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->foreign('node')
                  ->references('node_id')->on('nodes');
            $table->foreign('egg')
                  ->references('egg_id')->on('eggs');
            $table->foreign('user')
                  ->references('id')->on('users');
            $table->foreign('allocation_id')
                  ->references('id')->on('allocations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropForeign(['node_id']);
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['node']);
            $table->dropForeign(['egg']);
            $table->dropForeign(['user']);
            $table->dropForeign(['allocation_id']);
        });
    }
};
