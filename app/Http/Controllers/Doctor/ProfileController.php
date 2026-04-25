<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\DoctorAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /api/doctor/profile
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['doctorProfile', 'doctorProfile.availabilities']);

        return response()->json([
            'data' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'avatar'         => $user->avatar_url,
                'initials'       => $user->initials,
                'gender'         => $user->gender,
                'age'            => $user->age,
                'city'           => $user->city,
                'wilaya'         => $user->wilaya,
                'profile'        => $user->doctorProfile,
                'availabilities' => $user->doctorProfile?->availabilities ?? [],
            ],
        ]);
    }

    // PUT /api/doctor/profile
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'phone'                 => 'nullable|string|max:20',
            'gender'                => 'nullable|in:male,female',
            'date_of_birth'         => 'nullable|date|before:today',
            'city'                  => 'nullable|string|max:100',
            'wilaya'                => 'nullable|string|max:100',
            'specialty'             => 'sometimes|string|max:100',
            'sub_specialty'         => 'nullable|string|max:100',
            'experience_years'      => 'nullable|integer|min:0',
            'bio'                   => 'nullable|string|max:2000',
            'clinic_name'           => 'nullable|string|max:255',
            'clinic_address'        => 'nullable|string|max:500',
            'consultation_fee'      => 'nullable|numeric|min:0',
            'consultation_duration' => 'nullable|integer|min:10|max:120',
            'is_available'          => 'nullable|boolean',
            'languages'             => 'nullable|array',
            'languages.*'           => 'string',
            'education'             => 'nullable|array',
            'working_hours'         => 'nullable|array',
        ]);

        $user = $request->user();

        $user->update($request->only(['name', 'phone', 'gender', 'date_of_birth', 'city', 'wilaya']));

        $profileData = $request->only([
            'specialty', 'sub_specialty', 'experience_years', 'bio',
            'clinic_name', 'clinic_address', 'consultation_fee',
            'consultation_duration', 'is_available', 'languages',
            'education', 'working_hours',
        ]);

        if (!empty(array_filter($profileData, fn($v) => $v !== null))) {
            $user->doctorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $user->fresh('doctorProfile'),
        ]);
    }

    // POST /api/doctor/profile/avatar
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $request->user()->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Avatar uploaded.',
            'avatar'  => asset('storage/' . $path),
        ]);
    }

    // PUT /api/doctor/availability
    public function updateAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'availabilities'              => 'required|array',
            'availabilities.*.day_of_week'=> 'required|integer|between:0,6',
            'availabilities.*.start_time' => 'required|date_format:H:i',
            'availabilities.*.end_time'   => 'required|date_format:H:i',
            'availabilities.*.is_active'  => 'boolean',
        ]);

        $doctorId = $request->user()->id;

        foreach ($request->availabilities as $slot) {
            DoctorAvailability::updateOrCreate(
                ['doctor_id' => $doctorId, 'day_of_week' => $slot['day_of_week']],
                [
                    'start_time' => $slot['start_time'],
                    'end_time'   => $slot['end_time'],
                    'is_active'  => $slot['is_active'] ?? true,
                ]
            );
        }

        return response()->json([
            'message'        => 'Availability updated.',
            'availabilities' => DoctorAvailability::where('doctor_id', $doctorId)->get(),
        ]);
    }
}
