<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'subject',
        'invite_code',
        'created_by',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Generate a unique 8-character invite code like "SO-X7K2".
     */
    public static function generateInviteCode(string $prefix = ''): string
    {
        do {
            $code = strtoupper(($prefix ? substr($prefix, 0, 2) . '-' : '') . Str::random(4));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * Check if a given user is the Ketua (leader) of this group.
     */
    public function isKetua(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'ketua')
            ->exists();
    }
}
