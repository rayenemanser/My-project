<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    // GET /api/doctor/patients/{patientId}/records
    public function index(Request $request, int $patientId): JsonResponse
    {
        $records = MedicalRecord::where('patient_id', $patientId)
            ->where('doctor_id', $request->user()->id)
            ->with('appointment:id,appointment_date,appointment_time')
            ->when($request->get('type'), fn($q, $t) => $q->byType($t))
            ->orderBy('record_date', 'desc')
            ->paginate(10);

        return response()->json($records);
    }

    // POST /api/doctor/patients/{patientId}/records
    public function store(Request $request, int $patientId): JsonResponse
    {
        $request->validate([
            'title'                  => 'required|string|max:255',
            'record_type'            => 'required|in:consultation,lab_result,radiology,surgery,vaccination,other',
            'diagnosis'              => 'required|string',
            'symptoms'               => 'nullable|string',
            'treatment'              => 'nullable|string',
            'notes'                  => 'nullable|string',
            'record_date'            => 'required|date',
            'appointment_id'         => 'nullable|exists:appointments,id',
            'is_visible_to_patient'  => 'boolean',
            'attachment'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $filePath = null;
        $fileName = null;
        $fileSize = null;

        if ($request->hasFile('attachment')) {
            $file     = $request->file('attachment');
            $filePath = $file->store('records', 'public');
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
        }

        $record = MedicalRecord::create([
            'patient_id'             => $patientId,
            'doctor_id'              => $request->user()->id,
            'appointment_id'         => $request->appointment_id,
            'title'                  => $request->title,
            'record_type'            => $request->record_type,
            'diagnosis'              => $request->diagnosis,
            'symptoms'               => $request->symptoms,
            'treatment'              => $request->treatment,
            'notes'                  => $request->notes,
            'record_date'            => $request->record_date,
            'is_visible_to_patient'  => $request->boolean('is_visible_to_patient', true),
            'file_path'              => $filePath,
            'file_name'              => $fileName,
            'file_size'              => $fileSize,
        ]);

        return response()->json(['message' => 'Record created.', 'data' => $record], 201);
    }

    // GET /api/doctor/records/{id}
    public function show(Request $request, MedicalRecord $record): JsonResponse
    {
        if ($record->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $record->load(['patient:id,name', 'appointment:id,appointment_date,appointment_time']);

        return response()->json(['data' => $record]);
    }

    // PUT /api/doctor/records/{id}
    public function update(Request $request, MedicalRecord $record): JsonResponse
    {
        if ($record->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'title'                 => 'sometimes|string|max:255',
            'diagnosis'             => 'sometimes|string',
            'symptoms'              => 'nullable|string',
            'treatment'             => 'nullable|string',
            'notes'                 => 'nullable|string',
            'record_date'           => 'sometimes|date',
            'record_type'           => 'sometimes|in:consultation,lab_result,radiology,surgery,vaccination,other',
            'is_visible_to_patient' => 'boolean',
        ]);

        $record->update($request->only([
            'title', 'diagnosis', 'symptoms', 'treatment',
            'notes', 'record_date', 'record_type', 'is_visible_to_patient',
        ]));

        return response()->json(['message' => 'Record updated.', 'data' => $record->fresh()]);
    }

    // DELETE /api/doctor/records/{id}
    public function destroy(Request $request, MedicalRecord $record): JsonResponse
    {
        if ($record->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $record->delete();

        return response()->json(['message' => 'Record deleted.']);
    }
}
