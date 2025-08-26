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
            // MySQL ENUM alter (Schema::change() ne radi pouzdano za enum)
            DB::statement("
            ALTER TABLE catch_confirmations
            MODIFY COLUMN status
            ENUM('pending','approved','rejected','changes_requested')
            NOT NULL DEFAULT 'pending'
        ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catch_confirmations', function (Blueprint $table) {
            DB::statement("
            ALTER TABLE catch_confirmations
            MODIFY COLUMN status
            ENUM('approved','rejected')
            NOT NULL DEFAULT 'approved'
        ");
        });
    }
};
