<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateCompanyWithAdministrator
{
    /**
     * Recebe dados previamente validados no backend.
     */
    public function execute(array $companyData, array $administratorData): Company
    {
        return DB::transaction(function () use ($companyData, $administratorData): Company {
            $company = Company::create(Arr::only($companyData, ['name', 'city', 'state']));
            $company->user()->create(Arr::only($administratorData, ['name', 'email', 'password']));

            return $company->load('user');
        });
    }
}
