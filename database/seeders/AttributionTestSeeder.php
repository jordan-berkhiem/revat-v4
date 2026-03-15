<?php

namespace Database\Seeders;

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\ConversionSale;
use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttributionTestSeeder extends Seeder
{
    public function run(): void
    {
        // Organization + User + Workspace
        $org = Organization::create(['name' => 'Attribution Test Org']);
        $workspace = new Workspace(['name' => 'Default']);
        $workspace->organization_id = $org->id;
        $workspace->is_default = true;
        $workspace->save();

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($user);
        $workspace->users()->attach($user);

        // PIE Hierarchy: 1 program → 2 initiatives → 3 efforts
        $program = Program::create([
            'workspace_id' => $workspace->id,
            'name' => 'Email Marketing',
            'code' => 'EM',
            'status' => 'active',
        ]);

        $initiative1 = Initiative::create([
            'workspace_id' => $workspace->id,
            'program_id' => $program->id,
            'name' => 'Welcome Series',
            'code' => 'WS',
        ]);

        $initiative2 = Initiative::create([
            'workspace_id' => $workspace->id,
            'program_id' => $program->id,
            'name' => 'Promotions',
            'code' => 'PR',
        ]);

        $effort1 = Effort::create([
            'workspace_id' => $workspace->id,
            'initiative_id' => $initiative1->id,
            'name' => 'Welcome Email',
            'code' => 'WE1',
            'channel_type' => 'email',
            'status' => 'active',
        ]);

        $effort2 = Effort::create([
            'workspace_id' => $workspace->id,
            'initiative_id' => $initiative1->id,
            'name' => 'Follow-up Email',
            'code' => 'FU1',
            'channel_type' => 'email',
            'status' => 'active',
        ]);

        $effort3 = Effort::create([
            'workspace_id' => $workspace->id,
            'initiative_id' => $initiative2->id,
            'name' => 'Summer Sale',
            'code' => 'SS1',
            'channel_type' => 'email',
            'status' => 'active',
        ]);

        // Shared emails for matching
        $sharedEmails = [
            'alice@example.com',
            'bob@example.com',
            'charlie@example.com',
            'diana@example.com',
            'eve@example.com',
        ];

        // Campaign Emails (5) — linked to efforts
        $campaigns = [];
        $campaigns[] = CampaignEmail::create([
            'workspace_id' => $workspace->id,
            'effort_id' => $effort1->id,
            'external_id' => 'camp-1',
            'name' => 'Welcome to Alice',
            'from_email' => $sharedEmails[0],
            'sent_at' => now()->subDays(10),
        ]);
        $campaigns[] = CampaignEmail::create([
            'workspace_id' => $workspace->id,
            'effort_id' => $effort1->id,
            'external_id' => 'camp-2',
            'name' => 'Welcome to Bob',
            'from_email' => $sharedEmails[1],
            'sent_at' => now()->subDays(9),
        ]);
        $campaigns[] = CampaignEmail::create([
            'workspace_id' => $workspace->id,
            'effort_id' => $effort2->id,
            'external_id' => 'camp-3',
            'name' => 'Follow-up Alice',
            'from_email' => $sharedEmails[0], // Same email as camp-1 (for multi-match)
            'sent_at' => now()->subDays(7),
        ]);
        $campaigns[] = CampaignEmail::create([
            'workspace_id' => $workspace->id,
            'effort_id' => $effort2->id,
            'external_id' => 'camp-4',
            'name' => 'Follow-up Charlie',
            'from_email' => $sharedEmails[2],
            'sent_at' => now()->subDays(6),
        ]);
        $campaigns[] = CampaignEmail::create([
            'workspace_id' => $workspace->id,
            'effort_id' => $effort3->id,
            'external_id' => 'camp-5',
            'name' => 'Summer Sale Diana',
            'from_email' => $sharedEmails[3],
            'sent_at' => now()->subDays(5),
        ]);

        // Campaign Email Clicks (10) with varied timestamps
        $clicks = [];
        // Alice clicks on camp-1 (earliest)
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[0]->id,
            'clicked_at' => now()->subDays(9)->subHours(5),
        ]);
        // Alice clicks on camp-3 (later)
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[2]->id,
            'clicked_at' => now()->subDays(6)->subHours(3),
        ]);
        // Bob clicks on camp-2
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[1]->id,
            'clicked_at' => now()->subDays(8),
        ]);
        // Charlie clicks on camp-4 (earliest)
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[3]->id,
            'clicked_at' => now()->subDays(5)->subHours(2),
        ]);
        // Diana clicks on camp-5
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[4]->id,
            'clicked_at' => now()->subDays(4),
        ]);
        // More clicks for multi-match testing
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[0]->id,
            'clicked_at' => now()->subDays(8)->subHours(1),
        ]);
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[1]->id,
            'clicked_at' => now()->subDays(7)->subHours(2),
        ]);
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[2]->id,
            'clicked_at' => now()->subDays(5)->subHours(4),
        ]);
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[3]->id,
            'clicked_at' => now()->subDays(4)->subHours(6),
        ]);
        $clicks[] = CampaignEmailClick::create([
            'workspace_id' => $workspace->id,
            'campaign_email_id' => $campaigns[4]->id,
            'clicked_at' => now()->subDays(3),
        ]);

        // Conversion Sales (5) with varied revenue
        $conversions = [];
        $conversions[] = ConversionSale::create([
            'workspace_id' => $workspace->id,
            'external_id' => 'conv-alice',
            'revenue' => 100.00,
            'converted_at' => now()->subDays(3),
        ]);
        $conversions[] = ConversionSale::create([
            'workspace_id' => $workspace->id,
            'external_id' => 'conv-bob',
            'revenue' => 250.00,
            'converted_at' => now()->subDays(2),
        ]);
        $conversions[] = ConversionSale::create([
            'workspace_id' => $workspace->id,
            'external_id' => 'conv-charlie',
            'revenue' => 75.00,
            'converted_at' => now()->subDays(1),
        ]);
        $conversions[] = ConversionSale::create([
            'workspace_id' => $workspace->id,
            'external_id' => 'conv-diana',
            'revenue' => 500.00,
            'converted_at' => now(),
        ]);
        // Eve has no matching campaign (no match scenario)
        $conversions[] = ConversionSale::create([
            'workspace_id' => $workspace->id,
            'external_id' => 'conv-eve',
            'revenue' => 150.00,
            'converted_at' => now(),
        ]);

        // Attribution Connector
        $connector = AttributionConnector::create([
            'workspace_id' => $workspace->id,
            'name' => 'Email to Sale Connector',
            'campaign_integration_id' => 1,
            'campaign_data_type' => 'email',
            'conversion_integration_id' => 2,
            'conversion_data_type' => 'sale',
            'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
            'is_active' => true,
        ]);

        // Pre-compute attribution keys and record keys
        foreach ($sharedEmails as $email) {
            $hash = hash('sha256', $email, true);

            $key = AttributionKey::create([
                'workspace_id' => $workspace->id,
                'connector_id' => $connector->id,
                'key_hash' => bin2hex($hash), // BinaryHash cast will convert hex→binary on set
                'key_value' => $email,
            ]);

            // Link campaigns that use this email
            foreach ($campaigns as $campaign) {
                if ($campaign->from_email === $email) {
                    DB::table('attribution_record_keys')->insert([
                        'connector_id' => $connector->id,
                        'attribution_key_id' => $key->id,
                        'record_type' => 'campaign_email',
                        'record_id' => $campaign->id,
                        'workspace_id' => $workspace->id,
                    ]);
                }
            }

            // Link clicks for campaigns that use this email
            foreach ($campaigns as $campaign) {
                if ($campaign->from_email === $email) {
                    $campaignClicks = CampaignEmailClick::where('campaign_email_id', $campaign->id)->get();
                    foreach ($campaignClicks as $click) {
                        DB::table('attribution_record_keys')->updateOrInsert(
                            [
                                'connector_id' => $connector->id,
                                'record_type' => 'campaign_email_click',
                                'record_id' => $click->id,
                            ],
                            [
                                'attribution_key_id' => $key->id,
                                'workspace_id' => $workspace->id,
                            ]
                        );
                    }
                }
            }

            // Link conversions that use this email as external_id
            foreach ($conversions as $conversion) {
                if ($conversion->external_id === $email) {
                    // No conversions use email as external_id in this seeder
                    // This is intentional: conversions are matched via shared keys
                }
            }
        }

        // Link conversions to keys via their matching field
        // The connector maps conversion.external_id → campaign.from_email
        // So we need to link conversions with external_ids that match campaign from_emails
        $convEmailMap = [
            'conv-alice' => 'alice@example.com',
            'conv-bob' => 'bob@example.com',
            'conv-charlie' => 'charlie@example.com',
            'conv-diana' => 'diana@example.com',
            // conv-eve has no match
        ];

        foreach ($conversions as $conversion) {
            $email = $convEmailMap[$conversion->external_id] ?? null;
            if (! $email) {
                continue;
            }

            $hash = hash('sha256', $email, true);
            $key = AttributionKey::where('connector_id', $connector->id)
                ->whereRaw('key_hash = ?', [$hash])
                ->first();

            if ($key) {
                DB::table('attribution_record_keys')->insert([
                    'connector_id' => $connector->id,
                    'attribution_key_id' => $key->id,
                    'record_type' => 'conversion_sale',
                    'record_id' => $conversion->id,
                    'workspace_id' => $workspace->id,
                ]);
            }
        }
    }
}
