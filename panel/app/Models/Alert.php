<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'application_id',
        'type',
        'severity',
        'title',
        'message',
        'labels',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'labels' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Scope para alertas ativos (em disparo)
     */
    public function scopeFiring($query)
    {
        return $query->where('status', 'firing');
    }

    /**
     * Scope para alertas resolvidos
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope por severidade
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope por tipo
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Relação com a aplicação
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Scope para alertas do usuário (via aplicações)
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereHas('application', fn($app) => $app->where('user_id', $userId))
              ->orWhereNull('application_id');
        });
    }

    /**
     * Scope para alertas de uma aplicação específica
     */
    public function scopeForApplication($query, string $appId)
    {
        return $query->where('application_id', $appId);
    }
}
