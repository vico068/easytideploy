<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LogLevel: string implements HasLabel, HasColor, HasIcon
{
    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Debug => 'Debug',
            self::Info => 'Info',
            self::Warning => 'Aviso',
            self::Error => 'Erro',
            self::Critical => 'Crítico',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Debug => 'gray',
            self::Info => 'info',
            self::Warning => 'warning',
            self::Error => 'danger',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Debug => 'heroicon-o-bug-ant',
            self::Info => 'heroicon-o-information-circle',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Error => 'heroicon-o-x-circle',
            self::Critical => 'heroicon-o-fire',
        };
    }
}
