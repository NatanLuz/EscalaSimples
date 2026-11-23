<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        $files = [public_path('build/app.js'), public_path('build/app.css')];

        return hash('sha256', implode('|', array_map(
            fn ($file) => is_file($file) ? hash_file('sha256', $file) : '',
            $files
        )));
    }

    public function share(Request $request): array
    {
        return parent::share($request);
    }
}
