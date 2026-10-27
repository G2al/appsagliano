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
            ->brandName('Redif')
            ->brandLogo(asset('redif-assets/logo-light.png'))
            ->darkModeBrandLogo(asset('redif-assets/logo-dark.png'))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::hex('#FC4D04'),
            ])
            ->renderHook(
                'panels::head.start',
                fn (): string => <<<'HTML'
                    <link rel="manifest" href="/admin-manifest.json?v=1">
                    <link rel="icon" href="/admin-assets/icon-admin.png" type="image/png">
                    <link rel="apple-touch-icon" href="/admin-assets/icon-admin.png">
                    <meta name="theme-color" content="#FC4D04">
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
                \Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                function (): string {
                    $user = \Illuminate\Support\Facades\Auth::user();

                    if (! $user instanceof \App\Models\User || ! $user->canReceiveExpiryNotifications()) {
                        return '';
                    }

                    $subscribeUrl = e(route('push.subscribe'));
                    $unsubscribeUrl = e(route('push.unsubscribe'));
                    $vapidKeyUrl = e(route('push.vapid-public-key'));
                    $csrfToken = e(csrf_token());

                    return <<<HTML
                        <style>
                            .mega-push-btn { color: #6b7280; }
                            .dark .mega-push-btn { color: #9ca3af; }
                            .mega-push-btn:hover { color: #374151; }
                            .dark .mega-push-btn:hover { color: #e5e7eb; }
                            .mega-push-dot { background: #ef4444; }
                            .mega-push-dot.is-active { background: #22c55e; }
                            .mega-push-toast {
                                position: fixed; top: 1rem; right: 1rem; z-index: 60;
                                background: #111827; color: #fff; padding: 0.6rem 1rem;
                                border-radius: 0.5rem; font-size: 0.85rem; box-shadow: 0 2px 10px rgba(0,0,0,0.3);
                                opacity: 0; transform: translateY(-0.5rem); transition: opacity .2s, transform .2s;
                                pointer-events: none;
                            }
                            .mega-push-toast.is-visible { opacity: 1; transform: translateY(0); }
                        </style>
                        <button
                            id="mega-push-status-btn"
                            type="button"
                            title="Notifiche push disattivate (clicca per attivarle)"
                            class="mega-push-btn"
                            style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;border-radius:9999px;background:transparent;border:none;cursor:pointer;"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <span id="mega-push-dot" class="mega-push-dot" style="position:absolute;top:0.3rem;right:0.3rem;width:0.5rem;height:0.5rem;border-radius:9999px;"></span>
                        </button>
                        <div id="mega-push-toast" class="mega-push-toast"></div>
                        <script>
                        (function () {
                            let toastTimer = null;

                            function showToast(message) {
                                const toast = document.getElementById('mega-push-toast');
                                if (!toast) return;
                                toast.textContent = message;
                                toast.classList.add('is-visible');
                                clearTimeout(toastTimer);
                                toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 3000);
                            }

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

                            function setStatus(active) {
                                const dot = document.getElementById('mega-push-dot');
                                const btn = document.getElementById('mega-push-status-btn');
                                if (!dot || !btn) return;
                                dot.classList.toggle('is-active', active);
                                btn.title = active
                                    ? 'Notifiche push attive (clicca per disattivarle)'
                                    : 'Notifiche push disattivate (clicca per attivarle)';
                            }

                            async function subscribeToPush() {
                                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                                    return;
                                }

                                const permission = await Notification.requestPermission();
                                if (permission !== 'granted') {
                                    showToast('Permesso notifiche negato dal browser.');
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

                                setStatus(true);
                                showToast('Notifiche attivate con successo.');
                            }

                            async function unsubscribeFromPush() {
                                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                                    return;
                                }

                                const registration = await navigator.serviceWorker.ready;
                                const subscription = await registration.pushManager.getSubscription();
                                if (!subscription) {
                                    setStatus(false);
                                    return;
                                }

                                const endpoint = subscription.endpoint;
                                await subscription.unsubscribe();

                                await fetch('{$unsubscribeUrl}', {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{$csrfToken}',
                                    },
                                    body: JSON.stringify({ endpoint }),
                                });

                                setStatus(false);
                                showToast('Notifiche disattivate.');
                            }

                            async function refreshStatus() {
                                if (!('serviceWorker' in navigator) || !('PushManager' in window) || typeof Notification === 'undefined') {
                                    return;
                                }

                                if (Notification.permission !== 'granted') {
                                    setStatus(false);
                                    return;
                                }

                                const registration = await navigator.serviceWorker.ready;
                                const subscription = await registration.pushManager.getSubscription();
                                setStatus(!!subscription);
                            }

                            window.addEventListener('load', function () {
                                refreshStatus();

                                const btn = document.getElementById('mega-push-status-btn');
                                if (!btn) return;

                                btn.addEventListener('click', async function () {
                                    if (typeof Notification === 'undefined' || Notification.permission === 'denied') {
                                        showToast('Notifiche bloccate dal browser. Abilitale dalle impostazioni del sito.');
                                        return;
                                    }

                                    const registration = await navigator.serviceWorker.ready;
                                    const existing = await registration.pushManager.getSubscription();

                                    if (existing) {
                                        await unsubscribeFromPush();
                                    } else {
                                        await subscribeToPush();
                                    }
                                });
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
