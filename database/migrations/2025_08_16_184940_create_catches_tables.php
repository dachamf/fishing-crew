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
        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // npr. "som", "smudj"
            $table->string('name_sr');                 // “Smuđ”
            $table->string('name_latin')->nullable();  // “Sander lucioperca”
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Moved here so that catches.session_id can reference it
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

        Schema::create('catches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('fishing_sessions')->nullOnDelete();
            $table->foreignId('species_id')->nullable()->constrained('species')->nullOnDelete();
            $table->dateTime('caught_at')->nullable();
            $table->unsignedSmallInteger('season_year')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->decimal('total_weight_kg', 6, 3)->nullable();
            $table->decimal('biggest_single_kg', 6, 3)->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            // Indexes for faster filters
            $table->index('caught_at');
            $table->index('season_year');
            $table->index('status');
            $table->index(['session_id','species_id']);
        });

        Schema::create('catch_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catch_id')->constrained('catches')->cascadeOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending','approved','rejected','changes_requested'])->default('pending');
            $table->json('suggested_payload')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['catch_id', 'confirmed_by']);
            $table->index(['confirmed_by', 'status'], 'cc_confirmed_by_status_idx');
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('season_year');
            $table->unsignedInteger('activity_points')->default(0);
            $table->unsignedInteger('weight_points')->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->decimal('biggest_single_kg', 6, 3)->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'user_id', 'season_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse dependency order
        Schema::dropIfExists('catch_confirmations');
        Schema::dropIfExists('catches');
        Schema::dropIfExists('fishing_sessions');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('species');
    }
};
