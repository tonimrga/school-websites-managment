<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dashboard login that limits failed attempts per email + IP address.
 *
 * Inactive users are rejected by the parent through User::canAccessPanel(),
 * with the same generic failure message as a wrong password.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $throttleKey = $this->getFailedLoginThrottleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, config('cms.login.max_attempts'))) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($throttleKey),
            ))?->send();

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey, config('cms.login.decay_seconds'));

            throw $exception;
        }

        if ($response !== null) {
            RateLimiter::clear($throttleKey);
        }

        return $response;
    }

    /**
     * Filament's built-in limit counts every attempt per IP. Keep it only as a
     * looser per-IP ceiling against spraying many emails; the per-email limit
     * above is the primary protection.
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null)
    {
        parent::rateLimit(30, $decaySeconds, $method ?? 'authenticate', $component);
    }

    protected function getFailedLoginThrottleKey(): string
    {
        $email = Str::lower(trim((string) ($this->data['email'] ?? '')));

        return 'dashboard-login:'.sha1($email.'|'.request()->ip());
    }
}
