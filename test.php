<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/tasks', 'GET');
$user = App\Models\User::find(2);
$app['auth']->guard('sanctum')->setUser($user);
$response = $kernel->handle($request);
echo $response->getContent();
