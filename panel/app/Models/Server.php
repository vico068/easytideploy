<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'internal_ip',
        'agent_port',
        'status',
        'max_containers',
        'cpu_total',
        'memory_total',
        'cpu_used',
        'memory_used',
        'labels',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
            'labels' => 'array',
            'last_heartbeat_at' => 'datetime',
            'agent_port' => 'integer',
            'max_containers' => 'integer',
            'cpu_total' => 'integer',
            'memory_total' => 'integer',
            'cpu_used' => 'integer',
            'memory_used' => 'integer',
        ];
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function runningContainers(): HasMany
    {
        return $this->containers()->where('status', 'running');
    }

    public function getCpuAvailableAttribute(): int
    {
        return $this->cpu_total - $this->cpu_used;
    }

    public function getMemoryAvailableAttribute(): int
    {
        return $this->memory_total - $this->memory_used;
    }

    public function getCpuUsagePercentAttribute(): float
    {
        if ($this->cpu_total === 0) {
            return 0;
        }

        return round(($this->cpu_used / $this->cpu_total) * 100, 2);
    }

    public function getMemoryUsagePercentAttribute(): float
    {
        if ($this->memory_total === 0) {
            return 0;
        }

        return round(($this->memory_used / $this->memory_total) * 100, 2);
    }

    public function getAgentUrlAttribute(): string
    {
        return sprintf('%s:%d', $this->internal_ip ?? $this->ip_address, $this->agent_port);
    }

    public function isOnline(): bool
    {
        return $this->status === ServerStatus::Online;
    }

    public function canAcceptContainers(): bool
    {
        return $this->status->canAcceptContainers() &&
            $this->containers()->count() < $this->max_containers;
    }

    public function hasCapacity(int $cpuNeeded, int $memoryNeeded): bool
    {
        return $this->cpu_available >= $cpuNeeded &&
            $this->memory_available >= $memoryNeeded;
    }

    public function scopeOnline($query)
    {
        return $query->where('status', ServerStatus::Online);
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [ServerStatus::Online]);
    }
}
