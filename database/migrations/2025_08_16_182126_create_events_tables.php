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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 9, 6)->nullable('nullable|numeric|between:-90,90');   // [-90, 90]
            $table->decimal('longitude', 9, 6)->nullable('nullable|numeric|between:-180,180');  // [-180, 180]
            $table->timestamp('start_at');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'postponed', 'done'])->default('scheduled');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE events ADD CONSTRAINT chk_lat CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))');
        DB::statement('ALTER TABLE events ADD CONSTRAINT chk_lng CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))');

        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('rsvp', ['yes', 'no', 'undecided'])->default('undecided');
            $table->string('reason')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_attendees');
    }
};
