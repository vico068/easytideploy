<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center justify-between pb-4 border-b dark:border-gray-700">
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
                <x-heroicon-m-cube class="w-5 h-5 text-gray-500" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $deployment->application->name }}</h3>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                @if($deployment->commit_sha)
                    <span class="inline-flex items-center gap-1 font-mono bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                        <x-heroicon-m-code-bracket class="w-3.5 h-3.5" />
                        {{ $deployment->short_commit_sha }}
                    </span>
                    <span class="mx-1">-</span>
                @endif
                <span class="italic">{{ $deployment->commit_message ?? 'Sem mensagem de commit' }}</span>
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <x-status-badge :status="$deployment->status" size="md" />
            @if($deployment->duration)
                <span class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg">
                    <x-heroicon-m-clock class="w-4 h-4" />
                    {{ $deployment->duration }}
                </span>
            @endif
        </div>
    </div>

    {{-- Logs usando component terminal-viewer --}}
    @if($deployment->build_logs)
        @php
            $logsArray = array_filter(explode("\n", $deployment->build_logs));
            $logsFormatted = array_map(function($line) {
                return (object)[
                    'timestamp' => now(),
                    'level' => (object)[
                        'value' => str_contains($line, '[ERROR]') ? 'error' : (str_contains($line, '[WARN]') ? 'warning' : 'info')
                    ],
                    'message' => $line,
                    'container' => null
                ];
            }, $logsArray);
        @endphp

        <x-terminal-viewer
            :logs="collect($logsFormatted)"
            :searchQuery="null"
            :autoScroll="false"
            maxHeight="500px"
            emptyMessage="Nenhum log de build disponível"
            emptyIcon="heroicon-o-document-text"
        />
    @else
        <div class="bg-gray-900 rounded-lg p-12 text-center">
            <x-heroicon-o-document-text class="w-16 h-16 mx-auto mb-3 text-gray-600 opacity-50" />
            <p class="text-gray-400 font-medium">Nenhum log de build disponível</p>
            <p class="text-gray-500 text-sm mt-1">Os logs aparecerão aqui durante o processo de build</p>
        </div>
    @endif

    {{-- Footer --}}
    <div class="flex items-center justify-between pt-4 border-t dark:border-gray-700">
        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <span class="font-medium text-gray-500 dark:text-gray-500">Disparado por:</span>
            <span class="inline-flex items-center gap-1.5 bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-md font-medium">
                @php
                    $triggerIcon = match($deployment->triggered_by) {
                        'webhook' => 'heroicon-m-code-bracket',
                        'manual' => 'heroicon-m-user',
                        'api' => 'heroicon-m-command-line',
                        default => 'heroicon-m-question-mark-circle'
                    };
                @endphp
                <x-dynamic-component :component="$triggerIcon" class="w-4 h-4" />
                {{ $deployment->triggered_by }}
            </span>
        </div>
        <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
            <x-heroicon-m-calendar class="w-4 h-4" />
            <time datetime="{{ $deployment->created_at->toIso8601String() }}">
                {{ $deployment->created_at->format('d/m/Y H:i:s') }}
            </time>
            <span class="text-gray-400 mx-1">•</span>
            <span class="text-gray-500">{{ $deployment->created_at->diffForHumans() }}</span>
        </div>
    </div>
</div>
