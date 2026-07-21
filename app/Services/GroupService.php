<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupService
{
    public function getUserGroups(User $user): Collection
    {
        $groups = $user->groups()
            ->with(['members' => function ($query) {
                $query->select('users.id', 'users.name', 'users.xp_points', 'users.wa_connected', 'users.level', 'users.avatar');
            }])
            ->get();

        // Attach real tasks_completed count per member per group efficiently
        $groupIds = $groups->pluck('id')->toArray();
        if (!empty($groupIds)) {
            $completedCounts = Task::whereIn('group_id', $groupIds)
                ->where('status', 'done')
                ->select('group_id', 'user_id', DB::raw('COUNT(*) as completed_count'))
                ->groupBy('group_id', 'user_id')
                ->get()
                ->groupBy('group_id');
                
            $groups->each(function (Group $group) use ($completedCounts) {
                $groupCounts = $completedCounts->get($group->id, collect())->pluck('completed_count', 'user_id');
                $group->members->each(function ($member) use ($groupCounts) {
                    $member->setAttribute('tasks_completed', $groupCounts->get($member->id, 0));
                    // Manually set avatar_url since accessor doesn't run on select-constrained eager loads
                    $member->avatar_url = $member->avatar ? asset('storage/avatars/' . $member->avatar) : null;
                });
            });
        } else {
            // Still set avatar_url on members when no tasks to count
            $groups->each(function (Group $group) {
                $group->members->each(function ($member) {
                    $member->avatar_url = $member->avatar ? asset('storage/avatars/' . $member->avatar) : null;
                });
            });
        }

        return $groups;
    }

    public function createGroup(array $data, User $user): Group
    {
        $group = Group::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'subject'     => $data['subject'] ?? null,
            'created_by'  => $user->id,
            'invite_code' => Group::generateInviteCode(substr($data['name'], 0, 2)),
        ]);

        // Add the creator as 'ketua'
        $group->members()->attach($user->id, ['role' => 'ketua']);

        return $group->load('members:id,name,xp_points,wa_connected,level');
    }

    public function updateGroup(Group $group, array $data, User $user): Group
    {
        if (!$group->isKetua($user)) {
            throw new Exception('Hanya ketua grup yang bisa mengedit grup.', 403);
        }

        $group->update($data);

        return $group->fresh();
    }

    public function deleteGroup(Group $group, User $user): void
    {
        if (!$group->isKetua($user)) {
            throw new Exception('Hanya ketua grup yang bisa menghapus grup.', 403);
        }

        $group->delete(); // Uses SoftDeletes
    }

    public function leaveGroup(Group $group, User $user): void
    {
        // Check membership
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            throw new Exception('Anda bukan anggota grup ini.', 404);
        }

        // Prevent ketua from leaving
        if ($group->isKetua($user)) {
            throw new Exception('Ketua tidak bisa keluar dari grup. Hapus grup atau transfer kepemilikan terlebih dahulu.', 422);
        }

        $group->members()->detach($user->id);
    }

    public function joinGroup(string $inviteCode, User $user): Group
    {
        $group = Group::where('invite_code', strtoupper($inviteCode))->firstOrFail();

        // Add the user as 'anggota' if not already in the group
        $group->members()->syncWithoutDetaching([$user->id => ['role' => 'anggota']]);

        return $group->load('members:id,name,xp_points,wa_connected,level');
    }

    public function removeMember(Group $group, User $member, User $actor): void
    {
        if (!$group->isKetua($actor)) {
            throw new Exception('Hanya ketua grup yang bisa mengeluarkan anggota.', 403);
        }
        
        if ($group->isKetua($member)) {
            throw new Exception('Ketua tidak bisa dikeluarkan dari grup.', 422);
        }

        if (!$group->members()->where('user_id', $member->id)->exists()) {
            throw new Exception('User tersebut bukan anggota grup ini.', 404);
        }

        $group->members()->detach($member->id);
    }
}
