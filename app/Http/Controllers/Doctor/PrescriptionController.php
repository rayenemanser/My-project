<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    // GET /api/doctor/prescriptions
    public function index(Request $request): JsonResponse
    {
        $prescriptions = Prescription::forDoctor($request->user()->id)
            ->with(['patient:id,name', 'items'])
            ->when($request->get('status'),     fn($q, $s) => $q->where('status', $s))
            ->when($request->get('patient_id'), fn($q, $id) => $q->where('patient_id', $id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($prescriptions);
    }

    // POST /api/doctor/prescriptions
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id'        => 'required|exists:users,id',
            'appointment_id'    => 'nullable|exists:appointments,id',
            'diagnosis'         => 'nullable|string|max:1000',
            'notes'             => 'nullable|string|max:2000',
            'valid_until'       => 'required|date|after:today',
            'items'             => 'required|array|min:1',
            'items.*.medication_name' => 'required|string|max:255',
            'items.*.dosage'    => 'required|string|max:100',
            'items.*.frequency' => 'required|string|max:100',
            'items.*.duration'  => 'required|string|max:100',
            'items.*.route'     => 'nullable|string|max:100',
            'items.*.instructions' => 'nullable|string|max:500',
            'items.*.quantity'  => 'sometimes|integer|min:1',
        ]);

        $prescription = Prescription::create([
            'patient_id'     => $request->patient_id,
            'doctor_id'      => $request->user()->id,
            'appointment_id' => $request->appointment_id,
            'diagnosis'      => $request->diagnosis,
            'notes'          => $request->notes,
            'issued_date'    => today(),
            'valid_until'    => $request->valid_until,
            'status'         => 'active',
        ]);

        foreach ($request->items as $item) {
            PrescriptionItem::create([
                'prescription_id' => $prescription->id,
                'medication_name' => $item['medication_name'],
                'dosage'          => $item['dosage'],
                'frequency'       => $item['frequency'],
                'duration'        => $item['duration'],
                'route'           => $item['route'] ?? null,
                'instructions'    => $item['instructions'] ?? null,
                'quantity'        => $item['quantity'] ?? 1,
            ]);
        }

        AppNotification::send(
            $request->patient_id,
            'new_prescription',
            'New Prescription',
            'Dr. ' . $request->user()->name . ' has issued a new prescription for you.',
            ['prescription_id' => $prescription->id]
        );

        $prescription->load('items', 'patient:id,name');

        return response()->json(['message' => 'Prescription created.', 'data' => $prescription], 201);
    }

    // GET /api/doctor/prescriptions/{id}
    public function show(Request $request, Prescription $prescription): JsonResponse
    {
        if ($prescription->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $prescription->load(['patient:id,name,phone', 'items', 'appointment:id,appointment_date,appointment_time']);

        return response()->json(['data' => $prescription]);
    }

    // PUT /api/doctor/prescriptions/{id}
    public function update(Request $request, Prescription $prescription): JsonResponse
    {
        if ($prescription->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($prescription->status !== 'active') {
            return response()->json(['message' => 'Only active prescriptions can be updated.'], 422);
        }

        $request->validate([
            'diagnosis'   => 'nullable|string|max:1000',
            'notes'       => 'nullable|string|max:2000',
            'valid_until' => 'sometimes|date|after:today',
            'status'      => 'sometimes|in:active,cancelled',
        ]);

        $prescription->update($request->only(['diagnosis', 'notes', 'valid_until', 'status']));

        return response()->json(['message' => 'Updated.', 'data' => $prescription->fresh('items')]);
    }

    // GET /api/doctor/patients/{patientId}/prescriptions
    public function patientPrescriptions(Request $request, int $patientId): JsonResponse
    {
        $prescriptions = Prescription::forDoctor($request->user()->id)
            ->where('patient_id', $patientId)
            ->with('items')
            ->latest()
            ->get();

        return response()->json(['data' => $prescriptions]);
    }
}
