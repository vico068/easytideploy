<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center justify-between pb-4 border-b dark:border-gray-700">
        <div>
            <h3 class="text-lg font-medium">{{ $deployment->application->name }}</h3>
            <p class="text-sm text-gray-500">
                @if($deployment->commit_sha)
                    <span class="font-mono">{{ $deployment->short_commit_sha }}</span> -
                @endif
                {{ $deployment->commit_message ?? 'Sem mensagem de commit' }}
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <span @class([
                'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                'bg-green-100 text-green-800' => $deployment->status->value === 'running',
                'bg-yellow-100 text-yellow-800' => in_array($deployment->status->value, ['pending', 'building', 'deploying']),
                'bg-red-100 text-red-800' => $deployment->status->value === 'failed',
                'bg-gray-100 text-gray-800' => in_array($deployment->status->value, ['cancelled', 'rolled_back']),
            ])>
                {{ $deployment->status->getLabel() }}
            </span>
            @if($deployment->duration)
                <span class="text-sm text-gray-500">{{ $deployment->duration }}</span>
            @endif
        </div>
    </div>

    {{-- Logs --}}
    <div
        class="bg-gray-900 rounded-lg p-4 font-mono text-sm overflow-auto"
        style="max-height: 500px;"
    >
        @if($deployment->build_logs)
            @foreach(explode("\n", $deployment->build_logs) as $line)
                <div class="py-0.5 {{ str_contains($line, '[ERROR]') ? 'text-red-400' : (str_contains($line, '[WARN]') ? 'text-yellow-400' : 'text-gray-200') }}">
                    {{ $line }}
                </div>
            @endforeach
        @else
            <div class="text-center text-gray-500 py-8">
                <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-2" />
                <p>Nenhum log de build disponível</p>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="flex items-center justify-between pt-4 border-t dark:border-gray-700 text-sm text-gray-500">
        <div>
            Disparado por: <span class="font-medium">{{ $deployment->triggered_by }}</span>
        </div>
        <div>
            {{ $deployment->created_at->format('d/m/Y H:i:s') }}
        </div>
    </div>
</div>
