<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CreateCompanyWithAdministrator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_company_and_administrator_with_secure_identifiers_and_password(): void
    {
        $otherCompany = Company::factory()->create();
        $company = app(CreateCompanyWithAdministrator::class)->execute(
            ['name' => 'Empresa Exemplo', 'city' => 'Recife', 'state' => 'PE', 'public_id' => 'invalid'],
            ['name' => 'Pessoa Exemplo', 'email' => 'admin@example.test', 'password' => 'senha-ficticia', 'company_id' => $otherCompany->id]
        );

        $user = $company->user;
        $this->assertSame('Empresa Exemplo', $company->name);
        $this->assertSame('Recife', $company->city);
        $this->assertSame('PE', $company->state);
        $this->assertNotNull($user);
        $this->assertSame($company->id, $user->company_id);
        $this->assertTrue($user->company->is($company));
        $this->assertTrue($company->user()->first()->is($user));
        $this->assertTrue(Hash::check('senha-ficticia', $user->password));
        $this->assertNotSame('senha-ficticia', $user->password);
        $this->assertTrue(Str::isUlid($company->public_id));
        $this->assertTrue(Str::isUlid($user->public_id));
        $this->assertNotSame($otherCompany->public_id, $company->public_id);
        $this->assertNotSame($company->public_id, $user->public_id);
    }

    public function test_service_rolls_back_company_when_user_creation_fails(): void
    {
        User::factory()->create(['email' => 'duplicado@example.test']);

        try {
            app(CreateCompanyWithAdministrator::class)->execute(
                ['name' => 'Empresa que deve ser revertida'],
                ['name' => 'Pessoa', 'email' => 'duplicado@example.test', 'password' => 'senha-ficticia']
            );
            $this->fail('A criação deveria falhar por e-mail duplicado.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('companies', ['name' => 'Empresa que deve ser revertida']);
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_unique_company_identifier_is_enforced_by_database(): void
    {
        $company = Company::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('companies')->insert(['name' => 'Outra', 'public_id' => $company->public_id]);
    }

    public function test_unique_user_identifier_is_enforced_by_database(): void
    {
        $user = User::factory()->create();
        $otherCompany = Company::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Outro', 'email' => 'outro@example.test', 'password' => 'hash', 'public_id' => $user->public_id, 'company_id' => $otherCompany->id]);
    }

    public function test_database_rejects_a_second_user_for_the_same_company(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        User::factory()->create(['company_id' => $user->company_id]);
    }

    public function test_database_rejects_unknown_company(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['company_id' => 999999]);
    }

    public function test_database_rejects_user_without_company(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['company_id' => null]);
    }

    public function test_company_id_is_not_mass_assignable_on_user(): void
    {
        $user = new User;
        $this->assertFalse($user->isFillable('company_id'));
    }

    public function test_database_restricts_deletion_of_company_with_a_user(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        $user->company->delete();
    }

    public function test_company_migrations_stop_if_users_exist(): void
    {
        User::factory()->create();

        foreach (['2026_11_23_000001_create_companies_table.php', '2026_11_23_000002_add_company_to_users_table.php'] as $filename) {
            $migration = require database_path('migrations/'.$filename);
            try {
                $migration->up();
                $this->fail('A migration deveria recusar usuários existentes.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('usuários existentes', $exception->getMessage());
            }
        }
    }
}
