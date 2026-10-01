<x-dashboard-layout title="Association Details">
<div class="space-y-6"><a class="am-user-button am-user-button-secondary" href="{{ route('officer.associations.index') }}">Back to My Associations</a><header><span class="am-officer-eyebrow">Association details</span><h1>{{ $association->name }}</h1></header>
<dl class="grid gap-5 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
@foreach (['Municipality' => $association->areaUnit?->name, 'Barangay' => $association->subUnit?->name, 'Program component' => $association->programComponent?->name, 'Assigned officer' => $association->fieldOfficer?->name, 'Official members' => $association->members_count, 'Status' => $association->is_archived ? 'Archived' : $association->status?->status_name, 'Address' => $association->address] as $label => $value)
<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium">@if($label === 'Status') @include('shared.partials.badge', ['label' => $value]) @else {{ $value ?? 'Not recorded' }} @endif</dd></div>
@endforeach
</dl><nav class="flex flex-wrap gap-4"><a class="underline" href="{{ route('officer.projects.index', ['association_id' => $association->id]) }}">View projects</a><a class="underline" href="{{ route('officer.trainings.index', ['association_id' => $association->id]) }}">View training records</a></nav></div>
</x-dashboard-layout>

