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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // 6-character alphanumeric invite code (unique)
            $table->string('invite_code', 10)->unique()
                ->comment('Short invite code for members to join the group');

            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('User who created the group (Ketua by default)');

            $table->timestamps();
            $table->softDeletes();

            $table->index('invite_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
