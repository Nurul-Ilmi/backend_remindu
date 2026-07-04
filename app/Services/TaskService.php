<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function getTasks(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        // If querying for a specific group, check membership and return all group tasks
        if (!empty($filters['group_id'])) {
            if (!$this->isGroupMember($user, $filters['group_id'])) {
                throw new Exception('Unauthorized', 403);
            }
            $query = Task::where('group_id', $filters['group_id'])
                ->with(['group:id,name', 'user:id,name,avatar'])
                ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
                ->latest('deadline');
        } else {
            // Otherwise, return user's personal tasks
            $query = $user->tasks()
                ->with('group:id,name')
                ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
                ->oldest('deadline');
        }

        // Filter: status
        // if (!empty($filters['status'])) {
        //     $query->where('status', $filters['status']);
        // }

        // Filter: zona merah
        if (!empty($filters['zona_merah'])) {
            $query->zonaMerah();
        }

        $paginator = $query->paginate($perPage);

        // Manually append avatar_url to eager-loaded user (accessor doesn't run on select-constrained relations)
        if (!empty($filters['group_id'])) {
            $paginator->getCollection()->each(function ($task) {
                if ($task->user && $task->user->avatar) {
                    $task->user->avatar_url = asset('storage/avatars/' . $task->user->avatar);
                } elseif ($task->user) {
                    $task->user->avatar_url = null;
                }
            });
        }

        return $paginator;
    }

    public function createTask(User $user, array $data, bool $assignToAll = false, ?int $assignedTo = null): Task|Collection
    {
        $finalAssignedTo = $user->id; // Default to self

        if ($assignToAll) {
            if (empty($data['group_id'])) {
                throw new Exception('Anda tidak bisa menugaskan task ke semua orang tanpa grup.', 403);
            }
            $group = Group::find($data['group_id']);
            if (!$group->isKetua($user)) {
                throw new Exception('Hanya ketua grup yang dapat menugaskan tugas ke semua anggota.', 403);
            }
            
            $members = $group->members;
            $tasks = collect();

            DB::transaction(function () use ($members, $data, $tasks) {
                foreach ($members as $member) {
                    $task = Task::create([
                        ...$data,
                        'user_id'          => $member->id,
                        'xp_reward'        => $data['xp_reward'] ?? $this->defaultXp($data['load_type']),
                        'is_zona_merah'    => now()->diffInHours($data['deadline']) <= 24,
                        'wa_notif_enabled' => $data['wa_notif_enabled'] ?? true,
                    ]);

                    if ($task->wa_notif_enabled) {
                        \App\Jobs\SendTaskCreatedNotification::dispatch($task);
                    }
                    $tasks->push($task);
                }
            });
            
            return $tasks;
        }

        if ($assignedTo && $assignedTo != $user->id) {
            if (empty($data['group_id'])) {
                throw new Exception('Anda tidak bisa menugaskan task ke orang lain tanpa grup.', 403);
            }
            $group = Group::find($data['group_id']);
            if (!$group->isKetua($user)) {
                throw new Exception('Hanya ketua grup yang dapat menugaskan tugas ke anggota lain.', 403);
            }
            
            $isMember = $group->members()->where('user_id', $assignedTo)->exists();
            if (!$isMember) {
                throw new Exception('User tersebut bukan anggota grup ini.', 422);
            }

            $finalAssignedTo = $assignedTo;
        }

        $task = Task::create([
            ...$data,
            'user_id'          => $finalAssignedTo,
            'xp_reward'        => $data['xp_reward'] ?? $this->defaultXp($data['load_type']),
            'is_zona_merah'    => now()->diffInHours($data['deadline']) <= 24,
            'wa_notif_enabled' => $data['wa_notif_enabled'] ?? true,
        ]);

        if ($task->wa_notif_enabled) {
            \App\Jobs\SendTaskCreatedNotification::dispatch($task);
        }

        return $task->load('group:id,name');
    }

    public function getTask(Task $task, User $user): Task
    {
        if ($task->user_id !== $user->id && !$this->isGroupMember($user, $task->group_id)) {
            throw new Exception('Unauthorized', 403);
        }
        return $task->load('group', 'xpLogs');
    }

    public function updateTask(Task $task, User $user, array $data): Task
    {
        $isKetua = $task->group_id ? $task->group->isKetua($user) : false;
        if ($task->user_id !== $user->id && !$isKetua) {
            throw new Exception('Hanya pemilik tugas atau ketua grup yang dapat mengubah tugas ini.', 403);
        }

        // If marked done via this update, use the helper for XP award
        if (($data['status'] ?? null) === 'done' && $task->status !== 'done') {
            $task->markDone();
            return $task->fresh();
        }
        
        // If moved out of done state, revert the XP and completion time
        if (isset($data['status']) && $data['status'] !== 'done' && $task->status === 'done') {
            $task->unmarkDone();
        }

        $task->update($data);
        $task->refreshZonaMerah();

        $freshTask = $task->fresh();
        if ($freshTask->wa_notif_enabled) {
            \App\Jobs\SendTaskUpdatedNotification::dispatch($freshTask);
        }

        return $freshTask;
    }

    public function deleteTask(Task $task, User $user): void
    {
        $isKetua = $task->group_id ? $task->group->isKetua($user) : false;
        if ($task->user_id !== $user->id && !$isKetua) {
            throw new Exception('Hanya pemilik tugas atau ketua grup yang dapat menghapus tugas ini.', 403);
        }
        $task->delete();
    }

    public function markTaskDone(Task $task, User $user): Task
    {
        $isKetua = $task->group_id ? $task->group->isKetua($user) : false;
        if ($task->user_id !== $user->id && !$isKetua) {
            throw new Exception('Hanya pemilik tugas atau ketua grup yang dapat mengubah status tugas ini.', 403);
        }
        $task->markDone();
        return $task;
    }

    public function delayTask(Task $task, User $user): Task
    {
        $isKetua = $task->group_id ? $task->group->isKetua($user) : false;
        if ($task->user_id !== $user->id && !$isKetua) {
            throw new Exception('Hanya pemilik tugas atau ketua grup yang dapat menunda tugas ini.', 403);
        }

        $task->update([
            'deadline' => Carbon::parse($task->deadline)->addMinutes(15)
        ]);

        $user->addXp(-5, 'Menunda tugas: ' . $task->title, 'Tugas');

        return $task;
    }

    private function isGroupMember(User $user, ?int $groupId): bool
    {
        if (!$groupId) return false;
        return $user->groups()->where('groups.id', $groupId)->exists();
    }

    private function defaultXp(string $loadType): int
    {
        return match ($loadType) {
            'Ringan'  => 15,
            'Sedang'  => 50,
            'Berat'   => 100,
            default   => 15,
        };
    }
}
