<?php

use App\Jobs\ProcessAttribution;
use App\Models\AttributionConnector;
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
use App\Services\ConnectorKeyProcessor;
use Illuminate\Support\Facades\Log;

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
    $this->effort = Effort::create([
        'workspace_id' => $this->workspace->id,
        'initiative_id' => $initiative->id,
        'name' => 'Test Effort',
        'code' => 'TE',
        'channel_type' => 'email',
        'status' => 'active',
    ]);
});

/**
 * Helper to set up test data with campaign, click, conversion, and key linkages.
 */
function seedAttributionData(
    $workspace,
    $effort,
    AttributionConnector $connector,
    string $email = 'test@example.com'
): void {
    $campaign = CampaignEmail::create([
        'workspace_id' => $workspace->id,
        'effort_id' => $effort->id,
        'external_id' => 'camp-'.$email,
        'from_email' => $email,
    ]);

    CampaignEmailClick::create([
        'workspace_id' => $workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDays(3),
    ]);

    ConversionSale::create([
        'workspace_id' => $workspace->id,
        'external_id' => $email, // Matches the field_mapping conversion field
        'revenue' => 100,
        'converted_at' => now(),
    ]);
}

it('end-to-end: processes all active connectors and all models', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Email Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    seedAttributionData($this->workspace, $this->effort, $connector);

    $job = new ProcessAttribution($this->workspace);
    $job->handle(app(ConnectorKeyProcessor::class), app(AttributionEngine::class));

    // Should have results for all 3 models
    expect(AttributionResult::where('model', 'first_click')->count())->toBe(1);
    expect(AttributionResult::where('model', 'last_click')->count())->toBe(1);
    expect(AttributionResult::where('model', 'linear')->count())->toBe(1);

    // All results should have correct effort
    expect(AttributionResult::where('effort_id', $this->effort->id)->count())->toBe(3);
});

it('skips inactive connectors', function () {
    $activeConnector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Active Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Inactive Connector',
        'campaign_integration_id' => 3,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 4,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => false,
    ]);

    seedAttributionData($this->workspace, $this->effort, $activeConnector);

    $job = new ProcessAttribution($this->workspace);
    $job->handle(app(ConnectorKeyProcessor::class), app(AttributionEngine::class));

    // Only results from active connector
    expect(AttributionResult::where('connector_id', $activeConnector->id)->count())->toBe(3);
    expect(AttributionResult::count())->toBe(3);
});

it('processes only the specified connector when provided', function () {
    $connector1 = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Connector 1',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    $connector2 = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Connector 2',
        'campaign_integration_id' => 3,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 4,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    seedAttributionData($this->workspace, $this->effort, $connector1);

    // Only process connector1
    $job = new ProcessAttribution($this->workspace, $connector1);
    $job->handle(app(ConnectorKeyProcessor::class), app(AttributionEngine::class));

    expect(AttributionResult::where('connector_id', $connector1->id)->count())->toBe(3);
    expect(AttributionResult::where('connector_id', $connector2->id)->count())->toBe(0);
});

it('runs only the specified model when provided', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    seedAttributionData($this->workspace, $this->effort, $connector);

    $job = new ProcessAttribution($this->workspace, $connector, 'first_click');
    $job->handle(app(ConnectorKeyProcessor::class), app(AttributionEngine::class));

    expect(AttributionResult::where('model', 'first_click')->count())->toBe(1);
    expect(AttributionResult::where('model', 'last_click')->count())->toBe(0);
    expect(AttributionResult::where('model', 'linear')->count())->toBe(0);
});

it('partial failure: continues processing remaining connectors when one fails', function () {
    $connector1 = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Good Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    seedAttributionData($this->workspace, $this->effort, $connector1);

    // Mock ConnectorKeyProcessor to fail on second connector
    $mockProcessor = Mockery::mock(ConnectorKeyProcessor::class);
    $badConnectorId = null;
    $callCount = 0;
    $mockProcessor->shouldReceive('processKeys')
        ->andReturnUsing(function ($connector) use (&$callCount, &$badConnectorId, $connector1) {
            $callCount++;
            if ($connector->id !== $connector1->id) {
                $badConnectorId = $connector->id;
                throw new RuntimeException('Simulated failure');
            }
            // For the good connector, use the real processor
            app(ConnectorKeyProcessor::class)->processKeys($connector);
        });

    $connector2 = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Bad Connector',
        'campaign_integration_id' => 3,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 4,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->atLeast()->once();

    $job = new ProcessAttribution($this->workspace);
    $job->handle($mockProcessor, app(AttributionEngine::class));

    // Good connector should have results
    expect(AttributionResult::where('connector_id', $connector1->id)->count())->toBeGreaterThan(0);
});

it('throws when ALL connectors fail', function () {
    AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Failing Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    $mockProcessor = Mockery::mock(ConnectorKeyProcessor::class);
    $mockProcessor->shouldReceive('processKeys')
        ->andThrow(new RuntimeException('All fail'));

    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->atLeast()->once();

    $job = new ProcessAttribution($this->workspace);

    expect(fn () => $job->handle($mockProcessor, app(AttributionEngine::class)))
        ->toThrow(RuntimeException::class, 'All connectors failed');
});

it('is idempotent: running twice produces same result count', function () {
    $connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
        'is_active' => true,
    ]);

    seedAttributionData($this->workspace, $this->effort, $connector);

    $processor = app(ConnectorKeyProcessor::class);
    $engine = app(AttributionEngine::class);

    $job1 = new ProcessAttribution($this->workspace);
    $job1->handle($processor, $engine);
    $countAfterFirst = AttributionResult::count();

    $job2 = new ProcessAttribution($this->workspace);
    $job2->handle($processor, $engine);
    $countAfterSecond = AttributionResult::count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('dispatches to attribution queue', function () {
    $job = new ProcessAttribution($this->workspace);

    expect($job->queue)->toBe('attribution');
});

it('has correct uniqueId based on workspace', function () {
    $job = new ProcessAttribution($this->workspace);

    expect($job->uniqueId())->toBe((string) $this->workspace->id);
});

it('logs failure via failed method', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'ProcessAttribution: Job failed'
                && $context['workspace_id'] === $this->workspace->id;
        });

    $job = new ProcessAttribution($this->workspace);
    $job->failed(new RuntimeException('Test failure'));
});
