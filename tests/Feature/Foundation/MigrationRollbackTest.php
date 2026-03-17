<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('can rollback and re-migrate foundation migrations cleanly', function () {
    // Verify tables exist before rollback
    expect(Schema::hasTable('workspaces'))->toBeTrue();
    expect(Schema::hasTable('organization_user'))->toBeTrue();
    expect(Schema::hasTable('workspace_user'))->toBeTrue();

    // Rollback all custom migrations (everything after the 3 Laravel default migrations)
    $totalMigrations = count(File::files(database_path('migrations')));
    $laravelDefaults = 3; // users, cache, jobs
    Artisan::call('migrate:rollback', ['--step' => $totalMigrations - $laravelDefaults]);

    // Verify pivot and workspaces tables are gone
    expect(Schema::hasTable('workspace_user'))->toBeFalse();
    expect(Schema::hasTable('organization_user'))->toBeFalse();
    expect(Schema::hasTable('workspaces'))->toBeFalse();

    // Verify users table lost the added columns
    expect(Schema::hasColumn('users', 'current_organization_id'))->toBeFalse();
    expect(Schema::hasColumn('users', 'deactivated_at'))->toBeFalse();

    // Re-migrate
    Artisan::call('migrate');

    // Verify tables are back
    expect(Schema::hasTable('workspaces'))->toBeTrue();
    expect(Schema::hasTable('organization_user'))->toBeTrue();
    expect(Schema::hasTable('workspace_user'))->toBeTrue();
    expect(Schema::hasColumn('users', 'current_organization_id'))->toBeTrue();
    expect(Schema::hasColumn('users', 'deactivated_at'))->toBeTrue();
});
