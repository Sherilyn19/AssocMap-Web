@php
    $isApplication = isset($application);
    $record = $isApplication ? $application : $member;
    $recordTitle = $isApplication ? 'Application details' : 'Member details';

    $recordStatus = $isApplication
        ? ($application->status?->status_name ?? 'Not recorded')
        : ($member->is_archived ? 'Archived' : 'Current member');

    // Define the association link after the current record is available.
    $associationDrawer = filled($record->association?->area_unit_id);

    $associationUrl = $associationDrawer
        ? route('officer.areas.details', [
            'areaUnit' => $record->association->area_unit_id,
            'association' => $record->association_id,
            'section' => 'association',
        ])
        : route('officer.associations.show', $record->association_id);
@endphp

<x-dashboard-layout :title="$recordTitle">
<div class="fo-coverage fo-members space-y-5">
    <a href="{{ route('membership.index') }}" class="fo-action">
        Back to members
    </a>

    {{-- The dialog extracts this section; the full page remains usable without JavaScript. --}}
    <section data-record-content data-record-title="{{ $recordTitle }}" class="fo-members-record space-y-5">
        
        @php
        // This project uses its custom session resolver rather than the default guard.
        $recordActor = app(\App\Services\SessionUserResolver::class)->resolve(request());
    @endphp

    @if(!$isApplication && \Illuminate\Support\Facades\Gate::forUser($recordActor)->allows('update', $member))
        <div class="flex flex-wrap gap-3">
            {{-- Amber identifies a member-management action requiring care. --}}
            <a href="{{ route('officer.members.edit', $member) }}"
                data-member-manage
                class="fo-action am-button-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 0 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487ZM16.862 4.487 19.5 7.125"/>
                    </svg>
                    Manage member
            </a>
        </div>
    @endif

    @include('shared.membership.partials.feedback')

        <header class="am-drawer-intro">
            <p class="fo-eyebrow">{{ $recordTitle }}</p>
            <h2 class="fo-drawer-title">
                {{ implode(' ', array_filter([$record->first_name, $record->middle_name, $record->last_name], fn ($part) => filled($part))) }}
            </h2>
            <p class="fo-record-id">
                {{ $isApplication ? 'APP' : 'MEMBER' }}-{{ str_pad((string) $record->id, 6, '0', STR_PAD_LEFT) }}
            </p>
            <a href="{{ $associationUrl }}"
            @if($associationDrawer) data-record-association @endif
            class="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-teal-800 underline underline-offset-4">
                {{ $record->association?->name ?? 'Association not recorded' }}
                <span aria-hidden="true">↗</span>
            </a>

            <div class="mt-3 flex flex-wrap gap-2">
                <span class="fo-pill {{ match($recordStatus) {
                    'Pending', 'Archived' => 'fo-pill-amber',
                    'Approved', 'Current member' => 'fo-pill-green',
                    'Rejected' => 'fo-members-rejected',
                    default => 'fo-pill-slate',
                } }}">
                    {{ $recordStatus }}
                </span>
            </div>
        </header>

        <section class="fo-card">
            <header class="fo-section-heading">
                <h3 class="font-semibold">Profile and association</h3>
            </header>
            <div class="p-5">
                {{-- Reuse the existing escaped, read-only profile fields. --}}
                @include('shared.membership.partials.profile', [
                    'record' => $record,
                    'associationUrl' => $associationUrl,
                    'associationDrawer' => $associationDrawer,
                ])
            </div>
        </section>

        <section class="fo-card">
            <header class="fo-section-heading">
                <h3 class="font-semibold">
                    {{ $isApplication ? 'Application history' : 'Membership information' }}
                </h3>
            </header>
            <dl class="grid gap-4 p-5 sm:grid-cols-2">
                @if($isApplication)
                    <div>
                        <dt class="text-sm text-slate-500">Submitted</dt>
                        <dd class="mt-1 font-medium">
                            {{ $application->created_at?->format('M d, Y g:i A') ?? 'Not recorded' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Reviewed</dt>
                        <dd class="mt-1 font-medium">
                            {{ $application->reviewed_at?->format('M d, Y g:i A') ?? 'Not reviewed' }}
                        </dd>
                    </div>
                    @if($application->reviewed_at)
                        <div>
                            <dt class="text-sm text-slate-500">Reviewed by</dt>
                            <dd class="mt-1 font-medium">
                                {{ $application->reviewer_name ?: 'Not recorded' }}
                            </dd>
                        </div>
                    @endif
                    @if($application->rejection_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-slate-500">Rejection reason</dt>
                            <dd class="mt-1 whitespace-pre-wrap break-words">{{ $application->rejection_reason }}</dd>
                        </div>
                    @endif
                @else
                    <div>
                        <dt class="text-sm text-slate-500">Association role</dt>
                        <dd class="mt-1 font-medium">
                            @php
                            // Representation is separate from the member's association role.
                            $isRepresentative = (int) $member->association?->representative_member_id
                                === (int) $member->id;
                        @endphp

                        <div>{{ $member->role_in_assoc ?: 'Not recorded' }}</div>

                        <span class="fo-pill mt-2 {{ $isRepresentative ? 'fo-pill-teal' : 'fo-pill-slate' }}">
                            {{ $isRepresentative ? 'Representative' : 'Not representative' }}
                        </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Registered</dt>
                        <dd class="mt-1 font-medium">
                            {{ $member->date_registered?->format('M d, Y') ?? 'Not recorded' }}
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        @if($isApplication && ($canReview ?? false) && $recordStatus === 'Pending')
            @include('shared.membership.partials.review-form')
        @endif

        {{-- Follow the existing authorized member route without stacking dialogs. --}}
        @if($isApplication && $application->member)
            <a href="{{ route('membership.members.show', $application->member) }}"
               class="fo-primary am-button-green" data-record-open data-record-title="Member details">
                View official member record
            </a>
        @endif
    </section>
</div>
</x-dashboard-layout>
