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
        Schema::create('catch_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('catch_id')->constrained('catches')->cascadeOnDelete();
            $t->string('path');          // npr. storage path
            $t->string('disk')->default('public');
            $t->unsignedTinyInteger('ord')->default(1);
            $t->timestamps();
            $t->unique(['catch_id','ord']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catch_photos');
    }
};
