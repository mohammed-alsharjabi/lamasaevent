<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->login()
            ->databaseTransactions()
            ->brandName('لمسة التميز لإدارة المحتوى')
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::hex('#0ABAB5'),
                'warning' => Color::hex('#C9902E'),
            ])
            ->navigationGroups([
                'المحتوى',
                'التصنيفات',
                'المناطق والأماكن',
                'الوسائط والهيكل',
                'SEO والنشر',
                'النظام',
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<style>:root{--lams-navy:#10263f;--lams-tiffany:#0abab5}'
                    .'body{font-family:"Tahoma","Arial",sans-serif}'
                    .'.fi-sidebar{border-left:1px solid rgba(10,186,181,.18)}'
                    .'.fi-logo{color:var(--lams-navy);font-weight:800}'
                    .'.fi-sidebar-item.fi-active .fi-sidebar-item-btn{'
                    .'background:rgba(10,186,181,.12);color:var(--lams-navy)}</style>',
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
