{{-- The prefix makes IDs unique across material editors. recover limits old input and errors to the submitted form; other records keep saved values. --}}
@php
    $inputId = ($prefix ?? 'project').'-'.$name;
    $useOld = $recover ?? true;
    $fieldValue = $useOld ? old($name, $value ?? '') : ($value ?? '');
    $fieldError = $useOld ? $errors->first($name) : '';
    $fieldType = $type ?? 'text';
@endphp
<div class="min-w-0 {{ $span ?? '' }}">
    <label class="pm-label" for="{{ $inputId }}">{{ $label }} @if($required ?? false)<span aria-hidden="true">*</span>@endif</label>
    @if($fieldType === 'select')
        <select id="{{ $inputId }}" name="{{ $name }}" class="pm-input" @required($required ?? false) aria-describedby="{{ $inputId }}-help {{ $inputId }}-error" aria-invalid="{{ $fieldError ? 'true' : 'false' }}">
            <option value="">Select {{ strtolower($label) }}</option>
            @foreach($options as $optionValue=>$optionLabel)<option value="{{ $optionValue }}" @selected((string)$fieldValue === (string)$optionValue)>{{ $optionLabel }}</option>@endforeach
        </select>
    @elseif($fieldType === 'textarea')
        <textarea id="{{ $inputId }}" name="{{ $name }}" rows="3" class="pm-input" aria-describedby="{{ $inputId }}-help {{ $inputId }}-error" aria-invalid="{{ $fieldError ? 'true' : 'false' }}">{{ $fieldValue }}</textarea>
    @else
        <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $fieldType }}" value="{{ $fieldValue }}" class="pm-input"
               @required($required ?? false) @if(isset($min)) min="{{ $min }}" @endif @if(isset($step)) step="{{ $step }}" @endif @if(isset($maxLength)) maxlength="{{ $maxLength }}" @endif
               aria-describedby="{{ $inputId }}-help {{ $inputId }}-error" aria-invalid="{{ $fieldError ? 'true' : 'false' }}">
    @endif
    <p id="{{ $inputId }}-help" class="mt-1 text-xs text-slate-600">{{ $help ?? '' }}</p>
    <p id="{{ $inputId }}-error" data-pm-field-error class="mt-1 text-sm text-red-800" aria-live="polite">{{ $fieldError }}</p>
</div>
