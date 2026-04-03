<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DeploymentStatus: string implements HasLabel, HasColor, HasIcon
{
    case Pending = 'pending';
    case Building = 'building';
    case Deploying = 'deploying';
    case Running = 'running';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RolledBack = 'rolled_back';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Building => 'Compilando',
            self::Deploying => 'Implantando',
            self::Running => 'Em execução',
            self::Failed => 'Falhou',
            self::Cancelled => 'Cancelado',
            self::RolledBack => 'Revertido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Building => 'warning',
            self::Deploying => 'info',
            self::Running => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
            self::RolledBack => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Building => 'heroicon-o-cog-6-tooth',
            self::Deploying => 'heroicon-o-arrow-up-tray',
            self::Running => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-x-circle',
            self::Cancelled => 'heroicon-o-x-mark',
            self::RolledBack => 'heroicon-o-arrow-uturn-left',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Building, self::Deploying]);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Running, self::Failed, self::Cancelled, self::RolledBack]);
    }
}
