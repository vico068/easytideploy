<?php

namespace App\Models;

use App\Enums\ContainerStatus;
use App\Enums\HealthStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'deployment_id',
        'server_id',
        'container_id',
        'container_name',
        'internal_ip',
        'port',
        'status',
        'health_status',
        'cpu_usage',
        'memory_usage',
        'restart_count',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContainerStatus::class,
            'health_status' => HealthStatus::class,
            'cpu_usage' => 'decimal:2',
            'memory_usage' => 'decimal:2',
            'restart_count' => 'integer',
            'port' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApplicationLog::class)->orderByDesc('timestamp');
    }

    public function resourceUsages(): HasMany
    {
        return $this->hasMany(ResourceUsage::class);
    }

    public function getAddressAttribute(): string
    {
        return sprintf('%s:%d', $this->internal_ip, $this->port);
    }

    public function getShortContainerIdAttribute(): string
    {
        return substr($this->container_id, 0, 12);
    }

    public function getUptimeAttribute(): ?string
    {
        if (! $this->started_at) {
            return null;
        }

        return $this->started_at->diffForHumans(['parts' => 2, 'short' => true]);
    }

    public function isRunning(): bool
    {
        return $this->status === ContainerStatus::Running;
    }

    public function isHealthy(): bool
    {
        return $this->health_status === HealthStatus::Healthy;
    }

    public function markAsUnhealthy(): void
    {
        $this->update([
            'health_status' => HealthStatus::Unhealthy,
            'status' => ContainerStatus::Unhealthy,
        ]);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', ContainerStatus::Running);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', HealthStatus::Healthy);
    }
}
