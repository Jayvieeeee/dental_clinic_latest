<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // Stats data
        $totalAppointmentsToday = Appointment::whereDate('appointment_date', $today)->count();
        $patientsRegistered = User::where('role', 'user')->count();
        $pendingAppointments = Appointment::where('status', 'confirmed')
            ->whereDate('appointment_date', '>=', $today)
            ->count();

        // Weekly appointments data for chart
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();
        
        $weeklyAppointments = Appointment::whereBetween('appointment_date', [$weekStart, $weekEnd])
            ->selectRaw('DAYNAME(appointment_date) as day, COUNT(*) as count')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        // Map to match your frontend structure
        $appointmentsData = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0,
        ];

        foreach ($weeklyAppointments as $day => $data) {
            $appointmentsData[$day] = $data->count;
        }

        // Format for frontend
        $formattedAppointmentsData = [
            ['day' => 'Mon', 'value' => $appointmentsData['Monday']],
            ['day' => 'Tue', 'value' => $appointmentsData['Tuesday']],
            ['day' => 'Wed', 'value' => $appointmentsData['Wednesday']],
            ['day' => 'Thurs', 'value' => $appointmentsData['Thursday']],
            ['day' => 'Fri', 'value' => $appointmentsData['Friday']],
            ['day' => 'Sat', 'value' => $appointmentsData['Saturday']],
            ['day' => 'Sun', 'value' => $appointmentsData['Sunday']],
        ];

        // Appointment Status data
        $totalAppointments = Appointment::count();
        
        if ($totalAppointments > 0) {
            $completedCount = Appointment::where('status', 'completed')->count();
            $confirmedCount = Appointment::where('status', 'confirmed')->count();
            $rescheduledCount = Appointment::where('status', 'rescheduled')->count();
            $cancelledCount = Appointment::where('status', 'cancelled')->count();
            
            $appointmentStatus = [
                [
                    'label' => 'Completed',
                    'percentage' => round(($completedCount / $totalAppointments) * 100),
                    'color' => 'bg-green-500'
                ],
                [
                    'label' => 'Scheduled',
                    'percentage' => round(($confirmedCount / $totalAppointments) * 100),
                    'color' => 'bg-teal-500'
                ],
                [
                    'label' => 'Rescheduled',
                    'percentage' => round(($rescheduledCount / $totalAppointments) * 100),
                    'color' => 'bg-blue-500'
                ],
                [
                    'label' => 'Cancelled',
                    'percentage' => round(($cancelledCount / $totalAppointments) * 100),
                    'color' => 'bg-red-500'
                ],
            ];
        } else {
            $appointmentStatus = [
                ['label' => 'Completed', 'percentage' => 0, 'color' => 'bg-green-500'],
                ['label' => 'Scheduled', 'percentage' => 0, 'color' => 'bg-teal-500'],
                ['label' => 'Rescheduled', 'percentage' => 0, 'color' => 'bg-blue-500'],
                ['label' => 'Cancelled', 'percentage' => 0, 'color' => 'bg-red-500'],
            ];
        }

        // Upcoming appointments (next 7 days)
        $upcomingAppointments = Appointment::with(['patient', 'service', 'schedule'])
            ->where('status', 'confirmed')
            ->whereDate('appointment_date', '>=', $today)
            ->whereDate('appointment_date', '<=', $today->copy()->addDays(7))
            ->orderBy('appointment_date', 'asc')
            ->orderBy('schedule_id', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($appointment) {
                $patient = $appointment->patient;
                $service = $appointment->service;
                $schedule = $appointment->schedule;

                return [
                    'id' => $appointment->appointment_id,
                    'name' => trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')),
                    'procedure' => $service->service_name ?? 'N/A',
                    'dateTime' => $appointment->appointment_date->format('m-d-Y') . "\n" . 
                                 ($schedule->time_slot ?? 'Time not set')
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalAppointments' => $totalAppointmentsToday,
                'patientsRegistered' => $patientsRegistered,
                'pendingAppointments' => $pendingAppointments,
            ],
            'appointmentsData' => $formattedAppointmentsData,
            'appointmentStatus' => $appointmentStatus,
            'upcomingAppointments' => $upcomingAppointments,
            'currentDate' => [
                'dayName' => $today->format('l'),
                'fullDate' => $today->format('F j, Y')
            ]
        ]);
    }

    public function getChartData(Request $request)
    {
        $request->validate([
            'period' => 'required|in:Daily,Weekly,Monthly,Yearly'
        ]);

        $period = $request->period;
        $today = Carbon::today();

        switch ($period) {
            case 'Daily':
                $data = $this->getDailyData($today);
                break;
            case 'Weekly':
                $data = $this->getWeeklyData($today);
                break;
            case 'Monthly':
                $data = $this->getMonthlyData($today);
                break;
            case 'Yearly':
                $data = $this->getYearlyData($today);
                break;
            default:
                $data = $this->getWeeklyData($today);
        }

        return response()->json($data);
    }

    private function getDailyData($today)
    {
        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hourStart = $today->copy()->setTime($i, 0, 0);
            $hourEnd = $today->copy()->setTime($i, 59, 59);
            
            $count = Appointment::whereBetween('created_at', [$hourStart, $hourEnd])->count();
            
            $hours[] = [
                'hour' => $hourStart->format('g A'),
                'value' => $count
            ];
        }

        return $hours;
    }

    private function getWeeklyData($today)
    {
        $weekStart = $today->copy()->startOfWeek();
        
        $days = [];
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
        
        for ($i = 0; $i < 7; $i++) {
            $currentDay = $weekStart->copy()->addDays($i);
            
            $count = Appointment::whereDate('appointment_date', $currentDay)->count();
            
            $days[] = [
                'day' => $dayNames[$i],
                'value' => $count
            ];
        }

        return $days;
    }

    private function getMonthlyData($today)
    {
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        
        $weeks = [];
        $currentWeek = $monthStart->copy();
        
        while ($currentWeek <= $monthEnd) {
            $weekEnd = $currentWeek->copy()->endOfWeek();
            if ($weekEnd > $monthEnd) {
                $weekEnd = $monthEnd;
            }
            
            $count = Appointment::whereBetween('appointment_date', [$currentWeek, $weekEnd])->count();
            
            $weeks[] = [
                'week' => 'Week ' . (count($weeks) + 1),
                'value' => $count
            ];
            
            $currentWeek = $weekEnd->copy()->addDay();
        }

        return $weeks;
    }

    private function getYearlyData($today)
    {
        $yearStart = $today->copy()->startOfYear();
        
        $months = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        for ($i = 0; $i < 12; $i++) {
            $currentMonth = $yearStart->copy()->addMonths($i);
            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd = $currentMonth->copy()->endOfMonth();
            
            $count = Appointment::whereBetween('appointment_date', [$monthStart, $monthEnd])->count();
            
            $months[] = [
                'month' => $monthNames[$i],
                'value' => $count
            ];
        }

        return $months;
    }
}