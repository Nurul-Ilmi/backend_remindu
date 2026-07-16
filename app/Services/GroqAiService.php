<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAiService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private string $model = 'llama-3.3-70b-versatile';

    public function __construct()
    {
        // For development, assuming GROQ_API_KEY is in .env or settings
        $this->apiKey = env('GROQ_API_KEY', '');
    }

    public function analyzeWebhookMessage(string $userMessage, $activeTasks, $userName = 'Bro')
    {
        if (empty($this->apiKey)) {
            Log::error('Groq API Key is missing or invalid.');
            return null;
        }

        $tasksContext = [];
        foreach ($activeTasks as $task) {
            $tasksContext[] = [
                'id' => $task->id,
                'title' => $task->title,
                'deadline' => $task->deadline->format('Y-m-d H:i:s'),
                'is_zona_merah' => $task->is_zona_merah,
                'type' => $task->group_id ? 'Tugas Kelompok (' . $task->group->name . ')' : 'Tugas Pribadi'
            ];
        }

        $systemPrompt = "Kamu adalah asisten pengingat tugas (chatbot WhatsApp) bernama 'remind.u Bot'.
Gaya bahasamu harus asik, gacor, santai, relate ala Gen-Z Indonesia (pakai kata lu/gw, bro, cuy, sikat, kelar, dsb).
Lawan bicaramu bernama: {$userName}.

Berikut adalah daftar tugas aktif user saat ini:
" . json_encode($tasksContext, JSON_PRETTY_PRINT) . "

TUGAS UTAMAMU:
Analisis pesan user dan balas dengan format JSON murni (tanpa markdown, tanpa bungkus ```json).
Kamu harus merespons dalam struktur JSON berikut:
{
  \"intent\": \"<mark_done | snooze | check_status | chit_chat>\",
  \"task_id\": <ID tugas jika intent mark_done atau snooze. null jika tidak ada/tidak yakin>,
  \"reply\": \"<Balasan chat asik & gacor yang akan dikirim ke WhatsApp user>\"
}

Panduan Intent & Reply:
1. 'mark_done': Jika user ingin menyelesaikan tugas (misal: 'Kalkulus udah kelar bro', 'Coret nomor 1'). Pastikan task_id cocok dengan list. Balasan harus apresiatif (\"Mantap bro! Tugas 'Kalkulus' udah gw coret.\"). Jika itu tugas kelompok, mention kalau temen sekelompoknya bisa lega.
2. 'snooze': Jika user minta tunda pengingat tugas (misal: 'Kalkulus ntar aja deh'). Balasan: (\"Oke bro, gw kalem dulu buat tugas itu\").
3. 'check_status': Jika user nanya sisa tugas (misal: 'Utang gw apa aja njir'). Rangkum list tugasnya di 'reply' dengan gaya asik.
4. 'chit_chat': Obrolan di luar tugas (misal: 'Pusing banget gw', 'Halo', dll). Kasih support mental ala tongkrongan. Atau bantu jawab pertanyaan bot.

ATURAN KRITIS: 
- HANYA KELUARKAN JSON MURNI. Jangan ada teks apapun sebelum atau sesudah '{' dan '}'.";

        try {
            $response = Http::withToken($this->apiKey)->post($this->baseUrl, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'temperature' => 0.2, // Low temp for more deterministic JSON
                'response_format' => ['type' => 'json_object'], // Enforce JSON output with Groq
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                if ($content) {
                    return json_decode($content, true);
                }
            } else {
                Log::error('Groq API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Groq Request Exception: ' . $e->getMessage());
        }

        return null;
    }
}
