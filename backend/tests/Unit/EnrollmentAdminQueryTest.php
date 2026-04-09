<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\EnrollmentAdminQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnrollmentAdminQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_all_filters(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create(['name' => 'Filtro Nome', 'email' => 'filtro@mail.com']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
        Enrollment::factory()->count(2)->create();

        $query = new EnrollmentAdminQuery;
        $request = Request::create('/test', 'GET', [
            'name' => 'Filtro',
            'email' => 'filtro@',
            'course_id' => $course->id,
            'status' => PaymentStatus::Paid->value,
        ]);

        $this->assertSame(1, $query->filterFromRequest($request)->count());
    }
}
