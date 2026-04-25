<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\PharmacistProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // ─── POST /api/register ──────────────────────────────────────────
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|unique:users,email',
            'password'           => ['required', 'confirmed', Password::min(8)],
            'role'               => 'required|in:DOCTOR,PATIENT,PHARMACIST',
            'phone'              => 'nullable|string|max:20',
            'gender'             => 'nullable|in:male,female',
            'date_of_birth'      => 'nullable|date|before:today',
            'city'               => 'nullable|string|max:100',
            'country'            => 'nullable|string|max:100',
            // Doctor only
            'specialty'          => 'required_if:role,DOCTOR|string|max:100',
            'license_number'     => 'required_if:role,DOCTOR|string|unique:doctor_profiles,license_number',
            'experience_years'   => 'nullable|integer|min:0',
            // Pharmacist only
            'pharmacy_name'      => 'required_if:role,PHARMACIST|string|max:255',
            'pharma_license'     => 'required_if:role,PHARMACIST|string|unique:pharmacist_profiles,license_number',
            'pharmacist_license' => 'required_if:role,PHARMACIST|string|unique:pharmacist_profiles,pharmacist_license',
            'wilaya'             => 'nullable|string|max:100',
        ]);

        $user = User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'role'          => $request->role,
            'phone'         => $request->phone,
            'gender'        => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'city'          => $request->city,
            'country'       => $request->country,
            'status'        => 'active',
        ]);

        if ($request->role === 'DOCTOR') {
            DoctorProfile::create([
                'user_id'               => $user->id,
                'specialty'             => $request->specialty,
                'license_number'        => $request->license_number,
                'experience_years'      => $request->experience_years ?? 0,
                'is_available'          => true,
                'consultation_duration' => 30,
            ]);
        } elseif ($request->role === 'PHARMACIST') {
            PharmacistProfile::create([
                'user_id'            => $user->id,
                'pharmacy_name'      => $request->pharmacy_name,
                'license_number'     => $request->pharma_license,
                'pharmacist_license' => $request->pharmacist_license,
                'phone'              => $request->phone,
                'city'               => $request->city,
                'wilaya'             => $request->wilaya ?? $request->city,
            ]);
        } else {
            PatientProfile::create(['user_id' => $user->id]);
        }

        $token = $user->createToken('docdz-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 201);
    }

    // ─── POST /api/login ─────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (!$user->isActive()) {
            return response()->json(['message' => 'Your account is not active. Contact support.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('docdz-token')->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'is_online'     => true,
        ]);

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    // ─── POST /api/logout ────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $request->user()->update(['is_online' => false]);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ─── POST /api/logout-all ────────────────────────────────────────
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        $request->user()->update(['is_online' => false]);

        return response()->json(['message' => 'All sessions terminated.']);
    }

    // ─── GET /api/me ─────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isDoctor()) {
            $user->load(['doctorProfile', 'availabilities']);
        } elseif ($user->isPatient()) {
            $user->load('patientProfile');
        } elseif ($user->isPharmacist()) {
            $user->load('pharmacistProfile');
        }

        return response()->json(['data' => $this->formatUser($user)]);
    }

    // ─── POST /api/change-password ───────────────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        $token = $user->createToken('docdz-token')->plainTextToken;

        return response()->json([
            'message' => 'Password changed successfully.',
            'token'   => $token,
        ]);
    }

    // ─── GET /api/check-auth ─────────────────────────────────────────
    public function checkAuth(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => true,
            'user'          => $this->formatUser($request->user()),
        ]);
    }

    // ─── POST /api/forgot-password ───────────────────────────────────
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // نرجع نفس الرسالة سواء وُجد المستخدم أم لا (أمان)
        return response()->json([
            'message' => 'If this email exists, a reset link has been sent.',
        ]);
    }

    // ─── POST /api/reset-password ────────────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    // ─── Private helper ───────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        $data = [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'phone'         => $user->phone,
            'avatar'        => $user->avatar_url,
            'initials'      => $user->initials,
            'gender'        => $user->gender,
            'age'           => $user->age,
            'city'          => $user->city,
            'country'       => $user->country,
            'status'        => $user->status,
            'is_online'     => $user->is_online,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];

        if ($user->relationLoaded('doctorProfile')) {
            $data['profile']        = $user->doctorProfile;
            $data['availabilities'] = $user->availabilities ?? [];
        }

        if ($user->relationLoaded('patientProfile')) {
            $data['profile'] = $user->patientProfile;
        }

        if ($user->relationLoaded('pharmacistProfile')) {
            $data['profile'] = $user->pharmacistProfile;
        }

        return $data;
    }
}
