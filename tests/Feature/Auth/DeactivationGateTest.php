<?php

use App\Exceptions\DeactivatedUserException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('prevents deactivated user from logging in', function () {
    $user = User::factory()->create([
        'password' => 'password',
        'deactivated_at' => now(),
    ]);

    try {
        Auth::guard('web')->attempt([
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->fail('Expected DeactivatedUserException');
    } catch (DeactivatedUserException $e) {
        expect($e->getMessage())->toContain('deactivated');
    }
});

it('allows active user to log in', function () {
    $user = User::factory()->create([
        'password' => 'password',
    ]);

    $result = Auth::guard('web')->attempt([
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($result)->toBeTrue()
        ->and(Auth::guard('web')->user()->id)->toBe($user->id);
});

it('allows reactivated user to log in', function () {
    $user = User::factory()->create([
        'password' => 'password',
        'deactivated_at' => now(),
    ]);

    $user->reactivate();

    $result = Auth::guard('web')->attempt([
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($result)->toBeTrue();
});

it('does not exclude deactivated users from User::all()', function () {
    User::factory()->create();
    User::factory()->create(['deactivated_at' => now()]);

    $allUsers = User::all();

    expect($allUsers)->toHaveCount(2);
});

it('includes deactivation message about contacting administrator', function () {
    $user = User::factory()->create([
        'password' => 'password',
        'deactivated_at' => now(),
    ]);

    try {
        Auth::guard('web')->attempt([
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->fail('Expected DeactivatedUserException');
    } catch (DeactivatedUserException $e) {
        expect($e->getMessage())->toContain('organization administrator');
    }
});
