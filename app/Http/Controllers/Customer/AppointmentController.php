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

class AppointmentController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        // Get all schedules for operating hours reference
        $schedules = Schedule::all();

        return Inertia::render('Customer/ScheduleAppointment', [
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact_no' => $user->contact_no,
            ],
            'services' => Service::all(),
            'schedules' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'schedule_datetime' => 'required|date',
        ]);

        // Parse the datetime
        $scheduleDateTime = Carbon::parse($validated['schedule_datetime']);

        // check if time slot available
        $existingAppointment = Appointment::where('patient_id', Auth::id())->whereIn('status', ['pending', 'ongoing', 'confirmed'])->first();

        if ($existingAppointment) {
            return back()->withErrors([
                'error' => 'You already have a pending or ongoing appointment.'
            ]);
        }

        // check the selected datetime to conflict to any user
        $conflictingAppointment = Appointment::where('schedule_datetime', $validated['schedule_datetime'])->whereIn('status', ['pending', 'ongoing', 'confirmed'])->first();

        if ($conflictingAppointment) {
            Log::warning('Time slot conflict detected', [
                'requested_datetime' => $validated['schedule_datetime'],
                'existing_appointment_id' => $conflictingAppointment->appointment_id,
                'existing_user_id' => $conflictingAppointment->patient_id,
                'current_user_id' => Auth::id(),
                'existing_status' => $conflictingAppointment->status
            ]);
            
            return back()->withErrors([
                'error' => 'This time slot is already booked. Please choose another time.'
            ]);
        }

        $scheduleTime = $scheduleDateTime->format('H:i:s');
        $dayOfWeek = $scheduleDateTime->dayOfWeek;
        
        $scheduleForDay = Schedule::where('day_of_week', $dayOfWeek)->first();
        
        if (!$scheduleForDay || !$this->isTimeWithinSchedule($scheduleTime, $scheduleForDay)) {
            return back()->withErrors([
                'error' => 'The selected time is outside of operating hours.'
            ]);
        }

        // store the appointment data in session for payment processing
        session([
            'pending_appointment' => [
                'service_id' => $validated['service_id'],
                'schedule_datetime' => $validated['schedule_datetime'],
                'user_id' => Auth::id(),
            ]
        ]);

        Log::info('Appointment data stored in session for payment', [
            'user_id' => Auth::id(),
            'schedule_datetime' => $validated['schedule_datetime'],
            'service_id' => $validated['service_id']
        ]);

        // Redirect to payment page
        return redirect()->route('customer.payment.view');
    }

    /**
     * Show payment page for the pending appointment
     */
    public function showPaymentPage()
    {
        $pendingAppointment = session('pending_appointment');
        
        if (!$pendingAppointment || $pendingAppointment['user_id'] != Auth::id()) {
            return redirect()->route('customer.appointment')->with('error', 'No pending appointment found. Please schedule an appointment first.');
        }

        $service = Service::find($pendingAppointment['service_id']);
        
        if (!$service) {
            return redirect()->route('customer.appointment')->with('error', 'Service not found. Please try again.');
        }

        return Inertia::render('Customer/PaymentPage', [
            'appointment_data' => [
                'service_id' => $pendingAppointment['service_id'],
                'service_name' => $service->service_name,
                'schedule_datetime' => $pendingAppointment['schedule_datetime'],
                'date' => Carbon::parse($pendingAppointment['schedule_datetime'])->format('Y-m-d'),
                'time' => Carbon::parse($pendingAppointment['schedule_datetime'])->format('g:i A'),
                'amount' => 300.00, // Fixed amount for all services
            ]
        ]);
    }

    /**
     * View appointments for the logged-in user
     */
    public function view()
    {
        $user = Auth::user();
        $appointments = Appointment::with(['service'])
            ->where('patient_id', $user->user_id)
            ->orderBy('schedule_datetime', 'desc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'appointment_id' => $appointment->appointment_id,
                    'service_name' => $appointment->service->service_name,
                    'schedule_datetime' => $appointment->schedule_datetime,
                    'status' => $appointment->status,
                    'formatted_date' => Carbon::parse($appointment->schedule_datetime)->format('F j, Y'),
                    'formatted_time' => Carbon::parse($appointment->schedule_datetime)->format('g:i A'),
                    'can_cancel' => in_array($appointment->status, ['pending', 'confirmed']),
                    'can_reschedule' => in_array($appointment->status, ['pending', 'confirmed']),
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
        $appointment = Appointment::where('appointment_id', $id)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return back()->with('error', 'Appointment not found.');
        }

        // Only allow cancellation of pending or confirmed appointments
        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return back()->with('error', 'This appointment cannot be cancelled.');
        }

        $appointment->update(['status' => 'cancelled']);

        Log::info('Appointment cancelled', [
            'appointment_id' => $appointment->appointment_id,
            'user_id' => Auth::id(),
            'previous_status' => $appointment->status
        ]);

        return redirect()->route('customer.view')->with('success', 'Appointment cancelled successfully.');
    }

    /**
     * Reschedule an appointment
     */
    public function reschedule(Request $request, $id)
    {
        $validated = $request->validate([
            'new_schedule_datetime' => 'required|date',
        ]);

        $appointment = Appointment::where('appointment_id', $id)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return back()->with('error', 'Appointment not found.');
        }

        // only allow user that confirmed payment to resched
        if (!in_array($appointment->status, ['confirmed'])) {
            return back()->with('error', 'This appointment cannot be rescheduled.');
        }

        // check if the new datetime conflicts with existing appointments
        $conflictingAppointment = Appointment::where('schedule_datetime', $validated['new_schedule_datetime'])
            ->whereIn('status', ['pending', 'ongoing', 'confirmed'])
            ->where('appointment_id', '!=', $id)
            ->first();

        if ($conflictingAppointment) {
            return back()->with('error', 'The selected time slot is already booked. Please choose another time.');
        }

        $scheduleDateTime = Carbon::parse($validated['new_schedule_datetime']);
        $scheduleTime = $scheduleDateTime->format('H:i:s');
        $dayOfWeek = $scheduleDateTime->dayOfWeek;
        
        $scheduleForDay = Schedule::where('day_of_week', $dayOfWeek)->first();
        
        if (!$scheduleForDay || !$this->isTimeWithinSchedule($scheduleTime, $scheduleForDay)) {
            return back()->with('error', 'The selected time is outside of operating hours.');
        }

        // update the appointment
        $appointment->update([
            'schedule_datetime' => $validated['new_schedule_datetime'],
            'status' => 'rescheduled',
        ]);

        Log::info('Appointment rescheduled', [
            'appointment_id' => $appointment->appointment_id,
            'user_id' => Auth::id(),
            'previous_datetime' => $appointment->getOriginal('schedule_datetime'),
            'new_datetime' => $validated['new_schedule_datetime'],
            'previous_status' => $appointment->getOriginal('status')
        ]);

        return redirect()->route('customer.view')->with('success', 'Appointment rescheduled successfully.');
    }

    /**
     * Show reschedule form
     */
    public function showRescheduleForm($id)
    {
        $appointment = Appointment::with(['service'])
            ->where('appointment_id', $id)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return redirect()->route('customer.view')->with('error', 'Appointment not found.');
        }

        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return redirect()->route('customer.view')->with('error', 'This appointment cannot be rescheduled.');
        }

        $schedules = Schedule::all();

        return Inertia::render('Customer/RescheduleAppointment', [
            'appointment' => [
                'appointment_id' => $appointment->appointment_id,
                'service_name' => $appointment->service->service_name,
                'current_schedule_datetime' => $appointment->schedule_datetime,
                'current_date' => Carbon::parse($appointment->schedule_datetime)->format('Y-m-d'),
                'current_time' => Carbon::parse($appointment->schedule_datetime)->format('g:i A'),
                'status' => $appointment->status,
            ],
            'schedules' => $schedules,
            'services' => Service::all(),
        ]);
    }

    /**
     * Check if the selected time is within the schedule's operating hours
     */
    private function isTimeWithinSchedule($selectedTime, $schedule)
    {
        // Convert times to comparable format
        $selected = Carbon::createFromFormat('H:i:s', $selectedTime);
        $opening = Carbon::createFromFormat('H:i:s', $schedule->opening_time);
        $closing = Carbon::createFromFormat('H:i:s', $schedule->closing_time);

        return $selected->between($opening, $closing);
    }

    /**
     * Get available time slots for a specific date
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        // Get schedules for that day of the week
        $schedules = Schedule::where('day_of_week', $dayOfWeek)->get();

        $availableSlots = [];

        foreach ($schedules as $schedule) {
            // Generate time slots based on schedule
            $slots = $this->generateTimeSlots($schedule, $date);
            
            // Remove booked slots
            $availableSlots = array_merge($availableSlots, $this->filterBookedSlots($slots, $date));
        }

        return response()->json(['available_slots' => $availableSlots]);
    }

    /**
     * Generate time slots based on schedule
     */
    private function generateTimeSlots($schedule, $date)
    {
        $slots = [];
        $interval = 30; // 30-minute intervals

        $start = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $schedule->opening_time);
        $end = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $schedule->closing_time);

        while ($start < $end) {
            $slotEnd = $start->copy()->addMinutes($interval);
            
            if ($slotEnd <= $end) {
                $slots[] = [
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                    'display_time' => $start->format('g:i A') . ' - ' . $slotEnd->format('g:i A'),
                    'datetime' => $start->format('Y-m-d H:i:s'),
                ];
            }
            
            $start->addMinutes($interval);
        }

        return $slots;
    }

    /**
     * Filter out already booked slots
     */
    private function filterBookedSlots($slots, $date)
    {
        // Get all booked appointments for this date
        $bookedAppointments = Appointment::whereDate('schedule_datetime', $date)
            ->whereIn('status', ['pending', 'ongoing', 'confirmed', 'rescheduled'])
            ->pluck('schedule_datetime')
            ->map(function ($datetime) {
                return Carbon::parse($datetime)->format('Y-m-d H:i:s');
            })
            ->toArray();

        $availableSlots = array_filter($slots, function ($slot) use ($bookedAppointments) {
            return !in_array($slot['datetime'], $bookedAppointments);
        });

        return $availableSlots;
    }

    /**
     * Check slot availability for a specific datetime
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'schedule_datetime' => 'required|date',
        ]);

        $scheduleDatetime = $request->input('schedule_datetime');

        // Check if the selected datetime conflicts with existing appointments
        $isAvailable = !Appointment::where('schedule_datetime', $scheduleDatetime)
            ->whereIn('status', ['pending', 'ongoing', 'confirmed', 'rescheduled'])
            ->exists();

        return response()->json([
            'available' => $isAvailable,
            'schedule_datetime' => $scheduleDatetime
        ]);
    }
}