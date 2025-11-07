<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Schedule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminAppointmentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with([
                'patient', 
                'service.tools',
                'schedule',
                'payment'
            ])
            ->orderBy('appointment_date', 'asc')
            ->get()
            ->map(function ($apt) {
                $service = $apt->service;
                $schedule = $apt->schedule;
                $payment = $apt->payment;

                return [
                    'id'        => $apt->appointment_id,
                    'patient'   => trim(($apt->patient->first_name ?? '') . ' ' . ($apt->patient->last_name ?? '')),
                    'procedure' => $service?->service_name ?? 'N/A',
                    'tools'     => $service?->tools->pluck('tool_name')->toArray() ?? [],
                    'datetime'  => $apt->appointment_date
                        ? $apt->appointment_date->format('Y-m-d') . ' ' . ($schedule?->time_slot ?? '')
                        : 'N/A',
                    'time'      => $schedule?->time_slot ?? null,
                    'status'    => ucfirst($apt->status ?? 'confirmed'),
                    'payment'   => $payment?->payment_status ?? 'Paid', 
                    'day'       => strtoupper(optional($apt->appointment_date)->format('D')),
                    'date' => optional($apt->appointment_date)->timezone('Asia/Manila')->format('Y-m-d'),
                    'schedule_id' => $apt->schedule_id,
                ];
            });

        $timeSlots = Schedule::select('start_time', 'end_time')
            ->get()
            ->map(function ($s) {
                $start = date('h:i A', strtotime($s->start_time));
                $end   = date('h:i A', strtotime($s->end_time));
                return "$start - $end";
            })
            ->unique()
            ->values()
            ->toArray();

        $stats = [
            'scheduled' => Appointment::where('status', 'scheduled')->count(),
            'rescheduled'    => Appointment::where('status', 'rescheduled')->count(),
            'cancelled'      => Appointment::where('status', 'cancelled')->count(),
            'completed'      => Appointment::where('status', 'completed')->count(),
        ];

        return Inertia::render('Admin/AppointmentTable', [
            'appointments' => $appointments,
            'stats'        => $stats,
            'timeSlots'    => $timeSlots,
        ]);
    }

    public function updateStatus($id)
    {
        try {
            $appointment = Appointment::findOrFail($id);
            
            $validated = request()->validate([
                'status' => 'required|in:confirmed,rescheduled,cancelled,completed'
            ]);

            Log::info('Updating appointment status:', [
                'appointment_id' => $id,
                'old_status' => $appointment->status,
                'new_status' => $validated['status'],
                'user' => Auth::id()
            ]);

            $appointment->update([
                'status' => $validated['status']
            ]);

            return back()->with('success', 'Appointment status updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update appointment status:', [
                'appointment_id' => $id,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'Failed to update appointment status.');
        }
    }

    public function reschedule(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $appointment = Appointment::with('schedule')->findOrFail($id);

            // Check if appointment is cancelled
            if ($appointment->status === 'cancelled') {
                return back()->with('error', 'Cancelled appointments cannot be rescheduled.');
            }

            $validated = $request->validate([
                'date' => 'required|date|after:today',
                'schedule_id' => 'required|exists:schedules,schedule_id',
            ]);

            Log::info('Admin reschedule attempt:', [
                'appointment_id' => $id,
                'current_date' => $appointment->appointment_date,
                'new_date' => $validated['date'],
                'new_schedule_id' => $validated['schedule_id'],
                'current_status' => $appointment->status
            ]);

            // Check if the selected schedule is available
            $isScheduleTaken = Appointment::where('appointment_date', $validated['date'])
                ->where('schedule_id', $validated['schedule_id'])
                ->where('appointment_id', '!=', $id)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($isScheduleTaken) {
                return back()->with('error', 'The selected time slot is already taken.');
            }

            // Update the appointment - set to rescheduled status
            $appointment->update([
                'appointment_date' => $validated['date'],
                'schedule_id' => $validated['schedule_id'],
                'status' => 'rescheduled', // Use rescheduled status
            ]);

            DB::commit();

            Log::info('Appointment rescheduled successfully:', [
                'appointment_id' => $id,
                'new_date' => $validated['date'],
                'new_schedule_id' => $validated['schedule_id']
            ]);

            return redirect()->route('admin.appointments.index')
                ->with('success', 'Appointment rescheduled successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Reschedule validation failed:', $e->errors());
            return back()->withErrors($e->errors())->withInput();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reschedule failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to reschedule appointment: ' . $e->getMessage());
        }
    }

public function cancel($id)
{
    try {
        DB::beginTransaction();

        $appointment = Appointment::with('schedule')->findOrFail($id);

        if ($appointment->status === 'cancelled') {
            return redirect()->back()->with('error', 'Appointment is already cancelled.');
        }

        // Update appointment status to cancelled
        $appointment->update([
            'status' => 'cancelled',
        ]);

        // Release the schedule slot
        if ($appointment->schedule) {
            $appointment->schedule->update(['is_available' => true]);
        }

        DB::commit();

        return redirect()
            ->route('admin.appointments.index')
            ->with('success', 'Appointment cancelled successfully.');

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Cancel failed: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to cancel appointment.');
    }
}

    public function getBookedSlots(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date'
        ]);

        $bookedSlots = Appointment::where('appointment_date', $validated['date'])
            ->whereNotIn('status', ['cancelled'])
            ->pluck('schedule_id')
            ->toArray();

        return response()->json([
            'booked_slots' => $bookedSlots
        ]);
    }
}