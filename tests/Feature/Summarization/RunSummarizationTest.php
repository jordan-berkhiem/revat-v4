<?php

use App\Jobs\Summarization\RunSummarization;
use App\Jobs\Summarization\SummarizeAttribution;
use App\Jobs\Summarization\SummarizeCampaigns;
use App\Jobs\Summarization\SummarizeConversions;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
});

it('dispatches all three summarization jobs in a batch', function () {
    Bus::fake();

    $job = new RunSummarization($this->workspace->id);
    $job->handle();

    Bus::assertBatched(function ($batch) {
        $jobClasses = collect($batch->jobs)->map(fn ($job) => get_class($job))->toArray();

        return in_array(SummarizeCampaigns::class, $jobClasses)
            && in_array(SummarizeConversions::class, $jobClasses)
            && in_array(SummarizeAttribution::class, $jobClasses);
    });
});

it('has unique ID scoped by workspace', function () {
    $job = new RunSummarization($this->workspace->id);

    expect($job->uniqueId())->toBe((string) $this->workspace->id);
});
