<?php

use App\Services\RecaptchaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('enabled is false unless both keys are configured', function () {
    config(['services.recaptcha3.site_key' => '', 'services.recaptcha3.secret_key' => '']);
    expect(app(RecaptchaService::class)->enabled())->toBeFalse();

    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => '']);
    expect(app(RecaptchaService::class)->enabled())->toBeFalse();

    config(['services.recaptcha3.site_key' => '', 'services.recaptcha3.secret_key' => 'secret-key']);
    expect(app(RecaptchaService::class)->enabled())->toBeFalse();

    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key']);
    expect(app(RecaptchaService::class)->enabled())->toBeTrue();
});

test('the RECAPTCHA_ENABLED toggle overrides everything else, in both directions', function () {
    config([
        'services.recaptcha3.site_key' => 'site-key',
        'services.recaptcha3.secret_key' => 'secret-key',
        'services.recaptcha3.enabled' => false,
    ]);
    expect(app(RecaptchaService::class)->enabled())->toBeFalse();

    config(['services.recaptcha3.enabled' => true]);
    expect(app(RecaptchaService::class)->enabled())->toBeTrue();
});

test('verify fails closed on an empty token, without even calling Google', function () {
    Http::fake();
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key']);

    expect(app(RecaptchaService::class)->verify(null, 'login'))->toBeFalse();
    expect(app(RecaptchaService::class)->verify('', 'login'))->toBeFalse();

    Http::assertNothingSent();
});

test('verify passes when Google reports success, the right action, and a high enough score', function () {
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key']);
    Http::fake(['*' => Http::response(['success' => true, 'action' => 'login', 'score' => 0.9])]);

    expect(app(RecaptchaService::class)->verify('token', 'login'))->toBeTrue();
});

test('verify fails when the action does not match', function () {
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key']);
    Http::fake(['*' => Http::response(['success' => true, 'action' => 'some_other_form', 'score' => 0.9])]);

    expect(app(RecaptchaService::class)->verify('token', 'login'))->toBeFalse();
});

test('verify fails when the score is below the configured threshold', function () {
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key', 'services.recaptcha3.score_threshold' => 0.5]);
    Http::fake(['*' => Http::response(['success' => true, 'action' => 'login', 'score' => 0.4])]);

    expect(app(RecaptchaService::class)->verify('token', 'login'))->toBeFalse();
});

test('verify accepts a per-call threshold override', function () {
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key', 'services.recaptcha3.score_threshold' => 0.5]);
    Http::fake(['*' => Http::response(['success' => true, 'action' => 'login', 'score' => 0.4])]);

    expect(app(RecaptchaService::class)->verify('token', 'login', threshold: 0.3))->toBeTrue();
});

test('verify fails when google reports failure', function () {
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key']);
    Http::fake(['*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(app(RecaptchaService::class)->verify('token', 'login'))->toBeFalse();
});

test('verify fails closed when the request to google throws', function () {
    config(['services.recaptcha3.site_key' => 'site-key', 'services.recaptcha3.secret_key' => 'secret-key']);
    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);

    expect(app(RecaptchaService::class)->verify('token', 'login'))->toBeFalse();
});

test('siteKey returns the configured site key', function () {
    config(['services.recaptcha3.site_key' => 'my-site-key']);

    expect(app(RecaptchaService::class)->siteKey())->toBe('my-site-key');
});
