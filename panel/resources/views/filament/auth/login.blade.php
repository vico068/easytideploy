<x-filament-panels::page.simple>
    {{-- Fundo com grid pattern --}}
    <style>
        .login-bg {
            background-color: #080c14;
            background-image:
                linear-gradient(rgba(13, 139, 250, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(13, 139, 250, 0.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }
        .login-glow {
            background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(13, 139, 250, 0.12) 0%, transparent 70%);
        }
        .login-card {
            background: rgba(17, 24, 39, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.5rem;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .login-gradient-text {
            background: linear-gradient(135deg, #60b8ff 0%, #06b6d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>

    {{-- Layout fullscreen --}}
    <div class="fixed inset-0 login-bg z-0"></div>
    <div class="fixed inset-0 login-glow z-0 pointer-events-none"></div>

    {{-- Card central --}}
    <div class="relative z-10 w-full max-w-md mx-auto px-4">

        {{-- Header da marca --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4
                        bg-gradient-to-br from-brand-600 to-cyan-500 shadow-xl shadow-brand-500/30">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold font-display text-white tracking-tight">
                Easy<span class="login-gradient-text">Deploy</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1.5 tracking-wide">Deploy. Scale. Monitor.</p>
        </div>

        {{-- Form card --}}
        <div class="login-card p-8 shadow-2xl">
            <div class="mb-6">
                <h2 class="text-base font-semibold text-white">Entrar na plataforma</h2>
                <p class="text-sm text-slate-400 mt-1">Acesse o painel de controle EasyTI Cloud</p>
            </div>

            @if (filament()->hasRegistration())
                <x-slot name="subheading">
                    {{ __('filament-panels::pages/auth/login.actions.register.before') }}
                    {{ $this->registerAction }}
                </x-slot>
            @endif

            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                    class="mt-6"
                />
            </x-filament-panels::form>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-6">
            <p class="text-xs text-slate-600">
                EasyTI Cloud &mdash; Plataforma PaaS Self-hosted
            </p>
        </div>
    </div>
</x-filament-panels::page.simple>
