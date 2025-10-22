<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Customer\AppointmentController;
use App\Http\Controllers\Customer\ScheduleController;
use App\Http\Controllers\Customer\PaymongoController;

use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing/Welcome', [

    ]);
});
// Landing Page Routes
Route::get('/services', fn() => Inertia::render('Landing/Services'));
Route::get('/faqs', fn() => Inertia::render('Landing/Faqs'));
Route::get('/contactUs', fn() => Inertia::render('Landing/ContactUs'));
Route::get('/testimonials', fn() => Inertia::render('Landing/Testimonials'));

// Customer Routes
Route::middleware(['auth'])->group(function () {

    Route::get('/home', fn() => Inertia::render('Customer/Home'))->name('customer.home');

    // Appointment Routes
    Route::get('/schedule-appointment', [AppointmentController::class, 'create'])->name('customer.appointment');
    Route::post('/schedule-appointment', [AppointmentController::class, 'store'])->name('customer.appointment.store');
    Route::get('/payment', [AppointmentController::class, 'showPaymentPage'])->name('customer.payment.view');
    Route::get('/appointments', [AppointmentController::class, 'view'])->name('customer.view');
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->name('customer.appointment.cancel');
    Route::get('/customer/schedules/{date}', [ScheduleController::class, 'getByDate'])->name('customer.schedules.date');
    Route::get('/available-slots', [AppointmentController::class, 'getAvailableSlots'])->name('customer.available-slots');
    Route::get('/appointment/check-availability', [AppointmentController::class, 'checkAvailability'])->name('appointment.check-availability');
    
    // Resched Routes
    Route::get('/appointments/{id}/reschedule', [AppointmentController::class, 'showRescheduleForm'])->name('customer.appointment.reschedule.form');
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->name('customer.appointment.reschedule');


    // Schedule Routes
    Route::get('/customer/schedules', [ScheduleController::class, 'index'])->name('customer.schedules');

    Route::get('/view-appointment', fn() => Inertia::render('Customer/ViewAppointment'))->name('customer.view');
    Route::get('/feedback', fn() => Inertia::render('Customer/Feedback'))->name('customer.feedback');
});
    // Profile Routes
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    
    // Admin Routes
    Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
         Route::get('/admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
});

    //Payment Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/customer/payment/create', [PaymongoController::class, 'createPayment'])->name('payment.create');
        Route::get('/payment/success', [PaymongoController::class, 'success'])->name('payment.success');
        Route::get('/payment/cancelled', [PaymongoController::class, 'cancelled'])->name('payment.cancelled');
    });
        Route::post('/payment/webhook', [PaymongoController::class, 'webhook'])->name('payment.webhook');


require __DIR__.'/auth.php';
