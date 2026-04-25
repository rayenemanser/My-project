<?php
// app/Http/Controllers/Api/Pharmacist/MedicationController.php

namespace App\Http\Controllers\Api\Pharmacist;

use App\Http\Controllers\Controller;
use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Medication::forPharmacist($request->user()->id);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }
        if ($request->boolean('expiring_soon')) {
            $query->expiringSoon();
        }
        if ($request->filled('search')) {
            $query->where('medication_name', 'like', '%'.$request->search.'%');
        }

        $medications = $query->orderBy('medication_name')->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $medications,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'medication_name'        => 'required|string|max:255',
            'category'               => 'required|string|max:100',
            'stock_quantity'         => 'required|integer|min:0',
            'reorder_level'          => 'required|integer|min:0',
            'expiry_date'            => 'required|date|after:today',
            'price'                  => 'required|numeric|min:0',
            'description'            => 'nullable|string',
            'requires_prescription'  => 'boolean',
        ]);

        $medication = Medication::create([
            ...$validated,
            'pharmacist_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Médicament ajouté avec succès',
            'data'    => $medication,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $medication = Medication::forPharmacist($request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($medication->toArray(), [
                'is_low_stock'       => $medication->is_low_stock,
                'is_expired'         => $medication->is_expired,
                'days_until_expiry'  => $medication->days_until_expiry,
            ]),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $medication = Medication::forPharmacist($request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'medication_name'       => 'sometimes|string|max:255',
            'category'              => 'sometimes|string|max:100',
            'stock_quantity'        => 'sometimes|integer|min:0',
            'reorder_level'         => 'sometimes|integer|min:0',
            'expiry_date'           => 'sometimes|date|after:today',
            'price'                 => 'sometimes|numeric|min:0',
            'description'           => 'nullable|string',
            'requires_prescription' => 'boolean',
        ]);

        $medication->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Médicament mis à jour',
            'data'    => $medication,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $medication = Medication::forPharmacist($request->user()->id)
            ->findOrFail($id);

        $medication->delete();

        return response()->json([
            'success' => true,
            'message' => 'Médicament supprimé',
        ]);
    }

    public function updateStock(Request $request, int $id)
    {
        $medication = Medication::forPharmacist($request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'change' => 'required|integer', // موجب أو سالب
        ]);

        $newQuantity = max(0, $medication->stock_quantity + $validated['change']);
        $medication->update(['stock_quantity' => $newQuantity]);

        return response()->json([
            'success' => true,
            'message' => 'Stock mis à jour',
            'data'    => $medication,
        ]);
    }
}
