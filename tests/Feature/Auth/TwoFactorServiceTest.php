<?php

use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Models\Admin;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->service = app(TwoFactorService::class);
});

it('generates a valid TOTP secret', function () {
    $secret = $this->service->generateSecret();

    expect($secret)->toBeString()
        ->and(strlen($secret))->toBeGreaterThanOrEqual(16);
});

it('enables 2FA with a valid code', function () {
    $admin = Admin::factory()->create();
    $secret = $this->service->generateSecret();
    $admin->setTwoFactorSecret($secret);

    $google2fa = new Google2FA;
    $validCode = $google2fa->getCurrentOtp($secret);

    $result = $this->service->enable($admin, $validCode);

    expect($result)->toBeTrue()
        ->and($admin->hasTwoFactorEnabled())->toBeTrue()
        ->and($admin->getTwoFactorConfirmedAt())->not->toBeNull();
});

it('fails to enable 2FA with an invalid code', function () {
    $admin = Admin::factory()->create();
    $secret = $this->service->generateSecret();
    $admin->setTwoFactorSecret($secret);

    $result = $this->service->enable($admin, '000000');

    expect($result)->toBeFalse()
        ->and($admin->hasTwoFactorEnabled())->toBeFalse();
});

it('disables 2FA clearing secret and confirmed_at', function () {
    $admin = Admin::factory()->create();
    $secret = $this->service->generateSecret();
    $admin->setTwoFactorSecret($secret);

    $google2fa = new Google2FA;
    $this->service->enable($admin, $google2fa->getCurrentOtp($secret));

    expect($admin->hasTwoFactorEnabled())->toBeTrue();

    $this->service->disable($admin);

    expect($admin->hasTwoFactorEnabled())->toBeFalse()
        ->and($admin->getTwoFactorSecret())->toBeNull()
        ->and($admin->getTwoFactorConfirmedAt())->toBeNull();
});

it('returns correct hasTwoFactorEnabled boolean', function () {
    $admin = Admin::factory()->create();

    expect($admin->hasTwoFactorEnabled())->toBeFalse();

    $admin->two_factor_confirmed_at = now();
    $admin->save();

    expect($admin->hasTwoFactorEnabled())->toBeTrue();
});

it('generates recovery codes that are single-use', function () {
    $result = $this->service->generateRecoveryCodes();

    expect($result['plain'])->toHaveCount(8)
        ->and($result['hashed'])->toHaveCount(8);

    // Each plain code should match XXXX-XXXX format
    foreach ($result['plain'] as $code) {
        expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    }

    // Each hashed code should be a valid bcrypt hash
    foreach ($result['hashed'] as $index => $hashedCode) {
        expect(Hash::check($result['plain'][$index], $hashedCode))->toBeTrue();
    }
});

it('verifies and consumes a recovery code', function () {
    $admin = Admin::factory()->create();
    $codes = $this->service->generateRecoveryCodes();

    // Store hashed codes on the admin
    $admin->two_factor_recovery_codes = json_encode($codes['hashed']);
    $admin->save();

    $codeToUse = $codes['plain'][0];

    // First use should succeed
    $result = $this->service->verifyRecoveryCode($admin, $codeToUse);
    expect($result)->toBeTrue();

    // Second use should fail (consumed)
    $admin->refresh();
    $result = $this->service->verifyRecoveryCode($admin, $codeToUse);
    expect($result)->toBeFalse();

    // Other codes should still work
    $result = $this->service->verifyRecoveryCode($admin, $codes['plain'][1]);
    expect($result)->toBeTrue();
});

it('redirects when 2FA enabled but not verified in session', function () {
    $admin = Admin::factory()->create([
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'test-secret',
    ]);

    // Create a test route behind the middleware
    Route::middleware(['web', EnsureAdminTwoFactor::class])->get('/_test/admin-protected', function () {
        return 'protected content';
    });

    $this->actingAs($admin, 'admin')
        ->get('/_test/admin-protected')
        ->assertRedirect(route('admin.two-factor.challenge'));
});

it('passes when 2FA is not enabled', function () {
    $admin = Admin::factory()->create();

    Route::middleware(['web', EnsureAdminTwoFactor::class])->get('/_test/admin-no-2fa', function () {
        return 'protected content';
    });

    $this->actingAs($admin, 'admin')
        ->get('/_test/admin-no-2fa')
        ->assertOk();
});

it('passes when 2FA is verified in session', function () {
    $admin = Admin::factory()->create([
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'test-secret',
    ]);

    Route::middleware(['web', EnsureAdminTwoFactor::class])->get('/_test/admin-2fa-verified', function () {
        return 'protected content';
    });

    $this->actingAs($admin, 'admin')
        ->withSession(['admin_2fa_verified' => true])
        ->get('/_test/admin-2fa-verified')
        ->assertOk();
});
