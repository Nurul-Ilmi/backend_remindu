<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tracks all XP earned events for audit trail and leaderboard calculations.
     */
    public function up(): void
    {
        Schema::create('xp_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Optional: which task triggered this XP event
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->onDelete('set null');

            // XP amount (positive = earned, negative = penalty)
            $table->integer('xp_amount')
                ->comment('Positive for earned XP, negative for penalties');

            // Human-readable reason for the XP event
            $table->string('reason')
                ->comment('E.g.: "Task Completed", "Streak Bonus", "Late Penalty"');

            // Snapshot of user level at time of event
            $table->unsignedTinyInteger('level_at_event')->default(1);

            $table->timestamps();

            // Indexes for leaderboard and user history queries
            $table->index(['user_id', 'created_at']);
            $table->index(['task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xp_logs');
    }
};
