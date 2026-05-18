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
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedInteger('total_rows')->default(0)->after('unmatched_count');
            $table->unsignedInteger('imported_count')->default(0)->after('total_rows');
            $table->unsignedInteger('duplicate_count')->default(0)->after('imported_count');
            $table->unsignedInteger('revoked_count')->default(0)->after('duplicate_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn([
                'total_rows',
                'imported_count',
                'duplicate_count',
                'revoked_count',
            ]);
        });
    }
};
