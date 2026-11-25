<?php

namespace Tests\MySql;

use App\Models\User;
use App\Models\WorkPattern;
use App\Services\ScheduleCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

class MySqlFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $app = parent::createApplication();
        $config = $app['config']->get('database.connections.mysql');
        $id = getenv('ESCALA_DOCKER_ID');
        if ($app->environment() !== 'testing' || $app['config']->get('database.default') !== 'mysql'
            || ($config['host'] ?? '') !== '127.0.0.1' || (string) ($config['port'] ?? '') !== '3309'
            || ($config['database'] ?? '') !== 'escalasimples_test' || ($config['username'] ?? '') !== 'escala_test'
            || ! empty($config['url']) || isset($config['read']) || isset($config['write'])
            || ! preg_match('/^[a-f0-9]{12}$/', (string) $id)) {
            throw new RuntimeException('Conexão MySQL de teste não autorizada.');
        }
        $server = $app['db']->connection('mysql')->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account, @@hostname AS container_id, VERSION() AS version');
        if ($server->db !== 'escalasimples_test' || ! str_starts_with($server->account, 'escala_test@')
            || $server->container_id !== $id || ! str_starts_with($server->version, '8.4.')) {
            throw new RuntimeException('Servidor MySQL de teste não autorizado.');
        }
        $app['config']->set('database.connections', ['mysql' => $config]);

        return $app;
    }

    public function test_registration_and_cross_company_directory(): void
    {
        $other = User::factory()->create();
        $email = strtolower((string) Str::ulid()).'@example.test';
        $this->post('/cadastro', ['company_name' => 'Empresa fictícia', 'name' => 'Gestor', 'email' => $email, 'password' => 'senha-ficticia', 'password_confirmation' => 'senha-ficticia', 'company_id' => $other->company_id])->assertRedirect('/minhas-escalas');
        $user = User::where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotEquals($other->company_id, $user->company_id);
        $this->post('/setores', ['name' => 'Caixa', 'is_active' => true])->assertRedirect('/setores');
        $department = $user->company->departments()->firstOrFail();
        $foreign = $other->company->departments()->create(['name' => 'Externo', 'is_active' => true]);
        $this->post('/colaboradores', ['name' => 'Pessoa fictícia', 'is_active' => true, 'department' => $foreign->public_id])->assertSessionHasErrors('department');
        $this->post('/colaboradores', ['name' => 'Pessoa fictícia', 'is_active' => true, 'department' => $department->public_id])->assertRedirect('/colaboradores');
        $this->get('/setores/'.$foreign->public_id.'/edit')->assertNotFound();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $email, 'password' => 'senha-ficticia'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_batch_shifts_and_holidays(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = $user->company->employees()->create(['name' => 'Pessoa A', 'is_active' => true]);
        $second = $user->company->employees()->create(['name' => 'Pessoa B', 'is_active' => true]);
        $foreign = $other->company->employees()->create(['name' => 'Pessoa externa', 'is_active' => true]);
        $ids = [$first->public_id, $second->public_id];
        $this->actingAs($user)->post('/colaboradores/lote', ['employees' => $ids, 'action' => 'deactivate'])->assertRedirect('/colaboradores');
        $this->assertFalse($first->fresh()->is_active);
        $this->post('/colaboradores/lote', ['employees' => $ids, 'action' => 'activate'])->assertRedirect('/colaboradores');
        $this->post('/colaboradores/lote', ['employees' => [$first->public_id, $foreign->public_id], 'action' => 'deactivate'])->assertSessionHasErrors('employees');
        $this->assertTrue($first->fresh()->is_active);
        $this->post('/jornadas', ['name' => 'Ciclo fictício', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, false], 'starts_at' => '19:00', 'ends_at' => '07:00'])->assertRedirect('/jornadas');
        $pattern = WorkPattern::where('company_id', $user->company_id)->firstOrFail();
        $this->post('/atribuicoes', ['employees' => [$first->public_id], 'pattern' => $pattern->public_id, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28'])->assertSessionHasNoErrors();
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, [$first->id], '2024-02-28', '2024-02-29')[$first->id];
        $this->assertSame(['work', 'off'], array_column($result, 'status'));
        $this->assertTrue($result['2024-02-28']['ends_next_day']);
        $this->post('/feriados', ['name' => 'Feriado fictício', 'date' => '2024-02-29', 'type' => 'holiday', 'scope' => 'company', 'is_active' => true])->assertRedirect('/feriados');
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->has('holidays', 1)->where('holidays.0.name', 'Feriado fictício'));
    }

    public function test_csv_confirmation_and_replay_are_atomic(): void
    {
        $user = User::factory()->create();
        $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $content = "nome,setor\nPessoa ficticia,Caixa\n";
        $file = fn () => UploadedFile::fake()->createWithContent('teste.csv', $content);
        $this->actingAs($user);
        $preview = $this->post('/colaboradores/importar/previa', ['file' => $file()])->assertOk()->viewData('page')['props']['preview'];
        $this->assertSame(0, $user->company->employees()->count());
        $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $file()])->assertRedirect('/colaboradores');
        $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $file()])->assertSessionHasErrors('file');
        $this->assertSame(1, $user->company->employees()->count());
    }

    public function test_monthly_save_reopen_recurrence_and_revision_conflict(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa fictícia', 'is_active' => true]);
        $foreign = $other->company->employees()->create(['name' => 'Externo', 'is_active' => true]);
        $payload = ['month' => '2024-01', 'revision' => 0, 'operation_id' => (string) Str::ulid(), 'model' => 'custom', 'days_off' => [6], 'employees' => [['id' => $employee->public_id, 'name' => $employee->name, 'days' => array_fill(0, 31, 'work'), 'edited' => array_fill(0, 31, false), 'recurring_days_off' => [2, 6]]]];
        $this->actingAs($user)->post('/minhas-escalas', $payload)->assertRedirect();
        $this->post('/minhas-escalas', $payload)->assertRedirect();
        $this->post('/minhas-escalas', [...$payload, 'operation_id' => (string) Str::ulid()])->assertSessionHasErrors('revision');
        $this->get('/minhas-escalas?month=2024-01')->assertInertia(fn (Assert $page) => $page->where('revision', 1)->where('snapshot.employees.0.id', $employee->public_id));
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('reused.employees.0.days.3', 'off')->where('reused.employees.0.days.6', 'off'));
        $payload['month'] = '2024-03';
        $payload['employees'][0]['id'] = $foreign->public_id;
        $payload['operation_id'] = (string) Str::ulid();
        $this->post('/minhas-escalas', $payload)->assertSessionHasErrors('employees');
        $this->assertSame(1, DB::table('monthly_schedules')->where('company_id', $user->company_id)->count());
    }
}
