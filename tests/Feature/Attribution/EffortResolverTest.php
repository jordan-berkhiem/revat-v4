<?php

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Workspace;
use App\Services\EffortResolver;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    // WorkspaceObserver auto-creates default Program + Initiative
    $this->defaultInitiative = Initiative::where('workspace_id', $this->workspace->id)
        ->default()
        ->firstOrFail();

    $this->resolver = app(EffortResolver::class);
});

it('mapped: creates effort with ak_{id} code under default initiative', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Mapped Connector',
        'type' => 'mapped',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'campaignid']],
    ]);

    $key = AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'test@example.com'),
        'key_value' => 'test@example.com',
    ]);

    $this->resolver->resolveEfforts($connector);

    $key->refresh();
    expect($key->effort_id)->not->toBeNull();

    $effort = Effort::find($key->effort_id);
    expect($effort->code)->toBe("ak_{$key->id}");
    expect($effort->name)->toBe("ak_{$key->id}");
    expect($effort->initiative_id)->toBe($this->defaultInitiative->id);
    expect($effort->auto_generated)->toBeTrue();
});

it('mapped: sets effort_id on attribution_key', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Mapped Connector',
        'type' => 'mapped',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'campaignid']],
    ]);

    $key = AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'alice@example.com'),
        'key_value' => 'alice@example.com',
    ]);

    $this->resolver->resolveEfforts($connector);

    $key->refresh();
    expect($key->effort_id)->not->toBeNull();
    expect(Effort::find($key->effort_id))->not->toBeNull();
});

it('mapped: idempotent — skips already-resolved keys', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Mapped Connector',
        'type' => 'mapped',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'campaignid']],
    ]);

    $key = AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'bob@example.com'),
        'key_value' => 'bob@example.com',
    ]);

    $this->resolver->resolveEfforts($connector);
    $effortCountAfterFirst = Effort::count();

    $this->resolver->resolveEfforts($connector);
    $effortCountAfterSecond = Effort::count();

    expect($effortCountAfterSecond)->toBe($effortCountAfterFirst);
});

it('mapped: effort description matches key_value', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Mapped Connector',
        'type' => 'mapped',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'campaignid']],
    ]);

    $key = AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'val1|val2'),
        'key_value' => 'val1|val2',
    ]);

    $this->resolver->resolveEfforts($connector);

    $key->refresh();
    $effort = Effort::find($key->effort_id);
    expect($effort->description)->toBe('val1|val2');
});

it('simple: looks up existing effort by code', function () {
    $existingEffort = Effort::create([
        'workspace_id' => $this->workspace->id,
        'initiative_id' => $this->defaultInitiative->id,
        'name' => 'Promo Campaign',
        'code' => 'PROMO1',
        'channel_type' => 'email',
        'status' => 'active',
    ]);

    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Simple Connector',
        'type' => 'simple',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => ['effort_code_field' => 'campaignid'],
    ]);

    $key = AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'PROMO1'),
        'key_value' => 'PROMO1',
    ]);

    $this->resolver->resolveEfforts($connector);

    $key->refresh();
    expect($key->effort_id)->toBe($existingEffort->id);
});

it('simple: creates effort when code not found', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Simple Connector',
        'type' => 'simple',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => ['effort_code_field' => 'campaignid'],
    ]);

    $key = AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'NEW_CODE'),
        'key_value' => 'NEW_CODE',
    ]);

    $this->resolver->resolveEfforts($connector);

    $key->refresh();
    expect($key->effort_id)->not->toBeNull();

    $effort = Effort::find($key->effort_id);
    expect($effort->code)->toBe('NEW_CODE');
    expect($effort->auto_generated)->toBeTrue();
});

it('edge: throws when default Initiative missing', function () {
    // Create a workspace without observer (delete the auto-created initiative)
    $otherWorkspace = new Workspace(['name' => 'No Default']);
    $otherWorkspace->organization_id = $this->org->id;
    $otherWorkspace->is_default = false;
    $otherWorkspace->save();

    // Delete the auto-created default initiative (and its program)
    Initiative::where('workspace_id', $otherWorkspace->id)->forceDelete();
    Program::where('workspace_id', $otherWorkspace->id)->forceDelete();

    $connector = AttributionConnector::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Orphan Connector',
        'type' => 'mapped',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'campaignid']],
    ]);

    AttributionKey::create([
        'workspace_id' => $otherWorkspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', 'test'),
        'key_value' => 'test',
    ]);

    expect(fn () => $this->resolver->resolveEfforts($connector))
        ->toThrow(RuntimeException::class, 'No default Initiative found');
});

it('edge: throws when simple connector key_value exceeds 50 chars', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Simple Connector',
        'type' => 'simple',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => ['effort_code_field' => 'campaignid'],
    ]);

    $longValue = str_repeat('a', 51);
    AttributionKey::create([
        'workspace_id' => $this->workspace->id,
        'connector_id' => $connector->id,
        'key_hash' => hash('sha256', $longValue),
        'key_value' => $longValue,
    ]);

    expect(fn () => $this->resolver->resolveEfforts($connector))
        ->toThrow(RuntimeException::class, 'exceeds 50 chars');
});
