<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    /**
     * GET /api/patient/doctors
     * ?search= | ?specialty= | ?city= | ?available_only=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'DOCTOR')
            ->where('status', 'active')
            ->with(['doctorProfile']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('doctorProfile', fn($dq) =>
                      $dq->where('specialty', 'like', "%{$search}%")
                         ->orWhere('clinic_name', 'like', "%{$search}%")
                         ->orWhere('clinic_city', 'like', "%{$search}%")
                  );
            });
        }

        if ($specialty = $request->get('specialty')) {
            $query->whereHas('doctorProfile', fn($q) =>
                $q->where('specialty', $specialty)
            );
        }

        if ($city = $request->get('city')) {
            $query->whereHas('doctorProfile', fn($q) =>
                $q->where('clinic_city', $city)
            );
        }

        if ($request->boolean('available_only')) {
            $query->whereHas('doctorProfile', fn($q) =>
                $q->where('is_available', true)
            );
        }

        $doctors = $query->paginate($request->integer('per_page', 12));

        return response()->json($doctors);
    }

    /**
     * GET /api/patient/doctors/{id}
     */
    public function show(int $id): JsonResponse
    {
        $doctor = User::where('id', $id)
            ->where('role', 'DOCTOR')
            ->with(['doctorProfile'])
            ->firstOrFail();

        $reviews = Review::where('doctor_id', $id)
            ->with('patient:id,name,avatar')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'rating'       => $r->rating,
                'comment'      => $r->comment,
                'patient_name' => $r->patient_name, // respects anonymous
                'date'         => $r->created_at->toDateString(),
            ]);

        return response()->json([
            'data' => [
                'id'          => $doctor->id,
                'name'        => $doctor->name,
                'avatar'      => $doctor->avatar_url,
                'city'        => $doctor->city,
                'profile'     => $doctor->doctorProfile,
                'reviews'     => $reviews,
            ],
        ]);
    }

    /**
     * GET /api/patient/specialties
     */
    public function specialties(): JsonResponse
    {
        $specialties = DoctorProfile::distinct()
            ->whereNotNull('specialty')
            ->pluck('specialty')
            ->values();

        return response()->json(['data' => $specialties]);
    }
}
