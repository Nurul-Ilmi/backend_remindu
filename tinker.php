$request = Illuminate\Http\Request::create('/api/tasks', 'GET');
$user = App\Models\User::find(2);
app('auth')->guard('sanctum')->setUser($user);
$response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
echo $response->getContent();
