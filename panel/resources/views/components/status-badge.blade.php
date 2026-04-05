{{--
    Status Badge Component

    Props:
    - status (Enum|null): Enum com HasLabel, HasColor, HasIcon
    - label (string|null): Label manual (se não passar Enum)
    - color (string|null): Cor manual (success, warning, danger, info, gray, brand, cyan)
    - icon (string|null): Ícone manual (heroicon)
    - size (string): sm, md, lg
    - dot (bool): Mostrar dot indicator em vez de ícone
--}}

@props([
    'status' => null,
    'label' => null,
    'color' => null,
    'icon' => null,
    'size' => 'md',
    'dot' => false,
])

@php
    // Se status enum for passado, extrair dados dele
    if ($status && method_exists($status, 'getLabel')) {
        $displayLabel = $status->getLabel();
        $displayColor = $status->getColor();
        $displayIcon = $status->getIcon();
    } else {
        $displayLabel = $label ?? 'Desconhecido';
        $displayColor = $color ?? 'gray';
        $displayIcon = $icon;
    }

    // Mapear cores do Filament para Tailwind com paleta EasyTI
    $colorClasses = match($displayColor) {
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
        'danger' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/20',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-700 dark:text-gray-400 dark:ring-gray-500/20',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20',
        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20 dark:bg-cyan-500/10 dark:text-cyan-400 dark:ring-cyan-500/20',
        default => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-700 dark:text-gray-400 dark:ring-gray-500/20',
    };

    // Tamanhos
    $sizeClasses = match($size) {
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-0.5 text-xs',
        'lg' => 'px-3 py-1 text-sm',
        default => 'px-2.5 py-0.5 text-xs',
    };

    $iconSize = match($size) {
        'sm' => 'w-3 h-3',
        'md' => 'w-3.5 h-3.5',
        'lg' => 'w-4 h-4',
        default => 'w-3.5 h-3.5',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center gap-x-1.5 rounded-full font-medium ring-1 ring-inset {$sizeClasses} {$colorClasses}"
]) }}
    role="status"
    aria-label="{{ $displayLabel }} status"
>
    @if($dot)
        {{-- Dot indicator --}}
        <svg class="{{ $iconSize }}" viewBox="0 0 6 6" aria-hidden="true">
            <circle cx="3" cy="3" r="3" fill="currentColor" />
        </svg>
    @elseif($displayIcon)
        {{-- Ícone --}}
        @svg($displayIcon, $iconSize . ' flex-shrink-0', ['aria-hidden' => 'true'])
    @endif

    <span class="truncate">{{ $displayLabel }}</span>
</span>
