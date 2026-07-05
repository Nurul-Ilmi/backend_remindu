<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Seed App Settings (Essential for system)
        \App\Models\AppSetting::create([
            'key' => 'fonnte_token',
            'value' => 'YOUR_FONNTE_TOKEN_HERE',
            'type' => 'string',
            'description' => 'Fonnte API Token untuk notifikasi WA',
        ]);
        \App\Models\AppSetting::create([
            'key' => 'zona_merah_hours',
            'value' => '24',
            'type' => 'integer',
            'description' => 'Batas waktu Zona Merah (dalam jam)',
        ]);
        \App\Models\AppSetting::create([
            'key' => 'xp_reward_base',
            'value' => '50',
            'type' => 'integer',
            'description' => 'Base XP reward per task',
        ]);
        \App\Models\AppSetting::create([
            'key' => 'gemini_api_key',
            'value' => 'YOUR_GEMINI_API_KEY',
            'type' => 'string',
            'description' => 'Google Gemini API Key untuk integrasi AI Webhook',
        ]);

        // 1. Create Super Admin (Essential for dashboard access)
        User::create([
            'name'         => 'Super Admin',
            'email'        => 'admin@remindu.com',
            'password'     => bcrypt('admin123'),
            'role'         => 'admin',
        ]);
    }
}
