<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('display_name')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('location', 120)->nullable();
            $table->string('favorite_species', 120)->nullable();
            $table->string('gear', 255)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();   // npr. s3 key
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profiles');
    }
};
