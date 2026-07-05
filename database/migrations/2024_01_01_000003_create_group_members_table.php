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
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Role within the group
            $table->enum('role', ['ketua', 'anggota'])->default('anggota')
                ->comment('ketua = group leader with admin privileges, anggota = regular member');

            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // A user can only belong to a group once
            $table->unique(['group_id', 'user_id']);

            $table->index(['group_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
