<?php

use App\Enums\SupportLevel;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('creates admin with support levels', function () {
    $admin = Admin::factory()->create(['support_level' => SupportLevel::Manager]);

    expect($admin->support_level)->toBe(SupportLevel::Manager);
});

it('casts support_level to SupportLevel enum', function () {
    $admin = Admin::factory()->create();

    expect($admin->support_level)->toBeInstanceOf(SupportLevel::class)
        ->and($admin->support_level)->toBe(SupportLevel::Agent);
});

it('respects SupportLevel isAtLeast hierarchy', function () {
    expect(SupportLevel::Super->isAtLeast(SupportLevel::Super))->toBeTrue()
        ->and(SupportLevel::Super->isAtLeast(SupportLevel::Manager))->toBeTrue()
        ->and(SupportLevel::Super->isAtLeast(SupportLevel::Agent))->toBeTrue()
        ->and(SupportLevel::Manager->isAtLeast(SupportLevel::Super))->toBeFalse()
        ->and(SupportLevel::Manager->isAtLeast(SupportLevel::Manager))->toBeTrue()
        ->and(SupportLevel::Manager->isAtLeast(SupportLevel::Agent))->toBeTrue()
        ->and(SupportLevel::Agent->isAtLeast(SupportLevel::Super))->toBeFalse()
        ->and(SupportLevel::Agent->isAtLeast(SupportLevel::Manager))->toBeFalse()
        ->and(SupportLevel::Agent->isAtLeast(SupportLevel::Agent))->toBeTrue();
});

it('authenticates admin via admin guard', function () {
    $admin = Admin::factory()->create([
        'password' => 'secret-password',
    ]);

    $result = Auth::guard('admin')->attempt([
        'email' => $admin->email,
        'password' => 'secret-password',
    ]);

    expect($result)->toBeTrue()
        ->and(Auth::guard('admin')->user()->id)->toBe($admin->id);
});

it('hashes admin password', function () {
    $admin = Admin::factory()->create([
        'password' => 'plain-text-password',
    ]);

    expect($admin->password)->not->toBe('plain-text-password')
        ->and(Hash::check('plain-text-password', $admin->password))->toBeTrue();
});

it('encrypts two_factor_secret', function () {
    $admin = Admin::factory()->create();
    $admin->two_factor_secret = 'my-secret-key';
    $admin->save();

    // Retrieve raw value from database - should be encrypted (not plain text)
    $rawValue = DB::table('admins')
        ->where('id', $admin->id)
        ->value('two_factor_secret');

    expect($rawValue)->not->toBe('my-secret-key');

    // But accessing via model should decrypt
    $admin->refresh();
    expect($admin->two_factor_secret)->toBe('my-secret-key');
});

it('returns correct isDeactivated boolean', function () {
    $active = Admin::factory()->create();
    $deactivated = Admin::factory()->deactivated()->create();

    expect($active->isDeactivated())->toBeFalse()
        ->and($deactivated->isDeactivated())->toBeTrue();
});

it('toggles deactivated_at with deactivate and reactivate', function () {
    $admin = Admin::factory()->create();

    expect($admin->isDeactivated())->toBeFalse();

    $admin->deactivate();
    expect($admin->isDeactivated())->toBeTrue()
        ->and($admin->deactivated_at)->not->toBeNull();

    $admin->reactivate();
    expect($admin->isDeactivated())->toBeFalse()
        ->and($admin->deactivated_at)->toBeNull();
});

it('prevents deactivated admin from accessing filament panel', function () {
    $admin = Admin::factory()->deactivated()->create();

    expect($admin->canAccessPanel(Filament::getDefaultPanel()))->toBeFalse();
});

it('allows active admin to access filament panel', function () {
    $admin = Admin::factory()->create();

    expect($admin->canAccessPanel(Filament::getDefaultPanel()))->toBeTrue();
});

it('does not authenticate admin via web guard', function () {
    $admin = Admin::factory()->create([
        'password' => 'secret-password',
    ]);

    $result = Auth::guard('web')->attempt([
        'email' => $admin->email,
        'password' => 'secret-password',
    ]);

    expect($result)->toBeFalse();
});
