@php
    $editing = $record !== null;
    $projectOptions = $projects->mapWithKeys(fn ($project) => [
        $project->id => $project->title.' / '.$project->association_name,
    ]);

    $fields = [];
    $locked = [];

    // Explain permanent rules without displaying long paragraphs in the form.
    $fieldHelp = [
        'quarter_id' => 'Only completed quarters can be recorded.',
        'output_unit_code' => 'Use the source record to select the unit. Target and actual output must use the same unit.',
        'output_unit_spec' => 'For packaged output, enter the package size, such as 250 g per jar.',
        'correction_reason' => 'Briefly explain the change. Previous values and this reason are retained in audit history.',
    ];

    if (!$editing) {
        $fields['project_id'] = [
            'label' => 'Project', 'type' => 'select', 'required' => true,
            'options' => $projectOptions, 'wide' => true,
        ];
    }

    if ($type !== 'materials') {
        $fields['year'] = [
            'label' => 'Year', 'type' => 'number', 'required' => true,
            'value' => $record?->year ?? now('Asia/Manila')->year,
            'min' => 1900, 'max' => 2100, 'step' => 1,
        ];

        $period = $type === 'production' ? 'quarter_id' : 'month';
        $periodOptions = $type === 'production'
            ? $quarters
            : collect(range(1, 12))->mapWithKeys(fn ($month) => [
                $month => date('F', mktime(0, 0, 0, $month, 1)),
            ]);

        $fields[$period] = [
            'label' => $type === 'production' ? 'Quarter' : 'Month',
            'type' => 'select', 'required' => true,
            'options' => $periodOptions,
        ];

        if ($editing) {
            $locked['year'] = (string) $record->year;
            $locked[$period] = $periodOptions[$record->{$period}] ?? 'Not recorded';
        }

        if ($type === 'production') {
            $fields['output_unit_code'] = [
                'label' => 'Production unit', 'type' => 'select', 'required' => true,
                'options' => \App\Services\MonitoringService::UNITS,
            ];
            $fields['output_unit_spec'] = [
                'label' => 'Package size / unit specification', 'type' => 'text',
                'maxlength' => 100,
            ];

            if ($editing && $record->output_unit_code !== null) {
                $locked['output_unit_code'] =
                    \App\Services\MonitoringService::UNITS[$record->output_unit_code]
                    ?? $record->output_unit_code;
                $locked['output_unit_spec'] =
                    $record->output_unit_spec ?: 'Not applicable';
            }

            foreach (['target_output' => 'Target Output', 'actual_output' => 'Actual Output'] as $name => $text) {
                $fields[$name] = [
                    'label' => $text, 'type' => 'number', 'required' => true,
                    'min' => 0, 'step' => '0.01',
                ];
            }
        } else {
            $fields['gross_income'] = [
                'label' => "Gross Income (\u{20B1})", 'type' => 'number',
                'required' => true, 'min' => 0, 'step' => '0.01',
            ];
        }
    } else {
        $fields['project_material_id'] = [
            'label' => 'Project material', 'type' => 'select', 'required' => true,
            'options' => $materials->pluck('item_name', 'id'),
        ];
        $fields['observed_on'] = [
            'label' => 'Observation / inspection date', 'type' => 'date',
            'required' => true, 'max' => now('Asia/Manila')->toDateString(),
        ];
        $fields['condition_status_id'] = [
            'label' => 'Condition', 'type' => 'select', 'required' => true,
            'options' => $conditions,
        ];
        $fields['material_description'] = [
            'label' => 'Material description', 'type' => 'text', 'maxlength' => 255,
        ];
        $fields['scheduled_maintenance'] = [
            'label' => 'Scheduled maintenance', 'type' => 'date',
        ];
        $fields['actual_maintenance'] = [
            'label' => 'Actual maintenance', 'type' => 'date',
            'max' => now('Asia/Manila')->toDateString(),
        ];

        if ($editing) {
            $locked['project_material_id'] = $record->item_name;
        }
    }

    $fields['remarks'] = [
        'label' => 'Remarks', 'type' => 'textarea', 'maxlength' => 2000, 'wide' => true,
    ];

    if ($editing) {
        $fields['correction_reason'] = [
            'label' => 'Reason for change', 'type' => 'textarea',
            'required' => true, 'maxlength' => 2000, 'wide' => true,
        ];
    }
@endphp

<x-dashboard-layout title="Monitoring Module">
<section data-monitoring-panel
         data-panel-title="{{ $editing ? 'Edit' : 'Add' }} {{ $label }} Record"
         class="am-monitoring-form-panel">
    <header>
        <h1>{{ $editing ? 'Edit' : 'Add' }} {{ $label }} Record</h1>
    </header>

    @include('shared.partials.feedback')

    @if($projects->isEmpty())
        <p>No active projects are available within your current access scope.</p>
    @else
        <form data-monitoring-form method="POST"
              action="{{ $editing
                  ? route('monitoring.update', [$type, $record->id])
                  : route('monitoring.store', $type) }}">
            @csrf
            @if($editing) @method('PUT') @endif

            <p class="am-monitoring-muted">* Required field</p>

            @if($editing)
                {{-- Ownership always comes from the authorized stored record. --}}
                <input type="hidden" name="project_id" value="{{ $record->project_id }}">
                <div class="am-monitoring-context">
                    <strong>Project</strong>
                    <p>{{ $record->project_title }} &mdash; {{ $record->association_name }}</p>
                </div>
            @endif

            @if($type === 'materials' && !$editing)
                <input type="hidden" name="submission_token"
                       value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
            @endif

            <div class="am-monitoring-form-grid">
                @foreach($fields as $name => $field)
                    @php
                        $saved = $field['value'] ?? ($record?->{$name} ?? '');
                        $value = array_key_exists($name, $locked)
                            ? ($record?->{$name} ?? '')
                            : old($name, $saved);
                        $value = is_scalar($value) ? $value : '';
                        $inputId = 'monitoring-'.$name;
                    @endphp

                    <div class="{{ !empty($field['wide']) ? 'is-wide' : '' }}">
                        <div class="am-monitoring-field-heading">
                            <label for="{{ $inputId }}" class="pm-label">
                                {{ $field['label'] }}
                                @if(!empty($field['required']) && !array_key_exists($name, $locked))
                                    <span aria-hidden="true">*</span>
                                @endif
                            </label>

                            @isset($fieldHelp[$name])
                                {{-- Show help on hover or keyboard focus without expanding the form. --}}
                                <span class="am-monitoring-help">
                                    <button
                                        type="button"
                                        class="am-monitoring-help__button"
                                        aria-label="Information about {{ strtolower($field['label']) }}"
                                        aria-describedby="{{ $inputId }}-help"
                                    >
                                        {{-- Heroicons: information-circle, outline. --}}
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m11.25 11.25.041-.02a.75.75 0 0 1
                                                    1.063.852l-.708 2.836a.75.75 0 0 0
                                                    1.063.852l.041-.02M21 12a9 9 0 1
                                                    1-18 0 9 9 0 0 1 18 0ZM12 8.25h.008v.008H12V8.25Z"/>
                                        </svg>
                                    </button>

                                    <span id="{{ $inputId }}-help"
                                        class="am-monitoring-help__tooltip"
                                        role="tooltip">
                                        {{ $fieldHelp[$name] }}
                                    </span>
                                </span>
                            @endisset
                        </div>

                        @if(array_key_exists($name, $locked))
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            <p id="{{ $inputId }}" class="am-monitoring-fixed">{{ $locked[$name] }}</p>
                        @elseif($field['type'] === 'select')
                            <select id="{{ $inputId }}" name="{{ $name }}" class="pm-input"
                                    @required($field['required'] ?? false)
                                    aria-describedby="{{ $inputId }}-error">
                                <option value="">Select {{ strtolower($field['label']) }}</option>
                                @foreach($field['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}"
                                        @selected((string) $value === (string) $optionValue)
                                        @if($name === 'project_material_id')
                                            data-project="{{ $materials->firstWhere('id', $optionValue)?->project_id }}"
                                        @endif>
                                        {{ $optionLabel }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif($field['type'] === 'textarea')
                            <textarea id="{{ $inputId }}" name="{{ $name }}" class="pm-input"
                                      rows="2" maxlength="{{ $field['maxlength'] }}"
                                      @required($field['required'] ?? false)
                                      aria-describedby="{{ $inputId }}-error">{{ $value }}</textarea>
                        @else
                            <input id="{{ $inputId }}" name="{{ $name }}"
                                   type="{{ $field['type'] }}" class="pm-input"
                                   value="{{ $value }}"
                                   @required($field['required'] ?? false)
                                   @isset($field['min']) min="{{ $field['min'] }}" @endisset
                                   @isset($field['max']) max="{{ $field['max'] }}" @endisset
                                   @isset($field['step']) step="{{ $field['step'] }}" @endisset
                                   @isset($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endisset
                                   aria-describedby="{{ $inputId }}-error">
                        @endif

                        <p id="{{ $inputId }}-error" data-monitoring-field-error="{{ $name }}"
                           class="am-monitoring-error">{{ $errors->first($name) }}</p>
                    </div>
                @endforeach
            </div>

            @if($type === 'production')
                <script type="application/json" data-project-unit-data>
                {!! json_encode(
                    $projects->mapWithKeys(fn ($project) => [$project->id => [
                        'code' => $project->production_unit_code,
                        'spec' => $project->production_unit_spec,
                    ]]),
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ) !!}
                </script>
            @endif

            <div class="am-monitoring-form-footer">
                <a data-monitoring-cancel class="fo-action"
                   href="{{ route('monitoring.index', ['type' => $type]) }}">Cancel</a>
                <button class="fo-action am-button-green" type="submit">
                    {{ $editing ? 'Save changes' : 'Save record' }}
                </button>
            </div>
        </form>
    @endif
</section>
</x-dashboard-layout>