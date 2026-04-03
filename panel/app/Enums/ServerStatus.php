<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServerStatus: string implements HasLabel, HasColor, HasIcon
{
    case Online = 'online';
    case Offline = 'offline';
    case Maintenance = 'maintenance';
    case Draining = 'draining';

    public function getLabel(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Maintenance => 'Manutenção',
            self::Draining => 'Drenando',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Online => 'success',
            self::Offline => 'danger',
            self::Maintenance => 'warning',
            self::Draining => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Online => 'heroicon-o-check-circle',
            self::Offline => 'heroicon-o-x-circle',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
            self::Draining => 'heroicon-o-arrow-down-tray',
        };
    }

    public function canAcceptContainers(): bool
    {
        return $this === self::Online;
    }
}
