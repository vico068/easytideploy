<?php

namespace App\Models;

use App\Enums\LogLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'container_id',
        'level',
        'message',
        'context',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'level' => LogLevel::class,
            'context' => 'array',
            'timestamp' => 'datetime',
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

    public function scopeLevel($query, LogLevel $level)
    {
        return $query->where('level', $level);
    }

    public function scopeErrors($query)
    {
        return $query->whereIn('level', [LogLevel::Error, LogLevel::Critical]);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('timestamp', '>=', now()->subMinutes($minutes));
    }
}
