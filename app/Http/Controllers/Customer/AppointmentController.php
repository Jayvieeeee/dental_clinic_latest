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
     * View an appointment (Kept index for backward compatibility/route resource convention)
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
                    'date_raw' => optional($appointment->appointment_date)->format('Y-m-d') ?? 'N/A',
                    'schedule_id' => $appointment->schedule_id,
                    'procedure' => $appointment->service->service_name ?? 'N/A',
                    'date' => optional($appointment->appointment_date)->format('m-d-Y') ?? 'N/A',
                    'time' => $appointment->schedule 
                        ? date('g:i a', strtotime($appointment->schedule->start_time)) . 
                          ' - ' . date('g:i a', strtotime($appointment->schedule->end_time))
                        : 'N/A',
                    'status' => ucfirst($appointment->status),
                    // Ensure payment_status is correctly retrieved or defaulted
                    'payment_status' => ucfirst($appointment->payment_status ?? 'Pending Payment'), 
                ];
            });

        // NOTE: index() typically renders a list, the dedicated view() method below might be better for a full list
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
    * Store the initial PENDING appointment and prepare for payment
    * NOTE: The 'created_by' column reference has been removed.
    */
   public function store(Request $request)
    {
        Log::info('=== APPOINTMENT STORE STARTED ===', $request->all());

        $validated = $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'schedule_id' => 'required|exists:schedules,schedule_id',
            'appointment_date' => 'required|date|after:today',
        ]);

        return DB::transaction(function () use ($validated) {
            // Check if appointment date is at least 1 day in advance (Redundant check due to 'after:today' but kept for explicit logic)
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
                // Exclude cancelled/completed/failed status checks
                ->whereIn('status', ['pending', 'confirmed']) 
                ->exists();

            if ($isAlreadyBooked) {
                return back()->withErrors([
                    'error' => 'This time slot is already booked. Please choose another time.'
                ]);
            }

            // Get the schedule to create proper datetime
            $schedule = Schedule::find($validated['schedule_id']);
            
            // Use start_time for schedule_datetime field
            $scheduleDateTime = Carbon::parse($validated['appointment_date'] . ' ' . $schedule->start_time);
            
            // Get service for price
            $service = Service::find($validated['service_id']);
            $amount = $service->price ?? 300.00; // Use service price or default

            // Create the appointment (Status: 'pending' for payment)
            $appointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $validated['service_id'],
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'schedule_datetime' => $scheduleDateTime,
                'status' => 'pending', 
                'payment_status' => 'pending_payment', // Set explicit initial payment status
                // 'created_by' removed as column doesn't exist
            ]);
            
            // Set session data for payment flow
            session([
                'pending_payment' => [
                    'temp_appointment_id' => $appointment->appointment_id, 
                    'service_id' => $validated['service_id'],
                    'schedule_id' => $validated['schedule_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'user_id' => Auth::id(),
                    // Use the fetched amount
                    'amount' => $amount, 
                ],
                'pending_appointment' => [
                    'appointment_id' => $appointment->appointment_id,
                    'service_id' => $validated['service_id'],
                    'schedule_id' => $validated['schedule_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'user_id' => Auth::id(),
                ]
            ]);

            Log::info('✅ Appointment created successfully (Pending for Payment)', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
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
            return redirect()->route('customer.appointment.index')->with('error', 'No pending appointment found.'); // Changed route to index for consistency
        }

        $appointment = Appointment::find($pendingAppointment['appointment_id']);
        $service = Service::find($pendingAppointment['service_id']);
        $schedule = Schedule::find($pendingAppointment['schedule_id']);
        
        if (!$appointment || !$service || !$schedule) {
             // If the appointment exists in session but not DB, clear session
             session()->forget('pending_appointment');
             session()->forget('pending_payment');
             return redirect()->route('customer.appointment.index')->with('error', 'Appointment data not found or session mismatch.'); // Changed route to index for consistency
        }

        // IMPROVEMENT: Ensure the amount is correctly calculated/retrieved, not hardcoded.
        // Retrieve amount from session or service, prioritizing the session amount if it was correctly set in store()
        $pendingPayment = session('pending_payment');
        $amountInPesos = $pendingPayment['amount'] ?? $service->price ?? 300.00; 

        return Inertia::render('Customer/PaymentPage', [ 
            'appointment_data' => [
                'appointment_id' => $appointment->appointment_id,
                'service_name' => $service->service_name,
                'appointment_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                'time_slot' => $schedule->start_time . ' - ' . $schedule->end_time,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                'amount' => $amountInPesos, 
            ]
        ]);
    }

    /**
     * View user's appointments (Dedicated view method for the main list page)
     */
    public function view()
    {
        $user = Auth::user();
        
        // Use $user->id instead of $user->user_id if 'id' is the primary key and relationship field
        // Assuming $user->user_id is the correct column name for the patient_id lookup
        $appointments = Appointment::with(['service', 'schedule'])
            ->where('patient_id', $user->user_id) 
            ->orderBy('appointment_date', 'desc')
            ->orderBy('schedule_datetime', 'desc')
            ->get()
            ->map(function ($appointment) {
                // Ensure service and schedule are present before accessing properties
                $serviceName = optional($appointment->service)->service_name ?? 'Service Deleted'; 
                $timeSlot = optional($appointment->schedule)->start_time && optional($appointment->schedule)->end_time ? 
                    Carbon::parse($appointment->schedule->start_time)->format('g:i A') . ' - ' . 
                    Carbon::parse($appointment->schedule->end_time)->format('g:i A') : 
                    'N/A';

                return [
                    'appointment_id' => $appointment->appointment_id,
                    'service_name' => $serviceName,
                    'appointment_date' => $appointment->appointment_date,
                    'schedule_datetime' => $appointment->schedule_datetime,
                    'status' => $appointment->status,
                    'formatted_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                    'formatted_time' => $timeSlot,
                    // Check logic for reschedule/cancel based on time/status
                    'can_cancel' => $appointment->status === 'confirmed', 
                    'can_reschedule' => $appointment->status === 'confirmed', 
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
     * NOTE: The 'cancelled_at' and 'cancelled_by' column references have been removed.
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

            // Also allow cancellation of 'pending' appointments (before payment confirmation)
            if (!in_array($appointment->status, ['confirmed', 'pending'])) {
                return back()->with('error', 'Only pending or confirmed appointments can be cancelled.');
            }
            
            // Update appointment status to cancelled
            $appointment->update([
                'status' => 'cancelled',
                // Removed 'cancelled_at' and 'cancelled_by' as columns don't exist
            ]);

            Log::info('Appointment cancelled', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment cancelled successfully.');
        });
    }

    /**
     * Reschedule an appointment 
     * NOTE: The 'rescheduled_at' and 'rescheduled_by' column references have been removed.
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

            if (in_array($appointment->status, ['cancelled', 'completed', 'pending'])) { // Added pending, as only confirmed should be rescheduled
                return back()->with('error', 'Only confirmed appointments can be rescheduled.');
            }

            // Check if the NEW slot is already booked by someone else
            $isAlreadyBooked = Appointment::where('schedule_id', $validated['new_schedule_id'])
                ->whereDate('appointment_date', $validated['new_appointment_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('appointment_id', '!=', $id) // Exclude current appointment ID
                ->exists();

            if ($isAlreadyBooked) {
                return back()->with('error', 'The selected time slot is already booked. Please choose another time.');
            }

            $newSchedule = Schedule::find($validated['new_schedule_id']);

            $newScheduleDateTime = Carbon::parse(
                $validated['new_appointment_date'] . ' ' . $newSchedule->start_time
            );
            
            // Update appointment record
            $appointment->update([
                'schedule_id'       => $validated['new_schedule_id'],
                'appointment_date'  => $validated['new_appointment_date'],
                'schedule_datetime' => $newScheduleDateTime, // Use new datetime
                'status'            => 'confirmed', // Maintain confirmed status after successful reschedule
                // Removed 'rescheduled_at' and 'rescheduled_by' as columns don't exist
            ]);

            Log::info('Appointment rescheduled successfully', [
                'appointment_id'            => $appointment->appointment_id,
                'user_id'                   => Auth::id(),
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment rescheduled successfully.');
        });
    }
    
    /**
     * Confirm appointment after successful payment
     * NOTE: The 'confirmed_at' column reference has been removed.
     */
    public function confirmAfterPayment($appointmentId)
    {
        // This should ONLY be called by a trusted route/service after payment success
        return DB::transaction(function () use ($appointmentId) {
            $appointment = Appointment::where('appointment_id', $appointmentId)
                // If called by redirect, keep it for security
                ->where('patient_id', Auth::id()) 
                ->first();

            if (!$appointment) {
                Log::error('Appointment not found for confirmation', ['appointment_id' => $appointmentId]);
                // Use a more specific exception or log a message and return false if not throwing
                throw new \Exception('Appointment not found or unauthorized'); 
            }
            
            if ($appointment->status === 'confirmed') {
                 Log::info('Appointment already confirmed', ['appointment_id' => $appointmentId]);
                 return true;
            }

            // Only allow confirmation if current status is 'pending' (pending payment)
            if ($appointment->status !== 'pending') {
                 Log::error('Attempt to confirm non-pending appointment', ['appointment_id' => $appointmentId, 'status' => $appointment->status]);
                 throw new \Exception('Appointment status is not pending.');
            }

            // Update status to confirmed and payment_status if available
            $appointment->update([
                'status' => 'confirmed',
                'payment_status' => 'paid', // Assuming this field exists and is updated here
                // Removed 'confirmed_at' as column doesn't exist
            ]);

            Log::info('Appointment confirmed after payment', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => $appointment->patient_id, // Use appointment's patient_id
            ]);
            
            // OPTIONAL: Clear session now that payment is done
            session()->forget('pending_appointment');
            session()->forget('pending_payment');

            return true;
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

        // Check if date is at least 1 day in advance (Redundant check but kept for explicit API response)
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
        
        // This query correctly identifies slots that have a 'pending' or 'confirmed' appointment 
        // on the selected date by any user.
        $availableSlots = Schedule::leftJoin('appointments', function($join) use ($date) {
                $join->on('schedules.schedule_id', '=', 'appointments.schedule_id')
                    ->whereDate('appointments.appointment_date', $date)
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

        // Check if date is at least 1 day in advance (Redundant check but kept for explicit API response)
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

    /**
     * Send individual appointment reminder email
     */
    private function sendAppointmentReminder($appointment)
    {
        try {
            // Eager load if not already loaded (though it is in sendDailyReminders)
            $user = $appointment->user ?? User::find($appointment->patient_id);
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
        
        // Get all CONFIRMED appointments for TOMORROW
        $appointments = Appointment::with(['user', 'service', 'schedule'])
            ->whereDate('appointment_date', $tomorrow)
            ->where('status', 'confirmed') // Only send reminders for CONFIRMED slots
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
            'date' => $tomorrow, // Corrected date to tomorrow's date for relevant reminders
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
     * Handle the redirect after successful payment.
     */
    public function paymentSuccessHandler(Request $request)
    {
        $pendingAppointment = session('pending_appointment');

        // 1. Check if we have pending appointment data in the session
        if (!$pendingAppointment || $pendingAppointment['user_id'] != Auth::id()) {
            return redirect()->route('customer.appointment.index')
                ->with('error', 'Payment complete, but no matching pending appointment found. Please contact support.');
        }

        $appointmentId = $pendingAppointment['appointment_id'];

        try {
            // 2. Call the confirmation logic
            $this->confirmAfterPayment($appointmentId); 
            
            // 3. SUCCESS - Redirect to the user's appointment list
            return redirect()->route('customer.view')
                ->with('success', 'Appointment successfully confirmed and paid!');

        } catch (\Exception $e) {
            // 4. LOG ERROR - If confirmation fails for any reason
            Log::error('Failed to confirm appointment after payment.', [
                'appointment_id' => $appointmentId, 
                'error' => $e->getMessage()
            ]);
            return redirect()->route('customer.appointment.index')
                ->with('error', 'Payment was successful, but confirmation failed. Please contact support immediately.');
        }
    }
}