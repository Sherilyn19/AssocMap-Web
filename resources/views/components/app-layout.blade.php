{{--
    Component : app-layout
    Purpose   : Root HTML shell. Loads Inter font, Vite assets,
                CSRF meta tag, and renders $slot.
    Props     : $title — page title (optional, defaults to "AssocMAP")
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ $title ?? 'AssocMAP' }} — DA-BFAR Region VII</title>

    {{-- Inter font from Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

    {{-- Vite-compiled Tailwind CSS + JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-inter antialiased bg-assocmap-bg text-assocmap-text">
    {{ $slot }}
</body>
</html>
