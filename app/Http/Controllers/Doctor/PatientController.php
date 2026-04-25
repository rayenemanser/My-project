<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * GET /api/doctor/patients
     * Returns all patients who had at least one appointment with this doctor.
     */
    public function index(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $patientIds = Appointment::forDoctor($doctorId)
            ->distinct('patient_id')
            ->pluck('patient_id');

        $query = User::whereIn('id', $patientIds)
            ->with('patientProfile');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($patients);
    }

    // GET /api/doctor/patients/{id}
    public function show(Request $request, int $patientId): JsonResponse
    {
        $doctorId = $request->user()->id;

        // Verify this patient has had an appointment with this doctor
        $hasRelation = Appointment::forDoctor($doctorId)
            ->where('patient_id', $patientId)
            ->exists();

        if (!$hasRelation) {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        $patient = User::with([
            'patientProfile',
            'appointmentsAsPatient' => fn($q) => $q->forDoctor($doctorId)->latest('appointment_date')->take(10),
            'prescriptionsAsPatient' => fn($q) => $q->forDoctor($doctorId)->with('items')->latest()->take(5),
            'medicalRecordsAsPatient' => fn($q) => $q->where('doctor_id', $doctorId)->latest('record_date')->take(5),
        ])->findOrFail($patientId);

        return response()->json(['data' => $patient]);
    }
}
