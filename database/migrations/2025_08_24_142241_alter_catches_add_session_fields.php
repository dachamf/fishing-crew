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
        Schema::table('catches', function (Blueprint $t) {
            $t->foreignId('session_id')->nullable()->after('event_id')
                ->constrained('fishing_sessions')->nullOnDelete();

            $t->index(['session_id','species_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catches', function (Blueprint $t) {
            $t->dropConstrainedForeignId('session_id');
            $t->dropColumn(['caught_at','season_year']);
        });
    }
};
