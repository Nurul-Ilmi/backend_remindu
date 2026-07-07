<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'group_id',
        'title',
        'description',
        'output_type',
        'load_type',
        'involvement_type',
        'status',
        'kanban_column',
        'kanban_order',
        'deadline',
        'is_zona_merah',
        'xp_reward',
        'wa_notif_enabled',
        'last_wa_notif_sent_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline'              => 'datetime',
            'last_wa_notif_sent_at' => 'datetime',
            'completed_at'          => 'datetime',
            'is_zona_merah'         => 'boolean',
            'wa_notif_enabled'      => 'boolean',
            'kanban_order'          => 'integer',
            'xp_reward'             => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function xpLogs(): HasMany
    {
        return $this->hasMany(XpLog::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    /** Tasks with deadline within the next 24 hours and not yet done */
    public function scopeZonaMerah($query)
    {
        return $query->where('status', '!=', 'done')
            ->where('deadline', '<=', now()->addHours(24));
    }

    /** Tasks due today */
    public function scopeDueToday($query)
    {
        return $query->whereDate('deadline', today());
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Mark a task as done, award XP, update Zona Merah flag.
     */
    public function markDone(): void
    {
        $isFirstTime = $this->completed_at === null;

        $this->update([
            'status'       => 'done',
            'kanban_column'=> 'done',
            'completed_at' => $this->completed_at ?? now(),
        ]);

        if ($isFirstTime && !$this->xpLogs()->where('xp_amount', '>', 0)->exists()) {
            $this->user->addXp($this->xp_reward, "Task Completed: {$this->title}", $this->id);
        }
    }

    /**
     * Unmark a task as done, deducting XP and clearing completed_at.
     */
    public function unmarkDone(): void
    {
        $this->update([
            'completed_at' => null,
        ]);

        // Remove the previously awarded XP
        $this->user->removeXp($this->xp_reward, $this->id);
    }

    /**
     * Refresh the is_zona_merah flag based on current deadline.
     */
    public function refreshZonaMerah(): void
    {
        $this->update([
            'is_zona_merah' => $this->status !== 'done'
                && $this->deadline <= now()->addHours(24),
        ]);
    }
}
