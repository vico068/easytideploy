<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ContainerStatus: string implements HasLabel, HasColor, HasIcon
{
    case Starting = 'starting';
    case Running = 'running';
    case Stopping = 'stopping';
    case Stopped = 'stopped';
    case Failed = 'failed';
    case Unhealthy = 'unhealthy';

    public function getLabel(): string
    {
        return match ($this) {
            self::Starting => 'Iniciando',
            self::Running => 'Em execução',
            self::Stopping => 'Parando',
            self::Stopped => 'Parado',
            self::Failed => 'Falhou',
            self::Unhealthy => 'Não saudável',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Starting => 'warning',
            self::Running => 'success',
            self::Stopping => 'warning',
            self::Stopped => 'gray',
            self::Failed => 'danger',
            self::Unhealthy => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Starting => 'heroicon-o-arrow-path',
            self::Running => 'heroicon-o-play-circle',
            self::Stopping => 'heroicon-o-pause-circle',
            self::Stopped => 'heroicon-o-stop-circle',
            self::Failed => 'heroicon-o-x-circle',
            self::Unhealthy => 'heroicon-o-exclamation-triangle',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Starting, self::Running, self::Stopping]);
    }
}
