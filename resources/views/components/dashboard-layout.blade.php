{{--
    Shared page shell for authenticated users.
    Loads the application assets and renders navigation around the page content.
    Each page supplies its content through the default slot.
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
<body class="am-body {{ session('auth_user.role_name') === 'Field Officer' ? 'am-officer' : '' }} {{ session('auth_user.role_name') === 'Association Member' ? 'am-member' : '' }}">

    <x-sidebar />

    <div class="am-main">
        <x-topbar :title="$topbarTitle ?? $title" :contextual="$topbarTitle !== null"
                  :workspace="session('auth_user.role_name') === 'Association Member' || (session('auth_user.role_name') === 'System Administrator' && in_array($title, ['Project Management', 'Training Management', 'Member Management', 'Association Management', 'Area Management', 'User Management'], true)) || (session('auth_user.role_name') === 'Field Officer' && in_array($title, ['My Associations', 'Projects', 'Training Records', 'Monitoring Module', 'My Reports', 'GIS Mapping', 'Members and Applications (read-only)'], true))" />

        <main class="am-content">
            {{ $slot }}
        </main>
    </div>

</body>
</html>
