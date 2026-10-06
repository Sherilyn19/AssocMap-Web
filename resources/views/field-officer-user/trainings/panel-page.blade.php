{{-- Accessible fallback when JavaScript is unavailable. --}}
<x-dashboard-layout title="Training Records">
    <div class="fo-training-ui space-y-5">
        <a href="{{ route('officer.trainings.index') }}" class="fo-action">
            Back to Training Records
        </a>

        @include('shared.membership.partials.feedback')
        @include('field-officer-user.trainings.panel')
    </div>
</x-dashboard-layout>