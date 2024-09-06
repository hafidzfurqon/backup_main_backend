<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Instance;
use App\Models\Tags;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminSeeder = User::create([
            'name' => 'Administrator',
            'email' => 'administrator@gmail.com',
            'password' => bcrypt('inmydream205'),
            'is_superadmin' => true
        ]);

        $instance = Instance::where('name', 'KemenkopUKM')->first();

        $folderUser = Folder::where('id', $adminSeeder->id)->whereNull('parent_id')->first();

        $adminSeeder->instances()->attach($instance->id);

        $folderUser->instances()->attach($instance->id);

        $adminRole = Role::where('name', 'admin')->first();
        
        // Assign role with permissions to admin user
        $adminSeeder->assignRole($adminRole);
    }
}
