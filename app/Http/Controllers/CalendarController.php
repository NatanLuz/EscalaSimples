<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ScheduleOverride;
use App\Models\WorkPattern;
use App\Services\MonthlyScheduleReader;
use App\Services\ScheduleCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function __invoke(Request $request, ScheduleCalculator $calculator, MonthlyScheduleReader $reader)
    {
        Gate::authorize('viewAny', Employee::class);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:1900-01', 'before_or_equal:2100-12'],
            'search' => ['nullable', 'string', 'max:100'], 'department' => ['nullable', 'ulid'],
            'pattern' => ['nullable', 'ulid'], 'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'summary' => ['nullable', Rule::in(['page', 'filtered', 'company'])], 'summary_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'));
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $company = $request->user()->company;
        $monthlySnapshots = $reader->snapshots($company->id, [$start->subDay()->format('Y-m'), $start->format('Y-m')]);
        $query = $company->employees()->with('department');
        $query->when(! empty($filters['search']), fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            ->when(! empty($filters['status']), fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->when(! empty($filters['department']), fn ($q) => $q->whereHas('department', fn ($d) => $d->where('company_id', $company->id)->where('public_id', $filters['department'])))
            ->when(! empty($filters['pattern']), fn ($q) => $q->whereExists(function ($sub) use ($company, $filters, $start, $end) {
                $sub->selectRaw('1')->from('employee_work_patterns as ewp')
                    ->join('work_pattern_versions as wpv', 'wpv.id', '=', 'ewp.work_pattern_version_id')
                    ->join('work_patterns as wp', 'wp.id', '=', 'wpv.work_pattern_id')
                    ->whereColumn('ewp.employee_id', 'employees.id')->where('ewp.company_id', $company->id)
                    ->where('wp.company_id', $company->id)->where('wp.public_id', $filters['pattern'])
                    ->where('ewp.starts_on', '<=', $end->toDateString())
                    ->where(fn ($p) => $p->whereNull('ewp.ends_on')->orWhere('ewp.ends_on', '>=', $start->toDateString()));
            }));
        $summaryQuery = clone $query;
        $employees = $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $ids = $employees->getCollection()->modelKeys();
        $calculated = $ids ? $calculator->calculate($company->id, $ids, $start->subDay()->toDateString(), $end->toDateString()) : [];
        $calculated = $reader->apply($calculated, $employees->getCollection(), $monthlySnapshots);
        $assignments = DB::table('employee_work_patterns as ewp')->join('work_pattern_versions as wpv', 'wpv.id', '=', 'ewp.work_pattern_version_id')
            ->join('work_patterns as wp', 'wp.id', '=', 'wpv.work_pattern_id')
            ->where('ewp.company_id', $company->id)->where('wp.company_id', $company->id)->whereIn('ewp.employee_id', $ids)
            ->where('ewp.starts_on', '<=', $end->toDateString())->where(fn ($q) => $q->whereNull('ewp.ends_on')->orWhere('ewp.ends_on', '>=', $start->toDateString()))
            ->get(['ewp.employee_id', 'ewp.starts_on', 'ewp.ends_on', 'wp.name'])->groupBy('employee_id');
        $overrides = ScheduleOverride::where('company_id', $company->id)->whereIn('employee_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('id')->get()->groupBy('employee_id');
        $days = [];
        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $days[] = ['date' => $date->toDateString(), 'day' => $date->day, 'weekday' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'][$date->dayOfWeekIso - 1], 'weekend' => $date->isWeekend()];
        }
        $rows = $employees->getCollection()->map(function ($employee) use ($calculated, $assignments, $overrides, $days, $monthlySnapshots, $start) {
            $employeeAssignments = $assignments->get($employee->id, collect());
            $exceptions = $overrides->get($employee->id, collect())->keyBy('date');
            $cells = [];
            foreach ($days as $day) {
                $date = $day['date'];
                $cell = $calculated[$employee->id][$date];
                $previous = $calculated[$employee->id][CarbonImmutable::parse($date)->subDay()->toDateString()];
                $assignment = $employeeAssignments->first(fn ($a) => $a->starts_on <= $date && ($a->ends_on === null || $a->ends_on >= $date));
                $exception = $monthlySnapshots->has($start->format('Y-m')) ? null : $exceptions->get($date);
                $cells[] = [...$cell, 'pattern' => $assignment?->name, 'exception' => $exception ? ['is_work' => $exception->is_work, 'reason' => $exception->reason] : null,
                    'continuing' => $previous['status'] === 'work' && $previous['ends_next_day'] && $previous['ends_at'] !== '00:00'];
            }

            return ['id' => $employee->public_id, 'name' => $employee->name, 'department' => $employee->department?->name, 'is_active' => $employee->is_active, 'cells' => $cells];
        });
        $holidays = Holiday::where('company_id', $company->id)->where('is_active', true)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->filter(fn ($holiday) => ! in_array($holiday->scope, ['state', 'municipal'], true) || ($company->state && mb_strtoupper($company->state) === $holiday->state && ($holiday->scope !== 'municipal' || ($company->city && mb_strtolower(trim($company->city)) === mb_strtolower(trim($holiday->city))))))
            ->map(fn ($holiday) => ['date' => $holiday->date, 'name' => $holiday->name, 'type' => $holiday->type, 'scope' => $holiday->scope, 'source' => $holiday->source])->values()->all();
        foreach (config('national_holidays.dates') as $date => $name) {
            if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                $holidays[] = ['date' => $date, 'name' => $name, 'type' => 'holiday', 'scope' => 'national', 'source' => config('national_holidays.source')];
            }
        }
        $indicators = [];
        foreach ($days as $index => $day) {
            $totals = ['work' => 0, 'off' => 0, 'unscheduled' => 0, 'exceptions' => 0, 'continuing' => 0, 'coverage' => []];
            foreach ($rows as $row) {
                $cell = $row['cells'][$index];
                $totals[$cell['status']]++;
                $totals['exceptions'] += (int) ($cell['exception'] !== null);
                $totals['continuing'] += (int) $cell['continuing'];
                if ($cell['status'] === 'work' || $cell['continuing']) {
                    $sector = $row['department'] ?? 'Sem setor';
                    $totals['coverage'][$sector] = ($totals['coverage'][$sector] ?? 0) + 1;
                }
            }
            $indicators[$day['date']] = $totals;
        }

        $summaryDate = $filters['summary_date'] ?? $start->toDateString();
        if ($summaryDate < $start->toDateString() || $summaryDate > $end->toDateString()) {
            $summaryDate = $start->toDateString();
        }
        $scope = $filters['summary'] ?? 'page';
        $summary = $indicators[$summaryDate];
        if ($scope !== 'page') {
            $summaryEmployees = ($scope === 'company' ? $company->employees()->with('department') : $summaryQuery)->get(['id', 'public_id', 'department_id']);
            $summaryIds = $summaryEmployees->modelKeys();
            $before = CarbonImmutable::parse($summaryDate)->subDay()->toDateString();
            $summaryCalculated = $summaryIds ? $calculator->calculate($company->id, $summaryIds, $before, $summaryDate) : [];
            $summaryCalculated = $reader->apply($summaryCalculated, $summaryEmployees, $monthlySnapshots);
            $exceptionEmployees = ScheduleOverride::where('company_id', $company->id)->whereIn('employee_id', $summaryIds)->where('date', $summaryDate)->distinct()->pluck('employee_id')->flip();
            $summary = ['work' => 0, 'off' => 0, 'unscheduled' => 0, 'exceptions' => 0, 'continuing' => 0, 'coverage' => []];
            foreach ($summaryEmployees as $employee) {
                $cell = $summaryCalculated[$employee->id][$summaryDate];
                $previous = $summaryCalculated[$employee->id][$before];
                $continuing = $previous['status'] === 'work' && $previous['ends_next_day'] && $previous['ends_at'] !== '00:00';
                $summary[$cell['status']]++;
                $summary['exceptions'] += (int) (! $monthlySnapshots->has($start->format('Y-m')) && $exceptionEmployees->has($employee->id));
                $summary['continuing'] += (int) $continuing;
                if ($cell['status'] === 'work' || $continuing) {
                    $sector = $employee->department?->name ?? 'Sem setor';
                    $summary['coverage'][$sector] = ($summary['coverage'][$sector] ?? 0) + 1;
                }
            }
        }

        return Inertia::render('Calendar/Index', ['month' => $start->format('Y-m'), 'days' => $days, 'rows' => $rows, 'holidays' => $holidays, 'indicators' => $indicators,
            'filters' => $filters, 'pagination' => ['current' => $employees->currentPage(), 'last' => $employees->lastPage(), 'total' => $employees->total()],
            'summary' => ['scope' => $scope, 'date' => $summaryDate, 'totals' => $summary],
            'company_total' => $company->employees()->count(), 'departments' => $company->departments()->orderBy('name')->get(['public_id', 'name']),
            'patterns' => WorkPattern::where('company_id', $company->id)->orderBy('name')->get(['public_id', 'name']), 'monthly_saved' => $monthlySnapshots->has($start->format('Y-m')), 'flash' => session('success')]);
    }
}
