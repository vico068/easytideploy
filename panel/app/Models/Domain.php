<?php

namespace App\Models;

use App\Enums\SslStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'domain',
        'ssl_enabled',
        'ssl_certificate',
        'ssl_private_key',
        'is_primary',
        'ssl_status',
        'ssl_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
            'is_primary' => 'boolean',
            'ssl_status' => SslStatus::class,
            'ssl_expires_at' => 'datetime',
            'ssl_certificate' => 'encrypted',
            'ssl_private_key' => 'encrypted',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function getUrlAttribute(): string
    {
        $protocol = $this->ssl_enabled ? 'https' : 'http';

        return sprintf('%s://%s', $protocol, $this->domain);
    }

    public function isSslActive(): bool
    {
        return $this->ssl_enabled && $this->ssl_status === SslStatus::Active;
    }

    public function isSslExpiringSoon(): bool
    {
        if (! $this->ssl_expires_at) {
            return false;
        }

        return $this->ssl_expires_at->isBefore(now()->addDays(30));
    }

    public function markAsPrimary(): void
    {
        // Remove primary from other domains
        $this->application->domains()
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
