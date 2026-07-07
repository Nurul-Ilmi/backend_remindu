<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'phone_wa',
        'wa_connected',
        'wa_otp',
        'wa_otp_expires_at',
        'xp_points',
        'level',
        'extreme_mode',
        'study_program',
        'batch_year',
        'university',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'wa_connected'      => 'boolean',
            'extreme_mode'      => 'boolean',
            'xp_points'         => 'integer',
            'level'             => 'integer',
            'wa_otp_expires_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function xpLogs(): HasMany
    {
        return $this->hasMany(XpLog::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function groupMessages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Add XP to the user, level up if threshold crossed.
     */
    public function addXp(int $amount, string $reason, ?int $taskId = null): void
    {
        $this->xp_points += $amount;

        // Simple leveling: each level = 1000 XP × level number
        $xpForNextLevel = $this->level * 1000;
        if ($this->xp_points >= $xpForNextLevel) {
            $this->level++;
        }

        $this->save();

        XpLog::create([
            'user_id'        => $this->id,
            'task_id'        => $taskId,
            'xp_amount'      => $amount,
            'reason'         => $reason,
            'level_at_event' => $this->level,
        ]);
    }
    public function removeXp(int $amount, ?int $taskId = null): void
    {
        $this->xp_points -= $amount;
        if ($this->xp_points < 0) {
            $this->xp_points = 0;
        }

        $expectedLevel = max(1, (int) floor($this->xp_points / 1000) + 1);
        if ($this->level > $expectedLevel) {
            $this->level = $expectedLevel;
        }

        $this->save();

        if ($taskId) {
            $log = XpLog::where('user_id', $this->id)
                ->where('task_id', $taskId)
                ->where('xp_amount', $amount)
                ->latest()
                ->first();
                
            if ($log) {
                $log->delete();
            }
        }
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return asset('storage/avatars/' . $this->avatar);
        }
        return null;
    }
}
