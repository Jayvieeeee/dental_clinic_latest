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

        // Create appointment - ONLY with the fields that exist in your table
        $appointment = Appointment::create([
            'patient_id' => Auth::id(),
            'service_id' => $validated['service_id'],
            'schedule_id' => $validated['schedule_id'],
            'appointment_date' => $validated['appointment_date'], // This is a DATE field
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        if (!$appointment->appointment_id) {
            throw new \Exception('Failed to create appointment');
        }

        Log::info('Appointment created', ['appointment_id' => $appointment->appointment_id]);

        // ✅ REMOVED: The schedule_datetime code since the column doesn't exist
        // Your appointment_date is just a date, and schedule time is stored separately in schedules table

        // Create PayMongo checkout session
        $paymentMethods = ['gcash', 'grab_pay', 'paymaya', 'card'];
        
        $successUrl = route('payment.success') . '?appointment_id=' . $appointment->appointment_id . '&checkout_session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('payment.cancelled') . '?appointment_id=' . $appointment->appointment_id;

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
 * Success Payment - FIXED: Ensure payment is saved to database
 */
/**
 * Success Payment - FIXED: Show success page instead of redirecting to login
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
        return redirect()->route('customer.appointment')
            ->with('error', 'Invalid payment session.');
    }

    // Check if appointment exists and belongs to user
    $appointment = Appointment::where('appointment_id', $appointmentId)
        ->where('patient_id', Auth::id())
        ->first();

    if (!$appointment) {
        return redirect()->route('customer.appointment')
            ->with('error', 'Appointment not found.');
    }

    // Show success page with appointment details
    return view('payment.success', [
        'appointment' => $appointment,
        'checkoutSessionId' => $checkoutSessionId
    ]);
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
     * Detect payment method
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

            return $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Unknown';

        } catch (\Exception $e) {
            Log::warning('Could not detect payment method', ['session_id' => $sessionId]);
            return 'Online Payment';
        }
    }

    /**
     * Payment cancelled 
     */
    public function cancelled(Request $request)
    {
        Log::info('Payment cancelled', ['user_id' => Auth::id()]);

        $pendingPayment = session('pending_payment');
        if ($pendingPayment && isset($pendingPayment['temp_appointment_id'])) {
            $this->cleanupFailedPayment($pendingPayment['temp_appointment_id']);
        }

        // GET REFERRER URL
        $redirectUrl = $request->header('referer') ?: route('customer.appointment');
        
        // Avoid redirecting to payment URLs
        if (str_contains($redirectUrl, 'payment.success') || str_contains($redirectUrl, 'payment.cancelled')) {
            $redirectUrl = route('customer.appointment');
        }

        session()->forget(['pending_payment', 'pending_appointment']);

        return redirect($redirectUrl)
            ->with('error', 'Payment was cancelled. No appointment was created.');
    }

/**
 * Webhook handler - FIXED AND WORKING
 */
public function webhook(Request $request)
{
    Log::info('=== PAYMONGO WEBHOOK RECEIVED ===', ['data' => $request->all()]);

    try {
        $eventType = $request->input('data.attributes.type');

        Log::info('Webhook event type:', ['type' => $eventType]);

        // Only process payment.paid events
        if ($eventType !== 'payment.paid') {
            Log::info('Webhook ignored - not payment.paid event');
            return response()->json(['ignored' => true]);
        }

        $paymentData = $request->input('data.attributes.data.attributes');
        $metadata = $paymentData['metadata'] ?? [];
        
        // CORRECT METADATA KEY - appointment_id
        $appointmentId = $metadata['appointment_id'] ?? null;

        Log::info('Webhook processing payment:', [
            'appointment_id' => $appointmentId,
            'metadata' => $metadata,
            'payment_id' => $request->input('data.id'),
            'amount' => $paymentData['amount'],
            'status' => $paymentData['status']
        ]);

        if (!$appointmentId) {
            Log::error('Webhook missing appointment_id', ['metadata' => $metadata]);
            return response()->json(['error' => 'Missing appointment_id'], 400);
        }

        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            Log::error('Appointment not found in webhook', ['appointment_id' => $appointmentId]);
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        Log::info('Found appointment for webhook processing:', [
            'appointment_id' => $appointment->appointment_id,
            'current_status' => $appointment->status
        ]);

        // PROCESS THE PAYMENT - Payment is already verified as paid by webhook
        DB::beginTransaction();

        try {
            // Update appointment if still pending
            $appointmentUpdated = false;
            if ($appointment->status === 'pending') {
                $appointment->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);
                $appointmentUpdated = true;
                Log::info('✅ APPOINTMENT UPDATED TO CONFIRMED via webhook', [
                    'appointment_id' => $appointment->appointment_id
                ]);
            }

            // Check if payment record already exists
            $existingPayment = Payment::where('appointment_id', $appointment->appointment_id)->first();
            
            $paymentCreated = false;
            if (!$existingPayment) {
                // Create payment record
                $paymentMethod = $this->detectPaymentMethodFromWebhook($paymentData);
                $payment = Payment::create([
                    'appointment_id' => $appointment->appointment_id,
                    'amount' => $paymentData['amount'] / 100, // Convert centavos to pesos
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'completed',
                    'transaction_reference' => $request->input('data.id'),
                    'paid_at' => now(),
                ]);

                $paymentCreated = true;
                Log::info('✅ PAYMENT RECORD CREATED via webhook', [
                    'appointment_id' => $appointment->appointment_id,
                    'payment_id' => $payment->payment_id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method
                ]);

                // Send receipt email
                try {
                    $user = $appointment->patient;
                    if ($user && $user->email) {
                        Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                        Log::info('✅ PAYMENT RECEIPT EMAIL SENT via webhook');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send email via webhook', ['error' => $e->getMessage()]);
                }
            } else {
                Log::info('Payment record already exists via webhook', [
                    'payment_id' => $existingPayment->payment_id
                ]);
            }

            DB::commit();

            Log::info('=== WEBHOOK PROCESSING COMPLETED SUCCESSFULLY ===', [
                'appointment_id' => $appointmentId,
                'appointment_updated' => $appointmentUpdated,
                'payment_created' => $paymentCreated
            ]);

            return response()->json([
                'status' => 'success',
                'appointment_id' => $appointmentId,
                'appointment_updated' => $appointmentUpdated,
                'payment_created' => $paymentCreated
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Webhook transaction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Webhook processing failed'], 500);
    }
}

/**
 * Detect payment method from webhook data
 */
private function detectPaymentMethodFromWebhook($paymentData)
{
    // Get payment method from source type
    $method = $paymentData['source']['type'] ?? 'Online';
    
    $methodMap = [
        'gcash' => 'GCash',
        'grab_pay' => 'GrabPay', 
        'paymaya' => 'Maya',
        'card' => 'Credit/Debit Card',
    ];

    return $methodMap[$method] ?? ucfirst($method) ?? 'Online Payment';
}

    /**
     * Helper: Format time for display
     */
    private function formatTimeForDisplay($startTime, $endTime): string
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        
        return $start->format('g:i A') . ' - ' . $end->format('g:i A');
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

            return redirect()->route('customer.appointment')->with('success', 'Appointment manually confirmed for testing.');
        }

        return redirect()->route('customer.appointment')->with('info', 'Appointment already confirmed.');
    }

    
}