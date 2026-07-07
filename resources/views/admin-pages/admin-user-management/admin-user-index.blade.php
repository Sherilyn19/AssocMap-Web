{{--
    resources/views/admin-pages/admin-user-management/admin-user-index.blade.php
    User and Access Control Module - System Administrator view only.
--}}

@php
    $avatarPalette = ['bg-blue-600', 'bg-emerald-600', 'bg-purple-600', 'bg-amber-600', 'bg-rose-600', 'bg-cyan-600'];
    $avatarColor = fn ($name) => $avatarPalette[crc32($name) % count($avatarPalette)];
    $roleBadgeClass = fn ($role) => match ($role) {
        'System Administrator' => 'bg-green-100 text-green-700',
        'Field Officer'        => 'bg-blue-100 text-blue-700',
        'Association Member'   => 'bg-purple-100 text-purple-700',
        default                => 'bg-gray-100 text-gray-700',
    };

    // Resolved here only to build the clickable-card query strings below.
    $adminRoleId   = optional($roles->firstWhere('role_name', 'System Administrator'))->id;
    $officerRoleId = optional($roles->firstWhere('role_name', 'Field Officer'))->id;
    $memberRoleId  = optional($roles->firstWhere('role_name', 'Association Member'))->id;
@endphp

<x-dashboard-layout title="User Management">

    @if (session('success'))
        <div id="am-toast" class="fixed top-5 right-5 z-[60] rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
            {{ session('success') }}
        </div>
    @elseif (session('error'))
        <div id="am-toast" class="fixed top-5 right-5 z-[60] rounded-lg bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-6">
        <h2 class="text-xl font-bold text-assocmap-text">User Management</h2>
        <p class="text-sm text-assocmap-secondary">Manage authorized AssocMap accounts and role-based system access.</p>
    </div>

    {{-- Summary cards - each is a real link, filters the table below --}}
    <div class="grid grid-cols-2 gap-3 mb-6 sm:grid-cols-3 lg:grid-cols-5">
        <a href="{{ route('users.index') }}"
           class="rounded-xl border border-assocmap-border bg-white p-3.5 shadow-card transition hover:border-assocmap-primary hover:shadow-md">
            <p class="text-xs font-medium text-assocmap-secondary">Total Users</p>
            <p class="mt-0.5 text-xl font-bold text-assocmap-text">{{ $summary['total'] }}</p>
        </a>
        <a href="{{ route('users.index', ['role_id' => $adminRoleId]) }}"
           class="rounded-xl border border-assocmap-border bg-white p-3.5 shadow-card transition hover:border-green-400 hover:shadow-md">
            <p class="text-xs font-medium text-assocmap-secondary">System Admins</p>
            <p class="mt-0.5 text-xl font-bold text-green-700">{{ $summary['admins'] }}</p>
        </a>
        <a href="{{ route('users.index', ['role_id' => $officerRoleId]) }}"
           class="rounded-xl border border-assocmap-border bg-white p-3.5 shadow-card transition hover:border-blue-400 hover:shadow-md">
            <p class="text-xs font-medium text-assocmap-secondary">Field Officers</p>
            <p class="mt-0.5 text-xl font-bold text-blue-700">{{ $summary['field_officers'] }}</p>
        </a>
        <a href="{{ route('users.index', ['role_id' => $memberRoleId]) }}"
           class="rounded-xl border border-assocmap-border bg-white p-3.5 shadow-card transition hover:border-purple-400 hover:shadow-md">
            <p class="text-xs font-medium text-assocmap-secondary">Assoc. Members</p>
            <p class="mt-0.5 text-xl font-bold text-purple-700">{{ $summary['members'] }}</p>
        </a>
        <a href="{{ route('users.index', ['status' => 'inactive']) }}"
           class="rounded-xl border border-assocmap-border bg-white p-3.5 shadow-card transition hover:border-red-400 hover:shadow-md">
            <p class="text-xs font-medium text-assocmap-secondary">Inactive</p>
            <p class="mt-0.5 text-xl font-bold text-red-600">{{ $summary['inactive'] }}</p>
        </a>
    </div>

    {{-- Toolbar: filters row, then Apply/Reset/Add User together --}}
    <form method="GET" action="{{ route('users.index') }}"
          class="mb-6 rounded-xl border border-assocmap-border bg-white p-4 shadow-card">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="text-xs font-medium text-assocmap-secondary">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email..."
                       class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
            </div>
            <div>
                <label class="text-xs font-medium text-assocmap-secondary">Role</label>
                <select name="role_id" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? '') == $role->id)>{{ $role->role_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-assocmap-secondary">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-assocmap-secondary">Sort By</label>
                <select name="sort" class="mt-1 w-full rounded-lg border border-assocmap-border px-3 py-2 text-sm">
                    <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Name</option>
                    <option value="email" @selected(($filters['sort'] ?? '') === 'email')>Email</option>
                    <option value="role_name" @selected(($filters['sort'] ?? '') === 'role_name')>Role</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Date Created</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-assocmap-border pt-3">
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-assocmap-primary px-4 py-2 text-sm font-semibold text-white hover:bg-assocmap-hover">
                    Apply
                </button>
                <a href="{{ route('users.index') }}" class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                    Reset
                </a>
            </div>
            <button type="button" data-admin-user-modal-open="create"
                    class="rounded-lg bg-assocmap-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-assocmap-hover">
                + Add User
            </button>
        </div>
    </form>

    {{-- Desktop table --}}
    <div class="hidden overflow-x-auto rounded-xl border border-assocmap-border bg-white shadow-card md:block">
        <table class="min-w-full divide-y divide-assocmap-border text-sm">
            <thead class="bg-assocmap-bg">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Email</th>
                    <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Role</th>
                    <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Last Login</th>
                    <th class="px-4 py-3 text-left font-semibold text-assocmap-text">Created</th>
                    <th class="px-4 py-3 text-right font-semibold text-assocmap-text">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-assocmap-border">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $avatarColor($user->name) }}">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </span>
                                <p class="truncate font-medium text-assocmap-text">{{ $user->name }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-assocmap-secondary">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $roleBadgeClass($user->role_name) }}">
                                {{ strtoupper($user->role_name) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-assocmap-secondary">
                            {{ $user->last_login ? \Carbon\Carbon::parse($user->last_login)->diffForHumans() : 'Never Logged In' }}
                        </td>
                        <td class="px-4 py-3 text-assocmap-secondary">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            {{-- Dropdown menu: only Edit / Deactivate exist as real routes.
                                 Reset Password / View Activity intentionally left out until
                                 those endpoints are actually built. --}}
                            <div class="relative inline-block text-left" data-am-dropdown>
                                <button type="button" data-am-dropdown-toggle
                                        class="rounded-md border border-assocmap-border p-1.5 text-assocmap-text hover:bg-assocmap-bg">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                                    </svg>
                                </button>

                                <div data-am-dropdown-menu
                                     class="absolute right-0 z-10 mt-1 hidden w-40 rounded-lg border border-assocmap-border bg-white py-1 shadow-lg">
                                    <button type="button"
                                            data-admin-user-modal-open="edit"
                                            data-user='@json($user)'
                                            class="block w-full px-3 py-2 text-left text-xs font-medium text-assocmap-text hover:bg-assocmap-bg">
                                        Edit
                                    </button>

                                    <form id="toggle-active-form-{{ $user->id }}" action="{{ route('users.toggle-active', $user->id) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('PATCH')
                                    </form>
                                    <button type="button"
                                            data-confirm-open
                                            data-confirm-target="toggle-active-form-{{ $user->id }}"
                                            data-confirm-title="{{ $user->is_active ? 'Deactivate User?' : 'Reactivate User?' }}"
                                            data-confirm-message="{{ $user->is_active ? 'This user will no longer be able to access AssocMap.' : 'This user will be able to log in again.' }}"
                                            data-confirm-label="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}"
                                            class="block w-full px-3 py-2 text-left text-xs font-medium {{ $user->is_active ? 'text-red-600' : 'text-green-600' }} hover:bg-assocmap-bg">
                                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-assocmap-secondary">
                            No users found. Try adjusting your filters or create a new account.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile card fallback --}}
    <div class="space-y-3 md:hidden">
        @forelse ($users as $user)
            <div class="rounded-xl border border-assocmap-border bg-white p-4 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $avatarColor($user->name) }}">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-assocmap-text">{{ $user->name }}</p>
                        <p class="truncate text-xs text-assocmap-secondary">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $roleBadgeClass($user->role_name) }}">
                        {{ strtoupper($user->role_name) }}
                    </span>
                    @if ($user->is_active)
                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Active</span>
                    @else
                        <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Inactive</span>
                    @endif
                </div>
                <p class="mt-2 text-xs text-assocmap-secondary">
                    Last login: {{ $user->last_login ? \Carbon\Carbon::parse($user->last_login)->diffForHumans() : 'Never Logged In' }}
                </p>
                <div class="mt-3 flex gap-2">
                    <button type="button" data-admin-user-modal-open="edit" data-user='@json($user)'
                            class="flex-1 rounded-md border border-assocmap-border px-3 py-1.5 text-xs font-semibold text-assocmap-text">
                        Edit
                    </button>
                    <form id="toggle-active-form-m-{{ $user->id }}" action="{{ route('users.toggle-active', $user->id) }}" method="POST" class="hidden">
                        @csrf
                        @method('PATCH')
                    </form>
                    <button type="button"
                            data-confirm-open
                            data-confirm-target="toggle-active-form-m-{{ $user->id }}"
                            data-confirm-title="{{ $user->is_active ? 'Deactivate User?' : 'Reactivate User?' }}"
                            data-confirm-message="{{ $user->is_active ? 'This user will no longer be able to access AssocMap.' : 'This user will be able to log in again.' }}"
                            data-confirm-label="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}"
                            class="flex-1 rounded-md border border-assocmap-border px-3 py-1.5 text-xs font-semibold {{ $user->is_active ? 'text-red-600' : 'text-green-600' }}">
                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                    </button>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-assocmap-border bg-white p-6 text-center text-sm text-assocmap-secondary">
                No users found.
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

    {{-- Add/Edit modal --}}
    <div id="admin-user-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-card">
            <h3 id="admin-user-modal-title" class="mb-4 text-lg font-bold text-assocmap-text">Add User</h3>

            <form id="admin-user-form" method="POST" action="{{ route('users.store') }}">
                @csrf
                <input type="hidden" id="admin-user-form-method" name="_method" value="POST">

                <div class="space-y-6">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-assocmap-secondary">Basic Information</p>
                        <div class="space-y-3">
                            <x-text-input id="admin-user-name" name="name" label="Full Name" required />
                            <x-text-input id="admin-user-email" name="email" type="email" label="Email Address" required />
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-assocmap-secondary">Security</p>
                        <div class="flex flex-col gap-1.5">
                            <label for="admin-user-password" class="text-sm font-medium text-assocmap-text">Password</label>
                            <div class="relative">
                                <input id="admin-user-password" name="password" type="password"
                                       placeholder="Leave blank to keep current password"
                                       class="w-full rounded-lg border border-assocmap-border px-4 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                                <button type="button" data-toggle-password="admin-user-password"
                                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-assocmap-secondary">
                                    <svg data-eye-icon="open" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <svg data-eye-icon="closed" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M6.5 6.7C4 8.3 1 12 1 12s4 7 11 7c2 0 3.8-.5 5.3-1.2M17.9 17.9C20.4 16 23 12 23 12s-1.5-2.6-4-4.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-assocmap-secondary">Permissions</p>
                        <label for="admin-user-role" class="text-sm font-medium text-assocmap-text">Role</label>
                        <select id="admin-user-role" name="role_id" required
                                class="mt-1.5 w-full rounded-lg border border-assocmap-border px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-assocmap-primary">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                        <p id="admin-user-role-hint" class="mt-1 hidden text-xs text-amber-600">
                            This is the last active System Administrator. The system will block any change that leaves zero admins.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" data-admin-user-modal-close
                            class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                        Cancel
                    </button>
                    <x-primary-button type="submit" class="w-auto px-6">Save</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Generic confirm dialog --}}
    <div id="am-confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-card">
            <h3 id="am-confirm-title" class="text-lg font-bold text-assocmap-text">Are you sure?</h3>
            <p id="am-confirm-message" class="mt-2 text-sm text-assocmap-secondary"></p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-confirm-close
                        class="rounded-lg border border-assocmap-border px-4 py-2 text-sm font-semibold text-assocmap-text hover:bg-assocmap-bg">
                    Cancel
                </button>
                <button type="button" id="am-confirm-action-btn"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Confirm
                </button>
            </div>
        </div>
    </div>

</x-dashboard-layout>