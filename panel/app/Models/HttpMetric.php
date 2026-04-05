<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HttpMetric extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'requests_2xx',
        'requests_3xx',
        'requests_4xx',
        'requests_5xx',
        'total_requests',
        'avg_latency_ms',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'avg_latency_ms' => 'decimal:2',
        'requests_2xx' => 'integer',
        'requests_3xx' => 'integer',
        'requests_4xx' => 'integer',
        'requests_5xx' => 'integer',
        'total_requests' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function scopeForPeriod($query, string $period)
    {
        $start = match($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        return $query->where('recorded_at', '>=', $start);
    }
}
