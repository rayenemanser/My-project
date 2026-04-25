<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    // GET /api/doctor/appointments
    public function index(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $query = Appointment::forDoctor($doctorId)
            ->with('patient:id,name,avatar,phone');

        if ($request->boolean('today'))    $query->today();
        if ($request->boolean('upcoming')) $query->upcoming();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($date = $request->get('date')) {
            $query->whereDate('appointment_date', $date);
        }

        $appointments = $query
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->paginate($request->integer('per_page', 15));

        return response()->json($appointments);
    }

    // GET /api/doctor/appointments/stats
    public function stats(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        return response()->json([
            'today'      => Appointment::forDoctor($id)->today()->count(),
            'pending'    => Appointment::forDoctor($id)->pending()->count(),
            'confirmed'  => Appointment::forDoctor($id)->confirmed()->count(),
            'completed'  => Appointment::forDoctor($id)->completed()->count(),
            'this_month' => Appointment::forDoctor($id)
                ->whereMonth('appointment_date', now()->month)
                ->whereYear('appointment_date', now()->year)
                ->count(),
        ]);
    }

    // GET /api/doctor/appointments/calendar
    public function calendar(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;
        $year  = $request->integer('year',  now()->year);
        $month = $request->integer('month', now()->month);

        $appointments = Appointment::forDoctor($doctorId)
            ->whereYear('appointment_date', $year)
            ->whereMonth('appointment_date', $month)
            ->with('patient:id,name')
            ->get(['id', 'patient_id', 'appointment_date', 'appointment_time', 'status', 'reason']);

        $grouped = $appointments->groupBy(fn($a) => $a->appointment_date->day);

        return response()->json(['year' => $year, 'month' => $month, 'days' => $grouped]);
    }

    // GET /api/doctor/appointments/{id}
    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $appointment->load([
            'patient:id,name,phone,avatar',
            'patient.patientProfile',
            'prescription.items',
            'medicalRecord',
            'review',
        ]);

        return response()->json(['data' => $appointment]);
    }

    // PATCH /api/doctor/appointments/{id}/confirm
    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($appointment->status !== 'pending') {
            return response()->json(['message' => 'Only pending appointments can be confirmed.'], 422);
        }

        $appointment->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        AppNotification::send(
            $appointment->patient_id,
            'appointment_confirmed',
            'Appointment Confirmed',
            "Your appointment on {$appointment->appointment_date->format('d/m/Y')} at {$appointment->appointment_time} has been confirmed.",
            ['appointment_id' => $appointment->id]
        );

        return response()->json(['message' => 'Appointment confirmed.', 'data' => $appointment->fresh()]);
    }

    // PATCH /api/doctor/appointments/{id}/cancel
    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'This appointment cannot be cancelled.'], 422);
        }

        $request->validate(['cancellation_reason' => 'nullable|string|max:500']);

        $appointment->update([
            'status'              => 'cancelled',
            'cancelled_by'        => 'doctor',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at'        => now(),
        ]);

        AppNotification::send(
            $appointment->patient_id,
            'appointment_cancelled',
            'Appointment Cancelled',
            "Your appointment on {$appointment->appointment_date->format('d/m/Y')} was cancelled by the doctor.",
            ['appointment_id' => $appointment->id]
        );

        return response()->json(['message' => 'Appointment cancelled.']);
    }

    // PATCH /api/doctor/appointments/{id}/complete
    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($appointment->status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed appointments can be completed.'], 422);
        }

        $appointment->markAsCompleted();

        return response()->json(['message' => 'Appointment marked as completed.', 'data' => $appointment->fresh()]);
    }
}
