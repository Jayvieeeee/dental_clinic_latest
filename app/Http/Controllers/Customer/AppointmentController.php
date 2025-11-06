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
                    'payment_status' => ucfirst($appointment->payment_status ?? 'Pending'), // Changed default to Pending
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
        Log::info('=== APPOINTMENT STORE STARTED ===', $request->all());

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

            // Create the appointment - Automatically save to DB even with pending payment
            $appointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $validated['service_id'],
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'status' => 'pending', 
                'payment_status' => 'pending', // Explicitly set payment status as pending
                // REMOVED: schedule_datetime, created_by (they don't exist in DB)
            ]);

            Log::info('✅ APPOINTMENT CREATED SUCCESSFULLY', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);

            // Set session for payment - appointment is already saved in DB
            session([
                'pending_appointment' => [
                    'appointment_id' => $appointment->appointment_id,
                    'service_id' => $validated['service_id'],
                    'schedule_id' => $validated['schedule_id'],
                    'appointment_date' => $validated['appointment_date'],
                    'user_id' => Auth::id(),
                ]
            ]);

            // Return success response - appointment is saved regardless of payment
            return redirect()->route('customer.payment.view')->with([
                'success' => 'Appointment scheduled successfully! Please complete the payment to confirm your booking.'
            ]);
        });
    }

    /**
     * Show payment page
     */
    public function showPaymentPage()
    {
        $pendingAppointment = session('pending_appointment');
        
        if (!$pendingAppointment || $pendingAppointment['user_id'] != Auth::id()) {
            // If no session, check if user has a pending appointment in database
            $pendingAppointment = Appointment::where('patient_id', Auth::id())
                ->where('status', 'pending')
                ->where('payment_status', 'pending')
                ->latest()
                ->first();
                
            if (!$pendingAppointment) {
                return redirect()->route('customer.appointment')->with('error', 'No pending appointment found.');
            }
            
            // Recreate session from database
            session([
                'pending_appointment' => [
                    'appointment_id' => $pendingAppointment->appointment_id,
                    'service_id' => $pendingAppointment->service_id,
                    'schedule_id' => $pendingAppointment->schedule_id,
                    'appointment_date' => $pendingAppointment->appointment_date,
                    'user_id' => Auth::id(),
                ]
            ]);
        }

        $appointment = Appointment::find($pendingAppointment['appointment_id']);
        $service = Service::find($pendingAppointment['service_id']);
        $schedule = Schedule::find($pendingAppointment['schedule_id']);
        
        if (!$appointment || !$service || !$schedule) {
            return redirect()->route('customer.appointment')->with('error', 'Appointment data not found.');
        }

        return Inertia::render('Customer/ViewAppointment', [
            'appointment_data' => [
                'appointment_id' => $appointment->appointment_id,
                'service_name' => $service->service_name,
                'appointment_date' => $appointment->appointment_date,
                'time_slot' => $schedule->start_time . ' - ' . $schedule->end_time,
                'display_time' => Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A'),
                'amount' => 300.00,
                'status' => $appointment->status,
                'payment_status' => $appointment->payment_status,
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
                    'status' => $appointment->status,
                    'payment_status' => $appointment->payment_status ?? 'pending',
                    'formatted_date' => Carbon::parse($appointment->appointment_date)->format('F j, Y'),
                    'formatted_time' => $timeSlot,
                    'can_cancel' => $appointment->status === 'confirmed',
                    'can_reschedule' => $appointment->status === 'confirmed',
                    'is_pending' => $appointment->status === 'pending',
                    'is_confirmed' => $appointment->status === 'confirmed',
                    'is_cancelled' => $appointment->status === 'cancelled',
                    'is_completed' => $appointment->status === 'completed',
                    'needs_payment' => ($appointment->status === 'pending' && $appointment->payment_status === 'pending'),
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

            // Allow cancellation of both pending and confirmed appointments
            if (!in_array($appointment->status, ['pending', 'confirmed'])) {
                return back()->with('error', 'Only pending or confirmed appointments can be cancelled.');
            }

            // Update appointment status to cancelled
            $appointment->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled', // Also update payment status
                // REMOVED: cancelled_at, cancelled_by (they don't exist in DB)
            ]);

            Log::info('Appointment cancelled', [
                'appointment_id' => $appointment->appointment_id,
                'user_id' => Auth::id(),
                'previous_status' => $appointment->getOriginal('status'),
                'previous_payment_status' => $appointment->getOriginal('payment_status'),
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment cancelled successfully.');
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

            // Allow rescheduling of both pending and confirmed appointments
            if (!in_array($appointment->status, ['pending', 'confirmed'])) {
                return back()->with('error', 'Only pending or confirmed appointments can be rescheduled.');
            }

            $isAlreadyBooked = Appointment::where('schedule_id', $validated['new_schedule_id'])
                ->whereDate('appointment_date', $validated['new_appointment_date'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('appointment_id', '!=', $id)
                ->exists();

            if ($isAlreadyBooked) {
                return back()->with('error', 'The selected time slot is already booked. Please choose another time.');
            }

            // Update appointment record
            $appointment->update([
                'schedule_id'        => $validated['new_schedule_id'],
                'appointment_date'   => $validated['new_appointment_date'],
                'status'             => $appointment->status, // Keep the same status
                // REMOVED: schedule_datetime, rescheduled_at, rescheduled_by (they don't exist in DB)
            ]);

            Log::info('Appointment rescheduled successfully', [
                'appointment_id'         => $appointment->appointment_id,
                'user_id'                => Auth::id(),
                'previous_schedule_id'   => $appointment->getOriginal('schedule_id'),
                'new_schedule_id'        => $validated['new_schedule_id'],
                'previous_date'          => $appointment->getOriginal('appointment_date'),
                'new_date'               => $validated['new_appointment_date'],
                'status'                 => $appointment->status,
                'payment_status'         => $appointment->payment_status,
            ]);

            return redirect()
                ->route('customer.view')
                ->with('success', 'Appointment rescheduled successfully.');
        });
    }

    // ... (rest of your methods remain the same: getAvailableSlots, checkAvailability, getAvailableDates, sendAppointmentReminder, sendDailyReminders)

    /**
     * Method to handle successful payment
     */
    public function markAsPaid($appointmentId)
    {
        return DB::transaction(function () use ($appointmentId) {
            $appointment = Appointment::where('appointment_id', $appointmentId)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                return false;
            }

            $appointment->update([
                'payment_status' => 'paid',
                'status' => 'confirmed', // Change status to confirmed upon payment
            ]);

            Log::info('Appointment marked as paid', [
                'appointment_id' => $appointmentId,
                'user_id' => Auth::id(),
            ]);

            // Clear the pending appointment session
            session()->forget('pending_appointment');

            return true;
        });
    }

    /**
     * Method to handle failed payment
     */
    public function markPaymentFailed($appointmentId)
    {
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->first();

        if ($appointment) {
            $appointment->update([
                'payment_status' => 'failed',
                // Status remains pending until payment is successful
            ]);

            Log::info('Appointment payment failed', [
                'appointment_id' => $appointmentId,
                'user_id' => Auth::id(),
            ]);
        }
    }
}