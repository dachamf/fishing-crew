<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('catch_photos', function (Blueprint $t) {
            if (!Schema::hasColumn('catch_photos', 'format')) {
                $t->string('format', 12)->nullable()->after('ord');
            }
            if (!Schema::hasColumn('catch_photos', 'width')) {
                $t->unsignedInteger('width')->nullable()->after('format');
            }
            if (!Schema::hasColumn('catch_photos', 'height')) {
                $t->unsignedInteger('height')->nullable()->after('width');
            }
            if (!Schema::hasColumn('catch_photos', 'taken_at')) {
                $t->timestamp('taken_at')->nullable()->after('height');
            }
            if (!Schema::hasColumn('catch_photos', 'gps_lat')) {
                $t->decimal('gps_lat', 10, 7)->nullable()->after('taken_at');
            }
            if (!Schema::hasColumn('catch_photos', 'gps_lng')) {
                $t->decimal('gps_lng', 10, 7)->nullable()->after('gps_lat');
            }

            // Ako postoji exif_json a nema exif, može se preimenovati — zahteva doctrine/dbal.
            // Ako nemaš DBAL, preskoči rename i samo dodaj exif ako ne postoji.
            if (Schema::hasColumn('catch_photos', 'exif_json') && !Schema::hasColumn('catch_photos', 'exif')) {
                try {
                    $t->renameColumn('exif_json', 'exif'); // radi samo ako je instaliran doctrine/dbal
                } catch (\Throwable $e) {
                    // fallback: ako rename ne uspe i kolona 'exif' ne postoji, dodaćemo je ispod
                }
            }

            if (!Schema::hasColumn('catch_photos', 'exif')) {
                $t->json('exif')->nullable()->after('gps_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catch_photos', function (Blueprint $t) {
            if (Schema::hasColumn('catch_photos', 'exif')) {
                $t->dropColumn('exif');
            }
            if (Schema::hasColumn('catch_photos', 'gps_lng')) {
                $t->dropColumn('gps_lng');
            }
            if (Schema::hasColumn('catch_photos', 'gps_lat')) {
                $t->dropColumn('gps_lat');
            }
            if (Schema::hasColumn('catch_photos', 'taken_at')) {
                $t->dropColumn('taken_at');
            }
            if (Schema::hasColumn('catch_photos', 'height')) {
                $t->dropColumn('height');
            }
            if (Schema::hasColumn('catch_photos', 'width')) {
                $t->dropColumn('width');
            }
            if (Schema::hasColumn('catch_photos', 'format')) {
                $t->dropColumn('format');
            }
            // ne diramo exif_json nazad
        });
    }
};
