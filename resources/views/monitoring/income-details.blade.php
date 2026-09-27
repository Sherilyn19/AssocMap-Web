<details data-income-details class="max-w-md">
    <summary class="cursor-pointer rounded font-semibold text-blue-900 underline focus:outline-none focus:ring-2 focus:ring-blue-500">₱{{ number_format($record->gross_income, 2) }} <span class="text-xs font-normal">View income details</span></summary>
    <dl class="mt-3 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        @foreach(['Record number' => $record->id, 'Project' => $record->project_title, 'Association' => $record->association_name, 'Monitoring period' => date('F', mktime(0, 0, 0, $record->month, 1)).' '.$record->year, 'Gross income (PHP)' => number_format($record->gross_income, 2), 'Project lifecycle' => $record->terminated_on ? 'Terminated on '.$record->terminated_on : ($record->project_status_name ?? 'Not recorded'), 'Remarks / supporting details' => $record->remarks ?: 'Not recorded', 'Created' => $record->created_at, 'Last updated' => $record->updated_at] as $label => $value)
            <div class="min-w-0"><dt class="text-xs text-slate-600">{{ $label }}</dt><dd class="whitespace-pre-line break-words">{{ $value ?? 'Not recorded' }}</dd></div>
        @endforeach
    </dl>
    <button type="button" data-income-close class="pm-action mt-3 border border-slate-300">Close income details</button>
    <p class="mt-2 text-xs text-slate-600">Select the amount again or press Escape to close.</p>
</details>
