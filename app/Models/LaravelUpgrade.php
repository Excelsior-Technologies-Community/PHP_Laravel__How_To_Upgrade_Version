<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaravelUpgrade extends Model
{
    protected $fillable = [
        'current_version',
        'target_version',
        'status',
        'output',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Scope completed upgrades.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope failed upgrades.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope running upgrades.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Get duration in seconds.
     */
    public function getDurationInSecondsAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds(
            $this->completed_at
        );
    }
}
