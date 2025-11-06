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
use Inertia\Inertia;

class PaymongoController extends Controller
{
    public function createPayment(Request $request)
    {
        Log::info('Payment creation started', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        $amountInPesos = 300.00;
        $amount = intval(round($amountInPesos * 100));

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

            $paymentMethods = ['gcash', 'grab_pay', 'paymaya', 'card'];
            
            // FIX: Use your actual domain
            $successUrl = 'https://districtsmiles.online/payment/success?appointment_id=' . $appointment->appointment_id;
            $cancelUrl = 'https://districtsmiles.online/payment/cancelled?appointment_id=' . $appointment->appointment_id;

            $payload = [
                "data" => [
                    "attributes" => [
                        "line_items" => [[
                            "name" => $validated['service_name'] . ' - District Smile Dental Clinic',
                            "amount" => $amount,
                            "currency" => "PHP",
                            "quantity" => 1,
                            "images" => ['https://districtsmiles.online/images/logo.png']
                        ]],
                        "payment_method_types" => $paymentMethods,
                        "description" => "Service Fee of ₱300 for " . $validated['service_name'],
                        "success_url" => $successUrl,
                        "cancel_url" => $cancelUrl,
                        "metadata" => [
                            'user_id' => Auth::id(),
                            'service_id' => $validated['service_id'],
                            'schedule_id' => $validated['schedule_id'],
                            'appointment_date' => $validated['appointment_date'],
                            'service_name' => $validated['service_name'],
                            'appointment_id' => $appointment->appointment_id,
                        ],
                        "statement_descriptor" => "District Smile Dental"
                    ]
                ]
            ];

            Log::info('PayMongo API Request', ['payload' => $payload]);

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', $payload);

            Log::info('PayMongo API response', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorDetail = $errorData['errors'][0]['detail'] ?? 'Unknown API error';
                throw new \Exception('PayMongo API failed: ' . $errorDetail);
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
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Success Payment - IMPROVED WITH DIRECT PAYMENT VERIFICATION
     */
    public function success(Request $request)
    {
        Log::info('=== PAYMENT SUCCESS CALLBACK START ===');
        
        $appointmentId = $request->query('appointment_id');
        
        Log::info('Success Callback Data:', [
            'appointment_id' => $appointmentId,
            'user_authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'all_query_params' => $request->query()
        ]);

        if (!$appointmentId) {
            Log::error('No appointment ID in success callback');
            return redirect()->route('customer.appointments')
                ->with('error', 'Invalid payment session.');
        }

        // Get appointment (with or without auth)
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->with(['service', 'schedule'])
            ->first();

        if (!$appointment) {
            Log::error('Appointment not found', ['appointment_id' => $appointmentId]);
            return redirect()->route('customer.appointments')
                ->with('error', 'Appointment not found.');
        }

        // Check if already confirmed
        if ($appointment->status === 'confirmed') {
            $payment = Payment::where('appointment_id', $appointmentId)->first();
            
            Log::info('Appointment already confirmed via webhook');
            
            return Inertia::render('Payment/Success', [
                'appointment' => $appointment,
                'payment' => $payment,
                'message' => 'Payment completed successfully! Your appointment is confirmed.'
            ]);
        }

        Log::info('Appointment not yet confirmed, checking payment status...');

        // DIRECT PAYMENT VERIFICATION - Check PayMongo session status
        $isPaid = $this->verifyAndProcessPayment($appointment);
        
        if ($isPaid) {
            $payment = Payment::where('appointment_id', $appointmentId)->first();

            return Inertia::render('Payment/Success', [
                'appointment' => $appointment->fresh(['service', 'schedule']),
                'payment' => $payment,
                'message' => 'Payment completed successfully! Your appointment is confirmed.'
            ]);
        }

        // Show processing page if payment not yet confirmed
        return Inertia::render('Payment/Success', [
            'appointment' => $appointment,
            'message' => 'We are verifying your payment. This may take a few moments...'
        ]);
    }

    /**
     * DIRECT PAYMENT VERIFICATION AND PROCESSING
     */
    private function verifyAndProcessPayment($appointment)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            $sessionId = $appointment->paymongo_session_id;
            
            if (!$sessionId) {
                Log::error('No PayMongo session ID found for appointment', [
                    'appointment_id' => $appointment->appointment_id
                ]);
                return false;
            }

            Log::info('Checking PayMongo session status:', ['session_id' => $sessionId]);
            
            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");
            
            if ($response->successful()) {
                $sessionData = $response->json();
                Log::info('PayMongo session data:', $sessionData);
                
                $payments = $sessionData['data']['attributes']['payments'] ?? [];
                
                if (!empty($payments)) {
                    $paymentStatus = $payments[0]['attributes']['status'] ?? null;
                    $paymentId = $payments[0]['id'] ?? null;
                    
                    Log::info('Payment status found:', [
                        'status' => $paymentStatus,
                        'payment_id' => $paymentId
                    ]);
                    
                    if ($paymentStatus === 'paid') {
                        // PROCESS PAYMENT IMMEDIATELY
                        DB::transaction(function () use ($appointment, $sessionId, $paymentId) {
                            // Update appointment status
                            $appointment->update(['status' => 'confirmed']);
                            
                            // Create payment record
                            $paymentMethod = $this->detectPaymentMethod($sessionId);
                            
                            Payment::create([
                                'appointment_id' => $appointment->appointment_id,
                                'amount' => 300.00,
                                'payment_method' => $paymentMethod,
                                'payment_status' => 'completed',
                                'transaction_reference' => $paymentId ?: $sessionId,
                                'paid_at' => now(),
                            ]);

                            Log::info('✅ PAYMENT PROCESSED VIA SUCCESS URL', [
                                'appointment_id' => $appointment->appointment_id,
                                'payment_id' => $paymentId,
                                'session_id' => $sessionId
                            ]);

                            // Send receipt email
                            try {
                                $user = $appointment->patient;
                                if ($user && $user->email) {
                                    $payment = Payment::where('appointment_id', $appointment->appointment_id)->first();
                                    Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                                    Log::info('✅ PAYMENT RECEIPT EMAIL SENT');
                                }
                            } catch (\Exception $e) {
                                Log::error('Failed to send email', ['error' => $e->getMessage()]);
                            }
                        });
                        
                        return true;
                    }
                }
                
                // Check session status as fallback
                $sessionStatus = $sessionData['data']['attributes']['status'] ?? null;
                Log::info('Session status:', ['status' => $sessionStatus]);
                
                if ($sessionStatus === 'paid') {
                    // Process payment for paid session status
                    DB::transaction(function () use ($appointment, $sessionId) {
                        $appointment->update(['status' => 'confirmed']);
                        
                        $paymentMethod = $this->detectPaymentMethod($sessionId);
                        
                        Payment::create([
                            'appointment_id' => $appointment->appointment_id,
                            'amount' => 300.00,
                            'payment_method' => $paymentMethod,
                            'payment_status' => 'completed',
                            'transaction_reference' => $sessionId,
                            'paid_at' => now(),
                        ]);

                        Log::info('✅ PAYMENT PROCESSED VIA SESSION STATUS', [
                            'appointment_id' => $appointment->appointment_id,
                            'session_id' => $sessionId
                        ]);
                    });
                    
                    return true;
                }
            } else {
                Log::error('PayMongo API failed:', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return false;
    }

    /**
     * WEBHOOK - REAL PAYMENT CONFIRMATION (Primary)
     */
    public function webhook(Request $request)
    {
        Log::info('=== PAYMONGO WEBHOOK RECEIVED ===');
        
        // Log raw payload for debugging
        $rawPayload = $request->getContent();
        Log::info('Webhook Raw Payload:', ['payload' => $rawPayload]);
        Log::info('Webhook Headers:', $request->headers->all());

        try {
            $webhookData = json_decode($rawPayload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
            }
            
            Log::info('Webhook JSON Data:', $webhookData);

        } catch (\Exception $e) {
            Log::error('Webhook JSON parsing failed:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            Log::error('Webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $webhookData['data']['attributes']['type'] ?? null;
        
        Log::info('Webhook Event Type:', ['type' => $eventType]);

        if ($eventType === 'checkout_session.payment.paid') {
            return $this->handlePaymentPaid($webhookData);
        }

        if ($eventType === 'checkout_session.payment.failed') {
            return $this->handlePaymentFailed($webhookData);
        }

        Log::info('Webhook event ignored', ['type' => $eventType]);
        return response()->json(['status' => 'ignored']);
    }

    /**
     * Handle successful payment via webhook
     */
    private function handlePaymentPaid($webhookData)
    {
        DB::beginTransaction();

        try {
            Log::info('=== PROCESSING PAYMENT PAID WEBHOOK ===');
            
            $sessionData = $webhookData['data']['attributes']['data'];
            $checkoutSessionId = $sessionData['id'];
            $metadata = $sessionData['attributes']['metadata'] ?? [];
            
            $appointmentId = $metadata['appointment_id'] ?? null;
            $userId = $metadata['user_id'] ?? null;

            Log::info('Webhook Processing Data:', [
                'appointment_id' => $appointmentId,
                'user_id' => $userId,
                'checkout_session_id' => $checkoutSessionId,
                'metadata' => $metadata
            ]);

            if (!$appointmentId) {
                throw new \Exception('No appointment ID in webhook metadata');
            }

            // Get appointment
            $appointment = Appointment::where('appointment_id', $appointmentId)->first();
            
            if (!$appointment) {
                throw new \Exception("Appointment {$appointmentId} not found");
            }

            // Check if already processed
            if ($appointment->status === 'confirmed') {
                Log::info('Appointment already confirmed', ['appointment_id' => $appointmentId]);
                DB::commit();
                return response()->json(['status' => 'already_processed']);
            }

            // ✅ CONFIRM APPOINTMENT
            $appointment->update([
                'status' => 'confirmed',
                'paymongo_session_id' => $checkoutSessionId
            ]);

            // ✅ CREATE PAYMENT RECORD
            $paymentMethod = $this->detectPaymentMethodFromWebhook($webhookData);
            
            $payment = Payment::create([
                'appointment_id' => $appointment->appointment_id,
                'amount' => 300.00,
                'payment_method' => $paymentMethod,
                'payment_status' => 'completed',
                'transaction_reference' => $checkoutSessionId,
                'paid_at' => now(),
            ]);

            Log::info('Payment record created', [
                'payment_id' => $payment->payment_id,
                'appointment_id' => $appointment->appointment_id
            ]);

            // Send receipt email
            try {
                $user = $appointment->patient;
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                    Log::info('Payment receipt email sent via webhook');
                }
            } catch (\Exception $e) {
                Log::error('Failed to send email via webhook', ['error' => $e->getMessage()]);
            }

            DB::commit();

            Log::info('✅ PAYMENT CONFIRMED VIA WEBHOOK SUCCESSFULLY', [
                'appointment_id' => $appointmentId,
                'payment_id' => $payment->payment_id,
                'payment_method' => $paymentMethod
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ WEBHOOK PAYMENT PROCESSING FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'appointment_id' => $appointmentId ?? 'unknown'
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle failed payment via webhook
     */
    private function handlePaymentFailed($webhookData)
    {
        try {
            $sessionData = $webhookData['data']['attributes']['data'];
            $metadata = $sessionData['attributes']['metadata'] ?? [];
            $appointmentId = $metadata['appointment_id'] ?? null;

            if ($appointmentId) {
                $this->cleanupFailedPayment($appointmentId);
                Log::info('Payment failed via webhook', ['appointment_id' => $appointmentId]);
            }

            return response()->json(['status' => 'failed_handled']);

        } catch (\Exception $e) {
            Log::error('Failed to handle payment failure webhook', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to process'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(Request $request)
    {
        // For development/testing, you can temporarily disable signature verification
        if (app()->environment('local', 'testing')) {
            Log::info('Webhook signature verification skipped in local environment');
            return true;
        }

        $payload = $request->getContent();
        $signature = $request->header('paymongo-signature');
        $webhookSecret = env('PAYMONGO_WEBHOOK_SECRET');

        Log::info('Webhook Signature Verification:', [
            'has_signature' => !empty($signature),
            'has_secret' => !empty($webhookSecret),
            'payload_length' => strlen($payload)
        ]);

        if (!$signature || !$webhookSecret) {
            Log::warning('Missing webhook signature or secret');
            return false;
        }

        // Simple signature verification (Paymongo uses HMAC SHA256)
        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        $isValid = hash_equals($signature, $computedSignature);
        
        if (!$isValid) {
            Log::error('Webhook signature mismatch', [
                'computed' => $computedSignature,
                'received' => $signature
            ]);
        } else {
            Log::info('Webhook signature verified successfully');
        }

        return $isValid;
    }

    /**
     * Detect payment method from webhook data
     */
    private function detectPaymentMethodFromWebhook($webhookData)
    {
        try {
            $payments = $webhookData['data']['attributes']['data']['attributes']['payments'] ?? [];
            
            if (!empty($payments)) {
                $paymentMethod = $payments[0]['attributes']['payment_method']['attributes']['type'] ?? null;
                
                $methodMap = [
                    'gcash' => 'GCash',
                    'grab_pay' => 'GrabPay', 
                    'paymaya' => 'Maya',
                    'card' => 'Credit/Debit Card',
                ];

                return $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Online Payment';
            }
        } catch (\Exception $e) {
            Log::warning('Could not detect payment method from webhook');
        }

        return 'Online Payment';
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
     * Payment cancelled - FIXED AUTHENTICATION ISSUE
     */
    public function cancelled(Request $request)
    {
        $appointmentId = $request->query('appointment_id');
        
        Log::info('Payment cancelled', [
            'appointment_id' => $appointmentId,
            'user_authenticated' => Auth::check(),
            'user_id' => Auth::id()
        ]);

        if ($appointmentId) {
            $this->cleanupFailedPayment($appointmentId);
        }

        // Simple cancelled page - no authentication required
        return Inertia::render('Payment/Cancelled', [
            'appointmentId' => $appointmentId,
            'message' => 'Payment was cancelled. No appointment was created.'
        ]);
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
}