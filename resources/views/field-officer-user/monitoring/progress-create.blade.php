<section data-monitoring-panel
         data-panel-title="Open production quarter"
         class="am-monitoring-form-panel">
    <form data-monitoring-form method="POST"
          action="{{ route('officer.production-progress.store') }}">
        @csrf

        {{-- Repeated submissions cannot create the same request twice. --}}
        <input type="hidden" name="submission_token"
               value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <div class="am-monitoring-form-grid">
            <div class="is-wide">
                <label for="progress-project">Project *</label>
                <select id="progress-project" name="project_id" class="pm-input" required>
                    <option value="">Select project</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">
                            {{ $project->title }} / {{ $project->association_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="progress-year">Year *</label>
                <input id="progress-year" class="pm-input" name="year"
                       type="number" min="1900" max="2100"
                       value="{{ now('Asia/Manila')->year }}" required>
            </div>

            <div>
                <label for="progress-quarter">Quarter *</label>
                <select id="progress-quarter" name="quarter_id" class="pm-input" required>
                    <option value="">Select quarter</option>
                    @foreach($quarters as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <div class="am-info-line">
                    <label for="progress-target">Quarterly target *</label>

                    <x-info label="About quarterly production output">
                        Set the target for this quarter. Actual output is calculated from
                        dated production entries and excludes voided entries.
                    </x-info>
                </div>
                <input id="progress-target" name="target_output" class="pm-input"
                       type="number" min="0" max="99999999.99" step="0.01" required>
            </div>

            <div>
                <label for="progress-unit">Production unit *</label>
                <select id="progress-unit" name="output_unit_code" class="pm-input" required>
                    <option value="">Select unit</option>
                    @foreach(\App\Services\MonitoringService::UNITS as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="is-wide">
                <label for="progress-spec">Package size / unit specification</label>
                <input id="progress-spec" name="output_unit_spec"
                       class="pm-input" maxlength="100">
            </div>

            <div class="is-wide">
                <label for="progress-remarks">Remarks</label>
                <textarea id="progress-remarks" name="remarks"
                          class="pm-input" rows="2" maxlength="2000"></textarea>
            </div>
        </div>

        <div class="am-monitoring-form-footer">
            <button type="button" data-monitoring-cancel class="fo-action">Cancel</button>
            <button type="submit" class="fo-action am-button-green">Open quarter</button>
        </div>
    </form>
</section>