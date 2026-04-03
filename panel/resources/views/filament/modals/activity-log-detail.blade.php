<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Usuário</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->user?->name ?? 'Sistema' }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Data/Hora</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->created_at->format('d/m/Y H:i:s') }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Endereço IP</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->ip_address ?? '-' }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Recurso</span>
            <p class="text-sm text-gray-900 dark:text-gray-100">
                @if($record->subject_type)
                    {{ class_basename($record->subject_type) }} ({{ substr($record->subject_id, 0, 8) }}...)
                @else
                    -
                @endif
            </p>
        </div>
    </div>

    <div>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Descrição</span>
        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->description }}</p>
    </div>

    @if($record->properties && count($record->properties) > 0)
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Propriedades</span>
            <pre class="mt-1 text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-auto max-h-64">{{ json_encode($record->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif

    @if($record->user_agent)
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">User Agent</span>
            <p class="text-xs text-gray-500 dark:text-gray-400 break-all">{{ $record->user_agent }}</p>
        </div>
    @endif
</div>
