<?php

namespace Tests\Feature;

use App\Models\EmployeeWorkPattern;
use App\Models\User;
use App\Models\WorkPattern;
use App\Services\AssignWorkPattern;
use App\Services\ScheduleCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_cycle_override_history_and_tenant_scoping(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa fictícia', 'is_active' => true]);
        $foreign = $other->company->employees()->create(['name' => 'Outro', 'is_active' => true]);
        $data = ['name' => '12x36', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, false], 'starts_at' => '19:00', 'ends_at' => '07:00'];
        $this->actingAs($user)->post('/jornadas', $data)->assertRedirect('/jornadas');
        $pattern = WorkPattern::firstOrFail();
        $assignment = ['employees' => [$employee->public_id], 'pattern' => $pattern->public_id, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28', 'ends_on' => '2024-03-03', 'company_id' => $other->company_id];
        $this->post('/atribuicoes', $assignment)->assertSessionHasNoErrors();
        $this->post('/atribuicoes', $assignment)->assertSessionHasErrors('employees');
        $this->post('/atribuicoes', [...$assignment, 'employees' => [$foreign->public_id]])->assertSessionHasErrors('employees');
        $this->actingAs($other)->get('/jornadas/'.$pattern->public_id.'/edit')->assertNotFound();
        $this->actingAs($user)->put('/jornadas/'.$pattern->public_id, [...$data, 'sequence' => [false, true]])->assertRedirect('/jornadas');
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, [$employee->id], '2024-02-27', '2024-03-04')[$employee->id];
        $this->assertSame(['unscheduled', 'work', 'off', 'work', 'off', 'work', 'unscheduled'], array_column($result, 'status'));
        $this->assertSame('19:00', $result['2024-02-28']['starts_at']);
        $this->assertSame('07:00', $result['2024-02-28']['ends_at']);
        $this->assertTrue($result['2024-02-28']['ends_next_day']);
        $this->assertFalse($result['2024-02-29']['ends_next_day']);
        $this->post('/excecoes', ['employee' => $employee->public_id, 'date' => '2024-02-29', 'is_work' => true, 'reason' => 'Teste fictício'])->assertSessionHasNoErrors();
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, [$employee->id], '2024-02-29', '2024-03-01')[$employee->id];
        $this->assertSame(['work', 'work'], array_column($result, 'status'));
        $this->assertSame(1, EmployeeWorkPattern::count());
    }

    public function test_weekly_nonconsecutive_days_and_batch_conflict_is_atomic(): void
    {
        $user = User::factory()->create();
        $employees = collect(range(1, 2))->map(fn ($n) => $user->company->employees()->create(['name' => "Pessoa $n", 'is_active' => true]));
        $this->actingAs($user)->post('/jornadas', ['name' => 'Semanal', 'is_active' => true, 'kind' => 'weekly', 'sequence' => [false, true, true, false, true, true, true]])->assertRedirect();
        $pattern = WorkPattern::firstOrFail();
        $data = ['employees' => [$employees[0]->public_id], 'pattern' => $pattern->public_id, 'reference_date' => '2024-01-01', 'starts_on' => '2024-01-01'];
        $this->post('/atribuicoes', $data)->assertSessionHasNoErrors();
        $this->post('/atribuicoes', [...$data, 'employees' => $employees->pluck('public_id')->all()])->assertSessionHasErrors('employees');
        $this->assertSame(1, EmployeeWorkPattern::count());
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, [$employees[0]->id], '2024-01-01', '2024-01-07')[$employees[0]->id];
        $this->assertSame(['off', 'work', 'work', 'off', 'work', 'work', 'work'], array_column($result, 'status'));
    }

    public function test_cross_tenant_composite_foreign_key_is_enforced(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $employee = $one->company->employees()->create(['name' => 'Pessoa', 'is_active' => true]);
        $pattern = new WorkPattern(['name' => 'Outro', 'is_active' => true]);
        $pattern->company_id = $two->company_id;
        $pattern->save();
        $version = $pattern->versions()->create(['company_id' => $two->company_id, 'kind' => 'cycle', 'sequence' => [true]]);
        $this->expectException(QueryException::class);
        DB::table('employee_work_patterns')->insert(['public_id' => (string) Str::ulid(), 'company_id' => $one->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2024-01-01', 'starts_on' => '2024-01-01']);
    }

    public function test_inactive_patterns_and_employees_cannot_receive_assignments(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa', 'is_active' => false]);
        $this->actingAs($user)->post('/jornadas', ['name' => 'Inativo', 'is_active' => false, 'kind' => 'cycle', 'sequence' => [true, false]])->assertRedirect();
        $pattern = WorkPattern::firstOrFail();
        $data = ['employees' => [$employee->public_id], 'pattern' => $pattern->public_id, 'reference_date' => '2024-01-01', 'starts_on' => '2024-01-01'];
        $this->post('/atribuicoes', $data)->assertSessionHasErrors('employees');
        $employee->update(['is_active' => true]);
        $this->post('/atribuicoes', $data)->assertSessionHasErrors('pattern');
    }

    public function test_calculation_for_hundreds_of_employees_uses_bounded_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/jornadas', ['name' => '5x2', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, true, true, true, true, false, false]])->assertRedirect();
        $pattern = WorkPattern::firstOrFail();
        $ids = [];
        $versionId = $pattern->versions()->firstOrFail()->id;
        for ($i = 0; $i < 700; $i++) {
            $employee = $user->company->employees()->create(['name' => "Pessoa fictícia $i", 'is_active' => true]);
            $ids[] = $employee->id;
            EmployeeWorkPattern::create(['company_id' => $user->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $versionId, 'reference_date' => '2023-12-30', 'starts_on' => '2023-12-29']);
        }
        DB::enableQueryLog();
        $start = microtime(true);
        $memory = memory_get_usage(true);
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, $ids, '2023-12-29', '2024-01-28');
        $elapsed = microtime(true) - $start;
        $memoryDelta = memory_get_usage(true) - $memory;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        fwrite(STDERR, sprintf("Cálculo 700 por 31 dias: %.3f s; memória adicional %.2f MiB; pico %.2f MiB; consultas %d\n", $elapsed, $memoryDelta / 1048576, memory_get_peak_usage(true) / 1048576, count($queries)));
        $this->assertCount(700, $result);
        $this->assertCount(3, $queries);
        $this->assertSame(['off', 'work', 'work', 'work', 'work'], array_slice(array_column($result[$ids[0]], 'status'), 0, 5));
        $this->assertCount(31, $result[$ids[699]]);
    }

    public function test_six_by_one_continues_across_years_and_assignment_can_be_replaced(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa fictícia', 'is_active' => true]);
        $this->actingAs($user)->post('/jornadas', ['name' => '6x1', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, true, true, true, true, true, false]])->assertRedirect();
        $pattern = WorkPattern::firstOrFail();
        $data = ['employees' => [$employee->public_id], 'pattern' => $pattern->public_id, 'reference_date' => '2023-12-27', 'starts_on' => '2023-12-27'];
        $this->post('/atribuicoes', $data)->assertSessionHasNoErrors();
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, [$employee->id], '2023-12-31', '2024-01-03')[$employee->id];
        $this->assertSame(['work', 'work', 'off', 'work'], array_column($result, 'status'));
        $assignment = EmployeeWorkPattern::firstOrFail();
        $this->post('/atribuicoes/'.$assignment->public_id.'/encerrar', ['ends_on' => '2024-01-02', 'reason' => 'Substituição'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assignment_changes', ['company_id' => $user->company_id, 'user_id' => $user->id, 'employee_work_pattern_id' => $assignment->id, 'previous_ends_on' => null, 'new_ends_on' => '2024-01-02', 'reason' => 'Substituição']);
        $this->post('/atribuicoes', [...$data, 'starts_on' => '2024-01-03'])->assertSessionHasNoErrors();
        $this->assertSame(2, EmployeeWorkPattern::count());
    }

    public function test_guests_and_cross_company_operations_are_rejected(): void
    {
        $this->get('/jornadas')->assertRedirect('/login');
        $this->get('/atribuicoes')->assertRedirect('/login');
        $this->post('/excecoes', [])->assertRedirect('/login');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $other->company->employees()->create(['name' => 'Pessoa externa', 'is_active' => true]);
        $this->actingAs($user)->post('/excecoes', ['employee' => $foreign->public_id, 'date' => '2024-01-01', 'is_work' => true, 'reason' => 'Teste'])->assertNotFound();
        $this->assertDatabaseCount('schedule_overrides', 0);
    }

    public function test_versions_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/jornadas', ['name' => 'Teste', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, false]])->assertRedirect();
        $version = WorkPattern::firstOrFail()->versions()->firstOrFail();
        try {
            $version->update(['sequence' => [false, true]]);
            $this->fail('Atualização deveria ser recusada.');
        } catch (\LogicException) {
            $this->assertSame([true, false], $version->fresh()->sequence);
        }
        $this->expectException(\LogicException::class);
        $version->delete();
    }

    public function test_consecutive_overrides_and_transition_between_patterns(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa fictícia', 'is_active' => true]);
        $this->actingAs($user)->post('/jornadas', ['name' => 'A', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, false]])->assertRedirect();
        $first = WorkPattern::firstOrFail();
        $this->post('/jornadas', ['name' => 'B', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [false, true, true]])->assertRedirect();
        $second = WorkPattern::where('name', 'B')->firstOrFail();
        $data = ['employees' => [$employee->public_id], 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-27', 'ends_on' => '2024-02-29'];
        $this->post('/atribuicoes', [...$data, 'pattern' => $first->public_id])->assertSessionHasNoErrors();
        $this->post('/atribuicoes', [...$data, 'pattern' => $second->public_id, 'starts_on' => '2024-03-01', 'ends_on' => '2024-03-02'])->assertSessionHasNoErrors();
        $this->post('/excecoes', ['employee' => $employee->public_id, 'date' => '2024-02-28', 'is_work' => false, 'reason' => 'Teste'])->assertSessionHasNoErrors();
        $this->post('/excecoes', ['employee' => $employee->public_id, 'date' => '2024-02-29', 'is_work' => true, 'reason' => 'Teste'])->assertSessionHasNoErrors();
        $result = app(ScheduleCalculator::class)->calculate($user->company_id, [$employee->id], '2024-02-26', '2024-03-03')[$employee->id];
        $this->assertSame(['unscheduled', 'off', 'off', 'work', 'work', 'off', 'unscheduled'], array_column($result, 'status'));
    }

    public function test_batch_assignment_uses_bounded_queries_and_unique_ulids(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/jornadas', ['name' => 'Padrão', 'is_active' => true, 'kind' => 'cycle', 'sequence' => [true, false]])->assertRedirect();
        $pattern = WorkPattern::firstOrFail();
        $ids = [];
        for ($i = 0; $i < 120; $i++) {
            $ids[] = $user->company->employees()->create(['name' => "Pessoa $i", 'is_active' => true])->public_id;
        }
        DB::enableQueryLog();
        app(AssignWorkPattern::class)->assign($user->company_id, $ids, $pattern->public_id, '2024-01-01', '2024-01-01', null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(10, count($queries));
        $this->assertSame(120, EmployeeWorkPattern::distinct()->count('public_id'));
    }
}
