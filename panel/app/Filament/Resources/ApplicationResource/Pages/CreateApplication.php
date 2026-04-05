<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Enums\ApplicationType;
use App\Filament\Resources\ApplicationResource;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                        ->description('Nome, runtime e URL da aplicação')
                        ->icon('heroicon-o-cube')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome da Aplicação')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-o-tag')
                                ->placeholder('Ex: API Produção, Frontend Web, Worker Queue')
                                ->helperText('Nome exibido no dashboard e nas notificações de deploy')
                                ->hint('Use nomes descritivos como "API Users v2" ou "Frontend Marketing"')
                                ->hintIcon('heroicon-m-light-bulb')
                                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                            Forms\Components\TextInput::make('slug')
                                ->label('Subdomínio')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->prefix('https://')
                                ->suffix('.apps.easyti.cloud')
                                ->maxLength(255)
                                ->helperText('Gerado automaticamente — pode personalizar com letras, números e hífens')
                                ->hint('Será seu domínio público após o deploy')
                                ->hintIcon('heroicon-o-globe-alt'),

                            Forms\Components\Select::make('type')
                                ->label('Runtime / Linguagem')
                                ->options(ApplicationType::class)
                                ->required()
                                ->live()
                                ->native(false)
                                ->prefixIcon('heroicon-o-cpu-chip')
                                ->helperText('Porta e comandos padrão são preenchidos automaticamente')
                                ->hint('Node.js · Python · PHP · Go · Ruby · Java')
                                ->hintIcon('heroicon-m-sparkles')
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
                        ->description('Conecte seu repositório para deploys automáticos')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Forms\Components\TextInput::make('git_repository')
                                ->label('URL do repositório')
                                ->url()
                                ->prefixIcon('heroicon-o-code-bracket-square')
                                ->placeholder('https://github.com/sua-org/seu-projeto')
                                ->helperText('GitHub, GitLab, Bitbucket ou qualquer servidor Git com HTTPS')
                                ->hint('Use HTTPS — autenticação via token abaixo')
                                ->hintIcon('heroicon-o-lock-closed')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('git_branch')
                                ->label('Branch de deploy')
                                ->default('main')
                                ->prefixIcon('heroicon-o-bookmark')
                                ->placeholder('main')
                                ->helperText('Pushes nesta branch disparam deploys automáticos'),

                            Forms\Components\TextInput::make('root_directory')
                                ->label('Diretório raiz')
                                ->default('/')
                                ->prefixIcon('heroicon-o-folder')
                                ->placeholder('/')
                                ->helperText('Use "/" para raiz ou "/api" para monorepos'),

                            Forms\Components\TextInput::make('git_token')
                                ->label('Token de acesso (PAT)')
                                ->password()
                                ->revealable()
                                ->prefixIcon('heroicon-o-key')
                                ->placeholder('ghp_xxxxxxxxxxxxxxxxxxxx')
                                ->helperText('Personal Access Token — obrigatório para repositórios privados')
                                ->hint('Armazenado com criptografia AES-256')
                                ->hintIcon('heroicon-o-shield-check')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Build & Deploy')
                        ->description('Comandos de compilação e inicialização')
                        ->icon('heroicon-o-rocket-launch')
                        ->schema([
                            Forms\Components\TextInput::make('build_command')
                                ->label('Comando de build')
                                ->prefixIcon('heroicon-o-wrench-screwdriver')
                                ->placeholder('npm run build')
                                ->helperText('Executado antes de iniciar — compilações, bundling, migrações')
                                ->hint('Deixe vazio se não houver etapa de build')
                                ->hintIcon('heroicon-o-information-circle'),

                            Forms\Components\TextInput::make('start_command')
                                ->label('Comando de inicialização')
                                ->prefixIcon('heroicon-o-play')
                                ->placeholder('npm start')
                                ->helperText('Mantém a aplicação em execução contínua no container'),

                            Forms\Components\TextInput::make('port')
                                ->label('Porta da aplicação')
                                ->numeric()
                                ->default(3000)
                                ->minValue(1)
                                ->maxValue(65535)
                                ->prefixIcon('heroicon-o-signal')
                                ->suffix('TCP')
                                ->helperText('Porta interna onde a app escuta — exposta via Traefik')
                                ->hint('Node: 3000 · Python/PHP: 8000 · Go/Java: 8080')
                                ->hintIcon('heroicon-m-light-bulb'),

                            Forms\Components\Toggle::make('auto_deploy')
                                ->label('Deploy automático via git push')
                                ->helperText('Acionar deploy ao detectar novo push no branch configurado')
                                ->default(true)
                                ->onIcon('heroicon-m-bolt')
                                ->offIcon('heroicon-m-bolt-slash')
                                ->onColor('success'),
                        ])
                        ->columns(2),

                    Wizard\Step::make('Recursos')
                        ->description('CPU, memória, réplicas e health check')
                        ->icon('heroicon-o-server-stack')
                        ->schema(array_merge(
                            ApplicationResource::resourcesSchema(auth()->user()),
                            [
                                Forms\Components\TextInput::make('health_check.path')
                                    ->label('Endpoint de health check')
                                    ->default('/health')
                                    ->prefixIcon('heroicon-o-heart')
                                    ->placeholder('/health')
                                    ->helperText('Deve retornar HTTP 200 quando a aplicação estiver operacional')
                                    ->hint('Crie um endpoint simples que retorne HTTP 200')
                                    ->hintIcon('heroicon-m-light-bulb'),

                                Forms\Components\TextInput::make('health_check.interval')
                                    ->label('Intervalo de verificação')
                                    ->numeric()
                                    ->default(30)
                                    ->prefixIcon('heroicon-o-clock')
                                    ->suffix('segundos')
                                    ->helperText('Frequência com que o sistema verifica se a aplicação responde'),
                            ]
                        ))
                        ->columns(2),
                ])
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if (! $user->is_admin && ! $user->canCreateApplication()) {
            $limits = $user->getPlanLimits();

            Notification::make()
                ->title('Limite do plano atingido')
                ->body("Seu plano {$user->plan->getLabel()} permite no máximo {$limits['max_applications']} aplicações.")
                ->danger()
                ->send();

            $this->halt();
        }

        $data['user_id'] = $user->id;

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
