{{-- Shared read-only profile. Blade escapes all user-entered personal information. --}}
<dl class="grid gap-4 sm:grid-cols-2">
    @foreach ([['Full name', trim($record->first_name.' '.$record->middle_name.' '.$record->last_name)], ['Association', $record->association->name], ['Birthday', $record->birthday?->format('F j, Y')], ['Sex', $record->sex?->sex_name], ['Beneficiary type', $record->beneficiary_type], ['Contact number', $record->contact_number], ['Address', $record->address]] as [$label,$value])
        <div class="min-w-0"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-slate-900">{{ $value ?: 'Not provided' }}</dd></div>
    @endforeach
</dl>
