<?php
$data = ['sender' => '081918487102', 'message' => 'tugas gweh udah selesai bro'];
$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];
$context = stream_context_create($options);
$result = file_get_contents('http://localhost:8000/api/webhook/fonnte', false, $context);
echo "Result:\n" . $result . "\n";
