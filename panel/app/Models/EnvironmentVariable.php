<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentVariable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'key',
        'value',
        'is_secret',
        'is_build_time',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'is_build_time' => 'boolean',
            'value' => 'encrypted',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function getMaskedValueAttribute(): string
    {
        if ($this->is_secret) {
            return str_repeat('*', min(strlen($this->value), 20));
        }

        return $this->value;
    }

    public function scopeBuildTime($query)
    {
        return $query->where('is_build_time', true);
    }

    public function scopeRuntime($query)
    {
        return $query->where('is_build_time', false);
    }
}
