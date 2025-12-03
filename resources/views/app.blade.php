<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel Boilerplate') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        <!-- Scripts -->
        @if(app()->environment('production') && file_exists(public_path('build/manifest.json')))
            {{-- Forçar uso de assets compilados em produção --}}
            @php
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
                $cssEntry = $manifest['resources/css/app.css'] ?? null;
                $jsEntry = $manifest['resources/js/app.jsx'] ?? null;
            @endphp
            @if($cssEntry && isset($cssEntry['file']))
                <link rel="stylesheet" href="{{ asset('build/' . $cssEntry['file']) }}">
            @endif
            @if($jsEntry && isset($jsEntry['file']))
                <script type="module" src="{{ asset('build/' . $jsEntry['file']) }}"></script>
            @endif
        @else
            {{-- Modo desenvolvimento --}}
            @viteReactRefresh
            @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

