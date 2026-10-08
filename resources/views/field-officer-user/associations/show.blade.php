@php
    // Generate links to the authorized association-specific drawer.
    $drawerUrl = fn ($section) => route('officer.associations.details', [
        'association' => $association->id,
        'section' => $section,
    ]);
@endphp

<x-dashboard-layout title="Association Details">
<div class="fo-coverage space-y-5">
    <a href="{{ route('officer.associations.index') }}" class="fo-action">
        Back to My Associations
    </a>

    {{-- The modal extracts this section; the same information works as a normal page. --}}
    <section data-record-content
             data-record-title="Association details"
             class="space-y-5">
        <header class="am-drawer-intro">
            <p class="fo-eyebrow">Association profile</p>
            <h2 class="fo-drawer-title">{{ $association->name }}</h2>
            <p class="fo-record-id">
                ASSOC-{{ str_pad((string) $association->id, 6, '0', STR_PAD_LEFT) }}
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                {{-- Archive state and operational status are separate database values. --}}
                <span class="fo-pill fo-pill-teal">
                    {{ $association->status?->status_name ?? 'Status not recorded' }}
                </span>
                <span class="fo-pill {{ $association->is_archived ? 'fo-pill-amber' : 'fo-pill-slate' }}">
                    {{ $association->is_archived ? 'Archived' : 'Not archived' }}
                </span>
            </div>
        </header>

        <section class="fo-card">
            <header class="fo-section-heading">
                <h3 class="font-semibold">Location and program</h3>
            </header>
            <dl class="grid gap-5 p-5 sm:grid-cols-2">
                @foreach([
                    'Municipality' => $association->areaUnit?->name,
                    'Barangay' => $association->subUnit?->name,
                    'Program component' => $association->programComponent?->name,
                    'Address' => $association->address,
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-sm text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-medium">
                            @if($label === 'Address')
                                <a href="{{ $drawerUrl('address') }}" data-record-association
                                class="inline-flex min-h-11 items-center gap-2 text-teal-800 underline underline-offset-4">
                                    {{ filled($value) ? $value : 'View location details' }}
                                    <span aria-hidden="true">↗</span>
                                </a>
                            @else
                                {{ filled($value) ? $value : 'Not recorded' }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="fo-card">
            <header class="fo-section-heading">
                <h3 class="font-semibold">Assignment and membership</h3>
            </header>
            <dl class="grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-slate-500">Assigned officer</dt>
                    <dd class="mt-1 font-medium">
                        <a href="{{ $drawerUrl('officer') }}" data-record-association
                        class="inline-flex min-h-11 items-center gap-2 text-teal-800 underline underline-offset-4">
                            {{ $association->fieldOfficer?->name ?? 'View assignment' }}
                            <span aria-hidden="true">↗</span>
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">
                        Official members — non-archived
                    </dt>
                    <dd class="mt-1 font-medium">
                        <a href="{{ $drawerUrl('members') }}" data-record-association
                            class="fo-count fo-count-teal"
                            aria-label="View official members">
                            {{ number_format($association->members_count) }}
                            <span aria-hidden="true">↗</span>
                        </a>
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Open related records beside the association without leaving the modal. --}}
        <nav class="flex flex-wrap gap-3" aria-label="Related association records">
            <a href="{{ $drawerUrl('projects') }}" data-record-association
            class="fo-primary am-button-green">
                View projects
            </a>

            <a href="{{ $drawerUrl('trainings') }}" data-record-association
            class="fo-action">
                View training records
            </a>
        </nav>
    </section>
</div>
</x-dashboard-layout>