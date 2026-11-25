<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCompanyRequest;
use App\Models\User;
use App\Services\CreateCompanyWithAdministrator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterCompanyRequest $request, CreateCompanyWithAdministrator $service): RedirectResponse
    {
        $data = $request->validated();

        try {
            $company = $service->execute(
                ['name' => $data['company_name']],
                ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]
            );
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true) && User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'Este e-mail já está cadastrado.']);
            }

            throw $exception;
        }

        Auth::login($company->user);
        $request->session()->regenerate();

        return redirect()->route('schedule.editor');
    }
}
