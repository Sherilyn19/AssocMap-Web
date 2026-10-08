@php
    $activeTab = in_array(request('tab'), ['members', 'applications', 'drafts'], true)
        ? request('tab')
        : 'members';

    // Preserve common filters when moving between registers.
    $commonFilters = request()->only(['search', 'association_id']);
@endphp

    <x-dashboard-layout title="Members and Applications">
    {{-- This module has editable dialogs, so it uses its own interaction controller. --}}
    <div class="fo-coverage fo-members space-y-6"
        data-members-workspace
        data-members-url="{{ route('membership.index') }}">
        {{-- Reuse the Area module's visual styles and workspace heading. --}}
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="am-officer-eyebrow">BFAR SAAD Phase II</span>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">
                    Members and Applications
                </h1>
                <p class="mt-2 text-sm text-slate-600">
                    Member records within your assigned associations.
                </p>
            </div>
        </header>

        @include('shared.membership.partials.feedback')

        {{-- Feedback remains visible while the selected register is refreshed. --}}
        <div data-members-status role="status" tabindex="-1" hidden></div>

        <div data-members-register class="space-y-5">
            <div class="am-members-toolbar">
        <nav class="am-register-switch" aria-label="Member registers">
            @foreach([
                'members' => 'Official members',
                'applications' => 'Applications',
                'drafts' => 'Saved drafts',
            ] as $tab => $label)
                <a href="{{ route('membership.index', array_merge($commonFilters, ['tab' => $tab])) }}"
                   @if($activeTab === $tab) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <a href="{{ route('membership.drafts.create') }}"
           data-record-open
           data-record-title="Create member draft"
           class="fo-action am-button-green am-member-create">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            </svg>
            Create draft
        </a>
    </div>

    @if($activeTab === 'drafts')
        @include('field-officer-user.members.drafts-register')
    @else

    {{-- Preserve the controller's supported search and status parameters. --}}
    <form method="GET" action="{{ route('membership.index') }}"
      class="fo-filter-card">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <div class="fo-section-heading">
            <h2 class="flex items-center gap-2 font-semibold">
                <svg class="h-5 w-5 text-teal-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" d="M3 6h3m4 0h11M3 12h11m4 0h3M3 18h3m4 0h11M6 4v4m8 2v4M6 16v4"/>
                </svg>
                Filter records
            </h2>
            <span class="fo-pill fo-pill-teal">Your assignments only</span>
        </div>

        <div class="grid gap-4 px-5 py-3 md:grid-cols-2 xl:grid-cols-3">
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Search name</span>
                <input type="search" name="search" maxlength="255"
                       value="{{ request('search') }}"
                       placeholder="First, middle, or last name"
                       class="w-full">
            </label>

            {{-- These options come only from the officer's assigned associations. --}}
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Association</span>
                <select name="association_id" class="w-full">
                    <option value="">All assigned associations</option>
                    @foreach($associationOptions as $association)
                        <option value="{{ $association->id }}"
                                @selected((string) request('association_id') === (string) $association->id)>
                            {{ $association->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            @if($activeTab === 'members')
            {{-- Historical records remain accessible without permitting edits. --}}
            <label class="flex min-w-0 flex-col gap-2">
                <span class="text-sm font-semibold">Member record</span>

                <select name="record_state" class="w-full">
                    <option value="current"
                            @selected((request('record_state') ?: 'current') === 'current')>
                        Current members
                    </option>
                    <option value="archived"
                            @selected(request('record_state') === 'archived')>
                        Archived members
                    </option>
                    <option value="all"
                            @selected(request('record_state') === 'all')>
                        All records
                    </option>
                </select>
            </label>
                <label class="flex min-w-0 flex-col gap-2">
                    <span class="text-sm font-semibold">Association role</span>
                    <select name="role_in_assoc" class="w-full">
                        <option value="">All roles</option>
                        @foreach(\App\Support\MemberProfile::ROLES as $role)
                            <option value="{{ $role }}"
                                    @selected(request('role_in_assoc') === $role)>
                                {{ $role }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="flex min-w-0 flex-col gap-2">
                    <span class="text-sm font-semibold">Representative</span>
                    <select name="representative" class="w-full">
                        <option value="">All members</option>
                        <option value="yes" @selected(request('representative') === 'yes')>
                            Representative
                        </option>
                        <option value="no" @selected(request('representative') === 'no')>
                            Not representative
                        </option>
                    </select>
                </label>

            @else
                <label class="flex min-w-0 flex-col gap-2">
                    <span class="text-sm font-semibold">
                        Application status
                    </span>
                    <select name="status" class="w-full">
                        <option value="">All statuses</option>
                        @foreach(['Pending', 'Approved', 'Rejected'] as $status)
                            <option value="{{ $status }}"
                                    @selected(request('status') === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-3">
            <a href="{{ route('membership.index', ['tab' => $activeTab]) }}"
                class="fo-action">
                Reset
            </a>
            <button type="submit" class="fo-primary am-button-green">
                Apply filters
            </button>
        </div>

        @if($errors->any())
            <div role="alert" class="fo-warning m-5">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
    </form>

    {{-- Render only the register selected through the navigation buttons. --}}
    @foreach([
        $activeTab === 'applications'
            ? [
                'title' => 'Applications',
                'records' => $applications,
                'application' => true,
            ]
            : [
                'title' => 'Official members',
                'records' => $members,
                'application' => false,
            ],
    ] as $register)
        @php
            $isApplication = $register['application'];
            $records = $register['records'];
        @endphp

        <section class="fo-table-card" id="{{ $isApplication ? 'member-applications' : 'official-members' }}">
            <header class="fo-section-heading">
                <h2 class="font-semibold">{{ $register['title'] }}</h2>
                <span class="fo-pill fo-pill-slate">
                    {{ number_format($records->total()) }} matching records
                </span>
            </header>

            <div class="overflow-x-auto" tabindex="0" role="region"
                 aria-label="{{ $register['title'] }} table">
                <table class="fo-table fo-members-table">
                    <caption class="sr-only">{{ $register['title'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Name / ID</th>
                            <th scope="col">Association</th>
                            <th scope="col">
                                {{ $isApplication ? 'Status' : 'Association role' }}
                            </th>
                            <th scope="col">
                                {{ $isApplication ? 'Submitted' : 'Registered' }}
                            </th>
                            <th scope="col">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            @php
                                $url = $isApplication
                                    ? route('membership.applications.show', $record)
                                    : route('membership.members.show', $record);
                                // Missing middle names must not introduce duplicate spaces.
                                $name = implode(' ', array_filter([$record->first_name, $record->middle_name, $record->last_name], fn ($part) => filled($part)));
                                $status = $isApplication
                                ? ($record->status?->status_name ?? 'Not recorded')
                                : null;
                            @endphp

                            <tr>
                                <td class="fo-members-number text-slate-500" data-label="No.">
                                    {{ $records->firstItem() + $loop->index }}
                                </td>
                                <th scope="row" class="fo-members-name">
                                    <a href="{{ $url }}" data-record-open
                                       class="font-semibold hover:underline">
                                        {{ $name }}
                                    </a>
                                    <span class="fo-record-id">
                                        {{ $isApplication ? 'APP' : 'MEMBER' }}-{{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}
                                    </span>

                                    {{-- Show record state so mixed results are easy to distinguish. --}}
                                    @if(!$isApplication)
                                        <div class="mt-2">
                                            <span class="fo-pill {{ $record->is_archived ? 'fo-pill-amber' : 'fo-pill-green' }}">
                                                {{ $record->is_archived ? 'Archived' : 'Current' }}
                                            </span>
                                        </div>
                                    @endif
                                </th>
                                <td data-label="Association">{{ $record->association?->name ?? 'Not recorded' }}</td>
                                <td data-label="{{ $isApplication ? 'Status' : 'Association role' }}">
                                    @if($isApplication)
                                        <span class="fo-pill {{ match($status) {
                                            'Pending' => 'fo-pill-amber',
                                            'Approved' => 'fo-pill-green',
                                            'Rejected' => 'fo-members-rejected',
                                            default => 'fo-pill-slate',
                                        } }}">
                                            {{ $status }}
                                        </span>
                                    @else
                                        @php
                                        // Compare the member ID with the association's designated representative.
                                        $isRepresentative = (int) $record->association?->representative_member_id
                                            === (int) $record->id;
                                    @endphp

                                    <div>{{ $record->role_in_assoc ?: 'Not recorded' }}</div>

                                    <span class="fo-pill mt-2 {{ $isRepresentative ? 'fo-pill-teal' : 'fo-pill-slate' }}">
                                        {{ $isRepresentative ? 'Representative' : 'Not representative' }}
                                    </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap" data-label="{{ $isApplication ? 'Submitted' : 'Registered' }}">
                                    {{ ($isApplication ? $record->created_at : $record->date_registered)?->format('M d, Y') ?? 'Not recorded' }}
                                </td>
                                <td class="fo-members-action">
                                    <a href="{{ $url }}" data-record-open
                                       class="fo-action" data-record-title="{{ $isApplication ? 'Application details' : 'Member details' }}"
                                       aria-label="View {{ $name }}">
                                        View
                                        {{-- Heroicons: chevron right. --}}
                                        <svg viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="1.5"
                                             aria-hidden="true">
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  d="m9 5 7 7-7 7"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="fo-members-empty text-center text-slate-500">
                                    <p class="font-semibold">No {{ strtolower($register['title']) }} found</p>
                                    <p class="mt-1 text-sm">Try another name or reset your filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-management-pagination :records="$records"
                :label="$register['title'].' pagination'" />
        </section>
    @endforeach
        @endif
</div>

    {{-- One native dialog provides keyboard containment and Escape dismissal. --}}
    <dialog class="fo-dialog fo-members-dialog" data-record-dialog
            aria-labelledby="member-dialog-title">
        <header class="fo-dialog-header">
            <div>
                <p class="fo-eyebrow">Field Officer workspace</p>
                <h2 id="member-dialog-title" data-record-heading>Member and application details</h2>
            </div>
            <button type="button" class="fo-dialog-close" data-record-close>
                Close
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/>
                </svg>
            </button>
        </header>

        {{-- Both panels remain inside the same native dialog. --}}
        <div class="fo-workspace">
            <div class="fo-main-panel" data-record-body aria-live="polite"></div>

            <aside class="fo-side-panel" data-record-side hidden inert
                aria-labelledby="member-association-title">
                <header class="fo-side-header">
                    <h3 id="member-association-title" data-member-side-title>
                        Association details
                    </h3>
                    <button type="button" class="fo-action" data-record-side-close>
                        Close panel <span aria-hidden="true">×</span>
                    </button>
                </header>

                <div class="fo-side-body" data-record-side-body
                    aria-live="polite"></div>
            </aside>
        </div>
    </dialog>
</div>
</x-dashboard-layout>
