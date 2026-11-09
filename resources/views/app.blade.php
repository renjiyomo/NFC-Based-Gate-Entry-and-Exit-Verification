<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B0D0E">
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" type="image/png" href="/images/nfcheck-logo.png">
        <script src="/theme-init.js"></script>
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        <x-inertia::head />
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
