<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_crud_is_public_like_users(): void
    {
        $create = $this->postJson('/api/v1/admin/courses', [
            'name' => 'Curso API',
            'description' => 'Descrição',
            'price_cents' => 25_000,
            'currency' => 'brl',
            'active' => true,
        ]);

        $create->assertCreated();
        $id = $create->json('data.id');

        $this->getJson('/api/v1/admin/courses/'.$id)->assertOk();

        $this->putJson('/api/v1/admin/courses/'.$id, [
            'name' => 'Curso API 2',
            'description' => 'Descrição atualizada',
        ])->assertOk()->assertJsonPath('data.name', 'Curso API 2');

        $this->deleteJson('/api/v1/admin/courses/'.$id)->assertNoContent();
        $this->assertNull(Course::query()->find($id));
    }

    public function test_admin_token_still_works_for_courses(): void
    {
        $token = $this->adminToken();

        $create = $this->withToken($token)->postJson('/api/v1/admin/courses', [
            'name' => 'Com token',
            'description' => 'Descrição do curso',
            'price_cents' => 1000,
            'currency' => 'brl',
        ]);

        $create->assertCreated();
    }
}
