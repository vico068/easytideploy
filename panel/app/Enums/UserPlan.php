<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum UserPlan: string implements HasColor, HasDescription, HasLabel
{
    case Starter    = 'starter';
    case Premium    = 'premium';
    case Pro        = 'pro';
    case Enterprise = 'enterprise';

    public function getLabel(): string
    {
        return match ($this) {
            self::Starter    => 'Starter',
            self::Premium    => 'Premium',
            self::Pro        => 'Pro',
            self::Enterprise => 'Enterprise',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Starter    => 'gray',
            self::Premium    => 'info',
            self::Pro        => 'warning',
            self::Enterprise => 'danger',
        };
    }

    public function getDescription(): ?string
    {
        $limits = $this->getLimits();

        return sprintf(
            '%d apps · %d containers · %d millicores · %d MB RAM',
            $limits['max_applications'],
            $limits['max_containers'],
            $limits['cpu_limit'],
            $limits['memory_limit'],
        );
    }

    public function getLimits(): array
    {
        return config('easydeploy.plans.' . $this->value, config('easydeploy.plans.starter'));
    }
}
