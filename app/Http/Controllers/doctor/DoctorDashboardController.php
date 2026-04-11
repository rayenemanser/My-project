<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // GET /api/doctor/dashboard
    public function index(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $todayAppts     = Appointment::forDoctor($doctorId)->today();
        $pendingAppts   = Appointment::forDoctor($doctorId)->pending();
        $confirmedAppts = Appointment::forDoctor($doctorId)->confirmed();

        // Unique patients this doctor has seen
        $totalPatients = Appointment::forDoctor($doctorId)
            ->distinct('patient_id')
            ->count('patient_id');

        // Monthly stats
        $thisMonth = Appointment::forDoctor($doctorId)
            ->whereMonth('appointment_date', now()->month)
            ->whereYear('appointment_date', now()->year);

        // Today's appointment list
        $todayList = Appointment::forDoctor($doctorId)
            ->today()
            ->with('patient:id,name,avatar,phone')
            ->orderBy('appointment_time')
            ->get(['id', 'patient_id', 'appointment_date', 'appointment_time', 'status', 'reason', 'type']);

        return response()->json([
            'stats' => [
                'appointments' => [
                    'today'      => $todayAppts->count(),
                    'pending'    => $pendingAppts->count(),
                    'confirmed'  => $confirmedAppts->count(),
                    'this_month' => $thisMonth->count(),
                ],
                'patients' => [
                    'total' => $totalPatients,
                ],
                'prescriptions' => [
                    'total'  => Prescription::forDoctor($doctorId)->count(),
                    'active' => Prescription::forDoctor($doctorId)->active()->count(),
                ],
                'records' => [
                    'total' => MedicalRecord::where('doctor_id', $doctorId)->count(),
                ],
                'rating'        => $request->user()->doctorProfile?->rating ?? 0,
                'total_reviews' => $request->user()->doctorProfile?->total_reviews ?? 0,
            ],
            'today_appointments' => $todayList,
        ]);
    }
}