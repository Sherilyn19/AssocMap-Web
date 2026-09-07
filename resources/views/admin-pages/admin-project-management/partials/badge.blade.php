{{-- Color supplements the visible status label. Unknown labels keep a neutral style without changing stored status. --}}
@php
    $badgeClass = match($label) {
        'Ongoing' => 'bg-blue-50 text-blue-900 border-blue-200',
        'Completed' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
        default => 'bg-slate-100 text-slate-800 border-slate-200',
    };
@endphp
<span class="inline-flex rounded-md border px-2 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $label }}</span>
