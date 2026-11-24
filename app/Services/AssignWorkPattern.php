<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeWorkPattern;
use App\Models\WorkPattern;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssignWorkPattern
{
    public function assign(int $companyId, array $employeePublicIds, string $patternPublicId, string $referenceDate, string $startsOn, ?string $endsOn): void
    {
        DB::transaction(function () use ($companyId, $employeePublicIds, $patternPublicId, $referenceDate, $startsOn, $endsOn) {
            $employees = Employee::query()->where('company_id', $companyId)->whereIn('public_id', $employeePublicIds)
                ->orderBy('id')->lockForUpdate()->get();
            if ($employees->count() !== count(array_unique($employeePublicIds)) || $employees->contains(fn ($employee) => ! $employee->is_active)) {
                throw ValidationException::withMessages(['employees' => 'Selecione colaboradores ativos válidos.']);
            }
            $pattern = WorkPattern::query()->where('company_id', $companyId)->where('public_id', $patternPublicId)->lockForUpdate()->first();
            if (! $pattern || ! $pattern->is_active || ! ($version = $pattern->versions()->latest('id')->first())) {
                throw ValidationException::withMessages(['pattern' => 'Selecione um padrão ativo válido.']);
            }
            foreach ($employees as $employee) {
                Gate::authorize('update', $employee);
            }
            $conflict = EmployeeWorkPattern::query()->where('company_id', $companyId)->whereIn('employee_id', $employees->modelKeys())
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $startsOn))
                ->when($endsOn, fn ($query) => $query->where('starts_on', '<=', $endsOn))->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['employees' => 'Existe uma atribuição conflitante para um dos colaboradores.']);
            }
            $now = now();
            foreach ($employees->chunk(100) as $chunk) {
                DB::table('employee_work_patterns')->insert($chunk->map(fn ($employee) => [
                    'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'employee_id' => $employee->id,
                    'work_pattern_version_id' => $version->id, 'reference_date' => $referenceDate,
                    'starts_on' => $startsOn, 'ends_on' => $endsOn, 'created_at' => $now, 'updated_at' => $now,
                ])->all());
            }
        }, attempts: 3);
    }
}
