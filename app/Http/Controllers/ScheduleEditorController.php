<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Services\ScheduleCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ScheduleEditorController extends Controller
{
    public function index(Request $request, ScheduleCalculator $calculator)
    {
        Gate::authorize('viewAny', Employee::class);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'after_or_equal:1900-01', 'before_or_equal:2100-12']]);
        $start = CarbonImmutable::createFromFormat('!Y-m', $data['month'] ?? now()->format('Y-m'));
        $end = $start->endOfMonth();
        $company = $request->user()->company;
        $schedule = DB::table('monthly_schedules')->where('company_id', $company->id)->where('month', $start->format('Y-m'))->first();
        $snapshot = $schedule ? DB::table('monthly_schedule_revisions')->where('monthly_schedule_id', $schedule->id)->where('revision', $schedule->revision)->value('snapshot') : null;
        $previous = null;
        if (! $schedule) {
            $previousSchedule = DB::table('monthly_schedules')->where('company_id', $company->id)->where('month', $start->subMonth()->format('Y-m'))->first();
            $previousSnapshot = $previousSchedule ? DB::table('monthly_schedule_revisions')->where('monthly_schedule_id', $previousSchedule->id)->where('revision', $previousSchedule->revision)->value('snapshot') : null;
            $previous = $previousSnapshot ? json_decode($previousSnapshot, true, flags: JSON_THROW_ON_ERROR) : null;
        }
        $employees = $company->employees()->where('is_active', true)->orderBy('name')->orderBy('id')->limit(1001)->get(['id', 'public_id', 'name']);
        $employeesTruncated = $employees->count() > 1000;
        $employees = $employees->take(1000);
        $previousIds = array_column($previous['employees'] ?? [], 'id');
        $carriedEmployees = $previousIds ? $company->employees()->where('is_active', true)->whereIn('public_id', $previousIds)->get(['id', 'public_id', 'name'])->keyBy('public_id') : collect();
        $calculationIds = array_values(array_unique(array_merge($employees->modelKeys(), $carriedEmployees->pluck('id')->all())));
        $calculated = $calculationIds ? $calculator->calculate($company->id, $calculationIds, $start->toDateString(), $end->toDateString()) : [];
        $overrides = DB::table('schedule_overrides')->where('company_id', $company->id)->whereIn('employee_id', $calculationIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('id')->get()->groupBy('employee_id');
        $existing = $employees->map(fn ($employee) => ['id' => $employee->public_id, 'name' => $employee->name, 'days' => array_values(array_map(fn ($cell) => $cell['status'], $calculated[$employee->id] ?? []))]);
        $holidays = Holiday::where('company_id', $company->id)->where('is_active', true)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->filter(fn ($holiday) => ! in_array($holiday->scope, ['state', 'municipal'], true) || ($company->state && mb_strtoupper($company->state) === $holiday->state && ($holiday->scope !== 'municipal' || ($company->city && mb_strtolower(trim($company->city)) === mb_strtolower(trim($holiday->city))))))
            ->map(fn ($holiday) => ['date' => $holiday->date, 'name' => $holiday->name])->values()->all();
        foreach (config('national_holidays.dates') as $date => $name) {
            if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                $holidays[] = ['date' => $date, 'name' => $name];
            }
        }
        $days = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'day' => $day->day, 'weekday' => $day->dayOfWeekIso - 1];
        }

        $reused = null;
        if ($previous !== null) {
            $rows = [];
            $daysOff = array_map('intval', $previous['days_off']);
            foreach ($previous['employees'] as $row) {
                $employee = $carriedEmployees->get($row['id']);
                if (! $employee) {
                    continue;
                }
                $cells = array_values($calculated[$employee->id]);
                $recurring = isset($row['recurring_days_off']) ? array_map('intval', $row['recurring_days_off']) : null;
                $values = [];
                $fixed = [];
                $exceptions = $overrides->get($employee->id, collect())->keyBy('date');
                foreach ($days as $index => $day) {
                    $status = $cells[$index]['status'];
                    $exception = $exceptions->get($day['date']);
                    $values[] = $exception ? ($exception->is_work ? 'work' : 'off') : ($recurring !== null ? $calculator->weeklyStatus(CarbonImmutable::parse($day['date']), $recurring) : ($status === 'unscheduled' ? $calculator->weeklyStatus(CarbonImmutable::parse($day['date']), $daysOff) : $status));
                    $fixed[] = $exception !== null || ($recurring === null && $status !== 'unscheduled');
                }
                $rows[] = ['id' => $employee->public_id, 'name' => $employee->name, 'days' => $values, 'edited' => $fixed, 'recurring_days_off' => $recurring];
            }
            $reused = ['model' => $previous['model'], 'days_off' => $daysOff, 'employees' => $rows, 'from' => $start->subMonth()->format('Y-m')];
        }

        return Inertia::render('ScheduleEditor/Index', ['company' => $company->name, 'month' => $start->format('Y-m'), 'days' => $days, 'employees' => $existing, 'employees_truncated' => $employeesTruncated, 'revision' => $schedule?->revision ?? 0, 'snapshot' => $snapshot ? json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR) : null, 'reused' => $reused, 'holidays' => $holidays, 'operation_id' => (string) Str::ulid(), 'flash' => session('success')]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Employee::class);
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:1900-01', 'before_or_equal:2100-12'],
            'revision' => ['required', 'integer', 'min:0'], 'operation_id' => ['required', 'ulid'],
            'model' => ['required', Rule::in(['6x1', 'custom'])], 'days_off' => ['present', 'array', 'max:7'],
            'days_off.*' => ['required', 'integer', 'between:0,6', 'distinct'],
            'employees' => ['present', 'array', 'max:1000'], 'employees.*.id' => ['nullable', 'ulid', 'distinct'],
            'employees.*.name' => ['required', 'string', 'max:255'], 'employees.*.days' => ['required', 'array'],
            'employees.*.days.*' => ['required', Rule::in(['work', 'off'])],
            'employees.*.edited' => ['sometimes', 'array'], 'employees.*.edited.*' => ['boolean'],
            'employees.*.recurring_days_off' => ['nullable', 'array', 'max:7'],
            'employees.*.recurring_days_off.*' => ['integer', 'between:0,6', 'distinct'],
        ]);
        $dayCount = CarbonImmutable::createFromFormat('!Y-m', $data['month'])->daysInMonth;
        foreach ($data['employees'] as $row) {
            if (count($row['days']) !== $dayCount || (isset($row['edited']) && count($row['edited']) !== $dayCount) || trim($row['name']) === '') {
                throw ValidationException::withMessages(['employees' => 'Confira os colaboradores e os dias do mês.']);
            }
        }
        $companyId = $request->user()->company_id;
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        DB::transaction(function () use ($data, $companyId, $request, $hash, $dayCount) {
            DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            $operation = DB::table('monthly_schedule_operations')->where('company_id', $companyId)->where('operation_id', $data['operation_id'])->first();
            if ($operation) {
                if (! hash_equals($operation->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['operation_id' => 'Esta operação já foi utilizada com outros dados.']);
                }

                return;
            }
            $schedule = DB::table('monthly_schedules')->where('company_id', $companyId)->where('month', $data['month'])->first();
            if ((int) ($schedule->revision ?? 0) !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Esta escala foi alterada em outra sessão. Recarregue antes de salvar.']);
            }
            $ids = array_values(array_filter(array_column($data['employees'], 'id')));
            $existing = Employee::where('company_id', $companyId)->whereIn('public_id', $ids)->get()->keyBy('public_id');
            if ($existing->count() !== count($ids)) {
                throw ValidationException::withMessages(['employees' => 'Selecione colaboradores válidos da sua empresa.']);
            }
            $rows = [];
            $employeeIds = [];
            foreach ($data['employees'] as $row) {
                $employee = ! empty($row['id']) ? $existing[$row['id']] : null;
                if (! $employee) {
                    $employee = new Employee(['name' => trim($row['name']), 'is_active' => true]);
                    $employee->company_id = $companyId;
                    $employee->save();
                }
                $rows[] = ['id' => $employee->public_id, 'name' => $employee->name, 'days' => array_values($row['days']), 'edited' => array_values($row['edited'] ?? array_fill(0, $dayCount, true)), 'recurring_days_off' => isset($row['recurring_days_off']) ? array_map('intval', $row['recurring_days_off']) : null];
                $employeeIds[] = $employee->id;
            }
            $revision = (int) ($schedule->revision ?? 0) + 1;
            $scheduleId = $schedule->id ?? DB::table('monthly_schedules')->insertGetId(['company_id' => $companyId, 'month' => $data['month'], 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $revisionId = DB::table('monthly_schedule_revisions')->insertGetId(['monthly_schedule_id' => $scheduleId, 'revision' => $revision, 'snapshot' => json_encode(['model' => $data['model'], 'days_off' => array_values($data['days_off']), 'employees' => $rows], JSON_THROW_ON_ERROR), 'user_id' => $request->user()->id, 'created_at' => now()]);
            foreach (array_chunk($employeeIds, 100) as $chunk) {
                DB::table('monthly_schedule_revision_employees')->insert(array_map(fn ($id) => ['monthly_schedule_revision_id' => $revisionId, 'company_id' => $companyId, 'employee_id' => $id], $chunk));
            }
            DB::table('monthly_schedules')->where('id', $scheduleId)->update(['revision' => $revision, 'updated_at' => now()]);
            DB::table('monthly_schedule_operations')->insert(['company_id' => $companyId, 'operation_id' => $data['operation_id'], 'payload_hash' => $hash, 'month' => $data['month'], 'revision' => $revision, 'created_at' => now()]);
        }, attempts: 3);

        return redirect()->route('schedule.editor', ['month' => $data['month']])->with('success', 'Escala salva com sucesso.');
    }
}
