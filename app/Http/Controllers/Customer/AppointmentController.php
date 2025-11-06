<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentReminder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AppointmentController extends Controller
{
    /**
     * View an appointment
     */
    public function index()
    {
        $userId = Auth::id();

        $appointments = Appointment::with(['service', 'schedule'])
            ->where('patient_id', $userId)
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->appointment_id,
                    'date_raw' => $appointment->appointment_date->format('Y-m-d'),
                    'schedule_id' => $appointment->schedule_id,
                    'procedure' => $appointment->service->service_name ?? 'N/A',
                    'date' => optional($appointment->appointment_date)->format('m-d-Y') ?? 'N/A',
                    'time' => $appointment->schedule 
                        ? date('g:i a', strtotime($appointment->schedule->start_time)) . 
                          ' - ' . date('g:i a', strtotime($appointment->schedule->end_time))
                        : 'N/A',
                    'status' => ucfirst($appointment->status),
                    'payment_status' => ucfirst($appointment->payment_status ?? 'Paid'),
                ];
            });

        return Inertia::render('Customer/ViewAppointment', [
            'appointments' => $appointments,
        ]);
    }

    /**
     * Show appointment scheduling form
     */
    public function create()
    {
        $user = Auth::user();

        // Calculate minimum date (tomorrow)
        $minDate = Carbon::tomorrow()->format('Y-m-d');
        // Calculate maximum date (e.g., 3 months from now)
        $maxDate = Carbon::now()->addMonths(3)->format('Y-m-d');

        return Inertia::render('Customer/ScheduleAppointment', [
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact_no' => $user->contact_no,
            ],
            'services' => Service::all(),
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'today' => Carbon::today()->format('Y-m-d'),
            'tomorrow' => Carbon::tomorrow()->format('Y-m-d'),
        ]);
    }

   public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'schedule_id' => 'required|exists:schedules,schedule_id',
            'appointment_date' => 'required|date|after:today',
        ]);

        return DB::transaction(function () use ($validated) {
            // Check if appointment date is at least 1 day in advance
            $appointmentDate = Carbon::parse($validated['appointment_date']);
            $today = Carbon::today();
            
            if ($appointmentDate->lte($today)) {
                return back()->withErrors([
                    'error' => 'Appointments must be scheduled at least 1 day in advance. Please choose a future date.'
                ]);
            }

            // Check if user has existing pending or confirmed appointment
            $existingAppointment = Appointment::where('patient_id', Auth::id())
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existingAppointment) {
                return back()->withErrors([
                    'error' => 'You already have a pending or confirmed appointment. Please cancel it first to book a new one.'
                ]);
            }

            // Check if the schedule slot is already booked for this date
            $isAlreadyBooked = Appointment::where('schedule_id', $validated['schedule_id'])
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if ($isAlreadyBooked) {
                return back()->withErrors([
                    'error' => 'This time slot is already booked. Please choose another time.'
                ]);
            }

            // Get the schedule to create proper datetime
            $schedule = Schedule::find($validated['schedule_id']);
            $scheduleDateTime = Carbon::parse($validated['appointment_date'] . ' ' . $schedule->start_time);

            // Create the appointment
            $appointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $validated['service_id'],
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'schedule_datetime' => $scheduleDateTime,
                'status' => 'pending', 
                'created_by' => Auth::id(),
            ]);

            // FIX: Set BOTH session variables for compatibility
            session([
                'pending_payment' => [
                    'temp_appointment_id' => $appointment->appointment_id, // This is what success() looks for
                    'service_id' => $validated['service_id'],
                    'schedule_id' => $validated['schedule_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'user_id' => Auth::id(),
                    'amount' => 30000, // Add this for payment processing
                ],
                'pending_appointment' => [
                    'appointment_id' => $appointment->appointment_id,
                    'service_id' => $validated['service_id'],
                    'schedule_id' => $validated['schedule_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'user_id' => Auth::id(),
                ]
            ]);

            Log::info('Appointment created for payment', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'session_set' => true
            ]);

            return redirect()->route('customer.payment.view');
        });
    }

    /**
     * Show payment page
     */
    public function showPaymentPage()
    {
        $pendingAppointment = session('pending_appointment');
        
        if (!$pendingAppointment || $pendingAppointment['user_id'] != Auth::id()) {
            return redirect()->route('customer.appointment')->with('error', 'No pending appointment found.');
        }

        $appointment = Appointment::find($pendingAppointment['appointment_id']);
        $service = Service::find($pendingAppointment['service_id']);
        $schedule = Schedule::find($pendingAppointment['schedule_id']);
        
        if (!$appointment || !$service || !$schedule) {
            return redirect()->route('customer.appointment')->with('error', 'Appointment data not found.');
        }

        return Inertia::render('Customer/ScheduleAppointment', [
            'appointment_data' => [
                'appointment_id' => $appointment->appointment_id,
                'service_name' => $service->service_name,
                'appointment_date' => $appointment->appointment_date,
                'time_slot' => $schedule->start_time . ' - ' . $schedule->end_time,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                'amount' => 300.00,
            ]
        ]);
    }

    /**
     * View user's appointments
     */
    public function view()
    {
        $user = Auth::user();
        $appointments = Appointment::with(['service', 'schedule'])
            ->where('patient_id', $user->user_id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('schedule_datetime', 'desc')
            ->get()
            ->map(function ($appointment) {
                $timeSlot = $appointment->schedule ? 
                    Carbon::parse($appointment->schedule->start_time)->format('g:i A') . ' - ' . 
                    Carbon::parse($appointment->schedule->end_time)->format('g:i A') : 
                    'N/A';

                return [
                    'appointment_id' => $appointment->appointment_id,
                    'service_name' => $appointment->service->service_name,
                    'appointment_date' => $appointment->appointment_date,
                    'schedule_datetime' => $appointment->schedule_datetime,
                    'status' => $appointment->status,
                    'formatted_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                    'formatted_time' => $timeSlot,
                    'can_cancel' => $appointment->status === 'confirmed', // ONLY confirmed can be cancelled
                    'can_reschedule' => $appointment->status === 'confirmed', // ONLY confirmed can be rescheduled
                    'is_pending' => $appointment->status === 'pending',
                    'is_confirmed' => $appointment->status === 'confirmed',
                    'is_cancelled' => $appointment->status === 'cancelled',
                    'is_completed' => $appointment->status === 'completed',
                ];
            });

        return Inertia::render('Customer/ViewAppointments', [
            'appointments' => $appointments,
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Cancel an appointment 
     */
    public function cancel(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            $appointment = Appointment::with('schedule')
                ->where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            // Only allow cancellation of CONFIRMED appointments
            if ($appointment->status !== 'confirmed') {
                return back()->with('error', 'Only confirmed appointments can be cancelled.');
            }

            // Release the schedule slot (make it available again)
            if ($appointment->schedule) {
                $appointment->schedule->update(['is_available' => true]);
            }

            // Update appointment status to cancelled
            $appointment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
            ]);

            Log::info('Appointment cancelled and slot released', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'schedule_id' => $appointment->schedule_id,
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment cancelled successfully and time slot released.');
        });
    }

    /**
     * Reschedule an appointment 
     */
    public function reschedule(Request $request, $id)
    {
        $validated = $request->validate([
            'new_schedule_id' => 'required|exists:schedules,schedule_id',
            'new_appointment_date' => 'required|date|after:today',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            // Check if new appointment date is at least 1 day in advance
            $newAppointmentDate = Carbon::parse($validated['new_appointment_date']);
            $today = Carbon::today();
            
            if ($newAppointmentDate->lte($today)) {
                return back()->with('error', 'Appointments must be scheduled at least 1 day in advance. Please choose a future date.');
            }

            $appointment = Appointment::with('schedule')
                ->where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            if (in_array($appointment->status, ['cancelled', 'completed'])) {
                return back()->with('error', 'Cancelled or completed appointments cannot be rescheduled.');
            }

            if ($appointment->status !== 'confirmed') {
                return back()->with('error', 'Only confirmed appointments can be rescheduled.');
            }

            $isAlreadyBooked = Appointment::where('schedule_id', $validated['new_schedule_id'])
                ->whereDate('appointment_date', $validated['new_appointment_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('appointment_id', '!=', $id)
                ->exists();

            if ($isAlreadyBooked) {
                return back()->with('error', 'The selected time slot is already booked. Please choose another time.');
            }

            $newSchedule = Schedule::find($validated['new_schedule_id']);

            $newScheduleDateTime = Carbon::parse(
                $validated['new_appointment_date'] . ' ' . Carbon::parse($newSchedule->start_time)->format('H:i:s')
            );

            if ($appointment->schedule) {
                $appointment->schedule->update(['is_available' => true]);
            }

            $newSchedule->update(['is_available' => false]);

            // Update appointment record
            $appointment->update([
                'schedule_id'        => $validated['new_schedule_id'],
                'appointment_date'   => $validated['new_appointment_date'],
                'schedule_datetime'  => $newScheduleDateTime,
                'status'             => 'confirmed',
                'rescheduled_at'     => now(),
                'rescheduled_by'     => Auth::id(),
            ]);

            Log::info('Appointment rescheduled successfully', [
                'appointment_id'         => $appointment->appointment_id,
                'user_id'                => Auth::id(),
                'previous_schedule_id'   => $appointment->getOriginal('schedule_id'),
                'new_schedule_id'        => $validated['new_schedule_id'],
                'previous_date'          => $appointment->getOriginal('appointment_date'),
                'new_date'               => $validated['new_appointment_date'],
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment rescheduled successfully.');
        });
    }

    /**
     * Confirm appointment after successful payment
     */
    public function confirmAfterPayment($appointmentId)
    {
        DB::transaction(function () use ($appointmentId) {
            $appointment = Appointment::where('appointment_id', $appointmentId)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                throw new \Exception('Appointment not found');
            }

            // Update status to confirmed
            $appointment->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            Log::info('Appointment confirmed after payment', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id()
            ]);
        });
    }

    /**
     * Get available time slots for a specific date
     */
    public function getAvailableSlots(Request $request)
    {
        $userId = Auth::id();
        $request->validate([
            'date' => 'required|date|after:today',
        ]);

        $date = Carbon::parse($request->query('date'))->toDateString();

        // Check if date is at least 1 day in advance
        $today = Carbon::today();
        $selectedDate = Carbon::parse($date);
        
        if ($selectedDate->lte($today)) {
            return response()->json([
                'success' => false,
                'message' => 'Appointments must be scheduled at least 1 day in advance.',
                'available_slots' => []
            ], 422);
        }

        $cacheKey = "available_slots:{$date}";
        $cachedSlots = Cache::get($cacheKey);
        
        if ($cachedSlots) {
            return response()->json([
                'success' => true,
                'available_slots' => $cachedSlots,
                'date' => $date,
                'cached' => true
            ]);
        }

        $availableSlots = Schedule::leftJoin('appointments', function($join) use ($date, $userId) {
                $join->on('schedules.schedule_id', '=', 'appointments.schedule_id')
                    ->whereDate('appointments.appointment_date', $date)
                    ->where('appointments.patient_id', '!=', $userId)
                    ->whereIn('appointments.status', ['pending', 'confirmed']);
            })
            ->select(
                'schedules.schedule_id',
                'schedules.start_time',
                'schedules.end_time',
                DB::raw('appointments.schedule_id IS NOT NULL as is_booked')
            )
            ->get()
            ->map(function ($schedule) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') .
                        ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                    'is_booked' => (bool) $schedule->is_booked,
                ];
            })
            ->values();

        Cache::put($cacheKey, $availableSlots, 300); // 5 minutes

        return response()->json([
            'success' => true,
            'available_slots' => $availableSlots,
            'date' => $date,
            'cached' => false
        ]);
    }

    /**
     * Check slot availability
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,schedule_id',
            'date' => 'required|date|after:today',
        ]);

        // Check if date is at least 1 day in advance
        $today = Carbon::today();
        $selectedDate = Carbon::parse($request->date);
        
        if ($selectedDate->lte($today)) {
            return response()->json([
                'available' => false,
                'message' => 'Appointments must be scheduled at least 1 day in advance.',
                'schedule' => null
            ], 422);
        }

        $isAvailable = !Appointment::where('schedule_id', $request->schedule_id)
            ->whereDate('appointment_date', $request->date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        $schedule = Schedule::find($request->schedule_id);

        return response()->json([
            'available' => $isAvailable,
            'message' => $isAvailable ? 'Time slot is available' : 'Time slot is not available',
            'schedule' => $schedule ? [
                'schedule_id' => $schedule->schedule_id,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A')
            ] : null
        ]);
    }

    /**
     * Get available dates
     */
    public function getAvailableDates(Request $request)
    {
        $startDate = Carbon::tomorrow(); 
        $endDate = Carbon::now()->addMonths(3);
        
        // CACHE AVAILABLE DATES
        $cacheKey = 'available_dates_range';
        $cachedDates = Cache::get($cacheKey);
        
        if ($cachedDates) {
            return response()->json([
                'success' => true,
                'available_dates' => $cachedDates,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'cached' => true
            ]);
        }

        $availableDates = [];
        $totalSlots = Schedule::count();

        // BATCH QUERY - Get all booked counts in single query
        $bookedCounts = Appointment::whereDate('appointment_date', '>=', $startDate)
            ->whereDate('appointment_date', '<=', $endDate)
            ->whereIn('status', ['pending', 'confirmed'])
            ->groupBy('appointment_date')
            ->selectRaw('appointment_date, COUNT(*) as booked_count')
            ->pluck('booked_count', 'appointment_date');

        $period = $startDate->toPeriod($endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $bookedCount = $bookedCounts[$dateStr] ?? 0;
            $availableCount = $totalSlots - $bookedCount;

            if ($availableCount > 0) {
                $availableDates[] = [
                    'date' => $dateStr,
                    'available_slots' => $availableCount,
                    'day_name' => $date->englishDayOfWeek,
                    'is_available' => true
                ];
            }
        }
        
        Cache::put($cacheKey, $availableDates, 900); // 15 minutes

        return response()->json([
            'success' => true,
            'available_dates' => $availableDates,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'cached' => false
        ]);
    }

     private function sendAppointmentReminder($appointment)
    {
        try {
            $user = User::find($appointment->patient_id);
            $service = Service::find($appointment->service_id);
            $schedule = Schedule::find($appointment->schedule_id);

            if ($user && $service && $schedule) {
                Mail::to($user->email)->send(new AppointmentReminder(
                    $user,
                    $appointment,
                    $service,
                    $schedule
                ));
                
                Log::info('Appointment reminder email sent', [
                    'appointment_id' => $appointment->appointment_id,
                    'user_email' => $user->email,
                    'appointment_date' => $appointment->appointment_date
                ]);
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send appointment reminder email', [
                'appointment_id' => $appointment->appointment_id,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }

    /**
     * Command method to send daily reminders at 8:00 AM
     * Sends reminders to ALL users with confirmed appointments 
     */
    public function sendDailyReminders()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        // Get ALL confirmed appointments (today and future)
        $appointments = Appointment::with(['user', 'service', 'schedule'])
            ->whereDate('appointment_date', '>=', $today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($appointments as $appointment) {
            $success = $this->sendAppointmentReminder($appointment);
            
            if ($success) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        Log::info("Daily appointment reminders completed", [
            'date' => $today,
            'reminders_sent' => $sentCount,
            'reminders_failed' => $failedCount,
            'total_appointments' => $appointments->count()
        ]);

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $appointments->count()
        ];
    }

}