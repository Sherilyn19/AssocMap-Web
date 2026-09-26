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

<x-dashboard-layout title="User Management" topbar-title="User Management">
<div data-user-management-page data-management-register class="min-w-0 space-y-6">

    @if (session('success'))
        <div id="am-toast" class="fixed top-5 right-5 z-[60] rounded-lg bg-green-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
            {{ session('success') }}
        </div>
    @elseif (session('error'))
        <div id="am-toast" class="fixed top-5 right-5 z-[60] rounded-lg bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Match the heading and section hierarchy used by the other management registers. --}}
    <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">BFAR SAAD Phase II</span>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">User Management</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Manage authorized AssocMap accounts and role-based system access.</p>
    </header>

    {{-- List validation errors stay visible outside the account form until the next request. --}}
    @if ($errors->any() && ! session('user_form'))
        <div role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @php
        $summaryCards = [
            ['Total Users', $summary['total'], []],
            ['System Admins', $summary['admins'], ['role_id' => $adminRoleId]],
            ['Field Officers', $summary['field_officers'], ['role_id' => $officerRoleId]],
            ['Association Accounts', $summary['members'], ['role_id' => $memberRoleId]],
            ['Inactive Users', $summary['inactive'], ['status' => 'inactive']],
        ];
    @endphp
    <section aria-label="Account summary, independent of list filters" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($summaryCards as [$label, $count, $query])
            <a href="{{ route('users.index', $query) }}" class="am-user-summary">
                <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($count) }}</p>
                <p class="mt-1 text-xs text-slate-500">View matching accounts</p>
            </a>
        @endforeach
    </section>

    {{-- Filters and record actions stay in their own sections, as in Area Management. --}}
    <form method="GET" action="{{ route('users.index') }}"
          aria-labelledby="user-filters-title" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 id="user-filters-title" class="mb-4 text-lg font-bold text-slate-900">Filter Users</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="user-filter-search" class="text-sm font-medium text-slate-700">Search</label>
                <input id="user-filter-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email..." class="am-user-control mt-1.5">
            </div>
            <div>
                <label for="user-filter-role" class="text-sm font-medium text-slate-700">Role</label>
                <select id="user-filter-role" name="role_id" class="am-user-control mt-1.5">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? '') == $role->id)>{{ $role->role_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user-filter-status" class="text-sm font-medium text-slate-700">Status</label>
                <select id="user-filter-status" name="status" class="am-user-control mt-1.5">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div>
                <label for="user-filter-sort" class="text-sm font-medium text-slate-700">Sort By</label>
                <select id="user-filter-sort" name="sort" class="am-user-control mt-1.5">
                    <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Name</option>
                    <option value="email" @selected(($filters['sort'] ?? '') === 'email')>Email</option>
                    <option value="role_name" @selected(($filters['sort'] ?? '') === 'role_name')>Role</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Date Created</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-3 border-t border-slate-200 pt-4">
            <div class="flex gap-2">
                <button type="submit" class="am-user-button am-user-button-primary">
                    Apply
                </button>
                <a href="{{ route('users.index') }}" class="am-user-button am-user-button-secondary">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <section class="min-w-0 rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="user-records-title">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 sm:p-5">
            <div>
                <h2 id="user-records-title" class="text-lg font-bold text-slate-900">User Accounts</h2>
                <p class="mt-1 text-sm text-slate-600">{{ number_format($users->total()) }} matching {{ $users->total() === 1 ? 'account' : 'accounts' }}</p>
            </div>
            <button type="button" data-admin-user-modal-open="create" class="am-user-button am-user-button-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                Add User
            </button>
        </header>

    {{-- Fixed column sizes stop long names from squeezing badges, dates and action controls.
         Smaller desktop panels scroll inside this region; mobile keeps its card layout. --}}
    <div data-user-table-scroll role="region" aria-label="User accounts" tabindex="0"
         class="am-user-table-scroll hidden xl:block">
        <table class="am-user-table divide-y divide-assocmap-border text-sm">
            <colgroup><col style="width:32%"><col style="width:21%"><col style="width:11%"><col style="width:12%"><col style="width:14%"><col style="width:10%"></colgroup>
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600">Account</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold text-assocmap-text">Role</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold text-assocmap-text">Status</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold text-assocmap-text">Last Login</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold text-assocmap-text">Created</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold text-assocmap-text">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-assocmap-border">
                @forelse ($users as $user)
                    <tr class="transition-colors hover:bg-assocmap-bg/50">
                        <td class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $avatarColor($user->name) }}">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="am-user-text font-medium text-assocmap-text">{{ $user->name }}</p>
                                    <p class="am-user-text mt-1 text-xs text-slate-500">{{ $user->email }}</p>
                                    {{-- Keep association context with the account, without repeating an identical name. --}}
                                    @if ($user->role_name === 'Association Member' && $user->association_name !== $user->name)
                                        <p class="am-user-text mt-1 text-xs text-assocmap-secondary">{{ $user->association_name ?? 'No association linked' }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="am-user-badge {{ $roleBadgeClass($user->role_name) }}">
                                {{ $user->role_name }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span class="am-user-badge bg-emerald-50 text-emerald-700">Active</span>
                            @else
                                <span class="am-user-badge bg-slate-100 text-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-assocmap-secondary" title="{{ $user->last_login ?? 'Never logged in' }}">
                            {{ $user->last_login ? \Carbon\Carbon::parse($user->last_login)->diffForHumans(['short' => true]) : 'Never' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-assocmap-secondary">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            {{-- Dropdown menu: only Edit / Deactivate exist as real routes.
                                 Reset Password / View Activity intentionally left out until
                                 those endpoints are actually built. --}}
                            <div class="relative inline-block text-left" data-am-dropdown>
                                <button type="button" data-am-dropdown-toggle
                                        aria-label="Actions for {{ $user->name }}" aria-expanded="false"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-assocmap-border text-assocmap-text hover:bg-assocmap-bg focus-visible:outline focus-visible:outline-2 focus-visible:outline-assocmap-primary">
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

                                    {{-- The URL fixes the intended status even if this page becomes stale. --}}
                                    <form id="account-status-form-{{ $user->id }}" action="{{ route($user->is_active ? 'users.deactivate' : 'users.activate', $user->id) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('PATCH')
                                    </form>
                                    <button type="button"
                                            data-confirm-open
                                            data-confirm-target="account-status-form-{{ $user->id }}"
                                            data-confirm-title="{{ $user->is_active ? 'Deactivate User?' : 'Activate User?' }}"
                                            data-confirm-message="{{ $user->is_active ? 'This user will no longer be able to access AssocMap.' : 'This user will be able to log in again.' }}"
                                            data-confirm-label="{{ $user->is_active ? 'Deactivate' : 'Activate' }}"
                                            data-confirm-tone="{{ $user->is_active ? 'deactivate' : 'activate' }}"
                                            class="block w-full px-3 py-2 text-left text-xs font-medium {{ $user->is_active ? 'text-red-600' : 'text-green-600' }} hover:bg-assocmap-bg">
                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-assocmap-secondary">
                            No users found. Try adjusting your filters or create a new account.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile card fallback --}}
    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:hidden">
        @forelse ($users as $user)
            <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ $avatarColor($user->name) }}">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="am-user-text font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="am-user-text mt-1 text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="am-user-badge {{ $roleBadgeClass($user->role_name) }}">
                        {{ $user->role_name }}
                    </span>
                    @if ($user->is_active)
                        <span class="am-user-badge bg-emerald-50 text-emerald-700">Active</span>
                    @else
                        <span class="am-user-badge bg-slate-100 text-slate-600">Inactive</span>
                    @endif
                </div>
                <p class="am-user-text mt-3 text-xs text-slate-500">
                    @if ($user->role_name === 'Association Member' && $user->association_name !== $user->name)
                        Association: {{ $user->association_name ?? 'No association linked' }}<br>
                    @endif
                    Last login: {{ $user->last_login ? \Carbon\Carbon::parse($user->last_login)->diffForHumans(['short' => true]) : 'Never' }}<br>
                    Created: {{ $user->created_at->format('d M Y') }}
                </p>
                <div class="mt-3 flex gap-2">
                    <button type="button" data-admin-user-modal-open="edit" data-user='@json($user)'
                            class="am-user-button am-user-button-secondary flex-1">
                        Edit
                    </button>
                    <form id="account-status-form-m-{{ $user->id }}" action="{{ route($user->is_active ? 'users.deactivate' : 'users.activate', $user->id) }}" method="POST" class="hidden">
                        @csrf
                        @method('PATCH')
                    </form>
                    <button type="button"
                            data-confirm-open
                            data-confirm-target="account-status-form-m-{{ $user->id }}"
                            data-confirm-title="{{ $user->is_active ? 'Deactivate User?' : 'Activate User?' }}"
                            data-confirm-message="{{ $user->is_active ? 'This user will no longer be able to access AssocMap.' : 'This user will be able to log in again.' }}"
                            data-confirm-label="{{ $user->is_active ? 'Deactivate' : 'Activate' }}"
                            data-confirm-tone="{{ $user->is_active ? 'deactivate' : 'activate' }}"
                            class="am-user-button flex-1 border {{ $user->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-assocmap-border bg-white p-6 text-center text-sm text-assocmap-secondary">
                No users found.
            </p>
        @endforelse
    </div>

    <footer class="border-t border-slate-200 p-4 sm:px-5">{{ $users->links() }}</footer>
    </section>
</div>

    {{-- Keep overlays outside the spaced page stack so margins do not offset their centering. --}}
    <div id="admin-user-modal" role="dialog" aria-modal="true" aria-labelledby="admin-user-modal-title" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-card">
            <div class="mb-5 flex items-center justify-between gap-3 border-b border-slate-200 pb-4">
                <h2 id="admin-user-modal-title" class="text-lg font-bold text-slate-900">Add User</h2>
                <button type="button" data-admin-user-modal-close aria-label="Close account form" class="am-user-button am-user-button-secondary px-3">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke-linecap="round"/></svg>
                </button>
            </div>

            {{-- Safe recovery data tells JavaScript which form to reopen after a rejected save. --}}
            <form id="admin-user-form" method="POST" action="{{ route('users.store') }}"
                  data-recovery='@json(session("user_form") ? ["form" => session("user_form"), "input" => old()] : null)'>
                @csrf
                <input type="hidden" id="admin-user-form-method" name="_method" value="POST">
                {{-- Selecting an occupied association changes this form to an explicit edit. --}}
                <p id="admin-user-existing-account-note" role="status" class="mb-4 hidden rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"></p>
                @if ($errors->any() || session('user_form'))
                    <div data-user-errors role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                        <p>{{ session('error', 'Please correct the highlighted fields.') }}</p>
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="space-y-6">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-assocmap-secondary">Basic Information</p>
                        <div class="space-y-3">
                            <div>
                                <label for="admin-user-name" class="text-sm font-medium text-slate-700">Account Name <span class="text-red-600" aria-hidden="true">*</span></label>
                                <input id="admin-user-name" name="name" required maxlength="255" autocomplete="name" class="am-user-control mt-1.5">
                            </div>
                            <div>
                                <label for="admin-user-email" class="text-sm font-medium text-slate-700">Email Address <span class="text-red-600" aria-hidden="true">*</span></label>
                                <input id="admin-user-email" name="email" type="email" required maxlength="255" autocomplete="email" class="am-user-control mt-1.5">
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-assocmap-secondary">Security</p>
                        <div class="flex flex-col gap-1.5">
                            <label for="admin-user-password" class="text-sm font-medium text-assocmap-text">Password</label>
                            <div class="relative">
                                <input id="admin-user-password" name="password" type="password"
                                       autocomplete="new-password" minlength="8" maxlength="72"
                                       placeholder="Leave blank to keep current password"
                                       class="am-user-control pr-12">
                                <button type="button" data-toggle-password="admin-user-password"
                                        aria-label="Show password" aria-pressed="false"
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
                                class="am-user-control mt-1.5">
                            <option value="">Choose a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                        <p id="admin-user-role-hint" class="mt-1 hidden text-xs text-amber-600">
                            This is the last active System Administrator. The system will block any change that leaves zero admins.
                        </p>
                    </div>
                    {{-- Shared accounts use this link for record access; representative approval remains separate. --}}
                    <div id="admin-user-association-section" class="hidden">
                        <label for="admin-user-association" class="text-sm font-medium text-assocmap-text">Association</label>
                        <select id="admin-user-association" name="association_id" data-member-role="{{ $memberRoleId }}"
                                aria-describedby="admin-user-association-hint admin-user-association-error" class="am-user-control mt-1.5">
                            <option value="">Choose an association</option>
                            @foreach ($associations as $association)
                                @php
                                    $linkedAccount = $association->account_id ? [
                                        'id' => $association->account_id, 'name' => $association->account_name,
                                        'email' => $association->account_email, 'role_id' => $association->account_role_id,
                                        'association_id' => $association->id, 'is_active' => $association->account_is_active,
                                        'is_last_admin' => false,
                                    ] : null;
                                @endphp
                                <option value="{{ $association->id }}" data-account-id="{{ $association->account_id }}" data-archived="{{ $association->is_archived ? 'true' : 'false' }}" data-name="{{ $association->name }}"
                                        data-account='@json($linkedAccount)'
                                        @disabled($association->is_archived)>{{ $association->name }}{{ $association->is_archived ? ' (archived)' : ($association->account_id ? ' (edit existing account)' : '') }}</option>
                            @endforeach
                        </select>
                        <p id="admin-user-association-hint" class="mt-1 text-xs text-slate-500" aria-live="polite"></p>
                        <p id="admin-user-association-error" @if ($errors->has('association_id')) role="alert" @endif class="mt-1 text-xs text-red-700">{{ $errors->first('association_id') }}</p>
                        <p class="mt-1 text-xs text-assocmap-secondary">Each association has one shared account. Its assigned representative approves applications using a separate private review passphrase.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="button" data-admin-user-modal-close
                            class="am-user-button am-user-button-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="am-user-button am-user-button-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Generic confirm dialog --}}
    {{-- Native confirmation keeps keyboard focus inside the dialog and restores it on close. --}}
    <dialog id="am-confirm-modal" data-user-confirm-dialog aria-labelledby="am-confirm-title" aria-describedby="am-confirm-message"
            class="w-[calc(100%-2rem)] max-w-sm rounded-2xl border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-black/50">
        <div class="p-6">
            <h3 id="am-confirm-title" class="text-lg font-bold text-assocmap-text">Are you sure?</h3>
            <p id="am-confirm-message" class="mt-2 text-sm text-assocmap-secondary"></p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-confirm-close
                        class="am-user-button am-user-button-secondary">
                    Cancel
                </button>
                <button type="button" id="am-confirm-action-btn"
                        class="am-user-button text-white" data-tone="deactivate">
                    Confirm
                </button>
            </div>
        </div>
    </dialog>

</x-dashboard-layout>
