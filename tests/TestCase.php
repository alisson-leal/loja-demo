<?php

namespace Tests;

use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole(PermissionCatalog::ADMIN_ROLE);
    }

    /** @param list<string> $permissions */
    protected function userWith(array $permissions, array $attributes = []): User
    {
        return User::factory()->create($attributes)->givePermissionTo($permissions);
    }
}
