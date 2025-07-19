<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\{Role, User, Permission};
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $roles = ['SuperAdmin', 'Admin', 'User'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // Create permissions
        $permissions = ['user.list', 'user.create', 'user.update', 'user.delete', 'software.migrate', 'software.reset', 'assign.user'];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Assign all permissions to SuperAdmin
        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        $superAdminRole->permissions()->sync(Permission::pluck('id'));

        // Assign limited permissions to Admin
        $adminRole = Role::where('name', 'Admin')->first();
        $adminPerms = Permission::whereIn('name', ['user.list', 'user.create', 'user.update'])->pluck('id');
        $adminRole->permissions()->sync($adminPerms);

        // User role has no special permissions by default
        $userRole = Role::where('name', 'User')->first();

        // Create default users
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')]
        );

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );

        $normalUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Normal User', 'password' => Hash::make('password')]
        );

        // Assign roles to users
        $superAdminUser->roles()->sync([$superAdminRole->id]);
        $adminUser->roles()->sync([$adminRole->id]);
        $normalUser->roles()->sync([$userRole->id]);
    }
}
