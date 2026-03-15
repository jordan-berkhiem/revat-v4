<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'E2E');

/*
|--------------------------------------------------------------------------
| Test Groups
|--------------------------------------------------------------------------
|
| Organize tests into groups so they can be run selectively:
|   ./vendor/bin/pest --group=unit
|   ./vendor/bin/pest --group=feature
|   ./vendor/bin/pest --group=e2e
|
*/

pest()->group('unit')->in('Unit');
pest()->group('feature')->in('Feature');
pest()->group('e2e')->in('E2E');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create an Integration model with workspace_id and organization_id
 * set directly (these are not mass-assignable).
 */
function createIntegration(array $attributes): Integration
{
    $integration = new Integration(
        collect($attributes)->except(['workspace_id', 'organization_id', 'credentials', 'last_synced_at'])->all()
    );
    $integration->workspace_id = $attributes['workspace_id'];
    $integration->organization_id = $attributes['organization_id'];

    if (isset($attributes['credentials'])) {
        $integration->credentials = $attributes['credentials'];
    }
    if (isset($attributes['last_synced_at'])) {
        $integration->last_synced_at = $attributes['last_synced_at'];
    }

    $integration->save();

    return $integration;
}
