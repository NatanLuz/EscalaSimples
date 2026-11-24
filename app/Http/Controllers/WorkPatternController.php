<?php

namespace App\Http\Controllers;

use App\Models\WorkPattern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WorkPatternController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', WorkPattern::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $items = WorkPattern::query()->where('company_id', $request->user()->company_id)
            ->when(! empty($filters['search']), fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->with(['versions' => fn ($query) => $query->latest('id')])->orderBy('name')->paginate(15)->withQueryString()
            ->through(fn ($pattern) => ['id' => $pattern->public_id, 'name' => $pattern->name, 'is_active' => $pattern->is_active, 'version' => $pattern->versions->first()?->only(['kind', 'sequence', 'starts_at', 'ends_at'])]);

        return Inertia::render('WorkPatterns/Index', ['items' => $items, 'filters' => $filters, 'flash' => session('success')]);
    }

    public function create()
    {
        Gate::authorize('create', WorkPattern::class);

        return Inertia::render('WorkPatterns/Form', ['item' => null]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', WorkPattern::class);

        return $this->save($request, new WorkPattern);
    }

    public function edit(Request $request, string $pattern)
    {
        $item = WorkPattern::query()->where('company_id', $request->user()->company_id)->where('public_id', $pattern)->firstOrFail();
        Gate::authorize('update', $item);

        return Inertia::render('WorkPatterns/Form', ['item' => ['id' => $item->public_id, 'name' => $item->name, 'is_active' => $item->is_active, 'version' => $item->versions()->latest('id')->firstOrFail()->only(['kind', 'sequence', 'starts_at', 'ends_at'])]]);
    }

    public function update(Request $request, string $pattern)
    {
        $item = WorkPattern::query()->where('company_id', $request->user()->company_id)->where('public_id', $pattern)->firstOrFail();
        Gate::authorize('update', $item);

        return $this->save($request, $item);
    }

    private function save(Request $request, WorkPattern $item)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean'],
            'kind' => ['required', Rule::in(['weekly', 'cycle'])],
            'sequence' => ['required', 'array', 'min:1', 'max:366'], 'sequence.*' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date_format:H:i', 'required_with:ends_at'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_with:starts_at', 'different:starts_at'],
        ]);
        if ($data['kind'] === 'weekly' && count($data['sequence']) !== 7) {
            throw ValidationException::withMessages(['sequence' => 'Um padrão semanal deve conter sete dias.']);
        }
        if (! empty($data['starts_at']) && ! in_array(true, array_map('boolval', $data['sequence']), true)) {
            throw ValidationException::withMessages(['sequence' => 'Horários exigem pelo menos um dia de trabalho.']);
        }
        DB::transaction(function () use ($request, $item, $data) {
            if ($item->exists) {
                $item = WorkPattern::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            }
            $item->fill(['name' => trim($data['name']), 'is_active' => $data['is_active']]);
            $item->company_id = $request->user()->company_id;
            $item->save();
            $item->versions()->create(['company_id' => $item->company_id, 'kind' => $data['kind'], 'sequence' => array_values($data['sequence']), 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null]);
        });

        return redirect()->route('patterns.index')->with('success', 'Padrão salvo com sucesso. Atribuições anteriores mantêm sua versão.');
    }
}
