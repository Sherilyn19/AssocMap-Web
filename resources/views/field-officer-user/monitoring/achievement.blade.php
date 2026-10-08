@php
    $target = (float) $record->target_output;
    $actual = (float) $record->actual_output;
    $percentage = $target > 0 ? ($actual / $target) * 100 : null;
    $level = $percentage === null ? 0 : min(100, max(0, $percentage));
    $tracked = $record->tracking_mode === 'entries';
    $unit = \App\Services\MonitoringService::UNITS[$record->output_unit_code ?? '']
        ?? 'Unit not recorded';
    $currentQuarter = now('Asia/Manila')->toDateString() <= $end->toDateString();
    $status = $percentage === null
        ? 'No percentage'
        : ($actual >= $target ? 'Target met' : 'Below target');
@endphp

<section data-monitoring-panel data-panel-title="Production achievement"
         class="am-achievement">
    <header class="am-achievement-heading">
        <div>
            <h3>{{ $record->project_title }}</h3>
            <p>{{ $record->association_name }}</p>
        </div>
        <span class="am-achievement-chip">
            {{ $record->quarter_name }} {{ $record->year }}
        </span>
    </header>

    <section class="am-achievement-summary" aria-label="Achievement and progress">
        {{-- The animation uses the actual percentage, capped visually at 100%. --}}
        <div class="am-water"
             data-water-progress="{{ $level }}"
             role="img"
             aria-label="Achievement: {{ $percentage === null ? 'not calculable' : number_format($percentage, 1).' percent' }}">
            <div class="am-water-fill"></div>
            <strong>{{ $percentage === null ? 'N/A' : number_format($percentage, 1).'%' }}</strong>
        </div>

        <div class="am-achievement-values">
            <div class="am-achievement-status">
                <strong>{{ $status }}</strong>
                <span>{{ $currentQuarter ? 'In progress' : 'Completed reporting period' }}</span>
            </div>

            <dl>
                <div><dt>Actual</dt><dd>{{ number_format($actual, 2) }}</dd></div>
                <div><dt>Target</dt><dd>{{ number_format($target, 2) }}</dd></div>
            </dl>

            <div class="am-info-line">
                <small>
                    {{ $unit }}{{ $record->output_unit_spec ? ' / '.$record->output_unit_spec : '' }}
                </small>

                <x-info label="About the achievement calculation">
                    Actual ÷ target × 100. Voided entries are excluded.
                    A zero target has no percentage. The water level stops at 100%,
                    while the displayed percentage can exceed 100%.
                    Recorded results are not independent verification.
                </x-info>
            </div>

            @if(!$tracked)
                <span class="am-achievement-chip">Historical summary</span>
            @endif
        </div>
    </section>

    <section aria-label="Quarterly timeline">
        <h4>Quarterly progress · {{ $record->year }}</h4>

        <div class="am-quarter-track">
            @foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $name)
                @php
                    $quarter = $quarters->get($name);
                    $quarterPercent = $quarter && (float) $quarter->target_output > 0
                        ? (float) $quarter->actual_output / (float) $quarter->target_output * 100
                        : null;
                @endphp

                <div class="am-quarter {{ $name === $record->quarter_name ? 'is-selected' : '' }}">
                    @if($quarter)
                        <a data-monitoring-open="achievement"
                           href="{{ route('officer.monitoring.details', ['production', $quarter->id]) }}">
                            <strong>{{ $name }}</strong>
                            <span>{{ $quarterPercent === null ? 'N/A' : number_format($quarterPercent, 1).'%' }}</span>
                        </a>

                        <div class="am-quarter-bar" aria-hidden="true">
                            <span style="width: {{ min(100, max(0, $quarterPercent ?? 0)) }}%"></span>
                        </div>

                        <small>{{ number_format($quarter->actual_output, 2) }} / {{ number_format($quarter->target_output, 2) }}</small>
                    @else
                        <strong>{{ $name }}</strong>
                        <small>Not recorded</small>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <div class="am-info-line">
            <h4>Dated production timeline</h4>

            <x-info label="About dated production entries">
                Each entry records additional output on its production date.
                Historical summaries have no dated breakdown until explicitly reconciled.
                Editing a record does not create a new production milestone.
            </x-info>
        </div>

        @if(!$tracked)
            <p class="am-monitoring-muted">No dated breakdown recorded.</p>
        @else
            @if(!$readOnly)
                <details class="am-progress-disclosure">
                    <summary>＋ Add production entry</summary>
                    @include('field-officer-user.monitoring.progress-entry-form', ['entry' => null])
                </details>
            @endif

            <ol class="am-production-timeline">
                @forelse($entries as $entry)
                    <li class="{{ $entry->voided_at ? 'is-voided' : '' }}">
                        <div class="am-production-event-heading">
                            <time datetime="{{ $entry->produced_on }}">{{ $entry->produced_on }}</time>
                            <strong>{{ $entry->voided_at ? 'Voided' : '+'.number_format($entry->quantity, 2) }}</strong>
                        </div>

                        <p>{{ $entry->description }}</p>
                        <small>Recorded by {{ $entry->author_name ?? 'Unavailable' }}</small>

                        @if($entry->remarks)
                            <details class="am-progress-disclosure">
                                <summary>Supporting remarks</summary>
                                <p>{{ $entry->remarks }}</p>
                            </details>
                        @endif

                        @if(!$readOnly && !$entry->voided_at)
                            <details class="am-progress-disclosure">
                                <summary>Edit entry</summary>
                                @include('field-officer-user.monitoring.progress-entry-form', ['entry' => $entry])
                            </details>

                            <details class="am-progress-disclosure">
                                <summary>Void entry</summary>
                                <form data-monitoring-form method="POST"
                                      action="{{ route('officer.production-progress.update', $record->id) }}">
                                    @csrf
                                    <input type="hidden" name="operation" value="entry_void">
                                    <input type="hidden" name="revision" value="{{ $record->revision }}">
                                    <input type="hidden" name="entry_id" value="{{ $entry->id }}">

                                    <div class="am-monitoring-form-grid">
                                        <div class="is-wide">
                                            <label for="void-reason-{{ $entry->id }}">Reason for voiding *</label>
                                            <textarea id="void-reason-{{ $entry->id }}"
                                                      class="pm-input" name="correction_reason"
                                                      rows="2" maxlength="2000" required></textarea>
                                        </div>
                                    </div>

                                    <button type="submit" class="fo-action am-button-warning">
                                        Void entry
                                    </button>
                                </form>
                            </details>
                        @endif
                    </li>
                @empty
                    <li>No production entries recorded.</li>
                @endforelse
            </ol>

            @if($entries->hasPages())
                <nav class="am-monitoring-actions" aria-label="Production entry pages">
                    @if($entries->previousPageUrl())
                        <a class="fo-action" data-monitoring-open="achievement"
                           href="{{ $entries->previousPageUrl() }}">Previous</a>
                    @endif
                    <small>Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</small>
                    @if($entries->nextPageUrl())
                        <a class="fo-action" data-monitoring-open="achievement"
                           href="{{ $entries->nextPageUrl() }}">Next</a>
                    @endif
                </nav>
            @endif
        @endif
    </section>

    @if($tracked && !$readOnly)
        <details class="am-progress-disclosure">
            <summary>Edit quarterly target</summary>

            <form data-monitoring-form method="POST"
                  action="{{ route('officer.production-progress.update', $record->id) }}">
                @csrf
                <input type="hidden" name="operation" value="target">
                <input type="hidden" name="revision" value="{{ $record->revision }}">

                <div class="am-monitoring-form-grid">
                    <div>
                        <label for="quarter-target">Target *</label>
                        <input id="quarter-target" class="pm-input" name="target_output"
                               type="number" min="0" max="99999999.99" step="0.01"
                               value="{{ $record->target_output }}" required>
                    </div>
                    <div class="is-wide">
                        <label for="quarter-reason">Reason for change *</label>
                        <textarea id="quarter-reason" class="pm-input"
                                  name="correction_reason" rows="2"
                                  maxlength="2000" required></textarea>
                    </div>
                </div>

                <button type="submit" class="fo-action am-button-warning">Save target</button>
            </form>
        </details>
    @endif

    <details class="am-progress-disclosure" @if(request('audit_page')) open @endif>
        <summary>View change history</summary>

        <ol class="am-production-timeline">
            @forelse($audit as $log)
                @php
                    $change = json_decode($log->details ?? '', true) ?: [];
                    $before = $change['entry_before'] ?? $change['before'] ?? [];
                    $after = $change['entry_after'] ?? $change['after'] ?? [];
                    $labels = [
                        'produced_on' => 'Production date',
                        'quantity' => 'Quantity',
                        'description' => 'Description',
                        'remarks' => 'Remarks',
                        'target_output' => 'Target',
                        'actual_output' => 'Actual',
                        'voided_at' => 'Voided at',
                    ];
                @endphp
                <li>
                    <strong>{{ $change['event'] ?? $log->action_type }}</strong>
                    <p><small>{{ $log->performed_at }} · {{ $log->actor_name ?? 'Unavailable' }}</small></p>

                    @if($change['correction_reason'] ?? null)
                        <p>{{ $change['correction_reason'] }}</p>
                    @endif

                    <dl class="am-audit-values">
                        @foreach($labels as $key => $label)
                            @if(array_key_exists($key, $after) && ($before[$key] ?? null) !== $after[$key])
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $before[$key] ?? '—' }} → {{ $after[$key] ?? '—' }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </li>
            @empty
                <li>No progress-workflow changes recorded.</li>
            @endforelse
        </ol>

        @if($audit->hasPages())
            <nav class="am-monitoring-actions" aria-label="Change history pages">
                @if($audit->previousPageUrl())
                    <a class="fo-action" data-monitoring-open="achievement"
                       href="{{ $audit->previousPageUrl() }}">Previous</a>
                @endif
                <small>Page {{ $audit->currentPage() }} of {{ $audit->lastPage() }}</small>
                @if($audit->nextPageUrl())
                    <a class="fo-action" data-monitoring-open="achievement"
                       href="{{ $audit->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif

        @if($legacyAudit->isNotEmpty())
            <h4>Recent earlier monitoring changes</h4>
            @foreach($legacyAudit as $log)
                @php($change = json_decode($log->details ?? '', true) ?: [])
                <p>
                    <strong>{{ $log->action_type }}</strong>
                    · {{ $log->performed_at }}
                    · {{ $log->actor_name ?? 'Unavailable' }}
                </p>
                @if($change['correction_reason'] ?? null)
                    <p class="am-monitoring-muted">{{ $change['correction_reason'] }}</p>
                @endif
            @endforeach
        @endif
    </details>
</section>