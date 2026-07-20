<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$token  = config('services.fonnte.token');
$target = $argv[1] ?? '082196683781'; // default ke nomor device Fonnte

echo "Fonnte Token: $token\n";
echo "Target: $target\n\n";

$response = Http::withHeaders([
    'Authorization' => $token,
])->post('https://api.fonnte.com/send', [
    'target'  => $target,
    'message' => "🎓 *Halo dari remind.u!* 🎓\n\nBot WhatsApp kamu sudah aktif!\n\nCoba balas perintah berikut:\n• *STATUS* → lihat tugas aktif\n• *SELESAI [no]* → tandai tugas selesai\n• *TUNDA [no] [menit]* → tunda pengingat",
]);

echo "Status: " . $response->status() . "\n";
echo "Response: " . $response->body() . "\n";
