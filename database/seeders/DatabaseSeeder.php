<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //create admin user
        $this->call(AdminUserSeeder::class);
        //create services
        $this->call(ServiceSeeder::class);
        //create schedules
        $this->call(ScheduleSeeder::class);
    }
}
