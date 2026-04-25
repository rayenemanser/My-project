<?php
// app/Http/Controllers/Api/Pharmacist/PrescriptionFillController.php

namespace App\Http\Controllers\Api\Pharmacist;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Prescription;
use App\Models\PrescriptionFill;
use Illuminate\Http\Request;

class PrescriptionFillController extends Controller
{
    public function index(Request $request)
    {
        $fills = PrescriptionFill::where('pharmacist_id', $request->user()->id)
            ->with(['patient:id,name,phone', 'prescription'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $fills,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $fill = PrescriptionFill::where('pharmacist_id', $request->user()->id)
            ->with(['patient:id,name,phone', 'prescription.doctor:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $fill,
        ]);
    }

    public function fill(Request $request, int $id)
    {
        $fill = PrescriptionFill::where('pharmacist_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $fill->update([
            'status'    => 'filled',
            'notes'     => $validated['notes'] ?? null,
            'filled_at' => now(),
        ]);

        AppNotification::send(
            $fill->patient_id,
            'prescription_filled',
            'Ordonnance Préparée',
            "Votre ordonnance a été préparée par la pharmacie {$request->user()->pharmacistProfile->pharmacy_name}.",
            ['fill_id' => $fill->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Ordonnance préparée avec succès',
            'data'    => $fill,
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $fill = PrescriptionFill::where('pharmacist_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $fill->update([
            'status' => 'rejected',
            'notes'  => $validated['notes'],
        ]);

        AppNotification::send(
            $fill->patient_id,
            'prescription_rejected',
            'Ordonnance Rejetée',
            "Votre ordonnance a été rejetée. Raison: {$validated['notes']}",
            ['fill_id' => $fill->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Ordonnance rejetée',
            'data'    => $fill,
        ]);
    }
}
