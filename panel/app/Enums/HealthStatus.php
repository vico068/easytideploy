<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum HealthStatus: string implements HasLabel, HasColor, HasIcon
{
    case Healthy = 'healthy';
    case Unhealthy = 'unhealthy';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Healthy => 'Saudável',
            self::Unhealthy => 'Não saudável',
            self::Unknown => 'Desconhecido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Unhealthy => 'danger',
            self::Unknown => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Healthy => 'heroicon-o-heart',
            self::Unhealthy => 'heroicon-o-exclamation-circle',
            self::Unknown => 'heroicon-o-question-mark-circle',
        };
    }
}
