<?php

namespace App\Models;

use App\Enums\UserPlan;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'plan',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'plan' => UserPlan::class,
        ];
    }

    /**
     * Verifica se o usuário é administrador
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function activeApplications(): HasMany
    {
        return $this->applications()->where('status', 'active');
    }

    public function getTotalContainersAttribute(): int
    {
        return $this->applications()->withCount('containers')->get()->sum('containers_count');
    }

    public function getPlanLimits(): array
    {
        $planKey = $this->plan instanceof UserPlan ? $this->plan->value : ($this->plan ?? 'starter');

        return config('easydeploy.plans.' . $planKey, config('easydeploy.plans.starter'));
    }

    public function getApplicationsCountAttribute(): int
    {
        return $this->applications()->count();
    }

    public function canCreateApplication(): bool
    {
        $max = $this->getPlanLimits()['max_applications'];

        return $this->applications_count < $max;
    }

    public function canScaleTo(int $desiredReplicas, ?int $excludeAppId = null): bool
    {
        $max = $this->getPlanLimits()['max_containers'];
        $current = $this->applications()
            ->when($excludeAppId, fn ($q) => $q->where('id', '!=', $excludeAppId))
            ->sum('replicas');

        return ($current + $desiredReplicas) <= $max;
    }
}
