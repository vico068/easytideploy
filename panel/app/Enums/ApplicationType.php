<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ApplicationType: string implements HasLabel, HasColor, HasIcon
{
    case NodeJS = 'nodejs';
    case PHP = 'php';
    case Golang = 'golang';
    case Python = 'python';
    case Static = 'static';
    case Docker = 'docker';

    public function getLabel(): string
    {
        return match ($this) {
            self::NodeJS => 'Node.js',
            self::PHP => 'PHP',
            self::Golang => 'Go',
            self::Python => 'Python',
            self::Static => 'Site Estático',
            self::Docker => 'Docker',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NodeJS => 'success',    // Verde - Node.js
            self::PHP => 'primary',       // Azul - PHP
            self::Golang => 'info',       // Cyan - Go
            self::Python => 'warning',    // Amarelo - Python
            self::Static => 'gray',       // Cinza - Static
            self::Docker => 'info',       // Cyan - Docker
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NodeJS => 'heroicon-o-cube',
            self::PHP => 'heroicon-o-code-bracket',
            self::Golang => 'heroicon-o-bolt',
            self::Python => 'heroicon-o-command-line',
            self::Static => 'heroicon-o-document',
            self::Docker => 'heroicon-o-cube-transparent',
        };
    }

    public function getDefaultPort(): int
    {
        return match ($this) {
            self::NodeJS => 3000,
            self::PHP => 8080,
            self::Golang => 8080,
            self::Python => 8000,
            self::Static => 80,
            self::Docker => 3000,
        };
    }

    public function getDefaultBuildCommand(): ?string
    {
        return match ($this) {
            self::NodeJS => 'npm run build',
            self::PHP => 'composer install --no-dev',
            self::Golang => 'go build -o app .',
            self::Python => 'pip install -r requirements.txt',
            self::Static => null,
            self::Docker => null,
        };
    }

    public function getDefaultStartCommand(): ?string
    {
        return match ($this) {
            self::NodeJS => 'npm start',
            self::PHP => 'php artisan serve --host=0.0.0.0',
            self::Golang => './app',
            self::Python => 'python app.py',
            self::Static => null,
            self::Docker => null,
        };
    }
}
