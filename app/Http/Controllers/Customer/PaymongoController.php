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
    
public function success(Request $request)
{
    Log::info('=== PAYMENT SUCCESS STARTED ===', [
        'all_parameters' => $request->all(),
        'query_params' => $request->query(),
        'user_id' => Auth::id()
    ]);

    $appointmentId = $request->query('appointment_id');
    $checkoutSessionId = $request->query('checkout_session_id');
    
    if (!$appointmentId) {
        Log::error('No appointment_id in success URL');
        // Let's see what we actually received
        return response()->json([
            'error' => 'No appointment ID',
            'received_parameters' => $request->all(),
            'query_parameters' => $request->query()
        ]);
    }

    try {
        DB::beginTransaction();

        // 1. Find the appointment
        $appointment = Appointment::where('appointment_id', $appointmentId)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$appointment) {
            Log::error('Appointment not found in database', [
                'appointment_id' => $appointmentId,
                'user_id' => Auth::id(),
                'all_appointments' => Appointment::where('patient_id', Auth::id())->get()->toArray()
            ]);
            throw new \Exception("Appointment {$appointmentId} not found for user " . Auth::id());
        }

        Log::info('Found appointment', [
            'appointment_id' => $appointment->appointment_id,
            'current_status' => $appointment->status,
            'appointment_date' => $appointment->appointment_date
        ]);

        // 2. Update appointment status to confirmed
        $appointment->update([
            'status' => 'confirmed'
        ]);
        Log::info('✅ Appointment status updated to confirmed');

        // 3. Create payment record
        $existingPayment = Payment::where('appointment_id', $appointmentId)->first();
        if (!$existingPayment) {
            $paymentMethod = $this->detectPaymentMethod($checkoutSessionId);
            
            $payment = Payment::create([
                'appointment_id' => $appointment->appointment_id,
                'amount' => 300.00,
                'payment_method' => $paymentMethod,
                'payment_status' => 'completed',
                'transaction_reference' => $checkoutSessionId,
                'paid_at' => now(),
            ]);
            Log::info('✅ Payment record created', [
                'payment_id' => $payment->payment_id,
                'payment_method' => $paymentMethod
            ]);
        }

        DB::commit();

        // 4. Return a SIMPLE success response first
        return $this->showSimpleSuccessPage($appointment, $payment ?? $existingPayment);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Payment success failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'appointment_id' => $appointmentId
        ]);
        
        return $this->showSimpleErrorPage($e->getMessage());
    }
}

/**
 * Show a simple success page (temporary for testing)
 */
private function showSimpleSuccessPage($appointment, $payment)
{
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Successful - District Smiles</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .success-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
            .success-icon { color: #4CAF50; font-size: 48px; margin-bottom: 20px; }
            .btn { background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='success-box'>
            <div class='success-icon'>✅</div>
            <h1>Payment Successful!</h1>
            <p>Your appointment has been confirmed.</p>
            
            <div style='text-align: left; margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px;'>
                <h3>Appointment Details:</h3>
                <p><strong>Appointment ID:</strong> {$appointment->appointment_id}</p>
                <p><strong>Date:</strong> {$appointment->appointment_date}</p>
                <p><strong>Status:</strong> {$appointment->status}</p>
                <p><strong>Payment Method:</strong> {$payment->payment_method}</p>
                <p><strong>Amount:</strong> ₱{$payment->amount}</p>
            </div>
            
            <p>You will receive a confirmation email shortly.</p>
            
            <a href='https://districtsmiles.online/customer/appointments' class='btn'>
                View My Appointments
            </a>
            
            <div style='margin-top: 20px; font-size: 12px; color: #666;'>
                <p>Debug Info: Appointment #{$appointment->appointment_id} confirmed at " . now() . "</p>
            </div>
        </div>
        
        <script>
            console.log('Payment Success Debug:');
            console.log('Appointment ID: {$appointment->appointment_id}');
            console.log('Status: {$appointment->status}');
            console.log('Payment ID: {$payment->payment_id}');
            
            // Auto-redirect after 5 seconds
            setTimeout(function() {
                window.location.href = 'https://districtsmiles.online/customer/appointments';
            }, 5000);
        </script>
    </body>
    </html>
    ";
    
    return response($html);
}

/**
 * Show a simple error page
 */
private function showSimpleErrorPage($errorMessage)
{
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Error - District Smiles</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
            .error-icon { color: #f44336; font-size: 48px; margin-bottom: 20px; }
            .btn { background: #f44336; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <div class='error-icon'>❌</div>
            <h1>Payment Processing Error</h1>
            <p>There was an issue confirming your payment.</p>
            <div style='background: #ffeaea; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <strong>Error:</strong> {$errorMessage}
            </div>
            <p>Please contact support if this continues.</p>
            
            <a href='https://districtsmiles.online/customer/appointments' class='btn'>
                Back to Appointments
            </a>
        </div>
    </body>
    </html>
    ";
    
    return response($html);
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