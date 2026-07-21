<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $setting = AppSetting::where('key', 'gemini_api_key')->first();
        $this->apiKey = $setting ? $setting->value : '';
    }

    public function analyzeWebhookMessage(string $userMessage, $activeTasks)
    {
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_GEMINI_API_KEY') {
            Log::error('Gemini API Key is missing or invalid.');
            return null; // Fallback to basic regex or error handling
        }

        $tasksContext = [];
        foreach ($activeTasks as $task) {
            $tasksContext[] = [
                'id' => $task->id,
                'title' => $task->title,
                'deadline' => $task->deadline->format('Y-m-d H:i:s'),
                'is_zona_merah' => $task->is_zona_merah,
            ];
        }

        $systemPrompt = "Kamu adalah asisten pengingat tugas (chatbot WhatsApp) bernama 'remind.u Bot'.
Gaya bahasamu harus asik, gacor, santai, relate ala Gen-Z Indonesia (pakai kata lu/gw, bro, cuy, sikat, kelar, dsb).
Lawan bicaramu bernama: User.

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
4. 'chit_chat': Obrolan di luar tugas (misal: 'Pusing banget gw'). Kasih support mental ala tongkrongan.

ATURAN KRITIS: 
- HANYA KELUARKAN JSON MURNI. Jangan ada teks apapun sebelum atau sesudah '{' dan '}'.";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nPesan pengguna: \"{$userMessage}\""]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $jsonBody = $response->json();
                if (isset($jsonBody['candidates'][0]['content']['parts'][0]['text'])) {
                    $aiText = trim($jsonBody['candidates'][0]['content']['parts'][0]['text']);
                    
                    // Bersihkan jika gemini masih membalas dengan markdown block
                    $aiText = preg_replace('/```json/i', '', $aiText);
                    $aiText = preg_replace('/```/', '', $aiText);
                    $aiText = trim($aiText);

                    return json_decode($aiText, true);
                }
            } else {
                Log::error('Gemini API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Gemini Request Exception: ' . $e->getMessage());
        }

        return null;
    }
}
