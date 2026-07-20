<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$task = App\Models\Task::create([
    'user_id' => 2,
    'title' => 'Test Task',
    'output_type' => 'Tugas',
    'load_type' => 'Ringan',
    'involvement_type' => 'Pribadi',
    'deadline' => now()->addDay()
]);

var_dump($task->wa_notif_enabled);
