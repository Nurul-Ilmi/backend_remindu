<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$task = App\Models\Task::find(5);
if($task) { 
    \App\Jobs\SendTaskCreatedNotification::dispatchSync($task); 
    echo "Dispatched!"; 
}
