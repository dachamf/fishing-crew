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
        Schema::table('catch_confirmations', function (Blueprint $table) {
            $table->index(['confirmed_by', 'status'], 'cc_confirmed_by_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catch_confirmations', function (Blueprint $table) {
            $table->dropIndex('cc_confirmed_by_status_idx');
        });
    }
};
