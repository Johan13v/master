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
        Schema::table('comments', function (Blueprint $table) {
            $table->string('post_id')->nullable(); // Add a unique reference for WordPress comment ID
            $table->string('target_post_id')->nullable(); // Add a unique reference for WordPress comment ID

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('post_id');
            $table->dropColumn('target_post_id');
        });
    }
};