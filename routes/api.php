<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Doctor\DashboardController          as DoctorDashboard;
use App\Http\Controllers\Api\Doctor\AppointmentController        as DoctorAppointment;
use App\Http\Controllers\Api\Doctor\PatientController            as DoctorPatient;
use App\Http\Controllers\Api\Doctor\PrescriptionController       as DoctorPrescription;
use App\Http\Controllers\Api\Doctor\MedicalRecordController      as DoctorRecord;
use App\Http\Controllers\Api\Doctor\ProfileController            as DoctorProfile;
use App\Http\Controllers\Api\Patient\DashboardController         as PatientDashboard;
use App\Http\Controllers\Api\Patient\PatientAppointmentController;
use App\Http\Controllers\Api\Patient\PatientMedicalController;
use App\Http\Controllers\Api\Patient\DoctorController            as PatientDoctorSearch;
use App\Http\Controllers\Api\Patient\PatientProfileController;
use App\Http\Controllers\Api\Pharmacist\PharmacistDashboardController;
use App\Http\Controllers\Api\Pharmacist\PharmacistProfileController;
use App\Http\Controllers\Api\Pharmacist\MedicationController;
use App\Http\Controllers\Api\Pharmacist\PrescriptionFillController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Doc DZ — API Routes
|--------------------------------------------------------------------------
*/

// ── Public routes ──────────────────────────────────────────────────────────────
Route::post('/register',        [AuthController::class, 'register']);
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

// Public doctor search
Route::get('/doctors',             [PatientDoctorSearch::class, 'index']);
Route::get('/doctors/specialties', [PatientDoctorSearch::class, 'specialties']);
Route::get('/doctors/{id}',        [PatientDoctorSearch::class, 'show']);

// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::post('/logout-all',      [AuthController::class, 'logoutAll']);
    Route::get('/check-auth',       [AuthController::class, 'checkAuth']);
    Route::get('/me',               [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Notifications (shared — all roles)
    Route::prefix('notifications')->group(function () {
        Route::get('/',             [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/read-all',   [NotificationController::class, 'markAllRead']);
        Route::patch('/{id}/read',  [NotificationController::class, 'markRead']);
        Route::delete('/{id}',      [NotificationController::class, 'destroy']);
    });

    // ══════════════════════════════════════════════════════════════════════════
    // DOCTOR routes
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware('role:DOCTOR')->prefix('doctor')->group(function () {

        Route::get('/dashboard', [DoctorDashboard::class, 'index']);

        // Profile
        Route::get('/profile',   [DoctorProfile::class, 'show']);
        Route::put('/profile',   [DoctorProfile::class, 'update']);

        // Appointments
        Route::get('/appointments',                 [DoctorAppointment::class, 'index']);
        Route::get('/appointments/stats',           [DoctorAppointment::class, 'stats']);
        Route::get('/appointments/calendar',        [DoctorAppointment::class, 'calendar']);
        Route::get('/appointments/{id}',            [DoctorAppointment::class, 'show']);
        Route::patch('/appointments/{id}/confirm',  [DoctorAppointment::class, 'confirm']);
        Route::patch('/appointments/{id}/cancel',   [DoctorAppointment::class, 'cancel']);
        Route::patch('/appointments/{id}/complete', [DoctorAppointment::class, 'complete']);

        // Patients
        Route::get('/patients',      [DoctorPatient::class, 'index']);
        Route::post('/patients',     [DoctorPatient::class, 'store']);
        Route::get('/patients/{id}', [DoctorPatient::class, 'show']);

        // Medical Records
        Route::get('/patients/{patientId}/records',  [DoctorRecord::class, 'index']);
        Route::post('/patients/{patientId}/records', [DoctorRecord::class, 'store']);
        Route::get('/records/{id}',                  [DoctorRecord::class, 'show']);
        Route::put('/records/{id}',                  [DoctorRecord::class, 'update']);
        Route::delete('/records/{id}',               [DoctorRecord::class, 'destroy']);

        // Prescriptions
        Route::get('/prescriptions',      [DoctorPrescription::class, 'index']);
        Route::post('/prescriptions',     [DoctorPrescription::class, 'store']);
        Route::get('/prescriptions/{id}', [DoctorPrescription::class, 'show']);
        Route::put('/prescriptions/{id}', [DoctorPrescription::class, 'update']);
    });

    // ══════════════════════════════════════════════════════════════════════════
    // PATIENT routes
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware('role:PATIENT')->prefix('patient')->group(function () {

        Route::get('/dashboard', [PatientDashboard::class, 'index']);

        // Profile
        Route::get('/profile',   [PatientProfileController::class, 'show']);
        Route::put('/profile',   [PatientProfileController::class, 'update']);

        // Doctor search
        Route::get('/doctors',                  [PatientDoctorSearch::class, 'index']);
        Route::get('/doctors/specialties',      [PatientDoctorSearch::class, 'specialties']);
        Route::get('/doctors/{id}',             [PatientDoctorSearch::class, 'show']);
        Route::get('/doctors/{doctorId}/slots', [PatientAppointmentController::class, 'availableSlots']);

        // Appointments
        Route::get('/appointments',              [PatientAppointmentController::class, 'index']);
        Route::post('/appointments',             [PatientAppointmentController::class, 'store']);
        Route::get('/appointments/{id}',         [PatientAppointmentController::class, 'show']);
        Route::put('/appointments/{id}/cancel',  [PatientAppointmentController::class, 'cancel']);
        Route::post('/appointments/{id}/review', [PatientAppointmentController::class, 'review']);

        // Prescriptions
        Route::get('/prescriptions',              [PatientMedicalController::class, 'prescriptions']);
        Route::get('/prescriptions/{id}',         [PatientMedicalController::class, 'showPrescription']);
        Route::post('/prescriptions/{id}/refill', [PatientMedicalController::class, 'requestRefill']);

        // Medical Records
        Route::get('/medical-records',      [PatientMedicalController::class, 'records']);
        Route::get('/medical-records/{id}', [PatientMedicalController::class, 'showRecord']);

        // Allergies
        Route::get('/allergies',            [PatientMedicalController::class, 'allergies']);
        Route::post('/allergies',           [PatientMedicalController::class, 'addAllergy']);
        Route::put('/allergies/{index}',    [PatientMedicalController::class, 'updateAllergy']);
        Route::delete('/allergies/{index}', [PatientMedicalController::class, 'deleteAllergy']);
    });

    // ══════════════════════════════════════════════════════════════════════════
    // PHARMACIST routes
    // ══════════════════════════════════════════════════════════════════════════
    Route::middleware('role:PHARMACIST')->prefix('pharmacist')->group(function () {

        Route::get('/dashboard', [PharmacistDashboardController::class, 'index']);

        // Profile
        Route::get('/profile',   [PharmacistProfileController::class, 'show']);
        Route::put('/profile',   [PharmacistProfileController::class, 'update']);

        // Medications (inventory)
        Route::get('/medications',              [MedicationController::class, 'index']);
        Route::post('/medications',             [MedicationController::class, 'store']);
        Route::get('/medications/{id}',         [MedicationController::class, 'show']);
        Route::put('/medications/{id}',         [MedicationController::class, 'update']);
        Route::delete('/medications/{id}',      [MedicationController::class, 'destroy']);
        Route::patch('/medications/{id}/stock', [MedicationController::class, 'updateStock']);

        // Prescription fills
        Route::get('/prescription-fills',              [PrescriptionFillController::class, 'index']);
        Route::get('/prescription-fills/{id}',         [PrescriptionFillController::class, 'show']);
        Route::patch('/prescription-fills/{id}/fill',  [PrescriptionFillController::class, 'fill']);
        Route::patch('/prescription-fills/{id}/reject',[PrescriptionFillController::class, 'reject']);
    });
});
