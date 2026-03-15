<?php

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\AttributionResult;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\ConversionSale;
use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Workspace;
use App\Services\AttributionEngine;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    // PIE hierarchy
    $program = Program::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Program',
        'code' => 'TP',
    ]);
    $initiative = Initiative::create([
        'workspace_id' => $this->workspace->id,
        'program_id' => $program->id,
        'name' => 'Test Initiative',
        'code' => 'TI',
    ]);
    $this->effort1 = Effort::create([
        'workspace_id' => $this->workspace->id,
        'initiative_id' => $initiative->id,
        'name' => 'Effort A',
        'code' => 'EA',
        'channel_type' => 'email',
        'status' => 'active',
    ]);
    $this->effort2 = Effort::create([
        'workspace_id' => $this->workspace->id,
        'initiative_id' => $initiative->id,
        'name' => 'Effort B',
        'code' => 'EB',
        'channel_type' => 'email',
        'status' => 'active',
    ]);

    $this->connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
    ]);

    $this->engine = app(AttributionEngine::class);
});

/**
 * Helper to set up a key + record key linkage.
 */
function linkRecordToKey(int $connectorId, int $workspaceId, string $email, string $recordType, int $recordId): void
{
    $binaryHash = hash('sha256', $email, true);
    $hexHash = hash('sha256', $email);

    // Use raw query to find by binary hash (SQLite binary comparison workaround)
    $key = AttributionKey::where('connector_id', $connectorId)
        ->whereRaw('key_hash = ?', [$binaryHash])
        ->first();

    if (! $key) {
        $key = new AttributionKey;
        $key->workspace_id = $workspaceId;
        $key->connector_id = $connectorId;
        $key->key_hash = $hexHash; // BinaryHash cast converts to binary
        $key->key_value = $email;
        $key->save();
    }

    DB::table('attribution_record_keys')->updateOrInsert(
        ['connector_id' => $connectorId, 'record_type' => $recordType, 'record_id' => $recordId],
        ['attribution_key_id' => $key->id, 'workspace_id' => $workspaceId]
    );
}

it('first_click: single match produces weight 1.0 with earliest click', function () {
    $email = 'alice@example.com';

    $campaign = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDays(5),
    ]);
    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 100,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    $count = $this->engine->run($this->workspace, $this->connector, 'first_click');

    expect($count)->toBe(1);

    $result = AttributionResult::first();
    expect($result->effort_id)->toBe($this->effort1->id);
    expect((float) $result->weight)->toBe(1.0);
    expect($result->model)->toBe('first_click');
});

it('first_click: multi-match selects earliest click', function () {
    $email = 'bob@example.com';

    // Campaign 1 (effort1) with earlier click
    $campaign1 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click1 = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign1->id,
        'clicked_at' => now()->subDays(10), // Earlier
    ]);

    // Campaign 2 (effort2) with later click
    $campaign2 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort2->id,
        'external_id' => 'camp-2',
        'from_email' => $email,
    ]);
    $click2 = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign2->id,
        'clicked_at' => now()->subDays(2), // Later
    ]);

    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 200,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click1->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click2->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    $count = $this->engine->run($this->workspace, $this->connector, 'first_click');

    expect($count)->toBe(1);

    $result = AttributionResult::first();
    expect($result->effort_id)->toBe($this->effort1->id); // Earliest click
    expect((float) $result->weight)->toBe(1.0);
});

it('first_click: no match produces no result', function () {
    // Conversion with no matching campaign
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-orphan',
        'revenue' => 50,
        'converted_at' => now(),
    ]);

    $count = $this->engine->run($this->workspace, $this->connector, 'first_click');

    expect($count)->toBe(0);
    expect(AttributionResult::count())->toBe(0);
});

it('last_click: single match produces weight 1.0 with latest click', function () {
    $email = 'charlie@example.com';

    $campaign = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDay(),
    ]);
    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 150,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    $count = $this->engine->run($this->workspace, $this->connector, 'last_click');

    expect($count)->toBe(1);
    $result = AttributionResult::first();
    expect((float) $result->weight)->toBe(1.0);
    expect($result->model)->toBe('last_click');
});

it('last_click: multi-match selects latest click', function () {
    $email = 'diana@example.com';

    $campaign1 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click1 = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign1->id,
        'clicked_at' => now()->subDays(10), // Earlier
    ]);

    $campaign2 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort2->id,
        'external_id' => 'camp-2',
        'from_email' => $email,
    ]);
    $click2 = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign2->id,
        'clicked_at' => now()->subDay(), // Later
    ]);

    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 300,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click1->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click2->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    $count = $this->engine->run($this->workspace, $this->connector, 'last_click');

    expect($count)->toBe(1);
    $result = AttributionResult::first();
    expect($result->effort_id)->toBe($this->effort2->id); // Latest click
});

it('linear: single match produces weight 1.0', function () {
    $email = 'eve@example.com';

    $campaign = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDay(),
    ]);
    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 100,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    $count = $this->engine->run($this->workspace, $this->connector, 'linear');

    expect($count)->toBe(1);
    $result = AttributionResult::first();
    expect((float) $result->weight)->toBe(1.0);
});

it('linear: multi-match distributes weight evenly (sum = 1.0)', function () {
    $email = 'frank@example.com';

    // Two campaigns with different efforts, both matching same conversion
    $campaign1 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click1 = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign1->id,
        'clicked_at' => now()->subDays(5),
    ]);

    $campaign2 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort2->id,
        'external_id' => 'camp-2',
        'from_email' => $email,
    ]);
    $click2 = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign2->id,
        'clicked_at' => now()->subDays(2),
    ]);

    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 400,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click1->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click2->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    $count = $this->engine->run($this->workspace, $this->connector, 'linear');

    expect($count)->toBe(2);

    $results = AttributionResult::all();
    expect($results)->toHaveCount(2);

    // Each should have weight 0.5
    foreach ($results as $result) {
        expect((float) $result->weight)->toBe(0.5);
    }

    // Sum should be 1.0
    $totalWeight = $results->sum(fn ($r) => (float) $r->weight);
    expect($totalWeight)->toBe(1.0);
});

it('re-running clears previous results before writing new ones', function () {
    $email = 'grace@example.com';

    $campaign = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort1->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDay(),
    ]);
    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'conv-1',
        'revenue' => 100,
        'converted_at' => now(),
    ]);

    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'campaign_email_click', $click->id);
    linkRecordToKey($this->connector->id, $this->workspace->id, $email, 'conversion_sale', $conversion->id);

    // Run twice
    $this->engine->run($this->workspace, $this->connector, 'first_click');
    $countAfterFirst = AttributionResult::count();

    $this->engine->run($this->workspace, $this->connector, 'first_click');
    $countAfterSecond = AttributionResult::count();

    // Should not accumulate — same count after re-run
    expect($countAfterSecond)->toBe($countAfterFirst);
    expect($countAfterSecond)->toBe(1);
});

it('rejects invalid attribution model', function () {
    expect(fn () => $this->engine->run($this->workspace, $this->connector, 'invalid_model'))
        ->toThrow(InvalidArgumentException::class);
});
