<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paid_enrollments_for_email(): void
    {
        $course = Course::factory()->create(['active' => true]);
        $student = Student::factory()->create(['email' => 'aluno@exemplo.com']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $response = $this->postJson('/api/v1/my-enrollments', [
            'email' => 'ALUNO@exemplo.com',
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame($course->id, $response->json('data.0.course.id'));
        $this->assertSame(PaymentStatus::Paid->value, $response->json('data.0.payment_status'));
        $this->assertSame(PaymentStatus::Pending->value, $response->json('data.1.payment_status'));
    }

    public function test_returns_empty_when_email_unknown(): void
    {
        $response = $this->postJson('/api/v1/my-enrollments', [
            'email' => 'ninguem@exemplo.com',
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_validates_email(): void
    {
        $response = $this->postJson('/api/v1/my-enrollments', [
            'email' => 'invalido',
        ]);

        $response->assertStatus(422);
    }
}
