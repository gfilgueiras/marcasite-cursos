<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_endpoints_are_public(): void
    {
        $this->getJson('/api/v1/admin/users')->assertOk();
    }

    public function test_can_create_and_list_users_without_admin_token(): void
    {
        $create = $this->postJson('/api/v1/admin/users', [
            'name' => 'Usuario Teste',
            'email' => 'usuario.teste@marcasite.local',
            'password' => 'segredo123',
            'role' => 'editor',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Usuario Teste')
            ->assertJsonPath('data.email', 'usuario.teste@marcasite.local')
            ->assertJsonPath('data.role', 'editor');

        $this->getJson('/api/v1/admin/users?per_page=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'active'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_delete_user(): void
    {
        $create = $this->postJson('/api/v1/admin/users', [
            'name' => 'Para Excluir',
            'email' => 'excluir@marcasite.local',
            'password' => 'segredo123',
            'role' => 'aluno',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $del = $this->deleteJson("/api/v1/admin/users/{$id}");
        $del->assertOk()->assertJsonPath('message', 'Usuário removido.');

        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_can_update_user(): void
    {
        $create = $this->postJson('/api/v1/admin/users', [
            'name' => 'Antes',
            'email' => 'antes@marcasite.local',
            'password' => 'segredo123',
            'role' => 'aluno',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $res = $this->putJson("/api/v1/admin/users/{$id}", [
            'name' => 'Depois',
            'email' => 'depois@marcasite.local',
            'role' => 'editor',
            'active' => false,
            'password' => 'outrasenha12',
            'password_confirmation' => 'outrasenha12',
        ]);
        $res->assertOk()
            ->assertJsonPath('data.name', 'Depois')
            ->assertJsonPath('data.email', 'depois@marcasite.local')
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonPath('data.active', false);
    }
}

