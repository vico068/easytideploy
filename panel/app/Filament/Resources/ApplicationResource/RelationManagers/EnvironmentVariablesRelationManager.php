<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EnvironmentVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'environmentVariables';

    protected static ?string $title = 'Variáveis de Ambiente';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label('Chave')
                    ->required()
                    ->regex('/^[A-Z][A-Z0-9_]*$/')
                    ->helperText('Use MAIÚSCULAS e underscores (ex: DATABASE_URL)'),

                Forms\Components\Textarea::make('value')
                    ->label('Valor')
                    ->required()
                    ->rows(3),

                Forms\Components\Toggle::make('is_secret')
                    ->label('É segredo')
                    ->helperText('Valores secretos serão mascarados na interface'),

                Forms\Components\Toggle::make('is_build_time')
                    ->label('Disponível no build')
                    ->helperText('Se habilitado, estará disponível durante o processo de build'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Chave')
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('masked_value')
                    ->label('Valor')
                    ->fontFamily('mono')
                    ->limit(50),

                Tables\Columns\IconColumn::make('is_secret')
                    ->label('Segredo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_build_time')
                    ->label('Build')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),

                Tables\Actions\Action::make('bulk_import')
                    ->label('Importar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\Textarea::make('variables')
                            ->label('Variáveis')
                            ->rows(10)
                            ->helperText('Cole variáveis no formato KEY=value, uma por linha')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $lines = explode("\n", $data['variables']);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line) || ! str_contains($line, '=')) {
                                continue;
                            }

                            [$key, $value] = explode('=', $line, 2);
                            $this->getOwnerRecord()->environmentVariables()->updateOrCreate(
                                ['key' => trim($key)],
                                ['value' => trim($value)]
                            );
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
