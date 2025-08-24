<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        // MySQL ENUM alter
        DB::statement("ALTER TABLE catch_confirmations
      MODIFY COLUMN status ENUM('pending','approved','rejected','changes_requested') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE catch_confirmations
      ADD COLUMN suggested_payload JSON NULL AFTER status");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        DB::statement("ALTER TABLE catch_confirmations DROP COLUMN suggested_payload");
        DB::statement("ALTER TABLE catch_confirmations
      MODIFY COLUMN status ENUM('approved','rejected') NOT NULL DEFAULT 'approved'");
    }
};
