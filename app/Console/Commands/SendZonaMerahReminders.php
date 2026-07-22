<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

/**
 * SendZonaMerahReminders
 *
 * Runs every 30 minutes (via scheduler).
 * For each task approaching deadline (< 24 hours), sends a WhatsApp
 * reminder via Fonnte API to the task owner (or all members if group).
 */
class SendZonaMerahReminders extends Command
{
    protected $signature   = 'remindu:zona-merah-notify';
    protected $description = 'Send WhatsApp reminders for Zona Merah tasks via Fonnte API';

    public function handle(): int
    {
        $fonnteToken  = config('services.fonnte.token');
        $fonnteApiUrl = 'https://api.fonnte.com/send';

        if (!$fonnteToken) {
            $this->error('Fonnte API token not configured in services.fonnte.token');
            return self::FAILURE;
        }

        // Refresh Zona Merah flags first
        Task::where('status', '!=', 'done')
            ->where('deadline', '<=', now()->addHours(24))
            ->update(['is_zona_merah' => true]);

        // Fetch all zona merah tasks with WA notifications enabled
        $tasks = Task::with(['user', 'group.members'])
            ->zonaMerah()
            ->where('wa_notif_enabled', true)
            ->where(function ($q) {
                // Interval normal tercapai
                $q->whereNull('last_wa_notif_sent_at')
                  ->orWhere(function ($q2) {
                      $q2->where('load_type', 'Berat')
                         ->where('last_wa_notif_sent_at', '<=', now()->subMinutes(30));
                  })
                  ->orWhere(function ($q3) {
                      $q3->where('load_type', 'Sedang')
                         ->where('last_wa_notif_sent_at', '<=', now()->subMinutes(60));
                  })
                  ->orWhere(function ($q4) {
                      $q4->where('load_type', 'Ringan')
                         ->where('last_wa_notif_sent_at', '<=', now()->subMinutes(120));
                  })
                  // ATAU ada user extreme mode (dipanggil tiap 5 menit)
                  ->orWhere(function ($q5) {
                      $q5->where('last_wa_notif_sent_at', '<=', now()->subMinutes(5))
                         ->where(function ($uq) {
                             $uq->whereHas('user', function ($u) {
                                 $u->where('extreme_mode', true);
                             })
                             ->orWhereHas('group.members', function ($gm) {
                                 $gm->where('extreme_mode', true);
                             });
                         });
                  });
            })
            ->get();

        $this->info("Found {$tasks->count()} Zona Merah task(s) to notify.");

        foreach ($tasks as $task) {
            $isFirstTime = is_null($task->last_wa_notif_sent_at);
            $minsSinceLast = $isFirstTime ? 9999 : now()->diffInMinutes($task->last_wa_notif_sent_at);
            $isNormalIntervalReached = $isFirstTime || match($task->load_type) {
                'Berat' => $minsSinceLast >= 30,
                'Sedang' => $minsSinceLast >= 60,
                'Ringan' => $minsSinceLast >= 120,
                default => $minsSinceLast >= 60,
            };
            $isExtremeIntervalReached = $isFirstTime || $minsSinceLast >= 5;

            $diffMins = (int) now()->diffInMinutes($task->deadline, false);
            if ($diffMins < 0) {
                $absMins = abs($diffMins);
                if ($absMins < 60) {
                    $timeLabel = "Terlewat {$absMins} menit";
                } else {
                    $hours = (int) floor($absMins / 60);
                    $minutes = $absMins % 60;
                    $timeLabel = "Terlewat {$hours} jam" . ($minutes > 0 ? " {$minutes} menit" : "");
                }
            } else {
                if ($diffMins < 60) {
                    $timeLabel = "Sisa {$diffMins} menit lagi";
                } else {
                    $hours = (int) floor($diffMins / 60);
                    $minutes = $diffMins % 60;
                    $timeLabel = "Sisa {$hours} jam" . ($minutes > 0 ? " {$minutes} menit" : "") . " lagi";
                }
            }

            $sentToNormalUser = false;

            if ($task->group_id && $task->group) {
                // Notifikasi Grup
                foreach ($task->group->members as $member) {
                    if (!$member->wa_connected || !$member->phone_wa) continue;
                    
                    if ($member->extreme_mode) {
                        if (!$isExtremeIntervalReached) continue;
                    } else {
                        if (!$isNormalIntervalReached) continue;
                    }

                    $message = $member->extreme_mode
                        ? $this->buildExtremeGroupMessage($task, $member, $timeLabel)
                        : $this->buildNormalGroupMessage($task, $member, $timeLabel);

                    // Create in-app notification
                    if ($isFirstTime && !$member->extreme_mode) {
                        Notification::create([
                            'user_id' => $member->id,
                            'title' => 'Zona Merah: ' . $task->group->name,
                            'message' => "Tugas '{$task->title}' - {$timeLabel}.",
                            'type' => 'warning',
                        ]);
                    }

                    $this->sendFonnte($fonnteApiUrl, $fonnteToken, $member->phone_wa, $message, "{$member->name} (Group: {$task->group->name})", $task);

                    if (!$member->extreme_mode) {
                        $sentToNormalUser = true;
                    }
                }
            } else {
                // Notifikasi Personal
                $user = $task->user;
                if ($user->wa_connected && $user->phone_wa) {
                    
                    $shouldSend = $user->extreme_mode ? $isExtremeIntervalReached : $isNormalIntervalReached;
                    if ($shouldSend) {
                        $message = $user->extreme_mode
                            ? $this->buildExtremePersonalMessage($task, $user, $timeLabel)
                            : $this->buildNormalPersonalMessage($task, $user, $timeLabel);

                        // Create in-app notification
                        if ($isFirstTime && !$user->extreme_mode) {
                            Notification::create([
                                'user_id' => $user->id,
                                'title' => 'Zona Merah',
                                'message' => "Tugas '{$task->title}' - {$timeLabel}.",
                                'type' => 'warning',
                            ]);
                        }

                        $this->sendFonnte($fonnteApiUrl, $fonnteToken, $user->phone_wa, $message, $user->name, $task);

                        if (!$user->extreme_mode) {
                            $sentToNormalUser = true;
                        }
                    }
                }
            }
            
            // Mark task as notified ONLY if we sent it to a normal user
            // Extreme mode users get it every minute without updating this timestamp
            if ($sentToNormalUser || $isFirstTime) {
                // Wait, if we only sent to an extreme user on their first time, should we update it?
                // No, if we update it, the normal interval will start from now, which is fine.
                // But wait, if we only have an extreme user, it will be updated once, then never again.
                // That's also fine! So let's just update if sentToNormalUser is true, or if we want to mark the first notification.
                // Actually, if we only sent to extreme, we don't NEED to update it. But we can update it on first time so it's not null.
                $task->update(['last_wa_notif_sent_at' => now()]);
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function sendFonnte(string $url, string $token, string $phone, string $message, string $logName, Task $task)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post($url, [
                'target'  => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $this->line("  ✓ Notified: {$logName} → {$task->title}");
            } else {
                Log::warning("Fonnte send failed for task {$task->id} to {$phone}: " . $response->body());
                $this->warn("  ✗ Failed: {$logName} → {$task->title}");
            }
        } catch (\Exception $e) {
            Log::error("Fonnte exception for task {$task->id} to {$phone}: " . $e->getMessage());
            $this->error("  ✗ Exception: " . $e->getMessage());
        }
    }

    // ── Message Builders ──────────────────────────────────────────

    private function buildNormalPersonalMessage(Task $task, $user, string $timeLabel): string
    {
        return
            "ZONA MERAH — remind.u\n\n" .
            "Woi {$user->name}, tugas lu mau deadline nih cuy!\n\n" .
            "{$task->title}\n" .
            "Tenggat: {$task->deadline->format('d M Y, H:i')}\n" .
            "Waktu: *{$timeLabel}*\n\n" .
            "Gas nugas! Balas chat ini (misal: 'udah kelar bro') kalau udah selesai ya.";
    }

    private function buildExtremePersonalMessage(Task $task, $user, string $timeLabel): string
    {
        return
            "EXTREME ALERT\n\n" .
            "{$user->name}!!! LU MAU NUNDA SAMPE KAPAN?!\n\n" .
            "{$task->title}\n" .
            "WAKTU: *{$timeLabel}*!\n\n" .
            "Lu sendiri yang nyalain Extreme Mode, buktiin kalo lu niat! Balas pesan ini pake kata-kata lu sendiri kalau tugasnya udah kelar!";
    }

    private function buildNormalGroupMessage(Task $task, $member, string $timeLabel): string
    {
        return
            "ZONA MERAH KELOMPOK — remind.u\n\n" .
            "Bro {$member->name}, tugas kelompok kalian di grup *{$task->group->name}* nyaris deadline nih!\n\n" .
            "{$task->title}\n" .
            "Tenggat: {$task->deadline->format('d M Y, H:i')}\n" .
            "Waktu: *{$timeLabel}*\n\n" .
            "Ayo dong gerak, jangan nungguin temen doang wkwk. Kalo udah beres, chat gw ya biar gw coret tugasnya buat sekelompok!";
    }

    private function buildExtremeGroupMessage(Task $task, $member, string $timeLabel): string
    {
        return
            "EXTREME GROUP ALERT\n\n" .
            "Woi {$member->name}!!! TUGAS KELOMPOK *{$task->group->name}* MAU DEADLINE!!!\n\n" .
            "{$task->title}\n" .
            "WAKTU: *{$timeLabel}*!\n\n" .
            "Kasihan temen lu woi kalo nggak kelar. Buruan sikat sekarang juga! Balas chat gw kalo udah kelar!";
    }
}
