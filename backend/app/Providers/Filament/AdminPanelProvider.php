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
                    '<style>'
                    .'@font-face{font-family:"Cairo";font-style:normal;font-weight:400 800;'
                    .'font-display:swap;src:url("/fonts/cairo/cairo-arabic.woff2") format("woff2");'
                    .'unicode-range:U+0600-06FF,U+0750-077F,U+0870-08FF,U+200C-200E,'
                    .'U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE70-FEFC,U+1EE00-1EEFF}'
                    .'@font-face{font-family:"Cairo";font-style:normal;font-weight:400 800;'
                    .'font-display:swap;src:url("/fonts/cairo/cairo-latin.woff2") format("woff2");'
                    .'unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,'
                    .'U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,'
                    .'U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}'
                    .':root{--lams-navy:#10263f;--lams-tiffany:#0abab5;'
                    .'--font-sans:"Cairo",sans-serif;--default-font-family:"Cairo",sans-serif}'
                    .'html,body,button,input,select,textarea{font-family:"Cairo",sans-serif!important}'
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
