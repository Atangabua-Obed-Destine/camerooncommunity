<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('cameroon.roles') as $slug => $label) {
            Role::firstOrCreate(
                ['name' => $slug, 'guard_name' => 'web'],
            );
        }
    }
}
