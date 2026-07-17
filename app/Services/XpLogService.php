<?php

namespace App\Services;

use App\Models\XpLog;
use App\Models\User;
use Illuminate\Support\Collection;

class XpLogService
{
    /**
     * Get all XP transaction logs for a given user, latest first.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Support\Collection
     */
    public function getUserLogs(User $user): Collection
    {
        return XpLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Record a new XP transaction for a user.
     *
     * @param  \App\Models\User  $user
     * @param  int               $xpAmount
     * @param  string            $reason
     * @param  int|null          $taskId
     * @return \App\Models\XpLog
     */
    public function record(User $user, int $xpAmount, string $reason, ?int $taskId = null): XpLog
    {
        return XpLog::create([
            'user_id'        => $user->id,
            'task_id'        => $taskId,
            'xp_amount'      => $xpAmount,
            'reason'         => $reason,
            'level_at_event' => $user->level,
        ]);
    }
}
