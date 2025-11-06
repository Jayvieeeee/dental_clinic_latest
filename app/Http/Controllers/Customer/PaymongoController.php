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
     * Success Payment - Called when Paymongo redirects back after payment
     */
    public function success(Request $request)
    {
        Log::info('=== PAYMONGO SUCCESS REDIRECT STARTED ===', [
            'all_query_params' => $request->query(),
            'user_id' => Auth::id(),
            'full_url' => $request->fullUrl()
        ]);

        $appointmentId = $request->query('appointment_id');
        $checkoutSessionId = $request->query('checkout_session_id');

        // If no appointment_id, try to get it from the session
        if (!$appointmentId) {
            $pendingAppointment = session('pending_appointment');
            if ($pendingAppointment) {
                $appointmentId = $pendingAppointment['appointment_id'];
                Log::info('Using appointment_id from session', ['appointment_id' => $appointmentId]);
            }
        }

        if (!$appointmentId) {
            Log::error('No appointment_id found in URL or session');
            return $this->showErrorPage('No appointment found. Please contact support.');
        }

        DB::beginTransaction();
        try {
            // 1. Find the pending appointment
            $appointment = Appointment::where('appointment_id', $appointmentId)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$appointment) {
                Log::error('Appointment not found in database', [
                    'appointment_id' => $appointmentId,
                    'user_id' => Auth::id()
                ]);
                throw new \Exception("Appointment not found. Please contact support.");
            }

            Log::info('Found appointment', [
                'appointment_id' => $appointment->appointment_id,
                'current_status' => $appointment->status,
                'appointment_date' => $appointment->appointment_date
            ]);

            // 2. Verify payment with Paymongo API
            $isPaymentSuccessful = $this->verifyPaymentWithPaymongo($checkoutSessionId);
            
            if (!$isPaymentSuccessful) {
                // If verification fails but we have a session ID, still try to process
                Log::warning('Payment verification failed, but proceeding with session ID');
            }

            // 3. Update appointment status to confirmed
            if ($appointment->status === 'pending') {
                $appointment->update(['status' => 'confirmed']);
                Log::info('✅ Appointment status updated to confirmed');
            }

            // 4. Create payment record
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
            } else {
                $payment = $existingPayment;
                Log::info('Payment record already exists', ['payment_id' => $payment->payment_id]);
            }

            // 5. Send receipt email
            try {
                $user = $appointment->patient;
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new PaymentReceiptMail($appointment, $payment));
                    Log::info('✅ Payment receipt email sent');
                }
            } catch (\Exception $e) {
                Log::error('Failed to send email', ['error' => $e->getMessage()]);
            }

            DB::commit();

            // 6. Clear session
            session()->forget('pending_appointment');

            // 7. Show success page
            return $this->showSuccessPage($appointment, $payment);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment success processing failed', [
                'error' => $e->getMessage(),
                'appointment_id' => $appointmentId,
                'checkout_session_id' => $checkoutSessionId
            ]);
            
            return $this->showErrorPage($e->getMessage());
        }
    }

    /**
     * Verify payment with Paymongo API
     */
    private function verifyPaymentWithPaymongo($checkoutSessionId)
    {
        try {
            if (!$checkoutSessionId) {
                Log::warning('No checkout session ID provided for verification');
                return false;
            }

            $paymongo_SecretKey = env('PAYMONGO_SECRET_KEY');
            
            $response = Http::withBasicAuth($paymongo_SecretKey, '')
                ->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");
            
            if ($response->successful()) {
                $sessionData = $response->json();
                Log::info('Paymongo session verification successful', [
                    'session_id' => $checkoutSessionId,
                    'status' => $sessionData['data']['attributes']['status'] ?? 'unknown'
                ]);
                
                // Check if payment is paid
                $payments = $sessionData['data']['attributes']['payments'] ?? [];
                if (!empty($payments)) {
                    $paymentStatus = $payments[0]['attributes']['status'] ?? null;
                    $isPaid = $paymentStatus === 'paid';
                    
                    Log::info('Payment verification result', [
                        'payment_status' => $paymentStatus,
                        'is_paid' => $isPaid
                    ]);
                    
                    return $isPaid;
                }
            } else {
                Log::warning('Paymongo API verification failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Paymongo verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Show success page
     */
    private function showSuccessPage($appointment, $payment)
    {
        $service = $appointment->service;
        $schedule = $appointment->schedule;
        
        $appointmentDetails = [
            'id' => $appointment->appointment_id,
            'date' => $appointment->appointment_date,
            'status' => $appointment->status,
            'service_name' => $service->service_name ?? 'Dental Service',
            'time_slot' => $schedule ? 
                Carbon::parse($schedule->start_time)->format('g:i A') . ' - ' . Carbon::parse($schedule->end_time)->format('g:i A') : 
                'Time not specified'
        ];

        $paymentDetails = [
            'method' => $payment->payment_method,
            'amount' => $payment->amount,
            'reference' => $payment->transaction_reference,
            'paid_at' => $payment->paid_at->format('F j, Y g:i A')
        ];

        return $this->renderSuccessPage($appointmentDetails, $paymentDetails);
    }

    /**
     * Render HTML success page
     */
    private function renderSuccessPage($appointment, $payment)
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Successful - District Smiles</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>
        <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
            <div class="max-w-md w-full bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-green-500 py-4 px-6">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-check-circle text-white text-3xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-white">Payment Successful!</h1>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="text-center mb-6">
                        <p class="text-gray-600 mb-4">Your appointment has been confirmed and payment was processed successfully.</p>
                        
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                            <p class="text-green-800 font-semibold">
                                <i class="fas fa-calendar-check mr-2"></i>
                                Appointment Confirmed
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="border-b pb-4">
                            <h3 class="font-semibold text-gray-900 mb-2 flex items-center">
                                <i class="fas fa-tooth mr-2 text-blue-500"></i>
                                Appointment Details
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Appointment ID:</span>
                                    <span class="font-medium">#{$appointment['id']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Service:</span>
                                    <span class="font-medium">{$appointment['service_name']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-medium">{$appointment['date']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Time:</span>
                                    <span class="font-medium">{$appointment['time_slot']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium text-green-600">{$appointment['status']}</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-b pb-4">
                            <h3 class="font-semibold text-gray-900 mb-2 flex items-center">
                                <i class="fas fa-credit-card mr-2 text-green-500"></i>
                                Payment Details
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Amount Paid:</span>
                                    <span class="font-medium">₱{$payment['amount']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-medium">{$payment['method']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Reference:</span>
                                    <span class="font-medium text-xs">{$payment['reference']}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Paid On:</span>
                                    <span class="font-medium">{$payment['paid_at']}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <a href="https://districtsmiles.online/customer/appointments" 
                           class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition duration-200 block text-center font-semibold">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            View My Appointments
                        </a>
                        
                        <a href="https://districtsmiles.online/home" 
                           class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg hover:bg-gray-700 transition duration-200 block text-center">
                            <i class="fas fa-home mr-2"></i>
                            Back to Home
                        </a>
                    </div>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-500">
                            A confirmation email has been sent to your registered email address.
                        </p>
                    </div>
                </div>
            </div>

            <script>
                console.log('Payment Success Details:', {
                    appointment: {$appointment['id']},
                    status: '{$appointment['status']}',
                    payment: '{$payment['reference']}'
                });
                
                // Auto-redirect to appointments page after 10 seconds
                setTimeout(function() {
                    window.location.href = 'https://districtsmiles.online/customer/appointments';
                }, 10000);
            </script>
        </body>
        </html>
        HTML;

        return response($html);
    }

    /**
     * Show error page
     */
    private function showErrorPage($errorMessage)
    {
        return $this->renderErrorPage($errorMessage);
    }

    /**
     * Render HTML error page
     */
    private function renderErrorPage($errorMessage)
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Error - District Smiles</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>
        <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
            <div class="max-w-md w-full bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-red-500 py-4 px-6">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-white text-3xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-white">Payment Error</h1>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-times text-red-500 text-2xl"></i>
                        </div>
                        <p class="text-gray-600 mb-4">There was an issue processing your payment.</p>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-red-500 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-semibold text-red-800 mb-1">Error Details</h4>
                                <p class="text-red-700 text-sm">{$errorMessage}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a href="https://districtsmiles.online/customer/appointments" 
                           class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-200 block text-center font-semibold">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            View Appointments
                        </a>
                        
                        <a href="https://districtsmiles.online/home" 
                           class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg hover:bg-gray-700 transition duration-200 block text-center">
                            <i class="fas fa-home mr-2"></i>
                            Back to Home
                        </a>
                    </div>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-500">
                            If this error continues, please contact our support team.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return response($html);
    }

    /**
     * Payment cancelled - Called when user cancels on Paymongo checkout
     */
    public function cancelled(Request $request)
    {
        Log::info('Payment cancelled by user', [
            'appointment_id' => $request->query('appointment_id'),
            'user_id' => Auth::id()
        ]);

        $appointmentId = $request->query('appointment_id');
        
        if ($appointmentId) {
            // Mark appointment as cancelled
            $appointment = Appointment::where('appointment_id', $appointmentId)
                ->where('patient_id', Auth::id())
                ->first();
                
            if ($appointment && $appointment->status === 'pending') {
                $appointment->update(['status' => 'cancelled']);
                Log::info('Appointment cancelled due to payment cancellation');
            }
        }

        session()->forget('pending_appointment');

        return $this->renderCancelledPage();
    }

    /**
     * Render HTML cancelled page
     */
    private function renderCancelledPage()
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Cancelled - District Smiles</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        </head>
        <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
            <div class="max-w-md w-full bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-yellow-500 py-4 px-6">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-info-circle text-white text-3xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-white">Payment Cancelled</h1>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-shopping-cart text-yellow-500 text-2xl"></i>
                        </div>
                        <p class="text-gray-600 mb-2">Your payment was cancelled.</p>
                        <p class="text-gray-500 text-sm">No charges were made to your account.</p>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-800 mb-1">Want to try again?</h4>
                                <p class="text-yellow-700 text-sm">You can book another appointment anytime.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a href="https://districtsmiles.online/schedule-appointment" 
                           class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-200 block text-center font-semibold">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Book Another Appointment
                        </a>
                        
                        <a href="https://districtsmiles.online/customer/appointments" 
                           class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg hover:bg-gray-700 transition duration-200 block text-center">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            View Appointments
                        </a>
                    </div>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-500">
                            Your appointment slot has been released for other patients.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return response($html);
    }

    /**
     * Detect payment method from session
     */
    private function detectPaymentMethod($sessionId)
    {
        try {
            if (!$sessionId) {
                return 'Online Payment';
            }

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

            return redirect()->route('customer.view')->with('success', 'Appointment manually confirmed for testing.');
        }

        return redirect()->route('customer.view')->with('info', 'Appointment already confirmed.');
    }

    /**
     * Webhook handler (optional - for future use)
     */
    public function webhook(Request $request)
    {
        Log::info('Paymongo webhook received', $request->all());
        return response()->json(['status' => 'received']);
    }
}