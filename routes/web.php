<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\AdminAppointmentController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\AdminFeedbackController;
use App\Http\Controllers\Customer\AppointmentController;
use App\Http\Controllers\Customer\ScheduleController;
use App\Http\Controllers\Customer\PaymongoController;
use App\Http\Controllers\Customer\FeedbackController;



use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing/Welcome', [

    ]);
});
    //================ Public Routes ====================================
    Route::get('/services', fn() => Inertia::render('Landing/Services'));
    Route::get('/faqs', fn() => Inertia::render('Landing/Faqs'));
    Route::get('/contactUs', fn() => Inertia::render('Landing/ContactUs'));
    Route::get('/testimonials', fn() => Inertia::render('Landing/Testimonials'));

   // Schedule Routes
    Route::prefix('schedules')->group(function () {
        Route::get('/', [ScheduleController::class, 'index']);
        Route::get('/available-dates', [ScheduleController::class, 'getAvailableDates']);
        Route::get('/date-range', [ScheduleController::class, 'getByDateRange']);
        Route::get('/today', [ScheduleController::class, 'getTodaySlots']);
        Route::get('/tomorrow', [ScheduleController::class, 'getTomorrowSlots']);
        Route::get('/date/{date}', [ScheduleController::class, 'getByDate']);
        Route::get('/{date}/available-slots', [ScheduleController::class, 'getAvailableSlots']);
        Route::get('/{scheduleId}/check-availability', [ScheduleController::class, 'checkAvailability']);
        Route::post('/bulk-check', [ScheduleController::class, 'bulkCheckAvailability']);
    });;

//=============================== Customer Routes AUTHENTICATED =====================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/home', fn() => Inertia::render('Customer/Home'))->name('customer.home');

    //Feedback Route
    Route::get('/feedback', fn() => Inertia::render('Customer/Feedback'))->name('customer.feedback');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');

//=================================== Appointment Routes ============================================================
    Route::get('/customer/appointments', [AppointmentController::class, 'index'])->name('customer.view');
    Route::get('/schedule-appointment', [AppointmentController::class, 'create'])->name('customer.appointment');
    Route::post('/schedule-appointment', [AppointmentController::class, 'store'])->name('customer.appointment.store');
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->name('customer.appointment.cancel');
    Route::get('/available-slots', [AppointmentController::class, 'getAvailableSlots'])->name('customer.available-slots');
    Route::get('/appointment/check-availability', [AppointmentController::class, 'checkAvailability'])->name('appointment.check-availability');
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->name('customer.appointment.reschedule');
    Route::get('/appointment/payment', [AppointmentController::class, 'showPaymentPage'])->name('customer.payment.view');
    Route::get('/appointment/payment/success', [AppointmentController::class, 'paymentSuccessHandler'])->name('customer.payment.success');

//=========================================== Payment Routes ===================================================
    Route::post('/customer/payment/create', [PaymongoController::class, 'createPayment'])->name('payment.create');
    Route::get('/payment/success', [PaymongoController::class, 'success'])->name('payment.success');
    Route::get('/payment/cancelled', [PaymongoController::class, 'cancelled'])->name('payment.cancelled');
    Route::post('/payment/webhook', [PaymongoController::class, 'webhook'])->name('payment.webhook');


});
 //======================================= Profile Routes =========================================================
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    
//============================================ Admin Routes =====================================================
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/patients', [PatientController::class, 'index'])->name('patients');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');  

    //FEEDBACK
    Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/feedback/{id}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
    Route::get('/feedback-stats', [AdminFeedbackController::class, 'getStats'])->name('feedback.stats');
    Route::get('/feedback-image/{filename}', [AdminFeedbackController::class, 'getImage'])->where('filename', '.*')->name('feedback.image');
    

    //APPOINTMENT
    Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::patch('/appointments/{id}/status', [AdminAppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::patch('/appointments/{id}/reschedule', [AdminAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::patch('/appointments/{id}/cancel', [AdminAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/appointments/booked-slots', [AdminAppointmentController::class, 'getBookedSlots'])->name('appointments.booked-slots');

    //MANAGE STAFF
    Route::get('/manageStaffAccount', [StaffController::class, 'index'])->name('manageStaffAccount');
    Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{id}', [StaffController::class, 'show'])->name('staff.show');
    Route::post('/staff/{id}/toggle', [StaffController::class, 'toggleStatus'])->name('staff.toggle');
    Route::get('/staff-stats', [StaffController::class, 'getStats'])->name('staff.stats');

    //PROFILE
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/home', fn() => Inertia::render('Staff/StaffHome'))->name('home');
});


require __DIR__.'/auth.php';
