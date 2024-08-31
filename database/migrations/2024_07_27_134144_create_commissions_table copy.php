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
        Schema::table('commissions', function (Blueprint $table) {
            // Add import_id column
            if (!Schema::hasColumn('commissions', 'import_id')) {
                $table->foreignId('import_id')->nullable()->constrained()->onDelete('cascade')->after('website_id');
            }

            // Add customer_language column
            if (!Schema::hasColumn('commissions', 'status')) {
                $table->string('status')->nullable();
            }

            // Add customer_language column
            if (!Schema::hasColumn('commissions', 'customer_language')) {
                $table->string('customer_language')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
