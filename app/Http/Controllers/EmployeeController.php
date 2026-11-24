<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'department' => ['nullable', 'string', 'max:26']]);
        $company = $request->user()->company;
        $query = $company->employees()->with('department');
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if (! empty($filters['department'])) {
            $query->whereHas('department', fn ($q) => $q->where('company_id', $company->id)->where('public_id', $filters['department']));
        }

        return Inertia::render('Employees/Index', ['items' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($item) => ['id' => $item->public_id, 'name' => $item->name, 'is_active' => $item->is_active, 'department' => $item->department ? ['name' => $item->department->name, 'is_active' => $item->department->is_active] : null]), 'departments' => $this->departments($request), 'filters' => $filters, 'flash' => session('success')]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('Employees/Form', ['item' => null, 'departments' => $this->departments($request)]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Employee::class);

        return $this->save($request, new Employee);
    }

    public function edit(Request $request, string $employee)
    {
        $item = $request->user()->company->employees()->with('department')->where('public_id', $employee)->firstOrFail();
        Gate::authorize('update', $item);

        return Inertia::render('Employees/Form', ['item' => ['id' => $item->public_id, 'name' => $item->name, 'is_active' => $item->is_active, 'department' => $item->department?->public_id], 'departments' => $this->departments($request)]);
    }

    public function update(Request $request, string $employee)
    {
        $item = $request->user()->company->employees()->where('public_id', $employee)->firstOrFail();
        Gate::authorize('update', $item);

        return $this->save($request, $item);
    }

    private function save(Request $request, Employee $item)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean'], 'department' => ['nullable', 'string', 'max:26']], [
            'name.required' => 'Informe o nome do colaborador.',
            'name.string' => 'O nome do colaborador deve ser um texto.',
            'name.max' => 'O nome do colaborador deve ter no máximo 255 caracteres.',
            'is_active.required' => 'Informe a situação do colaborador.',
            'is_active.boolean' => 'Informe uma situação válida.',
            'department.string' => 'Selecione um setor válido.',
            'department.max' => 'Selecione um setor válido.',
        ]);
        $company = $request->user()->company;
        $department = ! empty($data['department']) ? $company->departments()->where('public_id', $data['department'])->first() : null;
        if (! empty($data['department']) && (! $department || (! $department->is_active && $department->id !== $item->department_id))) {
            throw ValidationException::withMessages(['department' => 'Selecione um setor ativo válido.']);
        }
        $item->fill(['name' => $data['name'], 'is_active' => $data['is_active']]);
        $item->department()->associate($department);
        $company->employees()->save($item);

        return redirect()->route('employees.index')->with('success', 'Colaborador salvo com sucesso.');
    }

    private function departments(Request $request): array
    {
        return $request->user()->company->departments()->orderBy('name')->get(['public_id', 'name', 'is_active'])->map(fn ($item) => ['id' => $item->public_id, 'name' => $item->name, 'is_active' => $item->is_active])->all();
    }
}
