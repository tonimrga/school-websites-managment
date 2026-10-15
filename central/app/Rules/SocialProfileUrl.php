<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts an https link on one of the given sites (or their subdomains, such
 * as www. or m.), so a link in the Facebook field really goes to Facebook.
 */
class SocialProfileUrl implements ValidationRule
{
    /**
     * @param  list<string>  $hosts
     */
    public function __construct(
        protected string $network,
        protected array $hosts,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! is_string($value) || ! $this->isAllowed($value)) {
            $fail("Unesite https poveznicu na {$this->network} (npr. https://{$this->hosts[0]}/…).");
        }
    }

    protected function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        foreach ($this->hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
