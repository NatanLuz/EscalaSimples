<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Department::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $query = $request->user()->company->departments();
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return Inertia::render('Departments/Index', ['items' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($item) => ['id' => $item->public_id, 'name' => $item->name, 'is_active' => $item->is_active]), 'filters' => $filters, 'flash' => session('success')]);
    }

    public function create()
    {
        Gate::authorize('create', Department::class);

        return Inertia::render('Departments/Form', ['item' => null]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Department::class);

        return $this->save($request, new Department);
    }

    public function edit(Request $request, string $department)
    {
        $item = $request->user()->company->departments()->where('public_id', $department)->firstOrFail();
        Gate::authorize('update', $item);

        return Inertia::render('Departments/Form', ['item' => ['id' => $item->public_id, 'name' => $item->name, 'is_active' => $item->is_active]]);
    }

    public function update(Request $request, string $department)
    {
        $item = $request->user()->company->departments()->where('public_id', $department)->firstOrFail();
        Gate::authorize('update', $item);

        return $this->save($request, $item);
    }

    private function save(Request $request, Department $item)
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => Department::normalizeName($request->input('name'))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean']], [
            'name.required' => 'Informe o nome do setor.',
            'name.string' => 'O nome do setor deve ser um texto.',
            'name.max' => 'O nome do setor deve ter no máximo 255 caracteres.',
            'is_active.required' => 'Informe a situação do setor.',
            'is_active.boolean' => 'Informe uma situação válida.',
        ]);
        $company = $request->user()->company;
        $key = Department::nameKey($data['name']);
        $duplicate = fn () => $company->departments()->where('name_key', $key)->when($item->exists, fn ($q) => $q->where('id', '!=', $item->id))->exists();
        if ($duplicate()) {
            throw ValidationException::withMessages(['name' => 'Já existe um setor com esse nome.']);
        }
        try {
            $item->fill($data);
            $company->departments()->save($item);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true) && $duplicate()) {
                throw ValidationException::withMessages(['name' => 'Já existe um setor com esse nome.']);
            }
            throw $exception;
        }

        return redirect()->route('departments.index')->with('success', 'Setor salvo com sucesso.');
    }
}
