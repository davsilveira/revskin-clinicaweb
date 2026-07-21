<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_admin_login_redirects_to_pacientes(): void
    {
        $this->makeUser('admin', 'admin-home@example.com');

        $this->post('/login', [
            'email' => 'admin-home@example.com',
            'password' => 'password',
        ])->assertRedirect(route('pacientes.index'));
    }

    public function test_medico_login_redirects_to_pacientes(): void
    {
        $this->makeUser('medico', 'medico-home@example.com');

        $this->post('/login', [
            'email' => 'medico-home@example.com',
            'password' => 'password',
        ])->assertRedirect(route('pacientes.index'));
    }

    public function test_secretaria_login_redirects_to_pacientes(): void
    {
        $this->makeUser('secretaria', 'secretaria-home@example.com');

        $this->post('/login', [
            'email' => 'secretaria-home@example.com',
            'password' => 'password',
        ])->assertRedirect(route('pacientes.index'));
    }

    public function test_callcenter_login_redirects_to_receitas(): void
    {
        $this->makeUser('callcenter', 'cc-home@example.com');

        $this->post('/login', [
            'email' => 'cc-home@example.com',
            'password' => 'password',
        ])->assertRedirect(route('receitas.index'));
    }

    public function test_dashboard_redirects_admin_to_pacientes(): void
    {
        $user = $this->makeUser('admin', 'admin-dash@example.com');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('pacientes.index'));
    }

    public function test_dashboard_redirects_callcenter_to_receitas(): void
    {
        $user = $this->makeUser('callcenter', 'cc-dash@example.com');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('receitas.index'));
    }

    public function test_root_redirects_authenticated_medico_to_pacientes(): void
    {
        $user = $this->makeUser('medico', 'medico-root@example.com');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('pacientes.index'));
    }
}
