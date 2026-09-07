{{--
    resources/views/components/dashboard-layout.blade.php

    Shell used by every authenticated page:
        <x-dashboard-layout title="Admin Dashboard">
            ... page content ...
        </x-dashboard-layout>

    Only this file owns the <html>/<head>/sidebar/topbar markup — page
    views (resources/views/admin-pages/*.blade.php) only ever supply
    their own content through the default slot. This is the "Extract
    Class" boundary: page content and page chrome are separate concerns.
--}}


@props(['title' => 'AssocMap', 'topbarTitle' => null])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — AssocMap</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="am-body">

    <x-sidebar />

    <div class="am-main">
        <x-topbar :title="$topbarTitle ?? $title" :contextual="$topbarTitle !== null" />

        <main class="am-content">
            {{ $slot }}
        </main>
    </div>

</body>
</html>
