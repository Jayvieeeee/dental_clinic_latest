<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    //
    public function index()
    {
        return response()->json(Schedule::all());
    }

    public function getByDate($date)
    {
        $schedules = Schedule::whereDate('schedule_date', $date)
            ->where('status', 'available')
            ->get();

        return response()->json($schedules);
    }
}
