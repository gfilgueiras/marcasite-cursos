<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_courses(): void
    {
        Course::factory()->count(2)->create(['active' => true]);
        Course::factory()->inactive()->create();
        Course::factory()->create([
            'active' => true,
            'enrollment_ends_at' => now()->subDay()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/courses');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_creates_enrollment_and_returns_checkout_url(): void
    {
        $course = Course::factory()->create([
            'active' => true,
            'price_cents' => 15_000,
        ]);

        $response = $this->postJson('/api/v1/enrollments', [
            'course_id' => $course->id,
            'name' => 'João Teste',
            'email' => 'joao@test.com',
            'phone' => '11999990000',
            'document' => '52998224725',
        ]);

        $response->assertCreated();
        $checkoutUrl = $response->json('checkout_url');
        $this->assertIsString($checkoutUrl);
        $this->assertTrue(
            str_contains($checkoutUrl, 'enrollment=') || str_contains($checkoutUrl, 'stripe.test')
        );

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'payment_status' => PaymentStatus::Pending->value,
            'amount_cents' => 15_000,
        ]);
    }

    public function test_rejects_inactive_course(): void
    {
        $course = Course::factory()->inactive()->create();

        $response = $this->postJson('/api/v1/enrollments', [
            'course_id' => $course->id,
            'name' => 'João Teste',
            'email' => 'joao@test.com',
            'phone' => '11999990000',
            'document' => '52998224725',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_rejects_enrollment_when_period_ended(): void
    {
        $course = Course::factory()->create([
            'active' => true,
            'enrollment_ends_at' => now()->subDay()->toDateString(),
        ]);

        $response = $this->postJson('/api/v1/enrollments', [
            'course_id' => $course->id,
            'name' => 'João Teste',
            'email' => 'joao@test.com',
            'phone' => '11999990000',
            'document' => '52998224725',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Enrollment::query()->count());
    }
}
