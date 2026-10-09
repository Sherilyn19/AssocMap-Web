{{-- Only authorized staff or verified representatives receive private profile fields. --}}
@php
    $privateProfile = $showPrivateProfile
        ?? in_array(
            session('auth_user.role_name'),
            ['System Administrator', 'Field Officer'],
            true
        );

    $profileFields = [
        [
            'Full name',
            trim($record->first_name.' '.$record->middle_name.' '.$record->last_name),
        ],
        ['Association', $record->association->name],
    ];

    if ($record instanceof \App\Models\Member) {
        $profileFields[] = ['Association role', $record->role_in_assoc ?: 'Member'];
    }

    if ($privateProfile) {
        $profileFields = array_merge($profileFields, [
            ['Birthday', $record->birthday?->format('F j, Y')],
            ['Sex', $record->sex?->sex_name],
            ['Beneficiary type', $record->beneficiary_type],
            ['Contact number', $record->contact_number],
            ['Address', $record->address],
        ]);
    }
@endphp

<dl class="grid gap-4 sm:grid-cols-2">
    @foreach ($profileFields as [$label, $value])
        <div class="min-w-0">
            <dt class="text-sm text-slate-500">{{ $label }}</dt>

            <dd class="mt-1 break-words font-medium text-slate-900">
                @if ($label === 'Association' && !empty($associationUrl))
                    <a href="{{ $associationUrl }}"
                       @if ($associationDrawer ?? false) data-record-association @endif
                       class="inline-flex min-h-11 items-center gap-2 text-teal-800 underline underline-offset-4">
                        {{ $value ?: 'Not provided' }}
                        <span aria-hidden="true">↗</span>
                    </a>
                @else
                    {{ $value ?: 'Not provided' }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>