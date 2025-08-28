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
        Schema::table('fishing_sessions', function (Blueprint $table) {
            Schema::table('fishing_sessions', function (Blueprint $table) {
                $table->timestamp('finalized_at')->nullable()->after('updated_at');
                $table->enum('final_result', ['approved','rejected'])->nullable()->after('finalized_at')->index();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fishing_sessions', function (Blueprint $table) {
            Schema::table('fishing_sessions', function (Blueprint $table) {
                $table->dropColumn(['finalized_at','final_result']);
            });
        });
    }
};
