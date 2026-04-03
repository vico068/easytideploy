<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deployment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'commit_sha',
        'commit_message',
        'commit_author',
        'status',
        'build_logs',
        'image_tag',
        'triggered_by',
        'started_at',
        'finished_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeploymentStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function runningContainers(): HasMany
    {
        return $this->containers()->where('status', 'running');
    }

    public function getShortCommitShaAttribute(): ?string
    {
        return $this->commit_sha ? substr($this->commit_sha, 0, 7) : null;
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        }

        return sprintf('%ds', $seconds);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isRunning(): bool
    {
        return $this->status === DeploymentStatus::Running;
    }

    public function isFailed(): bool
    {
        return $this->status === DeploymentStatus::Failed;
    }

    public function markAsBuilding(): void
    {
        $this->update([
            'status' => DeploymentStatus::Building,
            'started_at' => now(),
        ]);
    }

    public function markAsDeploying(): void
    {
        $this->update(['status' => DeploymentStatus::Deploying]);
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => DeploymentStatus::Running,
            'finished_at' => now(),
            'duration_seconds' => $this->started_at?->diffInSeconds(now()),
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => DeploymentStatus::Failed,
            'finished_at' => now(),
            'duration_seconds' => $this->started_at?->diffInSeconds(now()),
            'build_logs' => $this->build_logs."\n\n[ERROR] ".$reason,
        ]);
    }

    public function appendBuildLog(string $log): void
    {
        $this->update([
            'build_logs' => ($this->build_logs ?? '').$log,
        ]);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', DeploymentStatus::Running);
    }
}
