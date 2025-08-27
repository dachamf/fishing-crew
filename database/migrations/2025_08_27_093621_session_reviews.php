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
        Schema::create('session_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_id')->constrained('fishing_sessions')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['pending','approved','rejected'])->default('pending');
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->unique(['session_id','reviewer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_reviews');
    }
};
