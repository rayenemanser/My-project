<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\DoctorAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PatientAppointmentController extends Controller
{
    // ─── GET /patient/appointments ────────────────────────────────────────────
    public function index(Request $request)
    {
        $patient = $request->user();

        $query = Appointment::forPatient($patient->id)
            ->with(['doctor:id,name,email', 'doctor.doctorProfile'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter upcoming only
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $appointments = $query->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous récupérés avec succès',
            'data'    => $appointments,
        ]);
    }

    // ─── GET /patient/appointments/{id} ──────────────────────────────────────
    public function show(Request $request, int $id)
    {
        $appointment = Appointment::forPatient($request->user()->id)
            ->with([
                'doctor:id,name,email',
                'doctor.doctorProfile',
                'prescription',
                'medicalRecord',
                'review',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($appointment->toArray(), [
                'can_be_cancelled'   => $appointment->can_be_cancelled,
                'can_be_rescheduled' => $appointment->can_be_rescheduled,
                'is_reviewable'      => $appointment->is_reviewable,
            ]),
        ]);
    }

    // ─── POST /patient/appointments ───────────────────────────────────────────
    public function store(Request $request)
    {
        $patient = $request->user();

        $validated = $request->validate([
            'doctor_id'        => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'type'             => ['required', Rule::in(['in_person', 'online'])],
            'reason'           => 'required|string|max:500',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // تحقق إن المريض ما عندوش رندي-فو في نفس الوقت
        $conflict = Appointment::forPatient($patient->id)
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->whereTime('appointment_time', $validated['appointment_time'])
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un rendez-vous à cette date et heure.',
            ], 422);
        }

        // تحقق إن الدكتور ما عندوش رندي-فو في نفس الوقت
        $doctorConflict = Appointment::forDoctor($validated['doctor_id'])
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->whereTime('appointment_time', $validated['appointment_time'])
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($doctorConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Ce créneau n\'est plus disponible.',
            ], 422);
        }

        $appointment = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $validated['doctor_id'],
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'type'             => $validated['type'],
            'reason'           => $validated['reason'],
            'notes'            => $validated['notes'] ?? null,
            'status'           => 'pending',
            'duration'         => 30,
        ]);

        // إشعار للدكتور
        AppNotification::send(
            $validated['doctor_id'],
            'new_appointment',
            'Nouveau Rendez-vous',
            "Le patient {$patient->name} a pris un rendez-vous le {$appointment->appointment_date->format('d/m/Y')} à {$appointment->appointment_time}.",
            ['appointment_id' => $appointment->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous créé avec succès',
            'data'    => $appointment->load(['doctor:id,name,email', 'doctor.doctorProfile']),
        ], 201);
    }

    // ─── PUT /patient/appointments/{id}/cancel ────────────────────────────────
    public function cancel(Request $request, int $id)
    {
        $appointment = Appointment::forPatient($request->user()->id)
            ->findOrFail($id);

        if (!$appointment->can_be_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Ce rendez-vous ne peut pas être annulé.',
            ], 422);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment->update([
            'status'              => 'cancelled',
            'cancelled_by'        => 'patient',
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
            'cancelled_at'        => now(),
        ]);

        // إشعار للدكتور
        AppNotification::send(
            $appointment->doctor_id,
            'appointment_cancelled',
            'Rendez-vous Annulé',
            "Le patient {$request->user()->name} a annulé le rendez-vous du {$appointment->appointment_date->format('d/m/Y')} à {$appointment->appointment_time}.",
            ['appointment_id' => $appointment->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous annulé avec succès',
            'data'    => $appointment,
        ]);
    }

    // ─── POST /patient/appointments/{id}/review ───────────────────────────────
    public function review(Request $request, int $id)
    {
        $appointment = Appointment::forPatient($request->user()->id)
            ->findOrFail($id);

        if (!$appointment->is_reviewable) {
            return response()->json([
                'success' => false,
                'message' => 'Ce rendez-vous ne peut pas être évalué.',
            ], 422);
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = $appointment->review()->create([
            'patient_id' => $request->user()->id,
            'doctor_id'  => $appointment->doctor_id,
            'rating'     => $validated['rating'],
            'comment'    => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Évaluation soumise avec succès',
            'data'    => $review,
        ], 201);
    }

    // ─── GET /patient/doctors/{doctorId}/slots ────────────────────────────────
    public function availableSlots(Request $request, int $doctorId)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date    = Carbon::parse($request->date);
        $dayName = strtolower($date->format('l')); // monday, tuesday...

        // جيب الـ availability تاع الدكتور في هذا اليوم
        $availability = DoctorAvailability::where('doctor_id', $doctorId)
            ->where('day_of_week', $dayName)
            ->where('is_available', true)
            ->first();

        if (!$availability) {
            return response()->json([
                'success' => true,
                'message' => 'Aucun créneau disponible pour cette date.',
                'data'    => [],
            ]);
        }

        // جيب المواعيد المحجوزة في هذا اليوم
        $bookedTimes = Appointment::forDoctor($doctorId)
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('appointment_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->toArray();

        // توليد الـ slots
        $slots    = [];
        $start    = Carbon::parse($availability->start_time);
        $end      = Carbon::parse($availability->end_time);
        $duration = $availability->slot_duration ?? 30;

        while ($start->copy()->addMinutes($duration)->lte($end)) {
            $timeStr = $start->format('H:i');
            $slots[] = [
                'time'      => $timeStr,
                'available' => !in_array($timeStr, $bookedTimes),
            ];
            $start->addMinutes($duration);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'date'  => $date->format('Y-m-d'),
                'slots' => $slots,
            ],
        ]);
    }
}
