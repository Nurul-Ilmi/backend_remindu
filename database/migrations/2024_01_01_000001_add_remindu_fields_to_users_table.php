<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Extends the default Laravel users table with remind.u specific fields.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // WhatsApp integration
            $table->string('phone_wa', 20)->nullable()->unique()->after('email')
                ->comment('WhatsApp number for Fonnte bot integration');
            $table->boolean('wa_connected')->default(false)->after('phone_wa')
                ->comment('Whether WA bot is synced and active');

            // Gamification
            $table->unsignedInteger('xp_points')->default(0)->after('wa_connected');
            $table->unsignedTinyInteger('level')->default(1)->after('xp_points');

            // Accountability Mode
            $table->boolean('extreme_mode')->default(false)->after('level')
                ->comment('Extreme Accountability Mode — enables aggressive cron spamming');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_wa', 'wa_connected', 'xp_points', 'level', 'extreme_mode']);
        });
    }
};
