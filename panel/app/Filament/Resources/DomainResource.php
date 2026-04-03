<?php

namespace App\Filament\Resources;

use App\Enums\SslStatus;
use App\Filament\Resources\DomainResource\Pages;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Aplicações';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Domínio';

    protected static ?string $pluralModelLabel = 'Domínios';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração do domínio')
                    ->schema([
                        Forms\Components\Select::make('application_id')
                            ->label('Aplicação')
                            ->relationship('application', 'name')
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('domain')
                            ->label('Domínio')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('app.exemplo.com.br'),

                        Forms\Components\Toggle::make('is_primary')
                            ->label('Domínio principal')
                            ->helperText('O domínio principal será usado para redirecionamentos'),

                        Forms\Components\Toggle::make('ssl_enabled')
                            ->label('SSL habilitado')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Certificado SSL personalizado')
                    ->schema([
                        Forms\Components\Textarea::make('ssl_certificate')
                            ->label('Certificado')
                            ->rows(5)
                            ->placeholder('-----BEGIN CERTIFICATE-----'),

                        Forms\Components\Textarea::make('ssl_private_key')
                            ->label('Chave privada')
                            ->rows(5)
                            ->placeholder('-----BEGIN PRIVATE KEY-----'),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->description('Deixe em branco para usar Let\'s Encrypt'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label('Domínio')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Domain $record) => $record->url)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('application.name')
                    ->label('Aplicação')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean(),

                Tables\Columns\IconColumn::make('ssl_enabled')
                    ->label('SSL')
                    ->boolean(),

                Tables\Columns\TextColumn::make('ssl_status')
                    ->label('Status SSL')
                    ->badge(),

                Tables\Columns\TextColumn::make('ssl_expires_at')
                    ->label('Expira em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Domain $record) => $record->isSslExpiringSoon() ? 'warning' : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ssl_status')
                    ->label('Status SSL')
                    ->options(SslStatus::class),

                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Principal'),
            ])
            ->actions([
                Tables\Actions\Action::make('renew_ssl')
                    ->label('Renovar SSL')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Domain $record) => $record->ssl_enabled)
                    ->action(fn (Domain $record) => static::renewSsl($record)),

                Tables\Actions\Action::make('set_primary')
                    ->label('Tornar principal')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (Domain $record) => ! $record->is_primary)
                    ->action(fn (Domain $record) => $record->markAsPrimary()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }

    protected static function renewSsl(Domain $record): void
    {
        // TODO: Implement SSL renewal via OrchestratorClient
    }
}
