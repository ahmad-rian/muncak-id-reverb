<?php

namespace Database\Seeders;

use App\Http\Controllers\UserController;
use App\Models\Gunung;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::findOrCreate('admin');
        $user  = Role::findOrCreate('user');

        $controller = new UserController();

        $admin1 = User::firstOrCreate(
            ["email" => "admin@admin"],
            [
                "name"              => "Admin 1",
                "username"          => uniqid("admin_"),
                "password"          => Hash::make("123123123"),
                "email_verified_at" => now(),
            ]
        );
        $admin1->assignRole($admin);
        $controller->createPhotoProfile($admin1);

        $admin2 = User::firstOrCreate(
            ["email" => "admin@muncak.id"],
            [
                "name"              => "Admin 2",
                "username"          => uniqid("admin_"),
                "password"          => Hash::make("123123123"),
                "email_verified_at" => now(),
            ]
        );
        $admin2->assignRole($admin);
        $controller->createPhotoProfile($admin2);

        $user1 = User::firstOrCreate(
            ["email" => "user1@muncak.id"],
            [
                "name"              => "User 1",
                "username"          => uniqid("user"),
                "password"          => Hash::make("123123123"),
                "email_verified_at" => now(),
            ]
        );
        $user1->assignRole($user);
        $controller->createPhotoProfile($user1);
    }
}
