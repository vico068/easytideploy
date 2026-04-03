<?php

namespace App\Filament\Resources;

use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Log de Atividades';

    protected static ?string $navigationGroup = 'Infraestrutura';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'Atividade';

    protected static ?string $pluralModelLabel = 'Log de Atividades';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->default('Sistema')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Ação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'delete' => 'danger',
                        'deploy' => 'primary',
                        'rollback' => 'warning',
                        'stop' => 'danger',
                        'restart' => 'warning',
                        'scale' => 'info',
                        'login' => 'gray',
                        'logout' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'create' => 'Criar',
                        'update' => 'Atualizar',
                        'delete' => 'Excluir',
                        'deploy' => 'Deploy',
                        'rollback' => 'Rollback',
                        'scale' => 'Escalar',
                        'stop' => 'Parar',
                        'restart' => 'Reiniciar',
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'domain_add' => 'Add Domínio',
                        'domain_remove' => 'Rem. Domínio',
                        'ssl_renew' => 'Renovar SSL',
                        'server_drain' => 'Drenar Servidor',
                        'server_maintenance' => 'Manutenção',
                        'env_update' => 'Variáveis Env',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Recurso')
                    ->formatStateUsing(function (?string $state) {
                        if (! $state) return '-';
                        return match (class_basename($state)) {
                            'Application' => 'Aplicação',
                            'Server' => 'Servidor',
                            'Deployment' => 'Deploy',
                            'Container' => 'Container',
                            'Domain' => 'Domínio',
                            default => class_basename($state),
                        };
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Ação')
                    ->options([
                        'create' => 'Criar',
                        'update' => 'Atualizar',
                        'delete' => 'Excluir',
                        'deploy' => 'Deploy',
                        'rollback' => 'Rollback',
                        'scale' => 'Escalar',
                        'stop' => 'Parar',
                        'restart' => 'Reiniciar',
                        'login' => 'Login',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Hoje')
                    ->query(fn ($query) => $query->whereDate('created_at', today())),

                Tables\Filters\Filter::make('this_week')
                    ->label('Esta semana')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->startOfWeek())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn (ActivityLog $record) => view('filament.modals.activity-log-detail', ['record' => $record])),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs::route('/'),
        ];
    }
}
