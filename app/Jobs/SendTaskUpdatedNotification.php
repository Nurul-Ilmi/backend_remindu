<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

class SendTaskUpdatedNotification implements ShouldQueue
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
            Log::error('Fonnte token missing, cannot send task updated notification.');
            return;
        }

        $this->task->load('user', 'group.members');
        $creator = $this->task->user;
        $desc = $this->task->description ? $this->task->description : '-';

        if ($this->task->group_id && $this->task->group) {
            // Group Task Updated
            $message = "*Tugas Kelompok Diperbarui!*\n\n" .
                       "Penanggung Jawab: {$creator->name}\n" .
                       "Komunitas/Grup: {$this->task->group->name}\n\n" .
                       "DETAIL INFORMASI TERBARU:\n" .
                       "- Judul Tugas: {$this->task->title}\n" .
                       "- Deskripsi: {$desc}\n" .
                       "- Status: {$this->task->status}\n" .
                       "- Kategori/Output: {$this->task->output_type}\n" .
                       "- Beban Tugas: {$this->task->load_type}\n" .
                       "- Tipe Keterlibatan: {$this->task->involvement_type}\n" .
                       "- Deadline: {$this->task->deadline->format('d M Y, H:i')}\n\n" .
                       "Silakan cek kembali perubahannya!";

            foreach ($this->task->group->members as $member) {
                // In-App Notification
                Notification::create([
                    'user_id' => $member->id,
                    'title' => 'Tugas Kelompok Diperbarui',
                    'message' => "Tugas '{$this->task->title}' di grup {$this->task->group->name} telah diperbarui.",
                    'type' => 'info',
                ]);

                if (!$member->wa_connected || !$member->phone_wa) {
                    continue;
                }

                $this->sendFonnteMessage($member->phone_wa, $message, $fonnteToken);
            }
        } else {
            // Personal Task Updated
            Notification::create([
                'user_id' => $creator->id,
                'title' => 'Tugas Diperbarui',
                'message' => "Tugas '{$this->task->title}' telah diperbarui.",
                'type' => 'info',
            ]);

            if (!$creator->wa_connected || !$creator->phone_wa) {
                return;
            }

            $message = "*Tugas Pribadi Diperbarui!*\n\n" .
                       "Pemilik: {$creator->name}\n\n" .
                       "DETAIL INFORMASI TERBARU:\n" .
                       "- Judul Tugas: {$this->task->title}\n" .
                       "- Deskripsi: {$desc}\n" .
                       "- Status: {$this->task->status}\n" .
                       "- Kategori/Output: {$this->task->output_type}\n" .
                       "- Beban Tugas: {$this->task->load_type}\n" .
                       "- Tipe Keterlibatan: {$this->task->involvement_type}\n" .
                       "- Deadline: {$this->task->deadline->format('d M Y, H:i')}\n\n" .
                       "Silakan cek kembali perubahannya!";

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
                Log::error("Fonnte API error for target {$target}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Exception sending Fonnte message to {$target}: " . $e->getMessage());
        }
    }
}
