<?php

namespace Database\Seeders\Test;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FoundationSeeder extends Seeder
{
    /**
     * Seed 2 organizations with 3 users each (owner, admin, editor)
     * and 2 workspaces per organization.
     */
    public function run(): void
    {
        $orgs = [
            ['name' => 'Acme Marketing', 'timezone' => 'America/New_York'],
            ['name' => 'Globex Corp', 'timezone' => 'America/Los_Angeles'],
        ];

        foreach ($orgs as $orgData) {
            $org = Organization::create($orgData);

            // Create workspaces
            $defaultWs = new Workspace(['name' => 'Production']);
            $defaultWs->organization_id = $org->id;
            $defaultWs->is_default = true;
            $defaultWs->save();

            $stagingWs = new Workspace(['name' => 'Staging']);
            $stagingWs->organization_id = $org->id;
            $stagingWs->save();

            // Create users with roles
            $roles = ['owner', 'admin', 'editor'];
            $prefix = strtolower(str_replace(' ', '', $org->name));

            foreach ($roles as $index => $roleName) {
                $user = User::factory()->create([
                    'name' => ucfirst($roleName).' at '.$org->name,
                    'email' => "{$roleName}@{$prefix}.test",
                    'current_organization_id' => $org->id,
                ]);

                $org->users()->attach($user);
                $defaultWs->users()->attach($user);
                $stagingWs->users()->attach($user);

                app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
                $role = Role::findByName($roleName, 'web');
                $user->assignRole($role);
            }
        }
    }
}
