<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Models\XpLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

class AdminService
{
    public function getDashboardStats(): array
    {
        $totalUsers = User::count();
        $totalTasks = Task::count();
        $totalGroups = Group::count();

        // 1. Task Activity Chart Data (Last 7 Days)
        $taskActivity = [];
        $startDate = Carbon::today()->subDays(6);
        
        $createdTasks = Task::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');
            
        $completedTasks = Task::where('status', 'done')
            ->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');
            
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $taskActivity[] = [
                'date' => $date->format('d M'),
                'Dibuat' => $createdTasks->get($dateStr, 0),
                'Selesai' => $completedTasks->get($dateStr, 0),
            ];
        }

        // 2. WA Adoption Chart Data
        $waConnected = User::where('wa_connected', true)->count();
        $waDisconnected = User::where('wa_connected', false)->count();
        
        $waAdoption = [
            ['name' => 'Terhubung', 'value' => $waConnected, 'color' => '#076559'],
            ['name' => 'Terputus', 'value' => $waDisconnected, 'color' => '#E5E7EB'],
        ];

        return [
            'stats' => [
                'total_users' => $totalUsers,
                'total_tasks' => $totalTasks,
                'total_groups' => $totalGroups,
            ],
            'charts' => [
                'task_activity' => $taskActivity,
                'wa_adoption' => $waAdoption,
            ]
        ];
    }

    public function deleteUser(User $user): void
    {
        if ($user->role === 'admin') {
            throw new Exception('Cannot delete admin user');
        }

        DB::beginTransaction();
        try {
            // Manual Cascade Delete due to lack of onDelete('cascade') in old migrations
            Task::where('user_id', $user->id)->delete();
            XpLog::where('user_id', $user->id)->delete();
            Notification::where('user_id', $user->id)->delete();
            GroupMessage::where('user_id', $user->id)->delete();
            DB::table('group_members')->where('user_id', $user->id)->delete();
            
            // Delete groups created by this user
            Group::where('created_by', $user->id)->delete();

            $user->delete();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete user: ' . $e->getMessage());
        }
    }

    public function broadcastMessage(string $messageText): array
    {
        $users = User::where('wa_connected', true)
            ->whereNotNull('phone_wa')
            ->get();

        if ($users->isEmpty()) {
            throw new Exception('Tidak ada pengguna dengan WA terhubung.');
        }

        $targetNumbers = $users->pluck('phone_wa')->implode(',');
        $message = "*PENGUMUMAN SUPER ADMIN*\n\n" . $messageText;

        $tokenSetting = AppSetting::where('key', 'fonnte_token')->first();
        if (!$tokenSetting || empty($tokenSetting->value)) {
            throw new Exception('Token Fonnte belum dikonfigurasi.');
        }

        $response = Http::withHeaders([
            'Authorization' => $tokenSetting->value
        ])->post('https://api.fonnte.com/send', [
            'target' => $targetNumbers,
            'message' => $message,
            'delay' => '2',
            'countryCode' => '62',
        ]);

        return [
            'count' => $users->count(),
            'fonnte_response' => $response->json()
        ];
    }

    public function updateUserXP(User $user, int $xpAmount): void
    {
        DB::transaction(function () use ($user, $xpAmount) {
            $user->xp_points += $xpAmount;
            
            // Adjust level based on standard math (level = sqrt(xp / 100))
            if ($user->xp_points < 0) $user->xp_points = 0;
            $user->level = floor(sqrt($user->xp_points / 100)) + 1;
            
            $user->save();

            XpLog::create([
                'user_id' => $user->id,
                'task_id' => null,
                'xp_amount' => $xpAmount,
                'reason' => 'Admin manual modification',
                'description' => 'XP dimodifikasi oleh Superadmin'
            ]);
        });
    }

    public function toggleSuspendUser(User $user): bool
    {
        if ($user->role === 'admin') {
            throw new Exception('Cannot suspend admin user');
        }

        $user->is_suspended = !$user->is_suspended;
        $user->save();

        return $user->is_suspended;
    }

    public function getAllGroups()
    {
        return Group::with('creator:id,name,avatar')
            ->withCount('members')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function deleteGroup(Group $group): void
    {
        DB::transaction(function () use ($group) {
            // Because cascade deletes might not be in migration for some tables:
            DB::table('group_members')->where('group_id', $group->id)->delete();
            GroupMessage::where('group_id', $group->id)->delete();
            Task::where('group_id', $group->id)->delete();
            
            $group->delete();
        });
    }

    public function cleanupOldTasks(): int
    {
        // Delete overdue tasks older than 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $tasksToDelete = Task::where('status', 'overdue')
            ->where('deadline', '<', $thirtyDaysAgo);
            
        $count = $tasksToDelete->count();
        $tasksToDelete->delete();
        
        return $count;
    }
}
