<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Alertas Ativos';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Alert::query()
                    ->firing()
                    ->orderByDesc('starts_at')
                    ->limit(20)
            )
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->label('Severidade')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'container_down' => 'Container Parado',
                        'container_high_cpu' => 'CPU Alto (Container)',
                        'container_high_memory' => 'Memória Alta (Container)',
                        'server_down' => 'Servidor Offline',
                        'server_high_cpu' => 'CPU Alto (Servidor)',
                        'server_high_memory' => 'Memória Alta (Servidor)',
                        'server_high_disk' => 'Disco Alto (Servidor)',
                        'deployment_failed' => 'Deploy Falhou',
                        'build_failed' => 'Build Falhou',
                        'ssl_expiring' => 'SSL Expirando',
                        'health_check_failed' => 'Health Check Falhou',
                        'failover' => 'Failover Realizado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Mensagem')
                    ->limit(60),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Iniciado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('Nenhum alerta ativo')
            ->emptyStateDescription('Tudo funcionando normalmente.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
