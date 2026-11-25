<?php

namespace Tests\Feature;

use App\Models\EmployeeWorkPattern;
use App\Models\User;
use App\Models\WorkPattern;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleEditorTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $employees, int $revision = 0): array
    {
        return ['month' => '2024-02', 'revision' => $revision, 'operation_id' => (string) Str::ulid(), 'model' => 'custom', 'days_off' => [2, 6], 'employees' => $employees];
    }

    public function test_recurring_days_are_preserved_and_calendar_uses_saved_snapshot(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa', 'is_active' => true]);
        $days = array_fill(0, 31, 'work');
        $days[0] = 'off';
        $edited = array_fill(0, 31, false);
        $edited[0] = true;
        $row = ['id' => $employee->public_id, 'name' => 'Pessoa', 'days' => $days, 'edited' => $edited, 'recurring_days_off' => [2, 6]];
        $this->actingAs($user)->post('/minhas-escalas', [...$this->payload([$row]), 'month' => '2024-01'])->assertRedirect();
        $this->get('/minhas-escalas?month=2024-01')->assertInertia(fn (Assert $page) => $page->where('snapshot.employees.0.days.0', 'off')->where('snapshot.employees.0.edited.0', true)->where('snapshot.employees.0.recurring_days_off', [2, 6]));
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('reused.employees.0.days.0', 'work')->where('reused.employees.0.days.3', 'off')->where('reused.employees.0.days.6', 'off')->where('reused.employees.0.recurring_days_off', [2, 6]));
        $this->get('/escalas?month=2024-01&summary=company')->assertInertia(fn (Assert $page) => $page->where('monthly_saved', true)->where('rows.0.cells.0.status', 'off')->where('summary.totals.off', 1));
        $this->post('/excecoes', ['employee' => $employee->public_id, 'date' => '2024-01-01', 'is_work' => true, 'reason' => 'Teste'])->assertSessionHasErrors('date');
        $this->actingAs($other)->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('reused', null));
        $this->assertDatabaseCount('monthly_schedule_revisions', 1);
    }

    public function test_previous_month_is_reused_without_copying_daily_edits_or_inactive_employees(): void
    {
        $user = User::factory()->create();
        $first = $user->company->employees()->create(['name' => 'Primeiro', 'is_active' => true]);
        $second = $user->company->employees()->create(['name' => 'Segundo', 'is_active' => true]);
        $inactive = $user->company->employees()->create(['name' => 'Inativo', 'is_active' => true]);
        $rows = collect([$second, $inactive, $first])->map(fn ($employee) => ['id' => $employee->public_id, 'name' => $employee->name, 'days' => array_fill(0, 31, 'off')])->all();
        $this->actingAs($user)->post('/minhas-escalas', [...$this->payload($rows), 'month' => '2024-01'])->assertRedirect();
        $original = DB::table('monthly_schedule_revisions')->value('snapshot');
        $inactive->update(['is_active' => false]);
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page
            ->where('revision', 0)->where('snapshot', null)->where('reused.from', '2024-01')
            ->where('reused.model', 'custom')->where('reused.days_off', [2, 6])
            ->has('days', 29)->has('reused.employees', 2)
            ->where('reused.employees.0.id', $second->public_id)->where('reused.employees.1.id', $first->public_id)
            ->where('reused.employees.0.days.0', 'work')->where('reused.employees.0.days.3', 'off'));
        $this->assertDatabaseCount('monthly_schedules', 1);
        $this->assertSame($original, DB::table('monthly_schedule_revisions')->value('snapshot'));
        $this->assertSame(3, $user->company->employees()->count());
    }

    public function test_reuse_calculates_individual_continuous_pattern_and_saved_month_takes_precedence(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa', 'is_active' => true]);
        $pattern = new WorkPattern(['name' => 'Ciclo', 'is_active' => true]);
        $pattern->company_id = $user->company_id;
        $pattern->save();
        $version = $pattern->versions()->create(['company_id' => $user->company_id, 'kind' => 'cycle', 'sequence' => [true, true, false]]);
        EmployeeWorkPattern::create(['company_id' => $user->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2024-01-29', 'starts_on' => '2024-01-01']);
        $row = ['id' => $employee->public_id, 'name' => 'Pessoa', 'days' => array_fill(0, 31, 'off')];
        $this->actingAs($user)->post('/minhas-escalas', [...$this->payload([$row]), 'month' => '2024-01'])->assertRedirect();
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page
            ->where('reused.employees.0.days.0', 'work')->where('reused.employees.0.days.1', 'work')
            ->where('reused.employees.0.days.2', 'off')->where('reused.employees.0.edited.0', true));
        $this->post('/minhas-escalas', $this->payload([['id' => $employee->public_id, 'name' => 'Pessoa', 'days' => array_fill(0, 29, 'off')]]))->assertRedirect();
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('reused', null)->where('snapshot.employees.0.days.0', 'off')->where('revision', 1));
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_reuse_requires_immediately_previous_month_in_the_same_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post('/minhas-escalas', [...$this->payload([]), 'month' => '2023-12'])->assertRedirect();
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('reused', null));
        $this->actingAs($other)->get('/minhas-escalas?month=2024-01')->assertInertia(fn (Assert $page) => $page->where('reused', null));
    }

    public function test_month_is_protected_and_preview_does_not_persist(): void
    {
        $this->get('/minhas-escalas')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->component('ScheduleEditor/Index')->has('days', 29)->where('company', $user->company->name)->where('revision', 0));
        $this->assertDatabaseCount('monthly_schedules', 0);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_save_creates_employee_without_sector_and_reopens_exact_draft(): void
    {
        $user = User::factory()->create();
        $days = array_fill(0, 29, 'work');
        $days[3] = 'off';
        $data = $this->payload([['id' => null, 'name' => 'Ana fictícia', 'days' => $days]]);
        $this->actingAs($user)->post('/minhas-escalas', $data)->assertRedirect('/minhas-escalas?month=2024-02');
        $employee = $user->company->employees()->firstOrFail();
        $this->assertNull($employee->department_id);
        $this->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('revision', 1)->where('snapshot.employees.0.id', $employee->public_id)->where('snapshot.employees.0.days.3', 'off')->where('snapshot.days_off', [2, 6]));
        $this->post('/minhas-escalas', $data)->assertRedirect();
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('monthly_schedule_revisions', 1);
        $this->post('/minhas-escalas', [...$data, 'employees' => [['id' => null, 'name' => 'Alterado', 'days' => $days]]])->assertSessionHasErrors('operation_id');
    }

    public function test_conflicting_revision_and_foreign_employee_are_rejected_without_partial_changes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $other->company->employees()->create(['name' => 'Outro', 'is_active' => true]);
        $days = array_fill(0, 29, 'off');
        $this->actingAs($user)->post('/minhas-escalas', $this->payload([['id' => $foreign->public_id, 'name' => 'Outro', 'days' => $days]]))->assertSessionHasErrors('employees');
        $this->assertDatabaseCount('monthly_schedules', 0);
        $this->post('/minhas-escalas', $this->payload([['id' => null, 'name' => 'Pessoa', 'days' => $days]]))->assertRedirect();
        $this->post('/minhas-escalas', $this->payload([['id' => null, 'name' => 'Não criar', 'days' => $days]]))->assertSessionHasErrors('revision');
        $this->assertSame(1, $user->company->employees()->count());
        $this->assertDatabaseCount('monthly_schedule_revisions', 1);
        $this->actingAs($other)->get('/minhas-escalas?month=2024-02')->assertInertia(fn (Assert $page) => $page->where('revision', 0)->where('snapshot', null));
    }

    public function test_new_revision_preserves_history_and_removing_row_preserves_employee(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa', 'is_active' => true]);
        $days = array_fill(0, 29, 'work');
        $this->actingAs($user)->post('/minhas-escalas', $this->payload([['id' => $employee->public_id, 'name' => 'Ignorado', 'days' => $days]]))->assertRedirect();
        $this->post('/minhas-escalas', $this->payload([], 1))->assertRedirect();
        $this->assertDatabaseCount('monthly_schedule_revisions', 2);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('monthly_schedule_revision_employees', 1);
        $this->assertSame('Pessoa', json_decode(DB::table('monthly_schedule_revisions')->orderBy('id')->first()->snapshot, true)['employees'][0]['name']);
    }

    public function test_incomplete_days_are_rejected_atomically(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/minhas-escalas', $this->payload([['id' => null, 'name' => 'Pessoa', 'days' => ['work']]]))->assertSessionHasErrors('employees');
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('monthly_schedules', 0);
    }

    public function test_failure_while_saving_rolls_back_new_employees(): void
    {
        $user = User::factory()->create();
        DB::statement("CREATE TRIGGER fail_monthly_revision BEFORE INSERT ON monthly_schedule_revisions BEGIN SELECT RAISE(ABORT, 'failure'); END");
        try {
            $this->withoutExceptionHandling()->actingAs($user)->post('/minhas-escalas', $this->payload([['id' => null, 'name' => 'Pessoa', 'days' => array_fill(0, 29, 'work')]]));
            $this->fail('A falha deveria interromper o salvamento.');
        } catch (QueryException) {
            $this->assertDatabaseCount('employees', 0);
            $this->assertDatabaseCount('monthly_schedules', 0);
            $this->assertDatabaseCount('monthly_schedule_operations', 0);
        }
    }
}
