<?php
// database/migrations/2025_09_25_120000_tune_catch_photos_schema.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('catch_photos', function (Blueprint $t) {
            // Drop redundant URL columns
            foreach (['thumb_url','medium_url','webp_url'] as $c) {
                if (Schema::hasColumn('catch_photos', $c)) $t->dropColumn($c);
            }

            // Rename exif_json -> exif (opciono)
            if (Schema::hasColumn('catch_photos','exif_json') && !Schema::hasColumn('catch_photos','exif')) {
                $t->renameColumn('exif_json', 'exif');
            }

            // Indeksi
            if (Schema::hasColumn('catch_photos','taken_at')) {
                $t->index('taken_at');
            }
            if (Schema::hasColumn('catch_photos','session_id') && Schema::hasColumn('catch_photos','taken_at')) {
                $t->index(['session_id','taken_at']);
            }
        });
    }

    public function down(): void {
        Schema::table('catch_photos', function (Blueprint $t) {
            // Recreate URL columns if baš treba rollback
            if (!Schema::hasColumn('catch_photos','thumb_url'))  $t->string('thumb_url')->nullable();
            if (!Schema::hasColumn('catch_photos','medium_url')) $t->string('medium_url')->nullable();
            if (!Schema::hasColumn('catch_photos','webp_url'))   $t->string('webp_url')->nullable();

            // exif -> exif_json
            if (Schema::hasColumn('catch_photos','exif') && !Schema::hasColumn('catch_photos','exif_json')) {
                $t->renameColumn('exif', 'exif_json');
            }

            $t->dropIndex(['taken_at']);
            $t->dropIndex(['session_id','taken_at']);
        });
    }
};
