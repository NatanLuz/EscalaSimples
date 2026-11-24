<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_and_authenticates_a_company_administrator(): void
    {
        $other = Company::factory()->create();
        $this->withSession(['before_registration' => true]);
        $before = session()->getId();

        $response = $this->post('/cadastro', [
            'company_name' => 'Empresa Teste', 'name' => 'Ana Teste', 'email' => 'ANA@EXAMPLE.TEST',
            'password' => 'senha-ficticia', 'password_confirmation' => 'senha-ficticia', 'company_id' => $other->id,
        ]);

        $response->assertRedirect('/dashboard');
        $user = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotEquals($before, session()->getId());
        $this->assertNotEquals($other->id, $user->company_id);
        $this->assertSame('Empresa Teste', $user->company->name);
        $this->assertTrue(Hash::check('senha-ficticia', $user->password));
    }

    public function test_registration_rejects_invalid_fields_and_password_confirmation(): void
    {
        $this->post('/cadastro', ['company_name' => '', 'name' => '', 'email' => 'inválido', 'password' => 'curta', 'password_confirmation' => 'outra'])
            ->assertSessionHasErrors(['company_name', 'name', 'email', 'password']);
        $this->assertDatabaseCount('companies', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_email_without_creating_a_company(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);
        $this->post('/cadastro', [
            'company_name' => 'Outra', 'name' => 'Ana', 'email' => 'ANA@EXAMPLE.TEST',
            'password' => 'senha-ficticia', 'password_confirmation' => 'senha-ficticia',
        ])->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('companies', 1);
        $this->assertGuest();
    }

    public function test_registration_rejects_a_mismatched_password_confirmation(): void
    {
        $this->post('/cadastro', [
            'company_name' => 'Empresa', 'name' => 'Ana', 'email' => 'ana@example.test',
            'password' => 'senha-ficticia', 'password_confirmation' => 'senha-diferente',
        ])->assertSessionHasErrors(['password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_passwords_longer_than_bcrypt_byte_limit(): void
    {
        $password = str_repeat('á', 37);
        $this->post('/cadastro', [
            'company_name' => 'Empresa', 'name' => 'Ana', 'email' => 'ana@example.test',
            'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasErrors(['password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_authenticates_and_regenerates_the_session(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test', 'password' => 'senha-ficticia']);
        $this->withSession(['before_login' => true]);
        $before = session()->getId();
        $this->post('/login', ['email' => 'ANA@EXAMPLE.TEST', 'password' => 'senha-ficticia'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotEquals($before, session()->getId());
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ana@example.test', 'password' => 'senha-ficticia']);
        $this->post('/login', ['email' => 'ana@example.test', 'password' => 'incorreta'])->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'ana@example.test', 'password' => 'senha-ficticia']);
        $key = 'login:'.hash('sha256', 'ana@example.test|127.0.0.1');
        RateLimiter::clear($key);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'ana@example.test', 'password' => 'incorreta'])->assertSessionHasErrors(['email']);
        }
        $response = $this->post('/login', ['email' => 'ana@example.test', 'password' => 'senha-ficticia']);
        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Muitas tentativas.', session('errors')->first('email'));
        $this->assertGuest();
        RateLimiter::clear($key);
    }

    public function test_guests_cannot_access_dashboard_or_logout(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_login_rejects_post_requests_without_a_valid_csrf_token(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->post('/login', ['email' => 'ana@example.test', 'password' => 'senha-ficticia'])->assertStatus(419);
        $this->withSession(['_token' => 'token-de-teste'])
            ->post('/login', ['_token' => 'token-de-teste', 'email' => 'ana@example.test', 'password' => 'senha-ficticia'])
            ->assertSessionHasErrors(['email']);
    }

    public function test_dashboard_uses_only_the_authenticated_users_company(): void
    {
        $first = User::factory()->create(['name' => 'Administradora Um']);
        $first->company->update(['name' => 'Empresa Um']);
        $second = User::factory()->create(['name' => 'Administrador Dois']);
        $second->company->update(['name' => 'Empresa Dois']);

        $this->actingAs($first)->get('/dashboard?company_id='.$second->company_id.'&company='.$second->company->public_id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard')
            ->where('company.name', 'Empresa Um')->where('administrator.name', 'Administradora Um')
            ->missing('company.id')->missing('company.public_id')->missing('administrator.email'))
            ->assertDontSee('Empresa Dois');

        $this->actingAs($second)->get('/dashboard?company_id='.$first->company_id)
            ->assertInertia(fn (Assert $page) => $page->where('company.name', 'Empresa Dois')->where('administrator.name', 'Administrador Dois'))
            ->assertDontSee('Empresa Um');
    }

    public function test_logout_invalidates_authentication_and_rotates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_value' => 'segredo-ficticio']);
        $before = session()->getId();
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNotEquals($before, session()->getId());
        $this->assertNotEquals($token, session()->token());
        $this->assertFalse(session()->has('private_value'));
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
