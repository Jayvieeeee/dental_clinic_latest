<?php 

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymongoController extends Controller
{
    /**
     * All Available Payments
     */
    public function getAvailablePaymentMethods()
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            //url payment methods
            $response = Http::withBasicAuth($paymongo_SecretKey, '')->get('https://api.paymongo.com/v1/merchants/capabilities/payment_methods');
            
            $data = $response->json();
            
            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create Payment
     */
    public function createPayment(Request $request)
    {
        Log::info('Payment creation started', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        $amount = 30000; // in centavos
        $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');

        // validate the request
        $validated = $request->validate([
            'service_id' => 'required|exists:services,service_id',
            'schedule_datetime' => 'required|date',
            'service_name' => 'required|string',
            'schedule_id' => 'required|exists:schedules,schedule_id',
        ]);

        Log::info('Validation passed', ['validated_data' => $validated]);

        // check if time slot is available
        $conflictingAppointment = Appointment::where('schedule_datetime', $validated['schedule_datetime'])->whereIn('status', ['pending', 'ongoing', 'confirmed'])->first();

        if ($conflictingAppointment) {
            Log::error('Time slot already booked before payment creation', [
                'requested_datetime' => $validated['schedule_datetime'],
                'existing_appointment_id' => $conflictingAppointment->appointment_id,
                'existing_user_id' => $conflictingAppointment->patient_id,
                'current_user_id' => Auth::id(),
                'existing_status' => $conflictingAppointment->status
            ]);
            
            return response()->json([
                'error' => 'This time slot was just booked by another user. Please choose a different time.'], 422);
        }

        // check if user has ongoing appointment
        $existingAppointment = Appointment::where('patient_id', Auth::id())->whereIn('status', ['pending', 'ongoing', 'confirmed'])->first();

        if ($existingAppointment) {
            Log::warning('User has existing appointment', [
                'user_id' => Auth::id(),
                'existing_appointment_id' => $existingAppointment->appointment_id,
                'existing_status' => $existingAppointment->status
            ]);
            return response()->json([
                'error' => 'You already have a pending or ongoing appointment.'], 422);
        }

        try {
            // store session data
            $checkoutSessionId = 'cs_' . uniqid();
            session([
                'pending_payment' => [
                    'service_id' => $validated['service_id'],
                    'schedule_datetime' => $validated['schedule_datetime'],
                    'schedule_id' => $validated['schedule_id'],
                    'service_name' => $validated['service_name'],
                    'checkout_session_id' => $checkoutSessionId,
                    'amount' => $amount,
                    'user_id' => Auth::id(),
                ]
            ]);

            // all available payment methods
            $paymentMethods = [
                'gcash',
                'grab_pay',
                'paymaya',
                'card',
            ];

            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => [
                                [
                                    'name' => $validated['service_name'] . ' - District Smile Dental Clinic',
                                    'amount' => $amount,
                                    'currency' => 'PHP',
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => $paymentMethods,
                            'success_url' => route('payment.success') . '?session_id=' . $checkoutSessionId,
                            'cancel_url' => route('payment.cancelled'),
                            'description' => 'Service Fee of ₱300 for ' . $validated['service_name'],
                            'send_email_receipt' => false,
                            'show_description' => true,
                            'show_line_items' => true,
                            'metadata' => [
                                'user_id' => Auth::id(),
                                'service_id' => $validated['service_id'],
                                'schedule_datetime' => $validated['schedule_datetime'],
                                'service_name' => $validated['service_name'],
                                'checkout_session_id' => $checkoutSessionId,
                            ],
                        ],
                    ],
                ]);

            Log::info('PayMongo API response status', ['status' => $response->status()]);
            Log::info('PayMongo API response body', ['body' => $response->body()]);

            $checkoutData = $response->json();

            if (!$response->successful()) {
                Log::error('PayMongo API Error Response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'errors' => $checkoutData['errors'] ?? null
                ]);
                
                $errorMessage = 'Failed to create payment session. ';
                if (isset($checkoutData['errors'][0]['detail'])) {
                    $errorMessage .= $checkoutData['errors'][0]['detail'];
                }
                
                return response()->json(['error' => $errorMessage], 500);
            }

            if (!isset($checkoutData['data']['attributes']['checkout_url'])) {
                Log::error('PayMongo API missing checkout URL', ['response' => $checkoutData]);
                return response()->json([
                    'error' => 'Payment gateway returned invalid response.'
                ], 500);
            }

            // update session with actual PayMongo session id
            session([
                'pending_payment.checkout_session_id' => $checkoutData['data']['id'],
                'pending_payment.paymongo_session_id' => $checkoutData['data']['id']
            ]);

            Log::info('Payment session created successfully', [
                'user_id' => Auth::id(),
                'checkout_session_id' => $checkoutData['data']['id'],
                'service_id' => $validated['service_id'],
                'available_payment_methods' => $paymentMethods
            ]);

            return response()->json([
                'checkout_url' => $checkoutData['data']['attributes']['checkout_url'],
                'status' => 'created',
            ]);

        } catch (\Exception $e) {
            Log::error('PayMongo Payment Creation Exception', [
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Success Payment
     */
    public function success(Request $request)
    {
        try {
            $sessionId = $request->query('session_id');
            
            Log::info('Payment success callback received', [
                'session_id' => $sessionId,
                'all_query_params' => $request->query(),
                'user_id' => Auth::id()
            ]);

            if (!$sessionId) {
                Log::error('Missing session ID in success callback');
                return redirect()->route('customer.appointment')->with('error', 'Invalid payment session.');
            }

            // Get the pending payment data from session
            $pendingPayment = session('pending_payment');
            
            Log::info('Pending payment session data', [
                'pending_payment' => $pendingPayment,
                'user_id' => Auth::id()
            ]);

            if (!$pendingPayment) {
                Log::error('No pending payment found in session');
                return redirect()->route('customer.appointment')->with('error', 'Payment session expired. Please try again.');
            }

            if ($pendingPayment['user_id'] != Auth::id()) {
                Log::error('User ID mismatch in payment session', [
                    'session_user_id' => $pendingPayment['user_id'],
                    'current_user_id' => Auth::id()
                ]);
                return redirect()->route('customer.appointment')->with('error', 'Payment session user mismatch.');
            }

            // ensure time slot is still available
            $conflictingAppointment = Appointment::where('schedule_datetime', $pendingPayment['schedule_datetime'])
                ->whereIn('status', ['pending', 'ongoing', 'confirmed'])
                ->first();

            if ($conflictingAppointment) {
                Log::error('Time slot booked by another user during payment processing', [
                    'schedule_datetime' => $pendingPayment['schedule_datetime'],
                    'existing_appointment_id' => $conflictingAppointment->appointment_id,
                    'existing_user_id' => $conflictingAppointment->patient_id,
                    'current_user_id' => Auth::id(),
                    'existing_status' => $conflictingAppointment->status
                ]);
                
                // clear sessions if time slot not available
                session()->forget('pending_payment');
                session()->forget('pending_appointment');
                
                return redirect()->route('customer.appointment')->with('error', 
                    'Sorry, the selected time slot was booked by another user while you were processing payment. ' .
                    'Please choose a different time slot.'
                );
            }

            // create the appointment after the payment is successful
            $appointment = Appointment::create([
                'patient_id' => Auth::id(),
                'service_id' => $pendingPayment['service_id'],
                'schedule_datetime' => $pendingPayment['schedule_datetime'],
                'status' => 'confirmed', 
            ]);

            if (!$appointment->appointment_id) {
                throw new \Exception('Failed to create appointment - appointment_id is null');
            }

            Log::info('New appointment created after payment', [
                'appointment_id' => $appointment->appointment_id,
                'schedule_datetime' => $pendingPayment['schedule_datetime'],
                'user_id' => Auth::id()
            ]);

            // check the payment method used
            $paymentMethod = $this->detectPaymentMethod($pendingPayment['paymongo_session_id'] ?? $sessionId);

            // create payment record
            $payment = Payment::create([
                'appointment_id' => $appointment->appointment_id,
                'amount' => $pendingPayment['amount'] / 100,
                'payment_method' => $paymentMethod,
                'payment_status' => 'completed',
                'transaction_reference' => $pendingPayment['paymongo_session_id'] ?? $sessionId,
                'paid_at' => now(),
            ]);

            // clear all pending sessions
            session()->forget('pending_payment');
            session()->forget('pending_appointment');

            Log::info('SUCCESS: Payment completed and appointment created', [
                'appointment_id' => $appointment->appointment_id,
                'payment_id' => $payment->payment_id,
                'payment_method' => $paymentMethod,
                'appointment_status' => $appointment->status,
                'payment_status' => $payment->payment_status,
                'amount_paid' => $payment->amount,
                'transaction_reference' => $payment->transaction_reference,
                'schedule_datetime' => $appointment->schedule_datetime,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('customer.view')->with('success', 'Payment successful! Your appointment has been confirmed.');

        } catch (\Exception $e) {
            Log::error('Payment Success Handling Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // clear all sessions on error
            session()->forget('pending_payment');
            session()->forget('pending_appointment');
            
            return redirect()->route('customer.appointment')->with('error', 'Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Check Available Payments
     */
    private function detectPaymentMethod($sessionId)
    {
        try {
            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            
            Log::info('Attempting to detect payment method', ['session_id' => $sessionId]);
            
            $response = Http::withBasicAuth($paymongo_SecretKey, '')->get("https://api.paymongo.com/v1/checkout_sessions/{$sessionId}");
            
            $sessionData = $response->json();
            
            Log::info('Payment method detection response', [
                'session_id' => $sessionId,
                'response_status' => $response->status(),
                'session_data' => $sessionData
            ]);

            // Check multiple possible locations for payment method
            $paymentMethod = null;

            // Method 1: Check payments array
            if (isset($sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'])) {
                $paymentMethod = $sessionData['data']['attributes']['payments'][0]['attributes']['payment_method']['attributes']['type'];
            }
            // Method 2: Check payment_intent
            elseif (isset($sessionData['data']['attributes']['payment_intent']['attributes']['payment_method_allowed'][0])) {
                $paymentMethod = $sessionData['data']['attributes']['payment_intent']['attributes']['payment_method_allowed'][0];
            }
            // Method 3: Check payment_method_types (what was selected)
            elseif (isset($sessionData['data']['attributes']['payment_method_types'][0])) {
                $paymentMethod = $sessionData['data']['attributes']['payment_method_types'][0];
            }

            // Map PayMongo method names to your preferred names
            $methodMap = [
                'gcash' => 'GCash',
                'grab_pay' => 'GrabPay',
                'paymaya' => 'Maya',
                'card' => 'Credit/Debit Card',
            ];

            $finalMethod = $methodMap[$paymentMethod] ?? $paymentMethod ?? 'Unknown';

            Log::info('Payment method detection result', [
                'session_id' => $sessionId,
                'detected_method' => $paymentMethod,
                'final_method' => $finalMethod
            ]);

            return $finalMethod;

        } catch (\Exception $e) {
            Log::warning('Could not detect payment method, using default', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return 'Multiple Options';
        }
    }

    /**
     * Available Payments
     */
    public function cancelled()
    {
        Log::info('Payment cancelled by user', ['user_id' => Auth::id()]);
        
        // clear all sessions
        session()->forget('pending_payment');
        session()->forget('pending_appointment');
        
        return redirect()->route('customer.appointment')->with('error', 'Payment was cancelled. No appointment was created.');
    }

    /**
     * Handle payment webhooks from PayMongo 
     */
    public function webhook(Request $request)
    {
        Log::info('PayMongo webhook received', [
            'webhook_data' => $request->all()
        ]);

        // Verify webhook signature (important for security)
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature');

        // Implement webhook signature verification here
        // This ensures the webhook is actually from PayMongo

        $eventType = $request->input('data.attributes.type');
        
        switch ($eventType) {
            case 'payment.paid':
                // Handle successful payment
                $this->handlePaymentPaid($request->input('data'));
                break;
            case 'payment.failed':
                // Handle failed payment
                $this->handlePaymentFailed($request->input('data'));
                break;
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment webhook
     */
    private function handlePaymentPaid($data)
    {
        Log::info('Payment paid webhook received', ['data' => $data]);
        
        // You can implement additional logic here if needed
        // For example, send confirmation emails, update other systems, etc.
    }

    /**
     * Handle failed payment webhook
     */
    private function handlePaymentFailed($data)
    {
        Log::error('Payment failed webhook received', ['data' => $data]);
        
        // You can implement failure handling logic here
        // For example, notify admin, log failures, etc.
    }
}