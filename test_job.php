$task = App\Models\Task::find(2);
try {
    \App\Jobs\SendTaskCreatedNotification::dispatch($task);
    echo "Success";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
} catch (\Error $e) {
    echo "Fatal Error: " . $e->getMessage();
}
