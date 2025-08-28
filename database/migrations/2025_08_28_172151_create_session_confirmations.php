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
        Schema::create('session_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('fishing_sessions')->cascadeOnDelete();
            $table->foreignId('nominee_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending','approved','rejected'])->default('pending')->index();
            $table->timestamp('decided_at')->nullable();
            $table->string('token', 64)->nullable()->unique(); // za email akcije bez prijave
            $table->timestamps();

            $table->unique(['session_id','nominee_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_confirmations');
    }
};
