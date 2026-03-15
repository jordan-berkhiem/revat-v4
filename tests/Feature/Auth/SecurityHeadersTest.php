<?php

it('includes CSP header on web responses', function () {
    $response = $this->get('/');

    $response->assertOk();

    $cspHeader = $response->headers->get('Content-Security-Policy');
    expect($cspHeader)->not->toBeNull();
    expect($cspHeader)->toContain("script-src 'self'");
    expect($cspHeader)->toContain("object-src 'none'");
});

it('sets X-Frame-Options header to DENY', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('sets X-Content-Type-Options header to nosniff', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('sets Referrer-Policy header', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('sets Permissions-Policy header', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('Permissions-Policy'))->toBe('camera=(), microphone=(), geolocation=(), payment=()');
});

it('does not set HSTS in non-production environment', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('sets HSTS header in production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});
