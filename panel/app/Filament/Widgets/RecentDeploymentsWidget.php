<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DeploymentResource;
use App\Models\Deployment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentDeploymentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Deployments Recentes';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Deployment::query()
                    ->with(['application'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('application.name')
                    ->label('Aplicação')
                    ->searchable(),

                Tables\Columns\TextColumn::make('short_commit_sha')
                    ->label('Commit')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('commit_message')
                    ->label('Mensagem')
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Disparado por')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duração'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->url(fn (Deployment $record) => DeploymentResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
