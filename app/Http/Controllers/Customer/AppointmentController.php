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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class AppointmentController extends Controller
{
    /**
     * View an appointment (Kept index for backward compatibility/route resource convention)
     */
    public function index()
    {
        $userId = Auth::id();

        // Eager load 'payment' relationship to check payment status
        $appointments = Appointment::with(['service', 'schedule', 'payment'])
            ->where('patient_id', $userId)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('schedule_datetime', 'desc')
            ->get()
            ->map(function ($appointment) {
                $serviceName = optional($appointment->service)->service_name ?? 'Service Deleted'; 
                $timeSlot = optional($appointment->schedule)->start_time && optional($appointment->schedule)->end_time ? 
                    Carbon::parse($appointment->schedule->start_time)->format('g:i A') . ' - ' . 
                    Carbon::parse($appointment->schedule->end_time)->format('g:i A') : 
                    'N/A';

                // Get payment status from the related Payment model
                $paymentStatus = optional($appointment->payment)->payment_status 
                    ? ucfirst($appointment->payment->payment_status)
                    : (in_array(strtolower($appointment->status), ['cancelled', 'completed']) ? 'N/A' : 'Paid');

                // FIXED: Only allow cancel/reschedule for Scheduled appointments
                $canModify = $appointment->isScheduled();

                return [
                    'appointment_id' => $appointment->appointment_id,
                    'service_name' => $serviceName,
                    'appointment_date' => $appointment->appointment_date,
                    'schedule_datetime' => $appointment->schedule_datetime,
                    'schedule_id' => $appointment->schedule_id,
                    'status' => ucfirst(strtolower($appointment->status)),
                    'formatted_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                    'formatted_time' => $timeSlot,
                    // FIXED: Only Scheduled appointments can be cancelled or rescheduled
                    'can_cancel' => $canModify,
                    'can_reschedule' => $canModify, 
                    'is_scheduled' => $appointment->isScheduled(),
                    'is_completed' => $appointment->isCompleted(),
                    'is_cancelled' => $appointment->isCancelled(),
                    'is_rescheduled' => $appointment->isRescheduled(),
                    'payment_status' => $paymentStatus,
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

/**
 * Store the appointment - ULTRA FIXED DOUBLE BOOKING PREVENTION
 */
public function store(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'service_id' => 'required|exists:services,id',
            'schedule_id' => 'required|exists:schedules,id',
            'appointment_date' => 'required|date|after_or_equal:today',
        ]);

        $schedule = Schedule::findOrFail($validated['schedule_id']);
        $scheduleDateTime = Carbon::parse($validated['appointment_date'])
            ->setTimeFromTimeString($schedule->start_time);

        // ✅ Check again inside transaction (atomic)
        $isTaken = Appointment::lockForUpdate()
            ->where('schedule_id', $validated['schedule_id'])
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->exists();

        if ($isTaken) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this time slot has just been taken. Please choose another one.',
            ], 409);
        }

        // ✅ Proceed with booking
        $appointment = Appointment::create([
            'patient_id' => $validated['patient_id'],
            'service_id' => $validated['service_id'],
            'schedule_id' => $validated['schedule_id'],
            'appointment_date' => $validated['appointment_date'],
            'schedule_datetime' => $scheduleDateTime,
            'status' => 'pending',
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'data' => $appointment,
        ]);

    } catch (QueryException $e) {
        DB::rollBack();

        // ✅ Duplicate slot (MySQL 1062)
        if ($e->errorInfo[1] === 1062) {
            return response()->json([
                'success' => false,
                'message' => 'That time slot is no longer available. Please pick another one.',
            ], 409);
        }

        Log::error('DB Error during booking', [
            'error' => $e->getMessage(),
            'sql' => $e->getSql(),
            'bindings' => $e->getBindings(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Database error while booking appointment.',
        ], 500);

    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Unexpected booking error', ['error' => $e->getMessage()]);

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong while processing your request.',
        ], 500);
    }
}



    /**
     * Show payment page (SIMPLIFIED - No pending session checks)
     */
    public function showPaymentPage()
    {
        // Get the latest appointment for the user that might need payment
        $appointment = Appointment::with(['service', 'schedule', 'payment'])
            ->where('patient_id', Auth::id())
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$appointment) {
            return redirect()->route('customer.appointment.index')->with('error', 'No scheduled appointment found.');
        }

        $service = $appointment->service;
        $schedule = $appointment->schedule;
        $amount = $service->price ?? 300.00;

        return Inertia::render('Customer/PaymentPage', [ 
            'appointment_data' => [
                'appointment_id' => $appointment->appointment_id,
                'service_name' => $service->service_name,
                'appointment_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                'time_slot' => $schedule->start_time . ' - ' . $schedule->end_time,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                'amount' => $amount,
            ]
        ]);
    }

    /**
     * View user's appointments (Dedicated view method for the main list page)
     */
    public function view()
    {
        $user = Auth::user();
        
        // Eager load 'payment' relationship to check payment status
        $appointments = Appointment::with(['service', 'schedule', 'payment'])
            ->where('patient_id', $user->user_id)
            ->orderBy('appointment_date', 'desc')
            ->orderBy('schedule_datetime', 'desc')
            ->get()
            ->map(function ($appointment) {
                $serviceName = optional($appointment->service)->service_name ?? 'Service Deleted'; 
                $timeSlot = optional($appointment->schedule)->start_time && optional($appointment->schedule)->end_time ? 
                    Carbon::parse($appointment->schedule->start_time)->format('g:i A') . ' - ' . 
                    Carbon::parse($appointment->schedule->end_time)->format('g:i A') : 
                    'N/A';

                // Get payment status from the related Payment model
                $paymentStatus = optional($appointment->payment)->payment_status 
                    ? ucfirst($appointment->payment->payment_status)
                    : 'Paid';

                // FIXED: Only allow cancel/reschedule for Scheduled appointments
                $canModify = $appointment->isScheduled();

                return [
                    'appointment_id' => $appointment->appointment_id,
                    'service_name' => $serviceName,
                    'appointment_date' => $appointment->appointment_date,
                    'schedule_datetime' => $appointment->schedule_datetime,
                    'status' => ucfirst(strtolower($appointment->status)),
                    'formatted_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                    'formatted_time' => $timeSlot,
                    // FIXED: Only Scheduled appointments can be cancelled or rescheduled
                    'can_cancel' => $canModify,
                    'can_reschedule' => $canModify, 
                    'is_scheduled' => $appointment->isScheduled(),
                    'is_completed' => $appointment->isCompleted(),
                    'is_cancelled' => $appointment->isCancelled(),
                    'is_rescheduled' => $appointment->isRescheduled(),
                    'payment_status' => $paymentStatus,
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
     * Cancel an appointment - FIXED
     */
    public function cancel(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            $appointment = Appointment::with('schedule')
                ->where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            // FIXED: Use model method for status checking
            if (!$appointment->isScheduled()) {
                return back()->with('error', 'Only scheduled appointments can be cancelled.');
            }
            
            // Update appointment status to Cancelled
            $appointment->update([
                'status' => Appointment::STATUS_CANCELLED,
            ]);

            // Clear caches to make the time slot available again
            $this->clearAvailabilityCaches($appointment->appointment_date);

            Log::info('Appointment cancelled', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'old_date' => $appointment->appointment_date
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment cancelled successfully.');
        });
    }

    /**
     * Reschedule an appointment - FIXED
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
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            // FIXED: Use model method for status checking
            if (!$appointment->isScheduled()) {
                return back()->with('error', 'Only scheduled appointments can be rescheduled.');
            }

            // FIXED: Use model constants for status checking
            $isAlreadyBooked = Appointment::where('schedule_id', $validated['new_schedule_id'])
                ->whereDate('appointment_date', $validated['new_appointment_date'])
                ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED])
                ->where('appointment_id', '!=', $id)
                ->lockForUpdate()
                ->exists();

            if ($isAlreadyBooked) {
                return back()->with('error', 'The selected time slot is already booked. Please choose another time.');
            }

            $newSchedule = Schedule::find($validated['new_schedule_id']);

            if (!$newSchedule) {
                return back()->withErrors([
                    'error' => 'Selected schedule not found. Please try again.'
                ]);
            }

            // Parse the date and set time from the schedule's start_time
            $newScheduleDateTime = Carbon::parse($validated['new_appointment_date'])
                ->setTimeFromTimeString($newSchedule->start_time);
            
            // Store old date for cache clearing
            $oldAppointmentDate = $appointment->appointment_date;
            
            // FIXED: Use model constant
            $appointment->update([
                'schedule_id'       => $validated['new_schedule_id'],
                'appointment_date'  => $validated['new_appointment_date'],
                'schedule_datetime' => $newScheduleDateTime,
                'status'            => Appointment::STATUS_RESCHEDULED,
            ]);

            // Clear caches for both old and new dates
            $this->clearAvailabilityCaches($oldAppointmentDate);
            $this->clearAvailabilityCaches($validated['new_appointment_date']);

            Log::info('Appointment rescheduled successfully', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'old_date' => $oldAppointmentDate,
                'new_date' => $validated['new_appointment_date'],
                'status' => Appointment::STATUS_SCHEDULED
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment rescheduled successfully.');
        });
    }

/**
 * Get available time slots for a specific date - FIXED RACE CONDITION
 */
public function getAvailableSlots(Request $request)
{
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

    // Check if date is a Sunday or Monday (clinic closed)
    $dayOfWeek = $selectedDate->dayOfWeek;
    if ($dayOfWeek === 0 || $dayOfWeek === 1) { // 0 = Sunday, 1 = Monday
        return response()->json([
            'success' => false,
            'message' => 'Clinic is closed on Sundays and Mondays.',
            'available_slots' => []
        ], 422);
    }

    // Check if date is a holiday
    $philippineHolidays = [
        // 2025 Regular Holidays
        "2025-01-01", "2025-04-17", "2025-04-18", "2025-04-19", "2025-04-20", 
        "2025-04-09", "2025-05-01", "2025-06-12", "2025-08-21", "2025-08-25", 
        "2025-11-01", "2025-11-30", "2025-12-25", "2025-12-30", "2025-12-31",
        // 2025 Special Non-Working Holidays
        "2025-01-29", "2025-02-25", "2025-03-01", "2025-03-31", "2025-11-02", 
        "2025-12-08", "2025-12-24",
        // 2026 Regular Holidays
        "2026-01-01", "2026-04-02", "2026-04-03", "2026-04-04", "2026-04-05", 
        "2026-04-09", "2026-05-01", "2026-06-12", "2026-08-21", "2026-08-31", 
        "2026-11-01", "2026-11-30", "2026-12-25", "2026-12-30", "2026-12-31",
        // 2026 Special Non-Working Holidays
        "2026-02-17", "2026-02-25", "2026-02-18", "2026-03-20", "2026-11-02", 
        "2026-12-08", "2026-12-24"
    ];

    if (in_array($date, $philippineHolidays)) {
        $holidayName = $this->getHolidayName($date);
        return response()->json([
            'success' => false,
            'message' => $holidayName ? "Clinic closed for {$holidayName}" : "Clinic closed for holiday",
            'available_slots' => []
        ], 422);
    }

    // Use shorter cache time and include timestamp for real-time updates
    $cacheKey = "available_slots:{$date}:" . Carbon::now()->format('Y-m-d_H');
    $cachedSlots = Cache::get($cacheKey);
    
    if ($cachedSlots) {
        return response()->json([
            'success' => true,
            'available_slots' => $cachedSlots,
            'date' => $date,
            'cached' => true,
            'cache_key' => $cacheKey
        ]);
    }
    
    // Use database lock to prevent race conditions during the query
    $availableSlots = DB::transaction(function () use ($date) {
        // Get all active schedules
        $allSchedules = Schedule::select(
                'schedule_id',
                'start_time',
                'end_time'
            )
            ->orderBy('start_time', 'asc')
            ->get();

        // Get taken schedule IDs for this date with lock
        $takenScheduleIds = Appointment::whereDate('appointment_date', $date)
            ->whereNotIn('status', [
                Appointment::STATUS_CANCELLED, 
                Appointment::STATUS_COMPLETED
            ])
            ->lockForUpdate() // Prevent other transactions from reading until this completes
            ->pluck('schedule_id')
            ->toArray();

        // Filter out taken schedules
        return $allSchedules->filter(function ($schedule) use ($takenScheduleIds) {
                return !in_array($schedule->schedule_id, $takenScheduleIds);
            })
            ->map(function ($schedule) {
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') .
                        ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                ];
            })
            ->values();
    });

    // Cache for only 2 minutes instead of 5 for more real-time data
    Cache::put($cacheKey, $availableSlots, 120);

    return response()->json([
        'success' => true,
        'available_slots' => $availableSlots,
        'date' => $date,
        'cached' => false,
        'cache_key' => $cacheKey
    ]);
}

/**
 * Helper function to get holiday name
 */
private function getHolidayName($date)
{
    $holidayNames = [
        // 2025 Regular Holidays
        "2025-01-01" => "New Year's Day",
        "2025-04-17" => "Maundy Thursday",
        "2025-04-18" => "Good Friday",
        "2025-04-19" => "Black Saturday",
        "2025-04-20" => "Easter Sunday",
        "2025-04-09" => "Araw ng Kagitingan",
        "2025-05-01" => "Labor Day",
        "2025-06-12" => "Independence Day",
        "2025-08-21" => "Ninoy Aquino Day",
        "2025-08-25" => "National Heroes Day",
        "2025-11-01" => "All Saints' Day",
        "2025-11-30" => "Bonifacio Day",
        "2025-12-25" => "Christmas Day",
        "2025-12-30" => "Rizal Day",
        "2025-12-31" => "New Year's Eve",

        // 2025 Special Non-Working Holidays
        "2025-01-29" => "Chinese New Year",
        "2025-02-25" => "EDSA Revolution Anniversary",
        "2025-03-01" => "Ramadan Start",
        "2025-03-31" => "Eid'l Fitr",
        "2025-11-02" => "All Souls' Day",
        "2025-12-08" => "Feast of the Immaculate Conception",
        "2025-12-24" => "Christmas Eve",

        // 2026 Regular Holidays
        "2026-01-01" => "New Year's Day",
        "2026-04-02" => "Maundy Thursday",
        "2026-04-03" => "Good Friday",
        "2026-04-04" => "Black Saturday",
        "2026-04-05" => "Easter Sunday",
        "2026-04-09" => "Araw ng Kagitingan",
        "2026-05-01" => "Labor Day",
        "2026-06-12" => "Independence Day",
        "2026-08-21" => "Ninoy Aquino Day",
        "2026-08-31" => "National Heroes Day",
        "2026-11-01" => "All Saints' Day",
        "2026-11-30" => "Bonifacio Day",
        "2026-12-25" => "Christmas Day",
        "2026-12-30" => "Rizal Day",
        "2026-12-31" => "New Year's Eve",

        // 2026 Special Non-Working Holidays
        "2026-02-17" => "Chinese New Year",
        "2026-02-25" => "EDSA Revolution Anniversary",
        "2026-02-18" => "Ramadan Start",
        "2026-03-20" => "Eid'l Fitr",
        "2026-11-02" => "All Souls' Day",
        "2026-12-08" => "Feast of the Immaculate Conception",
        "2026-12-24" => "Christmas Eve"
    ];

    return $holidayNames[$date] ?? null;
}

    /**
     * Check slot availability - FIXED
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

        // FIXED: Use model constants
        $isAvailable = !Appointment::where('schedule_id', $request->schedule_id)
            ->whereDate('appointment_date', $request->date)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED])
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
     * Get available dates - FIXED
     */
    public function getAvailableDates(Request $request)
    {
        $startDate = Carbon::tomorrow(); 
        $endDate = Carbon::now()->addMonths(3);
        
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

        // FIXED: Use model constants
        $bookedCounts = Appointment::whereDate('appointment_date', '>=', $startDate)
            ->whereDate('appointment_date', '<=', $endDate)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED])
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
        
        Cache::put($cacheKey, $availableDates, 900);

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

/**
 * Clear availability caches for specific date 
 */
private function clearAvailabilityCaches($date)
{
    $date = Carbon::parse($date)->format('Y-m-d');
    
    // Clear all cache variations for this date
    $today = Carbon::now()->format('Y-m-d_H');
    $cacheKeys = [
        "available_slots:{$date}",
        "available_slots:{$date}:{$today}",
    ];
    
    // Clear for current hour and previous hour to be safe
    $previousHour = Carbon::now()->subHour()->format('Y-m-d_H');
    $cacheKeys[] = "available_slots:{$date}:{$previousHour}";
    
    foreach ($cacheKeys as $key) {
        Cache::forget($key);
    }
    
    Cache::forget('available_dates_range');
    
    Log::info('Availability caches cleared aggressively', [
        'date' => $date, 
        'cache_keys' => $cacheKeys
    ]);
}

    /**
     * Send individual appointment reminder email
     */
    private function sendAppointmentReminder($appointment)
    {
        try {
            $user = $appointment->patient ?? User::find($appointment->patient_id);
            $service = $appointment->service ?? Service::find($appointment->service_id);
            $schedule = $appointment->schedule ?? Schedule::find($appointment->schedule_id);

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
     * Command method to send daily reminders (Scheduled Task)
     */
    public function sendDailyReminders()
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        
        $appointments = Appointment::with(['patient', 'service', 'schedule'])
            ->whereDate('appointment_date', $tomorrow)
            ->where('status', Appointment::STATUS_SCHEDULED)
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
            'date' => $tomorrow,
            'reminders_sent' => $sentCount,
            'reminders_failed' => $failedCount,
            'total_appointments_checked' => $appointments->count()
        ]);

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $appointments->count()
        ];
    }

    /**
     * Mark appointment as completed (for admin use or automatic completion after service)
     */
    public function complete($id)
    {
        return DB::transaction(function () use ($id) {
            $appointment = Appointment::where('appointment_id', $id)
                ->where('patient_id', Auth::id())
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                return back()->with('error', 'Appointment not found.');
            }

            // FIXED: Use model method
            if (!$appointment->isScheduled()) {
                return back()->with('error', 'Only scheduled appointments can be marked as completed.');
            }
            
            $appointment->update([
                'status' => Appointment::STATUS_COMPLETED,
            ]);

            $this->clearAvailabilityCaches($appointment->appointment_date);

            Log::info('Appointment marked as completed', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment marked as completed successfully.');
        });
    }
}