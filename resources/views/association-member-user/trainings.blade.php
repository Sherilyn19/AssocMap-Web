<x-dashboard-layout title="Association Trainings" topbar-title="Association Trainings">
<div data-member-workspace class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('association-member-user.partials.header', ['heading' => 'Association Trainings', 'description' => 'Browse scheduled activities and training records for your association.'])
    @include('association-member-user.partials.filters', ['label' => 'Trainings', 'statusLabel' => 'Record state', 'options' => ['Current' => 'Current records', 'Archived' => 'Archived records', 'All' => 'All records'], 'reset' => route('member.trainings')])
    @include('shared.trainings.table', ['readOnly' => true])
</div>
@include('shared.trainings.dialog')
</x-dashboard-layout>
