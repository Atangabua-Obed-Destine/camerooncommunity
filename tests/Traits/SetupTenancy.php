<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait SetupTenancy
{
    protected Tenant $tenant;

    protected function setUpTenancy(): void
    {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        foreach (config('cameroon.roles') as $slug => $label) {
            Role::firstOrCreate(['name' => $slug, 'guard_name' => 'web']);
        }
    }

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(
            ['tenant_id' => $this->tenant->id],
            $attributes,
        ));
    }

    protected function createAdmin(): User
    {
        $admin = $this->createUser();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
