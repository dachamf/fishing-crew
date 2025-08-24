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
        Schema::create('fishing_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title')->nullable();
            $t->decimal('latitude', 10, 6)->nullable();
            $t->decimal('longitude', 10, 6)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->enum('status', ['open','closed'])->default('open');
            $t->unsignedSmallInteger('season_year')->nullable();
            $t->timestamps();

            $t->index(['user_id','status']);
            $t->index(['group_id','status']);
            $t->index(['season_year']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fishing_sessions');
    }
};
