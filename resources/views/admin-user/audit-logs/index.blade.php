<x-dashboard-layout title="Audit Logs">
<div class="pm-page mx-auto max-w-7xl space-y-5" data-audit-page>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-slate-900">Audit Logs</h1><p class="mt-1 text-sm text-slate-600">Review recorded system activity. Entries are retained and cannot be edited or deleted.</p></div>
        <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-900">Read-only</span>
    </header>
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Check your filters.</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5" aria-label="Filter audit logs">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div><label for="audit-user" class="pm-label">Performed by</label><input id="audit-user" type="search" name="performed_by" maxlength="255" value="{{ $filters['performed_by'] ?? '' }}" placeholder="Search by current user name" class="pm-input"></div>
            <div><label for="audit-module" class="pm-label">Module</label><select id="audit-module" name="module" class="pm-input"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>@endforeach</select></div>
            <div><label for="audit-action" class="pm-label">Action type</label><select id="audit-action" name="action_type" class="pm-input"><option value="">All actions</option>@foreach($actionTypes as $action)<option value="{{ $action }}" @selected(($filters['action_type'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></div>
            <div><label for="audit-from" class="pm-label">Date from (Philippine time)</label><input id="audit-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="pm-input"></div>
            <div><label for="audit-to" class="pm-label">Date to (Philippine time)</label><input id="audit-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="pm-input"></div>
            <div class="flex gap-2"><button type="submit" class="pm-primary">Apply filters</button><a href="{{ route('admin.audit-logs.index') }}" class="pm-action border border-slate-300">Reset</a></div>
        </form>
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-label="Audit entries">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">Recorded activity ({{ $logs->total() }})</h2><p class="mt-1 text-xs text-slate-500">Newest first · Philippine time (UTC+08:00). User names and roles reflect current account information.</p></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>@foreach(['Log ID', 'Action type', 'Module / Record', 'Details', 'Performed by', 'Date & time'] as $heading)<th scope="col" class="px-5 py-3">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    @php
                        $badge = match($log->action_type) {
                            'CREATE', 'APPROVE', 'ACTIVATE', 'RESTORE' => 'bg-green-50 text-green-800 border-green-200',
                            'REJECT', 'DELETE', 'DEACTIVATE' => 'bg-red-50 text-red-800 border-red-200',
                            'ARCHIVE', 'UNPUBLISH' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'LOGIN', 'LOGOUT' => 'bg-purple-50 text-purple-800 border-purple-200',
                            default => 'bg-blue-50 text-blue-800 border-blue-200',
                        };
                        $performedAt = $log->performed_at?->setTimezone($timezone);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-4 text-slate-500">#{{ $log->id }}</td>
                        <td class="px-5 py-4"><span class="inline-block rounded-full border px-2 py-1 text-xs font-semibold {{ $badge }}">{{ $log->action_type }}</span></td>
                        <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $log->module }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->record_id !== null ? 'Record #'.$log->record_id : 'No linked record' }}</p></td>
                        <td class="min-w-64 max-w-sm break-words px-5 py-4 text-slate-600">
                            {{-- Native details keeps the full text available even without JavaScript. --}}
                            @if(mb_strlen($log->details ?? '') > 80)
                                <details data-audit-details><summary class="cursor-pointer text-blue-800">{{ \Illuminate\Support\Str::limit($log->details, 80) }} <span class="underline">View full details</span></summary><p data-audit-text class="mt-2 whitespace-pre-wrap break-words">{{ $log->details }}</p></details>
                            @else
                                <p class="whitespace-pre-wrap">{{ $log->details ?? 'No details recorded.' }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4"><p class="font-medium text-slate-900">{{ $log->user?->name ?? ($log->user_id ? 'User unavailable' : 'System / unassigned') }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->user?->role?->role_name ?? 'No current role' }}</p>@if($log->user_id)<p class="mt-1 text-xs text-slate-500">User #{{ $log->user_id }}</p>@endif</td>
                        <td class="whitespace-nowrap px-5 py-4 text-slate-600">@if($performedAt)<time datetime="{{ $performedAt->toIso8601String() }}">{{ $performedAt->format('M d, Y') }}<br><span class="text-xs">{{ $performedAt->format('h:i:s A') }}</span></time>@else Time unavailable @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center"><p class="font-semibold text-slate-700">No audit entries found</p><p class="mt-1 text-slate-500">No recorded activity matches the selected filters.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-management-pagination :records="$logs" :numbered="true" label="Audit log pagination" />
    </section>
    <dialog data-audit-dialog aria-labelledby="audit-detail-title" class="w-full max-w-xl rounded-xl border border-slate-200 p-6 shadow-xl backdrop:bg-slate-900/50">
        <h2 id="audit-detail-title" class="text-lg font-semibold text-slate-900">Audit entry details</h2>
        <p data-audit-dialog-text class="my-5 max-h-96 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-4 text-sm text-slate-700"></p>
        <form method="dialog" class="flex justify-end"><button class="pm-primary" autofocus>Close</button></form>
    </dialog>
</div>
</x-dashboard-layout>
