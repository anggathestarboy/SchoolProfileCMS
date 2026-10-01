<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    protected string $secretKey;

    protected float $scoreThreshold;

    public function __construct()
    {
        $this->secretKey = (string) config('services.recaptcha3.secret_key');
        $this->scoreThreshold = (float) config('services.recaptcha3.score_threshold', 0.5);
    }

    /**
     * Whether reCAPTCHA should actually run — the RECAPTCHA_ENABLED toggle
     * is on (defaults to true) AND both keys are configured. Callers should
     * check this before requiring a token — verify() itself always fails
     * closed once called, so a disabled/unconfigured environment (e.g.
     * local dev with RECAPTCHA_ENABLED=false, or blank keys) should skip
     * the check entirely rather than have every login silently pass (or
     * worse, silently block) through verify().
     */
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha3.enabled', true)
            && $this->secretKey !== ''
            && $this->siteKey() !== '';
    }

    /**
     * Verify a reCAPTCHA v3 token.
     *
     * @param  string|null  $token  Token from frontend grecaptcha.execute()
     * @param  string  $expectedAction  Action name that must match, e.g. 'contact_form'
     * @param  float|null  $threshold  Override default score threshold for this call
     */
    public function verify(?string $token, string $expectedAction, ?float $threshold = null): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false)) {
                Log::info('reCAPTCHA verification failed', [
                    'errors' => $result['error-codes'] ?? [],
                    'action' => $expectedAction,
                ]);

                return false;
            }

            if (($result['action'] ?? null) !== $expectedAction) {
                Log::warning('reCAPTCHA action mismatch', [
                    'expected' => $expectedAction,
                    'received' => $result['action'] ?? null,
                ]);

                return false;
            }

            $score = (float) ($result['score'] ?? 0);
            $minScore = $threshold ?? $this->scoreThreshold;

            if ($score < $minScore) {
                Log::info('reCAPTCHA score too low', [
                    'score' => $score,
                    'threshold' => $minScore,
                    'action' => $expectedAction,
                    'ip' => request()->ip(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA verification error: '.$e->getMessage());

            return false;
        }
    }

    public function siteKey(): string
    {
        return (string) config('services.recaptcha3.site_key');
    }
}
