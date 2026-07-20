<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Users count: " . \App\Models\User::count() . "\n";
echo "Tasks count: " . \App\Models\Task::count() . "\n";
echo "Groups count: " . \App\Models\Group::count() . "\n";
echo "--- Users ---\n";
foreach (\App\Models\User::all() as $user) {
    echo "User {$user->id}: {$user->name} ({$user->email}) - google_id: {$user->google_id} - tasks: {$user->tasks()->count()}\n";
}
