<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Enums\SslStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $title = 'Domínios';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->label('Domínio')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('app.exemplo.com.br'),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Domínio principal'),

                Forms\Components\Toggle::make('ssl_enabled')
                    ->label('SSL habilitado')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label('Domínio')
                    ->searchable()
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab(),

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
                    ->date('d/m/Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('set_primary')
                    ->label('Tornar principal')
                    ->icon('heroicon-o-star')
                    ->visible(fn ($record) => ! $record->is_primary)
                    ->action(fn ($record) => $record->markAsPrimary()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
