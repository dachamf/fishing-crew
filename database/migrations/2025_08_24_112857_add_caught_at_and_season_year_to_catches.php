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
        Schema::table('catches', function (Blueprint $table) {
            $table->dateTime('caught_at')->nullable()->after('event_id');
            $table->unsignedSmallInteger('season_year')->nullable()->after('caught_at');

            // Indexi za brže filtere
            $table->index('caught_at');
            $table->index('season_year');
            $table->index('status');
        });

        DB::statement("
        UPDATE catches c
        LEFT JOIN events e ON e.id = c.event_id
        LEFT JOIN `groups` g ON g.id = c.group_id
        SET
            c.caught_at = COALESCE(e.start_at, c.created_at),
            c.season_year = COALESCE(g.season_year, YEAR(COALESCE(e.start_at, c.created_at)))
        WHERE c.caught_at IS NULL OR c.season_year IS NULL
    ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catches', function (Blueprint $table) {
            $table->dropIndex(['caught_at']);
            $table->dropIndex(['season_year']);
            $table->dropIndex(['status']);
            $table->dropColumn(['caught_at','season_year']);
        });
    }
};
