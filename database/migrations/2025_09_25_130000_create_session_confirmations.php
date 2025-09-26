<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Ako tabela već postoji (što je tvoj slučaj) — preskoči kreiranje
        if (Schema::hasTable('session_confirmations')) {
            return;
        }

        Schema::create('session_confirmations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_id')->constrained('fishing_sessions')->cascadeOnDelete();
            $t->foreignId('nominee_user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['pending','approved','rejected'])->default('pending')->index();
            $t->timestamp('decided_at')->nullable();
            $t->string('token', 64)->nullable()->index();
            $t->timestamps();

            $t->unique(['session_id','nominee_user_id'], 'sc_unique_session_nominee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_confirmations');
    }
};
