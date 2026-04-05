{{--
    Terminal Viewer Component

    Props:
    - logs (array|Collection): Logs para exibir
    - searchQuery (string|null): Query de busca para highlight
    - autoScroll (bool): Auto-scroll até o final
    - maxHeight (string): Altura máxima do viewer (CSS)
    - emptyMessage (string): Mensagem quando não há logs
    - emptyIcon (string): Ícone para estado vazio
--}}

@props([
    'logs' => [],
    'searchQuery' => null,
    'autoScroll' => false,
    'maxHeight' => '700px',
    'emptyMessage' => 'Nenhum log disponível',
    'emptyIcon' => 'heroicon-o-document-text',
])

<div
    {{ $attributes->merge([
        'class' => 'terminal-container bg-slate-950 rounded-xl p-4 font-mono text-sm overflow-auto border border-white/[0.07] scrollbar-thin'
    ]) }}
    style="max-height: {{ $maxHeight }};"
    role="log"
    aria-label="Log viewer"
    aria-live="polite"
    @if($autoScroll)
        x-data="{ scrollToBottom: true }"
        x-effect="if(scrollToBottom) $el.scrollTop = $el.scrollHeight"
    @endif
    tabindex="0"
>
    @forelse($logs as $log)
        {{-- Se é um objeto de log estruturado (com propriedades) --}}
        @if(is_object($log) && isset($log->message))
            <div class="flex py-0.5 hover:bg-slate-800/60 rounded px-2 group items-start transition-colors duration-150">
                {{-- Timestamp --}}
                @if(isset($log->timestamp))
                    <span class="text-slate-500 w-44 flex-shrink-0 select-none">
                        {{ $log->timestamp->format('Y-m-d H:i:s.v') }}
                    </span>
                @endif

                {{-- Level --}}
                @if(isset($log->level))
                    <span @class([
                        'w-20 flex-shrink-0 text-center font-medium',
                        'text-gray-500' => $log->level->value === 'debug',
                        'text-cyan-400' => $log->level->value === 'info',
                        'text-yellow-400' => $log->level->value === 'warning',
                        'text-red-400' => $log->level->value === 'error',
                        'text-red-600 font-bold' => $log->level->value === 'critical',
                    ])>
                        [{{ strtoupper($log->level->value) }}]
                    </span>
                @endif

                {{-- Container ID --}}
                @if(isset($log->container))
                    <span class="text-cyan-600 w-24 flex-shrink-0 text-xs truncate" title="{{ $log->container_id ?? '' }}">
                        [{{ $log->container->short_container_id ?? substr($log->container_id ?? '', 0, 8) }}]
                    </span>
                @endif

                {{-- Mensagem com highlight de busca --}}
                <span class="text-slate-200 flex-1 break-all">
                    @if($searchQuery && $searchQuery !== '')
                        {!! str_replace(
                            e($searchQuery),
                            '<mark class="bg-yellow-500/30 text-yellow-200 rounded px-0.5 ring-1 ring-yellow-500/50">'.e($searchQuery).'</mark>',
                            e($log->message)
                        ) !!}
                    @else
                        {{ $log->message }}
                    @endif
                </span>
            </div>

        {{-- Se é uma string simples (log de build) --}}
        @elseif(is_string($log))
            <div @class([
                'py-0.5 transition-colors duration-150',
                'text-red-400 font-medium' => str_contains($log, '[ERROR]') || str_contains($log, 'ERROR'),
                'text-yellow-400' => str_contains($log, '[WARN]') || str_contains($log, 'WARN'),
                'text-cyan-400' => str_contains($log, '[INFO]') || str_contains($log, 'INFO'),
                'text-green-400' => str_contains($log, '[SUCCESS]') || str_contains($log, 'SUCCESS'),
                'text-slate-200' => !str_contains($log, '[ERROR]') && !str_contains($log, '[WARN]') && !str_contains($log, '[INFO]') && !str_contains($log, '[SUCCESS]'),
            ])>
                @if($searchQuery && $searchQuery !== '')
                    {!! str_replace(
                        e($searchQuery),
                        '<mark class="bg-yellow-500/30 text-yellow-200 rounded px-0.5 ring-1 ring-yellow-500/50">'.e($searchQuery).'</mark>',
                        e($log)
                    ) !!}
                @else
                    {{ $log }}
                @endif
            </div>
        @endif
    @empty
        {{-- Estado vazio --}}
        <div class="text-center text-slate-600 py-12">
            @svg($emptyIcon, 'w-12 h-12 mx-auto mb-2 opacity-50')
            <p class="font-sans">{{ $emptyMessage }}</p>
            @if($slot->isEmpty() === false)
                {{ $slot }}
            @else
                <p class="text-xs mt-1 font-sans">Os logs aparecerão aqui quando houver atividade</p>
            @endif
        </div>
    @endforelse
</div>
