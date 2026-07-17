<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class TurnstileToken implements ValidationRule
{
    /**
     * Verify a Cloudflare Turnstile token server-side. A widget token is
     * single-use, so the frontend must reset the widget after each attempt.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            $fail(__('club_application.validation.turnstile.unavailable'));

            return;
        }

        // remoteip is intentionally omitted: behind Cloudflare/proxies the
        // resolved IP may not match the one that solved the challenge, which
        // makes siteverify reject an otherwise valid token.
        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $value,
        ]);

        if (! $response->successful() || $response->json('success') !== true) {
            $fail(__('club_application.validation.turnstile.failed'));
        }
    }
}
