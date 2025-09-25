<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('catch_photos', function (Blueprint $t) {
            if (!Schema::hasColumn('catch_photos','width')) $t->integer('width')->nullable()->after('path');
            if (!Schema::hasColumn('catch_photos','height')) $t->integer('height')->nullable()->after('width');
            if (!Schema::hasColumn('catch_photos','format')) $t->string('format',10)->nullable()->after('height');
            if (!Schema::hasColumn('catch_photos','ord')) $t->integer('ord')->nullable()->after('format');
            if (!Schema::hasColumn('catch_photos','exif_json')) $t->json('exif_json')->nullable()->after('ord');
            if (!Schema::hasColumn('catch_photos','taken_at')) $t->dateTime('taken_at')->nullable()->after('exif_json');
            if (!Schema::hasColumn('catch_photos','gps_lat')) $t->decimal('gps_lat',10,7)->nullable()->after('taken_at');
            if (!Schema::hasColumn('catch_photos','gps_lng')) $t->decimal('gps_lng',10,7)->nullable()->after('gps_lat');
            if (!Schema::hasColumn('catch_photos','thumb_url'))  $t->string('thumb_url')->nullable()->after('gps_lng');
            if (!Schema::hasColumn('catch_photos','medium_url')) $t->string('medium_url')->nullable()->after('thumb_url');
            if (!Schema::hasColumn('catch_photos','webp_url'))   $t->string('webp_url')->nullable()->after('medium_url');
        });
    }
    public function down(): void {
        Schema::table('catch_photos', function (Blueprint $t) {
            $cols = ['width','height','format','ord','exif_json','taken_at','gps_lat','gps_lng','thumb_url','medium_url','webp_url'];
            foreach ($cols as $c) if (Schema::hasColumn('catch_photos',$c)) $t->dropColumn($c);
        });
    }
};
