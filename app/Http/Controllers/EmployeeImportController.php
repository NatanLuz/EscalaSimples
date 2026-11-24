<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeCsvImport;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeImportController extends Controller
{
    public function create()
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('Employees/Import', ['preview' => null]);
    }

    public function template()
    {
        Gate::authorize('create', Employee::class);

        return response("\xEF\xBB\xBFnome,setor\r\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="modelo-colaboradores.csv"']);
    }

    public function preview(Request $request, EmployeeCsvImport $import)
    {
        Gate::authorize('create', Employee::class);
        $request->validate(['file' => ['required', 'file', 'extensions:csv', 'max:'.config('employee_import.max_kilobytes')]]);
        $company = $request->user()->company;
        $result = $import->inspect($request->file('file'), $company);
        $token = null;
        if (! $result['errors']) {
            $token = (string) Str::ulid();
            DB::table('employee_imports')->insert(['public_id' => $token, 'company_id' => $company->id, 'file_hash' => hash_file('sha256', $request->file('file')->getRealPath()), 'expires_at' => now()->addMinutes(config('employee_import.expires_minutes')), 'created_at' => now(), 'updated_at' => now()]);
        }

        return Inertia::render('Employees/Import', ['preview' => [
            'token' => $token, 'total' => count($result['rows']), 'duplicates' => $result['duplicates'],
            'rows' => array_map(fn ($row) => ['line' => $row['line'], 'name' => $row['name'], 'department' => $row['department'], 'possible_duplicate' => $row['possible_duplicate']], array_slice($result['rows'], 0, config('employee_import.preview_rows'))),
            'errors' => array_slice($result['errors'], 0, config('employee_import.preview_rows')), 'error_count' => count($result['errors']),
        ]]);
    }

    public function confirm(Request $request, EmployeeCsvImport $import)
    {
        Gate::authorize('create', Employee::class);
        $data = $request->validate(['file' => ['required', 'file', 'extensions:csv', 'max:'.config('employee_import.max_kilobytes')], 'token' => ['required', 'string', 'ulid']]);
        $company = $request->user()->company;
        try {
            $count = DB::transaction(function () use ($request, $data, $company, $import) {
                $operation = DB::table('employee_imports')->where('company_id', $company->id)->where('public_id', $data['token'])->lockForUpdate()->first();
                if (! $operation || $operation->completed_at || now()->greaterThan($operation->expires_at) || ! hash_equals($operation->file_hash, hash_file('sha256', $request->file('file')->getRealPath()))) {
                    throw ValidationException::withMessages(['file' => 'Prévia expirada, já confirmada ou arquivo diferente. Faça uma nova prévia.']);
                }
                $result = $import->inspect($request->file('file'), $company, true);
                if ($result['errors']) {
                    throw ValidationException::withMessages(['file' => 'Os dados ou setores mudaram. Faça uma nova prévia.']);
                }
                $now = now();
                foreach (array_chunk($result['rows'], 100) as $chunk) {
                    DB::table('employees')->insert(array_map(fn ($row) => ['public_id' => (string) Str::ulid(), 'company_id' => $company->id, 'department_id' => $row['department_id'], 'name' => $row['name'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], $chunk));
                }
                DB::table('employee_imports')->where('id', $operation->id)->update(['completed_at' => $now, 'updated_at' => $now]);

                return count($result['rows']);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['file' => 'Não foi possível concluir a importação. Nenhum colaborador foi importado.']);
        }

        return redirect()->route('employees.index')->with('success', "$count colaboradores importados com sucesso.");
    }
}
