<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public_without_authentication(): void
    {
        $this->getJson('/api/v1/admin/enrollments')->assertOk();
    }

    public function test_filters_by_name_email_course_and_status(): void
    {
        $courseA = Course::factory()->create(['name' => 'Curso Alpha']);
        $courseB = Course::factory()->create(['name' => 'Curso Beta']);

        $s1 = Student::factory()->create(['name' => 'Maria Silva', 'email' => 'maria@ex.com']);
        $s2 = Student::factory()->create(['name' => 'Pedro Santos', 'email' => 'pedro@ex.com']);

        Enrollment::factory()->create([
            'student_id' => $s1->id,
            'course_id' => $courseA->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
        Enrollment::factory()->create([
            'student_id' => $s2->id,
            'course_id' => $courseB->id,
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $token = $this->adminToken();

        $r = $this->withToken($token)->getJson('/api/v1/admin/enrollments?name=Maria');
        $r->assertOk();
        $this->assertCount(1, $r->json('data'));

        $r = $this->withToken($token)->getJson('/api/v1/admin/enrollments?email=pedro@');
        $r->assertOk();
        $this->assertCount(1, $r->json('data'));

        $r = $this->withToken($token)->getJson('/api/v1/admin/enrollments?course_id='.$courseA->id);
        $r->assertOk();
        $this->assertCount(1, $r->json('data'));

        $r = $this->withToken($token)->getJson('/api/v1/admin/enrollments?status=pending');
        $r->assertOk();
        $this->assertCount(1, $r->json('data'));
    }

    public function test_updates_enrollment_and_student_fields(): void
    {
        $enrollment = Enrollment::factory()->create();
        $token = $this->adminToken();

        $response = $this->withToken($token)->putJson('/api/v1/admin/enrollments/'.$enrollment->id, [
            'payment_status' => PaymentStatus::Paid->value,
            'student' => [
                'name' => 'Nome Atualizado',
                'phone' => '11888887777',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.student.name', 'Nome Atualizado');

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }

    public function test_deletes_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->deleteJson('/api/v1/admin/enrollments/'.$enrollment->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
    }

    public function test_deletes_enrollment_with_admin_token_still_works(): void
    {
        $enrollment = Enrollment::factory()->create();
        $token = $this->adminToken();

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/enrollments/'.$enrollment->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
    }

    public function test_exports_excel(): void
    {
        Enrollment::factory()->count(2)->create();
        $token = $this->adminToken();

        $response = $this->withToken($token)->get('/api/v1/admin/enrollments/export?format=xlsx');

        $response->assertOk()->assertDownload('inscricoes-marcasite.xlsx');
    }

    public function test_exports_pdf(): void
    {
        Enrollment::factory()->create();
        $token = $this->adminToken();

        $response = $this->withToken($token)->get('/api/v1/admin/enrollments/export?format=pdf');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
