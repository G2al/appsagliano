<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->brandName('Mega Trasporti')
            ->colors([
                'primary' => Color::hex('#1A2A9C'),
            ])
            ->renderHook(
                'panels::head.start',
                fn (): string => <<<'HTML'
                    <link rel="manifest" href="/admin-manifest.json?v=7">
                    <link rel="icon" href="/admin-assets/icon-admin.png" type="image/png">
                    <link rel="apple-touch-icon" href="/admin-assets/icon-admin.png">
                    <meta name="theme-color" content="#1A2A9C">
                    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
                    <style>
                        /* Impedisce lo zoom automatico di iOS/Android quando si tocca un campo
                           di testo: i browser mobili ingrandiscono la pagina se il font di un
                           input e inferiore a 16px, indipendentemente dal meta viewport sopra. */
                        @media (max-width: 1024px) {
                            input, select, textarea {
                                font-size: 16px !important;
                            }
                        }
                    </style>
                HTML
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => <<<'HTML'
                    <script>
                        if ('serviceWorker' in navigator) {
                            window.addEventListener('load', () => {
                                navigator.serviceWorker.register('/admin-sw.js').catch(() => {});
                            });
                        }
                    </script>
                HTML
            )
            ->renderHook(
                'panels::body.end',
                function (): string {
                    $user = \Illuminate\Support\Facades\Auth::user();

                    if (! $user instanceof \App\Models\User || ! $user->canReceiveExpiryNotifications()) {
                        return '';
                    }

                    $subscribeUrl = e(route('push.subscribe'));
                    $vapidKeyUrl = e(route('push.vapid-public-key'));
                    $csrfToken = e(csrf_token());

                    return <<<HTML
                        <button id="mega-push-enable-btn" type="button" style="display:none;position:fixed;bottom:1rem;right:1rem;z-index:50;background:#1A2A9C;color:#fff;border:none;border-radius:9999px;padding:0.6rem 1.1rem;font-size:0.85rem;box-shadow:0 2px 8px rgba(0,0,0,0.25);cursor:pointer;">
                            Attiva notifiche
                        </button>
                        <script>
                        (function () {
                            function urlBase64ToUint8Array(base64String) {
                                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                                const rawData = window.atob(base64);
                                const outputArray = new Uint8Array(rawData.length);
                                for (let i = 0; i < rawData.length; ++i) {
                                    outputArray[i] = rawData.charCodeAt(i);
                                }
                                return outputArray;
                            }

                            async function subscribeToPush() {
                                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                                    return;
                                }

                                const permission = await Notification.requestPermission();
                                if (permission !== 'granted') {
                                    return;
                                }

                                const registration = await navigator.serviceWorker.ready;
                                const keyResponse = await fetch('{$vapidKeyUrl}');
                                const { publicKey } = await keyResponse.json();

                                let subscription = await registration.pushManager.getSubscription();
                                if (!subscription) {
                                    subscription = await registration.pushManager.subscribe({
                                        userVisibleOnly: true,
                                        applicationServerKey: urlBase64ToUint8Array(publicKey),
                                    });
                                }

                                const key = subscription.getKey('p256dh');
                                const auth = subscription.getKey('auth');

                                await fetch('{$subscribeUrl}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{$csrfToken}',
                                    },
                                    body: JSON.stringify({
                                        endpoint: subscription.endpoint,
                                        public_key: btoa(String.fromCharCode(...new Uint8Array(key))).replace(/\\+/g, '-').replace(/\\//g, '_').replace(/=+$/, ''),
                                        auth_token: btoa(String.fromCharCode(...new Uint8Array(auth))).replace(/\\+/g, '-').replace(/\\//g, '_').replace(/=+$/, ''),
                                        content_encoding: 'aes128gcm',
                                    }),
                                });

                                document.getElementById('mega-push-enable-btn').style.display = 'none';
                            }

                            window.addEventListener('load', async function () {
                                if (!('serviceWorker' in navigator) || !('PushManager' in window) || typeof Notification === 'undefined') {
                                    return;
                                }

                                if (Notification.permission === 'granted') {
                                    const registration = await navigator.serviceWorker.ready;
                                    const existing = await registration.pushManager.getSubscription();
                                    if (existing) {
                                        return;
                                    }
                                }

                                if (Notification.permission === 'denied') {
                                    return;
                                }

                                const btn = document.getElementById('mega-push-enable-btn');
                                btn.style.display = 'block';
                                btn.addEventListener('click', subscribeToPush);
                            });
                        })();
                        </script>
                    HTML;
                }
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
