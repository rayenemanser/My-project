<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\doctor\DashboardController;
use App\Http\Controllers\doctor\PatientController;
use App\Http\Controllers\doctor\AppointmentController;
use App\Http\Controllers\doctor\PrescriptionController;
use App\Http\Controllers\doctor\ReportController;
use App\Http\Controllers\doctor\ScheduleController;
use App\Http\Controllers\patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\API\Patient\PatientAppointmentController;
use App\Http\Controllers\API\Patient\PatientMedicalController;
use App\Http\Controllers\API\Patient\DoctorController as PatientDoctorController;

// Public routes - CORS applied automatically if added to global/api middleware
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

// Protected routes with Sanctum
Route::middleware(['auth:sanctum'])->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/check-auth', [AuthController::class, 'checkAuth']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // Profile
    Route::get('/user/profile', [AuthController::class, 'getUserProfile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);

    // Password
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Email verification
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);

    // Account management
    Route::post('/account/deactivate', [AuthController::class, 'deactivateAccount']);

    // Sessions
    Route::get('/sessions', [AuthController::class, 'getActiveSessions']);
    Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession']);

    // Doctor specific routes
    Route::middleware(['role:DOCTOR'])->prefix('doctor')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::post('/patients', [PatientController::class, 'store']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::post('/prescriptions', [PrescriptionController::class, 'store']);

        // Schedule
        Route::get('/schedule', [ScheduleController::class, 'index']);
        Route::put('/schedule', [ScheduleController::class, 'update']);

        // Reports
        Route::get('/reports/monthly', [ReportController::class, 'monthly']);
        Route::get('/reports/yearly', [ReportController::class, 'yearly']);
        Route::get('/reports/patients', [ReportController::class, 'patients']);
    });

    // Patient specific routes
    Route::middleware(['role:PATIENT'])->prefix('patient')->group(function () {
        Route::get('/dashboard', [PatientDashboardController::class, 'index']);

        // Appointments
        Route::controller(PatientAppointmentController::class)->prefix('appointments')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show');
            Route::put('/{id}/cancel', 'cancel');
        });

        // Prescriptions
        Route::controller(PatientMedicalController::class)->group(function () {
            Route::get('/prescriptions', 'prescriptions');
            Route::post('/prescriptions/{id}/refill', 'requestRefill');

            // Medical Records
            Route::get('/medical-records', 'records');

            // Allergies
            Route::get('/allergies', 'allergies');
            Route::post('/allergies', 'addAllergy');
            Route::put('/allergies/{id}', 'updateAllergy');
            Route::delete('/allergies/{id}', 'deleteAllergy');
        });

        // Find Doctors
        Route::controller(PatientDoctorController::class)->prefix('doctors')->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
        });
    });
});
