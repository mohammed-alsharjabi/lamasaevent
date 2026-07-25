<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill([
                'last_login_at' => now(),
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->saveQuietly();

            $this->audit('auth.login', $event->user);
        });

        Event::listen(Logout::class, fn (Logout $event) => $this->audit(
            'auth.logout',
            $event->user,
        ));

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;
            $user = is_string($email) ? User::where('email', $email)->first() : null;

            if ($user) {
                $failures = $user->failed_login_count + 1;
                $user->forceFill([
                    'failed_login_count' => $failures,
                    'locked_until' => $failures >= 5 ? now()->addMinutes(15) : null,
                ])->saveQuietly();
            }

            $this->audit('auth.failed', $user, ['email' => $email]);
        });

        Event::listen(Lockout::class, fn (Lockout $event) => $this->audit(
            'auth.rate_limited',
            null,
            ['email' => $event->request->input('email')],
        ));
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function audit(string $action, mixed $user, array $after = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        ActivityLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'after' => $after ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
        ]);
    }
}
