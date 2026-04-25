<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientMedicalController extends Controller
{
    // ─── Prescriptions ────────────────────────────────────────────────────────

    /**
     * GET /api/patient/prescriptions
     */
    public function prescriptions(Request $request): JsonResponse
    {
        $prescriptions = Prescription::byPatient($request->user()->id)
            ->with(['doctor:id,name,avatar', 'doctor.doctorProfile:user_id,specialty'])
            ->when($request->get('status'), fn($q, $s) => $q->where('status', $s))
            ->latest('prescribed_date')
            ->paginate($request->integer('per_page', 10));

        return response()->json($prescriptions);
    }

    /**
     * GET /api/patient/prescriptions/{id}
     */
    public function showPrescription(Request $request, int $id): JsonResponse
    {
        $prescription = Prescription::byPatient($request->user()->id)
            ->with([
                'doctor:id,name,phone',
                'doctor.doctorProfile:user_id,specialty,clinic_name,clinic_address',
                'appointment:id,appointment_date,appointment_time',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $prescription]);
    }

    /**
     * POST /api/patient/prescriptions/{id}/refill
     */
    public function requestRefill(Request $request, int $id): JsonResponse
    {
        $prescription = Prescription::byPatient($request->user()->id)->findOrFail($id);

        if (!$prescription->can_refill) {
            return response()->json([
                'message' => 'This prescription cannot be refilled. It may be expired or has no refills remaining.',
            ], 422);
        }

        if ($prescription->refills_remaining <= 0) {
            return response()->json(['message' => 'No refills remaining.'], 422);
        }

        $prescription->decrement('refills_remaining');
        $prescription->update(['last_refill_date' => now()]);

        AppNotification::send(
            $prescription->doctor_id,
            'refill_request',
            'Prescription Refill',
            "{$request->user()->name} has requested a refill for {$prescription->medication}.",
            ['prescription_id' => $prescription->id]
        );

        return response()->json([
            'message' => 'Prescription refilled successfully.',
            'data'    => $prescription->fresh(),
        ]);
    }

    // ─── Medical Records ──────────────────────────────────────────────────────

    /**
     * GET /api/patient/medical-records
     */
    public function records(Request $request): JsonResponse
    {
        $records = MedicalRecord::byPatient($request->user()->id)
            ->visibleToPatient()
            ->with(['doctor:id,name', 'doctor.doctorProfile:user_id,specialty'])
            ->when($request->get('type'), fn($q, $t) => $q->byType($t))
            ->orderBy('record_date', 'desc')
            ->paginate($request->integer('per_page', 10));

        return response()->json($records);
    }

    /**
     * GET /api/patient/medical-records/{id}
     */
    public function showRecord(Request $request, int $id): JsonResponse
    {
        $record = MedicalRecord::byPatient($request->user()->id)
            ->visibleToPatient()
            ->with(['doctor:id,name', 'appointment:id,appointment_date,appointment_time'])
            ->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    // ─── Allergies (stored in PatientProfile) ────────────────────────────────

    /**
     * GET /api/patient/allergies
     */
    public function allergies(Request $request): JsonResponse
    {
        $profile = $request->user()->patientProfile;

        return response()->json([
            'data' => $profile?->allergies ?? [],
        ]);
    }

    /**
     * POST /api/patient/allergies
     */
    public function addAllergy(Request $request): JsonResponse
    {
        $request->validate(['allergy' => 'required|string|max:255']);

        $profile   = $request->user()->patientProfile()->firstOrCreate(['user_id' => $request->user()->id]);
        $allergies = $profile->allergies ?? [];

        if (in_array($request->allergy, $allergies)) {
            return response()->json(['message' => 'Allergy already exists.'], 422);
        }

        $allergies[] = $request->allergy;
        $profile->update(['allergies' => $allergies]);

        return response()->json([
            'message' => 'Allergy added.',
            'data'    => $allergies,
        ]);
    }

    /**
     * PUT /api/patient/allergies/{index}
     */
    public function updateAllergy(Request $request, int $index): JsonResponse
    {
        $request->validate(['allergy' => 'required|string|max:255']);

        $profile   = $request->user()->patientProfile;
        $allergies = $profile?->allergies ?? [];

        if (!isset($allergies[$index])) {
            return response()->json(['message' => 'Allergy not found.'], 404);
        }

        $allergies[$index] = $request->allergy;
        $profile->update(['allergies' => array_values($allergies)]);

        return response()->json([
            'message' => 'Allergy updated.',
            'data'    => array_values($allergies),
        ]);
    }

    /**
     * DELETE /api/patient/allergies/{index}
     */
    public function deleteAllergy(Request $request, int $index): JsonResponse
    {
        $profile   = $request->user()->patientProfile;
        $allergies = $profile?->allergies ?? [];

        if (!isset($allergies[$index])) {
            return response()->json(['message' => 'Allergy not found.'], 404);
        }

        unset($allergies[$index]);
        $profile->update(['allergies' => array_values($allergies)]);

        return response()->json([
            'message' => 'Allergy removed.',
            'data'    => array_values($allergies),
        ]);
    }
}
