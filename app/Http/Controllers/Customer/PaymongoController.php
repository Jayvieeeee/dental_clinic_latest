<?php 

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Arr;

class PaymongoController extends Controller
{
    /**
     * All Available Payments
     */
    public function getAvailablePaymentMethods()
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            $response = Http::withBasicAuth($paymongo_SecretKey, '')->get('https://api.paymongo.com/v1/merchants/capabilities/payment_methods');
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function createPayment(Request $request)
    {
        Log::info('Payment creation started', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        $amount = 30000;
        $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');

        try {
            $validated = $request->validate([
                'service_id' => 'required|exists:services,service_id',
                'appointment_date' => 'required|date', 
                'service_name' => 'required|string',
                'schedule_id' => 'required|exists:schedules,schedule_id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Invalid request data'], 422);
        }

        DB::beginTransaction();

        try {
            // Check if user has existing appointment
            $existingAppointment = Appointment::where('patient_id', Auth::id())
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existingAppointment) {
                DB::rollBack();
                return response()->json(['error' => 'You already have a pending or confirmed appointment.'], 422);
            }

            // Check if the slot is already booked
            $isBooked = Appointment::where('schedule_id', $validated['schedule_id'])
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->whereIn('status', ['confirmed'])
                ->exists();

            if ($isBooked) {
                DB::rollBack();
                return response()->json(['error' => 'This time slot was just booked by another user. Please choose another time.'], 422);
            }

            // Clean up previous unfinished appointments
            Appointment::where('patient_id', Auth::id())
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->delete();

            // Create appointment
            $appointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $validated['service_id'],
                'schedule_id' => $validated['schedule_id'],
                'appointment_date' => $validated['appointment_date'],
                'status' => 'pending',
            ]);

            if (!$appointment->appointment_id) {
                throw new \Exception('Failed to create appointment');
            }

            Log::info('Appointment created', ['appointment_id' => $appointment->appointment_id]);

            // Create PayMongo checkout session with YOUR DOMAIN
            $paymentMethods = ['gcash', 'grab_pay', 'paymaya', 'card'];
            
            $successUrl = 'https://districtsmiles.online/payment/success?appointment_id=' . $appointment->appointment_id . '&checkout_session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = 'https://districtsmiles.online/payment/cancelled?appointment_id=' . $appointment->appointment_id;

            $payload = [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'cancel_url' => $cancelUrl,
                        'success_url' => $successUrl,
                        'payment_method_types' => $paymentMethods,
                        'line_items' => [
                            [
                                'amount' => $amount,
                                'currency' => 'PHP',
                                'name' => $validated['service_name'] . ' - District Smile Dental Clinic',
                                'quantity' => 1,
                            ]
                        ],
                        'description' => 'Service Fee of ₱300 for ' . $validated['service_name'],
                        'metadata' => [
                            'user_id' => Auth::id(),
                            'service_id' => $validated['service_id'],
                            'schedule_id' => $validated['schedule_id'],
                            'appointment_date' => $validated['appointment_date'],
                            'service_name' => $validated['service_name'],
                            'appointment_id' => $appointment->appointment_id,
                        ]
                    ]
                ]
            ];

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', $payload);

            Log::info('PayMongo API response', ['status' => $response->status()]);

            if (!$response->successful()) {
                $errorData = $response->json();
                throw new \Exception('PayMongo API failed: ' . ($errorData['errors'][0]['detail'] ?? 'Unknown error'));
            }

            $checkoutData = $response->json();

            if (!isset($checkoutData['data']['attributes']['checkout_url'])) {
                throw new \Exception('PayMongo response missing checkout_url');
            }

            // Save PayMongo session ID to appointment
            $paymongoSessionId = $checkoutData['data']['id'];
            $appointment->paymongo_session_id = $paymongoSessionId;
            $appointment->save();

            Log::info('✅ Payment session created successfully', [
                'appointment_id' => $appointment->appointment_id,
                'paymongo_session_id' => $paymongoSessionId,
                'checkout_url' => $checkoutData['data']['attributes']['checkout_url']
            ]);

            DB::commit();

            return response()->json([
                'checkout_url' => $checkoutData['data']['attributes']['checkout_url'],
                'status' => 'created',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Success Payment - IMMEDIATE CONFIRMATION
     */
    public function success(Request $request)
    {
        $appointmentId = $request->query('appointment_id');
        $checkoutSessionId = $request->query('checkout_session_id');
        
        Log::info('Payment success callback', [
            'appointment_id' => $appointmentId,
            'checkout_session_id' => $checkoutSessionId,
            'user_id' => Auth::id()
        ]);

        if (!$appointmentId) {
            return redirect()->route('customer.appointments') // Changed to appointments page
                ->with('error', 'Invalid payment session.');
        }

        // Get appointment
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return redirect()->route('customer.appointments') // Changed to appointments page
                ->with('error', 'Appointment not found.');
        }

        DB::beginTransaction();
        try {
            // Check if already confirmed
            if ($appointment->status === 'pending') {
                // ✅ IMMEDIATELY CONFIRM APPOINTMENT
                $appointment->update([
                    'status' => 'confirmed',
                ]);

                // ✅ CREATE PAYMENT RECORD
                $paymentMethod = $this->detectPaymentMethod($checkoutSessionId);
                
                $payment = Payment::create([
                    'appointment_id' => $appointment->appointment_id,
                    'amount' => 300.00,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'completed',
                    'transaction_reference' => $checkoutSessionId,
                    'paid_at' => now(),
                ]);

                // Send receipt email
                try {
                    $user = $appointment->patient;
                    if ($user && $user->email) {
                        Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                        Log::info('✅ PAYMENT RECEIPT EMAIL SENT');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send email', ['error' => $e->getMessage()]);
                }

                Log::info('✅ APPOINTMENT CONFIRMED IMMEDIATELY', [
                    'appointment_id' => $appointmentId,
                    'payment_id' => $payment->payment_id
                ]);
            }

            DB::commit();

            // REDIRECT TO APPOINTMENTS PAGE INSTEAD OF LOGIN
            return redirect()->route('customer.appointments')
                ->with('success', 'Payment completed successfully! Your appointment is confirmed.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment confirmation failed', [
                'error' => $e->getMessage(),
                'appointment_id' => $appointmentId
            ]);

            return redirect()->route('customer.appointments')
                ->with('error', 'Payment confirmation failed. Please contact support.');
        }
    }

    /**
     * Payment cancelled - REDIRECT TO APPOINTMENTS PAGE
     */
    public function cancelled(Request $request)
    {
        Log::info('Payment cancelled', ['user_id' => Auth::id()]);

        $appointmentId = $request->query('appointment_id');
        
        if ($appointmentId) {
            $this->cleanupFailedPayment($appointmentId);
        }

        session()->forget(['pending_payment', 'pending_appointment']);

        // REDIRECT TO APPOINTMENTS PAGE INSTEAD OF LOGIN
        return redirect()->route('customer.appointments')
            ->with('error', 'Payment was cancelled. Please try again to book your appointment.');
    }

    /**
     * Detect payment method from session
     */
    private function detectPaymentMethod($sessionId)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            
            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");
            
            $sessionData = $response->json();

            // Check for payment method
            $paymentMethod = null;
            if (isset($sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'])) {
                $paymentMethod = $sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'];
            } elseif (isset($sessionData['data']['attributes']['payment_method_types'][0])) {
                $paymentMethod = $sessionData['data']['attributes']['payment_method_types'][0];
            }

            $methodMap = [
                'gcash' => 'GCash',
                'grab_pay' => 'GrabPay', 
                'paymaya' => 'Maya',
                'card' => 'Credit/Debit Card',
            ];

            return $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Online Payment';

        } catch (\Exception $e) {
            Log::warning('Could not detect payment method', ['session_id' => $sessionId]);
            return 'Online Payment';
        }
    }

    /**
     * Helper method to cleanup failed payment
     */
    private function cleanupFailedPayment($appointmentId)
    {
        try {
            $appointment = Appointment::find($appointmentId);

            if ($appointment && in_array($appointment->status, ['pending'])) {
                // Mark as cancelled instead of deleting
                $appointment->status = 'cancelled';
                $appointment->save();

                Log::info('Appointment marked as cancelled after failed payment', [
                    'appointment_id' => $appointmentId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to mark appointment as cancelled', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Manual verification - FOR DEVELOPMENT TESTING
     */
    public function manualVerify(Request $request)
    {
        // Only allow in local development
        if (!app()->environment('local')) {
            abort(404);
        }

        $appointmentId = $request->input('appointment_id');
        
        if (!$appointmentId) {
            return redirect()->back()->with('error', 'Appointment ID required');
        }

        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            return redirect()->back()->with('error', 'Appointment not found');
        }

        // Manually confirm for testing
        if ($appointment->status === 'pending') {
            $appointment->update(['status' => 'confirmed']);
            
            Payment::create([
                'appointment_id' => $appointment->appointment_id,
                'amount' => 300.00,
                'payment_method' => 'Manual_Test',
                'payment_status' => 'completed',
                'transaction_reference' => 'manual_test_' . time(),
                'paid_at' => now(),
            ]);

            return redirect()->route('customer.appointments')->with('success', 'Appointment manually confirmed for testing.');
        }

        return redirect()->route('customer.appointments')->with('info', 'Appointment already confirmed.');
    }

    /**
     * Remove webhook method since we're not using it anymore
     */
    // public function webhook(Request $request) 
    // {
    //     // Remove webhook functionality
    //     return response()->json(['status' => 'webhook_disabled']);
    // }
}