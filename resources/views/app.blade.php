<!DOCTYPE html>
@php($assetVersion = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()))
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('build/app.css') }}?v={{ $assetVersion }}">
    <script type="module" src="{{ asset('build/app.js') }}?v={{ $assetVersion }}"></script>
    @inertiaHead
</head>
<body class="bg-slate-950 antialiased">
    @inertia
</body>
</html>
