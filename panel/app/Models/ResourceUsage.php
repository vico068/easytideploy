<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceUsage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'container_id',
        'server_id',
        'cpu_percent',
        'memory_percent',
        'disk_percent',
        'cpu_usage',
        'memory_usage',
        'network_in',
        'network_out',
        'network_rx',
        'network_tx',
        'disk_read',
        'disk_write',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'cpu_percent' => 'decimal:2',
            'memory_percent' => 'decimal:2',
            'disk_percent' => 'decimal:2',
            'cpu_usage' => 'decimal:2',
            'memory_usage' => 'decimal:2',
            'network_in' => 'integer',
            'network_out' => 'integer',
            'network_rx' => 'integer',
            'network_tx' => 'integer',
            'disk_read' => 'integer',
            'disk_write' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function getNetworkTotalAttribute(): int
    {
        return $this->network_rx + $this->network_tx;
    }

    public function getDiskTotalAttribute(): int
    {
        return $this->disk_read + $this->disk_write;
    }

    public function getFormattedNetworkRxAttribute(): string
    {
        return $this->formatBytes($this->network_rx);
    }

    public function getFormattedNetworkTxAttribute(): string
    {
        return $this->formatBytes($this->network_tx);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function scopeForPeriod($query, string $period = '1h')
    {
        $since = match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subHour(),
        };

        return $query->where('recorded_at', '>=', $since);
    }
}
