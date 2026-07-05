<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Task classification system:
     *   Output Type : Praktik | Teori | Komunikasi | Studi | Org
     *   Load Type   : Micro-Task | Milestone | Major Project
     *   Involvement : Pribadi | Kelompok
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Optional group assignment (null = personal task)
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('groups')
                ->onDelete('set null');

            // Basic info
            $table->string('title');
            $table->text('description')->nullable();

            // Classification
            $table->enum('output_type', ['Praktik', 'Teori', 'Komunikasi', 'Studi', 'Org'])
                ->default('Studi');
            $table->enum('load_type', ['Micro-Task', 'Milestone', 'Major Project'])
                ->default('Micro-Task');
            $table->enum('involvement_type', ['Pribadi', 'Kelompok'])
                ->default('Pribadi');

            // Progress tracking
            $table->enum('status', ['todo', 'in_progress', 'done', 'overdue'])
                ->default('todo');

            // Kanban column position (for shared board)
            $table->enum('kanban_column', ['todo', 'in_progress', 'done'])
                ->default('todo');
            $table->unsignedSmallInteger('kanban_order')->default(0);

            // Deadline & scheduling
            $table->dateTime('deadline');
            $table->boolean('is_zona_merah')->default(false)
                ->comment('Computed flag: deadline within critical threshold');

            // Gamification
            $table->unsignedSmallInteger('xp_reward')->default(10)
                ->comment('XP awarded on completion');

            // WhatsApp notification settings
            $table->boolean('wa_notif_enabled')->default(true);
            $table->dateTime('last_wa_notif_sent_at')->nullable();

            // Completion tracking
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'deadline']);
            $table->index(['group_id', 'kanban_column', 'kanban_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
