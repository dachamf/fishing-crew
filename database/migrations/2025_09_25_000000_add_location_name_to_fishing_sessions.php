<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('fishing_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('fishing_sessions', 'location_name')) {
                $table->string('location_name', 160)->nullable()->after('longitude');
            }
        });
    }
    public function down(): void {
        Schema::table('fishing_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('fishing_sessions', 'location_name')) {
                $table->dropColumn('location_name');
            }
        });
    }
};
