{{-- A category describes the purpose of the training, not whether it has finished. --}}
@php
    $category = match ($training->stage) {
        'proposal' => 'Proposal preparation',
        'accepted' => 'After proposal acceptance',
        'terminated' => 'Project termination',
        default => 'Not specified',
    };
@endphp
<span class="am-training-category">{{ $category }}</span>
