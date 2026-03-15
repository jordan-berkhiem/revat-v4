<?php

namespace Database\Seeders\Test;

use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Program;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class PieSeeder extends Seeder
{
    /**
     * Seed 2 programs per workspace, 2 initiatives per program,
     * 3 efforts per initiative with realistic channel types.
     */
    public function run(): void
    {
        $channelTypes = ['email', 'paid_search', 'social'];

        $programTemplates = [
            ['name' => 'Email Marketing', 'code' => 'EM'],
            ['name' => 'Paid Acquisition', 'code' => 'PA'],
        ];

        $initiativeTemplates = [
            [
                ['name' => 'Welcome Series', 'code' => 'WS'],
                ['name' => 'Retention Campaign', 'code' => 'RC'],
            ],
            [
                ['name' => 'Google Ads', 'code' => 'GA'],
                ['name' => 'Facebook Ads', 'code' => 'FA'],
            ],
        ];

        $effortNames = [
            'Launch Blast', 'Follow-Up Drip', 'Re-engagement',
            'Brand Awareness', 'Lead Gen', 'Retargeting',
        ];

        $workspaces = Workspace::orderBy('id')->get();
        $effortIndex = 0;
        $codeCounter = 1;

        foreach ($workspaces as $workspace) {
            foreach ($programTemplates as $pi => $programData) {
                $program = Program::create([
                    'workspace_id' => $workspace->id,
                    'name' => $programData['name'],
                    'code' => $programData['code'].'-'.$workspace->id,
                    'status' => 'active',
                ]);

                foreach ($initiativeTemplates[$pi] as $initData) {
                    $initiative = Initiative::create([
                        'workspace_id' => $workspace->id,
                        'program_id' => $program->id,
                        'name' => $initData['name'],
                        'code' => $initData['code'].'-'.$workspace->id,
                        'budget' => fake()->randomFloat(2, 1000, 50000),
                    ]);

                    for ($e = 0; $e < 3; $e++) {
                        Effort::create([
                            'workspace_id' => $workspace->id,
                            'initiative_id' => $initiative->id,
                            'name' => $effortNames[$effortIndex % count($effortNames)],
                            'code' => 'EF'.str_pad($codeCounter++, 3, '0', STR_PAD_LEFT),
                            'channel_type' => $channelTypes[$e % count($channelTypes)],
                            'status' => 'active',
                        ]);
                        $effortIndex++;
                    }
                }
            }
        }
    }
}
