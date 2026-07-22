<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

class SendTaskCreatedNotification implements ShouldQueue
{
    use Queueable;

    private Task $task;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $fonnteToken = config('services.fonnte.token');
        if (!$fonnteToken) {
            Log::error('Fonnte token missing, cannot send task notification.');
            return;
        }

        $this->task->load('user', 'group.members');

        $creator = $this->task->user;

        if ($this->task->group_id && $this->task->group) {
            // Group Task
            $desc = $this->task->description ? $this->task->description : '-';
            $message = "*Tugas Kelompok Baru!*\n\n" .
                       "Penanggung Jawab: {$creator->name}\n" .
                       "Komunitas/Grup: {$this->task->group->name}\n\n" .
                       "INFORMASI TUGAS LENGKAP:\n" .
                       "- Judul Tugas: {$this->task->title}\n" .
                       "- Deskripsi: {$desc}\n" .
                       "- Kategori/Output: {$this->task->output_type}\n" .
                       "- Beban Tugas: {$this->task->load_type}\n" .
                       "- Tipe Keterlibatan: {$this->task->involvement_type}\n" .
                       "- Deadline: {$this->task->deadline->format('d M Y, H:i')}\n\n" .
                       "Ayo saling pantau dan kerjain bareng!";

            foreach ($this->task->group->members as $member) {
                // In-App Notification (always sent)
                if ($member->id !== $creator->id) {
                    Notification::create([
                        'user_id' => $member->id,
                        'title' => 'Tugas Kelompok Baru',
                        'message' => "{$creator->name} menambahkan tugas '{$this->task->title}' di grup {$this->task->group->name}.",
                        'type' => 'info',
                    ]);
                }

                if (!$member->wa_connected || !$member->phone_wa) {
                    continue;
                }

                $this->sendFonnteMessage($member->phone_wa, $message, $fonnteToken);
            }
        } else {
            // Personal Task
            Notification::create([
                'user_id' => $creator->id,
                'title' => 'Tugas Pribadi Baru',
                'message' => "Kamu menambahkan tugas '{$this->task->title}'. Semangat!",
                'type' => 'info',
            ]);

            if (!$creator->wa_connected || !$creator->phone_wa) {
                return;
            }

            $desc = $this->task->description ? $this->task->description : '-';
            $message = "*Tugas Pribadi Baru!*\n\n" .
                       "Pemilik: {$creator->name}\n\n" .
                       "INFORMASI TUGAS LENGKAP:\n" .
                       "- Judul Tugas: {$this->task->title}\n" .
                       "- Deskripsi: {$desc}\n" .
                       "- Kategori/Output: {$this->task->output_type}\n" .
                       "- Beban Tugas: {$this->task->load_type}\n" .
                       "- Tipe Keterlibatan: {$this->task->involvement_type}\n" .
                       "- Deadline: {$this->task->deadline->format('d M Y, H:i')}\n\n" .
                       "Jangan ditunda-tunda, kerjakan secepatnya!";

            $this->sendFonnteMessage($creator->phone_wa, $message, $fonnteToken);
        }
    }

    private function sendFonnteMessage(string $target, string $message, string $token): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target'  => $target,
                'message' => $message,
            ]);

            if (!$response->successful()) {
                Log::warning("Fonnte task created notif failed for {$target}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Fonnte exception for {$target}: " . $e->getMessage());
        }
    }
}
