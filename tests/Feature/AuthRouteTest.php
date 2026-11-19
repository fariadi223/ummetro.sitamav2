<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthRouteTest extends TestCase
{
    public function test_halaman_login_sitama_tampil(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('LOGIN SITAMA')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('operator/login');
    }

    public function test_halaman_login_operator_tampil_dan_noindex(): void
    {
        $this->get('/operator/login')
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('noindex', false);
    }

    public function test_login_wajib_isi(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['username', 'password']);
    }

    public function test_login_ditolak_endpoint_sitama(): void
    {
        Http::fake([
            '*/auth/login' => Http::response([
                'code' => 401,
                'messages' => 'Invalid Username and Password !',
                'errors' => null,
                'data' => null,
            ], 401),
        ]);

        $this->post('/login', ['username' => 'salah', 'password' => 'salah123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_area_internal_menolak_tamu(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/operator/login');
        $this->get('/prodi/dashboard')->assertRedirect('/operator/login');
    }

    public function test_area_alumni_mengarahkan_tamu_ke_login_alumni(): void
    {
        $this->get('/alumni/dashboard')->assertRedirect('/login');
    }

    public function test_root_mengarah_ke_login_alumni(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
