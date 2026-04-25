<?php
// app/Http/Controllers/Api/Pharmacist/PharmacistDashboardController.php

namespace App\Http\Controllers\Api\Pharmacist;

use App\Http\Controllers\Controller;
use App\Models\Medication;
use App\Models\PrescriptionFill;
use Illuminate\Http\Request;

class PharmacistDashboardController extends Controller
{
    public function index(Request $request)
    {
        $pharmacistId = $request->user()->id;

        $medications  = Medication::forPharmacist($pharmacistId);

        $stats = [
            'total_medications' => (clone $medications)->count(),
            'total_stock'       => (clone $medications)->sum('stock_quantity'),
            'low_stock_count'   => (clone $medications)->lowStock()->count(),
            'expiring_soon'     => (clone $medications)->expiringSoon()->count(),
            'stock_value'       => (clone $medications)->get()
                                    ->sum(fn($m) => $m->stock_quantity * $m->price),
            'pending_fills'     => PrescriptionFill::where('pharmacist_id', $pharmacistId)
                                    ->where('status', 'pending')->count(),
        ];

        $lowStockMeds  = (clone $medications)->lowStock()
                            ->orderBy('stock_quantity')->limit(5)->get();

        $expiringSoon  = (clone $medications)->expiringSoon(30)
                            ->orderBy('expiry_date')->limit(5)->get();

        $recentFills   = PrescriptionFill::where('pharmacist_id', $pharmacistId)
                            ->with(['patient:id,name', 'prescription'])
                            ->latest()->limit(5)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats'         => $stats,
                'low_stock'     => $lowStockMeds,
                'expiring_soon' => $expiringSoon,
                'recent_fills'  => $recentFills,
            ],
        ]);
    }
}
