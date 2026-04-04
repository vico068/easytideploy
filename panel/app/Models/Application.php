<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'type',
        'git_repository',
        'git_branch',
        'git_token',
        'runtime_version',
        'build_command',
        'start_command',
        'root_directory',
        'port',
        'replicas',
        'min_replicas',
        'max_replicas',
        'auto_deploy',
        'auto_scale',
        'ssl_enabled',
        'cpu_limit',
        'memory_limit',
        'status',
        'health_check_path',
        'health_check_interval',
        'health_check',
        'webhook_secret',
        'traefik_config_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ApplicationType::class,
            'status' => ApplicationStatus::class,
            'health_check' => 'array',
            'auto_deploy' => 'boolean',
            'auto_scale' => 'boolean',
            'ssl_enabled' => 'boolean',
            'port' => 'integer',
            'replicas' => 'integer',
            'min_replicas' => 'integer',
            'max_replicas' => 'integer',
            'cpu_limit' => 'integer',
            'memory_limit' => 'integer',
            'health_check_interval' => 'integer',
            'git_token' => 'encrypted',
            'traefik_config_updated_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($application) {
            if (empty($application->slug)) {
                $application->slug = Str::slug($application->name);
            }
            if (empty($application->webhook_secret)) {
                $application->webhook_secret = Str::random(40);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->orderByDesc('created_at');
    }

    public function latestDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->latestOfMany('created_at');
    }

    public function runningDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->where('status', 'running')->latestOfMany('created_at');
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function runningContainers(): HasMany
    {
        return $this->containers()->where('status', 'running');
    }

    public function healthyContainers(): HasMany
    {
        return $this->containers()
            ->where('status', 'running')
            ->where('health_status', 'healthy');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(Domain::class)->where('is_primary', true);
    }

    public function environmentVariables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApplicationLog::class)->orderByDesc('timestamp');
    }

    public function resourceUsages(): HasMany
    {
        return $this->hasMany(ResourceUsage::class);
    }

    public function getDefaultDomainAttribute(): string
    {
        return sprintf('%s.easyti.cloud', $this->slug);
    }

    public function getWebhookUrlAttribute(): string
    {
        return route('webhooks.github', ['application' => $this->id]);
    }

    public function isActive(): bool
    {
        return $this->status === ApplicationStatus::Active;
    }

    public function isDeploying(): bool
    {
        return $this->status === ApplicationStatus::Deploying;
    }

    public function getEnvironmentArray(): array
    {
        return $this->environmentVariables
            ->pluck('value', 'key')
            ->toArray();
    }

    public function getBuildEnvironmentArray(): array
    {
        return $this->environmentVariables
            ->where('is_build_time', true)
            ->pluck('value', 'key')
            ->toArray();
    }

    public function scopeActive($query)
    {
        return $query->where('status', ApplicationStatus::Active);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
