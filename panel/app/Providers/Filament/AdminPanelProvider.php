<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandLogo(secure_asset('images/logo-vertical-easyti-cloud.png'))
            ->brandLogoHeight('2.5rem')
            ->font('Manrope')
            ->sidebarWidth('280px')
            ->colors([
                'primary' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Cyan,
            ])
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="' . Vite::asset('resources/css/filament-addon.css') . '">' .
                    // Inject Reverb config as meta tags for runtime access in echo.js
                    // REVERB_PUBLIC_* are the browser-facing address (not the internal Docker host)
                    sprintf(
                        '<meta name="reverb-key" content="%s">' .
                        '<meta name="reverb-host" content="%s">' .
                        '<meta name="reverb-port" content="%s">' .
                        '<meta name="reverb-scheme" content="%s">',
                        htmlspecialchars(config('reverb.apps.apps.0.key', ''), ENT_QUOTES),
                        htmlspecialchars(env('REVERB_PUBLIC_HOST', parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost'), ENT_QUOTES),
                        htmlspecialchars(env('REVERB_PUBLIC_PORT', '443'), ENT_QUOTES),
                        htmlspecialchars(env('REVERB_PUBLIC_SCHEME', 'https'), ENT_QUOTES),
                    )
                )
            )
            ->renderHook(
                'panels::scripts.after',
                fn (): HtmlString => new HtmlString(
                    '<script type="module" src="' . Vite::asset('resources/js/app.js') . '"></script>'
                )
            )
            ->renderHook(
                'panels::sidebar.footer',
                fn (): HtmlString => new HtmlString(
                    '<div class="px-4 py-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
                        <p class="text-xs" style="color: #475569;">EasyDeploy &middot; EasyTI Cloud</p>
                    </div>'
                )
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets customizados são auto-descobertos via $sort
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
