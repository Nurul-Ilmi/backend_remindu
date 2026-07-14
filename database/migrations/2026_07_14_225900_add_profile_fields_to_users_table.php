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
        Schema::table('users', function (Blueprint $table) {
            $table->string('study_program', 150)->nullable()->after('extreme_mode');
            $table->string('batch_year', 10)->nullable()->after('study_program');
            $table->string('university', 150)->nullable()->after('batch_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['study_program', 'batch_year', 'university']);
        });
    }
};
