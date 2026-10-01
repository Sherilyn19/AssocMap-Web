{{-- Color supplements the visible status label. Unknown labels keep a neutral style without changing stored status. --}}
@php
    $badgeClass = match($label) {
        'Ongoing', 'Accepted' => 'bg-blue-50 text-blue-900 border-blue-200',
        'Terminated', 'Rejected', 'Damaged', 'Absent' => 'bg-red-50 text-red-900 border-red-200',
        'Active', 'Approved', 'Completed', 'Good', 'Present' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
        'Pending', 'Planned', 'For Repair', 'Initiation / Proposal' => 'bg-amber-50 text-amber-900 border-amber-200',
        default => 'bg-slate-100 text-slate-800 border-slate-200',
    };
@endphp
<span class="inline-flex max-w-full rounded-md border px-2 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $label ?? 'Not recorded' }}</span>
