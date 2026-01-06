<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create all roles

        DB::table('roles')->insert([
            ['name' => 'SUPER_ADMIN'],
            ['name' => 'ADMIN'],
            ['name' => 'CASHIER'],
        ]);

        // Create default users
        DB::table('users')->insert([
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@gmail.com',
                'password' => bcrypt('password'),
                'role_id' => DB::table('roles')->where('name', 'SUPER_ADMIN')->first()->id,
                'is_active' => true,
            ],
        ]);

    }
}
