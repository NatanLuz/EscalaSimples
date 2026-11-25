<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HolidayController extends Controller
{
    private const STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Holiday::class);
        $items = Holiday::where('company_id', $request->user()->company_id)->orderByDesc('date')->orderBy('id')->paginate(20)
            ->through(fn ($item) => ['id' => $item->public_id, 'name' => $item->name, 'date' => $item->date, 'type' => $item->type, 'scope' => $item->scope, 'state' => $item->state, 'city' => $item->city, 'source' => $item->source, 'is_active' => $item->is_active]);

        return Inertia::render('Holidays/Index', ['items' => $items, 'flash' => session('success'), 'company' => $request->user()->company->only(['city', 'state'])]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Holiday::class);

        return $this->save($request, new Holiday);
    }

    public function location(Request $request)
    {
        Gate::authorize('viewAny', Holiday::class);
        $data = $request->validate(['city' => ['nullable', 'string', 'max:255'], 'state' => ['nullable', 'required_with:city', Rule::in(self::STATES)]]);
        $request->user()->company->update(['city' => $data['city'] ?? null, 'state' => $data['state'] ?? null]);

        return redirect()->route('holidays.index')->with('success', 'Localidade atualizada.');
    }

    public function update(Request $request, string $holiday)
    {
        $item = Holiday::where('company_id', $request->user()->company_id)->where('public_id', $holiday)->firstOrFail();
        Gate::authorize('update', $item);

        return $this->save($request, $item);
    }

    private function save(Request $request, Holiday $item)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['holiday', 'optional'])],
            'scope' => ['required', Rule::in(['national', 'state', 'municipal', 'company'])],
            'state' => ['nullable', 'required_if:scope,state,municipal', Rule::in(self::STATES)],
            'city' => ['nullable', 'required_if:scope,municipal', 'string', 'max:255'],
            'source' => ['nullable', 'url:http,https', 'max:255'], 'is_active' => ['required', 'boolean'],
        ]);
        $data['state'] = in_array($data['scope'], ['state', 'municipal'], true) ? strtoupper($data['state']) : null;
        $data['city'] = $data['scope'] === 'municipal' ? trim($data['city']) : null;
        $item->fill($data);
        $item->company_id = $request->user()->company_id;
        $item->save();

        return redirect()->route('holidays.index')->with('success', 'Data salva com sucesso.');
    }
}
