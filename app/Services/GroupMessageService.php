<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;

class GroupMessageService
{
    public function getMessages(Group $group, User $user): Collection
    {
        // Check if user is a member of the group
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            throw new Exception('Bukan anggota grup', 403);
        }

        return $group->messages()
            ->with('user:id,name,avatar')
            ->oldest()
            ->get();
    }

    public function sendMessage(Group $group, array $data, User $user): GroupMessage
    {
        // Check if user is a member
        if (!$group->members()->where('user_id', $user->id)->exists()) {
            throw new Exception('Bukan anggota grup', 403);
        }

        $message = $group->messages()->create([
            'user_id' => $user->id,
            'message' => $data['message'],
        ]);

        return $message->load('user:id,name,avatar');
    }
}
