<?php

namespace App\Filament\Resources;

use App\Enums\ServerStatus;
use App\Filament\Resources\ServerResource\Pages;
use App\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Infraestrutura';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Servidor';

    protected static ?string $pluralModelLabel = 'Servidores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do servidor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('hostname')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP público')
                            ->required()
                            ->ipv4(),

                        Forms\Components\TextInput::make('internal_ip')
                            ->label('IP interno')
                            ->ipv4(),

                        Forms\Components\TextInput::make('agent_port')
                            ->label('Porta do agent')
                            ->numeric()
                            ->default(9090)
                            ->minValue(1)
                            ->maxValue(65535),

                        Forms\Components\Select::make('status')
                            ->options(ServerStatus::class)
                            ->default('offline')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Capacidade')
                    ->schema([
                        Forms\Components\TextInput::make('max_containers')
                            ->label('Máximo de containers')
                            ->numeric()
                            ->default(50)
                            ->minValue(1),

                        Forms\Components\TextInput::make('cpu_total')
                            ->label('CPU total')
                            ->numeric()
                            ->default(8000)
                            ->suffix('millicores')
                            ->helperText('1000 = 1 CPU'),

                        Forms\Components\TextInput::make('memory_total')
                            ->label('Memória total')
                            ->numeric()
                            ->default(32768)
                            ->suffix('MB'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Labels')
                    ->schema([
                        Forms\Components\KeyValue::make('labels')
                            ->keyLabel('Chave')
                            ->valueLabel('Valor')
                            ->addActionLabel('Adicionar label')
                            ->reorderable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hostname')
                    ->searchable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP'),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('containers_count')
                    ->counts('containers')
                    ->label('Containers'),

                Tables\Columns\TextColumn::make('cpu_usage_percent')
                    ->label('CPU')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('memory_usage_percent')
                    ->label('Memória')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('last_heartbeat')
                    ->label('Último heartbeat')
                    ->dateTime('d/m H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ServerStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('maintenance')
                    ->label('Manutenção')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Server $record) => $record->update(['status' => ServerStatus::Maintenance])),

                Tables\Actions\Action::make('drain')
                    ->label('Drenar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Isso irá mover todos os containers para outros servidores.')
                    ->action(fn (Server $record) => $record->update(['status' => ServerStatus::Draining])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
