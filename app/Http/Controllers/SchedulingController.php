<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\ScheduleOverride;
use App\Models\WorkPattern;
use App\Services\AssignWorkPattern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);
        $companyId = $request->user()->company_id;
        $employees = Employee::where('company_id', $companyId)->orderBy('name')->get(['id', 'public_id', 'name', 'is_active']);
        $assignments = EmployeeWorkPattern::where('company_id', $companyId)->with('version.pattern')->orderByDesc('starts_on')->limit(100)->get();
        $patterns = WorkPattern::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['public_id', 'name']);

        return Inertia::render('WorkPatterns/Assignments', [
            'employees' => $employees->map(fn ($employee) => ['id' => $employee->public_id, 'name' => $employee->name, 'is_active' => $employee->is_active]),
            'patterns' => $patterns->map(fn ($pattern) => ['id' => $pattern->public_id, 'name' => $pattern->name]),
            'assignments' => $assignments->map(fn ($assignment) => ['id' => $assignment->public_id, 'employee' => $employees->firstWhere('id', $assignment->employee_id)?->name, 'pattern' => $assignment->version->pattern->name, 'starts_on' => $assignment->starts_on, 'ends_on' => $assignment->ends_on]),
            'flash' => session('success'),
        ]);
    }

    public function store(Request $request, AssignWorkPattern $service)
    {
        Gate::authorize('viewAny', Employee::class);
        $data = $request->validate([
            'employees' => ['required', 'array', 'min:1', 'max:1000'], 'employees.*' => ['required', 'ulid', 'distinct'],
            'pattern' => ['required', 'ulid'], 'reference_date' => ['required', 'date_format:Y-m-d'],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $service->assign($request->user()->company_id, $data['employees'], $data['pattern'], $data['reference_date'], $data['starts_on'], $data['ends_on'] ?? null);

        return back()->with('success', 'Atribuição registrada.');
    }

    public function end(Request $request, string $assignment)
    {
        $data = $request->validate(['ends_on' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($request, $assignment, $data) {
            $item = EmployeeWorkPattern::where('company_id', $request->user()->company_id)->where('public_id', $assignment)->firstOrFail();
            $employee = Employee::where('company_id', $request->user()->company_id)->whereKey($item->employee_id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $employee);
            $item = EmployeeWorkPattern::where('company_id', $request->user()->company_id)->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($data['ends_on'] < $item->starts_on || ($item->ends_on !== null && $data['ends_on'] > $item->ends_on)) {
                throw ValidationException::withMessages(['ends_on' => 'Data de encerramento inválida.']);
            }
            DB::table('assignment_changes')->insert([
                'company_id' => $item->company_id, 'user_id' => $request->user()->id,
                'employee_work_pattern_id' => $item->id, 'previous_starts_on' => $item->starts_on,
                'previous_ends_on' => $item->ends_on, 'new_starts_on' => $item->starts_on,
                'new_ends_on' => $data['ends_on'], 'reason' => trim($data['reason']), 'created_at' => now(),
            ]);
            $item->update(['ends_on' => $data['ends_on']]);
        }, attempts: 3);

        return back()->with('success', 'Vigência encerrada.');
    }

    public function override(Request $request)
    {
        $data = $request->validate(['employee' => ['required', 'ulid'], 'date' => ['required', 'date_format:Y-m-d'], 'is_work' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:255']]);
        $employee = Employee::where('company_id', $request->user()->company_id)->where('public_id', $data['employee'])->firstOrFail();
        Gate::authorize('update', $employee);
        DB::transaction(function () use ($employee, $data) {
            DB::table('companies')->where('id', $employee->company_id)->lockForUpdate()->first();
            if (DB::table('monthly_schedules')->where('company_id', $employee->company_id)->where('month', substr($data['date'], 0, 7))->exists()) {
                throw ValidationException::withMessages(['date' => 'Altere esta escala pelo editor mensal.']);
            }
            ScheduleOverride::create(['company_id' => $employee->company_id, 'employee_id' => $employee->id, 'date' => $data['date'], 'is_work' => $data['is_work'], 'reason' => trim($data['reason'])]);
        });

        return back()->with('success', 'Exceção registrada.');
    }
}
