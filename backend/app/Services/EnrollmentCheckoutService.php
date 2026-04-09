<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string, document?: string|null}  $studentData
     * @return array{enrollment: Enrollment, checkout_url: string}
     */
    public function start(int $courseId, array $studentData): array
    {
        return DB::transaction(function () use ($courseId, $studentData) {
            $course = Course::query()->where('id', $courseId)->where('active', true)->firstOrFail();

            if (! $course->isOpenForEnrollment()) {
                throw ValidationException::withMessages([
                    'course_id' => ['As inscrições para este curso não estão abertas no momento.'],
                ]);
            }

            $student = Student::query()->updateOrCreate(
                ['email' => $studentData['email']],
                [
                    'name' => $studentData['name'],
                    'phone' => $studentData['phone'],
                    'document' => $studentData['document'] ?? null,
                ]
            );

            $enrollment = Enrollment::query()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'payment_status' => PaymentStatus::Pending->value,
                'amount_cents' => $course->price_cents,
                'currency' => $course->currency,
                'enrolled_at' => now(),
            ]);

            $session = $this->paymentGateway->createCheckoutSession($enrollment, $course, $student);

            $enrollment->update([
                'stripe_checkout_session_id' => $session['session_id'],
            ]);

            return [
                'enrollment' => $enrollment->fresh(['student', 'course']),
                'checkout_url' => $session['url'],
            ];
        });
    }
}
