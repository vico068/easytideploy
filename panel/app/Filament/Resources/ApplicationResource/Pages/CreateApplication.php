<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Enums\ApplicationType;
use App\Filament\Resources\ApplicationResource;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Informações Básicas')
                        ->description('Nome, tipo e URL da aplicação')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome da Aplicação')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->helperText('Nome descritivo da sua aplicação')
                                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                            Forms\Components\TextInput::make('slug')
                                ->label('Slug (URL)')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->prefix('https://')
                                ->suffix('.apps.easyti.cloud')
                                ->maxLength(255)
                                ->helperText('Este será o domínio principal da sua aplicação'),

                            Forms\Components\Select::make('type')
                                ->label('Tipo da Aplicação')
                                ->options(ApplicationType::class)
                                ->required()
                                ->live()
                                ->helperText('Escolha a linguagem/runtime da sua aplicação')
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if ($state) {
                                        $type = ApplicationType::from($state);
                                        $set('port', $type->getDefaultPort());
                                        $set('build_command', $type->getDefaultBuildCommand());
                                        $set('start_command', $type->getDefaultStartCommand());
                                    }
                                }),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Repositório Git')
                        ->description('Conecte seu repositório para deploy automático')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Forms\Components\TextInput::make('git_repository')
                                ->label('URL do repositório')
                                ->url()
                                ->placeholder('https://github.com/usuario/repositorio')
                                ->helperText('URL HTTPS do seu repositório Git (GitHub, GitLab, etc.)'),

                            Forms\Components\TextInput::make('git_branch')
                                ->label('Branch')
                                ->default('main')
                                ->helperText('Branch que será monitorada para deploys'),

                            Forms\Components\TextInput::make('git_token')
                                ->label('Token de acesso')
                                ->password()
                                ->revealable()
                                ->helperText('Personal Access Token para repositórios privados (opcional)'),

                            Forms\Components\TextInput::make('root_directory')
                                ->label('Diretório raiz')
                                ->default('/')
                                ->placeholder('/')
                                ->helperText('Use "/" para raiz ou "/backend" para subdiretório'),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Build & Deploy')
                        ->description('Comandos de compilação e inicialização')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\TextInput::make('build_command')
                                ->label('Comando de build')
                                ->placeholder('npm run build')
                                ->helperText('Comando executado antes do deploy (ex: npm run build, go build)'),

                            Forms\Components\TextInput::make('start_command')
                                ->label('Comando de inicialização')
                                ->placeholder('npm start')
                                ->helperText('Comando que inicia sua aplicação (ex: npm start, python app.py)'),

                            Forms\Components\TextInput::make('port')
                                ->label('Porta')
                                ->numeric()
                                ->default(3000)
                                ->minValue(1)
                                ->maxValue(65535)
                                ->helperText('Porta onde sua aplicação escuta (geralmente 3000, 8000 ou 8080)'),

                            Forms\Components\Toggle::make('auto_deploy')
                                ->label('Deploy automático')
                                ->helperText('Fazer deploy automaticamente ao receber push no Git')
                                ->default(true),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Recursos')
                        ->description('Limites de CPU, memória e réplicas')
                        ->icon('heroicon-o-server-stack')
                        ->schema([
                            Forms\Components\TextInput::make('replicas')
                                ->label('Réplicas')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(10)
                                ->helperText('Número de containers em execução (para balanceamento de carga)'),

                            Forms\Components\TextInput::make('cpu_limit')
                                ->label('Limite de CPU')
                                ->numeric()
                                ->default(1000)
                                ->suffix('millicores')
                                ->helperText('1000 millicores = 1 CPU core completo'),

                            Forms\Components\TextInput::make('memory_limit')
                                ->label('Limite de memória')
                                ->numeric()
                                ->default(512)
                                ->suffix('MB')
                                ->helperText('Memória RAM máxima permitida'),

                            Forms\Components\TextInput::make('health_check.path')
                                ->label('Caminho do Health Check')
                                ->default('/health')
                                ->placeholder('/health')
                                ->helperText('Endpoint HTTP para verificar se a aplicação está saudável'),

                            Forms\Components\TextInput::make('health_check.interval')
                                ->label('Intervalo do Health Check')
                                ->numeric()
                                ->default(30)
                                ->suffix('segundos')
                                ->helperText('Frequência das verificações de saúde'),
                        ])
                        ->columns(2),
                ])
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $application = $this->record;
        $suffix = config('easydeploy.domain.default_suffix', 'apps.easyti.cloud');
        $defaultDomain = sprintf('%s.%s', $application->slug, $suffix);

        // Criar domínio principal automaticamente
        $application->domains()->create([
            'domain' => $defaultDomain,
            'is_primary' => true,
            'verified' => true,
            'ssl_status' => 'pending',
            'ssl_enabled' => true,
        ]);

        // Disparar primeiro deploy automaticamente se repositório Git está configurado
        if (! empty($application->git_repository)) {
            \App\Jobs\ProcessDeploymentJob::dispatch($application);

            \Filament\Notifications\Notification::make()
                ->title('Deploy iniciado')
                ->body("O primeiro deploy de \"{$application->name}\" foi iniciado automaticamente.")
                ->success()
                ->send();
        }
    }
}
