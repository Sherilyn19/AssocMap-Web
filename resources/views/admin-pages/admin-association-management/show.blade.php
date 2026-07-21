{{--
    resources/views/admin-pages/admin-association-management/show.blade.php

    Association Management - Details Page

    Purpose:
    - Displays the complete information of one association.
    - Shows counts from related modules such as members, projects, and GIS.
    - Allows the administrator to assign or remove an Association Representative.
    - Prevents representative changes while the association is archived.

    Expected variables:
    - $association
        The selected Association model, including its relationships and counts.

    - $eligibleRepresentatives
        Active official members who belong to this association and can be
        assigned as its representative.

    Important business rules:
    - The representative must be an active member of the same association.
    - Archived associations cannot change their representative.
    - Operational status and archive state are displayed separately.
--}}

<x-dashboard-layout :title="$association->name">

    @php
        $isActiveStatus = $association->status?->status_name === 'Active';

        /*
         * These cards display calculated counts from related tables.
         * The values are produced through Eloquent loadCount() in the
         * Association Management service.
         */
        $summaryCards = [
            [
                'label' => 'Official members',
                'value' => $association->members_count ?? 0,
            ],
            [
                'label' => 'Pending applications',
                'value' => $association->pending_applications_count ?? 0,
            ],
            [
                'label' => 'Projects',
                'value' => $association->projects_count ?? 0,
            ],
            [
                'label' => 'Trainings',
                'value' => $association->trainings_count ?? 0,
            ],
            [
                'label' => 'GIS locations',
                'value' => $association->gis_locations_count ?? 0,
            ],
            [
                'label' => 'Published GIS',
                'value' => $association->published_gis_locations_count ?? 0,
            ],
        ];

        /*
         * Build the representative's full name only when a representative
         * has already been assigned.
         */

        $representativeName = $association->representative
            ? trim(
                $association->representative->first_name
                .' '
                .($association->representative->middle_name ?? '')
                .' '
                .$association->representative->last_name
            )
            : 'Not assigned';

        /*
         * These values are displayed in the Association Overview section.
         * Keeping them in one array avoids repeating the same HTML structure.
         */

        $overviewItems = [
            [
                'label' => 'Official name',
                'value' => $association->name,
            ],
            [
                'label' => 'Municipality',
                'value' => $association->areaUnit?->name,
            ],
            [
                'label' => 'Barangay',
                'value' => $association->subUnit?->name,
            ],
            [
                'label' => 'Program component',
                'value' => $association->programComponent?->name,
            ],
            [
                'label' => 'Date joined',
                'value' => $association->date_joined?->format('F j, Y'),
            ],
            [
                'label' => 'Field Officer',
                'value' => $association->fieldOfficer?->name,
            ],
            [
                'label' => 'Representative',
                'value' => $representativeName,
            ],
            [
                'label' => 'Created',
                'value' => $association->created_at?->format('F j, Y g:i A'),
            ],
            [
                'label' => 'Last updated',
                'value' => $association->updated_at?->format('F j, Y g:i A'),
            ],
        ];

        /*
         * These are informational placeholders for modules connected to
         * an association. They can later be replaced with actual links
         * when their routes and pages are implemented.
         */
        
        $relatedModules = [
            'Members',
            'Applications',
            'Projects',
            'Trainings',
            'Monitoring',
            'GIS Locations',
            'Audit History',
        ];
    @endphp

    <div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">

        {{-- ============================================================
             PAGE HEADER
             Displays navigation, association name, location, and status.
        ============================================================= --}}
        <header
            class="flex flex-col gap-4 border-b border-slate-200 pb-5
                   sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                {{-- Return to the Association Management list --}}
                <a
                    href="{{ route('admin.associations.index') }}"
                    class="inline-flex items-center gap-1 text-sm font-semibold
                           text-slate-600 hover:text-slate-900"
                >
                    <span aria-hidden="true">←</span>
                    Back to Association Management
                </a>

                {{-- Official association name --}}
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $association->name }}
                </h1>

                {{-- Location and BFAR program component --}}
                <p class="mt-2 text-sm text-slate-600">
                    {{ $association->subUnit?->name ?? 'No barangay' }},
                    {{ $association->areaUnit?->name ?? 'No municipality' }}

                    <span class="mx-1" aria-hidden="true">·</span>

                    {{ $association->programComponent?->name ?? 'No program component' }}
                </p>
            </div>

            {{-- Operational status and archive-state badges --}}
            <div class="flex flex-wrap gap-2">

                {{-- Active or Inactive status --}}
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold
                        {{ $isActiveStatus
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800' }}"
                >
                    {{ $association->status?->status_name ?? 'Unknown status' }}
                </span>

                {{-- Current or Archived record state --}}
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold
                        {{ $association->is_archived
                            ? 'bg-slate-200 text-slate-700'
                            : 'bg-blue-100 text-blue-800' }}"
                >
                    {{ $association->is_archived ? 'Archived' : 'Current' }}
                </span>
            </div>
        </header>

        {{-- ============================================================
             FLASH AND VALIDATION MESSAGES
        ============================================================= --}}

        {{-- Successful operation message --}}
        @if (session('success'))
            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50
                       px-4 py-3 text-sm text-emerald-800"
                role="status"
            >
                {{ session('success') }}
            </div>
        @endif

        {{-- Service or business-rule error message --}}
        @if (session('error'))
            <div
                class="rounded-xl border border-red-200 bg-red-50
                       px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ session('error') }}
            </div>
        @endif

        {{-- Validation errors from the representative form --}}
        @if ($errors->any())
            <div
                class="rounded-xl border border-red-200 bg-red-50
                       px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                <p class="font-semibold">
                    Please correct the following information:
                </p>

                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ============================================================
             RELATED RECORD COUNTS
             Values are calculated from related database tables.
        ============================================================= --}}
        <section
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
            aria-label="Related record counts"
        >
            @foreach ($summaryCards as $card)
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">
                        {{ $card['label'] }}
                    </p>

                    <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                        {{ $card['value'] }}
                    </p>
                </article>
            @endforeach
        </section>

        {{-- ============================================================
             MAIN CONTENT
             Left: Association overview
             Right: Representative assignment
        ============================================================= --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Association overview --}}
            <section
                class="rounded-xl border border-slate-200 bg-white
                       p-6 shadow-sm lg:col-span-2"
            >
                <h2 class="text-lg font-bold text-slate-900">
                    Association Overview
                </h2>

                <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2">

                    {{-- Reusable label-and-value fields --}}
                    @foreach ($overviewItems as $item)
                        <div>
                            <dt
                                class="text-xs font-semibold uppercase
                                       tracking-wide text-slate-500"
                            >
                                {{ $item['label'] }}
                            </dt>

                            <dd class="mt-1 text-sm font-medium text-slate-900">
                                {{ filled($item['value']) ? $item['value'] : '—' }}
                            </dd>
                        </div>
                    @endforeach

                    {{-- Address uses the full width because it may be long --}}
                    <div class="sm:col-span-2">
                        <dt
                            class="text-xs font-semibold uppercase
                                   tracking-wide text-slate-500"
                        >
                            Complete address
                        </dt>

                        <dd class="mt-1 text-sm leading-6 text-slate-900">
                            {{ $association->address ?: '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- ========================================================
                 ASSOCIATION REPRESENTATIVE
                 Only an active member of this association may be selected.
            ========================================================= --}}
            <aside class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">
                    Association Representative
                </h2>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Only an active official member of this association can
                    be assigned as its representative.
                </p>

                {{-- Representative changes are disabled while archived --}}
                @unless ($association->is_archived)
                    <form
                        method="POST"
                        action="{{ route('admin.associations.representative', $association) }}"
                        class="mt-5 space-y-4"
                    >
                        @csrf
                        @method('PATCH')

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">
                                Representative
                            </span>

                            <select
                                name="representative_member_id"
                                class="mt-1.5 min-h-11 w-full rounded-lg border
                                       border-slate-300 bg-white px-3 py-2 text-sm
                                       text-slate-900 focus:border-slate-500
                                       focus:outline-none focus:ring-2
                                       focus:ring-slate-200"
                            >
                                {{--
                                    An empty value removes the currently
                                    assigned representative.
                                --}}
                                <option value="">Not assigned</option>

                                @foreach ($eligibleRepresentatives as $member)
                                    <option
                                        value="{{ $member->id }}"
                                        @selected(
                                            (string) old(
                                                'representative_member_id',
                                                $association->representative_member_id
                                            ) === (string) $member->id
                                        )
                                    >
                                        {{ $member->first_name }}
                                        {{ $member->middle_name }}
                                        {{ $member->last_name }}

                                        @if ($member->role_in_assoc)
                                            — {{ $member->role_in_assoc }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        {{-- Field-specific validation message --}}
                        @error('representative_member_id')
                            <p class="text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        {{-- Show information when no active members exist --}}
                        @if ($eligibleRepresentatives->isEmpty())
                            <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                No active official members are currently
                                available for representative assignment.
                            </p>
                        @endif

                        <button
                            type="submit"
                            class="min-h-11 w-full rounded-lg bg-slate-800
                                   px-4 py-2 text-sm font-semibold text-white
                                   hover:bg-slate-700 focus:outline-none
                                   focus:ring-2 focus:ring-slate-500
                                   focus:ring-offset-2"
                        >
                            Update Representative
                        </button>
                    </form>
                @else
                    {{-- Archived records cannot be modified --}}
                    <p class="mt-5 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">
                        Restore this association before changing its representative.
                    </p>
                @endunless
            </aside>
        </div>

        {{-- ============================================================
             RELATED MODULES
             These are informational placeholders until their routes exist.
        ============================================================= --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">
                Related Modules
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-600">
                Association Management provides summary information. Detailed
                transactions and operations remain inside their respective modules.
            </p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($relatedModules as $module)
                    <div class="rounded-lg border border-slate-200 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $module }}
                        </p>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Navigation can be added when this module's route
                            and management page are implemented.
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-dashboard-layout>