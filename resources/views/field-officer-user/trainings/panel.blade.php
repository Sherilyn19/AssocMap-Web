@php
    $creating = $training === null;
    $purposes = [
        'proposal' => 'Proposal preparation',
        'accepted' => 'After proposal acceptance',
        'terminated' => 'Project termination',
    ];

    // Ignore malformed array input when restoring form values.
    $value = function ($key) use ($training) {
        $saved = $training?->$key;
        if ($saved instanceof \DateTimeInterface) {
            $saved = $saved->format('Y-m-d');
        }
        $input = old($key, $saved);
        return is_scalar($input) ? $input : '';
    };
@endphp

<div data-training-fragment class="space-y-5">
    @if($training)
        <div class="am-training-panel">
            <p class="fo-eyebrow">{{ $training->association->name }}</p>
            <h2 class="mt-2 font-semibold">{{ $training->title }}</h2>
            <p class="fo-record-id">TRAINING-{{ str_pad($training->id, 6, '0', STR_PAD_LEFT) }}</p>
        </div>
    @endif

    @if($panel === 'attendance')
        {{-- Reuse the existing participant checks and attendance forms. --}}
        @include('field-officer-user.trainings.manage')

    @elseif($panel === 'archive')
        <form method="POST"
              action="{{ route('officer.trainings.archive', $training) }}"
              data-training-write class="space-y-4">
            @csrf
            @method('PATCH')

            <p class="fo-warning">
                Archiving stops changes to this training. Participants and attendance remain available.
            </p>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="confirm" value="1" required>
                <span>I confirm that this training should be archived.</span>
            </label>

            @error('confirm')
                <p class="text-sm text-red-800">{{ $message }}</p>
            @enderror

            <button class="fo-action fo-training-warning">Archive training</button>
        </form>

    @else
        <form method="POST"
              action="{{ $creating
                  ? route('officer.trainings.store')
                  : route('officer.trainings.update', $training) }}"
              data-training-write class="space-y-5">
            @csrf
            @unless($creating) @method('PUT') @endunless

            @if($creating)
                <fieldset class="space-y-4">
                    <legend class="font-semibold mb-3">Training information</legend>

                    <label class="block">
                        <span>Association *</span>
                        <select name="association_id" required class="mt-2 w-full">
                            <option value="">Choose assigned association</option>
                            @foreach($associations as $associationOption)
                                <option value="{{ $associationOption->id }}"
                                    @selected($value('association_id') == $associationOption->id)>
                                    {{ $associationOption->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('association_id')
                            <span class="block text-sm text-red-800">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach([
                            'title' => 'Training title',
                            'training_type' => 'Training type',
                            'venue' => 'Venue',
                            'conducted_by' => 'Conducted by',
                        ] as $key => $label)
                            <label class="block">
                                <span>{{ $label }} *</span>
                                <input name="{{ $key }}" required
                                       maxlength="{{ $key === 'training_type' ? 100 : 255 }}"
                                       value="{{ $value($key) }}" class="mt-2 w-full">
                                @error($key)
                                    <span class="block text-sm text-red-800">{{ $message }}</span>
                                @enderror
                            </label>
                        @endforeach

                        <label class="block">
                            <span>Program component *</span>
                            <select name="program_component_id" required class="mt-2 w-full">
                                <option value="">Choose component</option>
                                @foreach($components as $component)
                                    <option value="{{ $component->id }}"
                                        @selected($value('program_component_id') == $component->id)>
                                        {{ $component->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('program_component_id')
                                <span class="block text-sm text-red-800">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span>Approved training cost (PHP) *</span>
                            <input type="number" name="training_cost" required
                                   min="0" max="999999999999.99" step="0.01"
                                   value="{{ $value('training_cost') }}" class="mt-2 w-full">
                            @error('training_cost')
                                <span class="block text-sm text-red-800">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>
                </fieldset>
            @endif

            <label class="block">
                <span>Training purpose *</span>
                <select name="stage" required class="mt-2 w-full">
                    <option value="">Choose purpose</option>
                    @foreach($purposes as $key => $label)
                        <option value="{{ $key }}" @selected($value('stage') === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('stage')
                    <span class="block text-sm text-red-800">{{ $message }}</span>
                @enderror
            </label>

            @if($datesEditable)
                <fieldset>
                    <legend class="font-semibold mb-3">Schedule</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach(['date_conducted' => 'Start date', 'end_date' => 'End date'] as $key => $label)
                            <label class="block">
                                <span>{{ $label }} *</span>
                                <input type="date" name="{{ $key }}" required
                                       value="{{ $value($key) }}" class="mt-2 w-full">
                                @error($key)
                                    <span class="block text-sm text-red-800">{{ $message }}</span>
                                @enderror
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                {{-- No hidden date inputs: locked values are retained by the backend. --}}
                <div class="fo-warning">
                    Schedule locked: participant registration has started.
                    <p class="mt-2">
                        {{ $training->date_conducted?->format('M d, Y') ?? 'Not recorded' }}
                        –
                        {{ $training->end_date?->format('M d, Y') ?? 'Not recorded' }}
                    </p>
                </div>
            @endif

            <label class="block">
                <span>Remarks</span>
                <textarea name="remarks" rows="4" maxlength="5000"
                          class="mt-2 w-full">{{ $value('remarks') }}</textarea>
                @error('remarks')
                    <span class="block text-sm text-red-800">{{ $message }}</span>
                @enderror
            </label>

            @if($creating)
                {{-- Confirmation records an external decision; FO does not grant approval. --}}
                <label class="flex items-start gap-3 rounded-lg border p-4">
                    <input type="checkbox" name="approval_confirmed" value="1"
                           required @checked(old('approval_confirmed'))>
                    <span>
                        I confirm that the hearing is complete and this training
                        and its budget have already been approved outside AssocMap.
                    </span>
                </label>
                @error('approval_confirmed')
                    <p class="text-sm text-red-800">{{ $message }}</p>
                @enderror
            @endif

            <button class="fo-action am-button-green">
                {{ $creating ? 'Create training record' : 'Save changes' }}
            </button>
        </form>
    @endif
</div>