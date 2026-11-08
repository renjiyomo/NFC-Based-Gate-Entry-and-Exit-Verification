<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b1420">
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" type="image/png" href="/images/nfcheck-logo.png">
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        <x-inertia::head />
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
