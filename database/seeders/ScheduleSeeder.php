<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleSeeder extends Seeder
{
    public function run()
    {
        // Clear existing data
        DB::table('schedules')->truncate();

        // Insert only the 3 time slots
        DB::table('schedules')->insert([
            [
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'start_time' => '15:00:00',
                'end_time' => '17:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
