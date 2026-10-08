@php
    $editingEntry = isset($entry) && $entry !== null;
    $suffix = $editingEntry ? 'entry-'.$entry->id : 'entry-new';
@endphp

<form data-monitoring-form method="POST"
      action="{{ route('officer.production-progress.update', $record->id) }}">
    @csrf

    {{-- The revision prevents an older open form from overwriting newer work. --}}
    <input type="hidden" name="revision" value="{{ $record->revision }}">
    <input type="hidden" name="operation"
           value="{{ $editingEntry ? 'entry_edit' : 'entry_add' }}">

    @if($editingEntry)
        <input type="hidden" name="entry_id" value="{{ $entry->id }}">
    @endif

    <div class="am-monitoring-form-grid">
        <div>
            <label for="{{ $suffix }}-date">Production date *</label>
            <input id="{{ $suffix }}-date" class="pm-input"
                   name="produced_on" type="date"
                   min="{{ $start->toDateString() }}"
                   max="{{ min($end->toDateString(), now('Asia/Manila')->toDateString()) }}"
                   value="{{ $editingEntry ? $entry->produced_on : '' }}" required>
        </div>

        <div>
            <label for="{{ $suffix }}-quantity">Additional output *</label>
            <input id="{{ $suffix }}-quantity" class="pm-input"
                   name="quantity" type="number" min="0.01"
                   max="99999999.99" step="0.01"
                   value="{{ $editingEntry ? $entry->quantity : '' }}" required>
        </div>

        <div class="is-wide">
            <label for="{{ $suffix }}-description">Activity / output description *</label>
            <input id="{{ $suffix }}-description" name="description"
                   class="pm-input" maxlength="255"
                   value="{{ $editingEntry ? $entry->description : '' }}" required>
        </div>

        <div class="is-wide">
            <label for="{{ $suffix }}-remarks">Supporting remarks</label>
            <textarea id="{{ $suffix }}-remarks" class="pm-input"
                      name="remarks" rows="2" maxlength="2000">{{ $editingEntry ? $entry->remarks : '' }}</textarea>
        </div>

        @if($editingEntry)
            <div class="is-wide">
                <label for="{{ $suffix }}-reason">Reason for change *</label>
                <textarea id="{{ $suffix }}-reason" class="pm-input"
                          name="correction_reason" rows="2"
                          maxlength="2000" required></textarea>
            </div>
        @endif
    </div>

    <button type="submit" class="fo-action am-button-green">
        {{ $editingEntry ? 'Save changes' : 'Save entry' }}
    </button>
</form>