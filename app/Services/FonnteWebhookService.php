<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FonnteWebhookService
{
    protected GroqAiService $groqAiService;
    protected GeminiAiService $geminiAiService;

    public function __construct(GroqAiService $groqAiService, GeminiAiService $geminiAiService)
    {
        $this->groqAiService = $groqAiService;
        $this->geminiAiService = $geminiAiService;
    }

    public function handleIncomingMessage(string $sender, string $message): string
    {
        Log::info("Fonnte incoming: {$sender} → {$message}");

        $normalizedPhone = $this->normalizePhone($sender);
        $user = User::where('phone_wa', 'LIKE', "%{$normalizedPhone}%")->first();

        if (!$user) {
            return 'Bro, nomor ini belom terdaftar di remind.u nih. Daftar dulu di https://remindu.app ya!';
        }

        // ── Pull Active Tasks (Personal & Group) ───────────────────
        $activeTasks = $user->tasks()
            ->where('status', '!=', 'done')
            ->orderBy('deadline')
            ->take(10) // Give AI up to 10 context tasks
            ->get();
        
        // ── 100% AI Natural Language Processing ────────────────────
        $aiResponse = $this->groqAiService->analyzeWebhookMessage($message, $activeTasks, $user->name);

        // Fallback to Gemini if Groq fails
        if (!$aiResponse) {
            $aiResponse = $this->geminiAiService->analyzeWebhookMessage($message, $activeTasks);
        }

        if ($aiResponse && isset($aiResponse['intent'])) {
            $intent = $aiResponse['intent'];
            $reply = $aiResponse['reply'] ?? 'Siap bro!';

            // ── Execute DB Actions based on AI Intent ──────────────
            if ($intent === 'mark_done' && !empty($aiResponse['task_id'])) {
                $task = Task::find($aiResponse['task_id']);
                // Note: The task might be a group task. As long as user is associated, we allow it.
                if ($task && ($task->user_id === $user->id || $user->groups()->where('groups.id', $task->group_id)->exists()) && $task->status !== 'done') {
                    $task->markDone();
                }
            } elseif ($intent === 'snooze' && !empty($aiResponse['task_id'])) {
                $task = Task::find($aiResponse['task_id']);
                if ($task && ($task->user_id === $user->id || $user->groups()->where('groups.id', $task->group_id)->exists())) {
                    $task->update(['last_wa_notif_sent_at' => now()->addMinutes(60)]);
                }
            }

            return $reply;
        }

        // ── Fallback if all AI fails ───────────────────────────────
        return "Waduh bro, server bot lagi ngos-ngosan nih. Coba lagi bentar ya! 🎓";
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Strip leading 0 or 62 to get the core number
        if (str_starts_with($phone, '62')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        
        return $phone;
    }
}
