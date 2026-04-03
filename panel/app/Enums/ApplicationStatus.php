<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ApplicationStatus: string implements HasLabel, HasColor, HasIcon
{
    case Active = 'active';
    case Stopped = 'stopped';
    case Deploying = 'deploying';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Stopped => 'Parado',
            self::Deploying => 'Implantando',
            self::Failed => 'Falhou',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Stopped => 'gray',
            self::Deploying => 'warning',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Stopped => 'heroicon-o-stop-circle',
            self::Deploying => 'heroicon-o-arrow-path',
            self::Failed => 'heroicon-o-x-circle',
        };
    }
}
