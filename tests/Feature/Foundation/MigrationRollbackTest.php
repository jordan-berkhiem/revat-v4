<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('can rollback and re-migrate foundation migrations cleanly', function () {
    // Verify tables exist before rollback
    expect(Schema::hasTable('workspaces'))->toBeTrue();
    expect(Schema::hasTable('organization_user'))->toBeTrue();
    expect(Schema::hasTable('workspace_user'))->toBeTrue();

    // Rollback all custom migrations (1 last_summarized_at + 6 summary tables + 2 incremental processing + 3 ETL columns on fact tables + 3 archive tables + 1 identity_hashes + 3 raw data + 2 extraction pipeline + 1 integrations + 1 last_active_at + 1 effort_id on campaign_emails + 4 attribution + 3 fact tables + 3 PIE + 3 billing + 1 invitations + 2 admin + 5 foundation + 1 audit_logs + 2 user_2fa)
    Artisan::call('migrate:rollback', ['--step' => 48]);

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
