{{--
    Progress Bar Component

    Props:
    - value (float): Valor de 0 a 100
    - label (string|null): Label do lado esquerdo
    - variant (string): auto (baseado em thresholds), success, warning, danger, brand, cyan
    - showValue (bool): Mostrar valor numérico
    - height (string): Altura da barra (Tailwind class como 'h-2', 'h-3', etc)
    - thresholds (array): Thresholds para cores automáticas [success => 60, warning => 80]
--}}

@props([
    'value' => 0,
    'label' => null,
    'variant' => 'auto',
    'showValue' => true,
    'height' => 'h-2',
    'thresholds' => ['success' => 60, 'warning' => 80],
])

@php
    $safeValue = min(max($value, 0), 100);

    // Determinar cor da barra
    if ($variant === 'auto') {
        if ($safeValue <= $thresholds['success']) {
            $barColor = 'bg-green-500';
        } elseif ($safeValue <= $thresholds['warning']) {
            $barColor = 'bg-yellow-500';
        } else {
            $barColor = 'bg-red-500';
        }
    } else {
        $barColor = match($variant) {
            'success' => 'bg-green-500',
            'warning' => 'bg-yellow-500',
            'danger' => 'bg-red-500',
            'brand' => 'bg-brand-600',
            'cyan' => 'bg-cyan-500',
            'info' => 'bg-blue-500',
            default => 'bg-gray-500',
        };
    }
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    {{-- Label e valor --}}
    @if($label || $showValue)
        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
            @if($label)
                <span class="font-medium">{{ $label }}</span>
            @endif
            @if($showValue)
                <span class="font-mono">{{ number_format($safeValue, 1) }}%</span>
            @endif
        </div>
    @endif

    {{-- Barra de progresso --}}
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full {{ $height }} overflow-hidden">
        <div
            class="{{ $height }} {{ $barColor }} rounded-full transition-all duration-300 ease-out"
            style="width: {{ $safeValue }}%"
            role="progressbar"
            aria-valuenow="{{ $safeValue }}"
            aria-valuemin="0"
            aria-valuemax="100"
            @if($label)
                aria-label="{{ $label }}"
            @endif
        ></div>
    </div>
</div>
