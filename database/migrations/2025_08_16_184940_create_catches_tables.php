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
        Schema::create('catches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('species')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->decimal('total_weight_kg', 6, 3)->nullable();
            $table->decimal('biggest_single_kg', 6, 3)->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->timestamps();
        });
        Schema::create('catch_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catch_id')->constrained('catches')->cascadeOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['approved','rejected'])->default('approved');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['catch_id','confirmed_by']);
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
            $table->unique(['group_id','user_id','season_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('catch_confirmations');
        Schema::dropIfExists('catches');
    }
};
