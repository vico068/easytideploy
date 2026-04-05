<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
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
}
