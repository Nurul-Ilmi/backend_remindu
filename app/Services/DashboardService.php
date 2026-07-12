<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function getDashboardSummary(User $user): array
    {
        $tasks = $user->tasks()->where('status', '!=', 'done')->with('group:id,name')->get();

        $completedTasksCount = $user->tasks()->where('status', 'done')->count();
        $overdueTasksCount = $user->tasks()->where('status', 'overdue')->count();
        $totalEvaluated = $completedTasksCount + $overdueTasksCount;
        
        $focusScore = 0; // Default score
        if ($totalEvaluated > 0) {
            $focusScore = (int) round(($completedTasksCount / $totalEvaluated) * 100);
            $focusScore = max(0, min(100, $focusScore));
        }

        // Calculate focus score trend
        $completedLastWeek = $user->tasks()->where('status', 'done')->where('updated_at', '<', Carbon::now()->subWeek())->count();
        $overdueLastWeek = $user->tasks()->where('status', 'overdue')->where('deadline', '<', Carbon::now()->subWeek())->count();
        $totalEvaluatedLastWeek = $completedLastWeek + $overdueLastWeek;
        
        $focusScoreLastWeek = 0;
        if ($totalEvaluatedLastWeek > 0) {
            $focusScoreLastWeek = (int) round(($completedLastWeek / $totalEvaluatedLastWeek) * 100);
            $focusScoreLastWeek = max(0, min(100, $focusScoreLastWeek));
        }
        
        $focusScoreTrend = $focusScore - $focusScoreLastWeek;

        // ── Calculate real streak (consecutive days with completed tasks) ──
        $completedDates = collect(
            $user->tasks()
                ->where('status', 'done')
                ->whereNotNull('completed_at')
                ->selectRaw('DATE(completed_at) as date')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->pluck('date')
        )->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->unique();

        $streakDays = 0;
        $checkDate = Carbon::today();
        
        if ($completedDates->isNotEmpty() && $completedDates->first() === Carbon::yesterday()->format('Y-m-d')) {
            $checkDate = Carbon::yesterday();
        }

        foreach ($completedDates as $date) {
            if ($date === $checkDate->format('Y-m-d')) {
                $streakDays++;
                $checkDate->subDay();
            } else if (Carbon::parse($date)->lt($checkDate)) {
                break;
            }
        }

        // ── Calculate dynamic achievements ──
        $groupCount = $user->groups()->count();
        $majorProjectsDone = $user->tasks()
            ->where('status', 'done')
            ->where('load_type', 'Berat')
            ->count();

        // Count consecutive days without using delay (no -5 XP logs in recent streak)
        $recentXpLogs = $user->xpLogs()
            ->where('created_at', '>=', Carbon::today()->subDays(14))
            ->selectRaw('DATE(created_at) as date, SUM(CASE WHEN xp_amount < 0 THEN 1 ELSE 0 END) as delay_count')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->keyBy('date');

        $noDelayStreak = 0;
        $checkDateDelay = Carbon::today();
        for ($i = 0; $i < 14; $i++) {
            $dateStr = $checkDateDelay->format('Y-m-d');
            $hasLog = $recentXpLogs->has($dateStr);
            if ($hasLog && $recentXpLogs[$dateStr]->delay_count == 0) {
                $noDelayStreak++;
                $checkDateDelay->subDay();
            } else {
                break;
            }
        }

        $achievements = [
            [
                'id'     => 1,
                'icon'   => 'military_tech',
                'label'  => 'Pencapaian Awal',
                'desc'   => 'Selesaikan 5 tugas pertama',
                'earned' => $completedTasksCount >= 5,
            ],
            [
                'id'     => 2,
                'icon'   => 'local_fire_department',
                'label'  => 'Streak 7 Hari',
                'desc'   => 'Aktif 7 hari berturut-turut',
                'earned' => $streakDays >= 7,
            ],
            [
                'id'     => 3,
                'icon'   => 'groups',
                'label'  => 'Tim Player',
                'desc'   => 'Bergabung ke 2 grup',
                'earned' => $groupCount >= 2,
            ],
            [
                'id'     => 4,
                'icon'   => 'rocket_launch',
                'label'  => 'Sprint Master',
                'desc'   => 'Selesaikan 3 Major Project',
                'earned' => $majorProjectsDone >= 3,
            ],
            [
                'id'     => 5,
                'icon'   => 'emoji_events',
                'label'  => 'Top Scorer',
                'desc'   => 'Raih 10.000 XP',
                'earned' => $user->xp_points >= 10000,
            ],
            [
                'id'     => 6,
                'icon'   => 'schedule',
                'label'  => 'Zero Delay',
                'desc'   => 'Tidak menunda 14 hari berturut',
                'earned' => $noDelayStreak >= 14,
            ],
        ];

        return [
            'user' => [
                'name'      => $user->name,
                'xp_points' => $user->xp_points,
                'level'     => $user->level,
                'wa_connected' => $user->wa_connected,
                'extreme_mode' => $user->extreme_mode,
            ],
            'stats' => [
                'total_tasks_completed' => $completedTasksCount,
                'total_xp'              => number_format($user->xp_points / 1000, 1) . 'K',
                'active_groups'         => $groupCount,
                'focus_hours'           => round($completedTasksCount * 0.5, 1),
                'streak_days'           => $streakDays,
            ],
            'achievements'     => $achievements,
            'zona_merah_count' => $tasks->where('is_zona_merah', true)->count(),
            'active_tasks'     => $tasks->take(5)->values(),
            'recent_xp'        => $user->xpLogs()->latest()->take(5)->get(),
            'focus_score'      => $focusScore,
            'focus_score_trend'=> $focusScoreTrend,
        ];
    }
}
