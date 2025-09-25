<?php

// database/migrations/2025_09_25_130000_create_session_confirmations.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('session_confirmations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('session_id')->index();
            $t->unsignedBigInteger('user_id')->index(); // nominee
            $t->string('status', 20)->default('pending'); // pending|approved|rejected
            $t->timestamp('decided_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();

            $t->unique(['session_id','user_id']);
            // FK po želji:
            // $t->foreign('session_id')->references('id')->on('fishing_sessions')->cascadeOnDelete();
            // $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('session_confirmations');
    }
};
