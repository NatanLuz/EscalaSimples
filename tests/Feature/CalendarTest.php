<?php

namespace Tests\Feature;

use App\Models\EmployeeWorkPattern;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function pattern(User $user, string $name, array $sequence, ?string $start = null, ?string $end = null): WorkPattern
    {
        $pattern = new WorkPattern(['name' => $name, 'is_active' => true]);
        $pattern->company_id = $user->company_id;
        $pattern->save();
        $pattern->versions()->create(['company_id' => $user->company_id, 'kind' => 'cycle', 'sequence' => $sequence, 'starts_at' => $start, 'ends_at' => $end]);

        return $pattern;
    }

    private function assign(User $user, $employee, WorkPattern $pattern, string $start, ?string $end = null): void
    {
        EmployeeWorkPattern::create(['company_id' => $user->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $pattern->versions()->latest('id')->firstOrFail()->id, 'reference_date' => $start, 'starts_on' => $start, 'ends_on' => $end]);
    }

    public function test_guests_are_redirected_and_month_lengths_are_correct(): void
    {
        $this->get('/escalas')->assertRedirect('/login');
        $this->get('/feriados')->assertRedirect('/login');
        $user = User::factory()->create();
        foreach (['2023-02' => 28, '2024-02' => 29, '2024-04' => 30, '2024-12' => 31] as $month => $days) {
            $this->actingAs($user)->get('/escalas?month='.$month)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Calendar/Index')->where('month', $month)->has('days', $days));
        }
        $this->get('/escalas?month=2024-13')->assertSessionHasErrors('month');
    }

    public function test_calculation_exceptions_holidays_and_overnight_coverage_are_preserved(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Ana fictícia', 'is_active' => true]);
        $otherEmployee = $user->company->employees()->create(['name' => 'Zilda fictícia', 'is_active' => true]);
        $pattern = $this->pattern($user, 'Noturno', [true, false], '19:00', '07:00');
        $this->assign($user, $employee, $pattern, '2026-03-31');
        $this->assign($user, $otherEmployee, $pattern, '2026-03-31');
        $this->actingAs($user)->post('/excecoes', ['employee' => $employee->public_id, 'date' => '2026-04-03', 'is_work' => true, 'reason' => 'Ajuste fictício'])->assertRedirect();
        $this->get('/escalas?month=2026-04')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells.0.status', 'off')->where('rows.0.cells.0.continuing', true)
            ->where('indicators.2026-04-01.coverage.Sem setor', 2)->where('rows.0.cells.2.status', 'work')->where('rows.1.cells.2.status', 'off')
            ->where('rows.0.cells.2.exception.reason', 'Ajuste fictício')->where('indicators.2026-04-03.exceptions', 1)
            ->has('holidays', 2));
    }

    public function test_filter_by_pattern_includes_transitions_and_pages_are_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa transição', 'is_active' => true]);
        $other->company->employees()->create(['name' => 'Pessoa externa secreta', 'is_active' => true]);
        $first = $this->pattern($user, 'Primeiro', [true]);
        $second = $this->pattern($user, 'Segundo', [false]);
        $this->assign($user, $employee, $first, '2026-04-01', '2026-04-15');
        $this->assign($user, $employee, $second, '2026-04-16');
        $this->actingAs($user)->get('/escalas?month=2026-04&pattern='.$second->public_id)->assertInertia(fn (Assert $page) => $page->where('pagination.total', 1)->where('rows.0.cells.14.pattern', 'Primeiro')->where('rows.0.cells.15.pattern', 'Segundo'));
        for ($i = 0; $i < 21; $i++) {
            $user->company->employees()->create(['name' => 'Colaborador '.$i, 'is_active' => true]);
        }
        $this->get('/escalas?month=2026-04')->assertInertia(fn (Assert $page) => $page->has('rows', 20)->where('pagination.total', 22)->where('company_total', 22))->assertDontSee('Pessoa externa secreta');
        $this->get('/escalas?month=2026-04&search=transição')->assertInertia(fn (Assert $page) => $page->where('pagination.total', 1));
        $this->get('/escalas?month=2026-04&status=inactive')->assertInertia(fn (Assert $page) => $page->where('pagination.total', 0));
        $this->get('/escalas?month=2026-04&search=transição&summary=filtered&summary_date=2026-04-16')->assertInertia(fn (Assert $page) => $page->where('summary.scope', 'filtered')->where('summary.totals.off', 1)->where('summary.totals.unscheduled', 0));
        $this->get('/escalas?month=2026-04&search=transição&summary=company&summary_date=2026-04-16')->assertInertia(fn (Assert $page) => $page->where('summary.scope', 'company')->where('summary.totals.off', 1)->where('summary.totals.unscheduled', 21));
    }

    public function test_holidays_are_company_scoped_and_locality_is_respected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->company->update(['city' => 'Curitiba', 'state' => 'PR']);
        $this->actingAs($user)->post('/feriados', ['name' => 'Data municipal', 'date' => '2026-04-05', 'type' => 'optional', 'scope' => 'municipal', 'state' => 'PR', 'city' => 'Curitiba', 'is_active' => true, 'company_id' => $other->company_id])->assertRedirect('/feriados');
        $holiday = Holiday::firstOrFail();
        $this->assertSame($user->company_id, $holiday->company_id);
        $this->post('/feriados', ['name' => 'Outra cidade', 'date' => '2026-04-06', 'type' => 'holiday', 'scope' => 'municipal', 'state' => 'PR', 'city' => 'Londrina', 'is_active' => true])->assertRedirect();
        $this->actingAs($other)->put('/feriados/'.$holiday->public_id, ['name' => 'Ataque'])->assertNotFound();
        $this->actingAs($user)->get('/escalas?month=2026-04')->assertSee('Data municipal')->assertDontSee('Outra cidade');
        $this->put('/feriados/'.$holiday->public_id, ['name' => 'Data municipal', 'date' => '2026-04-05', 'type' => 'optional', 'scope' => 'municipal', 'state' => 'PR', 'city' => 'Curitiba', 'is_active' => false])->assertRedirect();
        $this->get('/escalas?month=2026-04')->assertDontSee('Data municipal');
    }

    public function test_location_is_derived_from_the_authenticated_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $originalCity = $other->company->city;
        $this->actingAs($user)->put('/feriados/localidade', ['city' => 'Curitiba', 'state' => 'PR', 'company_id' => $other->company_id])->assertRedirect('/feriados');
        $this->assertSame('Curitiba', $user->company->fresh()->city);
        $this->assertSame($originalCity, $other->company->fresh()->city);
        $this->put('/feriados/localidade', ['city' => 'Teste', 'state' => 'ZZ'])->assertSessionHasErrors('state');
    }

    public function test_filters_and_cross_company_identifiers_are_not_trusted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $department = $user->company->departments()->create(['name' => 'Setor fictício', 'is_active' => true]);
        $foreignDepartment = $other->company->departments()->create(['name' => 'Setor externo', 'is_active' => true]);
        $employee = $user->company->employees()->create(['name' => 'Colaborador filtrado', 'is_active' => true]);
        $employee->department()->associate($department);
        $employee->save();
        $foreignEmployee = $other->company->employees()->create(['name' => 'Colaborador externo', 'is_active' => true]);
        $foreignPattern = $this->pattern($other, 'Padrão externo', [true]);
        $this->actingAs($user)->get('/escalas?month=2026-12&department='.$department->public_id)->assertInertia(fn (Assert $page) => $page->where('pagination.total', 1)->where('month', '2026-12'));
        $this->get('/escalas?month=2027-01&department='.$foreignDepartment->public_id)->assertInertia(fn (Assert $page) => $page->where('pagination.total', 0)->where('month', '2027-01'));
        $this->get('/escalas?month=2026-12&pattern='.$foreignPattern->public_id)->assertInertia(fn (Assert $page) => $page->where('pagination.total', 0));
        $this->post('/excecoes', ['employee' => $foreignEmployee->public_id, 'date' => '2026-12-01', 'is_work' => false, 'reason' => 'Teste'])->assertNotFound();
    }

    public function test_shift_ending_at_midnight_does_not_count_as_coverage_on_following_day(): void
    {
        $user = User::factory()->create();
        $employee = $user->company->employees()->create(['name' => 'Pessoa fictícia', 'is_active' => true]);
        $pattern = $this->pattern($user, 'Até meia-noite', [true, false], '19:00', '00:00');
        $this->assign($user, $employee, $pattern, '2026-03-31');
        $this->actingAs($user)->get('/escalas?month=2026-04')->assertInertia(fn (Assert $page) => $page->where('rows.0.cells.0.status', 'off')->where('rows.0.cells.0.continuing', false)->where('indicators.2026-04-01.continuing', 0));
    }
}
