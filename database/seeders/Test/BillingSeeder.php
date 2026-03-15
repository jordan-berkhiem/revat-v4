<?php

namespace Database\Seeders\Test;

use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    /**
     * Assign plans to organizations: first org gets Growth (paid),
     * second org stays on Free.
     */
    public function run(): void
    {
        $growthPlan = Plan::where('slug', 'growth')->first();
        $freePlan = Plan::where('slug', 'free')->first();

        $orgs = Organization::orderBy('id')->get();

        if ($orgs->count() >= 1 && $growthPlan) {
            $orgs[0]->plan_id = $growthPlan->id;
            $orgs[0]->save();
        }

        if ($orgs->count() >= 2 && $freePlan) {
            $orgs[1]->plan_id = $freePlan->id;
            $orgs[1]->save();
        }
    }
}
