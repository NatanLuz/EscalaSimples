<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeBulkController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);
        $data = $request->validate([
            'employees' => ['required', 'array', 'min:1', 'max:5000'],
            'employees.*' => ['required', 'string', 'ulid', 'distinct'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'department'])],
            'department' => ['required_if:action,department', 'nullable', 'string', 'ulid'],
        ]);
        $company = $request->user()->company;
        DB::transaction(function () use ($data, $company) {
            $employees = $company->employees()->whereIn('public_id', $data['employees'])->lockForUpdate()->get();
            if ($employees->count() !== count($data['employees'])) {
                throw ValidationException::withMessages(['employees' => 'Seleção inválida. Atualize a página e tente novamente.']);
            }
            foreach ($employees as $employee) {
                Gate::authorize('update', $employee);
            }
            $updates = ['is_active' => $data['action'] === 'activate'];
            if ($data['action'] === 'department') {
                $department = $company->departments()->where('public_id', $data['department'])->where('is_active', true)->lockForUpdate()->first();
                if (! $department) {
                    throw ValidationException::withMessages(['department' => 'Selecione um setor ativo válido.']);
                }
                $updates = ['department_id' => $department->id];
            }
            $company->employees()->whereIn('id', $employees->modelKeys())->update($updates);
        });

        return redirect()->route('employees.index')->with('success', 'Alterações em lote concluídas.');
    }
}
